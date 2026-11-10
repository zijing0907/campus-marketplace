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
    <?php // JavaScript reads the CSRF token from here when it sends a request with fetch(). ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? 'Campus Marketplace') ?> · Campus Marketplace</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/">Campus Marketplace</a>
    <nav>
        <?php // On a narrow phone this link is hidden; the site name above also leads home. ?>
        <a class="nav-browse" href="/">Browse</a>
        <?php if ($currentUser): ?>
            <a href="/pages/listing-create.php">Sell</a>
            <a href="/pages/my-listings.php">My listings</a>
            <?php $unreadMessages = unread_message_count((int) $currentUser['id']); ?>
            <a href="/pages/inbox.php">
                Inbox<?php if ($unreadMessages > 0): ?>
                    <span class="count-badge"><?= $unreadMessages ?><span class="visually-hidden"> unread</span></span>
                <?php endif; ?>
            </a>
            <a class="nav-user" href="/pages/profile.php"><?= e($currentUser['name']) ?></a>
            <?php // On a narrow phone this is hidden to save room; the account page has it too. ?>
            <form method="post" action="/pages/logout.php" class="inline-form nav-logout">
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
