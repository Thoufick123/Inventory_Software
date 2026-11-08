<?php
$lines = file('Inventory_Software/views/warehouse/catalog/index.php');
foreach ($lines as $num => $l) {
    if (strpos($l, '$products') !== false) {
        echo ($num + 1) . ": " . trim($l) . "\n";
    }
}
