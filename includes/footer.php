<?php // Shared bottom of every page. ?>
</main>
<?php
// current_user() and config() rather than page variables: this file is sometimes
// included from inside a function, where the page's variables cannot be seen.
$footerUser = current_user();
$sourceCodeUrl = config()['source_code_url'] ?? '';
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <a class="brand" href="/">
                <svg class="brand-mark" viewBox="0 0 32 32" width="30" height="30" aria-hidden="true">
                    <rect width="32" height="32" rx="9" fill="url(#brand-mark-fill)"/>
                    <path d="M9 13h14l-1.1 10.3a2 2 0 0 1-2 1.7h-7.8a2 2 0 0 1-2-1.7z" fill="#ffffff"/>
                    <path d="M12.5 13v-1.5a3.5 3.5 0 0 1 7 0V13" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Campus Marketplace
            </a>
            <p>Buy and sell textbooks, electronics and hostel essentials with other students at your university.</p>
        </div>

        <nav class="footer-links" aria-label="More pages">
            <div>
                <h2>Marketplace</h2>
                <a href="/">Browse listings</a>
                <a href="/pages/listing-create.php">Sell an item</a>
            </div>
            <div>
                <h2>Account</h2>
                <?php if ($footerUser): ?>
                    <a href="/pages/profile.php">My account</a>
                    <a href="/pages/my-listings.php">My listings</a>
                    <?php // Like the header, only shown to students who can have saved listings. ?>
                    <a href="/pages/wishlist.php">Saved listings</a>
                    <a href="/pages/notifications.php">Notifications</a>
                <?php else: ?>
                    <a href="/pages/login.php">Log in</a>
                    <a href="/pages/register.php">Create an account</a>
                    <a href="/pages/forgot-password.php">Forgot your password?</a>
                <?php endif; ?>
            </div>
            <?php if ($sourceCodeUrl !== ''): ?>
                <div>
                    <h2>Project</h2>
                    <a href="<?= e($sourceCodeUrl) ?>" rel="noopener"><?= icon('code', 16) ?> Source code</a>
                    <span class="footer-note">PHP · MySQL · JavaScript</span>
                </div>
            <?php endif; ?>
        </nav>
    </div>
    <p class="footer-bottom">© <?= date('Y') ?> Campus Marketplace · a student portfolio project</p>
</footer>
<?php // Keeps the numbers on the bell and beside Inbox up to date on every page. ?>
<?php if ($footerUser): ?>
    <script src="/assets/js/badges.js"></script>
<?php endif; ?>
</body>
</html>
