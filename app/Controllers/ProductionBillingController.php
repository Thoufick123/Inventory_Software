<?php

class ProductionBillingController
{
    public function billList(): void
    {
        $bills = Database::fetchAll(
            "SELECT pb.*,
                    (SELECT COUNT(*) FROM production_bill_items pbi WHERE pbi.bill_id = pb.id) AS line_count
             FROM production_bills pb
             ORDER BY pb.bill_date DESC, pb.id DESC"
        );

        $stats = [
            'total_revenue'    => 0.0,
            'paid_revenue'     => 0.0,
            'pending_revenue'  => 0.0,
            'total_bills'      => count($bills),
            'unique_customers' => 0,
            'paid_count'       => 0,
            'pending_count'    => 0,
        ];

        $customerSet = [];
        foreach ($bills as $b) {
            $amount = (float)($b['total_amount'] ?? 0);
            $stats['total_revenue'] += $amount;
            if (($b['payment_status'] ?? '') === 'PAID') {
                $stats['paid_revenue'] += $amount;
                $stats['paid_count']++;
            } else {
                $stats['pending_revenue'] += $amount;
                $stats['pending_count']++;
            }
            $buyer = trim(($b['buyer_type'] ?? '') . '_' . (($b['buyer_type'] ?? '') === 'CUSTOMER' ? ($b['customer_name'] ?? '') : ($b['company_name'] ?? '')));
            if ($buyer !== '_' && $buyer !== '') {
                $customerSet[$buyer] = true;
            }
        }
        $stats['unique_customers'] = count($customerSet);

        Response::view('production.billing.index', [
            'pageTitle' => 'Production Billing',
            'activeModule' => 'production',
            'activeMenu' => 'prod-billing',
            'bills' => $bills,
            'stats' => $stats,
        ]);
    }

    public function createBill(): void
    {
        $this->renderForm(null);
    }

    public function editBill(string $id): void
    {
        $bill = $this->findBill((int)$id);
        if (!$bill) {
            Response::redirect('production/billing', 'error', 'Bill not found.');
            return;
        }

        $this->renderForm($bill);
    }

    public function viewBill(string $id): void
    {
        $bill = $this->loadBill((int)$id);
        if (!$bill) {
            Response::redirect('production/billing', 'error', 'Bill not found.');
            return;
        }

        Response::view('production.billing.view', [
            'pageTitle' => 'Production Bill ' . $bill['bill_number'],
            'activeModule' => 'production',
            'activeMenu' => 'prod-billing',
            'bill' => $bill,
            'billItems' => $this->loadBillItems((int)$bill['id']),
            'prodUnit' => ProductionUnit::getActiveFacility($bill['production_unit_id'] ?? null),
        ]);
    }

    public function printBill(string $id): void
    {
        $bill = $this->loadBill((int)$id);
        if (!$bill) {
            Response::redirect('production/billing', 'error', 'Bill not found.');
            return;
        }

        Response::viewOnly('production.billing.print', [
            'bill' => $bill,
            'billItems' => $this->loadBillItems((int)$bill['id']),
            'sellerCompany' => SettingsService::getGroup('company'),
            'prodUnit' => ProductionUnit::getActiveFacility($bill['production_unit_id'] ?? null),
        ]);
    }

