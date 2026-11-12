<?php

namespace App\Services;

use Database;

class WarehouseReportService
{
    /**
     * Fetch complete dataset for the given date range, selected warehouse, and modules,
     * including executive financial summary and operational stock reconciliation metrics.
     */
    public static function getReportData(string $startDate, string $endDate, array $selectedModules, ?int $warehouseId = null): array
    {
        $data = [];
        $whStockClause = $warehouseId ? " AND sb.location_id = $warehouseId" : "";
        $whAllocClause = $warehouseId ? " AND wa.warehouse_id = $warehouseId" : "";
        $whOutClause   = $warehouseId ? " AND ao.warehouse_id = $warehouseId" : "";
        $whDamClause   = $warehouseId ? " AND wd.warehouse_id = $warehouseId" : "";
        $whRetClause   = $warehouseId ? " AND wr.warehouse_id = $warehouseId" : "";
        $whExpClause   = $warehouseId ? " AND e.location_id = $warehouseId" : "";
        $whInwClause   = $warehouseId ? " AND pi.warehouse_id = $warehouseId" : "";
        $whPoClause    = $warehouseId ? " AND po.warehouse_id = $warehouseId" : "";
        $whBatchClause = $warehouseId ? " AND pb.destination_warehouse_id = $warehouseId" : "";

        // =========================================================================
        // 1. INWARD SHIPMENTS & GRN (From Factory Outward & Direct Vendor Purchases)
        // =========================================================================
        if (in_array('inward', $selectedModules, true)) {
            // Production Finished Goods Inward
            $prodInward = Database::fetchAll(
                "SELECT pb.id, pb.batch_number, 'PRODUCTION' as source_type,
                        pu.name as supplier_or_source,
                        COALESCE(p.name, pp.name) as product_name,
                        p.code as product_code,
                        w.name as warehouse_name,
                        pb.box_count, pb.total_qty as total_pieces,
                        COALESCE(po.outward_number, '-') as outward_doc_no,
                        COALESCE(po.vehicle_number, '-') as vehicle_number,
                        COALESCE(po.driver_name, '-') as driver_name,
                        COALESCE(po.driver_contact, '-') as driver_contact,
                        pb.status,
                        COALESCE(po.dispatch_date, pb.created_at) as inward_date
                 FROM production_batches pb
                 JOIN production_programs pp ON pb.program_id = pp.id
                 LEFT JOIN production_units pu ON pp.production_unit_id = pu.id
                 LEFT JOIN products p ON pp.product_id = p.id
                 LEFT JOIN warehouses w ON pb.destination_warehouse_id = w.id
                 LEFT JOIN production_outwards po ON po.production_batch_id = pb.id
                 WHERE (DATE(pb.created_at) BETWEEN ? AND ? OR DATE(po.dispatch_date) BETWEEN ? AND ?)
                 $whBatchClause
                 ORDER BY pb.id DESC",
                [$startDate, $endDate, $startDate, $endDate]
            );

            // Direct Vendor GRN Inward
            $vendorInward = Database::fetchAll(
                "SELECT pi.id, pi.grn_number as batch_number, 'VENDOR_PURCHASE' as source_type,
                        COALESCE(ws.name, ws.company, s.name) as supplier_or_source,
                        COALESCE(po.po_number, pi.invoice_number) as product_name,
                        COALESCE(pi.invoice_number, '-') as product_code,
                        w.name as warehouse_name,
                        1 as box_count,
                        COALESCE(pi.total_received_qty, (SELECT SUM(received_qty) FROM purchase_inward_items WHERE purchase_inward_id = pi.id), 0) as total_pieces,
                        COALESCE(pi.gate_pass_number, pi.grn_number) as outward_doc_no,
                        COALESCE(pi.vehicle_number, '-') as vehicle_number,
                        COALESCE(pi.driver_name, '-') as driver_name,
                        COALESCE(pi.driver_contact, '-') as driver_contact,
                        pi.status,
                        COALESCE(pi.received_date, pi.created_at) as inward_date
                 FROM purchase_inwards pi
                 LEFT JOIN purchase_orders po ON pi.purchase_order_id = po.id
                 LEFT JOIN warehouse_suppliers ws ON po.warehouse_supplier_id = ws.id
                 LEFT JOIN sellers s ON po.seller_id = s.id
                 LEFT JOIN warehouses w ON pi.warehouse_id = w.id
                 WHERE DATE(COALESCE(pi.received_date, pi.created_at)) BETWEEN ? AND ?
                 $whInwClause
                 ORDER BY pi.id DESC",
                [$startDate, $endDate]
            );

            $data['inward'] = array_merge($prodInward, $vendorInward);
            usort($data['inward'], fn($a, $b) => strcmp($b['inward_date'], $a['inward_date']));
        }

        // =========================================================================
        // 2. CURRENT WAREHOUSE INVENTORY & STOCK BALANCES
        // =========================================================================
        if (in_array('stock', $selectedModules, true)) {
            $data['stock'] = Database::fetchAll(
                "SELECT p.id as product_id, p.name as product_name, p.code as product_code,
                        p.catalog_code, p.hsn_code, p.gst_percentage,
                        COALESCE(wc.name, cat.name) as category_name,
                        pv.id as variant_id, pv.sku, pv.qr_code,
                        s.name as size_name, c.name as color_name,
                        w.name as warehouse_name,
                        sb.quantity as available_qty,
                        COALESCE(pv.purchase_price, pv.production_cost, 0.00) as cost_price,
                        COALESCE(pv.mrp, 0.00) as mrp,
                        COALESCE(pv.selling_price, 0.00) as selling_price,
                        ROUND(sb.quantity * COALESCE(pv.selling_price, 0.00), 2) as retail_valuation,
                        ROUND(sb.quantity * COALESCE(pv.purchase_price, pv.production_cost, 0.00), 2) as cost_valuation
                 FROM stock_balances sb
                 JOIN product_variants pv ON sb.variant_id = pv.id
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                 LEFT JOIN categories cat ON p.category_id = cat.id
                 LEFT JOIN warehouses w ON sb.location_id = w.id
                 WHERE sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE' AND sb.quantity > 0
                 $whStockClause
                 ORDER BY p.name ASC, pv.sku ASC"
            );
        }

        // =========================================================================
        // 3. WAREHOUSE CATALOG & PRODUCT STYLES
        // =========================================================================
        if (in_array('catalog', $selectedModules, true)) {
            $data['catalog'] = Database::fetchAll(
                "SELECT p.id, p.name, p.code, p.catalog_code,
                        COALESCE(p.hsn_code, wc.hsn_code) as hsn_code,
                        COALESCE(p.gst_percentage, wc.gst_rate) as gst_percentage,
                        COALESCE(wc.name, cat.name) as category_name,
                        b.name as brand_name,
                        COALESCE(ws.company, ws.name) as supplier_name,
                        p.source_type, p.is_complementary,
                        (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variants_count,
                        COALESCE((
                            SELECT SUM(sb.quantity) 
                            FROM stock_balances sb
                            JOIN product_variants pv2 ON sb.variant_id = pv2.id
                            WHERE pv2.product_id = p.id AND sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
                            $whStockClause
                        ), 0) as current_warehouse_stock,
                        MIN(pv.mrp) as min_mrp, MAX(pv.mrp) as max_mrp,
                        MIN(pv.selling_price) as min_selling, MAX(pv.selling_price) as max_selling,
                        p.created_at
                 FROM products p
                 LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                 LEFT JOIN categories cat ON p.category_id = cat.id
                 LEFT JOIN brands b ON p.brand_id = b.id
                 LEFT JOIN warehouse_suppliers ws ON p.warehouse_supplier_id = ws.id
                 LEFT JOIN product_variants pv ON pv.product_id = p.id
                 GROUP BY p.id
                 ORDER BY p.id DESC"
            );
        }

        // =========================================================================
        // 4. SUPPLIERS & THIRD-PARTY SELLERS
        // =========================================================================
        if (in_array('suppliers', $selectedModules, true)) {
            $data['suppliers'] = Database::fetchAll(
                "SELECT ws.id, ws.name, ws.company, ws.phone, ws.email,
                        ws.gst_number, ws.seller_type, ws.city, ws.state, ws.payment_terms,
                        ws.material_type, ws.is_active, ws.created_at,
                        (SELECT COUNT(*) FROM products WHERE warehouse_supplier_id = ws.id) as total_catalog_products,
                        COALESCE((SELECT COUNT(*) FROM purchase_orders WHERE warehouse_supplier_id = ws.id), 0) as total_pos,
                        COALESCE((SELECT SUM(total_amount) FROM purchase_orders WHERE warehouse_supplier_id = ws.id), 0.00) as total_purchase_volume
                 FROM warehouse_suppliers ws
                 ORDER BY ws.name ASC"
            );
        }

        // =========================================================================
        // 5. DIRECT MATERIAL / VENDOR PURCHASES (PO Orders)
        // =========================================================================
        if (in_array('purchases', $selectedModules, true)) {
            $data['purchases'] = Database::fetchAll(
                "SELECT po.id, po.po_number, po.order_date, po.expected_date,
                        COALESCE(ws.company, ws.name, s.name) as supplier_name,
                        w.name as warehouse_name,
                        po.subtotal, po.tax_amount, po.total_amount, po.paid_amount,
                        po.payment_status, po.status, po.notes,
                        COALESCE((SELECT SUM(quantity) FROM purchase_items WHERE purchase_order_id = po.id), 0) as total_qty,
                        COALESCE((SELECT COUNT(*) FROM purchase_items WHERE purchase_order_id = po.id), 0) as line_items_count
                 FROM purchase_orders po
                 LEFT JOIN warehouse_suppliers ws ON po.warehouse_supplier_id = ws.id
                 LEFT JOIN sellers s ON po.seller_id = s.id
                 LEFT JOIN warehouses w ON po.warehouse_id = w.id
                 WHERE DATE(po.order_date) BETWEEN ? AND ?
                 $whPoClause
                 ORDER BY po.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 6. SHOP STOCK ALLOCATIONS & REQUISITIONS
        // =========================================================================
        if (in_array('allocations', $selectedModules, true)) {
            $data['allocations'] = Database::fetchAll(
                "SELECT wa.id, wa.allocation_number, wa.destination_type,
                        COALESCE(sh.name, wa.destination_type) as destination_name,
                        w.name as warehouse_name,
                        wa.status, wa.notes, wa.created_at, wa.approved_at,
                        COALESCE((SELECT SUM(requested_qty) FROM allocation_items WHERE allocation_id = wa.id), 0) as requested_pieces,
                        COALESCE((SELECT SUM(approved_qty) FROM allocation_items WHERE allocation_id = wa.id), 0) as approved_pieces,
                        COALESCE((SELECT SUM(packed_qty) FROM allocation_items WHERE allocation_id = wa.id), 0) as packed_pieces,
                        COALESCE((SELECT SUM(dispatched_qty) FROM allocation_items WHERE allocation_id = wa.id), 0) as dispatched_pieces
                 FROM warehouse_allocations wa
                 LEFT JOIN shops sh ON wa.shop_id = sh.id
                 LEFT JOIN warehouses w ON wa.warehouse_id = w.id
                 WHERE DATE(wa.created_at) BETWEEN ? AND ?
                 $whAllocClause
                 ORDER BY wa.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 7. OUTWARD DISPATCHES TO OUTLETS & SHOPS
        // =========================================================================
        if (in_array('outward', $selectedModules, true)) {
            $data['outward'] = Database::fetchAll(
                "SELECT ao.id, ao.outward_number,
                        COALESCE(wa.allocation_number, '-') as allocation_number,
                        COALESCE(sh.name, ao.destination_type) as destination_shop,
                        w.name as warehouse_name,
                        ao.total_boxes,
                        COALESCE(ao.total_quantity, ao.total_pieces, 0) as total_pieces,
                        ao.transporter_name, ao.lr_number, ao.vehicle_number,
                        ao.driver_name, ao.driver_contact,
                        ao.dispatch_date, ao.status, ao.notes
                 FROM allocation_outwards ao
                 LEFT JOIN warehouse_allocations wa ON ao.allocation_id = wa.id
                 LEFT JOIN shops sh ON ao.shop_id = sh.id
                 LEFT JOIN warehouses w ON ao.warehouse_id = w.id
                 WHERE DATE(COALESCE(ao.dispatch_date, ao.created_at)) BETWEEN ? AND ?
                 $whOutClause
                 ORDER BY ao.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 8. DAMAGED & QUARANTINE INVENTORY
        // =========================================================================
        if (in_array('damages', $selectedModules, true)) {
            $data['damages'] = Database::fetchAll(
                "SELECT wd.id, wd.damage_number, wd.tag_code,
                        p.name as product_name, pv.sku,
                        w.name as warehouse_name,
                        wd.source_type, wd.defect_type, wd.damage_reason,
                        wd.quantity, wd.cost_per_unit, wd.total_loss,
                        wd.status, wd.defect_notes, wd.created_at
                 FROM warehouse_damages wd
                 LEFT JOIN product_variants pv ON wd.variant_id = pv.id
                 LEFT JOIN products p ON pv.product_id = p.id
                 LEFT JOIN warehouses w ON wd.warehouse_id = w.id
                 WHERE DATE(wd.created_at) BETWEEN ? AND ?
                 $whDamClause
                 ORDER BY wd.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 9. CUSTOMER & SHOP RETURNS (Warehouse Inward QC)
        // =========================================================================
        if (in_array('returns', $selectedModules, true)) {
            $data['returns'] = Database::fetchAll(
                "SELECT wr.id, wr.return_number, wr.source_type,
                        COALESCE(sh.name, wr.source_type) as source_name,
                        w.name as warehouse_name,
                        wr.reason, wr.status, wr.notes, wr.created_at,
                        COALESCE((SELECT SUM(returned_qty) FROM warehouse_return_items WHERE warehouse_return_id = wr.id), 0) as total_returned_qty,
                        COALESCE((SELECT SUM(good_qty) FROM warehouse_return_items WHERE warehouse_return_id = wr.id), 0) as good_qty,
                        COALESCE((SELECT SUM(damaged_qty + defective_qty) FROM warehouse_return_items WHERE warehouse_return_id = wr.id), 0) as defective_qty
                 FROM warehouse_returns wr
                 LEFT JOIN shops sh ON wr.source_id = sh.id AND wr.source_type = 'SHOP'
                 LEFT JOIN warehouses w ON wr.warehouse_id = w.id
                 WHERE DATE(wr.created_at) BETWEEN ? AND ?
                 $whRetClause
                 ORDER BY wr.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 10. WAREHOUSE OPERATIONAL EXPENSES
        // =========================================================================
        if (in_array('expenses', $selectedModules, true)) {
            $data['expenses'] = Database::fetchAll(
                "SELECT e.id, e.expense_number, e.category, e.item_name,
                        w.name as warehouse_name,
                        e.quantity, e.unit_cost, e.total_cost, e.paid_amount,
                        e.payment_status, e.payment_method, e.paid_to,
                        e.expense_date, e.notes
                 FROM expenses e
                 LEFT JOIN warehouses w ON e.location_id = w.id
                 WHERE e.location_type = 'WAREHOUSE'
                   AND DATE(e.expense_date) BETWEEN ? AND ?
                 $whExpClause
                 ORDER BY e.id DESC",
                [$startDate, $endDate]
            );
        }

        // =========================================================================
        // 11. COMPLEMENTARY PRODUCTS STOCK & POTENTIAL VALUE
        // =========================================================================
        if (in_array('complementary', $selectedModules, true)) {
            $data['complementary'] = Database::fetchAll(
                "SELECT p.id as product_id, p.name as product_name, p.code as product_code,
                        p.catalog_code, pv.sku, s.name as size_name, c.name as color_name,
                        COALESCE((
                            SELECT SUM(sb.quantity) 
                            FROM stock_balances sb 
                            WHERE sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
                            $whStockClause
                        ), 0) as available_qty,
                        pv.selling_price, pv.mrp,
                        ROUND(COALESCE((
                            SELECT SUM(sb.quantity) 
                            FROM stock_balances sb 
                            WHERE sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
                            $whStockClause
                        ), 0) * pv.selling_price, 2) as potential_revenue
                 FROM products p
                 JOIN product_variants pv ON pv.product_id = p.id
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 WHERE p.is_complementary = 1 OR pv.is_complementary = 1
                 ORDER BY p.name ASC, pv.sku ASC"
            );
        }

        // =========================================================================
        // 12. STOCK ADJUSTMENTS
        // =========================================================================
        if (in_array('adjustments', $selectedModules, true)) {
            $data['adjustments'] = \StockAdjustment::getAll([
                'warehouse_id' => $warehouseId,
                'date_from' => $startDate,
                'date_to' => $endDate,
            ]);
            foreach ($data['adjustments'] as &$adjustment) {
                $adjustment['qr_codes_text'] = implode(
                    ', ',
                    \StockAdjustment::getAdjustmentQrCodes($adjustment)
                );
            }
            unset($adjustment);
        }

        // =========================================================================
        // EXECUTIVE AGGREGATE SUMMARY (Financial & Operational Reconciliation)
        // =========================================================================
        $data['summary'] = self::computeExecutiveSummary($startDate, $endDate, $warehouseId);

        return $data;
    }

    /**
     * Compute top-level executive KPI metrics for warehouse operations.
     */
    private static function computeExecutiveSummary(string $startDate, string $endDate, ?int $warehouseId = null): array
    {
        $whStockClause = $warehouseId ? " AND location_id = $warehouseId" : "";
        $whOutClause   = $warehouseId ? " AND warehouse_id = $warehouseId" : "";
        $whDamClause   = $warehouseId ? " AND warehouse_id = $warehouseId" : "";
        $whRetClause   = $warehouseId ? " AND warehouse_id = $warehouseId" : "";
        $whExpClause   = $warehouseId ? " AND location_id = $warehouseId" : "";
        $whInwClause   = $warehouseId ? " AND warehouse_id = $warehouseId" : "";
        $whPoClause    = $warehouseId ? " AND warehouse_id = $warehouseId" : "";
        $whBatchClause = $warehouseId ? " AND destination_warehouse_id = $warehouseId" : "";

        // 1. Current Available Stock & Retail Valuation
        $stockAgg = Database::fetchOne(
            "SELECT COALESCE(SUM(sb.quantity), 0) as total_units,
                    COALESCE(COUNT(DISTINCT sb.variant_id), 0) as total_skus,
                    COALESCE(SUM(sb.quantity * COALESCE(pv.selling_price, 0)), 0) as retail_valuation,
                    COALESCE(SUM(sb.quantity * COALESCE(pv.purchase_price, pv.production_cost, 0)), 0) as cost_valuation
             FROM stock_balances sb
             JOIN product_variants pv ON sb.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             WHERE sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
             $whStockClause"
        );

        // 2. Production Inward Volume in period
        $prodInwardAgg = Database::fetchOne(
            "SELECT COUNT(*) as batches_count,
                    COALESCE(SUM(box_count), 0) as total_boxes,
                    COALESCE(SUM(total_qty), 0) as total_pieces
             FROM production_batches
             WHERE DATE(created_at) BETWEEN ? AND ?
               AND status IN ('DISPATCHED', 'RECEIVED')
             $whBatchClause",
            [$startDate, $endDate]
        );

        // 3. Vendor Direct Inward Volume in period
        $vendorInwardAgg = Database::fetchOne(
            "SELECT COUNT(*) as grn_count,
                    COALESCE(SUM(total_received_qty), 0) as total_pieces
             FROM purchase_inwards
             WHERE DATE(COALESCE(received_date, created_at)) BETWEEN ? AND ?
             $whInwClause",
            [$startDate, $endDate]
        );

        // 4. Outward Dispatches to Outlets in period
        $outwardAgg = Database::fetchOne(
            "SELECT COUNT(*) as dispatches_count,
                    COALESCE(SUM(total_boxes), 0) as total_boxes,
                    COALESCE(SUM(COALESCE(total_quantity, total_pieces, 0)), 0) as total_pieces
             FROM allocation_outwards
             WHERE DATE(COALESCE(dispatch_date, created_at)) BETWEEN ? AND ?
               AND status != 'CANCELLED'
             $whOutClause",
            [$startDate, $endDate]
        );

        // 5. Vendor Material Purchases in period
        $poAgg = Database::fetchOne(
            "SELECT COUNT(*) as po_count,
                    COALESCE(SUM(total_amount), 0) as total_spend,
                    COALESCE(SUM(paid_amount), 0) as total_paid
             FROM purchase_orders
             WHERE DATE(order_date) BETWEEN ? AND ?
               AND status != 'CANCELLED'
             $whPoClause",
            [$startDate, $endDate]
        );

        // 6. Warehouse Operating Expenses in period
        $expAgg = Database::fetchOne(
            "SELECT COUNT(*) as expense_count,
                    COALESCE(SUM(total_cost), 0) as total_cost,
                    COALESCE(SUM(paid_amount), 0) as paid_amount
             FROM expenses
             WHERE location_type = 'WAREHOUSE'
               AND DATE(expense_date) BETWEEN ? AND ?
             $whExpClause",
            [$startDate, $endDate]
        );

        // 7. Damaged / Defective Loss in period
        $damAgg = Database::fetchOne(
            "SELECT COUNT(*) as cases_count,
                    COALESCE(SUM(quantity), 0) as total_pieces,
                    COALESCE(SUM(total_loss), 0) as total_loss
             FROM warehouse_damages
             WHERE DATE(created_at) BETWEEN ? AND ?
             $whDamClause",
            [$startDate, $endDate]
        );

        // 8. Returns Received in period
        $retAgg = Database::fetchOne(
            "SELECT COUNT(*) as returns_count,
                    COALESCE((
                        SELECT SUM(wri.returned_qty) 
                        FROM warehouse_return_items wri 
                        JOIN warehouse_returns wr2 ON wri.warehouse_return_id = wr2.id 
                        WHERE DATE(wr2.created_at) BETWEEN ? AND ?
                        $whRetClause
                    ), 0) as total_pieces
             FROM warehouse_returns wr
             WHERE DATE(wr.created_at) BETWEEN ? AND ?
             $whRetClause",
            [$startDate, $endDate, $startDate, $endDate]
        );

        $totalInwardPcs = (int)($prodInwardAgg['total_pieces'] ?? 0) + (int)($vendorInwardAgg['total_pieces'] ?? 0);
        $totalOutwardPcs = (int)($outwardAgg['total_pieces'] ?? 0);
        $totalExpenditure = (float)($poAgg['total_spend'] ?? 0) + (float)($expAgg['total_cost'] ?? 0);

        // Complementary products in warehouse
        $compAgg = Database::fetchOne(
            "SELECT COALESCE(SUM(sb.quantity), 0) as comp_units,
                    COALESCE(SUM(sb.quantity * COALESCE(pv.selling_price, 0)), 0) as comp_value
             FROM stock_balances sb
             JOIN product_variants pv ON sb.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             WHERE sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
               AND (p.is_complementary = 1 OR pv.is_complementary = 1)
             $whStockClause"
        );

        // Good vs Defective returns
        $retStats = Database::fetchOne(
            "SELECT COALESCE(SUM(wri.good_qty), 0) as good_pieces,
                    COALESCE(SUM(COALESCE(wri.damaged_qty, wri.defective_qty, 0)), 0) as defective_pieces
             FROM warehouse_return_items wri
             JOIN warehouse_returns wr ON wri.warehouse_return_id = wr.id
             WHERE DATE(wr.created_at) BETWEEN ? AND ?
             $whRetClause",
            [$startDate, $endDate]
        );

        $retGoodPcs = (int)($retStats['good_pieces'] ?? 0);
        $retDefPcs = (int)($retStats['defective_pieces'] ?? 0);
        $retTotalPcs = (int)($retAgg['total_pieces'] ?? 0);
        if ($retGoodPcs === 0 && $retDefPcs === 0 && $retTotalPcs > 0) {
            $retGoodPcs = $retTotalPcs;
        }

        return [
            'executive' => [
                'available_stock_units'    => (int)($stockAgg['total_units'] ?? 0),
                'unique_skus'              => (int)($stockAgg['total_skus'] ?? 0),
                'retail_valuation'         => (float)($stockAgg['retail_valuation'] ?? 0.0),
                'cost_valuation'           => (float)($stockAgg['cost_valuation'] ?? 0.0),
                'total_inward_pieces'      => $totalInwardPcs,
                'inward_batches_count'     => (int)($prodInwardAgg['batches_count'] ?? 0) + (int)($vendorInwardAgg['grn_count'] ?? 0),
                'total_outward_pieces'     => $totalOutwardPcs,
                'outward_dispatches_count' => (int)($outwardAgg['dispatches_count'] ?? 0),
                'total_purchases_spend'    => (float)($poAgg['total_spend'] ?? 0.0),
                'purchases_count'          => (int)($poAgg['po_count'] ?? 0),
                'total_expenses'           => (float)($expAgg['total_cost'] ?? 0.0),
                'expenses_count'           => (int)($expAgg['expense_count'] ?? 0),
                'damage_pieces'            => (int)($damAgg['total_pieces'] ?? 0),
                'damage_cost_loss'         => (float)($damAgg['total_loss'] ?? 0.0),
                'returns_total_pieces'     => $retTotalPcs,
                'returns_good_pieces'      => $retGoodPcs,
                'returns_defective_pieces' => $retDefPcs,
                'complementary_units'      => (int)($compAgg['comp_units'] ?? 0),
                'complementary_value'      => (float)($compAgg['comp_value'] ?? 0.0),
            ],
            'stock' => [
                'available_units'     => (int)($stockAgg['total_units'] ?? 0),
                'unique_skus'         => (int)($stockAgg['total_skus'] ?? 0),
                'retail_valuation'    => (float)($stockAgg['retail_valuation'] ?? 0.0),
                'cost_valuation'      => (float)($stockAgg['cost_valuation'] ?? 0.0),
            ],
            'inward' => [
                'prod_batches'        => (int)($prodInwardAgg['batches_count'] ?? 0),
                'prod_pieces'         => (int)($prodInwardAgg['total_pieces'] ?? 0),
                'vendor_grns'         => (int)($vendorInwardAgg['grn_count'] ?? 0),
                'vendor_pieces'       => (int)($vendorInwardAgg['total_pieces'] ?? 0),
                'total_inward_pieces' => $totalInwardPcs,
            ],
            'outward' => [
                'dispatches_count'    => (int)($outwardAgg['dispatches_count'] ?? 0),
                'total_boxes'         => (int)($outwardAgg['total_boxes'] ?? 0),
                'total_pieces'        => $totalOutwardPcs,
            ],
            'financial' => [
                'po_spend'            => (float)($poAgg['total_spend'] ?? 0.0),
                'po_paid'             => (float)($poAgg['total_paid'] ?? 0.0),
                'operating_expenses'  => (float)($expAgg['total_cost'] ?? 0.0),
                'expenses_paid'       => (float)($expAgg['paid_amount'] ?? 0.0),
                'total_expenditure'   => $totalExpenditure,
                'damage_loss'         => (float)($damAgg['total_loss'] ?? 0.0),
            ],
            'damages' => [
                'cases_count'         => (int)($damAgg['cases_count'] ?? 0),
                'damaged_pieces'      => (int)($damAgg['total_pieces'] ?? 0),
                'total_loss'          => (float)($damAgg['total_loss'] ?? 0.0),
            ],
            'returns' => [
                'returns_count'       => (int)($retAgg['returns_count'] ?? 0),
                'returned_pieces'     => (int)($retAgg['total_pieces'] ?? 0),
            ],
        ];
    }

    /**
     * Stream CSV formatted spreadsheet directly to HTTP output (Excel compatible).
     */
    public static function streamCsv(string $periodLabel, string $startDate, string $endDate, array $data, ?array $warehouse = null): void
    {
        $whName = !empty($warehouse['name']) ? strtoupper($warehouse['name']) : 'ALL WAREHOUSES';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="warehouse_report_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel recognition
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        // 1. Warehouse Facility Header
        fputcsv($out, [$whName . ' — COMPREHENSIVE WAREHOUSE OPERATIONS REPORT']);
        if (!empty($warehouse['address'])) {
            fputcsv($out, ['Warehouse Address:', str_replace(["\r\n", "\n", "\r"], ' ', (string)$warehouse['address'])]);
        }
        fputcsv($out, [
            'Facility Code:', $warehouse['code'] ?? '',
            'Phone / Contact:', $warehouse['phone'] ?? '-',
            'Report Scope:', !empty($warehouse['id']) ? "Specific Warehouse #{$warehouse['id']}" : 'All Warehouses'
        ]);
        fputcsv($out, ['Report Period:', $periodLabel, "From: {$startDate}", "To: {$endDate}"]);
        fputcsv($out, ['Generated Timestamp:', date('Y-m-d H:i:s')]);
        fputcsv($out, []); // Blank separator line

        // 2. Executive Operations & Stock Summary
        if (!empty($data['summary'])) {
            $sum = $data['summary'];
            $stk = $sum['stock'];
            $fin = $sum['financial'];
            $inw = $sum['inward'];
            $outw = $sum['outward'];

            fputcsv($out, ['=== EXECUTIVE WAREHOUSE KPI & FINANCIAL SUMMARY ===']);
            fputcsv($out, ['Key Performance Indicator', 'Value', 'Unit / Details']);
            fputcsv($out, ['Current Available Stock', $stk['available_units'], 'Finished Goods Pcs on Warehouse Shelves']);
            fputcsv($out, ['Unique SKUs in Stock', $stk['unique_skus'], 'Active Barcode / SKU Variants']);
            fputcsv($out, ['Retail Inventory Valuation (INR)', number_format($stk['retail_valuation'], 2, '.', ''), 'Estimated Gross Selling Value']);
            fputcsv($out, ['Cost Valuation (INR)', number_format($stk['cost_valuation'], 2, '.', ''), 'Direct Purchase / Production Cost Value']);
            fputcsv($out, ['Total Inward Volume', $inw['total_inward_pieces'] . ' Pieces', "Production: {$inw['prod_pieces']} pcs ({$inw['prod_batches']} batches) + Vendor: {$inw['vendor_pieces']} pcs ({$inw['vendor_grns']} GRNs)"]);
            fputcsv($out, ['Total Outward Dispatched to Shops', $outw['total_pieces'] . ' Pieces', "{$outw['dispatches_count']} Dispatches ({$outw['total_boxes']} Master Boxes)"]);
            fputcsv($out, ['Direct Material Purchases (Spend)', number_format($fin['po_spend'], 2, '.', ''), "Paid: INR " . number_format($fin['po_paid'], 2, '.', '')]);
            fputcsv($out, ['Warehouse Operating Expenses', number_format($fin['operating_expenses'], 2, '.', ''), "Paid: INR " . number_format($fin['expenses_paid'], 2, '.', '')]);
            fputcsv($out, ['Damaged / Quarantine Loss', number_format($fin['damage_loss'], 2, '.', ''), "{$sum['damages']['damaged_pieces']} Pcs in {$sum['damages']['cases_count']} defect cases"]);
            fputcsv($out, ['Customer & Shop Returns Received', $sum['returns']['returned_pieces'] . ' Pieces', "{$sum['returns']['returns_count']} Return Shipments Processed"]);
            fputcsv($out, []);
        }

        // =========================================================================
        // DETAILED MODULE DATA TABLES
        // =========================================================================

        // 1. Inward Shipments
        if (!empty($data['inward'])) {
            fputcsv($out, ['=== 1. WAREHOUSE INWARD SHIPMENTS & GRN (FACTORY & VENDORS) ===']);
            fputcsv($out, ['Inward Date', 'Batch / GRN #', 'Source Type', 'Supplier / Origin', 'Product / PO Info', 'Warehouse', 'Box Count', 'Total Pieces', 'Gate Pass / Outward #', 'Vehicle #', 'Driver & Contact', 'Status']);
            foreach ($data['inward'] as $row) {
                fputcsv($out, [
                    $row['inward_date'],
                    $row['batch_number'],
                    $row['source_type'],
                    $row['supplier_or_source'],
                    $row['product_name'],
                    $row['warehouse_name'] ?? '-',
                    $row['box_count'],
                    $row['total_pieces'],
                    $row['outward_doc_no'],
                    $row['vehicle_number'],
                    $row['driver_name'] . ($row['driver_contact'] !== '-' ? ' (' . $row['driver_contact'] . ')' : ''),
                    $row['status']
                ]);
            }
            fputcsv($out, []);
        }

        // 2. Current Stock
        if (!empty($data['stock'])) {
            fputcsv($out, ['=== 2. CURRENT WAREHOUSE INVENTORY & STOCK BALANCES ===']);
            fputcsv($out, ['Product Name', 'Product Code', 'Catalog Code', 'Category', 'SKU', 'Size', 'Color', 'HSN Code', 'GST %', 'Available Qty', 'Cost Price (INR)', 'Selling Price (INR)', 'Retail Valuation (INR)', 'Cost Valuation (INR)']);
            foreach ($data['stock'] as $row) {
                fputcsv($out, [
                    $row['product_name'],
                    $row['product_code'],
                    $row['catalog_code'] ?? '-',
                    $row['category_name'],
                    $row['sku'],
                    $row['size_name'] ?? '-',
                    $row['color_name'] ?? '-',
                    $row['hsn_code'] ?? '',
                    isset($row['gst_percentage']) ? number_format((float)$row['gst_percentage'], 2, '.', '') : '',
                    $row['available_qty'],
                    number_format((float)$row['cost_price'], 2, '.', ''),
                    number_format((float)$row['selling_price'], 2, '.', ''),
                    number_format((float)$row['retail_valuation'], 2, '.', ''),
                    number_format((float)$row['cost_valuation'], 2, '.', '')
                ]);
            }
            fputcsv($out, []);
        }

        // 3. Catalog Products
        if (!empty($data['catalog'])) {
            fputcsv($out, ['=== 3. WAREHOUSE CATALOG & PRODUCT STYLES ===']);
            fputcsv($out, ['Product Name', 'Style Code', 'Catalog Code', 'Category', 'Brand', 'Supplier', 'HSN Code', 'GST %', 'Source Type', 'Variants Count', 'Current Stock Pcs', 'Min MRP (INR)', 'Max MRP (INR)', 'Min Selling (INR)', 'Max Selling (INR)', 'Created Date']);
            foreach ($data['catalog'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['code'],
                    $row['catalog_code'] ?? '-',
                    $row['category_name'],
                    $row['brand_name'],
                    $row['supplier_name'],
                    $row['hsn_code'] ?? '',
                    isset($row['gst_percentage']) ? number_format((float)$row['gst_percentage'], 2, '.', '') : '',
                    $row['source_type'],
                    $row['variants_count'],
                    $row['current_warehouse_stock'],
                    number_format((float)($row['min_mrp'] ?? 0), 2, '.', ''),
                    number_format((float)($row['max_mrp'] ?? 0), 2, '.', ''),
                    number_format((float)($row['min_selling'] ?? 0), 2, '.', ''),
                    number_format((float)($row['max_selling'] ?? 0), 2, '.', ''),
                    $row['created_at']
                ]);
            }
            fputcsv($out, []);
        }

        // 4. Suppliers Directory
        if (!empty($data['suppliers'])) {
            fputcsv($out, ['=== 4. SUPPLIERS & THIRD-PARTY SELLERS DIRECTORY ===']);
            fputcsv($out, ['Supplier Name', 'Company Name', 'Phone', 'Email', 'GSTIN', 'Seller Type', 'City', 'State', 'Payment Terms', 'Catalog Products Count', 'Total Purchase Orders', 'Total Purchase Value (INR)', 'Status']);
            foreach ($data['suppliers'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['company'] ?? '-',
                    $row['phone'] ?? '-',
                    $row['email'] ?? '-',
                    $row['gst_number'] ?? '-',
                    $row['seller_type'] ?? '-',
                    $row['city'] ?? '-',
                    $row['state'] ?? '-',
                    $row['payment_terms'] ?? '-',
                    $row['total_catalog_products'],
                    $row['total_pos'],
                    number_format((float)$row['total_purchase_volume'], 2, '.', ''),
                    $row['is_active'] ? 'ACTIVE' : 'INACTIVE'
                ]);
            }
            fputcsv($out, []);
        }

        // 5. Purchases
        if (!empty($data['purchases'])) {
            fputcsv($out, ['=== 5. DIRECT MATERIAL / VENDOR PURCHASES (PO ORDERS) ===']);
            fputcsv($out, ['PO Number', 'Order Date', 'Supplier', 'Warehouse', 'Line Items', 'Total Qty', 'Subtotal (INR)', 'Tax Amount (INR)', 'Total Amount (INR)', 'Paid Amount (INR)', 'Payment Status', 'Order Status']);
            foreach ($data['purchases'] as $row) {
                fputcsv($out, [
                    $row['po_number'],
                    $row['order_date'],
                    $row['supplier_name'],
                    $row['warehouse_name'] ?? '-',
                    $row['line_items_count'],
                    $row['total_qty'],
                    number_format((float)$row['subtotal'], 2, '.', ''),
                    number_format((float)$row['tax_amount'], 2, '.', ''),
                    number_format((float)$row['total_amount'], 2, '.', ''),
                    number_format((float)$row['paid_amount'], 2, '.', ''),
                    $row['payment_status'],
                    $row['status']
                ]);
            }
            fputcsv($out, []);
        }

        // 6. Shop Allocations
        if (!empty($data['allocations'])) {
            fputcsv($out, ['=== 6. SHOP STOCK ALLOCATIONS & REQUISITIONS ===']);
            fputcsv($out, ['Allocation #', 'Destination Shop / Channel', 'Warehouse', 'Requested Pcs', 'Approved Pcs', 'Packed Pcs', 'Dispatched Pcs', 'Status', 'Created Date', 'Approved Date']);
            foreach ($data['allocations'] as $row) {
                fputcsv($out, [
                    $row['allocation_number'],
                    $row['destination_name'],
                    $row['warehouse_name'] ?? '-',
                    $row['requested_pieces'],
                    $row['approved_pieces'],
                    $row['packed_pieces'],
                    $row['dispatched_pieces'],
                    $row['status'],
                    $row['created_at'],
                    $row['approved_at'] ?? '-'
                ]);
            }
            fputcsv($out, []);
        }

        // 7. Outward Dispatches
        if (!empty($data['outward'])) {
            fputcsv($out, ['=== 7. OUTWARD DISPATCHES TO OUTLETS & SHOPS ===']);
            fputcsv($out, ['Outward #', 'Allocation #', 'Destination Shop', 'Warehouse', 'Total Boxes', 'Total Pieces', 'Transporter Name', 'LR / Docket #', 'Vehicle #', 'Driver & Contact', 'Dispatch Date', 'Status']);
            foreach ($data['outward'] as $row) {
                fputcsv($out, [
                    $row['outward_number'],
                    $row['allocation_number'],
                    $row['destination_shop'],
                    $row['warehouse_name'] ?? '-',
                    $row['total_boxes'],
                    $row['total_pieces'],
                    $row['transporter_name'] ?? '-',
                    $row['lr_number'] ?? '-',
                    $row['vehicle_number'] ?? '-',
                    $row['driver_name'] . (!empty($row['driver_contact']) ? ' (' . $row['driver_contact'] . ')' : ''),
                    $row['dispatch_date'] ?? '-',
                    $row['status']
                ]);
            }
            fputcsv($out, []);
        }

        // 8. Damages
        if (!empty($data['damages'])) {
            fputcsv($out, ['=== 8. DAMAGED & DEFECTIVE QUARANTINE INVENTORY ===']);
            fputcsv($out, ['Damage #', 'Tag / QR Code', 'Product Name', 'SKU', 'Warehouse', 'Source Stage', 'Defect Type', 'Reason', 'Quantity', 'Cost/Unit (INR)', 'Total Loss (INR)', 'Status', 'Logged Date']);
            foreach ($data['damages'] as $row) {
                fputcsv($out, [
                    $row['damage_number'],
                    $row['tag_code'] ?? '-',
                    $row['product_name'] ?? '-',
                    $row['sku'] ?? '-',
                    $row['warehouse_name'] ?? '-',
                    $row['source_type'] ?? '-',
                    $row['defect_type'] ?? '-',
                    $row['damage_reason'] ?? '-',
                    $row['quantity'],
                    number_format((float)$row['cost_per_unit'], 2, '.', ''),
                    number_format((float)$row['total_loss'], 2, '.', ''),
                    $row['status'],
                    $row['created_at']
                ]);
            }
            fputcsv($out, []);
        }

        // 9. Returns
        if (!empty($data['returns'])) {
            fputcsv($out, ['=== 9. CUSTOMER & SHOP RETURNS (WAREHOUSE INWARD QC) ===']);
            fputcsv($out, ['Return #', 'Source Type', 'Origin / Shop Name', 'Warehouse', 'Total Returned Pcs', 'Good Pcs Restocked', 'Defective Pcs Quarantined', 'Status', 'Reason', 'Created Date']);
            foreach ($data['returns'] as $row) {
                fputcsv($out, [
                    $row['return_number'],
                    $row['source_type'],
                    $row['source_name'] ?? '-',
                    $row['warehouse_name'] ?? '-',
                    $row['total_returned_qty'],
                    $row['good_qty'],
                    $row['defective_qty'],
                    $row['status'],
                    $row['reason'] ?? '-',
                    $row['created_at']
                ]);
            }
            fputcsv($out, []);
        }

        // 10. Expenses
        if (!empty($data['expenses'])) {
            fputcsv($out, ['=== 10. WAREHOUSE OPERATIONAL EXPENSES ===']);
            fputcsv($out, ['Expense #', 'Category', 'Item / Purpose', 'Warehouse', 'Quantity', 'Unit Cost (INR)', 'Total Cost (INR)', 'Paid (INR)', 'Payment Status', 'Payment Method', 'Paid To', 'Expense Date']);
            foreach ($data['expenses'] as $row) {
                fputcsv($out, [
                    $row['expense_number'] ?? '-',
                    $row['category'] ?? '-',
                    $row['item_name'],
                    $row['warehouse_name'] ?? '-',
                    $row['quantity'],
                    number_format((float)$row['unit_cost'], 2, '.', ''),
                    number_format((float)$row['total_cost'], 2, '.', ''),
                    number_format((float)$row['paid_amount'], 2, '.', ''),
                    $row['payment_status'],
                    $row['payment_method'] ?? '-',
                    $row['paid_to'] ?? '-',
                    $row['expense_date']
                ]);
            }
            fputcsv($out, []);
        }

        // 11. Complementary Products
        if (!empty($data['complementary'])) {
            fputcsv($out, ['=== 11. COMPLEMENTARY PRODUCTS STOCK & POTENTIAL VALUE ===']);
            fputcsv($out, ['Product Name', 'Product Code', 'Catalog Code', 'SKU', 'Size', 'Color', 'Available Qty', 'MRP (INR)', 'Selling Price (INR)', 'Potential Valuation (INR)']);
            foreach ($data['complementary'] as $row) {
                fputcsv($out, [
                    $row['product_name'],
                    $row['product_code'],
                    $row['catalog_code'] ?? '-',
                    $row['sku'],
                    $row['size_name'] ?? '-',
                    $row['color_name'] ?? '-',
                    $row['available_qty'],
                    number_format((float)$row['mrp'], 2, '.', ''),
                    number_format((float)$row['selling_price'], 2, '.', ''),
                    number_format((float)$row['potential_revenue'], 2, '.', '')
                ]);
            }
            fputcsv($out, []);
        }

        // 12. Stock Adjustments
        if (!empty($data['adjustments'])) {
            fputcsv($out, ['=== 12. WAREHOUSE STOCK ADJUSTMENTS ===']);
            fputcsv($out, ['Adjustment #', 'Created At', 'Warehouse', 'Product', 'SKU', 'Size', 'Color', 'Adjustment Type', 'Quantity', 'Reason', 'Notes', 'QR Codes', 'Status', 'Requested By', 'Approved By', 'Approved At']);
            foreach ($data['adjustments'] as $row) {
                fputcsv($out, [
                    $row['adjustment_number'],
                    $row['created_at'],
                    $row['warehouse_name'],
                    $row['product_name'],
                    $row['sku'],
                    $row['size_name'] ?? '',
                    $row['color_name'] ?? '',
                    $row['adjustment_type'],
                    $row['quantity'],
                    $row['reason'],
                    $row['notes'] ?? '',
                    $row['qr_codes_text'] ?? '',
                    $row['status'],
                    $row['requested_by_name'] ?? '',
                    $row['approved_by_name'] ?? '',
                    $row['approved_at'] ?? '',
                ]);
            }
            fputcsv($out, []);
        }

        fclose($out);
        exit;
    }
}
