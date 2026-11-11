<?php
// Helpers for listings: the fixed choices, and how prices and contact links are shown.

// The values stored in listings.item_condition, and the label shown for each.
const LISTING_CONDITIONS = [
    'new'      => 'New',
    'like_new' => 'Like new',
    'used'     => 'Used',
];

// A listing has between 1 and this many photos (FR-07).
const MAX_LISTING_PHOTOS = 5;

// Country calling code put in front of local phone numbers in WhatsApp links.
// 60 is Malaysia.
const COUNTRY_CALLING_CODE = '60';

// How many listings the browse page shows per page (FR-13).
const LISTINGS_PER_PAGE = 12;

// The catch-all category. It is listed last, it cannot be renamed or removed, and
// when an admin removes another category its listings are moved here (FR-25).
const FALLBACK_CATEGORY = 'Others';

// The ways results can be sorted (FR-15): the label shown in the dropdown, and the
// ORDER BY used for it. A value from the address is only ever used to look up an
// entry in this list. ORDER BY cannot take a "?" placeholder, so a fixed list of
// allowed choices is what keeps user input out of the SQL.
const LISTING_SORTS = [
    'newest'     => ['label' => 'Newest first',       'sql' => 'l.created_at DESC, l.id DESC'],
    'price_asc'  => ['label' => 'Price: low to high', 'sql' => 'l.price ASC, l.id DESC'],
    'price_desc' => ['label' => 'Price: high to low', 'sql' => 'l.price DESC, l.id DESC'],
];

/**
 * All categories for a dropdown: alphabetical, with "Others" last.
 */
function all_categories(): array
{
    // "name = ?" is 0 for normal categories and 1 for the catch-all one, so sorting
    // by it first pushes that category to the end.
    $stmt = db()->prepare('SELECT id, name FROM categories ORDER BY name = ?, name');
    $stmt->execute([FALLBACK_CATEGORY]);

    return $stmt->fetchAll();
}

/**
 * Read the text fields of the listing form from $_POST.
 * The create and edit pages both use this, together with validate_listing() and
 * the form fields in includes/listing-fields.php.
 */
function listing_form_input(): array
{
    return [
        'title'           => post_text('title'),
        'description'     => post_text('description'),
        'price'           => post_text('price'),
        'category_id'     => post_text('category_id'),
        'condition'       => post_text('condition'),
        'meetup_location' => post_text('meetup_location'),
    ];
}

/**
 * Check the text fields of the listing form. Creating and editing a listing share
 * this function, so both always follow the same rules.
 * $categories is the list from all_categories(). Returns [field => error message].
 */
function validate_listing(array $data, array $categories): array
{
    $errors = [];

    if (mb_strlen($data['title']) < 3 || mb_strlen($data['title']) > 120) {
        $errors['title'] = 'Enter a title (3 to 120 characters).';
    }

    if ($data['description'] === '' || mb_strlen($data['description']) > 2000) {
        $errors['description'] = 'Describe the item (up to 2000 characters).';
    }

    // Up to 6 digits, then optionally a dot and 1 or 2 more digits: 35, 35.5, 35.50.
    // The price stays a string all the way to the database, which stores it as an
    // exact DECIMAL. Turning money into a float can shift it by tiny fractions.
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $data['price'])) {
        $errors['price'] = 'Enter a price between 0 and 999999.99, for example 35 or 35.50.';
    }

    // The chosen category must be one that really exists in the database.
    $categoryIds = array_column($categories, 'id');
    if (!ctype_digit($data['category_id']) || !in_array((int) $data['category_id'], $categoryIds, true)) {
        $errors['category_id'] = 'Choose a category.';
    }

    if (!isset(LISTING_CONDITIONS[$data['condition']])) {
        $errors['condition'] = 'Choose the condition of the item.';
    }

    if (mb_strlen($data['meetup_location']) < 2 || mb_strlen($data['meetup_location']) > 120) {
        $errors['meetup_location'] = 'Say where on campus you can meet (2 to 120 characters).';
    }

    return $errors;
}

