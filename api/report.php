<?php
// POST /api/report.php
// A logged-in student reports a listing to the admins (FR-22).
//
// Form fields:  csrf_token, listing_id, reason (scam, prohibited, spam or other),
//               details (optional)
// Reply:        {"success": true, "data": {"message": "Thank you. ..."}}
// Called by assets/js/report.js from the listing page.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/api.php';

api_require_method('POST');
$user = api_require_login();
api_verify_csrf();

$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$reason = post_text('reason');
$details = post_text('details');

// 400: the request itself is wrong.
if (!is_int($listingId) || $listingId < 1 || !isset(REPORT_REASONS[$reason])) {
    api_error('Choose a reason for the report.', 400);
}
if (mb_strlen($details) > MAX_REPORT_DETAILS) {
    api_error('Details can be at most ' . MAX_REPORT_DETAILS . ' characters.', 400);
}

$stmt = db()->prepare(
    'SELECT l.id, l.seller_id, l.status, u.is_banned AS seller_is_banned
     FROM listings l
     JOIN users u ON u.id = l.seller_id
     WHERE l.id = ?'
);
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

// 404: no such listing. A hidden one, or one whose seller is banned, is treated
// the same way, as on its own page.
if (!$listing || $listing['status'] === 'hidden' || $listing['seller_is_banned']) {
    api_error('That listing does not exist.', 404);
}

// 403: the listing exists, but this user may not report it.
if ((int) $listing['seller_id'] === (int) $user['id']) {
    api_error('You cannot report your own listing.', 403);
}

// One open report per student per listing is enough for an admin to act on.
$stmt = db()->prepare("SELECT COUNT(*) FROM reports WHERE listing_id = ? AND reporter_id = ? AND status = 'open'");
$stmt->execute([$listingId, $user['id']]);
if ((int) $stmt->fetchColumn() > 0) {
    api_error('You have already reported this listing. An admin will review it.', 400);
}

// The reporter is the logged-in user, never a value from the form (NFR-04).
// A new report starts as "open" because that is the column's default.
$stmt = db()->prepare('INSERT INTO reports (listing_id, reporter_id, reason, details) VALUES (?, ?, ?, ?)');
$stmt->execute([$listingId, $user['id'], $reason, $details !== '' ? $details : null]);

api_success(['message' => 'Thank you. An admin will review this listing.']);
