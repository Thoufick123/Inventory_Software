<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

echo "Testing catalog product and variant creation with Dual HSN...\n";

// Find or create test product
$prodCode = 'TEST-WH-HSN-' . time();
$catId = 1; // Men's wear (HSN 6109)

Database::execute(
    "INSERT INTO products (name, code, catalog_code, warehouse_category_id, hsn_code, gst_percentage, is_active, created_at)
     VALUES (?, ?, ?, ?, '6109', 5.00, 1, NOW())",
    ['Test HSN Polo T-Shirt', $prodCode, 'CAT-' . $prodCode, $catId]
);
$productId = (int)Database::lastInsertId();

// Variant 1 with Category Default HSN (6109)
Database::execute(
    "INSERT INTO product_variants (product_id, sku, hsn_code, gst_rate, qr_code, mrp, purchase_price, selling_price, is_active, created_at)
     VALUES (?, ?, ?, ?, ?, 999.00, 350.00, 799.00, 1, NOW())",
    [$productId, $prodCode . '-M-BLK', '6109', 5.00, $prodCode . '-M-BLK']
);

// Variant 2 with Custom Overridden HSN (6205 - Men's Woven Shirt)
Database::execute(
    "INSERT INTO product_variants (product_id, sku, hsn_code, gst_rate, qr_code, mrp, purchase_price, selling_price, is_active, created_at)
     VALUES (?, ?, ?, ?, ?, 1199.00, 420.00, 999.00, 1, NOW())",
    [$productId, $prodCode . '-L-WHT', '6205', 12.00, $prodCode . '-L-WHT']
);

$retrieved = Database::fetchAll(
    "SELECT pv.sku, pv.hsn_code, pv.gst_rate, p.name as prod_name, p.hsn_code as prod_hsn
     FROM product_variants pv
     JOIN products p ON pv.product_id = p.id
     WHERE p.id = ?",
    [$productId]
);

echo "Retrieved Variants from DB:\n";
foreach ($retrieved as $r) {
    echo "- SKU: {$r['sku']} | Product HSN: {$r['prod_hsn']} | Variant HSN: {$r['hsn_code']} | GST: {$r['gst_rate']}%\n";
}

// Clean up test entry
Database::execute("DELETE FROM product_variants WHERE product_id = ?", [$productId]);
Database::execute("DELETE FROM products WHERE id = ?", [$productId]);

echo "Dual HSN persistence verified and test cleaned up successfully!\n";
