<?php
// This file contains the calendar functionality for admin panel
// It expects $conn, $month, $year, $reservations_by_date to be defined

if (!isset($conn) || !isset($month) || !isset($year) || !isset($reservations_by_date)) {
    die("Required variables not set");
}

$month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
$days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
$first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
$starting_day = date('w', $first_day_of_month);
$today_date = date('Y-m-d');
?>

<div class="calendar">
    <div class="calendar-nav">
        <a href="?tab=newbooking&month=<?php echo $month-1; ?>&year=<?php echo $year; ?>">
            <i class="fas fa-chevron-left"></i> Previous
        </a>
        <h4 class="mb-0"><?php echo $month_name . ' ' . $year; ?></h4>
        <a href="?tab=newbooking&month=<?php echo $month+1; ?>&year=<?php echo $year; ?>">
            Next <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    
    <div class="calendar-weekdays">
        <div>Sun</div>
        <div>Mon</div>
        <div>Tue</div>
        <div>Wed</div>
        <div>Thu</div>
        <div>Fri</div>
        <div>Sat</div>
    </div>
    
    <div class="calendar-days">
        <?php
        $current_day = 1;
        
        // Fill empty cells before the first day of the month
        for ($i = 0; $i < $starting_day; $i++) {
            echo '<div class="calendar-day empty"></div>';
        }
        
        // Fill days of the month
        while ($current_day <= $days_in_month) {
            $date_string = sprintf("%04d-%02d-%02d", $year, $month, $current_day);
            $is_today = ($date_string == $today_date);
            
            // Get reservations for this date (including those that span multiple days)
            $day_reservations = [];
            foreach ($reservations_by_date as $date_key => $reservations) {
                if ($date_key == $date_string) {
                    $day_reservations = $reservations;
                    break;
                }
            }
            
            echo '<div class="calendar-day" onclick="selectDate(\'' . $date_string . '\')" style="cursor: pointer;">';
            echo '<div class="day-number ' . ($is_today ? 'today' : '') . '">' . $current_day . '</div>';
            
            // Display events for this day (limit to 3 to prevent overflow)
            $display_count = 0;
            if (!empty($day_reservations)) {
                foreach ($day_reservations as $reservation) {
                    if ($display_count >= 3) {
                        $remaining = count($day_reservations) - 3;
                        echo '<div class="calendar-event more">+' . $remaining . ' more</div>';
                        break;
                    }
                    
                    // Determine status class
                    $status_class = $reservation['status'];
                    if ($reservation['type'] == 'pencil') {
                        $status_class = 'pencil';
                    }
                    
                    // Format time display
                    $time_display = date('g:i A', strtotime($reservation['time_from'])) . ' - ' . date('g:i A', strtotime($reservation['time_to']));
                    
                    // Short venue name
                    $venue_short = ($reservation['venue'] == 'Executive Lounge') ? 'EL' : 'Aud';
                    
                    // Activity type short
                    $activity_short = substr($reservation['activity_type'], 0, 12) . (strlen($reservation['activity_type']) > 12 ? '...' : '');
                    
                    echo '<div class="calendar-event ' . $status_class . '" 
                        onclick="event.stopPropagation(); showEventDetails(' . json_encode($reservation) . ', \'' . htmlspecialchars($time_display) . '\')"
                        title="' . htmlspecialchars($reservation['activity_type'] . ' - ' . $reservation['venue']) . '">';
                    echo '<i class="fas fa-calendar-check"></i> ' . htmlspecialchars($venue_short . ': ' . $activity_short);
                    echo '</div>';
                    $display_count++;
                }
            }
            
            echo '</div>';
            $current_day++;
        }
        ?>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle"></i> Booking Details
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="eventDetails">
                <!-- Event details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showEventDetails(reservation, timeDisplay) {
    let statusClass, statusIcon;
    
    // Determine status class and icon
    switch(reservation.status) {
        case 'pending':
            statusClass = 'warning';
            statusIcon = 'hourglass-half';
            break;
        case 'approved':
            statusClass = 'success';
            statusIcon = 'check';
            break;
        case 'rejected':
            statusClass = 'danger';
            statusIcon = 'times';
            break;
        default:
            statusClass = 'info';
            statusIcon = 'pencil-alt';
    }
    
    // Format date display (handle date range)
    let dateDisplay = reservation.date;
    if (reservation.date_from && reservation.date_to) {
        if (reservation.date_from === reservation.date_to) {
            dateDisplay = formatDate(reservation.date_from);
        } else {
            dateDisplay = formatDate(reservation.date_from) + ' to ' + formatDate(reservation.date_to);
        }
    } else if (reservation.date) {
        dateDisplay = formatDate(reservation.date);
    }
    
    const details = `
        <div class="event-detail">
            <p><strong><i class="fas fa-ticket-alt"></i> Booking ID:</strong> #${reservation.id}</p>
            <p><strong><i class="fas fa-calendar-day"></i> Date:</strong> ${dateDisplay}</p>
            <p><strong><i class="fas fa-clock"></i> Time:</strong> ${timeDisplay}</p>
            <p><strong><i class="fas fa-building"></i> Venue:</strong> ${escapeHtml(reservation.venue)}</p>
            <p><strong><i class="fas fa-tag"></i> Activity Type:</strong> ${escapeHtml(reservation.activity_type)}</p>
            <p><strong><i class="fas fa-chalkboard-user"></i> Program Manager:</strong> ${escapeHtml(reservation.program_manager)}</p>
            <p><strong><i class="fas fa-user"></i> User:</strong> ${escapeHtml(reservation.user_name)}</p>
            <p><strong><i class="fas fa-${statusIcon}"></i> Status:</strong> 
                <span class="badge badge-${statusClass}">${reservation.status.toUpperCase()}</span>
            </p>`;
    
    // Add type if pencil
    if (reservation.type === 'pencil') {
        details += `<p><strong><i class="fas fa-pencil-alt"></i> Type:</strong> <span class="badge badge-info">Pencil Booking</span></p>`;
    }
    
    // Add remarks if exists
    if (reservation.remarks) {
        details += `<p><strong><i class="fas fa-comment"></i> Remarks:</strong> ${escapeHtml(reservation.remarks)}</p>`;
    }
    
    details += `</div>`;
    
    document.getElementById('eventDetails').innerHTML = details;
    $('#eventModal').modal('show');
}

