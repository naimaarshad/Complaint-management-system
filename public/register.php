<?php require __DIR__ . '/../src/bootstrap.php'; ?>
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
</head>
<body>
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
      <form id="signupForm" class="form" novalidate>
        <h1>Create your account</h1>
        <p class="form__sub">Takes less than a minute.</p>

        <div class="role-switch">
          <a href="register.php" class="active">I'm a complainant</a>
          <a href="register-org.php">I'm an organization</a>
        </div>

        <div class="field">
          <label for="fullName">Full name</label>
          <input type="text" id="fullName" placeholder="Ali Raza" autocomplete="name">
          <small class="error" id="fullNameError"></small>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" placeholder="you@example.com" autocomplete="email">
          <small class="error" id="emailError"></small>
        </div>

        <div class="field">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" placeholder="03xx-xxxxxxx" autocomplete="tel">
          <small class="error" id="phoneError"></small>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" placeholder="At least 8 characters" autocomplete="new-password">
          <small class="error" id="passwordError"></small>
        </div>

        <button type="submit" class="btn">Create account</button>
        <p class="form__message" id="formMessage" role="status"></p>
        <p class="form__foot">Already have an account? <a href="login.php">Log in</a></p>
      </form>
    </section>

  </main>
  <script src="app.js"></script>
  <script src="script.js"></script>
</body>
</html>