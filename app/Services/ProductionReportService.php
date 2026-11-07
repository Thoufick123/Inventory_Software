<?php

namespace App\Services;

use Database;

class ProductionReportService
{
    /**
     * Fetch complete dataset for the given date range and selected modules.
     */
    public static function getReportData(string $startDate, string $endDate, array $selectedModules): array
    {
        $data = [];

        // 1. Rolls & Inwards (from seller_rolls)
        if (in_array('rolls', $selectedModules, true)) {
            $data['rolls'] = Database::fetchAll(
                "SELECT sr.roll_number, sr.created_at, s.name AS supplier_name, s.company AS supplier_company,
                        sr.material_type AS fabric_type, sr.color, COALESCE(sr.weight, 0) AS weight,
                        COALESCE(sr.price, 0) AS total_cost, 'IN_STOCK' AS status
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

        // 6. Production Billing
        if (in_array('billing', $selectedModules, true)) {
            $data['billing'] = Database::fetchAll(
                "SELECT pb.bill_number, pb.bill_date, pb.buyer_type,
                        COALESCE(pb.customer_name, pb.company_name) AS client_name,
                        pb.customer_phone, pb.subtotal, pb.tax_amount, pb.total_amount,
                        pb.payment_method, pb.payment_status,
                        (SELECT COUNT(*) FROM production_bill_item_tags WHERE bill_id = pb.id) AS total_pieces
                 FROM production_bills pb
                 WHERE DATE(pb.bill_date) BETWEEN ? AND ?
                 ORDER BY pb.id DESC",
                [$startDate, $endDate]
            );
        }

        // 7. Customer Returns
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

        // 8. Warehouse Stock Requests
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

        // 9. Wastage Selling
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

        // 10. Expenses & Assets
        if (in_array('expenses', $selectedModules, true)) {
            $data['expenses'] = Database::fetchAll(
                "SELECT pe.item_name AS title, pe.category, pe.production_unit, pe.total_cost,
                        pe.paid_amount, (pe.total_cost - pe.paid_amount) AS pending_amount,
                        pe.payment_status, pe.expense_date
                 FROM production_expenses pe
                 WHERE DATE(pe.expense_date) BETWEEN ? AND ?
                 ORDER BY pe.id DESC",
                [$startDate, $endDate]
            );
        }

        return $data;
    }

    /**
     * Generate and stream CSV export directly to browser output.
     */
    public static function streamCsv(string $periodLabel, string $startDate, string $endDate, array $data): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="production_report_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        // Company / Header Info
        fputcsv($out, ['GRM APPAREL INVENTORY — PRODUCTION UNIT COMPREHENSIVE EXPORT']);
        fputcsv($out, ['Report Period:', $periodLabel, "From: {$startDate}", "To: {$endDate}"]);
        fputcsv($out, ['Generated At:', date('Y-m-d H:i:s')]);
        fputcsv($out, []); // Blank line

        // 1. Rolls
        if (!empty($data['rolls'])) {
            fputcsv($out, ['=== 1. FABRIC ROLLS & INWARDS ===']);
            fputcsv($out, ['Roll Number', 'Purchase Date', 'Supplier', 'Fabric Type', 'Color', 'Weight (kg)', 'Total Cost (₹)', 'Status']);
            foreach ($data['rolls'] as $r) {
                fputcsv($out, [
                    $r['roll_number'],
                    date('d M Y', strtotime($r['created_at'])),
                    $r['supplier_company'] ?: ($r['supplier_name'] ?? 'Direct Supplier'),
                    $r['fabric_type'],
                    $r['color'],
                    $r['weight'],
                    number_format((float)$r['total_cost'], 2),
                    $r['status'],
                ]);
            }
            fputcsv($out, []);
        }

        // 2. Programs
        if (!empty($data['programs'])) {
            fputcsv($out, ['=== 2. PRODUCTION PROGRAMS ===']);
            fputcsv($out, ['Program Number', 'Program Name', 'Style Code', 'Target Pieces', 'Completed Pieces', 'Status', 'Target Date']);
            foreach ($data['programs'] as $p) {
                fputcsv($out, [
                    $p['program_number'],
                    $p['program_name'],
                    $p['style_code'] ?? '-',
                    $p['target_pieces'],
                    $p['completed_quantity'] ?? 0,
                    $p['status'],
                    $p['target_date'] ?? '-',
                ]);
            }
            fputcsv($out, []);
        }

        // 3. Cuttings
        if (!empty($data['cuttings'])) {
            fputcsv($out, ['=== 3. CUTTING BATCHES ===']);
            fputcsv($out, ['Batch Number', 'Program #', 'Roll Weight (kg)', 'Planned Qty', 'Cut Pieces', 'Wastage Pcs', 'Status', 'Date']);
            foreach ($data['cuttings'] as $c) {
                fputcsv($out, [
                    $c['batch_number'],
                    $c['program_number'] ?? '-',
                    $c['roll_weight'],
                    $c['planned_qty'],
                    $c['actual_pieces'],
                    $c['wastage_qty'],
                    $c['status'],
                    date('d M Y', strtotime($c['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 4. Stitching
        if (!empty($data['stitching'])) {
            fputcsv($out, ['=== 4. STITCHING OPERATIONS ===']);
            fputcsv($out, ['Batch Number', 'Program #', 'Target Pieces', 'Completed Pieces', 'Rework Pieces', 'Rejected Pcs', 'Status', 'Date']);
            foreach ($data['stitching'] as $s) {
                fputcsv($out, [
                    $s['batch_number'],
                    $s['program_number'] ?? '-',
                    $s['target_quantity'],
                    $s['completed_quantity'],
                    $s['rework_quantity'],
                    $s['rejected_qty'],
                    $s['status'],
                    date('d M Y', strtotime($s['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 5. Quality Control (QC)
        if (!empty($data['qc'])) {
            fputcsv($out, ['=== 5. QUALITY CONTROL (QC) INSPECTIONS ===']);
            fputcsv($out, ['QC Batch #', 'Program #', 'Inspected Qty', 'Passed Qty', 'Rework Qty', 'Rejected Qty', 'Yield %', 'Status', 'Date']);
            foreach ($data['qc'] as $q) {
                fputcsv($out, [
                    $q['batch_number'],
                    $q['program_number'] ?? '-',
                    $q['inspected_quantity'],
                    $q['passed_quantity'],
                    $q['rework_quantity'],
                    $q['rejected_quantity'],
                    $q['yield_percentage'] . '%',
                    $q['status'],
                    date('d M Y', strtotime($q['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 6. Production Billing
        if (!empty($data['billing'])) {
            fputcsv($out, ['=== 6. PRODUCTION BILLING & DIRECT SALES ===']);
            fputcsv($out, ['Bill Number', 'Bill Date', 'Buyer Type', 'Customer / Company', 'Phone', 'Pieces Sold', 'Taxable (₹)', 'GST (₹)', 'Total (₹)', 'Payment Method', 'Status']);
            foreach ($data['billing'] as $b) {
                fputcsv($out, [
                    $b['bill_number'],
                    $b['bill_date'],
                    $b['buyer_type'],
                    $b['client_name'],
                    $b['customer_phone'] ?? '',
                    $b['total_pieces'],
                    number_format((float)$b['subtotal'], 2),
                    number_format((float)$b['tax_amount'], 2),
                    number_format((float)$b['total_amount'], 2),
                    $b['payment_method'],
                    $b['payment_status'],
                ]);
            }
            fputcsv($out, []);
        }

        // 7. Returns & Credit Notes
        if (!empty($data['returns'])) {
            fputcsv($out, ['=== 7. CUSTOMER RETURNS & CREDIT NOTES ===']);
            fputcsv($out, ['Credit Note #', 'Original Bill #', 'Customer Name', 'Type', 'Disposition', 'Returned Pieces', 'Taxable Reversal (₹)', 'GST Reversal (₹)', 'Net Credit (₹)', 'Refund Mode', 'Date']);
            foreach ($data['returns'] as $ret) {
                fputcsv($out, [
                    $ret['return_number'],
                    $ret['bill_number'],
                    $ret['customer_name'],
                    $ret['return_type'],
                    $ret['disposition'],
                    $ret['total_returned_qty'],
                    number_format((float)$ret['subtotal'], 2),
                    number_format((float)$ret['gst_reversal_amount'], 2),
                    number_format((float)$ret['net_amount'], 2),
                    $ret['refund_mode'],
                    date('d M Y', strtotime($ret['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 8. Stock Requests
        if (!empty($data['stock_requests'])) {
            fputcsv($out, ['=== 8. WAREHOUSE STOCK REQUISITIONS ===']);
            fputcsv($out, ['Requisition #', 'Warehouse Destination', 'Requested By', 'Priority', 'Required By', 'Requested Pcs', 'Fulfilled Pcs', 'Status', 'Date']);
            foreach ($data['stock_requests'] as $sr) {
                fputcsv($out, [
                    $sr['request_number'],
                    $sr['warehouse_name'],
                    $sr['requested_by_name'],
                    $sr['priority'],
                    $sr['required_by_date'] ?? '-',
                    $sr['total_requested_pieces'],
                    $sr['fulfilled_pieces'],
                    $sr['status'],
                    date('d M Y', strtotime($sr['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 9. Wastage
        if (!empty($data['wastage'])) {
            fputcsv($out, ['=== 9. WASTAGE & SCRAP SALES ===']);
            fputcsv($out, ['Invoice Number', 'Wastage Type', 'Buyer Name', 'Weight (kg)', 'Rate/kg (₹)', 'Total Amount (₹)', 'Date']);
            foreach ($data['wastage'] as $w) {
                fputcsv($out, [
                    $w['invoice_number'],
                    $w['wastage_type'],
                    $w['buyer_name'],
                    $w['weight_kg'],
                    number_format((float)$w['rate_per_kg'], 2),
                    number_format((float)$w['total_amount'], 2),
                    date('d M Y', strtotime($w['created_at'])),
                ]);
            }
            fputcsv($out, []);
        }

        // 10. Expenses & Assets
        if (!empty($data['expenses'])) {
            fputcsv($out, ['=== 10. PRODUCTION EXPENSES & ASSETS ===']);
            fputcsv($out, ['Expense Title', 'Category', 'Unit', 'Total Cost (₹)', 'Paid (₹)', 'Pending (₹)', 'Payment Status', 'Expense Date']);
            foreach ($data['expenses'] as $e) {
                fputcsv($out, [
                    $e['title'],
                    $e['category'],
                    $e['production_unit'],
                    number_format((float)$e['total_cost'], 2),
                    number_format((float)$e['paid_amount'], 2),
                    number_format((float)$e['pending_amount'], 2),
                    $e['payment_status'],
                    $e['expense_date'],
                ]);
            }
            fputcsv($out, []);
        }

        fclose($out);
        exit;
    }
}
