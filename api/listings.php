<?php
// GET /api/listings.php?q=&category=&condition=&min=&max=&sort=&page=
// Search and filter the available listings (FR-13 to FR-16). No login needed.
//
// Reply: {"success": true, "data": {"listings": [...], "summary": "...",
//         "total": 30, "page": 2, "pages": 3, "per_page": 12}}
// Called by assets/js/browse.js. The home page (index.php) runs the very same
// search on the server for its first load, through the same two functions.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/api.php';

api_require_method('GET');

$filters = listing_filters(all_categories());
$result = search_listings($filters);

// Send only what a listing card shows. Nothing private, such as the seller's
// phone number, is ever part of this reply.
$listings = [];
foreach ($result['listings'] as $row) {
    $listings[] = [
        'id'              => (int) $row['id'],
        'title'           => $row['title'],
        'price'           => $row['price'],
        'price_label'     => format_price($row['price']),
        'condition'       => LISTING_CONDITIONS[$row['item_condition']],
        'category'        => $row['category_name'],
        'meetup_location' => $row['meetup_location'],
        'cover_url'       => $row['cover_path'] !== null ? '/' . $row['cover_path'] : null,
        'url'             => '/pages/listing.php?id=' . $row['id'],
    ];
}

api_success([
    'listings' => $listings,
    'summary'  => results_summary($filters, $result),
    'total'    => $result['total'],
    'page'     => $result['page'],
    'pages'    => $result['pages'],
    'per_page' => LISTINGS_PER_PAGE,
]);
