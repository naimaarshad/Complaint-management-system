<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

$user = require_access('org');
$org = org_row($user['org_id'] ?? null);
if ($org && $org['status'] === 'approved') {
    redirect('org-dashboard.php');
}

function step(string $text, string $state): string
{
    return '<li class="' . e($state) . '">' . e($text) . '</li>';
}

$status = $org['status'] ?? 'pending';
$actions = '';

if ($status === 'rejected') {
    $title = 'Registration not approved';
    $sub = $org['rejection_reason'] ?: 'We could not verify the documents you submitted.';
    $steps = step('Registration received', 'done') . step('Documents reviewed', 'done') . step('Not approved', 'current');
    $actions = '<a class="btn-secondary" href="index.php">Back to home</a>';
} elseif ($status === 'suspended') {
    $title = 'Organization suspended';
    $sub = 'Your organization is not receiving complaints right now. Please contact support.';
    $steps = step('Registration received', 'done') . step('Approved', 'done') . step('Suspended', 'current');
    $actions = '<a class="btn-secondary" href="index.php">Back to home</a>';
} else {
    $title = 'Your registration is under review';
    $sub = ($org ? $org['name'] . ' was submitted on ' . gmdate('d M Y', strtotime($org['created_at'] . ' UTC')) . '. ' : '') . 'Approval usually takes under 2 business days.';
    $steps = step('Registration received', 'done') . step('Documents under review', 'current') . step('Approved: dashboard access', '');
    $actions = '<a class="btn-primary" href="org-pending.php">Check status</a><a class="btn-secondary" href="index.php">Back to home</a>';
}

auth_page_start('Registration status');
?>
      <div class="form">
        <h1><?= e($title) ?></h1>
        <p class="form__sub"><?= e($sub) ?></p>
        <ul class="status-steps"><?= $steps ?></ul>
        <div class="status-actions">
          <?= $actions ?>
          <a class="btn-secondary" href="logout.php">Log out</a>
        </div>
      </div>
<?php auth_page_end();
