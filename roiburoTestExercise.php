<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'roiburo_test_db');
define('DB_PORT', 3306);

// Основной код
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new mysqli(
            DB_HOST, 
            DB_USER, 
            DB_PASS, 
            DB_NAME, 
            DB_PORT
        );
        
        if ($this->conn->connect_error) {
            throw new RuntimeException(
                "Connection failed: " . $this->conn->connect_error
            );
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}

class CategoryManager {
    private $categories = [];
    private $productCounts = [];
    private $products = [];

    public function __construct() {
        $this->loadData();
    }

    private function loadData() {
        $conn = Database::getInstance();
        
        // Загрузка категорий
        $stmt = $conn->prepare("SELECT id, id_parent, name FROM groups");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $this->categories[$row['id']] = $row;
        }

        // Загрузка продуктов
        $stmt = $conn->prepare("SELECT id_group, name FROM products");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $this->products[$row['id_group']][] = htmlspecialchars($row['name']);
            $this->productCounts[$row['id_group']] = 
                ($this->productCounts[$row['id_group']] ?? 0) + 1;
        }
    }

    private function getChildren($parentId) {
        return array_filter($this->categories, function($category) use ($parentId) {
            return $category['id_parent'] == $parentId;
        });
    }

    private function getAllChildrenIds($parentId) {
        $childrenIds = [];
        foreach ($this->getChildren($parentId) as $child) {
            $childrenIds[] = $child['id'];
            $childrenIds = array_merge($childrenIds, $this->getAllChildrenIds($child['id']));
        }
        return $childrenIds;
    }

    private function countTotalProducts($groupId) {
        $total = $this->productCounts[$groupId] ?? 0;
        foreach ($this->getChildren($groupId) as $child) {
            $total += $this->countTotalProducts($child['id']);
        }
        return $total;
    }

    public function renderTree($selectedGroup = 0) {
        $output = '<div style="display: flex;">';
        // Левая часть: Категории
        $output .= '<div style="width: 30%; border-right: 1px solid #ccc; padding: 10px;">';
        $output .= '<h2>Категории</h2>';
        $output .= '<ul style="list-style-type: none; padding: 0;">';
        $output .= $this->renderCategoryList(0, $selectedGroup);
        $output .= '</ul>';
        $output .= '</div>';

        // Правая часть: Товары
        $output .= '<div style="width: 70%; padding: 10px;">';
        if ($selectedGroup == 0) {
            $output .= '<h2>Все товары</h2>';
            $output .= $this->renderAllProducts();
        } else {
            $output .= '<h2>' . htmlspecialchars($this->categories[$selectedGroup]['name']) . '</h2>';
            $allChildrenIds = $this->getAllChildrenIds($selectedGroup);
            $allChildrenIds[] = $selectedGroup;
            $output .= $this->renderProducts($allChildrenIds);
        }
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    private function renderCategoryList($parentId, $selectedGroup) {
        $children = $this->getChildren($parentId);
        if (empty($children)) {
            return '';
        }

        $output = '';
        foreach ($children as $category) {
            $total = $this->countTotalProducts($category['id']);
            $output .= '<li style="margin-bottom: 10px;">';
            if ($category['id'] == $selectedGroup) {
                $output .= '<strong>' . htmlspecialchars($category['name']) . ' (' . $total . ')</strong>';
            } else {
                $output .= '<a href="?group=' . $category['id'] . '">' . htmlspecialchars($category['name']) . ' (' . $total . ')</a>';
            }
            // Рекурсивно добавляем дочерние категории
            $output .= $this->renderCategoryList($category['id'], $selectedGroup);
            $output .= '</li>';
        }
        return $output;
    }

    private function renderProducts($groupIds) {
        $allProducts = [];
        foreach ($groupIds as $groupId) {
            if (!empty($this->products[$groupId])) {
                foreach ($this->products[$groupId] as $product) {
                    $allProducts[] = $product;
                }
            }
        }

        if (empty($allProducts)) {
            return '<p>Нет товаров в этой категории.</p>';
        }

        $output = '<ul>';
        foreach ($allProducts as $product) {
            $output .= '<li>' . $product . '</li>';
        }
        $output .= '</ul>';
        return $output;
    }

    private function renderAllProducts() {
        $allProducts = [];
        foreach ($this->products as $groupId => $products) {
            foreach ($products as $product) {
                $allProducts[] = $product;
            }
        }

        if (empty($allProducts)) {
            return '<p>Нет товаров.</p>';
        }

        $output = '<ul>';
        foreach ($allProducts as $product) {
            $output .= '<li>' . $product . '</li>';
        }
        $output .= '</ul>';
        return $output;
    }
}

try {
    $selectedGroup = filter_input(INPUT_GET, 'group', FILTER_VALIDATE_INT) ?: 0;
    $manager = new CategoryManager();
    echo $manager->renderTree($selectedGroup);
    
} catch (RuntimeException $e) {
    error_log($e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    exit('Service temporarily unavailable');
}
