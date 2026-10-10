<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

$result = mysqli_query($conn, "
    SELECT
        orders.order_id,
        orders.order_time,
        orders.status,
        dining_session.table_no,
        COALESCE(SUM(order_items.subtotal), 0) AS total_amount
    FROM orders
    INNER JOIN dining_session ON orders.session_id = dining_session.session_id
    LEFT JOIN order_items ON orders.order_id = order_items.order_id
    WHERE orders.status = 'Served'
    GROUP BY orders.order_id, orders.order_time, orders.status, dining_session.table_no
    ORDER BY orders.order_time DESC
");

if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Orders - Kitchen KDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
</head>
<body class="kds-body">

    <header class="kds-navbar py-3">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand fs-4 text-white">
                👨‍🍳 Nimbus KDS
            </a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                ← Back to Live Queue
            </a>
        </div>
    </header>

    <main class="container-fluid px-4 py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="display-6 fw-bold text-white mb-1">✓ Completed Orders History</h2>
                <p class="text-slate-400 small mb-0" style="color: #94a3b8;">Record of all served orders</p>
            </div>
        </div>

        <?php if (mysqli_num_rows($result) == 0) { ?>
            <div class="text-center py-5" style="background: #1e293b; border-radius: 20px; border: 1px solid #334155;">
                <div class="display-4 mb-2">📦</div>
                <h4 class="fw-bold text-white mb-2">No Completed Orders Yet</h4>
                <p class="text-slate-400 mb-0" style="color: #94a3b8;">Orders marked as 'Served' will appear here.</p>
            </div>
        <?php } else { ?>
            <div class="card border-0 rounded-4 overflow-hidden shadow-lg" style="background: #1e293b; border: 1px solid #334155 !important;">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                        <thead style="background: #0f172a;">
                            <tr>
                                <th class="py-3 px-4">Order ID</th>
                                <th class="py-3">Table</th>
                                <th class="py-3">Order Time</th>
                                <th class="py-3">Total Amount</th>
                                <th class="py-3">Status</th>
                                <th class="py-3 px-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                <tr>
                                    <td class="py-3 px-4 fw-bold">#<?php echo (int)$row['order_id']; ?></td>
                                    <td class="py-3"><span class="badge bg-warning text-dark px-3 py-1 rounded-pill">Table <?php echo htmlspecialchars($row['table_no']); ?></span></td>
                                    <td class="py-3 text-slate-300" style="color: #cbd5e1;"><?php echo date("h:i A, M d, Y", strtotime($row['order_time'])); ?></td>
                                    <td class="py-3 fw-bold">₹<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                                    <td class="py-3"><span class="badge bg-success px-3 py-1 rounded-pill">✓ Served</span></td>
                                    <td class="py-3 px-4 text-end">
                                        <a href="order_details.php?id=<?php echo (int)$row['order_id']; ?>" class="btn btn-outline-light btn-sm rounded-3">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>

    </main>

</body>
</html>