    public function searchInventory(): void
    {
        if (!hasPermission('production_billing', 'create') && !hasPermission('production_billing', 'edit')) {
            Response::error(403, 'You do not have permission to search Production Billing inventory.');
            return;
        }

        $query = trim((string)($_GET['q'] ?? ''));
        if ($query === '') {
            Response::jsonSuccess('Inventory items loaded.', ['items' => []]);
            return;
        }

        $billId = max(0, (int)($_GET['bill_id'] ?? 0));
        $needle = strtoupper($query);

        // Check if scanned tag / QR matches a quarantined item in Damage Management
        $damagedMatch = Database::fetchOne(
            "SELECT rd.damage_number, rd.damage_reason, rd.qr_code, rd.damaged_weight, rd.status,
                    sr.roll_number
             FROM roll_damages rd
             LEFT JOIN seller_rolls sr ON sr.id = rd.seller_roll_id
             WHERE (UPPER(TRIM(rd.qr_code)) = ? OR UPPER(TRIM(sr.roll_number)) = ? OR UPPER(TRIM(sr.qr_code)) = ?)
               AND rd.status NOT IN ('CANCELLED', 'REJECTED')",
            [$needle, $needle, $needle]
        );

        if ($damagedMatch) {
            $dmgQr = !empty($damagedMatch['qr_code']) ? $damagedMatch['qr_code'] : $damagedMatch['roll_number'];
            Response::jsonSuccess('Damaged item quarantined.', [
                'items'         => [],
                'is_damaged'    => true,
                'damage_number' => $damagedMatch['damage_number'],
                'damage_reason' => $damagedMatch['damage_reason'],
                'qr_code'       => $dmgQr,
                'message'       => "Cannot bill QR item '{$dmgQr}'! This item is quarantined in the Damage Management module (Damage #{$damagedMatch['damage_number']} — {$damagedMatch['damage_reason']}). Damaged items cannot be sold or billed!"
            ]);
            return;
        }

        // Check if scanned tag matches Warehouse Damage Management
        $whDamageMatch = Database::fetchOne(
            "SELECT wd.damage_number, wd.damage_reason, wd.tag_code, w.name as warehouse_name
             FROM warehouse_damages wd
             LEFT JOIN warehouses w ON w.id = wd.warehouse_id
             WHERE UPPER(TRIM(wd.tag_code)) = ?
               AND wd.status NOT IN ('SALVAGED', 'REPAIRED')",
            [$needle]
        );
        if ($whDamageMatch) {
            Response::jsonSuccess('Damaged item in warehouse.', [
                'items'         => [],
                'is_damaged'    => true,
                'damage_number' => $whDamageMatch['damage_number'],
                'damage_reason' => $whDamageMatch['damage_reason'],
                'qr_code'       => $needle,
                'message'       => "Cannot bill QR item '{$needle}'! This item is quarantined in the Warehouse Damage module (Damage #{$whDamageMatch['damage_number']} — {$whDamageMatch['damage_reason']}). Damaged items cannot be sold or billed!"
            ]);
            return;
        }

        // Check if scanned QR / roll has already been outwarded to warehouse
        $outwardedRoll = null;
        if (preg_match('/^([A-Z]{2}\d{4})(\d+)$/', $needle, $m)) {
            $pfx = $m[1];
            $seq = (int)$m[2];
            $outwardedRoll = Database::fetchOne(
                "SELECT po.outward_number, po.dispatch_date, pb.batch_number, w.name as warehouse_name, sr.roll_number
                 FROM seller_rolls sr
                 JOIN production_program_rolls ppr ON ppr.seller_roll_id = sr.id
                 JOIN production_programs pp ON pp.id = ppr.production_program_id
                 JOIN production_batches pb ON pb.program_id = pp.id
                 JOIN production_outwards po ON po.production_batch_id = pb.id
                 LEFT JOIN warehouses w ON (w.id = po.warehouse_id OR w.id = pb.destination_warehouse_id)
                 WHERE sr.start_code LIKE ? AND sr.end_code LIKE ?
                   AND ? >= sr.start_seq AND ? <= sr.end_seq
                   AND (po.status IN ('DISPATCHED', 'RECEIVED') OR pb.status IN ('DISPATCHED', 'RECEIVED'))
                 LIMIT 1",
                [$pfx . '%', $pfx . '%', $seq, $seq]
            );
        }
        if (!$outwardedRoll) {
            $outwardedRoll = Database::fetchOne(
                "SELECT po.outward_number, po.dispatch_date, pb.batch_number, w.name as warehouse_name, sr.roll_number
                 FROM seller_rolls sr
                 JOIN production_program_rolls ppr ON ppr.seller_roll_id = sr.id
                 JOIN production_programs pp ON pp.id = ppr.production_program_id
                 JOIN production_batches pb ON pb.program_id = pp.id
                 JOIN production_outwards po ON po.production_batch_id = pb.id
                 LEFT JOIN warehouses w ON (w.id = po.warehouse_id OR w.id = pb.destination_warehouse_id)
                 WHERE (UPPER(TRIM(sr.roll_number)) = ? OR UPPER(TRIM(sr.start_code)) = ? OR UPPER(TRIM(sr.qr_code)) = ?)
                   AND (po.status IN ('DISPATCHED', 'RECEIVED') OR pb.status IN ('DISPATCHED', 'RECEIVED'))
                 LIMIT 1",
                [$needle, $needle, $needle]
            );
        }

        $outwardedBatchTag = Database::fetchOne(
            "SELECT po.outward_number, po.dispatch_date, pb.batch_number, w.name as warehouse_name
             FROM production_batch_items pbi
             JOIN production_batches pb ON pb.id = pbi.production_batch_id
             JOIN production_outwards po ON po.production_batch_id = pb.id
             LEFT JOIN warehouses w ON (w.id = po.warehouse_id OR w.id = pb.destination_warehouse_id)
             WHERE (pbi.piece_tags LIKE ? OR pbi.received_qr_codes LIKE ? OR UPPER(TRIM(pb.batch_number)) = ?)
               AND (po.status IN ('DISPATCHED', 'RECEIVED') OR pb.status IN ('DISPATCHED', 'RECEIVED'))
             LIMIT 1",
            ['%' . $needle . '%', '%' . $needle . '%', $needle]
        );

        $warehouseQr = Database::fetchOne(
            "SELECT wq.qr_code, w.name as warehouse_name
             FROM warehouse_qr_codes wq
             LEFT JOIN warehouses w ON w.id = wq.warehouse_id
             WHERE UPPER(TRIM(wq.qr_code)) = ?
             LIMIT 1",
            [$needle]
        );

        $outwardMatch = $outwardedRoll ?: ($outwardedBatchTag ?: null);
        if ($outwardMatch || $warehouseQr) {
            $outNum = $outwardMatch['outward_number'] ?? 'OUTWARD';
            $whName = $outwardMatch['warehouse_name'] ?? ($warehouseQr['warehouse_name'] ?? 'Warehouse');
            Response::jsonSuccess('Product already outwarded.', [
                'items'          => [],
                'is_outwarded'   => true,
                'outward_number' => $outNum,
                'warehouse_name' => $whName,
                'qr_code'        => $needle,
                'message'        => "Cannot bill QR item '{$needle}'! This product/roll has already been outwarded to the warehouse ({$whName} under Outward #{$outNum}). Outwarded products cannot be billed in production!"
            ]);
            return;
        }

        $items = $this->getAvailableInventory($billId);
        $matches = [];

        foreach ($items as $item) {
            $matchingTags = array_values(array_filter(
                $item['available_tags'],
                static fn(string $tag): bool => str_contains(strtoupper($tag), $needle)
            ));
            $metadataMatch = stripos($item['product_name'], $query) !== false
                || stripos((string)$item['sku'], $query) !== false
                || stripos((string)$item['size_name'], $query) !== false
                || stripos((string)$item['color_name'], $query) !== false
                || stripos((string)$item['program_number'], $query) !== false;

            if ($metadataMatch || $matchingTags) {
                $item['available_tags'] = $matchingTags ?: $item['available_tags'];
                $matches[] = $item;
                if (count($matches) >= 20) {
                    break;
                }
            }
        }

        Response::jsonSuccess('Inventory items loaded.', ['items' => $matches]);
    }

