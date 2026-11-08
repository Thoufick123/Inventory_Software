<?php
$filePath = 'C:/xampp/htdocs/test/Inventory_Software/app/Controllers/WarehouseController.php';
$content = file_get_contents($filePath);
$isCrlf = (strpos($content, "\r\n") !== false);

// 1. In createCatalog:
// Add $hsnCodes and pass to view
$s1 = "\$sellers = Database::fetchAll(\"SELECT * FROM warehouse_suppliers WHERE is_active = 1 ORDER BY name ASC\");";
$r1 = "\$sellers = Database::fetchAll(\"SELECT * FROM warehouse_suppliers WHERE is_active = 1 ORDER BY name ASC\");\n        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");";

$s1_view = "'pendingSupplierProducts' => \$pendingSupplierProducts,";
$r1_view = "'pendingSupplierProducts' => \$pendingSupplierProducts,\n            'hsnCodes'                => \$hsnCodes,";

// 2. In editCatalog:
$s2 = "\$colors = Database::fetchAll(\"SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC\");";
$r2 = "\$colors = Database::fetchAll(\"SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC\");\n        \$hsnCodes = Database::fetchAll(\"SELECT * FROM hsn_codes ORDER BY hsn_code ASC\");";

$s2_view = "'warehouses'       => \$warehouses,";
$r2_view = "'warehouses'       => \$warehouses,\n            'hsnCodes'         => \$hsnCodes,";