/**
 * Save several uploaded listing photos (a list from uploaded_files()).
 * It is all or nothing: if one photo is rejected, the ones saved before it are
 * deleted again, so no stray files are left behind.
 * Returns ['paths' => [...]] on success or ['error' => 'Photo 2: ...'] on failure.
 */
function save_listing_photos(array $photos): array
{
    $paths = [];

    foreach ($photos as $i => $photo) {
        $result = save_uploaded_image($photo, 'listings');

        if (isset($result['error'])) {
            foreach ($paths as $path) {
                delete_uploaded_file($path);
            }
            return ['error' => 'Photo ' . ($i + 1) . ': ' . $result['error']];
        }

        $paths[] = $result['path'];
    }

    return ['paths' => $paths];
}

/**
 * Read the search box, filters, sort and page number from the address, for example
 * /?q=desk&category=3&max=50&sort=price_asc&page=2 (FR-14, FR-15).
 *
 * Anything missing or invalid is simply left out, so a mistyped or tampered address
 * still shows results instead of an error. The home page and the API both use this.
 * $categories is the list from all_categories().
 */
function listing_filters(array $categories): array
{
    $q = mb_substr(get_text('q'), 0, 100);

    // Split the search text into words. Every word has to be found; at most 5 are used.
    $words = array_slice(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY), 0, 5);

    $category = get_text('category');
    $categoryIds = array_column($categories, 'id');
    $category = ctype_digit($category) && in_array((int) $category, $categoryIds, true) ? (int) $category : null;

    $condition = get_text('condition');
    $condition = isset(LISTING_CONDITIONS[$condition]) ? $condition : null;

    // Prices follow the same pattern as on the Sell form: 35, 35.5 or 35.50.
    $pricePattern = '/^\d{1,6}(\.\d{1,2})?$/';
    $min = get_text('min');
    $min = preg_match($pricePattern, $min) ? $min : null;
    $max = get_text('max');
    $max = preg_match($pricePattern, $max) ? $max : null;

    // A range typed the wrong way round (min 50, max 20) is quietly swapped.
    if ($min !== null && $max !== null && (float) $min > (float) $max) {
        [$min, $max] = [$max, $min];
    }

    $sort = get_text('sort');
    $sort = isset(LISTING_SORTS[$sort]) ? $sort : 'newest';

    // A whole number from 1 upwards; anything else means page 1.
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

    return [
        'q'         => $q,
        'words'     => $words,
        'category'  => $category,
        'condition' => $condition,
        'min'       => $min,
        'max'       => $max,
        'sort'      => $sort,
        'page'      => $page,
    ];
}

/**
 * Find the available listings that match the filters from listing_filters().
 * Returns the listings for one page, plus the numbers needed for pagination:
 * ['listings' => [...], 'total' => 30, 'page' => 2, 'pages' => 3].
 */
