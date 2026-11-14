<?php
// Send the "confirm your email" link again (FR-05), for a student who lost the
// first one or let it expire. The login page posts here with one click; the form
// below is for anyone who comes here directly.
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
        $stmt = db()->prepare('SELECT id, name, email, is_verified FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Sent only to an account that still needs confirming, and not if a link
        // went out in the last two minutes (that includes the one sent at sign-up).
        if ($user && !$user['is_verified'] && email_verification_required()
            && !user_token_is_recent((int) $user['id'], TOKEN_EMAIL_VERIFY)) {
            send_verification_email($user);
        }

        // The same answer whatever happened, as on the forgot-password page, so this
        // form cannot be used to find out which addresses have signed up.
        redirect('/pages/resend-verification.php?sent=1');
    }
}

$sent = get_text('sent') === '1';

$pageTitle = 'Confirm your email';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <?php if ($sent): ?>
        <h1>Check your email</h1>
        <p>If that address belongs to an account that still needs confirming, a new link is on its way.
           It works for 24 hours.</p>
        <?= dev_mailbox_note() ?>
        <a class="button button-secondary" href="/pages/login.php">Back to log in</a>
    <?php else: ?>
        <h1>Send the confirmation link again</h1>
        <p class="note">Enter the university email you signed up with.</p>

        <form method="post" action="/pages/resend-verification.php">
            <?= csrf_field() ?>

            <label for="email">University email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>"
                   required maxlength="255" autocomplete="email">
            <?php if ($error !== ''): ?>
                <p class="field-error"><?= e($error) ?></p>
            <?php endif; ?>

            <button type="submit" class="button">Send link</button>
        </form>

        <p class="form-footer"><a href="/pages/login.php">Back to log in</a></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
