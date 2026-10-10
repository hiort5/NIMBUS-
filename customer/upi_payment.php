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
    // If not waiting payment, mark as waiting payment
    mysqli_query($conn, "UPDATE dining_session SET session_status='Waiting Payment' WHERE session_id='$session_id'");
    $session['session_status'] = 'Waiting Payment';
}

if (isset($_POST['paid'])) {
    if ($total <= 0) {
        die("Invalid payment amount.");
    }

    $transaction_id = "UPI" . date("YmdHis") . rand(100, 999);

    $payment = mysqli_query($conn, "
        INSERT INTO payments (session_id, amount, payment_method, payment_status, transaction_id)
        VALUES ('$session_id', '$total', 'UPI', 'Paid', '$transaction_id')
    ");

    if (!$payment) {
        die("Payment failed: " . mysqli_error($conn));
    }

    $update = mysqli_query($conn, "
        UPDATE dining_session
        SET payment_status='Paid',
            session_status='Completed',
            end_time=NOW()
        WHERE session_id='$session_id'
    ");

    if (!$update) {
        die("Session update failed: " . mysqli_error($conn));
    }

    $_SESSION['transaction_id'] = $transaction_id;
    $_SESSION['payment_method'] = 'UPI';
    $_SESSION['payment_amount'] = $total;

    header("Location: receipt.php");
    exit();
}

// Configured UPI details
$upi_id = "riorohit555@oksbi";
$payee_name = "Rohit";
$amount = number_format($total, 2, '.', '');
$table_no = $session['table_no'];
$note = "Table " . $table_no . " Bill";

// Standard UPI Deep-Link URI recognized by Google Pay, PhonePe, Paytm, BHIM, etc.
$upi_link = "upi://pay?pa=" . $upi_id . "&pn=" . rawurlencode($payee_name) . "&am=" . $amount . "&cu=INR&tn=" . rawurlencode($note);

// High-resolution QR Code generator URL
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=12&data=" . rawurlencode($upi_link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UPI Payment - Table <?php echo htmlspecialchars($session['table_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .qr-card {
            max-width: 480px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 36px 28px;
            text-align: center;
        }
        .qr-frame {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
            display: inline-block;
            margin: 18px 0;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
            position: relative;
        }
        .qr-frame img, .qr-frame canvas {
            display: block;
            margin: 0 auto;
            border-radius: 8px;
            max-width: 240px;
            height: auto;
        }
        .upi-copy-box {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            max-width: 100%;
        }
        .upi-apps-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 14px 0 18px;
        }
        .upi-app-pill {
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Top Navbar -->
    <nav class="nimbus-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand">
                ☁ Nimbus
                <span class="nimbus-brand-badge">UPI Pay</span>
            </a>
            <div class="table-pill">
                <span>🍽️ Table</span>
                <strong class="text-warning"><?php echo htmlspecialchars($session['table_no']); ?></strong>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <div class="qr-card">
            
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fw-bold small text-uppercase">
                    ● Instant UPI Checkout
                </span>
            </div>

            <h1 class="display-5 fw-bold text-dark mt-1 mb-0">₹<?php echo number_format($total, 2); ?></h1>
            <p class="text-muted small mt-1 mb-2">Scan QR code using Google Pay, PhonePe, Paytm, or any UPI app</p>

            <!-- Supported Apps Banner -->
            <div class="upi-apps-row">
                <span class="upi-app-pill">🔵 Google Pay</span>
                <span class="upi-app-pill">🟣 PhonePe</span>
                <span class="upi-app-pill">🔷 Paytm</span>
                <span class="upi-app-pill">🇮🇳 BHIM UPI</span>
            </div>

            <!-- QR Code Frame with Dual Online + Client-Side Fallback -->
            <div class="qr-frame">
                <div id="qrcodeCanvas" style="display: none;"></div>
                <img id="qrcodeImg" 
                     src="<?php echo htmlspecialchars($qr_url); ?>" 
                     alt="UPI QR Code for <?php echo htmlspecialchars($upi_id); ?>" 
                     width="240" 
                     height="240"
                     class="img-fluid"
                     onerror="showCanvasFallback()">
            </div>

            <!-- UPI ID Copy Box -->
            <div class="mb-3">
                <div class="upi-copy-box">
                    <span class="text-muted small">UPI ID:</span>
                    <strong class="text-dark font-monospace" id="upiIdText"><?php echo htmlspecialchars($upi_id); ?></strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 border-0" id="copyBtn" onclick="copyUpiId()" title="Copy UPI ID">
                        📋 Copy
                    </button>
                </div>
            </div>

            <!-- Direct Mobile UPI Intent Button (visible on mobile phones) -->
            <div class="mb-3">
                <a href="<?php echo htmlspecialchars($upi_link); ?>" class="btn btn-outline-primary w-100 py-2 fw-semibold rounded-3 d-sm-none">
                    📱 Open in UPI App (Pay Now)
                </a>
            </div>

            <!-- Confirmation Form -->
            <form method="POST">
                <button type="submit" name="paid" class="btn btn-dark btn-lg w-100 py-3 fw-bold rounded-3 shadow-sm mb-2">
                    ✓ I Have Completed Payment
                </button>
            </form>

            <a href="payment.php" class="btn btn-outline-secondary w-100 py-2 rounded-3 text-decoration-none small">
                ← Change Payment Method
            </a>

        </div>

    </div>

    <script>
        const upiString = <?php echo json_encode($upi_link); ?>;
        const upiId = <?php echo json_encode($upi_id); ?>;

        // Fallback to client-side QRCode if external image server is unreachable
        function showCanvasFallback() {
            const img = document.getElementById('qrcodeImg');
            const canvasContainer = document.getElementById('qrcodeCanvas');
            if (img && canvasContainer && typeof QRCode !== 'undefined') {
                img.style.display = 'none';
                canvasContainer.style.display = 'block';
                canvasContainer.innerHTML = '';
                new QRCode(canvasContainer, {
                    text: upiString,
                    width: 240,
                    height: 240,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            }
        }

        // Copy UPI ID with visual feedback
        function copyUpiId() {
            navigator.clipboard.writeText(upiId).then(() => {
                const copyBtn = document.getElementById('copyBtn');
                copyBtn.innerHTML = '✓ Copied!';
                copyBtn.classList.remove('btn-outline-secondary');
                copyBtn.classList.add('btn-success');
                setTimeout(() => {
                    copyBtn.innerHTML = '📋 Copy';
                    copyBtn.classList.remove('btn-success');
                    copyBtn.classList.add('btn-outline-secondary');
                }, 2000);
            }).catch(() => {
                prompt('Copy UPI ID:', upiId);
            });
        }
    </script>
</body>
</html>
