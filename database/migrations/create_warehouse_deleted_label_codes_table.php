<?php
/**
 * Persist deleted Warehouse QR label codes so they are not generated or displayed again.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    Database::execute(
        "CREATE TABLE IF NOT EXISTS `warehouse_deleted_label_codes` (
            `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `variant_id` int unsigned DEFAULT NULL,
            `created_by` int unsigned DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`code`),
            INDEX `idx_wdlc_variant` (`variant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "warehouse_deleted_label_codes table is ready.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Warehouse deleted-label migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
