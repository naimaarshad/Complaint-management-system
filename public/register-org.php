<?php require __DIR__ . "/../src/bootstrap.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register your organization | Complaint System</title>
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
      <form id="orgForm" class="form" novalidate>
        <h1>Register your organization</h1>
        <p class="form__sub">We review every organization before it can receive complaints. Approval usually takes under 2 business days.</p>

        <div class="role-switch">
          <a href="register.php">I'm a complainant</a>
          <a href="register-org.php" class="active">I'm an organization</a>
        </div>

        <div class="field">
          <label for="orgName">Organization name</label>
          <input type="text" id="orgName" placeholder="Riverside General Hospital" autocomplete="organization">
          <small class="error" id="orgNameError"></small>
        </div>

        <div class="field">
          <label for="sector">Sector</label>
          <select id="sector">
            <option value="">Choose a sector</option>
            <option>Healthcare</option>
            <option>Education</option>
            <option>Government</option>
            <option>Banking and finance</option>
            <option>Telecom and utilities</option>
            <option>Retail and services</option>
            <option>Other</option>
          </select>
          <small class="error" id="sectorError"></small>
        </div>

        <div class="field">
          <label for="city">City</label>
          <input type="text" id="city" placeholder="Rawalpindi" autocomplete="address-level2">
          <small class="error" id="cityError"></small>
        </div>

        <div class="field">
          <label for="contactName">Contact person</label>
          <input type="text" id="contactName" placeholder="Full name" autocomplete="name">
          <small class="error" id="contactNameError"></small>
        </div>

        <div class="field">
          <label for="email">Official email</label>
          <input type="email" id="email" placeholder="complaints@organization.com" autocomplete="email">
          <small class="error" id="emailError"></small>
        </div>

        <div class="field">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" placeholder="051-1234567 or 03xx-xxxxxxx" autocomplete="tel">
          <small class="error" id="phoneError"></small>
        </div>

        <div class="field">
          <label for="orgDoc">Registration or authorization document</label>
          <label class="upload-box" for="orgDoc" id="docLabel">Click to attach a PDF or image
            <input type="file" id="orgDoc" accept=".pdf,.png,.jpg,.jpeg">
          </label>
          <small class="error" id="orgDocError"></small>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" placeholder="At least 8 characters" autocomplete="new-password">
          <small class="error" id="passwordError"></small>
        </div>

        <button type="submit" class="btn">Submit for approval</button>
        <p class="form__message" id="formMessage" role="status"></p>
        <p class="form__foot">Already registered? <a href="login.php?role=org">Log in</a></p>
      </form>
    </section>

  </main>
  <script src="app.js?v=2"></script>
  <script src="register-org.js?v=3"></script>
</body>
</html>