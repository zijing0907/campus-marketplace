<?php
// Home page: browse, search and filter the available listings (FR-13, FR-14, FR-15).
//
// The search form is an ordinary GET form, so everything works with a normal page
// load: the filters travel in the address (/?q=desk&max=50) and this file runs the
// search on the server. assets/js/browse.js then makes it smoother by fetching
// results from /api/listings.php without reloading the page (FR-16).
require __DIR__ . '/includes/bootstrap.php';

$categories = all_categories();
$filters = listing_filters($categories);
$result = search_listings($filters);

$pageTitle = 'Browse';
require __DIR__ . '/includes/header.php';

// On a phone the filters start folded away, so the listings show on the first
// screen. They start open when one of them is in use, so nobody forgets it is on.
// (On a wider screen browse.js opens them anyway.)
$filtersInUse = $filters['category'] !== null || $filters['condition'] !== null
    || $filters['min'] !== null || $filters['max'] !== null || $filters['sort'] !== 'newest';
?>

<?php // One form for the search box in the coloured band and the filters below it. ?>
<form id="search-form" class="browse-form" method="get" action="/" role="search">
    <section class="hero">
        <div class="hero-text">
            <h1>Buy and sell on campus</h1>
            <p>Used textbooks, electronics and hostel essentials, from students at your university.</p>
        </div>

        <div class="hero-search">
            <label for="q" class="visually-hidden">Search listings</label>
            <span class="hero-search-field">
                <?= icon('search', 20) ?>
                <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="100"
                       placeholder="Search for a textbook, a desk, a fridge…">
            </span>
            <button type="submit" class="button">Search</button>
        </div>

        <?php if ($currentUser === null): ?>
            <p class="hero-join">New here? <a href="/pages/register.php">Create an account</a> to sell your own things.</p>
        <?php endif; ?>
    </section>

    <?php // Shortcuts to each category. browse.js makes them work without reloading the page. ?>
    <nav class="category-chips" id="category-chips" aria-label="Categories">
        <a class="chip" href="/" data-category="" <?= $filters['category'] === null ? 'aria-current="true"' : '' ?>><?= icon('grid', 16) ?> All</a>
        <?php foreach ($categories as $category): ?>
            <a class="chip" href="/?category=<?= e((string) $category['id']) ?>" data-category="<?= e((string) $category['id']) ?>"
               <?= $category['id'] === $filters['category'] ? 'aria-current="true"' : '' ?>><?= category_icon($category['name'], 16) ?> <?= e($category['name']) ?></a>
        <?php endforeach; ?>
    </nav>

    <details class="filters" id="filters" <?= $filtersInUse ? 'open' : '' ?>>
        <summary><?= icon('filter') ?> Filters <span class="filters-hint">Category, condition, price and sort</span></summary>

        <div class="search-filters">
            <div>
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= $category['id'] === $filters['category'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="condition">Condition</label>
                <select id="condition" name="condition">
                    <option value="">Any condition</option>
                    <?php foreach (LISTING_CONDITIONS as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === $filters['condition'] ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="min">Min price (RM)</label>
                <input type="number" id="min" name="min" value="<?= e($filters['min']) ?>"
                       min="0" max="999999.99" step="0.01" inputmode="decimal">
            </div>

            <div>
                <label for="max">Max price (RM)</label>
                <input type="number" id="max" name="max" value="<?= e($filters['max']) ?>"
                       min="0" max="999999.99" step="0.01" inputmode="decimal">
            </div>

            <div>
                <label for="sort">Sort by</label>
                <select id="sort" name="sort">
                    <?php foreach (LISTING_SORTS as $value => $sort): ?>
                        <option value="<?= e($value) ?>" <?= $value === $filters['sort'] ? 'selected' : '' ?>><?= e($sort['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </details>
</form>

<?php // aria-live makes screen readers announce the new summary when results change. ?>
<p id="results-summary" class="results-summary" aria-live="polite"><?= e(results_summary($filters, $result)) ?></p>

<div id="results" class="listing-grid">
    <?php foreach ($result['listings'] as $row): ?>
        <?php require __DIR__ . '/includes/listing-card.php'; ?>
    <?php endforeach; ?>
</div>

<div id="pagination" class="pagination">
    <?php if ($result['page'] > 1): ?>
        <a class="button button-secondary button-small" rel="prev"
           href="<?= e(browse_url($filters, $result['page'] - 1)) ?>">Previous</a>
    <?php endif; ?>

    <?php if ($result['page'] < $result['pages']): ?>
        <a class="button button-secondary button-small" rel="next"
           href="<?= e(browse_url($filters, $result['page'] + 1)) ?>">Next</a>
    <?php endif; ?>
</div>

<script src="/assets/js/browse.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
