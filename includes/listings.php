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
