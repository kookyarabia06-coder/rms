<?php

session_start();
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

// In admin_home.php, update the reservations query to handle date ranges
$reservations_query = $conn->query("
    SELECT r.*, u.name as user_name 
    FROM reservations r 
    LEFT JOIN users u ON r.user_id = u.id
    WHERE (r.date_from BETWEEN '$start_date' AND '$end_date')
       OR (r.date_to BETWEEN '$start_date' AND '$end_date')
       OR (r.date_from <= '$start_date' AND r.date_to >= '$end_date')
    ORDER BY r.date_from ASC, r.time_from ASC
");

// Organize reservations by date - expand date ranges into individual dates
$reservations_by_date = [];
while ($row = $reservations_query->fetch_assoc()) {
    // If the reservation has date range, expand it to all dates in the range
    if (isset($row['date_from']) && isset($row['date_to']) && $row['date_from'] != $row['date_to']) {
        $current = $row['date_from'];
        $end = $row['date_to'];
        while (strtotime($current) <= strtotime($end)) {
            if (!isset($reservations_by_date[$current])) {
                $reservations_by_date[$current] = [];
            }
            // Add the same reservation to each date in the range
            $reservations_by_date[$current][] = $row;
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
        }
    } else {
        // Single date reservation
        $date_key = $row['date_from'] ?? $row['date'];
        if (!isset($reservations_by_date[$date_key])) {
            $reservations_by_date[$date_key] = [];
        }
        $reservations_by_date[$date_key][] = $row;
    }
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

/* Modal styles */
.modal-lg {
    max-width: 800px;
}

/* Remarks column styling */
.remarks-cell {
    max-width: 200px;
    word-wrap: break-word;
}

.action-buttons .btn {
    margin: 2px;
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

<!-- Display Session Messages -->
<?php if(isset($_SESSION['message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['message']; ?>
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; ?>
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

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
                <?php if($exec_upcoming_admin && $exec_upcoming_admin->num_rows>0): ?>
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
                <?php if($aud_upcoming_admin && $aud_upcoming_admin->num_rows>0): ?>
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
            <?php include(__DIR__ . '/admin_reservation.php'); ?>
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

    <?php 
    // Fetch all bookings with user details
    $all_bookings_query = $conn->query("
        SELECT r.*, u.name as user_name 
        FROM reservations r 
        LEFT JOIN users u ON r.user_id = u.id
        ORDER BY r.date_from DESC, r.time_from DESC
    ");
    
    // Check if query was successful
    if (!$all_bookings_query) {
        echo '<div class="alert alert-danger">Error fetching bookings: ' . $conn->error . '</div>';
        $all_bookings_query = null;
    }
    ?>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4><i class="fas fa-list"></i> All Bookings</h4>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Date From</th>
                        <th>Date To</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Venue</th>
                        <th>Activity Type</th>
                        <th>Program Manager</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($all_bookings_query && $all_bookings_query->num_rows > 0): ?>
                        <?php while($booking = $all_bookings_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $booking['id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['date_from'])); ?></td>
                                <td>
                                    <?php 
                                    if(isset($booking['date_to']) && !empty($booking['date_to']) && $booking['date_to'] != '0000-00-00') {
                                        echo date('M d, Y', strtotime($booking['date_to']));
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('h:i A', strtotime($booking['time_from'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($booking['time_to'])); ?></td>
                                <td><?php echo htmlspecialchars($booking['venue']); ?></td>
                                <td><?php echo htmlspecialchars($booking['activity_type']); ?></td>
                                <td><?php echo htmlspecialchars($booking['program_manager']); ?></td>
                                <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                <td>
                                    <?php 
                                    $type_badge = $booking['type'] == 'pencil' ? 'warning' : 'info';
                                    echo '<span class="badge badge-' . $type_badge . '">' . strtoupper($booking['type']) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_color = '';
                                    switch($booking['status']) {
                                        case 'approved':
                                            $status_color = 'success';
                                            break;
                                        case 'pending':
                                            $status_color = 'warning';
                                            break;
                                        case 'rejected':
                                            $status_color = 'danger';
                                            break;
                                        default:
                                            $status_color = 'secondary';
                                    }
                                    echo '<span class="badge badge-' . $status_color . '">' . strtoupper($booking['status']) . '</span>';
                                    ?>
                                </td>
                                <td class="remarks-cell">
                                    <?php 
                                    if(!empty($booking['remarks'])) {
                                        echo '<small>' . htmlspecialchars($booking['remarks']) . '</small>';
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="openEditModal(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="openRescheduleModal(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-calendar-alt"></i> Reschedule
                                    </button>
                                    <?php if($booking['status'] != 'rejected'): ?>
                                    <button class="btn btn-sm btn-danger" onclick="openRejectModal(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" class="text-center">No bookings found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Booking</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="editForm" action="process_booking.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="booking_id" id="edit_booking_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Venue *</label>
                                    <select name="venue" id="edit_venue" class="form-control" required>
                                        <option value="Executive Lounge">Executive Lounge</option>
                                        <option value="Auditorium">Auditorium</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date From *</label>
                                    <input type="date" name="date_from" id="edit_date_from" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" id="edit_date_to" class="form-control">
                                    <small class="text-muted">Leave blank if same as date from</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Time In *</label>
                                    <input type="time" name="time_from" id="edit_time_from" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Time Out *</label>
                                    <input type="time" name="time_to" id="edit_time_to" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Activity Type *</label>
                                    <input type="text" name="activity_type" id="edit_activity_type" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Program Manager *</label>
                                    <input type="text" name="program_manager" id="edit_program_manager" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Booking Type *</label>
                                    <select name="type" id="edit_type" class="form-control" required>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="pencil">Pencil Booking</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status *</label>
                                    <select name="status" id="edit_status" class="form-control" required>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea name="remarks" id="edit_remarks" class="form-control" rows="3" placeholder="Add any remarks here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Update Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Reschedule Booking</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="rescheduleForm" action="process_booking.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reschedule">
                        <input type="hidden" name="booking_id" id="reschedule_booking_id">
                        
                        <div class="form-group">
                            <label>Current Booking Details</label>
                            <div class="alert alert-info" id="current_booking_details"></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>New Date From *</label>
                                    <input type="date" name="new_date_from" id="reschedule_date_from" class="form-control" required 
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>New Date To</label>
                                    <input type="date" name="new_date_to" id="reschedule_date_to" class="form-control">
                                    <small class="text-muted">Leave blank if same as date from</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>New Time In *</label>
                                    <input type="time" name="new_time_from" id="reschedule_time_from" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>New Time Out *</label>
                                    <input type="time" name="new_time_to" id="reschedule_time_to" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Remarks (Optional)</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Reason for rescheduling..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Reschedule Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times"></i> Reject Booking</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="rejectForm" action="process_booking.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="booking_id" id="reject_booking_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Are you sure you want to reject this booking?
                        </div>
                        
                        <div class="form-group">
                            <label>Reason for Rejection *</label>
                            <textarea name="remarks" class="form-control" rows="4" required placeholder="Please provide a reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Function to open edit modal and populate data
    function openEditModal(bookingId) {
        // Fetch booking details via AJAX
        $.ajax({
            url: 'get_booking_details.php',
            method: 'POST',
            data: { id: bookingId },
            dataType: 'json',
            success: function(data) {
                if(data.success) {
                    $('#edit_booking_id').val(data.booking.id);
                    $('#edit_venue').val(data.booking.venue);
                    $('#edit_date_from').val(data.booking.date_from);
                    $('#edit_date_to').val(data.booking.date_to && data.booking.date_to != '0000-00-00' ? data.booking.date_to : '');
                    $('#edit_time_from').val(data.booking.time_from);
                    $('#edit_time_to').val(data.booking.time_to);
                    $('#edit_activity_type').val(data.booking.activity_type);
                    $('#edit_program_manager').val(data.booking.program_manager);
                    $('#edit_type').val(data.booking.type);
                    $('#edit_status').val(data.booking.status);
                    $('#edit_remarks').val(data.booking.remarks || '');
                    $('#editModal').modal('show');
                } else {
                    alert('Error fetching booking details: ' + (data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error connecting to server: ' + error);
                console.log(xhr.responseText);
            }
        });
    }
    
    // Function to open reschedule modal
    function openRescheduleModal(bookingId) {
        $.ajax({
            url: 'get_booking_details.php',
            method: 'POST',
            data: { id: bookingId },
            dataType: 'json',
            success: function(data) {
                if(data.success) {
                    $('#reschedule_booking_id').val(data.booking.id);
                    $('#current_booking_details').html(
                        '<strong>Venue:</strong> ' + data.booking.venue + '<br>' +
                        '<strong>Date From:</strong> ' + data.booking.date_from + '<br>' +
                        (data.booking.date_to && data.booking.date_to != '0000-00-00' ? '<strong>Date To:</strong> ' + data.booking.date_to + '<br>' : '') +
                        '<strong>Time In:</strong> ' + data.booking.time_from + '<br>' +
                        '<strong>Time Out:</strong> ' + data.booking.time_to + '<br>' +
                        '<strong>Activity:</strong> ' + data.booking.activity_type
                    );
                    $('#reschedule_date_from').val('');
                    $('#reschedule_date_to').val('');
                    $('#reschedule_time_from').val('');
                    $('#reschedule_time_to').val('');
                    $('#rescheduleModal').modal('show');
                } else {
                    alert('Error fetching booking details: ' + (data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error connecting to server: ' + error);
                console.log(xhr.responseText);
            }
        });
    }
    
    // Function to open reject modal
    function openRejectModal(bookingId) {
        $('#reject_booking_id').val(bookingId);
        $('#rejectModal').modal('show');
    }
    </script>


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