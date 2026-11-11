// "Report this listing" on the listing page (FR-22).
// The form is sent to /api/report.php with fetch(), so the page does not reload.
// On success the form is replaced by a thank-you message.

const reportBox = document.querySelector('#report-box');
const reportForm = document.querySelector('#report-form');
const reportMessage = document.querySelector('#report-message');

// The box starts out hidden in the HTML. It only works with JavaScript, so it is
// only shown once this script is running.
reportBox.hidden = false;

reportForm.addEventListener('submit', async (event) => {
    event.preventDefault();               // stop the normal page load

    // FormData collects the form's fields. The CSRF token comes from the page's
    // <meta name="csrf-token"> tag (see includes/header.php).
    const body = new FormData(reportForm);
    body.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

    const button = reportForm.querySelector('button[type="submit"]');
    button.disabled = true;
    reportMessage.hidden = true;

    try {
        const response = await fetch('/api/report.php', { method: 'POST', body: body });
        const reply = await response.json().catch(() => null);

        if (!reply || !reply.success) {
            throw new Error(reply ? reply.error : 'The server sent an unexpected reply. Please try again.');
        }

        // Replace the whole box with the server's thank-you message.
        const thanks = document.createElement('p');
        thanks.className = 'note';
        thanks.textContent = reply.data.message;
        reportBox.replaceWith(thanks);
    } catch (error) {
        // fetch() itself fails with a TypeError when the server cannot be reached.
        reportMessage.textContent = error instanceof TypeError
            ? 'Could not reach the server. Check your connection and try again.'
            : error.message;
        reportMessage.hidden = false;
        button.disabled = false;
    }
});
