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
				$day_reservations = isset($reservations_by_date[$date_string]) ? $reservations_by_date[$date_string] : [];
				
				echo '<div class="calendar-day" onclick="selectDate(\'' . $date_string . '\')" style="cursor: pointer;">';
				echo '<div class="day-number ' . ($is_today ? 'today' : '') . '">' . $current_day . '</div>';
				
				// Display events for this day (limit to 3 to prevent overflow)
				$display_count = 0;
				foreach ($day_reservations as $reservation) {
					if ($display_count >= 3) {
						$remaining = count($day_reservations) - 3;
						echo '<div class="calendar-event more" style="background:#e9ecef; text-align:center;">+' . $remaining . ' more</div>';
						break;
					}
					$status_class = $reservation['status'];
					if ($reservation['type'] == 'pencil') {
						$status_class = 'pencil';
					}
					$time_display = date('g:i A', strtotime($reservation['time_from'])) . ' - ' . date('g:i A', strtotime($reservation['time_to']));
					$venue_short = ($reservation['venue'] == 'Executive Lounge') ? 'EL' : 'Aud';
					
					echo '<div class="calendar-event ' . $status_class . '" 
						onclick="event.stopPropagation(); showEventDetails(' . json_encode($reservation) . ', \'' . htmlspecialchars($time_display) . '\')"
						title="' . htmlspecialchars($reservation['activity_type'] . ' - ' . $reservation['venue']) . '">';
					echo '<i class="fas fa-calendar-check"></i> ' . htmlspecialchars($venue_short . ': ' . substr($reservation['activity_type'], 0, 15) . (strlen($reservation['activity_type']) > 15 ? '...' : ''));
					echo '</div>';
					$display_count++;
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
				<div class="modal-header bg-primary text-white">
					<h5 class="modal-title"><i class="fas fa-info-circle"></i> Booking Details</h5>
					<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
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
		const statusClass = reservation.status === 'pending' ? 'warning' : 
						(reservation.status === 'approved' ? 'success' : 
						(reservation.status === 'rejected' ? 'danger' : 'info'));
		
		const statusIcon = reservation.status === 'pending' ? 'hourglass-half' : 
						(reservation.status === 'approved' ? 'check' : 
						(reservation.status === 'rejected' ? 'times' : 'pencil-alt'));
		
		const details = `
			<div class="event-detail">
				<strong><i class="fas fa-ticket-alt"></i> Booking ID:</strong> #${reservation.id}<br>
				<strong><i class="fas fa-calendar-day"></i> Date:</strong> ${reservation.date}<br>
				<strong><i class="fas fa-clock"></i> Time:</strong> ${timeDisplay}<br>
				<strong><i class="fas fa-building"></i> Venue:</strong> ${reservation.venue}<br>
				<strong><i class="fas fa-tag"></i> Activity Type:</strong> ${reservation.activity_type}<br>
				<strong><i class="fas fa-chalkboard-user"></i> Program Manager:</strong> ${reservation.program_manager}<br>
				<strong><i class="fas fa-user"></i> User:</strong> ${reservation.user_name}<br>
				<strong><i class="fas fa-${statusIcon}"></i> Status:</strong> 
				<span class="badge badge-${statusClass}">${reservation.status.toUpperCase()}</span><br>
				${reservation.type === 'pencil' ? '<strong><i class="fas fa-pencil-alt"></i> Type:</strong> <span class="badge badge-info">Pencil Booking</span><br>' : ''}
				${reservation.remarks ? '<strong><i class="fas fa-comment"></i> Remarks:</strong> ' + reservation.remarks + '<br>' : ''}
			</div>
		`;
		
		document.getElementById('eventDetails').innerHTML = details;
		$('#eventModal').modal('show');
	}

	function selectDate(date) {
		// Try to find and set the date input in the booking form
		const dateInput = document.querySelector('input[name="date"], input[id="date"], input[name="reservation_date"]');
		if (dateInput) {
			dateInput.value = date;
			// Highlight the selected date
			alert('Date ' + date + ' has been selected in the booking form');
		} else {
			console.log('Date selected:', date);
			alert('Date selected: ' + date + '\nPlease manually enter this date in the booking form if the date field is not automatically populated.');
		}
	}
	</script>