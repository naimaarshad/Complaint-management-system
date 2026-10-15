<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/otp.php';
require __DIR__ . '/../src/auth.php';

if (current_user() !== null) {
    redirect('login-done.php');
}

$error = '';
$email = '';
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $ip       = client_ip();

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } elseif (login_is_locked($email, $ip)) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $stmt = db()->prepare('SELECT id, org_id, name, email, password_hash, role, is_active, email_verified_at FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $passwordOk = password_verify($password, $user['password_hash'] ?? DUMMY_HASH);

        if (!$user || !$passwordOk || !(int) $user['is_active']) {
            login_record($email, $ip, false);
            $error = 'Incorrect email or password.';
        } else {
            login_record($email, $ip, true);
            $userId = (int) $user['id'];

            if ($user['email_verified_at'] === null) {
                $code = otp_issue($userId, 'verify_email');
                if ($code !== null) {
                    send_mail($user['email'], 'Your Complaint System verification code', "Hello {$user['name']},\n\nYour verification code is: $code\n\nIt expires in 10 minutes.");
                }
                $_SESSION['verify_user_id'] = $userId;
                redirect('verify-email.php');
            }

            $code = otp_issue($userId, 'login');
            if ($code !== null) {
                send_mail($user['email'], 'Your Complaint System login code', "Hello {$user['name']},\n\nYour login code is: $code\n\nIt expires in 10 minutes. If this was not you, change your password.");
            }
            $_SESSION['login_user_id'] = $userId;
            redirect('login-otp.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in | Complaint System</title>
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
      <form id="loginForm" class="form" method="post" action="login.php" novalidate>
        <?= csrf_field() ?>
        <h1 class="form__title--green">Log in</h1>
        <p class="form__sub form__sub--soft">Complainants, organizations and platform admins all log in here.</p>

        <?php if ($success): ?>
          <p class="form__message success" role="status"><?= e($success) ?></p>
        <?php endif; ?>

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required>
        </div>

        <div class="<?= $error !== '' ? 'field invalid' : 'field' ?>">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
          <small class="error"><?= e($error) ?></small>
        </div>

        <a href="forgot-password.php" class="link-green">Forgot password?</a>

        <button type="submit" class="btn">Log in</button>

        <p class="form__foot">Don't have an account? <a href="register.php" class="link-green">Register</a></p>
        <p class="form__foot">New organization? <a href="register-org.php" class="link-green">Register your organization</a></p>
      </form>
    </section>

  </main>
</body>
</html>