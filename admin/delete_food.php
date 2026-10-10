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

$food_id = (int)$_GET['id'];

// Get image path before deleting
$stmt = mysqli_prepare($conn, "SELECT image FROM menu WHERE food_id=? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $food_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $img = $row['image'];
        if (!empty($img) && file_exists("../assets/images/" . $img)) {
            @unlink("../assets/images/" . $img);
        }
    }
    mysqli_stmt_close($stmt);
}

// Delete row
$del_stmt = mysqli_prepare($conn, "DELETE FROM menu WHERE food_id=?");
if ($del_stmt) {
    mysqli_stmt_bind_param($del_stmt, "i", $food_id);
    mysqli_stmt_execute($del_stmt);
    mysqli_stmt_close($del_stmt);
}

header("Location: view_food.php");
exit();
?>
