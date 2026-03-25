<?php
// Start session
session_start();

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

// Log action for audit trail
function logAction($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs(user_id, action) VALUES(?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
}

// Check reservation availability
function isAvailable($conn, $venue, $date, $time_from, $time_to) {
    $stmt = $conn->prepare("
        SELECT id FROM reservations
        WHERE venue = ?
          AND date = ?
          AND status IN ('pending','approved')
          AND (
              (time_from <= ? AND time_to > ?)
              OR (time_from < ? AND time_to >= ?)
              OR (? <= time_from AND ? >= time_to)
          )
    ");
    $stmt->bind_param("ssssssss", $venue, $date, $time_from, $time_from, $time_to, $time_to, $time_from, $time_to);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows === 0;
}
?>