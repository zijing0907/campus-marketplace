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

    // The address of the site, without a slash at the end. Links inside emails
    // start with it. It is written here, not taken from the visitor's request,
    // so that nobody can make the site email a link to a different website.
    'app_url' => 'http://localhost:8000',

    // How emails are sent:
    //   'log'   Nothing is sent. Each email is saved in the database, and you can
    //           read it at /pages/dev-mailbox.php (while 'debug' below is true).
    //   'mail'  Really send, using PHP's mail() function. The host must support it.
    'mail_driver' => 'log',
    'mail_from' => 'no-reply@example.com',

    // true:  a new account works only after the student opens the link emailed
    //        to them (FR-05). This needs working email: a live site must use
    //        'mail_driver' => 'mail', or nobody could ever finish signing up.
    // false: a new account works at once. Use this if your host cannot send email.
    'require_email_verification' => true,

    // A link to this project's code, shown in the footer. Leave it empty ('') to hide it.
    'source_code_url' => 'https://github.com/zijing0907/campus-marketplace',

    // true:  show PHP's full error details. Use this on your own computer only.
    // false: log errors and show visitors a plain "Something went wrong" page.
    //        A live site must always use false.
    'debug' => true,
];
