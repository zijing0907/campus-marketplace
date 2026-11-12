<?php
// One listing shown as a card in a grid. It expects $row: a listing with id, title,
// price, item_condition, meetup_location and cover_path. If $row also has a status
// of 'sold', the card is marked as sold (the wishlist page uses that).
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
        <span class="listing-card-price">
            <?= e(format_price($row['price'])) ?>
            <?php if (($row['status'] ?? '') === 'sold'): ?>
                <span class="badge badge-sold">Sold</span>
            <?php endif; ?>
        </span>
        <span class="listing-card-meta"><?= e(LISTING_CONDITIONS[$row['item_condition']]) ?> · <?= e($row['meetup_location']) ?></span>
    </span>
</a>
