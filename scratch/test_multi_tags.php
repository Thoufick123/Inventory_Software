<?php
define('BASE_PATH', 'C:/xampp/htdocs/test/Inventory_Software');
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';
require_once BASE_PATH . '/app/Models/QCBatch.php';
require_once BASE_PATH . '/app/Models/ProductionBatch.php';
require_once BASE_PATH . '/app/Services/ProductionService.php';

echo "QC #1 available tags count:\n";
$t1 = ProductionService::getAvailableQCPieceTags(1, true);
foreach ($t1 as $k => $v) {
    echo "Item $k => " . count($v) . " tags: " . implode(', ', array_slice($v, 0, 5)) . "...\n";
}

echo "\nQC #2 available tags count:\n";
$t2 = ProductionService::getAvailableQCPieceTags(2, true);
foreach ($t2 as $k => $v) {
    echo "Item $k => " . count($v) . " tags: " . implode(', ', array_slice($v, 0, 5)) . "...\n";
}
