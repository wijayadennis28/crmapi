<?php
/**
 * Local development router for `php -S`.
 * Production uses .htaccess; this file is blocked from the web there.
 *
 * Run: php -S 127.0.0.1:8000 router.php
 */

$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$route = trim($path, '/');
$root  = __DIR__;

$privateFiles = [
    'config.php',
    'config.local.php',
    'config.local.example.php',
    'EspoClient.php',
    'store.php',
    'auth.php',
    'ui.php',
    'router.php',
    'sources.php',
    'test.php',
    'HOW_TO_CUSTOMIZE.php',
];
$privatePrefixes = ['cache/', 'data/', 'docs/', 'tests/', '.git/'];

$isPrivate = in_array(basename($path), $privateFiles, true);
foreach ($privatePrefixes as $prefix) {
    if (str_starts_with($route, $prefix) || $route === rtrim($prefix, '/')) {
        $isPrivate = true;
        break;
    }
}
if (preg_match('/\.(md|sqlite|sqlite3|log|env)$/i', $path)) {
    $isPrivate = true;
}

if ($isPrivate) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

// Direct .php -> clean URL, preserving query string.
if (str_ends_with($path, '.php')) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $clean = substr($path, 0, -4);
    header('Location: ' . $clean . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

// App routes without trailing slash.
$routes = ['index', 'input', 'mapping', 'report', 'fields', 'login', 'export'];
if ($path !== '/' && str_ends_with($path, '/')) {
    $clean = rtrim($path, '/');
    if (in_array(trim($clean, '/'), $routes, true)) {
        header('Location: ' . $clean, true, 301);
        exit;
    }
}

if ($route === '') {
    require $root . '/index.php';
    return true;
}

if (in_array($route, $routes, true)) {
    require $root . '/' . $route . '.php';
    return true;
}

$file = $root . $path;
if (is_file($file)) {
    return false;
}

http_response_code(404);
echo 'Not found';
return true;
