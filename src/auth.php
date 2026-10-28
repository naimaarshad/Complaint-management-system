<?php
declare(strict_types=1);

const LOGIN_MAX_FAILS_PER_EMAIL = 5;
const LOGIN_MAX_FAILS_PER_IP    = 20;
const LOGIN_WINDOW_SECONDS      = 900;

// Used so a wrong email takes as long to check as a wrong password.
const DUMMY_HASH = '$2y$10$PxYNGK0QWqPvx4HbfOxTyOiEVM0yd9P1Q.7x.yt6j2N3SvTrDbQiW';

function login_window_start(): string
{
    return gmdate('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);
}

function login_is_locked(string $email, string $ip): bool
{
    $pdo = db();
    $since = login_window_start();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND was_success = 0 AND attempted_at > ?');
    $stmt->execute([$email, $since]);
    if ((int) $stmt->fetchColumn() >= LOGIN_MAX_FAILS_PER_EMAIL) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND was_success = 0 AND attempted_at > ?');
    $stmt->execute([$ip, $since]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_FAILS_PER_IP;
}

function login_record(string $email, string $ip, bool $success): void
{
    db()->prepare('INSERT INTO login_attempts (email, ip_address, was_success, attempted_at) VALUES (?, ?, ?, ?)')
        ->execute([$email, $ip, $success ? 1 : 0, gmdate('Y-m-d H:i:s')]);
}

function login_clear_failures(string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE email = ? AND was_success = 0')->execute([$email]);
}

/** Where to send a user after a full login. */
function post_login_target(array $user): string
{
    $stmt = db()->prepare('SELECT must_change_password FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    if ((int) $stmt->fetchColumn() === 1) {
        return 'change-password.php';
    }

    if ($user['role'] === 'platform_admin') {
        return 'admin-approvals.php';
    }

    if ($user['role'] === 'complainant') {
        // A member who came in through an organization page goes straight to the complaint form.
        $wanted = (string) ($_SESSION['after_login'] ?? '');
        unset($_SESSION['after_login']);
        if (preg_match('/^file-complaint\.php$/', $wanted)) {
            return $wanted;
        }
        return 'my-complaints.php';
    }

    $stmt = db()->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$user['org_id']]);
    return $stmt->fetchColumn() === 'approved' ? 'org-dashboard.php' : 'org-pending.php';
}
