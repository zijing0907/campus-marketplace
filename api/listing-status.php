<?php
// POST /api/listing-status.php
// Mark one of my listings as sold, or put it back as available (FR-09).
//
// Form fields:  csrf_token, id, status ("sold" or "available")
// Reply:        {"success": true, "data": {"id": 12, "status": "sold"}}
// Called by assets/js/my-listings.js.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/api.php';

api_require_method('POST');
$user = api_require_login();
api_verify_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = post_text('status');

// 400: the request itself is wrong, whoever sent it.
if (!is_int($id) || $id < 1 || !in_array($status, ['available', 'sold'], true)) {
    api_error('Send a listing id and a status of "available" or "sold".', 400);
}

$stmt = db()->prepare('SELECT id, seller_id, status FROM listings WHERE id = ?');
$stmt->execute([$id]);
$listing = $stmt->fetch();

// 404: there is no such listing.
if (!$listing) {
    api_error('That listing does not exist.', 404);
}

// 403: the listing exists, but this user may not change it (FR-08, NFR-04).
if ((int) $listing['seller_id'] !== (int) $user['id']) {
    api_error('You can only change your own listings.', 403);
}

// A listing an admin has hidden stays hidden. Otherwise a seller could undo the
// admin's decision just by marking it available again.
if ($listing['status'] === 'hidden') {
    api_error('This listing was hidden by an admin, so its status cannot be changed.', 403);
}

// buyer_id is cleared here. Recording who bought the item is added with messaging.
$stmt = db()->prepare('UPDATE listings SET status = ?, buyer_id = NULL WHERE id = ? AND seller_id = ?');
$stmt->execute([$status, $id, $user['id']]);

api_success(['id' => $id, 'status' => $status]);