function search_listings(array $filters): array
{
    // The WHERE clause is built from fixed pieces of SQL. Every value that came
    // from the user goes into $params and reaches the database through a "?"
    // placeholder, never by being joined into the SQL text (NFR-01).
    // Sold and hidden listings never appear (FR-09), and neither do listings
    // whose seller has been banned (FR-24). "s" is the seller, joined in below.
    $where = ["l.status = 'available'", 's.is_banned = 0'];
    $params = [];

    foreach ($filters['words'] as $word) {
        // In a LIKE pattern, % means "anything" and _ means "any one character".
        // addcslashes() puts a backslash in front of those typed by the user, so a
        // search for "50%" finds the text 50% instead of matching everything.
        $pattern = '%' . addcslashes($word, '%_\\') . '%';
        $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
        $params[] = $pattern;
        $params[] = $pattern;
    }

    if ($filters['category'] !== null) {
        $where[] = 'l.category_id = ?';
        $params[] = $filters['category'];
    }

    if ($filters['condition'] !== null) {
        $where[] = 'l.item_condition = ?';
        $params[] = $filters['condition'];
    }

    if ($filters['min'] !== null) {
        $where[] = 'l.price >= ?';
        $params[] = $filters['min'];
    }

    if ($filters['max'] !== null) {
        $where[] = 'l.price <= ?';
        $params[] = $filters['max'];
    }

    $whereSql = implode(' AND ', $where);

    // First count all matches, to know how many pages there are.
    $stmt = db()->prepare("SELECT COUNT(*) FROM listings l JOIN users s ON s.id = l.seller_id WHERE $whereSql");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // A page number past the end shows the last page instead of an empty one.
    $pages = max(1, (int) ceil($total / LISTINGS_PER_PAGE));
    $page = min($filters['page'], $pages);

    // Then fetch just the rows for this page. The inner SELECT picks each listing's
    // cover photo. The ORDER BY text comes from the fixed LISTING_SORTS list.
    $orderSql = LISTING_SORTS[$filters['sort']]['sql'];
    $stmt = db()->prepare(
        "SELECT l.id, l.title, l.price, l.item_condition, l.meetup_location, l.created_at,
                c.name AS category_name,
                (SELECT i.file_path
                 FROM listing_images i
                 WHERE i.listing_id = l.id
                 ORDER BY i.sort_order, i.id
                 LIMIT 1) AS cover_path
         FROM listings l
         JOIN users s ON s.id = l.seller_id
         JOIN categories c ON c.id = l.category_id
         WHERE $whereSql
         ORDER BY $orderSql
         LIMIT ? OFFSET ?"
    );

    // bindValue() numbers placeholders from 1. LIMIT and OFFSET must be sent as
    // integers, which PDO::PARAM_INT guarantees.
    foreach ($params as $i => $value) {
        $stmt->bindValue($i + 1, $value);
    }
    $stmt->bindValue(count($params) + 1, LISTINGS_PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, ($page - 1) * LISTINGS_PER_PAGE, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'listings' => $stmt->fetchAll(),
        'total'    => $total,
        'page'     => $page,
        'pages'    => $pages,
    ];
}

/**
 * The address of the browse page for these filters and this page number.
 * Empty filters are left out, so the address stays short: /?q=desk&page=2
 */
function browse_url(array $filters, int $page = 1): string
{
    $query = [
        'q'         => $filters['q'],
        'category'  => $filters['category'],
        'condition' => $filters['condition'],
        'min'       => $filters['min'],
        'max'       => $filters['max'],
        'sort'      => $filters['sort'] !== 'newest' ? $filters['sort'] : null,
        'page'      => $page > 1 ? $page : null,
    ];
    $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

    return '/' . ($query ? '?' . http_build_query($query) : '');
}

/**
 * True if the visitor searched or filtered, as opposed to just opening the page.
 */
function has_active_filters(array $filters): bool
{
    return $filters['q'] !== ''
        || $filters['category'] !== null
        || $filters['condition'] !== null
        || $filters['min'] !== null
        || $filters['max'] !== null;
}

/**
 * The line shown above the results, such as "30 listings · page 2 of 3".
 * $result is what search_listings() returned. The page and the API share this, so
 * the wording is the same however the results were loaded.
 */
function results_summary(array $filters, array $result): string
{
    if ($result['total'] === 0) {
        return has_active_filters($filters)
            ? 'No listings match your search. Try different words or fewer filters.'
            : 'No listings yet. Be the first to sell something.';
    }

    $summary = $result['total'] . ($result['total'] === 1 ? ' listing' : ' listings');

    if ($filters['q'] !== '') {
        $summary .= ' for "' . $filters['q'] . '"';
    }

    if ($result['pages'] > 1) {
        $summary .= ' · page ' . $result['page'] . ' of ' . $result['pages'];
    }

    return $summary;
}

/**
 * Format a price from the database for display: "35.5" becomes "RM 35.50".
 */
function format_price(string $price): string
{
    return 'RM ' . number_format((float) $price, 2);
}

/**
 * Build a WhatsApp chat link for a phone number as stored in users.phone.
 * WhatsApp needs the international form without the plus sign, so the local
 * number 0123456789 becomes 60123456789.
 */
function whatsapp_link(string $phone): string
{
    if (str_starts_with($phone, '+')) {
        $number = substr($phone, 1);
    } elseif (str_starts_with($phone, '0')) {
        $number = COUNTRY_CALLING_CODE . substr($phone, 1);
    } else {
        $number = $phone;
    }

    return 'https://wa.me/' . $number;
}
