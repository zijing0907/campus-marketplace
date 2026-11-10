<?php
// Sell an item: create a new listing with 1 to 5 photos (FR-07, FR-11).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$categories = all_categories();

// Values to show in the form. They stay empty until the form is submitted.
$errors = [];
$data = [
    'title'           => '',
    'description'     => '',
    'price'           => '',
    'category_id'     => '',
    'condition'       => '',
    'meetup_location' => '',
];

if (upload_exceeded_post_limit()) {
    // The request was so large that PHP discarded all of it, CSRF token included.
    $errors['photos'] = 'Those photos were too large to upload together. Try fewer or smaller photos.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $data = listing_form_input();
    $errors = validate_listing($data, $categories);
    $photos = uploaded_files('photos');

    if (count($photos) < 1) {
        $errors['photos'] = 'Add at least one photo.';
    } elseif (count($photos) > MAX_LISTING_PHOTOS) {
        $errors['photos'] = 'You can add at most ' . MAX_LISTING_PHOTOS . ' photos.';
    } elseif ($errors) {
        // Browsers never refill a file field, so the photos have to be picked again.
        $errors['photos'] = 'Please choose your photos again.';
    }

    // Save the photos only once everything else is valid, so no file is stored
    // for a form that is about to be rejected.
    $savedPaths = [];
    if (!$errors) {
        $result = save_listing_photos($photos);
        if (isset($result['error'])) {
            $errors['photos'] = $result['error'];
        } else {
            $savedPaths = $result['paths'];
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
            $stmt->execute([
                $user['id'],
                (int) $data['category_id'],
                $data['title'],
                $data['description'],
                $data['price'],
                $data['condition'],
                $data['meetup_location'],
            ]);
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

        <?php require __DIR__ . '/../includes/listing-fields.php'; ?>

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
