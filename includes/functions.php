<?php
// Small helper functions shared by every page.

/**
 * Escape text before printing it into HTML (NFR-02, prevents XSS).
 * Use it on anything that came from a user or the database: <?= e($title) ?>
 */
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Return the settings from config/config.php. The file is read only once per request.
 */
function config(): array
{
    static $config = null;

    if ($config === null) {
        $file = __DIR__ . '/../config/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('Missing config/config.php. Copy config/config.example.php and fill it in.');
        }
        $config = require $file;
    }

    return $config;
}

/**
 * Read one text field from a submitted form. Always returns a string, even if
 * the field is missing or someone sends an array instead of text.
 * Pass trim: false for passwords, where spaces at the ends may be intentional.
 */
function post_text(string $field, bool $trim = true): string
{
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return $trim ? trim($value) : $value;
}

/**
 * Read one text value from the address's query string, such as ?q=desk.
 * Always returns a string, even if the value is missing or sent as an array.
 */
function get_text(string $field): string
{
    $value = $_GET[$field] ?? '';

    return is_string($value) ? trim($value) : '';
}

/**
 * Send the browser to another page and stop this script.
 */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Flash message: saved in the session and shown once, on the next page.
 * Needed because a redirect starts a brand-new request that remembers nothing else.
 * $type is 'success', 'error' or 'info'.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Return the waiting flash message, if any, and remove it so it shows only once.
 */
function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

/**
 * Check a display name. Returns an error message, or null if the name is fine.
 * Shared by the register and edit-profile forms so the rule lives in one place.
 */
function validate_name(string $name): ?string
{
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        return 'Enter your name (2 to 100 characters).';
    }

    return null;
}

/**
 * HTML for a user's profile photo, or a circle with their initial if they have none.
 * $user is a row from the users table; it needs the name and avatar_path columns.
 */
function avatar_html(array $user): string
{
    if (!empty($user['avatar_path'])) {
        // alt is empty because the user's name is always shown next to the photo.
        return '<img class="avatar" src="/' . e($user['avatar_path']) . '" alt="">';
    }

    $initial = mb_strtoupper(mb_substr($user['name'], 0, 1));

    return '<span class="avatar avatar-placeholder" aria-hidden="true">' . e($initial) . '</span>';
}

/**
 * HTML for one field's validation message, or an empty string if the field is fine.
 */
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field-error">' . e($errors[$field]) . '</p>';
}
