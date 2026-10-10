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

$session_query = mysqli_query($conn, "
    SELECT table_no, payment_status, session_status
    FROM dining_session
    WHERE session_id='$session_id'
");

if (!$session_query || mysqli_num_rows($session_query) == 0) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

$session = mysqli_fetch_assoc($session_query);

/* Process Cart Actions */
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $food_id = isset($_POST['food_id']) ? (int)$_POST['food_id'] : 0;

    if ($session['session_status'] != 'Dining') {
        header("Location: dashboard.php");
        exit();
    }

    if ($action == 'add' && $food_id > 0) {
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $_SESSION['cart'][$food_id] = ($_SESSION['cart'][$food_id] ?? 0) + $quantity;
    } elseif ($action == 'increase' && $food_id > 0) {
        if (isset($_SESSION['cart'][$food_id])) {
            $_SESSION['cart'][$food_id]++;
        }
    } elseif ($action == 'decrease' && $food_id > 0) {
        if (isset($_SESSION['cart'][$food_id])) {
            $_SESSION['cart'][$food_id]--;
            if ($_SESSION['cart'][$food_id] <= 0) {
                unset($_SESSION['cart'][$food_id]);
            }
        }
    } elseif ($action == 'remove' && $food_id > 0) {
        unset($_SESSION['cart'][$food_id]);
    } elseif ($action == 'clear') {
        unset($_SESSION['cart']);
    }

    header("Location: cart.php");
    exit();
}

