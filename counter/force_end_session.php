<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/db.php");

if (!isset($_GET['session_id']) || !is_numeric($_GET['session_id'])) {
    header("Location: dashboard.php");
    exit();
}

$session_id = (int)$_GET['session_id'];

mysqli_query($conn, "
    UPDATE dining_session
    SET session_status='Completed',
        end_time=NOW()
    WHERE session_id='$session_id'
");

header("Location: dashboard.php");
exit();
?>
