<?php
/**
 * Add performance indexes on warehouse_damages table for fast QR tag and status scanning.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    $existingIndexes = array_column(Database::fetchAll("SHOW INDEX FROM `warehouse_damages`"), 'Key_name');
    
    if (!in_array('idx_warehouse_damages_tag_code', $existingIndexes, true)) {
        Database::getInstance()->exec("ALTER TABLE `warehouse_damages` ADD INDEX `idx_warehouse_damages_tag_code` (`tag_code`)");
        echo "SUCCESS: Added index idx_warehouse_damages_tag_code to warehouse_damages.\n";
    } else {
        echo "INFO: Index idx_warehouse_damages_tag_code already exists.\n";
    }

    if (!in_array('idx_warehouse_damages_status', $existingIndexes, true)) {
        Database::getInstance()->exec("ALTER TABLE `warehouse_damages` ADD INDEX `idx_warehouse_damages_status` (`status`)");
        echo "SUCCESS: Added index idx_warehouse_damages_status to warehouse_damages.\n";
    } else {
        echo "INFO: Index idx_warehouse_damages_status already exists.\n";
    }

    echo "SUCCESS: warehouse_damages indexes migration completed.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Database Error: " . $e->getMessage() . "\n");
    exit(1);
}
