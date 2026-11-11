<?php
// Admin: act on a report (FR-23). The buttons on the reports page post here.
//   action=hide     hide the reported listing and close every open report about it
//   action=dismiss  close this one report without touching the listing
//   action=restore  make a hidden listing available again, to undo a mistake
require __DIR__ . '/../../includes/bootstrap.php';

// The role check comes first. Every request is checked on the server; it would
// not be enough that only admins are shown the buttons (NFR-04).
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/admin/reports.php');
}
verify_csrf();

$reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
$action = post_text('action');

$report = null;
if (is_int($reportId) && $reportId > 0) {
    $stmt = db()->prepare('SELECT id, listing_id, status FROM reports WHERE id = ?');
    $stmt->execute([$reportId]);
    $report = $stmt->fetch() ?: null;
}

if ($report === null) {
    set_flash('error', 'That report does not exist.');
    redirect('/pages/admin/reports.php');
}

if ($action === 'hide') {
    // Hiding the listing and closing its reports belong together, so they are one
    // transaction: either both happen or neither does.
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("UPDATE listings SET status = 'hidden' WHERE id = ?");
        $stmt->execute([$report['listing_id']]);

        // Other students may have reported the same listing. Those reports are
        // answered by this decision too.
        $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE listing_id = ? AND status = 'open'");
        $stmt->execute([$report['listing_id']]);
        $closed = $stmt->rowCount();

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    set_flash('success', 'The listing is now hidden. Reports closed: ' . $closed . '.');
    redirect('/pages/admin/reports.php');
}

if ($action === 'dismiss') {
    // "AND status = 'open'" means a report that was already handled stays as it is.
    $stmt = db()->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ? AND status = 'open'");
    $stmt->execute([$report['id']]);

    set_flash('success', 'The report has been dismissed. The listing was not changed.');
    redirect('/pages/admin/reports.php');
}

if ($action === 'restore') {
    $stmt = db()->prepare("UPDATE listings SET status = 'available' WHERE id = ? AND status = 'hidden'");
    $stmt->execute([$report['listing_id']]);

    set_flash('success', 'The listing is available again.');
    redirect('/pages/admin/reports.php?view=handled');
}

set_flash('error', 'Unknown action.');
redirect('/pages/admin/reports.php');
