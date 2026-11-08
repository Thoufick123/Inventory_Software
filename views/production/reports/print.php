<?php
$prodUnit = $prodUnit ?? (class_exists('ProductionUnit') ? \ProductionUnit::getActiveFacility() : []);
$sellerCompany = $sellerCompany ?? [];
$summary = $data['summary'] ?? [];
$fin = $summary['financial'] ?? [];
$stk = $summary['stock'] ?? [];

$isProfit = ($fin['net_profit_loss'] ?? 0) >= 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($prodUnit['name'] ?? 'Production Unit') ?> — Master Performance &amp; Financial Report (<?= htmlspecialchars($periodLabel) ?>)</title>
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
        .kpi-card.revenue { border-left: 4px solid #16a34a; background: #f0fdf4; }
        .kpi-card.cost { border-left: 4px solid #dc2626; background: #fef2f2; }
        .kpi-card.profit-pos { border-left: 4px solid #059669; background: #ecfdf5; }
        .kpi-card.profit-neg { border-left: 4px solid #b91c1c; background: #fff1f2; }
        .kpi-card.stock { border-left: 4px solid #2563eb; background: #eff6ff; }
        .kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px; }
        .kpi-val { font-size: 20px; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 4px; }
        .kpi-sub { font-size: 11px; color: #475569; }

        /* Executive Summary Tables */
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
        .badge { display: inline-block; padding: 2px 6px; font-size: 9.5px; font-weight: 700; border-radius: 4px; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-secondary { background: #f1f5f9; color: #475569; }

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
            <h1 class="facility-title"><?= htmlspecialchars($prodUnit['name'] ?? ($sellerCompany['company_name'] ?? 'Production Manufacturing Facility')) ?></h1>
            <div class="facility-meta">
                <?php if (!empty($prodUnit['address'])): ?>
                    <div><?= nl2br(htmlspecialchars((string)$prodUnit['address'])) ?></div>
                <?php endif; ?>
                <div style="margin-top: 3px;">
                    <?php if (!empty($prodUnit['code'])): ?><span><strong>Unit Code:</strong> <?= htmlspecialchars($prodUnit['code']) ?></span> &nbsp;·&nbsp; <?php endif; ?>
                    <?php if (!empty($prodUnit['phone'])): ?><span><strong>Phone:</strong> <?= htmlspecialchars($prodUnit['phone']) ?></span> &nbsp;·&nbsp; <?php endif; ?>
                    <?php if (!empty($prodUnit['gst_number'])): ?><span><strong>GSTIN:</strong> <?= htmlspecialchars($prodUnit['gst_number']) ?></span><?php endif; ?>
                </div>
                <div style="color: #64748b; font-size: 11px; margin-top: 2px;">Comprehensive Production &amp; Financial Intelligence Report</div>
            </div>
        </div>
        <div class="period-box">
            <span class="period-badge">Report Period</span>
            <div class="period-dates"><?= htmlspecialchars($periodLabel) ?></div>
            <div style="font-size: 11.5px; color: #475569; margin-top: 2px;"><?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></div>
            <div class="period-time">Generated on <?= date('d M Y, h:i A') ?> IST</div>
        </div>
    </header>

    <!-- Executive KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card revenue">
            <div class="kpi-label text-success">Total Gross Revenue</div>
            <div class="kpi-val text-success">₹<?= number_format((float)($fin['gross_revenue'] ?? 0), 2) ?></div>
            <div class="kpi-sub">Bills: ₹<?= number_format((float)($fin['billing_total'] ?? 0), 2) ?> + Scrap: ₹<?= number_format((float)($fin['wastage_total'] ?? 0), 2) ?></div>
        </div>
        <div class="kpi-card cost">
            <div class="kpi-label text-danger">Total Expenditures</div>
            <div class="kpi-val text-danger">₹<?= number_format((float)($fin['total_expenditure'] ?? 0), 2) ?></div>
            <div class="kpi-sub">Purchases: ₹<?= number_format((float)($fin['purchases_total'] ?? 0), 2) ?> + Exp: ₹<?= number_format((float)($fin['expenses_total'] ?? 0), 2) ?></div>
        </div>
        <div class="kpi-card <?= $isProfit ? 'profit-pos' : 'profit-neg' ?>">
            <div class="kpi-label <?= $isProfit ? 'text-success' : 'text-danger' ?>">Net Income (<?= $isProfit ? 'Profit' : 'Loss' ?>)</div>
            <div class="kpi-val <?= $isProfit ? 'text-success' : 'text-danger' ?>">₹<?= number_format((float)($fin['net_profit_loss'] ?? 0), 2) ?></div>
            <div class="kpi-sub">Operating Margin: <strong><?= number_format((float)($fin['profit_margin_pct'] ?? 0), 2) ?>%</strong></div>
        </div>
        <div class="kpi-card stock">
            <div class="kpi-label text-primary">Production &amp; Stock Output</div>
            <div class="kpi-val text-primary"><?= number_format((int)($stk['batches_pieces'] ?? 0)) ?> Pcs</div>
            <div class="kpi-sub">Cut: <?= number_format((int)($stk['cuttings_pieces'] ?? 0)) ?> | Stitched: <?= number_format((int)($stk['stitching_pieces'] ?? 0)) ?> | Out: <?= number_format((int)($stk['outward_pieces'] ?? 0)) ?></div>
        </div>
    </div>

    <!-- Executive Summary Panels: Financial P&L + Operational Reconciliation -->
    <div class="summary-panels">
        <!-- Panel 1: Financial Inflow vs Outflow -->
        <div class="panel">
            <div class="panel-header">1. Executive Financial Performance (Income &amp; Loss Breakdown)</div>
            <div class="panel-body">
                <table class="summary-table">
                    <tr>
                        <td>Direct Factory Billing (Sales)</td>
                        <td class="center"><?= (int)($fin['billing_count'] ?? 0) ?> bills (<?= (int)($fin['billing_pieces'] ?? 0) ?> pcs)</td>
                        <td class="right strong text-success">₹<?= number_format((float)($fin['billing_total'] ?? 0), 2) ?></td>
                    </tr>
                    <tr>
                        <td>Cutting &amp; Stitching Scrap Sales</td>
                        <td class="center">Wastage Disposals</td>
                        <td class="right strong text-success">₹<?= number_format((float)($fin['wastage_total'] ?? 0), 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL GROSS INFLOW (REVENUE)</strong></td>
                        <td class="center"><strong>All Sales</strong></td>
                        <td class="right strong text-success"><strong>₹<?= number_format((float)($fin['gross_revenue'] ?? 0), 2) ?></strong></td>
                    </tr>
                    <tr>
                        <td>Raw Fabric &amp; Material Purchases</td>
                        <td class="center"><?= (int)($fin['purchases_count'] ?? 0) ?> Purchase Orders</td>
                        <td class="right strong text-danger">₹<?= number_format((float)($fin['purchases_total'] ?? 0), 2) ?></td>
                    </tr>
                    <tr>
                        <td>Factory Operating &amp; Machinery Expenses</td>
                        <td class="center"><?= (int)($fin['expenses_count'] ?? 0) ?> Expense Items</td>
                        <td class="right strong text-danger">₹<?= number_format((float)($fin['expenses_total'] ?? 0), 2) ?></td>
                    </tr>
                    <tr>
                        <td>Customer Returns &amp; Credit Reversals</td>
                        <td class="center"><?= (int)($fin['returns_count'] ?? 0) ?> returns (<?= (int)($fin['returns_pieces'] ?? 0) ?> pcs)</td>
                        <td class="right strong text-danger">₹<?= number_format((float)($fin['returns_total'] ?? 0), 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL OPERATING EXPENDITURES</strong></td>
                        <td class="center"><strong>All Outflows</strong></td>
                        <td class="right strong text-danger"><strong>₹<?= number_format((float)($fin['total_expenditure'] ?? 0), 2) ?></strong></td>
                    </tr>
                    <tr style="background: <?= $isProfit ? '#ecfdf5' : '#fff1f2' ?>;">
                        <td style="font-weight:800; font-size:12px; color: <?= $isProfit ? '#15803d' : '#b91c1c' ?>;">NET OPERATING <?= $isProfit ? 'PROFIT' : 'LOSS' ?></td>
                        <td class="center fw-bold" style="color: <?= $isProfit ? '#15803d' : '#b91c1c' ?>;">Margin: <?= number_format((float)($fin['profit_margin_pct'] ?? 0), 2) ?>%</td>
                        <td class="right" style="font-weight:800; font-size:13px; color: <?= $isProfit ? '#15803d' : '#b91c1c' ?>;">₹<?= number_format((float)($fin['net_profit_loss'] ?? 0), 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Panel 2: Production Pipeline & Stock Inventory Reconciliation -->
        <div class="panel">
            <div class="panel-header">2. Production Pipeline &amp; Stock Reconciliation</div>
            <div class="panel-body">
                <table class="summary-table">
                    <tr>
                        <td>Fabric Inwarded (Rolls)</td>
                        <td class="center"><?= (int)($stk['inward_rolls_count'] ?? 0) ?> Rolls</td>
                        <td class="right strong"><?= number_format((float)($stk['inward_weight_kg'] ?? 0), 2) ?> kg</td>
                    </tr>
                    <tr>
                        <td>Garment Cutting Batches</td>
                        <td class="center"><?= (int)($stk['cuttings_batches_count'] ?? 0) ?> Batches</td>
                        <td class="right strong"><?= number_format((int)($stk['cuttings_pieces'] ?? 0)) ?> Cut Pcs</td>
                    </tr>
                    <tr>
                        <td>Stitching Completed</td>
                        <td class="center"><?= (int)($stk['stitching_batches_count'] ?? 0) ?> Batches</td>
                        <td class="right strong"><?= number_format((int)($stk['stitching_pieces'] ?? 0)) ?> Stitched Pcs</td>
                    </tr>
                    <tr>
                        <td>Quality Control (QC Passed)</td>
                        <td class="center">Inspected: <?= number_format((int)($stk['qc_inspected_pieces'] ?? 0)) ?></td>
                        <td class="right strong text-success"><?= number_format((int)($stk['qc_passed_pieces'] ?? 0)) ?> Pcs (<?= number_format((float)($stk['qc_yield_pct'] ?? 0), 1) ?>%)</td>
                    </tr>
                    <tr>
                        <td>Finished Goods Packed into Batches</td>
                        <td class="center"><?= (int)($stk['batches_boxes'] ?? 0) ?> Master Cartons</td>
                        <td class="right strong"><?= number_format((int)($stk['batches_pieces'] ?? 0)) ?> Packed Pcs</td>
                    </tr>
                    <tr>
                        <td>Dispatched to Warehouses (Outward)</td>
                        <td class="center"><?= (int)($stk['outward_boxes'] ?? 0) ?> Cartons Dispatched</td>
                        <td class="right strong"><?= number_format((int)($stk['outward_pieces'] ?? 0)) ?> Dispatched Pcs</td>
                    </tr>
                    <tr>
                        <td>Direct Factory Sales (Billed)</td>
                        <td class="center"><?= (int)($fin['billing_count'] ?? 0) ?> Direct Invoices</td>
                        <td class="right strong"><?= number_format((int)($stk['billing_pieces'] ?? 0)) ?> Sold Pcs</td>
                    </tr>
                    <tr class="total-row" style="background:#eff6ff;">
                        <td><strong>ESTIMATED ON-HAND FACTORY STOCK</strong></td>
                        <td class="center"><strong>Finished Balance</strong></td>
                        <td class="right strong" style="color:#1d4ed8; font-size:12px;"><strong><?= number_format((int)($stk['stock_on_hand_pieces'] ?? 0)) ?> Finished Pcs</strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- DETAILED MODULE DATA TABLES -->
    <!-- ========================================================================= -->

    <!-- 1. Purchases -->
    <?php if (in_array('purchases', $selectedModules, true) && !empty($data['purchases'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">1. Fabric &amp; Material Purchases (PO Orders)</h3>
                <span class="section-count"><?= count($data['purchases']) ?> records</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Purchase / PO #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Invoice #</th>
                        <th class="center">Total Items</th>
                        <th class="center">Rolls Count</th>
                        <th class="right">Total Weight (kg)</th>
                        <th class="right">Total Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['purchases'] as $pur): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pur['purchase_number']) ?></strong></td>
                            <td><?= date('d M Y', strtotime($pur['purchase_date'])) ?></td>
                            <td><?= htmlspecialchars($pur['supplier_company'] ?: ($pur['supplier_name'] ?? 'Direct Supplier')) ?></td>
                            <td><?= htmlspecialchars($pur['invoice_number'] ?? '-') ?></td>
                            <td class="center"><?= (int)$pur['total_items'] ?></td>
                            <td class="center fw-bold"><?= (int)$pur['total_rolls'] ?></td>
                            <td class="right"><?= number_format((float)$pur['total_weight'], 2) ?> kg</td>
                            <td class="right fw-bold text-success">₹<?= number_format((float)$pur['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 2. Fabric Rolls & Inward Stocks -->
    <?php if (in_array('rolls', $selectedModules, true) && !empty($data['rolls'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">2. Fabric Rolls &amp; Inwards</h3>
                <span class="section-count"><?= count($data['rolls']) ?> records</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Roll #</th>
                        <th>Inward Date</th>
                        <th>Supplier</th>
                        <th>Fabric Type</th>
                        <th>Color</th>
                        <th class="right">Weight (kg)</th>
                        <th class="right">Cost / kg (₹)</th>
                        <th class="right">Total Cost (₹)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['rolls'] as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['roll_number'] ?? '-') ?></strong></td>
                            <td><?= !empty($r['created_at']) ? date('d M Y', strtotime($r['created_at'])) : '-' ?></td>
                            <td><?= htmlspecialchars(($r['supplier_company'] ?? '') ?: ($r['supplier_name'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($r['fabric_type'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['color'] ?? '-') ?></td>
                            <td class="right"><?= number_format((float)($r['weight'] ?? 0), 2) ?></td>
                            <td class="right"><?= isset($r['cost_per_kg']) && (float)$r['cost_per_kg'] > 0 ? '₹' . number_format((float)$r['cost_per_kg'], 2) . '/kg' : '-' ?></td>
                            <td class="right fw-bold">₹<?= number_format((float)($r['total_cost'] ?? 0), 2) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($r['status'] ?? 'IN_STOCK') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 3. Production Programs -->
    <?php if (in_array('programs', $selectedModules, true) && !empty($data['programs'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">3. Production Programs (Planned Targets)</h3>
                <span class="section-count"><?= count($data['programs']) ?> records</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Program #</th>
                        <th>Program Name</th>
                        <th>Style Code</th>
                        <th class="center">Target Pcs</th>
                        <th class="center">Completed Pcs</th>
                        <th class="center">Status</th>
                        <th>Target Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['programs'] as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['program_number']) ?></strong></td>
                            <td><?= htmlspecialchars($p['program_name']) ?></td>
                            <td><?= htmlspecialchars($p['style_code'] ?? '-') ?></td>
                            <td class="center fw-bold"><?= number_format((int)$p['target_pieces']) ?></td>
                            <td class="center"><?= number_format((int)($p['completed_quantity'] ?? 0)) ?></td>
                            <td class="center"><span class="badge badge-info"><?= htmlspecialchars($p['status']) ?></span></td>
                            <td><?= !empty($p['target_date']) ? date('d M Y', strtotime($p['target_date'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 4. Cutting Batches -->
    <?php if (in_array('cuttings', $selectedModules, true) && !empty($data['cuttings'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">4. Cutting Batches &amp; Fabric Output</h3>
                <span class="section-count"><?= count($data['cuttings']) ?> records</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Batch #</th>
                        <th>Program #</th>
                        <th class="right">Roll Weight (kg)</th>
                        <th class="center">Planned Qty</th>
                        <th class="center">Cut Pieces</th>
                        <th class="right">Wastage (kg)</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['cuttings'] as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['batch_number']) ?></strong></td>
                            <td><?= htmlspecialchars($c['program_number'] ?? '-') ?></td>
                            <td class="right"><?= number_format((float)($c['roll_weight'] ?? 0), 2) ?> kg</td>
                            <td class="center"><?= number_format((int)$c['planned_qty']) ?></td>
                            <td class="center fw-bold text-success"><?= number_format((int)$c['actual_pieces']) ?></td>
                            <td class="right"><?= number_format((float)($c['wastage_qty'] ?? 0), 2) ?> kg</td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($c['status']) ?></span></td>
                            <td><?= !empty($c['created_at']) ? date('d M Y', strtotime($c['created_at'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 5. Stitching Batches -->
    <?php if (in_array('stitching', $selectedModules, true) && !empty($data['stitching'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">5. Stitching Operations &amp; Assembly</h3>
                <span class="section-count"><?= count($data['stitching']) ?> records</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Batch #</th>
                        <th>Program #</th>
                        <th class="center">Target Pcs</th>
                        <th class="center">Completed Pcs</th>
                        <th class="center">Rework Pcs</th>
                        <th class="center">Rejected Pcs</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['stitching'] as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['batch_number']) ?></strong></td>
                            <td><?= htmlspecialchars($s['program_number'] ?? '-') ?></td>
                            <td class="center"><?= number_format((int)$s['target_quantity']) ?></td>
                            <td class="center fw-bold text-success"><?= number_format((int)$s['completed_quantity']) ?></td>
                            <td class="center text-warning"><?= number_format((int)$s['rework_quantity']) ?></td>
                            <td class="center text-danger"><?= number_format((int)$s['rejected_qty']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($s['status']) ?></span></td>
                            <td><?= !empty($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 6. Quality Control (QC) -->
    <?php if (in_array('qc', $selectedModules, true) && !empty($data['qc'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">6. Quality Control (QC) &amp; Yield</h3>
                <span class="section-count"><?= count($data['qc']) ?> batches</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>QC Batch #</th>
                        <th>Program #</th>
                        <th class="center">Inspected</th>
                        <th class="center">Passed</th>
                        <th class="center">Rework</th>
                        <th class="center">Rejected</th>
                        <th class="center">Yield %</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['qc'] as $q): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($q['batch_number']) ?></strong></td>
                            <td><?= htmlspecialchars($q['program_number'] ?? '-') ?></td>
                            <td class="center"><?= number_format((int)$q['inspected_quantity']) ?></td>
                            <td class="center fw-bold text-success"><?= number_format((int)$q['passed_quantity']) ?></td>
                            <td class="center text-warning"><?= number_format((int)$q['rework_quantity']) ?></td>
                            <td class="center text-danger"><?= number_format((int)$q['rejected_quantity']) ?></td>
                            <td class="center fw-bold"><?= $q['yield_percentage'] ?>%</td>
                            <td><span class="badge badge-success"><?= htmlspecialchars($q['status']) ?></span></td>
                            <td><?= !empty($q['created_at']) ? date('d M Y', strtotime($q['created_at'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 7. Finished Goods Batches -->
    <?php if (in_array('batches', $selectedModules, true) && !empty($data['batches'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">7. Finished Goods Batches (Master Cartons)</h3>
                <span class="section-count"><?= count($data['batches']) ?> batches</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Batch #</th>
                        <th>Program #</th>
                        <th>QC Ref</th>
                        <th>Destination Warehouse</th>
                        <th class="center">Total Pieces</th>
                        <th class="center">Master Cartons</th>
                        <th class="right">Cost/Pc (₹)</th>
                        <th class="right">Total Value (₹)</th>
                        <th>Status</th>
                        <th>Packed Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['batches'] as $b): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['batch_number']) ?></strong></td>
                            <td><?= htmlspecialchars($b['program_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($b['qc_batch_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($b['destination_warehouse'] ?? '-') ?></td>
                            <td class="center fw-bold text-success"><?= number_format((int)$b['total_pieces']) ?></td>
                            <td class="center"><?= (int)$b['box_count'] ?> boxes</td>
                            <td class="right">₹<?= number_format((float)($b['cost_per_piece'] ?? 0), 2) ?></td>
                            <td class="right fw-bold">₹<?= number_format((float)($b['total_cost'] ?? 0), 2) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($b['status']) ?></span></td>
                            <td><?= !empty($b['packed_date']) ? date('d M Y', strtotime($b['packed_date'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 8. Production Outward & Dispatches -->
    <?php if (in_array('outward', $selectedModules, true) && !empty($data['outward'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">8. Production Outward &amp; Warehouse Dispatches</h3>
                <span class="section-count"><?= count($data['outward']) ?> dispatches</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Outward #</th>
                        <th>Batch #</th>
                        <th>Warehouse</th>
                        <th class="center">Boxes</th>
                        <th class="center">Total Pieces</th>
                        <th>Transporter</th>
                        <th>LR / Docket #</th>
                        <th>Vehicle #</th>
                        <th>Status</th>
                        <th>Dispatch Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['outward'] as $ow): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ow['outward_number']) ?></strong></td>
                            <td><?= htmlspecialchars($ow['batch_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($ow['warehouse_name'] ?? '-') ?></td>
                            <td class="center"><?= (int)$ow['total_boxes'] ?></td>
                            <td class="center fw-bold text-primary"><?= number_format((int)$ow['total_pieces']) ?></td>
                            <td><?= htmlspecialchars($ow['transporter_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($ow['lr_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($ow['vehicle_number'] ?? '-') ?></td>
                            <td><span class="badge badge-success"><?= htmlspecialchars($ow['status']) ?></span></td>
                            <td><?= !empty($ow['dispatch_date']) ? date('d M Y', strtotime($ow['dispatch_date'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 9. Production Billing -->
    <?php if (in_array('billing', $selectedModules, true) && !empty($data['billing'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">9. Factory Direct Billing &amp; Invoices</h3>
                <span class="section-count"><?= count($data['billing']) ?> bills</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Date</th>
                        <th>Client / Customer</th>
                        <th class="center">Sold Pcs</th>
                        <th class="right">Taxable (₹)</th>
                        <th class="right">GST (₹)</th>
                        <th class="right">Total (₹)</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['billing'] as $b): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['bill_number']) ?></strong></td>
                            <td><?= date('d M Y', strtotime($b['bill_date'])) ?></td>
                            <td><?= htmlspecialchars($b['client_name']) ?></td>
                            <td class="center"><?= (int)$b['total_pieces'] ?></td>
                            <td class="right">₹<?= number_format((float)$b['subtotal'], 2) ?></td>
                            <td class="right">₹<?= number_format((float)$b['tax_amount'], 2) ?></td>
                            <td class="right fw-bold text-success">₹<?= number_format((float)$b['total_amount'], 2) ?></td>
                            <td><?= htmlspecialchars($b['payment_method']) ?> (<?= htmlspecialchars($b['payment_status']) ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 10. Customer Returns -->
    <?php if (in_array('returns', $selectedModules, true) && !empty($data['returns'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">10. Customer Returns &amp; Credit Notes</h3>
                <span class="section-count"><?= count($data['returns']) ?> records</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Credit Note #</th>
                        <th>Original Bill #</th>
                        <th>Customer</th>
                        <th class="center">Returned Pcs</th>
                        <th>Disposition</th>
                        <th class="right">GST Reversal (₹)</th>
                        <th class="right">Net Refund (₹)</th>
                        <th>Refund Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['returns'] as $ret): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ret['return_number']) ?></strong></td>
                            <td><?= htmlspecialchars($ret['bill_number']) ?></td>
                            <td><?= htmlspecialchars($ret['customer_name']) ?></td>
                            <td class="center fw-bold text-danger"><?= (int)$ret['total_returned_qty'] ?></td>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($ret['disposition']) ?></span></td>
                            <td class="right">₹<?= number_format((float)$ret['gst_reversal_amount'], 2) ?></td>
                            <td class="right fw-bold text-danger">₹<?= number_format((float)$ret['net_amount'], 2) ?></td>
                            <td><?= htmlspecialchars($ret['refund_mode'] ?? 'CASH') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 11. Stock Requests -->
    <?php if (in_array('stock_requests', $selectedModules, true) && !empty($data['stock_requests'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">11. Warehouse Stock Requisitions</h3>
                <span class="section-count"><?= count($data['stock_requests']) ?> requests</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Warehouse</th>
                        <th>Priority</th>
                        <th>Required By</th>
                        <th class="center">Requested</th>
                        <th class="center">Fulfilled</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['stock_requests'] as $sr): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sr['request_number']) ?></strong></td>
                            <td><?= htmlspecialchars($sr['warehouse_name']) ?></td>
                            <td><span class="badge badge-warning"><?= htmlspecialchars($sr['priority']) ?></span></td>
                            <td><?= !empty($sr['required_by_date']) ? date('d M Y', strtotime($sr['required_by_date'])) : '-' ?></td>
                            <td class="center"><?= number_format((int)$sr['total_requested_pieces']) ?></td>
                            <td class="center fw-bold text-success"><?= number_format((int)$sr['fulfilled_pieces']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($sr['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 12. Wastage Selling -->
    <?php if (in_array('wastage', $selectedModules, true) && !empty($data['wastage'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">12. Cutting &amp; Stitching Scrap / Wastage Selling</h3>
                <span class="section-count"><?= count($data['wastage']) ?> sales</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Wastage Type</th>
                        <th>Buyer Name</th>
                        <th class="right">Weight / Qty (kg)</th>
                        <th class="right">Rate/kg (₹)</th>
                        <th class="right">Total Inflow (₹)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['wastage'] as $w): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($w['invoice_number']) ?></strong></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($w['wastage_type']) ?></span></td>
                            <td><?= htmlspecialchars($w['buyer_name']) ?></td>
                            <td class="right"><?= number_format((float)($w['weight_kg'] ?? 0), 2) ?> kg</td>
                            <td class="right">₹<?= number_format((float)($w['rate_per_kg'] ?? 0), 2) ?></td>
                            <td class="right fw-bold text-success">₹<?= number_format((float)($w['total_amount'] ?? 0), 2) ?></td>
                            <td><?= !empty($w['created_at']) ? date('d M Y', strtotime($w['created_at'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 13. Production Expenses & Machinery Assets -->
    <?php if (in_array('expenses', $selectedModules, true) && !empty($data['expenses'])): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">13. Production Expenses &amp; Machinery Assets</h3>
                <span class="section-count"><?= count($data['expenses']) ?> items</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Expense Title</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="right">Total Cost (₹)</th>
                        <th class="right">Paid (₹)</th>
                        <th class="right">Pending (₹)</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['expenses'] as $e): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($e['title'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($e['category'] ?? '-') ?></td>
                            <td><?= htmlspecialchars((string)($e['production_unit'] ?? '-')) ?></td>
                            <td class="right fw-bold">₹<?= number_format((float)($e['total_cost'] ?? 0), 2) ?></td>
                            <td class="right text-success">₹<?= number_format((float)($e['paid_amount'] ?? 0), 2) ?></td>
                            <td class="right text-danger">₹<?= number_format((float)($e['pending_amount'] ?? 0), 2) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($e['payment_status'] ?? '-') ?></span></td>
                            <td><?= !empty($e['expense_date']) ? date('d M Y', strtotime($e['expense_date'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <footer style="text-align: center; border-top: 1px solid #cbd5e1; padding-top: 18px; margin-top: 30px; font-size: 11px; color: #64748b;">
        <?= htmlspecialchars($prodUnit['name'] ?? 'Production Facility') ?> &nbsp;·&nbsp; Confidential Production ERP &amp; Financial Intelligence Report &nbsp;·&nbsp; Generated on <?= date('d M Y, h:i A') ?> IST
    </footer>
</main>
</body>
</html>
