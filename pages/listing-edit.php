<?php
// Edit one of my listings: its details and its photos (FR-08).
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$listing = null;
if (is_int($id) && $id > 0) {
    $stmt = db()->prepare(
        'SELECT id, seller_id, category_id, title, description, price, item_condition, meetup_location, status
         FROM listings
         WHERE id = ?'
    );
    $stmt->execute([$id]);
    $listing = $stmt->fetch() ?: null;
}

if ($listing === null) {
    $notFoundMessage = 'This listing does not exist or has been removed.';
    require __DIR__ . '/../includes/not-found.php';
}

// The ownership check (FR-08, NFR-04). Hiding the Edit button from other people is
// not enough: anyone can type listing-edit.php?id=5 into the address bar. The server
// has to compare the listing's seller with the logged-in user on every request.
if ((int) $listing['seller_id'] !== (int) $user['id']) {
    $forbiddenMessage = 'You can only edit your own listings.';
    require __DIR__ . '/../includes/forbidden.php';
}

$categories = all_categories();

$stmt = db()->prepare('SELECT id, file_path, sort_order FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id');
$stmt->execute([$listing['id']]);
$existingPhotos = $stmt->fetchAll();

// The form starts out showing what is already saved.
$errors = [];
$removeIds = [];
$data = [
    'title'           => $listing['title'],
    'description'     => $listing['description'],
    'price'           => $listing['price'],
    'category_id'     => (string) $listing['category_id'],
    'condition'       => $listing['item_condition'],
    'meetup_location' => $listing['meetup_location'],
];

if (upload_exceeded_post_limit()) {
    // The request was so large that PHP discarded all of it, CSRF token included.
    $errors['photos'] = 'Those photos were too large to upload together. Try fewer or smaller photos.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $data = listing_form_input();
    $errors = validate_listing($data, $categories);
    $newPhotos = uploaded_files('photos');

    // Which existing photos were ticked for removal. Only ids that really belong to
    // this listing count, so a made-up id cannot touch another listing's photos.
    $ticked = $_POST['remove_photos'] ?? [];
    $ticked = is_array($ticked) ? $ticked : [];
    $removedPhotos = [];
    foreach ($existingPhotos as $photo) {
        if (in_array((string) $photo['id'], $ticked, true)) {
            $removedPhotos[] = $photo;
            $removeIds[] = (int) $photo['id'];
        }
    }

    // What matters is the total after this edit: photos kept plus photos added.
    $total = count($existingPhotos) - count($removedPhotos) + count($newPhotos);
    if ($total < 1) {
        $errors['photos'] = 'A listing needs at least one photo.';
    } elseif ($total > MAX_LISTING_PHOTOS) {
        $errors['photos'] = 'A listing can have at most ' . MAX_LISTING_PHOTOS . ' photos. Remove some, or add fewer.';
    } elseif ($errors && $newPhotos) {
        // Browsers never refill a file field, so new photos have to be picked again.
        $errors['photos'] = 'Please choose your new photos again.';
    }

    // Save the new photos only once everything else is valid.
    $newPaths = [];
    if (!$errors && $newPhotos) {
        $result = save_listing_photos($newPhotos);
        if (isset($result['error'])) {
            $errors['photos'] = $result['error'];
        } else {
            $newPaths = $result['paths'];
        }
    }

    if (!$errors) {
        // One transaction for the listing and its photo rows, so a failure halfway
        // leaves the listing exactly as it was.
        $pdo = db();
        $pdo->beginTransaction();

        try {
            // "AND seller_id = ?" repeats the ownership rule inside the query itself.
            $stmt = $pdo->prepare(
                'UPDATE listings
                 SET category_id = ?, title = ?, description = ?, price = ?, item_condition = ?, meetup_location = ?
                 WHERE id = ? AND seller_id = ?'
            );
            $stmt->execute([
                (int) $data['category_id'],
                $data['title'],
                $data['description'],
                $data['price'],
                $data['condition'],
                $data['meetup_location'],
                $listing['id'],
                $user['id'],
            ]);

            $stmt = $pdo->prepare('DELETE FROM listing_images WHERE id = ? AND listing_id = ?');
            foreach ($removeIds as $photoId) {
                $stmt->execute([$photoId, $listing['id']]);
            }

            // New photos go after the existing ones. The photo with the lowest
            // sort_order that is left is the cover.
            $nextOrder = $existingPhotos ? max(array_column($existingPhotos, 'sort_order')) + 1 : 0;
            $stmt = $pdo->prepare('INSERT INTO listing_images (listing_id, file_path, sort_order) VALUES (?, ?, ?)');
            foreach ($newPaths as $path) {
                $stmt->execute([$listing['id'], $path, $nextOrder]);
                $nextOrder++;
            }

            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            foreach ($newPaths as $path) {
                delete_uploaded_file($path);
            }
            throw $ex;
        }

        // Only after the database change is safely stored are the removed photos'
        // files deleted from disk.
        foreach ($removedPhotos as $photo) {
            delete_uploaded_file($photo['file_path']);
        }

        // Cheaper than before, and still for sale? Tell everyone who saved it.
        // Prices are compared as numbers: "70.00" < "85.00".
        if ($listing['status'] === 'available' && (float) $data['price'] < (float) $listing['price']) {
            notify_savers((int) $listing['id'], NOTE_SAVED_PRICE_DROP, [(int) $user['id']],
                number_format((float) $listing['price'], 2, '.', '') . '|' . number_format((float) $data['price'], 2, '.', ''));
        }

        set_flash('success', 'Your listing has been updated.');
        redirect('/pages/listing.php?id=' . $listing['id']);
    }
}

$pageTitle = 'Edit listing';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide">
    <h1>Edit listing</h1>

    <form method="post" action="/pages/listing-edit.php?id=<?= e((string) $listing['id']) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <?php require __DIR__ . '/../includes/listing-fields.php'; ?>

        <p class="label">Current photos</p>
        <div class="edit-photos">
            <?php foreach ($existingPhotos as $i => $photo): ?>
                <label class="edit-photo">
                    <img src="/<?= e($photo['file_path']) ?>" alt="Photo <?= $i + 1 ?>">
                    <span>
                        <input type="checkbox" name="remove_photos[]" value="<?= e((string) $photo['id']) ?>"
                               <?= in_array((int) $photo['id'], $removeIds, true) ? 'checked' : '' ?>>
                        Remove
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <label for="photos">Add photos <span class="optional">(optional)</span></label>
        <input type="file" id="photos" name="photos[]" multiple
               accept="image/jpeg,image/png,image/webp"
               data-resize-max="1200" data-max-files="<?= MAX_LISTING_PHOTOS ?>" data-preview-list="#photo-previews">
        <p class="hint">A listing has 1 to <?= MAX_LISTING_PHOTOS ?> photos in total. The first one is the cover.</p>
        <div id="photo-previews" class="photo-previews"></div>
        <?= field_error($errors, 'photos') ?>

        <button type="submit" class="button">Save changes</button>
    </form>

    <p class="form-footer"><a href="/pages/my-listings.php">Cancel</a></p>
</section>

<script src="/assets/js/image-upload.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
