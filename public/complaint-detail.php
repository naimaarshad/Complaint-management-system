<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

$user = require_access('complainant', 'org');
$id = int_param('id');
$c = complaint_for($user, $id);
if (!$c) {
    redirect(home_for($user));
}

$isOrg = is_org_user($user);
$canAssign = $user['role'] === 'supervisor';
$isHandler = $user['role'] === 'staff' && (int) $c['assigned_to'] === $user['id'];
$isOwner = $user['role'] === 'complainant';
$finished = in_array($c['status'], ['resolved', 'closed'], true);
$self = 'complaint-detail.php?id=' . $id;
$msg = '';
$msgOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action', 20);
    $pdo = db();

    try {
        if ($action === 'assign' && $canAssign && $c['status'] !== 'closed') {
            $handlerId = int_param('handler');
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND org_id = ? AND role = 'staff' AND is_active = 1");
            $stmt->execute([$handlerId, $c['org_id']]);
            $handler = $stmt->fetch();
            $priority = post_str('priority', 10);
            if (!isset(PRIORITY_LABELS[$priority])) {
                $priority = $c['priority'];
            }

            if (!$handler && $priority === $c['priority']) {
                $msg = 'Choose a handler or change the priority.';
            } else {
                $pdo->beginTransaction();
                if ($handler && (int) $handler['id'] !== (int) ($c['assigned_to'] ?? 0)) {
                    $pdo->prepare("UPDATE assignments SET status = 'reassigned' WHERE complaint_id = ? AND status = 'active'")->execute([$id]);
                    $pdo->prepare('INSERT INTO assignments (complaint_id, assigned_to, supervisor_id, status, assigned_at) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$id, $handler['id'], $user['id'], 'active', now_utc()]);
                    if ($c['status'] === 'submitted') {
                        $pdo->prepare('UPDATE complaints SET status = ?, updated_at = ? WHERE id = ?')->execute(['assigned', now_utc(), $id]);
                        log_status($id, 'submitted', 'assigned', $user['id']);
                    }
                    notify((int) $handler['id'], $id, 'assigned', "{$c['tracking_no']} was assigned to you: {$c['subject']}");
                    if ($c['user_id']) {
                        notify((int) $c['user_id'], $id, 'assigned', "{$c['tracking_no']} has been assigned to a handler.");
                    }
                }
                if ($priority !== $c['priority']) {
                    $pdo->prepare('UPDATE complaints SET priority = ?, updated_at = ? WHERE id = ?')->execute([$priority, now_utc(), $id]);
                }
                $pdo->commit();
                audit('complaint_assigned', 'complaint', $id);
                flash('success', 'Saved.');
                redirect($self);
            }
        } elseif ($action === 'update' && $isHandler && $c['status'] !== 'closed') {
            $newStatus = post_str('status', 20);
            $text = post_str('message', 2000);
            if (!in_array($newStatus, ['assigned', 'in_progress', 'resolved'], true)) {
                $newStatus = $c['status'];
            }
            if ($newStatus === 'assigned' && $c['status'] !== 'assigned') {
                $newStatus = $c['status'];
            }
            $changed = $newStatus !== $c['status'];

            if (!$changed && $text === '') {
                $msg = 'Change the status or write a message.';
            } else {
                $pdo->beginTransaction();
                if ($text !== '') {
                    $pdo->prepare('INSERT INTO responses (assignment_id, response_text, submitted_at) VALUES (?, ?, ?)')
                        ->execute([$c['asg_id'], $text, now_utc()]);
                }
                if ($changed) {
                    $pdo->prepare('UPDATE complaints SET status = ?, updated_at = ? WHERE id = ?')->execute([$newStatus, now_utc(), $id]);
                    log_status($id, $c['status'], $newStatus, $user['id']);
                }
                $pdo->prepare('UPDATE assignments SET status = ? WHERE id = ?')
                    ->execute([$newStatus === 'resolved' ? 'completed' : 'active', $c['asg_id']]);
                if ($c['user_id']) {
                    $what = $newStatus === 'resolved' ? "{$c['tracking_no']} was resolved. Please rate how it went." : "{$c['tracking_no']} has an update.";
                    notify((int) $c['user_id'], $id, 'update', $what);
                }
                $pdo->commit();
                audit('complaint_updated', 'complaint', $id);
                flash('success', 'Saved.');
                redirect($self);
            }
        } elseif ($action === 'feedback' && $isOwner && $finished) {
            $rating = int_param('rating');
            $comment = post_str('comment', 1000);
            if ($rating < 1 || $rating > 5) {
                $msg = 'Choose a star rating first.';
            } elseif ($c['rating'] !== null) {
                $msg = 'You have already sent feedback.';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO feedback (complaint_id, rating, comment, created_at) VALUES (?, ?, ?, ?)')
                    ->execute([$id, $rating, $comment !== '' ? $comment : null, now_utc()]);
                if ($c['status'] === 'resolved') {
                    $pdo->prepare('UPDATE complaints SET status = ?, closed_at = ?, updated_at = ? WHERE id = ?')->execute(['closed', now_utc(), now_utc(), $id]);
                    log_status($id, 'resolved', 'closed', $user['id']);
                }
                $pdo->commit();
                audit('feedback_sent', 'complaint', $id);
                flash('success', 'Thanks for your feedback.');
                redirect($self);
            }
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('complaint-detail failed: ' . $ex->getMessage());
        $msg = 'Something went wrong. Please try again.';
    }
    // reload after a failed or empty submit so the page shows current data
    $c = complaint_for($user, $id) ?? $c;
    $finished = in_array($c['status'], ['resolved', 'closed'], true);
}

$notice = flash('success');
$isNew = ($_GET['new'] ?? '') === '1';

// Timeline: status changes plus handler messages, oldest first.
$events = [];
$stmt = db()->prepare('SELECT h.new_status, h.changed_at, u.name FROM status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.complaint_id = ?');
$stmt->execute([$id]);
foreach ($stmt->fetchAll() as $r) {
    $events[] = ['at' => $r['changed_at'], 'title' => STATUS_LABELS[$r['new_status']] ?? $r['new_status'], 'by' => $r['name'] ?: 'System', 'text' => ''];
}
$stmt = db()->prepare('SELECT r.response_text, r.submitted_at, u.name FROM responses r JOIN assignments a ON a.id = r.assignment_id LEFT JOIN users u ON u.id = a.assigned_to WHERE a.complaint_id = ?');
$stmt->execute([$id]);
foreach ($stmt->fetchAll() as $r) {
    $events[] = ['at' => $r['submitted_at'], 'title' => 'Message from handler', 'by' => $r['name'] ?: 'Handler', 'text' => $r['response_text']];
}
usort($events, fn($a, $b) => strcmp($a['at'], $b['at']));

$stmt = db()->prepare('SELECT id, original_name FROM attachments WHERE complaint_id = ?');
$stmt->execute([$id]);
$files = $stmt->fetchAll();

$handlers = $canAssign ? org_handlers((int) $c['org_id']) : [];
$reached = array_search($c['status'], array_keys(STATUS_LABELS), true);

$back = $isOrg ? 'org-dashboard.php' : 'my-complaints.php';
app_page_start($user, 'Complaint', $back, $c['tracking_no'], $c['org_name'], $back, $isOrg ? 'Back to dashboard' : 'Back to my complaints');
?>
<?php if ($isNew && $isOwner): ?>
      <div class="notice">Your complaint was submitted. Your tracking ID is <?= e($c['tracking_no']) ?>. You can follow progress here or from My complaints.</div>
<?php endif; ?>
<?php if ($notice): ?>
      <div class="notice"><?= e($notice) ?></div>
<?php endif; ?>

      <div class="detail-grid">
        <div>
          <div class="card">
            <h3><?= e($c['subject']) ?></h3>
            <p style="white-space:pre-wrap"><?= e($c['description']) ?></p>
          </div>

          <div class="card">
            <h3>Progress</h3>
            <ul class="timeline">
<?php foreach ($events as $ev): ?>
              <li><div class="tl-title"><?= e($ev['title']) ?></div>
                <div class="tl-meta"><?= e($ev['by']) ?> · <?= fmt_time($ev['at'], true) ?></div>
<?php if ($ev['text'] !== ''): ?>
                <div class="tl-text" style="white-space:pre-wrap"><?= e($ev['text']) ?></div>
<?php endif; ?>
              </li>
<?php endforeach; ?>
<?php foreach (array_keys(STATUS_LABELS) as $i => $k): if ($i > $reached): ?>
              <li class="pending"><div class="tl-title"><?= e(STATUS_LABELS[$k]) ?></div></li>
<?php endif; endforeach; ?>
            </ul>
          </div>

<?php if ($canAssign && $c['status'] !== 'closed'): ?>
          <div class="card">
            <h3><?= $c['handler_name'] ? 'Reassign or change priority' : 'Assign to a handler' ?></h3>
<?php if (!$handlers): ?>
            <p>No handlers yet. Ask your organization admin to add handlers.</p>
<?php endif; ?>
            <form class="app-form" method="post" action="<?= e($self) ?>" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="assign">
              <div class="field">
                <label for="handler">Handler</label>
                <select id="handler" name="handler">
                  <option value="">Choose a handler</option>
<?php foreach ($handlers as $h): ?>
                  <option value="<?= (int) $h['id'] ?>"<?= (int) $h['id'] === (int) ($c['assigned_to'] ?? 0) ? ' selected' : '' ?>><?= e($h['name']) ?></option>
<?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
<?php foreach (PRIORITY_LABELS as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $k === $c['priority'] ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn-primary">Save</button>
              <p class="form__message" role="status" style="color:#b3261e"><?= $msg !== '' ? e($msg) : '' ?></p>
            </form>
          </div>
<?php endif; ?>

<?php if ($isHandler && $c['status'] !== 'closed'): ?>
          <div class="card">
            <h3>Update this complaint</h3>
            <form class="app-form" method="post" action="<?= e($self) ?>" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
<?php foreach (['assigned', 'in_progress', 'resolved'] as $k):
        if ($k === 'assigned' && $c['status'] !== 'assigned') continue; ?>
                  <option value="<?= e($k) ?>"<?= $k === $c['status'] ? ' selected' : '' ?>><?= e(STATUS_LABELS[$k]) ?></option>
<?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="message">Message to the complainant</label>
                <textarea id="message" name="message" placeholder="Explain what has been done or what happens next." style="min-height: 90px;"></textarea>
              </div>
              <button type="submit" class="btn-primary">Save update</button>
              <p class="form__message" role="status" style="color:#b3261e"><?= $msg !== '' ? e($msg) : '' ?></p>
            </form>
          </div>
<?php endif; ?>

<?php if ($isOwner && $finished): ?>
          <div class="card">
            <h3>How was the resolution?</h3>
<?php if ($c['rating'] !== null): ?>
            <div class="stars-static"><?= str_repeat('★', (int) $c['rating']) . str_repeat('☆', 5 - (int) $c['rating']) ?></div>
            <p><?= $c['feedback_comment'] ? e($c['feedback_comment']) : 'Thanks for your rating.' ?></p>
<?php else: ?>
            <form class="app-form" method="post" action="<?= e($self) ?>" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="feedback">
              <input type="hidden" name="rating" id="rating" value="0">
              <div class="stars" id="stars">
<?php for ($n = 1; $n <= 5; $n++): ?>
                <button type="button" data-n="<?= $n ?>" aria-label="<?= $n ?> stars">★</button>
<?php endfor; ?>
              </div>
              <div class="field"><textarea name="comment" placeholder="Anything you would like to add? (optional)" style="min-height: 80px;"></textarea></div>
              <button type="submit" class="btn-primary">Send feedback</button>
              <p class="form__message" role="status" style="color:#b3261e"><?= $msg !== '' ? e($msg) : '' ?></p>
            </form>
            <script>
              var stars = document.querySelectorAll('#stars button');
              stars.forEach(function (b) {
                b.addEventListener('click', function () {
                  var n = parseInt(b.dataset.n, 10);
                  document.getElementById('rating').value = n;
                  stars.forEach(function (s, i) { s.classList.toggle('on', i < n); });
                });
              });
            </script>
<?php endif; ?>
          </div>
<?php elseif ($isOrg && $c['rating'] !== null): ?>
          <div class="card">
            <h3>Complainant feedback</h3>
            <div class="stars-static"><?= str_repeat('★', (int) $c['rating']) . str_repeat('☆', 5 - (int) $c['rating']) ?></div>
<?php if ($c['feedback_comment']): ?><p><?= e($c['feedback_comment']) ?></p><?php endif; ?>
          </div>
<?php endif; ?>
        </div>

        <div class="card">
          <h3>Details</h3>
          <dl class="meta-list">
            <div><dt>Status</dt><dd><?= status_badge($c['status']) ?></dd></div>
            <div><dt>Priority</dt><dd><?= priority_badge($c['priority']) ?></dd></div>
            <div><dt>Category</dt><dd><?= e($c['category_name'] ?? 'Not set') ?></dd></div>
            <div><dt>Organization</dt><dd><?= e($c['org_name']) ?></dd></div>
            <div><dt>Filed by</dt><dd><?= e($c['user_name'] ?? 'Removed account') ?></dd></div>
            <div><dt>Handler</dt><dd><?= e($c['handler_name'] ?? 'Not assigned yet') ?></dd></div>
            <div><dt>Filed on</dt><dd><?= fmt_time($c['created_at']) ?></dd></div>
<?php foreach ($files as $f): ?>
            <div><dt>Attachment</dt><dd><a class="file-link" href="download.php?t=attachment&amp;id=<?= (int) $f['id'] ?>" target="_blank" rel="noopener"><?= e($f['original_name']) ?></a></dd></div>
<?php endforeach; ?>
          </dl>
        </div>
      </div>
<?php app_page_end();
