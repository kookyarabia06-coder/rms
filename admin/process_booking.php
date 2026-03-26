<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    die("Unauthorized access");
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $booking_id = intval($_POST['booking_id']);
    
    switch($action) {
        case 'edit':
            $venue = $conn->real_escape_string($_POST['venue']);
            $date_from = $conn->real_escape_string($_POST['date_from']);
            $date_to = isset($_POST['date_to']) && !empty($_POST['date_to']) ? $conn->real_escape_string($_POST['date_to']) : null;
            $time_from = $conn->real_escape_string($_POST['time_from']);
            $time_to = $conn->real_escape_string($_POST['time_to']);
            $activity_type = $conn->real_escape_string($_POST['activity_type']);
            $program_manager = $conn->real_escape_string($_POST['program_manager']);
            $type = $conn->real_escape_string($_POST['type']);
            $status = $conn->real_escape_string($_POST['status']);
            $remarks = $conn->real_escape_string($_POST['remarks']);
            
            $query = $conn->prepare("UPDATE reservations SET venue=?, date_from=?, date_to=?, time_from=?, time_to=?, activity_type=?, program_manager=?, type=?, status=?, remarks=? WHERE id=?");
            $query->bind_param("ssssssssssi", $venue, $date_from, $date_to, $time_from, $time_to, $activity_type, $program_manager, $type, $status, $remarks, $booking_id);
            
            if($query->execute()) {
                $_SESSION['message'] = "Booking updated successfully";
            } else {
                $_SESSION['error'] = "Error updating booking: " . $conn->error;
            }
            break;
            
        case 'reschedule':
            $new_date_from = $conn->real_escape_string($_POST['new_date_from']);
            $new_date_to = isset($_POST['new_date_to']) && !empty($_POST['new_date_to']) ? $conn->real_escape_string($_POST['new_date_to']) : null;
            $new_time_from = $conn->real_escape_string($_POST['new_time_from']);
            $new_time_to = $conn->real_escape_string($_POST['new_time_to']);
            $remarks = $conn->real_escape_string($_POST['remarks']);
            
            $query = $conn->prepare("UPDATE reservations SET date_from=?, date_to=?, time_from=?, time_to=?, remarks=? WHERE id=?");
            $query->bind_param("sssssi", $new_date_from, $new_date_to, $new_time_from, $new_time_to, $remarks, $booking_id);
            
            if($query->execute()) {
                $_SESSION['message'] = "Booking rescheduled successfully";
            } else {
                $_SESSION['error'] = "Error rescheduling booking: " . $conn->error;
            }
            break;
            
        case 'reject':
            $remarks = $conn->real_escape_string($_POST['remarks']);
            
            $query = $conn->prepare("UPDATE reservations SET status='rejected', remarks=? WHERE id=?");
            $query->bind_param("si", $remarks, $booking_id);
            
            if($query->execute()) {
                $_SESSION['message'] = "Booking rejected successfully";
            } else {
                $_SESSION['error'] = "Error rejecting booking: " . $conn->error;
            }
            break;
    }
    
    header("Location: admin_home.php?tab=allbooking");
    exit;
}
?>