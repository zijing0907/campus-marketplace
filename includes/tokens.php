<?php
// Links sent by email that prove "the person clicking this can read that inbox",
// such as a password reset link (FR-06).
//
// How such a link works:
//   1. The site makes up a long random secret, the "token", and emails a link
//      that contains it:  /pages/reset-password.php?token=3f9a...
//   2. The site stores only a HASH of the token, in the user_tokens table.
//   3. When the link is opened, the site hashes the token from the address and
//      looks that hash up. Only the real link produces a hash that is on file.
//
// Storing the hash, not the token, follows the same idea as with passwords: if
// someone ever got a copy of the database, they still could not build a link.
// A fast hash (SHA-256) is enough here, because the token is 64 random characters
// and cannot be guessed, unlike a password a person chose.

// What a link is for. The same token must never work for two different things.
const TOKEN_PASSWORD_RESET = 'password_reset';
const TOKEN_EMAIL_VERIFY = 'email_verify';

// How long a password reset link works, in minutes.
const PASSWORD_RESET_MINUTES = 60;

// How long a "confirm your email" link works, in minutes: one day. Longer than a
// reset link, because a new student may not check their inbox straight away.
const EMAIL_VERIFY_MINUTES = 60 * 24;

// A new link is not emailed if the last one is younger than this, in seconds.
// It stops someone from filling another person's inbox by pressing the button
// again and again.
const TOKEN_RESEND_WAIT = 120;

/**
 * Make a new link secret for this user and return it. It is returned only here,
 * to be put into the email; afterwards not even the site knows it.
 *
 * Any earlier link of the same kind stops working, so only the newest one counts.
 */
function create_user_token(int $userId, string $purpose, int $minutes): string
{
    $stmt = db()->prepare('DELETE FROM user_tokens WHERE user_id = ? AND purpose = ?');
    $stmt->execute([$userId, $purpose]);

    // random_bytes() is made for secrets: its output cannot be predicted.
    // bin2hex() writes the 32 bytes as 64 letters and digits that fit in a link.
    $token = bin2hex(random_bytes(32));

    // The database works out the expiry time itself, and later compares it with
    // its own clock (NOW()), so PHP's clock and time zone never come into it.
    $stmt = db()->prepare(
        'INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    $stmt->execute([$userId, $purpose, hash('sha256', $token), $minutes]);

    return $token;
}

/**
 * Was a link of this kind made for this user in the last TOKEN_RESEND_WAIT seconds?
 */
function user_token_is_recent(int $userId, string $purpose): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM user_tokens
         WHERE user_id = ? AND purpose = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$userId, $purpose, TOKEN_RESEND_WAIT]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Look up the token from a link. Returns its row (id and user_id), or null if
 * the link is unknown, was made for something else, was already used, or is too old.
 */
function find_user_token(string $token, string $purpose): ?array
{
    // A real token is exactly 64 letters a-f and digits. Anything else cannot be
    // one, so there is no need to ask the database.
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, user_id
         FROM user_tokens
         WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $token), $purpose]);

    return $stmt->fetch() ?: null;
}

/**
 * Mark a link as used, so that it never works again.
 *
 * Returns false if it had already been used. The "AND used_at IS NULL" makes the
 * database decide that in one step: if the same link is submitted twice at the
 * same moment, only one of the two requests gets true.
 */
function use_user_token(int $tokenId): bool
{
    $stmt = db()->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
    $stmt->execute([$tokenId]);

    return $stmt->rowCount() === 1;
}

/**
 * Email a new student the link that confirms their address (FR-05).
 * $user needs id, name and email. Any earlier confirmation link stops working.
 */
function send_verification_email(array $user): bool
{
    $token = create_user_token((int) $user['id'], TOKEN_EMAIL_VERIFY, EMAIL_VERIFY_MINUTES);

    return send_email(
        $user['email'],
        'Confirm your Campus Marketplace account',
        "Hello {$user['name']},\n\n"
        . "Welcome to Campus Marketplace! To activate your account, open this link within 24 hours:\n\n"
        . site_url('/pages/verify-email.php?token=' . $token) . "\n\n"
        . "If you did not create an account, ignore this email and nothing will happen.\n"
    );
}
