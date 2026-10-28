<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

[$user, $org] = require_active_org();
$isHandler = $user['role'] === 'staff';

$sql = "SELECT c.id, c.tracking_no, c.subject, c.priority, c.status, c.created_at,
               u.name AS user_name, cat.name AS category_name, h.name AS handler_name
        FROM complaints c
        LEFT JOIN users u ON u.id = c.user_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        LEFT JOIN assignments a ON a.id = (SELECT MAX(id) FROM assignments WHERE complaint_id = c.id AND status IN ('active','completed'))
        LEFT JOIN users h ON h.id = a.assigned_to
        WHERE c.org_id = ?";
$params = [$org['id']];
if ($isHandler) {
    $sql .= " AND a.assigned_to = ?";
    $params[] = $user['id'];
}
$sql .= ' ORDER BY c.created_at DESC, c.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$all = $stmt->fetchAll();

$isOpen = fn($c) => !in_array($c['status'], ['resolved', 'closed'], true);
$n = fn(callable $f): int => count(array_filter($all, $f));

$ready = $user['role'] === 'org_admin' ? org_readiness((int) $org['id']) : null;
$sub = $isHandler ? 'Complaints assigned to you.' : ($user['role'] === 'org_admin' ? 'Overview of every complaint (read only). Focal persons assign them.' : 'Every complaint your organization has received.');
$active = 'org-dashboard.php';
app_page_start($user, 'Dashboard', $active, $org['name'], $sub);
?>
<?php
$reqCount = 0;
if ($user['role'] === 'org_admin') {
    $q = db()->prepare("SELECT COUNT(*) FROM users WHERE org_id = ? AND role = 'complainant' AND is_active = 0 AND email_verified_at IS NOT NULL AND last_login_at IS NULL");
    $q->execute([$org['id']]);
    $reqCount = (int) $q->fetchColumn();
}
?>
<?php if ($reqCount > 0): ?>
      <div class="notice warn"><?= $reqCount ?> <?= $reqCount === 1 ? 'person asked' : 'people asked' ?> to join. <a href="org-members.php">Review requests</a></div>
<?php endif; ?>
<?php if ($ready && !($ready['focal'] && $ready['handler'] && $ready['members'])): ?>
      <div class="card" style="margin-bottom:20px">
        <h3>Set up your organization</h3>
        <p>Members cannot file complaints until a focal person and a handler exist.</p>
        <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px">
          <li><?= $ready['focal'] ? '✓' : '○' ?> <a href="org-team.php">Add a focal person</a> (assigns complaints)</li>
          <li><?= $ready['handler'] ? '✓' : '○' ?> <a href="org-team.php">Add at least one handler</a> (resolves complaints)</li>
          <li><?= $ready['members'] ? '✓' : '○' ?> <a href="org-members.php">Add your members</a> or set your email domain in <a href="org-settings.php">Settings</a></li>
        </ul>
      </div>
<?php endif; ?>
      <div class="stat-grid">
        <div class="stat-card"><div class="num"><?= count($all) ?></div><div class="lbl"><?= $isHandler ? 'Assigned to you' : 'Total received' ?></div></div>
<?php if (!$isHandler): ?>
        <div class="stat-card amber"><div class="num"><?= $n(fn($c) => $c['status'] === 'submitted') ?></div><div class="lbl">New, not yet assigned</div></div>
<?php else: ?>
        <div class="stat-card amber"><div class="num"><?= $n(fn($c) => $isOpen($c)) ?></div><div class="lbl">Open</div></div>
<?php endif; ?>
        <div class="stat-card brick"><div class="num"><?= $n(fn($c) => $isOpen($c) && $c['priority'] === 'urgent') ?></div><div class="lbl">Urgent and open</div></div>
        <div class="stat-card moss"><div class="num"><?= $n(fn($c) => !$isOpen($c)) ?></div><div class="lbl">Resolved or closed</div></div>
      </div>

      <div class="filters">
        <input type="text" id="search" placeholder="Search by ID, subject or complainant">
        <select id="statusFilter">
          <option value="">All statuses</option>
<?php foreach (STATUS_LABELS as $k => $label): ?>
          <option value="<?= e($k) ?>"><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
        <select id="priorityFilter">
          <option value="">All priorities</option>
<?php foreach (array_reverse(PRIORITY_LABELS, true) as $k => $label): ?>
          <option value="<?= e($k) ?>"><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Subject</th><th>Complainant</th><th>Category</th><th>Priority</th><th>Status</th><th>Handler</th><th>Filed on</th></tr></thead>
          <tbody id="rows">
<?php foreach ($all as $c): ?>
            <tr class="row-link" data-id="<?= (int) $c['id'] ?>" data-status="<?= e($c['status']) ?>" data-priority="<?= e($c['priority']) ?>" data-s="<?= e(strtolower($c['tracking_no'] . ' ' . $c['subject'] . ' ' . $c['user_name'])) ?>">
              <td class="tnum"><?= e($c['tracking_no']) ?></td>
              <td><?= e($c['subject']) ?></td>
              <td><?= e($c['user_name'] ?? '') ?></td>
              <td><?= e($c['category_name'] ?? '') ?></td>
              <td><?= priority_badge($c['priority']) ?></td>
              <td><?= status_badge($c['status']) ?></td>
              <td><?= e($c['handler_name'] ?? 'Not assigned') ?></td>
              <td><?= fmt_time($c['created_at']) ?></td>
            </tr>
<?php endforeach; ?>
            <tr id="emptyRow" style="display:<?= $all ? 'none' : '' ?>"><td colspan="8"><div class="empty-note">
              <?= $all ? 'No complaints match these filters.' : ($isHandler ? 'Nothing is assigned to you yet.' : 'No complaints yet. They will appear here as people file them.') ?>
            </div></td></tr>
          </tbody>
        </table>
      </div>
      <script>
        var rows = document.querySelectorAll('#rows tr.row-link');
        function applyFilters() {
          var t = document.getElementById('search').value.trim().toLowerCase();
          var s = document.getElementById('statusFilter').value, p = document.getElementById('priorityFilter').value, shown = 0;
          rows.forEach(function (r) {
            var hit = r.dataset.s.indexOf(t) !== -1 && (s === '' || r.dataset.status === s) && (p === '' || r.dataset.priority === p);
            r.style.display = hit ? '' : 'none';
            if (hit) shown++;
          });
          if (rows.length) document.getElementById('emptyRow').style.display = shown ? 'none' : '';
        }
        ['search'].forEach(function (i) { document.getElementById(i).addEventListener('input', applyFilters); });
        ['statusFilter', 'priorityFilter'].forEach(function (i) { document.getElementById(i).addEventListener('change', applyFilters); });
        document.getElementById('rows').addEventListener('click', function (e) {
          var r = e.target.closest('tr.row-link');
          if (r) location.href = 'complaint-detail.php?id=' + r.dataset.id;
        });
      </script>
<?php app_page_end();
