<?php
define('BASE_PATH', __DIR__);
$appConfig = require BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    $status = Database::fetchAll("SHOW TABLE STATUS LIKE 'assets'");
    print_r($status);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
