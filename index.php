<?php
// Home page. Later this will show the newest listings (FR-13).
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>Campus Marketplace</h1>
    <p>Buy and sell used textbooks, electronics and hostel essentials on campus.</p>

    <?php if (!$currentUser): ?>
        <p class="hero-actions">
            <a class="button" href="/pages/register.php">Create an account</a>
            <a class="button button-secondary" href="/pages/login.php">Log in</a>
        </p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
