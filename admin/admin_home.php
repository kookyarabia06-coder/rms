<?php
require_once '../config/db.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') die("Login required");

$tab = $_GET['tab'] ?? 'dashboard';

// Dashboard counts
$total = $conn->query("SELECT COUNT(*) c FROM reservations")->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) c FROM reservations WHERE status='pending'")->fetch_assoc()['c'];
$approved = $conn->query("SELECT COUNT(*) c FROM reservations WHERE status='approved'")->fetch_assoc()['c'];
$rejected = $conn->query("SELECT COUNT(*) c FROM reservations WHERE status='rejected'")->fetch_assoc()['c'];
$pencil = $conn->query("SELECT COUNT(*) c FROM reservations WHERE type='pencil'")->fetch_assoc()['c'];
$today = date('Y-m-d');

// Calendar variables
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// If month is 0, go to previous year December
if ($month < 1) {
    $month = 12;
    $year--;
}
// If month is 13, go to next year January
if ($month > 12) {
    $month = 1;
    $year++;
}

$days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
$start_date = "$year-$month-01";
$end_date = "$year-$month-$days_in_month";

// Fetch all reservations for the selected month
$reservations_query = $conn->query("
    SELECT r.*, u.name as user_name 
    FROM reservations r 
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.date BETWEEN '$start_date' AND '$end_date'
    ORDER BY r.date ASC, r.time_from ASC
");

// Organize reservations by date
$reservations_by_date = [];
while ($row = $reservations_query->fetch_assoc()) {
    $date = $row['date'];
    if (!isset($reservations_by_date[$date])) {
        $reservations_by_date[$date] = [];
    }
    $reservations_by_date[$date][] = $row;
}

// Upcoming activities by venue (today and future, excluding past)
$exec_upcoming_admin = $conn->query("SELECT r.*, u.name as user_name 
    FROM reservations r 
    LEFT JOIN users u ON r.user_id=u.id
    WHERE r.date >= '$today' AND r.venue='Executive Lounge'
    ORDER BY r.date ASC, r.time_from ASC");

$aud_upcoming_admin = $conn->query("SELECT r.*, u.name as user_name 
    FROM reservations r 
    LEFT JOIN users u ON r.user_id=u.id
    WHERE r.date >= '$today' AND r.venue='Auditorium'
    ORDER BY r.date ASC, r.time_from ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Panel</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="css/admin_calendar.css">

<style>
.nav-tabs .nav-link.active {
    background-color: #007bff;
    color: #fff !important;
}

.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
}

.nav-tabs .nav-link:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
}

/* Make calendar smaller when inside new booking */
.booking-with-calendar {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.booking-form-container {
    flex: 1;
    min-width: 300px;
}

.calendar-container {
    flex: 1;
    min-width: 400px;
}

@media (max-width: 768px) {
    .booking-with-calendar {
        flex-direction: column;
    }
    .calendar-container {
        min-width: auto;
    }
}
</style>
</head>

<body class="hold-transition layout-top-nav">

<div class="wrapper">

<!-- NAVBAR -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <span class="navbar-brand"><i class="fas fa-calendar-check"></i> Reservation Admin</span>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item mr-2">
            <a href="../auth/logout.php" class="btn btn-danger btn-sm">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</nav>

<!-- CONTENT -->
<div class="content-wrapper p-3">

<!-- TABS -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php if($tab=='dashboard') echo 'active'; ?>" href="?tab=dashboard">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?php if($tab=='newbooking') echo 'active'; ?>" href="?tab=newbooking">
            <i class="fas fa-plus"></i> New Booking
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?php if($tab=='allbooking') echo 'active'; ?>" href="?tab=allbooking">
            <i class="fas fa-list"></i> All Bookings
        </a>
    </li>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content">

<?php if($tab=='dashboard'): ?>

    <h4>Dashboard</h4>

    <div class="row" align-items-stretch">
        <?php
        $cards = [
            ['Total',$total,'dark','list'],
            ['Pending',$pending,'warning','hourglass-half'],
            ['Approved',$approved,'success','check'],
            ['Rejected',$rejected,'danger','times'],
            ['Pencil Booking',$pencil,'primary','pencil-alt']
        ];
        foreach($cards as $c): ?>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="info-box bg-<?php echo $c[2]; ?>">
                <span class="info-box-icon"><i class="fas fa-<?php echo $c[3]; ?>"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?php echo $c[0]; ?></span>
                    <span class="info-box-number"><?php echo $c[1]; ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="row mb-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">Executive Lounge - Upcoming Activities</div>
            <div class="card-body">
                <?php if($exec_upcoming_admin->num_rows>0): ?>
                    <ul>
                        <?php while($row = $exec_upcoming_admin->fetch_assoc()): ?>
                            <li>
                                <?php 
                                    echo htmlspecialchars(date('M d, Y', strtotime($row['date'])) . " | " . 
                                    $row['activity_type']." | ".$row['time_from']."-".$row['time_to']." | Manager: ".$row['program_manager']." | User: ".$row['user_name']); 
                                ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No upcoming activities.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">Auditorium - Upcoming Activities</div>
            <div class="card-body">
                <?php if($aud_upcoming_admin->num_rows>0): ?>
                    <ul>
                        <?php while($row = $aud_upcoming_admin->fetch_assoc()): ?>
                            <li>
                                <?php 
                                    echo htmlspecialchars(date('M d, Y', strtotime($row['date'])) . " | " . 
                                    $row['activity_type']." | ".$row['time_from']."-".$row['time_to']." | Manager: ".$row['program_manager']." | User: ".$row['user_name']); 
                                ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No upcoming activities.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif($tab=='newbooking'): ?>

    <div class="booking-with-calendar">
        <div class="booking-form-container">
            <?php include(__DIR__ . '/../user/reservation.php'); ?>
        </div>
        
        <div class="calendar-container">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-calendar-alt"></i> Booking Calendar
                </div>
                <div class="card-body p-0">
                    <?php 
                    // Include the calendar file
                    include(__DIR__ . '/admin_calendar.php'); 
                    ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif($tab=='allbooking'): ?>

    <?php include(__DIR__ . '/approval.php'); ?>

<?php endif; ?>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// Optional: Add functionality to auto-select date from calendar click
function selectDateFromCalendar(date) {
    // If you want to automatically fill the date in the booking form when clicking on a date
    // You can implement this based on your reservation.php form structure
    console.log('Selected date:', date);
    // Example: $('#date').val(date);
}
</script>

</body>
</html>