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
<?php // A page can set $bodyClass first, for example the account pages' "auth-page". ?>
<body<?= isset($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
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
// The same for the bell: how many notifications are new.
$notificationBadge = '';
$unreadNotifications = 0;
if ($currentUser) {
    $unreadMessages = unread_message_count((int) $currentUser['id']);
    if ($unreadMessages > 0) {
        $unreadBadge = ' <span class="count-badge">' . $unreadMessages . '<span class="visually-hidden"> unread</span></span>';
    }

    $unreadNotifications = unread_notification_count((int) $currentUser['id']);
    if ($unreadNotifications > 0) {
        $notificationBadge = ' <span class="count-badge">' . $unreadNotifications . '<span class="visually-hidden"> new</span></span>';
    }
}
?>
<header class="site-header" id="site-header">
    <div class="header-inner">
        <a class="brand" href="/">
            <?php // The logo, a small shopping bag. It is hidden from screen readers
                  // because the name right beside it already says everything. ?>
            <svg class="brand-mark" viewBox="0 0 32 32" width="30" height="30" aria-hidden="true">
                <defs>
                    <linearGradient id="brand-mark-fill" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#2563eb"/>
                        <stop offset="1" stop-color="#6d28d9"/>
                    </linearGradient>
                </defs>
                <rect width="32" height="32" rx="9" fill="url(#brand-mark-fill)"/>
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
                <?php // A red dot while there are new notifications, which on a phone are inside the menu. ?>
                <span class="nav-toggle-dot" data-notification-dot <?= $unreadNotifications > 0 ? '' : 'hidden' ?>>
                    <span class="visually-hidden">(new notifications)</span>
                </span>
            </button>
        <?php endif; ?>

        <nav class="site-nav" id="site-nav" aria-label="Main menu">
            <?php // For a guest on a narrow phone this link is hidden; the site name also leads home. ?>
            <a class="nav-browse" href="/"<?= $current('/index.php') ?>>Browse</a>
            <?php if ($currentUser): ?>
                <a href="/pages/my-listings.php"<?= $current('/pages/my-listings.php', '/pages/listing-edit.php') ?>>My listings</a>
                <?php // The id is the one live.js has always used; data-inbox-link marks both Inbox links. ?>
                <a href="/pages/inbox.php" id="inbox-link" data-inbox-link<?= $current('/pages/inbox.php', '/pages/conversation.php') ?>>Inbox<?= $unreadBadge ?></a>
                <?php // The bell. On a wide screen only the icon shows; in the phone menu the word does too. ?>
                <a class="nav-bell" href="/pages/notifications.php" data-notification-link<?= $current('/pages/notifications.php') ?>>
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                        <path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M10 21a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span class="nav-bell-label">Notifications</span><?= $notificationBadge ?>
                </a>
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
<?php if ($currentUser): ?>
    <?php // The shared code for anything that updates without a refresh (FR-21). Loaded
          // here, before the page, because the inbox and conversation pages use it. ?>
    <script src="/assets/js/live.js"></script>
<?php endif; ?>
<main>
<?php if ($flash = take_flash()): ?>
    <p class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
<?php endif; ?>
