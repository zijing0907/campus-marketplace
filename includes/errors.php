<?php
// What a visitor sees when something goes wrong on the server.
//
// While you are developing, PHP's detailed error page is useful: it names the file,
// the line and the values involved. On a live site those same details would help an
// attacker (file paths, database settings). So there, every error is written to the
// server's log and the visitor sees a plain "Something went wrong" page instead.
//
// The 'debug' setting in config/config.php chooses between the two.

/**
 * True when full error details may be shown: on your own computer, never on a live site.
 */
function debug_mode(): bool
{
    try {
        // A missing setting counts as "off", which is the safe choice.
        return (bool) (config()['debug'] ?? false);
    } catch (Throwable $ex) {
        // The config file itself could not be loaded. PHP's built-in web server is
        // only ever used for development, so showing details there is safe.
        return PHP_SAPI === 'cli-server';
    }
}

/**
 * Called once per request, from bootstrap.php.
 */
function setup_error_handling(): void
{
    error_reporting(E_ALL);
    ini_set('log_errors', '1');        // always keep a record in the server's log

    if (debug_mode()) {
        ini_set('display_errors', '1');
        return;
    }

    ini_set('display_errors', '0');

    // Build the page in memory instead of sending it piece by piece. If it fails
    // halfway, the half-built page can then be thrown away and replaced.
    ob_start();

    // An exception nobody caught, for example the database being unreachable.
    set_exception_handler(function (Throwable $ex): void {
        error_log('Uncaught ' . get_class($ex) . ': ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
        show_server_error();
    });

    // Fatal errors, such as running out of memory, are not exceptions. They can
    // only be noticed afterwards, when the script is shutting down.
    register_shutdown_function(function (): void {
        $error = error_get_last();
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

        if ($error !== null && in_array($error['type'], $fatal, true)) {
            show_server_error();
        }
    });
}

/**
 * Replace whatever was being built with the "Something went wrong" reply (status 500).
 */
function show_server_error(): void
{
    // Throw away the part of the page that was already built.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    // JavaScript calling an API expects JSON; a person expects a page.
    if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'error' => 'Something went wrong on our side. Please try again later.']);
        return;
    }

    require __DIR__ . '/server-error.php';
}
