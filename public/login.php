<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/otp.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/cms.php';

if (current_user() !== null) {
    redirect(post_login_target(current_user()));
}

// Who is logging in: 'admin' (platform admin page), 'member' (picked an organization), or 'staff' (org team).
$scope = ($scope ?? '') ?: ((int_param('org') > 0) ? 'member' : 'staff');
$org = null;
if ($scope === 'member') {
    $org = org_row(int_param('org'));
    if (!$org || $org['status'] !== 'approved') {
        redirect('select-org.php');
    }
}
$self = $scope === 'admin' ? 'admin-login.php' : 'login.php';
$ctx = $scope === 'member' ? '?org=' . (int) $org['id'] : ($scope === 'staff' ? '?as=staff' : '');

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
        $stmt = db()->prepare('SELECT id, org_id, name, email, password_hash, role, is_active, email_verified_at, last_login_at FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $passwordOk = password_verify($password, $user['password_hash'] ?? DUMMY_HASH);

        // Each entry point only lets in the right kind of account, with the same message as a wrong password.
        $scopeOk = $user && (
            ($scope === 'admin'  && $user['role'] === 'platform_admin') ||
            ($scope === 'member' && $user['role'] === 'complainant' && (int) $user['org_id'] === (int) $org['id']) ||
            ($scope === 'staff'  && $user['role'] !== 'platform_admin' && $user['role'] !== 'complainant')
        );

        // A join request: member who confirmed their email but has not been approved yet.
        $pending = $user && $passwordOk && $user['role'] === 'complainant' && !(int) $user['is_active']
            && $user['last_login_at'] === null;

        if ($pending && $user['email_verified_at'] !== null) {
            $error = 'Your request to join is waiting for approval by the organization. You will be able to log in once it is approved.';
        } elseif (!$user || !$passwordOk || (!(int) $user['is_active'] && !$pending)) {
            login_record($email, $ip, false);
            $error = 'Incorrect email or password.';
        } elseif (!$scopeOk) {
            $error = match ($scope) {
                'admin'  => 'This page is for platform admins only.',
                'member' => 'This account is not a member of ' . $org['name'] . '.',
                default  => 'Members log in from the organization page. Choose your organization on the home page.',
            };
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

$titles = ['admin' => 'Admin login', 'member' => 'Log in', 'staff' => 'Staff log in'];
$subs = [
    'admin'  => 'Platform administrators only.',
    'member' => 'Member of ' . ($org['name'] ?? '') . '.',
    'staff'  => 'Organization admins, focal persons and handlers.',
];

auth_page_start($titles[$scope]);
?>
      <form class="form" method="post" action="<?= e($self . $ctx) ?>" novalidate>
        <?= csrf_field() ?>
        <h1 class="form__title--green"><?= e($titles[$scope]) ?></h1>
        <p class="form__sub form__sub--soft"><?= e($subs[$scope]) ?></p>

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

<?php if ($scope === 'member'): ?>
        <p class="form__foot">First time? <a href="register.php?org=<?= (int) $org['id'] ?>" class="link-green">Join <?= e($org['name']) ?></a></p>
        <p class="form__foot"><a href="org-portal.php?org=<?= (int) $org['id'] ?>" class="link-green">Back</a></p>
<?php elseif ($scope === 'staff'): ?>
        <p class="form__foot">New organization? <a href="register-org.php" class="link-green">Register your organization</a></p>
<?php endif; ?>
      </form>
<?php auth_page_end();