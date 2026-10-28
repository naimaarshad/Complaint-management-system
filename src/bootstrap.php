<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';

date_default_timezone_set('UTC');

if (env('APP_ENV') === 'dev') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name('cms_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 1800) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_seen'] = time();
}

start_secure_session();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || $expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(403);
        exit('Invalid or expired form. Go back, refresh the page and try again.');
    }
}

function flash(string $type, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$type] = $message;
        return null;
    }
    $text = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $text;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'     => (int) $user['id'],
        'name'   => $user['name'],
        'email'  => $user['email'],
        'role'   => $user['role'],
        'org_id' => $user['org_id'] !== null ? (int) $user['org_id'] : null,
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have access to this page.');
    }
    return $user;
}

function audit(string $action, string $entity, ?int $entityId = null): void
{
    $user = current_user();
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, entity, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user['id'] ?? null, $action, $entity, $entityId, client_ip()]);
}