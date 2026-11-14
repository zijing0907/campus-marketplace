<?php
// A student's public profile: who they are, what they are selling, and the
// ratings buyers have given them (FR-20). Open to everyone.
//
// Deliberately NOT shown here: email address and phone number. A phone number
// appears only on a listing page, and only to logged-in students.
require __DIR__ . '/../includes/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$profile = null;
if (is_int($id) && $id > 0) {
    $stmt = db()->prepare('SELECT id, name, faculty, avatar_path, is_banned, deleted_at, created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $profile = $stmt->fetch() ?: null;
}

$isAdmin = $currentUser !== null && $currentUser['role'] === 'admin';

// A banned student's profile is "not found" for everyone except admins, the same
// as their listings.
if ($profile === null || ($profile['is_banned'] && !$isAdmin)) {
    $notFoundMessage = 'This profile does not exist.';
    require __DIR__ . '/../includes/not-found.php';
}

$rating = seller_rating((int) $profile['id']);

// What they are selling now, newest first, each with its cover photo.
$stmt = db()->prepare(
    "SELECT l.id, l.title, l.price, l.item_condition, l.meetup_location,
            (SELECT i.file_path
             FROM listing_images i
             WHERE i.listing_id = l.id
             ORDER BY i.sort_order, i.id
             LIMIT 1) AS cover_path
     FROM listings l
     WHERE l.seller_id = ? AND l.status = 'available'
     ORDER BY l.created_at DESC, l.id DESC"
);
$stmt->execute([$profile['id']]);
$listings = $stmt->fetchAll();

$stmt = db()->prepare("SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'sold'");
$stmt->execute([$profile['id']]);
$soldCount = (int) $stmt->fetchColumn();

// The ratings they received, newest first, with who gave each one and for which item.
$stmt = db()->prepare(
    'SELECT r.rating, r.comment, r.created_at, reviewer.name AS reviewer_name, l.title AS listing_title
     FROM reviews r
     JOIN users reviewer ON reviewer.id = r.reviewer_id
     JOIN listings l ON l.id = r.listing_id
     WHERE r.seller_id = ?
     ORDER BY r.created_at DESC, r.id DESC'
);
$stmt->execute([$profile['id']]);
$reviews = $stmt->fetchAll();

$isMe = $currentUser !== null && (int) $currentUser['id'] === (int) $profile['id'];

$pageTitle = $profile['name'];
require __DIR__ . '/../includes/header.php';
?>

<?php if ($profile['deleted_at'] !== null): ?>
    <p class="alert alert-info">This student deleted their account. Only admins can see this profile.</p>
<?php elseif ($profile['is_banned']): ?>
    <p class="alert alert-info">This student is banned. Only admins can see this profile.</p>
<?php endif; ?>

<section class="profile-card">
    <div class="profile-head">
        <?= avatar_html($profile) ?>
        <div>
            <h1><?= e($profile['name']) ?></h1>
            <?php if ($profile['faculty'] !== null): ?>
                <p class="note"><?= e($profile['faculty']) ?></p>
            <?php endif; ?>
            <p class="note">
                Member since <?= e(date('M Y', strtotime($profile['created_at']))) ?>
                · <?= $soldCount ?> <?= $soldCount === 1 ? 'item' : 'items' ?> sold
            </p>
            <p class="profile-rating"><?= e(rating_summary($rating)) ?></p>
        </div>
    </div>

    <?php if ($isMe): ?>
        <p class="note">This is how other students see you. <a href="/pages/profile-edit.php">Edit your profile</a></p>
    <?php endif; ?>
</section>

<h2 class="section-title">For sale (<?= count($listings) ?>)</h2>
<?php if (!$listings): ?>
    <p class="empty">Nothing for sale at the moment.</p>
<?php else: ?>
    <div class="listing-grid">
        <?php foreach ($listings as $row): ?>
            <?php require __DIR__ . '/../includes/listing-card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 class="section-title" id="ratings">Ratings (<?= count($reviews) ?>)</h2>
<?php if (!$reviews): ?>
    <p class="empty">No ratings yet. A rating appears here after a buyer rates a sale.</p>
<?php else: ?>
    <ul class="reviews">
        <?php foreach ($reviews as $review): ?>
            <li class="review">
                <p class="review-stars"><?= stars_html((int) $review['rating']) ?></p>
                <?php if ($review['comment'] !== null): ?>
                    <?php // Written by a student, so it is escaped like any other user text. ?>
                    <p class="description"><?= nl2br(e($review['comment'])) ?></p>
                <?php endif; ?>
                <p class="note">
                    <?= e($review['reviewer_name']) ?> · bought <?= e($review['listing_title']) ?>
                    · <?= e(date('j M Y', strtotime($review['created_at']))) ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
