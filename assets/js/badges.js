// Keeps the two numbers in the header up to date on every page, without a
// refresh: new notifications on the bell, and unread messages beside Inbox.
//
// It asks /api/notifications.php now and then, using the polling code in
// live.js. Much more slowly than a conversation page does: every 20 seconds,
// stretching to once a minute while nothing changes, and not at all while the tab
// is in the background. Every open tab of every student asks, so a slow pace
// keeps the work for the server small.
//
// Everything is inside { } so its names cannot clash with the page's own scripts.
{
    let lastCounts = null;

    const badgePoller = startPolling(async () => {
        let data;
        try {
            data = await apiGet('/api/notifications.php');
        } catch (error) {
            // 401: logged out (or banned) in the meantime. Asking again would only fail again.
            if (error.status === 401) {
                badgePoller.stop();
            }
            return false;
        }

        setInboxBadge(data.messages_unread);
        setNotificationBadge(data.notifications_unread);

        // Tell the polling code whether anything changed, so it knows whether to
        // ask again soon or slow down.
        const counts = data.notifications_unread + ':' + data.messages_unread;
        const changed = lastCounts !== null && counts !== lastCounts;
        lastCounts = counts;

        return changed;
    }, { shortestWait: 20000, longestWait: 60000 });
}
