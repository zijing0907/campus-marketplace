<?php
// Stops the request with an "access denied" page and the HTTP status 403.
// 403 means: we know who you are, and you are not allowed to do this.
//
// Usage in a page, after bootstrap.php:
//   $forbiddenMessage = 'You can only edit your own listings.';   // optional
//   require __DIR__ . '/../includes/forbidden.php';
http_response_code(403);

$pageTitle = 'Access denied';
require __DIR__ . '/header.php';
?>

<section class="form-card">
    <h1>Access denied</h1>
    <p><?= e($forbiddenMessage ?? 'You are not allowed to open this page.') ?></p>
    <a class="button" href="/">Back to the home page</a>
</section>

<?php
require __DIR__ . '/footer.php';
exit;
