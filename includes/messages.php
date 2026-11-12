<?php
// Helpers for buyer-seller messaging (FR-17, FR-18).
//
// A conversation belongs to one listing and one buyer. The seller is whoever owns
// the listing. So "Aisyah asks Daniel about his desk" is one conversation, and a
// second buyer asking about the same desk gets a separate one.

// The longest message accepted, in characters.
const MAX_MESSAGE_LENGTH = 1000;

/**
 * Check a message's text. Returns an error message, or null if it is fine.
 */
function validate_message(string $body): ?string
{
    if ($body === '') {
        return 'Write a message first.';
    }

    if (mb_strlen($body) > MAX_MESSAGE_LENGTH) {
        return 'A message can be at most ' . MAX_MESSAGE_LENGTH . ' characters.';
    }

    return null;
}

/**
 * Load a conversation, but only if this user is its buyer or its seller.
 *
 * The rule is part of the query itself: for anyone else the query finds nothing
 * and this returns null, exactly as if the conversation did not exist. Pages then
 * answer "not found", so an outsider cannot even learn that two other people have
 * been talking (NFR-04).
 */
function find_conversation(int $conversationId, int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT c.id, c.listing_id, c.buyer_id, c.seller_id,
                l.title AS listing_title, l.price AS listing_price, l.status AS listing_status,
                l.buyer_id AS listing_buyer_id,
                buyer.name AS buyer_name, seller.name AS seller_name
         FROM conversations c
         JOIN listings l ON l.id = c.listing_id
         JOIN users buyer ON buyer.id = c.buyer_id
         JOIN users seller ON seller.id = c.seller_id
         WHERE c.id = ? AND (c.buyer_id = ? OR c.seller_id = ?)'
    );
    $stmt->execute([$conversationId, $userId, $userId]);

    return $stmt->fetch() ?: null;
}

/**
 * Add a message to a conversation and return the new message's id. The caller must
 * already have checked that the sender takes part in it and that the text is valid.
 */
function send_message(int $conversationId, int $senderId, string $body): int
{
    $stmt = db()->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$conversationId, $senderId, $body]);

    return (int) db()->lastInsertId();
}

/**
 * Mark everything the OTHER person wrote in this conversation as read.
 * Called when the user opens the conversation.
 */
function mark_conversation_read(int $conversationId, int $userId): void
{
    $stmt = db()->prepare(
        'UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0'
    );
    $stmt->execute([$conversationId, $userId]);
}

/**
 * How many messages are waiting unread for this user, across all conversations.
 * Shown beside "Inbox" in the header.
 */
function unread_message_count(int $userId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         WHERE (c.buyer_id = ? OR c.seller_id = ?)
           AND m.sender_id <> ?
           AND m.is_read = 0'
    );
    $stmt->execute([$userId, $userId, $userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Format the time a message was sent, for example "10 Nov, 2:15 PM".
 */
function format_message_time(string $sentAt): string
{
    return date('j M, g:i A', strtotime($sentAt));
}

/**
 * A message shortened to one line for the inbox: line breaks become spaces, and a
 * long message is cut off with "...".
 */
function message_preview(string $body): string
{
    $oneLine = trim(preg_replace('/\s+/', ' ', $body));

    return mb_strlen($oneLine) > 120 ? mb_substr($oneLine, 0, 120) . '…' : $oneLine;
}

/**
 * Everything the inbox shows for this user: all their conversations, as a buyer or
 * as a seller, newest first (FR-18). Used by the inbox page and by
 * /api/conversations.php, so both always show the same thing.
 *
 * One query builds the whole inbox. Its parts:
 *   "other"   the person I am talking to: the seller if I am the buyer, else the buyer
 *   "m"       each conversation's newest message: the one with the highest id.
 *             Joining on it also leaves out conversations that have no messages.
 *   unread    how many messages from the other person I have not opened yet
 *   cover     the listing's cover photo
 */
function inbox_conversations(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT c.id, c.listing_id,
                l.title AS listing_title, l.status AS listing_status,
                other.name AS other_name,
                m.id AS last_id, m.body AS last_body, m.sender_id AS last_sender_id, m.sent_at AS last_sent_at,
                (SELECT COUNT(*)
                 FROM messages u
                 WHERE u.conversation_id = c.id AND u.sender_id <> ? AND u.is_read = 0) AS unread_count,
                (SELECT i.file_path
                 FROM listing_images i
                 WHERE i.listing_id = l.id
                 ORDER BY i.sort_order, i.id
                 LIMIT 1) AS cover_path
         FROM conversations c
         JOIN listings l ON l.id = c.listing_id
         JOIN users other ON other.id = IF(c.buyer_id = ?, c.seller_id, c.buyer_id)
         JOIN messages m ON m.id = (SELECT MAX(id) FROM messages WHERE conversation_id = c.id)
         WHERE c.buyer_id = ? OR c.seller_id = ?
         ORDER BY m.id DESC'
    );
    $stmt->execute([$userId, $userId, $userId, $userId]);

    return $stmt->fetchAll();
}

/**
 * A short "fingerprint" of an inbox: for each conversation its id, the id of its
 * newest message and its unread count, for example "7:44:2,3:20:0".
 *
 * assets/js/inbox.js builds the same text from the API's reply. If the two differ,
 * something has changed and the list is drawn again; if not, the page is left alone.
 */
function inbox_fingerprint(array $conversations): string
{
    $parts = [];
    foreach ($conversations as $row) {
        $parts[] = (int) $row['id'] . ':' . (int) $row['last_id'] . ':' . (int) $row['unread_count'];
    }

    return implode(',', $parts);
}

/**
 * One inbox row as plain data for the JSON API. $row comes from inbox_conversations().
 */
function conversation_for_api(array $row, int $userId): array
{
    return [
        'id'              => (int) $row['id'],
        'url'             => '/pages/conversation.php?id=' . (int) $row['id'] . '#latest',
        'other_name'      => $row['other_name'],
        'listing_id'      => (int) $row['listing_id'],
        'listing_title'   => $row['listing_title'],
        'listing_status'  => $row['listing_status'],
        'cover_url'       => $row['cover_path'] !== null ? '/' . $row['cover_path'] : null,
        'last_message_id' => (int) $row['last_id'],
        'last_message'    => message_preview($row['last_body']),
        'last_from_me'    => (int) $row['last_sender_id'] === $userId,
        'last_sent_at'    => $row['last_sent_at'],
        'time_label'      => format_message_time($row['last_sent_at']),
        'unread_count'    => (int) $row['unread_count'],
    ];
}

/**
 * One message as plain data for the JSON API.
 * $message is a row from the messages table; $conversation comes from find_conversation().
 *
 * The text is sent exactly as it was written. Making it safe to show is the job of
 * whoever puts it on a page: PHP pages use e(), and the JavaScript uses textContent.
 */
function message_for_api(array $message, array $conversation, int $userId): array
{
    $senderId = (int) $message['sender_id'];
    $senderIsBuyer = $senderId === (int) $conversation['buyer_id'];

    return [
        'id'          => (int) $message['id'],
        'mine'        => $senderId === $userId,
        'sender_name' => $senderIsBuyer ? $conversation['buyer_name'] : $conversation['seller_name'],
        'body'        => $message['body'],
        'sent_at'     => $message['sent_at'],
        'time_label'  => format_message_time($message['sent_at']),
    ];
}
