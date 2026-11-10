<?php
// Log in (FR-02).
require __DIR__ . '/../includes/bootstrap.php';
require_guest();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(post_text('email'));
    $password = post_text('password', trim: false);

    $stmt = db()->prepare('SELECT id, name, password_hash, is_banned FROM users WHERE email = ?');
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
    } else {
        login_user((int) $user['id']);
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

    <p class="form-footer">New here? <a href="/pages/register.php">Create an account</a></p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
