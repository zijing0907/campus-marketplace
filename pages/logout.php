<?php
// Log out (FR-02).
// This accepts POST only and checks the CSRF token. If a plain link could log
// people out, any other website could do it to them with a hidden image tag.
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}
verify_csrf();

logout_user();
set_flash('success', 'You have been logged out.');
redirect('/');
