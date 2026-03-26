<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'user') {
    die("Login required");
}

$uid = (int)$_SESSION['user']['id'];

// Function to safely execute queries
function safeQuery($conn, $sql, $errorMessage = "Query failed") {
    $result = $conn->query($sql);
    if ($result === false) {
        error_log($errorMessage . ": " . $conn->error);
        return false;
    }
    return $result;
}

// Get current month and year - FIXED navigation logic
$current_timestamp = time(); // Use current timestamp
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m', $current_timestamp);
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y', $current_timestamp);

// Handle navigation properly
if (isset($_GET['prev'])) {
    // Go to previous month
    $selected_month--;
    if ($selected_month < 1) {
        $selected_month = 12;
        $selected_year--;
    }
} elseif (isset($_GET['next'])) {
    // Go to next month
    $selected_month++;
    if ($selected_month > 12) {
        $selected_month = 1;
        $selected_year++;
    }
}

$month = $selected_month;
$year = $selected_year;

// Get first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$month_name = date('F', $first_day);
$days_in_month = date('t', $first_day);
$start_day_of_week = date('w', $first_day); // 0 = Sunday, 1 = Monday, etc.

// Debug info (remove in production)
$debug_info = false; // Set to true to see debug info

// Get all reservations for the current month
$start_date = date('Y-m-01', strtotime("$year-$month-01"));
$end_date = date('Y-m-t', strtotime("$year-$month-01"));

$sql = "SELECT * FROM reservations 
        WHERE user_id = $uid 
        AND (
            (date_from BETWEEN '$start_date' AND '$end_date')
            OR (date_to BETWEEN '$start_date' AND '$end_date')
            OR (date_from <= '$start_date' AND date_to >= '$end_date')
        )
        ORDER BY date_from ASC, time_from ASC";

$reservations_result = safeQuery($conn, $sql, "Failed to fetch reservations");

