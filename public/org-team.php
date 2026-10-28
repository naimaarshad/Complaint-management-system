<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/cms.php';

[$user, $org] = require_active_org('org_admin');

$errors = [];
$old = ['name' => '', 'email' => '', 'role' => 'staff'];
$shownPassword = null;
$ROLE_NAMES = ['supervisor' => 'Focal person', 'staff' => 'Handler'];

function temp_password(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 12; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action', 20);

    if ($action === 'create') {
        $old['name'] = post_str('name', 120);
        $old['email'] = strtolower(post_str('email', 190));
        $old['role'] = post_str('role', 20);

        if (mb_strlen($old['name']) < 3) {
            $errors['name'] = 'Enter their full name.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!isset($ROLE_NAMES[$old['role']])) {
            $errors['role'] = 'Choose a role.';
        }
        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ($stmt->fetchColumn() !== false) {
                $errors['email'] = 'An account with this email already exists.';
            }
        }
        if (!$errors) {
            $pw = temp_password();
            $now = now_utc();
            db()->prepare('INSERT INTO users (org_id, name, email, password_hash, role, is_active, email_verified_at, must_change_password, created_at)
                           VALUES (?, ?, ?, ?, ?, 1, ?, 1, ?)')
                ->execute([$org['id'], $old['name'], $old['email'], password_hash($pw, PASSWORD_DEFAULT), $old['role'], $now, $now]);
            audit('team_member_created', 'user', (int) db()->lastInsertId());

            $sent = send_mail($old['email'], 'Your Complaint System account',
                "Hello {$old['name']},\n\n{$org['name']} added you as a {$ROLE_NAMES[$old['role']]} on Complaint System.\n\n"
                . "Log in with this email and the temporary password below. You will be asked to choose your own password.\n\n"
                . "Temporary password: $pw\n");
            if ($sent) {
                flash('success', "Account created for {$old['name']}. The temporary password was emailed to {$old['email']}.");
                redirect('org-team.php');
            }
            // Mail failed: show the password once so the admin can pass it on.
            $shownPassword = ['name' => $old['name'], 'email' => $old['email'], 'pw' => $pw];
            $old = ['name' => '', 'email' => '', 'role' => 'staff'];
        }
    } elseif ($action === 'toggle') {
        $uid = int_param('uid');
        $stmt = db()->prepare("SELECT id, is_active FROM users WHERE id = ? AND org_id = ? AND role IN ('supervisor','staff')");
        $stmt->execute([$uid, $org['id']]);
        $row = $stmt->fetch();
        if ($row && $uid !== $user['id']) {
            $new = (int) $row['is_active'] ? 0 : 1;
            db()->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$new, $uid]);
            $back = $new ? 0 : unassign_cases($uid, (int) $org['id'], $user['id']);
            audit($new ? 'team_member_enabled' : 'team_member_disabled', 'user', $uid);
            flash('success', $new ? 'Account enabled.' : 'Account disabled. They can no longer log in.'
                . ($back ? " $back open complaint(s) went back to the focal person to reassign." : ''));
        }
        redirect('org-team.php');
    } elseif ($action === 'role') {
        $uid = int_param('uid');
        $newRole = post_str('role', 20);
        $stmt = db()->prepare("SELECT id, role FROM users WHERE id = ? AND org_id = ? AND role IN ('supervisor','staff')");
        $stmt->execute([$uid, $org['id']]);
        $row = $stmt->fetch();
        if ($row && isset($ROLE_NAMES[$newRole]) && $newRole !== $row['role']) {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $uid]);
            $back = $row['role'] === 'staff' ? unassign_cases($uid, (int) $org['id'], $user['id']) : 0;
            audit('team_member_role', 'user', $uid);
            flash('success', 'Role changed.' . ($back ? " $back open complaint(s) went back to the focal person to reassign." : ''));
        }
        redirect('org-team.php');
    }
}

$stmt = db()->prepare(
    "SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at,
            (SELECT COUNT(*) FROM assignments a WHERE a.assigned_to = u.id AND a.status = 'active') AS active_cases
     FROM users u WHERE u.org_id = ? AND u.role IN ('supervisor','staff') ORDER BY u.role, u.name"
);
$stmt->execute([$org['id']]);
$team = $stmt->fetchAll();
$notice = flash('success');

function fc(array $errors, string $key): string
{
    return isset($errors[$key]) ? 'field invalid' : 'field';
}

app_page_start($user, 'Team', 'org-team.php', 'Team', 'Add focal persons, who assign complaints, and handlers, who resolve them.');
?>
<?php if ($notice): ?>
      <div class="notice"><?= e($notice) ?></div>
<?php endif; ?>
<?php if ($shownPassword): ?>
      <div class="notice warn">The email could not be sent. Give <?= e($shownPassword['name']) ?> (<?= e($shownPassword['email']) ?>) this temporary password now, it is not shown again: <strong><?= e($shownPassword['pw']) ?></strong></div>
<?php endif; ?>

      <div class="detail-grid">
        <div class="card">
          <h3>Your team</h3>
<?php if (!$team): ?>
          <p>No one yet. Add a focal person first, then at least one handler.</p>
<?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Name</th><th>Role</th><th>Open cases</th><th></th></tr></thead>
              <tbody>
<?php foreach ($team as $m): ?>
                <tr>
                  <td><?= e($m['name']) ?><br><small><?= e($m['email']) ?></small></td>
                  <td>
                    <form class="inline-form" method="post" action="org-team.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="role">
                      <input type="hidden" name="uid" value="<?= (int) $m['id'] ?>">
                      <select name="role" onchange="this.form.submit()">
<?php foreach ($ROLE_NAMES as $k => $label): ?>
                        <option value="<?= e($k) ?>"<?= $m['role'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
                      </select>
                    </form><?= (int) $m['is_active'] ? '' : ' (disabled)' ?>
                  </td>
                  <td><?= $m['role'] === 'staff' ? (int) $m['active_cases'] : '' ?></td>
                  <td>
                    <form class="inline-form" method="post" action="org-team.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="uid" value="<?= (int) $m['id'] ?>">
                      <button type="submit" class="btn-secondary"><?= (int) $m['is_active'] ? 'Disable' : 'Enable' ?></button>
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
          <h3>Add a team member</h3>
          <form class="app-form" method="post" action="org-team.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="<?= fc($errors, 'name') ?>">
              <label for="name">Full name</label>
              <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" maxlength="120">
              <small class="error"><?= e($errors['name'] ?? '') ?></small>
            </div>
            <div class="<?= fc($errors, 'email') ?>">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" maxlength="190">
              <small class="error"><?= e($errors['email'] ?? '') ?></small>
            </div>
            <div class="<?= fc($errors, 'role') ?>">
              <label for="role">Role</label>
              <select id="role" name="role">
<?php foreach ($ROLE_NAMES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $old['role'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
              </select>
              <small class="error"><?= e($errors['role'] ?? '') ?></small>
            </div>
            <button type="submit" class="btn-primary">Create account</button>
            <p class="form__message">They get a temporary password by email and must change it at first login.</p>
          </form>
        </div>
      </div>
<?php app_page_end();
