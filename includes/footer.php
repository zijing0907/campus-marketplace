<?php // Shared bottom of every page. ?>
</main>
<footer class="site-footer">
    <p>Campus Marketplace · a student portfolio project</p>
</footer>
<?php // Keeps the numbers on the bell and beside Inbox up to date on every page.
      // current_user() rather than $currentUser: this file is sometimes included
      // from inside a function, where the page's variables cannot be seen. ?>
<?php if (current_user()): ?>
    <script src="/assets/js/badges.js"></script>
<?php endif; ?>
</body>
</html>
