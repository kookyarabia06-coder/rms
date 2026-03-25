<?php
include '../config/db.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) session_start();

// Ensure user is logged in
if(!isset($_SESSION['user'])) exit();

$uid = $_SESSION['user']['id'];
$events = [];

$result = $conn->query("SELECT * FROM reservations WHERE user_id=$uid");
while($row = $result->fetch_assoc()){

    // Treat 'final' type as approved for calendar display
    if($row['type']=='pencil') {
        $color = 'orange'; // Pencil reservation
        $status_text = ucfirst($row['status']); // Pending
    } elseif($row['type']=='final') {
        $color = 'green'; // Approved / Final
        $status_text = 'Approved';
    } elseif($row['status']=='rejected') {
        $color = 'red'; // Rejected
        $status_text = 'Rejected';
    } else {
        $color = 'blue'; // fallback
        $status_text = ucfirst($row['status']);
    }

    // Build event array
    $events[] = [
        'title' => $row['activity_type'] . " | " . $row['venue'], // show activity + venue
        'start' => $row['date'].'T'.$row['time_from'],
        'end' => $row['date'].'T'.$row['time_to'],
        'color' => $color,
        'description' => "Manager: ".$row['program_manager']." | Status: ".$status_text
    ];
}

// Return JSON
header('Content-Type: application/json');
echo json_encode($events);