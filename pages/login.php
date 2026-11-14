<?php
// Log in (FR-02).
require __DIR__ . '/../includes/bootstrap.php';
require_guest();

$error = '';
$email = '';
$unconfirmed = false;   // true: the password was right, but the email is not confirmed yet

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(post_text('email'));
    $password = post_text('password', trim: false);

    $stmt = db()->prepare('SELECT id, name, password_hash, is_verified, is_banned FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // password_verify() hashes what was typed, using the salt stored inside the
    // saved hash, and compares the two results. The real password is never stored.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // One message for both "no such email" and "wrong password", so the form
        // does not confirm which emails have accounts.
        $error = 'Incorrect email or password.';
    } elseif ($user['is_banned']) {
        // Checked after the password, so only the account's owner sees this (FR-24).
        $error = 'This account has been suspended. Contact an admin if you think this is a mistake.';
    } elseif (email_verification_required() && !$user['is_verified']) {
        // Also checked after the password, so a stranger cannot use this message
        // to find out which addresses have signed up (FR-05).
        $unconfirmed = true;
        $error = 'Confirm your email address first. Open the link we sent to ' . $email . ' when you signed up.';
    } else {
        login_user((int) $user['id'], $user['password_hash']);
        set_flash('success', 'Welcome back, ' . $user['name'] . '!');
        redirect('/');
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h1>Log in</h1>

    <?php if ($error !== ''): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($unconfirmed): ?>
        <?php // One click to get a fresh link, without typing the address again. ?>
        <form method="post" action="/pages/resend-verification.php" class="resend-form">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?= e($email) ?>">
            <button type="submit" class="button button-secondary">Send the link again</button>
        </form>
    <?php endif; ?>

    <form method="post" action="/pages/login.php">
        <?= csrf_field() ?>

        <label for="email">University email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>"
               required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               required autocomplete="current-password">

        <button type="submit" class="button">Log in</button>
    </form>

    <p class="form-footer"><a href="/pages/forgot-password.php">Forgot your password?</a></p>
    <p class="form-footer">New here? <a href="/pages/register.php">Create an account</a></p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
