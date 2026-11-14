<?php
// Register a new student account (FR-01, FR-03).
require __DIR__ . '/../includes/bootstrap.php';
require_guest();

$domain = config()['university_email_domain'];

// Values to show in the form. They stay empty until the form is submitted.
$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = post_text('name');
    $email = strtolower(post_text('email'));
    $password = post_text('password', trim: false);
    $passwordConfirm = post_text('password_confirm', trim: false);

    // Server-side validation. The browser checks the same rules, but browser
    // checks can be switched off, so these are the ones that count (NFR-06).
    $nameError = validate_name($name);
    if ($nameError !== null) {
        $errors['name'] = $nameError;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (!str_ends_with($email, '@' . $domain)) {
        $errors['email'] = 'Use your university email ending in @' . $domain . '.';
    }

    // The same password rules as on the "reset your password" page.
    $errors += validate_new_password($password, $passwordConfirm);

    if (!$errors) {
        try {
            // Only the hash is stored. password_hash() adds a random salt, so two
            // users with the same password still get different hashes.
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, $passwordHash]);
            $userId = (int) db()->lastInsertId();

            if (email_verification_required()) {
                // The account exists but stays inactive until the link in this
                // email is opened (FR-05). That proves the student can read the
                // inbox of the university address they typed.
                send_verification_email(['id' => $userId, 'name' => $name, 'email' => $email]);
                redirect('/pages/register.php?done=1');
            }

            // Confirming is switched off in config.php: the account works at once.
            login_user($userId, $passwordHash);
            set_flash('success', 'Welcome, ' . $name . '! Your account is ready.');
            redirect('/');
        } catch (PDOException $ex) {
            // Error 1062 means a duplicate value in a UNIQUE column: this email is
            // already registered. Letting the database enforce it is safer than
            // checking with a SELECT first, because two people could submit at once.
            if (($ex->errorInfo[1] ?? null) === 1062) {
                $errors['email'] = 'That email is already registered. Try logging in instead.';
            } else {
                throw $ex;
            }
        }
    }
}

// The same domain rule for the browser's own check. Dots are escaped because
// a dot means "any character" in a pattern.
$emailPattern = '.+@' . str_replace('.', '\.', $domain);

$pageTitle = 'Register';
$bodyClass = 'auth-page';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/auth-start.php';
?>

<?php if (get_text('done') === '1'): ?>
    <?php // Shown after a successful sign-up while confirming is switched on. ?>
    <div class="auth-message">
        <span class="auth-message-icon"><?= icon('mail', 28) ?></span>
        <h1>Check your email</h1>
        <p>Your account is almost ready. We sent a link to your university email.
           Open it within 24 hours to activate your account, then log in.</p>
        <p class="note">Nothing there? Look in your spam folder, or
           <a href="/pages/resend-verification.php">send the link again</a>.</p>
        <?= dev_mailbox_note() ?>
        <a class="button button-gradient" href="/pages/login.php"><?= icon('log-in') ?> Go to log in</a>
    </div>
<?php else: ?>
    <h1>Create your account</h1>
    <p class="note">
        For students with an @<?= e($domain) ?> email address.
        <?php if (email_verification_required()): ?>
            We will email you a link to confirm it.
        <?php endif; ?>
    </p>

    <form method="post" action="/pages/register.php">
        <?= csrf_field() ?>

        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>"
               required minlength="2" maxlength="100" autocomplete="name">
        <?= field_error($errors, 'name') ?>

        <label for="email">University email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>"
               required maxlength="255" autocomplete="email"
               placeholder="you@<?= e($domain) ?>"
               pattern="<?= e($emailPattern) ?>"
               title="Use your university email ending in @<?= e($domain) ?>">
        <?= field_error($errors, 'email') ?>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               required minlength="8" autocomplete="new-password" data-password-toggle>
        <p class="hint">At least 8 characters.</p>
        <?= field_error($errors, 'password') ?>

        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm"
               required minlength="8" autocomplete="new-password" data-password-toggle>
        <?= field_error($errors, 'password_confirm') ?>

        <button type="submit" class="button button-gradient" data-loading-text="Creating your account…"><?= icon('user-plus') ?> Create account</button>
    </form>

    <ul class="auth-links">
        <li><a href="/pages/login.php"><?= icon('log-in') ?> Already have an account? Log in</a></li>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/auth-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
