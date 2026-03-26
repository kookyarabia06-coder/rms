<?php
// Start session at the very beginning


// Check if user is logged in and is admin or superadmin
if (!isset($_SESSION['user'])) {
    die("Login required. <a href='../auth/login.php'>Click here to login</a>");
}

// Allow both admin and superadmin
if ($_SESSION['user']['role'] != 'admin' && $_SESSION['user']['role'] != 'superadmin') {
    die("Access denied. You do not have permission to access this page.");
}

require_once '../config/db.php';

$message = '';
$message_type = '';

// Get venues for dropdown
$venues = ['Executive Lounge', 'Auditorium'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_type = mysqli_real_escape_string($conn, $_POST['activity_type']);
    $date_from = mysqli_real_escape_string($conn, $_POST['date_from']);
    $date_to = mysqli_real_escape_string($conn, $_POST['date_to']);
    $time_from = mysqli_real_escape_string($conn, $_POST['time_from']);
    $time_to = mysqli_real_escape_string($conn, $_POST['time_to']);
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $program_manager = mysqli_real_escape_string($conn, $_POST['program_manager']);
    $remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($conn, $_POST['remarks']) : '';
    $type = 'pencil'; // Always pencil type
    $status = 'pending'; // Always pending status
    $user_id = $_SESSION['user']['id'];
    
    // Check if date_from is not in the past
    if (strtotime($date_from) < strtotime(date('Y-m-d'))) {
        $message = "Start date cannot be in the past!";
        $message_type = "danger";
    }
    // Check if date_to is before date_from
    elseif (strtotime($date_to) < strtotime($date_from)) {
        $message = "End date must be after or equal to start date!";
        $message_type = "danger";
    }
    // Check if time is valid
    elseif ($time_from >= $time_to) {
        $message = "End time must be after start time!";
        $message_type = "danger";
    }
    else {
        // Check availability for the date range using the updated function
        if (function_exists('isAvailable') && isAvailable($conn, $venue, $date_from, $date_to, $time_from, $time_to)) {
            
            // Insert single reservation with date range
            $sql = "INSERT INTO reservations (user_id, venue, activity_type, program_manager, date_from, date_to, time_from, time_to, status, type, created_at, remarks) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                $message = "Prepare failed: " . $conn->error;
                $message_type = "danger";
            } else {
                $stmt->bind_param("issssssssss", $user_id, $venue, $activity_type, $program_manager, $date_from, $date_to, $time_from, $time_to, $status, $type, $remarks);
                
                if ($stmt->execute()) {
                    $date_range_text = ($date_from == $date_to) ? date('F d, Y', strtotime($date_from)) : date('F d, Y', strtotime($date_from)) . " to " . date('F d, Y', strtotime($date_to));
                    $message = "Reservation submitted successfully! Date: $date_range_text, Time: $time_from - $time_to. Status: Pending";
                    $message_type = "success";
                    
                    // Log the action
                    if (function_exists('logAction')) {
                        logAction($conn, $user_id, "Created a new $type booking for $venue from $date_from to $date_to (Status: $status)");
                    }
                    
                    // Clear form after successful submission
                    $_POST = array();
                } else {
                    $message = "Error: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
        } else {
            // Get conflicting reservation details for better error message
            $conflict_query = $conn->prepare("
                SELECT date_from, date_to, time_from, time_to, activity_type 
                FROM reservations 
                WHERE venue = ? 
                  AND status IN ('pending', 'approved')
                  AND (
                      (date_from <= ? AND date_to >= ?)
                      OR (date_from <= ? AND date_to >= ?)
                      OR (date_from >= ? AND date_to <= ?)
                  )
                  AND (
                      (time_from < ? AND time_to > ?)
                      OR (time_from < ? AND time_to > ?)
                      OR (time_from >= ? AND time_to <= ?)
                  )
                LIMIT 1
            ");
            
            $conflict_query->bind_param("ssssssssssss", 
                $venue, 
                $date_to, $date_from,
                $date_to, $date_from,
                $date_from, $date_to,
                $time_to, $time_from,
                $time_to, $time_from,
                $time_from, $time_to
            );
            $conflict_query->execute();
            $conflict_result = $conflict_query->get_result();
            $conflict = $conflict_result->fetch_assoc();
            
            if ($conflict) {
                $conflict_date = ($conflict['date_from'] == $conflict['date_to']) ? 
                    date('F d, Y', strtotime($conflict['date_from'])) : 
                    date('F d, Y', strtotime($conflict['date_from'])) . " to " . date('F d, Y', strtotime($conflict['date_to']));
                
                $message = "Sorry, this time slot conflicts with an existing reservation!<br>
                            <strong>Conflict Details:</strong><br>
                            • Venue: $venue<br>
                            • Date: $conflict_date<br>
                            • Time: {$conflict['time_from']} - {$conflict['time_to']}<br>
                            • Activity: {$conflict['activity_type']}<br>
                            Please choose a different time or date range.";
                $message_type = "warning";
            } else {
                $message = "Sorry, this time slot is already booked. Please choose a different time or date range.";
                $message_type = "warning";
            }
            $conflict_query->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservation - Admin Panel</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flatpickr for better date/time picking -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reservation-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-top: 30px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 0 !important;
            padding: 20px 25px;
        }
        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .card-header h3 i {
            margin-right: 10px;
        }
        .card-body {
            padding: 30px;
            background: white;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-group label i {
            margin-right: 8px;
            color: #667eea;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        .required:after {
            content: " *";
            color: #dc3545;
            font-weight: bold;
        }
        .date-range-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .help-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #ffc107;
            color: #856404;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            width: 100%;
            color: white;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46a0 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
            color: white;
        }
        .btn-reset {
            background: #6c757d;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            width: 100%;
            color: white;
        }
        .btn-reset:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
            vertical-align: middle;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .conflict-details {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin-top: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="reservation-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-calendar-plus"></i> 
                            Create New Reservation
                        </h3>
                        <p class="mb-0 mt-2">Fill in the details below to create a new reservation</p>
                    </div>
                    <div class="card-body">
                        <!-- Alert Messages -->
                        <?php if($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                                <?php echo $message; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Reservation Form -->
                        <form method="POST" action="" id="reservationForm">
                            <!-- Venue Selection -->
                            <div class="form-group">
                                <label class="required">
                                    <i class="fas fa-building"></i> Venue
                                </label>
                                <select name="venue" class="form-control" required>
                                    <option value="">Select Venue</option>
                                    <?php foreach($venues as $v): ?>
                                        <option value="<?php echo $v; ?>" <?php echo (isset($_POST['venue']) && $_POST['venue'] == $v) ? 'selected' : ''; ?>>
                                            <?php echo $v; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Activity Type -->
                            <div class="form-group">
                                <label class="required">
                                    <i class="fas fa-tasks"></i> Activity Type
                                </label>
                                <input type="text" name="activity_type" class="form-control" 
                                       placeholder="e.g., Meeting, Seminar, Conference, Workshop"
                                       value="<?php echo isset($_POST['activity_type']) ? htmlspecialchars($_POST['activity_type']) : ''; ?>" required>
                            </div>

                            <!-- Date Range -->
                            <div class="date-range-group">
                                <div class="form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required">
                                                <i class="fas fa-calendar-day"></i> Date From
                                            </label>
                                            <input type="date" name="date_from" class="form-control datepicker" 
                                                   value="<?php echo isset($_POST['date_from']) ? $_POST['date_from'] : ''; ?>" 
                                                   min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="required">
                                                <i class="fas fa-calendar-day"></i> Date To
                                            </label>
                                            <input type="date" name="date_to" class="form-control datepicker" 
                                                   value="<?php echo isset($_POST['date_to']) ? $_POST['date_to'] : ''; ?>" 
                                                   min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <small class="help-text">
                                    <i class="fas fa-info-circle"></i> Select date range for multiple day bookings. For single day, select same date in both fields.
                                </small>
                            </div>

                            <!-- Time Row -->
                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required">
                                            <i class="fas fa-clock"></i> Start Time
                                        </label>
                                        <input type="time" name="time_from" class="form-control timepicker" 
                                               value="<?php echo isset($_POST['time_from']) ? $_POST['time_from'] : '09:00'; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required">
                                            <i class="fas fa-clock"></i> End Time
                                        </label>
                                        <input type="time" name="time_to" class="form-control timepicker" 
                                               value="<?php echo isset($_POST['time_to']) ? $_POST['time_to'] : '17:00'; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Program Manager -->
                            <div class="form-group">
                                <label class="required">
                                    <i class="fas fa-user-tie"></i> Program Manager
                                </label>
                                <input type="text" name="program_manager" class="form-control" 
                                       placeholder="Name of program manager or coordinator"
                                       value="<?php echo isset($_POST['program_manager']) ? htmlspecialchars($_POST['program_manager']) : ''; ?>" required>
                            </div>

                            <!-- Remarks -->
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-comment"></i> Remarks (Optional)
                                </label>
                                <textarea name="remarks" class="form-control" rows="3" 
                                          placeholder="Any additional notes or special requests..."><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
                            </div>

                            <!-- Status and Type Info -->
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>Booking Details:</strong><br>
                                <i class="fas fa-tag"></i> Booking Type: <strong>Pencil Booking</strong><br>
                                <i class="fas fa-flag-checkered"></i> Status: <span class="status-badge status-pending">Pending Approval</span>
                                <hr class="my-2">
                                <small>This reservation will be created with status "pending" and type "pencil". Admin approval is required.</small>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-row mt-4">
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-submit" id="submitBtn">
                                        <i class="fas fa-save"></i> Submit Reservation
                                        <span class="spinner"></span>
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="reset" class="btn btn-reset">
                                        <i class="fas fa-undo-alt"></i> Reset Form
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize date pickers
            flatpickr(".datepicker", {
                minDate: "today",
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y"
            });
            
            // Initialize time pickers
            flatpickr(".timepicker", {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
                time_24hr: true
            });
            
            // Form validation
            $('#reservationForm').submit(function(e) {
                let dateFrom = $('input[name="date_from"]').val();
                let dateTo = $('input[name="date_to"]').val();
                let timeFrom = $('input[name="time_from"]').val();
                let timeTo = $('input[name="time_to"]').val();
                
                if (!dateFrom || !dateTo) {
                    e.preventDefault();
                    alert('Please select both start and end dates!');
                    return false;
                }
                
                if (dateFrom > dateTo) {
                    e.preventDefault();
                    alert('End date must be after or equal to start date!');
                    return false;
                }
                
                if (timeFrom >= timeTo) {
                    e.preventDefault();
                    alert('End time must be after start time!');
                    return false;
                }
                
                // Show spinner
                $('#submitBtn .spinner').show();
                $('#submitBtn').prop('disabled', true);
            });
            
            // Reset form
            $('button[type="reset"]').click(function() {
                setTimeout(function() {
                    $('input[name="date_from"]').val('');
                    $('input[name="date_to"]').val('');
                    $('input[name="time_from"]').val('09:00');
                    $('input[name="time_to"]').val('17:00');
                    $('input[name="activity_type"]').val('');
                    $('input[name="program_manager"]').val('');
                    $('textarea[name="remarks"]').val('');
                    $('select[name="venue"]').val('');
                }, 100);
            });
        });
    </script>
</body>
</html>