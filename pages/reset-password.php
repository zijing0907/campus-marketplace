<?php
// Forgot your password? Step 2 of 2: choose a new password (FR-06).
// This page is opened from the link that forgot-password.php emailed:
//   /pages/reset-password.php?token=3f9a...
//
// Holding a working link is the proof of identity here: it shows that the visitor
// can read the account's inbox. includes/tokens.php explains how the link is checked.
require __DIR__ . '/../includes/bootstrap.php';

// The address of this page contains the secret. This header tells the browser not
// to pass that address on to any other site the visitor goes to from here.
header('Referrer-Policy: no-referrer');

$posting = $_SERVER['REQUEST_METHOD'] === 'POST';

// On the first visit the token is in the address; the form then sends it back.
$token = $posting ? post_text('token') : get_text('token');
$tokenRow = find_user_token($token, TOKEN_PASSWORD_RESET);

$errors = [];

if ($posting && $tokenRow !== null) {
    verify_csrf();

    $password = post_text('password', trim: false);
    $passwordConfirm = post_text('password_confirm', trim: false);

    $errors = validate_new_password($password, $passwordConfirm);

    if (!$errors) {
        // Both changes happen together or not at all. use_user_token() goes first:
        // it returns false if the link was used a moment ago by another request,
        // and then the password is left alone.
        db()->beginTransaction();

        if (use_user_token((int) $tokenRow['id'])) {
            // Opening a link that was emailed to the account also proves the
            // student can read that inbox, so the address counts as confirmed too.
            $stmt = db()->prepare('UPDATE users SET password_hash = ?, is_verified = 1 WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $tokenRow['user_id']]);
            db()->commit();

            // Anyone still logged in with the old password is logged out by this
            // change: see password_mark() in includes/auth.php. That includes this
            // browser, if it happened to be logged in.
            logout_user();
            set_flash('success', 'Your password has been changed. Log in with the new one.');
            redirect('/pages/login.php');
        }

        db()->rollBack();
        $tokenRow = null;
    }
}

$pageTitle = 'Choose a new password';
$bodyClass = 'auth-page';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/auth-start.php';
?>

<?php if ($tokenRow === null): ?>
    <?php // One message for every reason: unknown, already used, or too old. ?>
    <div class="auth-message">
        <span class="auth-message-icon auth-message-icon-warning"><?= icon('clock', 28) ?></span>
        <h1>This link no longer works</h1>
        <p>A reset link works once, and only for <?= PASSWORD_RESET_MINUTES ?> minutes. Ask for a new one and use it straight away.</p>
        <a class="button button-gradient" href="/pages/forgot-password.php"><?= icon('send') ?> Send a new link</a>
    </div>
<?php else: ?>
    <h1>Choose a new password</h1>
    <p class="note">Every device that is logged in with the old password will be logged out.</p>

    <form method="post" action="/pages/reset-password.php">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <label for="password">New password</label>
        <input type="password" id="password" name="password"
               required minlength="8" autocomplete="new-password" data-password-toggle>
        <p class="hint">At least 8 characters.</p>
        <?= field_error($errors, 'password') ?>

        <label for="password_confirm">Confirm new password</label>
        <input type="password" id="password_confirm" name="password_confirm"
               required minlength="8" autocomplete="new-password" data-password-toggle>
        <?= field_error($errors, 'password_confirm') ?>

        <button type="submit" class="button button-gradient" data-loading-text="Saving…"><?= icon('lock') ?> Change password</button>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/auth-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
