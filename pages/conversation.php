<?php
// One conversation between a buyer and a seller about a listing (FR-17).
// Only those two people can open it or write in it.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// find_conversation() returns null both when the conversation does not exist and
// when this user is not part of it. Either way the answer is "not found".
$conversation = is_int($id) && $id > 0 ? find_conversation($id, (int) $user['id']) : null;

if ($conversation === null) {
    $notFoundMessage = 'This conversation does not exist.';
    require __DIR__ . '/../includes/not-found.php';
}

$conversationUrl = '/pages/conversation.php?id=' . $conversation['id'];
$error = null;
$body = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $body = post_text('body');
    $error = validate_message($body);

    if ($error === null) {
        // The sender is the logged-in user, never a value from the form.
        send_message((int) $conversation['id'], (int) $user['id'], $body);

        // Redirect after saving, so refreshing the page does not send the message
        // a second time. "#latest" makes the browser jump to the newest message.
        redirect($conversationUrl . '#latest');
    }
}

// Opening the conversation counts as reading it. This runs before the header is
// printed, so the unread number beside "Inbox" is already up to date.
mark_conversation_read((int) $conversation['id'], (int) $user['id']);

$stmt = db()->prepare('SELECT id, sender_id, body, sent_at FROM messages WHERE conversation_id = ? ORDER BY id');
$stmt->execute([$conversation['id']]);
$messages = $stmt->fetchAll();

// The other person is the seller if I am the buyer, and the buyer otherwise.
$iAmBuyer = (int) $conversation['buyer_id'] === (int) $user['id'];
$otherName = $iAmBuyer ? $conversation['seller_name'] : $conversation['buyer_name'];

$pageTitle = 'Conversation with ' . $otherName;
require __DIR__ . '/../includes/header.php';
?>

<section class="chat">
    <div class="chat-head">
        <a href="/pages/inbox.php">Back to inbox</a>

        <h1><?= e($otherName) ?></h1>
        <p class="note">
            About
            <a href="/pages/listing.php?id=<?= e((string) $conversation['listing_id']) ?>"><?= e($conversation['listing_title']) ?></a>
            · <?= e(format_price($conversation['listing_price'])) ?>
            <?php if ($conversation['listing_status'] === 'sold'): ?>
                · <span class="badge badge-sold">Sold</span>
            <?php endif; ?>
        </p>
    </div>

    <ol class="messages">
        <?php foreach ($messages as $message): ?>
            <?php $mine = (int) $message['sender_id'] === (int) $user['id']; ?>
            <li class="message <?= $mine ? 'message-mine' : 'message-theirs' ?>">
                <?php // e() first makes the text safe; nl2br() then keeps the line breaks. ?>
                <p><?= nl2br(e($message['body'])) ?></p>
                <time><?= $mine ? 'You' : e($otherName) ?> · <?= e(format_message_time($message['sent_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ol>

    <form id="latest" class="chat-form" method="post" action="<?= e($conversationUrl) ?>#latest">
        <?= csrf_field() ?>

        <label for="body" class="visually-hidden">Your message</label>
        <textarea id="body" name="body" rows="2" required maxlength="<?= MAX_MESSAGE_LENGTH ?>"
                  placeholder="Write a message"><?= e($body) ?></textarea>
        <?php if ($error !== null): ?>
            <p class="field-error"><?= e($error) ?></p>
        <?php endif; ?>

        <button type="submit" class="button">Send</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
