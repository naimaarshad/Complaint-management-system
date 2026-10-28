<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

[$user, $org] = require_active_org('org_admin');
$orgId = (int) $org['id'];
$pdo = db();
$error = '';
$domainError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action', 10);

    if ($action === 'domain') {
        $d = ltrim(strtolower(post_str('domain', 190)), '@ ');
        if ($d !== '' && !valid_domain($d)) {
            $error = 'Enter a domain like northbridge.edu.pk, or leave it empty.';
            $domainError = $error;
            $error = '';
        } else {
            $pdo->prepare('UPDATE organizations SET email_domain = ? WHERE id = ?')->execute([$d !== '' ? $d : null, $orgId]);
            flash('success', $d !== '' ? "Anyone with an @$d email can now join as a member." : 'Email domain removed. Only members you add can join.');
            redirect('org-settings.php');
        }
    } elseif ($action === 'add') {
        $name = post_str('name', 40);
        $stmt = $pdo->prepare('SELECT id, is_active FROM categories WHERE org_id = ? AND LOWER(name) = LOWER(?)');
        $stmt->execute([$orgId, $name]);
        $existing = $stmt->fetch();
        $activeCount = count(active_categories($orgId));

        if (mb_strlen($name) < 2) {
            $error = 'Enter a category name (at least 2 characters).';
        } elseif ($existing && (int) $existing['is_active']) {
            $error = 'That category already exists.';
        } elseif ($activeCount >= MAX_CATEGORIES) {
            $error = 'You can have up to ' . MAX_CATEGORIES . ' categories.';
        } else {
            if ($existing) {
                $pdo->prepare('UPDATE categories SET is_active = 1 WHERE id = ?')->execute([$existing['id']]);
            } else {
                $pdo->prepare('INSERT INTO categories (org_id, name) VALUES (?, ?)')->execute([$orgId, $name]);
            }
            flash('success', 'Added "' . $name . '". It now appears on your complaint form.');
            redirect('org-settings.php');
        }
    } elseif ($action === 'remove') {
        $catId = int_param('cat');
        if (count(active_categories($orgId)) > 1) {
            // Soft delete: complaints already filed under it keep their category.
            $pdo->prepare('UPDATE categories SET is_active = 0 WHERE id = ? AND org_id = ?')->execute([$catId, $orgId]);
            flash('success', 'Category removed from the complaint form.');
        }
        redirect('org-settings.php');
    }
}

$cats = active_categories($orgId);
$notice = flash('success');

app_page_start($user, 'Settings', 'org-settings.php', 'Settings', 'Your organization profile and the complaint categories people can choose from.');
?>
<?php if ($notice): ?>
      <div class="notice"><?= e($notice) ?></div>
<?php endif; ?>
      <div class="detail-grid">
        <div class="card">
          <h3>Complaint categories</h3>
          <p>People pick one of these when they file a complaint with you. Changes apply straight away.</p>
          <ul class="cat-list">
<?php foreach ($cats as $c): ?>
            <li class="cat-item"><span><?= e($c['name']) ?></span>
              <form class="inline-form" method="post" action="org-settings.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="cat" value="<?= (int) $c['id'] ?>">
                <button type="submit" class="cat-remove"<?= count($cats) === 1 ? ' disabled' : '' ?>>Remove</button>
              </form>
            </li>
<?php endforeach; ?>
          </ul>
          <form class="app-form" method="post" action="org-settings.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="<?= $error !== '' ? 'field invalid' : 'field' ?>">
              <label for="catName">Add a category</label>
              <input type="text" id="catName" name="name" placeholder="e.g. Parking" maxlength="40">
              <small class="error"><?= e($error) ?></small>
            </div>
            <button type="submit" class="btn-primary">Add category</button>
          </form>
        </div>

        <div class="card">
          <h3>Member email domain</h3>
          <p>People with an email on this domain can join as members on their own, after confirming the email with a code. Leave it empty to admit only the members you add.</p>
          <form class="app-form" method="post" action="org-settings.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="domain">
            <div class="<?= $domainError !== '' ? 'field invalid' : 'field' ?>">
              <label for="domain">Domain</label>
              <input type="text" id="domain" name="domain" value="<?= e((string) $org['email_domain']) ?>" placeholder="northbridge.edu.pk" maxlength="190">
              <small class="error"><?= e($domainError) ?></small>
            </div>
            <button type="submit" class="btn-primary">Save domain</button>
          </form>
        </div>

        <div class="card">
          <h3>Organization profile</h3>
          <dl class="meta-list">
            <div><dt>Name</dt><dd><?= e($org['name']) ?></dd></div>
            <div><dt>Sector</dt><dd><?= e($org['sector']) ?></dd></div>
            <div><dt>City</dt><dd><?= e($org['city']) ?></dd></div>
          </dl>
        </div>
      </div>
<?php app_page_end();
