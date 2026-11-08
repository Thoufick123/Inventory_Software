<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

$rows = Database::fetchAll("SELECT * FROM warehouse_categories");
print_r($rows);
