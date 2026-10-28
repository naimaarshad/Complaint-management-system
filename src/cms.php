<?php
declare(strict_types=1);

/* ---------- constants ---------- */

const STATUS_LABELS = [
    'submitted'   => 'Submitted',
    'assigned'    => 'Assigned',
    'in_progress' => 'In Progress',
    'resolved'    => 'Resolved',
    'closed'      => 'Closed',
];
const STATUS_CLASSES = [
    'submitted' => 't-submitted', 'assigned' => 't-assigned', 'in_progress' => 't-progress',
    'resolved' => 't-resolved', 'closed' => 't-closed',
];
const PRIORITY_LABELS = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
const PRIORITY_CLASSES = ['low' => 't-low', 'medium' => 't-medium', 'high' => 't-high', 'urgent' => 't-urgent'];
const DEFAULT_CATEGORIES = ['Service quality', 'Billing', 'Staff behavior', 'Facilities', 'Other'];
const SECTORS = ['Healthcare', 'Education', 'Government', 'Banking and finance', 'Telecom and utilities', 'Retail and services', 'Other'];
const ORG_ROLES = ['org_admin', 'supervisor', 'staff'];
const MAX_CATEGORIES = 12;

/* ---------- small helpers ---------- */

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function status_badge(string $status): string
{
    return '<span class="badge ' . e(STATUS_CLASSES[$status] ?? 't-submitted') . '">' . e(STATUS_LABELS[$status] ?? $status) . '</span>';
}

function priority_badge(string $priority): string
{
    return '<span class="badge ' . e(PRIORITY_CLASSES[$priority] ?? 't-medium') . '">' . e(PRIORITY_LABELS[$priority] ?? $priority) . '</span>';
}

/** Times are stored in UTC. The <time> tag is rewritten to the viewer's local time by a small script in the layout. */
function fmt_time(?string $utc, bool $withTime = false): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    $ts = strtotime($utc . ' UTC');
    $iso = gmdate('Y-m-d\TH:i:s\Z', $ts);
    $text = $withTime ? gmdate('d M Y, H:i', $ts) . ' UTC' : gmdate('d M Y', $ts);
    return '<time class="lt" data-t="' . ($withTime ? 'dt' : 'd') . '" datetime="' . $iso . '">' . e($text) . '</time>';
}

function post_str(string $key, int $max = 5000): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function int_param(string $key): int
{
    $v = $_GET[$key] ?? $_POST[$key] ?? 0;
    return is_string($v) && ctype_digit($v) ? (int) $v : 0;
}

/* ---------- access control ---------- */

function home_for(array $user): string
{
    if ($user['role'] === 'platform_admin') {
        return 'admin-approvals.php';
    }
    if ($user['role'] === 'complainant') {
        return 'my-complaints.php';
    }
    return org_is_approved($user) ? 'org-dashboard.php' : 'org-pending.php';
}

function org_row(?int $orgId): ?array
{
    if (!$orgId) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM organizations WHERE id = ?');
    $stmt->execute([$orgId]);
    return $stmt->fetch() ?: null;
}

function org_is_approved(array $user): bool
{
    $org = org_row($user['org_id'] ?? null);
    return $org !== null && $org['status'] === 'approved';
}

function is_org_user(array $user): bool
{
    return in_array($user['role'], ORG_ROLES, true);
}

/**
 * Allow only the given kinds: 'complainant', 'admin', 'org' (any organization role),
 * or an exact role such as 'org_admin', 'supervisor', 'staff'.
 * The account is re-read from the database on every request, so a deactivated user or a changed role
 * takes effect immediately. Someone who must change a temporary password is sent to do that first.
 */
function require_access(string ...$kinds): array
{
    $session = require_login();

    $stmt = db()->prepare('SELECT id, org_id, name, email, role, is_active, must_change_password FROM users WHERE id = ?');
    $stmt->execute([$session['id']]);
    $row = $stmt->fetch();
    if (!$row || !(int) $row['is_active']) {
        logout_user();
        redirect('login.php');
    }
    $user = [
        'id'     => (int) $row['id'],
        'org_id' => $row['org_id'] !== null ? (int) $row['org_id'] : null,
        'name'   => $row['name'],
        'email'  => $row['email'],
        'role'   => $row['role'],
        'must_change_password' => (int) $row['must_change_password'],
    ];

    if ($user['must_change_password'] && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'change-password.php') {
        redirect('change-password.php');
    }

    $ok = in_array($user['role'], $kinds, true)
        || (in_array('admin', $kinds, true) && $user['role'] === 'platform_admin')
        || (in_array('org', $kinds, true) && is_org_user($user));
    if (!$ok) {
        redirect(home_for($user));
    }
    return $user;
}