function selectDate(date) {
    // Try to find and set the date input in the booking form
    const dateFromInput = document.querySelector('input[name="date_from"]');
    const dateToInput = document.querySelector('input[name="date_to"]');
    
    if (dateFromInput) {
        dateFromInput.value = date;
        if (dateToInput && !dateToInput.value) {
            dateToInput.value = date;
        }
        // Highlight the selected date in the form
        dateFromInput.style.borderColor = '#28a745';
        dateFromInput.style.backgroundColor = '#f8f9ff';
        setTimeout(() => {
            dateFromInput.style.borderColor = '';
            dateFromInput.style.backgroundColor = '';
        }, 2000);
        
        // Optional: Show a subtle notification
        const notification = document.createElement('div');
        notification.textContent = '📅 Date ' + formatDate(date) + ' selected';
        notification.style.cssText = 'position:fixed; bottom:20px; right:20px; background:#28a745; color:white; padding:10px 20px; border-radius:5px; z-index:9999; animation:fadeOut 2s forwards;';
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 2000);
    } else {
        console.log('Date selected:', date);
        alert('Date selected: ' + formatDate(date) + '\nPlease manually enter this date in the booking form.');
    }
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
@keyframes fadeOut {
    0% { opacity: 1; }
    70% { opacity: 1; }
    100% { opacity: 0; visibility: hidden; }
}
</style>