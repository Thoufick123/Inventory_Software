<?php
define('BASE_PATH', 'c:/xampp/htdocs/test/Inventory_Software');
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

function showCols($table) {
    echo "=== $table ===\n";
    try {
        $cols = Database::fetchAll("SHOW COLUMNS FROM $table");
        foreach ($cols as $c) {
            echo "{$c['Field']} ({$c['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Table does not exist or error: " . $e->getMessage() . "\n";
    }
}

showCols('production_batches');
showCols('production_batch_items');
showCols('production_outwards');
showCols('production_outward_items');
showCols('production_outward_batches');
showCols('qc_batches');
showCols('qc_items');