    public function storeBill(): void
    {
        $this->persistBill(null);
    }

    public function updateBill(string $id): void
    {
        $billId = (int)$id;
        if (!$this->findBill($billId)) {
            Response::redirect('production/billing', 'error', 'Bill not found.');
            return;
        }
        $this->persistBill($billId);
    }

    public function deleteBill(string $id): void
    {
        $billId = (int)$id;
        try {
            Database::execute("DELETE FROM production_bills WHERE id = ?", [$billId]);
            Response::redirect('production/billing', 'success', 'Production bill deleted.');
        } catch (Throwable $e) {
            error_log('ProductionBillingController::deleteBill failed: ' . $e->getMessage());
            Response::redirect('production/billing', 'error', 'Unable to delete this bill.');
        }
    }

    private function renderForm(?array $bill): void
    {
        $billItems = $bill ? $this->loadBillItems((int)$bill['id']) : [];
        $productionUnits = function_exists('getScopedProductionUnits')
            ? getScopedProductionUnits()
            : ProductionUnit::getAll(['is_active' => 1]);
        $savedUnit = !empty($bill['production_unit_id']) ? ProductionUnit::find((int)$bill['production_unit_id']) : null;
        $prodUnit = $savedUnit ?: ProductionUnit::getActiveFacility();
        if ($savedUnit && !array_filter($productionUnits, static fn(array $unit): bool => (int)$unit['id'] === (int)$savedUnit['id'])) {
            $productionUnits[] = $savedUnit;
        }
        Response::view('production.billing.form', [
            'pageTitle' => $bill ? 'Edit Production Bill' : 'Create Production Bill',
            'activeModule' => 'production',
            'activeMenu' => 'prod-billing',
            'bill' => $bill,
            'billItems' => $billItems,
            'prodUnit' => $prodUnit,
            'productionUnits' => $productionUnits,
        ]);
    }

    private function findBill(int $id): ?array
    {
        return $id > 0
            ? Database::fetchOne("SELECT * FROM production_bills WHERE id = ?", [$id])
            : null;
    }

    private function loadBill(int $id): ?array
    {
        return $id > 0
            ? Database::fetchOne("SELECT * FROM production_bills WHERE id = ?", [$id])
            : null;
    }

    private function loadBillItems(int $billId): array
    {
        $items = Database::fetchAll(
            "SELECT pbi.*
             FROM production_bill_items pbi
             WHERE pbi.bill_id = ?
             ORDER BY pbi.id",
            [$billId]
        );

        $tagsByItem = [];
        if ($items) {
            $tagRows = Database::fetchAll(
                "SELECT bill_item_id, tag_code
                 FROM production_bill_item_tags
                 WHERE bill_id = ?
                 ORDER BY id",
                [$billId]
            );
            foreach ($tagRows as $tagRow) {
                $tagsByItem[(int)$tagRow['bill_item_id']][] = (string)$tagRow['tag_code'];
            }
        }
        foreach ($items as &$item) {
            $item['tags'] = $tagsByItem[(int)$item['id']] ?? [];
        }
        unset($item);
        return $items;
    }

