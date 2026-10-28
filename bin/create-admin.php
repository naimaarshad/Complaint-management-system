<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line only.');
}

require __DIR__ . '/../config/db.php';

$args    = array_slice($argv, 1);
$promote = in_array('--promote', $args, true);
$args    = array_values(array_filter($args, fn ($a) => $a !== '--promote'));

$email = strtolower(trim($args[0] ?? ''));
$name  = trim($args[1] ?? 'Platform Admin');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Usage:\n";
    echo "  php bin/create-admin.php you@gmail.com \"Your Name\"\n";
    echo "  php bin/create-admin.php you@gmail.com --promote   (turn an existing account into an admin)\n";
    exit(1);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();
$now = gmdate('Y-m-d H:i:s');

if ($existing) {
    if (!$promote) {
        echo "An account with $email already exists (role: {$existing['role']}).\n";
        echo "To make it an admin and keep its password, run again with --promote.\n";
        exit(1);
    }
    $pdo->prepare("UPDATE users SET role = 'platform_admin', org_id = NULL, is_active = 1, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?")
        ->execute([$now, $existing['id']]);
    echo "Done. $email is now a platform admin. Log in with your existing password.\n";
    exit(0);
}

if ($promote) {
    echo "No account with $email exists yet. Run without --promote to create one.\n";
    exit(1);
}

$alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$password = '';
for ($i = 0; $i < 14; $i++) {
    $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}

$pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active, email_verified_at) VALUES (?, ?, ?, 'platform_admin', 1, ?)")
    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $now]);

echo "Platform admin created.\n";
echo "  Email:    $email\n";
echo "  Password: $password\n";
echo "Save this password now. It is shown only once.\n";
echo "Logging in also needs a code sent to $email, so use an inbox you can read.\n";
