<?php
// Sell an item: create a new listing with 1 to 5 photos (FR-07, FR-11).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$categories = all_categories();
$categoryIds = array_column($categories, 'id');

// Values to show in the form. They stay empty until the form is submitted.
$errors = [];
$title = '';
$description = '';
$price = '';
$categoryId = '';
$condition = '';
$location = '';

if (upload_exceeded_post_limit()) {
    // The request was so large that PHP discarded all of it, CSRF token included.
    $errors['photos'] = 'Those photos were too large to upload together. Try fewer or smaller photos.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = post_text('title');
    $description = post_text('description');
    $price = post_text('price');
    $categoryId = post_text('category_id');
    $condition = post_text('condition');
    $location = post_text('meetup_location');
    $photos = uploaded_files('photos');

    if (mb_strlen($title) < 3 || mb_strlen($title) > 120) {
        $errors['title'] = 'Enter a title (3 to 120 characters).';
    }

    if ($description === '' || mb_strlen($description) > 2000) {
        $errors['description'] = 'Describe the item (up to 2000 characters).';
    }

    // Up to 6 digits, then optionally a dot and 1 or 2 more digits: 35, 35.5, 35.50.
    // The price stays a string all the way to the database, which stores it as an
    // exact DECIMAL. Turning money into a float can shift it by tiny fractions.
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $price)) {
        $errors['price'] = 'Enter a price between 0 and 999999.99, for example 35 or 35.50.';
    }

    // The chosen category must be one that really exists in the database.
    if (!ctype_digit($categoryId) || !in_array((int) $categoryId, $categoryIds, true)) {
        $errors['category_id'] = 'Choose a category.';
    }

    if (!isset(LISTING_CONDITIONS[$condition])) {
        $errors['condition'] = 'Choose the condition of the item.';
    }

    if (mb_strlen($location) < 2 || mb_strlen($location) > 120) {
        $errors['meetup_location'] = 'Say where on campus you can meet (2 to 120 characters).';
    }

    if (count($photos) < 1) {
        $errors['photos'] = 'Add at least one photo.';
    } elseif (count($photos) > MAX_LISTING_PHOTOS) {
        $errors['photos'] = 'You can add at most ' . MAX_LISTING_PHOTOS . ' photos.';
    } elseif ($errors) {
        // Browsers never refill a file field, so the photos have to be picked again.
        $errors['photos'] = 'Please choose your photos again.';
    }

    // Save the photos only once everything else is valid. If one of them is
    // rejected, delete the ones saved before it, so no stray files are left behind.
    $savedPaths = [];
    if (!$errors) {
        foreach ($photos as $i => $photo) {
            $result = save_uploaded_image($photo, 'listings');
            if (isset($result['error'])) {
                $errors['photos'] = 'Photo ' . ($i + 1) . ': ' . $result['error'];
                break;
            }
            $savedPaths[] = $result['path'];
        }

        if ($errors) {
            foreach ($savedPaths as $path) {
                delete_uploaded_file($path);
            }
        }
    }

    if (!$errors) {
        // A transaction makes the listing row and its photo rows succeed or fail
        // together. Without it, an error halfway could leave a listing with no photos.
        $pdo = db();
        $pdo->beginTransaction();

        try {
            // seller_id comes from the session, never from the form (NFR-04).
            $stmt = $pdo->prepare(
                'INSERT INTO listings (seller_id, category_id, title, description, price, item_condition, meetup_location)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$user['id'], (int) $categoryId, $title, $description, $price, $condition, $location]);
            $listingId = (int) $pdo->lastInsertId();

            // sort_order keeps the photos in the order they were chosen; 0 is the cover.
            $stmt = $pdo->prepare('INSERT INTO listing_images (listing_id, file_path, sort_order) VALUES (?, ?, ?)');
            foreach ($savedPaths as $order => $path) {
                $stmt->execute([$listingId, $path, $order]);
            }

            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            foreach ($savedPaths as $path) {
                delete_uploaded_file($path);
            }
            throw $ex;
        }

        set_flash('success', 'Your listing is live.');
        redirect('/pages/listing.php?id=' . $listingId);
    }
}

$pageTitle = 'Sell an item';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide">
    <h1>Sell an item</h1>

    <?php if ($user['phone'] === null): ?>
        <p class="alert alert-info">
            Buyers contact you on WhatsApp.
            <a href="/pages/profile-edit.php">Add a phone number to your profile</a> so they can reach you.
        </p>
    <?php endif; ?>

    <form method="post" action="/pages/listing-create.php" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($title) ?>"
               required minlength="3" maxlength="120" placeholder="Casio fx-570 scientific calculator">
        <?= field_error($errors, 'title') ?>

        <div class="form-row">
            <div>
                <label for="price">Price (RM)</label>
                <input type="number" id="price" name="price" value="<?= e($price) ?>"
                       required min="0" max="999999.99" step="0.01" inputmode="decimal" placeholder="35.00">
                <?= field_error($errors, 'price') ?>
            </div>

            <div>
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Choose a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= (string) $category['id'] === $categoryId ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'category_id') ?>
            </div>
        </div>

        <label for="condition">Condition</label>
        <select id="condition" name="condition" required>
            <option value="">Choose a condition</option>
            <?php foreach (LISTING_CONDITIONS as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $value === $condition ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'condition') ?>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5" required maxlength="2000"
                  placeholder="What is it, how old is it, and is anything wrong with it?"><?= e($description) ?></textarea>
        <?= field_error($errors, 'description') ?>

        <label for="meetup_location">Meetup location</label>
        <input type="text" id="meetup_location" name="meetup_location" value="<?= e($location) ?>"
               required minlength="2" maxlength="120" placeholder="Library entrance">
        <?= field_error($errors, 'meetup_location') ?>

        <label for="photos">Photos</label>
        <input type="file" id="photos" name="photos[]" multiple required
               accept="image/jpeg,image/png,image/webp"
               data-resize-max="1200" data-max-files="<?= MAX_LISTING_PHOTOS ?>" data-preview-list="#photo-previews">
        <p class="hint">Choose 1 to <?= MAX_LISTING_PHOTOS ?> photos together. The first one is the cover. JPG, PNG or WebP.</p>
        <div id="photo-previews" class="photo-previews"></div>
        <?= field_error($errors, 'photos') ?>

        <button type="submit" class="button">Post listing</button>
    </form>

    <p class="form-footer"><a href="/">Cancel</a></p>
</section>

<script src="/assets/js/image-upload.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
