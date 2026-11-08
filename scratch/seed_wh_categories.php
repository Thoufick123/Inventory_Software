<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

Database::execute("UPDATE warehouse_categories SET slug = 'mens-wear' WHERE id = 1 AND (slug = '' OR slug IS NULL)");

$cats = [
    ['name' => "Women's Wear", 'slug' => 'womens-wear', 'code' => 'WOM', 'hsn_code' => '6204', 'gst_rate' => 5.00, 'sort_order' => 2],
    ['name' => "Kids' Wear", 'slug' => 'kids-wear', 'code' => 'KID', 'hsn_code' => '6109', 'gst_rate' => 5.00, 'sort_order' => 3],
    ['name' => "Fabrics & Textiles", 'slug' => 'fabrics-textiles', 'code' => 'FAB', 'hsn_code' => '5208', 'gst_rate' => 5.00, 'sort_order' => 4],
    ['name' => "Home & Linen", 'slug' => 'home-linen', 'code' => 'LIN', 'hsn_code' => '6302', 'gst_rate' => 5.00, 'sort_order' => 5],
];

foreach ($cats as $cat) {
    $exists = Database::fetchOne("SELECT id FROM warehouse_categories WHERE slug = ?", [$cat['slug']]);
    if (!$exists) {
        Database::execute(
            "INSERT INTO warehouse_categories (name, slug, code, hsn_code, gst_rate, sort_order, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
            [$cat['name'], $cat['slug'], $cat['code'], $cat['hsn_code'], $cat['gst_rate'], $cat['sort_order']]
        );
    }
}

echo "Current warehouse_categories:\n";
$all = Database::fetchAll("SELECT id, name, slug, code, hsn_code, gst_rate FROM warehouse_categories ORDER BY sort_order ASC");
print_r($all);
