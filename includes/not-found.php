<?php
// Stops the request with a "not found" page and the HTTP status 404.
//
// Usage in a page, after bootstrap.php:
//   $notFoundMessage = 'This listing does not exist.';   // optional
//   require __DIR__ . '/../includes/not-found.php';
http_response_code(404);

$pageTitle = 'Not found';
require __DIR__ . '/header.php';
?>

<section class="form-card">
    <h1>Not found</h1>
    <p><?= e($notFoundMessage ?? 'This page does not exist.') ?></p>
    <a class="button" href="/">Back to the home page</a>
</section>

<?php
require __DIR__ . '/footer.php';
exit;
