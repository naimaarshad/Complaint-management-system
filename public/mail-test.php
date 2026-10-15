<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';

$to = 'laaraib98@gmail.com';

echo 'MAIL_DRIVER = ' . e(env('MAIL_DRIVER')) . '<br>';
echo 'MAIL_HOST = ' . e(env('MAIL_HOST')) . '<br>';
echo 'MAIL_PORT = ' . e(env('MAIL_PORT')) . '<br>';
echo 'MAIL_USERNAME = ' . e(env('MAIL_USERNAME')) . '<br>';
echo 'MAIL_PASSWORD length = ' . strlen(env('MAIL_PASSWORD')) . ' (should be 16)<br>';
echo 'function smtp_send exists = ' . (function_exists('smtp_send') ? 'yes' : 'NO, old mailer.php') . '<br>';
echo 'openssl loaded = ' . (extension_loaded('openssl') ? 'yes' : 'NO') . '<br><br>';

$ok = send_mail($to, 'Mail test', 'If you read this, email works.');
echo $ok ? '<strong>SENT. Check the inbox and spam.</strong>' : '<strong>FAILED. Read the last lines of storage/logs/mail-error.log</strong>';
echo '<br><br><strong>Last error log lines:</strong><pre>';
$log = __DIR__ . '/../storage/logs/mail-error.log';
if (is_file($log)) {
    echo e(implode("\n", array_slice(file($log, FILE_IGNORE_NEW_LINES), -3)));
} else {
    echo 'No mail-error.log file yet';
}
echo '</pre>';