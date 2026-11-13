<?php
/**
 * Shop Damage Model
 * Handles damaged goods logging in retail shops (both during inward transit inspection
 * and shop bin / floor damage identification), defect categorization, photo attachments,
 * salvage/write-off/repair/return-to-warehouse lifecycle, and financial loss calculations.
 */

class ShopDamage {

    /**
     * Return only the piece tags explicitly recorded for this damage case.
     */
    public static function resolvePieceTags(array $d): array {
        $rawTags = trim((string)($d['tag_code'] ?? ''));
        if ($rawTags === '') {
            return [];
        }

        $decoded = json_decode($rawTags, true);
        $parts = is_array($decoded) ? $decoded : explode(',', $rawTags);
        $tags = [];
        foreach ($parts as $part) {
            $tag = is_array($part)
                ? trim((string)($part['tag_code'] ?? $part['qr_code'] ?? ''))
                : trim((string)$part);
            if ($tag !== '') {
                $tags[$tag] = $tag;
            }
        }

        return array_values($tags);
    }

    /**
     * Find shop damage record by ID.
     */
    public static function find(int $id): ?array {
        $record = Database::fetchOne(
            "SELECT sd.*, 
                    sh.name as shop_name, sh.code as shop_code,
                    pv.sku, pv.qr_code, pv.selling_price, pv.mrp, pv.purchase_price, pv.production_cost,
                    p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                    s.name as size_name, c.name as color_name,
                    cat.name as category_name, subcat.name as subcategory_name, subsubcat.name as sub_subcategory_name,
                    u.full_name as reported_by_name,
                    u_insp.full_name as inspected_by_name,
                    wa.allocation_number, wa.id as allocation_id, wa.status as allocation_status,
                    ao.outward_number, ao.dispatched_at as outward_dispatch_date, ao.vehicle_number, ao.driver_name
             FROM shop_damages sd
             JOIN shops sh ON sd.shop_id = sh.id
             JOIN product_variants pv ON sd.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             LEFT JOIN categories cat ON p.category_id = cat.id
             LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
             LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
             LEFT JOIN users u ON sd.reported_by = u.id
             LEFT JOIN users u_insp ON sd.inspected_by = u_insp.id
             LEFT JOIN warehouse_allocations wa ON (sd.source_type = 'INWARD_TRANSIT' AND (sd.allocation_id = wa.id OR sd.reference_id = wa.id))
             LEFT JOIN allocation_outwards ao ON ao.allocation_id = wa.id
             WHERE sd.id = ?",
            [$id]
        );

        if ($record) {
            $record['piece_tags'] = self::resolvePieceTags($record);
            $record['tag_code'] = implode(', ', $record['piece_tags']);
        }

        return $record;
    }

