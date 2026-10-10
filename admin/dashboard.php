<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$admin_name = htmlspecialchars($_SESSION['admin']);

// Metrics
$total_items = 0;
$in_stock = 0;
$out_stock = 0;
$total_orders = 0;
$total_revenue = 0;

$items_q = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(CASE WHEN availability='Available' THEN 1 ELSE 0 END) AS available FROM menu");
if ($items_q) {
    $row = mysqli_fetch_assoc($items_q);
    $total_items = (int)$row['total'];
    $in_stock = (int)$row['available'];
    $out_stock = $total_items - $in_stock;
}

$orders_q = mysqli_query($conn, "SELECT COUNT(*) AS total_orders FROM orders");
if ($orders_q) $total_orders = (int)mysqli_fetch_assoc($orders_q)['total_orders'];

$rev_q = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS rev FROM payments WHERE payment_status='Paid'");
if ($rev_q) $total_revenue = (float)mysqli_fetch_assoc($rev_q)['rev'];

// Recent Orders
$recent_orders = mysqli_query($conn, "
    SELECT orders.order_id, orders.order_time, orders.status, dining_session.table_no,
           COALESCE(SUM(order_items.subtotal), 0) AS total_amount
    FROM orders
    INNER JOIN dining_session ON orders.session_id = dining_session.session_id
    LEFT JOIN order_items ON orders.order_id = order_items.order_id
    GROUP BY orders.order_id, orders.order_time, orders.status, dining_session.table_no
    ORDER BY orders.order_id DESC
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Nimbus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .admin-kpi-card {
            background: #131b2e;
            border: 1px solid #23324d;
            border-radius: var(--nb-radius-lg);
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            height: 100%;
        }
        .admin-action-btn {
            background: #131b2e;
            border: 1px solid #23324d;
            border-radius: var(--nb-radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: #f8fafc;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
        }
        .admin-action-btn:hover {
            border-color: var(--nb-primary);
            background: #1a253c;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.15);
            color: #ffffff;
        }
    </style>
</head>
<body class="admin-dark-body">

    <!-- Top Navigation -->
    <nav class="nimbus-navbar">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand">
                ⚙️ Nimbus Admin
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white small">Welcome, <strong><?php echo $admin_name; ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-4 py-4">
        
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h1 class="display-6 fw-bold mb-1">Executive Dashboard</h1>
                <p class="text-muted small mb-0">Overview of restaurant catalog, orders, and total revenue</p>
            </div>
            <a href="add_food.php" class="btn btn-dark fw-bold rounded-3 px-4 py-2 shadow-sm">
                ＋ Add New Dish
            </a>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="row g-3 mb-4">
            
            <div class="col-sm-6 col-lg-3">
                <div class="admin-kpi-card">
                    <span class="text-muted small fw-bold text-uppercase">Total Food Menu</span>
                    <div class="display-6 fw-bold text-white my-1"><?php echo $total_items; ?></div>
                    <span class="text-muted small"><?php echo $in_stock; ?> in stock • <?php echo $out_stock; ?> out of stock</span>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="admin-kpi-card">
                    <span class="text-muted small fw-bold text-uppercase">Total Orders Placed</span>
                    <div class="display-6 fw-bold text-white my-1"><?php echo $total_orders; ?></div>
                    <span class="text-success small">Across all dining sessions</span>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="admin-kpi-card">
                    <span class="text-muted small fw-bold text-uppercase">Total Gross Revenue</span>
                    <div class="display-6 fw-bold text-success my-1">₹<?php echo number_format($total_revenue, 2); ?></div>
                    <span class="text-muted small">Settled & collected payments</span>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="admin-kpi-card">
                    <span class="text-muted small fw-bold text-uppercase">Floor Capacity</span>
                    <div class="display-6 fw-bold text-info my-1">20</div>
                    <span class="text-muted small">Standard dining tables</span>
                </div>
            </div>

        </div>

        <!-- Quick Launch Navigation -->
        <div class="row g-3 mb-4">
            
            <div class="col-md-6 col-lg-3">
                <a href="view_food.php" class="admin-action-btn">
                    <div class="fs-2">🍔</div>
                    <div>
                        <div class="fw-bold">Manage Menu</div>
                        <div class="text-muted small">Edit items, prices, stock</div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-3">
                <a href="add_food.php" class="admin-action-btn">
                    <div class="fs-2">➕</div>
                    <div>
                        <div class="fw-bold">Add New Dish</div>
                        <div class="text-muted small">Upload image & set price</div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-3">
                <a href="../kitchen/dashboard.php" class="admin-action-btn" target="_blank">
                    <div class="fs-2">👨‍🍳</div>
                    <div>
                        <div class="fw-bold">Kitchen KDS ↗</div>
                        <div class="text-muted small">Live tickets & cooking queue</div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-3">
                <a href="../counter/dashboard.php" class="admin-action-btn" target="_blank">
                    <div class="fs-2">💰</div>
                    <div>
                        <div class="fw-bold">Counter POS ↗</div>
                        <div class="text-muted small">Floor tables & billing</div>
                    </div>
                </a>
            </div>

        </div>

        <!-- Recent Orders -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-white">Recent Orders</h5>
                <a href="../kitchen/completed_orders.php" class="text-warning small text-decoration-none fw-semibold">View All in KDS →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="py-3 px-4">Order ID</th>
                            <th class="py-3">Table</th>
                            <th class="py-3">Order Time</th>
                            <th class="py-3">Total Amount</th>
                            <th class="py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ord = mysqli_fetch_assoc($recent_orders)) { ?>
                            <tr>
                                <td class="py-3 px-4 fw-bold text-white">#<?php echo (int)$ord['order_id']; ?></td>
                                <td class="py-3"><span class="badge bg-dark-subtle text-light border border-secondary">Table <?php echo htmlspecialchars($ord['table_no']); ?></span></td>
                                <td class="py-3 text-muted small"><?php echo date("h:i A, M d", strtotime($ord['order_time'])); ?></td>
                                <td class="py-3 fw-bold text-warning">₹<?php echo number_format((float)$ord['total_amount'], 2); ?></td>
                                <td class="py-3">
                                    <span class="badge <?php echo $ord['status'] == 'Served' ? 'bg-success' : ($ord['status'] == 'Preparing' ? 'bg-warning text-dark' : 'bg-secondary'); ?> rounded-pill px-3 py-1">
                                        <?php echo htmlspecialchars($ord['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>
