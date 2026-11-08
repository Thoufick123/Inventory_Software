<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';
require_once BASE_PATH . '/app/Helpers/Session.php';
require_once BASE_PATH . '/app/Helpers/Response.php';

// Check routes/web.php
$content = file_get_contents(BASE_PATH . '/routes/web.php');
if (strpos($content, "'warehouse/reports/export'") !== false) {
    echo "Route 'warehouse/reports/export' is registered in routes/web.php!\n";
} else {
    echo "ERROR: Route not found in routes/web.php\n";
}

// Check WarehouseController method
require_once BASE_PATH . '/app/Controllers/WarehouseController.php';
$ref = new ReflectionMethod('WarehouseController', 'exportReports');
if ($ref) {
    echo "WarehouseController::exportReports exists and is public!\n";
} else {
    echo "ERROR: exportReports method not found!\n";
}
