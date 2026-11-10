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

/**
 * All categories for a dropdown: alphabetical, with "Others" last.
 */
function all_categories(): array
{
    // "name = 'Others'" is 0 for normal categories and 1 for Others, so sorting
    // by it first pushes Others to the end.
    return db()->query("SELECT id, name FROM categories ORDER BY name = 'Others', name")->fetchAll();
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
