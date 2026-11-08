<?php
$mainFile = 'C:/xampp/htdocs/test/Inventory_Software/views/layouts/main.php';
$content = file_get_contents($mainFile);

// Add Returns
$find1 = "<li><a href=\"<?= url('production/billing') ?>\" class=\"<?= (\$activeMenu ?? '') === 'prod-billing' ? 'active' : '' ?>\">Billing</a></li>\n                            <?php endif; ?>";
$replace1 = "<li><a href=\"<?= url('production/billing') ?>\" class=\"<?= (\$activeMenu ?? '') === 'prod-billing' ? 'active' : '' ?>\">Billing</a></li>\n                            <li><a href=\"<?= url('production/returns') ?>\" class=\"<?= (\$activeMenu ?? '') === 'prod-returns' ? 'active' : '' ?>\">Returns &amp; Exchanges</a></li>\n                            <?php endif; ?>";

// Add Stock Requests
$find2 = "<li><a href=\"<?= url('production/inventory') ?>\" class=\"<?= (\$activeMenu ?? '') === 'prod-inventory' ? 'active' : '' ?>\">Inventory</a></li>\n                            <?php endif; ?>";
$replace2 = "<li><a href=\"<?= url('production/inventory') ?>\" class=\"<?= (\$activeMenu ?? '') === 'prod-inventory' ? 'active' : '' ?>\">Inventory</a></li>\n                            <?php endif; ?>\n                            <li><a href=\"<?= url('production/stock-requests') ?>\" class=\"<?= (\$activeMenu ?? '') === 'prod-stock-requests' ? 'active' : '' ?>\">Stock Requests</a></li>";

$content = str_replace($find1, $replace1, $content);
$content = str_replace($find2, $replace2, $content);

file_put_contents($mainFile, $content);
echo "SUCCESS: views/layouts/main.php updated with Returns and Stock Requests links!\n";
