<?php
// Inbox: all my conversations, as a buyer or as a seller, each with its latest
// message and how many messages I have not read yet (FR-18).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$me = (int) $user['id'];

// One query builds the whole inbox. Its parts:
//   "other"   the person I am talking to: the seller if I am the buyer, else the buyer
//   "m"       each conversation's newest message: the one with the highest id.
//             Joining on it also leaves out conversations that have no messages.
//   unread    how many messages from the other person I have not opened yet
//   cover     the listing's cover photo
$stmt = db()->prepare(
    'SELECT c.id, c.listing_id,
            l.title AS listing_title, l.status AS listing_status,
            other.name AS other_name,
            m.body AS last_body, m.sender_id AS last_sender_id, m.sent_at AS last_sent_at,
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
$stmt->execute([$me, $me, $me, $me]);
$conversations = $stmt->fetchAll();

$pageTitle = 'Inbox';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Inbox</h1>
</div>

<?php if (!$conversations): ?>
    <p class="empty">No messages yet. When you message a seller, or a buyer messages you, the conversation appears here.</p>
<?php else: ?>
    <ul class="inbox">
        <?php foreach ($conversations as $conversation): ?>
            <?php
            // Line breaks would look odd in a one-line preview, so turn them into spaces.
            $preview = preg_replace('/\s+/', ' ', $conversation['last_body']);
            $fromMe = (int) $conversation['last_sender_id'] === $me;
            $unread = (int) $conversation['unread_count'];
            ?>
            <li>
                <a class="inbox-row <?= $unread > 0 ? 'inbox-row-unread' : '' ?>"
                   href="/pages/conversation.php?id=<?= e((string) $conversation['id']) ?>#latest">
                    <span class="inbox-photo">
                        <?php if ($conversation['cover_path'] !== null): ?>
                            <img src="/<?= e($conversation['cover_path']) ?>" alt="">
                        <?php endif; ?>
                    </span>

                    <span class="inbox-text">
                        <span class="inbox-name">
                            <?= e($conversation['other_name']) ?>
                            <?php if ($unread > 0): ?>
                                <span class="count-badge"><?= $unread ?> new</span>
                            <?php endif; ?>
                        </span>
                        <span class="inbox-listing">
                            <?= e($conversation['listing_title']) ?>
                            <?php if ($conversation['listing_status'] === 'sold'): ?>(sold)<?php endif; ?>
                        </span>
                        <span class="inbox-preview"><?= $fromMe ? 'You: ' : '' ?><?= e($preview) ?></span>
                    </span>

                    <span class="inbox-time"><?= e(format_message_time($conversation['last_sent_at'])) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
