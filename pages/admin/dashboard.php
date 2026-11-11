<?php
// Admin: dashboard with the site's totals (FR-26).
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/demo-data.php';
$admin = require_admin();

$demoLoaded = demo_data_loaded();

// One query returns every total. Each "(SELECT COUNT(*) ...)" is a small query of
// its own, and the outer SELECT gathers their answers into a single row.
$totals = db()->query(
    "SELECT (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM users WHERE is_banned = 1) AS banned_users,
            (SELECT COUNT(*) FROM listings WHERE status = 'available') AS active_listings,
            (SELECT COUNT(*) FROM listings WHERE status = 'sold') AS sold_listings,
            (SELECT COUNT(*) FROM listings WHERE status = 'hidden') AS hidden_listings,
            (SELECT COUNT(*) FROM reports WHERE status = 'open') AS open_reports"
)->fetch();

$pageTitle = 'Admin dashboard';
$adminPage = 'dashboard';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/admin-nav.php';
?>

<div class="page-head">
    <h1>Dashboard</h1>
</div>

<div class="stat-grid">
    <a class="stat" href="/pages/admin/users.php">
        <span class="stat-number"><?= (int) $totals['users'] ?></span>
        <span class="stat-label">Users</span>
        <span class="stat-note"><?= (int) $totals['banned_users'] ?> banned</span>
    </a>

    <a class="stat" href="/">
        <span class="stat-number"><?= (int) $totals['active_listings'] ?></span>
        <span class="stat-label">Active listings</span>
        <span class="stat-note"><?= (int) $totals['hidden_listings'] ?> hidden by admins</span>
    </a>

    <div class="stat">
        <span class="stat-number"><?= (int) $totals['sold_listings'] ?></span>
        <span class="stat-label">Sold listings</span>
    </div>

    <a class="stat" href="/pages/admin/reports.php">
        <span class="stat-number"><?= (int) $totals['open_reports'] ?></span>
        <span class="stat-label">Open reports</span>
        <span class="stat-note"><?= (int) $totals['open_reports'] > 0 ? 'Waiting for a decision' : 'Nothing waiting' ?></span>
    </a>
</div>

<section class="demo-box">
    <h2>Demo data</h2>

    <?php if ($demoLoaded): ?>
        <p>
            Demo data is loaded. Visitors can log in as <strong><?= e(demo_email('student')) ?></strong>.
            The password is <code>DEMO_PASSWORD</code> in <code>includes/demo-data.php</code>.
        </p>
        <form method="post" action="/pages/admin/demo-action.php"
              data-confirm="Remove the demo accounts, with their listings, photos and messages?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <button type="submit" class="button button-danger button-small">Remove demo data</button>
        </form>
    <?php else: ?>
        <p>
            Add <?= count(DEMO_USERS) ?> sample accounts and <?= count(DEMO_LISTINGS) ?> sample listings,
            so the site does not look empty. They can be removed again with one click.
        </p>
        <form method="post" action="/pages/admin/demo-action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="load">
            <button type="submit" class="button button-secondary button-small">Load demo data</button>
        </form>
    <?php endif; ?>
</section>

<script src="/assets/js/confirm.js"></script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
