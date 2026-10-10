<?php
session_start();
include("../config/db.php");

// If customer already has an active session, redirect to dashboard
if (isset($_SESSION['session_id']) && isset($_SESSION['table_no'])) {
    $current_sess = (int)$_SESSION['session_id'];
    $check_active = mysqli_query($conn, "SELECT session_id, session_status FROM dining_session WHERE session_id='$current_sess' AND session_status != 'Completed' LIMIT 1");
    if ($check_active && mysqli_num_rows($check_active) > 0) {
        header("Location: dashboard.php");
        exit();
    }
}

// Fetch occupied tables
$occupied_tables = [];
$occ_res = mysqli_query($conn, "SELECT DISTINCT table_no FROM dining_session WHERE session_status != 'Completed'");
if ($occ_res) {
    while ($row = mysqli_fetch_assoc($occ_res)) {
        $occupied_tables[] = (int)$row['table_no'];
    }
}

if (isset($_POST['continue'])) {
    $table_no = (int)$_POST['table_no'];

    if ($table_no < 1 || $table_no > 20) {
        $error = "Please select a valid table number (1 to 20).";
    } else {
        $check = mysqli_query($conn, "
            SELECT session_id
            FROM dining_session
            WHERE table_no='$table_no'
            AND session_status != 'Completed'
            LIMIT 1
        ");

        if (!$check) {
            $error = "Unable to check table: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($check) > 0) {
            $error = "Table $table_no is currently occupied. Please choose another table or consult restaurant staff.";
        } else {
            $insert = mysqli_query($conn, "
                INSERT INTO dining_session(table_no, session_status, payment_status, total_amount)
                VALUES('$table_no', 'Dining', 'Pending', 0.00)
            ");

            if (!$insert) {
                $error = "Unable to start dining session: " . mysqli_error($conn);
            } else {
                $session_id = mysqli_insert_id($conn);

                unset(
                    $_SESSION['cart'],
                    $_SESSION['last_order'],
                    $_SESSION['transaction_id'],
                    $_SESSION['payment_method'],
                    $_SESSION['payment_amount']
                );

                $_SESSION['table_no'] = $table_no;
                $_SESSION['session_id'] = $session_id;

                header("Location: dashboard.php");
                exit();
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
    <title>Select Table - Nimbus Dining</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at 50% 30%, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .table-picker-card {
            background: #ffffff;
            border-radius: var(--nb-radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 540px;
            width: 100%;
            overflow: hidden;
        }
        .table-grid-select {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        .table-opt-btn {
            border: 1.5px solid var(--nb-border);
            border-radius: var(--nb-radius-md);
            padding: 12px 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f8fafc;
            user-select: none;
        }
        .table-opt-btn:hover:not(.occupied) {
            border-color: var(--nb-primary);
            background: var(--nb-primary-light);
            transform: scale(1.05);
        }
        .table-opt-btn.selected {
            background: var(--nb-dark);
            color: #ffffff;
            border-color: var(--nb-dark);
            box-shadow: var(--nb-shadow-md);
        }
        .table-opt-btn.occupied {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
            cursor: not-allowed;
            opacity: 0.6;
        }
    </style>
</head>
<body>

    <div class="table-picker-card p-4 p-md-5">
        <div class="text-center mb-4">
            <a href="../index.php" class="text-muted small text-decoration-none d-inline-block mb-2">← Back to Portal</a>
            <h2 class="display-6 fw-bold mb-1">☁ Nimbus</h2>
            <p class="text-muted">Welcome! Choose your table to view menu & order.</p>
        </div>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger rounded-3 py-2 px-3 small">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <form method="POST" id="tableForm">
            <input type="hidden" name="table_no" id="selectedTableNo" value="<?php echo !in_array(1, $occupied_tables) ? '1' : ''; ?>" required>

            <label class="form-label fw-bold small text-uppercase text-muted">Select Table</label>
            
            <div class="table-grid-select">
                <?php for ($i = 1; $i <= 20; $i++) { 
                    $is_occupied = in_array($i, $occupied_tables);
                    $is_default = ($i === 1 && !$is_occupied);
                ?>
                    <div class="table-opt-btn <?php echo $is_occupied ? 'occupied' : ($is_default ? 'selected' : ''); ?>" 
                         data-table="<?php echo $i; ?>"
                         onclick="selectFloorTable(<?php echo $i; ?>, <?php echo $is_occupied ? 'true' : 'false'; ?>)">
                        <div class="fw-bold fs-5"><?php echo $i; ?></div>
                        <div style="font-size: 10px;"><?php echo $is_occupied ? 'Occupied' : 'Vacant'; ?></div>
                    </div>
                <?php } ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4 px-1 small text-muted">
                <div><span class="d-inline-block rounded-circle bg-success me-1" style="width:8px;height:8px;"></span> Vacant</div>
                <div><span class="d-inline-block rounded-circle bg-danger me-1" style="width:8px;height:8px;"></span> Occupied</div>
            </div>

            <button type="submit" name="continue" id="submitBtn" class="btn btn-dark w-100 py-3 fw-bold rounded-3 shadow">
                Start Dining →
            </button>
        </form>
    </div>

    <script>
        function selectFloorTable(num, isOccupied) {
            if (isOccupied) return;
            document.getElementById('selectedTableNo').value = num;
            document.querySelectorAll('.table-opt-btn').forEach(btn => {
                if (!btn.classList.contains('occupied')) {
                    btn.classList.remove('selected');
                }
            });
            const selectedBtn = document.querySelector(`.table-opt-btn[data-table="${num}"]`);
            if (selectedBtn) selectedBtn.classList.add('selected');
        }
    </script>
</body>
</html>
