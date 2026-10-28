<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

$user = current_user();
if ($user) {
    redirect(home_for($user));
}

$stmt = db()->prepare("SELECT id, name, sector, city, email_domain FROM organizations WHERE id = ? AND status = 'approved'");
$stmt->execute([int_param('org')]);
$org = $stmt->fetch();
if (!$org) {
    redirect('select-org.php');
}
$ready = org_readiness((int) $org['id'])['ready'];
$_SESSION['after_login'] = 'file-complaint.php';

auth_page_start($org['name']);
?>
      <div class="form">
        <h1><?= e($org['name']) ?></h1>
        <p class="form__sub"><?= e($org['sector']) ?> · <?= e($org['city']) ?></p>
<?php if (!$ready): ?>
        <p class="form__message" style="color:#b3261e">This organization is still setting up and is not taking complaints yet. Please check back soon.</p>
<?php else: ?>
        <p class="form__sub">Complaints here go only to <?= e($org['name']) ?>. You need to be a member of this organization to file one.</p>
        <div style="display:grid;gap:12px;margin:18px 0">
          <a class="btn" style="text-align:center;text-decoration:none" href="login.php?org=<?= (int) $org['id'] ?>">I'm a member: log in</a>
          <a class="btn-secondary" style="text-align:center;text-decoration:none" href="register.php?org=<?= (int) $org['id'] ?>">First time here: join as a member</a>
        </div>
        <p class="form__foot">
<?php if (trim((string) $org['email_domain']) !== ''): ?>
          To join, use your <strong>@<?= e($org['email_domain']) ?></strong> email address, or the email your organization added for you.
<?php else: ?>
          To join, use the email address your organization added for you.
<?php endif; ?>
        </p>
<?php endif; ?>
        <p class="form__foot"><a href="select-org.php">Choose a different organization</a></p>
      </div>
<?php auth_page_end();
