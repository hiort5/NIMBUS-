<?php
session_start();

if(isset($_GET['action']) && isset($_GET['id']))
{
    $food_id = $_GET['id'];
    $action = $_GET['action'];

    if(isset($_SESSION['cart'][$food_id]))
    {
        if($action == "increase")
        {
            $_SESSION['cart'][$food_id]++;
        }

        if($action == "decrease")
        {
            $_SESSION['cart'][$food_id]--;

            if($_SESSION['cart'][$food_id] <= 0)
            {
                unset($_SESSION['cart'][$food_id]);
            }
        }
    }
}

header("Location: cart.php");
exit();
?>