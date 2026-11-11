<?php
/**
 * Master Enterprise Asset Intelligence & Operations Printable Report
 */
$summary = $data['summary'] ?? [];
$totalAssets = (int)($summary['total_assets'] ?? 0);
$totalPurchaseCost = (float)($summary['total_purchase_cost'] ?? 0.0);
$totalBookValue = (float)($summary['total_book_value'] ?? 0.0);
$inUseCount = (int)($summary['in_use_count'] ?? 0);
$inMntCount = (int)($summary['in_maintenance_count'] ?? 0);
$idleCount = (int)($summary['idle_count'] ?? 0);
$damagedCount = (int)($summary['damaged_count'] ?? 0);
$criticalCount = (int)($summary['critical_condition_count'] ?? 0);
$warrantyExpiringCount = (int)($summary['warranty_expiring_soon_count'] ?? 0);
$utilizationRate = $totalAssets > 0 ? round(($inUseCount / $totalAssets) * 100, 1) : 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Enterprise Asset Intelligence Report — <?= htmlspecialchars($facilityScopeLabel) ?> (<?= htmlspecialchars($periodLabel) ?>)</title>
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

        /* Executive KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; }
        .kpi-card.book-value { border-left: 4px solid #2563eb; background: #eff6ff; }
        .kpi-card.purchase-cost { border-left: 4px solid #0284c7; background: #f0f9ff; }
        .kpi-card.active-fleet { border-left: 4px solid #16a34a; background: #f0fdf4; }
        .kpi-card.maintenance { border-left: 4px solid #d97706; background: #fffbeb; }
        .kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px; }
        .kpi-val { font-size: 20px; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 4px; font-family: monospace; }
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
        .badge { display: inline-block; padding: 2px 6px; font-size: 9.5px; font-weight: 700; border-radius: 4px; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-secondary { background: #f1f5f9; color: #475569; }

        /* Print Media Styles */
        @media print {
            body { background: #fff; padding: 0; }
            .report-wrap { box-shadow: none; padding: 0; border-radius: 0; }
            .actions { display: none !important; }
            .facility-header { border-bottom: 2px solid #000; }
            table.data-table th { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .kpi-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .section { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="actions">
    <button class="btn-print" onclick="window.print()">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
        Print / Save as PDF
    </button>
</div>

<div class="report-wrap">
    <!-- Header -->
    <div class="facility-header">
        <div>
            <h1 class="facility-title">GRM APPARELS</h1>
            <div class="facility-meta">
                <strong>ENTERPRISE ASSET MANAGEMENT &amp; OPERATIONS REPORT</strong><br>
                <span>Scope: <strong><?= htmlspecialchars($facilityScopeLabel) ?></strong></span>
            </div>
        </div>
        <div class="period-box">
            <span class="period-badge">Report Period</span>
            <div class="period-dates"><?= htmlspecialchars($periodLabel) ?></div>
            <div class="period-time">Generated on: <?= date('d M Y, h:i A') ?> IST</div>
        </div>
    </div>

    <!-- KPI Grid -->
    <div class="kpi-grid">
        <div class="kpi-card book-value">
            <div class="kpi-label">Current Book Value</div>
            <div class="kpi-val">&#8377;<?= number_format($totalBookValue, 2) ?></div>
            <div class="kpi-sub">Written down value (Straight-line)</div>
        </div>
        <div class="kpi-card purchase-cost">
            <div class="kpi-label">Original Capital Cost</div>
            <div class="kpi-val">&#8377;<?= number_format($totalPurchaseCost, 2) ?></div>
            <div class="kpi-sub">Acquisition value for <?= $totalAssets ?> registered units</div>
        </div>
        <div class="kpi-card active-fleet">
            <div class="kpi-label">Fleet Utilization</div>
            <div class="kpi-val"><?= $inUseCount ?> / <?= $totalAssets ?></div>
            <div class="kpi-sub"><strong class="text-success"><?= $utilizationRate ?>% Active Fleet Uptime</strong></div>
        </div>
        <div class="kpi-card maintenance">
            <div class="kpi-label">Maintenance &amp; Downtime</div>
            <div class="kpi-val"><?= $inMntCount ?> Units</div>
            <div class="kpi-sub">Critical: <strong><?= $criticalCount ?></strong> &bull; Damaged: <strong><?= $damagedCount ?></strong></div>
        </div>
    </div>

    <!-- Summary Panels (Categories & Facilities) -->
    <div class="summary-panels">
        <!-- Panel 1: Operations Status Breakdown -->
        <div class="panel">
            <div class="panel-header">Asset Operational Status Summary</div>
            <div class="panel-body">
                <table class="summary-table">
                    <tr>
                        <td>Active &amp; Operational (In Use)</td>
                        <td class="right strong text-success"><?= $inUseCount ?></td>
                    </tr>
                    <tr>
                        <td>Under Active Maintenance / Servicing</td>
                        <td class="right strong text-warning"><?= $inMntCount ?></td>
                    </tr>
                    <tr>
                        <td>Idle / Reserve Stock</td>
                        <td class="right strong"><?= $idleCount ?></td>
                    </tr>
                    <tr>
                        <td>Damaged / Defective Equipment</td>
                        <td class="right strong text-danger"><?= $damagedCount ?></td>
                    </tr>
                    <tr>
                        <td>Critical / Poor Condition Equipment</td>
                        <td class="right strong text-danger"><?= $criticalCount ?></td>
                    </tr>
                    <tr>
                        <td>Warranty Expiring Soon / Expired</td>
                        <td class="right strong text-warning"><?= $warrantyExpiringCount ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>TOTAL REGISTERED ASSETS</td>
                        <td class="right"><?= $totalAssets ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Panel 2: Capital Valuation Summary -->
        <div class="panel">
            <div class="panel-header">Capital Valuation &amp; Financial Summary</div>
            <div class="panel-body">
                <table class="summary-table">
                    <tr>
                        <td>Total Original Purchase Cost</td>
                        <td class="right strong">&#8377;<?= number_format($totalPurchaseCost, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Total Depreciated Written Down Value</td>
                        <td class="right strong text-success">&#8377;<?= number_format($totalBookValue, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Cumulative Accumulated Depreciation</td>
                        <td class="right strong text-danger">&#8377;<?= number_format(max(0, $totalPurchaseCost - $totalBookValue), 2) ?></td>
                    </tr>
                    <tr>
                        <td>Fleet Depreciation Percentage</td>
                        <td class="right strong">
                            <?= $totalPurchaseCost > 0 ? round((max(0, $totalPurchaseCost - $totalBookValue) / $totalPurchaseCost) * 100, 1) : 0 ?>%
                        </td>
                    </tr>
                    <tr class="total-row">
                        <td>NET ENTERPRISE BOOK VALUE</td>
                        <td class="right text-success">&#8377;<?= number_format($totalBookValue, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Category Breakdown Table -->
    <?php if (in_array('categories', $selectedModules) && !empty($data['categories'])): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Asset Category Valuation &amp; Fleet Distribution</h2>
            <span class="section-count"><?= count($data['categories']) ?> Categories</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Code</th>
                    <th class="center">Depr. Rate (%/yr)</th>
                    <th class="right">Asset Count</th>
                    <th class="right">Total Purchase Cost</th>
                    <th class="right">Total Book Value</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $cTotalCount = 0;
                $cTotalCost = 0.0;
                $cTotalBook = 0.0;
                foreach ($data['categories'] as $c): 
                    $cTotalCount += (int)$c['asset_count'];
                    $cTotalCost += (float)$c['total_purchase_cost'];
                    $cTotalBook += (float)$c['total_book_value'];
                ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                    <td><code><?= htmlspecialchars($c['code']) ?></code></td>
                    <td class="center"><?= number_format((float)$c['depreciation_rate_annual'], 1) ?>%</td>
                    <td class="right"><?= $c['asset_count'] ?></td>
                    <td class="right">&#8377;<?= number_format((float)$c['total_purchase_cost'], 2) ?></td>
                    <td class="right fw-bold text-success">&#8377;<?= number_format((float)$c['total_book_value'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold" style="background: #f1f5f9;">
                    <td colspan="3">TOTAL</td>
                    <td class="right"><?= $cTotalCount ?></td>
                    <td class="right">&#8377;<?= number_format($cTotalCost, 2) ?></td>
                    <td class="right text-success">&#8377;<?= number_format($cTotalBook, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Facility Distribution Table -->
    <?php if (in_array('facilities', $selectedModules) && !empty($data['facilities'])): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Facility &amp; Operational Units Distribution</h2>
            <span class="section-count"><?= count($data['facilities']) ?> Operational Facilities</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Facility Name</th>
                    <th>Pillar Type</th>
                    <th class="right">Total Units</th>
                    <th class="right">In-Use Active</th>
                    <th class="right">Under Maintenance</th>
                    <th class="right">Damaged</th>
                    <th class="right">Total Book Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['facilities'] as $f): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($f['label']) ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($f['type']) ?></span></td>
                    <td class="right"><?= $f['asset_count'] ?></td>
                    <td class="right text-success"><?= $f['in_use_count'] ?></td>
                    <td class="right text-warning"><?= $f['in_maintenance'] ?></td>
                    <td class="right text-danger"><?= $f['damaged_count'] ?></td>
                    <td class="right fw-bold">&#8377;<?= number_format((float)$f['total_value'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Detailed Asset Inventory Registry Table -->
    <?php if (in_array('registry', $selectedModules) && !empty($data['registry'])): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Asset Inventory Registry</h2>
            <span class="section-count"><?= count($data['registry']) ?> Asset Items</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Asset Code</th>
                    <th>Name / Description</th>
                    <th>Category</th>
                    <th>Location &amp; Floor Area</th>
                    <th>Brand / Model</th>
                    <th>Serial # (SN)</th>
                    <th class="right">Purchase Cost</th>
                    <th class="right">Book Value</th>
                    <th class="center">Status</th>
                    <th class="center">Condition</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['registry'] as $a): 
                    $statBadge = match($a['status']) {
                        'IN_USE' => 'badge-success',
                        'IN_MAINTENANCE' => 'badge-warning',
                        'DAMAGED' => 'badge-danger',
                        default => 'badge-secondary'
                    };
                ?>
                <tr>
                    <td class="fw-bold"><code><?= htmlspecialchars($a['asset_code']) ?></code></td>
                    <td><?= htmlspecialchars($a['name']) ?></td>
                    <td><?= htmlspecialchars($a['category_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($a['location_display']) ?>
                        <?php if (!empty($a['floor_area'])): ?>
                            <small class="text-secondary">(<?= htmlspecialchars($a['floor_area']) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(trim(($a['brand'] ?? '') . ' ' . ($a['model_number'] ?? ''))) ?: '-' ?></td>
                    <td><?= htmlspecialchars($a['serial_number'] ?? '-') ?></td>
                    <td class="right">&#8377;<?= number_format((float)$a['purchase_cost'], 2) ?></td>
                    <td class="right fw-bold text-success">&#8377;<?= number_format((float)$a['current_book_value'], 2) ?></td>
                    <td class="center"><span class="badge <?= $statBadge ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                    <td class="center"><?= htmlspecialchars($a['condition_state']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Inter-Unit Movement & Transfers Manifest -->
    <?php if (in_array('transfers', $selectedModules) && !empty($data['transfers'])): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Inter-Unit Movement &amp; Relocation Manifest</h2>
            <span class="section-count"><?= count($data['transfers']) ?> Movement Records</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Transfer #</th>
                    <th>Date &amp; Time</th>
                    <th>Asset</th>
                    <th>Origin (From)</th>
                    <th>Destination (To)</th>
                    <th>Transferred By</th>
                    <th>Reason</th>
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['transfers'] as $t): ?>
                <tr>
                    <td class="fw-bold"><code><?= htmlspecialchars($t['transfer_number']) ?></code></td>
                    <td><?= date('d M Y, h:i A', strtotime($t['transfer_date'])) ?></td>
                    <td><strong><?= htmlspecialchars($t['asset_code']) ?></strong> &bull; <?= htmlspecialchars($t['asset_name']) ?></td>
                    <td><?= htmlspecialchars($t['from_display']) ?></td>
                    <td><?= htmlspecialchars($t['to_display']) ?> <?= !empty($t['to_floor_area']) ? '(' . htmlspecialchars($t['to_floor_area']) . ')' : '' ?></td>
                    <td><?= htmlspecialchars($t['transferred_by_name'] ?? 'System') ?></td>
                    <td><?= htmlspecialchars($t['reason'] ?: '-') ?></td>
                    <td class="center"><span class="badge badge-success"><?= htmlspecialchars($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Maintenance & Repair Tickets -->
    <?php if (in_array('maintenance', $selectedModules) && !empty($data['maintenance'])): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Maintenance, Repairs &amp; Service Tickets</h2>
            <span class="section-count"><?= count($data['maintenance']) ?> Service Records</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Date</th>
                    <th>Asset Code &amp; Name</th>
                    <th>Service Type</th>
                    <th>Issue Description</th>
                    <th>Technician / Vendor</th>
                    <th class="right">Cost</th>
                    <th class="center">Downtime</th>
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $mTotalCost = 0.0;
                $mTotalDowntime = 0.0;
                foreach ($data['maintenance'] as $m): 
                    $mTotalCost += (float)$m['service_cost'];
                    $mTotalDowntime += (float)$m['downtime_hours'];
                ?>
                <tr>
                    <td class="fw-bold"><code><?= htmlspecialchars($m['log_number']) ?></code></td>
                    <td><?= date('d M Y', strtotime($m['service_date'])) ?></td>
                    <td><strong><?= htmlspecialchars($m['asset_code']) ?></strong> &bull; <?= htmlspecialchars($m['asset_name']) ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($m['service_type']) ?></span></td>
                    <td><?= htmlspecialchars($m['issue_description'] ?: '-') ?></td>
                    <td><?= htmlspecialchars(trim(($m['technician_name'] ?? '') . ' / ' . ($m['serviced_by_vendor'] ?? ''))) ?: '-' ?></td>
                    <td class="right">&#8377;<?= number_format((float)$m['service_cost'], 2) ?></td>
                    <td class="center"><?= number_format((float)$m['downtime_hours'], 1) ?>h</td>
                    <td class="center"><span class="badge badge-success"><?= htmlspecialchars($m['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold" style="background: #f1f5f9;">
                    <td colspan="6">TOTAL MAINTENANCE EXPENDITURE &amp; DOWNTIME</td>
                    <td class="right text-danger">&#8377;<?= number_format($mTotalCost, 2) ?></td>
                    <td class="center"><?= number_format($mTotalDowntime, 1) ?>h</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Critical Condition Alerts -->
    <?php if (in_array('critical', $selectedModules) && !empty($data['critical'])): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Critical Condition &amp; Warranty Expiring Alerts</h2>
            <span class="section-count"><?= count($data['critical']) ?> High Priority Items</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Asset Code</th>
                    <th>Equipment Name</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th class="center">Condition</th>
                    <th class="center">Status</th>
                    <th>Warranty Expiry</th>
                    <th class="right">Current Book Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['critical'] as $cr): ?>
                <tr>
                    <td class="fw-bold"><code><?= htmlspecialchars($cr['asset_code']) ?></code></td>
                    <td><?= htmlspecialchars($cr['name']) ?></td>
                    <td><?= htmlspecialchars($cr['category_name']) ?></td>
                    <td><?= htmlspecialchars($cr['location_display']) ?></td>
                    <td class="center"><span class="badge badge-danger"><?= htmlspecialchars($cr['condition_state']) ?></span></td>
                    <td class="center"><span class="badge badge-warning"><?= htmlspecialchars($cr['status']) ?></span></td>
                    <td class="text-danger fw-bold"><?= !empty($cr['warranty_expiry']) ? date('d M Y', strtotime($cr['warranty_expiry'])) : 'No Warranty' ?></td>
                    <td class="right fw-bold">&#8377;<?= number_format((float)$cr['current_book_value'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</body>
</html>

