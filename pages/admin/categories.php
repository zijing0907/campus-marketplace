<?php
// Admin: add, rename and remove the categories listings are filed under (FR-25).
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();

// Every category with the number of listings filed under it. LEFT JOIN keeps a
// category in the list even when it has no listings yet (its count is then 0).
$stmt = db()->prepare(
    'SELECT c.id, c.name, COUNT(l.id) AS listing_count
     FROM categories c
     LEFT JOIN listings l ON l.category_id = c.id
     GROUP BY c.id, c.name
     ORDER BY c.name = ?, c.name'
);
$stmt->execute([FALLBACK_CATEGORY]);
$categories = $stmt->fetchAll();

$pageTitle = 'Categories';
$adminPage = 'categories';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/admin-nav.php';
?>

<div class="page-head">
    <h1>Categories</h1>
</div>

<form class="search-form" method="post" action="/pages/admin/category-action.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">

    <div class="search-main">
        <label for="new-category" class="visually-hidden">New category name</label>
        <input type="text" id="new-category" name="name" required minlength="2" maxlength="50" placeholder="New category name">
        <button type="submit" class="button">Add category</button>
    </div>
</form>

<ul class="admin-list">
    <?php foreach ($categories as $category): ?>
        <?php $count = (int) $category['listing_count']; ?>
        <li class="admin-row">
            <?php if ($category['name'] === FALLBACK_CATEGORY): ?>
                <div class="admin-row-main">
                    <span class="admin-row-title"><?= e($category['name']) ?></span>
                    <span class="note">
                        <?= $count ?> <?= $count === 1 ? 'listing' : 'listings' ?>
                        · the catch-all category, so it cannot be renamed or removed
                    </span>
                </div>
            <?php else: ?>
                <form class="admin-row-main rename-form" method="post" action="/pages/admin/category-action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">

                    <label for="category-<?= e((string) $category['id']) ?>" class="visually-hidden">Name of the category <?= e($category['name']) ?></label>
                    <input type="text" id="category-<?= e((string) $category['id']) ?>" name="name" value="<?= e($category['name']) ?>"
                           required minlength="2" maxlength="50">
                    <button type="submit" class="button button-secondary button-small">Rename</button>
                    <span class="note"><?= $count ?> <?= $count === 1 ? 'listing' : 'listings' ?></span>
                </form>

                <div class="admin-row-actions">
                    <form method="post" action="/pages/admin/category-action.php"
                          data-confirm="Remove this category? Its <?= $count ?> <?= $count === 1 ? 'listing' : 'listings' ?> will be moved to <?= e(FALLBACK_CATEGORY) ?>.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">
                        <button type="submit" class="button button-danger button-small">Remove</button>
                    </form>
                </div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<script src="/assets/js/confirm.js"></script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
