<?php


require_once '../config/db.php';

// Get month and year parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Handle month navigation
if ($month < 1) {
    $month = 12;
    $year--;
}
if ($month > 12) {
    $month = 1;
    $year++;
}

$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$start_date = "$year-$month-01";
$end_date = "$year-$month-$days_in_month";

// Get current user ID if logged in
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

// Fetch all reservations for the month (including admin-made ones)
$query = "
    SELECT r.*, u.name as user_name, u.role as user_role
    FROM reservations r 
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.date BETWEEN '$start_date' AND '$end_date'
    ORDER BY r.date ASC, r.time_from ASC
";

$reservations_query = $conn->query($query);

// Organize reservations by date
$reservations_by_date = [];
while ($row = $reservations_query->fetch_assoc()) {
    $date = $row['date'];
    if (!isset($reservations_by_date[$date])) {
        $reservations_by_date[$date] = [];
    }
    $reservations_by_date[$date][] = $row;
}

// Get day of week for the first day (Monday as first day)
$first_day_of_week = date('N', $first_day); // 1 (Monday) to 7 (Sunday)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Calendar - User Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="css/calendar.css">
</head>
<body class="hold-transition layout-top-nav">
    <div class="wrapper">
        <div class="content-wrapper">
            <div class="content">
                <div class="container-fluid">
                    <div class="user-calendar-container">
                        <!-- Calendar Header -->
                        <div class="calendar-header">
                            <h3>
                                <i class="fas fa-calendar-alt"></i> 
                                Booking Calendar
                            </h3>
                        </div>
                        
                        <!-- Calendar Navigation -->
                        <div class="calendar-nav">
                            <h4 class="calendar-title">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('F Y', $first_day); ?>
                            </h4>
                            <div class="calendar-nav-buttons">
                                <a href="?month=<?php echo $month - 1; ?>&year=<?php echo $year; ?>" class="calendar-nav-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                                <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" class="calendar-nav-btn">
                                    <i class="fas fa-calendar-day"></i> Today
                                </a>
                                <a href="?month=<?php echo $month + 1; ?>&year=<?php echo $year; ?>" class="calendar-nav-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="filter-buttons">
                            <button class="filter-btn active" onclick="filterReservations('all')">
                                <i class="fas fa-list"></i> All
                            </button>
                            <button class="filter-btn" onclick="filterReservations('pending')">
                                <i class="fas fa-hourglass-half"></i> Pending
                            </button>
                            <button class="filter-btn" onclick="filterReservations('approved')">
                                <i class="fas fa-check-circle"></i> Approved
                            </button>
                            <button class="filter-btn" onclick="filterReservations('admin')">
                                <i class="fas fa-crown"></i> Admin Only
                            </button>
                            <button class="filter-btn" onclick="filterReservations('user')">
                                <i class="fas fa-user"></i> My Bookings
                            </button>
                        </div>

                        <!-- Calendar Table -->
                        <div class="calendar-table-wrapper">
                            <table class="calendar-table">
                                <thead>
                                    <tr>
                                        <th>Monday</th>
                                        <th>Tuesday</th>
                                        <th>Wednesday</th>
                                        <th>Thursday</th>
                                        <th>Friday</th>
                                        <th>Saturday</th>
                                        <th>Sunday</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Initialize day counter
                                    $current_day = 1;
                                    $first_week_day = $first_day_of_week;
                                    
                                    // Calculate total weeks to display
                                    $total_days_displayed = $first_week_day + $days_in_month;
                                    $total_weeks = ceil($total_days_displayed / 7);
                                    
                                    for ($week = 0; $week < $total_weeks; $week++) {
                                        echo '<tr>';
                                        for ($weekday = 1; $weekday <= 7; $weekday++) {
                                            if ($week == 0 && $weekday < $first_week_day) {
                                                // Empty cells before the first day of month
                                                echo '<td class="calendar-empty">';
                                                echo '<div class="calendar-day-number"></div>';
                                                echo '<div class="calendar-events"></div>';
                                                echo '</td>';
                                            } elseif ($current_day <= $days_in_month) {
                                                $current_date = sprintf("%04d-%02d-%02d", $year, $month, $current_day);
                                                $is_today = ($current_date == date('Y-m-d'));
                                                $today_class = $is_today ? 'today' : '';
                                                
                                                echo '<td class="' . $today_class . '">';
                                                echo '<div class="calendar-day-number">' . $current_day . '</div>';
                                                
                                                // Display events for this date
                                                if (isset($reservations_by_date[$current_date])) {
                                                    echo '<div class="calendar-events">';
                                                    foreach ($reservations_by_date[$current_date] as $reservation) {
                                                        // Determine status class
                                                        $status_class = '';
                                                        switch($reservation['status']) {
                                                            case 'pending':
                                                                $status_class = 'event-pending';
                                                                break;
                                                            case 'approved':
                                                                $status_class = 'event-approved';
                                                                break;
                                                            case 'rejected':
                                                                $status_class = 'event-rejected';
                                                                break;
                                                        }
                                                        
                                                        // Determine reservation type class
                                                        $reserved_by = ($reservation['user_role'] == 'admin') ? 'Admin' : $reservation['user_name'];
                                                        $is_admin_reservation = ($reservation['user_role'] == 'admin');
                                                        
                                                        echo '<div class="event-card ' . $status_class . '" data-status="' . $reservation['status'] . '" data-is-admin="' . ($is_admin_reservation ? '1' : '0') . '">';
                                                        echo '<div class="event-venue">';
                                                        echo '<i class="fas fa-building"></i> ' . htmlspecialchars($reservation['venue']);
                                                        if ($reservation['type'] == 'pencil') {
                                                            echo '<span class="pencil-badge"><i class="fas fa-pencil-alt"></i> Pencil</span>';
                                                        }
                                                        echo '</div>';
                                                        
                                                        echo '<div class="event-time">';
                                                        echo '<i class="far fa-clock"></i> ' . htmlspecialchars($reservation['time_from']) . ' - ' . htmlspecialchars($reservation['time_to']);
                                                        echo '</div>';
                                                        
                                                        echo '<div class="event-activity">';
                                                        echo '<i class="fas fa-tag"></i> ' . htmlspecialchars($reservation['activity_type']);
                                                        echo '</div>';
                                                        
                                                        echo '<div class="event-manager">';
                                                        echo '<i class="fas fa-user-tie"></i> ' . htmlspecialchars($reservation['program_manager']);
                                                        echo '</div>';
                                                        
                                                        echo '<div class="event-reserved-by">';
                                                        echo '<i class="fas fa-user"></i> ' . htmlspecialchars($reserved_by);
                                                        if ($is_admin_reservation) {
                                                            echo '<span class="admin-badge"><i class="fas fa-crown"></i> Admin</span>';
                                                        } else {
                                                            echo '<span class="user-badge"><i class="fas fa-user"></i> User</span>';
                                                        }
                                                        echo '</div>';
                                                        echo '</div>';
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo '<div class="calendar-events"></div>';
                                                }
                                                
                                                echo '</td>';
                                                $current_day++;
                                            } else {
                                                // Empty cells after the last day of month
                                                echo '<td class="calendar-empty">';
                                                echo '<div class="calendar-day-number"></div>';
                                                echo '<div class="calendar-events"></div>';
                                                echo '</td>';
                                            }
                                        }
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Legend -->
                        <div class="calendar-legend">
                            <div class="legend-item">
                                <div class="legend-color pending"></div>
                                <span><i class="fas fa-hourglass-half"></i> Pending</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color approved"></div>
                                <span><i class="fas fa-check-circle"></i> Approved</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color rejected"></div>
                                <span><i class="fas fa-times-circle"></i> Rejected</span>
                            </div>
                            <div class="legend-item">
                                <span class="admin-badge"><i class="fas fa-crown"></i> Admin</span>
                                <span>Admin Reservation</span>
                            </div>
                            <div class="legend-item">
                                <span class="pencil-badge"><i class="fas fa-pencil-alt"></i> Pencil</span>
                                <span>Pencil Booking</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script>
        // Filter reservations by type
        function filterReservations(filterType) {
            // Update active button state
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Find the clicked button and add active class
            const clickedButton = Array.from(buttons).find(btn => {
                const onclickAttr = btn.getAttribute('onclick');
                return onclickAttr && onclickAttr.includes(filterType);
            });
            
            if (clickedButton) {
                clickedButton.classList.add('active');
            }
            
            // Get all event cards
            const events = document.querySelectorAll('.event-card');
            
            events.forEach(event => {
                switch(filterType) {
                    case 'pending':
                        event.style.display = event.classList.contains('event-pending') ? 'block' : 'none';
                        break;
                    case 'approved':
                        event.style.display = event.classList.contains('event-approved') ? 'block' : 'none';
                        break;
                    case 'admin':
                        const isAdmin = event.getAttribute('data-is-admin') === '1';
                        event.style.display = isAdmin ? 'block' : 'none';
                        break;
                    case 'user':
                        const isUser = event.getAttribute('data-is-admin') === '0';
                        event.style.display = isUser ? 'block' : 'none';
                        break;
                    default:
                        event.style.display = 'block';
                }
            });
        }
        
        // Show reservation details (optional)
        function showReservationDetails(eventCard) {
            const venue = eventCard.querySelector('.event-venue').innerText;
            const time = eventCard.querySelector('.event-time').innerText;
            const activity = eventCard.querySelector('.event-activity').innerText;
            const manager = eventCard.querySelector('.event-manager').innerText;
            const reservedBy = eventCard.querySelector('.event-reserved-by').innerText;
            
            alert(`Reservation Details:\n\n${venue}\n${time}\n${activity}\n${manager}\n${reservedBy}`);
        }
        
        // Initialize calendar functionality
        $(document).ready(function() {
            // Add click event to event cards
            $('.event-card').click(function() {
                showReservationDetails(this);
            });
        });
    </script>
</body>
</html>