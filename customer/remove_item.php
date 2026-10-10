<?php
session_start();

if(isset($_GET['id']))
{
    $food_id = $_GET['id'];

    if(isset($_SESSION['cart'][$food_id]))
    {
        unset($_SESSION['cart'][$food_id]);
    }
}

header("Location: cart.php");
exit();
?>