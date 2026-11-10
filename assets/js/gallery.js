// Listing photo gallery: clicking a small photo shows it as the large one.
// Each small photo is a <button data-photo="/uploads/listings/...">.

const mainPhoto = document.querySelector('#gallery-main');

document.querySelectorAll('.gallery-thumb').forEach((thumb) => {
    thumb.addEventListener('click', () => {
        mainPhoto.src = thumb.dataset.photo;

        // aria-current marks the selected photo, for the highlight in the CSS
        // and for screen readers.
        const selected = document.querySelector('.gallery-thumb[aria-current="true"]');
        if (selected) {
            selected.removeAttribute('aria-current');
        }
        thumb.setAttribute('aria-current', 'true');
    });
});
