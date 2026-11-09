<?php
define('BASE_PATH', 'c:/xampp/htdocs/test/Inventory_Software');
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

echo "=== qc_batches COLUMNS ===\n";
$cols = Database::fetchAll("SHOW COLUMNS FROM qc_batches");
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

echo "\n=== qc_items COLUMNS ===\n";
$cols = Database::fetchAll("SHOW COLUMNS FROM qc_items");
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

echo "\n=== Sample qc_batches ===\n";
$samples = Database::fetchAll("SELECT * FROM qc_batches LIMIT 5");
print_r($samples);

echo "\n=== Sample qc_items ===\n";
$samples2 = Database::fetchAll("SELECT * FROM qc_items LIMIT 5");
print_r($samples2);
