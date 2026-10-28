<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/otp.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/cms.php';

$email = (string) ($_SESSION['reset_email'] ?? '');
if ($email === '') {
    redirect('forgot-password.php');
}

$stmt = db()->prepare('SELECT id, name, email, role, org_id FROM users WHERE email = ? AND is_active = 1 AND email_verified_at IS NOT NULL');
$stmt->execute([$email]);
$user = $stmt->fetch() ?: null;

$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'resend') {
        if ($user) {
            $code = otp_issue((int) $user['id'], 'password_reset');
            if ($code === null) {
                $errors['code'] = 'Please wait a minute before asking for another code.';
            } else {
                send_mail($user['email'], 'Your Complaint System password reset code',
                    "Hello {$user['name']},\n\nYour password reset code is: $code\n\nIt expires in 10 minutes.");
            }
        }
        if (!$errors) {
            $notice = 'If the account exists, a new code has been sent.';
        }
    } else {
        $code = preg_replace('/\D/', '', post_str('code', 20));
        $new = (string) ($_POST['password'] ?? '');

        if (strlen($new) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        } elseif (strlen($new) > 72) {
            $errors['password'] = 'Use at most 72 characters.';
        }
        if (strlen($code) !== 6) {
            $errors['code'] = 'Enter the 6-digit code from your email.';
        }

        if (!$errors) {
            $result = $user ? otp_verify((int) $user['id'], 'password_reset', $code) : 'invalid';
            if ($result === 'ok') {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                login_clear_failures($user['email']);
                audit('password_reset', 'user', (int) $user['id']);
                unset($_SESSION['reset_email']);
                flash('success', 'Password changed. You can log in now.');
                redirect($user['role'] === 'platform_admin' ? 'admin-login.php' : ($user['role'] === 'complainant' && $user['org_id'] ? 'login.php?org=' . (int) $user['org_id'] : 'login.php?as=staff'));
            }
            $messages = [
                'invalid' => 'That code is not correct.',
                'expired' => 'That code has expired. Request a new one.',
                'locked'  => 'Too many wrong attempts. Request a new code.',
                'none'    => 'There is no active code. Request a new one.',
            ];
            $errors['code'] = $messages[$result] ?? 'Something went wrong. Try again.';
        }
    }
}

auth_page_start('Choose a new password');
?>
      <form class="form" method="post" action="reset-password.php" novalidate>
        <?= csrf_field() ?>
        <h1>Choose a new password</h1>
        <p class="form__sub">If an account exists for <strong><?= e(mask_email($email)) ?></strong>, we sent it a 6-digit code. It expires in 10 minutes.</p>

        <div class="<?= isset($errors['code']) ? 'field invalid' : 'field' ?>">
          <label for="code">Reset code</label>
          <input type="text" id="code" name="code" inputmode="numeric" maxlength="6" placeholder="123456" autocomplete="one-time-code" autofocus style="letter-spacing:0.4em;font-size:1.3rem;text-align:center;">
          <small class="error"><?= e($errors['code'] ?? '') ?></small>
        </div>

        <div class="<?= isset($errors['password']) ? 'field invalid' : 'field' ?>">
          <label for="password">New password</label>
          <input type="password" id="password" name="password" placeholder="At least 8 characters" autocomplete="new-password">
          <small class="error"><?= e($errors['password'] ?? '') ?></small>
        </div>

        <button type="submit" class="btn" name="action" value="reset">Change password</button>
        <p class="form__message success" role="status"><?= e($notice) ?></p>
<?php if (env('APP_ENV') === 'dev'): ?>
        <p class="form__message">Dev mode: the code is in <code>storage/logs/mail.log</code>.</p>
<?php endif; ?>
        <p class="form__foot">Did not get it? <button type="submit" name="action" value="resend" class="btn-link">Send a new code</button></p>
        <p class="form__foot"><a href="forgot-password.php">Use a different email</a> &nbsp;·&nbsp; <a href="login.php">Back to log in</a></p>
      </form>
<?php auth_page_end();
