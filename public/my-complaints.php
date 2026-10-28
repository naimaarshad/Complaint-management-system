<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

$user = require_access('complainant');

$stmt = db()->prepare(
    'SELECT c.id, c.tracking_no, c.subject, c.priority, c.status, c.created_at, o.name AS org_name
     FROM complaints c JOIN organizations o ON o.id = c.org_id
     WHERE c.user_id = ? ORDER BY c.created_at DESC, c.id DESC'
);
$stmt->execute([$user['id']]);
$mine = $stmt->fetchAll();

$count = fn(callable $f): int => count(array_filter($mine, $f));
$open = $count(fn($c) => !in_array($c['status'], ['resolved', 'closed'], true));

app_page_start($user, 'My complaints', 'my-complaints.php', 'My complaints', 'Track everything you have filed, across all organizations.');
?>
      <div class="stat-grid">
        <div class="stat-card"><div class="num"><?= count($mine) ?></div><div class="lbl">Total filed</div></div>
        <div class="stat-card amber"><div class="num"><?= $open ?></div><div class="lbl">Still open</div></div>
        <div class="stat-card moss"><div class="num"><?= $count(fn($c) => $c['status'] === 'resolved') ?></div><div class="lbl">Resolved, awaiting your feedback</div></div>
        <div class="stat-card"><div class="num"><?= $count(fn($c) => $c['status'] === 'closed') ?></div><div class="lbl">Closed</div></div>
      </div>

      <div class="filters">
        <input type="text" id="search" placeholder="Search by ID or subject">
        <select id="statusFilter">
          <option value="">All statuses</option>
<?php foreach (STATUS_LABELS as $k => $label): ?>
          <option value="<?= e($k) ?>"><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
        <a class="btn-primary" href="file-complaint.php">File a complaint</a>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Subject</th><th>Organization</th><th>Priority</th><th>Status</th><th>Filed on</th></tr></thead>
          <tbody id="rows">
<?php foreach ($mine as $c): ?>
            <tr class="row-link" data-id="<?= (int) $c['id'] ?>" data-status="<?= e($c['status']) ?>" data-s="<?= e(strtolower($c['tracking_no'] . ' ' . $c['subject'])) ?>">
              <td class="tnum"><?= e($c['tracking_no']) ?></td>
              <td><?= e($c['subject']) ?></td>
              <td><?= e($c['org_name']) ?></td>
              <td><?= priority_badge($c['priority']) ?></td>
              <td><?= status_badge($c['status']) ?></td>
              <td><?= fmt_time($c['created_at']) ?></td>
            </tr>
<?php endforeach; ?>
            <tr id="emptyRow" style="display:<?= $mine ? 'none' : '' ?>"><td colspan="6"><div class="empty-note" id="emptyNote">
              <?= $mine ? 'No complaints match these filters.' : 'You have not filed any complaints yet. <a href="file-complaint.php">File your first complaint</a>.' ?>
            </div></td></tr>
          </tbody>
        </table>
      </div>
      <script>
        var rows = document.querySelectorAll('#rows tr.row-link');
        function applyFilters() {
          var t = document.getElementById('search').value.trim().toLowerCase();
          var s = document.getElementById('statusFilter').value, shown = 0;
          rows.forEach(function (r) {
            var hit = r.dataset.s.indexOf(t) !== -1 && (s === '' || r.dataset.status === s);
            r.style.display = hit ? '' : 'none';
            if (hit) shown++;
          });
          if (rows.length) document.getElementById('emptyRow').style.display = shown ? 'none' : '';
        }
        document.getElementById('search').addEventListener('input', applyFilters);
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
        document.getElementById('rows').addEventListener('click', function (e) {
          var r = e.target.closest('tr.row-link');
          if (r) location.href = 'complaint-detail.php?id=' + r.dataset.id;
        });
      </script>
<?php app_page_end();
