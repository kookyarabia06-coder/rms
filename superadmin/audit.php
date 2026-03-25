<?php include '../config/db.php';
if($_SESSION['user']['role']!='superadmin') die("Access denied");
?>

<h2>Super Admin Dashboard</h2>
<a href="../auth/logout.php">Logout</a>

<hr>

<?php
function countData($conn,$where){
    return $conn->query("SELECT COUNT(*) c FROM reservations WHERE $where")->fetch_assoc()['c'];
}
?>

<h3>Summary</h3>
Total: <?=countData($conn,"1=1")?><br>
Pending: <?=countData($conn,"status='pending'")?><br>
Approved: <?=countData($conn,"status='approved'")?><br>
Rejected: <?=countData($conn,"status='rejected'")?><br>

<hr>

<h3>Audit Trail</h3>
<?php
$logs=$conn->query("SELECT a.*,u.name FROM audit_logs a 
LEFT JOIN users u ON u.id=a.user_id 
ORDER BY created_at DESC");

while($l=$logs->fetch_assoc()){
    echo "{$l['name']} - {$l['action']} - {$l['created_at']}<br>";
}
?>