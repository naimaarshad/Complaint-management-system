<?php
require __DIR__ . '/../src/bootstrap.php';

logout_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logging out</title>
</head>
<body>  
  <script>
    try { localStorage.removeItem("currentUser"); } catch (e) {}
    window.location.replace("login.php");
  </script>
  <noscript><a href="login.php">Continue to log in</a></noscript>
</body>
</html>