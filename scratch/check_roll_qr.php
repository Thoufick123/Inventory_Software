<?php
require_once __DIR__ . '/../app/Config/Database.php';
$db = Database::getInstance();
$rolls = Database::fetchAll("SELECT id, roll_number, qr_code, start_code FROM seller_rolls LIMIT 5");
echo json_encode($rolls, JSON_PRETTY_PRINT);
