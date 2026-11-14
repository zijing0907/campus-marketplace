// Two small helpers for forms. Any page can use them by adding an attribute.
//
// 1. data-password-toggle on a password field adds an eye button beside it that
//    shows or hides what was typed, so a student can check for typos.
// 2. data-loading-text on a submit button changes it to that text, with a
//    spinner, once the form is sent, so nobody presses it twice while waiting.
//
// Without JavaScript neither appears, and the forms still work exactly the same.
// Everything is inside { } so its names cannot clash with other scripts.
{
    const eye = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
    const eyeOff = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M3 3l18 18M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

    document.querySelectorAll('input[data-password-toggle]').forEach((input) => {
        // Put the field and its new button side by side in one box.
        const box = document.createElement('span');
        box.className = 'password-field';
        input.replaceWith(box);
        box.append(input);

        const button = document.createElement('button');
        button.type = 'button';               // a plain button: it must not send the form
        button.className = 'password-toggle';

        // aria-pressed tells screen readers whether the password is showing;
        // the label says what pressing the button will do.
        const show = (visible) => {
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', visible ? 'true' : 'false');
            button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            button.innerHTML = visible ? eyeOff : eye;
        };
        show(false);

        button.addEventListener('click', () => {
            show(input.type === 'password');
            input.focus();
        });

        box.append(button);
    });

    document.querySelectorAll('form').forEach((form) => {
        const button = form.querySelector('button[type="submit"][data-loading-text]');
        if (!button) {
            return;
        }

        const label = button.innerHTML;

        // "submit" only fires once the browser's own checks (such as "required")
        // have passed, so the button never gets stuck on a form that was not sent.
        form.addEventListener('submit', () => {
            button.disabled = true;
            button.classList.add('is-loading');
            button.textContent = button.dataset.loadingText;
        });

        // Coming back with the browser's Back button can show the page exactly as
        // it was left, button still disabled. Put it back to normal then.
        window.addEventListener('pageshow', () => {
            button.disabled = false;
            button.classList.remove('is-loading');
            button.innerHTML = label;
        });
    });
}
