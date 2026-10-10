<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// Load Categories
$categories = [];
$cat_res = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name");
if ($cat_res) {
    while ($c = mysqli_fetch_assoc($cat_res)) {
        $categories[] = $c;
    }
}

// Load Subcategories
$subcategories = [];
$subcat_res = mysqli_query($conn, "SELECT subcategory_id, category_id, subcategory_name FROM subcategories ORDER BY subcategory_name");
if ($subcat_res) {
    while ($sc = mysqli_fetch_assoc($subcat_res)) {
        $subcategories[] = $sc;
    }
}

if (isset($_POST['add_food'])) {
    $food_name = trim($_POST['food_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $subcategory_id = (int)($_POST['subcategory_id'] ?? 0);
    $price = $_POST['price'] ?? '';
    $description = trim($_POST['description'] ?? '');

    // Resolve Category Name
    $category_name = "Food";
    foreach ($categories as $c) {
        if ($c['category_id'] == $category_id) {
            $category_name = $c['category_name'];
            break;
        }
    }

    if ($food_name == '') {
        $message = "Food name is required.";
        $message_type = "danger";
    } elseif ($category_id <= 0) {
        $message = "Please select a category.";
        $message_type = "danger";
    } elseif (!is_numeric($price) || $price <= 0) {
        $message = "Please enter a valid price.";
        $message_type = "danger";
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] != UPLOAD_ERR_OK) {
        $message = "Please choose a dish photo.";
        $message_type = "danger";
    } else {
        $image_name = $_FILES['image']['name'];
        $temp_name = $_FILES['image']['tmp_name'];
        $image_size = $_FILES['image']['size'];

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
        $extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowed_extensions)) {
            $message = "Only JPG, JPEG, PNG, WEBP and JFIF images are allowed.";
            $message_type = "danger";
        } elseif ($image_size > 5 * 1024 * 1024) {
            $message = "Image size must be less than 5 MB.";
            $message_type = "danger";
        } else {
            $new_image_name = time() . "_" . uniqid() . "." . $extension;
            $upload_path = "../assets/images/" . $new_image_name;

            if (!move_uploaded_file($temp_name, $upload_path)) {
                $message = "Unable to save uploaded image.";
                $message_type = "danger";
            } else {
                $price_value = (float)$price;
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO menu (food_name, category, category_id, subcategory_id, price, description, image, availability)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Available')
                ");

                if ($stmt) {
                    $sub_id_val = ($subcategory_id > 0) ? $subcategory_id : null;
                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssiidss",
                        $food_name,
                        $category_name,
                        $category_id,
                        $sub_id_val,
                        $price_value,
                        $description,
                        $new_image_name
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Dish '$food_name' added successfully!";
                        $message_type = "success";
                    } else {
                        unlink($upload_path);
                        $message = "Database error: " . mysqli_stmt_error($stmt);
                        $message_type = "danger";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    unlink($upload_path);
                    $message = "Database error: " . mysqli_error($conn);
                    $message_type = "danger";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Food Item - Nimbus Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .form-container-card {
            max-width: 740px;
            margin: 30px auto;
            background: #131b2e;
            border-radius: var(--nb-radius-xl);
            border: 1px solid #23324d;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 36px 32px;
        }
        .image-preview-box {
            width: 140px;
            height: 140px;
            border-radius: var(--nb-radius-md);
            object-fit: cover;
            border: 2px dashed #334155;
            padding: 4px;
            background: #0f172a;
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
            <a href="view_food.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                ← View Food Catalog
            </a>
        </div>
    </nav>

    <main class="container py-4">
        
        <div class="form-container-card">
            
            <div class="mb-4">
                <h2 class="display-6 fw-bold mb-1">＋ Add New Dish</h2>
                <p class="text-muted small mb-0">Create a new culinary item for the digital restaurant menu</p>
            </div>

            <?php if (!empty($message)) { ?>
                <div class="alert alert-<?php echo $message_type; ?> rounded-3 py-2 px-3 mb-4">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php } ?>

            <form method="POST" enctype="multipart/form-data">
                
                <!-- Food Name -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Dish Name</label>
                    <input type="text" name="food_name" class="form-control rounded-3 py-2" placeholder="e.g. Crispy Zinger Burger" value="<?php echo htmlspecialchars($_POST['food_name'] ?? ''); ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    
                    <!-- Category -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">Category</label>
                        <select name="category_id" id="categorySelect" class="form-select rounded-3 py-2" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat) { ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Subcategory -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">Subcategory / Tag</label>
                        <select name="subcategory_id" id="subcategorySelect" class="form-select rounded-3 py-2">
                            <option value="">-- Optional Subcategory --</option>
                            <?php foreach ($subcategories as $sc) { ?>
                                <option value="<?php echo $sc['subcategory_id']; ?>" data-cat="<?php echo $sc['category_id']; ?>">
                                    <?php echo htmlspecialchars($sc['subcategory_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                </div>

                <!-- Price & Description -->
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-uppercase text-muted">Price (₹)</label>
                        <input type="number" step="0.01" min="0.01" name="price" class="form-control rounded-3 py-2" placeholder="e.g. 199.00" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold small text-uppercase text-muted">Description / Ingredients</label>
                        <input type="text" name="description" class="form-control rounded-3 py-2" placeholder="e.g. Juicy crispy chicken patty with lettuce and spicy mayo" value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Image Upload with Live Preview -->
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">Dish Photo</label>
                    <div class="d-flex align-items-center gap-3">
                        <img id="foodPreviewImg" src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=150&q=80" alt="Preview" class="image-preview-box">
                        <div class="flex-grow-1">
                            <input type="file" name="image" id="foodImageInput" class="form-control rounded-3" accept=".jpg,.jpeg,.png,.webp,.jfif" required>
                            <small class="text-muted d-block mt-1">Accepted: JPG, JPEG, PNG, WEBP, JFIF (Max 5MB)</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 pt-2">
                    <button type="submit" name="add_food" class="btn btn-dark btn-lg py-3 px-4 fw-bold rounded-3 shadow">
                        ✓ Add Dish to Menu
                    </button>
                    <a href="view_food.php" class="btn btn-outline-secondary btn-lg py-3 px-4 rounded-3">
                        Cancel
                    </a>
                </div>

            </form>

        </div>

    </main>

    <script src="../assets/js/nimbus.js"></script>
    <script>
        // Setup Image Preview
        Nimbus.setupImagePreview('foodImageInput', 'foodPreviewImg');

        // Dynamic Subcategory filtering based on Category
        const catSelect = document.getElementById('categorySelect');
        const subcatSelect = document.getElementById('subcategorySelect');
        const subcatOptions = Array.from(subcatSelect.options);

        catSelect.addEventListener('change', function() {
            const selectedCat = this.value;
            subcatSelect.innerHTML = '<option value="">-- Optional Subcategory --</option>';
            subcatOptions.forEach(opt => {
                if (!opt.value || opt.dataset.cat === selectedCat) {
                    if (opt.value) subcatSelect.appendChild(opt.cloneNode(true));
                }
            });
        });
    </script>
</body>
</html>
