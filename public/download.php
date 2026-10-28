<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

$user = require_access('complainant', 'org', 'admin');
$id = int_param('id');
$type = (string) ($_GET['t'] ?? '');
$row = null;

if ($type === 'attachment') {
    $stmt = db()->prepare('SELECT * FROM attachments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch() ?: null;
    // Same rule as viewing the complaint itself.
    if ($row && !complaint_for($user, (int) $row['complaint_id'])) {
        $row = null;
    }
} elseif ($type === 'orgdoc') {
    $stmt = db()->prepare('SELECT * FROM organization_documents WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch() ?: null;
    $allowed = $row && ($user['role'] === 'platform_admin' || (is_org_user($user) && (int) $user['org_id'] === (int) $row['org_id']));
    if (!$allowed) {
        $row = null;
    }
}

$path = $row ? upload_dir() . '/' . basename($row['file_path']) : '';
if (!$row || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$safeName = preg_replace('/[^\w.\- ]+/', '_', $row['original_name']);
header('Content-Type: ' . $row['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
readfile($path);
