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
    SELECT session_id, table_no, start_time, end_time, session_status, payment_status
    FROM dining_session
    WHERE session_id='$session_id'
");

if (!$result || mysqli_num_rows($result) == 0) {
    die("Dining session not found.");
}

$session = mysqli_fetch_assoc($result);

$result = mysqli_query($conn, "
    SELECT payment_id, amount, payment_method, payment_status, transaction_id, payment_time
    FROM payments
    WHERE session_id='$session_id'
    ORDER BY payment_id DESC
    LIMIT 1
");

if (!$result || mysqli_num_rows($result) == 0) {
    die("Payment record not found.");
}

$payment = mysqli_fetch_assoc($result);

$items = mysqli_query($conn, "
    SELECT menu.food_name, menu.price,
           SUM(order_items.quantity) AS quantity,
           SUM(order_items.subtotal) AS subtotal
    FROM orders
    INNER JOIN order_items ON orders.order_id = order_items.order_id
    INNER JOIN menu ON order_items.food_id = menu.food_id
    WHERE orders.session_id='$session_id'
    GROUP BY menu.food_id, menu.food_name, menu.price
    ORDER BY menu.food_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($payment['transaction_id']); ?> - Nimbus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .receipt-card {
            max-width: 500px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: var(--nb-radius-xl);
            border: 1px solid var(--nb-border);
            box-shadow: var(--nb-shadow-lg);
            padding: 36px 30px;
        }
        .dashed-line {
            border-top: 1.5px dashed #cbd5e1;
            margin: 16px 0;
        }
        .paid-stamp {
            display: inline-block;
            border: 2px solid #16a34a;
            color: #16a34a;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 6px;
            transform: rotate(-5deg);
            font-size: 14px;
            letter-spacing: 0.1em;
        }
    </style>
</head>
<body class="bg-light">

    <div class="container">
        
        <div class="receipt-card printable-receipt">
            
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h3 class="fw-bold mb-0">☁ NIMBUS</h3>
                    <span class="text-muted small">Tax Invoice & Receipt</span>
                </div>
                <div class="paid-stamp">PAID</div>
            </div>

            <div class="dashed-line"></div>

            <div class="row small mb-2">
                <div class="col-6">
                    <span class="text-muted">Table:</span> <strong><?php echo htmlspecialchars($session['table_no']); ?></strong>
                </div>
                <div class="col-6 text-end">
                    <span class="text-muted">Session:</span> <strong>#<?php echo $session_id; ?></strong>
                </div>
            </div>

            <div class="row small mb-3">
                <div class="col-6">
                    <span class="text-muted">Txn ID:</span> <strong class="text-dark"><?php echo htmlspecialchars($payment['transaction_id']); ?></strong>
                </div>
                <div class="col-6 text-end">
                    <span class="text-muted">Method:</span> <strong><?php echo htmlspecialchars($payment['payment_method']); ?></strong>
                </div>
            </div>

            <div class="dashed-line"></div>

            <h6 class="fw-bold mb-3 small text-uppercase text-muted">Order Details</h6>

            <?php
            $total = 0;
            while ($item = mysqli_fetch_assoc($items)) {
                $total += $item['subtotal'];
            ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <div>
                        <div class="fw-bold small"><?php echo htmlspecialchars($item['food_name']); ?></div>
                        <small class="text-muted">₹<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?></small>
                    </div>
                    <div class="fw-bold small">
                        ₹<?php echo number_format($item['subtotal'], 2); ?>
                    </div>
                </div>
            <?php } ?>

            <div class="dashed-line"></div>

            <div class="d-flex justify-content-between align-items-center py-2">
                <span class="fs-5 fw-bold">TOTAL PAID</span>
                <span class="fs-4 fw-bold text-success">₹<?php echo number_format($total, 2); ?></span>
            </div>

            <div class="text-center my-4 py-2">
                <p class="mb-1 fw-bold">Thank you for dining with us! 🍽️</p>
                <small class="text-muted">We hope to serve you again soon.</small>
            </div>

            <div class="no-print d-flex gap-2 flex-column">
                <button onclick="window.print()" class="btn btn-outline-dark w-100 py-2 fw-bold rounded-3">
                    🖨️ Print Receipt
                </button>
                <a href="end_session.php" class="btn btn-dark w-100 py-3 fw-bold rounded-3 shadow" onclick="return confirm('Complete and exit this dining session?');">
                    ✓ Finish Dining & Return to Table Selection
                </a>
            </div>

        </div>

    </div>

</body>
</html>