/** Organization user whose organization is approved. Returns [user, org]. */
function require_active_org(string ...$kinds): array
{
    $user = require_access(...($kinds ?: ['org']));
    $org = org_row($user['org_id']);
    if ($org === null || $org['status'] !== 'approved') {
        redirect('org-pending.php');
    }
    return [$user, $org];
}

/** Load a complaint with joined names, only if this user may see it. */
function complaint_for(array $user, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT c.*, o.name AS org_name, cat.name AS category_name, u.name AS user_name, u.email AS user_email,
                f.rating, f.comment AS feedback_comment,
                a.id AS asg_id, a.assigned_to, h.name AS handler_name
         FROM complaints c
         JOIN organizations o ON o.id = c.org_id
         LEFT JOIN categories cat ON cat.id = c.category_id
         LEFT JOIN users u ON u.id = c.user_id
         LEFT JOIN feedback f ON f.complaint_id = c.id
         LEFT JOIN assignments a ON a.id = (SELECT MAX(id) FROM assignments WHERE complaint_id = c.id AND status IN (\'active\', \'completed\'))
         LEFT JOIN users h ON h.id = a.assigned_to
         WHERE c.id = ?'
    );
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) {
        return null;
    }
    if ($user['role'] === 'complainant') {
        return (int) $c['user_id'] === $user['id'] ? $c : null;
    }
    if (is_org_user($user) && (int) $c['org_id'] === (int) $user['org_id']) {
        if ($user['role'] === 'staff') {
            return (int) ($c['assigned_to'] ?? 0) === $user['id'] ? $c : null;
        }
        return $c;
    }
    return null;
}

/** Active handlers (staff) of an organization. */
function org_handlers(int $orgId): array
{
    $stmt = db()->prepare("SELECT id, name, email FROM users WHERE org_id = ? AND role = 'staff' AND is_active = 1 ORDER BY name");
    $stmt->execute([$orgId]);
    return $stmt->fetchAll();
}

function log_status(int $complaintId, ?string $old, string $new, ?int $by): void
{
    db()->prepare('INSERT INTO status_history (complaint_id, old_status, new_status, changed_by, changed_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$complaintId, $old, $new, $by, now_utc()]);
}

/* ---------- organization readiness and membership ---------- */

/** An organization can take complaints once it has at least one active focal person and one active handler. */
function org_readiness(int $orgId): array
{
    $stmt = db()->prepare("SELECT role, COUNT(*) FROM users WHERE org_id = ? AND is_active = 1 AND role IN ('supervisor','staff') GROUP BY role");
    $stmt->execute([$orgId]);
    $n = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE org_id = ? AND role = 'complainant'");
    $stmt->execute([$orgId]);
    return [
        'focal'   => (int) ($n['supervisor'] ?? 0) > 0,
        'handler' => (int) ($n['staff'] ?? 0) > 0,
        'members' => (int) $stmt->fetchColumn() > 0 || trim((string) (org_row($orgId)['email_domain'] ?? '')) !== '',
        'ready'   => (int) ($n['supervisor'] ?? 0) > 0 && (int) ($n['staff'] ?? 0) > 0,
    ];
}

function valid_domain(string $d): bool
{
    return (bool) preg_match('/^(?=.{3,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $d);
}

/** Take back every open complaint from a handler (disabled or changed role) so a focal person can reassign it. */
function unassign_cases(int $userId, int $orgId, int $byUserId): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT a.id AS asg_id, c.id, c.status, c.tracking_no FROM assignments a JOIN complaints c ON c.id = a.complaint_id
         WHERE a.assigned_to = ? AND a.status = 'active' AND c.org_id = ? AND c.status IN ('assigned','in_progress')"
    );
    $stmt->execute([$userId, $orgId]);
    $rows = $stmt->fetchAll();
    $sup = $pdo->prepare("SELECT id FROM users WHERE org_id = ? AND role = 'supervisor' AND is_active = 1");
    $sup->execute([$orgId]);
    $supervisors = $sup->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $r) {
        $pdo->prepare("UPDATE assignments SET status = 'reassigned' WHERE id = ?")->execute([$r['asg_id']]);
        $pdo->prepare("UPDATE complaints SET status = 'submitted', updated_at = ? WHERE id = ?")->execute([now_utc(), $r['id']]);
        log_status((int) $r['id'], $r['status'], 'submitted', $byUserId);
        foreach ($supervisors as $sid) {
            notify((int) $sid, (int) $r['id'], 'unassigned', "{$r['tracking_no']} needs a new handler.");
        }
    }
    return count($rows);
}

