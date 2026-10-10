<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$result = mysqli_query($conn, "
    SELECT menu.*, 
           COALESCE(categories.category_name, menu.category, 'Other') AS cat_name,
           COALESCE(subcategories.subcategory_name, '') AS subcat_name
    FROM menu
    LEFT JOIN categories ON menu.category_id = categories.category_id
    LEFT JOIN subcategories ON menu.subcategory_id = subcategories.subcategory_id
    ORDER BY menu.food_id DESC
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
    <title>Food Menu Management - Nimbus Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .admin-food-thumb {
            width: 56px;
            height: 56px;
            border-radius: var(--nb-radius-md);
            object-fit: cover;
        }
        .stock-toggle-btn {
            border-radius: var(--nb-radius-full);
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .stock-toggle-btn.in-stock {
            background: rgba(34, 197, 94, 0.18);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .stock-toggle-btn.out-stock {
            background: rgba(239, 68, 68, 0.18);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
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
                <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    ← Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-4 py-4">
        
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h1 class="display-6 fw-bold mb-1">🍔 Food Menu Catalog</h1>
                <p class="text-muted small mb-0">Manage dishes, pricing, inventory availability, and descriptions</p>
            </div>
            <a href="add_food.php" class="btn btn-dark fw-bold rounded-3 px-4 py-2 shadow-sm">
                ＋ Add New Dish
            </a>
        </div>

        <!-- Filter and Search Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="search-input-wrapper w-100 max-w-100" style="max-width: 100%;">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="adminFoodSearch" placeholder="Search dish by name or category...">
                    </div>
                </div>
                <div class="col-md-6 text-md-end text-muted small">
                    Showing <strong id="itemCountDisplay" class="text-white"><?php echo mysqli_num_rows($result); ?></strong> total dishes
                </div>
            </div>
        </div>

        <!-- Food Items Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="adminFoodTable">
                    <thead>
                        <tr>
                            <th class="py-3 px-4">ID</th>
                            <th class="py-3">Dish</th>
                            <th class="py-3">Category</th>
                            <th class="py-3">Price</th>
                            <th class="py-3">Availability</th>
                            <th class="py-3 px-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        mysqli_data_seek($result, 0);
                        while ($dish = mysqli_fetch_assoc($result)) {
                            $d_id = (int)$dish['food_id'];
                            $d_name = htmlspecialchars($dish['food_name']);
                            $d_price = (float)$dish['price'];
                            $d_cat = htmlspecialchars($dish['cat_name']);
                            $d_subcat = htmlspecialchars($dish['subcat_name']);
                            $d_avail = $dish['availability'];
                            $d_img = !empty($dish['image']) ? $dish['image'] : 'bucketchicken.jpg';
                        ?>
                            <tr class="food-row" data-name="<?php echo strtolower($d_name); ?>" data-cat="<?php echo strtolower($d_cat . ' ' . $d_subcat); ?>">
                                <td class="py-3 px-4 fw-bold text-muted">#<?php echo $d_id; ?></td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="../assets/images/<?php echo $d_img; ?>" 
                                             alt="<?php echo $d_name; ?>" 
                                             class="admin-food-thumb"
                                             onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&q=80'">
                                        <div>
                                            <div class="fw-bold text-white"><?php echo $d_name; ?></div>
                                            <?php if (!empty($dish['description'])) { ?>
                                                <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;">
                                                    <?php echo htmlspecialchars($dish['description']); ?>
                                                </small>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-dark-subtle text-light border border-secondary px-2 py-1"><?php echo $d_cat; ?></span>
                                    <?php if (!empty($d_subcat)) { ?>
                                        <span class="badge bg-secondary-subtle text-secondary px-2 py-1"><?php echo $d_subcat; ?></span>
                                    <?php } ?>
                                </td>
                                <td class="py-3 fw-bold fs-6 text-warning">₹<?php echo number_format($d_price, 2); ?></td>
                                <td class="py-3">
                                    <button type="button" 
                                            class="stock-toggle-btn <?php echo $d_avail == 'Available' ? 'in-stock' : 'out-stock'; ?>"
                                            onclick="toggleStock(<?php echo $d_id; ?>, this)">
                                        ● <?php echo $d_avail; ?>
                                    </button>
                                </td>
                                <td class="py-3 px-4 text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="edit_food.php?id=<?php echo $d_id; ?>" class="btn btn-outline-light btn-sm rounded-3 px-3">
                                            ✏ Edit
                                        </a>
                                        <a href="delete_food.php?id=<?php echo $d_id; ?>" class="btn btn-outline-danger btn-sm rounded-3 px-2" onclick="return confirm('Delete this food item permanently?');" title="Delete Dish">
                                            🗑
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script src="../assets/js/nimbus.js"></script>
    <script>
        // Real-time table search filter
        document.getElementById('adminFoodSearch').addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            let count = 0;
            document.querySelectorAll('.food-row').forEach(row => {
                const name = row.dataset.name || '';
                const cat = row.dataset.cat || '';
                if (!query || name.includes(query) || cat.includes(query)) {
                    row.style.display = '';
                    count++;
                } else {
                    row.style.display = 'none';
                }
            });
            document.getElementById('itemCountDisplay').innerText = count;
        });

        // Toggle Stock Availability via AJAX
        async function toggleStock(id, btn) {
            try {
                const res = await fetch(`toggle_stock.php?id=${id}`);
                const data = await res.json();
                if (data.success) {
                    const isAvail = data.new_status === 'Available';
                    btn.className = `stock-toggle-btn ${isAvail ? 'in-stock' : 'out-stock'}`;
                    btn.innerHTML = `● ${data.new_status}`;
                    Nimbus.toast(`Availability set to: ${data.new_status}`, 'success');
                }
            } catch(e) {
                Nimbus.toast('Unable to update availability', 'error');
            }
        }
    </script>
</body>
</html>
