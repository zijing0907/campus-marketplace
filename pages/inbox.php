<?php
// Inbox: all my conversations, as a buyer or as a seller, each with its latest
// message and how many messages I have not read yet (FR-18).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$me = (int) $user['id'];

// The query lives in includes/messages.php, because /api/conversations.php needs
// exactly the same list.
$conversations = inbox_conversations($me);

$pageTitle = 'Inbox';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Inbox</h1>
</div>

<?php // Both the list and the "no messages" note are always in the page, one of them
      // hidden. assets/js/inbox.js switches them when the first message arrives. ?>
<div class="empty" id="inbox-empty" <?= $conversations ? 'hidden' : '' ?>>
    <span class="empty-icon"><?= icon('message', 26) ?></span>
    <p><strong class="empty-title">No messages yet</strong>When you message a seller, or a buyer messages you, the conversation appears here.</p>
    <a class="button button-secondary" href="/">Browse listings</a>
</div>

<ul class="inbox" id="inbox-list" data-fingerprint="<?= e(inbox_fingerprint($conversations)) ?>" <?= $conversations ? '' : 'hidden' ?>>
    <?php foreach ($conversations as $conversation): ?>
        <?php
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
                        <span class="inbox-who"><?= e($conversation['other_name']) ?></span>
                        <?php if ($unread > 0): ?>
                            <span class="count-badge"><?= $unread ?> new</span>
                        <?php endif; ?>
                    </span>
                    <span class="inbox-listing">
                        <?= e($conversation['listing_title']) ?>
                        <?php if ($conversation['listing_status'] === 'sold'): ?>(sold)<?php endif; ?>
                    </span>
                    <span class="inbox-preview"><?= $fromMe ? 'You: ' : '' ?><?= e(message_preview($conversation['last_body'])) ?></span>
                </span>

                <span class="inbox-time"><?= e(format_message_time($conversation['last_sent_at'])) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php // inbox.js redraws this list using the polling code in live.js, which the header loads (FR-21). ?>
<script src="/assets/js/inbox.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
