<?php
declare(strict_types=1);

const OTP_TTL_SECONDS      = 600;
const OTP_MAX_ATTEMPTS     = 5;
const OTP_RESEND_COOLDOWN  = 60;

function otp_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function otp_hash(string $code, int $userId, string $purpose): string
{
    $key = env('APP_KEY');
    if ($key === '') {
        throw new RuntimeException('APP_KEY is missing in .env');
    }
    return hash_hmac('sha256', $userId . '|' . $purpose . '|' . $code, $key);
}

function otp_issue(int $userId, string $purpose): ?string
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT created_at FROM otp_codes WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $purpose]);
    $last = $stmt->fetchColumn();
    if ($last !== false && time() - strtotime($last . ' UTC') < OTP_RESEND_COOLDOWN) {
        return null;
    }

    $now = otp_now();
    $pdo->prepare('UPDATE otp_codes SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL')
        ->execute([$now, $userId, $purpose]);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare('INSERT INTO otp_codes (user_id, purpose, code_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([
            $userId,
            $purpose,
            otp_hash($code, $userId, $purpose),
            gmdate('Y-m-d H:i:s', time() + OTP_TTL_SECONDS),
            $now,
        ]);

    return $code;
}

function otp_verify(int $userId, string $purpose, string $code): string
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, code_hash, attempts, expires_at FROM otp_codes WHERE user_id = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $purpose]);
    $row = $stmt->fetch();

    if (!$row) {
        return 'none';
    }
    if ($row['expires_at'] < otp_now()) {
        return 'expired';
    }
    if ((int) $row['attempts'] >= OTP_MAX_ATTEMPTS) {
        return 'locked';
    }

    if (!hash_equals($row['code_hash'], otp_hash($code, $userId, $purpose))) {
        $pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        return 'invalid';
    }

    $pdo->prepare('UPDATE otp_codes SET used_at = ? WHERE id = ?')->execute([otp_now(), $row['id']]);
    return 'ok';
}