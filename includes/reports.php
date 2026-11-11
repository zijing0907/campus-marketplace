<?php
// Helpers for reporting listings and for admin moderation (FR-22, FR-23).

// The values stored in reports.reason, and the label shown for each.
const REPORT_REASONS = [
    'scam'       => 'Scam or fraud',
    'prohibited' => 'Prohibited item',
    'spam'       => 'Spam',
    'other'      => 'Something else',
];

// The longest "details" text accepted with a report, in characters.
const MAX_REPORT_DETAILS = 500;

/**
 * How many reports are waiting for an admin. Shown beside "Admin" in the header.
 */
function open_report_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();
}
