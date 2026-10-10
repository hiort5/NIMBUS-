<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

$q = mysqli_query($conn, "SELECT availability FROM menu WHERE food_id='$id' LIMIT 1");
if (!$q || mysqli_num_rows($q) == 0) {
    echo json_encode(['success' => false, 'message' => 'Food not found']);
    exit();
}

$current = mysqli_fetch_assoc($q)['availability'];
$new = ($current === 'Available') ? 'Out of Stock' : 'Available';

mysqli_query($conn, "UPDATE menu SET availability='$new' WHERE food_id='$id'");

if (isset($_GET['redirect'])) {
    header("Location: view_food.php");
    exit();
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'new_status' => $new]);
?>
