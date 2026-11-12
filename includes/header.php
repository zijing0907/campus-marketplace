<?php
// Shared top of every page (NFR-10: no repeated header HTML).
// A page requires bootstrap.php first, sets $pageTitle, then requires this file.
// $currentUser is the logged-in user's row, or null for a guest. bootstrap.php sets
// the same variable for pages; it is fetched again here (no extra database query)
// so this file also works when it is included from inside a function, such as
// require_admin(), where the page's own variables are out of reach.
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php // JavaScript reads the CSRF token from here when it sends a request with fetch(). ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? 'Campus Marketplace') ?> · Campus Marketplace</title>
    <?php // The small picture in the browser tab. ?>
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php
// Marks the menu link of the page being shown. Screen readers announce it as the
// current page, and the stylesheet uses the same attribute to highlight it.
// $here is the file that is running, for example "/pages/inbox.php".
$here = $_SERVER['SCRIPT_NAME'] ?? '';
$current = static fn (string ...$pages): string => in_array($here, $pages, true) ? ' aria-current="page"' : '';

// The unread number beside "Inbox". It is built once because the link appears twice
// below: in the menu, and as a shortcut that stays visible on phones.
// assets/js/live.js keeps both up to date without a refresh.
$unreadBadge = '';
if ($currentUser) {
    $unreadMessages = unread_message_count((int) $currentUser['id']);
    if ($unreadMessages > 0) {
        $unreadBadge = ' <span class="count-badge">' . $unreadMessages . '<span class="visually-hidden"> unread</span></span>';
    }
}
?>
<header class="site-header" id="site-header">
    <div class="header-inner">
        <a class="brand" href="/">
            <?php // The logo, a small shopping bag. It is hidden from screen readers
                  // because the name right beside it already says everything. ?>
            <svg class="brand-mark" viewBox="0 0 32 32" width="28" height="28" aria-hidden="true">
                <rect width="32" height="32" rx="8" fill="#2563eb"/>
                <path d="M9 13h14l-1.1 10.3a2 2 0 0 1-2 1.7h-7.8a2 2 0 0 1-2-1.7z" fill="#ffffff"/>
                <path d="M12.5 13v-1.5a3.5 3.5 0 0 1 7 0V13" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Campus Marketplace
        </a>

        <?php if ($currentUser): ?>
            <?php // Phones only: unread messages stay visible while the menu is closed.
                  // The Menu button starts hidden and assets/js/nav.js switches it on,
                  // so without JavaScript the full menu is simply always shown.
                  // Both are icons to fit a narrow screen. The words in "visually-hidden"
                  // are not shown, but screen readers read them out. ?>
            <a class="nav-quick" href="/pages/inbox.php" data-inbox-link>
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
                    <path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-8l-5 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
                <span class="visually-hidden">Inbox</span><?= $unreadBadge ?>
            </a>
            <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="site-nav" hidden>
                <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true">
                    <path d="M3 5h14M3 10h14M3 15h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span class="visually-hidden">Menu</span>
            </button>
        <?php endif; ?>

        <nav class="site-nav" id="site-nav" aria-label="Main menu">
            <?php // For a guest on a narrow phone this link is hidden; the site name also leads home. ?>
            <a class="nav-browse" href="/"<?= $current('/index.php') ?>>Browse</a>
            <?php if ($currentUser): ?>
                <a href="/pages/my-listings.php"<?= $current('/pages/my-listings.php', '/pages/listing-edit.php') ?>>My listings</a>
                <?php // The id is the one live.js has always used; data-inbox-link marks both Inbox links. ?>
                <a href="/pages/inbox.php" id="inbox-link" data-inbox-link<?= $current('/pages/inbox.php', '/pages/conversation.php') ?>>Inbox<?= $unreadBadge ?></a>
                <a href="/pages/wishlist.php"<?= $current('/pages/wishlist.php') ?>>Saved</a>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <?php // Hiding this link from students is only for tidiness. The real
                          // protection is require_admin() at the top of every admin page. ?>
                    <?php $openReports = open_report_count(); ?>
                    <a href="/pages/admin/dashboard.php"<?= str_starts_with($here, '/pages/admin/') ? ' aria-current="page"' : '' ?>>
                        Admin<?php if ($openReports > 0): ?>
                            <span class="count-badge"><?= $openReports ?><span class="visually-hidden"> open reports</span></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <a class="nav-user" href="/pages/profile.php"<?= $current('/pages/profile.php', '/pages/profile-edit.php') ?>><?= e($currentUser['name']) ?></a>
                <?php // Logging out changes something, so it is a form post with a CSRF token, not a link. ?>
                <form method="post" action="/pages/logout.php" class="inline-form nav-logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Log out</button>
                </form>
                <a class="button button-small nav-sell" href="/pages/listing-create.php"<?= $current('/pages/listing-create.php') ?>>Sell an item</a>
            <?php else: ?>
                <a href="/pages/login.php"<?= $current('/pages/login.php') ?>>Log in</a>
                <?php // Hidden on a very narrow phone, where the header has no room for it.
                      // The home page and the login page both offer "Create an account" too. ?>
                <a class="button button-small nav-register" href="/pages/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php // Loaded right here, before the page below is drawn, so the phone menu never flashes open. ?>
<script src="/assets/js/nav.js"></script>
<main>
<?php if ($flash = take_flash()): ?>
    <p class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
<?php endif; ?>
