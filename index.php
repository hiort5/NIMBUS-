<?php
session_start();
include("config/db.php");

// Fetch quick restaurant stats
$total_menu = 0;
$active_sessions = 0;
$pending_orders = 0;

$menu_res = @mysqli_query($conn, "SELECT COUNT(*) FROM menu WHERE availability='Available'");
if ($menu_res) $total_menu = mysqli_fetch_row($menu_res)[0];

$sess_res = @mysqli_query($conn, "SELECT COUNT(*) FROM dining_session WHERE session_status != 'Completed'");
if ($sess_res) $active_sessions = mysqli_fetch_row($sess_res)[0];

$order_res = @mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status != 'Served'");
if ($order_res) $pending_orders = mysqli_fetch_row($order_res)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nimbus - Smart Restaurant Management Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/nimbus.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Top Navigation -->
    <nav class="nimbus-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="nimbus-brand">
                ☁ Nimbus
                <span class="nimbus-brand-badge">PRO SUITE</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold">
                    ● System Live
                </span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="portal-hero">
        <div class="container">
            <h1 class="display-4 fw-bold text-white mb-3">Smart Restaurant Suite</h1>
            <p class="lead text-slate-300 mx-auto" style="max-width: 680px; color: #cbd5e1;">
                End-to-end digital dining workflow: from contactless customer ordering and kitchen display management to counter billing and admin controls.
            </p>

            <!-- Quick Live Stats -->
            <div class="d-flex justify-content-center gap-3 gap-md-5 mt-4 pt-2">
                <div class="text-center">
                    <div class="fs-3 fw-bold text-warning"><?php echo $total_menu; ?></div>
                    <div class="small text-slate-400" style="color:#94a3b8;">Active Menu Items</div>
                </div>
                <div class="border-end border-slate-700"></div>
                <div class="text-center">
                    <div class="fs-3 fw-bold text-info"><?php echo $active_sessions; ?></div>
                    <div class="small text-slate-400" style="color:#94a3b8;">Occupied Tables</div>
                </div>
                <div class="border-end border-slate-700"></div>
                <div class="text-center">
                    <div class="fs-3 fw-bold text-success"><?php echo $pending_orders; ?></div>
                    <div class="small text-slate-400" style="color:#94a3b8;">Live Kitchen Orders</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Portal Navigation Grid -->
    <main class="container py-5" style="margin-top: -30px; position: relative; z-index: 10;">
        <div class="row g-4">
            
            <!-- 1. Customer Ordering -->
            <div class="col-md-6 col-lg-3">
                <div class="portal-card">
                    <div class="portal-icon">🍽️</div>
                    <h3 class="h4 mb-2">Customer Dining</h3>
                    <p class="text-muted small mb-4 flex-grow-1">
                        Table selection, interactive food catalog, real-time cart, live order progress tracking, and instant UPI checkout.
                    </p>
                    <a href="customer/index.php" class="btn btn-dark w-100 py-2 fw-semibold rounded-3">
                        Launch Menu →
                    </a>
                </div>
            </div>

            <!-- 2. Kitchen Display (KDS) -->
            <div class="col-md-6 col-lg-3">
                <div class="portal-card">
                    <div class="portal-icon">👨‍🍳</div>
                    <h3 class="h4 mb-2">Kitchen Display</h3>
                    <p class="text-muted small mb-4 flex-grow-1">
                        Live real-time KDS tickets, preparation timers, audio alerts for new orders, and instant 1-click status transitions.
                    </p>
                    <a href="kitchen/dashboard.php" class="btn btn-dark w-100 py-2 fw-semibold rounded-3">
                        Open Kitchen KDS →
                    </a>
                </div>
            </div>

            <!-- 3. Counter & Billing -->
            <div class="col-md-6 col-lg-3">
                <div class="portal-card">
                    <div class="portal-icon">💰</div>
                    <h3 class="h4 mb-2">Counter POS</h3>
                    <p class="text-muted small mb-4 flex-grow-1">
                        Interactive 20-table floor plan, live occupancy badges, payment collection, and thermal invoice printing.
                    </p>
                    <a href="counter/dashboard.php" class="btn btn-dark w-100 py-2 fw-semibold rounded-3">
                        Open Counter POS →
                    </a>
                </div>
            </div>

            <!-- 4. Admin Management -->
            <div class="col-md-6 col-lg-3">
                <div class="portal-card">
                    <div class="portal-icon">⚙️</div>
                    <h3 class="h4 mb-2">Admin Portal</h3>
                    <p class="text-muted small mb-4 flex-grow-1">
                        Menu management, category organization, stock availability toggle, revenue analytics, and system settings.
                    </p>
                    <a href="admin/login.php" class="btn btn-outline-dark w-100 py-2 fw-semibold rounded-3">
                        Admin Login →
                    </a>
                </div>
            </div>

        </div>

        <!-- Footer Info -->
        <div class="text-center mt-5 text-muted small">
            <p class="mb-0">Nimbus Smart Restaurant Food Ordering System • Built for Modern Gastronomy</p>
        </div>
    </main>

</body>
</html>
