// Shared by the conversation page (conversation.js) and the inbox (inbox.js):
// talking to the JSON API, checking for news again and again ("polling"), and
// the unread number beside "Inbox" in the header (FR-21).

// Send a request to the API and return the "data" part of its reply.
// If anything goes wrong this throws an Error whose message can be shown to the
// user. error.status is the HTTP status code, or 0 if the server was not reached.
async function apiRequest(url, options) {
    let response;
    try {
        response = await fetch(url, options);
    } catch (error) {
        // fetch() itself fails when the server cannot be reached.
        const problem = new Error('Could not reach the server. Check your connection and try again.');
        problem.status = 0;
        throw problem;
    }

    const reply = await response.json().catch(() => null);
    if (!reply || !reply.success) {
        const problem = new Error(reply ? reply.error : 'The server sent an unexpected reply. Please try again.');
        problem.status = response.status;
        throw problem;
    }

    return reply.data;
}

// Read from the API.
function apiGet(url) {
    return apiRequest(url, { headers: { Accept: 'application/json' } });
}

// Send a form to the API. "fields" is an object such as { body: 'Hello' }.
// The CSRF token is added here, so no caller can forget it.
function apiPost(url, fields) {
    const body = new FormData();
    body.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    for (const name in fields) {
        body.append(name, fields[name]);
    }

    return apiRequest(url, { method: 'POST', body: body });
}

// Show "count" in the small red pill on every link that matches "selector", or
// take the pill away when the count is 0. "words" is what screen readers hear
// after the number, such as " unread".
function setCountBadge(selector, count, words) {
    document.querySelectorAll(selector).forEach((link) => {
        let badge = link.querySelector('.count-badge');

        if (count < 1) {
            if (badge) {
                badge.remove();
            }
            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'count-badge';
            link.append(' ', badge);
        }

        // The same two parts the PHP header prints: the number for the eye, and
        // the words for screen readers.
        const forScreenReaders = document.createElement('span');
        forScreenReaders.className = 'visually-hidden';
        forScreenReaders.textContent = words;

        badge.textContent = count;
        badge.append(forScreenReaders);
    });
}

// The number beside "Inbox". The header has two Inbox links (one in the menu,
// and a shortcut that stays visible on phones), so both are updated.
function setInboxBadge(count) {
    setCountBadge('[data-inbox-link]', count, ' unread');
}

// The number on the bell, and the red dot on the phone's Menu button.
function setNotificationBadge(count) {
    setCountBadge('[data-notification-link]', count, ' new');

    const dot = document.querySelector('[data-notification-dot]');
    if (dot) {
        dot.hidden = count < 1;
    }
}

// Call check() again and again for as long as the page is open.
//
// check() does one round: it asks the server and updates the page. It returns a
// promise for true if it found something new, false if nothing had changed.
//
// Asking costs the server work every time, so three rules keep it low:
//   1. The next round is planned only after the last one has finished
//      (setTimeout, not setInterval), so slow replies can never pile up.
//   2. While nothing new arrives, the wait grows step by step from 3 seconds to
//      15 (or whatever "options" asks for). Anything new brings it straight back.
//   3. While the tab is in the background nothing is asked at all. The moment
//      the user comes back, one round runs at once.
//
// Returns an object with two functions: now() runs a round immediately, and
// stop() ends the polling for good.
function startPolling(check, options = {}) {
    // In milliseconds. A page can ask for slower polling, as badges.js does.
    const SHORTEST_WAIT = options.shortestWait ?? 3000;
    const LONGEST_WAIT = options.longestWait ?? 15000;

    let wait = SHORTEST_WAIT;
    let timer = null;       // the planned next round, if there is one
    let running = false;    // true while a round is waiting for the server
    let runAgain = false;   // someone asked for a round while one was running
    let stopped = false;

    function planNext() {
        if (!stopped && !document.hidden && timer === null) {
            timer = setTimeout(round, wait);
        }
    }

    function cancelPlanned() {
        clearTimeout(timer);
        timer = null;
    }

    async function round() {
        // Never two rounds at the same time: remember the wish and run it after.
        if (running) {
            runAgain = true;
            return;
        }

        running = true;
        cancelPlanned();

        let foundNew = false;
        try {
            foundNew = await check();
        } catch (error) {
            // One failed round is not the end: the next one simply tries again.
        }
        running = false;

        if (stopped) {
            return;
        }

        if (runAgain) {
            runAgain = false;
            wait = SHORTEST_WAIT;
            round();
            return;
        }

        wait = foundNew ? SHORTEST_WAIT : Math.min(wait * 1.5, LONGEST_WAIT);
        planNext();
    }

    document.addEventListener('visibilitychange', () => {
        if (stopped) {
            return;
        }

        if (document.hidden) {
            cancelPlanned();
        } else {
            wait = SHORTEST_WAIT;
            round();
        }
    });

    planNext();

    return {
        now() {
            if (!stopped) {
                wait = SHORTEST_WAIT;
                round();
            }
        },
        stop() {
            stopped = true;
            cancelPlanned();
        },
    };
}
