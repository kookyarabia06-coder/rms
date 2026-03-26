<?php
// Start session at the very beginning
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    die("Login required. <a href='../auth/login.php'>Click here to login</a>");
}

require_once '../config/db.php';

$user = $_SESSION['user'];
$uid = $user['id'];
$isEdit = false;
$message = '';
$message_type = '';
$redirect = false;

// Get venues for dropdown
$venues = ['Executive Lounge', 'Auditorium'];

// Initialize variables
$venue = '';
$activity_type = '';
$program_manager = '';
$date_from = '';
$date_to = '';
$time_from = '';
$time_to = '';
$remarks = '';
$type = 'pencil';
$status = 'pending';
$reservation_id = '';

// ================= EDIT LOAD =================
if(isset($_GET['edit_id'])){
    $id = (int)$_GET['edit_id'];
    $isEdit = true;
    
    $result = $conn->query("SELECT * FROM reservations WHERE id = $id");
    if (!$result) {
        die("Error loading reservation: " . $conn->error);
    }
    
    $edit = $result->fetch_assoc();
    
    if (!$edit) {
        die("Reservation not found");
    }

    // Check if user owns this reservation
    if ($edit['user_id'] != $uid) {
        die("You don't have permission to edit this reservation.");
    }

    // Assign values
    $venue = $edit['venue'];
    $activity_type = $edit['activity_type'];
    $program_manager = $edit['program_manager'];
    $date_from = $edit['date_from'];
    $date_to = $edit['date_to'];
    $time_from = $edit['time_from'];
    $time_to = $edit['time_to'];
    $remarks = $edit['remarks'];
    $type = $edit['type'];
    $status = $edit['status'];
    $reservation_id = $id;
}

