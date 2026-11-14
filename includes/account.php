<?php
// Account security for a logged-in student: checking their current password, and
// deleting their account.

/**
 * Check the current password typed into "Change password" or "Delete account".
 * Returns an error message, or null if it is right.
 *
 * Asking for the password again, although the student is already logged in, makes
 * sure the person at the keyboard really is them, and not someone who found the
 * laptop unlocked. Wrong guesses count towards the same limit as on the login page.
 */
function check_current_password(array $user, string $password): ?string
{
    $wait = login_wait_minutes($user['email']);
    if ($wait > 0) {
        return login_wait_message($wait);
    }

    // current_user() leaves the hash out of $user on purpose, so read it here.
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);

    if (!password_verify($password, (string) $stmt->fetchColumn())) {
        record_failed_login($user['email']);

        $left = login_tries_left($user['email']);
        if ($left === 0) {
            return login_wait_message(LOGIN_LOCK_MINUTES);
        }

        return 'That is not your current password.'
            . ($left <= 2 ? ' ' . $left . ' more ' . ($left === 1 ? 'try' : 'tries') . ' before a ' . LOGIN_LOCK_MINUTES . '-minute pause.' : '');
    }

    clear_failed_logins($user['email']);

    return null;
}

/**
 * Is this the only admin left? The site would then have nobody to handle reports.
 */
function is_last_admin(int $userId): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND deleted_at IS NULL AND id <> ?");
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn() === 0;
}

/**
 * Delete a student's account.
 *
 * What it removes: everything that is only theirs. Their name, email, phone,
 * faculty and profile photo; their password (replaced by one nobody knows); their
 * saved listings; and every listing of theirs that no buyer has rated, with its
 * photos and its conversations, exactly as if they had deleted each one themselves.
 *
 * What it keeps: the things they share with other students. A conversation they
 * had as a buyer, a rating they gave a seller, a sale that was rated, a report
 * they filed. Deleting those would take away other students' messages and ratings.
 * They stay, but now say "Deleted user" instead of a name.
 *
 * The account row itself stays too, emptied, because those shared things point to
 * it. It is marked as banned, so everything that already hides a banned student
 * (their profile, their listings, logging in) hides this one as well, and
 * deleted_at records that it was deleted rather than banned.
 */
function delete_account(int $userId): void
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT email, avatar_path FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $account = $stmt->fetch();

    // Their listings that no buyer has rated. A rated one is kept, for the same
    // reason a seller cannot delete it themselves (see listing-delete.php).
    $stmt = $pdo->prepare(
        'SELECT l.id FROM listings l
         WHERE l.seller_id = ? AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.listing_id = l.id)'
    );
    $stmt->execute([$userId]);
    $listingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Note the photo files now: the rows that name them are about to go.
    $files = [];
    if ($listingIds) {
        // One "?" per listing, for example "?,?,?" for three listings.
        $marks = implode(',', array_fill(0, count($listingIds), '?'));
        $stmt = $pdo->prepare("SELECT file_path FROM listing_images WHERE listing_id IN ($marks)");
        $stmt->execute($listingIds);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($account['avatar_path'] !== null) {
        $files[] = $account['avatar_path'];
    }

    // All the database changes happen together or not at all. If anything fails
    // half-way, the account is left exactly as it was.
    $pdo->beginTransaction();
    try {
        if ($listingIds) {
            // ON DELETE CASCADE also removes their photos rows, conversations,
            // messages, saves and reports.
            $stmt = $pdo->prepare("DELETE FROM listings WHERE id IN ($marks)");
            $stmt->execute($listingIds);
        }

        foreach (['wishlists', 'user_tokens', 'notifications'] as $table) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE user_id = ?");
            $stmt->execute([$userId]);
        }

        // Rows that hold the email address itself.
        foreach (['login_attempts' => 'email', 'outbox' => 'to_email'] as $table => $column) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE $column = ?");
            $stmt->execute([$account['email']]);
        }

        // Empty the account. The new email ends in ".invalid", a name that can
        // never receive mail. It is unique (it contains the id) and it frees the
        // real address, so the student could sign up again one day.
        $stmt = $pdo->prepare(
            "UPDATE users
             SET name = 'Deleted user',
                 email = CONCAT('deleted-', id, '@deleted.invalid'),
                 password_hash = ?,
                 faculty = NULL, phone = NULL, avatar_path = NULL,
                 role = 'student', is_verified = 0, is_banned = 1,
                 deleted_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $userId]);

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    // Files are removed only once the database has really changed.
    foreach ($files as $path) {
        delete_uploaded_file($path);
    }
}
