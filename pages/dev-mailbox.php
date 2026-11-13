<?php
// The development mailbox: the emails the site "sent" while 'mail_driver' is 'log'.
// A developer's computer usually has no mail server, so the emails are saved in
// the outbox table instead, and this page shows them.
//
// It exists only on a developer's own computer. On a live site it answers
// "not found": see dev_mailbox_is_open() in includes/mail.php for the rule.
require __DIR__ . '/../includes/bootstrap.php';

if (!dev_mailbox_is_open()) {
    require __DIR__ . '/../includes/not-found.php';
}

// The newest 50 are plenty for a developer.
$emails = db()->query('SELECT id, to_email, subject, body, created_at FROM outbox ORDER BY id DESC LIMIT 50')->fetchAll();

$pageTitle = 'Local mailbox';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Local mailbox</h1>
</div>

<p class="alert alert-info">
    Development only. These emails were saved instead of sent, because config.php has
    <code>'mail_driver' =&gt; 'log'</code>. This page does not exist on a live site.
</p>

<?php if (!$emails): ?>
    <p class="empty">No emails yet. Ask for a password reset link and it will appear here.</p>
<?php else: ?>
    <ul class="mailbox">
        <?php foreach ($emails as $email): ?>
            <li class="mail">
                <p class="mail-subject"><?= e($email['subject']) ?></p>
                <p class="note">To <?= e($email['to_email']) ?> · <?= e(date('j M Y, g:i A', strtotime($email['created_at']))) ?></p>
                <?php
                // The text is escaped first, like all text on this site. Only then
                // are the web addresses in it turned into links, so the only HTML
                // in the result is the <a> tags added on this line.
                $bodyHtml = preg_replace('~https?://[^\s<]+~', '<a href="$0">$0</a>', e($email['body']));
                ?>
                <pre class="mail-body"><?= $bodyHtml ?></pre>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
