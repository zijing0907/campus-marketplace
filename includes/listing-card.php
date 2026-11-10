<?php
// One listing shown as a card in a grid. It expects $row: a listing from
// search_listings(), with id, title, price, item_condition, meetup_location, cover_path.
//
// assets/js/browse.js builds the same card from the API's JSON (see createCard there).
// If this markup changes, change it there too.
?>
<a class="listing-card" href="/pages/listing.php?id=<?= e((string) $row['id']) ?>">
    <?php // alt is empty because the title is right below, inside the same link. ?>
    <img class="listing-card-photo" alt="" loading="lazy"
         <?php if ($row['cover_path'] !== null): ?>src="/<?= e($row['cover_path']) ?>"<?php endif; ?>>
    <span class="listing-card-body">
        <span class="listing-card-title"><?= e($row['title']) ?></span>
        <span class="listing-card-price"><?= e(format_price($row['price'])) ?></span>
        <span class="listing-card-meta"><?= e(LISTING_CONDITIONS[$row['item_condition']]) ?> · <?= e($row['meetup_location']) ?></span>
    </span>
</a>
