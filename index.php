<?php
// Home page. Later this will show the newest listings (FR-13).
$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>Campus Marketplace</h1>
    <p>Buy and sell used textbooks, electronics and hostel essentials on campus.</p>
    <p class="note">Setup works. PHP <?= e(PHP_VERSION) ?> is serving this page.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
