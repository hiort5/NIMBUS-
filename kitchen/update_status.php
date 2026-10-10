<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
        exit();
    }
    die("Invalid Order ID.");
}

$order_id = (int)$_GET['id'];

if (!isset($_GET['status'])) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Status not provided']);
        exit();
    }
    die("Order status not provided.");
}

$new_status = $_GET['status'];
$allowed_statuses = ['Pending', 'Preparing', 'Ready', 'Served'];

if (!in_array($new_status, $allowed_statuses)) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit();
    }
    die("Invalid order status.");
}

$update = mysqli_query($conn, "
    UPDATE orders
    SET status='$new_status'
    WHERE order_id='$order_id'
");

if (!$update) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        exit();
    }
    die("Unable to update order status: " . mysqli_error($conn));
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'order_id' => $order_id, 'new_status' => $new_status]);
    exit();
}

header("Location: dashboard.php");
exit();
?>
