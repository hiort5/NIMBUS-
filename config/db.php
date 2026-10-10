<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "smart_restaurant";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

// Auto-sync category_id for menu items where category_id IS NULL
$sync_check = @mysqli_query($conn, "SELECT COUNT(*) FROM menu WHERE category_id IS NULL");
if ($sync_check) {
    $count_row = mysqli_fetch_row($sync_check);
    if ($count_row && $count_row[0] > 0) {
        // Map food items to categories
        @mysqli_query($conn, "
            UPDATE menu 
            SET category_id = (
                CASE 
                    WHEN category IN ('Beverage', 'Beverages', 'Soft Drinks', 'Juices', 'Coffee') THEN (SELECT category_id FROM categories WHERE category_name = 'Beverages' LIMIT 1)
                    WHEN category IN ('Dessert', 'Desserts', 'Ice Cream', 'Cake', 'Brownies') THEN (SELECT category_id FROM categories WHERE category_name = 'Desserts' LIMIT 1)
                    ELSE (SELECT category_id FROM categories WHERE category_name = 'Food' LIMIT 1)
                END
            )
            WHERE category_id IS NULL
        ");

        // Ensure Pizza subcategory exists
        @mysqli_query($conn, "INSERT IGNORE INTO subcategories (category_id, subcategory_name) VALUES (1, 'Pizza')");

        // Map subcategories
        @mysqli_query($conn, "
            UPDATE menu 
            SET subcategory_id = (
                SELECT s.subcategory_id 
                FROM subcategories s 
                WHERE LOWER(s.subcategory_name) = LOWER(menu.category)
                   OR (menu.food_name LIKE '%Burger%' AND s.subcategory_name = 'Burger')
                   OR (menu.food_name LIKE '%Pizza%' AND s.subcategory_name = 'Pizza')
                   OR (menu.food_name LIKE '%Fries%' AND s.subcategory_name = 'Fries')
                   OR (menu.food_name LIKE '%Ice Cream%' AND s.subcategory_name = 'Ice Cream')
                   OR (menu.food_name LIKE '%Wrap%' AND s.subcategory_name = 'Wraps')
                   OR (menu.food_name LIKE '%Brownie%' AND s.subcategory_name = 'Brownies')
                   OR (menu.food_name LIKE '%Blondie%' AND s.subcategory_name = 'Brownies')
                   OR (menu.food_name LIKE '%Cheesecake%' AND s.subcategory_name = 'Cake')
                   OR (menu.food_name IN ('Coke', 'Sprite', 'Pepsi') AND s.subcategory_name = 'Soft Drinks')
                LIMIT 1
            )
            WHERE subcategory_id IS NULL
        ");
    }
}

// Auto-seed appetizing culinary descriptions if missing
$desc_check = @mysqli_query($conn, "SELECT COUNT(*) FROM menu WHERE description IS NULL OR description = ''");
if ($desc_check) {
    $desc_count = mysqli_fetch_row($desc_check);
    if ($desc_count && $desc_count[0] > 0) {
        @mysqli_query($conn, "
            UPDATE menu SET description = CASE food_id 
                WHEN 1 THEN 'Crispy seasoned chicken fillet layered with melted cheese, fresh iceberg lettuce & smoky house mayo in a toasted bun.'
                WHEN 2 THEN 'Juicy flame-grilled prime beef patty with melted cheddar, caramelized onions & rich barbecue glaze.'
                WHEN 3 THEN 'Golden crumbed king prawns with tangy citrus tartar sauce and crisp slaw in a soft brioche.'
                WHEN 4 THEN 'Warm toasted flatbread rolled with tender spiced chicken, crunchy peppers & velvety garlic dip.'
                WHEN 5 THEN 'Flaked tuna seasoned with cracked black pepper, crunchy cucumbers & fresh garden herb mayo.'
                WHEN 6 THEN 'Stone-baked artisan crust smothered in rich tomato sauce, melted mozzarella & crisp spicy pepperoni.'
                WHEN 7 THEN 'Fire-baked crust loaded with marinated herb chicken, roasted bell peppers & bubbly golden cheese.'
                WHEN 8 THEN 'Crispy golden fried chicken marinated in 11 aromatic spices, fried to crunch perfection.'
                WHEN 9 THEN 'Golden crispy shoestring potatoes tossed in flaky sea salt, served piping hot with house dip.'
                WHEN 10 THEN 'Bite-sized tender chicken nuggets fried to crunchy golden perfection with dipping sauce.'
                WHEN 11 THEN 'Classic effervescent Coca-Cola served ice-cold with bubbly fizz and a refreshing lemon twist.'
                WHEN 12 THEN 'Sparkling lemon-lime soda served chilled over ice for an instant burst of crisp freshness.'
                WHEN 13 THEN 'Chilled bubbly cola bursting with bold caramel sweetness and refreshing effervescence.'
                WHEN 14 THEN 'Velvety dark chocolate gelato drizzled with molten chocolate fudge and crunchy sprinkles.'
                WHEN 15 THEN 'Creamy real strawberry ice cream swirled with sweet sun-ripened strawberry compote.'
                WHEN 16 THEN 'Aromatic royal pistachio gelato churned with slow-roasted pistachio nuts & hint of cardamom.'
                WHEN 17 THEN 'Silky Madagascar vanilla bean ice cream with a delightfully rich and buttery smooth finish.'
                WHEN 18 THEN 'Warm, decadent Belgian dark chocolate brownie with a gooey molten fudge core.'
                WHEN 19 THEN 'Soft, chewy golden butterscotch vanilla bar with caramelized edges and melted white chocolate.'
                WHEN 20 THEN 'Classic velvety New York-style baked cheesecake on a buttery honey-graham cracker crust.'
                WHEN 21 THEN 'Signature extra-long potato fries dusted with smoked paprika and aromatic rosemary salt.'
                ELSE 'Handcrafted fresh to order with premium culinary ingredients.'
            END
            WHERE description IS NULL OR description = ''
        ");
    }
}
?>