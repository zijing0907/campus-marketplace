<?php
// Handling uploaded images (FR-11). Used for profile photos now, listing photos later.
//
// Nothing the browser says about a file can be trusted: not its name, not its type,
// not its size. So this code looks at the file's actual contents, chooses the file
// extension itself, and saves the file under a random name.

// The largest image accepted, in bytes (2 MB).
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

// The image types accepted, and the extension each one is saved with.
const ALLOWED_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * True if a form was submitted but PHP threw the whole request away because it was
 * bigger than the post_max_size setting. $_POST and $_FILES then arrive empty, with
 * no CSRF token either, so pages with an upload check for this case first.
 */
function upload_exceeded_post_limit(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST'
        && empty($_POST)
        && empty($_FILES)
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

/**
 * True if the user chose a file for this upload field.
 * $file is one entry of $_FILES, for example $_FILES['avatar'], or null if absent.
 */
function has_upload(?array $file): bool
{
    return $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Check one uploaded image and move it into uploads/<folder>/ under a random name.
 *
 * Returns ['path' => 'uploads/avatars/3f9c...e1.jpg'] on success,
 * or ['error' => 'a message to show the user'] if the file is not acceptable.
 */
function save_uploaded_image(array $file, string $folder): array
{
    $tooLarge = 'That image is too large. The limit is ' . (MAX_IMAGE_BYTES / 1024 / 1024) . ' MB.';

    // PHP sets 'error' to a number. An array here means several files were sent
    // under one field name, which this site's forms never do.
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error)) {
        return ['error' => 'Upload one image at a time.'];
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => $tooLarge];
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'The upload did not complete. Please try again.'];
    }

    // Measure the file on the server instead of trusting the size the browser sent.
    if (filesize($file['tmp_name']) > MAX_IMAGE_BYTES) {
        return ['error' => $tooLarge];
    }

    // getimagesize() reads the file's own header. It returns false for anything that
    // is not an image, such as a PHP script renamed to photo.jpg. The @ hides the
    // warning it prints for damaged files, since false is handled just below.
    $info = @getimagesize($file['tmp_name']);
    $type = $info ? $info['mime'] : '';
    if (!isset(ALLOWED_IMAGE_TYPES[$type])) {
        return ['error' => 'Use a JPG, PNG or WebP image.'];
    }

    $directory = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Cannot create the folder uploads/' . $folder);
    }

    // The saved name is random, and its extension comes from the detected type.
    // The name the file had on the user's device is never used, so an upload cannot
    // overwrite another file, climb out of the folder with "../", or end in ".php".
    $name = bin2hex(random_bytes(16)) . '.' . ALLOWED_IMAGE_TYPES[$type];

    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
        throw new RuntimeException('Cannot save the uploaded file.');
    }

    return ['path' => 'uploads/' . $folder . '/' . $name];
}

/**
 * Delete a file that save_uploaded_image() stored earlier, given its saved path.
 */
function delete_uploaded_file(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }

    // realpath() resolves any "../" in a path. The file is deleted only if it really
    // is inside the uploads folder, whatever the stored value says.
    $uploads = realpath(__DIR__ . '/../uploads');
    $file = realpath(__DIR__ . '/../' . $path);

    if ($uploads !== false
        && $file !== false
        && str_starts_with($file, $uploads . DIRECTORY_SEPARATOR)
        && is_file($file)
    ) {
        unlink($file);
    }
}