/* ---------- categories ---------- */

function seed_categories(int $orgId): void
{
    $stmt = db()->prepare('INSERT INTO categories (org_id, name) VALUES (?, ?)');
    foreach (DEFAULT_CATEGORIES as $name) {
        $stmt->execute([$orgId, $name]);
    }
}

function active_categories(int $orgId): array
{
    $stmt = db()->prepare('SELECT id, name FROM categories WHERE org_id = ? AND is_active = 1 ORDER BY id');
    $stmt->execute([$orgId]);
    return $stmt->fetchAll();
}

/* ---------- notifications ---------- */

function notify(int $userId, ?int $complaintId, string $type, string $message): void
{
    db()->prepare('INSERT INTO notifications (user_id, complaint_id, type, message, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $complaintId, $type, mb_substr($message, 0, 255), now_utc()]);
}

/* ---------- uploads ---------- */

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const UPLOAD_TYPES = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
];

function upload_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Validate and store one uploaded file. Returns [meta, error]. meta is null when no file was sent.
 * The stored name is random; the original name is only kept for display.
 */
function save_upload(string $field, bool $required): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, $required ? 'Attach a file (PDF, PNG or JPG).' : ''];
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'The file is too large. The limit is 5 MB.'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return [null, 'The upload failed. Try again.'];
    }
    if ($f['size'] > UPLOAD_MAX_BYTES || $f['size'] <= 0) {
        return [null, 'The file must be between 1 byte and 5 MB.'];
    }

    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!isset(UPLOAD_TYPES[$ext])) {
        return [null, 'Only PDF, PNG and JPG files are allowed.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if ($mime !== UPLOAD_TYPES[$ext]) {
        return [null, 'The file contents do not match its type.'];
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], upload_dir() . '/' . $stored)) {
        return [null, 'Could not save the file. Try again.'];
    }

    $orig = preg_replace('/[^\w.\- ]+/u', '_', basename((string) $f['name']));
    return [[
        'file_path'     => $stored,
        'original_name' => mb_substr($orig, 0, 200),
        'mime_type'     => $mime,
        'size_bytes'    => (int) $f['size'],
    ], ''];
}

function delete_upload(string $stored): void
{
    $path = upload_dir() . '/' . basename($stored);
    if (is_file($path)) {
        unlink($path);
    }
}

/* ---------- layout ---------- */

