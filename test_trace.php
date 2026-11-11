<?php
define('BASE_PATH', __DIR__);
$appConfig = require BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';
require_once BASE_PATH . '/app/Helpers/Session.php';
require_once BASE_PATH . '/app/Helpers/Auth.php';
require_once BASE_PATH . '/app/Helpers/Response.php';

spl_autoload_register(function (string $className) {
    $cleanClass = basename(str_replace('\\', '/', $className));
    $directories = [
        BASE_PATH . '/app/Services/',
        BASE_PATH . '/app/Models/',
        BASE_PATH . '/app/Helpers/',
        BASE_PATH . '/app/Controllers/',
    ];
    foreach ($directories as $dir) {
        $file = $dir . $cleanClass . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

try {
    $res = QrFinderService::trace('AST-PRD-0001');
    print_r($res);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
}
