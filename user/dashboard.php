<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'user') {
    die("Login required");
}

$uid = (int)$_SESSION['user']['id'];
$today = date('Y-m-d');

// Function to safely execute queries and get count
function getCount($conn, $query, $errorMessage = "Query failed") {
    $result = $conn->query($query);
    if ($result === false) {
        error_log($errorMessage . ": " . $conn->error);
        return 0;
    }
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row ? (int)$row['cnt'] : 0;
    }
    return 0;
}

// Dashboard counts with corrected column names
// For today: Check if today's date falls within date_from and date_to range
$today_count = getCount($conn, "SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND '$today' BETWEEN date_from AND date_to", "Today count query failed");
$upcoming_count = getCount($conn, "SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND date_from > '$today'", "Upcoming count query failed");
$pencil_count = getCount($conn, "SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND type='pencil'", "Pencil count query failed");
$confirmed_count = getCount($conn, "SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND status='approved'", "Confirmed count query failed");
$pending_count = getCount($conn, "SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND status='pending'", "Pending count query failed");
$total_count = getCount($conn, "SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid", "Total count query failed");

// Today's activities by venue
// Check if today's date falls within the reservation period
$exec_today = $conn->query("SELECT * FROM reservations WHERE user_id=$uid AND '$today' BETWEEN date_from AND date_to AND venue='Executive Lounge' ORDER BY time_from ASC");
if ($exec_today === false) {
    error_log("Executive Lounge query failed: " . $conn->error);
    $exec_today = false;
}

$aud_today = $conn->query("SELECT * FROM reservations WHERE user_id=$uid AND '$today' BETWEEN date_from AND date_to AND venue='Auditorium' ORDER BY time_from ASC");
if ($aud_today === false) {
    error_log("Auditorium query failed: " . $conn->error);
    $aud_today = false;
}

