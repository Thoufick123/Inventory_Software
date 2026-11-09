<?php
$shop = $shop ?? [
    'name'    => 'Retail Shop Outlet',
    'code'    => 'SHOP-01',
    'phone'   => '+91 98765 43210',
    'address' => 'Retail Outlet & Point of Sale',
];
$summary = $data['summary'] ?? [];
$fin = $summary['financial'] ?? [];
$stk = $summary['stock'] ?? [];
$dam = $summary['damages'] ?? [];
$selectedModules = $selectedModules ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($shop['name'] ?? 'Shop') ?> — Retail Operations &amp; Sales Master Report (<?= htmlspecialchars($periodLabel ?? '') ?>)</title>
    <style>
        * { box-sizing: border-box; }
        body { font: 12px/1.4 "Segoe UI", Arial, sans-serif; color: #1e293b; background: #f8fafc; margin: 0; padding: 24px; }
        .report-wrap { max-width: 1200px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .actions { max-width: 1200px; margin: 0 auto 16px; text-align: right; }
        .btn-print { padding: 9px 20px; background: #16a34a; color: #fff; font-weight: 600; font-size: 13px; border: none; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background .15s; }
        .btn-print:hover { background: #15803d; }

        /* Header */
        .facility-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 18px; margin-bottom: 22px; }
        .facility-title { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 6px; letter-spacing: -0.02em; }
        .facility-meta { font-size: 12px; color: #475569; line-height: 1.5; }
        .period-box { text-align: right; }
        .period-badge { display: inline-block; background: #0f172a; color: #fff; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; padding: 4px 10px; border-radius: 4px; margin-bottom: 6px; }
        .period-dates { font-size: 14px; font-weight: 700; color: #0f172a; }
        .period-time { font-size: 11px; color: #64748b; margin-top: 4px; }

        /* Executive Financial & Stock Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; }
        .kpi-card.revenue { border-left: 4px solid #16a34a; background: #f0fdf4; }
        .kpi-card.orders { border-left: 4px solid #2563eb; background: #eff6ff; }
        .kpi-card.stock { border-left: 4px solid #0284c7; background: #f0f9ff; }
        .kpi-card.realized { border-left: 4px solid #8b5cf6; background: #f5f3ff; }
        .kpi-card.returns { border-left: 4px solid #dc2626; background: #fef2f2; }
        .kpi-card.expense { border-left: 4px solid #d97706; background: #fffbeb; }
        .kpi-card.damage { border-left: 4px solid #e11d48; background: #fff1f2; }
        .kpi-card.inward { border-left: 4px solid #0d9488; background: #f0fdfa; }

        .kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px; }
        .kpi-val { font-size: 19px; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 4px; }
        .kpi-sub { font-size: 11px; color: #475569; }

        /* Summary Panels */
        .summary-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .panel-header { background: #f1f5f9; padding: 10px 14px; font-size: 12px; font-weight: 700; color: #1e293b; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.03em; }
        .panel-body { padding: 12px 14px; }
        .summary-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        .summary-table td { padding: 5px 4px; border-bottom: 1px dashed #e2e8f0; }
        .summary-table tr:last-child td { border-bottom: none; }
        .summary-table td.strong { font-weight: 700; color: #0f172a; }
        .summary-table tr.total-row td { border-top: 2px solid #cbd5e1; border-bottom: 2px solid #cbd5e1; font-weight: 800; background: #f8fafc; padding: 7px 4px; }

        /* Section Headings */
        .section { margin: 26px 0; page-break-inside: avoid; }
        .section-header { display: flex; justify-content: space-between; align-items: center; background: #f1f5f9; padding: 8px 12px; border-left: 4px solid #16a34a; border-radius: 0 4px 4px 0; margin-bottom: 10px; }
        .section-title { font-size: 13.5px; font-weight: 700; color: #0f172a; margin: 0; }
        .section-count { font-size: 11px; font-weight: 600; color: #64748b; }

        /* Data Tables */
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 11px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.data-table th { background: #f8fafc; font-weight: 700; color: #334155; text-transform: uppercase; font-size: 10px; letter-spacing: 0.03em; }
        table.data-table tbody tr:nth-child(even) { background: #fcfcfd; }
        table.data-table tbody tr:hover { background: #f1f5f9; }
        .right { text-align: right !important; }
        .center { text-align: center !important; }
        .fw-bold { font-weight: 700; }
        .text-success { color: #16a34a !important; }
        .text-danger { color: #dc2626 !important; }
        .text-warning { color: #d97706 !important; }
        .text-muted { color: #64748b !important; }
        .badge { display: inline-block; padding: 2px 6px; font-size: 9.5px; font-weight: 700; border-radius: 4px; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-secondary { background: #f1f5f9; color: #475569; }

        /* Footer */
        .report-footer { margin-top: 40px; padding-top: 14px; border-top: 1px solid #e2e8f0; font-size: 10.5px; color: #64748b; text-align: center; }

        /* Print media styles */
        @media print {
            body { margin: 0; padding: 0; background: #fff; font-size: 10.5px; color: #000; }
            .report-wrap { max-width: none; width: 100%; padding: 0; box-shadow: none; border-radius: 0; }
            .actions { display: none !important; }
            .section { page-break-inside: avoid; }
            table.data-table th { background: #eee !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .kpi-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; border: 1px solid #ccc !important; }
            .panel { -webkit-print-color-adjust: exact; print-color-adjust: exact; border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body>

<div class="actions">
    <button type="button" class="btn-print" onclick="window.print()">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
        Print / Save PDF
    </button>
</div>

<div class="report-wrap">
    <!-- Facility Header -->
    <div class="facility-header">
        <div>
            <div class="facility-title"><?= htmlspecialchars($shop['name'] ?? 'Retail Shop Outlet') ?></div>
            <div class="facility-meta">
                <div><strong>Outlet Code:</strong> <?= htmlspecialchars($shop['code'] ?? 'SHOP-01') ?> &bull; <strong>Contact:</strong> <?= htmlspecialchars($shop['phone'] ?? '-') ?></div>
                <?php if (!empty($shop['address'])): ?>
                    <div><strong>Address:</strong> <?= htmlspecialchars($shop['address']) ?></div>
                <?php endif; ?>
                <div><strong>Report Type:</strong> Retail Shop Master Operations, Sales, Inward &amp; Financial Audit</div>
            </div>
        </div>
        <div class="period-box">
            <span class="period-badge">Report Period</span>
            <div class="period-dates"><?= htmlspecialchars($periodLabel ?? '') ?></div>
            <div class="period-time">Date Range: <?= htmlspecialchars($startDate) ?> &rarr; <?= htmlspecialchars($endDate) ?></div>
            <div class="period-time">Generated: <?= date('d M Y, h:i A') ?> IST</div>
        </div>
    </div>

    <!-- Executive KPI Grid -->
    <?php if (!empty($fin)): ?>
    <div class="kpi-grid">
        <div class="kpi-card revenue">
            <div class="kpi-label">Gross Sales Revenue</div>
            <div class="kpi-val text-success">₹<?= number_format($fin['total_revenue'] ?? 0, 2) ?></div>
            <div class="kpi-sub"><?= number_format($fin['total_invoices'] ?? 0) ?> completed billing invoices</div>
        </div>
        <div class="kpi-card orders">
            <div class="kpi-label">Total Pieces Sold</div>
            <div class="kpi-val"><?= number_format($fin['total_pieces_sold'] ?? 0) ?> pcs</div>
            <div class="kpi-sub">Across <?= number_format($fin['total_invoices'] ?? 0) ?> POS customer orders</div>
        </div>
        <div class="kpi-card stock">
            <div class="kpi-label">Available Stock &amp; Valuation</div>
            <div class="kpi-val"><?= number_format($stk['available_pieces'] ?? 0) ?> pcs</div>
            <div class="kpi-sub">Valuation: ₹<?= number_format($stk['total_valuation'] ?? 0, 2) ?></div>
        </div>
        <div class="kpi-card realized">
            <div class="kpi-label">Net Realized Cash</div>
            <div class="kpi-val">₹<?= number_format($fin['net_realized'] ?? 0, 2) ?></div>
            <div class="kpi-sub">Gross Sales - Refunds - Expenses</div>
        </div>
    </div>

    <!-- Financial Breakdown Panels -->
    <div class="summary-panels">
        <!-- Panel 1: Revenue by Payment Method -->
        <div class="panel">
            <div class="panel-header">Payment Mode Realization</div>
            <div class="panel-body">
                <table class="summary-table">
                    <tr>
                        <td>Cash Payments (Drawer Receipts)</td>
                        <td class="right strong">₹<?= number_format($fin['cash_revenue'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>UPI Payments (QR / Digital)</td>
                        <td class="right strong">₹<?= number_format($fin['upi_revenue'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Card Payments (Debit / Credit)</td>
                        <td class="right strong">₹<?= number_format($fin['card_revenue'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Total GST Collected (CGST + SGST)</td>
                        <td class="right strong">₹<?= number_format($fin['total_tax'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Customer Promotional Discounts Given</td>
                        <td class="right strong text-danger">-₹<?= number_format($fin['total_discount'] ?? 0, 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Total Billed Sales Volume</td>
                        <td class="right text-success">₹<?= number_format($fin['total_revenue'] ?? 0, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Panel 2: Outflows & Cash Adjustments -->
        <div class="panel">
            <div class="panel-header">Refunds, Expenses &amp; Stock Health</div>
            <div class="panel-body">
                <table class="summary-table">
                    <tr>
                        <td>Customer Returns (<?= number_format($fin['returns_count'] ?? 0) ?> cases)</td>
                        <td class="right strong text-danger">-₹<?= number_format($fin['refund_total'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Petty Cash Expenses (<?= number_format($fin['expenses_count'] ?? 0) ?> vouchers)</td>
                        <td class="right strong text-danger">-₹<?= number_format($fin['expense_total'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Damaged / Defective Stock Pieces</td>
                        <td class="right strong"><?= number_format($stk['damaged_pieces'] ?? 0) ?> pcs</td>
                    </tr>
                    <tr>
                        <td>Estimated Damage Value Loss</td>
                        <td class="right strong text-danger">₹<?= number_format($dam['total_loss'] ?? 0, 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Net Operating Realization</td>
                        <td class="right text-success">₹<?= number_format($fin['net_realized'] ?? 0, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: POS SALES INVOICES -->
    <!-- ========================================================================= -->
    <?php if (in_array('sales', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">1. POS Sales Invoices &amp; Billing History</h4>
            <span class="section-count"><?= count($data['sales'] ?? []) ?> Invoices</span>
        </div>
        <?php if (!empty($data['sales'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date &amp; Time</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th class="center">Items</th>
                    <th class="center">Pieces</th>
                    <th class="right">Subtotal</th>
                    <th class="right">Discount</th>
                    <th class="right">GST</th>
                    <th class="right">Total</th>
                    <th class="center">Payment</th>
                    <th class="center">Status</th>
                    <th>Cashier</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['sales'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['invoice_number']) ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                    <td><?= htmlspecialchars($row['customer_phone']) ?></td>
                    <td class="center"><?= (int)$row['items_count'] ?></td>
                    <td class="center"><?= (int)$row['total_pieces'] ?></td>
                    <td class="right">₹<?= number_format($row['subtotal'], 2) ?></td>
                    <td class="right text-danger">₹<?= number_format($row['discount_amount'], 2) ?></td>
                    <td class="right">₹<?= number_format($row['tax_amount'], 2) ?></td>
                    <td class="right fw-bold text-success">₹<?= number_format($row['total_amount'], 2) ?></td>
                    <td class="center"><span class="badge badge-info"><?= htmlspecialchars($row['payment_method']) ?></span></td>
                    <td class="center"><span class="badge badge-success"><?= htmlspecialchars($row['status']) ?></span></td>
                    <td><?= htmlspecialchars($row['biller_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No sales invoices recorded in this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: SELLED PRODUCTS DETAILS -->
    <!-- ========================================================================= -->
    <?php if (in_array('sale_items', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">2. Selled Products / Itemized Sales Breakdown</h4>
            <span class="section-count"><?= count($data['sale_items'] ?? []) ?> Items Sold</span>
        </div>
        <?php if (!empty($data['sale_items'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Sale Date</th>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Size</th>
                    <th>Color</th>
                    <th class="center">Quantity</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Discount</th>
                    <th class="right">Total</th>
                    <th>Scanned QR / Tags</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['sale_items'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['invoice_number']) ?></td>
                    <td><?= date('d M Y', strtotime($row['sale_date'])) ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><code><?= htmlspecialchars($row['sku']) ?></code></td>
                    <td><?= htmlspecialchars($row['size_name']) ?></td>
                    <td><?= htmlspecialchars($row['color_name']) ?></td>
                    <td class="center fw-bold"><?= (int)$row['quantity'] ?></td>
                    <td class="right">₹<?= number_format($row['unit_price'], 2) ?></td>
                    <td class="right text-danger">₹<?= number_format($row['discount_amount'], 2) ?></td>
                    <td class="right fw-bold text-success">₹<?= number_format($row['total_amount'], 2) ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($row['scanned_qr_codes']) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No sold item records found for this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: CURRENT SHOP INVENTORY -->
    <!-- ========================================================================= -->
    <?php if (in_array('stock', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">3. Current Shop Stock &amp; Inventory Valuation</h4>
            <span class="section-count"><?= count($data['stock'] ?? []) ?> SKUs</span>
        </div>
        <?php if (!empty($data['stock'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Size</th>
                    <th>Color</th>
                    <th class="center">Stock Quantity</th>
                    <th class="center">State</th>
                    <th class="right">MRP</th>
                    <th class="right">Retail Selling Price</th>
                    <th class="right">Valuation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['stock'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><code><?= htmlspecialchars($row['sku']) ?></code></td>
                    <td><?= htmlspecialchars($row['size_name']) ?></td>
                    <td><?= htmlspecialchars($row['color_name']) ?></td>
                    <td class="center fw-bold text-success"><?= number_format($row['stock_quantity']) ?> pcs</td>
                    <td class="center"><span class="badge badge-success"><?= htmlspecialchars($row['stock_state']) ?></span></td>
                    <td class="right">₹<?= number_format($row['mrp'], 2) ?></td>
                    <td class="right">₹<?= number_format($row['selling_price'], 2) ?></td>
                    <td class="right fw-bold">₹<?= number_format($row['valuation'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No stock balance records found for this shop.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: INWARD FROM CENTRAL WAREHOUSE -->
    <!-- ========================================================================= -->
    <?php if (in_array('inward', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">4. Inward Stock Allocations from Warehouse</h4>
            <span class="section-count"><?= count($data['inward'] ?? []) ?> Shipments</span>
        </div>
        <?php if (!empty($data['inward'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Allocation #</th>
                    <th>Dispatch Date</th>
                    <th>Source Warehouse</th>
                    <th class="center">Status</th>
                    <th class="center">Requested Qty</th>
                    <th class="center">Received Qty</th>
                    <th class="center">Damaged Qty</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['inward'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['allocation_number']) ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($row['dispatch_date'])) ?></td>
                    <td><?= htmlspecialchars($row['source_warehouse']) ?></td>
                    <td class="center"><span class="badge badge-info"><?= htmlspecialchars($row['status']) ?></span></td>
                    <td class="center"><?= number_format($row['total_requested_qty']) ?> pcs</td>
                    <td class="center fw-bold text-success"><?= number_format($row['total_received_qty']) ?> pcs</td>
                    <td class="center text-danger"><?= number_format($row['total_damaged_qty']) ?> pcs</td>
                    <td><?= htmlspecialchars($row['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No inward shipments received in this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: CUSTOMER RETURNS -->
    <!-- ========================================================================= -->
    <?php if (in_array('returns', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">5. Customer Returns &amp; Counter Refunds</h4>
            <span class="section-count"><?= count($data['returns'] ?? []) ?> Returns</span>
        </div>
        <?php if (!empty($data['returns'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Return #</th>
                    <th>Date &amp; Time</th>
                    <th>Invoice #</th>
                    <th>Customer Name</th>
                    <th>Return Type</th>
                    <th>Reason</th>
                    <th class="right">Refund Amount</th>
                    <th class="center">Refund Mode</th>
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['returns'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['return_number']) ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                    <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                    <td><?= htmlspecialchars($row['return_type']) ?></td>
                    <td><?= htmlspecialchars($row['reason']) ?></td>
                    <td class="right fw-bold text-danger">₹<?= number_format($row['refund_amount'], 2) ?></td>
                    <td class="center"><span class="badge badge-secondary"><?= htmlspecialchars($row['refund_mode']) ?></span></td>
                    <td class="center"><span class="badge badge-success"><?= htmlspecialchars($row['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No customer returns logged in this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: RETURNS TO WAREHOUSE -->
    <!-- ========================================================================= -->
    <?php if (in_array('return_to_warehouse', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">6. Stock Returns Dispatched to Warehouse</h4>
            <span class="section-count"><?= count($data['return_to_warehouse'] ?? []) ?> Dispatches</span>
        </div>
        <?php if (!empty($data['return_to_warehouse'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Return #</th>
                    <th>Date</th>
                    <th>Destination Warehouse</th>
                    <th class="center">Returned Pieces</th>
                    <th>Reason</th>
                    <th class="center">Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['return_to_warehouse'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['return_number']) ?></td>
                    <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                    <td><?= htmlspecialchars($row['destination_warehouse']) ?></td>
                    <td class="center fw-bold"><?= number_format($row['total_returned_qty']) ?> pcs</td>
                    <td><?= htmlspecialchars($row['reason']) ?></td>
                    <td class="center"><span class="badge badge-info"><?= htmlspecialchars($row['status']) ?></span></td>
                    <td><?= htmlspecialchars($row['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No stock returns to warehouse recorded in this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: CASH REGISTERS -->
    <!-- ========================================================================= -->
    <?php if (in_array('cash_register', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">7. Cash Register Shifts &amp; Drawer Reconciliations</h4>
            <span class="section-count"><?= count($data['cash_register'] ?? []) ?> Shifts</span>
        </div>
        <?php if (!empty($data['cash_register'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Register #</th>
                    <th>Cashier</th>
                    <th>Opening Time</th>
                    <th>Closing Time</th>
                    <th class="right">Open Float</th>
                    <th class="right">Cash Sales</th>
                    <th class="right">UPI Sales</th>
                    <th class="right">Card Sales</th>
                    <th class="right">Expected Cash</th>
                    <th class="right">Counted Cash</th>
                    <th class="right">Discrepancy</th>
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['cash_register'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['register_number']) ?></td>
                    <td><?= htmlspecialchars($row['cashier_name']) ?></td>
                    <td><?= date('d M, h:i A', strtotime($row['opening_time'])) ?></td>
                    <td><?= !empty($row['closing_time']) ? date('d M, h:i A', strtotime($row['closing_time'])) : '-' ?></td>
                    <td class="right">₹<?= number_format($row['opening_balance'], 2) ?></td>
                    <td class="right text-success">₹<?= number_format($row['cash_sales_amount'], 2) ?></td>
                    <td class="right">₹<?= number_format($row['upi_sales_amount'], 2) ?></td>
                    <td class="right">₹<?= number_format($row['card_sales_amount'], 2) ?></td>
                    <td class="right">₹<?= number_format($row['expected_cash'], 2) ?></td>
                    <td class="right fw-bold">₹<?= number_format($row['closing_cash_counted'], 2) ?></td>
                    <td class="right <?= $row['cash_difference'] < 0 ? 'text-danger fw-bold' : ($row['cash_difference'] > 0 ? 'text-success fw-bold' : '') ?>">
                        <?= $row['cash_difference'] != 0 ? '₹' . number_format($row['cash_difference'], 2) : '₹0.00' ?>
                    </td>
                    <td class="center"><span class="badge badge-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No cash register shifts logged in this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: PETTY CASH EXPENSES -->
    <!-- ========================================================================= -->
    <?php if (in_array('cash_expenses', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">8. Petty Cash Expenses &amp; Drawer Disbursals</h4>
            <span class="section-count"><?= count($data['cash_expenses'] ?? []) ?> Vouchers</span>
        </div>
        <?php if (!empty($data['cash_expenses'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Voucher #</th>
                    <th>Date &amp; Time</th>
                    <th>Expense Category</th>
                    <th>Paid To</th>
                    <th class="right">Amount</th>
                    <th class="center">Payment Mode</th>
                    <th>Bill #</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['cash_expenses'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['voucher_number']) ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($row['expense_category']) ?></span></td>
                    <td><?= htmlspecialchars($row['paid_to']) ?></td>
                    <td class="right fw-bold text-danger">₹<?= number_format($row['amount'], 2) ?></td>
                    <td class="center"><?= htmlspecialchars($row['payment_mode']) ?></td>
                    <td><?= htmlspecialchars($row['bill_number']) ?></td>
                    <td><?= htmlspecialchars($row['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No petty cash expense vouchers recorded in this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: SHOP DAMAGES -->
    <!-- ========================================================================= -->
    <?php if (in_array('damages', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">9. Shop Damaged / Defective Stock Items</h4>
            <span class="section-count"><?= count($data['damages'] ?? []) ?> Incidents</span>
        </div>
        <?php if (!empty($data['damages'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Damage #</th>
                    <th>Date &amp; Time</th>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Tag / QR Code</th>
                    <th class="center">Quantity</th>
                    <th class="right">Total Loss</th>
                    <th>Damage Reason</th>
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['damages'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['damage_number']) ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><code><?= htmlspecialchars($row['sku']) ?></code></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($row['tag_code']) ?></span></td>
                    <td class="center fw-bold"><?= (int)$row['quantity'] ?></td>
                    <td class="right text-danger fw-bold">₹<?= number_format($row['total_loss'], 2) ?></td>
                    <td><?= htmlspecialchars($row['damage_reason']) ?></td>
                    <td class="center"><span class="badge badge-danger"><?= htmlspecialchars($row['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No damaged or defective items recorded in this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- MODULE: SHOP CUSTOMERS DIRECTORY -->
    <!-- ========================================================================= -->
    <?php if (in_array('customers', $selectedModules, true)): ?>
    <div class="section">
        <div class="section-header">
            <h4 class="section-title">10. Registered Customers Directory</h4>
            <span class="section-count"><?= count($data['customers'] ?? []) ?> Customers</span>
        </div>
        <?php if (!empty($data['customers'])): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>City</th>
                    <th>State</th>
                    <th class="right">Total Purchases</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['customers'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['city']) ?></td>
                    <td><?= htmlspecialchars($row['state']) ?></td>
                    <td class="right fw-bold text-success">₹<?= number_format($row['total_purchases'], 2) ?></td>
                    <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No customers registered in this period.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Report Footer -->
    <div class="report-footer">
        <div>Generated by <strong><?= htmlspecialchars($shop['name'] ?? 'Retail Shop Outlet') ?></strong> POS System &bull; GRM Inventory Management System v1.0</div>
        <div style="margin-top: 4px; color: #94a3b8;">Confidential Internal Document &bull; Validated with Official Database Records</div>
    </div>
</div>

</body>
</html>
