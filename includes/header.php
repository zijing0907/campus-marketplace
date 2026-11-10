<?php
// Shared top of every page (NFR-10: no repeated header HTML).
// A page sets $pageTitle, then requires this file.
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Campus Marketplace') ?> · Campus Marketplace</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/">Campus Marketplace</a>
    <nav>
        <a href="/">Browse</a>
    </nav>
</header>
<main>
