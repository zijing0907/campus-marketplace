// Asks "are you sure?" before a form is submitted.
// Add data-confirm="your question" to any <form> that does something that cannot
// be undone, such as deleting a listing.

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        // confirm() shows the browser's own OK / Cancel box. Cancel stops the submit.
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
