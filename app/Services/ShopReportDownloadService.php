<?php

require_once BASE_PATH . '/app/Services/ShopService.php';
require_once BASE_PATH . '/app/Models/ShopDamage.php';
require_once BASE_PATH . '/app/Models/ShopReturnedProduct.php';
require_once BASE_PATH . '/app/Models/ShopStockAdjustment.php';

class ShopReportDownloadService {
    public static function buildRows(string $module, int $shopId, array $filters): array {
        switch ($module) {
            case 'dashboard':
                $period = trim((string)($filters['period'] ?? 'today'));
                if (!in_array($period, ['today', 'week', 'month', 'custom'], true)) {
                    $period = 'today';
                }
                if ($period === 'custom') {
                    $startDate = self::validDate($filters['start_date'] ?? null) ?? date('Y-m-d', strtotime('-30 days'));
                    $endDate = self::validDate($filters['end_date'] ?? null) ?? date('Y-m-d');
                } elseif ($period === 'week') {
                    $startDate = date('Y-m-d', strtotime('-6 days'));
                    $endDate = date('Y-m-d');
                } elseif ($period === 'month') {
                    $startDate = date('Y-m-01');
                    $endDate = date('Y-m-d');
                } else {
                    $startDate = $endDate = date('Y-m-d');
                }
                if (!empty($filters['all_records'])) {
                    $startDate = '1970-01-01';
                    $endDate = date('Y-m-d');
                }
                if ($startDate > $endDate) {
                    [$startDate, $endDate] = [$endDate, $startDate];
                }
                $summary = Database::fetchOne(
                    "SELECT COUNT(*) as invoices, COALESCE(SUM(total_amount), 0) as revenue,
                            COALESCE(SUM(CASE WHEN payment_mode = 'CASH' THEN total_amount ELSE 0 END), 0) as cash_revenue,
                            COALESCE(SUM(CASE WHEN payment_mode <> 'CASH' THEN total_amount ELSE 0 END), 0) as digital_revenue
                     FROM sales
                     WHERE shop_id = ? AND sale_type = 'SHOP' AND status <> 'CANCELLED'
                       AND DATE(created_at) BETWEEN ? AND ?",
                    [$shopId, $startDate, $endDate]
                ) ?: [];
                $summary['units_sold'] = (int)(Database::fetchOne(
                    "SELECT COALESCE(SUM(si.quantity), 0) as units_sold
                     FROM sale_items si JOIN sales s ON s.id = si.sale_id
                     WHERE s.shop_id = ? AND s.sale_type = 'SHOP' AND s.status <> 'CANCELLED'
                       AND DATE(s.created_at) BETWEEN ? AND ?",
                    [$shopId, $startDate, $endDate]
                )['units_sold'] ?? 0);
                $summary['date_from'] = $startDate;
                $summary['date_to'] = $endDate;
                return [$summary];

            case 'inventory':
                if (($filters['tab'] ?? '') === 'allocations') {
                    return self::allocationRows($shopId, $filters);
                }
                $inventoryFilters = array_intersect_key($filters, array_flip(['search', 'category_id', 'status', 'sort']));
                $inventoryRows = ShopService::getShopInventory($shopId, $inventoryFilters);
                $productType = strtoupper(trim((string)($filters['product_type'] ?? '')));
                if (in_array($productType, ['NORMAL', 'SERIES', 'COMPLEMENTARY'], true)) {
                    $inventoryRows = array_values(array_filter($inventoryRows, static function (array $row) use ($productType): bool {
                        $categoryType = strtoupper((string)($row['warehouse_category_type'] ?? 'NORMAL'));
                        $qrType = strtoupper((string)($row['variant_qr_type'] ?? ''));
                        $isComplementary = !empty($row['is_complementary']) || $categoryType === 'COMPLEMENTARY';
                        $isSeries = !$isComplementary && (
                            $categoryType === 'SERIES'
                            || in_array($qrType, ['SERIES_BATCH', 'SERIES'], true)
                            || (int)($row['pack_quantity'] ?? 1) > 1
                        );
                        $rowType = $isComplementary ? 'COMPLEMENTARY' : ($isSeries ? 'SERIES' : 'NORMAL');
                        return $rowType === $productType;
                    }));
                }
                return self::selectFields($inventoryRows, [
                    'product_name', 'sku', 'size_name', 'color_name', 'category_name', 'available_quantity',
                    'damaged_quantity', 'sold_quantity', 'returned_quantity', 'exchanged_quantity',
                    'selling_price', 'latest_allocation_number',
                ]);

            case 'adjustments':
                $rows = ShopStockAdjustment::getAll($shopId);
                if (!empty($filters['search'])) {
                    $needle = strtolower(trim((string)$filters['search']));
                    $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                        return str_contains(strtolower(implode(' ', array_map('strval', [
                            $row['adjustment_number'] ?? '', $row['sku'] ?? '', $row['product_name'] ?? '',
                            $row['reason'] ?? '', $row['status'] ?? '',
                        ]))), $needle);
                    }));
                }
                return self::selectFields($rows, [
                    'adjustment_number', 'sku', 'product_name', 'size_name', 'color_name', 'adjustment_type',
                    'quantity', 'reason', 'status', 'requested_by_name', 'approved_by_name', 'created_at',
                    'updated_at', 'notes', 'qr_codes',
                ]);

            case 'inward':
                return self::inwardRows($shopId, $filters);

            case 'sales':
                $salesFilters = array_intersect_key($filters, array_flip(['search', 'start_date', 'end_date', 'category_id']));
                $salesData = ShopService::getSoldProducts($shopId, $salesFilters);
                return self::selectFields($salesData['items'], [
                    'sale_date', 'invoice_number', 'product_name', 'sku', 'size_name', 'color_name',
                    'quantity', 'unit_price', 'discount_amount', 'total_amount', 'payment_mode',
                    'payment_status', 'customer_name', 'customer_phone', 'cashier_name', 'scanned_qr_codes',
                ]);

            case 'returns':
            case 'exchanges':
                $where = ["(s.sale_type = 'SHOP' OR (s.sale_type IS NULL AND s.shop_id = ?))", "(r.source_type = 'SHOP' OR r.source_type IS NULL)"];
                $params = [$shopId];
                if ($module === 'exchanges') {
                    $where[] = "r.return_type = 'EXCHANGE'";
                } elseif (!empty($filters['type'])) {
                    $where[] = 'r.return_type = ?';
                    $params[] = trim((string)$filters['type']);
                }
                if (!empty($filters['search'])) {
                    $where[] = '(r.return_number LIKE ? OR s.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR s.customer_name LIKE ? OR s.customer_phone LIKE ?)';
                    $like = '%' . trim((string)$filters['search']) . '%';
                    array_push($params, $like, $like, $like, $like, $like, $like);
                }
                if (!empty($filters['status'])) {
                    $where[] = 'r.status = ?';
                    $params[] = trim((string)$filters['status']);
                }
                self::appendDateFilters($where, $params, 'r.created_at', $filters, 'date_from', 'date_to');
                return Database::fetchAll(
                    "SELECT r.return_number, r.created_at, r.return_type, r.status, s.invoice_number,
                            COALESCE(c.name, s.customer_name, 'Shop Customer') as customer_name,
                            COALESCE(c.phone, s.customer_phone, '-') as customer_phone,
                            (SELECT COALESCE(SUM(quantity), 0) FROM return_items WHERE return_id = r.id) as returned_quantity,
                            r.refund_amount, r.refund_mode, r.reason, e.exchange_number, e.difference_amount,
                            r.notes
                     FROM returns r JOIN sales s ON r.sale_id = s.id
                     LEFT JOIN customers c ON s.customer_id = c.id
                     LEFT JOIN exchanges e ON e.return_id = r.id
                     WHERE " . implode(' AND ', $where) . "
                     ORDER BY r.created_at DESC",
                    $params
                );

            case 'returned-products':
                return self::selectFields(ShopReturnedProduct::getAll($shopId), [
                    'created_at', 'return_number', 'invoice_number', 'product_name', 'sku', 'size_name',
                    'color_name', 'original_piece_tag', 'returned_tag', 'item_condition', 'printed_at',
                    'printed_by_name',
                ]);

            case 'return-to-warehouse':
                $returnFilters = array_intersect_key($filters, array_flip(['search', 'status']));
                $data = ShopService::getShopWarehouseReturns($shopId, $returnFilters);
                return self::selectFields($data['returns'] ?? [], [
                    'return_number', 'created_at', 'status', 'warehouse_name', 'reason', 'item_count',
                    'total_returned_qty', 'total_good_qty', 'total_damaged_qty', 'notes',
                ]);

            case 'customers':
                $customerFilters = array_intersect_key($filters, array_flip(['q', 'status', 'tier', 'city', 'sort']));
                $customerFilters['channel'] = 'SHOP';
                return self::selectFields(ShopService::getCustomersList($customerFilters), [
                    'name', 'phone', 'email', 'city', 'is_active', 'total_orders', 'total_spend',
                    'total_items_bought', 'total_returns', 'last_visit_date', 'last_invoice_number',
                ]);

            case 'damage':
                $damageFilters = ['shop_id' => $shopId];
                foreach (['status', 'source_type', 'search'] as $key) {
                    if (!empty($filters[$key])) {
                        $damageFilters[$key] = trim((string)$filters[$key]);
                    }
                }
                return self::selectFields(ShopDamage::getAll($damageFilters), [
                    'damage_number', 'created_at', 'product_name', 'sku', 'size_name', 'color_name', 'tag_code',
                    'quantity', 'defect_type', 'damage_reason', 'status', 'source_type', 'total_loss',
                    'reported_by_name', 'allocation_number', 'outward_number',
                ]);

            case 'cash':
                return self::cashRows($shopId, $filters);
        }

        throw new InvalidArgumentException('Unsupported Shop report section.');
    }

    private static function allocationRows(int $shopId, array $filters): array {
        $where = ["((wa.destination_type = 'SHOP' AND wa.destination_id = ?) OR wa.shop_id = ?)"];
        $params = [$shopId, $shopId];
        if (!empty($filters['search'])) {
            $where[] = '(wa.allocation_number LIKE ? OR wa.notes LIKE ? OR w.name LIKE ?)';
            $like = '%' . trim((string)$filters['search']) . '%';
            array_push($params, $like, $like, $like);
        }
        return Database::fetchAll(
            "SELECT wa.allocation_number, wa.created_at, wa.status, w.name as warehouse_name,
                    (SELECT COALESCE(SUM(ai.requested_qty), 0) FROM allocation_items ai WHERE ai.allocation_id = wa.id) as requested_quantity,
                    (SELECT COALESCE(SUM(ai.received_qty), 0) FROM allocation_items ai WHERE ai.allocation_id = wa.id) as received_quantity,
                    (SELECT COALESCE(SUM(ai.damaged_qty), 0) FROM allocation_items ai WHERE ai.allocation_id = wa.id) as damaged_quantity,
                    wa.notes
             FROM warehouse_allocations wa
             LEFT JOIN warehouses w ON w.id = wa.warehouse_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY wa.id DESC",
            $params
        );
    }

    private static function inwardRows(int $shopId, array $filters): array {
        $destinationShop = "COALESCE(NULLIF(ao.shop_id, 0), CASE WHEN ao.destination_type = 'SHOP' THEN NULLIF(ao.destination_id, 0) END, NULLIF(wa.shop_id, 0), CASE WHEN wa.destination_type = 'SHOP' THEN NULLIF(wa.destination_id, 0) END)";
        $where = ["wa.status NOT IN ('REQUESTED', 'PENDING', 'CANCELLED', 'REJECTED')", "{$destinationShop} = ?"];
        $params = [$shopId];
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'wa.warehouse_id = ?';
            $params[] = (int)$filters['warehouse_id'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'RECEIVED') {
                $where[] = "wa.status IN ('RECEIVED', 'COMPLETED', 'DELIVERED')";
            } else {
                $where[] = 'wa.status = ?';
                $params[] = trim((string)$filters['status']);
            }
        }
        if (!empty($filters['search'])) {
            $where[] = "(wa.allocation_number LIKE ? OR ao.outward_number LIKE ? OR ao.vehicle_number LIKE ? OR ao.driver_name LIKE ? OR w.name LIKE ? OR EXISTS (SELECT 1 FROM allocation_items sai JOIN product_variants spv ON spv.id = sai.variant_id JOIN products sp ON sp.id = spv.product_id WHERE sai.allocation_id = wa.id AND (spv.sku LIKE ? OR sp.name LIKE ?)))";
            $like = '%' . trim((string)$filters['search']) . '%';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        self::appendDateFilters($where, $params, 'wa.created_at', $filters, 'date_from', 'date_to');
        return Database::fetchAll(
            "SELECT wa.allocation_number, wa.created_at, wa.status, w.name as warehouse_name,
                    ao.outward_number, ao.vehicle_number, ao.driver_name,
                    p.name as product_name, pv.sku, ai.requested_qty, ai.dispatched_qty,
                    ai.received_qty, ai.damaged_qty, ai.missing_qty, ai.received_qr_codes
             FROM warehouse_allocations wa
             JOIN warehouses w ON w.id = wa.warehouse_id
             LEFT JOIN allocation_outwards ao ON ao.allocation_id = wa.id
             JOIN allocation_items ai ON ai.allocation_id = wa.id
             JOIN product_variants pv ON pv.id = ai.variant_id
             JOIN products p ON p.id = pv.product_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY wa.id DESC, ai.id ASC",
            $params
        );
    }

    private static function cashRows(int $shopId, array $filters): array {
        $tab = trim((string)($filters['tab'] ?? 'overview'));
        if (in_array($tab, ['overview', 'ledger'], true)) {
            require_once BASE_PATH . '/app/Services/CashService.php';
            $date = trim((string)($filters['filter_date'] ?? ''));
            $allTime = !empty($filters['all_time']) || !empty($filters['all_records']);
            $ledgerFilters = [
                'type' => trim((string)($filters['type'] ?? '')),
                'flow' => trim((string)($filters['flow'] ?? '')),
                'payment_mode' => trim((string)($filters['payment_mode'] ?? '')),
                'start_date' => $allTime ? null : ($date ?: ($filters['start_date'] ?? date('Y-m-d'))),
                'end_date' => $allTime ? null : ($date ?: ($filters['end_date'] ?? date('Y-m-d'))),
                'limit' => 100000,
            ];
            $rows = self::selectFields(CashService::getTransactionsLedger($shopId, $ledgerFilters), [
                'created_at', 'transaction_number', 'transaction_type', 'category', 'flow_direction',
                'payment_mode', 'amount', 'register_number', 'description', 'reference_id', 'staff_name',
            ]);
            return self::filterRowsBySearch($rows, $filters['q'] ?? '');
        }

        if ($tab === 'expenses') {
            $where = ['ce.shop_id = ?'];
            $params = [$shopId];
            self::appendDateFilters($where, $params, 'ce.created_at', $filters, 'start_date', 'end_date');
            $rows = Database::fetchAll(
                "SELECT ce.voucher_number, ce.created_at, ce.expense_category, ce.amount,
                        ce.payment_mode, ce.paid_to, ce.bill_number, ce.notes, u.full_name as staff_name
                 FROM cash_expenses ce LEFT JOIN users u ON u.id = ce.created_by
                 WHERE " . implode(' AND ', $where) . " ORDER BY ce.id DESC",
                $params
            );
            return self::filterRowsBySearch($rows, $filters['q'] ?? '');
        }
        if ($tab === 'deposits') {
            $where = ['bd.shop_id = ?'];
            $params = [$shopId];
            self::appendDateFilters($where, $params, 'bd.deposit_date', $filters, 'start_date', 'end_date');
            $rows = Database::fetchAll(
                "SELECT bd.deposit_number, bd.deposit_date, bd.bank_name, bd.account_number, bd.amount,
                        bd.slip_number, bd.status, bd.notes, u.full_name as staff_name
                 FROM bank_deposits bd LEFT JOIN users u ON u.id = bd.deposited_by
                 WHERE " . implode(' AND ', $where) . " ORDER BY bd.id DESC",
                $params
            );
            return self::filterRowsBySearch($rows, $filters['q'] ?? '');
        }
        $where = ['cr.shop_id = ?'];
        $params = [$shopId];
        self::appendDateFilters($where, $params, 'cr.opening_time', $filters, 'start_date', 'end_date');
        $rows = Database::fetchAll(
            "SELECT cr.register_number, COALESCE(cr.cashier_name, opener.full_name) as cashier_name,
                    cr.opening_time, cr.closing_time, cr.opening_balance, cr.cash_sales_amount,
                    cr.cash_out_amount, cr.expected_cash, cr.closing_cash_counted, cr.cash_difference,
                    cr.status, cr.notes,
                    closed_by.full_name as closed_by_name
             FROM cash_registers cr
             LEFT JOIN users opener ON opener.id = cr.cashier_id
             LEFT JOIN users closed_by ON closed_by.id = cr.closed_by
             WHERE " . implode(' AND ', $where) . " ORDER BY cr.id DESC",
            $params
        );
        return self::filterRowsBySearch($rows, $filters['q'] ?? '');
    }

    private static function filterRowsBySearch(array $rows, $search): array {
        $needle = strtolower(trim((string)$search));
        if ($needle === '') {
            return $rows;
        }
        return array_values(array_filter($rows, static function (array $row) use ($needle): bool {
            return str_contains(strtolower(implode(' ', array_map('strval', $row))), $needle);
        }));
    }

    private static function appendDateFilters(array &$where, array &$params, string $column, array $filters, string $fromKey, string $toKey): void {
        $from = self::validDate($filters[$fromKey] ?? null);
        $to = self::validDate($filters[$toKey] ?? null);
        if ($from !== null) {
            $where[] = "DATE({$column}) >= ?";
            $params[] = $from;
        }
        if ($to !== null) {
            $where[] = "DATE({$column}) <= ?";
            $params[] = $to;
        }
    }

    private static function validDate($value): ?string {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $date = DateTime::createFromFormat('!Y-m-d', trim($value));
        return $date && $date->format('Y-m-d') === trim($value) ? trim($value) : null;
    }

    private static function selectFields(array $rows, array $fields): array {
        return array_map(static function (array $row) use ($fields): array {
            $selected = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, $row)) {
                    $selected[$field] = $row[$field];
                }
            }
            return $selected ?: ['record' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }, $rows);
    }
}
