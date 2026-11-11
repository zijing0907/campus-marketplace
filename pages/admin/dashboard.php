<?php
// Admin: dashboard with the site's totals (FR-26).
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();

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

<?php require __DIR__ . '/../../includes/footer.php'; ?>
