<?php
include '../config/db.php';

// If already logged in → redirect
if(isset($_SESSION['user'])){
    $role = $_SESSION['user']['role'];
    if($role=='admin') header("Location: ../admin/approval.php");
    elseif($role=='superadmin') header("Location: ../superadmin/audit.php");
    else header("Location: ../user/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - Hospital Reservation</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
<style>
body { background-color: #f4f6f9; }
.register-box { width: 450px; margin: 60px auto; }
.register-card-body { padding: 30px; }
</style>
</head>
<body class="hold-transition register-page">

<div class="register-box">
  <div class="card">
    <div class="card-body register-card-body">
      <h4 class="text-center mb-3">Create New Account</h4>
      <form method="POST">
        <div class="input-group mb-3">
          <input type="text" name="name" class="form-control" placeholder="Full Name" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-user"></span></div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="text" name="username" class="form-control" placeholder="Username" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-user-circle"></span></div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>
        <div class="input-group mb-3">
          <select name="role" class="form-control" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
            <option value="superadmin">Super Admin</option>
          </select>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-user-shield"></span></div>
          </div>
        </div>
        <button type="submit" name="register" class="btn btn-success btn-block">Register</button>
      </form>
      <p class="mt-3 mb-0 text-center">
        <a href="login.php">Already have an account? Login</a>
      </p>

      <?php
      if(isset($_POST['register'])){
          $name = $_POST['name'];
          $username = $_POST['username'];
          $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
          $role = $_POST['role'];

          $check = $conn->prepare("SELECT id FROM users WHERE username=?");
          $check->bind_param("s", $username);
          $check->execute();
          $check->store_result();
          if($check->num_rows > 0){
              echo "<p class='text-danger mt-2 text-center'>Username already exists!</p>";
          } else {
              $stmt = $conn->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,?)");
              $stmt->bind_param("ssss",$name,$username,$password,$role);
              $stmt->execute();
              echo "<p class='text-success mt-2 text-center'>Registration successful! <a href='login.php'>Login here</a></p>";
          }
      }
      ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/js/all.min.js"></script>
</body>
</html>