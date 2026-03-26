<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if user is superadmin
if ($_SESSION['user']['role'] !== 'superadmin') {
    // If not superadmin, redirect to appropriate page
    if ($_SESSION['user']['role'] === 'admin') {
        header("Location: ../admin/admin_home.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit();
}

// Include database connection
require_once '../config/db.php';

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Hospital Reservation</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/superadmin_styles.css">
    <!-- If CSS is in same directory, use: -->
    <!-- <link rel="stylesheet" href="superadmin_styles.css"> -->
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="superadmin_home.php">
                <i class="fas fa-hospital"></i> Hospital Reservation
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="superadmin_home.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_admins.php">
                            <i class="fas fa-users"></i> Manage Admins
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/audit.php">
                            <i class="fas fa-history"></i> Audit Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="system_settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h1>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                <p>Here's what's happening with your hospital reservation system today.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <?php
                            // Get total admins count
                            $admin_count = 0;
                            $query = "SELECT COUNT(*) as total FROM users WHERE role = 'admin'";
                            $result = mysqli_query($conn, $query);
                            if($result && mysqli_num_rows($result) > 0) {
                                $row = mysqli_fetch_assoc($result);
                                $admin_count = $row['total'];
                            }
                            ?>
                            <h3><?php echo $admin_count; ?></h3>
                            <p>Total Admins</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <a href="manage_admins.php" class="small-box-footer">
                            Manage <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <?php
                            // Get total users count
                            $user_count = 0;
                            $query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
                            $result = mysqli_query($conn, $query);
                            if($result && mysqli_num_rows($result) > 0) {
                                $row = mysqli_fetch_assoc($result);
                                $user_count = $row['total'];
                            }
                            ?>
                            <h3><?php echo $user_count; ?></h3>
                            <p>Total Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <a href="#" class="small-box-footer">
                            View <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <?php
                            // Get pending reservations count
                            $pending_count = 0;
                            $query = "SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'";
                            $result = mysqli_query($conn, $query);
                            if($result && mysqli_num_rows($result) > 0) {
                                $row = mysqli_fetch_assoc($result);
                                $pending_count = $row['total'];
                            }
                            ?>
                            <h3><?php echo $pending_count; ?></h3>
                            <p>Pending Reservations</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <a href="#" class="small-box-footer">
                            View <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <?php
                            // Get total reservations count
                            $total_reservations = 0;
                            $query = "SELECT COUNT(*) as total FROM reservations";
                            $result = mysqli_query($conn, $query);
                            if($result && mysqli_num_rows($result) > 0) {
                                $row = mysqli_fetch_assoc($result);
                                $total_reservations = $row['total'];
                            }
                            ?>
                            <h3><?php echo $total_reservations; ?></h3>
                            <p>Total Reservations</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <a href="#" class="small-box-footer">
                            View <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-history mr-2"></i>
                        Recent Activities
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                32<th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch recent audit logs
                                $audit_query = "SELECT a.*, u.name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 10";
                                $audit_result = mysqli_query($conn, $audit_query);
                                
                                if($audit_result && mysqli_num_rows($audit_result) > 0) {
                                    while($log = mysqli_fetch_assoc($audit_result)) {
                                        echo "<tr>";
                                        echo "<td>" . date('Y-m-d H:i:s', strtotime($log['created_at'])) . "</td>";
                                        echo "<td>" . htmlspecialchars($log['name'] ?? 'System') . "</td>";
                                        echo "<td>" . htmlspecialchars($log['action']) . "</td>";
                                        echo "<td>" . htmlspecialchars($log['ip_address'] ?? 'N/A') . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='text-center'>No recent activities found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-bolt mr-2"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="card-body quick-actions">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="manage_admins.php" class="btn btn-info btn-block">
                                <i class="fas fa-user-plus"></i> Add New Admin
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="../admin/audit.php" class="btn btn-primary btn-block">
                                <i class="fas fa-history"></i> View All Logs
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="system_settings.php" class="btn btn-warning btn-block">
                                <i class="fas fa-cog"></i> System Settings
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="../auth/logout.php" class="btn btn-danger btn-block">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <strong>&copy; <?php echo date('Y'); ?> Hospital Reservation System.</strong> All rights reserved.
                    <div class="float-right d-none d-sm-block">
                        <b>Version</b> 1.0.0
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>