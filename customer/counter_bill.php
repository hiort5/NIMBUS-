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
    SELECT session_id, table_no, start_time, session_status, payment_status
    FROM dining_session
    WHERE session_id='$session_id'
");

if (!$result || mysqli_num_rows($result) == 0) {
    die("Dining session not found.");
}

$session = mysqli_fetch_assoc($result);

$result = mysqli_query($conn, "
    SELECT payment_id, amount, payment_method, payment_status, transaction_id
    FROM payments
    WHERE session_id='$session_id'
    AND payment_method='Counter'
    ORDER BY payment_id DESC
    LIMIT 1
");

if (!$result || mysqli_num_rows($result) == 0) {
    die("Counter bill not found.");
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
    <title>Counter Bill #<?php echo htmlspecialchars($payment['transaction_id']); ?> - Nimbus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .bill-slip {
            max-width: 480px;
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
    </style>
</head>
<body class="bg-light">

    <div class="container">
        
        <div class="bill-slip printable-receipt">
            
            <div class="text-center mb-3">
                <h3 class="fw-bold mb-0">☁ NIMBUS</h3>
                <span class="text-muted small text-uppercase letter-spacing-1">Counter Payment Slip</span>
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
                <div class="col-12">
                    <span class="text-muted">Bill No:</span> <strong class="text-dark"><?php echo htmlspecialchars($payment['transaction_id']); ?></strong>
                </div>
            </div>

            <div class="dashed-line"></div>

            <h6 class="fw-bold mb-3 small text-uppercase text-muted">Itemized Orders</h6>

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
                <span class="fs-5 fw-bold">TOTAL AMOUNT</span>
                <span class="fs-4 fw-bold text-dark">₹<?php echo number_format($total, 2); ?></span>
            </div>

            <div class="alert alert-warning rounded-3 py-2 px-3 small my-3 text-center">
                ⚠️ Please present this slip at the billing counter to make payment.
            </div>

            <div class="no-print d-flex gap-2 flex-column mt-4">
                <button onclick="window.print()" class="btn btn-dark w-100 py-2 fw-bold rounded-3">
                    🖨️ Print Slip
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary w-100 py-2 rounded-3 small">
                    ← Back to Dashboard
                </a>
            </div>

        </div>

    </div>

</body>
</html>
