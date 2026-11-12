<?php
// One listing's page: photos, details and the seller (FR-10). Open to everyone.
require __DIR__ . '/../includes/bootstrap.php';

// FILTER_VALIDATE_INT returns an integer, or false for anything else ("abc", "1.5"),
// or null when there is no id in the URL at all.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$listing = null;
if (is_int($id) && $id > 0) {
    // One query fetches the listing together with its category name and its seller,
    // by joining the three tables on their ids.
    $stmt = db()->prepare(
        'SELECT l.id, l.seller_id, l.title, l.description, l.price, l.item_condition,
                l.meetup_location, l.status, l.created_at,
                c.name AS category_name,
                u.name AS seller_name, u.faculty AS seller_faculty,
                u.phone AS seller_phone, u.avatar_path AS seller_avatar_path,
                u.is_banned AS seller_is_banned
         FROM listings l
         JOIN categories c ON c.id = l.category_id
         JOIN users u ON u.id = l.seller_id
         WHERE l.id = ?'
    );
    $stmt->execute([$id]);
    $listing = $stmt->fetch() ?: null;
}

$isOwner = $listing !== null && $currentUser !== null
    && (int) $currentUser['id'] === (int) $listing['seller_id'];
$isAdmin = $currentUser !== null && $currentUser['role'] === 'admin';

// Two kinds of listing are kept from the public:
//   - one hidden by an admin: only its seller and admins can see it
//   - one whose seller is banned: only admins can see it (the seller cannot log in)
// Everyone else gets the same page as for a listing that never existed, so the
// response does not reveal that the listing is there.
$hiddenFromViewer = $listing !== null && (
    ($listing['status'] === 'hidden' && !$isOwner && !$isAdmin)
    || ($listing['seller_is_banned'] && !$isAdmin)
);

if ($listing === null || $hiddenFromViewer) {
    $notFoundMessage = 'This listing does not exist or has been removed.';
    require __DIR__ . '/../includes/not-found.php';
}

$stmt = db()->prepare('SELECT file_path FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id');
$stmt->execute([$listing['id']]);
$photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

$seller = ['name' => $listing['seller_name'], 'avatar_path' => $listing['seller_avatar_path']];

// If the visitor is a logged-in student who has already messaged the seller about
// this listing, find that conversation so the page can link to it.
$myConversationId = null;
if ($currentUser !== null && !$isOwner) {
    $stmt = db()->prepare('SELECT id FROM conversations WHERE listing_id = ? AND buyer_id = ?');
    $stmt->execute([$listing['id'], $currentUser['id']]);
    $found = $stmt->fetchColumn();
    $myConversationId = $found !== false ? (int) $found : null;
}

// A logged-in student can save a listing that is not their own (FR-19). The Save
// button is shown for an item still on sale, or for one they saved earlier, so
// that they can always take it off their list again.
$isSaved = false;
if ($currentUser !== null && !$isOwner) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ? AND listing_id = ?');
    $stmt->execute([$currentUser['id'], $listing['id']]);
    $isSaved = (int) $stmt->fetchColumn() > 0;
}
$canSave = $currentUser !== null && !$isOwner && !$listing['seller_is_banned']
    && ($isSaved || $listing['status'] === 'available');

// Any logged-in student except the seller can report a listing (FR-22). If they
// already have a report waiting for an admin, say so instead of offering the form.
$canReport = $currentUser !== null && !$isOwner && $listing['status'] !== 'hidden' && !$listing['seller_is_banned'];
$alreadyReported = false;
if ($canReport) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM reports WHERE listing_id = ? AND reporter_id = ? AND status = 'open'");
    $stmt->execute([$listing['id'], $currentUser['id']]);
    $alreadyReported = (int) $stmt->fetchColumn() > 0;
}

$pageTitle = $listing['title'];
require __DIR__ . '/../includes/header.php';
?>

<?php if ($listing['status'] === 'hidden'): ?>
    <p class="alert alert-info">This listing is hidden. Only its seller and the admins can see it.</p>
<?php endif; ?>
<?php if ($listing['seller_is_banned']): ?>
    <p class="alert alert-info">This seller is banned. Only admins can see this listing.</p>
<?php endif; ?>

