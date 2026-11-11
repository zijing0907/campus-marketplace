<?php
// The row of links at the top of every admin page.
// A page sets $adminPage to 'dashboard', 'reports', 'users' or 'categories' before
// including this file, so the link for the page being shown can be highlighted.
$adminLinks = [
    'dashboard'  => ['Dashboard', '/pages/admin/dashboard.php'],
    'reports'    => ['Reports', '/pages/admin/reports.php'],
    'users'      => ['Users', '/pages/admin/users.php'],
    'categories' => ['Categories', '/pages/admin/categories.php'],
];
?>
<nav class="admin-nav" aria-label="Admin pages">
    <?php foreach ($adminLinks as $key => [$label, $url]): ?>
        <a href="<?= e($url) ?>" <?= $key === ($adminPage ?? '') ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
