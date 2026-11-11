<?php
// Admin: load or remove the demo data. The buttons on the dashboard post here.
// It does the same as "php database/seed.php", for hosts with no command line.
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/demo-data.php';
$admin = require_admin();

$back = '/pages/admin/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($back);
}
verify_csrf();

$action = post_text('action');

if ($action === 'load') {
    try {
        $added = load_demo_data();
        set_flash('success', 'Demo data loaded: ' . $added['users'] . ' accounts and ' . $added['listings'] . ' listings.');
    } catch (RuntimeException $ex) {
        // For example "Demo data is already loaded."
        set_flash('error', $ex->getMessage());
    }
} elseif ($action === 'remove') {
    $removed = remove_demo_data();
    set_flash('success', 'Demo data removed: ' . $removed['users'] . ' accounts and ' . $removed['listings'] . ' listings.');
} else {
    set_flash('error', 'Unknown action.');
}

redirect($back);
