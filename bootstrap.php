<?php
// Shared runtime bootstrap: environment helpers and a last-resort error boundary.
// Loaded after config.local.php so APP_ENV and DATA_DIR are available.
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

function app_is_production(): bool
{
    // Missing APP_ENV is treated as production so live errors never leak details.
    return !defined('APP_ENV') || APP_ENV !== 'local';
}

function app_log_error(Throwable $error): void
{
    $dir = defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $request = ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? '');
    $line = '[' . date('c') . '] ' . $request . PHP_EOL
        . get_class($error) . ': ' . $error->getMessage()
        . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL
        . $error->getTraceAsString() . PHP_EOL . PHP_EOL;

    if (@file_put_contents($dir . '/error.log', $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('[crm] ' . $error->getMessage());
    }
}

function app_render_error(Throwable $error): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    if (!app_is_production()) {
        echo '<pre style="white-space:pre-wrap;font:13px/1.5 ui-monospace,monospace;padding:24px;">'
            . htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }

    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Service unavailable</title>'
        . '<body style="margin:0;background:#fbfbf8;color:#141414;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh">'
        . '<main style="max-width:34rem;padding:32px">'
        . '<div style="font:700 10px/1.2 ui-monospace,monospace;letter-spacing:.14em;text-transform:uppercase;color:#c62828">Error 500</div>'
        . '<h1 style="font-size:28px;line-height:1.1;margin:10px 0 8px">Service unavailable</h1>'
        . '<p style="font-size:14px;line-height:1.5;color:#62665f;margin:0 0 16px">The request could not be completed. The error has been logged.</p>'
        . '<a href="./" style="color:#141414">Return to dashboard</a>'
        . '</main></body></html>';
}

function app_handle_exception(Throwable $error): void
{
    $GLOBALS['app_error_rendered'] = true;
    app_log_error($error);
    app_render_error($error);
}

function app_handle_shutdown(): void
{
    if (!empty($GLOBALS['app_error_rendered'])) {
        return;
    }

    $last = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!$last || !in_array($last['type'], $fatal, true)) {
        return;
    }

    $error = new ErrorException($last['message'], 0, $last['type'], $last['file'] ?? '', $last['line'] ?? 0);
    $GLOBALS['app_error_rendered'] = true;
    app_log_error($error);
    app_render_error($error);
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler('app_handle_exception');
    register_shutdown_function('app_handle_shutdown');
}
