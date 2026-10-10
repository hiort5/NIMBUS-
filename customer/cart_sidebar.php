<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once("../config/db.php");

$cart_total = 0;
$cart_count = 0;
$cart_items = [];

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $c_food_id => $c_qty) {
        $c_food_id = (int)$c_food_id;
        $c_qty = (int)$c_qty;
        if ($c_qty <= 0) continue;

        $q = mysqli_query($conn, "SELECT food_id, food_name, price, image FROM menu WHERE food_id='$c_food_id' AND availability='Available' LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $f_row = mysqli_fetch_assoc($q);
            $sub = (float)$f_row['price'] * $c_qty;
            $cart_total += $sub;
            $cart_count += $c_qty;
            $cart_items[] = [
                'id' => $c_food_id,
                'name' => $f_row['food_name'],
                'price' => (float)$f_row['price'],
                'image' => $f_row['image'],
                'qty' => $c_qty,
                'subtotal' => $sub
            ];
        }
    }
}
?>

<?php if (empty($cart_items)) { ?>
    <div class="text-center py-4">
        <div class="fs-1 mb-2">🛒</div>
        <h6 class="fw-bold mb-1">Your Cart is Empty</h6>
        <p class="text-muted small mb-0">Add delicious food from the menu to get started.</p>
    </div>
<?php } else { ?>
    <div class="cart-items-preview" style="max-height: 280px; overflow-y: auto;">
        <?php foreach ($cart_items as $c_item) { ?>
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div class="me-2 text-truncate" style="max-width: 170px;">
                    <div class="fw-bold small text-truncate"><?php echo htmlspecialchars($c_item['name']); ?></div>
                    <small class="text-muted">₹<?php echo number_format($c_item['price'], 2); ?> × <?php echo $c_item['qty']; ?></small>
                </div>
                <div class="fw-bold text-dark small text-nowrap">
                    ₹<?php echo number_format($c_item['subtotal'], 2); ?>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
        <span class="text-muted small">Total (<?php echo $cart_count; ?> items)</span>
        <span class="fs-5 fw-bold text-dark">₹<?php echo number_format($cart_total, 2); ?></span>
    </div>

    <a href="cart.php" class="btn btn-dark w-100 mt-3 py-2 fw-bold rounded-3 shadow-sm">
        Review Cart & Checkout →
    </a>
<?php } ?>
