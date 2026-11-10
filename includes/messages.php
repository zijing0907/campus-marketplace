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
 * Add a message to a conversation. The caller must already have checked that the
 * sender takes part in it and that the text is valid.
 */
function send_message(int $conversationId, int $senderId, string $body): void
{
    $stmt = db()->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$conversationId, $senderId, $body]);
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
