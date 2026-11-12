<?php
// Delete one of my listings, together with its photos (FR-08).
// POST only, with a CSRF token: deleting must never happen from a plain link.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/my-listings.php');
}
verify_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$listing = null;
if (is_int($id) && $id > 0) {
    $stmt = db()->prepare('SELECT id, seller_id FROM listings WHERE id = ?');
    $stmt->execute([$id]);
    $listing = $stmt->fetch() ?: null;
}

if ($listing === null) {
    set_flash('error', 'That listing does not exist. It may already have been deleted.');
    redirect('/pages/my-listings.php');
}

// The ownership check: only the seller may delete a listing (FR-08, NFR-04).
if ((int) $listing['seller_id'] !== (int) $user['id']) {
    $forbiddenMessage = 'You can only delete your own listings.';
    require __DIR__ . '/../includes/forbidden.php';
}

// A listing whose buyer has rated the seller cannot be deleted. Deleting it would
// delete the rating with it, and a seller could then erase any rating they did not
// like (FR-20).
$stmt = db()->prepare('SELECT COUNT(*) FROM reviews WHERE listing_id = ?');
$stmt->execute([$listing['id']]);
if ((int) $stmt->fetchColumn() > 0) {
    set_flash('error', 'This listing has a rating from its buyer, so it cannot be deleted.');
    redirect('/pages/my-listings.php');
}

// Note the photo files before deleting, because the rows that name them are
// about to disappear.
$stmt = db()->prepare('SELECT file_path FROM listing_images WHERE listing_id = ?');
$stmt->execute([$listing['id']]);
$photoPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Deleting the listing row also deletes its photo rows, and later its messages,
// wishlist entries and reports, because their foreign keys say ON DELETE CASCADE.
$stmt = db()->prepare('DELETE FROM listings WHERE id = ? AND seller_id = ?');
$stmt->execute([$listing['id'], $user['id']]);

// The database only knew the file names. The files themselves are removed here,
// otherwise they would stay on the server forever.
foreach ($photoPaths as $path) {
    delete_uploaded_file($path);
}

set_flash('success', 'Your listing has been deleted.');
redirect('/pages/my-listings.php');