// ================= SAVE =================
if(isset($_POST['reserve'])){
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $activity_type = mysqli_real_escape_string($conn, $_POST['activity_type']);
    $program_manager = mysqli_real_escape_string($conn, $_POST['program_manager']);
    $date_from = mysqli_real_escape_string($conn, $_POST['date_from']);
    $date_to = mysqli_real_escape_string($conn, $_POST['date_to']);
    $time_from = mysqli_real_escape_string($conn, $_POST['time_from']);
    $time_to = mysqli_real_escape_string($conn, $_POST['time_to']);
    $remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($conn, $_POST['remarks']) : '';
    $type = 'pencil';
    $status = 'pending';
    
    // Validation
    if (strtotime($date_from) < strtotime(date('Y-m-d'))) {
        $message = "Start date cannot be in the past!";
        $message_type = "danger";
    }
    elseif (strtotime($date_to) < strtotime($date_from)) {
        $message = "End date must be after or equal to start date!";
        $message_type = "danger";
    }
    elseif ($time_from >= $time_to) {
        $message = "End time must be after start time!";
        $message_type = "danger";
    }
    else {
        // Check availability
        $all_available = true;
        $current_date = $date_from;
        $conflict_date = '';
        
        while (strtotime($current_date) <= strtotime($date_to)) {
            if (!isAvailable($conn, $venue, $current_date, $current_date, $time_from, $time_to)) {
                $all_available = false;
                $conflict_date = $current_date;
                break;
            }
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        if ($all_available) {
            if ($isEdit && isset($_POST['reservation_id'])) {
                // ===== UPDATE MODE =====
                $id = (int)$_POST['reservation_id'];
                
                $sql = "UPDATE reservations SET 
                            venue = ?, 
                            activity_type = ?,
                            program_manager = ?,
                            date_from = ?, 
                            date_to = ?,
                            time_from = ?, 
                            time_to = ?,
                            remarks = ?
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                
                if ($stmt === false) {
                    $message = "Prepare failed: " . $conn->error;
                    $message_type = "danger";
                } else {
                    $stmt->bind_param("ssssssssi", $venue, $activity_type, $program_manager, $date_from, $date_to, $time_from, $time_to, $remarks, $id);
                    
                    if ($stmt->execute()) {
                        $message = "Reservation updated successfully!";
                        $message_type = "success";
                        $redirect = true;
                        
                        if (function_exists('logAction')) {
                            logAction($conn, $uid, "Updated reservation ID $id");
                        }
                    } else {
                        $message = "Error: " . $stmt->error;
                        $message_type = "danger";
                    }
                    $stmt->close();
                }
            } else {
                // ===== CREATE MODE =====
                $sql = "INSERT INTO reservations (user_id, venue, activity_type, program_manager, date_from, date_to, time_from, time_to, status, type, created_at, remarks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                
                $stmt = $conn->prepare($sql);
                
                if ($stmt === false) {
                    $message = "Prepare failed: " . $conn->error;
                    $message_type = "danger";
                } else {
                    $stmt->bind_param("issssssssss", $uid, $venue, $activity_type, $program_manager, $date_from, $date_to, $time_from, $time_to, $status, $type, $remarks);
                    
                    if ($stmt->execute()) {
                        $date_range_text = ($date_from == $date_to) ? date('F d, Y', strtotime($date_from)) : date('F d, Y', strtotime($date_from)) . " to " . date('F d, Y', strtotime($date_to));
                        $message = "Reservation submitted successfully! Date: $date_range_text, Time: $time_from - $time_to. Status: Pending";
                        $message_type = "success";
                        $redirect = true;
                        
                        if (function_exists('logAction')) {
                            logAction($conn, $uid, "Created a new $type booking for $venue from $date_from to $date_to");
                        }
                        
                        // Clear form after successful submission
                        $_POST = array();
                        $venue = $activity_type = $program_manager = $date_from = $date_to = $time_from = $time_to = $remarks = '';
                    } else {
                        $message = "Error: " . $stmt->error;
                        $message_type = "danger";
                    }
                    $stmt->close();
                }
            }
        } else {
            $message = "Sorry, time slot on $conflict_date is already booked. Please choose a different time or date range.";
            $message_type = "warning";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $isEdit ? 'Edit Reservation' : 'Create Reservation'; ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
<style>
    .date-range-group {
        display: flex;
        gap: 10px;
    }
    .date-range-group .form-group {
        flex: 1;
    }
    .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        border: 1px solid #f5c6cb;
    }
    .info-text {
        font-size: 0.85em;
        color: #6c757d;
        margin-top: 5px;
    }
</style>
</head>

<body class="hold-transition sidebar-mini">

<div class="wrapper">

<nav class="main-header navbar navbar-white navbar-light">
    <div class="container-fluid">
        <span class="navbar-brand">
            <i class="fas fa-calendar-plus"></i> 
            <?php echo $isEdit ? 'Edit Reservation' : 'Create New Reservation'; ?>
        </span>
        <a href="<?php echo $_SESSION['user']['role']=='admin' ? '../admin/admin_home.php' : 'dashboard.php'; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</nav>

<div class="content-wrapper p-3">

<h3><?php echo $isEdit ? 'Edit Reservation' : 'Create New Reservation'; ?></h3>

<?php if($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
        <?php echo $message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<div class="card">
<div class="card-header bg-primary text-white">
    <i class="fas fa-info-circle"></i> Reservation Details
</div>
<div class="card-body">

<form method="POST" id="reservationForm">

<?php if($isEdit): ?>
<input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
<?php endif; ?>

<div class="form-group">
    <label><i class="fas fa-building"></i> Venue <span class="text-danger">*</span></label>
    <select name="venue" class="form-control" required>
        <option value="">Select Venue</option>
        <option value="Executive Lounge" <?php if(isset($venue) && $venue=='Executive Lounge') echo 'selected'; ?>>Executive Lounge</option>
        <option value="Auditorium" <?php if(isset($venue) && $venue=='Auditorium') echo 'selected'; ?>>Auditorium</option>
    </select>
</div>

<div class="form-group">
    <label><i class="fas fa-tasks"></i> Activity Type <span class="text-danger">*</span></label>
    <input type="text" name="activity_type" class="form-control" 
           value="<?php echo htmlspecialchars($activity_type ?? ''); ?>" 
           placeholder="e.g., Seminar, Workshop, Meeting, Conference"
           required>
</div>

<div class="form-group">
    <label><i class="fas fa-user-tie"></i> Program Manager <span class="text-danger">*</span></label>
    <input type="text" name="program_manager" class="form-control" 
           value="<?php echo htmlspecialchars($program_manager ?? ''); ?>" 
           placeholder="Full name of program manager"
           required>
</div>

<div class="form-group">
    <label><i class="fas fa-calendar-alt"></i> Date Range <span class="text-danger">*</span></label>
    <div class="date-range-group">
        <div>
            <input type="date" name="date_from" class="form-control" 
                   value="<?php echo $date_from ?? ''; ?>" 
                   min="<?php echo date('Y-m-d'); ?>"
                   required>
            <small class="info-text">Start Date</small>
        </div>
        <div>
            <input type="date" name="date_to" class="form-control" 
                   value="<?php echo $date_to ?? ''; ?>" 
                   min="<?php echo date('Y-m-d'); ?>"
                   required>
            <small class="info-text">End Date</small>
        </div>
    </div>
    <small class="form-text text-muted">
        <i class="fas fa-info-circle"></i> For single day events, select the same date for both fields.
    </small>
</div>

<div class="form-group">
    <label><i class="fas fa-clock"></i> Time Range <span class="text-danger">*</span></label>
    <div class="date-range-group">
        <div>
            <input type="time" name="time_from" class="form-control" 
                   value="<?php echo $time_from ?? ''; ?>" 
                   required>
            <small class="info-text">Start Time</small>
        </div>
        <div>
            <input type="time" name="time_to" class="form-control" 
                   value="<?php echo $time_to ?? ''; ?>" 
                   required>
            <small class="info-text">End Time</small>
        </div>
    </div>
</div>

<div class="form-group">
    <label><i class="fas fa-comment"></i> Remarks (Optional)</label>
    <textarea name="remarks" class="form-control" rows="3" 
              placeholder="Any additional notes or special requests..."><?php echo htmlspecialchars($remarks ?? ''); ?></textarea>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> 
    <strong>Note:</strong> This is a <strong>pencil booking</strong>. The reservation will be pending until approved by an administrator.
</div>

<div class="form-group">
    <button type="submit" name="reserve" class="btn btn-primary btn-lg">
        <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update Reservation' : 'Submit Reservation'; ?>
    </button>
    <a href="dashboard.php" class="btn btn-secondary btn-lg">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

</div>
</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    // Validate that end date is not before start date
    $('input[name="date_to"]').change(function() {
        var dateFrom = $('input[name="date_from"]').val();
        var dateTo = $(this).val();
        
        if (dateFrom && dateTo && dateTo < dateFrom) {
            alert('End date cannot be before start date');
            $(this).val(dateFrom);
        }
    });
    
    $('input[name="date_from"]').change(function() {
        var dateFrom = $(this).val();
        var dateTo = $('input[name="date_to"]').val();
        
        if (dateFrom && dateTo && dateTo < dateFrom) {
            $('input[name="date_to"]').val(dateFrom);
        }
        
        // Set min date for date_to
        $('input[name="date_to"]').attr('min', dateFrom);
    });
    
    // Validate that end time is after start time
    $('input[name="time_to"]').change(function() {
        var timeFrom = $('input[name="time_from"]').val();
        var timeTo = $(this).val();
        
        if (timeFrom && timeTo && timeTo <= timeFrom) {
            alert('End time must be after start time');
            $(this).val('');
        }
    });
    
    $('input[name="time_from"]').change(function() {
        var timeFrom = $(this).val();
        var timeTo = $('input[name="time_to"]').val();
        
        if (timeFrom && timeTo && timeTo <= timeFrom) {
            $('input[name="time_to"]').val('');
        }
    });
    
    <?php if($redirect && $message_type == 'success'): ?>
    // Auto redirect to dashboard after 2 seconds on success
    setTimeout(function() {
        window.location.href = "<?php echo $_SESSION['user']['role']=='admin' ? '../admin/admin_home.php' : 'dashboard.php'; ?>";
    }, 2000);
    <?php endif; ?>
});
</script>

</body>
</html>