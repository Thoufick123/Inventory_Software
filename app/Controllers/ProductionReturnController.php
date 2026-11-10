<?php

class ProductionReturnController
{
    public function index(): void
    {
        $search = trim((string)($_GET['search'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $type = trim((string)($_GET['type'] ?? ''));
        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo = trim((string)($_GET['date_to'] ?? ''));

        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(pr.return_number LIKE ? OR pr.bill_number LIKE ? OR pr.customer_name LIKE ? OR pr.customer_phone LIKE ?)';
            $wild = '%' . $search . '%';
            $params = array_merge($params, [$wild, $wild, $wild, $wild]);
        }

        if ($status !== '') {
            $where[] = 'pr.status = ?';
            $params[] = $status;
        }

        if ($type !== '') {
            $where[] = 'pr.return_type = ?';
            $params[] = $type;
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(pr.created_at) >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(pr.created_at) <= ?';
            $params[] = $dateTo;
        }

        $sql = "SELECT pr.*, 
                       (SELECT COUNT(*) FROM production_return_items WHERE return_id = pr.id) AS item_count
                FROM production_returns pr
                WHERE " . implode(' AND ', $where) . "
                ORDER BY pr.id DESC";

        $returns = Database::fetchAll($sql, $params);

        // Stats summary
        $allStats = Database::fetchOne(
            "SELECT COUNT(*) AS total_returns,
                    COALESCE(SUM(total_returned_qty), 0) AS total_pieces,
                    COALESCE(SUM(net_amount), 0) AS total_amount,
                    COALESCE(SUM(gst_reversal_amount), 0) AS total_gst_reversal
             FROM production_returns"
        ) ?: [];

        Response::view('production.returns.index', [
            'pageTitle'    => 'Customer Returns & Exchanges | Production Unit',
            'activeModule' => 'production',
            'activeMenu'   => 'prod-returns',
            'returns'      => $returns,
            'stats'        => $allStats,
            'search'       => $search,
            'status'       => $status,
            'type'         => $type,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
        ]);
    }

    public function create(): void
    {
        Response::view('production.returns.create', [
            'pageTitle'    => 'Record Return & Exchange | Production Unit',
            'activeModule' => 'production',
            'activeMenu'   => 'prod-returns',
        ]);
    }

    public function ajaxLookupBill(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        if ($query === '') {
            Response::json(['success' => false, 'message' => 'Please enter a bill number or customer phone.']);
            return;
        }

        $bill = Database::fetchOne(
            "SELECT * FROM production_bills 
             WHERE bill_number = ? OR customer_phone = ? OR company_phone = ? 
             ORDER BY id DESC LIMIT 1",
            [$query, $query, $query]
        );

        if (!$bill) {
            Response::json(['success' => false, 'message' => 'No production bill found for query: ' . $query]);
            return;
        }

        $billId = (int)$bill['id'];

        $billItems = Database::fetchAll(
            "SELECT * FROM production_bill_items WHERE bill_id = ? ORDER BY id",
            [$billId]
        );

        // Fetch piece tags belonging to this bill from production_bill_item_tags
        // UNION with any tags that were previously returned for this bill in production_return_items
        $tagRows = Database::fetchAll(
            "SELECT bill_item_id, tag_code, 0 as is_returned
             FROM production_bill_item_tags
             WHERE bill_id = ?
             UNION
             SELECT pri.bill_item_id, pri.tag_code, 1 as is_returned
             FROM production_return_items pri
             JOIN production_returns pr ON pr.id = pri.return_id
             WHERE pr.bill_id = ? AND pri.tag_code IS NOT NULL AND pri.tag_code <> ''",
            [$billId, $billId]
        );

        // Already returned tags for this bill
        $returnedTagRows = Database::fetchAll(
            "SELECT pri.tag_code 
             FROM production_return_items pri 
             JOIN production_returns pr ON pr.id = pri.return_id 
             WHERE pr.bill_id = ? AND pr.status <> 'CANCELLED'",
            [$billId]
        );
        $alreadyReturnedTags = array_fill_keys(array_map('strtoupper', array_column($returnedTagRows, 'tag_code')), true);

        // Already returned quantity per bill_item_id
        $returnedQtyRows = Database::fetchAll(
            "SELECT pri.bill_item_id, SUM(pri.quantity) as total_returned_qty
             FROM production_return_items pri
             JOIN production_returns pr ON pr.id = pri.return_id
             WHERE pr.bill_id = ? AND pr.status <> 'CANCELLED'
             GROUP BY pri.bill_item_id",
            [$billId]
        );
        $returnedQtyByItem = [];
        foreach ($returnedQtyRows as $rqr) {
            $returnedQtyByItem[(int)$rqr['bill_item_id']] = (int)$rqr['total_returned_qty'];
        }

        // Group tags by item ID, avoiding duplicates
        $seenTags = [];
        $tagsByItem = [];
        foreach ($tagRows as $t) {
            $tagCode = strtoupper(trim((string)$t['tag_code']));
            if (empty($tagCode) || isset($seenTags[$tagCode])) {
                continue;
            }
            $seenTags[$tagCode] = true;
            $isReturned = isset($alreadyReturnedTags[$tagCode]) || !empty($t['is_returned']);
            $tagsByItem[(int)$t['bill_item_id']][] = [
                'tag_code'    => $t['tag_code'],
                'is_returned' => $isReturned,
            ];
        }

        $totalBillPieces = 0;
        $totalReturnedPieces = 0;
        $hasReturnablePieces = false;

        foreach ($billItems as &$bi) {
            $itemId = (int)$bi['id'];
            $biTags = $tagsByItem[$itemId] ?? [];
            $bi['tags'] = $biTags;
            $qty = (int)$bi['quantity'];
            $totalBillPieces += $qty;

            $alreadyReturnedCount = $returnedQtyByItem[$itemId] ?? 0;
            $bi['returned_qty'] = $alreadyReturnedCount;
            $bi['remaining_qty'] = max(0, $qty - $alreadyReturnedCount);
            $totalReturnedPieces += min($qty, $alreadyReturnedCount);

            if (!empty($biTags)) {
                foreach ($biTags as $t) {
                    if (empty($t['is_returned'])) {
                        $hasReturnablePieces = true;
                        break;
                    }
                }
            } else {
                if ($bi['remaining_qty'] > 0) {
                    $hasReturnablePieces = true;
                }
            }
        }
        unset($bi);

        // Fetch previous credit notes for this bill
        $prevReturns = Database::fetchAll(
            "SELECT id, return_number, return_type, total_returned_qty, net_amount, status, created_at
             FROM production_returns
             WHERE bill_id = ? AND status <> 'CANCELLED'
             ORDER BY id DESC",
            [$billId]
        );

        Response::json([
            'success'            => true,
            'bill'               => $bill,
            'items'              => $billItems,
            'is_fully_returned'  => !$hasReturnablePieces,
            'total_bill_pieces'  => $totalBillPieces,
            'total_returned'     => $totalReturnedPieces,
            'previous_returns'   => $prevReturns,
        ]);
    }

    public function store(): void
    {
        $billId = (int)($_POST['bill_id'] ?? 0);
        $bill = Database::fetchOne("SELECT * FROM production_bills WHERE id = ?", [$billId]);
        if (!$bill) {
            Session::setFlash('error', 'Original Production Bill not found.');
            Response::redirect(url('production/returns/create'));
            return;
        }

        $returnType = strtoupper(trim((string)($_POST['return_type'] ?? 'CREDIT_NOTE')));
        $disposition = strtoupper(trim((string)($_POST['disposition'] ?? 'RESTOCK_INVENTORY')));
        $refundMode = strtoupper(trim((string)($_POST['refund_mode'] ?? 'STORE_CREDIT')));
        $notes = trim((string)($_POST['notes'] ?? ''));

        // Selected tags and selected non-tagged items
        $selectedTags = $_POST['selected_tags'] ?? [];
        $selectedItemIds = $_POST['selected_items'] ?? [];
        $tagsData = $_POST['tags'] ?? [];
        $itemsData = $_POST['items'] ?? [];

        if (empty($selectedTags) && empty($selectedItemIds)) {
            Session::setFlash('error', 'Please select at least one garment piece to return.');
            Response::redirect(url('production/returns/create'));
            return;
        }

        // Fetch existing returned tags for this bill to prevent duplicates
        $returnedTagRows = Database::fetchAll(
            "SELECT pri.tag_code 
             FROM production_return_items pri 
             JOIN production_returns pr ON pr.id = pri.return_id 
             WHERE pr.bill_id = ? AND pr.status <> 'CANCELLED'",
            [$billId]
        );
        $alreadyReturnedTags = array_fill_keys(array_map('strtoupper', array_column($returnedTagRows, 'tag_code')), true);

        // Fetch returned quantities for non-tagged items
        $returnedQtyRows = Database::fetchAll(
            "SELECT pri.bill_item_id, SUM(pri.quantity) as total_returned_qty
             FROM production_return_items pri 
             JOIN production_returns pr ON pr.id = pri.return_id 
             WHERE pr.bill_id = ? AND pr.status <> 'CANCELLED'
             GROUP BY pri.bill_item_id",
            [$billId]
        );
        $returnedQtyByItem = [];
        foreach ($returnedQtyRows as $rqr) {
            $returnedQtyByItem[(int)$rqr['bill_item_id']] = (int)$rqr['total_returned_qty'];
        }

        Database::beginTransaction();
        try {
            $totalQty = 0;
            $subtotal = 0.0;
            $gstReversal = 0.0;
            $netAmount = 0.0;
            $itemsToInsert = [];
            $tagsToRestock = [];

            // 1. Process strictly selected piece tags
            if (!empty($selectedTags) && is_array($selectedTags)) {
                foreach ($selectedTags as $tagCodeRaw) {
                    $tagCode = trim((string)$tagCodeRaw);
                    if ($tagCode === '') {
                        continue;
                    }

                    $upperTag = strtoupper($tagCode);
                    if (isset($alreadyReturnedTags[$upperTag])) {
                        Database::rollBack();
                        Session::setFlash('error', "Piece tag '{$tagCode}' has already been returned for this bill.");
                        Response::redirect(url('production/returns/create'));
                        return;
                    }

                    $tagInfo = $tagsData[$tagCode] ?? [];
                    $billItemId = (int)($tagInfo['bill_item_id'] ?? 0);

                    // If bill_item_id wasn't in post, lookup from production_bill_item_tags
                    if ($billItemId <= 0) {
                        $pbitRow = Database::fetchOne("SELECT bill_item_id FROM production_bill_item_tags WHERE bill_id = ? AND tag_code = ?", [$billId, $tagCode]);
                        if ($pbitRow) {
                            $billItemId = (int)$pbitRow['bill_item_id'];
                        }
                    }

                    $bi = Database::fetchOne("SELECT * FROM production_bill_items WHERE id = ? AND bill_id = ?", [$billItemId, $billId]);
                    if (!$bi) {
                        Database::rollBack();
                        Session::setFlash('error', "Item for tag '{$tagCode}' not found on this bill.");
                        Response::redirect(url('production/returns/create'));
                        return;
                    }

                    $reason = trim((string)($tagInfo['reason'] ?? 'Customer Return'));
                    $condition = strtoupper(trim((string)($tagInfo['condition'] ?? 'GOOD')));
                    $itemAction = ($condition === 'GOOD' && $disposition === 'RESTOCK_INVENTORY') ? 'RESTOCKED' : (($condition === 'DAMAGED') ? 'SCRAPPED' : 'SENT_TO_REWORK');

                    $unitPrice = (float)$bi['unit_price'];
                    $gstPct = (float)$bi['gst_percentage'];
                    $itemTaxable = round($unitPrice, 2);
                    $itemTax = round($itemTaxable * ($gstPct / 100), 2);
                    $itemTotal = round($itemTaxable + $itemTax, 2);

                    $totalQty += 1;
                    $subtotal += $itemTaxable;
                    $gstReversal += $itemTax;
                    $netAmount += $itemTotal;

                    $itemsToInsert[] = [
                        'bill_item_id'   => (int)$bi['id'],
                        'qc_item_id'     => (int)$bi['qc_item_id'],
                        'variant_id'     => (int)($bi['variant_id'] ?? 0) ?: null,
                        'product_name'   => $bi['product_name'],
                        'sku'            => $bi['sku'],
                        'hsn_code'       => $bi['hsn_code'] ?? '6109',
                        'tag_code'       => $tagCode,
                        'quantity'       => 1,
                        'unit_price'     => $unitPrice,
                        'gst_percentage' => $gstPct,
                        'taxable_amount' => $itemTaxable,
                        'tax_amount'     => $itemTax,
                        'total_amount'   => $itemTotal,
                        'reason'         => $reason,
                        'item_condition' => $condition,
                        'action_taken'   => $itemAction,
                    ];

                    if ($itemAction === 'RESTOCKED') {
                        $tagsToRestock[] = $tagCode;
                    }
                }
            }

            // 2. Process non-tagged items
            if (!empty($selectedItemIds) && is_array($selectedItemIds)) {
                foreach ($selectedItemIds as $billItemIdRaw) {
                    $billItemId = (int)$billItemIdRaw;
                    if ($billItemId <= 0) {
                        continue;
                    }

                    $bi = Database::fetchOne("SELECT * FROM production_bill_items WHERE id = ? AND bill_id = ?", [$billItemId, $billId]);
                    if (!$bi) {
                        continue;
                    }

                    $itemInfo = $itemsData[$billItemId] ?? [];
                    $alreadyReturned = $returnedQtyByItem[$billItemId] ?? 0;
                    $remainingQty = max(0, (int)$bi['quantity'] - $alreadyReturned);

                    $requestedQty = max(1, (int)($itemInfo['qty'] ?? 1));
                    if ($requestedQty > $remainingQty) {
                        Database::rollBack();
                        Session::setFlash('error', "Return quantity ({$requestedQty}) exceeds remaining returnable quantity ({$remainingQty}) for item '{$bi['product_name']}'.");
                        Response::redirect(url('production/returns/create'));
                        return;
                    }

                    $reason = trim((string)($itemInfo['reason'] ?? 'Customer Return'));
                    $condition = strtoupper(trim((string)($itemInfo['condition'] ?? 'GOOD')));
                    $itemAction = ($condition === 'GOOD' && $disposition === 'RESTOCK_INVENTORY') ? 'RESTOCKED' : (($condition === 'DAMAGED') ? 'SCRAPPED' : 'SENT_TO_REWORK');

                    $unitPrice = (float)$bi['unit_price'];
                    $gstPct = (float)$bi['gst_percentage'];
                    $itemTaxable = round($unitPrice * $requestedQty, 2);
                    $itemTax = round($itemTaxable * ($gstPct / 100), 2);
                    $itemTotal = round($itemTaxable + $itemTax, 2);

                    $totalQty += $requestedQty;
                    $subtotal += $itemTaxable;
                    $gstReversal += $itemTax;
                    $netAmount += $itemTotal;

                    $itemsToInsert[] = [
                        'bill_item_id'   => (int)$bi['id'],
                        'qc_item_id'     => (int)$bi['qc_item_id'],
                        'variant_id'     => (int)($bi['variant_id'] ?? 0) ?: null,
                        'product_name'   => $bi['product_name'],
                        'sku'            => $bi['sku'],
                        'hsn_code'       => $bi['hsn_code'] ?? '6109',
                        'tag_code'       => null,
                        'quantity'       => $requestedQty,
                        'unit_price'     => $unitPrice,
                        'gst_percentage' => $gstPct,
                        'taxable_amount' => $itemTaxable,
                        'tax_amount'     => $itemTax,
                        'total_amount'   => $itemTotal,
                        'reason'         => $reason,
                        'item_condition' => $condition,
                        'action_taken'   => $itemAction,
                    ];
                }
            }

            if ($totalQty === 0) {
                Database::rollBack();
                Session::setFlash('error', 'No valid items selected for return.');
                Response::redirect(url('production/returns/create'));
                return;
            }

            // Generate Return Number: PR-CRN-YYYY-XXXX
            $count = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM production_returns")['c'] ?? 0);
            $returnNumber = 'PR-CRN-' . date('Y') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

            Database::execute(
                "INSERT INTO production_returns 
                 (return_number, bill_id, bill_number, customer_name, customer_phone, return_type, disposition,
                  total_returned_qty, subtotal, gst_reversal_amount, net_amount, status, refund_mode, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'COMPLETED', ?, ?, ?)",
                [
                    $returnNumber,
                    $billId,
                    $bill['bill_number'],
                    $bill['customer_name'] ?: ($bill['company_name'] ?? 'B2B Client'),
                    $bill['customer_phone'] ?: ($bill['company_phone'] ?? ''),
                    $returnType,
                    $disposition,
                    $totalQty,
                    $subtotal,
                    $gstReversal,
                    $netAmount,
                    $refundMode,
                    $notes,
                    (int)(Auth::id() ?? 0) ?: null,
                ]
            );
            $returnId = (int)Database::lastInsertId();

            foreach ($itemsToInsert as $ins) {
                Database::execute(
                    "INSERT INTO production_return_items
                     (return_id, bill_item_id, qc_item_id, variant_id, product_name, sku, hsn_code, tag_code,
                      quantity, unit_price, gst_percentage, taxable_amount, tax_amount, total_amount,
                      reason, item_condition, action_taken)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $returnId, $ins['bill_item_id'], $ins['qc_item_id'], $ins['variant_id'],
                        $ins['product_name'], $ins['sku'], $ins['hsn_code'], $ins['tag_code'],
                        $ins['quantity'], $ins['unit_price'], $ins['gst_percentage'],
                        $ins['taxable_amount'], $ins['tax_amount'], $ins['total_amount'],
                        $ins['reason'], $ins['item_condition'], $ins['action_taken'],
                    ]
                );
            }

            // Execute stock release for restocked tags: remove from bill tags so they can be sold again!
            if (!empty($tagsToRestock)) {
                $placeholders = implode(',', array_fill(0, count($tagsToRestock), '?'));
                Database::execute(
                    "DELETE FROM production_bill_item_tags WHERE bill_id = ? AND tag_code IN ({$placeholders})",
                    array_merge([$billId], $tagsToRestock)
                );
            }

            Database::commit();
            Session::setFlash('success', "Return processed successfully! Credit Note #{$returnNumber} issued for ₹" . number_format($netAmount, 2));
            Response::redirect(url('production/returns/view/' . $returnId));
        } catch (Throwable $e) {
            Database::rollBack();
            error_log('ProductionReturnController::store failed: ' . $e->getMessage());
            Session::setFlash('error', 'Error processing return: ' . $e->getMessage());
            Response::redirect(url('production/returns/create'));
        }
    }

    public function view(string $id): void
    {
        $returnId = (int)$id;
        $return = Database::fetchOne("SELECT * FROM production_returns WHERE id = ?", [$returnId]);
        if (!$return) {
            Session::setFlash('error', 'Return / Credit Note record not found.');
            Response::redirect(url('production/returns'));
            return;
        }

        $items = Database::fetchAll("SELECT * FROM production_return_items WHERE return_id = ? ORDER BY id", [$returnId]);
        $bill = $return['bill_id'] ? Database::fetchOne("SELECT * FROM production_bills WHERE id = ?", [$return['bill_id']]) : null;

        Response::view('production.returns.view', [
            'pageTitle'    => 'Credit Note ' . $return['return_number'],
            'activeModule' => 'production',
            'activeMenu'   => 'prod-returns',
            'return'       => $return,
            'items'        => $items,
            'bill'         => $bill,
        ]);
    }

    public function print(string $id): void
    {
        $returnId = (int)$id;
        $return = Database::fetchOne("SELECT * FROM production_returns WHERE id = ?", [$returnId]);
        if (!$return) {
            Session::setFlash('error', 'Return / Credit Note not found.');
            Response::redirect(url('production/returns'));
            return;
        }

        $items = Database::fetchAll("SELECT * FROM production_return_items WHERE return_id = ? ORDER BY id", [$returnId]);
        $bill = $return['bill_id'] ? Database::fetchOne("SELECT * FROM production_bills WHERE id = ?", [$return['bill_id']]) : null;

        Response::viewOnly('production.returns.print', [
            'return'        => $return,
            'items'         => $items,
            'bill'          => $bill,
            'sellerCompany' => SettingsService::getGroup('company'),
            'prodUnit'      => ProductionUnit::getActiveFacility($return['production_unit_id'] ?? null),
        ]);
    }
}
