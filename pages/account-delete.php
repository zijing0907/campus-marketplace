<?php
// Delete my account. delete_account() in includes/account.php does the work and
// explains exactly what is removed and what is kept.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$errors = [];

// The listings that will disappear, so the page can say how many.
$stmt = db()->prepare(
    'SELECT COUNT(*) FROM listings l
     WHERE l.seller_id = ? AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.listing_id = l.id)'
);
$stmt->execute([$user['id']]);
$listingCount = (int) $stmt->fetchColumn();

$lastAdmin = $user['role'] === 'admin' && is_last_admin((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($lastAdmin) {
        // Checked on the server too: the page hides the form, but a form can be
        // sent without the page.
        $errors['password'] = 'You are the only admin. Make another student an admin first.';
    } else {
        $passwordError = check_current_password($user, post_text('password', trim: false));
        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }

        if (post_text('confirm') !== 'yes') {
            $errors['confirm'] = 'Tick the box to confirm that you want to delete your account.';
        }
    }

    if (!$errors) {
        delete_account((int) $user['id']);

        logout_user();
        set_flash('success', 'Your account has been deleted. Thank you for using Campus Marketplace.');
        redirect('/');
    }
}

$pageTitle = 'Delete account';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h1>Delete your account</h1>

    <?php if ($lastAdmin): ?>
        <p class="alert alert-error">You are the only admin. Make another student an admin first, so someone can still handle reports.</p>
        <a class="button button-secondary" href="/pages/profile.php">Back to my account</a>
    <?php else: ?>
        <p>This cannot be undone. Deleting your account:</p>
        <ul class="plain-list">
            <li>removes your name, email, phone, faculty and profile photo</li>
            <?php if ($listingCount > 0): ?>
                <li>deletes <?= $listingCount ?> <?= $listingCount === 1 ? 'listing' : 'listings' ?> of yours, with their photos and messages</li>
            <?php endif; ?>
            <li>logs you out everywhere</li>
        </ul>
        <p class="note">Messages you sent about other students' items, and ratings you gave, stay for the
           other student, shown as from "Deleted user". A sale that was rated also stays.</p>

        <form method="post" action="/pages/account-delete.php">
            <?= csrf_field() ?>

            <label for="password">Your password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <?= field_error($errors, 'password') ?>

            <label class="checkbox">
                <input type="checkbox" name="confirm" value="yes" required>
                I understand that my account will be deleted for good.
            </label>
            <?= field_error($errors, 'confirm') ?>

            <button type="submit" class="button button-danger">Delete my account</button>
        </form>

        <p class="form-footer"><a href="/pages/profile.php">Keep my account</a></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
