<?php
declare(strict_types=1);

function env(string $key, string $default = ''): string
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = __DIR__ . '/../.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if ($line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v);
            }
        }
    }
    return $vars[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . env('DB_HOST', '127.0.0.1')
             . ';port=' . env('DB_PORT', '3306')
             . ';dbname=' . env('DB_NAME')
             . ';charset=utf8mb4';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ];

        // Online databases require an encrypted connection. Set DB_SSL_CA to the
        // certificate file (for example config/ca.pem). Leave it empty for local XAMPP.
        $ca = env('DB_SSL_CA');
        if ($ca !== '') {
            $path = realpath(__DIR__ . '/../' . $ca) ?: $ca;
            $options[PDO::MYSQL_ATTR_SSL_CA] = $path;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = env('DB_SSL_VERIFY', '1') !== '0';
        }

        $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS'), $options);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
