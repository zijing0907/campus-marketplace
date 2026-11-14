<?php
// The buyer of a sold item rates its seller (FR-20). The form is on the listing page.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}
verify_csrf();

$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$rating = post_text('rating');
$comment = post_text('comment');

$listing = null;
if (is_int($listingId) && $listingId > 0) {
    $stmt = db()->prepare('SELECT id, seller_id, buyer_id, status FROM listings WHERE id = ?');
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch() ?: null;
}

if ($listing === null) {
    set_flash('error', 'That listing does not exist.');
    redirect('/');
}

$listingUrl = '/pages/listing.php?id=' . $listing['id'];

// Who may rate: only the student the seller recorded as the buyer, and only once
// the item is sold. This is what stops people rating sellers they never bought
// from, or sellers rating themselves (NFR-04).
if ($listing['status'] !== 'sold' || (int) $listing['buyer_id'] !== (int) $user['id']) {
    set_flash('error', 'Only the buyer of a sold item can rate its seller.');
    redirect($listingUrl);
}

// The rating must be one of the five choices in the form.
if (!ctype_digit($rating) || !isset(RATING_LABELS[(int) $rating])) {
    set_flash('error', 'Choose a rating from 1 to 5.');
    redirect($listingUrl . '#rate');
}

if (mb_strlen($comment) > MAX_REVIEW_COMMENT) {
    set_flash('error', 'A comment can be at most ' . MAX_REVIEW_COMMENT . ' characters.');
    redirect($listingUrl . '#rate');
}

try {
    // The seller comes from the listing and the reviewer from the session.
    // Neither is taken from the form.
    $stmt = db()->prepare(
        'INSERT INTO reviews (listing_id, reviewer_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$listing['id'], $user['id'], $listing['seller_id'], (int) $rating, $comment !== '' ? $comment : null]);

    // Tell the seller. The stars go in "detail", so the bell can say how many.
    notify((int) $listing['seller_id'], NOTE_REVIEW_RECEIVED, (int) $user['id'], (int) $listing['id'], (string) (int) $rating);
} catch (PDOException $ex) {
    // Error 1062: UNIQUE (listing_id, reviewer_id) refused a second rating of the
    // same sale by the same buyer.
    if (($ex->errorInfo[1] ?? null) !== 1062) {
        throw $ex;
    }
    set_flash('error', 'You have already rated this seller for this item.');
    redirect($listingUrl);
}

set_flash('success', 'Thank you. Your rating has been added.');
redirect($listingUrl);
