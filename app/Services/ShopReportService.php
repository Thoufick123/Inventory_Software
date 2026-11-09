<?php

namespace App\Services;

use Database;

class ShopReportService
{
    /**
     * Fetch complete dataset for the given date range, selected shop, and modules,
     * including executive financial summary and retail operations reconciliation metrics.
     */
    public static function getReportData(string $startDate, string $endDate, array $selectedModules, ?int $shopId = null): array
    {
        $data = [];
        $shopClause = $shopId ? " AND (s.shop_id = $shopId OR s.shop_id IS NULL)" : "";
        $stockShopClause = $shopId ? " AND sb.location_id = $shopId" : "";
        $inwardShopClause = $shopId ? " AND (wa.destination_id = $shopId OR wa.shop_id = $shopId)" : "";
        $retShopClause = $shopId ? " AND (s.shop_id = $shopId OR r.source_type = 'SHOP')" : "";
        $retWhShopClause = $shopId ? " AND wr.source_type = 'SHOP' AND wr.source_id = $shopId" : "";
        $crShopClause = $shopId ? " AND cr.shop_id = $shopId" : "";
        $ceShopClause = $shopId ? " AND ce.shop_id = $shopId" : "";
        $sdShopClause = $shopId ? " AND sd.shop_id = $shopId" : "";

        // =========================================================================
        // 1. POS SALES & INVOICES
        // =========================================================================
        if (in_array('sales', $selectedModules, true)) {
            $data['sales'] = Database::fetchAll(
                "SELECT s.id, s.invoice_number, s.created_at,
                        COALESCE(s.customer_name, 'Walk-in Customer') AS customer_name,
                        COALESCE(s.customer_phone, '-') AS customer_phone,
                        COALESCE(s.subtotal, 0) AS subtotal,
                        COALESCE(s.discount_amount, 0) AS discount_amount,
                        COALESCE(s.tax_amount, s.gst_amount, 0) AS tax_amount,
                        COALESCE(s.total_amount, 0) AS total_amount,
                        COALESCE(s.paid_amount, s.total_amount) AS paid_amount,
                        COALESCE(s.payment_method, s.payment_mode, 'CASH') AS payment_method,
                        COALESCE(s.payment_status, 'PAID') AS payment_status,
                        COALESCE(s.status, 'COMPLETED') AS status,
                        COALESCE(s.biller_name, s.employee_name, 'Cashier') AS biller_name,
                        COALESCE(si_cnt.item_count, 0) AS items_count,
                        COALESCE(si_cnt.total_pieces, 0) AS total_pieces
                 FROM sales s
                 LEFT JOIN (
                     SELECT sale_id, COUNT(*) AS item_count, SUM(quantity) AS total_pieces
                     FROM sale_items GROUP BY sale_id
                 ) si_cnt ON si_cnt.sale_id = s.id
                 WHERE DATE(s.created_at) BETWEEN ? AND ?
                 $shopClause
                 ORDER BY s.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 2. SELLED PRODUCTS / ITEM-WISE SALES BREAKDOWN
        // =========================================================================
        if (in_array('sale_items', $selectedModules, true)) {
            $data['sale_items'] = Database::fetchAll(
                "SELECT si.id, s.invoice_number, s.created_at AS sale_date,
                        COALESCE(p.name, 'Retail Item') AS product_name,
                        COALESCE(pv.sku, '-') AS sku,
                        COALESCE(sz.name, 'Free') AS size_name,
                        COALESCE(col.name, 'Std') AS color_name,
                        si.quantity,
                        COALESCE(si.unit_price, 0) AS unit_price,
                        COALESCE(si.discount_amount, 0) AS discount_amount,
                        COALESCE(si.cgst_amount, 0) AS cgst_amount,
                        COALESCE(si.sgst_amount, 0) AS sgst_amount,
                        COALESCE(si.total_amount, 0) AS total_amount,
                        COALESCE(si.scanned_qr_codes, si.qr_code, '-') AS scanned_qr_codes
                 FROM sale_items si
                 JOIN sales s ON si.sale_id = s.id
                 JOIN product_variants pv ON si.variant_id = pv.id
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 WHERE DATE(s.created_at) BETWEEN ? AND ?
                 $shopClause
                 ORDER BY si.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 3. SHOP INVENTORY / STOCK LEVELS & VALUATION
        // =========================================================================
        if (in_array('stock', $selectedModules, true)) {
            $data['stock'] = Database::fetchAll(
                "SELECT pv.id AS variant_id,
                        COALESCE(p.name, 'Retail Product') AS product_name,
                        COALESCE(pv.sku, '-') AS sku,
                        COALESCE(sz.name, 'Free') AS size_name,
                        COALESCE(col.name, 'Std') AS color_name,
                        sb.quantity AS stock_quantity,
                        sb.stock_state,
                        COALESCE(pv.mrp, 0) AS mrp,
                        COALESCE(pv.selling_price, 0) AS selling_price,
                        (sb.quantity * COALESCE(pv.selling_price, 0)) AS valuation
                 FROM stock_balances sb
                 JOIN product_variants pv ON sb.variant_id = pv.id
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 WHERE sb.location_type = 'SHOP'
                   AND sb.quantity > 0
                   $stockShopClause
                 ORDER BY p.name ASC, pv.sku ASC"
            );
        }

        // =========================================================================
        // 4. INWARD SHIPMENTS (From Central Warehouse Allocation)
        // =========================================================================
        if (in_array('inward', $selectedModules, true)) {
            $data['inward'] = Database::fetchAll(
                "SELECT wa.id, wa.allocation_number, wa.created_at AS dispatch_date,
                        COALESCE(w.name, 'Central Warehouse') AS source_warehouse,
                        COALESCE(wa.status, 'PENDING') AS status,
                        COALESCE(wa.notes, '-') AS notes,
                        COALESCE(ai_sum.requested_qty, 0) AS total_requested_qty,
                        COALESCE(ai_sum.received_qty, 0) AS total_received_qty,
                        COALESCE(ai_sum.damaged_qty, 0) AS total_damaged_qty
                 FROM warehouse_allocations wa
                 LEFT JOIN warehouses w ON wa.warehouse_id = w.id
                 LEFT JOIN (
                     SELECT allocation_id,
                            SUM(requested_qty) AS requested_qty,
                            SUM(received_qty) AS received_qty,
                            SUM(damaged_qty) AS damaged_qty
                     FROM allocation_items GROUP BY allocation_id
                 ) ai_sum ON ai_sum.allocation_id = wa.id
                 WHERE DATE(wa.created_at) BETWEEN ? AND ?
                 $inwardShopClause
                 ORDER BY wa.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 5. CUSTOMER RETURNS & REFUNDS
        // =========================================================================
        if (in_array('returns', $selectedModules, true)) {
            $data['returns'] = Database::fetchAll(
                "SELECT r.id, r.return_number, r.created_at,
                        COALESCE(s.invoice_number, '-') AS invoice_number,
                        COALESCE(c.name, s.customer_name, 'Retail Customer') AS customer_name,
                        COALESCE(r.return_type, 'CUSTOMER_RETURN') AS return_type,
                        COALESCE(r.reason, '-') AS reason,
                        COALESCE(r.refund_amount, r.total_amount, 0) AS refund_amount,
                        COALESCE(r.refund_mode, 'CASH') AS refund_mode,
                        COALESCE(r.status, 'COMPLETED') AS status
                 FROM returns r
                 LEFT JOIN sales s ON r.sale_id = s.id
                 LEFT JOIN customers c ON r.customer_id = c.id
                 WHERE DATE(r.created_at) BETWEEN ? AND ?
                 $retShopClause
                 ORDER BY r.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 6. RETURNS TO WAREHOUSE (Defective/Excess Stock Transferred Back)
        // =========================================================================
        if (in_array('return_to_warehouse', $selectedModules, true)) {
            $data['return_to_warehouse'] = Database::fetchAll(
                "SELECT wr.id, wr.return_number, wr.created_at,
                        COALESCE(w.name, 'Central Warehouse') AS destination_warehouse,
                        COALESCE(wr.reason, '-') AS reason,
                        COALESCE(wr.status, 'PENDING') AS status,
                        COALESCE(wr.notes, '-') AS notes,
                        COALESCE(wri_sum.returned_qty, 0) AS total_returned_qty
                 FROM warehouse_returns wr
                 LEFT JOIN warehouses w ON wr.destination_warehouse_id = w.id
                 LEFT JOIN (
                     SELECT warehouse_return_id, SUM(returned_qty) AS returned_qty
                     FROM warehouse_return_items GROUP BY warehouse_return_id
                 ) wri_sum ON wri_sum.warehouse_return_id = wr.id
                 WHERE DATE(wr.created_at) BETWEEN ? AND ?
                 $retWhShopClause
                 ORDER BY wr.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 7. SHOP CUSTOMERS DIRECTORY
        // =========================================================================
        if (in_array('customers', $selectedModules, true)) {
            $data['customers'] = Database::fetchAll(
                "SELECT c.id, c.name, COALESCE(c.phone, '-') AS phone,
                        COALESCE(c.email, '-') AS email,
                        COALESCE(c.city, '-') AS city,
                        COALESCE(c.state, '-') AS state,
                        COALESCE(c.total_purchases, 0) AS total_purchases,
                        c.created_at
                 FROM customers c
                 WHERE DATE(c.created_at) BETWEEN ? AND ?
                 ORDER BY c.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 8. CASH REGISTERS (Shift Management & Drawer Reconciliations)
        // =========================================================================
        if (in_array('cash_register', $selectedModules, true)) {
            $data['cash_register'] = Database::fetchAll(
                "SELECT cr.id, cr.register_number, cr.cashier_name, cr.opening_time, cr.closing_time,
                        COALESCE(cr.opening_balance, 0) AS opening_balance,
                        COALESCE(cr.cash_sales_amount, 0) AS cash_sales_amount,
                        COALESCE(cr.upi_sales_amount, 0) AS upi_sales_amount,
                        COALESCE(cr.card_sales_amount, 0) AS card_sales_amount,
                        COALESCE(cr.cash_in_amount, 0) AS cash_in_amount,
                        COALESCE(cr.cash_out_amount, 0) AS cash_out_amount,
                        COALESCE(cr.expected_cash, 0) AS expected_cash,
                        COALESCE(cr.closing_cash_counted, 0) AS closing_cash_counted,
                        COALESCE(cr.cash_difference, 0) AS cash_difference,
                        COALESCE(cr.status, 'CLOSED') AS status,
                        COALESCE(cr.notes, '-') AS notes
                 FROM cash_registers cr
                 WHERE DATE(cr.opening_time) BETWEEN ? AND ?
                 $crShopClause
                 ORDER BY cr.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 9. PETTY CASH EXPENSES & VOUCHERS
        // =========================================================================
        if (in_array('cash_expenses', $selectedModules, true)) {
            $data['cash_expenses'] = Database::fetchAll(
                "SELECT ce.id, ce.voucher_number, ce.created_at,
                        COALESCE(ce.expense_category, 'General') AS expense_category,
                        COALESCE(ce.amount, 0) AS amount,
                        COALESCE(ce.paid_to, '-') AS paid_to,
                        COALESCE(ce.payment_mode, 'CASH') AS payment_mode,
                        COALESCE(ce.bill_number, '-') AS bill_number,
                        COALESCE(ce.notes, '-') AS notes
                 FROM cash_expenses ce
                 WHERE DATE(ce.created_at) BETWEEN ? AND ?
                 $ceShopClause
                 ORDER BY ce.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 10. SHOP DAMAGES & DEFECTIVE GOODS
        // =========================================================================
        if (in_array('damages', $selectedModules, true)) {
            $data['damages'] = Database::fetchAll(
                "SELECT sd.id, sd.damage_number, sd.created_at,
                        COALESCE(p.name, 'Shop Item') AS product_name,
                        COALESCE(pv.sku, '-') AS sku,
                        COALESCE(sd.tag_code, '-') AS tag_code,
                        COALESCE(sd.defect_type, 'DAMAGE') AS defect_type,
                        COALESCE(sd.quantity, 1) AS quantity,
                        COALESCE(sd.total_loss, 0) AS total_loss,
                        COALESCE(sd.damage_reason, '-') AS damage_reason,
                        COALESCE(sd.status, 'RECORDED') AS status
                 FROM shop_damages sd
                 JOIN product_variants pv ON sd.variant_id = pv.id
                 JOIN products p ON pv.product_id = p.id
                 WHERE DATE(sd.created_at) BETWEEN ? AND ?
                 $sdShopClause
                 ORDER BY sd.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // EXECUTIVE FINANCIAL & RETAIL PERFORMANCE SUMMARY
        // =========================================================================
        $salesAgg = Database::fetchOne(
            "SELECT COUNT(*) AS total_invoices,
                    COALESCE(SUM(total_amount), 0) AS total_revenue,
                    COALESCE(SUM(subtotal), 0) AS total_subtotal,
                    COALESCE(SUM(tax_amount), SUM(gst_amount), 0) AS total_tax,
                    COALESCE(SUM(discount_amount), 0) AS total_discount,
                    COALESCE(SUM(CASE WHEN payment_method = 'CASH' OR payment_mode = 'CASH' THEN paid_amount ELSE 0 END), 0) AS cash_revenue,
                    COALESCE(SUM(CASE WHEN payment_method = 'UPI' OR payment_mode = 'UPI' THEN paid_amount ELSE 0 END), 0) AS upi_revenue,
                    COALESCE(SUM(CASE WHEN payment_method = 'CARD' OR payment_mode = 'CARD' THEN paid_amount ELSE 0 END), 0) AS card_revenue
             FROM sales s
             WHERE DATE(s.created_at) BETWEEN ? AND ?
             $shopClause",
            [$startDate, $endDate]
        );

        $piecesAgg = Database::fetchOne(
            "SELECT COALESCE(SUM(si.quantity), 0) AS total_pieces_sold
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             WHERE DATE(s.created_at) BETWEEN ? AND ?
             $shopClause",
            [$startDate, $endDate]
        );

        $returnsAgg = Database::fetchOne(
            "SELECT COUNT(*) AS return_count,
                    COALESCE(SUM(r.refund_amount), SUM(r.total_amount), 0) AS refund_total
             FROM returns r
             LEFT JOIN sales s ON r.sale_id = s.id
             WHERE DATE(r.created_at) BETWEEN ? AND ?
             $retShopClause",
            [$startDate, $endDate]
        );

        $expensesAgg = Database::fetchOne(
            "SELECT COUNT(*) AS expense_count,
                    COALESCE(SUM(amount), 0) AS expense_total
             FROM cash_expenses ce
             WHERE DATE(ce.created_at) BETWEEN ? AND ?
             $ceShopClause",
            [$startDate, $endDate]
        );

        $stockAgg = Database::fetchOne(
            "SELECT COALESCE(SUM(CASE WHEN sb.stock_state = 'AVAILABLE' THEN sb.quantity ELSE 0 END), 0) AS available_pieces,
                    COALESCE(SUM(CASE WHEN sb.stock_state = 'DAMAGED' THEN sb.quantity ELSE 0 END), 0) AS damaged_pieces,
                    COALESCE(SUM(CASE WHEN sb.stock_state = 'AVAILABLE' THEN (sb.quantity * pv.selling_price) ELSE 0 END), 0) AS total_valuation
             FROM stock_balances sb
             JOIN product_variants pv ON sb.variant_id = pv.id
             WHERE sb.location_type = 'SHOP'
               AND sb.quantity > 0
               $stockShopClause"
        );

        $damagesAgg = Database::fetchOne(
            "SELECT COUNT(*) AS damage_cases,
                    COALESCE(SUM(quantity), 0) AS damaged_pieces,
                    COALESCE(SUM(total_loss), 0) AS total_loss
             FROM shop_damages sd
             WHERE DATE(sd.created_at) BETWEEN ? AND ?
             $sdShopClause",
            [$startDate, $endDate]
        );

        $totRev   = (float)($salesAgg['total_revenue'] ?? 0);
        $refTotal = (float)($returnsAgg['refund_total'] ?? 0);
        $expTotal = (float)($expensesAgg['expense_total'] ?? 0);
        $netRealized = max(0, $totRev - $refTotal - $expTotal);

        $data['summary'] = [
            'financial' => [
                'total_invoices'     => (int)($salesAgg['total_invoices'] ?? 0),
                'total_revenue'      => $totRev,
                'total_subtotal'     => (float)($salesAgg['total_subtotal'] ?? 0),
                'total_tax'          => (float)($salesAgg['total_tax'] ?? 0),
                'total_discount'     => (float)($salesAgg['total_discount'] ?? 0),
                'cash_revenue'       => (float)($salesAgg['cash_revenue'] ?? 0),
                'upi_revenue'        => (float)($salesAgg['upi_revenue'] ?? 0),
                'card_revenue'       => (float)($salesAgg['card_revenue'] ?? 0),
                'total_pieces_sold'  => (int)($piecesAgg['total_pieces_sold'] ?? 0),
                'returns_count'      => (int)($returnsAgg['return_count'] ?? 0),
                'refund_total'       => $refTotal,
                'expenses_count'     => (int)($expensesAgg['expense_count'] ?? 0),
                'expense_total'      => $expTotal,
                'net_realized'       => $netRealized,
            ],
            'stock' => [
                'available_pieces'   => (int)($stockAgg['available_pieces'] ?? 0),
                'damaged_pieces'     => (int)($stockAgg['damaged_pieces'] ?? 0),
                'total_valuation'    => (float)($stockAgg['total_valuation'] ?? 0),
            ],
            'damages' => [
                'cases_count'        => (int)($damagesAgg['damage_cases'] ?? 0),
                'damaged_pieces'     => (int)($damagesAgg['damaged_pieces'] ?? 0),
                'total_loss'         => (float)($damagesAgg['total_loss'] ?? 0),
            ]
        ];

        return $data;
    }

    /**
     * Stream CSV formatted spreadsheet directly to HTTP output (Excel compatible).
     */
    public static function streamCsv(string $periodLabel, string $startDate, string $endDate, array $data, ?array $shop = null): void
    {
        $shopName = !empty($shop['name']) ? strtoupper($shop['name']) : 'RETAIL OUTLET & POS SHOP';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="shop_report_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel recognition
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        // 1. Shop Header
        fputcsv($out, [$shopName . ' — COMPREHENSIVE RETAIL SHOP OPERATIONS REPORT']);
        if (!empty($shop['address'])) {
            fputcsv($out, ['Shop Address:', str_replace(["\r\n", "\n", "\r"], ' ', (string)$shop['address'])]);
        }
        fputcsv($out, [
            'Shop Code:', $shop['code'] ?? 'SHOP-01',
            'Contact Phone:', $shop['phone'] ?? '-',
            'Report Scope:', !empty($shop['id']) ? "Specific Shop #{$shop['id']} ({$shopName})" : 'All Shops (Admin Scope)'
        ]);
        fputcsv($out, ['Report Period:', $periodLabel, "From: {$startDate}", "To: {$endDate}"]);
        fputcsv($out, ['Generated Timestamp:', date('Y-m-d H:i:s') . ' IST']);
        fputcsv($out, []); // Blank separator line

        // 2. Executive Operations & Retail Financial Summary
        if (!empty($data['summary'])) {
            $sum = $data['summary'];
            $fin = $sum['financial'];
            $stk = $sum['stock'];
            $dam = $sum['damages'];

            fputcsv($out, ['=== 1. EXECUTIVE RETAIL FINANCIAL & STOCK PERFORMANCE KPI SUMMARY ===']);
            fputcsv($out, ['METRIC', 'VALUE', 'REMARKS']);
            fputcsv($out, ['Total Invoices Issued', number_format($fin['total_invoices']), 'Completed POS billing transactions']);
            fputcsv($out, ['Total Products Sold (Pcs)', number_format($fin['total_pieces_sold']), 'Total items checked out at counter']);
            fputcsv($out, ['Gross Sales Revenue (INR)', 'INR ' . number_format($fin['total_revenue'], 2), 'Total billed volume inclusive of taxes']);
            fputcsv($out, ['Net Taxable Sales (INR)', 'INR ' . number_format($fin['total_subtotal'], 2), 'Base value before GST']);
            fputcsv($out, ['Total GST Collected (INR)', 'INR ' . number_format($fin['total_tax'], 2), 'CGST + SGST aggregate']);
            fputcsv($out, ['Discounts Given (INR)', 'INR ' . number_format($fin['total_discount'], 2), 'Total promotional / price reductions']);
            fputcsv($out, ['Cash Sales Realization', 'INR ' . number_format($fin['cash_revenue'], 2), 'Physical cash deposited in drawer']);
            fputcsv($out, ['UPI Sales Realization', 'INR ' . number_format($fin['upi_revenue'], 2), 'QR / UPI digital receipts']);
            fputcsv($out, ['Card Sales Realization', 'INR ' . number_format($fin['card_revenue'], 2), 'Debit / Credit POS terminals']);
            fputcsv($out, ['Customer Refunds (INR)', 'INR ' . number_format($fin['refund_total'], 2), number_format($fin['returns_count']) . ' returned transactions']);
            fputcsv($out, ['Petty Cash Expenses (INR)', 'INR ' . number_format($fin['expense_total'], 2), number_format($fin['expenses_count']) . ' vouchers logged']);
            fputcsv($out, ['Net Realized Cash (INR)', 'INR ' . number_format($fin['net_realized'], 2), 'Gross Revenue - Refunds - Expenses']);
            fputcsv($out, ['Current Available Stock', number_format($stk['available_pieces']) . ' pcs', 'Valuation: INR ' . number_format($stk['total_valuation'], 2)]);
            fputcsv($out, ['Damaged / Defective Stock', number_format($stk['damaged_pieces']) . ' pcs', 'Recorded loss: INR ' . number_format($dam['total_loss'], 2)]);
            fputcsv($out, []); // Blank separator line
        }

        // 3. Module: POS Sales & Invoices
        if (!empty($data['sales'])) {
            fputcsv($out, ['=== 2. POS SALES INVOICES & BILLING TRANSACTIONS ===']);
            fputcsv($out, [
                'Invoice #', 'Date & Time', 'Customer Name', 'Phone',
                'Items Count', 'Pieces', 'Subtotal (INR)', 'Discount (INR)',
                'Tax (INR)', 'Grand Total (INR)', 'Paid (INR)',
                'Payment Method', 'Payment Status', 'Status', 'Biller / Cashier'
            ]);
            foreach ($data['sales'] as $row) {
                fputcsv($out, [
                    $row['invoice_number'],
                    $row['created_at'],
                    $row['customer_name'],
                    $row['customer_phone'],
                    $row['items_count'],
                    $row['total_pieces'],
                    number_format($row['subtotal'], 2, '.', ''),
                    number_format($row['discount_amount'], 2, '.', ''),
                    number_format($row['tax_amount'], 2, '.', ''),
                    number_format($row['total_amount'], 2, '.', ''),
                    number_format($row['paid_amount'], 2, '.', ''),
                    $row['payment_method'],
                    $row['payment_status'],
                    $row['status'],
                    $row['biller_name']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 4. Module: Item-wise Selled Products Breakdown
        if (!empty($data['sale_items'])) {
            fputcsv($out, ['=== 3. SELLED PRODUCTS / ITEM-BY-ITEM SALES DETAILS ===']);
            fputcsv($out, [
                'Invoice #', 'Sale Date', 'Product Name', 'SKU', 'Size', 'Color',
                'Quantity (Pcs)', 'Unit Price (INR)', 'Discount (INR)',
                'Total (INR)', 'Scanned Tag / QR Codes'
            ]);
            foreach ($data['sale_items'] as $row) {
                fputcsv($out, [
                    $row['invoice_number'],
                    $row['sale_date'],
                    $row['product_name'],
                    $row['sku'],
                    $row['size_name'],
                    $row['color_name'],
                    $row['quantity'],
                    number_format($row['unit_price'], 2, '.', ''),
                    number_format($row['discount_amount'], 2, '.', ''),
                    number_format($row['total_amount'], 2, '.', ''),
                    $row['scanned_qr_codes']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 5. Module: Shop Inventory Stock & Valuation
        if (!empty($data['stock'])) {
            fputcsv($out, ['=== 4. CURRENT SHOP INVENTORY & STOCK VALUATION ===']);
            fputcsv($out, [
                'Product Name', 'SKU', 'Size', 'Color',
                'Stock Quantity (Pcs)', 'Stock State', 'MRP (INR)',
                'Retail Selling Price (INR)', 'Total Valuation (INR)'
            ]);
            foreach ($data['stock'] as $row) {
                fputcsv($out, [
                    $row['product_name'],
                    $row['sku'],
                    $row['size_name'],
                    $row['color_name'],
                    $row['stock_quantity'],
                    $row['stock_state'],
                    number_format($row['mrp'], 2, '.', ''),
                    number_format($row['selling_price'], 2, '.', ''),
                    number_format($row['valuation'], 2, '.', '')
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 6. Module: Inward Shipments from Central Warehouse
        if (!empty($data['inward'])) {
            fputcsv($out, ['=== 5. INWARD STOCK ALLOCATIONS FROM CENTRAL WAREHOUSE ===']);
            fputcsv($out, [
                'Allocation #', 'Date', 'Source Warehouse', 'Status',
                'Requested Qty (Pcs)', 'Received Qty (Pcs)', 'Damaged Qty (Pcs)', 'Notes'
            ]);
            foreach ($data['inward'] as $row) {
                fputcsv($out, [
                    $row['allocation_number'],
                    $row['dispatch_date'],
                    $row['source_warehouse'],
                    $row['status'],
                    $row['total_requested_qty'],
                    $row['total_received_qty'],
                    $row['total_damaged_qty'],
                    $row['notes']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 7. Module: Customer Returns
        if (!empty($data['returns'])) {
            fputcsv($out, ['=== 6. CUSTOMER RETURNS & REFUNDS ===']);
            fputcsv($out, [
                'Return #', 'Date & Time', 'Invoice #', 'Customer Name',
                'Return Type', 'Reason', 'Refund Amount (INR)', 'Refund Mode', 'Status'
            ]);
            foreach ($data['returns'] as $row) {
                fputcsv($out, [
                    $row['return_number'],
                    $row['created_at'],
                    $row['invoice_number'],
                    $row['customer_name'],
                    $row['return_type'],
                    $row['reason'],
                    number_format($row['refund_amount'], 2, '.', ''),
                    $row['refund_mode'],
                    $row['status']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 8. Module: Returns to Warehouse
        if (!empty($data['return_to_warehouse'])) {
            fputcsv($out, ['=== 7. STOCK RETURNS DISPATCHED TO CENTRAL WAREHOUSE ===']);
            fputcsv($out, [
                'Return #', 'Date', 'Destination Warehouse', 'Returned Pieces',
                'Reason', 'Status', 'Notes'
            ]);
            foreach ($data['return_to_warehouse'] as $row) {
                fputcsv($out, [
                    $row['return_number'],
                    $row['created_at'],
                    $row['destination_warehouse'],
                    $row['total_returned_qty'],
                    $row['reason'],
                    $row['status'],
                    $row['notes']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 9. Module: Shop Customers
        if (!empty($data['customers'])) {
            fputcsv($out, ['=== 8. REGISTERED SHOP CUSTOMERS DIRECTORY ===']);
            fputcsv($out, [
                'Customer Name', 'Phone Number', 'Email', 'City', 'State',
                'Total Purchases (INR)', 'Registration Date'
            ]);
            foreach ($data['customers'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['phone'],
                    $row['email'],
                    $row['city'],
                    $row['state'],
                    number_format($row['total_purchases'], 2, '.', ''),
                    $row['created_at']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 10. Module: Cash Register Shifts
        if (!empty($data['cash_register'])) {
            fputcsv($out, ['=== 9. CASH REGISTER SHIFTS & DRAWER RECONCILIATIONS ===']);
            fputcsv($out, [
                'Register #', 'Cashier', 'Opening Time', 'Closing Time',
                'Opening Float (INR)', 'Cash Sales (INR)', 'UPI Sales (INR)',
                'Card Sales (INR)', 'Cash In (INR)', 'Cash Out (INR)',
                'Expected Cash (INR)', 'Counted Cash (INR)', 'Discrepancy (INR)', 'Status'
            ]);
            foreach ($data['cash_register'] as $row) {
                fputcsv($out, [
                    $row['register_number'],
                    $row['cashier_name'],
                    $row['opening_time'],
                    $row['closing_time'],
                    number_format($row['opening_balance'], 2, '.', ''),
                    number_format($row['cash_sales_amount'], 2, '.', ''),
                    number_format($row['upi_sales_amount'], 2, '.', ''),
                    number_format($row['card_sales_amount'], 2, '.', ''),
                    number_format($row['cash_in_amount'], 2, '.', ''),
                    number_format($row['cash_out_amount'], 2, '.', ''),
                    number_format($row['expected_cash'], 2, '.', ''),
                    number_format($row['closing_cash_counted'], 2, '.', ''),
                    number_format($row['cash_difference'], 2, '.', ''),
                    $row['status']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 11. Module: Petty Cash Expenses
        if (!empty($data['cash_expenses'])) {
            fputcsv($out, ['=== 10. PETTY CASH EXPENSES & PAYMENTS ===']);
            fputcsv($out, [
                'Voucher #', 'Date & Time', 'Expense Category', 'Paid To',
                'Amount (INR)', 'Payment Mode', 'Bill #', 'Notes'
            ]);
            foreach ($data['cash_expenses'] as $row) {
                fputcsv($out, [
                    $row['voucher_number'],
                    $row['created_at'],
                    $row['expense_category'],
                    $row['paid_to'],
                    number_format($row['amount'], 2, '.', ''),
                    $row['payment_mode'],
                    $row['bill_number'],
                    $row['notes']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        // 12. Module: Shop Damages
        if (!empty($data['damages'])) {
            fputcsv($out, ['=== 11. SHOP DAMAGED & DEFECTIVE STOCK ===']);
            fputcsv($out, [
                'Damage #', 'Date & Time', 'Product Name', 'SKU', 'Tag / QR Code',
                'Defect Type', 'Quantity', 'Loss Amount (INR)', 'Damage Reason', 'Status'
            ]);
            foreach ($data['damages'] as $row) {
                fputcsv($out, [
                    $row['damage_number'],
                    $row['created_at'],
                    $row['product_name'],
                    $row['sku'],
                    $row['tag_code'],
                    $row['defect_type'],
                    $row['quantity'],
                    number_format($row['total_loss'], 2, '.', ''),
                    $row['damage_reason'],
                    $row['status']
                ]);
            }
            fputcsv($out, []); // Blank separator line
        }

        fclose($out);
        exit;
    }
}
