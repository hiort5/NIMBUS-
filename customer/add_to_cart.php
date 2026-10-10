<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['session_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please select a table.']);
    exit();
}

$food_id = (int)($_POST['food_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$action = $_POST['action'] ?? 'add';

if ($food_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item ID.']);
    exit();
}

$check = mysqli_query($conn, "
    SELECT food_id, food_name, price
    FROM menu
    WHERE food_id='$food_id'
    AND availability='Available'
    LIMIT 1
");

if (!$check || mysqli_num_rows($check) == 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Food item is currently unavailable.']);
    exit();
}

$food_item = mysqli_fetch_assoc($check);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($action === 'set') {
    if ($quantity > 0) {
        $_SESSION['cart'][$food_id] = $quantity;
    } else {
        unset($_SESSION['cart'][$food_id]);
    }
} elseif ($action === 'remove') {
    unset($_SESSION['cart'][$food_id]);
} else {
    // Default add
    $current_qty = $_SESSION['cart'][$food_id] ?? 0;
    $_SESSION['cart'][$food_id] = $current_qty + max(1, $quantity);
}

// If requested as JSON AJAX
if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false || isset($_POST['json'])) {
    $total_count = array_sum($_SESSION['cart']);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Added ' . $food_item['food_name'] . ' to cart',
        'food_name' => $food_item['food_name'],
        'cart_count' => $total_count
    ]);
    exit();
}

// Otherwise output the updated cart sidebar HTML
include("cart_sidebar.php");
?>