// All reservations for table
$reservations = $conn->query("SELECT * FROM reservations WHERE user_id=$uid ORDER BY date_from DESC, time_from ASC");
if ($reservations === false) {
    error_log("Reservations query failed: " . $conn->error);
    $reservations = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
<style>
    .info-box .info-box-icon { font-size: 1.7rem; }
    @media(max-width:767px){ .info-box { margin-bottom: 1rem; } }
    .error-message { 
        background: #f8d7da; 
        color: #721c24; 
        padding: 10px; 
        margin: 10px 0; 
        border-radius: 4px;
        border: 1px solid #f5c6cb;
    }
    .date-range {
        font-size: 0.85rem;
        color: #666;
    }
</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

<!-- Navbar -->
<nav class="main-header navbar navbar-white navbar-light">
    <span class="navbar-brand">User Dashboard</span>
    <a href="../auth/logout.php" class="btn btn-danger ml-auto">Logout</a>
</nav>

<div class="content-wrapper p-3">
    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></h3>

    <div class="row">
        <!-- Dashboard Cards -->
        <?php
        $cards = [
            ['text'=>'Today', 'count'=>$today_count, 'icon'=>'calendar-day', 'color'=>'info', 'tooltip'=>'Active reservations today'],
            ['text'=>'Upcoming', 'count'=>$upcoming_count, 'icon'=>'calendar-alt', 'color'=>'warning', 'tooltip'=>'Future reservations'],
            ['text'=>'Pencil', 'count'=>$pencil_count, 'icon'=>'pencil-alt', 'color'=>'primary', 'tooltip'=>'Pencil bookings'],
            ['text'=>'Pending', 'count'=>$pending_count, 'icon'=>'hourglass-half', 'color'=>'secondary', 'tooltip'=>'Pending approvals'],
            ['text'=>'Confirmed', 'count'=>$confirmed_count, 'icon'=>'check', 'color'=>'success', 'tooltip'=>'Approved bookings'],
            ['text'=>'Total', 'count'=>$total_count, 'icon'=>'list', 'color'=>'dark', 'tooltip'=>'All reservations']
        ];
        foreach($cards as $c): ?>
        <div class="col-md-2 col-sm-6">
            <div class="info-box bg-<?php echo $c['color']; ?>" data-toggle="tooltip" title="<?php echo $c['tooltip']; ?>">
                <span class="info-box-icon"><i class="fas fa-<?php echo $c['icon']; ?>"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?php echo $c['text']; ?></span>
                    <span class="info-box-number"><?php echo $c['count']; ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Actions -->
    <div class="mb-3">
        <a href="reservation.php" class="btn btn-primary">➕ Create New Reservation</a>
        <a href="calendar.php" class="btn btn-info">📅 View Calendar</a>
    </div>

    <!-- All Reservations Table -->
    <div class="card mt-3">
        <div class="card-header">Your Reservations</div>
        <div class="card-body table-responsive">
            <?php if($reservations === false): ?>
                <div class="error-message">
                    <strong>Error:</strong> Unable to load reservations. Please contact administrator.
                </div>
            <?php elseif($reservations->num_rows > 0): ?>
                <table class="table table-bordered table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Venue</th>
                            <th>Activity Type</th>
                            <th>Program Manager</th>
                            <th>Date Range</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $reservations->fetch_assoc()): 
                            $status = $row['status'];
                            $badge_class = $status=='approved' ? 'success' : ($status=='pending' ? 'warning' : 'danger');
                            $today = date('Y-m-d');
                            $is_active = ($today >= $row['date_from'] && $today <= $row['date_to']);
                        ?>
                        <tr <?php echo $is_active ? 'class="table-info"' : ''; ?>>
                            <td><?php echo htmlspecialchars($row['venue']); ?></td>
                            <td><?php echo htmlspecialchars($row['activity_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['program_manager']); ?></td>
                            <td>
                                <?php echo date("m/d/Y", strtotime($row['date_from'])); ?> - 
                                <?php echo date("m/d/Y", strtotime($row['date_to'])); ?>
                                <?php if($is_active): ?>
                                    <span class="badge badge-info">Active</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date("h:i A", strtotime($row['time_from'])) . " - " . date("h:i A", strtotime($row['time_to'])); ?></td>
                            <td><span class="badge badge-<?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span></td>
                            <td><?php echo ucfirst($row['type']); ?></td>
                            <td>
                                <?php if($row['type']=='pencil' && $status=='pending'): ?>
                                    <button class="btn btn-primary btn-sm uploadHOBtn" data-id="<?php echo $row['id']; ?>">
                                        <i class="fas fa-upload"></i> Upload HO
                                    </button>
                                    <a href="cancel_reservation.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this reservation?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php elseif($status=='approved'): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Confirmed</span>
                                <?php else: ?>
                                    <span class="text-muted"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center text-muted">No reservations found. <a href="reservation.php">Create your first reservation</a></p>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Single Upload HO Modal -->
<div class="modal fade" id="uploadHO" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" action="upload_ho.php">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Hospital Order</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                
                <div class="modal-body">
                    <input type="hidden" name="reservation_id" id="reservation_id">
                    
                    <div class="form-group">
                        <div class="custom-control custom-radio">
                            <input type="radio" id="manualEntry" name="entry_type" class="custom-control-input" value="manual" checked>
                            <label class="custom-control-label" for="manualEntry">Manual Entry</label>
                        </div>
                        <div id="manualEntryFields" class="mt-2">
                            <input type="text" 
                                   class="form-control" 
                                   name="hospital_order_no" 
                                   placeholder="Enter Hospital Order Number"
                                   maxlength="100">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-radio">
                            <input type="radio" id="fileUpload" name="entry_type" class="custom-control-input" value="file">
                            <label class="custom-control-label" for="fileUpload">File Upload</label>
                        </div>
                        <div id="fileUploadFields" class="mt-2" style="display: none;">
                            <input type="file" 
                                   class="form-control-file" 
                                   name="file" 
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="form-text text-muted">Allowed formats: PDF, JPG, PNG, DOC, DOCX (Max 5MB)</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" name="upload_ho" class="btn btn-primary">Submit</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle fields based on radio selection
document.querySelectorAll('input[name="entry_type"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        if (this.value === 'manual') {
            document.getElementById('manualEntryFields').style.display = 'block';
            document.getElementById('fileUploadFields').style.display = 'none';
        } else {
            document.getElementById('manualEntryFields').style.display = 'none';
            document.getElementById('fileUploadFields').style.display = 'block';
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/js/all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(function () {
    $('[data-toggle="tooltip"]').tooltip();

    // Open single modal for HO upload
    $('.uploadHOBtn').click(function(){
        var resId = $(this).data('id');
        $('#reservation_id').val(resId);
        $('#uploadHO').modal('show');
    });
});
</script>
</body>
</html>