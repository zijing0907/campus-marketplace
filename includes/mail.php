<?php
// Sending email (FR-06).
//
// config.php decides how, with 'mail_driver':
//   'log'   Nothing leaves this computer. The email is saved in the "outbox" table
//           and can be read at /pages/dev-mailbox.php. This is for development,
//           where there is usually no mail server to send with.
//   'mail'  PHP's own mail() function hands the email to the server's mail
//           program. This is for a live site whose host supports it.
//
// The rest of the site only ever calls send_email() and does not care which one
// is in use. Switching is one line in config.php.

/**
 * Send a plain-text email. Returns true if it was sent (or saved, with 'log').
 */
function send_email(string $to, string $subject, string $body): bool
{
    // An address or subject containing a line break could smuggle extra headers
    // into the email, such as a hidden "Bcc:". Neither should ever have one.
    if (preg_match('/[\r\n]/', $to . $subject)) {
        return false;
    }

    if ((config()['mail_driver'] ?? 'log') === 'mail') {
        $headers = [
            'From' => config()['mail_from'] ?? 'no-reply@example.com',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ];

        // mb_encode_mimeheader() makes non-English letters in the subject safe to send.
        return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers);
    }

    $stmt = db()->prepare('INSERT INTO outbox (to_email, subject, body) VALUES (?, ?, ?)');
    $stmt->execute([$to, $subject, $body]);

    return true;
}

/**
 * Is the development mailbox page open?
 *
 * Only when emails are being saved instead of sent AND the site is in debug mode.
 * Both must hold: the mailbox shows password reset links, so on a live site
 * (where 'debug' is always false) it must never be reachable, even if someone
 * forgot to change 'mail_driver'.
 */
function dev_mailbox_is_open(): bool
{
    return (config()['mail_driver'] ?? 'log') === 'log' && !empty(config()['debug']);
}

/**
 * The full address of a page on this site, for use inside an email.
 * site_url('/pages/login.php') gives "http://localhost:8000/pages/login.php".
 *
 * The first part comes from config.php and never from the visitor's request.
 * Otherwise a request with a forged "Host" header could make the site email
 * someone a link that leads to an attacker's website.
 */
function site_url(string $path): string
{
    return rtrim(config()['app_url'] ?? 'http://localhost:8000', '/') . $path;
}
