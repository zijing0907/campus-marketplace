// Save and remove listings from the wishlist (FR-19), without reloading the page.
// Used on a listing page (the Save button) and on the Saved listings page (the
// Remove buttons). Both call the same API, which works as a toggle.

// Ask the server to save or un-save a listing.
// Resolves to true if the listing is now saved, false if it is now removed.
// Throws an Error with a message for the user if the server says no.
async function toggleSaved(listingId) {
    const body = new FormData();
    body.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    body.append('listing_id', listingId);

    let response;
    try {
        response = await fetch('/api/wishlist.php', { method: 'POST', body: body });
    } catch (error) {
        // fetch() itself fails when the server cannot be reached.
        throw new Error('Could not reach the server. Check your connection and try again.');
    }

    const reply = await response.json().catch(() => null);
    if (!reply || !reply.success) {
        throw new Error(reply ? reply.error : 'The server sent an unexpected reply. Please try again.');
    }

    return reply.data.saved;
}

// Show or hide the error message on the page.
function showWishlistError(text) {
    const message = document.querySelector('#wishlist-message');
    message.textContent = text;
    message.hidden = text === '';
}

// The Save button on a listing page. It starts out hidden in the HTML, because it
// only works with JavaScript, and is shown once this script is running.
document.querySelectorAll('[data-wishlist-toggle]').forEach((button) => {
    button.hidden = false;

    button.addEventListener('click', async () => {
        button.disabled = true;
        showWishlistError('');

        try {
            const saved = await toggleSaved(button.dataset.listingId);

            // aria-pressed tells screen readers whether the button is "on", and
            // the CSS uses it to fill the button in.
            button.setAttribute('aria-pressed', saved ? 'true' : 'false');
            button.textContent = saved ? '♥ Saved' : '♡ Save';
        } catch (error) {
            showWishlistError(error.message);
        } finally {
            button.disabled = false;
        }
    });
});

// The Remove buttons on the Saved listings page.
document.querySelectorAll('[data-wishlist-remove]').forEach((button) => {
    button.addEventListener('click', async () => {
        button.disabled = true;
        showWishlistError('');

        try {
            await toggleSaved(button.dataset.listingId);

            // Take the card off the page, then update the count in the heading.
            button.closest('.wish-item').remove();
            const left = document.querySelectorAll('.wish-item').length;
            document.querySelector('#wishlist-count').textContent = left;

            // Nothing left: show the "nothing saved yet" message instead.
            if (left === 0) {
                document.querySelector('#wishlist-empty').hidden = false;
            }
        } catch (error) {
            showWishlistError(error.message);
            button.disabled = false;
        }
    });
});
