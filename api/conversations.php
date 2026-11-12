<?php
// GET /api/conversations.php
// My inbox: every conversation I take part in, newest first, each with its latest
// message and how many messages I have not read yet (FR-18).
//
// Reply: {"success": true, "data": {"conversations": [...], "unread_total": 3}}
//
// Each conversation looks like this:
//   {"id": 7, "url": "/pages/conversation.php?id=7#latest", "other_name": "Daniel Lim",
//    "listing_id": 12, "listing_title": "Study desk", "listing_status": "available",
//    "cover_url": "/uploads/listings/abc.jpg", "last_message_id": 44,
//    "last_message": "See you at 3pm", "last_from_me": false,
//    "last_sent_at": "2026-11-12 15:03:00", "time_label": "12 Nov, 3:03 PM", "unread_count": 2}
//
// Called by assets/js/inbox.js, which uses it to keep the inbox page up to date
// without refreshing (FR-21).
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/api.php';

api_require_method('GET');
$user = api_require_login();
$me = (int) $user['id'];

// The same function the inbox page uses, so the two can never disagree.
$rows = inbox_conversations($me);

$conversations = [];
$unreadTotal = 0;

foreach ($rows as $row) {
    $conversations[] = conversation_for_api($row, $me);
    $unreadTotal += (int) $row['unread_count'];
}

api_success([
    'conversations' => $conversations,
    'unread_total'  => $unreadTotal,
]);
