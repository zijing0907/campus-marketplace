// The conversation page, made live (FR-21): new messages appear without
// refreshing, and sending a message does not reload the page.
//
// Without JavaScript the page still works: the form is a normal form that posts
// to conversation.php. This script only takes over when it is running.
// Needs live.js, which is loaded just before this file.

const messageList = document.querySelector('#messages');
const chatForm = document.querySelector('#latest');
const chatInput = chatForm.querySelector('textarea');
const sendButton = chatForm.querySelector('button');
const chatError = document.querySelector('#chat-error');

const conversationId = messageList.dataset.conversationId;

// The id of the newest message on the page. Every request asks only for
// messages after this one, so the server never sends the same message twice.
let lastMessageId = Number(messageList.dataset.lastId);

function showChatError(text) {
    chatError.textContent = text;
    chatError.hidden = text === '';
}

// Put one message from the API on the page, as a bubble like the ones PHP printed.
function addMessage(message) {
    // Already on the page? Then leave it. This makes it harmless if the same
    // message ever arrives twice.
    if (message.id <= lastMessageId) {
        return;
    }
    lastMessageId = message.id;

    const bubble = document.createElement('li');
    bubble.className = 'message ' + (message.mine ? 'message-mine' : 'message-theirs');

    // The text was written by a student, so it goes on the page as TEXT, never
    // as HTML. Adding a string with append() does exactly that: a message
    // such as "<b>hi</b>" shows those very characters instead of bold text.
    // Line breaks are the one thing kept: each new line gets a <br>.
    const text = document.createElement('p');
    message.body.split(/\r?\n/).forEach((line, index) => {
        if (index > 0) {
            text.append(document.createElement('br'));
        }
        text.append(line);
    });

    const time = document.createElement('time');
    time.textContent = (message.mine ? 'You' : message.sender_name) + ' · ' + message.time_label;

    bubble.append(text, time);
    messageList.append(bubble);
}

// One round of polling: ask for anything newer than what the page shows.
// Returns true if something new arrived.
async function loadNewMessages() {
    let data;
    try {
        data = await apiGet('/api/messages.php?conversation_id=' + conversationId + '&after_id=' + lastMessageId);
    } catch (error) {
        // 401: logged out (or banned). 404: the conversation is gone. Asking
        // again would only fail again, so stop and tell the user.
        // Anything else, such as a lost connection, is simply tried again later.
        if (error.status === 401 || error.status === 404) {
            poller.stop();
            showChatError(error.message + ' Refresh the page to continue.');
            sendButton.disabled = true;
        }
        return false;
    }

    // If the message box is on screen, the user is at the end of the
    // conversation and should stay there. If they scrolled up to read older
    // messages, the page must not jump away under them.
    const atTheEnd = chatForm.getBoundingClientRect().top < window.innerHeight;
    const before = lastMessageId;
    let sentByMe = false;

    data.messages.forEach((message) => {
        if (message.id > before && message.mine) {
            sentByMe = true;
        }
        addMessage(message);
    });

    setInboxBadge(data.unread_total);

    const foundNew = lastMessageId > before;
    if (foundNew && (atTheEnd || sentByMe)) {
        chatForm.scrollIntoView({ block: 'end' });
    }

    return foundNew;
}

const poller = startPolling(loadNewMessages);

chatForm.addEventListener('submit', async (event) => {
    // Stop the browser from sending the form itself, which would reload the page.
    event.preventDefault();

    sendButton.disabled = true;
    showChatError('');

    try {
        await apiPost('/api/messages.php', { conversation_id: conversationId, body: chatInput.value });
        chatInput.value = '';

        // The new message is not added to the page here. Instead the page asks
        // for everything new straight away. That brings back my message together
        // with anything the other person wrote in the meantime, in the right order.
        poller.now();
    } catch (error) {
        // The text stays in the box, so nothing the user typed is lost.
        showChatError(error.message);
    } finally {
        sendButton.disabled = false;
        chatInput.focus();
    }
});
