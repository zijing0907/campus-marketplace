// Browse page: search, filter and change page without reloading (FR-16).
//
// Without this script the page still works: the search form is a normal GET form
// and index.php runs the search on the server. This script takes over those same
// actions, asks /api/listings.php for the results as JSON, and redraws only the
// results. It also updates the address bar, so a search can still be bookmarked,
// shared, and stepped through with the Back button.

const form = document.querySelector('#search-form');
const results = document.querySelector('#results');
const summary = document.querySelector('#results-summary');
const pagination = document.querySelector('#pagination');
const chips = document.querySelector('#category-chips');
const filters = document.querySelector('#filters');

// On a wider screen there is room for the filters, so they start open.
if (window.matchMedia('(min-width: 760px)').matches) {
    filters.open = true;
}

// A category shortcut sets the Category filter and searches, without reloading.
chips.addEventListener('click', (event) => {
    const chip = event.target.closest('a[data-category]');
    if (!chip) {
        return;
    }

    event.preventDefault();
    form.elements.category.value = chip.dataset.category;
    showResults(paramsFromForm(), true);
});

// Highlight the shortcut of the category being shown ("All" when there is none).
function markChip(category) {
    chips.querySelectorAll('a[data-category]').forEach((chip) => {
        if (chip.dataset.category === category) {
            chip.setAttribute('aria-current', 'true');
        } else {
            chip.removeAttribute('aria-current');
        }
    });
}

// Counts searches, so a slow old reply can be told apart from the newest one.
let latestSearch = 0;

// A search starts when the form is submitted (Search button or Enter)...
form.addEventListener('submit', (event) => {
    event.preventDefault();               // stop the normal page load
    showResults(paramsFromForm(), true);
});

// ...or as soon as a dropdown is changed.
form.querySelectorAll('select').forEach((select) => {
    select.addEventListener('change', () => showResults(paramsFromForm(), true));
});

// Previous and Next. One listener on the container also covers links that are
// created later, when the results are redrawn.
pagination.addEventListener('click', async (event) => {
    const link = event.target.closest('a');
    if (!link) {
        return;
    }

    event.preventDefault();
    await showResults(new URL(link.href).searchParams, true);
    form.scrollIntoView();                // back to the top of the results
});

// Back and Forward buttons: show the results that belong to the address now shown.
window.addEventListener('popstate', () => {
    const params = new URLSearchParams(location.search);
    fillForm(params);
    showResults(params, false);
});

// Collect the form's fields as address parameters, such as q=desk&max=50.
function paramsFromForm() {
    const params = new URLSearchParams();

    for (const [name, value] of new FormData(form)) {
        const text = value.trim();
        // Empty fields and the default sort are left out, to keep the address short.
        if (text !== '' && !(name === 'sort' && text === 'newest')) {
            params.set(name, text);
        }
    }

    return params;                        // no "page": a new search starts on page 1
}

// Put the values from an address back into the form (used for Back and Forward).
function fillForm(params) {
    for (const field of form.elements) {
        if (field.name) {
            field.value = params.get(field.name) || (field.name === 'sort' ? 'newest' : '');
        }
    }
}

async function showResults(params, addToHistory) {
    const query = params.toString();
    const thisSearch = ++latestSearch;

    results.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch('/api/listings.php' + (query ? '?' + query : ''));
        const reply = await response.json().catch(() => null);

        // A newer search was started while this one was on its way: ignore this reply.
        if (thisSearch !== latestSearch) {
            return;
        }

        if (!reply || !reply.success) {
            throw new Error('The search failed.');
        }

        draw(reply.data, params);

        if (addToHistory) {
            history.pushState(null, '', '/' + (query ? '?' + query : ''));
        }
    } catch (error) {
        if (thisSearch === latestSearch) {
            summary.textContent = 'Could not load listings. Check your connection and try again.';
        }
    } finally {
        if (thisSearch === latestSearch) {
            results.removeAttribute('aria-busy');
        }
    }
}

// Redraw the summary line, the cards and the Previous / Next links.
function draw(data, params) {
    markChip(params.get('category') || '');
    summary.textContent = data.summary;
    results.replaceChildren(...data.listings.map(createCard));

    pagination.replaceChildren();
    if (data.page > 1) {
        pagination.append(pageLink('Previous', 'prev', params, data.page - 1));
    }
    if (data.page < data.pages) {
        pagination.append(pageLink('Next', 'next', params, data.page + 1));
    }
}

// Builds the same card as includes/listing-card.php. If one changes, change the other.
// The text is set with textContent, which never treats it as HTML, so a title
// containing <script> is shown as plain text (NFR-02).
const PIN_ICON = '<svg class="icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>';

function createCard(listing) {
    const card = document.createElement('a');
    card.className = 'listing-card';
    card.href = listing.url;

    const photo = document.createElement('img');
    photo.className = 'listing-card-photo';
    photo.alt = '';
    photo.loading = 'lazy';
    if (listing.cover_url) {
        photo.src = listing.cover_url;
    }

    const media = textSpan('listing-card-media', '');
    media.append(photo, textSpan('listing-card-condition', listing.condition));

    // The icon is fixed markup written above, never text from the server, so
    // innerHTML is safe here. The place name is added as text after it.
    const meta = textSpan('listing-card-meta', '');
    meta.innerHTML = PIN_ICON;
    meta.append(' ' + listing.meetup_location);

    const body = textSpan('listing-card-body', '');
    body.append(
        textSpan('listing-card-price', listing.price_label),
        textSpan('listing-card-title', listing.title),
        meta
    );

    card.append(media, body);
    return card;
}

function textSpan(className, text) {
    const span = document.createElement('span');
    span.className = className;
    span.textContent = text;
    return span;
}

// A Previous or Next link: the current search, with a different page number.
function pageLink(label, rel, params, page) {
    const target = new URLSearchParams(params);
    if (page > 1) {
        target.set('page', page);
    } else {
        target.delete('page');
    }
    const query = target.toString();

    const link = document.createElement('a');
    link.className = 'button button-secondary button-small';
    link.rel = rel;
    link.href = '/' + (query ? '?' + query : '');
    link.textContent = label;
    return link;
}
