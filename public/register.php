<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/otp.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old['name']  = trim((string) ($_POST['name'] ?? ''));
    $old['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $old['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $password     = (string) ($_POST['password'] ?? '');

    if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > 120) {
        $errors['name'] = 'Enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 190) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^03\d{2}-?\d{7}$/', $old['phone'])) {
        $errors['phone'] = 'Use the format 03xx-xxxxxxx.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Use at most 72 characters.';
    }

    if (!$errors) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, role, email_verified_at FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        $existing = $stmt->fetch();

        if ($existing && ($existing['email_verified_at'] !== null || $existing['role'] !== 'complainant')) {
            $errors['email'] = 'An account with this email already exists. Log in instead.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            if ($existing) {
                $pdo->prepare('UPDATE users SET name = ?, phone = ?, password_hash = ? WHERE id = ?')
                    ->execute([$old['name'], $old['phone'], $hash, $existing['id']]);
                $userId = (int) $existing['id'];
            } else {
                $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$old['name'], $old['email'], $old['phone'], $hash, 'complainant']);
                $userId = (int) $pdo->lastInsertId();
            }

            $code = otp_issue($userId, 'verify_email');
            if ($code !== null) {
                send_mail(
                    $old['email'],
                    'Your Complaint System verification code',
                    "Hello {$old['name']},\n\nYour verification code is: $code\n\nIt expires in 10 minutes. If you did not create an account, ignore this email."
                );
            }

            $_SESSION['verify_user_id'] = $userId;
            redirect('verify-email.php');
        }
    }
}

function field_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? 'field invalid' : 'field';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create account | Complaint System</title>
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

    <!-- LEFT: brand panel -->
    <section class="brand">
      <h2 class="brand__name"><a href="index.php">Complaint System</a></h2>

      <div class="chips">
  <div class="chip"><span class="chip__id">CMP-2461</span><span class="chip__status t-high">High</span></div>
  <div class="chip"><span class="chip__id">CMP-2455</span><span class="chip__status  t-closed">Closed</span></div>
  <div class="chip"><span class="chip__id">CMP-2470</span><span class="chip__status t-assigned">Assigned</span></div>
  <div class="chip"><span class="chip__id">CMP-2444</span><span class="chip__status t-submitted">Submitted</span></div>
  <div class="chip"><span class="chip__id">CMP-2468</span><span class="chip__status t-urgent">Urgent</span></div>
  <div class="chip"><span class="chip__id">CMP-2450</span><span class="chip__status t-closed">Closed</span></div>
</div>

      

      <p class="brand__tagline">
        One place for every organization to receive, assign, and resolve complaints  with nothing sitting unanswered.
      </p>
    </section>

    <!-- RIGHT: form -->
    <section class="form-side">
      <form id="signupForm" class="form" method="post" action="register.php" novalidate>
        <?= csrf_field() ?>
        <h1>Create your account</h1>
        <p class="form__sub">Takes less than a minute.</p>

        <div class="role-switch">
          <a href="register.php" class="active">I'm a complainant</a>
          <a href="register-org.php">I'm an organization</a>
        </div>

        <div class="<?= field_class($errors, 'name') ?>">
          <label for="fullName">Full name</label>
          <input type="text" id="fullName" name="name" value="<?= e($old['name']) ?>" placeholder="Ali Raza" autocomplete="name" required>
          <small class="error" id="fullNameError"><?= e($errors['name'] ?? '') ?></small>
        </div>

        <div class="<?= field_class($errors, 'email') ?>">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" placeholder="you@example.com" autocomplete="email" required>
          <small class="error" id="emailError"><?= e($errors['email'] ?? '') ?></small>
        </div>

        <div class="<?= field_class($errors, 'phone') ?>">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="03xx-xxxxxxx" autocomplete="tel" required>
          <small class="error" id="phoneError"><?= e($errors['phone'] ?? '') ?></small>
        </div>

        <div class="<?= field_class($errors, 'password') ?>">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" required>
          <small class="error" id="passwordError"><?= e($errors['password'] ?? '') ?></small>
        </div>

        <button type="submit" class="btn">Create account</button>
        <p class="form__foot">Already have an account? <a href="login.php">Log in</a></p>
      </form>
    </section>

  </main>
</body>
</html>