<?php
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
    WHERE orders.status != 'Served'
    GROUP BY orders.order_id, orders.order_time, orders.status, dining_session.table_no
    ORDER BY orders.order_time ASC
");

if (!$result) {
    echo '<div class="alert alert-danger">Unable to load orders: ' . mysqli_error($conn) . '</div>';
    exit();
}

if (mysqli_num_rows($result) == 0) {
?>
    <div class="text-center py-5" style="background: #1e293b; border-radius: 20px; border: 1px solid #334155;">
        <div class="display-3 mb-2">👨‍🍳</div>
        <h3 class="fw-bold text-white mb-2">All Caught Up!</h3>
        <p class="text-slate-400 mb-0" style="color: #94a3b8;">There are no active orders waiting in the kitchen queue right now.</p>
    </div>
<?php
    exit();
}
?>

<div class="kds-grid">
    <?php
    while ($order = mysqli_fetch_assoc($result)) {
        $order_id = (int)$order['order_id'];
        $table_no = htmlspecialchars($order['table_no']);
        $order_time = $order['order_time'];
        $status = $order['status'];
        $total = (float)$order['total_amount'];

        // Calculate time elapsed
        $created_timestamp = strtotime($order_time);
        $minutes_elapsed = round((time() - $created_timestamp) / 60);
        if ($minutes_elapsed < 0) $minutes_elapsed = 0;

        $time_badge_class = 'kds-time-green';
        if ($minutes_elapsed >= 5 && $minutes_elapsed < 15) {
            $time_badge_class = 'kds-time-amber';
        } elseif ($minutes_elapsed >= 15) {
            $time_badge_class = 'kds-time-red';
        }

        // Fetch items for this order
        $items_query = mysqli_query($conn, "
            SELECT order_items.quantity, menu.food_name
            FROM order_items
            INNER JOIN menu ON order_items.food_id = menu.food_id
            WHERE order_items.order_id = '$order_id'
        ");
    ?>
        <div class="kds-ticket-wrapper" data-order-id="<?php echo $order_id; ?>" data-status="<?php echo $status; ?>">
            <div class="kds-ticket">
                
                <!-- Ticket Header -->
                <div class="kds-ticket-header">
                    <div>
                        <div class="kds-ticket-table">Table <?php echo $table_no; ?></div>
                        <div class="small text-slate-400" style="color: #94a3b8;">Order #<?php echo $order_id; ?></div>
                    </div>
                    <div class="text-end">
                        <span class="kds-ticket-time <?php echo $time_badge_class; ?>">
                            ⏱ <?php echo $minutes_elapsed; ?>m ago
                        </span>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                            <?php echo date("h:i A", $created_timestamp); ?>
                        </div>
                    </div>
                </div>

                <!-- Items List -->
                <div class="kds-items-list">
                    <?php while ($item = mysqli_fetch_assoc($items_query)) { ?>
                        <div class="kds-item-row">
                            <label class="kds-item-check mb-0">
                                <input type="checkbox" onchange="this.parentElement.style.opacity = this.checked ? '0.4' : '1'; this.parentElement.style.textDecoration = this.checked ? 'line-through' : 'none';">
                                <span><?php echo htmlspecialchars($item['food_name']); ?></span>
                            </label>
                            <span class="kds-item-qty">× <?php echo $item['quantity']; ?></span>
                        </div>
                    <?php } ?>
                </div>

                <!-- Ticket Action Footer -->
                <div class="kds-ticket-footer d-flex justify-content-between align-items-center gap-2">
                    
                    <?php if ($status == 'Pending') { ?>
                        <button type="button" class="btn btn-warning w-100 py-2 fw-bold rounded-3 text-dark" onclick="updateKdsStatus(<?php echo $order_id; ?>, 'Preparing')">
                            👨‍🍳 Start Cooking
                        </button>
                    <?php } elseif ($status == 'Preparing') { ?>
                        <button type="button" class="btn btn-info w-100 py-2 fw-bold rounded-3 text-dark" onclick="updateKdsStatus(<?php echo $order_id; ?>, 'Ready')">
                            🍽️ Mark as Ready
                        </button>
                    <?php } elseif ($status == 'Ready') { ?>
                        <button type="button" class="btn btn-success w-100 py-2 fw-bold rounded-3" onclick="updateKdsStatus(<?php echo $order_id; ?>, 'Served')">
                            ✓ Mark as Served
                        </button>
                    <?php } ?>

                    <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary btn-sm p-2 rounded-3" title="View Ticket Details">
                        🔍
                    </a>

                </div>

            </div>
        </div>
    <?php } ?>
</div>
