<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';

$user = require_login();

$roleMap = ['complainant' => 'user', 'platform_admin' => 'admin'];
$demoSession = [
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $roleMap[$user['role']] ?? 'org',
];
if ($demoSession['role'] === 'org') {
    $demoSession['orgId'] = null;
    $demoSession['requestId'] = null;
}

$target = post_login_target($user);
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logging in</title>
</head>
<body>
  <script>
    try { localStorage.setItem("currentUser", JSON.stringify(<?= json_encode($demoSession, $flags) ?>)); } catch (e) {}
    window.location.replace(<?= json_encode($target, $flags) ?>);
  </script>
  <noscript><a href="<?= e($target) ?>">Continue</a></noscript>
</body>
</html>