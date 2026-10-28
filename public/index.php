<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/cms.php';

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Complaint System | Home</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="app.css">
  <style>
       .hero{padding-top:90px!important;padding-bottom:50px!important}
    .section{padding-top:20px!important}
    .choice-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:1040px;margin:0 auto}
    .choice{display:flex;flex-direction:column;gap:10px;padding:28px 24px;background:#fff;border:1px solid #e0ddd0;border-radius:14px;text-decoration:none;color:inherit;transition:transform .12s,box-shadow .12s,border-color .12s}
    .choice:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(16,40,30,.12);border-color:#106d5a}
    .choice .step{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#106d5a}
    .choice h3{margin:0;font-size:1.3rem}
    .choice p{margin:0;color:#4a5750;line-height:1.5;flex:1}
    .choice .go{font-weight:700;color:#106d5a}
    .home-staff{text-align:center;margin-top:26px;color:#4a5750}
    .home-staff a{font-weight:700;color:#106d5a}
    @media(max-width:860px){.choice-grid{grid-template-columns:1fr}}
  </style>
</head>
<body class="home">
  <div class="nav">
    <div class="logo">Complaint System</div>
    <div class="links">
<?php if ($user): ?>
      <a class="btn-primary" href="<?= e(home_for($user)) ?>">Go to my dashboard</a>
      <a href="logout.php">Log out</a>
<?php endif; ?>
    </div>
  </div>

  <div class="hero">
    <h1>Every complaint, tracked from the moment it's filed to the moment it's closed.</h1>
    <p>Built for organizations and the people they serve. Choose where you fit.</p>
  </div>

  <div class="section">
    <div class="choice-grid">
      <a class="choice" href="select-org.php">
        <span class="step">I have a complaint</span>
        <h3>Select your organization</h3>
        <p>Students, employees, patients, customers: find your organization, log in or join as a member, and file a complaint with it.</p>
        <span class="go">Select organization →</span>
      </a>
      <a class="choice" href="register-org.php">
        <span class="step">We receive complaints</span>
        <h3>Register your organization</h3>
        <p>Submit your organization for approval. Once approved you set up your team, add your members and start receiving complaints.</p>
        <span class="go">Register organization →</span>
      </a>
      <a class="choice" href="admin-login.php">
        <span class="step">Platform administration</span>
        <h3>Admin login</h3>
        <p>Review organizations that asked to join and approve or reject them.</p>
        <span class="go">Admin login →</span>
      </a>
    </div>
    <p class="home-staff">Focal person, handler or organization admin? <a href="login.php?as=staff">Staff log in</a></p>
  </div>
</body>
</html>
