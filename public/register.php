<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/otp.php';
require __DIR__ . '/../src/cms.php';

if (current_user() !== null) {
    redirect(home_for(current_user()));
}

// Members always join a specific organization.
$org = org_row(int_param('org'));
if (!$org || $org['status'] !== 'approved') {
    redirect('select-org.php');
}
$orgId = (int) $org['id'];
$domain = strtolower(trim((string) $org['email_domain']));

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old['name']  = post_str('name', 120);
    $old['email'] = strtolower(post_str('email', 190));
    $old['phone'] = post_str('phone', 20);
    $password     = (string) ($_POST['password'] ?? '');

    if (mb_strlen($old['name']) < 3) {
        $errors['name'] = 'Enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
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

    $existing = null;
    $instant = false;
    if (!$errors) {
        $stmt = db()->prepare('SELECT id, org_id, role, email_verified_at FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        $existing = $stmt->fetch() ?: null;

        $invited = $existing && $existing['role'] === 'complainant' && (int) $existing['org_id'] === $orgId;
        $onDomain = $domain !== '' && str_ends_with($old['email'], '@' . $domain);

        if ($existing && $existing['email_verified_at'] !== null) {
            $errors['email'] = 'An account with this email already exists. Log in instead.';
        } elseif ($existing && !$invited) {
            $errors['email'] = 'An account with this email already exists. Log in instead.';
        }
        $instant = $invited || $onDomain;
    }

    if (!$errors) {
        $pdo = db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($existing) {
            $pdo->prepare('UPDATE users SET name = ?, phone = ?, password_hash = ? WHERE id = ?')
                ->execute([$old['name'], $old['phone'], $hash, $existing['id']]);
            $userId = (int) $existing['id'];
        } else {
            $pdo->prepare('INSERT INTO users (org_id, name, email, phone, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$orgId, $old['name'], $old['email'], $old['phone'], $hash, 'complainant', $instant ? 1 : 0]);
            $userId = (int) $pdo->lastInsertId();
        }

        $code = otp_issue($userId, 'verify_email');
        if ($code !== null) {
            send_mail($old['email'], 'Your Complaint System verification code',
                "Hello {$old['name']},\n\nYour verification code is: $code\n\nIt expires in 10 minutes. If you did not create an account, ignore this email.");
        }
        $_SESSION['verify_user_id'] = $userId;
        redirect('verify-email.php');
    }
}

function fc(array $errors, string $key): string
{
    return isset($errors[$key]) ? 'field invalid' : 'field';
}

auth_page_start('Join ' . $org['name']);
?>
      <form class="form" method="post" action="register.php?org=<?= $orgId ?>" novalidate>
        <?= csrf_field() ?>
        <h1>Join <?= e($org['name']) ?></h1>
        <p class="form__sub">Create your member account. We will email you a code to confirm your address.
<?php if ($domain !== ''): ?>
          Emails ending in <strong>@<?= e($domain) ?></strong> are accepted straight away.
<?php endif; ?>
          If <?= e($org['name']) ?> has already added you, you can start right after confirming. Otherwise your request goes to the organization for approval.</p>

        <div class="<?= fc($errors, 'name') ?>">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" placeholder="Your full name" autocomplete="name" maxlength="120">
          <small class="error"><?= e($errors['name'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'email') ?>">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" placeholder="<?= $domain !== '' ? 'you@' . e($domain) : 'you@example.com' ?>" autocomplete="email" maxlength="190">
          <small class="error"><?= e($errors['email'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'phone') ?>">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="03xx-xxxxxxx" autocomplete="tel" maxlength="20">
          <small class="error"><?= e($errors['phone'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'password') ?>">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="At least 8 characters" autocomplete="new-password">
          <small class="error"><?= e($errors['password'] ?? '') ?></small>
        </div>

        <button type="submit" class="btn">Create account / request to join</button>
        <p class="form__foot">Already a member? <a href="login.php?org=<?= $orgId ?>">Log in</a></p>
        <p class="form__foot"><a href="org-portal.php?org=<?= $orgId ?>">Back</a></p>
      </form>
<?php auth_page_end();