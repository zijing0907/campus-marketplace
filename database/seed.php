<?php
// Command-line tool for the demo data (see includes/demo-data.php).
//
//   php database/seed.php            load the demo accounts, listings and messages
//   php database/seed.php --remove   remove them again
//
// The database must already exist, with the tables from database/schema.sql.

// This file is a tool, not a page. If a browser ever reaches it, pretend it is
// not there.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/uploads.php';
require __DIR__ . '/../includes/listings.php';
require __DIR__ . '/../includes/demo-data.php';

// The same time zone as the website, so "posted 3 hours ago" means the same thing.
date_default_timezone_set(config()['timezone'] ?? 'Asia/Kuala_Lumpur');

try {
    if (in_array('--remove', $argv, true)) {
        $removed = remove_demo_data();
        echo "Removed {$removed['users']} demo accounts and {$removed['listings']} demo listings.\n";
    } else {
        $added = load_demo_data();
        echo "Loaded {$added['users']} demo accounts and {$added['listings']} demo listings.\n";
        echo 'Log in as ' . demo_email('student') . ". The password is DEMO_PASSWORD in includes/demo-data.php.\n";
    }
} catch (Throwable $ex) {
    // Exit code 1 tells whoever ran the command that it failed.
    fwrite(STDERR, 'Error: ' . $ex->getMessage() . "\n");
    exit(1);
}
