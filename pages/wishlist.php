<?php
// Saved listings: everything the logged-in student has added to their wishlist (FR-19).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

// The wishlists table has just two ids per row: who saved, and what they saved.
// It links users and listings "many to many": one student can save many listings,
// and one listing can be saved by many students. Joining it to listings turns
// those ids back into listings to show.
//
// A listing that an admin has hidden, or whose seller is banned, is left out. A
// sold one stays, marked as sold, so the student can see what happened to it.
$stmt = db()->prepare(
    "SELECT l.id, l.title, l.price, l.item_condition, l.meetup_location, l.status,
            (SELECT i.file_path
             FROM listing_images i
             WHERE i.listing_id = l.id
             ORDER BY i.sort_order, i.id
             LIMIT 1) AS cover_path
     FROM wishlists w
     JOIN listings l ON l.id = w.listing_id
     JOIN users s ON s.id = l.seller_id
     WHERE w.user_id = ? AND l.status <> 'hidden' AND s.is_banned = 0
     ORDER BY w.created_at DESC, l.id DESC"
);
$stmt->execute([$user['id']]);
$listings = $stmt->fetchAll();

$pageTitle = 'Saved listings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Saved listings (<span id="wishlist-count"><?= count($listings) ?></span>)</h1>
</div>

<?php // Shown when the list is empty, including after the last item is removed. ?>
<p id="wishlist-empty" class="empty" <?= $listings ? 'hidden' : '' ?>>
    You have not saved anything yet. Use the Save button on a listing to keep it here.
</p>

<?php // Filled in by wishlist.js if removing an item fails. ?>
<p id="wishlist-message" class="alert alert-error" role="alert" hidden></p>

<div class="listing-grid">
    <?php foreach ($listings as $row): ?>
        <div class="wish-item">
            <?php require __DIR__ . '/../includes/listing-card.php'; ?>
            <button type="button" class="button button-secondary button-small"
                    data-wishlist-remove data-listing-id="<?= e((string) $row['id']) ?>">Remove</button>
        </div>
    <?php endforeach; ?>
</div>

<script src="/assets/js/wishlist.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
