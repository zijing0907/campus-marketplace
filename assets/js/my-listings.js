// The "Mark as sold" / "Mark as available" buttons on the My listings page (FR-09).
//
// A normal form would load a whole new page. Here the button sends a request in the
// background with fetch(), the server replies with a small piece of JSON, and this
// script updates just the one row that changed.

// The token the server put in <meta name="csrf-token"> (see includes/header.php).
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const statusMessage = document.querySelector('#status-message');

document.querySelectorAll('.status-toggle').forEach((button) => {
    button.addEventListener('click', () => changeStatus(button));
});

async function changeStatus(button) {
    const row = button.closest('.my-listing');

    // FormData sends fields the same way a normal form does, so PHP finds them in $_POST.
    const body = new FormData();
    body.append('csrf_token', csrfToken);
    body.append('id', row.dataset.id);
    body.append('status', button.dataset.setStatus);   // "sold" or "available"

    button.disabled = true;
    statusMessage.hidden = true;

    try {
        const response = await fetch('/api/listing-status.php', { method: 'POST', body: body });

        // Every reply from the API is JSON shaped like {success, data} or {success, error}.
        // If the reply is not JSON at all, treat it as a failure.
        const reply = await response.json().catch(() => null);

        if (!reply || !reply.success) {
            throw new Error(reply ? reply.error : 'The server sent an unexpected reply. Please try again.');
        }

        showStatus(row, button, reply.data.status);
    } catch (error) {
        // fetch() itself fails with a TypeError when the server cannot be reached.
        statusMessage.textContent = error instanceof TypeError
            ? 'Could not reach the server. Check your connection and try again.'
            : error.message;
        statusMessage.hidden = false;
    } finally {
        button.disabled = false;
    }
}

// Update one row to match the status the server confirmed.
function showStatus(row, button, status) {
    const sold = status === 'sold';

    const badge = row.querySelector('.badge');
    badge.textContent = sold ? 'Sold' : 'Available';
    badge.className = 'badge ' + (sold ? 'badge-sold' : 'badge-available');

    // The button now offers the opposite action.
    button.textContent = sold ? 'Mark as available' : 'Mark as sold';
    button.dataset.setStatus = sold ? 'available' : 'sold';
}
