<?php

class ShopReturnedProduct {
    private static bool $tableReady = false;

    private static function ensureTable(): void {
        if (self::$tableReady) {
            return;
        }

        Database::query(
            "CREATE TABLE IF NOT EXISTS shop_returned_products (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                shop_id int(10) unsigned NOT NULL,
                return_id int(10) unsigned NOT NULL,
                sale_item_id int(10) unsigned DEFAULT NULL,
                variant_id int(10) unsigned NOT NULL,
                original_piece_tag varchar(150) DEFAULT NULL,
                returned_tag varchar(190) NOT NULL,
                item_condition varchar(30) NOT NULL DEFAULT 'GOOD',
                printed_at datetime DEFAULT NULL,
                printed_by int(10) unsigned DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (id),
                UNIQUE KEY uq_shop_returned_tag (returned_tag),
                KEY idx_shop_return_shop (shop_id),
                KEY idx_shop_return_id (return_id),
                KEY idx_shop_return_sale_item (sale_item_id),
                KEY idx_shop_return_variant (variant_id),
                KEY idx_shop_return_printed (printed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$tableReady = true;
    }

    public static function ensureReady(): void {
        self::ensureTable();
    }

    public static function create(array $data): int {
        self::ensureTable();
        Database::execute(
            "INSERT INTO shop_returned_products
                (shop_id, return_id, sale_item_id, variant_id, original_piece_tag, returned_tag, item_condition, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                (int)$data['shop_id'],
                (int)$data['return_id'],
                !empty($data['sale_item_id']) ? (int)$data['sale_item_id'] : null,
                (int)$data['variant_id'],
                $data['original_piece_tag'] ?: null,
                $data['returned_tag'],
                $data['item_condition'] ?? 'GOOD',
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function getAll(int $shopId): array {
        self::ensureTable();
        return Database::fetchAll(
            "SELECT srp.*, pv.sku, pv.qr_code, p.name as product_name, p.code as product_code,
                    sz.name as size_name, clr.name as color_name,
                    r.return_number, s.invoice_number, u.full_name as printed_by_name
             FROM shop_returned_products srp
             JOIN product_variants pv ON pv.id = srp.variant_id
             JOIN products p ON p.id = pv.product_id
             LEFT JOIN sizes sz ON sz.id = pv.size_id
             LEFT JOIN colors clr ON clr.id = pv.color_id
             LEFT JOIN returns r ON r.id = srp.return_id
             LEFT JOIN sales s ON s.id = r.sale_id
             LEFT JOIN users u ON u.id = srp.printed_by
             WHERE srp.shop_id = ?
             ORDER BY srp.created_at DESC, srp.id DESC",
            [$shopId]
        );
    }

    public static function find(int $id, int $shopId, bool $forUpdate = false): ?array {
        self::ensureTable();
        return Database::fetchOne(
            "SELECT srp.*, pv.sku, pv.qr_code, pv.mrp, pv.selling_price,
                    p.name as product_name, p.code as product_code,
                    sz.name as size_name, clr.name as color_name,
                    r.return_number, s.invoice_number
             FROM shop_returned_products srp
             JOIN product_variants pv ON pv.id = srp.variant_id
             JOIN products p ON p.id = pv.product_id
             LEFT JOIN sizes sz ON sz.id = pv.size_id
             LEFT JOIN colors clr ON clr.id = pv.color_id
             LEFT JOIN returns r ON r.id = srp.return_id
             LEFT JOIN sales s ON s.id = r.sale_id
             WHERE srp.id = ? AND srp.shop_id = ?
             LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
            [$id, $shopId]
        );
    }

    public static function getForVariant(int $variantId, int $shopId): array {
        self::ensureTable();
        return Database::fetchAll(
            "SELECT srp.*, r.return_number, s.invoice_number
             FROM shop_returned_products srp
             LEFT JOIN returns r ON r.id = srp.return_id
             LEFT JOIN sales s ON s.id = r.sale_id
             WHERE srp.variant_id = ? AND srp.shop_id = ?
             ORDER BY srp.created_at DESC, srp.id DESC",
            [$variantId, $shopId]
        );
    }

    public static function findByTag(string $tag, int $shopId): ?array {
        self::ensureTable();
        return Database::fetchOne(
            "SELECT id, variant_id, returned_tag, item_condition, printed_at
             FROM shop_returned_products
             WHERE returned_tag = ? AND shop_id = ?
             LIMIT 1",
            [$tag, $shopId]
        );
    }

    public static function getReturnedSourceTags(int $saleItemId): array {
        self::ensureTable();
        $rows = Database::fetchAll(
            "SELECT original_piece_tag
             FROM shop_returned_products
             WHERE sale_item_id = ? AND original_piece_tag IS NOT NULL AND original_piece_tag <> ''",
            [$saleItemId]
        );
        return array_values(array_filter(array_map(
            static fn(array $row): string => strtoupper(trim((string)$row['original_piece_tag'])),
            $rows
        )));
    }

    public static function tagExists(string $tag): bool {
        self::ensureTable();
        return (bool)Database::fetchOne(
            "SELECT id FROM shop_returned_products WHERE returned_tag = ? LIMIT 1",
            [$tag]
        );
    }

    public static function markPrinted(int $id, int $shopId, int $userId): bool {
        self::ensureTable();
        return Database::execute(
            "UPDATE shop_returned_products
             SET printed_at = NOW(), printed_by = ?
             WHERE id = ? AND shop_id = ? AND printed_at IS NULL",
            [$userId, $id, $shopId]
        ) > 0;
    }
}
