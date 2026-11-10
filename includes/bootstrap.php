<?php
// The first line of every page and API file is:
//   require __DIR__ . '/../includes/bootstrap.php';
// It loads the shared code and starts the session, so pages need only this one line.

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/uploads.php';

start_secure_session();

// Look up the logged-in user now, before any HTML is sent. Ending a banned user's
// session changes the session cookie, and cookies can only be set before output starts.
// Pages and the header read this variable; it is null for a guest.
$currentUser = current_user();
