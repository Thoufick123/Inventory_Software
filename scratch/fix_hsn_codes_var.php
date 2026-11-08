<?php
$filePath = 'C:/xampp/htdocs/test/Inventory_Software/app/Controllers/WarehouseController.php';
$content = file_get_contents($filePath);
$isCrlf = (strpos($content, "\r\n") !== false);

// 1. In catalogList (around line 10290)
$s1 = "\$warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);";
$r1 = "\$warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);\n        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");";

// 2. In direct purchase returns (around line 5985)
$s2 = "\$warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAllActive();";
$r2 = "\$warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAllActive();\n        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");";

// 3. Remove duplicate $hsnCodes in createCatalog
$s3 = "        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");\n        \$sellers = Database::fetchAll(\"SELECT * FROM warehouse_suppliers WHERE is_active = 1 ORDER BY name ASC\");\n        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");";
$r3 = "        \$sellers = Database::fetchAll(\"SELECT * FROM warehouse_suppliers WHERE is_active = 1 ORDER BY name ASC\");\n        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");";

// 4. Remove duplicate $hsnCodes in editCatalog
$s4 = "        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");\n        if (empty(\$sellers)) {\n            \$sellers = Database::fetchAll(\"SELECT * FROM sellers WHERE is_active = 1 ORDER BY name ASC\");\n        }\n        \$sizes = Database::fetchAll(\"SELECT * FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC\");\n        \$colors = Database::fetchAll(\"SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC\");\n        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");";
$r4 = "        if (empty(\$sellers)) {\n            \$sellers = Database::fetchAll(\"SELECT * FROM sellers WHERE is_active = 1 ORDER BY name ASC\");\n        }\n        \$sizes = Database::fetchAll(\"SELECT * FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC\");\n        \$colors = Database::fetchAll(\"SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC\");\n        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");";

$replacements = [
    [$s1, $r1, "catalogList $hsnCodes definition"],
    [$s2, $r2, "purchaseReturns $hsnCodes definition"],
    [$s3, $r3, "createCatalog duplicate cleanup"],
    [$s4, $r4, "editCatalog duplicate cleanup"],
];

foreach ($replacements as [$search, $replace, $label]) {
    if ($isCrlf) {
        $search = str_replace("\n", "\r\n", $search);
        $replace = str_replace("\n", "\r\n", $replace);
    }
    if (strpos($content, $search) !== false) {
        $content = str_replace($search, $replace, $content);
        echo "Successfully patched: $label\n";
    } else {
        echo "Could not find: $label\n";
    }
}

file_put_contents($filePath, $content);
