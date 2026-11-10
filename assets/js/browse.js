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

    const body = document.createElement('span');
    body.className = 'listing-card-body';
    body.append(
        textSpan('listing-card-title', listing.title),
        textSpan('listing-card-price', listing.price_label),
        textSpan('listing-card-meta', listing.condition + ' · ' + listing.meetup_location)
    );

    card.append(photo, body);
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
