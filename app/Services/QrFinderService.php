<?php
/**
 * QrFinderService — Universal QR & Barcode Status Engine
 * Scans, analyzes, and traces the complete lifecycle history of ANY QR code,
 * barcode, piece tag, batch, or transaction number across all inventory modules.
 */

class QrFinderService {

    /**
     * Trace any QR code / barcode across the entire system.
     *
     * @param string $rawCode
     * @return array
     */
    public static function trace(string $rawCode): array {
        $code = strtoupper(trim(explode('|', $rawCode, 2)[0]));
        $code = ltrim($code, '#');
        $code = trim($code);

        if ($code === '') {
            return [
                'success' => false,
                'message' => 'Please provide a valid QR code or barcode to scan.',
            ];
        }

        try {
            $timeline = [];
            $matched = false;
            $itemType = 'UNKNOWN';
            $itemTitle = 'Item ' . $code;
            $currentStatus = 'UNKNOWN';
            $statusColor = 'secondary';
            $currentLocation = 'System Archive / Unspecified';
            $details = [];

        // =========================================================================
        // 1. FABRIC ROLL PIECE (e.g. FB112601) OR ROLL NUMBER (e.g. ROLL #1)
        // =========================================================================
        $isRollPiece = false;
        $roll = null;
        $pieceSeq = null;

        if (preg_match('/^([A-Z]{2}\d{4})(\d+)$/', $code, $matches)) {
            $prefix = $matches[1];
            $seq = (int)$matches[2];
            $candidates = Database::fetchAll(
                "SELECT sr.*, s.name as seller_name, s.company as seller_company,
                        pp.purchase_number, pp.purchase_date, pp.invoice_number, pp.id as purchase_id
                 FROM seller_rolls sr
                 JOIN sellers s ON s.id = sr.seller_id
                 LEFT JOIN production_purchase_items ppi ON ppi.seller_roll_id = sr.id
                 LEFT JOIN production_purchases pp ON pp.id = ppi.purchase_id
                 WHERE sr.start_code LIKE ? AND sr.end_code LIKE ?",
                [$prefix . '%', $prefix . '%']
            );

            foreach ($candidates as $cand) {
                if (strtoupper(substr($cand['start_code'], 0, 6)) === $prefix
                    && $seq >= (int)$cand['start_seq'] && $seq <= (int)$cand['end_seq']) {
                    $isRollPiece = true;
                    $roll = $cand;
                    $pieceSeq = $seq;
                    break;
                }
            }
        }

        if (!$isRollPiece) {
            // Check direct roll number, qr_code, start_code
            $directRoll = Database::fetchOne(
                "SELECT sr.*, s.name as seller_name, s.company as seller_company,
                        pp.purchase_number, pp.purchase_date, pp.invoice_number, pp.id as purchase_id
                 FROM seller_rolls sr
                 JOIN sellers s ON s.id = sr.seller_id
                 LEFT JOIN production_purchase_items ppi ON ppi.seller_roll_id = sr.id
                 LEFT JOIN production_purchases pp ON pp.id = ppi.purchase_id
                 WHERE sr.roll_number = ? OR sr.qr_code = ? OR sr.start_code = ? OR sr.end_code = ?
                 LIMIT 1",
                [$code, $code, $code, $code]
            );
            if ($directRoll) {
                $isRollPiece = true;
                $roll = $directRoll;
            }
        }

        if ($isRollPiece && $roll) {
            $matched = true;
            $itemType = 'Fabric Roll Piece';
            $suppName = $roll['seller_company'] ?: $roll['seller_name'];
            $itemTitle = "{$suppName} — {$roll['roll_number']}";

            $totQty = max(1, (int)$roll['quantity']);
            $pieceWeight = round(((float)$roll['weight']) / $totQty, 2);

            $details = [
                'Roll Label' => $roll['roll_number'],
                'Supplier / Mill' => $suppName,
                'Fabric Type' => $roll['material_type'] ?: 'Textile Fabric',
                'Color & Design' => trim(($roll['color'] ?? '') . ' ' . ($roll['design_name'] ?? '')),
                'Total Lot Weight' => number_format((float)$roll['weight'], 2) . ' kg (' . $totQty . ' Pcs)',
                'Piece Weight' => $pieceWeight . ' kg',
                'Piece Tag' => $pieceSeq ? "Piece #{$pieceSeq} of {$totQty} ({$code})" : $code,
            ];

            // Step 1: Procurement
            $timeline[] = [
                'stage' => 'MATERIAL_PURCHASE',
                'title' => 'Fabric Inward & Procurement',
                'timestamp' => $roll['purchase_date'] ?: $roll['created_at'],
                'badge' => 'Procured',
                'badge_color' => 'success',
                'icon' => 'bi-truck',
                'location' => 'Raw Material Inward / Fabric Store',
                'description' => "Procured from {$suppName} (Invoice: " . ($roll['invoice_number'] ?: 'N/A') . ", PO: " . ($roll['purchase_number'] ?: 'N/A') . "). Lot: {$roll['roll_number']} ({$roll['weight']} kg).",
            ];
            $currentLocation = 'Fabric Inventory / Storage Rack';
            $currentStatus = 'IN_STOCK';
            $statusColor = 'success';

            // Check Cutting Batches
            $cuttings = Database::fetchAll(
                "SELECT cb.*, p.name as product_name, cbr.used_weight
                 FROM cutting_batch_rolls cbr
                 JOIN cutting_batches cb ON cb.id = cbr.cutting_batch_id
                 LEFT JOIN production_programs pp ON pp.id = cb.program_id
                 LEFT JOIN products p ON p.id = pp.product_id
                 WHERE cbr.seller_roll_id = ?
                 ORDER BY cb.id ASC",
                [(int)$roll['id']]
            );

            foreach ($cuttings as $cut) {
                $timeline[] = [
                    'stage' => 'CUTTING',
                    'title' => 'Cutting Batch #' . $cut['batch_number'],
                    'timestamp' => $cut['created_at'],
                    'badge' => $cut['status'] ?: 'CUTTING',
                    'badge_color' => 'info',
                    'icon' => 'bi-scissors',
                    'location' => 'Production Cutting Floor',
                    'description' => "Assigned to Cutting Batch {$cut['batch_number']} for product: " . ($cut['product_name'] ?: 'Garment') . ". Cut Qty: " . ($cut['cut_qty'] ?: 0) . " pcs.",
                ];
                $currentLocation = "Cutting Section (Batch {$cut['batch_number']})";
                $currentStatus = 'IN_CUTTING';
                $statusColor = 'info';

                // Check Stitching Batches for this cutting batch
                $stitchings = Database::fetchAll(
                    "SELECT sb.*, pu.name as unit_name
                     FROM stitching_batches sb
                     LEFT JOIN production_units pu ON pu.id = sb.production_unit_id
                     WHERE sb.cutting_batch_id = ?
                     ORDER BY sb.id ASC",
                    [(int)$cut['id']]
                );
                foreach ($stitchings as $st) {
                    $timeline[] = [
                        'stage' => 'STITCHING',
                        'title' => 'Stitching Batch #' . $st['batch_number'],
                        'timestamp' => $st['created_at'],
                        'badge' => $st['status'] ?: 'STITCHING',
                        'badge_color' => 'info',
                        'icon' => 'bi-threads',
                        'location' => ($st['unit_name'] ?: 'Production') . ' Stitching Floor',
                        'description' => "Stitched under Batch {$st['batch_number']}. Output: {$st['stitched_qty']} pcs stitched.",
                    ];
                    $currentLocation = "Stitching Floor (Batch {$st['batch_number']})";
                    $currentStatus = 'IN_STITCHING';
                    $statusColor = 'info';
                }
            }

            // Check Damage
            $dmg = Database::fetchOne(
                "SELECT rd.*, u.full_name as reporter_name
                 FROM roll_damages rd
                 LEFT JOIN users u ON u.id = rd.reported_by
                 WHERE rd.qr_code = ? OR (rd.seller_roll_id = ? AND rd.qr_code IS NULL)
                 LIMIT 1",
                [$code, (int)$roll['id']]
            );
            if ($dmg) {
                $timeline[] = [
                    'stage' => 'DAMAGE',
                    'title' => 'Damage Quarantined #' . $dmg['damage_number'],
                    'timestamp' => $dmg['created_at'],
                    'badge' => $dmg['status'] ?: 'DAMAGED',
                    'badge_color' => 'danger',
                    'icon' => 'bi-exclamation-octagon-fill',
                    'location' => 'Defect Quarantine / Scrap Room',
                    'description' => "Defect reported: {$dmg['damage_reason']}. Loss valuation: ₹" . number_format((float)$dmg['loss_amount'], 2) . ". By: " . ($dmg['reporter_name'] ?: 'Staff'),
                ];
                $currentLocation = "Defect Quarantine ({$dmg['damage_number']})";
                $currentStatus = 'DAMAGED';
                $statusColor = 'danger';
            }

            // Check Billing / Invoiced
            $bill = Database::fetchOne(
                "SELECT pb.*
                 FROM production_bill_item_tags pbit
                 JOIN production_bills pb ON pb.id = pbit.bill_id
                 WHERE pbit.tag_code = ?
                 LIMIT 1",
                [$code]
            );
            if ($bill) {
                $cust = $bill['customer_name'] ?: $bill['company_name'] ?: 'Customer';
                $timeline[] = [
                    'stage' => 'BILLED',
                    'title' => 'Sold / Invoiced #' . $bill['bill_number'],
                    'timestamp' => $bill['created_at'],
                    'badge' => 'Sold',
                    'badge_color' => 'dark',
                    'icon' => 'bi-receipt',
                    'location' => 'Customer Handover (Sold)',
                    'description' => "Invoiced to {$cust} under Bill #{$bill['bill_number']}. Total: ₹" . number_format((float)$bill['total_amount'], 2),
                ];
                $currentLocation = "Sold / Dispatched to {$cust}";
                $currentStatus = 'SOLD';
                $statusColor = 'dark';
            }
        }

        // =========================================================================
        // 2. FINISHED PRODUCT PIECE TAG / GARMENT QR CODE (e.g. MSF112606, TTT112631)
        // =========================================================================
        if (!$matched) {
            $qcItem = Database::fetchOne(
                "SELECT qi.*, qb.batch_number as qc_batch_number, qb.created_at as qc_date,
                        qb.program_id, qb.stitching_batch_id,
                        p.id as product_id, p.name as product_name, p.code as product_code,
                        pv.sku, COALESCE(qi.size_name, sz.name) as size_name, COALESCE(qi.color_name, cl.name) as color_name,
                        u.full_name as inspector_name
                 FROM qc_items qi
                 JOIN qc_batches qb ON qb.id = qi.qc_batch_id
                 LEFT JOIN product_variants pv ON pv.id = qi.variant_id
                 LEFT JOIN products p ON (p.id = pv.product_id OR p.category_id = qi.category_id)
                 LEFT JOIN sizes sz ON sz.id = pv.size_id
                 LEFT JOIN colors cl ON cl.id = pv.color_id
                 LEFT JOIN users u ON u.id = qb.inspector_id
                 WHERE qi.tag_code = ?
                 LIMIT 1",
                [$code]
            );

            $batchItem = Database::fetchOne(
                "SELECT pbi.*, pb.batch_number, pb.created_at as batch_date,
                        pb.status as batch_status, pb.destination_warehouse_id, w.name as dest_warehouse_name,
                        p.id as product_id, p.name as product_name, p.code as product_code,
                        pv.sku, sz.name as size_name, cl.name as color_name
                 FROM production_batch_items pbi
                 JOIN production_batches pb ON pb.id = pbi.production_batch_id
                 LEFT JOIN product_variants pv ON pv.id = pbi.variant_id
                 LEFT JOIN products p ON p.id = pv.product_id
                 LEFT JOIN sizes sz ON sz.id = pv.size_id
                 LEFT JOIN colors cl ON cl.id = pv.color_id
                 LEFT JOIN warehouses w ON w.id = pb.destination_warehouse_id
                 WHERE pbi.piece_tags LIKE ? OR pbi.received_qr_codes LIKE ?
                 LIMIT 1",
                ['%' . $code . '%', '%' . $code . '%']
            );

            $whQr = Database::fetchOne(
                "SELECT wq.*, w.name as warehouse_name,
                        p.id as product_id, p.name as product_name, p.code as product_code,
                        pv.sku, sz.name as size_name, cl.name as color_name
                 FROM warehouse_qr_codes wq
                 LEFT JOIN warehouses w ON w.id = wq.warehouse_id
                 LEFT JOIN product_variants pv ON pv.id = wq.variant_id
                 LEFT JOIN products p ON p.id = pv.product_id
                 LEFT JOIN sizes sz ON sz.id = pv.size_id
                 LEFT JOIN colors cl ON cl.id = pv.color_id
                 WHERE wq.qr_code = ?
                 LIMIT 1",
                [$code]
            );

            // Also check product variant series
            $variantMatch = null;
            if (!$qcItem && !$batchItem && !$whQr) {
                $variantMatch = Database::fetchOne(
                    "SELECT pv.*, p.name as product_name, p.code as product_code,
                            sz.name as size_name, cl.name as color_name
                     FROM product_variants pv
                     JOIN products p ON p.id = pv.product_id
                     LEFT JOIN sizes sz ON sz.id = pv.size_id
                     LEFT JOIN colors cl ON cl.id = pv.color_id
                     WHERE pv.sku = ? OR pv.qr_code = ?
                     LIMIT 1",
                    [$code, $code]
                );
            }

            if ($qcItem || $batchItem || $whQr || $variantMatch) {
                $matched = true;
                $itemType = 'Finished Garment Piece';
                $prodName = $qcItem['product_name'] ?? $batchItem['product_name'] ?? $whQr['product_name'] ?? $variantMatch['product_name'] ?? 'Garment Piece';
                $sku = $qcItem['sku'] ?? $batchItem['sku'] ?? $whQr['sku'] ?? $variantMatch['sku'] ?? $code;
                $size = $qcItem['size_name'] ?? $batchItem['size_name'] ?? $whQr['size_name'] ?? $variantMatch['size_name'] ?? '';
                $color = $qcItem['color_name'] ?? $batchItem['color_name'] ?? $whQr['color_name'] ?? $variantMatch['color_name'] ?? '';

                $itemTitle = $prodName . ($size || $color ? " ({$size}" . ($color ? " / {$color}" : '') . ")" : '');
                $details = [
                    'Product Name' => $prodName,
                    'SKU' => $sku,
                    'Size' => $size ?: 'Standard',
                    'Color' => $color ?: 'Standard',
                    'Piece Tag / QR' => $code,
                ];

                // Backward trace to Program & Stitching if linked to QC
                if (!empty($qcItem['stitching_batch_id'])) {
                    $st = Database::fetchOne(
                        "SELECT sb.*, cb.batch_number as cutting_batch_number
                         FROM stitching_batches sb
                         LEFT JOIN cutting_batches cb ON cb.id = sb.cutting_batch_id
                         WHERE sb.id = ?",
                        [(int)$qcItem['stitching_batch_id']]
                    );
                    if ($st) {
                        $timeline[] = [
                            'stage' => 'STITCHING',
                            'title' => 'Stitched in Batch #' . $st['batch_number'],
                            'timestamp' => $st['created_at'],
                            'badge' => 'COMPLETED',
                            'badge_color' => 'info',
                            'icon' => 'bi-threads',
                            'location' => 'Production Stitching Floor',
                            'description' => "Stitched under Batch {$st['batch_number']}." . ($st['cutting_batch_number'] ? " Cut from Batch {$st['cutting_batch_number']}." : ''),
                        ];
                    }
                }

                // QC Inspection
                if ($qcItem) {
                    $timeline[] = [
                        'stage' => 'QC_INSPECTION',
                        'title' => 'Quality Control Inspection',
                        'timestamp' => $qcItem['qc_date'],
                        'badge' => $qcItem['action_taken'] ?: 'PASSED',
                        'badge_color' => ($qcItem['action_taken'] === 'PASSED') ? 'success' : 'warning',
                        'icon' => 'bi-shield-check',
                        'location' => 'QC & Finishing Inspection Floor',
                        'description' => "Inspected under Batch {$qcItem['qc_batch_number']}. Result: {$qcItem['action_taken']}." . ($qcItem['inspector_name'] ? " Inspector: {$qcItem['inspector_name']}." : ''),
                    ];
                    $currentLocation = 'QC & Finishing Station';
                    $currentStatus = 'QC_' . ($qcItem['action_taken'] ?: 'PASSED');
                    $statusColor = 'info';
                }

                // Packaging Batch
                if ($batchItem) {
                    $timeline[] = [
                        'stage' => 'PACKING_BATCH',
                        'title' => 'Packed in Batch #' . $batchItem['batch_number'],
                        'timestamp' => $batchItem['batch_date'],
                        'badge' => $batchItem['batch_status'] ?: 'PACKED',
                        'badge_color' => 'primary',
                        'icon' => 'bi-box-seam',
                        'location' => 'Packing & Dispatch Section',
                        'description' => "Batched for warehouse transfer to " . ($batchItem['dest_warehouse_name'] ?: 'Central Hub') . ". Batch: {$batchItem['batch_number']}.",
                    ];
                    $currentLocation = 'Production Dispatch / Packing Room';
                    $currentStatus = 'BATCH_PACKED';
                    $statusColor = 'primary';
                }

                // Outward Dispatch
                $prodBatchId = (int)($batchItem['production_batch_id'] ?? 0);
                $outward = Database::fetchOne(
                    "SELECT po.*, w.name as warehouse_name
                     FROM production_outwards po
                     LEFT JOIN warehouses w ON w.id = po.warehouse_id
                     WHERE (po.production_batch_id = ? AND ? > 0) OR po.notes LIKE ?
                     ORDER BY po.id DESC LIMIT 1",
                    [$prodBatchId, $prodBatchId, '%' . $code . '%']
                );
                if ($outward) {
                    $isRcv = ($outward['status'] === 'RECEIVED');
                    $timeline[] = [
                        'stage' => 'DISPATCH_TRANSIT',
                        'title' => 'Outward Dispatch #' . $outward['outward_number'],
                        'timestamp' => $outward['dispatch_date'] ?: $outward['created_at'],
                        'badge' => $outward['status'] ?: 'DISPATCHED',
                        'badge_color' => $isRcv ? 'success' : 'warning',
                        'icon' => 'bi-truck-flatbed',
                        'location' => $isRcv ? ($outward['warehouse_name'] ?: 'Warehouse') : 'In Transit to ' . ($outward['warehouse_name'] ?: 'Warehouse'),
                        'description' => "Outwarded from Production Unit to {$outward['warehouse_name']}. Tracking/Vehicle: " . ($outward['vehicle_number'] ?: 'Direct Transfer') . ". Status: {$outward['status']}.",
                    ];
                    if ($isRcv) {
                        $currentLocation = ($outward['warehouse_name'] ?: 'Warehouse') . ' Inward Floor';
                        $currentStatus = 'AT_WAREHOUSE';
                        $statusColor = 'success';
                    } else {
                        $currentLocation = 'In Transit to ' . ($outward['warehouse_name'] ?: 'Warehouse');
                        $currentStatus = 'IN_TRANSIT';
                        $statusColor = 'warning';
                    }
                }

                // Warehouse Inward Stock
                if ($whQr) {
                    $inwardTime = $whQr['inwarded_at'] ?: $whQr['created_at'];
                    $timeline[] = [
                        'stage' => 'WAREHOUSE_STOCK',
                        'title' => 'Warehouse Inward & Stock Registration',
                        'timestamp' => $inwardTime,
                        'badge' => 'Active Inward Stock',
                        'badge_color' => 'success',
                        'icon' => 'bi-building-check',
                        'location' => ($whQr['warehouse_name'] ?: 'Main Warehouse') . ' Stock Bin',
                        'description' => "Inwarded into warehouse stock inventory at {$whQr['warehouse_name']}.",
                    ];
                    $currentLocation = ($whQr['warehouse_name'] ?: 'Warehouse') . ' Inventory Stock';
                    $currentStatus = 'IN_WAREHOUSE_STOCK';
                    $statusColor = 'success';
                }

                // Allocations to Shops
                $alloc = Database::fetchOne(
                    "SELECT wa.*, s.name as shop_name, ao.dispatch_date, ao.status as dispatch_status
                     FROM warehouse_allocations wa
                     LEFT JOIN shops s ON s.id = wa.shop_id
                     LEFT JOIN allocation_outwards ao ON ao.allocation_id = wa.id
                     WHERE wa.notes LIKE ? OR wa.allocation_number LIKE ?
                     LIMIT 1",
                    ['%' . $code . '%', '%' . $code . '%']
                );
                if ($alloc) {
                    $timeline[] = [
                        'stage' => 'SHOP_ALLOCATION',
                        'title' => 'Allocated to Retail Shop: ' . ($alloc['shop_name'] ?: 'Shop'),
                        'timestamp' => $alloc['created_at'],
                        'badge' => $alloc['status'] ?: 'ALLOCATED',
                        'badge_color' => 'info',
                        'icon' => 'bi-shop',
                        'location' => 'Retail Shop — ' . ($alloc['shop_name'] ?: 'Shop'),
                        'description' => "Allocated under Allocation #{$alloc['allocation_number']} to {$alloc['shop_name']}.",
                    ];
                    $currentLocation = 'Retail Shop (' . ($alloc['shop_name'] ?: 'Shop') . ')';
                    $currentStatus = 'AT_SHOP';
                    $statusColor = 'info';
                }

                // Damage Checks
                $dmg = Database::fetchOne(
                    "SELECT rd.*, u.full_name as reporter_name
                     FROM roll_damages rd
                     LEFT JOIN users u ON u.id = rd.reported_by
                     WHERE rd.qr_code = ? LIMIT 1",
                    [$code]
                );
                if (!$dmg) {
                    $dmg = Database::fetchOne(
                        "SELECT wd.*, u.full_name as reporter_name
                         FROM warehouse_damages wd
                         LEFT JOIN users u ON u.id = wd.reported_by
                         WHERE wd.tag_code = ? LIMIT 1",
                        [$code]
                    );
                }
                if ($dmg) {
                    $dmgNum = $dmg['damage_number'] ?? 'DMG';
                    $lossVal = $dmg['loss_amount'] ?? $dmg['total_loss'] ?? 0;
                    $timeline[] = [
                        'stage' => 'DAMAGE',
                        'title' => 'Damage Reported #' . $dmgNum,
                        'timestamp' => $dmg['created_at'],
                        'badge' => $dmg['status'] ?? 'DAMAGED',
                        'badge_color' => 'danger',
                        'icon' => 'bi-exclamation-octagon-fill',
                        'location' => 'Defect Quarantine',
                        'description' => "Defect: " . ($dmg['damage_reason'] ?? 'Damaged') . ". Loss Valuation: ₹" . number_format((float)$lossVal, 2),
                    ];
                    $currentLocation = "Damage Quarantine ({$dmgNum})";
                    $currentStatus = 'DAMAGED';
                    $statusColor = 'danger';
                }

                // Billing / Sales
                $bill = Database::fetchOne(
                    "SELECT pb.*
                     FROM production_bill_item_tags pbit
                     JOIN production_bills pb ON pb.id = pbit.bill_id
                     WHERE pbit.tag_code = ?
                     LIMIT 1",
                    [$code]
                );
                if ($bill) {
                    $cust = $bill['customer_name'] ?: $bill['company_name'] ?: 'Customer';
                    $timeline[] = [
                        'stage' => 'BILLED',
                        'title' => 'Sold / Billed #' . $bill['bill_number'],
                        'timestamp' => $bill['created_at'],
                        'badge' => 'Sold',
                        'badge_color' => 'dark',
                        'icon' => 'bi-receipt',
                        'location' => 'Customer Handover (Sold)',
                        'description' => "Invoiced to {$cust} under Bill #{$bill['bill_number']}.",
                    ];
                    $currentLocation = "Sold to Customer (Bill #{$bill['bill_number']})";
                    $currentStatus = 'SOLD';
                    $statusColor = 'dark';
                }
            }
        }

        // =========================================================================
        // 3. PURCHASE ORDER / INVOICE NUMBER (e.g. PP-2026-000001)
        // =========================================================================
        if (!$matched) {
            $po = Database::fetchOne(
                "SELECT pp.*, s.name as seller_name, s.company as seller_company
                 FROM production_purchases pp
                 LEFT JOIN sellers s ON s.id = pp.seller_id
                 WHERE pp.purchase_number = ? OR pp.invoice_number = ?
                 LIMIT 1",
                [$code, $code]
            );
            if ($po) {
                $matched = true;
                $itemType = 'Procurement Order';
                $supp = $po['seller_company'] ?: $po['seller_name'];
                $itemTitle = "Procurement Order {$po['purchase_number']} ({$supp})";
                $details = [
                    'PO Number' => $po['purchase_number'],
                    'Supplier' => $supp,
                    'Invoice #' => $po['invoice_number'] ?: 'N/A',
                    'Purchase Date' => date('d M Y', strtotime($po['purchase_date'])),
                    'Total Amount' => '₹ ' . number_format((float)$po['total_amount'], 2),
                ];
                $timeline[] = [
                    'stage' => 'PURCHASE_ORDER',
                    'title' => 'Procurement Order Created',
                    'timestamp' => $po['created_at'],
                    'badge' => 'Recorded',
                    'badge_color' => 'success',
                    'icon' => 'bi-cart-check',
                    'location' => 'Purchasing Department',
                    'description' => "Purchase order generated for {$supp}. Total: ₹" . number_format((float)$po['total_amount'], 2),
                ];
                $currentStatus = 'RECEIVED';
                $statusColor = 'success';
                $currentLocation = 'Inward Materials / Fabric Store';
            }
        }

        // =========================================================================
        // 4. CUTTING BATCH NUMBER (e.g. CUT-20261108-0001)
        // =========================================================================
        if (!$matched) {
            $cutBatch = Database::fetchOne(
                "SELECT cb.*, p.name as product_name
                 FROM cutting_batches cb
                 LEFT JOIN production_programs pp ON pp.id = cb.program_id
                 LEFT JOIN products p ON p.id = pp.product_id
                 WHERE cb.batch_number = ?
                 LIMIT 1",
                [$code]
            );
            if ($cutBatch) {
                $matched = true;
                $itemType = 'Cutting Batch';
                $itemTitle = "Cutting Batch {$cutBatch['batch_number']}";
                $details = [
                    'Batch Number' => $cutBatch['batch_number'],
                    'Target Product' => $cutBatch['product_name'] ?: 'Garment Product',
                    'Status' => $cutBatch['status'] ?: 'IN_PROGRESS',
                    'Planned Qty' => ($cutBatch['planned_qty'] ?: 0) . ' pcs',
                    'Cut Qty' => ($cutBatch['cut_qty'] ?: 0) . ' pcs',
                ];
                $timeline[] = [
                    'stage' => 'CUTTING',
                    'title' => 'Cutting Batch Initialized',
                    'timestamp' => $cutBatch['created_at'],
                    'badge' => $cutBatch['status'] ?: 'CUTTING',
                    'badge_color' => 'info',
                    'icon' => 'bi-scissors',
                    'location' => 'Production Cutting Floor',
                    'description' => "Target Product: " . ($cutBatch['product_name'] ?: 'Garment') . ". Cut: {$cutBatch['cut_qty']} pcs.",
                ];
                $currentStatus = $cutBatch['status'] ?: 'IN_PROGRESS';
                $statusColor = 'info';
                $currentLocation = 'Production Cutting Floor';
            }
        }

        // =========================================================================
        // 5. PACKING / FINISHED PRODUCTION BATCH (e.g. BAT-20261108-0001)
        // =========================================================================
        if (!$matched) {
            $pb = Database::fetchOne(
                "SELECT pb.*, w.name as warehouse_name
                 FROM production_batches pb
                 LEFT JOIN warehouses w ON w.id = pb.destination_warehouse_id
                 WHERE pb.batch_number = ?
                 LIMIT 1",
                [$code]
            );
            if ($pb) {
                $matched = true;
                $itemType = 'Production Batch (Packed)';
                $itemTitle = "Production Batch {$pb['batch_number']}";
                $details = [
                    'Batch Number' => $pb['batch_number'],
                    'Destination Warehouse' => $pb['warehouse_name'] ?: 'Central Hub',
                    'Status' => $pb['status'] ?: 'PACKED',
                    'Total Pieces' => ($pb['total_qty'] ?: 0) . ' pcs',
                    'Total Boxes' => ($pb['box_count'] ?: 1) . ' boxes',
                ];
                $timeline[] = [
                    'stage' => 'PACKING_BATCH',
                    'title' => 'Batch Packed & Registered',
                    'timestamp' => $pb['created_at'],
                    'badge' => $pb['status'] ?: 'PACKED',
                    'badge_color' => 'primary',
                    'icon' => 'bi-box-seam',
                    'location' => 'Packing & Dispatch Section',
                    'description' => "Total {$pb['total_qty']} pcs packed for {$pb['warehouse_name']}.",
                ];
                $currentStatus = $pb['status'] ?: 'PACKED';
                $statusColor = 'primary';
                $currentLocation = 'Production Dispatch / ' . ($pb['warehouse_name'] ?: 'Warehouse');
            }
        }

        // =========================================================================
        // 6. OUTWARD DISPATCH NUMBER (e.g. OUT-20261108-0001)
        // =========================================================================
        if (!$matched) {
            $out = Database::fetchOne(
                "SELECT po.*, w.name as warehouse_name
                 FROM production_outwards po
                 LEFT JOIN warehouses w ON w.id = po.warehouse_id
                 WHERE po.outward_number = ?
                 LIMIT 1",
                [$code]
            );
            if ($out) {
                $matched = true;
                $itemType = 'Outward Dispatch Consignment';
                $itemTitle = "Outward Consignment {$out['outward_number']}";
                $details = [
                    'Outward #' => $out['outward_number'],
                    'Destination' => $out['warehouse_name'] ?: 'Central Warehouse',
                    'Status' => $out['status'] ?: 'DISPATCHED',
                    'Total Quantity' => ($out['quantity'] ?: $out['total_quantity'] ?: 0) . ' pcs',
                    'Vehicle / LR' => ($out['vehicle_number'] ?: 'Direct Courier') . ' ' . ($out['lr_number'] ? "(LR: {$out['lr_number']})" : ''),
                ];
                $timeline[] = [
                    'stage' => 'DISPATCH_TRANSIT',
                    'title' => 'Dispatched from Production Unit',
                    'timestamp' => $out['dispatch_date'] ?: $out['created_at'],
                    'badge' => $out['status'] ?: 'DISPATCHED',
                    'badge_color' => ($out['status'] === 'RECEIVED') ? 'success' : 'warning',
                    'icon' => 'bi-truck-flatbed',
                    'location' => ($out['status'] === 'RECEIVED') ? $out['warehouse_name'] : 'In Transit',
                    'description' => "Dispatched to {$out['warehouse_name']}. Status: {$out['status']}.",
                ];
                $currentStatus = $out['status'] ?: 'DISPATCHED';
                $statusColor = ($out['status'] === 'RECEIVED') ? 'success' : 'warning';
                $currentLocation = ($out['status'] === 'RECEIVED') ? ($out['warehouse_name'] . ' Inward Bay') : ('In Transit to ' . $out['warehouse_name']);
            }
        }

        // =========================================================================
        // 7. DAMAGE LOG NUMBER (e.g. DMG-ROL-2026-...)
        // =========================================================================
        if (!$matched) {
            $dmgLog = Database::fetchOne(
                "SELECT rd.*, s.name as seller_name, u.full_name as reporter_name
                 FROM roll_damages rd
                 LEFT JOIN sellers s ON s.id = rd.seller_id
                 LEFT JOIN users u ON u.id = rd.reported_by
                 WHERE rd.damage_number = ?
                 LIMIT 1",
                [$code]
            );
            if (!$dmgLog) {
                $dmgLog = Database::fetchOne(
                    "SELECT wd.*, u.full_name as reporter_name
                     FROM warehouse_damages wd
                     LEFT JOIN users u ON u.id = wd.reported_by
                     WHERE wd.damage_number = ?
                     LIMIT 1",
                    [$code]
                );
            }
            if ($dmgLog) {
                $matched = true;
                $itemType = 'Defect / Damage Record';
                $dmgNum = $dmgLog['damage_number'];
                $itemTitle = "Damage Record {$dmgNum}";
                $details = [
                    'Damage Record #' => $dmgNum,
                    'Reason' => $dmgLog['damage_reason'],
                    'Loss Valuation' => '₹ ' . number_format((float)($dmgLog['loss_amount'] ?? $dmgLog['total_loss'] ?? 0), 2),
                    'Reported By' => $dmgLog['reporter_name'] ?: 'Staff',
                    'Status' => $dmgLog['status'] ?: 'LOGGED',
                ];
                $timeline[] = [
                    'stage' => 'DAMAGE',
                    'title' => 'Damage Logged',
                    'timestamp' => $dmgLog['created_at'],
                    'badge' => $dmgLog['status'] ?: 'LOGGED',
                    'badge_color' => 'danger',
                    'icon' => 'bi-exclamation-octagon-fill',
                    'location' => 'Defect Quarantine Floor',
                    'description' => "Defect: {$dmgLog['damage_reason']}. Quarantined from active stock.",
                ];
                $currentStatus = $dmgLog['status'] ?: 'LOGGED';
                $statusColor = 'danger';
                $currentLocation = 'Defect Quarantine Area';
            }
        }

        // =========================================================================
        // 8. BILLING / SALES INVOICE (e.g. PB-2026-000001)
        // =========================================================================
        if (!$matched) {
            $billRec = Database::fetchOne(
                "SELECT pb.*
                 FROM production_bills pb
                 WHERE pb.bill_number = ?
                 LIMIT 1",
                [$code]
            );
            if ($billRec) {
                $matched = true;
                $itemType = 'Sales Invoice';
                $cust = $billRec['customer_name'] ?: $billRec['company_name'] ?: 'Customer';
                $itemTitle = "Invoice {$billRec['bill_number']} ({$cust})";
                $details = [
                    'Invoice #' => $billRec['bill_number'],
                    'Customer' => $cust,
                    'Invoice Date' => date('d M Y', strtotime($billRec['bill_date'] ?: $billRec['created_at'])),
                    'Total Amount' => '₹ ' . number_format((float)$billRec['total_amount'], 2),
                    'Payment Status' => $billRec['payment_status'] ?: 'PAID',
                ];
                $timeline[] = [
                    'stage' => 'BILLED',
                    'title' => 'Invoice Issued & Paid',
                    'timestamp' => $billRec['created_at'],
                    'badge' => 'COMPLETED',
                    'badge_color' => 'success',
                    'icon' => 'bi-receipt',
                    'location' => 'Customer Handover',
                    'description' => "Invoiced to {$cust}. Total: ₹" . number_format((float)$billRec['total_amount'], 2),
                ];
                $currentStatus = 'SOLD';
                $statusColor = 'dark';
                $currentLocation = 'Dispatched to Customer (' . $cust . ')';
            }
        }

        // =========================================================================
        // 9. PHYSICAL ASSET / ENTERPRISE EQUIPMENT (e.g. AST-PRD-..., AST-WHS-...)
        // =========================================================================
        if (!$matched) {
            try {
                if (!class_exists('AssetService') && defined('BASE_PATH') && file_exists(BASE_PATH . '/app/Services/AssetService.php')) {
                    require_once BASE_PATH . '/app/Services/AssetService.php';
                }

                $asset = null;
                if (class_exists('AssetService')) {
                    $asset = AssetService::findByCodeOrQR($code);
                }

                if (!$asset) {
                    $rawAsset = Database::fetchOne(
                        "SELECT a.*, c.name as category_name, c.code as category_code
                         FROM assets a
                         LEFT JOIN asset_categories c ON c.id = a.category_id
                         WHERE a.asset_code = ? OR a.barcode_qr = ? OR a.serial_number = ?
                         LIMIT 1",
                        [$code, $code, $code]
                    );
                    if ($rawAsset && class_exists('AssetService')) {
                        $asset = AssetService::getAssetById((int)$rawAsset['id']);
                    } else {
                        $asset = $rawAsset;
                    }
                }

                if ($asset) {
                    $matched = true;
                    $itemType = 'Enterprise Equipment / Asset';
                    $catName = $asset['category_name'] ?? 'Equipment & Machinery';
                    $itemTitle = "Asset {$asset['asset_code']} — {$asset['name']}";

                    // Resolve human-readable facility/location
                    $locDisplay = $asset['location_display'] ?? null;
                    if (!$locDisplay && class_exists('AssetService')) {
                        $locDisplay = AssetService::resolveLocationName(
                            $asset['location_type'] ?? 'PRODUCTION', 
                            (int)($asset['location_id'] ?? 1)
                        );
                    }
                    if (!$locDisplay) {
                        $locDisplay = $asset['floor_area'] ?: 'Facility Equipment Store';
                    }

                    // Map status color
                    $statusColorMap = [
                        'IN_USE'         => 'success',
                        'IDLE'           => 'secondary',
                        'IN_MAINTENANCE' => 'warning',
                        'DAMAGED'        => 'danger',
                        'DISPOSED'       => 'dark',
                        'WRITTEN_OFF'    => 'dark',
                    ];
                    $currentStatus = $asset['status'] ?? 'IN_USE';
                    $statusColor = $statusColorMap[$currentStatus] ?? 'primary';
                    $currentLocation = $locDisplay . (!empty($asset['floor_area']) ? " ({$asset['floor_area']})" : '');

                    $details = [
                        'Asset Code'       => $asset['asset_code'],
                        'Asset Name'       => $asset['name'],
                        'Category'         => $catName,
                        'Allocated Unit'   => $locDisplay,
                        'Floor / Bay'      => $asset['floor_area'] ?: 'Unassigned Floor',
                        'Condition'        => $asset['condition_state'] ?? 'GOOD',
                        'Current Book Val' => '₹ ' . number_format((float)($asset['current_book_value'] ?? 0), 2),
                        'Original Cost'    => '₹ ' . number_format((float)($asset['purchase_cost'] ?? 0), 2),
                        'Brand / Model'    => trim(($asset['brand'] ?? '') . ' ' . ($asset['model_number'] ?? '')) ?: 'N/A',
                        'Serial Number'    => $asset['serial_number'] ?: 'N/A',
                        'QR / Barcode'     => $asset['barcode_qr'] ?? $asset['asset_code'],
                        'Custodian'        => $asset['custodian_name'] ?: 'Unit Operations In-Charge',
                    ];

                    // Timeline Step 1: Asset Registration
                    $timeline[] = [
                        'stage'       => 'ASSET_REGISTER',
                        'title'       => 'Asset Registered in System',
                        'timestamp'   => $asset['created_at'],
                        'badge'       => 'REGISTERED',
                        'badge_color' => 'primary',
                        'icon'        => 'bi-cpu',
                        'location'    => $locDisplay,
                        'description' => "Asset '{$asset['name']}' ({$asset['asset_code']}) registered under {$catName}. Original Cost: ₹" . number_format((float)($asset['purchase_cost'] ?? 0), 2) . (!empty($asset['created_by_name']) ? " by {$asset['created_by_name']}." : '.'),
                    ];

                    // Timeline Step 2: Inter-Unit Allocation / Transfer History
                    $transfers = $asset['transfers'] ?? [];
                    if (empty($transfers) && !empty($asset['id'])) {
                        $transfers = Database::fetchAll("
                            SELECT t.*, u.full_name as transferred_by_name
                            FROM asset_transfers t
                            LEFT JOIN users u ON t.transferred_by = u.id
                            WHERE t.asset_id = ?
                            ORDER BY t.transfer_date ASC
                        ", [(int)$asset['id']]);
                        foreach ($transfers as &$tRef) {
                            $tRef['from_display'] = class_exists('AssetService') 
                                ? AssetService::resolveLocationName($tRef['from_location_type'], (int)$tRef['from_location_id']) 
                                : 'Previous Facility';
                            $tRef['to_display']   = class_exists('AssetService') 
                                ? AssetService::resolveLocationName($tRef['to_location_type'], (int)$tRef['to_location_id']) 
                                : 'Destination Facility';
                        }
                    }

                    foreach ($transfers as $trf) {
                        $fromUnit = $trf['from_display'] ?? 'Previous Location';
                        $toUnit = $trf['to_display'] ?? 'Destination Location';
                        $timeline[] = [
                            'stage'       => 'ASSET_TRANSFER',
                            'title'       => 'Inter-Unit Allocation: ' . ($trf['transfer_number'] ?? 'TRF'),
                            'timestamp'   => $trf['transfer_date'] ?: $trf['created_at'],
                            'badge'       => $trf['status'] ?? 'COMPLETED',
                            'badge_color' => ($trf['status'] === 'COMPLETED') ? 'info' : 'warning',
                            'icon'        => 'bi-arrow-left-right',
                            'location'    => $toUnit . (!empty($trf['to_floor_area']) ? " ({$trf['to_floor_area']})" : ''),
                            'description' => "Allocated from {$fromUnit} to {$toUnit}. Reason: " . ($trf['reason'] ?: 'Internal operational allocation') . (!empty($trf['challan_notes']) ? " | Challan: {$trf['challan_notes']}" : ''),
                        ];
                    }

                    // Timeline Step 3: Maintenance & Service Logs
                    $maintenanceLogs = $asset['maintenance_logs'] ?? [];
                    if (empty($maintenanceLogs) && !empty($asset['id'])) {
                        $maintenanceLogs = Database::fetchAll("
                            SELECT m.*, u.full_name as logged_by_name
                            FROM asset_maintenance_logs m
                            LEFT JOIN users u ON m.created_by = u.id
                            WHERE m.asset_id = ?
                            ORDER BY m.service_date ASC
                        ", [(int)$asset['id']]);
                    }

                    foreach ($maintenanceLogs as $mLog) {
                        $st = str_replace('_', ' ', $mLog['service_type'] ?? 'MAINTENANCE');
                        $timeline[] = [
                            'stage'       => 'ASSET_MAINTENANCE',
                            'title'       => 'Maintenance Log: ' . ($mLog['log_number'] ?? 'LOG') . " ({$st})",
                            'timestamp'   => ($mLog['service_date'] ?? date('Y-m-d')) . ' 10:00:00',
                            'badge'       => $mLog['status'] ?? 'COMPLETED',
                            'badge_color' => ($mLog['status'] === 'COMPLETED') ? 'success' : 'danger',
                            'icon'        => 'bi-wrench-adjustable',
                            'location'    => $locDisplay,
                            'description' => "Service: {$st}. Issue: " . ($mLog['issue_description'] ?: 'Routine service') . ". Action: " . ($mLog['action_taken'] ?: 'Inspected') . ". Cost: ₹" . number_format((float)($mLog['service_cost'] ?? 0), 2) . (!empty($mLog['serviced_by_vendor']) ? " by {$mLog['serviced_by_vendor']}" : ''),
                        ];
                    }
                }
            } catch (Throwable $e) {
                error_log("QrFinderService asset trace error: " . $e->getMessage());
            }
        }

        if (!$matched) {
            return [
                'success' => false,
                'message' => "This QR code ({$code}) is not in any inventory sections.",
            ];
        }

        // Sort timeline chronologically
        usort($timeline, static function(array $a, array $b): int {
            return strtotime($a['timestamp'] ?? '') <=> strtotime($b['timestamp'] ?? '');
        });

        return [
            'success' => true,
            'code' => $code,
            'item_title' => $itemTitle,
            'item_type' => $itemType,
            'current_status' => $currentStatus,
            'status_color' => $statusColor,
            'current_location' => $currentLocation,
            'details' => $details,
            'timeline' => $timeline,
            'timeline_count' => count($timeline),
        ];
    } catch (Throwable $e) {
        error_log("QrFinderService trace error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "This QR code ({$code}) is not in any inventory sections.",
            'code' => $code,
        ];
    }
}
}
