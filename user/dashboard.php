<?php
include '../config/db.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'user') die("Login required");

$uid = $_SESSION['user']['id'];
$today = date('Y-m-d');

// Dashboard counts
$today_count = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND date='$today'")->fetch_assoc()['cnt'];
$upcoming_count = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND date>'$today'")->fetch_assoc()['cnt'];
$pencil_count = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND type='pencil'")->fetch_assoc()['cnt'];
$confirmed_count = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND status='approved'")->fetch_assoc()['cnt'];
$pending_count = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid AND status='pending'")->fetch_assoc()['cnt'];
$total_count = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE user_id=$uid")->fetch_assoc()['cnt'];

// Today's activities by venue
$exec_today = $conn->query("SELECT * FROM reservations WHERE user_id=$uid AND date='$today' AND venue='Executive Lounge' ORDER BY time_from ASC");
$aud_today = $conn->query("SELECT * FROM reservations WHERE user_id=$uid AND date='$today' AND venue='Auditorium' ORDER BY time_from ASC");

// All reservations for table
$reservations = $conn->query("SELECT * FROM reservations WHERE user_id=$uid ORDER BY date DESC, time_from ASC");
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
            ['text'=>'Today', 'count'=>$today_count, 'icon'=>'calendar-day', 'color'=>'info', 'tooltip'=>'Reservations today'],
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

    <!-- Today's Activities -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">Executive Lounge - Today</div>
                <div class="card-body">
                    <?php if($exec_today->num_rows>0): ?>
                        <ul>
                        <?php while($row = $exec_today->fetch_assoc()): ?>
                            <li><?php echo htmlspecialchars($row['activity_type'] . " | " . $row['time_from'] . "-" . $row['time_to'] . " | Manager: " . $row['program_manager']); ?></li>
                        <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>No activity today.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">Auditorium - Today</div>
                <div class="card-body">
                    <?php if($aud_today->num_rows>0): ?>
                        <ul>
                        <?php while($row = $aud_today->fetch_assoc()): ?>
                            <li><?php echo htmlspecialchars($row['activity_type'] . " | " . $row['time_from'] . "-" . $row['time_to'] . " | Manager: " . $row['program_manager']); ?></li>
                        <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>No activity today.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
            <table class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th>Venue</th>
                        <th>Activity Type</th>
                        <th>Program Manager</th>
                        <th>Date</th>
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
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['venue']); ?></td>
                        <td><?php echo htmlspecialchars($row['activity_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['program_manager']); ?></td>
                        <td><?php echo date("m/d/Y", strtotime($row['date'])); ?></td>
                        <td><?php echo date("h:i A", strtotime($row['time_from'])) . " - " . date("h:i A", strtotime($row['time_to'])); ?></td>
                        <td><span class="badge badge-<?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span></td>
                        <td><?php echo ucfirst($row['type']); ?></td>
                        <td>
                            <?php if($row['type']=='pencil' && $status=='pending'): ?>
                                <button class="btn btn-primary btn-sm uploadHOBtn" data-id="<?php echo $row['id']; ?>"><i class="fas fa-upload"></i></button>
                                <a href="cancel_reservation.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')"><i class="fas fa-times"></i></a>
                            <?php else: ?>
                                <span class="text-success">Submitted</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
                            <small class="form-text text-muted">Allowed formats: PDF, JPG, PNG, DOC, DOCX</small>
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