<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/otp.php';
require __DIR__ . '/../src/auth.php';

$userId = (int) ($_SESSION['login_user_id'] ?? 0);
if ($userId === 0) {
    redirect('login.php');
}

$stmt = db()->prepare('SELECT id, org_id, name, email, role, is_active FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !(int) $user['is_active']) {
    unset($_SESSION['login_user_id']);
    redirect('login.php');
}

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'resend') {
        $code = otp_issue($userId, 'login');
        if ($code === null) {
            $error = 'Please wait a minute before asking for another code.';
        } else {
            send_mail($user['email'], 'Your Complaint System login code', "Hello {$user['name']},\n\nYour login code is: $code\n\nIt expires in 10 minutes.");
            $notice = 'A new code has been sent.';
        }
    } else {
        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        $result = strlen($code) === 6 ? otp_verify($userId, 'login', $code) : 'format';

        if ($result === 'ok') {
            unset($_SESSION['login_user_id']);
            login_user($user);
            login_clear_failures($user['email']);
            db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $userId]);
            audit('login', 'user', $userId);
            redirect('login-done.php');
        }

        $messages = [
            'format'  => 'Enter the 6-digit code from your email.',
            'invalid' => 'That code is not correct.',
            'expired' => 'That code has expired. Request a new one.',
            'locked'  => 'Too many wrong attempts. Request a new code.',
            'none'    => 'There is no active code. Request a new one.',
        ];
        $error = $messages[$result] ?? 'Something went wrong. Try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login code | Complaint System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="app.css">
  <style>
    .home-btn {
      position: absolute;
      top: 18px;
      right: auto;
      box-shadow: none;
      transform: none;
      left: 24px;
      z-index: 50;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 18px 9px 14px;
      border: 2px solid rgba(255, 255, 255, 0.35);
      border-radius: 8px;
      background: #106d5a;
      color: #ffffff;
      font-family: inherit;
      font-size: 14px;
      font-weight: 700;
      line-height: 1;
      text-decoration: none;
      transition: background 0.15s ease, border-color 0.15s ease;
    }
    .home-btn::before {
      content: "";
      width: 16px;
      height: 16px;
      background: currentColor;
      -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 3 2 12h3v8h5v-5h4v5h5v-8h3L12 3z'/%3E%3C/svg%3E") center / contain no-repeat;
      mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 3 2 12h3v8h5v-5h4v5h5v-8h3L12 3z'/%3E%3C/svg%3E") center / contain no-repeat;
    }
    .home-btn:hover {
      background: #138a72;
      border-color: #ffffff;
      color: #ffffff;
    }
    .brand,
    .brand-panel {
      padding-top: 88px;
    }
    @media (max-width: 700px) {
      .home-btn {
        top: 14px;
        left: 14px;
      }
    }
  </style>
</head>
<body>
  <a class="home-btn" href="index.php">Home</a>
  <main class="page">

    <section class="brand">
      <h2 class="brand__name"><a href="index.php">Complaint System</a></h2>

      <div class="chips">
        <div class="chip"><span class="chip__id">CMP-2461</span><span class="chip__status t-high">High</span></div>
        <div class="chip"><span class="chip__id">CMP-2455</span><span class="chip__status t-closed">Closed</span></div>
        <div class="chip"><span class="chip__id">CMP-2470</span><span class="chip__status t-assigned">Assigned</span></div>
        <div class="chip"><span class="chip__id">CMP-2444</span><span class="chip__status t-submitted">Submitted</span></div>
        <div class="chip"><span class="chip__id">CMP-2468</span><span class="chip__status t-urgent">Urgent</span></div>
        <div class="chip"><span class="chip__id">CMP-2450</span><span class="chip__status t-closed">Closed</span></div>
      </div>

      <p class="brand__tagline">
        One place for every organization to receive, assign, and resolve complaints with nothing sitting unanswered.
      </p>
    </section>

    <section class="form-side">
      <form id="otpForm" class="form" method="post" action="login-otp.php" novalidate>
        <?= csrf_field() ?>
        <h1 class="form__title--green">Enter your login code</h1>
        <p class="form__sub form__sub--soft">We sent a 6-digit code to <strong><?= e(mask_email($user['email'])) ?></strong>. It expires in 10 minutes.</p>

        <div class="<?= $error !== '' ? 'field invalid' : 'field' ?>">
          <label for="code">Login code</label>
          <input type="text" id="code" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="123456" autocomplete="one-time-code" autofocus required style="letter-spacing: 0.4em; font-size: 1.3rem; text-align: center;">
          <small class="error"><?= e($error) ?></small>
        </div>

        <button type="submit" class="btn" name="action" value="verify">Log in</button>
        <p class="form__message success" role="status"><?= e($notice) ?></p>

        <?php if (env('APP_ENV') === 'dev'): ?>
          <p class="form__message">Dev mode: the code is in <code>storage/logs/mail.log</code>.</p>
        <?php endif; ?>

        <p class="form__foot">
          Did not get it?
          <button type="submit" name="action" value="resend" formnovalidate style="border:0;background:none;padding:0;font:inherit;font-weight:700;color:var(--green-btn);cursor:pointer;">Send a new code</button>
        </p>
        <p class="form__foot"><a class="link-green" href="login.php">Back to log in</a></p>
      </form>
    </section>

  </main>
</body>
</html>