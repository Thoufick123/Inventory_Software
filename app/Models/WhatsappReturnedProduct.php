<?php

class WhatsappReturnedProduct {
    private static bool $tableReady = false;

    private static function ensureTable(): void {
        if (self::$tableReady) {
            return;
        }

        Database::query(
            "CREATE TABLE IF NOT EXISTS whatsapp_returned_products (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                channel_id int(10) unsigned NOT NULL,
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
                UNIQUE KEY uq_whatsapp_returned_tag (returned_tag),
                KEY idx_whatsapp_return_channel (channel_id),
                KEY idx_whatsapp_return_id (return_id),
                KEY idx_whatsapp_return_sale_item (sale_item_id),
                KEY idx_whatsapp_return_variant (variant_id),
                KEY idx_whatsapp_return_printed (printed_at)
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
            "INSERT INTO whatsapp_returned_products
                (channel_id, return_id, sale_item_id, variant_id, original_piece_tag, returned_tag, item_condition, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                (int)$data['channel_id'],
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

    public static function getAll(array $channelIds): array {
        self::ensureTable();
        if (!$channelIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
        return Database::fetchAll(
            "SELECT wrp.*, pv.sku, pv.qr_code, p.name as product_name, p.code as product_code,
                    sz.name as size_name, clr.name as color_name,
                    r.return_number, s.invoice_number,
                    u.full_name as printed_by_name
             FROM whatsapp_returned_products wrp
             JOIN product_variants pv ON pv.id = wrp.variant_id
             JOIN products p ON p.id = pv.product_id
             LEFT JOIN sizes sz ON sz.id = pv.size_id
             LEFT JOIN colors clr ON clr.id = pv.color_id
             LEFT JOIN returns r ON r.id = wrp.return_id
             LEFT JOIN sales s ON s.id = r.sale_id
             LEFT JOIN users u ON u.id = wrp.printed_by
             WHERE wrp.channel_id IN ($placeholders)
             ORDER BY wrp.created_at DESC, wrp.id DESC",
            array_map('intval', $channelIds)
        );
    }

    public static function find(int $id, array $channelIds, bool $forUpdate = false): ?array {
        self::ensureTable();
        if (!$channelIds) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
        return Database::fetchOne(
            "SELECT wrp.*, pv.sku, pv.qr_code, pv.mrp, pv.selling_price,
                    p.name as product_name, p.code as product_code,
                    sz.name as size_name, clr.name as color_name,
                    r.return_number, s.invoice_number
             FROM whatsapp_returned_products wrp
             JOIN product_variants pv ON pv.id = wrp.variant_id
             JOIN products p ON p.id = pv.product_id
             LEFT JOIN sizes sz ON sz.id = pv.size_id
             LEFT JOIN colors clr ON clr.id = pv.color_id
             LEFT JOIN returns r ON r.id = wrp.return_id
             LEFT JOIN sales s ON s.id = r.sale_id
             WHERE wrp.id = ? AND wrp.channel_id IN ($placeholders)
             LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
            array_merge([$id], array_map('intval', $channelIds))
        );
    }

    public static function getReturnedSourceTags(int $saleItemId): array {
        self::ensureTable();
        $rows = Database::fetchAll(
            "SELECT original_piece_tag
             FROM whatsapp_returned_products
             WHERE sale_item_id = ? AND original_piece_tag IS NOT NULL AND original_piece_tag <> ''",
            [$saleItemId]
        );
        return array_values(array_filter(array_map(
            static fn(array $row): string => strtoupper(trim((string)$row['original_piece_tag'])),
            $rows
        )));
    }

    public static function getForVariant(int $variantId, array $channelIds): array {
        self::ensureTable();
        if (!$channelIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
        return Database::fetchAll(
            "SELECT wrp.*, r.return_number, s.invoice_number
             FROM whatsapp_returned_products wrp
             LEFT JOIN returns r ON r.id = wrp.return_id
             LEFT JOIN sales s ON s.id = r.sale_id
             WHERE wrp.variant_id = ? AND wrp.channel_id IN ($placeholders)
             ORDER BY wrp.created_at DESC, wrp.id DESC",
            array_merge([$variantId], array_map('intval', $channelIds))
        );
    }

    public static function tagExists(string $tag): bool {
        self::ensureTable();
        return (bool)Database::fetchOne(
            "SELECT id FROM whatsapp_returned_products WHERE returned_tag = ? LIMIT 1",
            [$tag]
        );
    }

    public static function markPrinted(int $id, array $channelIds, int $userId): bool {
        self::ensureTable();
        if (!$channelIds) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
        return Database::execute(
            "UPDATE whatsapp_returned_products
             SET printed_at = NOW(), printed_by = ?
             WHERE id = ? AND channel_id IN ($placeholders) AND printed_at IS NULL",
            array_merge([$userId, $id], array_map('intval', $channelIds))
        ) > 0;
    }
}
