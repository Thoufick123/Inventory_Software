<?php
require_once 'C:/xampp/htdocs/test/Inventory_Software/config/app.php';
require_once 'C:/xampp/htdocs/test/Inventory_Software/app/Helpers/Database.php';

$pdo = Database::getInstance();
$row = $pdo->query('SELECT NOW() as db_now, @@global.time_zone as tz, @@session.time_zone as stz')->fetch(PDO::FETCH_ASSOC);
echo "DB NOW: " . $row['db_now'] . " (Global TZ: " . $row['tz'] . ", Session TZ: " . $row['stz'] . ")\n";
echo "PHP Date: " . date('Y-m-d H:i:s') . " (PHP TZ: " . date_default_timezone_get() . ")\n";
date_default_timezone_set('Asia/Kolkata');
echo "After set Asia/Kolkata: " . date('Y-m-d H:i:s') . " (PHP TZ: " . date_default_timezone_get() . ")\n";
