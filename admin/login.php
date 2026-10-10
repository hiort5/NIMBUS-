<?php
session_start();
include("../config/db.php");

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $username_safe = mysqli_real_escape_string($conn, $username);
    $sql = "SELECT * FROM admin WHERE username='$username_safe' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        $error = "Database Error: " . mysqli_error($conn);
    } elseif (mysqli_num_rows($result) == 0) {
        $error = "Username not found.";
    } else {
        $row = mysqli_fetch_assoc($result);
        if ($row['password'] === $password) {
            $_SESSION['admin'] = $row['username'];
            $_SESSION['admin_role'] = $row['role'] ?? 'Admin';
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Nimbus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
    <style>
        body.admin-dark-body {
            background: radial-gradient(circle at 50% 30%, #1e293b 0%, #090d16 100%) !important;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .admin-login-box {
            max-width: 420px;
            width: 100%;
            background: #131b2e;
            border: 1px solid #23324d;
            border-radius: var(--nb-radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.05);
            padding: 40px 32px;
            color: #f8fafc;
        }
        .admin-login-box h3,
        .admin-login-box .admin-login-title {
            color: #ffffff !important;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .admin-login-box .text-muted,
        .admin-login-box .login-subtitle {
            color: #94a3b8 !important;
        }
        .admin-login-box a.back-link {
            color: #94a3b8;
            transition: color 0.2s ease;
        }
        .admin-login-box a.back-link:hover {
            color: #f59e0b;
        }
        .admin-login-box .form-label {
            color: #cbd5e1 !important;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .admin-login-box .form-control {
            background-color: #0b1120 !important;
            border: 1.5px solid #23324d !important;
            color: #ffffff !important;
            font-size: 14.5px;
        }
        .admin-login-box .form-control::placeholder {
            color: #64748b !important;
            opacity: 1;
        }
        .admin-login-box .form-control:focus {
            background-color: #0f172a !important;
            border-color: #f59e0b !important;
            color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25) !important;
        }
        .admin-login-box .btn-dark {
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%) !important;
            border: none !important;
            color: #0f172a !important;
            font-weight: 800 !important;
            font-size: 15px;
            box-shadow: 0 4px 18px rgba(245, 158, 11, 0.35) !important;
            transition: all 0.2s ease;
        }
        .admin-login-box .btn-dark:hover {
            opacity: 0.95;
            color: #000000 !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 22px rgba(245, 158, 11, 0.5) !important;
        }
        .admin-login-box code {
            background: rgba(245, 158, 11, 0.15) !important;
            color: #fbbf24 !important;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid rgba(245, 158, 11, 0.25);
        }
    </style>
</head>
<body class="admin-dark-body">

    <div class="admin-login-box">
        <div class="text-center mb-4">
            <a href="../index.php" class="back-link small text-decoration-none d-inline-block mb-2">← Back to Portal</a>
            <div class="fs-1 mb-1">⚙️</div>
            <h3 class="admin-login-title fw-bold mb-1">Nimbus Admin</h3>
            <p class="login-subtitle small">Sign in to restaurant administration panel</p>
        </div>

        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger rounded-3 py-2 px-3 small">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-uppercase">Username</label>
                <input type="text" name="username" class="form-control rounded-3 py-2" value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>" placeholder="Enter username" required autofocus>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-uppercase">Password</label>
                <input type="password" name="password" class="form-control rounded-3 py-2" placeholder="••••••••" required>
            </div>

            <button type="submit" name="login" class="btn btn-dark w-100 py-3 fw-bold rounded-3 shadow">
                Sign In to Dashboard →
            </button>
        </form>

        <div class="text-center mt-4 login-subtitle small">
            Default credentials: <code>admin</code> / <code>admin123</code>
        </div>
    </div>

</body>
</html>
