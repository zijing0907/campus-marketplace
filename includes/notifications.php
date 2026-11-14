<?php
// Notifications: the bell in the header.
//
// Something happens (a buyer rates a seller, an admin hides a listing) and the
// page that made it happen calls notify(). That stores one row in the
// notifications table for the student it concerns. The bell then shows how many
// are unread, and pages/notifications.php lists them.
//
// New messages are not notifications: the number beside "Inbox" already counts
// them, and listing each message here as well would count it twice.

// What happened. The text for each one is in notification_view() below.
const NOTE_LISTING_SAVED = 'listing_saved';          // to a seller: someone saved their listing
const NOTE_REVIEW_RECEIVED = 'review_received';      // to a seller: a buyer rated them
const NOTE_SOLD_TO_YOU = 'sold_to_you';              // to a buyer: the seller recorded them as the buyer
const NOTE_LISTING_HIDDEN = 'listing_hidden';        // to a seller: an admin hid their listing
const NOTE_LISTING_RESTORED = 'listing_restored';    // to a seller: an admin showed it again
const NOTE_SAVED_SOLD = 'saved_listing_sold';        // to everyone who saved a listing that sold
const NOTE_SAVED_PRICE_DROP = 'saved_price_drop';    // to everyone who saved a listing that got cheaper

// How many the notifications page shows, and how long they are kept, in days.
const NOTIFICATIONS_SHOWN = 50;
const NOTIFICATIONS_KEPT_DAYS = 90;

/**
 * Tell one student that something happened.
 * $actorId is the student who caused it, if any; nobody is told about their own action.
 */
function notify(int $userId, string $type, ?int $actorId = null, ?int $listingId = null, ?string $detail = null): void
{
    if ($actorId === $userId) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, type, actor_id, listing_id, detail) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $type, $actorId, $listingId, $detail]);
}

/**
 * Tell a seller that someone saved their listing, once per student and listing.
 * Without the "once", saving and un-saving again and again would flood them.
 */
function notify_listing_saved(int $sellerId, int $saverId, int $listingId): void
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ? AND actor_id = ? AND listing_id = ?'
    );
    $stmt->execute([$sellerId, NOTE_LISTING_SAVED, $saverId, $listingId]);

    if ((int) $stmt->fetchColumn() === 0) {
        notify($sellerId, NOTE_LISTING_SAVED, $saverId, $listingId);
    }
}

/**
 * Tell everyone who saved this listing that something happened to it, except the
 * students in $except (such as its seller, or the buyer who already knows).
 * One query adds a row for each of them.
 */
function notify_savers(int $listingId, string $type, array $except, ?string $detail = null): void
{
    $except = array_values(array_unique(array_map('intval', $except))) ?: [0];
    $marks = implode(',', array_fill(0, count($except), '?'));

    $stmt = db()->prepare(
        "INSERT INTO notifications (user_id, type, listing_id, detail)
         SELECT w.user_id, ?, w.listing_id, ?
         FROM wishlists w
         WHERE w.listing_id = ? AND w.user_id NOT IN ($marks)"
    );
    $stmt->execute(array_merge([$type, $detail, $listingId], $except));
}

/**
 * Who sells this listing. Used when a notification goes to a listing's seller.
 */
function listing_seller_id(int $listingId): int
{
    $stmt = db()->prepare('SELECT seller_id FROM listings WHERE id = ?');
    $stmt->execute([$listingId]);

    return (int) $stmt->fetchColumn();
}

/**
 * How many notifications this student has not seen yet. Shown on the bell.
 */
function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * This student's newest notifications, with the names and titles they mention.
 * The names are looked up now, not stored, so they are always current.
 */
function user_notifications(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT n.id, n.type, n.listing_id, n.detail, n.is_read, n.created_at,
                actor.name AS actor_name, l.title AS listing_title
         FROM notifications n
         LEFT JOIN users actor ON actor.id = n.actor_id
         LEFT JOIN listings l ON l.id = n.listing_id
         WHERE n.user_id = ?
         ORDER BY n.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, NOTIFICATIONS_SHOWN, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Mark all of this student's notifications as seen, and clear away old ones.
 */
function mark_notifications_read(int $userId): void
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);

    $stmt = db()->prepare('DELETE FROM notifications WHERE user_id = ? AND created_at < NOW() - INTERVAL ? DAY');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, NOTIFICATIONS_KEPT_DAYS, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * The sentence and the link for one notification, for example
 * ['text' => 'Ben Tan rated you 5 out of 5 for “Study desk”.', 'url' => '/pages/user.php?id=3#ratings'].
 *
 * The text is plain text that contains names and titles students typed, so the
 * page must escape it with e(), like everything else.
 */
function notification_view(array $row, int $userId): array
{
    $title = '“' . ($row['listing_title'] ?? 'a listing') . '”';
    // If the student who did it has since been removed completely, say "Someone".
    $who = $row['actor_name'] ?? 'Someone';
    $listingUrl = '/pages/listing.php?id=' . (int) $row['listing_id'];

    switch ($row['type']) {
        case NOTE_LISTING_SAVED:
            // Who saved it stays private: a student may not want the seller to know yet.
            return ['text' => "Someone saved your listing $title.", 'url' => $listingUrl];

        case NOTE_REVIEW_RECEIVED:
            return [
                'text' => "$who rated you " . (int) $row['detail'] . " out of 5 for $title.",
                'url'  => '/pages/user.php?id=' . $userId . '#ratings',
            ];

        case NOTE_SOLD_TO_YOU:
            return ['text' => "$who marked $title as sold to you. You can now rate the seller.", 'url' => $listingUrl . '#rate'];

        case NOTE_LISTING_HIDDEN:
            return ['text' => "An admin hid your listing $title after a report.", 'url' => $listingUrl];

        case NOTE_LISTING_RESTORED:
            return ['text' => "An admin made your listing $title visible again.", 'url' => $listingUrl];

        case NOTE_SAVED_SOLD:
            return ['text' => "$title, which you saved, has been sold.", 'url' => $listingUrl];

        case NOTE_SAVED_PRICE_DROP:
            // detail holds "old|new", for example "85.00|70.00".
            [$old, $new] = array_pad(explode('|', (string) $row['detail']), 2, '0');
            return [
                'text' => "$title, which you saved, is now " . format_price($new) . ' (was ' . format_price($old) . ').',
                'url'  => $listingUrl,
            ];
    }

    // A type this version of the site does not know. It should never happen.
    return ['text' => 'Something happened on your account.', 'url' => '/pages/notifications.php'];
}
