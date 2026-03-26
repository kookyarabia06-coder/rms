<?php
session_start();

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Include database connection if needed
// include 'config/db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            transition: 0.3s;
        }
        .sidebar a:hover {
            background: #34495e;
        }
        .sidebar a.active {
            background: #3498db;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            color: white;
        }
        .stat-card i {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .bg-primary-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }
        .bg-warning-gradient {
            background: linear-gradient(135deg, #fad0c4 0%, #ffd1ff 100%);
        }
        .bg-info-gradient {
            background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="text-center py-4">
                    <h4>Super Admin</h4>
                    <p>Welcome, <?php echo $_SESSION['username'] ?? 'Admin'; ?></p>
                </div>
                <nav>
                    <a href="superadmin_home.php" class="active">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a href="admin_management.php">
                        <i class="fas fa-users me-2"></i> Manage Admins
                    </a>
                    <a href="audit.php">
                        <i class="fas fa-history me-2"></i> Audit Logs
                    </a>
                    <a href="system_settings.php">
                        <i class="fas fa-cog me-2"></i> System Settings
                    </a>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Dashboard Overview</h2>
                    <div>
                        <span class="badge bg-info">Last login: <?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary-gradient">
                            <i class="fas fa-user-shield"></i>
                            <h3>5</h3>
                            <p>Total Admins</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success-gradient">
                            <i class="fas fa-chart-line"></i>
                            <h3>127</h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning-gradient">
                            <i class="fas fa-clock"></i>
                            <h3>24</h3>
                            <p>Pending Actions</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info-gradient">
                            <i class="fas fa-eye"></i>
                            <h3>1,234</h3>
                            <p>Total Views</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>2024-01-15 10:30:00</td>
                                        <td>Admin User</td>
                                        <td>Created new admin account</td>
                                        <td>192.168.1.1</td>
                                    </tr>
                                    <tr>
                                        <td>2024-01-15 09:15:00</td>
                                        <td>System</td>
                                        <td>Updated system settings</td>
                                        <td>127.0.0.1</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>