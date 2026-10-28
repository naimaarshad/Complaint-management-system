<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

[$user, $org] = require_active_org('org_admin');
$orgId = (int) $org['id'];
$pdo = db();
$error = '';
$report = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action', 10);

    if ($action === 'add') {
        $raw = post_str('emails', 20000);
        $parts = preg_split('/[\s,;]+/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_values(array_unique($parts));

        if (!$parts) {
            $error = 'Enter at least one email address.';
        } elseif (count($parts) > 200) {
            $error = 'Add at most 200 emails at a time.';
        } else {
            $added = 0;
            $existing = 0;
            $invalid = [];
            $find = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $ins = $pdo->prepare('INSERT INTO users (org_id, name, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
            foreach ($parts as $em) {
                if (!filter_var($em, FILTER_VALIDATE_EMAIL) || strlen($em) > 190) {
                    $invalid[] = $em;
                    continue;
                }
                $find->execute([$em]);
                if ($find->fetchColumn() !== false) {
                    $existing++;
                    continue;
                }
                // Placeholder account: nobody can log in until the person joins and sets a password.
                $ins->execute([$orgId, mb_substr(strstr($em, '@', true), 0, 120), $em, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'complainant', now_utc()]);
                $added++;
            }
            audit('members_added', 'organization', $orgId);
            $report = "$added added" . ($existing ? ", $existing already had an account" : '') . ($invalid ? ', ignored invalid: ' . implode(', ', array_slice($invalid, 0, 5)) : '') . '.';
            flash('success', $report);
            redirect('org-members.php');
        }
    } elseif ($action === 'remove') {
        $uid = int_param('uid');
        // Only people who have not joined yet and have never filed anything.
        $pdo->prepare("DELETE FROM users WHERE id = ? AND org_id = ? AND role = 'complainant' AND email_verified_at IS NULL
                       AND NOT EXISTS (SELECT 1 FROM complaints c WHERE c.user_id = users.id)")->execute([$uid, $orgId]);
        flash('success', 'Removed from the member list.');
        redirect('org-members.php');
    } elseif ($action === 'toggle') {
        $uid = int_param('uid');
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ? AND org_id = ? AND role = 'complainant'");
        $stmt->execute([$uid, $orgId]);
        $cur = $stmt->fetchColumn();
        if ($cur !== false) {
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([(int) $cur ? 0 : 1, $uid]);
            flash('success', (int) $cur ? 'Member disabled. They can no longer log in.' : 'Member enabled.');
        }
        redirect('org-members.php');
    }
}

$stmt = $pdo->prepare("SELECT id, name, email, is_active, email_verified_at FROM users WHERE org_id = ? AND role = 'complainant' ORDER BY email");
$stmt->execute([$orgId]);
$members = $stmt->fetchAll();
$notice = flash('success');
$domain = trim((string) $org['email_domain']);

app_page_start($user, 'Members', 'org-members.php', 'Members', 'The people who can file complaints with ' . $org['name'] . '.');
?>
<?php if ($notice): ?>
      <div class="notice"><?= e($notice) ?></div>
<?php endif; ?>
      <div class="detail-grid">
        <div class="card">
          <h3>Member list (<?= count($members) ?>)</h3>
<?php if (!$members): ?>
          <p>No members yet.<?= $domain !== '' ? ' Anyone with an @' . e($domain) . ' email can also join on their own.' : '' ?></p>
<?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Member</th><th>Status</th><th></th></tr></thead>
              <tbody>
<?php foreach ($members as $m): $joined = $m['email_verified_at'] !== null; ?>
                <tr>
                  <td><?= $joined ? e($m['name']) . '<br>' : '' ?><small><?= e($m['email']) ?></small></td>
                  <td><?= $joined ? ((int) $m['is_active'] ? 'Joined' : 'Disabled') : 'Invited, not joined yet' ?></td>
                  <td>
                    <form class="inline-form" method="post" action="org-members.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="uid" value="<?= (int) $m['id'] ?>">
                      <?php if ($joined): ?>
                        <input type="hidden" name="action" value="toggle">
                        <button type="submit" class="btn-secondary"><?= (int) $m['is_active'] ? 'Disable' : 'Enable' ?></button>
                      <?php else: ?>
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="btn-secondary">Remove</button>
                      <?php endif; ?>
                    </form>
                  </td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
<?php endif; ?>
        </div>

        <div class="card">
          <h3>Add members</h3>
          <p>Paste email addresses, one per line or separated by commas. Each person then picks your organization on the home page and joins with that email.</p>
          <form class="app-form" method="post" action="org-members.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="<?= $error !== '' ? 'field invalid' : 'field' ?>">
              <label for="emails">Emails</label>
              <textarea id="emails" name="emails" placeholder="ali@example.com&#10;sara@example.com" style="min-height:140px"></textarea>
              <small class="error"><?= e($error) ?></small>
            </div>
            <button type="submit" class="btn-primary">Add to member list</button>
            <p class="form__message">Tell them to join; we do not email invitations.</p>
          </form>
        </div>
      </div>
<?php app_page_end();
