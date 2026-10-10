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

// Calculate total from order items
$result = mysqli_query($conn, "
    SELECT COALESCE(SUM(order_items.subtotal), 0) AS total
    FROM orders
    INNER JOIN order_items ON orders.order_id = order_items.order_id
    WHERE orders.session_id='$session_id'
");

$total = (float)mysqli_fetch_assoc($result)['total'];

if ($total <= 0) {
    echo "<script>
    alert('There are no items ordered to pay for.');
    window.location='dashboard.php';
    </script>";
    exit();
}

// Auto-advance session to Waiting Payment so payment options are immediately accessible
if ($session['session_status'] == 'Dining' || isset($_POST['proceed'])) {
    $update = mysqli_query($conn, "
        UPDATE dining_session
        SET session_status='Waiting Payment'
        WHERE session_id='$session_id'
    ");

    if ($update) {
        $session['session_status'] = 'Waiting Payment';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Table <?php echo htmlspecialchars($session['table_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .payment-container {
            max-width: 580px;
            margin: 40px auto;
        }
        .payment-method-card {
            background: #ffffff;
            border: 1.5px solid var(--nb-border);
            border-radius: var(--nb-radius-lg);
            padding: 24px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .payment-method-card:hover {
            border-color: var(--nb-primary);
            background: var(--nb-primary-light);
            transform: translateY(-2px);
            box-shadow: var(--nb-shadow-md);
        }
        .pay-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: var(--nb-border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Top Navbar -->
    <nav class="nimbus-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand">
                ☁ Nimbus
                <span class="nimbus-brand-badge">Payment</span>
            </a>
            <div class="table-pill">
                <span>🍽️ Table</span>
                <strong class="text-warning"><?php echo htmlspecialchars($session['table_no']); ?></strong>
            </div>
        </div>
    </nav>

    <div class="container payment-container">
        
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4 text-center">
            
            <span class="text-muted text-uppercase fw-bold small">Total Outstanding Bill</span>
            <h1 class="display-4 fw-bold text-dark my-2">₹<?php echo number_format($total, 2); ?></h1>
            <p class="text-muted mb-0">Table <?php echo htmlspecialchars($session['table_no']); ?> • Session #<?php echo $session_id; ?></p>

            <div class="mt-3">
                <span class="badge <?php echo $session['payment_status'] == 'Paid' ? 'bg-success' : 'bg-warning text-dark'; ?> px-3 py-2 rounded-pill fw-bold">
                    Status: <?php echo htmlspecialchars($session['payment_status']); ?>
                </span>
            </div>

        </div>

        <?php if ($session['session_status'] == 'Dining') { ?>
            
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white text-center">
                <h5 class="fw-bold mb-2">Ready to complete your dining?</h5>
                <p class="text-muted small mb-4">Click below to proceed to the payment settlement options.</p>
                <form method="POST">
                    <button type="submit" name="proceed" class="btn btn-dark btn-lg w-100 py-3 fw-bold rounded-3 shadow">
                        Proceed to Payment Options →
                    </button>
                </form>
            </div>

        <?php } elseif ($session['session_status'] == 'Waiting Payment') { ?>

            <h5 class="fw-bold mb-3">Choose Payment Method</h5>

            <!-- UPI Payment Option -->
            <a href="upi_payment.php" class="payment-method-card">
                <div class="pay-icon">📱</div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold mb-1">Instant UPI Payment</h5>
                    <p class="text-muted small mb-0">Google Pay, PhonePe, Paytm, BHIM & UPI Apps</p>
                </div>
                <span class="fs-4 text-muted">→</span>
            </a>

            <!-- Counter Payment Option -->
            <a href="counter_payment.php" class="payment-method-card">
                <div class="pay-icon">💵</div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold mb-1">Pay at Restaurant Counter</h5>
                    <p class="text-muted small mb-0">Cash / Card / POS at the billing counter</p>
                </div>
                <span class="fs-4 text-muted">→</span>
            </a>

        <?php } elseif ($session['payment_status'] == 'Paid') { ?>

            <div class="alert alert-success rounded-4 p-4 text-center">
                <div class="fs-1 mb-2">✅</div>
                <h4 class="fw-bold">Payment Completed!</h4>
                <p class="mb-3">Your bill of ₹<?php echo number_format($total, 2); ?> has been settled successfully.</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="receipt.php" class="btn btn-dark fw-bold rounded-3 px-4 py-2">
                        🧾 View Receipt
                    </a>
                    <a href="end_session.php" class="btn btn-outline-dark rounded-3 px-4 py-2" onclick="return confirm('Finish session and return to table selection?');">
                        Exit Session
                    </a>
                </div>
            </div>

        <?php } ?>

        <div class="text-center mt-4">
            <a href="dashboard.php" class="text-muted small text-decoration-none fw-semibold">
                ← Return to Dashboard
            </a>
        </div>

    </div>

</body>
</html>