// 3. In storeCatalog:
$s3 = "\$vHsn = !empty(\$v['hsn_code']) ? strtoupper(trim(\$v['hsn_code'])) : (!empty(\$_POST['hsn_code']) ? strtoupper(trim(\$_POST['hsn_code'])) : null);

                Database::execute(
                    \"INSERT INTO product_variants (product_id, size_id, color_id, sku, hsn_code, qr_code, mrp, purchase_price, production_cost, selling_price, weight, mfg_date, expiry_date, qr_type, series_start_code, series_end_code, pack_quantity, is_complementary, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)\",
                    [
                        \$productId,
                        \$sizeId,
                        \$colorId,
                        \$sku,
                        \$vHsn,
                        \$variantQr,";

$r3 = "\$vHsn = !empty(\$v['hsn_code']) ? strtoupper(trim(\$v['hsn_code'])) : (!empty(\$_POST['hsn_code']) ? strtoupper(trim(\$_POST['hsn_code'])) : null);
                \$vGst = isset(\$v['gst_rate']) && \$v['gst_rate'] !== '' ? (float)\$v['gst_rate'] : (!empty(\$_POST['gst_rate']) ? (float)\$_POST['gst_rate'] : 5.00);

                Database::execute(
                    \"INSERT INTO product_variants (product_id, size_id, color_id, sku, hsn_code, gst_rate, qr_code, mrp, purchase_price, production_cost, selling_price, weight, mfg_date, expiry_date, qr_type, series_start_code, series_end_code, pack_quantity, is_complementary, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)\",
                    [
                        \$productId,
                        \$sizeId,
                        \$colorId,
                        \$sku,
                        \$vHsn,
                        \$vGst,
                        \$variantQr,";

// 4. In updateCatalog:
$s4 = "\$vHsn = !empty(\$v['hsn_code']) ? strtoupper(trim(\$v['hsn_code'])) : (!empty(\$_POST['hsn_code']) ? strtoupper(trim(\$_POST['hsn_code'])) : null);

                \$existingVar = Database::fetchOne(\"SELECT id FROM product_variants WHERE product_id = ? AND sku = ?\", [\$id, \$sku]);
                if (\$existingVar) {
                    Database::execute(
                        \"UPDATE product_variants SET size_id = ?, color_id = ?, sku = ?, hsn_code = ?, qr_code = ?, mrp = ?, purchase_price = ?, production_cost = ?, selling_price = ?, weight = ?, mfg_date = ?, expiry_date = ?, qr_type = ?, series_start_code = ?, series_end_code = ?, pack_quantity = ?, is_complementary = ?, is_active = 1, updated_at = NOW() WHERE id = ?\",
                        [\$sizeId, \$colorId, \$sku, \$vHsn, \$sku, \$mrp, \$costPrice, \$costPrice, \$sellingPrice, \$weight, \$vMfg, \$vExp, \$qrType, \$seriesStart, \$seriesEnd, \$packQty, \$isComplementary, \$existingVar['id']]
                    );
                    \$variantId = (int)\$existingVar['id'];
                } else {
                    Database::execute(
                        \"INSERT INTO product_variants (product_id, size_id, color_id, sku, hsn_code, qr_code, mrp, purchase_price, production_cost, selling_price, weight, mfg_date, expiry_date, qr_type, series_start_code, series_end_code, pack_quantity, is_complementary, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)\",
                        [\$id, \$sizeId, \$colorId, \$sku, \$vHsn, \$sku, \$mrp, \$costPrice, \$costPrice, \$sellingPrice, \$weight, \$vMfg, \$vExp, \$qrType, \$seriesStart, \$seriesEnd, \$packQty, \$isComplementary]
                    );";

$r4 = "\$vHsn = !empty(\$v['hsn_code']) ? strtoupper(trim(\$v['hsn_code'])) : (!empty(\$_POST['hsn_code']) ? strtoupper(trim(\$_POST['hsn_code'])) : null);
                \$vGst = isset(\$v['gst_rate']) && \$v['gst_rate'] !== '' ? (float)\$v['gst_rate'] : (!empty(\$_POST['gst_rate']) ? (float)\$_POST['gst_rate'] : 5.00);

                \$existingVar = Database::fetchOne(\"SELECT id FROM product_variants WHERE product_id = ? AND sku = ?\", [\$id, \$sku]);
                if (\$existingVar) {
                    Database::execute(
                        \"UPDATE product_variants SET size_id = ?, color_id = ?, sku = ?, hsn_code = ?, gst_rate = ?, qr_code = ?, mrp = ?, purchase_price = ?, production_cost = ?, selling_price = ?, weight = ?, mfg_date = ?, expiry_date = ?, qr_type = ?, series_start_code = ?, series_end_code = ?, pack_quantity = ?, is_complementary = ?, is_active = 1, updated_at = NOW() WHERE id = ?\",
                        [\$sizeId, \$colorId, \$sku, \$vHsn, \$vGst, \$sku, \$mrp, \$costPrice, \$costPrice, \$sellingPrice, \$weight, \$vMfg, \$vExp, \$qrType, \$seriesStart, \$seriesEnd, \$packQty, \$isComplementary, \$existingVar['id']]
                    );
                    \$variantId = (int)\$existingVar['id'];
                } else {
                    Database::execute(
                        \"INSERT INTO product_variants (product_id, size_id, color_id, sku, hsn_code, gst_rate, qr_code, mrp, purchase_price, production_cost, selling_price, weight, mfg_date, expiry_date, qr_type, series_start_code, series_end_code, pack_quantity, is_complementary, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)\",
                        [\$id, \$sizeId, \$colorId, \$sku, \$vHsn, \$vGst, \$sku, \$mrp, \$costPrice, \$costPrice, \$sellingPrice, \$weight, \$vMfg, \$vExp, \$qrType, \$seriesStart, \$seriesEnd, \$packQty, \$isComplementary]
                    );";

$replacements = [
    [$s1, $r1, "createCatalog hsnCodes"],
    [$s1_view, $r1_view, "createCatalog view param"],
    [$s2, $r2, "editCatalog hsnCodes"],
    [$s2_view, $r2_view, "editCatalog view param"],
    [$s3, $r3, "storeCatalog gst_rate"],
    [$s4, $r4, "updateCatalog gst_rate"]
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
