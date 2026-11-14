<?php
// A limit on wrong passwords.
//
// Without it, a program could try thousands of passwords for one account, one
// after another, until one works (a "brute-force" attack). With it, after
// LOGIN_MAX_FAILURES wrong passwords for the same email address in
// LOGIN_LOCK_MINUTES minutes, that address must wait before it may try again.
//
// The same limit protects every form that asks for the current password: the
// login page, "Change password" and "Delete account". Otherwise someone using a
// student's unlocked laptop could guess the password through one of those instead.
//
// Every wrong password is written to the login_attempts table. Counting rows in a
// time window ("how many in the last 15 minutes?") gives the limit; rows older
// than a day are cleared away.

// Wrong passwords allowed for one email address within LOGIN_LOCK_MINUTES.
const LOGIN_MAX_FAILURES = 5;

// Wrong passwords allowed from one IP address, for any email, in the same time.
// It stops one computer from trying a few passwords on every account in turn.
// It is much higher than the per-address limit on purpose: a whole campus Wi-Fi
// network can share a single IP address, and one person's typos must not lock
// out everyone else on it.
const LOGIN_MAX_FAILURES_PER_IP = 50;

// How long the window, and so the pause, lasts.
const LOGIN_LOCK_MINUTES = 15;

/**
 * The visitor's IP address, as the web server saw it.
 *
 * Headers such as "X-Forwarded-For" are deliberately ignored: the visitor writes
 * those, so an attacker could send a different made-up address with every attempt
 * and never be counted.
 */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

/**
 * How many minutes this email address (or this computer) must wait before it may
 * try a password again. 0 means it may try now.
 */
function login_wait_minutes(string $email): int
{
    $wait = 0;

    // The column names come from this fixed list, never from the visitor, so
    // putting them into the SQL text is safe.
    $limits = [
        ['email', $email, LOGIN_MAX_FAILURES],
        ['ip', client_ip(), LOGIN_MAX_FAILURES_PER_IP],
    ];

    foreach ($limits as [$column, $value, $max]) {
        // Find the failure that used up the last allowed try: with a limit of 5,
        // the 5th most recent one inside the window. If there is one, the limit is
        // reached, and the pause ends when that failure is LOGIN_LOCK_MINUTES old.
        $stmt = db()->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), attempted_at + INTERVAL ? MINUTE)
             FROM login_attempts
             WHERE $column = ? AND attempted_at > NOW() - INTERVAL ? MINUTE
             ORDER BY attempted_at DESC, id DESC
             LIMIT 1 OFFSET ?"
        );
        $stmt->bindValue(1, LOGIN_LOCK_MINUTES, PDO::PARAM_INT);
        $stmt->bindValue(2, $value);
        $stmt->bindValue(3, LOGIN_LOCK_MINUTES, PDO::PARAM_INT);
        $stmt->bindValue(4, $max - 1, PDO::PARAM_INT);
        $stmt->execute();

        $seconds = $stmt->fetchColumn();
        if ($seconds !== false && (int) $seconds > 0) {
            // Rounded up, so "1 minute" never means "a few seconds too early".
            $wait = max($wait, (int) ceil((int) $seconds / 60));
        }
    }

    return $wait;
}

/**
 * How many more wrong passwords this email address may enter before the pause.
 */
function login_tries_left(string $email): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > NOW() - INTERVAL ? MINUTE'
    );
    $stmt->bindValue(1, $email);
    $stmt->bindValue(2, LOGIN_LOCK_MINUTES, PDO::PARAM_INT);
    $stmt->execute();

    return max(0, LOGIN_MAX_FAILURES - (int) $stmt->fetchColumn());
}

/**
 * Write down one wrong password for this email address, from this computer.
 * It is stored whether or not the address has an account, so the limit behaves
 * the same either way and gives away nothing about who has signed up.
 */
function record_failed_login(string $email): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)');
    $stmt->execute([$email, client_ip()]);

    // Old rows are no use to anyone; keep the table small.
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
}

/**
 * Forget the wrong passwords for this address, after the right one was entered.
 */
function clear_failed_logins(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE email = ?');
    $stmt->execute([$email]);
}

/**
 * The message shown while an address has to wait.
 */
function login_wait_message(int $minutes): string
{
    return 'Too many wrong passwords. For your safety, wait ' . $minutes . ' '
        . ($minutes === 1 ? 'minute' : 'minutes')
        . ' and try again, or reset your password.';
}

/**
 * The message after a wrong password. When only a few tries are left, it says so,
 * so that a student who simply forgot knows to reset it before the pause starts.
 */
function wrong_password_message(string $email): string
{
    $left = login_tries_left($email);

    if ($left === 0) {
        return login_wait_message(LOGIN_LOCK_MINUTES);
    }

    if ($left <= 2) {
        return 'Incorrect email or password. ' . $left . ' more ' . ($left === 1 ? 'try' : 'tries')
            . ' before a ' . LOGIN_LOCK_MINUTES . '-minute pause.';
    }

    return 'Incorrect email or password.';
}
