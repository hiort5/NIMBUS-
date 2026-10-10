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
    SELECT session_status
    FROM dining_session
    WHERE session_id='$session_id'
");

if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: index.php");
    exit();
}

$session = mysqli_fetch_assoc($result);

if ($session['session_status'] != 'Dining') {
    header("Location: dashboard.php");
    exit();
}

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

$total = 0;
$items = [];

foreach ($_SESSION['cart'] as $food_id => $quantity) {
    $food_id = (int)$food_id;
    $quantity = (int)$quantity;

    if ($quantity <= 0) continue;

    $result = mysqli_query($conn, "
        SELECT food_id, price
        FROM menu
        WHERE food_id='$food_id'
        AND availability='Available'
    ");

    if (!$result || mysqli_num_rows($result) == 0) {
        die("One or more food items are no longer available. Please return to the menu.");
    }

    $food = mysqli_fetch_assoc($result);
    $subtotal = (float)$food['price'] * $quantity;
    $total += $subtotal;

    $items[] = [
        'food_id' => $food_id,
        'quantity' => $quantity,
        'subtotal' => $subtotal
    ];
}

if (empty($items) || $total <= 0) {
    header("Location: cart.php");
    exit();
}

mysqli_begin_transaction($conn);

try {
    $order_query = mysqli_query($conn, "
        INSERT INTO orders(session_id, status)
        VALUES('$session_id', 'Pending')
    ");

    if (!$order_query) {
        throw new Exception("Order creation failed: " . mysqli_error($conn));
    }

    $order_id = mysqli_insert_id($conn);

    foreach ($items as $item) {
        $f_id = $item['food_id'];
        $qty = $item['quantity'];
        $sub = $item['subtotal'];

        $item_query = mysqli_query($conn, "
            INSERT INTO order_items (order_id, food_id, quantity, subtotal)
            VALUES ('$order_id', '$f_id', '$qty', '$sub')
        ");

        if (!$item_query) {
            throw new Exception("Order item insertion failed: " . mysqli_error($conn));
        }
    }

    $update = mysqli_query($conn, "
        UPDATE dining_session
        SET total_amount = total_amount + $total
        WHERE session_id = '$session_id'
    ");

    if (!$update) {
        throw new Exception("Session total update failed: " . mysqli_error($conn));
    }

    mysqli_commit($conn);

    unset($_SESSION['cart']);
    $_SESSION['last_order'] = $order_id;

    header("Location: dashboard.php#orders");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    die($e->getMessage());
}
?>
