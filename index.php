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
?>

<section class="hero">
    <div>
        <h1>Buy and sell on campus</h1>
        <p>Used textbooks, electronics and hostel essentials, from students at your university.</p>
    </div>
    <?php // A guest is invited to join. A logged-in student already has "Sell an item" in the header. ?>
    <?php if ($currentUser === null): ?>
        <a class="button" href="/pages/register.php">Create an account</a>
    <?php endif; ?>
</section>

<form id="search-form" class="search-form" method="get" action="/" role="search">
    <div class="search-main">
        <label for="q" class="visually-hidden">Search listings</label>
        <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="100"
               placeholder="Search for an item">
        <button type="submit" class="button">Search</button>
    </div>

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
