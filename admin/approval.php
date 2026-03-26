<?php

require_once '../config/db.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') die("Login required");

// Handle Approve / Reject
if(isset($_POST['action']) && isset($_POST['reservation_id'])){
    $id = $_POST['reservation_id'];
    $action = $_POST['action'];

    if(in_array($action, ['approved','rejected'])){
        if($action == 'approved'){
            $stmt = $conn->prepare("UPDATE reservations SET status=?, type='final' WHERE id=?");
        } else {
            $stmt = $conn->prepare("UPDATE reservations SET status=? WHERE id=?");
        }

        $stmt->bind_param("si",$action,$id);
        $stmt->execute();

        logAction($conn,$_SESSION['user']['id'], ucfirst($action)." reservation ID $id");

        header("Location: admin_home.php?tab=allbooking");
        exit;
    }
}

// Handle Reschedule
if(isset($_POST['reschedule']) && isset($_POST['reservation_id'])){
    $id = $_POST['reservation_id'];
    $new_date = $_POST['date'];
    $new_time_from = $_POST['time_from'];
    $new_time_to = $_POST['time_to'];

    $res_row = $conn->query("SELECT * FROM reservations WHERE id=$id")->fetch_assoc();

    if(isAvailable($conn, $res_row['venue'], $new_date, $new_time_from, $new_time_to)){
        $stmt = $conn->prepare("UPDATE reservations SET date=?, time_from=?, time_to=? WHERE id=?");
        $stmt->bind_param("sssi",$new_date,$new_time_from,$new_time_to,$id);
        $stmt->execute();

        logAction($conn,$_SESSION['user']['id'], "Rescheduled reservation ID $id");

        header("Location: admin_home.php?tab=allbooking");
        exit;
    } else {
        $error_msg = "New time slot is already taken!";
    }
}

// Handle Cancel
if(isset($_POST['cancel']) && isset($_POST['reservation_id'])){
    $id = $_POST['reservation_id'];

    $stmt = $conn->prepare("DELETE FROM reservations WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    logAction($conn,$_SESSION['user']['id'], "Cancelled reservation ID $id");

    header("Location: admin_home.php?tab=allbooking");
    exit;
}

// Fetch all reservations - UPDATED to include file column
$reservations = $conn->query("SELECT r.*, u.name as user_name 
    FROM reservations r 
    LEFT JOIN users u ON r.user_id=u.id 
    ORDER BY r.date DESC, r.time_from ASC");

// Check if the query was successful
if(!$reservations) {
    die("Query failed: " . $conn->error);
}
?>

<h3>All Reservations</h3>

<?php if(isset($error_msg)): ?>
<div class="alert alert-danger"><?php echo $error_msg; ?></div>
<?php endif; ?>

<div class="card">
<div class="card-body table-responsive">

<table class="table table-bordered table-striped">
<thead>
<tr>
    <th>User</th>
    <th>Venue</th>
    <th>Activity Type</th>
    <th>Program Manager</th>
    <th>Date</th>
    <th>Time</th>
    <th>Status</th>
    <th>Type</th>
    <th>Hospital Order</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>
<?php while($row = $reservations->fetch_assoc()): ?>
<tr>
    <td><?php echo htmlspecialchars($row['user_name']); ?></td>
    <td><?php echo htmlspecialchars($row['venue']); ?></td>
    <td><?php echo htmlspecialchars($row['activity_type']); ?></td>
    <td><?php echo htmlspecialchars($row['program_manager']); ?></td>
    <td><?php echo htmlspecialchars($row['date']); ?></td>
    <td><?php echo htmlspecialchars($row['time_from']." - ".$row['time_to']); ?></td>
    <td><span class="badge badge-<?php echo $row['status']=='approved'?'success':($row['status']=='pending'?'warning':'danger'); ?>">
        <?php echo ucfirst($row['status']); ?>
    </span></td>
    <td><?php echo ucfirst($row['type']); ?></td>
    <td>
        <?php 
        // Check if file column exists and has a value
        if(isset($row['file']) && !empty($row['file'])): 
        ?>
            <a href="../uploads/<?php echo htmlspecialchars($row['file']); ?>" target="_blank" class="btn btn-info btn-sm">View HO</a>
        <?php else: ?>
            <span class="text-muted">No file</span>
        <?php endif; ?>
    </td>
    <td>
        <a href="../admin/reservation.php?edit_id=<?php echo $row['id']; ?>" 
        class="btn btn-primary btn-sm">
        <i class="fas fa-edit"></i>
        </a>
        <?php if($row['status']=='pending'): ?>
        <form method="post" style="display:inline;">
            <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
            <button type="submit" name="action" value="approved" class="btn btn-success btn-sm">Approve</button>
            <button type="submit" name="action" value="rejected" class="btn btn-danger btn-sm">Reject</button>
        </form>
        <?php endif; ?>

        <!-- Reschedule -->
        <form method="post" style="display:inline;" class="form-inline mt-1">
            <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
            <input type="date" name="date" class="form-control form-control-sm mr-1" value="<?php echo $row['date']; ?>" required>
            <input type="time" name="time_from" class="form-control form-control-sm mr-1" value="<?php echo $row['time_from']; ?>" required>
            <input type="time" name="time_to" class="form-control form-control-sm mr-1" value="<?php echo $row['time_to']; ?>" required>
            <button type="submit" name="reschedule" class="btn btn-warning btn-sm">Reschedule</button>
        </form>

        <!-- Cancel -->
        <form method="post" style="display:inline;" class="mt-1">
            <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
            <button type="submit" name="cancel" class="btn btn-danger btn-sm">Cancel</button>
        </form>

    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</div>
</div>