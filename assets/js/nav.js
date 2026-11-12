// The phone menu: the "Menu" button in the header opens and closes the list of links.
//
// Without JavaScript the button stays hidden and all the links are simply shown.
// This script switches the button on and tucks the links away behind it. On a
// wide screen the stylesheet shows the links in a row and hides the button again.
//
// Everything is wrapped in { } so that the names used here stay inside this file.
// Other scripts on the page are then free to use the same names.
{
    const toggle = document.querySelector('#nav-toggle');

    // A guest has only two links and no Menu button, so there is nothing to do.
    if (toggle) {
        const header = document.querySelector('#site-header');
        const menu = document.querySelector('#site-nav');

        header.classList.add('has-menu');
        toggle.hidden = false;

        const isOpen = () => toggle.getAttribute('aria-expanded') === 'true';

        // aria-expanded tells screen readers whether the menu is open; the class
        // is what the stylesheet uses to show or hide it.
        const setOpen = (open) => {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            menu.classList.toggle('is-open', open);
        };

        toggle.addEventListener('click', () => setOpen(!isOpen()));

        // The Escape key closes the menu and puts the keyboard back on the button.
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen()) {
                setOpen(false);
                toggle.focus();
            }
        });
    }
}
