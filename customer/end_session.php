<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['session_id'])) {
    header("Location: index.php");
    exit();
}

$session_id = (int)$_SESSION['session_id'];

$result = mysqli_query($conn, "
    SELECT payment_status, session_status
    FROM dining_session
    WHERE session_id='$session_id'
");

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    // If completed or paid, clean up session
    if ($row['payment_status'] == 'Paid' || $row['session_status'] == 'Completed') {
        unset(
            $_SESSION['table_no'],
            $_SESSION['session_id'],
            $_SESSION['cart'],
            $_SESSION['last_order'],
            $_SESSION['transaction_id'],
            $_SESSION['payment_method'],
            $_SESSION['payment_amount'],
            $_SESSION['counter_bill']
        );
        header("Location: index.php");
        exit();
    }
}

// If not yet paid, return to dashboard
header("Location: dashboard.php");
exit();
?>
