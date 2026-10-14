<?php require __DIR__ . '/../src/bootstrap.php'; ?>
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
</head>
<body>
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
      <form id="loginForm" class="form" novalidate>
        <h1 class="form__title--green" id="loginTitle">Log in</h1>
        <p class="form__sub form__sub--soft" id="loginSub">Log in to file and track your complaints.</p>

        <div class="role-switch" id="roleSwitch">
          <button type="button" data-role="user" class="active">I'm a complainant</button>
          <button type="button" data-role="org">I'm an organization</button>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" placeholder="you@example.com" autocomplete="email">
          <small class="error" id="emailError"></small>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" placeholder="Your password" autocomplete="current-password">
          <small class="error" id="passwordError"></small>
        </div>

        <a href="forgot-password.php" class="link-green" id="forgotLink">Forgot password?</a>

        <button type="submit" class="btn" id="loginBtn">Log in</button>
        <p class="form__message" id="formMessage" role="status"></p>

        <p class="form__foot" id="loginFoot"></p>

        <div class="hint-box">
          <strong>Demo accounts (prototype only)</strong>
          Complainant: user@demo.com / password123<br>
          Organization: org@riverside.com / password123<br>
          Platform admin: <a href="login.php?role=admin" id="adminLink">log in here</a> (admin@cms.com / admin12345)
        </div>
      </form>
    </section>

  </main>
  <script src="app.js"></script>
  <script src="login.js"></script>
</body>
</html>