// Store reservations in an array for easy access
$reservations = [];
if ($reservations_result !== false) {
    while ($row = $reservations_result->fetch_assoc()) {
        // Add each day from date_from to date_to to the calendar
        $current = new DateTime($row['date_from']);
        $end = new DateTime($row['date_to']);
        $end->modify('+1 day');
        
        while ($current < $end) {
            $date_key = $current->format('Y-m-d');
            if (!isset($reservations[$date_key])) {
                $reservations[$date_key] = [];
            }
            $reservations[$date_key][] = $row;
            $current->modify('+1 day');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar View - My Reservations</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Navbar Styles */
        .main-header {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand {
            font-size: 1.2rem;
            font-weight: bold;
            color: #007bff;
        }
        
        /* Calendar Container */
        .content-wrapper {
            padding: 20px;
        }
        
        .calendar-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* Calendar Header */
        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .calendar-header h4 {
            margin: 0;
            font-weight: 600;
        }
        
        .calendar-header .btn-light {
            background: rgba(255,255,255,0.9);
            border: none;
            margin-left: 10px;
            transition: all 0.3s ease;
            color: #333;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .calendar-header .btn-light:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        
        /* Calendar Grid */
        .calendar-grid {
            padding: 20px;
        }
        
        /* Weekday Headers */
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 10px;
        }
        
        .weekday {
            padding: 12px;
            text-align: center;
            font-weight: bold;
            color: #495057;
            font-size: 14px;
        }
        
        /* Calendar Days Grid */
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: #dee2e6;
            border: 1px solid #dee2e6;
        }
        
        /* Individual Day Cell */
        .calendar-day {
            background-color: white;
            min-height: 120px;
            padding: 8px;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .calendar-day:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1;
        }
        
        .calendar-day.empty {
            background-color: #f8f9fa;
            min-height: 120px;
            cursor: default;
        }
        
        .calendar-day.today {
            background: linear-gradient(135deg, #fff3cd 0%, #fff3cd 100%);
            border: 2px solid #ffc107;
        }
        
        /* Day Number */
        .day-number {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 8px;
            color: #495057;
            display: inline-block;
            width: 28px;
            height: 28px;
            line-height: 28px;
            text-align: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .calendar-day.today .day-number {
            background-color: #ffc107;
            color: #212529;
            font-weight: bold;
        }
        
        .calendar-day:not(.empty):hover .day-number {
            background-color: #007bff;
            color: white;
        }
        
        /* Events Container */
        .events-container {
            max-height: 80px;
            overflow-y: auto;
            margin-top: 5px;
        }
        
        /* Event Styling */
        .event {
            font-size: 0.7em;
            margin: 3px 0;
            padding: 4px 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            background-color: #e7f3ff;
            border-left: 3px solid #007bff;
            color: #004085;
        }
        
        .event:hover {
            transform: translateX(2px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .event i {
            margin-right: 4px;
            font-size: 0.8em;
        }
        
        /* Event Status Colors */
        .event.approved {
            background-color: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .event.pending {
            background-color: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        
        .event.rejected {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        /* Scrollbar */
        .events-container::-webkit-scrollbar {
            width: 3px;
        }
        
        .events-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .events-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .events-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Legend */
        .calendar-legend {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        
        .calendar-legend h6 {
            margin-bottom: 10px;
            color: #495057;
            font-weight: 600;
        }
        
        .legend-items {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .legend-color.approved {
            background-color: #d4edda;
            border-left: 3px solid #28a745;
        }
        
        .legend-color.pending {
            background-color: #fff3cd;
            border-left: 3px solid #ffc107;
        }
        
        .legend-color.rejected {
            background-color: #f8d7da;
            border-left: 3px solid #dc3545;
        }
        
        .legend-color.today-badge {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
        }
        
        /* No Events Message */
        .no-events {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .calendar-header .row {
                flex-direction: column;
                text-align: center;
            }
            
            .calendar-header .col-md-8 {
                margin-top: 15px;
                text-align: center !important;
            }
            
            .calendar-header .btn-light {
                margin: 5px;
            }
            
            .calendar-day {
                min-height: 100px;
                font-size: 0.85em;
            }
            
            .day-number {
                font-size: 0.9em;
                width: 24px;
                height: 24px;
                line-height: 24px;
            }
            
            .event {
                font-size: 0.65em;
                padding: 2px 4px;
                white-space: normal;
            }
            
            .weekday {
                font-size: 11px;
                padding: 8px 4px;
            }
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
            }
            70% {
                box-shadow: 0 0 0 5px rgba(255, 193, 7, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
            }
        }
        
        .calendar-day.today {
            animation: pulse 2s infinite;
        }
        
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-info:hover {
            background-color: #138496;
            text-decoration: none;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            text-decoration: none;
            color: white;
        }
        
        .ml-2 {
            margin-left: 10px;
        }
        
        /* Debug info styling */
        .debug-info {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin: 10px;
            border-radius: 4px;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="wrapper">

<!-- Navbar -->
<nav class="main-header">
    <span class="navbar-brand">
        <i class="fas fa-calendar-alt"></i> Calendar View
    </span>
    <div>
        <a href="dashboard.php" class="btn-info">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="../auth/logout.php" class="btn-danger ml-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<div class="content-wrapper">
    <?php if($debug_info): ?>
    <div class="debug-info">
        <strong>Debug Info:</strong><br>
        Current Month: <?php echo $month; ?><br>
        Current Year: <?php echo $year; ?><br>
        Start Date: <?php echo $start_date; ?><br>
        End Date: <?php echo $end_date; ?><br>
        Number of Reservations: <?php echo count($reservations); ?>
    </div>
    <?php endif; ?>
    
    <div class="calendar-container">
        <!-- Calendar Header - FIXED navigation links -->
        <div class="calendar-header">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h4>
                        <i class="fas fa-calendar-alt"></i> 
                        <?php echo $month_name . ' ' . $year; ?>
                    </h4>
                </div>
                <div class="col-md-8 text-right">
                    <a href="?prev=1&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn-light btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <a href="calendar.php" class="btn-light btn">
                        <i class="fas fa-calendar-day"></i> Today
                    </a>
                    <a href="?next=1&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn-light btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid">
            <!-- Weekday Headers -->
            <div class="calendar-weekdays">
                <div class="weekday">Sunday</div>
                <div class="weekday">Monday</div>
                <div class="weekday">Tuesday</div>
                <div class="weekday">Wednesday</div>
                <div class="weekday">Thursday</div>
                <div class="weekday">Friday</div>
                <div class="weekday">Saturday</div>
            </div>

            <!-- Calendar Days -->
            <div class="calendar-days">
                <?php
                // Empty cells for days before the first day of the month
                for ($i = 0; $i < $start_day_of_week; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }

                // Loop through days of the month
                $today = date('Y-m-d');
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = date('Y-m-d', strtotime("$year-$month-$day"));
                    $is_today = ($current_date == $today);
                    $day_class = $is_today ? 'today' : '';
                    
                    echo '<div class="calendar-day ' . $day_class . '">';
                    echo '<div class="day-number">' . $day . '</div>';
                    echo '<div class="events-container">';
                    
                    // Display events for this day
                    if (isset($reservations[$current_date]) && count($reservations[$current_date]) > 0) {
                        foreach ($reservations[$current_date] as $event) {
                            $status_class = '';
                            switch($event['status']) {
                                case 'approved':
                                    $status_class = 'approved';
                                    break;
                                case 'pending':
                                    $status_class = 'pending';
                                    break;
                                case 'rejected':
                                    $status_class = 'rejected';
                                    break;
                            }
                            
                            echo '<div class="event ' . $status_class . '" title="' . htmlspecialchars($event['activity_type']) . ' - ' . htmlspecialchars($event['venue']) . '">';
                            echo '<i class="fas fa-calendar-check"></i> ';
                            echo '<strong>' . htmlspecialchars($event['venue']) . '</strong><br>';
                            echo '<small>' . date("h:i A", strtotime($event['time_from'])) . ' - ' . date("h:i A", strtotime($event['time_to'])) . '</small><br>';
                            echo '<small>' . htmlspecialchars($event['activity_type']) . '</small>';
                            echo '</div>';
                        }
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
                
                // Fill remaining cells after the last day
                $remaining_cells = (7 - (($days_in_month + $start_day_of_week) % 7)) % 7;
                for ($i = 0; $i < $remaining_cells; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                ?>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="calendar-legend">
            <h6><i class="fas fa-info-circle"></i> Status Legend</h6>
            <div class="legend-items">
                <div class="legend-item">
                    <span class="legend-color approved"></span>
                    <span>Approved Reservation</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color pending"></span>
                    <span>Pending Approval</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color rejected"></span>
                    <span>Rejected Reservation</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color today-badge"></span>
                    <span>Today</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>