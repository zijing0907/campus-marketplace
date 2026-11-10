<?php
// My account. Only logged-in students can open this page (NFR-04).
// Editing the profile (FR-04) will be added here later.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$pageTitle = 'My account';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h1>My account</h1>

    <dl class="details">
        <dt>Name</dt>
        <dd><?= e($user['name']) ?></dd>

        <dt>Email</dt>
        <dd><?= e($user['email']) ?></dd>
    </dl>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
