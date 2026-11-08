<?php
$warehouse = $warehouse ?? [
    'name'    => 'Central Warehouses Network',
    'code'    => 'WH-ALL',
    'phone'   => '+91 98765 43210',
    'address' => 'Central Hub & Distribution Facility',
];
$company = $company ?? [];
$summary = $data['summary'] ?? [];
$exec = $summary['executive'] ?? [];
$selectedModules = $selectedModules ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($warehouse['name'] ?? 'Warehouse') ?> — Operations &amp; Inventory Master Report (<?= htmlspecialchars($periodLabel ?? '') ?>)</title>
    <style>
        * { box-sizing: border-box; }
        body { font: 12px/1.4 "Segoe UI", Arial, sans-serif; color: #1e293b; background: #f8fafc; margin: 0; padding: 24px; }
        .report-wrap { max-width: 1200px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .actions { max-width: 1200px; margin: 0 auto 16px; text-align: right; }
        .btn-print { padding: 9px 20px; background: #2563eb; color: #fff; font-weight: 600; font-size: 13px; border: none; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background .15s; }
        .btn-print:hover { background: #1d4ed8; }

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
        .kpi-card.stock { border-left: 4px solid #2563eb; background: #eff6ff; }
        .kpi-card.revenue { border-left: 4px solid #16a34a; background: #f0fdf4; }
        .kpi-card.inward { border-left: 4px solid #0284c7; background: #f0f9ff; }
        .kpi-card.outward { border-left: 4px solid #8b5cf6; background: #f5f3ff; }
        .kpi-card.spend { border-left: 4px solid #d97706; background: #fffbeb; }
        .kpi-card.expense { border-left: 4px solid #64748b; background: #f8fafc; }
        .kpi-card.damage { border-left: 4px solid #ef4444; background: #fef2f2; }
        .kpi-card.return { border-left: 4px solid #0d9488; background: #f0fdfa; }

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
        .section-header { display: flex; justify-content: space-between; align-items: center; background: #f1f5f9; padding: 8px 12px; border-left: 4px solid #2563eb; border-radius: 0 4px 4px 0; margin-bottom: 10px; }
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
    <button class="btn-print" onclick="window.print()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Print Report
    </button>
</div>

<main class="report-wrap">
    <!-- Facility Header -->
    <header class="facility-header">
        <div>
            <h1 class="facility-title"><?= htmlspecialchars($warehouse['name'] ?? 'Warehouse Operations') ?></h1>
            <div class="facility-meta">
                <div><strong>Code:</strong> <?= htmlspecialchars($warehouse['code'] ?? 'WH') ?> &bull; <strong>Contact:</strong> <?= htmlspecialchars($warehouse['phone'] ?? '+91 98765 43210') ?></div>
                <div><strong>Address:</strong> <?= htmlspecialchars($warehouse['address'] ?? 'Main Distribution Facility') ?></div>
                <?php if (!empty($company['company_name'])): ?>
                    <div><strong>Enterprise:</strong> <?= htmlspecialchars($company['company_name']) ?> <?php if (!empty($company['gstin'])): ?> &bull; <strong>GSTIN:</strong> <?= htmlspecialchars($company['gstin']) ?><?php endif; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="period-box">
            <span class="period-badge">Warehouse Report</span>
            <div class="period-dates"><?= htmlspecialchars($periodLabel ?? 'Custom Range') ?></div>
            <div class="period-time">Date Span: <?= htmlspecialchars($startDate ?? '') ?> to <?= htmlspecialchars($endDate ?? '') ?></div>
            <div class="period-time">Generated: <?= date('d M Y, h:i A') ?></div>
        </div>
    </header>

    <!-- Executive KPI Grid -->
    <section class="kpi-grid">
        <div class="kpi-card stock">
            <div class="kpi-label">Available Inventory</div>
            <div class="kpi-val"><?= number_format($exec['available_stock_units'] ?? 0) ?> pcs</div>
            <div class="kpi-sub"><?= number_format($exec['unique_skus'] ?? 0) ?> active SKUs &bull; ₹<?= number_format($exec['cost_valuation'] ?? 0, 2) ?> cost</div>
        </div>
        <div class="kpi-card revenue">
            <div class="kpi-label">Retail Selling Valuation</div>
            <div class="kpi-val">₹<?= number_format($exec['retail_valuation'] ?? 0, 2) ?></div>
            <div class="kpi-sub">Potential retail asset value across warehouse</div>
        </div>
        <div class="kpi-card inward">
            <div class="kpi-label">Total Inward Received</div>
            <div class="kpi-val"><?= number_format($exec['total_inward_pieces'] ?? 0) ?> pcs</div>
            <div class="kpi-sub"><?= number_format($exec['inward_batches_count'] ?? 0) ?> factory batches &amp; vendor GRNs</div>
        </div>
        <div class="kpi-card outward">
            <div class="kpi-label">Outward Dispatched</div>
            <div class="kpi-val"><?= number_format($exec['total_outward_pieces'] ?? 0) ?> pcs</div>
            <div class="kpi-sub"><?= number_format($exec['outward_dispatches_count'] ?? 0) ?> dispatches to shops / channels</div>
        </div>
        <div class="kpi-card spend">
            <div class="kpi-label">Direct Vendor Purchases</div>
            <div class="kpi-val">₹<?= number_format($exec['total_purchases_spend'] ?? 0, 2) ?></div>
            <div class="kpi-sub"><?= number_format($exec['purchases_count'] ?? 0) ?> POs issued this period</div>
        </div>
        <div class="kpi-card expense">
            <div class="kpi-label">Operating Expenses</div>
            <div class="kpi-val">₹<?= number_format($exec['total_expenses'] ?? 0, 2) ?></div>
            <div class="kpi-sub"><?= number_format($exec['expenses_count'] ?? 0) ?> operational records</div>
        </div>
        <div class="kpi-card damage">
            <div class="kpi-label">Damaged / Quarantine</div>
            <div class="kpi-val"><?= number_format($exec['damage_pieces'] ?? 0) ?> pcs</div>
            <div class="kpi-sub">Cost Loss: ₹<?= number_format($exec['damage_cost_loss'] ?? 0, 2) ?></div>
        </div>
        <div class="kpi-card return">
            <div class="kpi-label">Customer / Shop Returns</div>
            <div class="kpi-val"><?= number_format($exec['returns_total_pieces'] ?? 0) ?> pcs</div>
            <div class="kpi-sub"><?= number_format($exec['returns_good_pieces'] ?? 0) ?> Restocked &bull; <?= number_format($exec['returns_defective_pieces'] ?? 0) ?> Defective</div>
        </div>
    </section>

    <!-- Operational & Financial Summary Panels -->
    <div class="summary-panels">
        <div class="panel">
            <div class="panel-header">Warehouse Financial Overview</div>
            <div class="panel-body">
                <table class="summary-table">
                    <tr>
                        <td>Current Inventory Valuation (Cost Price)</td>
                        <td class="right strong">₹<?= number_format($exec['cost_valuation'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Current Inventory Valuation (Retail MRP)</td>
                        <td class="right strong text-success">₹<?= number_format($exec['retail_valuation'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Direct Supplier Purchases Spend</td>
                        <td class="right strong text-warning">₹<?= number_format($exec['total_purchases_spend'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Warehouse Operational Expenses</td>
                        <td class="right strong text-danger">₹<?= number_format($exec['total_expenses'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Quarantine / Damaged Stock Loss</td>
                        <td class="right strong text-danger">₹<?= number_format($exec['damage_cost_loss'] ?? 0, 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Complementary Product Stock Valuation</td>
                        <td class="right strong">₹<?= number_format($exec['complementary_value'] ?? 0, 2) ?> (<?= number_format($exec['complementary_units'] ?? 0) ?> pcs)</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">Logistics &amp; Inventory Balance Summary</div>
            <div class="panel-body">
                <table class="summary-table">
                    <tr>
                        <td>Available Stock in Warehouse</td>
                        <td class="right strong"><?= number_format($exec['available_stock_units'] ?? 0) ?> pcs</td>
                    </tr>
                    <tr>
                        <td>Unique Catalog SKUs on Shelf</td>
                        <td class="right strong"><?= number_format($exec['unique_skus'] ?? 0) ?> SKUs</td>
                    </tr>
                    <tr>
                        <td>Total Inward Received (Factory &amp; Vendor)</td>
                        <td class="right strong text-info"><?= number_format($exec['total_inward_pieces'] ?? 0) ?> pcs</td>
                    </tr>
                    <tr>
                        <td>Total Outward Dispatched (Shops &amp; Channels)</td>
                        <td class="right strong text-success"><?= number_format($exec['total_outward_pieces'] ?? 0) ?> pcs</td>
                    </tr>
                    <tr>
                        <td>Customer Returns Handled (Restocked)</td>
                        <td class="right strong"><?= number_format($exec['returns_good_pieces'] ?? 0) ?> pcs</td>
                    </tr>
                    <tr class="total-row">
                        <td>Active Dispatches &amp; Reconciled Inwards</td>
                        <td class="right strong"><?= number_format(($exec['inward_batches_count'] ?? 0) + ($exec['outward_dispatches_count'] ?? 0)) ?> Operations</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- MODULE 1: INWARD SHIPMENTS -->
    <?php if (in_array('inward', $selectedModules, true) && !empty($data['inward'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">1. Inward Shipments &amp; GRN Logs</h2>
            <span class="section-count"><?= count($data['inward']) ?> Records</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Batch / GRN #</th>
                    <th>Type</th>
                    <th>Source / Supplier</th>
                    <th>Product / Description</th>
                    <th>Warehouse</th>
                    <th class="right">Boxes</th>
                    <th class="right">Total Pcs</th>
                    <th>Outward Doc / Gate Pass</th>
                    <th>Vehicle &amp; Driver</th>
                    <th class="center">Status</th>
                    <th>Inward Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['inward'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['batch_number'] ?? '-') ?></td>
                    <td><span class="badge <?= ($row['source_type'] ?? '') === 'PRODUCTION' ? 'badge-info' : 'badge-secondary' ?>"><?= htmlspecialchars($row['source_type'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($row['supplier_or_source'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['product_name'] ?? '-') ?> (<?= htmlspecialchars($row['product_code'] ?? '-') ?>)</td>
                    <td><?= htmlspecialchars($row['warehouse_name'] ?? '-') ?></td>
                    <td class="right"><?= number_format($row['box_count'] ?? 0) ?></td>
                    <td class="right fw-bold"><?= number_format($row['total_pieces'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($row['outward_doc_no'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['vehicle_number'] ?? '-') ?> <?= !empty($row['driver_name']) && $row['driver_name'] !== '-' ? '(' . htmlspecialchars($row['driver_name']) . ')' : '' ?></td>
                    <td class="center"><span class="badge badge-success"><?= htmlspecialchars($row['status'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($row['inward_date'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 2: INVENTORY & STOCK BALANCES -->
    <?php if (in_array('stock', $selectedModules, true) && !empty($data['stock'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">2. Current Warehouse Inventory &amp; Stock Balances</h2>
            <span class="section-count"><?= count($data['stock']) ?> SKUs</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Style Code</th>
                    <th>Catalog Code</th>
                    <th>HSN</th>
                    <th>Category</th>
                    <th>SKU</th>
                    <th>Size &amp; Color</th>
                    <th>Warehouse</th>
                    <th class="right">Available</th>
                    <th class="right">Cost Price (₹)</th>
                    <th class="right">MRP / Selling (₹)</th>
                    <th class="right">Cost Value (₹)</th>
                    <th class="right">Retail Value (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['stock'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['product_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['product_code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['catalog_code'] ?? '-') ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($row['hsn_code'] ?? '6109') ?></span></td>
                    <td><?= htmlspecialchars($row['category_name'] ?? '-') ?></td>
                    <td><code><?= htmlspecialchars($row['sku'] ?? '-') ?></code></td>
                    <td><?= htmlspecialchars($row['size_name'] ?? '-') ?> / <?= htmlspecialchars($row['color_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['warehouse_name'] ?? '-') ?></td>
                    <td class="right fw-bold text-success"><?= number_format($row['available_qty'] ?? 0) ?></td>
                    <td class="right"><?= number_format((float)($row['cost_price'] ?? 0), 2) ?></td>
                    <td class="right"><?= number_format((float)($row['selling_price'] ?? 0), 2) ?></td>
                    <td class="right"><?= number_format((float)($row['cost_valuation'] ?? 0), 2) ?></td>
                    <td class="right fw-bold"><?= number_format((float)($row['retail_valuation'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 3: CATALOG & PRODUCT STYLES -->
    <?php if (in_array('catalog', $selectedModules, true) && !empty($data['catalog'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">3. Warehouse Catalog &amp; Product Styles</h2>
            <span class="section-count"><?= count($data['catalog']) ?> Styles</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product Style Name</th>
                    <th>Style Code</th>
                    <th>Catalog Code</th>
                    <th>HSN Code</th>
                    <th>Category</th>
                    <th>Brand</th>
                    <th>Gender</th>
                    <th class="right">Variants</th>
                    <th class="right">Total Available Stock</th>
                    <th>Created Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['catalog'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['catalog_code'] ?? '-') ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($row['hsn_code'] ?? '6109') ?></span></td>
                    <td><?= htmlspecialchars($row['category_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['brand_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['gender'] ?? '-') ?></td>
                    <td class="right"><?= number_format($row['variants_count'] ?? 0) ?></td>
                    <td class="right fw-bold"><?= number_format($row['total_stock'] ?? 0) ?> pcs</td>
                    <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 4: SUPPLIERS DIRECTORY -->
    <?php if (in_array('suppliers', $selectedModules, true) && !empty($data['suppliers'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">4. Suppliers &amp; Vendors Directory</h2>
            <span class="section-count"><?= count($data['suppliers']) ?> Suppliers</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Supplier Code</th>
                    <th>Supplier / Company Name</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Location</th>
                    <th>GSTIN</th>
                    <th class="right">Total POs</th>
                    <th class="right">Total Spend (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['suppliers'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['company'] ?? $row['name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['contact_person'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['city'] ?? '-') ?>, <?= htmlspecialchars($row['state'] ?? '-') ?></td>
                    <td><code><?= htmlspecialchars($row['gst_number'] ?? '-') ?></code></td>
                    <td class="right"><?= number_format($row['total_po_count'] ?? 0) ?></td>
                    <td class="right fw-bold">₹<?= number_format((float)($row['total_procurement_spend'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 5: DIRECT PURCHASES -->
    <?php if (in_array('purchases', $selectedModules, true) && !empty($data['purchases'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">5. Direct Vendor Purchases &amp; Purchase Orders</h2>
            <span class="section-count"><?= count($data['purchases']) ?> Orders</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Warehouse</th>
                    <th class="right">Items Count</th>
                    <th class="right">Total Amount (₹)</th>
                    <th class="right">Paid (₹)</th>
                    <th class="center">Status</th>
                    <th>PO Date</th>
                    <th>Expected Delivery</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['purchases'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['po_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['supplier_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['warehouse_name'] ?? '-') ?></td>
                    <td class="right"><?= number_format($row['total_items'] ?? 0) ?></td>
                    <td class="right fw-bold">₹<?= number_format((float)($row['total_amount'] ?? 0), 2) ?></td>
                    <td class="right">₹<?= number_format((float)($row['paid_amount'] ?? 0), 2) ?></td>
                    <td class="center"><span class="badge badge-info"><?= htmlspecialchars($row['status'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($row['po_date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['expected_delivery_date'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 6: SHOP ALLOCATIONS -->
    <?php if (in_array('allocations', $selectedModules, true) && !empty($data['allocations'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">6. Shop Allocations &amp; Stock Requisitions</h2>
            <span class="section-count"><?= count($data['allocations']) ?> Requisitions</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Allocation #</th>
                    <th>Destination Shop</th>
                    <th>Warehouse</th>
                    <th>Sales Channel</th>
                    <th class="right">Requested Pcs</th>
                    <th class="center">Status</th>
                    <th class="center">Priority</th>
                    <th>Request Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['allocations'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['allocation_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['shop_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['warehouse_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['channel_name'] ?? '-') ?></td>
                    <td class="right fw-bold"><?= number_format($row['total_pieces'] ?? 0) ?></td>
                    <td class="center"><span class="badge badge-info"><?= htmlspecialchars($row['status'] ?? '-') ?></span></td>
                    <td class="center"><?= htmlspecialchars($row['priority'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 7: OUTWARD DISPATCHES -->
    <?php if (in_array('outward', $selectedModules, true) && !empty($data['outward'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">7. Outward Dispatches to Outlets</h2>
            <span class="section-count"><?= count($data['outward']) ?> Dispatches</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Outward #</th>
                    <th>Allocation #</th>
                    <th>Warehouse</th>
                    <th>Destination Shop / Channel</th>
                    <th class="right">Total Pcs</th>
                    <th>Vehicle</th>
                    <th>Driver &amp; Contact</th>
                    <th class="center">Status</th>
                    <th>Dispatch Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['outward'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['outward_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['allocation_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['warehouse_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['shop_name'] ?? '-') ?> (<?= htmlspecialchars($row['channel_name'] ?? '-') ?>)</td>
                    <td class="right fw-bold text-success"><?= number_format($row['total_pieces'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($row['vehicle_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['driver_name'] ?? '-') ?> <?= !empty($row['driver_contact']) && $row['driver_contact'] !== '-' ? '(' . htmlspecialchars($row['driver_contact']) . ')' : '' ?></td>
                    <td class="center"><span class="badge badge-success"><?= htmlspecialchars($row['status'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($row['dispatch_date'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 8: DAMAGED / QUARANTINE -->
    <?php if (in_array('damages', $selectedModules, true) && !empty($data['damages'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">8. Damaged &amp; Quarantine Inventory Records</h2>
            <span class="section-count"><?= count($data['damages']) ?> Records</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Damage #</th>
                    <th>Warehouse</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="right">Quantity</th>
                    <th class="right">Unit Cost (₹)</th>
                    <th class="right">Total Loss (₹)</th>
                    <th>Reason</th>
                    <th>Action Taken</th>
                    <th>Damage Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['damages'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['damage_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['warehouse_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['product_name'] ?? '-') ?></td>
                    <td><code><?= htmlspecialchars($row['sku'] ?? '-') ?></code></td>
                    <td class="right fw-bold text-danger"><?= number_format($row['quantity'] ?? 0) ?></td>
                    <td class="right"><?= number_format((float)($row['unit_cost'] ?? 0), 2) ?></td>
                    <td class="right fw-bold text-danger">₹<?= number_format((float)($row['total_loss'] ?? 0), 2) ?></td>
                    <td><?= htmlspecialchars($row['reason'] ?? '-') ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($row['action_taken'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($row['damage_date'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 9: RETURNS QC -->
    <?php if (in_array('returns', $selectedModules, true) && !empty($data['returns'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">9. Returns QC Inspection &amp; Stock Reclassification</h2>
            <span class="section-count"><?= count($data['returns']) ?> Shipments</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Return #</th>
                    <th>Returning Shop</th>
                    <th>Warehouse</th>
                    <th class="right">Total Returned</th>
                    <th class="right">Good (Restocked)</th>
                    <th class="right">Defective</th>
                    <th class="center">Status</th>
                    <th>Reason</th>
                    <th>Return Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['returns'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['return_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['shop_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['warehouse_name'] ?? '-') ?></td>
                    <td class="right fw-bold"><?= number_format($row['total_returned_qty'] ?? 0) ?></td>
                    <td class="right text-success"><?= number_format($row['good_qty'] ?? 0) ?></td>
                    <td class="right text-danger"><?= number_format($row['defective_qty'] ?? 0) ?></td>
                    <td class="center"><span class="badge badge-info"><?= htmlspecialchars($row['status'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($row['reason'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 10: WAREHOUSE EXPENSES -->
    <?php if (in_array('expenses', $selectedModules, true) && !empty($data['expenses'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">10. Warehouse Operational Expenses</h2>
            <span class="section-count"><?= count($data['expenses']) ?> Expenses</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Expense #</th>
                    <th>Category</th>
                    <th>Item / Purpose</th>
                    <th>Warehouse</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit Cost (₹)</th>
                    <th class="right">Total Cost (₹)</th>
                    <th class="right">Paid (₹)</th>
                    <th class="center">Status</th>
                    <th>Payment Method</th>
                    <th>Paid To</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['expenses'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['expense_number'] ?? '-') ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($row['category'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($row['item_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['warehouse_name'] ?? '-') ?></td>
                    <td class="right"><?= number_format($row['quantity'] ?? 1) ?></td>
                    <td class="right"><?= number_format((float)($row['unit_cost'] ?? 0), 2) ?></td>
                    <td class="right fw-bold">₹<?= number_format((float)($row['total_cost'] ?? 0), 2) ?></td>
                    <td class="right">₹<?= number_format((float)($row['paid_amount'] ?? 0), 2) ?></td>
                    <td class="center"><span class="badge badge-success"><?= htmlspecialchars($row['payment_status'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($row['payment_method'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['paid_to'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['expense_date'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- MODULE 11: COMPLEMENTARY PRODUCTS -->
    <?php if (in_array('complementary', $selectedModules, true) && !empty($data['complementary'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">11. Complementary Products Stock &amp; Valuation</h2>
            <span class="section-count"><?= count($data['complementary']) ?> SKUs</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Product Code</th>
                    <th>Catalog Code</th>
                    <th>SKU</th>
                    <th>Size &amp; Color</th>
                    <th class="right">Available Qty</th>
                    <th class="right">MRP (₹)</th>
                    <th class="right">Selling Price (₹)</th>
                    <th class="right">Potential Value (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['complementary'] as $row): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($row['product_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['product_code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['catalog_code'] ?? '-') ?></td>
                    <td><code><?= htmlspecialchars($row['sku'] ?? '-') ?></code></td>
                    <td><?= htmlspecialchars($row['size_name'] ?? '-') ?> / <?= htmlspecialchars($row['color_name'] ?? '-') ?></td>
                    <td class="right fw-bold text-success"><?= number_format($row['available_qty'] ?? 0) ?></td>
                    <td class="right"><?= number_format((float)($row['mrp'] ?? 0), 2) ?></td>
                    <td class="right"><?= number_format((float)($row['selling_price'] ?? 0), 2) ?></td>
                    <td class="right fw-bold">₹<?= number_format((float)($row['potential_revenue'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <footer class="report-footer">
        GRM Apparel ERP &bull; Confidential Warehouse Operations Report &bull; Generated <?= date('Y-m-d H:i:s') ?>
    </footer>
</main>

</body>
</html>
