<?php
// config/db.php

// Database configuration
$host = "localhost";
$user = "root";
$password = ""; // Put your MySQL password if any
$database = "reservation_db"; // Change this to your database name

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: set charset to utf8
$conn->set_charset("utf8");

// ================== Helper Functions ==================
// Check if functions exist before declaring them

if (!function_exists('logAction')) {
    function logAction($conn, $user_id, $action) {
        $stmt = $conn->prepare("INSERT INTO audit_logs(user_id, action, created_at) VALUES(?, ?, NOW())");
        
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("is", $user_id, $action);
        $result = $stmt->execute();
        
        if ($result === false) {
            error_log("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        return $result;
    }
}

if (!function_exists('isAvailable')) {
    function isAvailable($conn, $venue, $date_from, $date_to, $time_from, $time_to) {
        // Loop through each date in the range and check for conflicts
        $current_date = $date_from;
        
        while (strtotime($current_date) <= strtotime($date_to)) {
            $stmt = $conn->prepare("
                SELECT id FROM reservations
                WHERE venue = ?
                  AND date_from <= ? 
                  AND date_to >= ?
                  AND status IN ('pending', 'approved')
                  AND (
                      (time_from < ? AND time_to > ?)
                      OR (time_from < ? AND time_to > ?)
                      OR (time_from >= ? AND time_to <= ?)
                  )
            ");
            
            if ($stmt === false) {
                error_log("Prepare failed in isAvailable: " . $conn->error);
                return false;
            }
            
            $stmt->bind_param("sssssssss", 
                $venue,           // venue
                $current_date,    // date_from <= current_date
                $current_date,    // date_to >= current_date
                $time_to,         // time_from < time_to
                $time_from,       // time_to > time_from
                $time_to,         // time_from < time_to (second condition)
                $time_from,       // time_to > time_from (second condition)
                $time_from,       // time_from >= time_from
                $time_to          // time_to <= time_to
            );
            
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $stmt->close();
                return false; // Found a conflict on this date
            }
            $stmt->close();
            
            // Move to next date
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return true; // No conflicts found for all dates
    }
}
?>