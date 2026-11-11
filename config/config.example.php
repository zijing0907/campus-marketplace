<?php
// Copy this file to config.php and fill in your own values.
// config.php is listed in .gitignore, so real passwords never reach GitHub (NFR-05).
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'campus_marketplace',
    'db_user' => 'root',
    'db_pass' => '',
    'university_email_domain' => '1utar.my',
    'timezone' => 'Asia/Kuala_Lumpur',

    // true:  show PHP's full error details. Use this on your own computer only.
    // false: log errors and show visitors a plain "Something went wrong" page.
    //        A live site must always use false.
    'debug' => true,
];
