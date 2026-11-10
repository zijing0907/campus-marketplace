<?php
// My account. Only logged-in students can open this page (NFR-04).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

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
    </dl>

    <a class="button" href="/pages/profile-edit.php">Edit profile</a>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
