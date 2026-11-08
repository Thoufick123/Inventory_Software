<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

echo "warehouse_categories count: " . count(Database::fetchAll("SELECT * FROM warehouse_categories")) . "\n";
echo "categories count: " . count(Database::fetchAll("SELECT * FROM categories")) . "\n";

$prodCats = Database::fetchAll("SELECT id, name, hsn_code, gst_rate FROM categories LIMIT 5");
print_r($prodCats);
