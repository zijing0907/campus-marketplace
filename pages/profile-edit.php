<?php
// Edit my profile (FR-04): display name, faculty, phone and profile photo.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

// The form starts out showing what is already saved.
$errors = [];
$name = $user['name'];
$faculty = $user['faculty'] ?? '';
$phone = $user['phone'] ?? '';

if (upload_exceeded_post_limit()) {
    // The request was so large that PHP discarded all of it, CSRF token included.
    // Nothing was saved, so just explain what happened.
    $errors['avatar'] = 'That upload was too large. Choose an image under 2 MB.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = post_text('name');
    $faculty = post_text('faculty');
    $phone = post_text('phone');
    $removePhoto = post_text('remove_photo') === '1';

    $nameError = validate_name($name);
    if ($nameError !== null) {
        $errors['name'] = $nameError;
    }

    if (mb_strlen($faculty) > 100) {
        $errors['faculty'] = 'Faculty can be at most 100 characters.';
    }

    // People write numbers in many ways: "012-345 6789", "+60 12 345 6789".
    // Remove the spaces, dashes and brackets, then check what is left.
    $phoneDigits = preg_replace('/[\s\-()]/', '', $phone);
    if ($phone !== '' && !preg_match('/^\+?[0-9]{8,15}$/', $phoneDigits)) {
        $errors['phone'] = 'Enter a valid phone number, for example 012-345 6789.';
    }

    // The photo is handled last, and only if everything else is valid, so a file is
    // never saved for a form that is about to be rejected.
    $avatarPath = $user['avatar_path'];
    $file = $_FILES['avatar'] ?? null;

    if (!$errors && has_upload($file)) {
        $result = save_uploaded_image($file, 'avatars');
        if (isset($result['error'])) {
            $errors['avatar'] = $result['error'];
        } else {
            $avatarPath = $result['path'];
        }
    } elseif (!$errors && $removePhoto) {
        $avatarPath = null;
    }

    if (!$errors) {
        // The id comes from the session, never from the form, so a user can only
        // ever change their own profile (NFR-04).
        $stmt = db()->prepare('UPDATE users SET name = ?, faculty = ?, phone = ?, avatar_path = ? WHERE id = ?');
        $stmt->execute([
            $name,
            $faculty !== '' ? $faculty : null,   // empty optional fields are stored as NULL
            $phone !== '' ? $phoneDigits : null,
            $avatarPath,
            $user['id'],
        ]);

        // If the photo changed or was removed, the old file is no longer needed.
        if ($avatarPath !== $user['avatar_path']) {
            delete_uploaded_file($user['avatar_path']);
        }

        set_flash('success', 'Your profile has been updated.');
        redirect('/pages/profile.php');
    }
}

$pageTitle = 'Edit profile';
require __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h1>Edit profile</h1>
    <p class="note"><?= e($user['email']) ?></p>

    <?php // enctype="multipart/form-data" is required for a form that uploads a file. ?>
    <form method="post" action="/pages/profile-edit.php" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label for="name">Display name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>"
               required minlength="2" maxlength="100" autocomplete="name">
        <?= field_error($errors, 'name') ?>

        <label for="faculty">Faculty <span class="optional">(optional)</span></label>
        <input type="text" id="faculty" name="faculty" value="<?= e($faculty) ?>" maxlength="100">
        <?= field_error($errors, 'faculty') ?>

        <label for="phone">Phone or WhatsApp <span class="optional">(optional)</span></label>
        <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>"
               maxlength="20" autocomplete="tel" placeholder="012-345 6789">
        <?= field_error($errors, 'phone') ?>

        <label for="avatar">Profile photo <span class="optional">(optional)</span></label>
        <div class="photo-row">
            <span id="avatar-current"><?= avatar_html($user) ?></span>
            <img id="avatar-preview" class="avatar" alt="Preview of your new photo" hidden>
            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp"
                   data-resize-max="400" data-preview="#avatar-preview" data-preview-hides="#avatar-current">
        </div>
        <p class="hint">JPG, PNG or WebP, up to 2 MB.</p>
        <?= field_error($errors, 'avatar') ?>

        <?php if ($user['avatar_path'] !== null): ?>
            <label class="checkbox">
                <input type="checkbox" name="remove_photo" value="1">
                Remove my current photo
            </label>
        <?php endif; ?>

        <button type="submit" class="button">Save changes</button>
    </form>

    <p class="form-footer"><a href="/pages/profile.php">Cancel</a></p>
</section>

<script src="/assets/js/image-upload.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
