<?php
// Helpers for the JSON API files in /api (section 6 of the spec).
//
// A normal page replies with HTML for a person to read. An API file replies with
// JSON for JavaScript to read. Every reply has the same shape, so the JavaScript
// only needs one way of reading replies:
//   {"success": true,  "data": ...}
//   {"success": false, "error": "a message to show the user"}
//
// The HTTP status code says the same thing in a way any program understands:
//   200 OK            400 bad input          401 not logged in
//   403 not allowed   404 not found          405 wrong request method

/**
 * Send a JSON reply with the given status code and stop the script.
 */
function api_respond(array $body, int $status): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    // The two flags keep "/" and non-English letters readable instead of
    // writing them as \/ and \uXXXX codes. Both forms are valid JSON.
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_success(mixed $data = null): void
{
    api_respond(['success' => true, 'data' => $data], 200);
}

function api_error(string $message, int $status): void
{
    api_respond(['success' => false, 'error' => $message], $status);
}

/**
 * Refuse any request method other than the ones this endpoint expects.
 * Most endpoints expect one: api_require_method('POST'). One that both reads and
 * writes lists two: api_require_method('GET', 'POST').
 */
function api_require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        api_error('This endpoint only accepts ' . implode(' and ', $methods) . ' requests.', 405);
    }
}

/**
 * Like require_login(), but replies with JSON instead of redirecting to the login
 * page. A redirect would make no sense to JavaScript expecting JSON.
 */
function api_require_login(): array
{
    $user = current_user();

    if ($user === null) {
        api_error('Please log in first.', 401);
    }

    return $user;
}

/**
 * Like verify_csrf(), but replies with JSON. The token arrives as a form field.
 */
function api_verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';

    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        api_error('Your session has expired. Refresh the page and try again.', 403);
    }
}
