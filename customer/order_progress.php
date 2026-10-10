<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once("../config/db.php");

if (!isset($_SESSION['session_id'])) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No active session']);
        exit();
    }
    exit('<div class="text-muted text-center py-3">No active session</div>');
}

$session_id = (int)$_SESSION['session_id'];

// Fetch latest active order
$order_query = mysqli_query($conn, "
    SELECT order_id, order_time, status
    FROM orders
    WHERE session_id = '$session_id'
    ORDER BY order_id DESC
    LIMIT 1
");

$latest_order = ($order_query && mysqli_num_rows($order_query) > 0) ? mysqli_fetch_assoc($order_query) : null;

// Handle JSON API mode
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    if (!$latest_order) {
        echo json_encode(['success' => true, 'has_order' => false]);
    } else {
        echo json_encode([
            'success' => true,
            'has_order' => true,
            'order_id' => (int)$latest_order['order_id'],
            'status' => $latest_order['status'],
            'order_time' => $latest_order['order_time']
        ]);
    }
    exit();
}

// Render HTML Stepper
if (!$latest_order) {
?>
    <div class="text-center py-4">
        <div class="fs-1 mb-2">🍽️</div>
        <h6 class="fw-bold mb-1">No Active Order</h6>
        <p class="text-muted small mb-0">Choose items from the menu to place an order.</p>
    </div>
<?php
} else {
    $status = $latest_order['status'];
    $order_id = $latest_order['order_id'];
    
    $steps = [
        'Pending'   => ['icon' => '🕐', 'title' => 'Order Received', 'desc' => 'Sent to kitchen'],
        'Preparing' => ['icon' => '👨‍🍳', 'title' => 'Preparing Food', 'desc' => 'Chefs are cooking'],
        'Ready'     => ['icon' => '🍽️', 'title' => 'Order Ready', 'desc' => 'Ready to serve'],
        'Served'    => ['icon' => '✓',  'title' => 'Served & Enjoy', 'desc' => 'Delivered to your table']
    ];

    $order_flow = ['Pending', 'Preparing', 'Ready', 'Served'];
    $current_index = array_search($status, $order_flow);
    if ($current_index === false) $current_index = 0;
?>
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <div>
            <span class="badge bg-dark">Order #<?php echo $order_id; ?></span>
        </div>
        <small class="text-muted"><?php echo date("h:i A", strtotime($latest_order['order_time'])); ?></small>
    </div>

    <div class="progress-stepper">
        <?php 
        $i = 0;
        foreach ($steps as $step_key => $step_info) {
            $is_completed = $i < $current_index;
            $is_active = $i === $current_index;
            $class = $is_completed ? 'completed' : ($is_active ? 'active' : '');
        ?>
            <div class="step-item <?php echo $class; ?>">
                <div class="step-dot">
                    <?php echo $is_completed ? '✓' : $step_info['icon']; ?>
                </div>
                <div class="step-title"><?php echo $step_info['title']; ?></div>
                <div class="step-subtitle"><?php echo $step_info['desc']; ?></div>
            </div>
        <?php 
            $i++;
        } 
        ?>
    </div>
<?php
}
?>
