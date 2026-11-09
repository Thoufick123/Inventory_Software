<?php
$files = [
    'c:/xampp/htdocs/test/Inventory_Software/app/Models/ProductionBatch.php',
    'c:/xampp/htdocs/test/Inventory_Software/app/Services/ProductionService.php',
    'c:/xampp/htdocs/test/Inventory_Software/app/Controllers/ProductionController.php',
    'c:/xampp/htdocs/test/Inventory_Software/views/production/batches/create.php',
    'c:/xampp/htdocs/test/Inventory_Software/views/production/batches/index.php',
];
foreach ($files as $file) {
    if (file_exists($file)) {
        $c = file_get_contents($file);
        $c = str_replace("\r\n", "\n", $c);
        file_put_contents($file, $c);
        echo "Normalized " . basename($file) . "\n";
    }
}
