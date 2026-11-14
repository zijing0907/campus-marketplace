<?php
// GET /api/notifications.php
// The two numbers in the header: unread notifications (the bell) and unread
// messages (Inbox). assets/js/badges.js asks for them now and then, so both stay
// up to date on every page without a refresh.
//
// Reply: {"success": true, "data": {"notifications_unread": 2, "messages_unread": 1}}
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/api.php';

api_require_method('GET');
$user = api_require_login();

api_success([
    'notifications_unread' => unread_notification_count((int) $user['id']),
    'messages_unread'      => unread_message_count((int) $user['id']),
]);
