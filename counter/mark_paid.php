<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_POST['session_id']) || !is_numeric($_POST['session_id'])) {
    header("Location: dashboard.php");
    exit();
}

$session_id = (int)$_POST['session_id'];

mysqli_begin_transaction($conn);

try {
    $session_query = mysqli_query($conn, "
        SELECT session_id, table_no, session_status, payment_status
        FROM dining_session
        WHERE session_id='$session_id'
        LIMIT 1
    ");

    if (!$session_query || mysqli_num_rows($session_query) == 0) {
        throw new Exception("Dining session not found.");
    }

    $session = mysqli_fetch_assoc($session_query);

    // Calculate total from order items
    $total_q = mysqli_query($conn, "
        SELECT COALESCE(SUM(order_items.subtotal), 0) AS total
        FROM orders
        INNER JOIN order_items ON orders.order_id = order_items.order_id
        WHERE orders.session_id = '$session_id'
    ");
    $total = (float)mysqli_fetch_assoc($total_q)['total'];

    // Check for existing pending counter payment
    $payment_query = mysqli_query($conn, "
        SELECT payment_id
        FROM payments
        WHERE session_id='$session_id'
        AND payment_status='Pending'
        LIMIT 1
    ");

    if ($payment_query && mysqli_num_rows($payment_query) > 0) {
        $payment = mysqli_fetch_assoc($payment_query);
        $payment_id = (int)$payment['payment_id'];
        mysqli_query($conn, "
            UPDATE payments
            SET payment_status='Paid', amount='$total'
            WHERE payment_id='$payment_id'
        ");
    } else {
        // Create new paid payment record
        $txn_id = "CTR" . date("YmdHis") . rand(100, 999);
        mysqli_query($conn, "
            INSERT INTO payments (session_id, amount, payment_method, payment_status, transaction_id)
            VALUES ('$session_id', '$total', 'Counter', 'Paid', '$txn_id')
        ");
    }

    // Complete session
    $update_session = mysqli_query($conn, "
        UPDATE dining_session
        SET payment_status='Paid',
            session_status='Completed',
            total_amount='$total',
            end_time=NOW()
        WHERE session_id='$session_id'
    ");

    if (!$update_session) {
        throw new Exception("Session update failed: " . mysqli_error($conn));
    }

    mysqli_commit($conn);
    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Payment processing failed: " . htmlspecialchars($e->getMessage()));
}
?>