    private function getAvailableInventory(int $excludeBillId = 0): array
    {
        $rows = Database::fetchAll(
            "SELECT qi.id AS qc_item_id, qi.qc_batch_id, qi.variant_id, qi.size_name, qi.color_name,
                    qi.base_price AS qc_base_price, qi.discount_price AS qc_discount_price,
                    pp.program_number, pp.name AS program_name,
                    COALESCE(p.name, pp.name, 'Finished Product') AS product_name,
                    p.code AS product_code, p.gst_percentage, p.hsn_code,
                    pv.sku, pv.mrp AS variant_mrp, pv.selling_price,
                    c.name AS color_from_variant, sz.name AS size_from_variant
             FROM qc_items qi
             JOIN qc_batches qb ON qb.id = qi.qc_batch_id
             JOIN production_programs pp ON pp.id = qb.program_id
             LEFT JOIN products p ON p.id = pp.product_id
             LEFT JOIN product_variants pv ON pv.id = qi.variant_id
             LEFT JOIN colors c ON c.id = pv.color_id
             LEFT JOIN sizes sz ON sz.id = pv.size_id
             WHERE qi.passed_qty > 0
             ORDER BY qb.created_at DESC, qi.id DESC"
        );

        $batchIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int)$row['qc_batch_id'],
            $rows
        )));
        $tagsByItem = [];
        foreach ($batchIds as $batchId) {
            $tagsByItem += ProductionService::getAvailableQCPieceTags($batchId);
        }

        $soldRows = $excludeBillId > 0
            ? Database::fetchAll("SELECT tag_code FROM production_bill_item_tags WHERE bill_id <> ?", [$excludeBillId])
            : Database::fetchAll("SELECT tag_code FROM production_bill_item_tags");
        $soldTags = array_fill_keys(array_map(
            static fn(array $row): string => strtoupper(trim((string)$row['tag_code'])),
            $soldRows
        ), true);

        $damagedRows = Database::fetchAll(
            "SELECT DISTINCT qr_code FROM roll_damages 
             WHERE qr_code IS NOT NULL AND TRIM(qr_code) <> '' 
               AND status NOT IN ('CANCELLED', 'REJECTED')"
        );
        $damagedTags = array_fill_keys(array_map(
            static fn(array $row): string => strtoupper(trim((string)$row['qr_code'])),
            $damagedRows
        ), true);

        // Get all piece tags that were already packed into dispatched batches
        $outwardedTagRows = Database::fetchAll(
            "SELECT pbi.piece_tags
             FROM production_batch_items pbi
             JOIN production_batches pb ON pb.id = pbi.production_batch_id
             JOIN production_outwards po ON po.production_batch_id = pb.id
             WHERE po.status IN ('DISPATCHED', 'RECEIVED') OR pb.status IN ('DISPATCHED', 'RECEIVED')"
        );
        $outwardedTags = [];
        foreach ($outwardedTagRows as $otRow) {
            if (!empty($otRow['piece_tags'])) {
                $decoded = json_decode($otRow['piece_tags'], true);
                $tagList = is_array($decoded) ? $decoded : explode(',', $otRow['piece_tags']);
                foreach ($tagList as $t) {
                    $tClean = strtoupper(trim((string)$t));
                    if ($tClean !== '') $outwardedTags[$tClean] = true;
                }
            }
        }
        $whQrRows = Database::fetchAll("SELECT qr_code FROM warehouse_qr_codes");
        foreach ($whQrRows as $wqRow) {
            $t = strtoupper(trim((string)$wqRow['qr_code']));
            if ($t !== '') $outwardedTags[$t] = true;
        }

        $available = [];
        foreach ($rows as $row) {
            $qcItemId = (int)$row['qc_item_id'];
            $tags = array_values(array_filter(
                $tagsByItem[$qcItemId] ?? [],
                static fn(string $tag): bool => !isset($soldTags[strtoupper(trim($tag))])
                    && !isset($damagedTags[strtoupper(trim($tag))])
                    && !isset($outwardedTags[strtoupper(trim($tag))])
            ));
            if (!$tags) {
                continue;
            }

            $mrp = (float)($row['variant_mrp'] ?? 0);
            if ($mrp <= 0) {
                $mrp = (float)($row['qc_base_price'] ?? 0);
            }
            $defaultPrice = (float)($row['selling_price'] ?? 0);
            if ($defaultPrice <= 0) {
                $defaultPrice = (float)($row['qc_discount_price'] ?? 0);
            }
            if ($defaultPrice <= 0) {
                $defaultPrice = $mrp;
            }

            $available[] = [
                'qc_item_id' => $qcItemId,
                'qc_batch_id' => (int)$row['qc_batch_id'],
                'variant_id' => (int)($row['variant_id'] ?? 0),
                'product_name' => (string)$row['product_name'],
                'program_number' => (string)($row['program_number'] ?? ''),
                'sku' => (string)(!empty($row['sku']) ? $row['sku'] : ($row['product_code'] ?? '')),
                'size_name' => (string)(!empty($row['size_name']) ? $row['size_name'] : ($row['size_from_variant'] ?? '')),
                'color_name' => (string)(!empty($row['color_name']) ? $row['color_name'] : ($row['color_from_variant'] ?? '')),
                'mrp' => $mrp,
                'unit_price' => $defaultPrice,
                'gst_percentage' => max(0, (float)($row['gst_percentage'] ?? 0)),
                'hsn_code' => (string)($row['hsn_code'] ?? '6109'),
                'available_tags' => $tags,
            ];
        }

        return $available;
    }

    private function persistBill(?int $billId): void
    {
        $isNewBill = $billId === null;
        $companyName = trim((string)($_POST['company_name'] ?? ''));
        $companyAddress = trim((string)($_POST['company_address'] ?? ''));
        $companyPhone = trim((string)($_POST['company_phone'] ?? ''));
        $companyEmail = trim((string)($_POST['company_email'] ?? ''));
        $companyGstin = trim((string)($_POST['company_gstin'] ?? ''));
        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        $customerPhone = preg_replace('/\s+/', '', (string)($_POST['customer_phone'] ?? ''));
        $customerAddress = trim((string)($_POST['customer_address'] ?? ''));
        $customerCity = trim((string)($_POST['customer_city'] ?? ''));
        $customerState = trim((string)($_POST['customer_state'] ?? ''));
        $customerGstin = trim((string)($_POST['customer_gstin'] ?? ''));
        $buyerType = strtoupper(trim((string)($_POST['buyer_type'] ?? 'COMPANY')));
        $billDate = trim((string)($_POST['bill_date'] ?? date('Y-m-d')));
        $paymentMethod = strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH')));
        $productionUnitId = (int)($_POST['production_unit_id'] ?? 0);
        $submittedItems = $_POST['items'] ?? [];

        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;
        if ($scopedUnitId !== null) {
            $productionUnitId = $scopedUnitId;
        }
        $productionUnit = $productionUnitId > 0
            ? Database::fetchOne("SELECT id, is_active FROM production_units WHERE id = ?", [$productionUnitId])
            : null;
        if (!$productionUnit || (int)$productionUnit['is_active'] !== 1) {
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'Select an active production unit for this bill.');
            return;
        }

        if (!in_array($buyerType, ['COMPANY', 'CUSTOMER'], true)) {
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'Select whether this bill is for a company or customer.');
            return;
        }
        if (($buyerType === 'COMPANY' && $companyName === '') || ($buyerType === 'CUSTOMER' && ($customerName === '' || $customerPhone === ''))) {
            $requiredMessage = $buyerType === 'COMPANY'
                ? 'Company name is required.'
                : 'Customer name and phone are required.';
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', $requiredMessage);
            return;
        }
        $validPhone = static fn(string $phone): bool => $phone === '' || (bool)preg_match('/^\+?[0-9][0-9 ()-]{6,19}$/', $phone);
        $validGstin = static fn(string $gstin): bool => $gstin === '' || (bool)preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', strtoupper($gstin));
        if (
            ($buyerType === 'COMPANY' && (
                strlen($companyName) > 200 ||
                !$validPhone($companyPhone) || strlen($companyPhone) > 30 ||
                ($companyEmail !== '' && (!filter_var($companyEmail, FILTER_VALIDATE_EMAIL) || strlen($companyEmail) > 150)) ||
                !$validGstin($companyGstin) || strlen($companyGstin) > 30
            )) ||
            ($buyerType === 'CUSTOMER' && (
                strlen($customerName) > 150 || !$validPhone($customerPhone) || strlen($customerPhone) > 20 ||
                !$validGstin($customerGstin) || strlen($customerGstin) > 30 ||
                strlen($customerCity) > 100 || strlen($customerState) > 100
            ))
        ) {
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'Check the company or customer contact details, phone, email, and GSTIN.');
            return;
        }
        $companyGstin = strtoupper($companyGstin);
        $customerGstin = strtoupper($customerGstin);
        if ($buyerType === 'COMPANY') {
            $customerName = null;
            $customerPhone = null;
            $customerAddress = null;
            $customerCity = null;
            $customerState = null;
            $customerGstin = null;
        } else {
            $companyName = null;
            $companyAddress = null;
            $companyPhone = null;
            $companyEmail = null;
            $companyGstin = null;
        }
        $dateParts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $billDate, $dateMatch) ? $dateMatch : null;
        if (!$dateParts || !checkdate((int)$dateParts[2], (int)$dateParts[3], (int)$dateParts[1])) {
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'The bill date is invalid.');
            return;
        }
        if (!in_array($paymentMethod, ['CASH', 'UPI', 'CASH_UPI', 'CARD', 'NET_BANKING', 'CREDIT'], true)) {
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'Select a valid payment method.');
            return;
        }
        $upiReference = trim((string)($_POST['upi_reference'] ?? ''));
        if (in_array($paymentMethod, ['UPI', 'CASH_UPI'], true) && $upiReference === '') {
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'Enter the UPI reference for this payment.');
            return;
        }
        if (strlen($upiReference) > 150) {
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'UPI reference must be 150 characters or fewer.');
            return;
        }

        $inventory = [];
        foreach ($this->getAvailableInventory($billId ?? 0) as $entry) {
            $inventory[(int)$entry['qc_item_id']] = $entry;
        }

        $itemPayload = [];
        $usedTags = [];
        foreach (is_array($submittedItems) ? $submittedItems : [] as $submitted) {
            if (!is_array($submitted)) {
                continue;
            }
            $qcItemId = (int)($submitted['qc_item_id'] ?? 0);
            $tags = array_values(array_unique(array_filter(array_map(
                static fn($tag): string => trim((string)$tag),
                (array)($submitted['tags'] ?? [])
            ))));
            if (!$tags) {
                continue;
            }
            if (!isset($inventory[$qcItemId])) {
                Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'One or more selected items are no longer available.');
                return;
            }

            $stock = $inventory[$qcItemId];
            $availableTagMap = array_fill_keys(array_map('strtoupper', $stock['available_tags']), true);
            
            // Check against Damage Management records
            $damagedCheckRows = Database::fetchAll(
                "SELECT rd.damage_number, rd.damage_reason, rd.qr_code, sr.roll_number
                 FROM roll_damages rd
                 LEFT JOIN seller_rolls sr ON sr.id = rd.seller_roll_id
                 WHERE rd.status NOT IN ('CANCELLED', 'REJECTED')"
            );
            $damagedLookup = [];
            foreach ($damagedCheckRows as $dCR) {
                if (!empty($dCR['qr_code'])) $damagedLookup[strtoupper(trim($dCR['qr_code']))] = $dCR;
                if (!empty($dCR['roll_number'])) $damagedLookup[strtoupper(trim($dCR['roll_number']))] = $dCR;
            }

            // Check against Outward Dispatches to warehouse
            $outwardCheckRows = Database::fetchAll(
                "SELECT po.outward_number, w.name as warehouse_name, pbi.piece_tags
                 FROM production_batch_items pbi
                 JOIN production_batches pb ON pb.id = pbi.production_batch_id
                 JOIN production_outwards po ON po.production_batch_id = pb.id
                 LEFT JOIN warehouses w ON (w.id = po.warehouse_id OR w.id = pb.destination_warehouse_id)
                 WHERE po.status IN ('DISPATCHED', 'RECEIVED') OR pb.status IN ('DISPATCHED', 'RECEIVED')"
            );
            $outwardLookup = [];
            foreach ($outwardCheckRows as $oCR) {
                if (!empty($oCR['piece_tags'])) {
                    $decoded = json_decode($oCR['piece_tags'], true);
                    $tagList = is_array($decoded) ? $decoded : explode(',', $oCR['piece_tags']);
                    foreach ($tagList as $t) {
                        $tClean = strtoupper(trim((string)$t));
                        if ($tClean !== '') $outwardLookup[$tClean] = $oCR;
                    }
                }
            }
            $whQrCheck = Database::fetchAll("SELECT wq.qr_code, w.name as warehouse_name FROM warehouse_qr_codes wq LEFT JOIN warehouses w ON w.id = wq.warehouse_id");
            foreach ($whQrCheck as $wQC) {
                $tClean = strtoupper(trim((string)$wQC['qr_code']));
                if ($tClean !== '') $outwardLookup[$tClean] = ['outward_number' => 'WAREHOUSE_STOCK', 'warehouse_name' => $wQC['warehouse_name']];
            }

            foreach ($tags as $tag) {
                $normalized = strtoupper($tag);
                if (isset($damagedLookup[$normalized])) {
                    $d = $damagedLookup[$normalized];
                    Session::flash('error', "Cannot bill QR item '{$tag}'! It has been quarantined in Damage Management (Damage #{$d['damage_number']} — {$d['damage_reason']}).");
                    Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create');
                    return;
                }
                if (isset($outwardLookup[$normalized])) {
                    $o = $outwardLookup[$normalized];
                    Session::flash('error', "Cannot bill QR item '{$tag}'! It has already been outwarded to warehouse ({$o['warehouse_name']} under Outward #{$o['outward_number']}).");
                    Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create');
                    return;
                }
                if (!isset($availableTagMap[$normalized]) || isset($usedTags[$normalized])) {
                    Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'A scanned QR code is unavailable or duplicated. Please scan the item again.');
                    return;
                }
                $usedTags[$normalized] = true;
            }

            $unitPrice = filter_var($submitted['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
            $hsnCode = trim((string)($submitted['hsn_code'] ?? $stock['hsn_code'] ?? '6109'));
            $gstType = strtoupper(trim((string)($submitted['gst_type'] ?? 'BOTH')));
            $taxInputType = strtoupper(trim((string)($submitted['tax_input_type'] ?? 'PERCENT')));
            $sgstRate = filter_var($submitted['sgst_rate'] ?? 0, FILTER_VALIDATE_FLOAT);
            $cgstRate = filter_var($submitted['cgst_rate'] ?? 0, FILTER_VALIDATE_FLOAT);
            $igstRate = filter_var($submitted['igst_rate'] ?? 0, FILTER_VALIDATE_FLOAT);
            $validGstTypes = ['SGST', 'IGST', 'CGST', 'BOTH', 'EXEMPTED'];
            $validTaxInputTypes = ['PERCENT', 'FIXED'];
            if (
                $unitPrice === false || $unitPrice < 0 ||
                !preg_match('/^(?:[0-9]{4}|[0-9]{6}|[0-9]{8})$/', $hsnCode) ||
                !in_array($gstType, $validGstTypes, true) ||
                !in_array($taxInputType, $validTaxInputTypes, true) ||
                $sgstRate === false || $cgstRate === false || $igstRate === false ||
                min($sgstRate, $cgstRate, $igstRate) < 0 ||
                ($taxInputType === 'PERCENT' && max($sgstRate, $cgstRate, $igstRate) > 100)
            ) {
                Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'A product price or GST rate is invalid.');
                return;
            }

            if ($gstType === 'EXEMPTED') {
                $sgstRate = $cgstRate = $igstRate = 0.0;
            } elseif ($gstType === 'SGST') {
                $cgstRate = $igstRate = 0.0;
            } elseif ($gstType === 'CGST') {
                $sgstRate = $igstRate = 0.0;
            } elseif ($gstType === 'IGST') {
                $sgstRate = $cgstRate = 0.0;
            } else {
                $igstRate = 0.0;
            }

            $itemPayload[] = [
                'stock' => $stock,
                'tags' => $tags,
                'quantity' => count($tags),
                'unit_price' => round((float)$unitPrice, 2),
                'hsn_code' => $hsnCode,
                'gst_type' => $gstType,
                'tax_input_type' => $taxInputType,
                'sgst_rate' => round((float)$sgstRate, 3),
                'cgst_rate' => round((float)$cgstRate, 3),
                'igst_rate' => round((float)$igstRate, 3),
            ];
        }
        if (!$itemPayload) {
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'Scan at least one available Production Inventory QR item.');
            return;
        }

        $totals = [
            'gross_amount' => 0.0, 'discount_amount' => 0.0, 'subtotal' => 0.0,
            'cgst_amount' => 0.0, 'sgst_amount' => 0.0, 'igst_amount' => 0.0,
            'tax_amount' => 0.0, 'total_amount' => 0.0,
        ];
        foreach ($itemPayload as &$item) {
            $stock = $item['stock'];
            $qty = $item['quantity'];
            $mrp = max(0, (float)$stock['mrp']);
            $unitPrice = $item['unit_price'];
            $gross = round($mrp * $qty, 2);
            $taxable = round($unitPrice * $qty, 2);
            $discount = max(0, round($gross - $taxable, 2));
            $taxAmount = static function (float $rate) use ($item, $taxable, $qty): float {
                if ($rate <= 0) {
                    return 0.0;
                }
                return round(
                    $item['tax_input_type'] === 'PERCENT' ? $taxable * $rate / 100 : $rate * $qty,
                    2
                );
            };
            $cgst = $taxAmount($item['cgst_rate']);
            $sgst = $taxAmount($item['sgst_rate']);
            $igst = $taxAmount($item['igst_rate']);
            $tax = round($cgst + $sgst + $igst, 2);
            $lineTotal = round($taxable + $tax, 2);
            $gstPercentage = $item['tax_input_type'] === 'PERCENT'
                ? $item['cgst_rate'] + $item['sgst_rate'] + $item['igst_rate']
                : 0.0;
            $item['gst_percentage'] = round($gstPercentage, 3);
            $item['amounts'] = [
                'mrp' => $mrp,
                'discount_percentage' => $mrp > 0 ? round(($discount / $gross) * 100, 3) : 0,
                'discount_amount' => $discount,
                'taxable_amount' => $taxable,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'tax_amount' => $tax,
                'total_amount' => $lineTotal,
            ];
            $totals['gross_amount'] += $gross;
            $totals['discount_amount'] += $discount;
            $totals['subtotal'] += $taxable;
            $totals['cgst_amount'] += $cgst;
            $totals['sgst_amount'] += $sgst;
            $totals['igst_amount'] += $igst;
            $totals['tax_amount'] += $tax;
            $totals['total_amount'] += $lineTotal;
        }
        unset($item);
        $taxType = count(array_unique(array_column($itemPayload, 'gst_type'))) === 1
            && $itemPayload[0]['gst_type'] === 'IGST'
            ? 'IGST'
            : 'CGST_SGST';
        foreach ($totals as &$amount) {
            $amount = round($amount, 2);
        }
        unset($amount);

        $cashAmount = 0.0;
        $upiAmount = 0.0;
        if ($paymentMethod === 'CASH') {
            $cashAmount = $totals['total_amount'];
        } elseif ($paymentMethod === 'UPI') {
            $upiAmount = $totals['total_amount'];
        } elseif ($paymentMethod === 'CASH_UPI') {
            $cashInput = filter_var($_POST['cash_amount'] ?? null, FILTER_VALIDATE_FLOAT);
            $upiInput = filter_var($_POST['upi_amount'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($cashInput === false || $upiInput === false || $cashInput < 0 || $upiInput < 0) {
                Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'Enter valid non-negative cash and UPI amounts.');
                return;
            }
            $cashAmount = round((float)$cashInput, 2);
            $upiAmount = round((float)$upiInput, 2);
            if (abs(($cashAmount + $upiAmount) - $totals['total_amount']) > 0.01) {
                Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', 'Cash and UPI amounts must add up to the bill total.');
                return;
            }
        }

        $paymentStatus = $paymentMethod === 'CREDIT' ? 'PENDING' : 'PAID';
        $header = [
            $productionUnitId,
            $buyerType,
            $companyName,
            $companyAddress,
            $companyPhone,
            $companyEmail,
            $companyGstin,
            $customerName,
            $customerPhone,
            $customerAddress,
            $customerCity,
            $customerState,
            $customerGstin,
            $billDate,
            $paymentMethod,
            $paymentStatus,
            $taxType,
            $cashAmount,
            $upiAmount,
            $upiReference,
            $totals['gross_amount'],
            $totals['discount_amount'],
            $totals['subtotal'],
            $totals['cgst_amount'],
            $totals['sgst_amount'],
            $totals['igst_amount'],
            $totals['tax_amount'],
            $totals['total_amount'],
            trim((string)($_POST['notes'] ?? '')),
        ];

        Database::beginTransaction();
        try {
            if ($billId === null) {
                $billNumber = 'PB-' . date('Y') . '-' . date('mdHis') . '-' . random_int(10, 99);
                Database::execute(
                    "INSERT INTO production_bills
                     (bill_number, production_unit_id, buyer_type, company_name, company_address, company_phone, company_email, company_gstin,
                      customer_name, customer_phone, customer_address, customer_city, customer_state, customer_gstin,
                      bill_date, payment_method, payment_status, tax_type, cash_amount, upi_amount, upi_reference,
                      gross_amount, discount_amount, subtotal, cgst_amount, sgst_amount, igst_amount, tax_amount,
                      total_amount, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    array_merge([$billNumber], $header, [(int)(Auth::id() ?? 0) ?: null])
                );
                $billId = (int)Database::lastInsertId();
            } else {
                Database::execute(
                    "UPDATE production_bills SET production_unit_id = ?, buyer_type = ?, company_name = ?, company_address = ?, company_phone = ?, company_email = ?,
                     company_gstin = ?, customer_name = ?, customer_phone = ?, customer_address = ?, customer_city = ?,
                     customer_state = ?, customer_gstin = ?, bill_date = ?, payment_method = ?, payment_status = ?,
                     tax_type = ?, cash_amount = ?, upi_amount = ?, upi_reference = ?, gross_amount = ?, discount_amount = ?,
                     subtotal = ?, cgst_amount = ?, sgst_amount = ?, igst_amount = ?, tax_amount = ?, total_amount = ?, notes = ?
                     WHERE id = ?",
                    array_merge($header, [$billId])
                );
                Database::execute("DELETE FROM production_bill_items WHERE bill_id = ?", [$billId]);
                Database::execute("DELETE FROM production_bill_item_tags WHERE bill_id = ?", [$billId]);
            }

            foreach ($itemPayload as $item) {
                $stock = $item['stock'];
                $amounts = $item['amounts'];
                Database::execute(
                    "INSERT INTO production_bill_items
                     (bill_id, qc_item_id, variant_id, product_name, sku, size_name, color_name, quantity, mrp,
                      hsn_code, unit_price, discount_percentage, discount_amount, gst_percentage, gst_type, tax_input_type,
                      sgst_rate, cgst_rate, igst_rate, taxable_amount, cgst_amount, sgst_amount, igst_amount,
                      tax_amount, total_amount)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $billId, $stock['qc_item_id'], $stock['variant_id'] ?: null,
                        $stock['product_name'], $stock['sku'], $stock['size_name'], $stock['color_name'],
                        $item['quantity'], $amounts['mrp'], $item['hsn_code'], $item['unit_price'], $amounts['discount_percentage'],
                        $amounts['discount_amount'], $item['gst_percentage'], $item['gst_type'], $item['tax_input_type'],
                        $item['sgst_rate'], $item['cgst_rate'], $item['igst_rate'], $amounts['taxable_amount'],
                        $amounts['cgst_amount'], $amounts['sgst_amount'], $amounts['igst_amount'],
                        $amounts['tax_amount'], $amounts['total_amount'],
                    ]
                );
                $billItemId = (int)Database::lastInsertId();
                foreach ($item['tags'] as $tag) {
                    Database::execute(
                        "INSERT INTO production_bill_item_tags (bill_id, bill_item_id, qc_item_id, tag_code) VALUES (?, ?, ?, ?)",
                        [$billId, $billItemId, $stock['qc_item_id'], $tag]
                    );
                }
            }
            Database::commit();
            Response::redirect('production/billing/view/' . $billId, 'success', $isNewBill ? 'Production bill created.' : 'Production bill saved.');
        } catch (Throwable $e) {
            Database::rollback();
            error_log('ProductionBillingController::persistBill failed: ' . $e->getMessage());
            $message = str_contains($e->getMessage(), 'uniq_production_bill_tag')
                ? 'A QR code was just billed in another transaction. Refresh inventory and try again.'
                : 'Unable to save the production bill. Please verify the details and try again.';
            Response::redirect($billId ? 'production/billing/edit/' . $billId : 'production/billing/create', 'error', $message);
        }
    }
}
