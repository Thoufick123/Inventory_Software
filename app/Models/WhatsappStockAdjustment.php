<?php

class WhatsappStockAdjustment {
    private static bool $tableReady = false;

    private static function ensureTable(): void {
        if (self::$tableReady) {
            return;
        }

        Database::query(
            "CREATE TABLE IF NOT EXISTS whatsapp_stock_adjustments (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                adjustment_number varchar(50) NOT NULL,
                channel_id int(10) unsigned NOT NULL,
                variant_id int(10) unsigned NOT NULL,
                adjustment_type enum('INCREASE','DECREASE') NOT NULL,
                quantity decimal(10,2) NOT NULL,
                reason text NOT NULL,
                requested_by int(10) unsigned DEFAULT NULL,
                approved_by int(10) unsigned DEFAULT NULL,
                status enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
                notes text DEFAULT NULL,
                qr_codes text DEFAULT NULL,
                created_at datetime DEFAULT current_timestamp(),
                updated_at datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (id),
                UNIQUE KEY adjustment_number (adjustment_number),
                KEY idx_whatsapp_adjustment_channel (channel_id),
                KEY idx_whatsapp_adjustment_variant (variant_id),
                KEY idx_whatsapp_adjustment_status (status),
                KEY idx_whatsapp_adjustment_requested_by (requested_by),
                KEY idx_whatsapp_adjustment_approved_by (approved_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$tableReady = true;
    }

    public static function getAll(): array {
        self::ensureTable();
        return Database::fetchAll(
            "SELECT wa.*, sc.name as channel_name, pv.sku,
                    p.name as product_name, s.name as size_name, c.name as color_name,
                    requested.full_name as requested_by_name,
                    approved.full_name as approved_by_name
             FROM whatsapp_stock_adjustments wa
             JOIN sales_channels sc ON sc.id = wa.channel_id
             JOIN product_variants pv ON pv.id = wa.variant_id
             JOIN products p ON p.id = pv.product_id
             LEFT JOIN sizes s ON s.id = pv.size_id
             LEFT JOIN colors c ON c.id = pv.color_id
             LEFT JOIN users requested ON requested.id = wa.requested_by
             LEFT JOIN users approved ON approved.id = wa.approved_by
             ORDER BY wa.id DESC"
        );
    }

    public static function find(int $id): ?array {
        self::ensureTable();
        return Database::fetchOne(
            "SELECT * FROM whatsapp_stock_adjustments WHERE id = ?",
            [$id]
        );
    }

    public static function create(array $data): int {
        self::ensureTable();
        Database::execute(
            "INSERT INTO whatsapp_stock_adjustments
                (adjustment_number, channel_id, variant_id, adjustment_type, quantity, reason,
                 requested_by, status, notes, qr_codes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, NOW())",
            [
                $data['adjustment_number'],
                (int)$data['channel_id'],
                (int)$data['variant_id'],
                $data['adjustment_type'],
                (float)$data['quantity'],
                $data['reason'],
                $data['requested_by'] ?? null,
                $data['notes'] ?? null,
                !empty($data['qr_codes']) ? json_encode($data['qr_codes'], JSON_UNESCAPED_UNICODE) : null,
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function updateStatus(int $id, string $status, int $approvedBy): bool {
        self::ensureTable();
        return Database::execute(
            "UPDATE whatsapp_stock_adjustments
             SET status = ?, approved_by = ?, updated_at = NOW()
             WHERE id = ?",
            [$status, $approvedBy, $id]
        ) > 0;
    }

    public static function getApprovedDecreaseTags(int $channelId, int $variantId): array {
        self::ensureTable();
        $removed = [];
        $rows = Database::fetchAll(
            "SELECT qr_codes FROM whatsapp_stock_adjustments
             WHERE channel_id = ? AND variant_id = ?
               AND adjustment_type = 'DECREASE' AND status = 'APPROVED'
               AND qr_codes IS NOT NULL",
            [$channelId, $variantId]
        );
        foreach ($rows as $row) {
            $codes = json_decode((string)($row['qr_codes'] ?? ''), true);
            if (!is_array($codes)) {
                continue;
            }
            foreach ($codes as $code) {
                $tag = strtoupper(trim((string)$code));
                if ($tag !== '') {
                    $removed[$tag] = true;
                }
            }
        }
        return array_keys($removed);
    }

    public static function findQrCodeUsage(int $channelId, string $tag, int $excludeAdjustmentId = 0): ?array {
        self::ensureTable();
        $sql = "SELECT id, adjustment_number, status, qr_codes
                FROM whatsapp_stock_adjustments
                WHERE channel_id = ? AND status IN ('PENDING', 'APPROVED')";
        $params = [$channelId];
        if ($excludeAdjustmentId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeAdjustmentId;
        }
        $sql .= " ORDER BY id DESC";
        $normalizedTag = strtoupper(trim($tag));
        foreach (Database::fetchAll($sql, $params) as $adjustment) {
            $codes = json_decode((string)($adjustment['qr_codes'] ?? ''), true);
            if (!is_array($codes)) {
                continue;
            }
            foreach ($codes as $code) {
                if (strtoupper(trim((string)$code)) === $normalizedTag) {
                    return $adjustment;
                }
            }
        }
        return null;
    }
}
