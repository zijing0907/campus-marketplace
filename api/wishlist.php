<?php
// POST /api/wishlist.php
// Save a listing to my wishlist, or remove it if it is already there (FR-19).
// One endpoint does both: it is a toggle.
//
// Form fields:  csrf_token, listing_id
// Reply:        {"success": true, "data": {"listing_id": 12, "saved": true}}
//               "saved" says what is true NOW, after the request.
// Called by assets/js/wishlist.js.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/api.php';

api_require_method('POST');
$user = api_require_login();
api_verify_csrf();

$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);

if (!is_int($listingId) || $listingId < 1) {
    api_error('Send a listing id.', 400);
}

// Already saved? Then this request removes it. Removing is always allowed,
// whatever has happened to the listing since it was saved.
$stmt = db()->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ? AND listing_id = ?');
$stmt->execute([$user['id'], $listingId]);

if ((int) $stmt->fetchColumn() > 0) {
    $stmt = db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND listing_id = ?');
    $stmt->execute([$user['id'], $listingId]);

    api_success(['listing_id' => $listingId, 'saved' => false]);
}

// Not saved yet. It can be added only if this student could see and buy it.
$stmt = db()->prepare(
    'SELECT l.id, l.seller_id, l.status, u.is_banned AS seller_is_banned
     FROM listings l
     JOIN users u ON u.id = l.seller_id
     WHERE l.id = ?'
);
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

// 404: no such listing, or one that is hidden from this student.
if (!$listing || $listing['status'] === 'hidden' || $listing['seller_is_banned']) {
    api_error('That listing does not exist.', 404);
}

// 403: it exists, but saving your own listing makes no sense.
if ((int) $listing['seller_id'] === (int) $user['id']) {
    api_error('You cannot save your own listing.', 403);
}

// 400: there is nothing left to buy.
if ($listing['status'] !== 'available') {
    api_error('This item has already been sold.', 400);
}

try {
    // The user is the logged-in student, never a value from the form (NFR-04).
    $stmt = db()->prepare('INSERT INTO wishlists (user_id, listing_id) VALUES (?, ?)');
    $stmt->execute([$user['id'], $listingId]);
} catch (PDOException $ex) {
    // Error 1062: the row already exists. The table's primary key is
    // (user_id, listing_id), so a double click cannot save a listing twice.
    // Either way the listing is now saved, which is what was asked for.
    if (($ex->errorInfo[1] ?? null) !== 1062) {
        throw $ex;
    }
}

api_success(['listing_id' => $listingId, 'saved' => true]);
