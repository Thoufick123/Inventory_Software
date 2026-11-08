<?php

namespace App\Services;

use Database;

class ProductionReportService
{
    /**
     * Fetch complete dataset for the given date range and selected modules,
     * including executive financial P&L and operational stock reconciliation metrics.
     */
    public static function getReportData(string $startDate, string $endDate, array $selectedModules): array
    {
        $data = [];

        // 0. Supplier Purchases (POs & Invoices)
        if (in_array('purchases', $selectedModules, true)) {
            $data['purchases'] = Database::fetchAll(
                "SELECT pp.id, pp.purchase_number, pp.purchase_date, pp.invoice_number,
                        s.name AS supplier_name, s.company AS supplier_company,
                        pp.total_amount, pp.notes, pp.created_at,
                        COALESCE(items_stat.total_items, 0) AS total_items,
                        COALESCE(items_stat.total_rolls, 0) AS total_rolls,
                        COALESCE(items_stat.total_weight, 0) AS total_weight
                 FROM production_purchases pp
                 LEFT JOIN sellers s ON s.id = pp.seller_id
                 LEFT JOIN (
                     SELECT purchase_id,
                            COUNT(*) AS total_items,
                            SUM(CASE WHEN item_type = 'FABRIC_ROLL' THEN 1 ELSE 0 END) AS total_rolls,
                            SUM(COALESCE(weight_kg, 0)) AS total_weight
                     FROM production_purchase_items
                     GROUP BY purchase_id
                 ) items_stat ON items_stat.purchase_id = pp.id
                 WHERE DATE(pp.purchase_date) BETWEEN ? AND ?
                 ORDER BY pp.id DESC",
                [$startDate, $endDate]
            );
        }

        // 1. Rolls & Inwards (from seller_rolls)
        if (in_array('rolls', $selectedModules, true)) {
            $data['rolls'] = Database::fetchAll(
                "SELECT sr.roll_number, sr.created_at, s.name AS supplier_name, s.company AS supplier_company,
                        sr.material_type AS fabric_type, sr.color, COALESCE(sr.weight, 0) AS weight,
                        COALESCE(sr.price, 0) AS total_cost, 'IN_STOCK' AS status,
                        sr.start_code, sr.end_code, sr.qr_code,
                        '-' AS length,
                        0.00 AS cost_per_meter,
                        ROUND(COALESCE(sr.price, 0) / NULLIF(sr.weight, 0), 2) AS cost_per_kg
                 FROM seller_rolls sr
                 LEFT JOIN sellers s ON s.id = sr.seller_id
                 WHERE DATE(sr.created_at) BETWEEN ? AND ?
                 ORDER BY sr.id DESC",
                [$startDate, $endDate]
            );
        }

        // 2. Production Programs
        if (in_array('programs', $selectedModules, true)) {
            $data['programs'] = Database::fetchAll(
                "SELECT pp.program_number, pp.name AS program_name, p.code AS style_code,
                        pp.planned_quantity AS target_pieces, pp.completed_quantity, pp.status,
                        pp.created_at, pp.target_date
                 FROM production_programs pp
                 LEFT JOIN products p ON p.id = pp.product_id
                 WHERE DATE(pp.created_at) BETWEEN ? AND ?
                 ORDER BY pp.id DESC",
                [$startDate, $endDate]
            );
        }

        // 3. Cutting Batches
        if (in_array('cuttings', $selectedModules, true)) {
            $data['cuttings'] = Database::fetchAll(
                "SELECT cb.batch_number, pp.program_number,
                        cb.roll_weight, cb.planned_qty, cb.cut_qty AS actual_pieces,
                        cb.wastage_qty, cb.rejected_qty, cb.status, cb.created_at
                 FROM cutting_batches cb
                 LEFT JOIN production_programs pp ON pp.id = cb.program_id
                 WHERE DATE(cb.created_at) BETWEEN ? AND ?
                 ORDER BY cb.id DESC",
                [$startDate, $endDate]
            );
        }

        // 4. Stitching Batches
        if (in_array('stitching', $selectedModules, true)) {
            $data['stitching'] = Database::fetchAll(
                "SELECT sb.batch_number, pp.program_number, sb.received_qty AS target_quantity,
                        sb.stitched_qty AS completed_quantity, sb.rework_qty AS rework_quantity,
                        sb.rejected_qty, sb.status, sb.created_at
                 FROM stitching_batches sb
                 LEFT JOIN production_programs pp ON pp.id = sb.program_id
                 WHERE DATE(sb.created_at) BETWEEN ? AND ?
                 ORDER BY sb.id DESC",
                [$startDate, $endDate]
            );
        }

        // 5. Quality Control (QC)
        if (in_array('qc', $selectedModules, true)) {
            $data['qc'] = Database::fetchAll(
                "SELECT qb.batch_number, qb.qc_number, pp.program_number, qb.total_qty AS inspected_quantity,
                        qb.passed_qty AS passed_quantity, qb.rework_qty AS rework_quantity, qb.rejected_qty AS rejected_quantity,
                        CASE WHEN qb.total_qty > 0 
                             THEN ROUND((qb.passed_qty / qb.total_qty) * 100, 2) 
                             ELSE 0 END AS yield_percentage,
                        qb.status, qb.created_at
                 FROM qc_batches qb
                 LEFT JOIN production_programs pp ON pp.id = qb.program_id
                 WHERE DATE(qb.created_at) BETWEEN ? AND ?
                 ORDER BY qb.id DESC",
                [$startDate, $endDate]
            );
        }

        // 6. Finished Goods Batches (Master Cartons)
        if (in_array('batches', $selectedModules, true)) {
            $data['batches'] = Database::fetchAll(
                "SELECT pb.id, pb.batch_number, pp.program_number, pp.name AS program_name,
                        qb.batch_number AS qc_batch_number,
                        COALESCE(pb.box_count, 0) AS box_count,
                        COALESCE(CASE WHEN pb.total_qty > 0 THEN pb.total_qty ELSE pb.quantity END, 0) AS total_pieces,
                        COALESCE(pb.cost_per_piece, 0.00) AS cost_per_piece,
                        COALESCE(pb.total_cost, 0.00) AS total_cost,
                        COALESCE(w.name, 'Central Distribution Warehouse') AS destination_warehouse,
                        pb.status,
                        COALESCE(pb.packed_date, pb.created_at) AS packed_date,
                        pb.created_at
                 FROM production_batches pb
                 LEFT JOIN production_programs pp ON pp.id = pb.program_id
                 LEFT JOIN qc_batches qb ON qb.id = pb.qc_batch_id
                 LEFT JOIN warehouses w ON w.id = pb.destination_warehouse_id
                 WHERE DATE(COALESCE(pb.packed_date, pb.created_at)) BETWEEN ? AND ?
                 ORDER BY pb.id DESC",
                [$startDate, $endDate]
            );
        }

        // 7. Production Outward & Dispatches
        if (in_array('outward', $selectedModules, true)) {
            $data['outward'] = Database::fetchAll(
                "SELECT po.id, po.outward_number, pb.batch_number,
                        COALESCE(w.name, 'Central Distribution Warehouse') AS warehouse_name,
                        COALESCE(po.total_boxes, 0) AS total_boxes,
                        COALESCE(CASE WHEN po.total_quantity > 0 THEN po.total_quantity ELSE po.quantity END, 0) AS total_pieces,
                        COALESCE(po.transporter_name, '-') AS transporter_name,
                        COALESCE(po.vehicle_number, '-') AS vehicle_number,
                        COALESCE(po.lr_number, '-') AS lr_number,
                        COALESCE(po.driver_name, '-') AS driver_name,
                        COALESCE(po.driver_contact, '-') AS driver_contact,
                        po.status,
                        COALESCE(po.dispatch_date, po.created_at) AS dispatch_date,
                        po.created_at
                 FROM production_outwards po
                 LEFT JOIN production_batches pb ON pb.id = po.production_batch_id
                 LEFT JOIN warehouses w ON w.id = po.warehouse_id
                 WHERE DATE(COALESCE(po.dispatch_date, po.created_at)) BETWEEN ? AND ?
                 ORDER BY po.id DESC",
                [$startDate, $endDate]
            );
        }

        // 8. Production Billing
        if (in_array('billing', $selectedModules, true)) {
            $data['billing'] = Database::fetchAll(
                "SELECT pb.bill_number, pb.bill_date, pb.buyer_type,
                        COALESCE(pb.customer_name, pb.company_name, 'Direct Client') AS client_name,
                        pb.customer_phone, pb.subtotal, pb.tax_amount, pb.total_amount,
                        pb.payment_method, pb.payment_status,
                        COALESCE((SELECT COUNT(*) FROM production_bill_item_tags WHERE bill_id = pb.id), 0) AS total_pieces
                 FROM production_bills pb
                 WHERE DATE(pb.bill_date) BETWEEN ? AND ?
                 ORDER BY pb.id DESC",
                [$startDate, $endDate]
            );
        }

        // 9. Customer Returns & Credit Notes
        if (in_array('returns', $selectedModules, true)) {
            $data['returns'] = Database::fetchAll(
                "SELECT pr.return_number, pr.bill_number, pr.customer_name,
                        pr.return_type, pr.disposition, pr.total_returned_qty,
                        pr.subtotal, pr.gst_reversal_amount, pr.net_amount,
                        pr.refund_mode, pr.status, pr.created_at
                 FROM production_returns pr
                 WHERE DATE(pr.created_at) BETWEEN ? AND ?
                 ORDER BY pr.id DESC",
                [$startDate, $endDate]
            );
        }

        // 10. Warehouse Stock Requests
        if (in_array('stock_requests', $selectedModules, true)) {
            $data['stock_requests'] = Database::fetchAll(
                "SELECT psr.request_number, psr.warehouse_name, psr.requested_by_name,
                        psr.priority, psr.required_by_date, psr.total_requested_pieces,
                        psr.fulfilled_pieces, psr.status, psr.created_at
                 FROM production_stock_requests psr
                 WHERE DATE(psr.created_at) BETWEEN ? AND ?
                 ORDER BY psr.id DESC",
                [$startDate, $endDate]
            );
        }

        // 11. Wastage Selling (Cutting + Stitching Scrap)
        if (in_array('wastage', $selectedModules, true)) {
            $cuttingWastage = Database::fetchAll(
                "SELECT ws.sale_number AS invoice_number, 'Cutting Scrap' AS wastage_type, ws.buyer_name,
                        ws.weight AS weight_kg, ws.rate_per_kg, ws.total_amount, ws.sale_date AS created_at
                 FROM wastage_sales ws
                 WHERE DATE(ws.sale_date) BETWEEN ? AND ?
                 ORDER BY ws.id DESC",
                [$startDate, $endDate]
            );

            $stitchingWastage = Database::fetchAll(
                "SELECT sws.sale_number AS invoice_number, 'Stitching Scrap' AS wastage_type, sws.buyer_name,
                        sws.quantity AS weight_kg, sws.rate_per_unit AS rate_per_kg, sws.total_amount, sws.sale_date AS created_at
                 FROM stitching_wastage_sales sws
                 WHERE DATE(sws.sale_date) BETWEEN ? AND ?
                 ORDER BY sws.id DESC",
                [$startDate, $endDate]
            );

            $data['wastage'] = array_merge($cuttingWastage, $stitchingWastage);
        }

        // 12. Expenses & Assets
        if (in_array('expenses', $selectedModules, true)) {
            $data['expenses'] = Database::fetchAll(
                "SELECT pe.item_name AS title, pe.category,
                        COALESCE(NULLIF(pe.production_unit, ''), (SELECT name FROM production_units WHERE is_active = 1 ORDER BY id ASC LIMIT 1), 'Production Facility') AS production_unit,
                        pe.total_cost, pe.paid_amount, (pe.total_cost - pe.paid_amount) AS pending_amount,
                        pe.payment_status, pe.expense_date
                 FROM production_expenses pe
                 WHERE DATE(pe.expense_date) BETWEEN ? AND ?
                 ORDER BY pe.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // EXECUTIVE FINANCIAL & OPERATIONAL STOCK RECONCILIATION SUMMARY
        // =========================================================================
        // Billing Revenue
        $billStat = Database::fetchOne(
            "SELECT COUNT(*) AS bill_count,
                    COALESCE(SUM(subtotal), 0) AS taxable_amt,
                    COALESCE(SUM(tax_amount), 0) AS gst_amt,
                    COALESCE(SUM(total_amount), 0) AS total_revenue
             FROM production_bills
             WHERE DATE(bill_date) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $billPcs = Database::fetchOne(
            "SELECT COUNT(*) AS total_pcs
             FROM production_bill_item_tags pbit
             JOIN production_bills pb ON pb.id = pbit.bill_id
             WHERE DATE(pb.bill_date) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $billingPieces = (int)($billPcs['total_pcs'] ?? 0);
        $billingTotal = (float)($billStat['total_revenue'] ?? 0);
        $billingTaxable = (float)($billStat['taxable_amt'] ?? 0);
        $billingGst = (float)($billStat['gst_amt'] ?? 0);
        $billingCount = (int)($billStat['bill_count'] ?? 0);

        // Scrap / Wastage Revenue
        $cWastage = Database::fetchOne(
            "SELECT COALESCE(SUM(weight), 0) AS weight, COALESCE(SUM(total_amount), 0) AS rev
             FROM wastage_sales
             WHERE DATE(sale_date) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $sWastage = Database::fetchOne(
            "SELECT COALESCE(SUM(quantity), 0) AS weight, COALESCE(SUM(total_amount), 0) AS rev
             FROM stitching_wastage_sales
             WHERE DATE(sale_date) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $wastageCuttingRev = (float)($cWastage['rev'] ?? 0);
        $wastageStitchingRev = (float)($sWastage['rev'] ?? 0);
        $wastageTotalRev = $wastageCuttingRev + $wastageStitchingRev;

        // Total Gross Revenue / Inflows
        $grossRevenue = $billingTotal + $wastageTotalRev;

        // Expenditures / Outflows: Purchases
        $purStat = Database::fetchOne(
            "SELECT COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS total_cost
             FROM production_purchases
             WHERE DATE(purchase_date) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $purchasesCost = (float)($purStat['total_cost'] ?? 0);
        $purchasesCount = (int)($purStat['count'] ?? 0);

        // Expenditures: Factory Operating & Machinery Expenses
        $expStat = Database::fetchOne(
            "SELECT COUNT(*) AS count,
                    COALESCE(SUM(total_cost), 0) AS total_cost,
                    COALESCE(SUM(paid_amount), 0) AS paid_amount
             FROM production_expenses
             WHERE DATE(expense_date) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $expensesCost = (float)($expStat['total_cost'] ?? 0);
        $expensesPaid = (float)($expStat['paid_amount'] ?? 0);
        $expensesPending = max(0.00, $expensesCost - $expensesPaid);
        $expensesCount = (int)($expStat['count'] ?? 0);

        // Expenditures: Customer Returns Reversal / Refunds
        $retStat = Database::fetchOne(
            "SELECT COUNT(*) AS count,
                    COALESCE(SUM(total_returned_qty), 0) AS returned_pcs,
                    COALESCE(SUM(subtotal), 0) AS taxable_reversal,
                    COALESCE(SUM(gst_reversal_amount), 0) AS gst_reversal,
                    COALESCE(SUM(net_amount), 0) AS net_refund
             FROM production_returns
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $returnsRefund = (float)($retStat['net_refund'] ?? 0);
        $returnsCount = (int)($retStat['count'] ?? 0);
        $returnsPieces = (int)($retStat['returned_pcs'] ?? 0);

        // Total Outflow / Cost
        $totalExpenditure = $purchasesCost + $expensesCost + $returnsRefund;
        $netProfitLoss = $grossRevenue - $totalExpenditure;
        $profitMarginPct = ($grossRevenue > 0) ? round(($netProfitLoss / $grossRevenue) * 100, 2) : 0.0;

        // Operational Stock Pipeline Metrics
        $rollsStat = Database::fetchOne(
            "SELECT COUNT(*) as rolls_count, COALESCE(SUM(weight), 0) as weight_kg, COALESCE(SUM(price), 0) as total_value
             FROM seller_rolls
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $cutStat = Database::fetchOne(
            "SELECT COUNT(*) as batches_count, COALESCE(SUM(cut_qty), 0) as cut_pieces, COALESCE(SUM(wastage_qty), 0) as wastage_kg
             FROM cutting_batches
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $stitchStat = Database::fetchOne(
            "SELECT COUNT(*) as batches_count, COALESCE(SUM(stitched_qty), 0) as stitched_pieces,
                    COALESCE(SUM(rework_qty), 0) as rework_pieces, COALESCE(SUM(rejected_qty), 0) as rejected_pieces
             FROM stitching_batches
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $qcStat = Database::fetchOne(
            "SELECT COUNT(*) as batches_count, COALESCE(SUM(total_qty), 0) as inspected_pieces,
                    COALESCE(SUM(passed_qty), 0) as passed_pieces, COALESCE(SUM(rework_qty), 0) as rework_pieces,
                    COALESCE(SUM(rejected_qty), 0) as rejected_pieces
             FROM qc_batches
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $batchStat = Database::fetchOne(
            "SELECT COUNT(*) as batches_count, COALESCE(SUM(box_count), 0) as box_count,
                    COALESCE(SUM(CASE WHEN total_qty > 0 THEN total_qty ELSE quantity END), 0) as packed_pieces
             FROM production_batches
             WHERE DATE(COALESCE(packed_date, created_at)) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $outwardStat = Database::fetchOne(
            "SELECT COUNT(*) as outward_count, COALESCE(SUM(total_boxes), 0) as total_boxes,
                    COALESCE(SUM(CASE WHEN total_quantity > 0 THEN total_quantity ELSE quantity END), 0) as dispatched_pieces
             FROM production_outwards
             WHERE DATE(COALESCE(dispatch_date, created_at)) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );
        $stockReqStat = Database::fetchOne(
            "SELECT COUNT(*) as req_count, COALESCE(SUM(total_requested_pieces), 0) as requested_pieces,
                    COALESCE(SUM(fulfilled_pieces), 0) as fulfilled_pieces
             FROM production_stock_requests
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$startDate, $endDate]
        );

        $qcInspected = (int)($qcStat['inspected_pieces'] ?? 0);
        $qcPassed = (int)($qcStat['passed_pieces'] ?? 0);
        $qcYieldPct = ($qcInspected > 0) ? round(($qcPassed / $qcInspected) * 100, 2) : 0.0;
        $packedPcs = (int)($batchStat['packed_pieces'] ?? 0);
        $dispatchedPcs = (int)($outwardStat['dispatched_pieces'] ?? 0);
        $onHandEstimated = max(0, $packedPcs - $dispatchedPcs - $billingPieces);

        $data['summary'] = [
            'financial' => [
                'billing_count'      => $billingCount,
                'billing_pieces'     => $billingPieces,
                'billing_taxable'    => $billingTaxable,
                'billing_gst'        => $billingGst,
                'billing_total'      => $billingTotal,
                'wastage_cutting'    => $wastageCuttingRev,
                'wastage_stitching'  => $wastageStitchingRev,
                'wastage_total'      => $wastageTotalRev,
                'gross_revenue'      => $grossRevenue,
                'purchases_count'    => $purchasesCount,
                'purchases_total'    => $purchasesCost,
                'expenses_count'     => $expensesCount,
                'expenses_total'     => $expensesCost,
                'expenses_paid'      => $expensesPaid,
                'expenses_pending'   => $expensesPending,
                'returns_count'      => $returnsCount,
                'returns_pieces'     => $returnsPieces,
                'returns_total'      => $returnsRefund,
                'total_expenditure'  => $totalExpenditure,
                'net_profit_loss'    => $netProfitLoss,
                'profit_margin_pct'  => $profitMarginPct,
            ],
            'stock' => [
                'inward_rolls_count'     => (int)($rollsStat['rolls_count'] ?? 0),
                'inward_weight_kg'       => (float)($rollsStat['weight_kg'] ?? 0),
                'inward_value'           => (float)($rollsStat['total_value'] ?? 0),
                'cuttings_batches_count' => (int)($cutStat['batches_count'] ?? 0),
                'cuttings_pieces'        => (int)($cutStat['cut_pieces'] ?? 0),
                'cuttings_wastage_kg'    => (float)($cutStat['wastage_kg'] ?? 0),
                'stitching_batches_count'=> (int)($stitchStat['batches_count'] ?? 0),
                'stitching_pieces'       => (int)($stitchStat['stitched_pieces'] ?? 0),
                'stitching_rework'       => (int)($stitchStat['rework_pieces'] ?? 0),
                'stitching_rejected'     => (int)($stitchStat['rejected_pieces'] ?? 0),
                'qc_batches_count'       => (int)($qcStat['batches_count'] ?? 0),
                'qc_inspected_pieces'    => $qcInspected,
                'qc_passed_pieces'       => $qcPassed,
                'qc_rework_pieces'       => (int)($qcStat['rework_pieces'] ?? 0),
                'qc_rejected_pieces'     => (int)($qcStat['rejected_pieces'] ?? 0),
                'qc_yield_pct'           => $qcYieldPct,
                'batches_count'          => (int)($batchStat['batches_count'] ?? 0),
                'batches_boxes'          => (int)($batchStat['box_count'] ?? 0),
                'batches_pieces'         => $packedPcs,
                'outward_count'          => (int)($outwardStat['outward_count'] ?? 0),
                'outward_boxes'          => (int)($outwardStat['total_boxes'] ?? 0),
                'outward_pieces'         => $dispatchedPcs,
                'billing_pieces'         => $billingPieces,
                'stock_on_hand_pieces'   => $onHandEstimated,
                'stock_req_count'        => (int)($stockReqStat['req_count'] ?? 0),
                'stock_req_pieces'       => (int)($stockReqStat['requested_pieces'] ?? 0),
                'stock_req_fulfilled'    => (int)($stockReqStat['fulfilled_pieces'] ?? 0),
            ]
        ];

        return $data;
    }

    /**
     * Generate and stream CSV export directly to browser output.
     * Formats all numbers with pure decimal notation (no comma thousands separators)
     * so Microsoft Excel interprets all values natively as numeric data.
     */
    public static function streamCsv(string $periodLabel, string $startDate, string $endDate, array $data, ?array $facility = null): void
    {
        $facility = $facility ?? (class_exists('ProductionUnit') ? \ProductionUnit::getActiveFacility() : []);
        $facilityName = !empty($facility['name']) ? strtoupper($facility['name']) : 'PRODUCTION MANUFACTURING FACILITY';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="production_report_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel recognition
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        // 1. Facility Header
        fputcsv($out, [$facilityName . ' — COMPREHENSIVE PRODUCTION INTELLIGENCE REPORT']);
        if (!empty($facility['address'])) {
            fputcsv($out, ['Facility Address:', str_replace(["\r\n", "\n", "\r"], ' ', (string)$facility['address'])]);
        }
        fputcsv($out, [
            'Unit Code:', $facility['code'] ?? 'PU-01',
            'Phone / Contact:', $facility['phone'] ?? '-',
            'GSTIN:', $facility['gst_number'] ?? '-'
        ]);
        fputcsv($out, ['Report Period:', $periodLabel, "From: {$startDate}", "To: {$endDate}"]);
        fputcsv($out, ['Generated Timestamp:', date('Y-m-d H:i:s') . ' IST']);
        fputcsv($out, []); // Blank separator line

        // 2. Executive Financial Summary (Income & Loss)
        if (!empty($data['summary']['financial'])) {
            $fin = $data['summary']['financial'];
            fputcsv($out, ['=== EXECUTIVE FINANCIAL PERFORMANCE (REVENUE, COSTS & PROFIT / LOSS) ===']);
            fputcsv($out, ['Financial Metric', 'Value (INR)', 'Details / Component Breakdown']);
            fputcsv($out, ['Direct Factory Billing Sales', number_format($fin['billing_total'], 2, '.', ''), "Taxable: " . number_format($fin['billing_taxable'], 2, '.', '') . " + GST: " . number_format($fin['billing_gst'], 2, '.', '') . " ({$fin['billing_count']} bills, {$fin['billing_pieces']} pcs)"]);
            fputcsv($out, ['Cutting & Stitching Scrap Sales', number_format($fin['wastage_total'], 2, '.', ''), "Cutting Scrap: " . number_format($fin['wastage_cutting'], 2, '.', '') . " + Stitching Scrap: " . number_format($fin['wastage_stitching'], 2, '.', '')]);
            fputcsv($out, ['TOTAL GROSS REVENUE (INFLOW)', number_format($fin['gross_revenue'], 2, '.', ''), 'All Sales Revenue Inflow for the Period']);
            fputcsv($out, ['Fabric & Material Purchases', number_format($fin['purchases_total'], 2, '.', ''), "{$fin['purchases_count']} Material Purchase Orders"]);
            fputcsv($out, ['Factory Operating & Asset Expenses', number_format($fin['expenses_total'], 2, '.', ''), "Paid: " . number_format($fin['expenses_paid'], 2, '.', '') . " / Pending: " . number_format($fin['expenses_pending'], 2, '.', '')]);
            fputcsv($out, ['Customer Returns Reversal / Refunds', number_format($fin['returns_total'], 2, '.', ''), "{$fin['returns_count']} Returns ({$fin['returns_pieces']} pcs returned)"]);
            fputcsv($out, ['TOTAL OPERATING EXPENDITURES (OUTFLOW)', number_format($fin['total_expenditure'], 2, '.', ''), 'Purchases + Operating Expenses + Return Refunds']);
            fputcsv($out, ['NET PROFIT / LOSS', number_format($fin['net_profit_loss'], 2, '.', ''), ($fin['net_profit_loss'] >= 0 ? 'NET OPERATING PROFIT' : 'NET OPERATING LOSS')]);
            fputcsv($out, ['OPERATING MARGIN (%)', number_format($fin['profit_margin_pct'], 2, '.', '') . '%', 'Net Profit Margin on Gross Inflow']);
            fputcsv($out, []);
        }

        // 3. Executive Stock Pipeline & Production Volume
        if (!empty($data['summary']['stock'])) {
            $stk = $data['summary']['stock'];
            fputcsv($out, ['=== PRODUCTION PIPELINE & STOCK INVENTORY RECONCILIATION ===']);
            fputcsv($out, ['Operational Stage', 'Count / Batches', 'Primary Units', 'Secondary Metric']);
            fputcsv($out, ['Fabric Rolls Inward', $stk['inward_rolls_count'] . ' Rolls', number_format($stk['inward_weight_kg'], 2, '.', '') . ' kg', 'Value: INR ' . number_format($stk['inward_value'], 2, '.', '')]);
            fputcsv($out, ['Cutting Output', $stk['cuttings_batches_count'] . ' Batches', $stk['cuttings_pieces'] . ' Cut Pcs', 'Scrap: ' . number_format($stk['cuttings_wastage_kg'], 2, '.', '') . ' kg']);
            fputcsv($out, ['Stitching Output', $stk['stitching_batches_count'] . ' Batches', $stk['stitching_pieces'] . ' Stitched Pcs', "Rework: {$stk['stitching_rework']} / Reject: {$stk['stitching_rejected']}"]);
            fputcsv($out, ['Quality Control (QC)', $stk['qc_batches_count'] . ' Batches', $stk['qc_passed_pieces'] . ' Passed Pcs', "Yield: {$stk['qc_yield_pct']}% / Inspected: {$stk['qc_inspected_pieces']}"]);
            fputcsv($out, ['Finished Goods Packed (Batches)', $stk['batches_count'] . ' Batches', $stk['batches_pieces'] . ' Pcs Packed', $stk['batches_boxes'] . ' Master Cartons']);
            fputcsv($out, ['Dispatched to Warehouses (Outward)', $stk['outward_count'] . ' Outwards', $stk['outward_pieces'] . ' Dispatched Pcs', $stk['outward_boxes'] . ' Cartons Dispatched']);
            fputcsv($out, ['Direct Factory Sales (Billed)', $fin['billing_count'] ?? 0 . ' Invoices', $stk['billing_pieces'] . ' Sold Pcs', 'Direct Billed Pieces']);
            fputcsv($out, ['Warehouse Stock Requisitions', $stk['stock_req_count'] . ' Requisitions', $stk['stock_req_fulfilled'] . ' Fulfilled Pcs', "Total Requested: {$stk['stock_req_pieces']} Pcs"]);
            fputcsv($out, ['Estimated Finished Stock On-Hand', '-', $stk['stock_on_hand_pieces'] . ' Finished Pcs', 'Packed minus Dispatched & Sold']);
            fputcsv($out, []);
        }

        // =========================================================================
        // DETAILED MODULE DATA TABLES
        // =========================================================================

        // 1. Purchases
        if (!empty($data['purchases'])) {
            fputcsv($out, ['=== 1. FABRIC & MATERIAL PURCHASES (PO ORDERS) ===']);
            fputcsv($out, ['Purchase / PO #', 'Purchase Date', 'Supplier', 'Invoice #', 'Total Items', 'Fabric Rolls Count', 'Total Weight (kg)', 'Total Value (INR)', 'Notes']);
            foreach ($data['purchases'] as $pur) {
                fputcsv($out, [
                    $pur['purchase_number'],
                    date('d M Y', strtotime($pur['purchase_date'])),
                    $pur['supplier_company'] ?: ($pur['supplier_name'] ?? 'Direct Supplier'),
                    $pur['invoice_number'] ?: '-',
                    (int)$pur['total_items'],
                    (int)$pur['total_rolls'],
                    number_format((float)$pur['total_weight'], 2, '.', ''),
                    number_format((float)$pur['total_amount'], 2, '.', ''),
                    $pur['notes'] ?? '',
                ]);
            }
            fputcsv($out, []);
        }

        // 2. Rolls & Inwards
        if (!empty($data['rolls'])) {
            fputcsv($out, ['=== 2. FABRIC ROLLS & INWARDS ===']);
            fputcsv($out, ['Roll Number', 'Inward Date', 'Supplier', 'Fabric Type', 'Color', 'Weight (kg)', 'Cost/kg (INR)', 'Total Cost (INR)', 'Status']);
            foreach ($data['rolls'] as $r) {
                fputcsv($out, [
                    $r['roll_number'],
                    !empty($r['created_at']) ? date('d M Y', strtotime($r['created_at'])) : '-',
                    $r['supplier_company'] ?: ($r['supplier_name'] ?? 'Direct Supplier'),
                    $r['fabric_type'] ?? '-',
                    $r['color'] ?? '-',
                    number_format((float)($r['weight'] ?? 0), 2, '.', ''),
                    number_format((float)($r['cost_per_kg'] ?? 0), 2, '.', ''),
                    number_format((float)($r['total_cost'] ?? 0), 2, '.', ''),
                    $r['status'] ?? 'IN_STOCK',
                ]);
            }
            fputcsv($out, []);
        }

        // 3. Programs
        if (!empty($data['programs'])) {
            fputcsv($out, ['=== 3. PRODUCTION PROGRAMS ===']);
            fputcsv($out, ['Program Number', 'Program Name', 'Style Code', 'Target Pieces', 'Completed Pieces', 'Status', 'Target Date']);
            foreach ($data['programs'] as $p) {
                fputcsv($out, [
                    $p['program_number'],
                    $p['program_name'],
                    $p['style_code'] ?? '-',
                    (int)$p['target_pieces'],
                    (int)($p['completed_quantity'] ?? 0),
                    $p['status'],
                    $p['target_date'] ?? '-',
                ]);
            }
            fputcsv($out, []);
        }

        // 4. Cuttings
        if (!empty($data['cuttings'])) {
            fputcsv($out, ['=== 4. CUTTING BATCHES ===']);
            fputcsv($out, ['Batch Number', 'Program #', 'Roll Weight (kg)', 'Planned Qty', 'Cut Pieces', 'Wastage Pcs / kg', 'Status', 'Date']);
            foreach ($data['cuttings'] as $c) {
                fputcsv($out, [
                    $c['batch_number'],
                    $c['program_number'] ?? '-',
                    number_format((float)($c['roll_weight'] ?? 0), 2, '.', ''),
                    (int)$c['planned_qty'],
                    (int)$c['actual_pieces'],
                    number_format((float)($c['wastage_qty'] ?? 0), 2, '.', ''),
                    $c['status'],
                    !empty($c['created_at']) ? date('d M Y', strtotime($c['created_at'])) : '-',
                ]);
            }
            fputcsv($out, []);
        }

        // 5. Stitching
        if (!empty($data['stitching'])) {
            fputcsv($out, ['=== 5. STITCHING OPERATIONS ===']);
            fputcsv($out, ['Batch Number', 'Program #', 'Target Pieces', 'Completed Pieces', 'Rework Pieces', 'Rejected Pcs', 'Status', 'Date']);
            foreach ($data['stitching'] as $s) {
                fputcsv($out, [
                    $s['batch_number'],
                    $s['program_number'] ?? '-',
                    (int)$s['target_quantity'],
                    (int)$s['completed_quantity'],
                    (int)$s['rework_quantity'],
                    (int)$s['rejected_qty'],
                    $s['status'],
                    !empty($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-',
                ]);
            }
            fputcsv($out, []);
        }

        // 6. Quality Control (QC)
        if (!empty($data['qc'])) {
            fputcsv($out, ['=== 6. QUALITY CONTROL (QC) INSPECTIONS ===']);
            fputcsv($out, ['QC Batch #', 'Program #', 'Inspected Qty', 'Passed Qty', 'Rework Qty', 'Rejected Qty', 'Yield %', 'Status', 'Date']);
            foreach ($data['qc'] as $q) {
                fputcsv($out, [
                    $q['batch_number'],
                    $q['program_number'] ?? '-',
                    (int)$q['inspected_quantity'],
                    (int)$q['passed_quantity'],
                    (int)$q['rework_quantity'],
                    (int)$q['rejected_quantity'],
                    number_format((float)($q['yield_percentage'] ?? 0), 2, '.', '') . '%',
                    $q['status'],
                    !empty($q['created_at']) ? date('d M Y', strtotime($q['created_at'])) : '-',
                ]);
            }
            fputcsv($out, []);
        }

        // 7. Finished Goods Batches
        if (!empty($data['batches'])) {
            fputcsv($out, ['=== 7. FINISHED GOODS BATCHES (MASTER CARTONS) ===']);
            fputcsv($out, ['Batch Number', 'Program #', 'QC Batch #', 'Destination Warehouse', 'Total Pieces', 'Master Cartons / Boxes', 'Cost/Pc (INR)', 'Total Value (INR)', 'Status', 'Packed Date']);
            foreach ($data['batches'] as $b) {
                fputcsv($out, [
                    $b['batch_number'],
                    $b['program_number'] ?? '-',
                    $b['qc_batch_number'] ?? '-',
                    $b['destination_warehouse'] ?? '-',
                    (int)$b['total_pieces'],
                    (int)$b['box_count'],
                    number_format((float)($b['cost_per_piece'] ?? 0), 2, '.', ''),
                    number_format((float)($b['total_cost'] ?? 0), 2, '.', ''),
                    $b['status'],
                    !empty($b['packed_date']) ? date('d M Y', strtotime($b['packed_date'])) : '-',
                ]);
            }
            fputcsv($out, []);
        }

        // 8. Production Outward & Dispatches
        if (!empty($data['outward'])) {
            fputcsv($out, ['=== 8. PRODUCTION OUTWARD & WAREHOUSE DISPATCHES ===']);
            fputcsv($out, ['Outward Number', 'Batch Number', 'Destination Warehouse', 'Boxes Dispatched', 'Total Pieces', 'Transporter Name', 'LR / Docket #', 'Vehicle #', 'Driver & Contact', 'Status', 'Dispatch Date']);
            foreach ($data['outward'] as $ow) {
                $driverInfo = trim(($ow['driver_name'] ?? '') . ' ' . ($ow['driver_contact'] ?? ''));
                fputcsv($out, [
                    $ow['outward_number'],
                    $ow['batch_number'] ?? '-',
                    $ow['warehouse_name'] ?? '-',
                    (int)$ow['total_boxes'],
                    (int)$ow['total_pieces'],
                    $ow['transporter_name'] ?? '-',
                    $ow['lr_number'] ?? '-',
                    $ow['vehicle_number'] ?? '-',
                    $driverInfo ?: '-',
                    $ow['status'],
                    !empty($ow['dispatch_date']) ? date('d M Y', strtotime($ow['dispatch_date'])) : '-',
                ]);
            }
            fputcsv($out, []);
        }

        // 9. Production Billing
        if (!empty($data['billing'])) {
            fputcsv($out, ['=== 9. PRODUCTION BILLING & DIRECT SALES ===']);
            fputcsv($out, ['Bill Number', 'Bill Date', 'Buyer Type', 'Customer / Company', 'Phone', 'Pieces Sold', 'Taxable (INR)', 'GST (INR)', 'Total (INR)', 'Payment Method', 'Status']);
            foreach ($data['billing'] as $b) {
                fputcsv($out, [
                    $b['bill_number'],
                    date('d M Y', strtotime($b['bill_date'])),
                    $b['buyer_type'],
                    $b['client_name'],
                    $b['customer_phone'] ?? '',
                    (int)$b['total_pieces'],
                    number_format((float)$b['subtotal'], 2, '.', ''),
                    number_format((float)$b['tax_amount'], 2, '.', ''),
                    number_format((float)$b['total_amount'], 2, '.', ''),
                    $b['payment_method'],
                    $b['payment_status'],
                ]);
            }
            fputcsv($out, []);
        }

        // 10. Customer Returns & Credit Notes
        if (!empty($data['returns'])) {
            fputcsv($out, ['=== 10. CUSTOMER RETURNS & CREDIT NOTES ===']);
            fputcsv($out, ['Credit Note #', 'Original Bill #', 'Customer Name', 'Type', 'Disposition', 'Returned Pieces', 'Taxable Reversal (INR)', 'GST Reversal (INR)', 'Net Refund (INR)', 'Refund Mode', 'Date']);
            foreach ($data['returns'] as $ret) {
                fputcsv($out, [
                    $ret['return_number'],
                    $ret['bill_number'],
                    $ret['customer_name'],
                    $ret['return_type'],
                    $ret['disposition'],
                    (int)$ret['total_returned_qty'],
                    number_format((float)$ret['subtotal'], 2, '.', ''),
                    number_format((float)$ret['gst_reversal_amount'], 2, '.', ''),
                    number_format((float)$ret['net_amount'], 2, '.', ''),
                    $ret['refund_mode'],
                    date('d M Y', strtotime($ret['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 11. Warehouse Stock Requisitions
        if (!empty($data['stock_requests'])) {
            fputcsv($out, ['=== 11. WAREHOUSE STOCK REQUISITIONS ===']);
            fputcsv($out, ['Requisition #', 'Warehouse Destination', 'Requested By', 'Priority', 'Required By', 'Requested Pcs', 'Fulfilled Pcs', 'Status', 'Date']);
            foreach ($data['stock_requests'] as $sr) {
                fputcsv($out, [
                    $sr['request_number'],
                    $sr['warehouse_name'],
                    $sr['requested_by_name'],
                    $sr['priority'],
                    $sr['required_by_date'] ?? '-',
                    (int)$sr['total_requested_pieces'],
                    (int)$sr['fulfilled_pieces'],
                    $sr['status'],
                    date('d M Y', strtotime($sr['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 12. Wastage & Scrap Sales
        if (!empty($data['wastage'])) {
            fputcsv($out, ['=== 12. WASTAGE & SCRAP SALES ===']);
            fputcsv($out, ['Invoice Number', 'Wastage Type', 'Buyer Name', 'Weight (kg)', 'Rate/kg (INR)', 'Total Amount (INR)', 'Date']);
            foreach ($data['wastage'] as $w) {
                fputcsv($out, [
                    $w['invoice_number'],
                    $w['wastage_type'],
                    $w['buyer_name'],
                    number_format((float)($w['weight_kg'] ?? 0), 2, '.', ''),
                    number_format((float)($w['rate_per_kg'] ?? 0), 2, '.', ''),
                    number_format((float)($w['total_amount'] ?? 0), 2, '.', ''),
                    date('d M Y', strtotime($w['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 13. Production Expenses & Machinery Assets
        if (!empty($data['expenses'])) {
            fputcsv($out, ['=== 13. PRODUCTION EXPENSES & ASSETS ===']);
            fputcsv($out, ['Expense Title', 'Category', 'Unit', 'Total Cost (INR)', 'Paid (INR)', 'Pending (INR)', 'Payment Status', 'Expense Date']);
            foreach ($data['expenses'] as $e) {
                fputcsv($out, [
                    $e['title'] ?? '-',
                    $e['category'] ?? '-',
                    $e['production_unit'] ?? 'Production Facility',
                    number_format((float)($e['total_cost'] ?? 0), 2, '.', ''),
                    number_format((float)($e['paid_amount'] ?? 0), 2, '.', ''),
                    number_format((float)($e['pending_amount'] ?? 0), 2, '.', ''),
                    $e['payment_status'] ?? '-',
                    $e['expense_date'] ?? '-',
                ]);
            }
            fputcsv($out, []);
        }

        fclose($out);
        exit;
    }
}
