<?php
// My listings: everything the logged-in student has posted, with the actions
// Edit, Delete and Mark as sold (FR-08, FR-09).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

// "WHERE seller_id = ?" with the id from the session is what limits this page to
// the student's own listings. The inner SELECTs pick each listing's cover photo
// (the image with the lowest sort_order) and count its ratings. LEFT JOIN brings
// in the buyer's name where one was recorded, and keeps the listing where not.
$stmt = db()->prepare(
    'SELECT l.id, l.title, l.price, l.status, l.created_at,
            b.name AS buyer_name,
            (SELECT COUNT(*) FROM reviews r WHERE r.listing_id = l.id) AS review_count,
            (SELECT i.file_path
             FROM listing_images i
             WHERE i.listing_id = l.id
             ORDER BY i.sort_order, i.id
             LIMIT 1) AS cover_path
     FROM listings l
     LEFT JOIN users b ON b.id = l.buyer_id
     WHERE l.seller_id = ?
     ORDER BY l.created_at DESC, l.id DESC'
);
$stmt->execute([$user['id']]);
$listings = $stmt->fetchAll();

// The students who messaged me about each listing: they are the possible buyers
// to choose from when marking it as sold (FR-20). Grouped by listing id.
$stmt = db()->prepare(
    'SELECT c.listing_id, u.id, u.name
     FROM conversations c
     JOIN users u ON u.id = c.buyer_id
     WHERE c.seller_id = ?
     ORDER BY u.name'
);
$stmt->execute([$user['id']]);
$possibleBuyers = [];
foreach ($stmt->fetchAll() as $row) {
    $possibleBuyers[$row['listing_id']][] = $row;
}

// The label and badge style for each status.
$statusLabels = [
    'available' => 'Available',
    'sold'      => 'Sold',
    'hidden'    => 'Hidden by an admin',
];

$pageTitle = 'My listings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>My listings</h1>
    <a class="button" href="/pages/listing-create.php">Sell an item</a>
</div>

<?php // Filled in by my-listings.js if changing a status fails. ?>
<p id="status-message" class="alert alert-error" role="alert" hidden></p>

<?php if (!$listings): ?>
    <p class="empty">You have not posted anything yet.</p>
<?php else: ?>
    <ul class="my-listings">
        <?php foreach ($listings as $listing): ?>
            <?php
            $url = '/pages/listing.php?id=' . $listing['id'];
            $buyers = $possibleBuyers[$listing['id']] ?? [];
            $isSold = $listing['status'] === 'sold';
            $isRated = (int) $listing['review_count'] > 0;
            ?>
            <li class="my-listing" data-id="<?= e((string) $listing['id']) ?>">
                <a class="my-listing-photo" href="<?= e($url) ?>">
                    <?php if ($listing['cover_path'] !== null): ?>
                        <img src="/<?= e($listing['cover_path']) ?>" alt="">
                    <?php endif; ?>
                </a>

                <div class="my-listing-info">
                    <a class="my-listing-title" href="<?= e($url) ?>"><?= e($listing['title']) ?></a>
                    <p class="note">
                        <?= e(format_price($listing['price'])) ?> · Posted <?= e(date('j M Y', strtotime($listing['created_at']))) ?>
                    </p>
                    <span class="badge badge-<?= e($listing['status']) ?>"><?= e($statusLabels[$listing['status']]) ?></span>

                    <?php // Who it was sold to. my-listings.js fills this in when the status changes. ?>
                    <span class="note sold-to" <?= $isSold && $listing['buyer_name'] !== null ? '' : 'hidden' ?>>
                        Sold to <?= e($listing['buyer_name']) ?><?= $isRated ? ' · rated by the buyer' : '' ?>
                    </span>
                </div>

                <div class="my-listing-actions">
                    <?php if ($listing['status'] !== 'hidden'): ?>
                        <?php if ($buyers): ?>
                            <?php // Shown while the item is on sale: who is it being sold to? ?>
                            <label class="buyer-pick" <?= $isSold ? 'hidden' : '' ?>>
                                Sold to
                                <select class="buyer-select">
                                    <option value="">Someone not on this site</option>
                                    <?php foreach ($buyers as $buyer): ?>
                                        <option value="<?= e((string) $buyer['id']) ?>"><?= e($buyer['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>

                        <?php // Handled by my-listings.js, which calls /api/listing-status.php. ?>
                        <button type="button" class="button button-secondary button-small status-toggle"
                                data-set-status="<?= $isSold ? 'available' : 'sold' ?>">
                            <?= $isSold ? 'Mark as available' : 'Mark as sold' ?>
                        </button>
                    <?php endif; ?>

                    <a class="button button-secondary button-small" href="/pages/listing-edit.php?id=<?= e((string) $listing['id']) ?>">Edit</a>

                    <?php if ($isRated): ?>
                        <?php // A rated listing is kept, so a seller cannot delete a rating they dislike. ?>
                        <span class="note">Rated listings cannot be deleted</span>
                    <?php else: ?>
                        <form method="post" action="/pages/listing-delete.php"
                              data-confirm="Delete this listing and its photos? This cannot be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $listing['id']) ?>">
                            <button type="submit" class="button button-danger button-small">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<script src="/assets/js/confirm.js"></script>
<script src="/assets/js/my-listings.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
