<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Order ID.");
}

$order_id = (int)$_GET['id'];

$order_query = mysqli_query($conn, "
    SELECT orders.order_id, orders.session_id, orders.order_time, orders.status, dining_session.table_no
    FROM orders
    INNER JOIN dining_session ON orders.session_id = dining_session.session_id
    WHERE orders.order_id='$order_id'
    LIMIT 1
");

if (!$order_query || mysqli_num_rows($order_query) == 0) {
    die("Order not found.");
}

$order = mysqli_fetch_assoc($order_query);
$status = $order['status'];

$items_query = mysqli_query($conn, "
    SELECT order_items.quantity, order_items.subtotal, menu.food_name, menu.price
    FROM order_items
    INNER JOIN menu ON order_items.food_id = menu.food_id
    WHERE order_items.order_id='$order_id'
    ORDER BY menu.food_name
");

$status_colors = [
    'Pending' => 'bg-secondary',
    'Preparing' => 'bg-warning text-dark',
    'Ready' => 'bg-info text-dark',
    'Served' => 'bg-success'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order_id; ?> - Kitchen KDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
</head>
<body class="kds-body">

    <header class="kds-navbar py-3">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand fs-4 text-white">
                👨‍🍳 Nimbus KDS
            </a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                ← Back to Live Queue
            </a>
        </div>
    </header>

    <main class="container py-5" style="max-width: 760px;">
        
        <div class="kds-ticket p-4 rounded-4 shadow-lg mb-4">
            
            <div class="d-flex justify-content-between align-items-start pb-3 border-bottom border-secondary mb-3">
                <div>
                    <h2 class="display-6 fw-bold text-white mb-1">Table <?php echo htmlspecialchars($order['table_no']); ?></h2>
                    <div class="text-slate-400" style="color: #94a3b8;">
                        Order #<?php echo $order_id; ?> • Placed at <?php echo date("h:i A, M d", strtotime($order['order_time'])); ?>
                    </div>
                </div>
                <span class="badge <?php echo $status_colors[$status] ?? 'bg-secondary'; ?> fs-6 px-3 py-2 rounded-pill fw-bold">
                    <?php echo htmlspecialchars($status); ?>
                </span>
            </div>

            <h5 class="fw-bold text-white mb-3">Cook Checklist</h5>

            <div class="p-3 rounded-3 mb-4" style="background: #0f172a;">
                <?php 
                $total = 0;
                while ($item = mysqli_fetch_assoc($items_query)) { 
                    $total += $item['subtotal'];
                ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-slate-700">
                        <label class="kds-item-check mb-0 text-white fs-5">
                            <input type="checkbox" onchange="this.parentElement.style.opacity = this.checked ? '0.4' : '1'; this.parentElement.style.textDecoration = this.checked ? 'line-through' : 'none';">
                            <span><?php echo htmlspecialchars($item['food_name']); ?></span>
                        </label>
                        <span class="badge bg-warning text-dark fs-6 fw-bold">× <?php echo $item['quantity']; ?></span>
                    </div>
                <?php } ?>
            </div>

            <h5 class="fw-bold text-white mb-3">Change Ticket Status</h5>
            
            <div class="d-flex gap-2 flex-wrap">
                <a href="update_status.php?id=<?php echo $order_id; ?>&status=Preparing" class="btn <?php echo $status == 'Preparing' ? 'btn-warning text-dark fw-bold disabled' : 'btn-outline-warning'; ?> py-2 px-4 rounded-3">
                    👨‍🍳 Preparing
                </a>
                <a href="update_status.php?id=<?php echo $order_id; ?>&status=Ready" class="btn <?php echo $status == 'Ready' ? 'btn-info text-dark fw-bold disabled' : 'btn-outline-info'; ?> py-2 px-4 rounded-3">
                    🍽️ Ready to Serve
                </a>
                <a href="update_status.php?id=<?php echo $order_id; ?>&status=Served" class="btn <?php echo $status == 'Served' ? 'btn-success fw-bold disabled' : 'btn-outline-success'; ?> py-2 px-4 rounded-3" onclick="return confirm('Mark this order as Served?');">
                    ✓ Served
                </a>
            </div>

        </div>

    </main>

</body>
</html>
