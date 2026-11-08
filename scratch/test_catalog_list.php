<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';
require_once BASE_PATH . '/app/Helpers/Session.php';
require_once BASE_PATH . '/app/Helpers/CSRF.php';
require_once BASE_PATH . '/app/Helpers/Auth.php';
require_once BASE_PATH . '/app/Helpers/Response.php';

// Mock currentUser superadmin
$_SESSION['user'] = [
    'id' => 1,
    'full_name' => 'System Administrator',
    'role_id' => 1,
    'role_code' => 'SUPERADMIN',
    'role_name' => 'Super Administrator',
    'status' => 'ACTIVE',
];

require_once BASE_PATH . '/app/Controllers/WarehouseController.php';

// Capture output
ob_start();
try {
    $wc = new WarehouseController();
    $wc->catalogList();
    $output = ob_get_clean();
    echo "SUCCESS: catalogList rendered without any errors! Output size: " . strlen($output) . " bytes\n";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
