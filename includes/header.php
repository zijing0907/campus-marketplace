<?php
// Shared top of every page (NFR-10: no repeated header HTML).
// A page requires bootstrap.php first, sets $pageTitle, then requires this file.
// $currentUser comes from bootstrap.php: the logged-in user's row, or null for a guest.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Campus Marketplace') ?> · Campus Marketplace</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/">Campus Marketplace</a>
    <nav>
        <a href="/">Browse</a>
        <?php if ($currentUser): ?>
            <a href="/pages/listing-create.php">Sell</a>
            <a class="nav-user" href="/pages/profile.php"><?= e($currentUser['name']) ?></a>
            <form method="post" action="/pages/logout.php" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="link-button">Log out</button>
            </form>
        <?php else: ?>
            <a href="/pages/login.php">Log in</a>
            <a href="/pages/register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>
<main>
<?php if ($flash = take_flash()): ?>
    <p class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
<?php endif; ?>