$total = 0;
$items_count = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - Nimbus Dining</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .cart-item-card {
            background: #ffffff;
            border: 1px solid var(--nb-border);
            border-radius: var(--nb-radius-lg);
            padding: 16px;
            margin-bottom: 14px;
            box-shadow: var(--nb-shadow-xs);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .cart-item-img {
            width: 80px;
            height: 80px;
            border-radius: var(--nb-radius-md);
            object-fit: cover;
            flex-shrink: 0;
        }
        .order-summary-box {
            background: #ffffff;
            border: 1px solid var(--nb-border);
            border-radius: var(--nb-radius-xl);
            padding: 28px;
            box-shadow: var(--nb-shadow-sm);
            position: sticky;
            top: 90px;
        }
    </style>
</head>
<body class="customer-dashboard-body">

    <!-- Atmospheric Blurred Food Canvas -->
    <div class="food-atmosphere-canvas" id="foodAtmosphereCanvas" aria-hidden="true">
        <div class="food-atm-item atm-pos-1 food-depth-distant blur-heavy-1">
            <div class="food-drift-inner drift-loop-1">
                <img src="../assets/images/burger.png" alt="" />
            </div>
        </div>
        <div class="food-atm-item atm-pos-2 food-depth-distant blur-heavy-2">
            <div class="food-drift-inner drift-loop-2">
                <img src="../assets/images/pizza.png" alt="" />
            </div>
        </div>
        <div class="food-atm-item atm-pos-3 food-depth-distant blur-heavy-3">
            <div class="food-drift-inner drift-loop-3">
                <img src="../assets/images/sandwich.png" alt="" />
            </div>
        </div>
        <div class="food-atm-item atm-pos-4 food-depth-mid blur-soft-1">
            <div class="food-drift-inner drift-loop-4">
                <img src="../assets/images/pudding.png" alt="" />
            </div>
        </div>
        <div class="food-atm-item atm-pos-11 food-depth-mid blur-soft-2">
            <div class="food-drift-inner drift-loop-1">
                <img src="../assets/images/pizza.png" alt="" />
            </div>
        </div>
    </div>

    <!-- Vibrant Yellow & Orange Navbar -->
    <nav class="nimbus-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand">
                ☁ Nimbus
                <span class="nimbus-brand-badge">Dining</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="table-pill">
                    <span>🍽️ Table</span>
                    <strong class="text-warning"><?php echo htmlspecialchars($session['table_no']); ?></strong>
                </div>
                <a href="dashboard.php" class="nav-link-btn">
                    ← Menu
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="position: relative; z-index: 10;">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <span class="text-uppercase fw-bold small letter-spacing-1 d-block mb-1" style="color: #E65100;">Order Review</span>
                <h1 class="display-6 fw-bold mb-1" style="color: #1A0C00;">Shopping Cart</h1>
                <p class="text-muted small mb-0">Review items before sending to the kitchen</p>
            </div>
            <a href="dashboard.php" class="cart-btn-outline d-inline-flex align-items-center gap-2">
                ＋ Add More Items
            </a>
        </div>

        <?php if (empty($_SESSION['cart'])) { ?>
            <div class="text-center py-5 bg-white rounded-4 shadow-sm my-4" style="border: 2px solid rgba(255, 160, 0, 0.35);">
                <div class="fs-1 mb-3">🛒</div>
                <h3 class="fw-bold mb-2" style="color: #1A0C00;">Your Cart is Empty</h3>
                <p class="text-muted mb-4">Explore our delicious menu items and add them to your cart.</p>
                <div class="d-flex justify-content-center">
                    <a href="dashboard.php" class="btn-place-order text-decoration-none px-5 py-3" style="width: auto;">
                        🍔 Browse Menu
                    </a>
                </div>
            </div>
        <?php } else { ?>
            <div class="row g-4">
                
                <!-- Cart Items List -->
                <div class="col-lg-8">
                    
                    <?php
                    foreach ($_SESSION['cart'] as $food_id => $quantity) {
                        $food_id = (int)$food_id;
                        $quantity = (int)$quantity;
                        if ($food_id <= 0 || $quantity <= 0) continue;

                        $result = mysqli_query($conn, "
                            SELECT food_id, food_name, price, image, category
                            FROM menu
                            WHERE food_id='$food_id'
                            AND availability='Available'
                            LIMIT 1
                        ");

                        if (!$result || mysqli_num_rows($result) == 0) {
                            unset($_SESSION['cart'][$food_id]);
                            continue;
                        }

                        $row = mysqli_fetch_assoc($result);
                        $price = (float)$row['price'];
                        $subtotal = $price * $quantity;
                        $total += $subtotal;
                        $items_count += $quantity;
                    ?>
                        <div class="cart-item-card">
                            <img src="../assets/images/<?php echo htmlspecialchars($row['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($row['food_name']); ?>" 
                                 class="cart-item-img"
                                 onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&q=80'">

                            <div class="flex-grow-1">
                                <h5 class="cart-item-title mb-1"><?php echo htmlspecialchars($row['food_name']); ?></h5>
                                <span class="text-muted small d-block mb-2">₹<?php echo number_format($price, 2); ?> each</span>
                                <span class="cart-item-price fs-6">₹<?php echo number_format($subtotal, 2); ?></span>
                            </div>

                            <div class="d-flex align-items-center gap-3">
                                
                                <!-- Quantity Controls -->
                                <div class="qty-control">
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="action" value="decrease">
                                        <input type="hidden" name="food_id" value="<?php echo $food_id; ?>">
                                        <button type="submit" class="qty-btn" title="Decrease quantity">−</button>
                                    </form>

                                    <input type="text" readonly class="qty-input" value="<?php echo $quantity; ?>">

                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="action" value="increase">
                                        <input type="hidden" name="food_id" value="<?php echo $food_id; ?>">
                                        <button type="submit" class="qty-btn" title="Increase quantity">＋</button>
                                    </form>
                                </div>

                                <!-- Remove Button -->
                                <form method="POST" class="m-0">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="food_id" value="<?php echo $food_id; ?>">
                                    <button type="submit" class="cart-remove-btn" title="Remove Item" onclick="return confirm('Remove this item?');">
                                        🗑
                                    </button>
                                </form>

                            </div>
                        </div>
                    <?php } ?>

                    <!-- Action buttons -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3">
                        <form method="POST">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-3 px-3 py-2 fw-semibold" onclick="return confirm('Clear entire cart?');">
                                🗑 Clear Cart
                            </button>
                        </form>

                        <a href="dashboard.php" class="text-decoration-none fw-bold small" style="color: #E65100;">
                            ← Continue Adding Items
                        </a>
                    </div>

                </div>

                <!-- Order Summary Card -->
                <div class="col-lg-4">
                    <div class="order-summary-box">
                        <h4 class="mb-3">Order Summary</h4>
                        
                        <div class="d-flex justify-content-between py-2 border-bottom text-muted">
                            <span>Table Number</span>
                            <strong class="text-dark">Table <?php echo htmlspecialchars($session['table_no']); ?></strong>
                        </div>

                        <div class="d-flex justify-content-between py-2 border-bottom text-muted">
                            <span>Total Items</span>
                            <strong class="text-dark"><?php echo $items_count; ?></strong>
                        </div>

                        <div class="d-flex justify-content-between py-3 border-bottom mt-2">
                            <span class="fs-5 fw-bold" style="color: #1A0C00;">Total Amount</span>
                            <span class="fs-4 fw-bold" style="color: #E65100;">₹<?php echo number_format($total, 2); ?></span>
                        </div>

                        <div class="cart-alert-kitchen py-2 px-3 small mt-3">
                            ⚡ Orders are sent directly to the kitchen display immediately.
                        </div>

                        <a href="place_order.php" class="btn-place-order text-decoration-none mt-3">
                            ✓ Confirm &amp; Place Order →
                        </a>
                    </div>
                </div>

            </div>
        <?php } ?>

    </div>

</body>
</html>
