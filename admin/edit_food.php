<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: view_food.php");
    exit();
}

$id = (int)$_GET['id'];
$message = "";
$message_type = "";

// Fetch Existing Food
$stmt = mysqli_prepare($conn, "SELECT * FROM menu WHERE food_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($res) == 0) {
    mysqli_stmt_close($stmt);
    die("Food item not found.");
}
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Load Categories
$categories = [];
$cat_res = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name");
if ($cat_res) {
    while ($c = mysqli_fetch_assoc($cat_res)) $categories[] = $c;
}

// Load Subcategories
$subcategories = [];
$subcat_res = mysqli_query($conn, "SELECT subcategory_id, category_id, subcategory_name FROM subcategories ORDER BY subcategory_name");
if ($subcat_res) {
    while ($sc = mysqli_fetch_assoc($subcat_res)) $subcategories[] = $sc;
}

if (isset($_POST['update'])) {
    $food_name = trim($_POST['food_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $subcategory_id = (int)($_POST['subcategory_id'] ?? 0);
    $price = $_POST['price'] ?? '';
    $availability = trim($_POST['availability'] ?? 'Available');
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
    } elseif (!is_numeric($price) || $price <= 0) {
        $message = "Please enter a valid price.";
        $message_type = "danger";
    } else {
        $old_image = $row['image'];
        $new_image = $old_image;
        $uploaded_new = false;

        // Check if new image was provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
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
                $new_image = time() . "_" . uniqid() . "." . $extension;
                $upload_path = "../assets/images/" . $new_image;

                if (move_uploaded_file($temp_name, $upload_path)) {
                    $uploaded_new = true;
                } else {
                    $message = "Unable to save new image.";
                    $message_type = "danger";
                }
            }
        }

        if (empty($message)) {
            $price_val = (float)$price;
            $sub_id_val = ($subcategory_id > 0) ? $subcategory_id : null;

            $update_stmt = mysqli_prepare($conn, "
                UPDATE menu 
                SET food_name=?, category=?, category_id=?, subcategory_id=?, price=?, description=?, image=?, availability=?
                WHERE food_id=?
            ");

            if ($update_stmt) {
                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssiidsssi",
                    $food_name,
                    $category_name,
                    $category_id,
                    $sub_id_val,
                    $price_val,
                    $description,
                    $new_image,
                    $availability,
                    $id
                );

                if (mysqli_stmt_execute($update_stmt)) {
                    // Remove old image if replaced
                    if ($uploaded_new && !empty($old_image) && file_exists("../assets/images/" . $old_image)) {
                        @unlink("../assets/images/" . $old_image);
                    }
                    mysqli_stmt_close($update_stmt);
                    header("Location: view_food.php");
                    exit();
                } else {
                    if ($uploaded_new) @unlink("../assets/images/" . $new_image);
                    $message = "Update failed: " . mysqli_stmt_error($update_stmt);
                    $message_type = "danger";
                    mysqli_stmt_close($update_stmt);
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
    <title>Edit Dish #<?php echo $id; ?> - Nimbus Admin</title>
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
                <h2 class="display-6 fw-bold mb-1">✏ Edit Dish</h2>
                <p class="text-muted small mb-0">Update menu item details, pricing, and availability</p>
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
                    <input type="text" name="food_name" class="form-control rounded-3 py-2" value="<?php echo htmlspecialchars($row['food_name']); ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    
                    <!-- Category -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">Category</label>
                        <select name="category_id" id="categorySelect" class="form-select rounded-3 py-2" required>
                            <?php foreach ($categories as $cat) { 
                                $is_sel = ($row['category_id'] == $cat['category_id']) || ($row['category'] == $cat['category_name']);
                            ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo $is_sel ? 'selected' : ''; ?>>
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
                            <?php foreach ($subcategories as $sc) { 
                                $is_sc_sel = ($row['subcategory_id'] == $sc['subcategory_id']);
                            ?>
                                <option value="<?php echo $sc['subcategory_id']; ?>" data-cat="<?php echo $sc['category_id']; ?>" <?php echo $is_sc_sel ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sc['subcategory_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                </div>

                <!-- Price & Description -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">Price (₹)</label>
                        <input type="number" step="0.01" min="0.01" name="price" class="form-control rounded-3 py-2" value="<?php echo htmlspecialchars($row['price']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">Stock Status</label>
                        <select name="availability" class="form-select rounded-3 py-2">
                            <option value="Available" <?php echo $row['availability'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="Out of Stock" <?php echo $row['availability'] == 'Out of Stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">Description</label>
                        <input type="text" name="description" class="form-control rounded-3 py-2" value="<?php echo htmlspecialchars($row['description'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Image Upload with Live Preview -->
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">Dish Photo</label>
                    <div class="d-flex align-items-center gap-3">
                        <img id="foodPreviewImg" src="../assets/images/<?php echo htmlspecialchars($row['image']); ?>" alt="Preview" class="image-preview-box" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=150&q=80'">
                        <div class="flex-grow-1">
                            <input type="file" name="image" id="foodImageInput" class="form-control rounded-3" accept=".jpg,.jpeg,.png,.webp,.jfif">
                            <small class="text-muted d-block mt-1">Leave empty to keep current photo. Accepted: JPG, PNG, WEBP (Max 5MB)</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 pt-2">
                    <button type="submit" name="update" class="btn btn-dark btn-lg py-3 px-4 fw-bold rounded-3 shadow">
                        ✓ Save Changes
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
        Nimbus.setupImagePreview('foodImageInput', 'foodPreviewImg');
    </script>
</body>
</html>
