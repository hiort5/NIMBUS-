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

$session_query = mysqli_query($conn, "
    SELECT session_id, table_no, start_time, end_time,
           session_status, payment_status, total_amount
    FROM dining_session
    WHERE session_id='$session_id'
");

if (!$session_query || mysqli_num_rows($session_query) == 0) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

$session = mysqli_fetch_assoc($session_query);

// Fetch all available menu items with category & subcategory info
$menu_query = mysqli_query($conn, "
    SELECT menu.*, 
           COALESCE(categories.category_name, menu.category, 'Other') AS cat_name,
           COALESCE(subcategories.subcategory_name, menu.category, '') AS subcat_name
    FROM menu
    LEFT JOIN categories ON menu.category_id = categories.category_id
    LEFT JOIN subcategories ON menu.subcategory_id = subcategories.subcategory_id
    WHERE menu.availability = 'Available'
    ORDER BY categories.category_name, subcategories.subcategory_name, menu.food_name
");

// Organize menu items into custom ordered category and subcategory sections
$all_menu_items = [];
if ($menu_query) {
    while ($row = mysqli_fetch_assoc($menu_query)) {
        $all_menu_items[] = $row;
    }
}

// Custom category display order
$category_order = ['Fast Food', 'Beverages', 'Desserts'];

// Custom subcategory display order (Fast Food: Burger first, then Pizza, Wraps, Chicken, Fries)
$subcat_order = [
    'Fast Food' => ['Burger', 'Pizza', 'Wraps', 'Chicken', 'Fries'],
    'Beverages' => ['Soft Drinks', 'Juices', 'Coffee'],
    'Desserts' => ['Ice Cream', 'Brownies', 'Cake']
];

// Subcategory metadata (icons, display titles, appetizing descriptions)
$subcat_meta = [
    'Burger' => [
        'icon' => '🍔',
        'title' => 'Burgers',
        'desc' => 'Handcrafted prime patties layered with melted cheese & fresh toppings'
    ],
    'Pizza' => [
        'icon' => '🍕',
        'title' => 'Pizzas',
        'desc' => 'Stone-baked artisan pizzas with bubbly mozzarella and rich toppings'
    ],
    'Wraps' => [
        'icon' => '🌯',
        'title' => 'Wraps',
        'desc' => 'Warm toasted flatbread rolls with seasoned fillings & house dressings'
    ],
    'Chicken' => [
        'icon' => '🍗',
        'title' => 'Crispy Chicken',
        'desc' => 'Golden fried chicken buckets and crunchy bite-sized nuggets'
    ],
    'Fries' => [
        'icon' => '🍟',
        'title' => 'Fries & Sides',
        'desc' => 'Hot golden shoestring & seasoned long fries tossed in sea salt'
    ],
    'Soft Drinks' => [
        'icon' => '🥤',
        'title' => 'Soft Drinks & Beverages',
        'desc' => 'Ice-cold bubbly carbonated sodas and crisp refreshing quenchers'
    ],
    'Ice Cream' => [
        'icon' => '🍨',
        'title' => 'Ice Creams',
        'desc' => 'Velvety churned scoops in decadent chocolate, strawberry & pistachio'
    ],
    'Brownies' => [
        'icon' => '🍫',
        'title' => 'Brownies & Blondies',
        'desc' => 'Warm molten Belgian chocolate brownies and chewy golden blondies'
    ],
    'Cake' => [
        'icon' => '🍰',
        'title' => 'Cakes & Desserts',
        'desc' => 'Classic baked New York-style cheesecakes and artisanal desserts'
    ]
];

// Group items into raw category -> subcategory structure
$raw_grouped = [];
foreach ($all_menu_items as $item) {
    $cat = !empty($item['cat_name']) ? trim($item['cat_name']) : 'Other';
    $sub = !empty($item['subcat_name']) ? trim($item['subcat_name']) : 'General';
    $raw_grouped[$cat][$sub][] = $item;
}

// Build ordered structure according to display order
$ordered_menu = [];
foreach ($category_order as $cat) {
    if (isset($raw_grouped[$cat])) {
        $ordered_menu[$cat] = [];
        if (isset($subcat_order[$cat])) {
            foreach ($subcat_order[$cat] as $sub) {
                if (isset($raw_grouped[$cat][$sub])) {
                    $ordered_menu[$cat][$sub] = $raw_grouped[$cat][$sub];
                    unset($raw_grouped[$cat][$sub]);
                }
            }
        }
        foreach ($raw_grouped[$cat] as $sub => $items) {
            $ordered_menu[$cat][$sub] = $items;
        }
        unset($raw_grouped[$cat]);
    }
}
foreach ($raw_grouped as $cat => $subs) {
    $ordered_menu[$cat] = $subs;
}

// Fetch orders in this session
$orders_query = mysqli_query($conn, "
    SELECT order_id, order_time, status
    FROM orders
    WHERE session_id='$session_id'
    ORDER BY order_id DESC
");

// Calculate real total from order_items
$total_query = mysqli_query($conn, "
    SELECT COALESCE(SUM(order_items.subtotal), 0) AS total
    FROM orders
    INNER JOIN order_items ON orders.order_id = order_items.order_id
    WHERE orders.session_id='$session_id'
");

$total_row = mysqli_fetch_assoc($total_query);
$current_total = (float)$total_row['total'];

// Cart count
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}

// Function to classify veg / non-veg
function isNonVeg($food_name, $category) {
    $non_veg_keywords = ['chicken', 'beef', 'prawn', 'tuna', 'mutton', 'fish', 'meat', 'peproni', 'pepperoni', 'nugget', 'bucket'];
    $str = strtolower($food_name . ' ' . $category);
    foreach ($non_veg_keywords as $kw) {
        if (strpos($str, $kw) !== false) return true;
    }
    return false;
}

// Function to provide mouth-watering culinary descriptions if empty in DB
function getAppetizingDesc($food_name, $category, $desc) {
    if (!empty($desc) && trim($desc) !== '') {
        return htmlspecialchars($desc);
    }
    $descriptions = [
        'chicken burger' => 'Crispy seasoned chicken fillet layered with melted cheese, fresh iceberg lettuce & smoky house mayo in a toasted bun.',
        'beef burger' => 'Juicy flame-grilled prime beef patty with melted cheddar, caramelized onions & rich barbecue glaze.',
        'prawns burger' => 'Golden crumbed king prawns with tangy citrus tartar sauce and crisp slaw in a soft brioche.',
        'chicken wrap' => 'Warm toasted flatbread rolled with tender spiced chicken, crunchy peppers & velvety garlic dip.',
        'tuna salad wrap' => 'Flaked tuna seasoned with cracked black pepper, crunchy cucumbers & fresh garden herb mayo.',
        'peproni pizza' => 'Stone-baked artisan crust smothered in rich tomato sauce, melted mozzarella & crisp spicy pepperoni.',
        'chicken pizza' => 'Fire-baked crust loaded with marinated herb chicken, roasted bell peppers & bubbly golden cheese.',
        'bucket chicken' => 'Crispy golden fried chicken marinated in 11 aromatic spices, fried to crunch perfection.',
        'french fries' => 'Golden crispy shoestring potatoes tossed in flaky sea salt, served piping hot with house dip.',
        'chicken nugget' => 'Bite-sized tender chicken nuggets fried to crunchy golden perfection with dipping sauce.',
        'long fries' => 'Signature extra-long potato fries dusted with smoked paprika and aromatic rosemary salt.',
        'coke' => 'Classic effervescent Coca-Cola served ice-cold with bubbly fizz and a refreshing lemon twist.',
        'sprite' => 'Sparkling lemon-lime soda served chilled over ice for an instant burst of crisp freshness.',
        'pepsi' => 'Chilled bubbly cola bursting with bold caramel sweetness and refreshing effervescence.',
        'choco ice cream' => 'Velvety dark chocolate gelato drizzled with molten chocolate fudge and crunchy sprinkles.',
        'strwb ice cream' => 'Creamy real strawberry ice cream swirled with sweet sun-ripened strawberry compote.',
        'pista ice cream' => 'Aromatic royal pistachio gelato churned with slow-roasted pistachio nuts & hint of cardamom.',
        'vanilla ice cream' => 'Silky Madagascar vanilla bean ice cream with a delightfully rich and buttery smooth finish.',
        'brownie' => 'Warm, decadent Belgian dark chocolate brownie with a gooey molten fudge core.',
        'blondie' => 'Soft, chewy golden butterscotch vanilla bar with caramelized edges and melted white chocolate.',
        'cheesecake' => 'Classic velvety New York-style baked cheesecake on a buttery honey-graham cracker crust.'
    ];
    $key = strtolower(trim($food_name));
    if (isset($descriptions[$key])) {
        return $descriptions[$key];
    }
    return 'Handcrafted fresh to order with premium culinary ingredients.';
}

// Function to get appetizing culinary badge tag
function getAppetizingBadge($food_name, $cat, $subcat) {
    $fn = strtolower($food_name);
    if (strpos($fn, 'pizza') !== false || strpos($fn, 'bucket') !== false || strpos($fn, 'cheesecake') !== false) {
        return '🔥 Chef’s Pick';
    }
    if (strpos($fn, 'chicken burger') !== false || strpos($fn, 'brownie') !== false || strpos($fn, 'french fries') !== false) {
        return '⭐ Bestseller';
    }
    if (strtolower($cat) === 'beverage' || strtolower($cat) === 'beverages') {
        return '❄️ Ice Chilled';
    }
    if (strtolower($cat) === 'dessert' || strtolower($cat) === 'desserts') {
        return '✨ Sweet Treat';
    }
    return !empty($subcat) ? $subcat : $cat;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nimbus Dining - Table <?php echo htmlspecialchars($session['table_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        .food-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 22px;
        }
        @media(max-width: 991.98px) {
            .food-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 14px;
            }
            .food-img-wrap {
                height: 140px;
            }
            .food-card-body {
                padding: 12px;
            }
            .food-title {
                font-size: 14px;
            }
            .food-price {
                font-size: 16px;
            }
            .add-btn {
                padding: 6px 10px;
                font-size: 11.5px;
            }
        }
        @media(max-width: 575.98px) {
            .food-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 12px;
            }
        }
        .order-history-card {
            background: #ffffff;
            border: 1px solid var(--nb-border);
            border-radius: var(--nb-radius-lg);
            overflow: hidden;
            box-shadow: var(--nb-shadow-xs);
            margin-bottom: 16px;
        }
        .order-history-header {
            background: #f8fafc;
            padding: 14px 20px;
            border-bottom: 1px solid var(--nb-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body class="customer-dashboard-body">

    <!-- Atmospheric Blurred Food Canvas (Floating in Distance Behind All UI) -->
    <div class="food-atmosphere-canvas" id="foodAtmosphereCanvas" aria-hidden="true">
        <!-- Distant Layer: Large, heavily blurred dreamlike silhouettes -->
        <div class="food-atm-item atm-pos-1 food-depth-distant blur-heavy-1">
            <div class="food-drift-inner drift-loop-1">
                <img src="../assets/images/burger.png" alt="" />
            </div>
        </div>

        <div class="food-atm-item atm-pos-2 food-depth-distant blur-heavy-2">
            <div class="food-drift-inner drift-loop-2">
                <img src="../assets/images/pizza.png" alt="" />
            </div>
        </div>

        <div class="food-atm-item atm-pos-3 food-depth-distant blur-heavy-3">
            <div class="food-drift-inner drift-loop-3">
                <img src="../assets/images/sandwich.png" alt="" />
            </div>
        </div>

        <div class="food-atm-item atm-pos-10 food-depth-distant blur-heavy-2">
            <div class="food-drift-inner drift-loop-4">
                <img src="../assets/images/burger.png" alt="" />
            </div>
        </div>

        <!-- Midground Layer: Medium scale, soft blur -->
        <div class="food-atm-item atm-pos-4 food-depth-mid blur-soft-1">
            <div class="food-drift-inner drift-loop-4">
                <img src="../assets/images/pudding.png" alt="" />
            </div>
        </div>

        <div class="food-atm-item atm-pos-5 food-depth-mid blur-soft-3">
            <div class="food-drift-inner drift-loop-5">
                <img src="../assets/images/icecream_choco.png" alt="" />
            </div>
        </div>

        <div class="food-atm-item atm-pos-6 food-depth-mid blur-soft-2">
            <div class="food-drift-inner drift-loop-6">
                <img src="../assets/images/sandwich.png" alt="" />
            </div>
        </div>

        <div class="food-atm-item atm-pos-7 food-depth-mid blur-soft-1">
            <div class="food-drift-inner drift-loop-3">
                <img src="../assets/images/pudding.png" alt="" />
            </div>
        </div>

        <div class="food-atm-item atm-pos-11 food-depth-mid blur-soft-2">
            <div class="food-drift-inner drift-loop-1">
                <img src="../assets/images/pizza.png" alt="" />
            </div>
        </div>

        <!-- Foreground Layer: Subtle, positioned away from text, light blur -->
        <div class="food-atm-item atm-pos-8 food-depth-near blur-light-1">
            <div class="food-drift-inner drift-loop-5">
                <img src="../assets/images/burger.png" alt="" />
            </div>
        </div>

        <div class="food-atm-item atm-pos-9 food-depth-near blur-light-2">
            <div class="food-drift-inner drift-loop-6">
                <img src="../assets/images/pudding.png" alt="" />
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="nimbus-navbar">
        <div class="container-fluid px-3 px-md-5 d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="nimbus-brand">
                ☁ Nimbus
                <span class="nimbus-brand-badge">Dining</span>
            </a>

            <div class="d-flex align-items-center gap-2 gap-md-3">
                <div class="table-pill">
                    <span>🍽️ Table</span>
                    <strong class="text-warning"><?php echo htmlspecialchars($session['table_no']); ?></strong>
                </div>

                <a href="#menu" class="nav-link-btn d-none d-sm-inline-flex">
                    🍔 Menu
                </a>

                <a href="#orders" class="nav-link-btn d-none d-sm-inline-flex">
                    📦 Orders
                </a>

                <a href="#payment" class="nav-link-btn d-none d-sm-inline-flex">
                    💳 Bill: ₹<?php echo number_format($current_total, 2); ?>
                </a>

                <?php if ($session['session_status'] == 'Dining') { ?>
                    <a href="cart.php" class="btn nav-cart-btn d-inline-flex align-items-center gap-2">
                        🛒 <span class="d-none d-sm-inline">Cart</span>
                        <span class="badge bg-dark text-white rounded-pill" id="navCartBadge"><?php echo $cart_count; ?></span>
                    </a>
                <?php } ?>
            </div>
        </div>
    </nav>

    <!-- Warm Creamy Landing Hero Section (~100vh) -->
    <section id="landingHero" class="landing-hero-section">
        <div class="landing-hero-content" id="landingHeroContent">
            <div class="landing-table-badge mb-3">
                <span class="badge-icon">🍽️</span>
                <span>Table <?php echo htmlspecialchars($session['table_no']); ?></span>
                <span class="landing-badge-divider">•</span>
                <span>Artisanal Dining</span>
            </div>

            <h1 class="landing-brand-title mb-3">
                Nimbus Dining
            </h1>

            <p class="landing-subtitle mb-4">
                A symphony of handcrafted culinary perfection &amp; seamless table service.
            </p>

            <a href="#menuSectionWrap" id="landingScrollCue" class="landing-scroll-cue">
                <span class="cue-label">Scroll to explore menu</span>
                <div class="cue-arrow-pill">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 13l5 5 5-5M7 6l5 5 5-5"/>
                    </svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Continuous Menu Section Wrapper (Rises Gracefully from Below on Scroll) -->
    <div id="menuSectionWrap" class="menu-section-wrap">

    <!-- Interactive Category Filter & Live Search Bar -->
    <div class="category-filter-bar" id="categoryBar">
        <div class="container-fluid px-3 px-md-5">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                <div class="d-flex flex-column gap-2 flex-grow-1">

                    <!-- Parent Category Row -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="cat-label">Category</span>
                        <div class="cat-chip active parent-chip" data-filter="all" data-parent="all">✨ All Delights</div>
                        <div class="cat-chip parent-chip" data-filter="fast food" data-parent="fast-food">🍔 Fast Food</div>
                        <div class="cat-chip parent-chip" data-filter="beverages" data-parent="beverages">🥤 Beverages</div>
                        <div class="cat-chip parent-chip" data-filter="desserts" data-parent="desserts">🍰 Desserts</div>
                    </div>

                    <!-- Subcategory Row: visible only when Fast Food is selected -->
                    <div class="d-flex align-items-center gap-2 flex-wrap" id="subCatRow" style="display: none !important;">
                        <span class="cat-label">Type</span>
                        <div class="sub-chip active" data-subfilter="all">All Fast Food</div>
                        <div class="sub-chip" data-subfilter="burger">🍔 Burgers</div>
                        <div class="sub-chip" data-subfilter="pizza">🍕 Pizza</div>
                        <div class="sub-chip" data-subfilter="wraps">🌯 Wraps</div>
                        <div class="sub-chip" data-subfilter="chicken">🍗 Chicken</div>
                        <div class="sub-chip" data-subfilter="fries">🍟 Fries</div>
                    </div>

                </div>

                <!-- Search Input -->
                <div class="search-input-wrapper flex-shrink-0">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="menuSearch" placeholder="Search dish (e.g. Pizza, Coke)...">
                </div>

            </div>

        </div>
    </div>

    <!-- Main Content Area -->
    <div class="container-fluid px-2 px-sm-3 px-md-5 py-4 main-customer-content">
        <div class="row g-3 g-md-4">
            
            <!-- Menu Column (LEFT Column on Desktop >=992px, FIRST on Mobile) -->
            <div class="col-12 col-lg-8 col-xl-9 order-1">
                
                <section id="menu" style="scroll-margin-top: 140px;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <span class="text-warning text-uppercase fw-bold small letter-spacing-1 d-block mb-1">Culinary Selection</span>
                            <h2 class="h3 fw-bold mb-1">Our Menu</h2>
                            <p class="text-muted small mb-0">Freshly made culinary delights prepared on order</p>
                        </div>
                    </div>

                    <!-- Subcategory Sections Container -->
                    <div id="foodSectionsContainer">
                        <?php
                        if (empty($all_menu_items)) {
                        ?>
                            <div class="col-12 py-5 text-center bg-white rounded-4 border">
                                <div class="fs-1 mb-2">🍽️</div>
                                <h4>No Menu Items Available</h4>
                                <p class="text-muted">Please check back shortly.</p>
                            </div>
                        <?php
                        } else {
                            foreach ($ordered_menu as $cat_name => $subcats) {
                                foreach ($subcats as $subcat_name => $items) {
                                    if (empty($items)) continue;

                                    $meta = $subcat_meta[$subcat_name] ?? [
                                        'icon' => '🍽️',
                                        'title' => $subcat_name,
                                        'desc' => ''
                                    ];
                                    $icon = $meta['icon'];
                                    $display_title = $meta['title'];
                                    $display_desc = $meta['desc'];
                                    $sec_id = 'section-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $subcat_name));
                                    $item_count = count($items);
                        ?>
                                    <div class="menu-subcategory-section" 
                                         id="<?php echo $sec_id; ?>"
                                         data-category="<?php echo strtolower($cat_name); ?>" 
                                         data-subcategory="<?php echo strtolower($subcat_name); ?>">
                                        
                                        <div class="subcategory-section-header">
                                            <div class="subcategory-title-wrap">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="subcategory-icon"><?php echo $icon; ?></span>
                                                    <h3 class="subcategory-title mb-0"><?php echo htmlspecialchars($display_title); ?></h3>
                                                    <span class="subcategory-badge"><?php echo $item_count; ?> <?php echo $item_count === 1 ? 'item' : 'items'; ?></span>
                                                </div>
                                                <?php if (!empty($display_desc)) { ?>
                                                    <p class="subcategory-desc"><?php echo htmlspecialchars($display_desc); ?></p>
                                                <?php } ?>
                                            </div>
                                            <span class="subcategory-parent-tag d-none d-sm-inline-block"><?php echo htmlspecialchars($cat_name); ?></span>
                                        </div>

                                        <div class="food-grid">
                                            <?php
                                            foreach ($items as $dish) {
                                                $dish_id = (int)$dish['food_id'];
                                                $dish_name = htmlspecialchars($dish['food_name']);
                                                $dish_price = (float)$dish['price'];
                                                $dish_cat = htmlspecialchars($dish['cat_name']);
                                                $dish_subcat = htmlspecialchars($dish['subcat_name']);
                                                $dish_img = !empty($dish['image']) ? $dish['image'] : 'bucketchicken.jpg';
                                                $dish_desc = getAppetizingDesc($dish['food_name'], $dish['category'], $dish['description'] ?? '');
                                                $badge_label = getAppetizingBadge($dish['food_name'], $dish_cat, $dish_subcat);
                                                
                                                $is_non_veg = isNonVeg($dish['food_name'], $dish['category']);
                                            ?>
                                                <div class="food-item-col reveal-ready" 
                                                     data-category="<?php echo strtolower($dish_cat); ?>" 
                                                     data-subcategory="<?php echo strtolower($dish_subcat); ?>" 
                                                     data-name="<?php echo strtolower($dish_name); ?>">
                                                    
                                                    <div class="food-card">
                                                        
                                                        <!-- Image & Badges -->
                                                        <div class="food-img-wrap">
                                                            <img src="../assets/images/<?php echo $dish_img; ?>" 
                                                                 alt="<?php echo $dish_name; ?>" 
                                                                 class="food-img"
                                                                 loading="lazy"
                                                                 onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&q=80'">
                                                            
                                                            <span class="food-badge-tag"><?php echo $badge_label; ?></span>
                                                            <div class="diet-dot <?php echo $is_non_veg ? 'non-veg' : 'veg'; ?>" 
                                                                 title="<?php echo $is_non_veg ? 'Non-Vegetarian' : 'Vegetarian'; ?>"></div>
                                                        </div>

                                                        <!-- Card Body -->
                                                        <div class="food-card-body">
                                                            <h5 class="food-title"><?php echo $dish_name; ?></h5>
                                                            <p class="food-desc"><?php echo $dish_desc; ?></p>
                                                            
                                                            <div class="food-price-row">
                                                                <div class="food-price">
                                                                    <span class="currency">₹</span><span class="amount"><?php echo number_format($dish_price, 2); ?></span>
                                                                </div>

                                                                <?php if ($session['session_status'] == 'Dining') { ?>
                                                                    <form class="ajax-add-cart-form d-flex align-items-center gap-2" method="POST" action="add_to_cart.php">
                                                                        <input type="hidden" name="food_id" value="<?php echo $dish_id; ?>">
                                                                        <input type="hidden" name="quantity" value="1" class="item-qty-input">
                                                                        <button type="submit" class="add-btn">
                                                                            <span>＋</span> Add
                                                                        </button>
                                                                    </form>
                                                                <?php } else { ?>
                                                                    <span class="badge bg-secondary rounded-pill px-3 py-2">Billed</span>
                                                                <?php } ?>
                                                            </div>
                                                        </div>

                                                    </div>

                                                </div>
                                            <?php
                                            }
                                            ?>
                                        </div>

                                    </div>
                        <?php
                                }
                            }
                        }
                        ?>
                    </div>

                    <div id="noResultsMessage" class="text-center py-5 bg-white rounded-4 border mt-4" style="display: none;">
                        <div class="fs-1 mb-2">🔍</div>
                        <h5>No matching dishes found</h5>
                        <p class="text-muted small">Try searching for a different item or category.</p>
                    </div>

                </section>

                <!-- Orders Section -->
                <section id="orders" class="mt-5 pt-4 border-top" style="scroll-margin-top: 140px;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="h3 fw-bold mb-1">Your Orders</h2>
                            <p class="text-muted small mb-0">Track all orders submitted during this dining session</p>
                        </div>
                    </div>

                    <?php if (mysqli_num_rows($orders_query) == 0) { ?>
                        <div class="text-center py-5 bg-white rounded-4 border">
                            <div class="fs-1 mb-2">📦</div>
                            <h5>No Orders Placed Yet</h5>
                            <p class="text-muted small">Add items to your cart and place an order to see live tracking.</p>
                        </div>
                    <?php } else { 
                        while ($ord = mysqli_fetch_assoc($orders_query)) {
                            $o_id = (int)$ord['order_id'];
                            $o_status = $ord['status'];
                            $o_time = $ord['order_time'];

                            $badge_color = 'bg-secondary';
                            if ($o_status == 'Preparing') $badge_color = 'bg-warning text-dark';
                            if ($o_status == 'Ready') $badge_color = 'bg-info text-dark';
                            if ($o_status == 'Served') $badge_color = 'bg-success';

                            $items_res = mysqli_query($conn, "
                                SELECT menu.food_name, order_items.quantity, order_items.subtotal
                                FROM order_items
                                INNER JOIN menu ON order_items.food_id = menu.food_id
                                WHERE order_items.order_id='$o_id'
                            ");
                    ?>
                            <div class="order-history-card">
                                <div class="order-history-header">
                                    <div>
                                        <span class="fw-bold">Order #<?php echo $o_id; ?></span>
                                        <span class="text-muted small ms-2"><?php echo date("h:i A, M d", strtotime($o_time)); ?></span>
                                    </div>
                                    <span class="badge <?php echo $badge_color; ?> px-3 py-2 rounded-pill fw-bold">
                                        <?php echo htmlspecialchars($o_status); ?>
                                    </span>
                                </div>
                                <div class="p-3">
                                    <?php while ($it = mysqli_fetch_assoc($items_res)) { ?>
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom-dashed">
                                            <span><?php echo htmlspecialchars($it['food_name']); ?> <strong class="text-muted">× <?php echo $it['quantity']; ?></strong></span>
                                            <span class="fw-bold">₹<?php echo number_format($it['subtotal'], 2); ?></span>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                    <?php 
                        }
                    } 
                    ?>
                </section>

                <!-- Payment Section -->
                <section id="payment" class="mt-5 pt-4 border-top mb-5" style="scroll-margin-top: 140px;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="h3 fw-bold mb-1">Billing & Settlement</h2>
                            <p class="text-muted small mb-0">Total bill breakdown and instant checkout</p>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <span class="text-muted text-uppercase fw-bold small">Current Session Total</span>
                                <h1 class="display-5 fw-bold text-dark mb-2">₹<?php echo number_format($current_total, 2); ?></h1>
                                <p class="text-muted mb-3">Table <?php echo htmlspecialchars($session['table_no']); ?> • Payment Status: 
                                    <strong class="<?php echo $session['payment_status'] == 'Paid' ? 'text-success' : 'text-warning'; ?>">
                                        <?php echo htmlspecialchars($session['payment_status']); ?>
                                    </strong>
                                </p>
                            </div>
                            <div class="col-md-5 text-md-end">
                                <?php if ($session['session_status'] == 'Dining') { ?>
                                    <?php if ($current_total > 0) { ?>
                                        <a href="payment.php" class="btn btn-dark btn-lg py-3 px-4 fw-bold rounded-3 shadow">
                                            Proceed to Payment →
                                        </a>
                                    <?php } else { ?>
                                        <button class="btn btn-secondary btn-lg py-3 px-4 fw-bold rounded-3" disabled>
                                            No Items to Pay
                                        </button>
                                    <?php } ?>
                                <?php } elseif ($session['payment_status'] == 'Paid') { ?>
                                    <div class="d-flex flex-column gap-2">
                                        <a href="receipt.php" class="btn btn-success py-3 px-4 fw-bold rounded-3">
                                            🧾 View Final Receipt
                                        </a>
                                        <a href="end_session.php" class="btn btn-outline-dark py-2 px-4 rounded-3" onclick="return confirm('Complete and finish this session?');">
                                            Finish Session & Exit
                                        </a>
                                    </div>
                                <?php } else { ?>
                                    <a href="payment.php" class="btn btn-warning btn-lg py-3 px-4 fw-bold rounded-3 text-dark">
                                        Complete Payment (Waiting) →
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </section>

            </div>

            <!-- Small Windows Sidebar (RIGHT Column on Desktop >=992px, SECOND on Mobile) -->
            <div class="col-12 col-lg-4 col-xl-3 order-2">
                <div class="sidebar-sticky-wrap">
                    
                    <!-- Live Order Tracker Widget -->
                    <div class="nimbus-sidebar-card">
                        <div class="sidebar-header">
                            <h5><span>📦</span> Live Order Status</h5>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">LIVE</span>
                        </div>
                        <div id="orderTrackerContainer" style="max-height: 280px; overflow-y: auto;">
                            <?php include("order_progress.php"); ?>
                        </div>
                    </div>

                    <!-- Cart Preview Widget -->
                    <div class="nimbus-sidebar-card">
                        <div class="sidebar-header">
                            <h5><span>🛒</span> Your Cart</h5>
                            <a href="cart.php" class="text-decoration-none small fw-bold text-dark">Open →</a>
                        </div>
                        <div id="cart-sidebar" style="max-height: 280px; overflow-y: auto;">
                            <?php include("cart_sidebar.php"); ?>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
    </div><!-- /menuSectionWrap -->

    <!-- Reusable Nimbus JS -->
    <script src="../assets/js/nimbus.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ── Two-Tier Category Filter ──────────────────────────────
            let activeParent = 'all';
            let activeSubcat = 'all';
            const subCatRow  = document.getElementById('subCatRow');

            function applyFoodFilter() {
                const searchVal = (document.getElementById('menuSearch')?.value || '').trim().toLowerCase();
                let visibleCount = 0;

                const sections = document.querySelectorAll('.menu-subcategory-section');

                sections.forEach(section => {
                    const secCat = (section.dataset.category || '').toLowerCase();
                    const secSub = (section.dataset.subcategory || '').toLowerCase();

                    // Check parent category match
                    let passParent = (activeParent === 'all') || secCat.includes(activeParent);

                    // Check subcategory match (when fast food is active)
                    let passSub = true;
                    if (activeParent === 'fast food' && activeSubcat !== 'all') {
                        passSub = secSub.includes(activeSubcat);
                    }

                    if (!passParent || !passSub) {
                        section.style.display = 'none';
                        return;
                    }

                    // Check food cards inside this section against search
                    let sectionVisibleCount = 0;
                    const cards = section.querySelectorAll('.food-item-col');

                    cards.forEach(card => {
                        const cardCat    = (card.dataset.category    || '').toLowerCase();
                        const cardSubcat = (card.dataset.subcategory || '').toLowerCase();
                        const cardName   = (card.dataset.name        || '').toLowerCase();

                        let passSearch = true;
                        if (searchVal) {
                            passSearch = cardName.includes(searchVal) || cardCat.includes(searchVal) || cardSubcat.includes(searchVal);
                        }

                        if (passSearch) {
                            card.style.display = '';
                            sectionVisibleCount++;
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    // Hide section header and grid if no cards match search
                    if (sectionVisibleCount === 0) {
                        section.style.display = 'none';
                    } else {
                        section.style.display = '';
                    }
                });

                const noResults = document.getElementById('noResultsMessage');
                if (noResults) noResults.style.display = visibleCount === 0 ? '' : 'none';
            }

            // Parent chips click handler
            document.querySelectorAll('.parent-chip').forEach(chip => {
                chip.addEventListener('click', function () {
                    document.querySelectorAll('.parent-chip').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    activeParent = this.dataset.filter;
                    activeSubcat = 'all';

                    if (activeParent === 'fast food') {
                        subCatRow.style.setProperty('display', 'flex', 'important');
                        document.querySelectorAll('.sub-chip').forEach(s => s.classList.remove('active'));
                        document.querySelector('.sub-chip[data-subfilter="all"]').classList.add('active');
                    } else {
                        subCatRow.style.setProperty('display', 'none', 'important');
                    }

                    applyFoodFilter();
                });
            });

            // Subcategory chips click handler
            document.querySelectorAll('.sub-chip').forEach(chip => {
                chip.addEventListener('click', function () {
                    document.querySelectorAll('.sub-chip').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    activeSubcat = this.dataset.subfilter;
                    applyFoodFilter();
                });
            });

            // Live Search
            document.getElementById('menuSearch')?.addEventListener('input', applyFoodFilter);
            // ─────────────────────────────────────────────────────────

            // ── Smooth Landing-to-Menu Scroll Transition & Rising Menu ──
            const landingHero = document.getElementById('landingHero');
            const landingContent = document.getElementById('landingHeroContent');
            const menuSectionWrap = document.getElementById('menuSectionWrap');
            const distantFoods = document.querySelectorAll('.food-depth-distant');
            const midFoods = document.querySelectorAll('.food-depth-mid');
            const nearFoods = document.querySelectorAll('.food-depth-near');

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (!prefersReducedMotion && landingHero && menuSectionWrap) {
                let isTicking = false;

                function onLandingScroll() {
                    const scrollY = window.pageYOffset || document.documentElement.scrollTop;
                    const heroHeight = landingHero.offsetHeight || (window.innerHeight - 65);

                    // Progress from 0 (top of landing) to 1 (scrolled past landing)
                    const progress = Math.min(1, Math.max(0, scrollY / (heroHeight * 0.70)));

                    // 1. Landing content glides upward and gently fades out
                    if (landingContent) {
                        const moveY = -scrollY * 0.38;
                        const opacity = Math.max(0, 1 - (scrollY / (heroHeight * 0.52)));
                        landingContent.style.transform = `translate3d(0, ${moveY.toFixed(1)}px, 0)`;
                        landingContent.style.opacity = opacity.toFixed(3);
                        landingContent.style.pointerEvents = opacity <= 0.05 ? 'none' : 'auto';
                    }

                    // 2. Menu section physically rises into view from below with subtle scale
                    if (menuSectionWrap) {
                        if (progress >= 1) {
                            menuSectionWrap.style.transform = '';
                            menuSectionWrap.style.opacity = '';
                        } else {
                            const riseY = (1 - progress) * 55;
                            const scale = 0.975 + (progress * 0.025);
                            const opacity = 0.70 + (progress * 0.30);
                            menuSectionWrap.style.transform = `translate3d(0, ${riseY.toFixed(1)}px, 0) scale(${scale.toFixed(4)})`;
                            menuSectionWrap.style.opacity = opacity.toFixed(3);
                        }
                    }

                    // 3. Dreamlike atmospheric parallax on blurred floating food layers
                    if (distantFoods.length > 0) {
                        const distantY = -scrollY * 0.05;
                        distantFoods.forEach(el => {
                            el.style.transform = `translate3d(0, ${distantY.toFixed(1)}px, 0)`;
                        });
                    }
                    if (midFoods.length > 0) {
                        const midY = -scrollY * 0.12;
                        midFoods.forEach(el => {
                            el.style.transform = `translate3d(0, ${midY.toFixed(1)}px, 0)`;
                        });
                    }
                    if (nearFoods.length > 0) {
                        const nearY = -scrollY * 0.18;
                        nearFoods.forEach(el => {
                            el.style.transform = `translate3d(0, ${nearY.toFixed(1)}px, 0)`;
                        });
                    }

                    // 4. Smooth navbar glass backing when scrolling into menu cards
                    const mainNavbar = document.querySelector('.nimbus-navbar');
                    if (mainNavbar) {
                        if (scrollY > (heroHeight * 0.75)) {
                            mainNavbar.classList.add('nav-scrolled');
                        } else {
                            mainNavbar.classList.remove('nav-scrolled');
                        }
                    }

                    isTicking = false;
                }

                window.addEventListener('scroll', function() {
                    if (!isTicking) {
                        window.requestAnimationFrame(onLandingScroll);
                        isTicking = true;
                    }
                }, { passive: true });

                // Initial calculation
                onLandingScroll();
            }

            // Scroll Reveal Animation on Menu Items (with staggered column delays)
            const observerOptions = {
                root: null,
                rootMargin: '0px 0px -30px 0px',
                threshold: 0.08
            };

            const itemObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('reveal-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.food-item-col').forEach((el, index) => {
                el.style.transitionDelay = `${(index % 4) * 0.07}s`;
                itemObserver.observe(el);
            });

            // Smooth scroll on clicking Explore Menu & anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('href');
                    if (targetId.startsWith('#') && targetId.length > 1) {
                        const targetEl = document.querySelector(targetId);
                        if (targetEl) {
                            e.preventDefault();
                            targetEl.scrollIntoView({ behavior: 'smooth' });
                        }
                    }
                });
            });

            // Handle AJAX Add to Cart
            document.querySelectorAll('.ajax-add-cart-form').forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const btn = form.querySelector('button');
                    const originalHtml = btn.innerHTML;

                    btn.disabled = true;
                    btn.innerHTML = '<span>⏳</span> Adding...';

                    try {
                        const formData = new FormData(form);
                        formData.append('json', '1');

                        const res = await fetch('add_to_cart.php', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: formData
                        });

                        if (!res.ok) throw new Error('Failed to add item');
                        const data = await res.json();

                        // Refresh cart sidebar preview
                        const cartRes = await fetch('cart_sidebar.php');
                        const cartHtml = await cartRes.text();
                        document.getElementById('cart-sidebar').innerHTML = cartHtml;

                        // Update Nav Badge
                        const badge = document.getElementById('navCartBadge');
                        if (badge) badge.innerText = data.cart_count;

                        // Spawn appetizing floating +1 particle animation
                        try {
                            const rect = btn.getBoundingClientRect();
                            const particle = document.createElement('div');
                            particle.className = 'cart-particle';
                            particle.innerHTML = '+1 🍽️';
                            particle.style.left = `${rect.left + rect.width / 2}px`;
                            particle.style.top = `${rect.top}px`;
                            document.body.appendChild(particle);
                            setTimeout(() => particle.remove(), 850);
                        } catch(pe) {}

                        btn.innerHTML = '<span>✓</span> Added';
                        Nimbus.toast(`Added ${data.food_name || 'dish'} to cart!`, 'success');

                        setTimeout(() => {
                            btn.innerHTML = originalHtml;
                            btn.disabled = false;
                        }, 900);

                    } catch(err) {
                        Nimbus.toast('Unable to add item. Please try again.', 'error');
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }
                });
            });

            // Live Order Tracker Poller
            setInterval(async () => {
                try {
                    const trackerRes = await fetch('order_progress.php');
                    if (trackerRes.ok) {
                        const trackerHtml = await trackerRes.text();
                        document.getElementById('orderTrackerContainer').innerHTML = trackerHtml;
                    }
                } catch(e) {
                    console.debug('Polling order status...');
                }
            }, 3500);
        });
    </script>
</body>
</html>