function page_head(string $title): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | Complaint System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="app.css">
  <style>
    .home-btn{position:absolute;top:18px;left:24px;z-index:50;display:inline-flex;align-items:center;gap:8px;padding:9px 18px 9px 14px;border:2px solid rgba(255,255,255,.35);border-radius:8px;background:#106d5a;color:#fff;font-family:inherit;font-size:14px;font-weight:700;line-height:1;text-decoration:none}
    .home-btn::before{content:"";width:16px;height:16px;background:currentColor;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 3 2 12h3v8h5v-5h4v5h5v-8h3L12 3z'/%3E%3C/svg%3E") center/contain no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 3 2 12h3v8h5v-5h4v5h5v-8h3L12 3z'/%3E%3C/svg%3E") center/contain no-repeat}
    .home-btn:hover{background:#138a72;border-color:#fff;color:#fff}
    .brand,.brand-panel{padding-top:88px}
    @media (max-width:700px){.home-btn{top:14px;left:14px}}
    .btn-link{border:0;background:none;padding:0;font:inherit;font-weight:700;color:var(--green-btn,#106d5a);cursor:pointer}
    .inline-form{display:inline}
    .reject-input{width:150px;margin-right:4px}
    .file-link{color:inherit;text-decoration:underline}
  </style>
</head>
<body>
<?php
}

function page_foot(): void
{
    ?>
  <script>
    document.querySelectorAll('time.lt').forEach(function (el) {
      var d = new Date(el.getAttribute('datetime'));
      if (isNaN(d)) return;
      el.textContent = el.dataset.t === 'dt'
        ? d.toLocaleString('en-GB', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})
        : d.toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
    });
  </script>
</body>
</html>
<?php
}

function brand_chips(): string
{
    return '<div class="chips">
        <div class="chip"><span class="chip__id">CMP-2461</span><span class="chip__status t-high">High</span></div>
        <div class="chip"><span class="chip__id">CMP-2455</span><span class="chip__status t-closed">Closed</span></div>
        <div class="chip"><span class="chip__id">CMP-2470</span><span class="chip__status t-assigned">Assigned</span></div>
        <div class="chip"><span class="chip__id">CMP-2444</span><span class="chip__status t-submitted">Submitted</span></div>
        <div class="chip"><span class="chip__id">CMP-2468</span><span class="chip__status t-urgent">Urgent</span></div>
        <div class="chip"><span class="chip__id">CMP-2450</span><span class="chip__status t-closed">Closed</span></div>
      </div>';
}

/** Two-column public page (brand on the left, form on the right). Close with auth_page_end(). */
function auth_page_start(string $title): void
{
    page_head($title);
    ?>
  <a class="home-btn" href="index.php">Home</a>
  <main class="page">
    <section class="brand">
      <h2 class="brand__name"><a href="index.php">Complaint System</a></h2>
      <?= brand_chips() ?>
      <p class="brand__tagline">One place for every organization to receive, assign, and resolve complaints with nothing sitting unanswered.</p>
    </section>
    <section class="form-side">
<?php
}

function auth_page_end(): void
{
    echo "    </section>\n  </main>\n";
    page_foot();
}

function nav_for(array $user): array
{
    switch ($user['role']) {
        case 'platform_admin':
            return [['index.php', 'Home'], ['admin-approvals.php', 'Organization approvals']];
        case 'complainant':
            return [['index.php', 'Home'], ['my-complaints.php', 'My complaints'], ['file-complaint.php', 'File a complaint']];
        case 'org_admin':
            return [['index.php', 'Home'], ['org-dashboard.php', 'Dashboard'], ['org-team.php', 'Team'], ['org-members.php', 'Members'], ['org-settings.php', 'Settings']];
        case 'supervisor':
            return [['index.php', 'Home'], ['org-dashboard.php', 'Dashboard']];
        default:
            return [['index.php', 'Home'], ['org-dashboard.php', 'My assignments']];
    }
}

function role_label(array $user): string
{
    return [
        'platform_admin' => 'Platform admin',
        'complainant'    => 'Complainant',
        'org_admin'      => 'Organization admin',
        'supervisor'     => 'Focal person',
        'staff'          => 'Handler',
    ][$user['role']] ?? 'User';
}

/** Sidebar layout for logged-in pages. Close with app_page_end(). */
function app_page_start(array $user, string $title, string $active, string $heading, string $sub = '', string $backHref = '', string $backText = ''): void
{
    page_head($title);
    ?>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand-link" href="index.php">Complaint System</a>
      <div class="role-tag"><?= e(role_label($user)) ?></div>
      <nav>
<?php foreach (nav_for($user) as [$href, $label]): ?>
        <a href="<?= e($href) ?>"<?= $href === $active ? ' class="active"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
      </nav>
      <div class="sidebar-foot"><a href="logout.php">Log out</a></div>
    </aside>
    <main class="main">
<?php if ($backHref !== ''): ?>
      <a class="back-link" href="<?= e($backHref) ?>">&larr; <?= e($backText) ?></a>
<?php endif; ?>
      <div class="topbar">
        <div>
          <h1 class="page-title"><?= e($heading) ?></h1>
<?php if ($sub !== ''): ?>
          <p class="page-sub"><?= e($sub) ?></p>
<?php endif; ?>
        </div>
        <div class="user-chip"><?= e($user['name'] ?: $user['email']) ?></div>
      </div>
<?php
}

function app_page_end(): void
{
    echo "    </main>\n  </div>\n";
    page_foot();
}
