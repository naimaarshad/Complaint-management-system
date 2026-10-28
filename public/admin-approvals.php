<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/cms.php';

$user = require_access('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action', 10);
    $orgId = int_param('org');

    $stmt = $pdo->prepare("SELECT id, name FROM organizations WHERE id = ? AND status = 'pending'");
    $stmt->execute([$orgId]);
    $org = $stmt->fetch();

    if ($org && in_array($action, ['approve', 'reject'], true)) {
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE org_id = ? AND role = 'org_admin' ORDER BY id LIMIT 1");
        $stmt->execute([$orgId]);
        $contact = $stmt->fetch();

        $pdo->beginTransaction();
        if ($action === 'approve') {
            $pdo->prepare("UPDATE organizations SET status = 'approved', reviewed_by = ?, reviewed_at = ?, rejection_reason = NULL WHERE id = ?")
                ->execute([$user['id'], now_utc(), $orgId]);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE org_id = ?');
            $stmt->execute([$orgId]);
            if ((int) $stmt->fetchColumn() === 0) {
                seed_categories($orgId);
            }
            $reason = null;
        } else {
            $reason = post_str('reason', 255);
            if ($reason === '') {
                $reason = 'We could not verify the documents you submitted. Please contact support.';
            }
            $pdo->prepare("UPDATE organizations SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, rejection_reason = ? WHERE id = ?")
                ->execute([$user['id'], now_utc(), $reason, $orgId]);
        }
        $pdo->commit();
        audit('org_' . $action, 'organization', $orgId);

        if ($contact) {
            $body = $action === 'approve'
                ? "Hello {$contact['name']},\n\n{$org['name']} has been approved. Log in to add focal persons and handlers and start receiving complaints."
                : "Hello {$contact['name']},\n\nWe could not approve {$org['name']}.\nReason: $reason";
            send_mail($contact['email'], 'Your Complaint System registration', $body);
        }
        flash('success', $org['name'] . ($action === 'approve' ? ' approved.' : ' rejected.'));
    }
    redirect('admin-approvals.php');
}

$rows = $pdo->query(
    "SELECT o.id, o.name, o.sector, o.city, o.status, o.created_at,
            (SELECT u.name FROM users u WHERE u.org_id = o.id AND u.role = 'org_admin' ORDER BY u.id LIMIT 1) AS contact_name,
            (SELECT u.email FROM users u WHERE u.org_id = o.id AND u.role = 'org_admin' ORDER BY u.id LIMIT 1) AS contact_email,
            (SELECT d.id FROM organization_documents d WHERE d.org_id = o.id ORDER BY d.id DESC LIMIT 1) AS doc_id,
            (SELECT d.original_name FROM organization_documents d WHERE d.org_id = o.id ORDER BY d.id DESC LIMIT 1) AS doc_name,
            (SELECT u.email_verified_at FROM users u WHERE u.org_id = o.id AND u.role = 'org_admin' ORDER BY u.id LIMIT 1) AS contact_verified
     FROM organizations o ORDER BY o.created_at DESC, o.id DESC"
)->fetchAll();

// Only registrations whose contact verified their email are worth reviewing.
$pending = array_values(array_filter($rows, fn($r) => $r['status'] === 'pending' && $r['contact_verified'] !== null));
$reviewed = array_values(array_filter($rows, fn($r) => $r['status'] !== 'pending'));
$approved = count(array_filter($rows, fn($r) => $r['status'] === 'approved'));
$rejected = count(array_filter($rows, fn($r) => $r['status'] === 'rejected'));
$notice = flash('success');

app_page_start($user, 'Organization approvals', 'admin-approvals.php', 'Organization approvals', 'Review organizations that asked to join before they can receive complaints.');
?>
<?php if ($notice): ?>
      <div class="notice"><?= e($notice) ?></div>
<?php endif; ?>
      <div class="stat-grid">
        <div class="stat-card amber"><div class="num"><?= count($pending) ?></div><div class="lbl">Waiting for review</div></div>
        <div class="stat-card moss"><div class="num"><?= $approved ?></div><div class="lbl">Approved</div></div>
        <div class="stat-card brick"><div class="num"><?= $rejected ?></div><div class="lbl">Rejected</div></div>
        <div class="stat-card"><div class="num"><?= $approved ?></div><div class="lbl">Live organizations</div></div>
      </div>

      <h2 class="section-heading">Waiting for review</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Organization</th><th>Sector</th><th>City</th><th>Contact</th><th>Document</th><th>Submitted</th><th>Decision</th></tr></thead>
          <tbody>
<?php foreach ($pending as $r): ?>
            <tr>
              <td class="tnum"><?= e($r['name']) ?></td>
              <td><?= e($r['sector']) ?></td>
              <td><?= e($r['city']) ?></td>
              <td><?= e($r['contact_name']) ?><br><?= e($r['contact_email']) ?></td>
              <td><?php if ($r['doc_id']): ?><a class="file-link" href="download.php?t=orgdoc&amp;id=<?= (int) $r['doc_id'] ?>" target="_blank" rel="noopener"><?= e($r['doc_name']) ?></a><?php endif; ?></td>
              <td><?= fmt_time($r['created_at']) ?></td>
              <td>
                <form class="inline-form" method="post" action="admin-approvals.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="org" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn-primary" name="action" value="approve">Approve</button>
                </form>
                <form class="inline-form" method="post" action="admin-approvals.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="org" value="<?= (int) $r['id'] ?>">
                  <input type="text" class="reject-input" name="reason" placeholder="Reason (optional)" maxlength="255">
                  <button type="submit" class="btn-secondary" name="action" value="reject">Reject</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
<?php if (!$pending): ?>
            <tr><td colspan="7"><div class="empty-note">Nothing waiting. New registrations will show up here.</div></td></tr>
<?php endif; ?>
          </tbody>
        </table>
      </div>

      <h2 class="section-heading">Already reviewed</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Organization</th><th>Sector</th><th>City</th><th>Contact</th><th>Submitted</th><th>Result</th></tr></thead>
          <tbody>
<?php foreach ($reviewed as $r): ?>
            <tr>
              <td class="tnum"><?= e($r['name']) ?></td>
              <td><?= e($r['sector']) ?></td>
              <td><?= e($r['city']) ?></td>
              <td><?= e($r['contact_name']) ?></td>
              <td><?= fmt_time($r['created_at']) ?></td>
              <td><span class="badge <?= $r['status'] === 'approved' ? 't-closed' : 't-urgent' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            </tr>
<?php endforeach; ?>
<?php if (!$reviewed): ?>
            <tr><td colspan="6"><div class="empty-note">No reviewed registrations yet.</div></td></tr>
<?php endif; ?>
          </tbody>
        </table>
      </div>
<?php app_page_end();
