<?php
$file = 'C:/xampp/htdocs/test/Inventory_Software/app/Services/ProductionReportService.php';
$content = file_get_contents($file);

$content = str_replace(
    'SELECT pe.title, pe.category, pe.production_unit, pe.total_cost,',
    'SELECT pe.item_name AS title, pe.category, pe.production_unit, pe.total_cost,',
    $content
);

file_put_contents($file, $content);
echo "SUCCESS: ProductionReportService updated with pe.item_name AS title!\n";
