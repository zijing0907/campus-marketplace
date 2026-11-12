<?php
// Helpers for seller ratings (FR-20).
//
// A rating belongs to one sale: the student recorded as the buyer of a sold
// listing rates its seller from 1 to 5, once, with an optional comment.

// The choices in the rating form, best first: the number stored and its label.
const RATING_LABELS = [
    5 => '5 – Excellent',
    4 => '4 – Good',
    3 => '3 – OK',
    2 => '2 – Poor',
    1 => '1 – Terrible',
];

// The longest comment accepted with a rating, in characters.
const MAX_REVIEW_COMMENT = 500;

/**
 * A seller's average rating and how many ratings they have:
 * ['average' => 4.5, 'count' => 12]. The average is null when there are none.
 */
function seller_rating(int $sellerId): array
{
    // AVG() works the average out in the database, so no ratings need to be
    // fetched and added up in PHP.
    $stmt = db()->prepare('SELECT AVG(rating) AS average, COUNT(*) AS total FROM reviews WHERE seller_id = ?');
    $stmt->execute([$sellerId]);
    $row = $stmt->fetch();

    return [
        'average' => $row['average'] !== null ? round((float) $row['average'], 1) : null,
        'count'   => (int) $row['total'],
    ];
}

/**
 * A rating in words, for example "★ 4.5 · 12 ratings" or "No ratings yet".
 * $rating is what seller_rating() returned.
 */
function rating_summary(array $rating): string
{
    if ($rating['count'] === 0) {
        return 'No ratings yet';
    }

    return '★ ' . number_format($rating['average'], 1) . ' · '
        . $rating['count'] . ($rating['count'] === 1 ? ' rating' : ' ratings');
}

/**
 * Five stars for one rating, such as ★★★★☆ for 4. The aria-label gives screen
 * readers the number instead of a row of symbols.
 */
function stars_html(int $rating): string
{
    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

    return '<span class="stars" role="img" aria-label="' . $rating . ' out of 5">' . $stars . '</span>';
}
