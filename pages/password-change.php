<?php
// Change my password, while logged in.
//
// The current password is asked for again (see check_current_password()). After
// the change, every other browser logged in to this account is logged out, and
// the student gets an email saying the password changed.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current = post_text('current_password', trim: false);
    $password = post_text('password', trim: false);
    $passwordConfirm = post_text('password_confirm', trim: false);

    $currentError = check_current_password($user, $current);

    if ($currentError !== null) {
        $errors['current_password'] = $currentError;
    } else {
        $errors = validate_new_password($password, $passwordConfirm);

        if (!$errors && $password === $current) {
            $errors['password'] = 'Choose a password that is different from your current one.';
        }
    }

    if (!$errors) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $user['id']]);

        // A new hash means every older login of this account ends on its next
        // click (see password_mark() in includes/auth.php). Logging this browser
        // in again, with the new hash, keeps the student here logged in.
        login_user((int) $user['id'], $newHash);

        // If someone else changed it, this email is how the real owner finds out,
        // and the link in it lets them take the account back.
        send_email(
            $user['email'],
            'Your Campus Marketplace password was changed',
            "Hello {$user['name']},\n\n"
            . "The password of your Campus Marketplace account was just changed.\n\n"
            . "If this was you, there is nothing to do.\n"
            . "If it was not you, reset your password straight away:\n\n"
            . site_url('/pages/forgot-password.php') . "\n"
        );

        set_flash('success', 'Your password has been changed. Any other device that was logged in has been logged out.');
        redirect('/pages/profile.php');
    }
}

$pageTitle = 'Change password';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h1>Change password</h1>

    <form method="post" action="/pages/password-change.php">
        <?= csrf_field() ?>

        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password"
               required autocomplete="current-password">
        <?= field_error($errors, 'current_password') ?>

        <label for="password">New password</label>
        <input type="password" id="password" name="password"
               required minlength="8" autocomplete="new-password">
        <p class="hint">At least 8 characters.</p>
        <?= field_error($errors, 'password') ?>

        <label for="password_confirm">Confirm new password</label>
        <input type="password" id="password_confirm" name="password_confirm"
               required minlength="8" autocomplete="new-password">
        <?= field_error($errors, 'password_confirm') ?>

        <button type="submit" class="button">Change password</button>
    </form>

    <p class="form-footer"><a href="/pages/profile.php">Back to my account</a></p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
