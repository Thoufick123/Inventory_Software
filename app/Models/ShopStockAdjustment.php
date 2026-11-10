<?php

class ShopStockAdjustment {
    private static bool $tableReady = false;

    public static function ensureTable(): void {
        if (self::$tableReady) {
            return;
        }

        Database::query(
            "CREATE TABLE IF NOT EXISTS shop_stock_adjustments (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                adjustment_number varchar(50) NOT NULL,
                shop_id int(10) unsigned NOT NULL,
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
                KEY idx_shop_adjustment_shop (shop_id),
                KEY idx_shop_adjustment_variant (variant_id),
                KEY idx_shop_adjustment_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$tableReady = true;
    }

    public static function getAll(int $shopId): array {
        self::ensureTable();
        return Database::fetchAll(
            "SELECT sa.*, sh.name as shop_name, pv.sku,
                    p.name as product_name, s.name as size_name, c.name as color_name,
                    requested.full_name as requested_by_name,
                    approved.full_name as approved_by_name
             FROM shop_stock_adjustments sa
             JOIN shops sh ON sh.id = sa.shop_id
             JOIN product_variants pv ON pv.id = sa.variant_id
             JOIN products p ON p.id = pv.product_id
             LEFT JOIN sizes s ON s.id = pv.size_id
             LEFT JOIN colors c ON c.id = pv.color_id
             LEFT JOIN users requested ON requested.id = sa.requested_by
             LEFT JOIN users approved ON approved.id = sa.approved_by
             WHERE sa.shop_id = ?
             ORDER BY sa.id DESC",
            [$shopId]
        );
    }

    public static function find(int $id, int $shopId, bool $forUpdate = false): ?array {
        self::ensureTable();
        return Database::fetchOne(
            "SELECT * FROM shop_stock_adjustments WHERE id = ? AND shop_id = ?" . ($forUpdate ? " FOR UPDATE" : ""),
            [$id, $shopId]
        );
    }

    public static function create(array $data): int {
        self::ensureTable();
        Database::execute(
            "INSERT INTO shop_stock_adjustments
                (adjustment_number, shop_id, variant_id, adjustment_type, quantity, reason,
                 requested_by, status, notes, qr_codes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, NOW())",
            [
                $data['adjustment_number'],
                (int)$data['shop_id'],
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

    public static function updateStatus(int $id, int $shopId, string $status, int $approvedBy, ?array $qrCodes = null): bool {
        self::ensureTable();
        if ($qrCodes !== null) {
            return Database::execute(
                "UPDATE shop_stock_adjustments
                 SET status = ?, approved_by = ?, qr_codes = ?, updated_at = NOW()
                 WHERE id = ? AND shop_id = ? AND status = 'PENDING'",
                [
                    $status,
                    $approvedBy,
                    $qrCodes ? json_encode($qrCodes, JSON_UNESCAPED_UNICODE) : null,
                    $id,
                    $shopId,
                ]
            ) > 0;
        }
        return Database::execute(
            "UPDATE shop_stock_adjustments
             SET status = ?, approved_by = ?, updated_at = NOW()
             WHERE id = ? AND shop_id = ? AND status = 'PENDING'",
            [$status, $approvedBy, $id, $shopId]
        ) > 0;
    }

    public static function getApprovedDecreaseTags(int $shopId, int $variantId): array {
        self::ensureTable();
        return self::getApprovedTags($shopId, $variantId, 'DECREASE');
    }

    public static function getApprovedIncreaseTags(int $shopId, int $variantId): array {
        self::ensureTable();
        return self::getApprovedTags($shopId, $variantId, 'INCREASE');
    }

    private static function getApprovedTags(int $shopId, int $variantId, string $adjustmentType): array {
        $removed = [];
        $rows = Database::fetchAll(
            "SELECT qr_codes FROM shop_stock_adjustments
             WHERE shop_id = ? AND variant_id = ?
               AND adjustment_type = ? AND status = 'APPROVED'
               AND qr_codes IS NOT NULL",
            [$shopId, $variantId, $adjustmentType]
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

    public static function findApprovedIncreaseQrVariant(int $shopId, string $tag): ?int {
        self::ensureTable();
        $normalizedTag = strtoupper(trim($tag));
        $rows = Database::fetchAll(
            "SELECT variant_id, qr_codes
             FROM shop_stock_adjustments
             WHERE shop_id = ? AND adjustment_type = 'INCREASE' AND status = 'APPROVED'
               AND qr_codes IS NOT NULL",
            [$shopId]
        );
        foreach ($rows as $row) {
            $codes = json_decode((string)($row['qr_codes'] ?? ''), true);
            if (!is_array($codes)) {
                continue;
            }
            foreach ($codes as $code) {
                if (strtoupper(trim((string)$code)) === $normalizedTag) {
                    return (int)$row['variant_id'];
                }
            }
        }
        return null;
    }

    public static function findQrCodeUsage(int $shopId, string $tag, int $excludeAdjustmentId = 0): ?array {
        self::ensureTable();
        $sql = "SELECT id, adjustment_number, status, qr_codes
                FROM shop_stock_adjustments
                WHERE shop_id = ? AND status IN ('PENDING', 'APPROVED')";
        $params = [$shopId];
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
