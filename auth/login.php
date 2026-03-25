<?php

include '../config/db.php'; // Your database connection

// If already logged in → redirect based on role
if(isset($_SESSION['user'])){
    $role = $_SESSION['user']['role'];
    if($role=='admin') header("Location: ../admin/admin_home.php");
    elseif($role=='superadmin') header("Location: ../admin/admin_home.php");
    else header("Location: ../user/dashboard.php");
    exit;
}

// Handle login form submission
$message = '';
if(isset($_POST['login'])){
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, username, password, role FROM users WHERE username=?");
    $stmt->bind_param("s",$username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $user = $result->fetch_assoc();
        if(password_verify($password,$user['password'])){
            // Set session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role']
            ];

            logAction($conn, $user['id'], "Logged in");

            // Redirect based on role
            if($user['role']=='admin' || $user['role']=='superadmin')
                header("Location: ../admin/admin_home.php");
            else
                header("Location: ../user/dashboard.php");
            exit;

        } else {
            $message = "❌ Incorrect password!";
        }
    } else {
        $message = "❌ Username not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - Hospital Reservation</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
<style>
body { background-color: #f4f6f9; }
.login-box { width: 400px; margin: 80px auto; }
.login-card-body { padding: 30px; }
.input-group-text { background-color: #e9ecef; }
</style>
</head>
<body class="hold-transition login-page">

<div class="login-box">
  <div class="card shadow-sm">
    <div class="card-body login-card-body">
      <h4 class="text-center mb-3"><i class="fas fa-hospital"></i> Hospital Reservation</h4>
      <p class="login-box-msg">Sign in to start your session</p>

      <!-- Show error message -->
      <?php if($message): ?>
        <div class="alert alert-danger text-center">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $message; ?>
        </div>
      <?php endif; ?>

      <form action="" method="post">
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
        <button type="submit" name="login" class="btn btn-primary btn-block"><i class="fas fa-sign-in-alt"></i> Login</button>
      </form>

      <p class="mt-3 mb-0 text-center">
        <a href="register.php">Don't have an account? Register here</a>
      </p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/js/all.min.js"></script>
</body>
</html>