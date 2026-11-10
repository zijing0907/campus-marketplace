<?php
// Small helper functions shared by every page.

/**
 * Escape text before printing it into HTML (NFR-02, prevents XSS).
 * Use it on anything that came from a user or the database: <?= e($title) ?>
 */
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
