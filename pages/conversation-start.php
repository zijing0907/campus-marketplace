<?php
// A buyer sends the first message about a listing (FR-17).
// This creates the conversation if there is not one yet, adds the message, and
// then shows the conversation. The form is on the listing page.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}
verify_csrf();

$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$body = post_text('body');

$listing = null;
if (is_int($listingId) && $listingId > 0) {
    $stmt = db()->prepare('SELECT id, seller_id, status FROM listings WHERE id = ?');
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch() ?: null;
}

// A hidden listing is treated like one that does not exist, as on its own page.
if ($listing === null || $listing['status'] === 'hidden') {
    set_flash('error', 'That listing does not exist or has been removed.');
    redirect('/');
}

$listingUrl = '/pages/listing.php?id=' . $listing['id'];

if ((int) $listing['seller_id'] === (int) $user['id']) {
    set_flash('error', 'You cannot message yourself about your own listing.');
    redirect($listingUrl);
}

$error = validate_message($body);
if ($error !== null) {
    set_flash('error', $error);
    redirect($listingUrl);
}

// Is there already a conversation between this buyer and this listing?
$find = db()->prepare('SELECT id FROM conversations WHERE listing_id = ? AND buyer_id = ?');
$find->execute([$listing['id'], $user['id']]);
$conversationId = $find->fetchColumn();

if ($conversationId === false) {
    // A new conversation can only be started while the item is still for sale.
    if ($listing['status'] !== 'available') {
        set_flash('error', 'This item is no longer available.');
        redirect($listingUrl);
    }

    try {
        // The buyer is the logged-in user and the seller is the listing's owner.
        // Neither comes from the form, so they cannot be faked (NFR-04).
        $stmt = db()->prepare('INSERT INTO conversations (listing_id, buyer_id, seller_id) VALUES (?, ?, ?)');
        $stmt->execute([$listing['id'], $user['id'], $listing['seller_id']]);
        $conversationId = db()->lastInsertId();
    } catch (PDOException $ex) {
        // Error 1062: the UNIQUE (listing_id, buyer_id) rule refused a duplicate.
        // That happens if the form is submitted twice at the same moment. The
        // other request already created the conversation, so just use that one.
        if (($ex->errorInfo[1] ?? null) !== 1062) {
            throw $ex;
        }
        $find->execute([$listing['id'], $user['id']]);
        $conversationId = $find->fetchColumn();
    }
}

send_message((int) $conversationId, (int) $user['id'], $body);

redirect('/pages/conversation.php?id=' . $conversationId . '#latest');
