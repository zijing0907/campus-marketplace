<?php
// Admin: ban or unban a user (FR-24). The buttons on the users page post here.
//
// A ban sets users.is_banned to 1. The rest of the site already acts on that flag:
//   - login refuses a banned account (pages/login.php)
//   - a banned user who is logged in is logged out on their next request
//     (current_user() in includes/auth.php)
//   - their listings disappear for other students (includes/listings.php, pages/listing.php)
// Unbanning sets the flag back to 0, and all of that is undone by itself.
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/admin/users.php');
}
verify_csrf();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = post_text('action');

// Go back to the same search the admin was looking at.
$q = mb_substr(post_text('q'), 0, 100);
$backUrl = '/pages/admin/users.php' . ($q !== '' ? '?' . http_build_query(['q' => $q]) : '');

$target = null;
if (is_int($userId) && $userId > 0) {
    $stmt = db()->prepare('SELECT id, name, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $target = $stmt->fetch() ?: null;
}

if ($target === null) {
    set_flash('error', 'That user does not exist.');
    redirect($backUrl);
}

// Two safety rules, checked on the server because the buttons can be bypassed:
// an admin cannot ban themselves, and admins cannot ban each other.
if ((int) $target['id'] === (int) $admin['id']) {
    set_flash('error', 'You cannot ban yourself.');
    redirect($backUrl);
}
if ($target['role'] === 'admin') {
    set_flash('error', 'An admin cannot be banned.');
    redirect($backUrl);
}

if ($action === 'ban') {
    $stmt = db()->prepare('UPDATE users SET is_banned = 1 WHERE id = ?');
    $stmt->execute([$target['id']]);
    set_flash('success', $target['name'] . ' is banned. Their listings are hidden from other students.');
} elseif ($action === 'unban') {
    $stmt = db()->prepare('UPDATE users SET is_banned = 0 WHERE id = ?');
    $stmt->execute([$target['id']]);
    set_flash('success', $target['name'] . ' can log in again.');
} else {
    set_flash('error', 'Unknown action.');
}

redirect($backUrl);