    /**
     * Get all shop damage records with filters.
     */
    public static function getAll(array $filters = []): array {
        $sql = "SELECT sd.*, 
                       sh.name as shop_name, sh.code as shop_code,
                       pv.sku, pv.mrp, pv.selling_price,
                       p.name as product_name, p.code as product_code, p.image_url as product_image,
                       s.name as size_name, c.name as color_name,
                       cat.name as category_name, subcat.name as subcategory_name, subsubcat.name as sub_subcategory_name,
                       u.full_name as reported_by_name,
                       wa.allocation_number, wa.id as allocation_id, wa.status as allocation_status,
                       ao.outward_number,
                       EXISTS (
                           SELECT 1 FROM stock_transactions st
                           WHERE st.reference_type = 'shop_damages' AND st.reference_id = sd.id
                             AND st.variant_id = sd.variant_id AND st.location_type = 'SHOP'
                             AND st.location_id = sd.shop_id AND st.stock_state = 'DAMAGED'
                             AND st.transaction_type IN ('DAMAGE_IN', 'DAMAGE_EDIT_QUARANTINE')
                       ) AS has_quarantine_movement
                FROM shop_damages sd
                JOIN shops sh ON sd.shop_id = sh.id
                JOIN product_variants pv ON sd.variant_id = pv.id
                JOIN products p ON pv.product_id = p.id
                LEFT JOIN sizes s ON pv.size_id = s.id
                LEFT JOIN colors c ON pv.color_id = c.id
                LEFT JOIN categories cat ON p.category_id = cat.id
                LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                LEFT JOIN users u ON sd.reported_by = u.id
                LEFT JOIN warehouse_allocations wa ON (sd.source_type = 'INWARD_TRANSIT' AND (sd.allocation_id = wa.id OR sd.reference_id = wa.id))
                LEFT JOIN allocation_outwards ao ON ao.allocation_id = wa.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['shop_id'])) {
            $sql .= " AND sd.shop_id = ?";
            $params[] = (int)$filters['shop_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND sd.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['source_type'])) {
            $sql .= " AND sd.source_type = ?";
            $params[] = $filters['source_type'];
        }

        if (!empty($filters['reference_id'])) {
            $sql .= " AND (sd.reference_id = ? OR sd.allocation_id = ?)";
            $params[] = (int)$filters['reference_id'];
            $params[] = (int)$filters['reference_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (sd.damage_number LIKE ? OR pv.sku LIKE ? OR p.name LIKE ? OR sd.damage_reason LIKE ? OR sd.tag_code LIKE ? OR wa.allocation_number LIKE ? OR ao.outward_number LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s]);
        }

        $sql .= " ORDER BY sd.id DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $records = Database::fetchAll($sql, $params);
        foreach ($records as &$r) {
            $r['piece_tags'] = self::resolvePieceTags($r);
            $r['tag_code'] = implode(', ', $r['piece_tags']);
        }
        unset($r);
        self::addWarehouseReturnFlags($records);

        return $records;
    }

    public static function getActivePieceTagsByVariant(int $shopId, array $variantIds, bool $forUpdate = false): array {
        $variantIds = array_values(array_unique(array_filter(array_map('intval', $variantIds), static fn(int $id): bool => $id > 0)));
        if ($shopId <= 0 || !$variantIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $sql = "SELECT variant_id, tag_code
                FROM shop_damages
                WHERE shop_id = ? AND variant_id IN ({$placeholders})
                  AND status NOT IN ('RESTOCKED', 'REPAIRED', 'SALVAGED', 'WRITTEN_OFF', 'CANCELLED')
                  AND tag_code IS NOT NULL AND tag_code <> ''";
        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $records = Database::fetchAll($sql, array_merge([$shopId], $variantIds));
        $tagsByVariant = [];
        foreach ($records as $record) {
            $variantId = (int)$record['variant_id'];
            foreach (self::resolvePieceTags($record) as $tag) {
                $normalizedTag = strtoupper(trim($tag));
                if ($normalizedTag !== '') {
                    $tagsByVariant[$variantId][$normalizedTag] = $normalizedTag;
                }
            }
        }

        foreach ($tagsByVariant as &$tags) {
            $tags = array_values($tags);
        }
        unset($tags);

        return $tagsByVariant;
    }

    /**
     * Mark records whose tagged damaged pieces have already left the shop.
     */
    public static function addWarehouseReturnFlags(array &$records): void {
        if (!$records) {
            return;
        }

        $shopIds = array_values(array_unique(array_filter(array_map(
            static fn(array $record): int => (int)($record['shop_id'] ?? 0),
            $records
        ))));
        $variantIds = array_values(array_unique(array_filter(array_map(
            static fn(array $record): int => (int)($record['variant_id'] ?? 0),
            $records
        ))));
        if (!$shopIds || !$variantIds) {
            return;
        }

        $shopPlaceholders = implode(',', array_fill(0, count($shopIds), '?'));
        $variantPlaceholders = implode(',', array_fill(0, count($variantIds), '?'));
        $returnRows = Database::fetchAll(
            "SELECT DISTINCT wr.source_id AS shop_id, wr.return_number,
                    wri.variant_id, wri.damaged_qty, wri.scanned_qr_codes
             FROM warehouse_returns wr
             JOIN warehouse_return_items wri ON wri.warehouse_return_id = wr.id
             JOIN stock_transactions st ON st.reference_type = 'warehouse_returns'
                AND st.reference_id = wr.id AND st.variant_id = wri.variant_id
                AND st.location_type = 'SHOP' AND st.location_id = wr.source_id
                AND st.stock_state = 'DAMAGED' AND st.transaction_type = 'TRANSFER_OUT'
             WHERE wr.source_type = 'SHOP'
               AND wr.source_id IN ($shopPlaceholders)
               AND wri.variant_id IN ($variantPlaceholders)
               AND wri.damaged_qty > 0",
            array_merge($shopIds, $variantIds)
        );

        $returnsByStock = [];
        foreach ($returnRows as $returnRow) {
            $key = (int)$returnRow['shop_id'] . ':' . (int)$returnRow['variant_id'];
            $rawTags = trim((string)($returnRow['scanned_qr_codes'] ?? ''));
            $decodedTags = json_decode($rawTags, true);
            $tagValues = is_array($decodedTags) ? $decodedTags : (preg_split('/[,|]/', $rawTags) ?: []);
            $tagSet = [];
            foreach ($tagValues as $tagValue) {
                $tag = is_array($tagValue)
                    ? (string)($tagValue['tag_code'] ?? $tagValue['qr_code'] ?? '')
                    : (string)$tagValue;
                $tag = strtoupper(trim($tag, " \t\n\r\0\x0B\"'"));
                if ($tag !== '') {
                    $tagSet[$tag] = true;
                }
            }
            $returnsByStock[$key][] = [
                'return_number' => $returnRow['return_number'],
                'quantity' => (float)$returnRow['damaged_qty'],
                'tags' => $tagSet,
            ];
        }

        foreach ($records as &$record) {
            $record['has_outward_return'] = false;
            $record['outward_return_number'] = null;
            $record['outward_return_quantity'] = 0;
            $key = (int)($record['shop_id'] ?? 0) . ':' . (int)($record['variant_id'] ?? 0);
            $damageTags = array_fill_keys(array_map('strtoupper', self::resolvePieceTags($record)), true);
            $returnedTags = [];
            $unmatchedReturnedQuantity = 0.0;
            foreach ($returnsByStock[$key] ?? [] as $return) {
                if (!$damageTags || !$return['tags']) {
                    $record['has_outward_return'] = true;
                    $unmatchedReturnedQuantity += $return['quantity'];
                } else {
                    $matchedTags = array_intersect_key($damageTags, $return['tags']);
                    if ($matchedTags) {
                        $returnedTags += $matchedTags;
                        $record['has_outward_return'] = true;
                    }
                }
                if ($record['has_outward_return'] && empty($record['outward_return_number'])) {
                    $record['outward_return_number'] = $return['return_number'];
                }
            }
            if ($record['has_outward_return']) {
                $recordQuantity = (float)($record['quantity'] ?? 0);
                $returnedQuantity = $returnedTags
                    ? (float)count($returnedTags)
                    : $unmatchedReturnedQuantity;
                $record['outward_return_quantity'] = min($recordQuantity, $returnedQuantity);
            }
        }
        unset($record);
    }

    /**
     * Mark fully returned, QR-tracked damage records as returned to the warehouse.
     */
    public static function markReturnedToWarehouse(
        int $shopId,
        int $variantId,
        int $quantity,
        array $returnedTags,
        int $returnId,
        string $returnNumber,
        ?int $userId
    ): void {
        $returnedTagSet = [];
        foreach ($returnedTags as $tag) {
            $normalizedTag = strtoupper(trim((string)$tag, " \t\n\r\0\x0B\"'"));
            if ($normalizedTag !== '') {
                $returnedTagSet[$normalizedTag] = true;
            }
        }
        if (!$returnedTagSet) {
            return;
        }

        $records = Database::fetchAll(
            "SELECT * FROM shop_damages
             WHERE shop_id = ? AND variant_id = ?
               AND status IN ('LOGGED', 'INSPECTED')
               AND COALESCE(source_type, '') <> 'INWARD_TRANSIT'
             FOR UPDATE",
            [$shopId, $variantId]
        );
        foreach ($records as $record) {
            $damageTags = array_map('strtoupper', self::resolvePieceTags($record));
            $recordQuantity = (int)$record['quantity'];
            if (!$damageTags || count($damageTags) !== $recordQuantity || $recordQuantity > $quantity) {
                continue;
            }
            if (count(array_intersect_key(array_fill_keys($damageTags, true), $returnedTagSet)) !== $recordQuantity) {
                continue;
            }

            $notes = trim((string)($record['disposition_notes'] ?? ''));
            $returnNote = "Returned to warehouse in {$returnNumber}.";
            $newNotes = $notes === '' ? $returnNote : $notes . "\n" . $returnNote;
            Database::execute(
                "UPDATE shop_damages
                 SET status = 'RETURN_TO_WH', disposition_notes = ?, updated_at = NOW()
                 WHERE id = ?",
                [$newNotes, (int)$record['id']]
            );
            AuditService::logStrict(
                'SHOP',
                'DAMAGE_RETURNED_TO_WAREHOUSE',
                'shop_damages',
                (int)$record['id'],
                ['status' => $record['status'], 'disposition_notes' => $record['disposition_notes']],
                ['status' => 'RETURN_TO_WH', 'disposition_notes' => $newNotes, 'warehouse_return_id' => $returnId],
                $userId
            );
        }
    }

    /**
     * Get all damage cases for a specific allocation.
     */
    public static function getByAllocationId(int $allocationId): array {
        return self::getAll(['reference_id' => $allocationId]);
    }

    /**
     * Generate unique damage case number for shop (e.g. SH-DMG-20261026-0001).
     */
    public static function generateDamageNumber(string $prefix = 'SH-DMG'): string {
        $today = date('Ymd');
        $fullPrefix = $prefix . '-' . $today . '-';
        $last = Database::fetchOne(
            "SELECT damage_number FROM shop_damages WHERE damage_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$fullPrefix . '%']
        );
        $seq = 1;
        if ($last && !empty($last['damage_number'])) {
            $parts = explode('-', $last['damage_number']);
            $seq = (int)end($parts) + 1;
        }

        while (true) {
            $num = sprintf('%s%04d', $fullPrefix, $seq);
            $exists = Database::fetchOne(
                "SELECT id FROM shop_damages WHERE damage_number = ? LIMIT 1 FOR UPDATE",
                [$num]
            );
            if (!$exists) {
                return $num;
            }
            $seq++;
        }
    }

    /**
     * Create shop damage entry record.
     */
    public static function create(array $data): int {
        if (empty($data['damage_number'])) {
            $data['damage_number'] = self::generateDamageNumber();
        }

        $sql = "INSERT INTO shop_damages 
             (damage_number, shop_id, variant_id, tag_code, source_type, reference_id, allocation_id, defect_type, quantity, cost_per_unit, estimated_cost, total_loss, damage_reason, defect_notes, photo_url, status, reported_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['damage_number'],
            (int)$data['shop_id'],
            (int)$data['variant_id'],
            !empty($data['tag_code']) ? trim($data['tag_code']) : null,
            $data['source_type'] ?? 'SHOP_BIN_HANDLING',
            !empty($data['reference_id']) ? (int)$data['reference_id'] : null,
            !empty($data['allocation_id']) ? (int)$data['allocation_id'] : (!empty($data['reference_id']) && ($data['source_type'] ?? '') === 'INWARD_TRANSIT' ? (int)$data['reference_id'] : null),
            $data['defect_type'] ?? 'OTHER',
            max(1, (int)($data['quantity'] ?? 1)),
            (float)($data['cost_per_unit'] ?? 0.00),
            (float)($data['estimated_cost'] ?? 0.00),
            (float)($data['total_loss'] ?? 0.00),
            $data['damage_reason'] ?? 'Damaged / Defect',
            $data['defect_notes'] ?? null,
            $data['photo_url'] ?? null,
            $data['status'] ?? 'LOGGED',
            !empty($data['reported_by']) ? (int)$data['reported_by'] : null,
        ];
        $collisionRetries = 0;
        while (true) {
            try {
                Database::execute($sql, $params);
                return (int)Database::lastInsertId();
            } catch (PDOException $e) {
                $driverMessage = (string)($e->errorInfo[2] ?? $e->getMessage());
                if (
                    (int)($e->errorInfo[1] ?? 0) !== 1062
                    || !str_contains(strtolower($driverMessage), 'damage_number')
                    || $collisionRetries >= 5
                ) {
                    throw $e;
                }

                $collisionRetries++;
                $params[0] = self::generateDamageNumber();
            }
        }
    }

    public static function getDamageNumberById(int $id): ?string {
        $record = Database::fetchOne(
            "SELECT damage_number FROM shop_damages WHERE id = ?",
            [$id]
        );
        return $record ? (string)$record['damage_number'] : null;
    }

    /**
     * Update damage record.
     */
    public static function update(int $id, array $data): bool {
        $fields = [];
        $params = [];

        $allowed = ['status', 'tag_code', 'defect_notes', 'damage_reason', 'quantity', 'cost_per_unit', 'estimated_cost', 'total_loss', 'photo_url', 'disposition_notes', 'inspected_by', 'defect_type'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE shop_damages SET " . implode(', ', $fields) . " WHERE id = ?";
        return Database::execute($sql, $params) >= 0;
    }

    /**
     * Get KPI Summary Statistics for Shop Damage Module.
     */
    public static function getSummaryStats(?int $shopId = null): array {
        $sql = "SELECT 
                COUNT(*) as total_records,
                COALESCE(SUM(quantity), 0) as total_units,
                COALESCE(SUM(total_loss), 0.00) as total_valuation_loss,
                SUM(CASE WHEN status = 'LOGGED' THEN quantity ELSE 0 END) as pending_inspection_units,
                SUM(CASE WHEN status = 'INSPECTED' THEN quantity ELSE 0 END) as inspected_units,
                SUM(CASE WHEN status = 'SALVAGED' THEN quantity ELSE 0 END) as salvaged_units,
                SUM(CASE WHEN status = 'WRITTEN_OFF' THEN quantity ELSE 0 END) as written_off_units,
                SUM(CASE WHEN status = 'REPAIRED' THEN quantity ELSE 0 END) as repaired_units,
                SUM(CASE WHEN status = 'RETURN_TO_WH' THEN quantity ELSE 0 END) as return_to_wh_units,
                SUM(CASE WHEN status = 'REWORK' THEN quantity ELSE 0 END) as rework_units,
                SUM(CASE WHEN source_type = 'INWARD_TRANSIT' THEN quantity ELSE 0 END) as inward_damage_units,
                SUM(CASE WHEN source_type = 'SHOP_BIN_HANDLING' THEN quantity ELSE 0 END) as bin_damage_units
             FROM shop_damages";
        
        $params = [];
        if ($shopId) {
            $sql .= " WHERE shop_id = ?";
            $params[] = $shopId;
        }

        $stats = Database::fetchOne($sql, $params);

        return [
            'total_records'          => (int)($stats['total_records'] ?? 0),
            'total_units'            => (int)($stats['total_units'] ?? 0),
            'total_valuation_loss'   => (float)($stats['total_valuation_loss'] ?? 0.00),
            'pending_inspection'     => (int)($stats['pending_inspection_units'] ?? 0),
            'inspected'              => (int)($stats['inspected_units'] ?? 0),
            'salvaged'               => (int)($stats['salvaged_units'] ?? 0),
            'written_off'            => (int)($stats['written_off_units'] ?? 0),
            'repaired'               => (int)($stats['repaired_units'] ?? 0),
            'return_to_wh'           => (int)($stats['return_to_wh_units'] ?? 0),
            'rework_units'           => (int)($stats['rework_units'] ?? 0),
            'inward_damage_units'    => (int)($stats['inward_damage_units'] ?? 0),
            'bin_damage_units'       => (int)($stats['bin_damage_units'] ?? 0),
        ];
    }
}
