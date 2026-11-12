<?php
// GET  /api/messages.php?conversation_id=7&after_id=42
//      The messages in one of my conversations that are newer than message 42.
//      The conversation page asks this every few seconds, which is how new
//      messages appear without refreshing (FR-21). Leave after_id out to get all.
//      Reply: {"success": true, "data": {"messages": [...], "unread_total": 0}}
//
// POST /api/messages.php
//      Send a message in one of my conversations (FR-17).
//      Form fields: csrf_token, conversation_id, body
//      Reply: {"success": true, "data": {"message": {...}}}
//
// Each message looks like this:
//      {"id": 43, "mine": false, "sender_name": "Daniel Lim", "body": "See you at 3pm",
//       "sent_at": "2026-11-12 15:03:00", "time_label": "12 Nov, 3:03 PM"}
//
// Called by assets/js/conversation.js.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/api.php';

api_require_method('GET', 'POST');
$user = api_require_login();
$me = (int) $user['id'];

$sending = $_SERVER['REQUEST_METHOD'] === 'POST';

// Only the request that changes something needs the CSRF check. Reading sends
// nothing an attacker could misuse: another site cannot see the reply.
if ($sending) {
    api_verify_csrf();
}

// 400: the request itself is wrong, whoever sent it.
$conversationId = filter_input($sending ? INPUT_POST : INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT);

if (!is_int($conversationId) || $conversationId < 1) {
    api_error('Send a conversation id.', 400);
}

// 404: there is no such conversation, or I am not its buyer or its seller.
// find_conversation() makes no difference between the two, on purpose: an outsider
// must not even learn that two other students have been talking (NFR-04).
$conversation = find_conversation($conversationId, $me);

if ($conversation === null) {
    api_error('That conversation does not exist.', 404);
}

if ($sending) {
    $body = post_text('body');
    $error = validate_message($body);

    if ($error !== null) {
        api_error($error, 400);
    }

    // The sender is the logged-in user, never a value from the form.
    $messageId = send_message($conversationId, $me, $body);

    // Read the new row back, because the database filled in the time it was sent.
    $stmt = db()->prepare('SELECT id, sender_id, body, sent_at FROM messages WHERE id = ?');
    $stmt->execute([$messageId]);

    api_success(['message' => message_for_api($stmt->fetch(), $conversation, $me)]);
}

// From here on: reading. after_id is optional; without it every message is sent.
$afterId = 0;

if (isset($_GET['after_id']) && $_GET['after_id'] !== '') {
    $afterId = filter_input(INPUT_GET, 'after_id', FILTER_VALIDATE_INT);

    if (!is_int($afterId) || $afterId < 0) {
        api_error('after_id must be the id of a message, or 0.', 400);
    }
}

// The index on (conversation_id, id) lets the database jump straight to the new
// messages instead of reading the whole conversation each time.
$stmt = db()->prepare(
    'SELECT id, sender_id, body, sent_at
     FROM messages
     WHERE conversation_id = ? AND id > ?
     ORDER BY id'
);
$stmt->execute([$conversationId, $afterId]);
$rows = $stmt->fetchAll();

$messages = [];
$fromOtherPerson = false;

foreach ($rows as $row) {
    $messages[] = message_for_api($row, $conversation, $me);

    if ((int) $row['sender_id'] !== $me) {
        $fromOtherPerson = true;
    }
}

// Messages from the other person are now on my screen, so they count as read,
// exactly as when the conversation page is opened.
if ($fromOtherPerson) {
    mark_conversation_read($conversationId, $me);
}

// unread_total is what is still waiting in my OTHER conversations. The page uses
// it to keep the number beside "Inbox" in the header up to date.
api_success([
    'messages'     => $messages,
    'unread_total' => unread_message_count($me),
]);
