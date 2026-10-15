<?php
declare(strict_types=1);

function send_mail(string $to, string $subject, string $body): bool
{
    $driver = env('MAIL_DRIVER', 'log');

    if ($driver === 'smtp') {
        $ok = smtp_send($to, $subject, $body);
        if ($ok) {
            return true;
        }
        // In dev, keep the flow usable even if the mail server is not reachable.
        if (env('APP_ENV') === 'dev') {
            mail_log_file($to, $subject, $body);
        }
        return false;
    }

    return mail_log_file($to, $subject, $body);
}

function mail_log_dir(): string
{
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function mail_log_file(string $to, string $subject, string $body): bool
{
    $entry = '[' . gmdate('Y-m-d H:i:s') . " UTC] TO: $to\nSUBJECT: $subject\n$body\n" . str_repeat('-', 40) . "\n";
    return file_put_contents(mail_log_dir() . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
}

function mail_error(string $message): void
{
    file_put_contents(
        mail_log_dir() . '/mail-error.log',
        '[' . gmdate('Y-m-d H:i:s') . " UTC] $message\n",
        FILE_APPEND | LOCK_EX
    );
}

function smtp_read($fp): array
{
    $text = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $text .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    if ($text === '') {
        throw new RuntimeException('No reply from the mail server (timeout)');
    }
    return [(int) substr($text, 0, 3), trim($text)];
}

function smtp_cmd($fp, ?string $command, array $expect, ?string $label = null): void
{
    if ($command !== null) {
        fwrite($fp, $command . "\r\n");
    }
    [$code, $text] = smtp_read($fp);
    if (!in_array($code, $expect, true)) {
        throw new RuntimeException("Mail server said $code to \"" . ($label ?? (string) $command) . "\": $text");
    }
}

function smtp_send(string $to, string $subject, string $body): bool
{
    $host  = env('MAIL_HOST');
    $port  = (int) env('MAIL_PORT', '587');
    $enc   = strtolower(env('MAIL_ENCRYPTION', 'tls'));
    $user  = env('MAIL_USERNAME');
    $pass  = env('MAIL_PASSWORD');
    $from  = env('MAIL_FROM', $user);
    $name  = env('MAIL_FROM_NAME', 'Complaint System');
    $verify = env('MAIL_VERIFY_PEER', '1') !== '0';

    if ($host === '' || $from === '') {
        mail_error('MAIL_HOST or MAIL_FROM is missing in .env');
        return false;
    }

    $to      = str_replace(["\r", "\n"], '', $to);
    $subject = str_replace(["\r", "\n"], ' ', $subject);

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'      => $verify,
        'verify_peer_name' => $verify,
        'peer_name'        => $host,
    ]]);

    $fp = @stream_socket_client(
        ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
        $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$fp) {
        mail_error("Cannot connect to $host:$port ($errno $errstr)");
        return false;
    }
    stream_set_timeout($fp, 15);

    try {
        smtp_cmd($fp, null, [220]);
        smtp_cmd($fp, 'EHLO localhost', [250]);

        if ($enc === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start encryption (certificate problem? see MAIL_VERIFY_PEER)');
            }
            smtp_cmd($fp, 'EHLO localhost', [250]);
        }

        if ($user !== '') {
            smtp_cmd($fp, 'AUTH LOGIN', [334], 'AUTH LOGIN');
            smtp_cmd($fp, base64_encode($user), [334], 'username');
            smtp_cmd($fp, base64_encode($pass), [235], 'password');
        }

        smtp_cmd($fp, "MAIL FROM:<$from>", [250]);
        smtp_cmd($fp, "RCPT TO:<$to>", [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);

        $headers = [
            'From: =?UTF-8?B?' . base64_encode($name) . "?= <$from>",
            "To: <$to>",
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($host ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n.\r\n");
        smtp_cmd($fp, null, [250]);

        smtp_cmd($fp, 'QUIT', [221]);
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        mail_error($e->getMessage());
        if (is_resource($fp)) {
            fclose($fp);
        }
        return false;
    }
}

function mask_email(string $email): string
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $keep = mb_substr($name, 0, min(2, mb_strlen($name)));
    return $keep . str_repeat('*', max(1, mb_strlen($name) - mb_strlen($keep))) . '@' . $domain;
}