<?php
// Forgot your password? Step 1 of 2: ask for a reset link by email (FR-06).
// Step 2 is reset-password.php, which the emailed link opens.
require __DIR__ . '/../includes/bootstrap.php';
require_guest();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(post_text('email'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // A link is emailed only if the account exists, and only if one was not
        // sent in the last two minutes already.
        if ($user && !user_token_is_recent((int) $user['id'], TOKEN_PASSWORD_RESET)) {
            $token = create_user_token((int) $user['id'], TOKEN_PASSWORD_RESET, PASSWORD_RESET_MINUTES);

            send_email(
                $user['email'],
                'Reset your Campus Marketplace password',
                "Hello {$user['name']},\n\n"
                . "Someone asked to reset the password of your Campus Marketplace account.\n"
                . 'To choose a new password, open this link within ' . PASSWORD_RESET_MINUTES . " minutes:\n\n"
                . site_url('/pages/reset-password.php?token=' . $token) . "\n\n"
                . "If this was not you, ignore this email. Your password stays the same.\n"
            );
        }

        // Whatever happened above, the visitor is told exactly the same thing.
        // Otherwise this form could be used to find out which emails have an
        // account here. The redirect also means a refresh does not ask again.
        redirect('/pages/forgot-password.php?sent=1');
    }
}

$sent = get_text('sent') === '1';

$pageTitle = 'Forgot your password';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <?php if ($sent): ?>
        <h1>Check your email</h1>
        <p>If there is an account for that email address, a link to choose a new password is on its way.
           It works for <?= PASSWORD_RESET_MINUTES ?> minutes.</p>
        <p class="note">Nothing after a few minutes? Check the address and <a href="/pages/forgot-password.php">try again</a>.</p>

        <?php if (dev_mailbox_is_open()): ?>
            <?php // Shown on a developer's own computer only; see dev_mailbox_is_open(). ?>
            <p class="alert alert-info">
                Development mode: emails are saved, not sent.
                <a href="/pages/dev-mailbox.php">Open the local mailbox</a>
            </p>
        <?php endif; ?>

        <a class="button button-secondary" href="/pages/login.php">Back to log in</a>
    <?php else: ?>
        <h1>Forgot your password?</h1>
        <p class="note">Enter your university email and we will send you a link to choose a new one.</p>

        <form method="post" action="/pages/forgot-password.php">
            <?= csrf_field() ?>

            <label for="email">University email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>"
                   required maxlength="255" autocomplete="email">
            <?php if ($error !== ''): ?>
                <p class="field-error"><?= e($error) ?></p>
            <?php endif; ?>

            <button type="submit" class="button">Send reset link</button>
        </form>

        <p class="form-footer"><a href="/pages/login.php">Back to log in</a></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
