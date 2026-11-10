<?php
// CSRF protection (NFR-03).
//
// The attack: while you are logged in here, a different website quietly submits a
// form to this site. Your browser attaches your session cookie automatically, so
// the request looks as if you sent it.
//
// The defence: every form on this site carries a secret random token that is also
// stored in the session. Another website cannot read it, so it cannot send it.
// A POST request without the right token is refused.

/**
 * Return this session's CSRF token, creating it on first use.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * The hidden input to put inside every <form method="post">: <?= csrf_field() ?>
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Call first in every POST handler. Stops the request if the token is missing or wrong.
 */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';

    // hash_equals() compares in constant time, so response timing reveals nothing
    // about how much of a guessed token was correct.
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('This form has expired. Go back, refresh the page and try again.');
    }
}
