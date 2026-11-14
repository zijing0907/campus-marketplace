<?php
// My account. Only logged-in students can open this page (NFR-04).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$myRating = seller_rating((int) $user['id']);

$pageTitle = 'My account';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <div class="profile-head">
        <?= avatar_html($user) ?>
        <div>
            <h1><?= e($user['name']) ?></h1>
            <p class="note"><?= e($user['email']) ?></p>
        </div>
    </div>

    <dl class="details">
        <dt>Faculty</dt>
        <dd>
            <?php if ($user['faculty'] !== null): ?>
                <?= e($user['faculty']) ?>
            <?php else: ?>
                <span class="muted">Not set</span>
            <?php endif; ?>
        </dd>

        <dt>Phone or WhatsApp</dt>
        <dd>
            <?php if ($user['phone'] !== null): ?>
                <?= e($user['phone']) ?>
            <?php else: ?>
                <span class="muted">Not set</span>
            <?php endif; ?>
        </dd>

        <dt>Rating as a seller</dt>
        <dd><?= e(rating_summary($myRating)) ?></dd>
    </dl>

    <p class="note">
        <a href="/pages/user.php?id=<?= e((string) $user['id']) ?>">See your public profile</a>, the page other students see.
    </p>

    <a class="button" href="/pages/profile-edit.php">Edit profile</a>

    <h2 class="card-subtitle">Security</h2>
    <ul class="security-links">
        <li><a href="/pages/password-change.php">Change password</a></li>
        <li><a class="danger-link" href="/pages/account-delete.php">Delete my account</a></li>
    </ul>

    <?php // Logging out is a form post with a CSRF token, never a plain link. ?>
    <form method="post" action="/pages/logout.php">
        <?= csrf_field() ?>
        <button type="submit" class="button button-secondary">Log out</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
