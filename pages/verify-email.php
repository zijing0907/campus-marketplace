<?php
// Confirm an email address (FR-05). Opened from the link that register.php or
// resend-verification.php emailed:
//   /pages/verify-email.php?token=3f9a...
//
// Opening the link is the proof: only someone who can read that inbox has it.
// includes/tokens.php explains how the link is checked.
require __DIR__ . '/../includes/bootstrap.php';

// The address of this page contains the secret. Do not pass it on to other sites.
header('Referrer-Policy: no-referrer');

$tokenRow = find_user_token(get_text('token'), TOKEN_EMAIL_VERIFY);

if ($tokenRow !== null) {
    // Using up the link and confirming the account happen together or not at all.
    db()->beginTransaction();

    if (use_user_token((int) $tokenRow['id'])) {
        $stmt = db()->prepare('UPDATE users SET is_verified = 1 WHERE id = ?');
        $stmt->execute([$tokenRow['user_id']]);
        db()->commit();

        // The student still logs in with their password. A link in an email should
        // prove an address, not stand in for a password.
        set_flash('success', 'Your email address is confirmed. You can log in now.');
        redirect('/pages/login.php');
    }

    db()->rollBack();
}

$pageTitle = 'Confirm your email';
$bodyClass = 'auth-page';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/auth-start.php';
?>

<?php // One message for every reason: unknown, already used, or too old. ?>
<div class="auth-message">
    <span class="auth-message-icon auth-message-icon-warning"><?= icon('clock', 28) ?></span>
    <h1>This link no longer works</h1>
    <p>A confirmation link works once, and only for 24 hours.
       If you already confirmed your address, just log in.</p>
    <a class="button button-gradient" href="/pages/login.php"><?= icon('log-in') ?> Log in</a>
    <p class="form-footer">Not confirmed yet? <a href="/pages/resend-verification.php">Send a new link</a></p>
</div>

<?php require __DIR__ . '/../includes/auth-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
