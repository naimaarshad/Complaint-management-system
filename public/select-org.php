<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

// Public page: anyone can browse organizations. Logging in happens when they pick one.
$user = current_user();
if ($user) {
    redirect(home_for($user));
}

$orgs = db()->query("SELECT id, name, sector, city FROM organizations WHERE status = 'approved' ORDER BY name")->fetchAll();

page_head('Select organization');
?>
  <a class="home-btn" href="index.php">Home</a>
  <div class="auth-shell">
    <aside class="brand-panel">
      <div class="brand-word"><a href="index.php">Complaint System</a></div>
      <div class="chip-grid" aria-hidden="true">
        <div class="chip"><span class="chip-id">CMP-2461</span><span class="chip-status">High</span></div>
        <div class="chip"><span class="chip-id">CMP-2455</span><span class="chip-status">Closed</span></div>
        <div class="chip"><span class="chip-id">CMP-2470</span><span class="chip-status">Assigned</span></div>
        <div class="chip"><span class="chip-id">CMP-2444</span><span class="chip-status">Submitted</span></div>
        <div class="chip"><span class="chip-id">CMP-2468</span><span class="chip-status">Urgent</span></div>
        <div class="chip"><span class="chip-id">CMP-2450</span><span class="chip-status">Closed</span></div>
      </div>
      <p class="brand-tag">One place for every organization to receive, assign, and resolve complaints, with nothing sitting unanswered.</p>
    </aside>

    <main class="form-panel">
      <div class="form-wrap">
        <h1>Select an organization</h1>
        <p class="sub">Which organization are you part of?</p>

        <div class="filters" style="margin-bottom: 18px;">
          <input type="text" id="orgSearch" placeholder="Search organizations" style="width: 100%;">
        </div>

        <div id="orgList">
<?php if (!$orgs): ?>
          <p class="sub">No organizations have joined yet.</p>
<?php endif; ?>
<?php foreach ($orgs as $o): ?>
          <a class="org-card" href="org-portal.php?org=<?= (int) $o['id'] ?>" data-s="<?= e(strtolower($o['name'] . ' ' . $o['sector'] . ' ' . $o['city'])) ?>">
            <div class="org-top"><span class="org-name"><?= e($o['name']) ?></span><span class="org-arrow">→</span></div>
            <div class="org-meta"><?= e($o['sector']) ?> · <?= e($o['city']) ?></div>
          </a>
<?php endforeach; ?>
        </div>
        <p class="sub" id="noMatch" style="display:none">No organizations match your search.</p>

        <p class="foot">Don't see your organization? Ask them to <a href="register-org.php">register on Complaint System</a>.</p>
        <p class="foot">Complaints go only to the organization you choose, and you need to be one of its members.</p>
      </div>
    </main>
  </div>
  <script>
    var box = document.getElementById('orgSearch');
    box.addEventListener('input', function () {
      var t = box.value.trim().toLowerCase(), shown = 0;
      document.querySelectorAll('#orgList .org-card').forEach(function (a) {
        var hit = a.dataset.s.indexOf(t) !== -1;
        a.style.display = hit ? '' : 'none';
        if (hit) shown++;
      });
      document.getElementById('noMatch').style.display = shown === 0 && document.querySelector('#orgList .org-card') ? '' : 'none';
    });
  </script>
<?php page_foot();
