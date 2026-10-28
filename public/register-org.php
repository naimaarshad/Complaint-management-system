<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';
require __DIR__ . '/../src/otp.php';
require __DIR__ . '/../src/cms.php';

if (current_user() !== null) {
    redirect(home_for(current_user()));
}

$errors = [];
$old = ['orgName' => '', 'sector' => '', 'city' => '', 'contactName' => '', 'email' => '', 'phone' => '', 'emailDomain' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (array_keys($old) as $k) {
        $old[$k] = in_array($k, ['email', 'emailDomain'], true) ? strtolower(post_str($k, 190)) : post_str($k, 150);
    }
    $old['emailDomain'] = ltrim($old['emailDomain'], '@ ');
    $password = (string) ($_POST['password'] ?? '');

    if (mb_strlen($old['orgName']) < 3) {
        $errors['orgName'] = 'Enter the organization name.';
    }
    if (!in_array($old['sector'], SECTORS, true)) {
        $errors['sector'] = 'Choose a sector.';
    }
    if (mb_strlen($old['city']) < 2) {
        $errors['city'] = 'Enter the city.';
    }
    if (mb_strlen($old['contactName']) < 3) {
        $errors['contactName'] = "Enter the contact person's full name.";
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($old['emailDomain'] !== '' && !valid_domain($old['emailDomain'])) {
        $errors['emailDomain'] = 'Enter a domain like northbridge.edu.pk, or leave it empty.';
    }
    if (!preg_match('/^[0-9+\-\s]{7,16}$/', $old['phone'])) {
        $errors['phone'] = 'Enter a valid phone number.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Use at most 72 characters.';
    }

    $pdo = db();
    $existingUser = null;
    $existingOrgId = null;

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id, role, org_id, email_verified_at FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        $existingUser = $stmt->fetch() ?: null;

        if ($existingUser) {
            $o = org_row($existingUser['org_id'] !== null ? (int) $existingUser['org_id'] : null);
            // An unverified, still-pending registration may be retried. Anything else is a real account.
            if ($existingUser['email_verified_at'] === null && $existingUser['role'] === 'org_admin' && $o && $o['status'] === 'pending') {
                $existingOrgId = (int) $o['id'];
            } else {
                $errors['email'] = 'An account with this email already exists. Log in instead.';
            }
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM organizations WHERE name = ?');
        $stmt->execute([$old['orgName']]);
        $sameName = $stmt->fetchColumn();
        if ($sameName !== false && (int) $sameName !== $existingOrgId) {
            $errors['orgName'] = 'An organization with this name is already registered.';
        }
    }

    $doc = null;
    if (!$errors) {
        [$doc, $docError] = save_upload('orgDoc', true);
        if ($docError !== '') {
            $errors['orgDoc'] = $docError;
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo->beginTransaction();

            if ($existingOrgId !== null) {
                $orgId = $existingOrgId;
                $pdo->prepare('UPDATE organizations SET name = ?, sector = ?, city = ?, email_domain = ? WHERE id = ?')
                    ->execute([$old['orgName'], $old['sector'], $old['city'], $old['emailDomain'] ?: null, $orgId]);
                $pdo->prepare('UPDATE users SET name = ?, phone = ?, password_hash = ? WHERE id = ?')
                    ->execute([$old['contactName'], $old['phone'], $hash, $existingUser['id']]);
                $userId = (int) $existingUser['id'];

                $stmt = $pdo->prepare('SELECT file_path FROM organization_documents WHERE org_id = ?');
                $stmt->execute([$orgId]);
                $oldFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $pdo->prepare('DELETE FROM organization_documents WHERE org_id = ?')->execute([$orgId]);
            } else {
                $oldFiles = [];
                $pdo->prepare('INSERT INTO organizations (name, sector, city, email_domain, status, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$old['orgName'], $old['sector'], $old['city'], $old['emailDomain'] ?: null, 'pending', now_utc()]);
                $orgId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO users (org_id, name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$orgId, $old['contactName'], $old['email'], $old['phone'], $hash, 'org_admin']);
                $userId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare('INSERT INTO organization_documents (org_id, file_path, original_name, mime_type, size_bytes, uploaded_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$orgId, $doc['file_path'], $doc['original_name'], $doc['mime_type'], $doc['size_bytes'], now_utc()]);

            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            delete_upload($doc['file_path']);
            error_log('register-org failed: ' . $ex->getMessage());
            $errors['form'] = 'Something went wrong. Please try again.';
        }

        if (!$errors) {
            foreach ($oldFiles as $f) {
                delete_upload($f);
            }
            $code = otp_issue($userId, 'verify_email');
            if ($code !== null) {
                send_mail($old['email'], 'Your Complaint System verification code',
                    "Hello {$old['contactName']},\n\nYour verification code is: $code\n\nIt expires in 10 minutes. After you verify, your organization goes to the platform admin for review.");
            }
            $_SESSION['verify_user_id'] = $userId;
            redirect('verify-email.php');
        }
    }
}

function fc(array $errors, string $key): string
{
    return isset($errors[$key]) ? 'field invalid' : 'field';
}

auth_page_start('Register your organization');
?>
      <form class="form" method="post" action="register-org.php" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <h1>Register your organization</h1>
        <p class="form__sub">We review every organization before it can receive complaints. Approval usually takes under 2 business days.</p>

        <div class="role-switch">
          <a href="select-org.php">I'm a member</a>
          <a href="register-org.php" class="active">I'm an organization</a>
        </div>

        <div class="<?= fc($errors, 'orgName') ?>">
          <label for="orgName">Organization name</label>
          <input type="text" id="orgName" name="orgName" value="<?= e($old['orgName']) ?>" placeholder="Riverside General Hospital" autocomplete="organization" maxlength="150">
          <small class="error"><?= e($errors['orgName'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'sector') ?>">
          <label for="sector">Sector</label>
          <select id="sector" name="sector">
            <option value="">Choose a sector</option>
<?php foreach (SECTORS as $s): ?>
            <option<?= $old['sector'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
<?php endforeach; ?>
          </select>
          <small class="error"><?= e($errors['sector'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'city') ?>">
          <label for="city">City</label>
          <input type="text" id="city" name="city" value="<?= e($old['city']) ?>" placeholder="Rawalpindi" autocomplete="address-level2" maxlength="80">
          <small class="error"><?= e($errors['city'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'contactName') ?>">
          <label for="contactName">Contact person</label>
          <input type="text" id="contactName" name="contactName" value="<?= e($old['contactName']) ?>" placeholder="Full name" autocomplete="name" maxlength="120">
          <small class="error"><?= e($errors['contactName'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'email') ?>">
          <label for="email">Official email</label>
          <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" placeholder="complaints@organization.com" autocomplete="email" maxlength="190">
          <small class="error"><?= e($errors['email'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'phone') ?>">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="051-1234567 or 03xx-xxxxxxx" autocomplete="tel" maxlength="16">
          <small class="error"><?= e($errors['phone'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'emailDomain') ?>">
          <label for="emailDomain">Member email domain (optional)</label>
          <input type="text" id="emailDomain" name="emailDomain" value="<?= e($old['emailDomain']) ?>" placeholder="northbridge.edu.pk" maxlength="190">
          <small class="error"><?= e($errors['emailDomain'] ?? '') ?></small>
          <small>If your members have emails on one domain, they can join on their own. Otherwise you add them by email later.</small>
        </div>

        <div class="<?= fc($errors, 'orgDoc') ?>">
          <label for="orgDoc">Registration or authorization document</label>
          <label class="upload-box" for="orgDoc" id="docLabel">Click to attach a PDF or image (max 5 MB)
            <input type="file" id="orgDoc" name="orgDoc" accept=".pdf,.png,.jpg,.jpeg">
          </label>
          <small class="error"><?= e($errors['orgDoc'] ?? '') ?></small>
        </div>

        <div class="<?= fc($errors, 'password') ?>">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="At least 8 characters" autocomplete="new-password">
          <small class="error"><?= e($errors['password'] ?? '') ?></small>
        </div>

        <button type="submit" class="btn">Submit for approval</button>
        <p class="form__message" role="status" style="color:#b3261e"><?= e($errors['form'] ?? '') ?></p>
        <p class="form__foot">Already registered? <a href="login.php?as=staff">Log in</a></p>
      </form>
      <script>
        document.getElementById('orgDoc').addEventListener('change', function () {
          var f = this.files[0];
          document.getElementById('docLabel').firstChild.textContent = f ? 'Attached: ' + f.name : 'Click to attach a PDF or image (max 5 MB)';
        });
      </script>
<?php auth_page_end();
