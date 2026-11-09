<?php
define('BASE_PATH', 'c:/xampp/htdocs/test/Inventory_Software');
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    // 1. Create production_outward_batches table
    Database::execute("
        CREATE TABLE IF NOT EXISTS production_outward_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            production_outward_id INT UNSIGNED NOT NULL,
            production_batch_id INT UNSIGNED NOT NULL,
            total_qty INT NOT NULL DEFAULT 0,
            box_count INT NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pob_outward (production_outward_id),
            INDEX idx_pob_batch (production_batch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "production_outward_batches table ensured.\n";

    // 2. Ensure columns on production_outwards
    $cols = Database::fetchAll("SHOW COLUMNS FROM production_outwards LIKE 'batch_ids'");
    if (empty($cols)) {
        Database::execute("ALTER TABLE production_outwards ADD COLUMN batch_ids TEXT NULL AFTER production_batch_id");
        echo "Added batch_ids to production_outwards.\n";
    }

    $cols = Database::fetchAll("SHOW COLUMNS FROM production_outwards LIKE 'is_multi_batch'");
    if (empty($cols)) {
        Database::execute("ALTER TABLE production_outwards ADD COLUMN is_multi_batch TINYINT(1) DEFAULT 0 AFTER batch_ids");
        echo "Added is_multi_batch to production_outwards.\n";
    }

    // 3. Ensure columns on production_batches
    $cols = Database::fetchAll("SHOW COLUMNS FROM production_batches LIKE 'production_outward_id'");
    if (empty($cols)) {
        Database::execute("ALTER TABLE production_batches ADD COLUMN production_outward_id INT UNSIGNED NULL AFTER destination_warehouse_id");
        echo "Added production_outward_id to production_batches.\n";
    }

    $cols = Database::fetchAll("SHOW COLUMNS FROM production_batches LIKE 'additional_qc_batch_ids'");
    if (empty($cols)) {
        Database::execute("ALTER TABLE production_batches ADD COLUMN additional_qc_batch_ids TEXT NULL AFTER qc_batch_id");
        echo "Added additional_qc_batch_ids to production_batches.\n";
    }

    $cols = Database::fetchAll("SHOW COLUMNS FROM production_batches LIKE 'is_multi_qc'");
    if (empty($cols)) {
        Database::execute("ALTER TABLE production_batches ADD COLUMN is_multi_qc TINYINT(1) DEFAULT 0 AFTER additional_qc_batch_ids");
        echo "Added is_multi_qc to production_batches.\n";
    }

    // 4. Backfill existing production_outwards into production_outward_batches
    $existingOutwards = Database::fetchAll("SELECT id, production_batch_id, total_quantity, total_boxes FROM production_outwards WHERE production_batch_id IS NOT NULL");
    $backfilled = 0;
    foreach ($existingOutwards as $eo) {
        $exists = Database::fetchOne("SELECT id FROM production_outward_batches WHERE production_outward_id = ? AND production_batch_id = ?", [$eo['id'], $eo['production_batch_id']]);
        if (!$exists) {
            Database::execute(
                "INSERT INTO production_outward_batches (production_outward_id, production_batch_id, total_qty, box_count) VALUES (?, ?, ?, ?)",
                [$eo['id'], $eo['production_batch_id'], (int)($eo['total_quantity'] ?: 0), (int)($eo['total_boxes'] ?: 1)]
            );
            Database::execute(
                "UPDATE production_batches SET production_outward_id = ? WHERE id = ? AND production_outward_id IS NULL",
                [$eo['id'], $eo['production_batch_id']]
            );
            $backfilled++;
        }
    }
    echo "Backfilled $backfilled existing outward records into production_outward_batches.\n";

} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
