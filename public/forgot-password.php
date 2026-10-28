<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/otp.php';
require __DIR__ . '/../src/cms.php';

if (current_user() !== null) {
    redirect(home_for(current_user()));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(post_str('email', 190));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 AND email_verified_at IS NOT NULL');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $code = otp_issue((int) $user['id'], 'password_reset');
            if ($code !== null) {
                send_mail($user['email'], 'Your Complaint System password reset code',
                    "Hello {$user['name']},\n\nYour password reset code is: $code\n\nIt expires in 10 minutes. If you did not ask for this, ignore this email; your password has not changed.");
            }
        }
        // Same answer whether or not the account exists, so this page cannot be used to find accounts.
        $_SESSION['reset_email'] = $email;
        redirect('reset-password.php');
    }
}

auth_page_start('Reset your password');
?>
      <form class="form" method="post" action="forgot-password.php" novalidate>
        <?= csrf_field() ?>
        <h1>Reset your password</h1>
        <p class="form__sub">Enter the email on your account. We will send a 6-digit code.</p>

        <div class="<?= $error !== '' ? 'field invalid' : 'field' ?>">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" autofocus required>
          <small class="error"><?= e($error) ?></small>
        </div>

        <button type="submit" class="btn">Send reset code</button>
        <p class="form__foot"><a href="login.php">Back to log in</a></p>
      </form>
<?php auth_page_end();
