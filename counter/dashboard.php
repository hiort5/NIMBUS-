<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

// Fetch active sessions mapped by table_no
$sessions_by_table = [];
$active_sessions_q = mysqli_query($conn, "
    SELECT session_id, table_no, session_status, payment_status, start_time, end_time, total_amount
    FROM dining_session
    WHERE session_status != 'Completed'
");

if ($active_sessions_q) {
    while ($s_row = mysqli_fetch_assoc($active_sessions_q)) {
        $t_num = (int)$s_row['table_no'];
        $s_id = (int)$s_row['session_id'];

        // Calculate accurate current bill from order_items
        $bill_q = mysqli_query($conn, "
            SELECT COALESCE(SUM(order_items.subtotal), 0) AS bill_total
            FROM orders
            INNER JOIN order_items ON orders.order_id = order_items.order_id
            WHERE orders.session_id = '$s_id'
        ");
        $s_row['calculated_total'] = (float)mysqli_fetch_assoc($bill_q)['bill_total'];
        $sessions_by_table[$t_num] = $s_row;
    }
}

// Calculate revenue metrics for today
$revenue_today = 0;
$today_start = date("Y-m-d 00:00:00");
$rev_q = mysqli_query($conn, "
    SELECT COALESCE(SUM(amount), 0) AS total_rev
    FROM payments
    WHERE payment_status = 'Paid'
    AND payment_time >= '$today_start'
");
if ($rev_q) $revenue_today = (float)mysqli_fetch_assoc($rev_q)['total_rev'];

// Counts
$count_dining = 0;
$count_waiting = 0;
$count_vacant = 0;

for ($i = 1; $i <= 20; $i++) {
    if (isset($sessions_by_table[$i])) {
        if ($sessions_by_table[$i]['session_status'] == 'Waiting Payment') {
            $count_waiting++;
        } else {
            $count_dining++;
        }
    } else {
        $count_vacant++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counter POS & Floor Dashboard - Nimbus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
</head>
<body class="counter-dark-body">

    <!-- Top Navigation -->
    <nav class="nimbus-navbar">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <a href="../index.php" class="nimbus-brand">
                ☁ Nimbus
                <span class="nimbus-brand-badge">Counter POS</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white small d-none d-md-inline" id="counterClock">--:--:--</span>
                <a href="../index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    Exit POS
                </a>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-4 py-4">
        
        <!-- Summary Stats Banner -->
        <div class="row g-3 mb-4">
            
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="fs-2 p-3 bg-success-subtle rounded-3">🟢</div>
                        <div>
                            <div class="text-muted small fw-bold">Vacant Tables</div>
                            <div class="fs-3 fw-bold text-white"><?php echo $count_vacant; ?> / 20</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="fs-2 p-3 bg-warning-subtle rounded-3">🟡</div>
                        <div>
                            <div class="text-muted small fw-bold">Active Dining</div>
                            <div class="fs-3 fw-bold text-white"><?php echo $count_dining; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="fs-2 p-3 bg-danger-subtle rounded-3">🔴</div>
                        <div>
                            <div class="text-muted small fw-bold">Payment Pending</div>
                            <div class="fs-3 fw-bold text-danger"><?php echo $count_waiting; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="fs-2 p-3 bg-primary-subtle rounded-3">💵</div>
                        <div>
                            <div class="text-muted small fw-bold">Today's Revenue</div>
                            <div class="fs-3 fw-bold text-success">₹<?php echo number_format($revenue_today, 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Section Title & Filter -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-white">Restaurant Floor Plan (20 Tables)</h2>
                <p class="text-muted small mb-0">Live table occupancy, billing settlements, and active orders</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-dark rounded-pill px-3 floor-filter-btn" data-filter="all">All (20)</button>
                <button class="btn btn-sm btn-outline-dark rounded-pill px-3 floor-filter-btn" data-filter="occupied">Occupied (<?php echo $count_dining + $count_waiting; ?>)</button>
                <button class="btn btn-sm btn-outline-dark rounded-pill px-3 floor-filter-btn" data-filter="vacant">Vacant (<?php echo $count_vacant; ?>)</button>
                <button class="btn btn-sm btn-outline-danger rounded-pill px-3 floor-filter-btn" data-filter="waiting">Pending Pay (<?php echo $count_waiting; ?>)</button>
            </div>
        </div>

        <!-- 20-Table Grid -->
        <div class="floor-grid" id="floorPlanGrid">
            <?php
            for ($table_no = 1; $table_no <= 20; $table_no++) {
                $has_session = isset($sessions_by_table[$table_no]);
                $sess = $has_session ? $sessions_by_table[$table_no] : null;

                $status_type = 'vacant';
                $badge_class = 'badge-vacant';
                $badge_label = '🟢 Vacant';
                $bill_amount = 0;
                $session_id = 0;

                if ($has_session) {
                    $session_id = (int)$sess['session_id'];
                    $bill_amount = (float)$sess['calculated_total'];

                    if ($sess['session_status'] == 'Waiting Payment') {
                        $status_type = 'waiting';
                        $badge_class = 'badge-waiting';
                        $badge_label = '🔴 Payment Due';
                    } elseif ($sess['payment_status'] == 'Paid') {
                        $status_type = 'paid';
                        $badge_class = 'badge-paid';
                        $badge_label = '🔵 Paid';
                    } else {
                        $status_type = 'dining';
                        $badge_class = 'badge-dining';
                        $badge_label = '🟡 Dining';
                    }
                }
            ?>
                <div class="table-card-wrapper" data-status="<?php echo $status_type; ?>" data-table="<?php echo $table_no; ?>">
                    <div class="table-card-pos <?php echo $status_type; ?>">
                        
                        <div class="table-header-row">
                            <div class="table-number-lg">Table <?php echo $table_no; ?></div>
                            <span class="status-badge-pos <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                        </div>

                        <?php if (!$has_session) { ?>
                            <div class="text-center py-4 my-auto">
                                <div class="text-muted small">Table is available for new guests</div>
                            </div>
                        <?php } else { ?>
                            <div class="my-2">
                                <div class="d-flex justify-content-between text-muted small py-1 border-bottom border-secondary-subtle">
                                    <span>Session ID</span>
                                    <strong class="text-white">#<?php echo $session_id; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between text-muted small py-1 border-bottom border-secondary-subtle">
                                    <span>Started At</span>
                                    <span class="text-white"><?php echo date("h:i A", strtotime($sess['start_time'])); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center pt-2">
                                    <span class="text-muted small fw-bold">Current Bill:</span>
                                    <span class="fs-5 fw-bold text-warning">₹<?php echo number_format($bill_amount, 2); ?></span>
                                </div>
                            </div>

                            <div class="mt-3 pt-2 border-top d-flex flex-column gap-2">
                                
                                <?php if ($sess['session_status'] == 'Waiting Payment' && $sess['payment_status'] != 'Paid') { ?>
                                    <form action="mark_paid.php" method="POST" class="m-0">
                                        <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                        <button type="submit" class="btn btn-success btn-sm w-100 py-2 fw-bold rounded-3" onclick="return confirm('Confirm payment received for Table <?php echo $table_no; ?>?');">
                                            ✅ Mark as Paid
                                        </button>
                                    </form>
                                <?php } ?>

                                <a href="force_end_session.php?session_id=<?php echo $session_id; ?>" 
                                   class="btn btn-outline-danger btn-sm py-1 rounded-3 small" 
                                   onclick="return confirm('End session for Table <?php echo $table_no; ?>? This will free the table.');">
                                    Release Table
                                </a>

                            </div>
                        <?php } ?>

                    </div>
                </div>
            <?php } ?>
        </div>

    </main>

    <script src="../assets/js/nimbus.js"></script>
    <script>
        // Live Clock
        function updateClock() {
            const now = new Date();
            const el = document.getElementById('counterClock');
            if (el) el.innerText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Floor Filter
        document.querySelectorAll('.floor-filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.floor-filter-btn').forEach(b => {
                    b.classList.remove('btn-dark');
                    b.classList.add('btn-outline-dark');
                });
                this.classList.remove('btn-outline-dark');
                this.classList.add('btn-dark');

                const filter = this.dataset.filter;
                document.querySelectorAll('.table-card-wrapper').forEach(card => {
                    const status = card.dataset.status;
                    if (filter === 'all') {
                        card.style.display = '';
                    } else if (filter === 'occupied') {
                        card.style.display = (status !== 'vacant') ? '' : 'none';
                    } else if (filter === 'vacant') {
                        card.style.display = (status === 'vacant') ? '' : 'none';
                    } else if (filter === 'waiting') {
                        card.style.display = (status === 'waiting') ? '' : 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
