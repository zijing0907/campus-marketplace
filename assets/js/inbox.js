// The inbox page, made live (FR-21): a new message moves its conversation to the
// top and shows the unread number, without refreshing the page.
// Needs live.js, which is loaded just before this file.

const inboxList = document.querySelector('#inbox-list');
const inboxEmpty = document.querySelector('#inbox-empty');

// A short "fingerprint" of what the list shows: for each conversation its id, the
// id of its newest message and its unread count, for example "7:44:2,3:20:0".
// PHP wrote the first one into the page (inbox_fingerprint() in messages.php).
let shownFingerprint = inboxList.dataset.fingerprint;

function fingerprintOf(conversations) {
    return conversations
        .map((conversation) => conversation.id + ':' + conversation.last_message_id + ':' + conversation.unread_count)
        .join(',');
}

// Make an element with a class and, optionally, some text. The text is set with
// textContent, so names, titles and messages written by students are always
// shown as plain text, never run as HTML.
function makeElement(tag, className, text) {
    const element = document.createElement(tag);
    element.className = className;
    if (text !== undefined) {
        element.textContent = text;
    }
    return element;
}

// Build one row of the inbox. It must match the HTML that pages/inbox.php prints.
function buildRow(conversation) {
    const unread = conversation.unread_count;

    const row = makeElement('a', 'inbox-row' + (unread > 0 ? ' inbox-row-unread' : ''));
    row.href = conversation.url;

    const photo = makeElement('span', 'inbox-photo');
    if (conversation.cover_url !== null) {
        const image = document.createElement('img');
        image.src = conversation.cover_url;
        image.alt = '';
        photo.append(image);
    }

    const name = makeElement('span', 'inbox-name', conversation.other_name);
    if (unread > 0) {
        name.append(' ', makeElement('span', 'count-badge', unread + ' new'));
    }

    const sold = conversation.listing_status === 'sold' ? ' (sold)' : '';
    const listing = makeElement('span', 'inbox-listing', conversation.listing_title + sold);

    const preview = makeElement(
        'span',
        'inbox-preview',
        (conversation.last_from_me ? 'You: ' : '') + conversation.last_message
    );

    const text = makeElement('span', 'inbox-text');
    text.append(name, listing, preview);

    row.append(photo, text, makeElement('span', 'inbox-time', conversation.time_label));

    const item = document.createElement('li');
    item.append(row);
    return item;
}

// One round of polling. Returns true if the inbox had changed.
async function refreshInbox() {
    let data;
    try {
        data = await apiGet('/api/conversations.php');
    } catch (error) {
        // 401: logged out (or banned). Asking again would only fail again.
        if (error.status === 401) {
            inboxPoller.stop();
        }
        return false;
    }

    setInboxBadge(data.unread_total);

    const fingerprint = fingerprintOf(data.conversations);
    if (fingerprint === shownFingerprint) {
        return false;   // nothing changed, so the page is left exactly as it is
    }
    shownFingerprint = fingerprint;

    // Someone using the keyboard may be on one of the rows. Remember which, so
    // the redraw does not throw them back to the top of the page.
    const focused = inboxList.contains(document.activeElement) ? document.activeElement.getAttribute('href') : null;

    // replaceChildren() swaps the whole list in one step.
    inboxList.replaceChildren(...data.conversations.map(buildRow));
    inboxList.hidden = data.conversations.length === 0;
    inboxEmpty.hidden = data.conversations.length > 0;

    if (focused !== null) {
        const sameRow = [...inboxList.querySelectorAll('a')].find((row) => row.getAttribute('href') === focused);
        if (sameRow) {
            sameRow.focus();
        }
    }

    return true;
}

const inboxPoller = startPolling(refreshInbox);
