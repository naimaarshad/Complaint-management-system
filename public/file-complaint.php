<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

// Members file only to their own organization.
if (current_user() === null) {
    redirect('select-org.php');
}

$user = require_access('complainant');

$org = org_row($user['org_id']);
if (!$org || $org['status'] !== 'approved') {
    redirect('my-complaints.php');
}
$orgReady = org_readiness((int) $org['id'])['ready'];

$categories = active_categories((int) $org['id']);
$errors = [];
$old = ['category' => '', 'subject' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $orgReady) {
    csrf_check();
    $old['category'] = (string) int_param('category');
    $old['subject'] = post_str('subject', 120);
    $old['description'] = post_str('description', 5000);

    $catId = 0;
    foreach ($categories as $c) {
        if ((string) $c['id'] === $old['category']) {
            $catId = (int) $c['id'];
        }
    }
    if ($catId === 0) {
        $errors['category'] = 'Choose a category.';
    }
    if (mb_strlen($old['subject']) < 5) {
        $errors['subject'] = 'Write a short subject (at least 5 characters).';
    }
    if (mb_strlen($old['description']) < 20) {
        $errors['description'] = 'Describe what happened (at least 20 characters).';
    }

    $file = null;
    if (!$errors) {
        [$file, $fileError] = save_upload('attachment', false);
        if ($fileError !== '') {
            $errors['attachment'] = $fileError;
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $now = now_utc();
            $pdo->prepare('INSERT INTO complaints (org_id, user_id, category_id, tracking_no, subject, description, priority, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$org['id'], $user['id'], $catId, 'T' . bin2hex(random_bytes(8)), $old['subject'], $old['description'], 'medium', 'submitted', $now, $now]);
            $cid = (int) $pdo->lastInsertId();
            $tracking = 'CMP-' . (2400 + $cid);
            $pdo->prepare('UPDATE complaints SET tracking_no = ? WHERE id = ?')->execute([$tracking, $cid]);
            log_status($cid, null, 'submitted', $user['id']);

            if ($file) {
                $pdo->prepare('INSERT INTO attachments (complaint_id, file_path, original_name, mime_type, size_bytes, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$cid, $file['file_path'], $file['original_name'], $file['mime_type'], $file['size_bytes'], $user['id'], $now]);
            }

            // Tell the organization's focal persons and admin that something new arrived.
            $stmt = $pdo->prepare("SELECT id FROM users WHERE org_id = ? AND role IN ('supervisor','org_admin') AND is_active = 1");
            $stmt->execute([$org['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                notify((int) $uid, $cid, 'new_complaint', "New complaint $tracking: " . $old['subject']);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($file) {
                delete_upload($file['file_path']);
            }
            error_log('file-complaint failed: ' . $ex->getMessage());
            $errors['form'] = 'Something went wrong. Please try again.';
        }

        if (!$errors) {
            audit('complaint_created', 'complaint', $cid);
            redirect('complaint-detail.php?id=' . $cid . '&new=1');
        }
    }
}

function fc(array $errors, string $key): string
{
    return isset($errors[$key]) ? 'field invalid' : 'field';
}

app_page_start($user, 'File a complaint', 'file-complaint.php', 'File a complaint', 'Tell the organization what went wrong. You can follow progress from My complaints.');
?>
      <form class="app-form card" method="post" action="file-complaint.php" enctype="multipart/form-data" novalidate style="max-width: 640px;">
        <?= csrf_field() ?>
<?php if (!$orgReady): ?>
        <div class="notice warn">Your organization is still setting up and cannot take complaints yet. Please try again later.</div>
<?php endif; ?>
        <div class="field">
          <label>Organization</label>
          <div class="readonly-box">
            <span><?= e($org['name']) ?></span>
          </div>
        </div>

        <div class="<?= fc($errors, 'category') ?>">
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="">Choose a category</option>
<?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= $old['category'] === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
<?php endforeach; ?>
          </select>
          <small class="error"><?= e($errors['category'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'subject') ?>">
          <label for="subject">Subject</label>
          <input type="text" id="subject" name="subject" value="<?= e($old['subject']) ?>" placeholder="A short summary of the problem" maxlength="120">
          <small class="error"><?= e($errors['subject'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'description') ?>">
          <label for="description">What happened?</label>
          <textarea id="description" name="description" placeholder="Include dates, names of departments or staff, and anything you already tried."><?= e($old['description']) ?></textarea>
          <small class="error"><?= e($errors['description'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'attachment') ?>">
          <label for="attachment">Attachment (optional)</label>
          <label class="upload-box" for="attachment" id="attachLabel">Click to attach a photo or PDF (max 5 MB)
            <input type="file" id="attachment" name="attachment" accept=".pdf,.png,.jpg,.jpeg">
          </label>
          <small class="error"><?= e($errors['attachment'] ?? '') ?></small>
        </div>

        <button type="submit" class="btn-primary"<?= $orgReady ? '' : ' disabled' ?>>Submit complaint</button>
        <p class="form__message" role="status" style="color:#b3261e"><?= e($errors['form'] ?? '') ?></p>
      </form>
      <script>
        document.getElementById('attachment').addEventListener('change', function () {
          var f = this.files[0];
          document.getElementById('attachLabel').firstChild.textContent = f ? 'Attached: ' + f.name : 'Click to attach a photo or PDF (max 5 MB)';
        });
      </script>
<?php app_page_end();
