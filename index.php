<?php
session_start();

// IF LOGGED IN → REDIRECT BASED ON ROLE
if(isset($_SESSION['user'])){
    $role = $_SESSION['user']['role'];

    if($role == 'admin'){
        header("Location: admin/approval.php");
    } elseif($role == 'superadmin'){
        header("Location: superadmin/audit.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Hospital Reservation System</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
<style>
body { background-color: #f4f6f9; }
.login-box { width: 400px; margin: 80px auto; }
.login-card-body { padding: 30px; }
</style>
</head>
<body class="hold-transition login-page">

<div class="login-box">
  <div class="card">
    <div class="card-body login-card-body">
      <h4 class="text-center mb-3">Hospital Reservation System</h4>
      <p class="login-box-msg">Sign in to start your session</p>

      <form action="auth/login.php" method="post">
        <div class="input-group mb-3">
          <input type="text" name="username" class="form-control" placeholder="Username" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-user"></span></div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Login</button>
      </form>

      <p class="mt-3 mb-0 text-center">
        <a href="auth/register.php">Register a new account</a>
      </p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/js/all.min.js"></script>
</body>
</html>