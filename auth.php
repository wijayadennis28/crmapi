<?php
require_once __DIR__ . '/config.local.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function auth_user(): ?string
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!auth_user()) {
        header('Location: login');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token. Reload the page and try again.');
    }
}

function attempt_login(string $user, string $password): bool
{
    if (hash_equals(APP_USER, $user) && password_verify($password, APP_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}
