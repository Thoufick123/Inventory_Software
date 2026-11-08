<?php
$testFile = 'C:/xampp/htdocs/test/Inventory_Software/scratch/test_production_30_rolls_full_lifecycle.php';
$content = file_get_contents($testFile);
$find = "\$tags[] = 'TG30R-' . date('md') . '-' . str_pad((string)\$t, 4, '0', STR_PAD_LEFT);";
$replace = "\$tags[] = 'TG30R-' . date('His') . '-' . str_pad((string)\$t, 4, '0', STR_PAD_LEFT);";
$content = str_replace($find, $replace, $content);
file_put_contents($testFile, $content);
echo "Updated tag generator with unique timestamp\n";
