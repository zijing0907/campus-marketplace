<?php
// The page a visitor sees when an error happens on a live site (see errors.php).
// It is deliberately self-contained: it uses no database, no session and none of
// the usual header, because any of those may be the very thing that failed.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong · Campus Marketplace</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/">Campus Marketplace</a>
</header>
<main>
    <section class="form-card">
        <h1>Something went wrong</h1>
        <p>The site hit a problem on our side. Please try again in a few minutes.</p>
        <a class="button" href="/">Back to the home page</a>
    </section>
</main>
</body>
</html>