<article class="listing">
    <div class="gallery">
        <?php if ($photos): ?>
            <img id="gallery-main" class="gallery-main" src="/<?= e($photos[0]) ?>" alt="<?= e($listing['title']) ?>">

            <?php if (count($photos) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($photos as $i => $photo): ?>
                        <button type="button" class="gallery-thumb" data-photo="/<?= e($photo) ?>"
                                aria-label="Show photo <?= $i + 1 ?>" <?= $i === 0 ? 'aria-current="true"' : '' ?>>
                            <img src="/<?= e($photo) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="listing-info">
        <?php if ($listing['status'] === 'sold'): ?>
            <span class="badge badge-sold">Sold</span>
        <?php endif; ?>

        <h1><?= e($listing['title']) ?></h1>
        <p class="price"><?= e(format_price($listing['price'])) ?></p>

        <?php if ($canSave): ?>
            <?php // Hidden until assets/js/wishlist.js runs, because saving uses fetch(). ?>
            <button type="button" class="button button-secondary button-small wishlist-button" hidden
                    data-wishlist-toggle data-listing-id="<?= e((string) $listing['id']) ?>"
                    aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"><?= $isSaved ? '♥ Saved' : '♡ Save' ?></button>
            <p id="wishlist-message" class="field-error" role="alert" hidden></p>
        <?php endif; ?>

        <dl class="facts">
            <dt>Condition</dt>
            <dd><?= e(LISTING_CONDITIONS[$listing['item_condition']]) ?></dd>

            <dt>Category</dt>
            <dd><?= e($listing['category_name']) ?></dd>

            <dt>Meetup location</dt>
            <dd><?= e($listing['meetup_location']) ?></dd>

            <dt>Posted</dt>
            <dd><?= e(date('j M Y', strtotime($listing['created_at']))) ?></dd>
        </dl>

        <h2>Description</h2>
        <?php // e() first makes the text safe; nl2br() then turns line breaks into <br>. ?>
        <p class="description"><?= nl2br(e($listing['description'])) ?></p>

        <section class="seller-card">
            <h2>Seller</h2>
            <div class="profile-head">
                <?= avatar_html($seller) ?>
                <div>
                    <p class="seller-name"><?= e($listing['seller_name']) ?></p>
                    <?php if ($listing['seller_faculty'] !== null): ?>
                        <p class="note"><?= e($listing['seller_faculty']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isOwner): ?>
                <p class="note">
                    This is your listing.
                    <a href="/pages/listing-edit.php?id=<?= e((string) $listing['id']) ?>">Edit it</a>
                    or manage it in <a href="/pages/my-listings.php">My listings</a>.
                </p>
            <?php elseif ($currentUser === null): ?>
                <?php // Messaging and phone numbers are for logged-in students only, never guests. ?>
                <?php if ($listing['status'] === 'sold'): ?>
                    <p class="note">This item has been sold.</p>
                <?php else: ?>
                    <a class="button" href="/pages/login.php">Log in to contact the seller</a>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($myConversationId !== null): ?>
                    <?php // A buyer has one conversation per listing (FR-17), so link to it. ?>
                    <a class="button" href="/pages/conversation.php?id=<?= e((string) $myConversationId) ?>#latest">
                        Open your conversation
                    </a>
                <?php elseif ($listing['status'] === 'available' && !$listing['seller_is_banned']): ?>
                    <form method="post" action="/pages/conversation-start.php" class="message-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="listing_id" value="<?= e((string) $listing['id']) ?>">

                        <label for="message-body">Message the seller</label>
                        <textarea id="message-body" name="body" rows="3" required
                                  maxlength="<?= MAX_MESSAGE_LENGTH ?>" placeholder="Hi, is this still available?"></textarea>
                        <button type="submit" class="button">Send message</button>
                    </form>
                <?php endif; ?>

                <?php if ($listing['status'] === 'sold'): ?>
                    <p class="note">This item has been sold.</p>
                <?php elseif ($listing['seller_phone'] !== null && !$listing['seller_is_banned']): ?>
                    <a class="button button-secondary" href="<?= e(whatsapp_link($listing['seller_phone'])) ?>" target="_blank" rel="noopener">
                        Message on WhatsApp
                    </a>
                    <p class="note">Or call <a href="tel:<?= e($listing['seller_phone']) ?>"><?= e($listing['seller_phone']) ?></a></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($alreadyReported): ?>
            <p class="note report-note">You reported this listing. An admin will review it.</p>
        <?php elseif ($canReport): ?>
            <?php // Hidden until assets/js/report.js runs, because the form is sent with fetch(). ?>
            <details id="report-box" class="report-box" hidden>
                <summary>Report this listing</summary>

                <form id="report-form">
                    <input type="hidden" name="listing_id" value="<?= e((string) $listing['id']) ?>">

                    <label for="report-reason">Reason</label>
                    <select id="report-reason" name="reason" required>
                        <option value="">Choose a reason</option>
                        <?php foreach (REPORT_REASONS as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="report-details">Details <span class="optional">(optional)</span></label>
                    <textarea id="report-details" name="details" rows="3" maxlength="<?= MAX_REPORT_DETAILS ?>"
                              placeholder="What is wrong with this listing?"></textarea>

                    <p id="report-message" class="field-error" role="alert" hidden></p>
                    <button type="submit" class="button button-danger button-small">Send report</button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</article>

<?php if (count($photos) > 1): ?>
    <script src="/assets/js/gallery.js"></script>
<?php endif; ?>

<?php if ($canReport && !$alreadyReported): ?>
    <script src="/assets/js/report.js"></script>
<?php endif; ?>

<?php if ($canSave): ?>
    <script src="/assets/js/wishlist.js"></script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
