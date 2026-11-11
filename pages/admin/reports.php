<?php
// Admin: reports from students about listings (FR-23).
// The default view shows the open reports, each with the actions Hide listing and
// Dismiss. ?view=handled shows the ones already dealt with.
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();

$showHandled = get_text('view') === 'handled';

// The two possible conditions are fixed pieces of SQL chosen here. Nothing typed
// by a user is ever put into the query text.
$statusSql = $showHandled ? "r.status <> 'open'" : "r.status = 'open'";

// Each report with its listing, the listing's seller, the student who reported
// it, and the listing's cover photo.
$reports = db()->query(
    "SELECT r.id, r.reason, r.details, r.status, r.created_at,
            l.id AS listing_id, l.title AS listing_title, l.status AS listing_status,
            seller.name AS seller_name, reporter.name AS reporter_name,
            (SELECT i.file_path
             FROM listing_images i
             WHERE i.listing_id = l.id
             ORDER BY i.sort_order, i.id
             LIMIT 1) AS cover_path
     FROM reports r
     JOIN listings l ON l.id = r.listing_id
     JOIN users seller ON seller.id = l.seller_id
     JOIN users reporter ON reporter.id = r.reporter_id
     WHERE $statusSql
     ORDER BY r.created_at DESC, r.id DESC"
)->fetchAll();

$listingStatusLabels = ['available' => 'Available', 'sold' => 'Sold', 'hidden' => 'Hidden'];
// What was decided about a handled report. The listing may have been restored
// since, which is why the first label is in the past tense.
$reportStatusLabels = ['resolved' => 'Listing was hidden', 'dismissed' => 'Dismissed'];

$pageTitle = $showHandled ? 'Handled reports' : 'Open reports';
$adminPage = 'reports';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/admin-nav.php';
?>

<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($showHandled): ?>
        <a href="/pages/admin/reports.php">Show open reports</a>
    <?php else: ?>
        <a href="/pages/admin/reports.php?view=handled">Show handled reports</a>
    <?php endif; ?>
</div>

<?php if (!$reports): ?>
    <p class="empty"><?= $showHandled ? 'No reports have been handled yet.' : 'No open reports. Nothing needs your attention.' ?></p>
<?php else: ?>
    <ul class="my-listings">
        <?php foreach ($reports as $report): ?>
            <?php $listingUrl = '/pages/listing.php?id=' . $report['listing_id']; ?>
            <li class="my-listing">
                <a class="my-listing-photo" href="<?= e($listingUrl) ?>">
                    <?php if ($report['cover_path'] !== null): ?>
                        <img src="/<?= e($report['cover_path']) ?>" alt="">
                    <?php endif; ?>
                </a>

                <div class="my-listing-info">
                    <a class="my-listing-title" href="<?= e($listingUrl) ?>"><?= e($report['listing_title']) ?></a>
                    <span class="badge badge-<?= e($report['listing_status']) ?>"><?= e($listingStatusLabels[$report['listing_status']]) ?></span>

                    <p class="note">Listed by <?= e($report['seller_name']) ?></p>
                    <p class="report-reason">
                        <strong><?= e(REPORT_REASONS[$report['reason']]) ?></strong>
                        <?php if ($report['details'] !== null): ?>
                            <?php // Written by a student, so it is escaped like any other user text. ?>
                            <span class="report-details"><?= nl2br(e($report['details'])) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="note">
                        Reported by <?= e($report['reporter_name']) ?> on <?= e(date('j M Y', strtotime($report['created_at']))) ?>
                        <?php if ($showHandled): ?>
                            · <?= e($reportStatusLabels[$report['status']]) ?>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="my-listing-actions">
                    <?php if (!$showHandled): ?>
                        <form method="post" action="/pages/admin/report-action.php"
                              data-confirm="Hide this listing? Only its seller and admins will be able to see it.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="report_id" value="<?= e((string) $report['id']) ?>">
                            <input type="hidden" name="action" value="hide">
                            <button type="submit" class="button button-danger button-small">Hide listing</button>
                        </form>

                        <form method="post" action="/pages/admin/report-action.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="report_id" value="<?= e((string) $report['id']) ?>">
                            <input type="hidden" name="action" value="dismiss">
                            <button type="submit" class="button button-secondary button-small">Dismiss</button>
                        </form>
                    <?php elseif ($report['listing_status'] === 'hidden'): ?>
                        <?php // An admin can undo a hide, in case it was a mistake. ?>
                        <form method="post" action="/pages/admin/report-action.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="report_id" value="<?= e((string) $report['id']) ?>">
                            <input type="hidden" name="action" value="restore">
                            <button type="submit" class="button button-secondary button-small">Restore listing</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<script src="/assets/js/confirm.js"></script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
