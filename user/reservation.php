<?php

require_once '../config/db.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if(!isset($_SESSION['user'])) {
    die("Login required");
}

$uid = $_SESSION['user']['id'];
$isEdit = false;

// ================= EDIT LOAD =================
if(isset($_GET['edit_id'])){
    $id = (int)$_GET['edit_id'];
    
    $result = $conn->query("SELECT * FROM reservations WHERE id = $id");
    if (!$result) {
        die("Error loading reservation: " . $conn->error);
    }
    
    $edit = $result->fetch_assoc();
    
    if (!$edit) {
        die("Reservation not found");
    }

    // assign values - UPDATE THESE COLUMN NAMES TO MATCH YOUR DATABASE
    $venue = $edit['venue'];
    $activity_type = $edit['activity_type']; // ← Check if this column exists
    $program_manager = $edit['program_manager']; // ← Check if this column exists
    $date = $edit['date'];
    $time_from = $edit['time_from'];
    $time_to = $edit['time_to'];
}

// ================= SAVE =================
if(isset($_POST['reserve'])){
    $venue = $_POST['venue'];
    $activity_type = $_POST['activity_type'];
    $program_manager = $_POST['program_manager'];
    $date = $_POST['date'];
    $time_from = $_POST['time_from'];
    $time_to = $_POST['time_to'];

    if(isAvailable($conn, $venue, $date, $time_from, $time_to)){

        // ===== UPDATE MODE =====
        if(isset($_POST['reservation_id'])){
            $id = (int)$_POST['reservation_id'];
            
            // UPDATE THIS SQL - Use the correct column names from your database
            $sql = "UPDATE reservations SET 
                        venue = ?, 
                        activity_type = ?,  -- ← Change if your column has different name
                        program_manager = ?, -- ← Change if your column has different name
                        date = ?, 
                        time_from = ?, 
                        time_to = ?,
                        status = 'pending', 
                        type = 'pencil'
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            
            $stmt->bind_param(
                "ssssssi",
                $venue, 
                $activity_type, 
                $program_manager,
                $date, 
                $time_from, 
                $time_to, 
                $id
            );
            
            if (!$stmt->execute()) {
                die("Execute failed: " . $stmt->error);
            }
            
            if (function_exists('logAction')) {
                logAction($conn, $uid, "Updated reservation ID $id");
            }

            $success_msg = "Reservation updated successfully!";

        } else {
            // ===== CREATE MODE =====
            $type = 'pencil';
            $status = 'pending';
            
            // UPDATE THIS SQL - Use the correct column names from your database
            $sql = "INSERT INTO reservations(
                        user_id, 
                        venue, 
                        activity_type,  -- ← Change if your column has different name
                        program_manager, -- ← Change if your column has different name
                        date, 
                        time_from, 
                        time_to, 
                        status, 
                        type
                    ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            
            $stmt->bind_param(
                "issssssss",
                $uid, 
                $venue, 
                $activity_type, 
                $program_manager,
                $date, 
                $time_from, 
                $time_to, 
                $status, 
                $type
            );
            
            if (!$stmt->execute()) {
                die("Execute failed: " . $stmt->error);
            }
            
            if (function_exists('logAction')) {
                logAction($conn, $uid, "Created reservation");
            }

            $success_msg = "Reservation submitted successfully!";
        }
        
        $stmt->close();

    } else {
        $error_msg = "Selected time slot is already taken!";
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
</head>

<body class="hold-transition sidebar-mini">

<div class="wrapper">

<nav class="main-header navbar navbar-white navbar-light">
    <a href="<?php echo $_SESSION['user']['role']=='admin' ? '../admin/admin_home.php' : 'dashboard.php'; ?>" class="btn btn-secondary">⬅ Back</a>
</nav>

<div class="content-wrapper p-3">

<h3><?php echo $isEdit ? 'Edit Reservation' : 'Create New Reservation'; ?></h3>

<?php if(isset($success_msg)) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
<?php if(isset($error_msg)) echo "<div class='alert alert-danger'>$error_msg</div>"; ?>

<div class="card">
<div class="card-header">Reservation Form</div>
<div class="card-body">

<form method="POST">

<?php if($isEdit): ?>
<input type="hidden" name="reservation_id" value="<?php echo $id; ?>">
<?php endif; ?>

<div class="form-group">
<label>Venue</label>
<select name="venue" class="form-control" required>
    <option value="Executive Lounge" <?php if(($venue??'')=='Executive Lounge') echo 'selected'; ?>>Executive Lounge</option>
    <option value="Auditorium" <?php if(($venue??'')=='Auditorium') echo 'selected'; ?>>Auditorium</option>
</select>
</div>

<div class="form-group">
<label>Activity Type</label>
<input type="text" name="activity_type" class="form-control" value="<?php echo htmlspecialchars($activity_type ?? ''); ?>" required>
</div>

<div class="form-group">
<label>Program Manager</label>
<input type="text" name="program_manager" class="form-control" value="<?php echo htmlspecialchars($program_manager ?? ''); ?>" required>
</div>

<div class="form-group">
<label>Date</label>
<input type="date" name="date" class="form-control" value="<?php echo $date ?? ''; ?>" required>
</div>

<div class="form-group">
<label>Time From</label>
<input type="time" name="time_from" class="form-control" value="<?php echo $time_from ?? ''; ?>" required>
</div>

<div class="form-group">
<label>Time To</label>
<input type="time" name="time_to" class="form-control" value="<?php echo $time_to ?? ''; ?>" required>
</div>

<button type="submit" name="reserve" class="btn btn-primary">
    <?php echo $isEdit ? 'Update Reservation' : 'Submit Reservation'; ?>
</button>

</form>

</div>
</div>

</div>
</div>

</body>
</html>