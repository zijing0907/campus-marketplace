<?php
// Sessions and login state.
//
// How a session works: the first time a browser visits, PHP creates a long random
// session ID and sends it to the browser in a cookie. On every later request the
// browser sends that cookie back, and PHP loads the $_SESSION array it saved for
// that ID. "Logged in" simply means $_SESSION['user_id'] holds a user's id.

// How long a login lasts if the user never logs out: 30 days, in seconds (FR-02).
const SESSION_LIFETIME = 60 * 60 * 24 * 30;

/**
 * Start the session with safe cookie settings. Called once per request, in bootstrap.php.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    // Keep the session data on the server for as long as the cookie lives.
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    // Only accept session IDs that this server created itself.
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttps,  // send over HTTPS only, once the site has HTTPS
        'httponly' => true,      // JavaScript cannot read the cookie
        'samesite' => 'Lax',     // not sent with form posts coming from other sites
    ]);
    session_name('cm_session');
    session_start();
}

/**
 * Return the logged-in user's row, or null for a guest.
 *
 * The row is read from the database on every request instead of being copied into
 * the session at login. That way a ban takes effect on the user's very next click,
 * not the next time they log in (FR-24).
 */
function current_user(): ?array
{
    // "static" variables keep their value between calls, so the query below runs
    // only once per request however many times this function is called.
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, password_hash, faculty, phone, avatar_path, role, is_verified, is_banned, created_at
         FROM users
         WHERE id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        // The account no longer exists.
        logout_user();
        return null;
    }

    // The hash is needed only for the check below. It is taken out of the row so
    // that no page can show it by accident.
    $passwordMark = password_mark($row['password_hash']);
    unset($row['password_hash']);

    // A login from before this check existed has no mark yet: give it one.
    $_SESSION['password_mark'] ??= $passwordMark;

    if ($row['is_banned']) {
        logout_user();
        set_flash('error', 'Your account has been suspended.');
    } elseif (!hash_equals($_SESSION['password_mark'], $passwordMark)) {
        // The password was changed after this browser logged in, for example with
        // a "reset your password" link. Whoever is using this session must log in
        // again. This is what throws out someone who had stolen the old password.
        logout_user();
        set_flash('info', 'Your password was changed. Please log in again.');
    } else {
        $user = $row;
    }

    return $user;
}

/**
 * A short mark that stands for "the password this account has right now".
 *
 * login_user() saves it in the session, and current_user() compares it on every
 * request. When the password changes, the stored hash changes, so the mark no
 * longer matches and every older login ends. It is a hash of the hash, so the
 * session never holds anything a password could be worked out from.
 */
function password_mark(string $passwordHash): string
{
    return hash('sha256', $passwordHash);
}

/**
 * Mark this browser's session as logged in. Call only after the password is verified.
 * $passwordHash is the hash stored for the user; see password_mark() for why.
 */
function login_user(int $userId, string $passwordHash): void
{
    // Give the session a brand-new ID at login. If an attacker somehow planted a
    // session ID in the victim's browser beforehand, it stops working here
    // (this attack is called session fixation).
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['password_mark'] = password_mark($passwordHash);

    // A fresh CSRF token will be created for the logged-in session.
    unset($_SESSION['csrf_token']);
}

/**
 * Log out: forget everything stored for this user and switch to a new, empty session.
 */
function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);  // true = delete the old session's data on the server
}

/**
 * Put this at the top of any page that only logged-in students may open (NFR-04).
 * Returns the user's row, so a page can write: $user = require_login();
 */
function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        // Keep a message that is already waiting, such as "Your account has been
        // suspended", instead of replacing it with the general one.
        if (!isset($_SESSION['flash'])) {
            set_flash('info', 'Please log in to continue.');
        }
        redirect('/pages/login.php');
    }

    return $user;
}

/**
 * Put this at the top of any page that only admins may open (NFR-04).
 *
 * require_login() asks "who are you?". This asks the next question: "are you
 * allowed here?". A guest is sent to the login page; a logged-in student who is
 * not an admin gets the "access denied" page with status 403.
 */
function require_admin(): array
{
    $user = require_login();

    if ($user['role'] !== 'admin') {
        $forbiddenMessage = 'This page is for admins only.';
        require __DIR__ . '/forbidden.php';   // prints the page and stops the script
    }

    return $user;
}

/**
 * Put this at the top of pages that make no sense once logged in (login, register).
 */
function require_guest(): void
{
    if (current_user() !== null) {
        redirect('/');
    }
}
