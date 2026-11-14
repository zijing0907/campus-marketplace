<?php
// Log in (FR-02).
require __DIR__ . '/../includes/bootstrap.php';
require_guest();

$error = '';
$email = '';
$unconfirmed = false;   // true: the password was right, but the email is not confirmed yet
$remember = false;      // the "Remember me" box; it starts unticked, the safer choice

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(post_text('email'));
    $password = post_text('password', trim: false);
    $remember = post_text('remember') === '1';

    $stmt = db()->prepare('SELECT id, name, password_hash, is_verified, is_banned FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Too many wrong passwords lately? Then the password is not even checked, not
    // even a correct one. That is what makes guessing pointless (see login-limit.php).
    $wait = login_wait_minutes($email);

    if ($wait > 0) {
        $error = login_wait_message($wait);
    } elseif (!$user) {
        // Checking a password is deliberately slow. Do the same slow work here as
        // for a real account, so the reply does not come back noticeably faster and
        // give away that this address has no account.
        password_hash($password, PASSWORD_DEFAULT);
        record_failed_login($email);
        $error = wrong_password_message($email);
    } elseif (!password_verify($password, $user['password_hash'])) {
        // password_verify() hashes what was typed, using the salt stored inside the
        // saved hash, and compares the two results. The real password is never stored.
        record_failed_login($email);
        // The same message as above for "no such email", so the form does not
        // confirm which emails have accounts.
        $error = wrong_password_message($email);
    } elseif ($user['is_banned']) {
        // Checked after the password, so only the account's owner sees this (FR-24).
        $error = 'This account has been suspended. Contact an admin if you think this is a mistake.';
    } elseif (email_verification_required() && !$user['is_verified']) {
        // Also checked after the password, so a stranger cannot use this message
        // to find out which addresses have signed up (FR-05).
        $unconfirmed = true;
        $error = 'Confirm your email address first. Open the link we sent to ' . $email . ' when you signed up.';
    } else {
        clear_failed_logins($email);
        // Ticked "Remember me": stay logged in for 30 days. Not ticked: until the browser closes.
        login_user((int) $user['id'], $user['password_hash'], $remember);
        set_flash('success', 'Welcome back, ' . $user['name'] . '!');
        redirect('/');
    }
}

$pageTitle = 'Log in';
$bodyClass = 'auth-page';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/auth-start.php';
?>

<h1>Welcome back</h1>
<p class="note">Log in with your university email.</p>

<?php if ($error !== ''): ?>
    <p class="alert alert-error"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($unconfirmed): ?>
    <?php // One click to get a fresh link, without typing the address again. ?>
    <form method="post" action="/pages/resend-verification.php" class="resend-form">
        <?= csrf_field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <button type="submit" class="button button-secondary"><?= icon('send') ?> Send the link again</button>
    </form>
<?php endif; ?>

<form method="post" action="/pages/login.php">
    <?= csrf_field() ?>

    <label for="email">University email</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>"
           required autocomplete="email" placeholder="you@<?= e($authDomain) ?>">

    <label for="password">Password</label>
    <input type="password" id="password" name="password"
           required autocomplete="current-password" data-password-toggle>

    <div class="auth-row">
        <label class="checkbox">
            <input type="checkbox" name="remember" value="1" <?= $remember ? 'checked' : '' ?>>
            Remember me
        </label>
        <a href="/pages/forgot-password.php"><?= icon('key', 16) ?> Forgot your password?</a>
    </div>

    <button type="submit" class="button button-gradient" data-loading-text="Logging in…"><?= icon('lock') ?> Log in</button>
</form>

<ul class="auth-links">
    <li><a href="/pages/register.php"><?= icon('user-plus') ?> New here? Create an account</a></li>
    <?php if (email_verification_required()): ?>
        <li><a href="/pages/resend-verification.php"><?= icon('mail') ?> Didn't get the confirmation email?</a></li>
    <?php endif; ?>
</ul>

<?php require __DIR__ . '/../includes/auth-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
