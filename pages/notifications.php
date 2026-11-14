<?php
// My notifications: everything behind the bell in the header, newest first.
// Opening this page counts as reading them all.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$me = (int) $user['id'];

// Read the list first, so the ones that were new can still be shown as new on
// this visit. Only then mark them as read: that also runs before the header is
// printed, so the bell already shows 0.
$notifications = user_notifications($me);
mark_notifications_read($me);

$pageTitle = 'Notifications';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Notifications</h1>
</div>

<?php if (!$notifications): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('bell', 26) ?></span>
        <p><strong class="empty-title">Nothing new</strong>You will see here when someone saves or buys your items,
           rates you, or when an item you saved is sold or gets cheaper.</p>
        <a class="button button-secondary" href="/">Browse listings</a>
    </div>
<?php else: ?>
    <ul class="notifications">
        <?php foreach ($notifications as $row): ?>
            <?php $view = notification_view($row, $me); ?>
            <li>
                <a class="notification <?= $row['is_read'] ? '' : 'notification-new' ?>" href="<?= e($view['url']) ?>">
                    <span class="notification-icon notification-icon-<?= e($view['icon']) ?>"><?= icon($view['icon'], 18) ?></span>
                    <?php if (!$row['is_read']): ?>
                        <span class="visually-hidden">New: </span>
                    <?php endif; ?>
                    <span class="notification-text"><?= e($view['text']) ?></span>
                    <span class="notification-time"><?= e(format_message_time($row['created_at'])) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
