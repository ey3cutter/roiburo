<?php

// Подключение к базе данных
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roiburo_test_db";
$port = "3306";

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function countProductsInGroup($groupId, $conn) {
    $totalProducts = 0;

    $sql = "SELECT COUNT(*) as total FROM products WHERE id_group = $groupId";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $totalProducts += $row['total'];

    $sql = "SELECT id FROM categories WHERE id_parent = $groupId";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $totalProducts += countProductsInGroup($row['id'], $conn);
        }
    }

    return $totalProducts;
}

function displayGroups($parentId, $conn) {
    $output = "<ul>";

    $sql = "SELECT id, name FROM categories WHERE id_parent = $parentId";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $groupId = $row['id'];
            $groupName = $row['name'];
            $totalProducts = countProductsInGroup($groupId, $conn);

            $output .= "<li><a href='roiburoTestExercise.php?group=$groupId'>$groupName ($totalProducts)</a>";
            $output .= displayGroups($groupId, $conn);
            
            $output .= "</li>";
            $output .= displayProducts($groupId, $conn);
        }
    }

    $output .= "</ul>";

    return $output;
}

function displayProducts($groupId, $conn) {
    $output = "<ul>";

    $sql = "SELECT name FROM products WHERE id_group = $groupId";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $productName = $row['name'];
            $output .= "<div>$productName</div>";
        }
    }

    $output .= "</ul>";

    return $output;
}

// Основной код
$selectedGroup = isset($_GET['group']) ? $_GET['group'] : 0;

echo displayGroups($selectedGroup, $conn);
echo displayProducts($selectedGroup, $conn);

$conn->close();

?>
