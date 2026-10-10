<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_SESSION['session_id'])) {
    header("Location: index.php");
    exit();
}

$session_id = (int)$_SESSION['session_id'];

$result = mysqli_query($conn, "
    SELECT session_id, table_no, session_status, payment_status
    FROM dining_session
    WHERE session_id='$session_id'
");

if (!$result || mysqli_num_rows($result) == 0) {
    die("Dining session not found.");
}

$session = mysqli_fetch_assoc($result);

$result = mysqli_query($conn, "
    SELECT COALESCE(SUM(order_items.subtotal), 0) AS total
    FROM orders
    INNER JOIN order_items ON orders.order_id = order_items.order_id
    WHERE orders.session_id='$session_id'
");

$total = (float)mysqli_fetch_assoc($result)['total'];

if ($session['session_status'] != 'Waiting Payment' && $session['payment_status'] != 'Paid') {
    mysqli_query($conn, "UPDATE dining_session SET session_status='Waiting Payment' WHERE session_id='$session_id'");
    $session['session_status'] = 'Waiting Payment';
}

$bill_number = "BILL" . date("YmdHis") . rand(100, 999);

if (isset($_POST['counter'])) {
    if ($total <= 0) {
        die("Invalid bill amount.");
    }

    $check = mysqli_query($conn, "
        SELECT payment_id, transaction_id
        FROM payments
        WHERE session_id='$session_id'
        AND payment_status='Pending'
        LIMIT 1
    ");

    if (mysqli_num_rows($check) == 0) {
        $payment = mysqli_query($conn, "
            INSERT INTO payments (session_id, amount, payment_method, payment_status, transaction_id)
            VALUES ('$session_id', '$total', 'Counter', 'Pending', '$bill_number')
        ");

        if (!$payment) {
            die("Unable to create counter payment: " . mysqli_error($conn));
        }
    } else {
        $existing = mysqli_fetch_assoc($check);
        $bill_number = $existing['transaction_id'];
    }

    $_SESSION['counter_bill'] = $bill_number;
    header("Location: counter_bill.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay at Counter - Nimbus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .counter-box {
            max-width: 500px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: var(--nb-radius-xl);
            border: 1px solid var(--nb-border);
            box-shadow: var(--nb-shadow-lg);
            padding: 36px 28px;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Top Navbar -->
    <nav class="nimbus-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand">
                ☁ Nimbus
                <span class="nimbus-brand-badge">Counter Pay</span>
            </a>
            <div class="table-pill">
                <span>🍽️ Table</span>
                <strong class="text-warning"><?php echo htmlspecialchars($session['table_no']); ?></strong>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="counter-box">
            <div class="fs-1 mb-2">💵</div>
            <h2 class="fw-bold mb-1">Pay at Counter</h2>
            <p class="text-muted small mb-3">Present your bill summary at the cash counter to complete payment.</p>

            <div class="py-3 px-4 bg-light rounded-4 my-4">
                <span class="text-muted text-uppercase fw-bold small">Total Due</span>
                <h1 class="display-5 fw-bold text-dark my-1">₹<?php echo number_format($total, 2); ?></h1>
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold">Payment Pending</span>
            </div>

            <form method="POST">
                <button type="submit" name="counter" class="btn btn-dark btn-lg w-100 py-3 fw-bold rounded-3 shadow mb-2">
                    🧾 Generate Counter Slip →
                </button>
            </form>

            <a href="payment.php" class="btn btn-outline-secondary w-100 py-2 rounded-3 text-decoration-none small">
                ← Change Payment Method
            </a>
        </div>
    </div>

</body>
</html>
