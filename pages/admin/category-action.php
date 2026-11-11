<?php
// Admin: add, rename or remove a category (FR-25). The forms on the categories
// page post here.
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();

$back = '/pages/admin/categories.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($back);
}
verify_csrf();

$action = post_text('action');
$name = post_text('name');
$categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);

// Adding and renaming both take a name, checked the same way.
if ($action === 'add' || $action === 'rename') {
    if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
        set_flash('error', 'A category name must be 2 to 50 characters.');
        redirect($back);
    }
}

// Renaming and removing both act on an existing category, which must not be the
// catch-all one.
$category = null;
if ($action === 'rename' || $action === 'remove') {
    if (is_int($categoryId) && $categoryId > 0) {
        $stmt = db()->prepare('SELECT id, name FROM categories WHERE id = ?');
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch() ?: null;
    }

    if ($category === null) {
        set_flash('error', 'That category does not exist.');
        redirect($back);
    }
    if ($category['name'] === FALLBACK_CATEGORY) {
        set_flash('error', 'The ' . FALLBACK_CATEGORY . ' category cannot be renamed or removed.');
        redirect($back);
    }
}

try {
    if ($action === 'add') {
        $stmt = db()->prepare('INSERT INTO categories (name) VALUES (?)');
        $stmt->execute([$name]);
        set_flash('success', 'Category "' . $name . '" added.');
    } elseif ($action === 'rename') {
        // Listings point at a category by its id, so renaming it needs no change
        // to any listing. That is the benefit of linking tables by id, not by name.
        $stmt = db()->prepare('UPDATE categories SET name = ? WHERE id = ?');
        $stmt->execute([$name, $category['id']]);
        set_flash('success', 'Category renamed to "' . $name . '".');
    } elseif ($action === 'remove') {
        $stmt = db()->prepare('SELECT id FROM categories WHERE name = ?');
        $stmt->execute([FALLBACK_CATEGORY]);
        $fallbackId = $stmt->fetchColumn();

        // The database refuses to delete a category that listings still point at
        // (the foreign key protects them). So the listings are moved to the
        // catch-all category first, and both steps are one transaction.
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $moved = 0;
            if ($fallbackId !== false) {
                $stmt = $pdo->prepare('UPDATE listings SET category_id = ? WHERE category_id = ?');
                $stmt->execute([$fallbackId, $category['id']]);
                $moved = $stmt->rowCount();
            }

            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$category['id']]);

            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        set_flash('success', 'Category "' . $category['name'] . '" removed. Listings moved to ' . FALLBACK_CATEGORY . ': ' . $moved . '.');
    } else {
        set_flash('error', 'Unknown action.');
    }
} catch (PDOException $ex) {
    // 1062: the name already exists (categories.name is UNIQUE).
    // 1451: listings still use the category and there was nowhere to move them.
    $code = $ex->errorInfo[1] ?? null;
    if ($code === 1062) {
        set_flash('error', 'A category named "' . $name . '" already exists.');
    } elseif ($code === 1451) {
        set_flash('error', 'That category still has listings and cannot be removed.');
    } else {
        throw $ex;
    }
}

redirect($back);
