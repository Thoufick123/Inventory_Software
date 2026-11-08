<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';
require_once BASE_PATH . '/app/Helpers/Session.php';
require_once BASE_PATH . '/app/Helpers/CSRF.php';
require_once BASE_PATH . '/app/Helpers/Auth.php';
require_once BASE_PATH . '/app/Helpers/Response.php';

$_SESSION['user'] = [
    'id' => 1,
    'full_name' => 'System Administrator',
    'role_id' => 1,
    'role_code' => 'SUPERADMIN',
    'role_name' => 'Super Administrator',
    'status' => 'ACTIVE',
];

require_once BASE_PATH . '/app/Controllers/WarehouseController.php';

$wc = new WarehouseController();

// Test createCatalog
ob_start();
try {
    $wc->createCatalog();
    $outCreate = ob_get_clean();
    echo "SUCCESS: createCatalog rendered without errors! Size: " . strlen($outCreate) . " bytes\n";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "ERROR in createCatalog: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test editCatalog with existing product
$firstProduct = Database::fetchOne("SELECT id FROM products WHERE is_active = 1 LIMIT 1");
if ($firstProduct) {
    ob_start();
    try {
        $wc->editCatalog((int)$firstProduct['id']);
        $outEdit = ob_get_clean();
        echo "SUCCESS: editCatalog rendered without errors for Product ID {$firstProduct['id']}! Size: " . strlen($outEdit) . " bytes\n";
    } catch (\Throwable $e) {
        ob_end_clean();
        echo "ERROR in editCatalog: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}
