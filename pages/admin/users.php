<?php
// Admin: all accounts, with search, and the actions Ban and Unban (FR-24).
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();

const USERS_PER_PAGE = 20;

// Optional search on name or email, from ?q= in the address.
$q = mb_substr(get_text('q'), 0, 100);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

// As on the browse page: fixed SQL text, with the user's words only in "?" values,
// and % and _ escaped so they are searched for as ordinary characters.
$whereSql = '1 = 1';
$params = [];
if ($q !== '') {
    $pattern = '%' . addcslashes($q, '%_\\') . '%';
    $whereSql = '(u.name LIKE ? OR u.email LIKE ?)';
    $params = [$pattern, $pattern];
}

$stmt = db()->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pages = max(1, (int) ceil($total / USERS_PER_PAGE));
$page = min($page, $pages);

$stmt = db()->prepare(
    "SELECT u.id, u.name, u.email, u.role, u.is_verified, u.is_banned, u.deleted_at, u.created_at,
            (SELECT COUNT(*) FROM listings l WHERE l.seller_id = u.id) AS listing_count
     FROM users u
     WHERE $whereSql
     ORDER BY u.created_at DESC, u.id DESC
     LIMIT ? OFFSET ?"
);
foreach ($params as $i => $value) {
    $stmt->bindValue($i + 1, $value);
}
$stmt->bindValue(count($params) + 1, USERS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, ($page - 1) * USERS_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// The address of another page of the same search.
$pageUrl = function (int $number) use ($q): string {
    $query = array_filter(['q' => $q, 'page' => $number > 1 ? $number : null], fn ($value) => $value !== null && $value !== '');
    return '/pages/admin/users.php' . ($query ? '?' . http_build_query($query) : '');
};

$pageTitle = 'Users';
$adminPage = 'users';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/admin-nav.php';
?>

<div class="page-head">
    <h1>Users</h1>
</div>

<form class="search-form" method="get" action="/pages/admin/users.php" role="search">
    <div class="search-main">
        <label for="q" class="visually-hidden">Search users</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Search by name or email">
        <button type="submit" class="button">Search</button>
    </div>
</form>

<p class="results-summary">
    <?= $total ?> <?= $total === 1 ? 'user' : 'users' ?><?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?><?= $pages > 1 ? ' · page ' . $page . ' of ' . $pages : '' ?>
</p>

<?php if ($users): ?>
    <ul class="admin-list">
        <?php foreach ($users as $user): ?>
            <?php
            $isMe = (int) $user['id'] === (int) $admin['id'];
            $isAdmin = $user['role'] === 'admin';
            ?>
            <li class="admin-row">
                <div class="admin-row-main">
                    <span class="admin-row-title">
                        <?= e($user['name']) ?>
                        <?php if ($isAdmin): ?>
                            <span class="badge badge-available">Admin</span>
                        <?php endif; ?>
                        <?php if ($user['deleted_at'] !== null): ?>
                            <?php // A deleted account is also marked banned, but saying so would mislead. ?>
                            <span class="badge badge-sold">Deleted</span>
                        <?php elseif ($user['is_banned']): ?>
                            <span class="badge badge-hidden">Banned</span>
                        <?php endif; ?>
                        <?php if ($user['deleted_at'] === null && email_verification_required() && !$user['is_verified']): ?>
                            <span class="badge badge-pending">Email not confirmed</span>
                        <?php endif; ?>
                    </span>
                    <span class="note">
                        <?= e($user['email']) ?> · joined <?= e(date('j M Y', strtotime($user['created_at']))) ?>
                        · <?= (int) $user['listing_count'] ?> <?= (int) $user['listing_count'] === 1 ? 'listing' : 'listings' ?>
                    </span>
                </div>

                <div class="admin-row-actions">
                    <?php if ($isMe): ?>
                        <span class="note">This is you</span>
                    <?php elseif ($user['deleted_at'] !== null): ?>
                        <span class="note">Deleted <?= e(date('j M Y', strtotime($user['deleted_at']))) ?></span>
                    <?php elseif ($isAdmin): ?>
                        <?php // Admins cannot ban each other here, so no admin can lock the others out. ?>
                        <span class="note">Admins cannot be banned</span>
                    <?php elseif ($user['is_banned']): ?>
                        <form method="post" action="/pages/admin/user-action.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                            <input type="hidden" name="action" value="unban">
                            <input type="hidden" name="q" value="<?= e($q) ?>">
                            <button type="submit" class="button button-secondary button-small">Unban</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/pages/admin/user-action.php"
                              data-confirm="Ban this user? They will be logged out, cannot log in again, and their listings will be hidden from other students.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                            <input type="hidden" name="action" value="ban">
                            <input type="hidden" name="q" value="<?= e($q) ?>">
                            <button type="submit" class="button button-danger button-small">Ban</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="pagination">
    <?php if ($page > 1): ?>
        <a class="button button-secondary button-small" rel="prev" href="<?= e($pageUrl($page - 1)) ?>">Previous</a>
    <?php endif; ?>
    <?php if ($page < $pages): ?>
        <a class="button button-secondary button-small" rel="next" href="<?= e($pageUrl($page + 1)) ?>">Next</a>
    <?php endif; ?>
</div>

<script src="/assets/js/confirm.js"></script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
