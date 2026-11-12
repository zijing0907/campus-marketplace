// The "Mark as sold" / "Mark as available" buttons on the My listings page (FR-09).
//
// A normal form would load a whole new page. Here the button sends a request in the
// background with fetch(), the server replies with a small piece of JSON, and this
// script updates just the one row that changed.
//
// When students have messaged the seller about a listing, the row also has a
// "Sold to" dropdown, and the chosen buyer is sent along (FR-20).

// The token the server put in <meta name="csrf-token"> (see includes/header.php).
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const statusMessage = document.querySelector('#status-message');

document.querySelectorAll('.status-toggle').forEach((button) => {
    button.addEventListener('click', () => changeStatus(button));
});

async function changeStatus(button) {
    const row = button.closest('.my-listing');
    const buyerSelect = row.querySelector('.buyer-select');     // null if nobody messaged
    const newStatus = button.dataset.setStatus;                 // "sold" or "available"

    // FormData sends fields the same way a normal form does, so PHP finds them in $_POST.
    const body = new FormData();
    body.append('csrf_token', csrfToken);
    body.append('id', row.dataset.id);
    body.append('status', newStatus);

    // Remember the buyer's name now, to show it once the server has agreed.
    let buyerName = '';
    if (newStatus === 'sold' && buyerSelect && buyerSelect.value !== '') {
        body.append('buyer_id', buyerSelect.value);
        buyerName = buyerSelect.selectedOptions[0].textContent;
    }

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

        showStatus(row, button, reply.data.status, buyerName);
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
function showStatus(row, button, status, buyerName) {
    const sold = status === 'sold';

    const badge = row.querySelector('.badge');
    badge.textContent = sold ? 'Sold' : 'Available';
    badge.className = 'badge ' + (sold ? 'badge-sold' : 'badge-available');

    // "Sold to Ben" appears only when a buyer was chosen.
    const soldTo = row.querySelector('.sold-to');
    soldTo.textContent = buyerName ? 'Sold to ' + buyerName : '';
    soldTo.hidden = !(sold && buyerName);

    // The "Sold to" dropdown is only needed while the item is still on sale.
    const buyerPick = row.querySelector('.buyer-pick');
    if (buyerPick) {
        buyerPick.hidden = sold;
    }

    // The button now offers the opposite action.
    button.textContent = sold ? 'Mark as available' : 'Mark as sold';
    button.dataset.setStatus = sold ? 'available' : 'sold';
}
