<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

$user = require_access('complainant', 'org', 'admin');
$forced = (bool) $user['must_change_password'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors['current'] = 'That is not your current password.';
    }
    if (strlen($new) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif (strlen($new) > 72) {
        $errors['password'] = 'Use at most 72 characters.';
    } elseif ($new === $current) {
        $errors['password'] = 'Choose a different password.';
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        audit('password_changed', 'user', $user['id']);
        flash('success', 'Password changed.');
        redirect(home_for($user));
    }
}

auth_page_start('Change password');
?>
      <form class="form" method="post" action="change-password.php" novalidate>
        <?= csrf_field() ?>
        <h1>Choose your password</h1>
        <p class="form__sub"><?= $forced ? 'You are using a temporary password. Set your own to continue.' : 'Enter your current password and a new one.' ?></p>

        <div class="<?= isset($errors['current']) ? 'field invalid' : 'field' ?>">
          <label for="current"><?= $forced ? 'Temporary password' : 'Current password' ?></label>
          <input type="password" id="current" name="current" autocomplete="current-password" autofocus>
          <small class="error"><?= e($errors['current'] ?? '') ?></small>
        </div>

        <div class="<?= isset($errors['password']) ? 'field invalid' : 'field' ?>">
          <label for="password">New password</label>
          <input type="password" id="password" name="password" placeholder="At least 8 characters" autocomplete="new-password">
          <small class="error"><?= e($errors['password'] ?? '') ?></small>
        </div>

        <button type="submit" class="btn">Save password</button>
        <p class="form__foot"><a href="logout.php">Log out</a></p>
      </form>
<?php auth_page_end();
