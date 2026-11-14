<?php
// The top half of the shared layout for the account pages: log in, register,
// forgot password and the email pages. A page includes this file, prints its own
// heading and form, then includes auth-end.php.
//
//   $bodyClass = 'auth-page';                      // before header.php: the coloured background
//   require __DIR__ . '/../includes/header.php';
//   require __DIR__ . '/../includes/auth-start.php';
//   ... the page's heading and form ...
//   require __DIR__ . '/../includes/auth-end.php';
//
// The card has two halves: who we are on the left (on a phone, at the top), and
// the form on the right.
$authDomain = config()['university_email_domain'];
?>
<div class="auth">
    <section class="auth-card">
        <div class="auth-brand">
            <svg class="auth-logo" viewBox="0 0 32 32" width="72" height="72" aria-hidden="true">
                <defs>
                    <linearGradient id="auth-logo-fill" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#2563eb"/>
                        <stop offset="1" stop-color="#6d28d9"/>
                    </linearGradient>
                </defs>
                <rect width="32" height="32" rx="9" fill="url(#auth-logo-fill)"/>
                <path d="M9 13h14l-1.1 10.3a2 2 0 0 1-2 1.7h-7.8a2 2 0 0 1-2-1.7z" fill="#ffffff"/>
                <path d="M12.5 13v-1.5a3.5 3.5 0 0 1 7 0V13" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
            </svg>

            <span class="auth-pill">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                    <path d="M2 9l10-5 10 5-10 5z M6 11v5c3 2 9 2 12 0v-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
                For @<?= e($authDomain) ?> students
            </span>

            <?php // Not a heading: each page's own <h1> is in the form half. ?>
            <p class="auth-name">Campus Marketplace</p>
            <p class="auth-lead">Buy and sell textbooks, electronics and hostel essentials with other students.</p>

            <ul class="auth-points">
                <li>
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z M9 12l2 2 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>
                    Only students with a university email
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-8l-5 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    Chat with sellers, live
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    See every seller's ratings first
                </li>
            </ul>
        </div>

        <div class="auth-form">
