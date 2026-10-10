<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

$tables_query = mysqli_query($conn, "
    SELECT DISTINCT dining_session.table_no
    FROM dining_session
    INNER JOIN orders ON dining_session.session_id = orders.session_id
    WHERE dining_session.session_status != 'Completed'
    AND orders.status != 'Served'
    ORDER BY dining_session.table_no ASC
");

if (!$tables_query) {
    die("Database Error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen - Table View - Nimbus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
</head>
<body class="kds-body">

    <!-- Top Navigation -->
    <header class="kds-navbar py-3">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand fs-4 text-white">
                👨‍🍳 Nimbus KDS
            </a>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    ← All Live Tickets
                </a>
                <a href="completed_orders.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    ✓ Completed Orders
                </a>
            </div>
        </div>
    </header>

    <main class="container-fluid px-4 py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="display-6 fw-bold text-white mb-1">🍽️ Orders by Table</h2>
                <p class="text-slate-400 small mb-0" style="color: #94a3b8;">Active orders consolidated by dining table</p>
            </div>
        </div>

        <?php if (mysqli_num_rows($tables_query) == 0) { ?>
            <div class="text-center py-5" style="background: #1e293b; border-radius: 20px; border: 1px solid #334155;">
                <div class="display-4 mb-2">🍽️</div>
                <h4 class="fw-bold text-white mb-2">No Active Table Orders</h4>
                <p class="text-slate-400 mb-0" style="color: #94a3b8;">No tables currently have pending or preparing items.</p>
            </div>
        <?php } else { ?>
            <div class="row g-4">
                <?php
                while ($table = mysqli_fetch_assoc($tables_query)) {
                    $table_no = htmlspecialchars($table['table_no']);
                    $table_no_safe = mysqli_real_escape_string($conn, $table_no);

                    $orders_q = mysqli_query($conn, "
                        SELECT orders.order_id, orders.order_time, orders.status
                        FROM orders
                        INNER JOIN dining_session ON orders.session_id = dining_session.session_id
                        WHERE dining_session.table_no = '$table_no_safe'
                        AND dining_session.session_status != 'Completed'
                        AND orders.status != 'Served'
                        ORDER BY orders.order_time ASC
                    ");
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="kds-ticket h-100">
                            
                            <div class="kds-ticket-header">
                                <div class="kds-ticket-table text-warning">Table <?php echo $table_no; ?></div>
                                <span class="badge bg-secondary px-3 py-2 rounded-pill"><?php echo mysqli_num_rows($orders_q); ?> Active Orders</span>
                            </div>

                            <div class="p-3 flex-grow-1">
                                <?php while ($ord = mysqli_fetch_assoc($orders_q)) { 
                                    $o_id = (int)$ord['order_id'];
                                    $o_status = $ord['status'];

                                    $items_q = mysqli_query($conn, "
                                        SELECT menu.food_name, order_items.quantity
                                        FROM order_items
                                        INNER JOIN menu ON order_items.food_id = menu.food_id
                                        WHERE order_items.order_id = '$o_id'
                                    ");
                                ?>
                                    <div class="p-3 mb-3 rounded-3" style="background: #0f172a; border: 1px solid #334155;">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-slate-800">
                                            <strong class="text-white">Order #<?php echo $o_id; ?></strong>
                                            <span class="badge <?php echo $o_status == 'Preparing' ? 'bg-warning text-dark' : ($o_status == 'Ready' ? 'bg-info text-dark' : 'bg-secondary'); ?>">
                                                <?php echo htmlspecialchars($o_status); ?>
                                            </span>
                                        </div>

                                        <?php while ($it = mysqli_fetch_assoc($items_q)) { ?>
                                            <div class="d-flex justify-content-between text-slate-300 py-1" style="color: #cbd5e1; font-size: 14px;">
                                                <span><?php echo htmlspecialchars($it['food_name']); ?></span>
                                                <strong class="text-warning">× <?php echo $it['quantity']; ?></strong>
                                            </div>
                                        <?php } ?>

                                        <div class="mt-3 pt-2 border-top border-slate-800 d-flex justify-content-between align-items-center">
                                            <a href="order_details.php?id=<?php echo $o_id; ?>" class="btn btn-outline-light btn-sm py-1 px-3 rounded-3">
                                                Manage Order →
                                            </a>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

    </main>

</body>
</html>
