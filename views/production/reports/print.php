<?php
$sellerCompany = $sellerCompany ?? [];
$seller = static fn(string $key): string => htmlspecialchars((string)($sellerCompany[$key] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Production Unit Master Report — <?= htmlspecialchars($periodLabel) ?></title>
    <style>
        body{font:13px Arial,sans-serif;color:#222;margin:24px}.report-wrap{max-width:1100px;margin:auto}.header{display:flex;justify-content:space-between;border-bottom:2px solid #222;padding-bottom:12px;margin-bottom:18px}.section{margin:22px 0}h3{font-size:15px;margin:0 0 8px;background:#f3f4f6;padding:6px 10px;border-left:4px solid #0d6efd}table{width:100%;border-collapse:collapse;margin-bottom:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:12px}th{background:#f8f9fa;font-weight:bold}.right{text-align:right}.center{text-align:center}.actions{max-width:1100px;margin:0 auto 15px;text-align:right}.actions button{padding:8px 14px;background:#0d6efd;color:#fff;border:none;border-radius:4px;cursor:pointer}@media print{body{margin:0}.actions{display:none}.report-wrap{max-width:none}}
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print Report</button></div>
<main class="report-wrap">
    <div class="header">
        <div>
            <h2 style="margin:0 0 4px"><?= $seller('company_name') ?: 'GRM APPAREL INVENTORY' ?></h2>
            <div>Production Manufacturing Unit · Comprehensive Performance Report</div>
        </div>
        <div style="text-align:right">
            <h3 style="margin:0 0 4px;background:none;border:none;padding:0">PERIOD: <?= htmlspecialchars($periodLabel) ?></h3>
            <div><?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?></div>
            <div style="font-size:11px;color:#666">Generated on <?= date('d M Y, h:i A') ?></div>
        </div>
    </div>

    <?php if (!empty($data['rolls'])): ?>
        <div class="section">
            <h3>1. Fabric Rolls &amp; Inwards (<?= count($data['rolls']) ?> records)</h3>
            <table>
                <thead><tr><th>Roll #</th><th>Date</th><th>Supplier</th><th>Fabric</th><th>Color</th><th class="right">Weight (kg)</th><th class="right">Length (m)</th><th class="right">Cost/m</th><th class="right">Total (₹)</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($data['rolls'] as $r): ?>
                        <tr><td><?= htmlspecialchars($r['roll_number']) ?></td><td><?= date('d M Y', strtotime($r['created_at'])) ?></td><td><?= htmlspecialchars($r['supplier_company'] ?: ($r['supplier_name'] ?? '')) ?></td><td><?= htmlspecialchars($r['fabric_type']) ?></td><td><?= htmlspecialchars($r['color']) ?></td><td class="right"><?= $r['weight'] ?></td><td class="right"><?= $r['length'] ?></td><td class="right">₹<?= number_format((float)$r['cost_per_meter'], 2) ?></td><td class="right fw-bold">₹<?= number_format((float)$r['total_cost'], 2) ?></td><td><?= htmlspecialchars($r['status']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['programs'])): ?>
        <div class="section">
            <h3>2. Production Programs (<?= count($data['programs']) ?> records)</h3>
            <table>
                <thead><tr><th>Program #</th><th>Program Name</th><th>Style Code</th><th class="center">Target Pcs</th><th class="center">Status</th><th>Start Date</th><th>Due Date</th></tr></thead>
                <tbody>
                    <?php foreach ($data['programs'] as $p): ?>
                        <tr><td><?= htmlspecialchars($p['program_number']) ?></td><td><?= htmlspecialchars($p['program_name']) ?></td><td><?= htmlspecialchars($p['style_code'] ?? '-') ?></td><td class="center fw-bold"><?= number_format($p['target_pieces']) ?></td><td class="center"><?= htmlspecialchars($p['status']) ?></td><td><?= $p['start_date'] ?? '-' ?></td><td><?= $p['delivery_date'] ?? '-' ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['qc'])): ?>
        <div class="section">
            <h3>3. Quality Control (QC) &amp; Yield (<?= count($data['qc']) ?> batches)</h3>
            <table>
                <thead><tr><th>QC Batch #</th><th>Program #</th><th class="right">Inspected</th><th class="right">Passed</th><th class="right">Rework</th><th class="right">Rejected</th><th class="center">Yield %</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($data['qc'] as $q): ?>
                        <tr><td><?= htmlspecialchars($q['batch_number']) ?></td><td><?= htmlspecialchars($q['program_number'] ?? '-') ?></td><td class="right"><?= number_format($q['inspected_quantity']) ?></td><td class="right fw-bold" style="color:green"><?= number_format($q['passed_quantity']) ?></td><td class="right" style="color:orange"><?= number_format($q['rework_quantity']) ?></td><td class="right" style="color:red"><?= number_format($q['rejected_quantity']) ?></td><td class="center fw-bold"><?= $q['yield_percentage'] ?>%</td><td><?= htmlspecialchars($q['status']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['billing'])): ?>
        <div class="section">
            <h3>4. Factory Direct Billing &amp; Invoices (<?= count($data['billing']) ?> bills)</h3>
            <table>
                <thead><tr><th>Bill #</th><th>Date</th><th>Client / Customer</th><th class="center">Pcs</th><th class="right">Taxable (₹)</th><th class="right">GST (₹)</th><th class="right">Total (₹)</th><th>Payment</th></tr></thead>
                <tbody>
                    <?php foreach ($data['billing'] as $b): ?>
                        <tr><td><?= htmlspecialchars($b['bill_number']) ?></td><td><?= date('d M Y', strtotime($b['bill_date'])) ?></td><td><?= htmlspecialchars($b['client_name']) ?></td><td class="center"><?= (int)$b['total_pieces'] ?></td><td class="right">₹<?= number_format((float)$b['subtotal'], 2) ?></td><td class="right">₹<?= number_format((float)$b['tax_amount'], 2) ?></td><td class="right fw-bold">₹<?= number_format((float)$b['total_amount'], 2) ?></td><td><?= htmlspecialchars($b['payment_method']) ?> (<?= htmlspecialchars($b['payment_status']) ?>)</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['returns'])): ?>
        <div class="section">
            <h3>5. Returns &amp; Credit Notes (<?= count($data['returns']) ?> records)</h3>
            <table>
                <thead><tr><th>Credit Note #</th><th>Original Bill #</th><th>Customer</th><th class="center">Returned Pcs</th><th>Disposition</th><th class="right">GST Reversal (₹)</th><th class="right">Net Credit (₹)</th></tr></thead>
                <tbody>
                    <?php foreach ($data['returns'] as $ret): ?>
                        <tr><td><?= htmlspecialchars($ret['return_number']) ?></td><td><?= htmlspecialchars($ret['bill_number']) ?></td><td><?= htmlspecialchars($ret['customer_name']) ?></td><td class="center"><?= (int)$ret['total_returned_qty'] ?></td><td><?= htmlspecialchars($ret['disposition']) ?></td><td class="right">₹<?= number_format((float)$ret['gst_reversal_amount'], 2) ?></td><td class="right fw-bold" style="color:red">₹<?= number_format((float)$ret['net_amount'], 2) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['stock_requests'])): ?>
        <div class="section">
            <h3>6. Warehouse Stock Requisitions (<?= count($data['stock_requests']) ?> requests)</h3>
            <table>
                <thead><tr><th>Request #</th><th>Warehouse</th><th>Priority</th><th>Required By</th><th class="center">Requested</th><th class="center">Fulfilled</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($data['stock_requests'] as $sr): ?>
                        <tr><td><?= htmlspecialchars($sr['request_number']) ?></td><td><?= htmlspecialchars($sr['warehouse_name']) ?></td><td><?= htmlspecialchars($sr['priority']) ?></td><td><?= $sr['required_by_date'] ?? '-' ?></td><td class="center"><?= number_format($sr['total_requested_pieces']) ?></td><td class="center fw-bold" style="color:green"><?= number_format($sr['fulfilled_pieces']) ?></td><td><?= htmlspecialchars($sr['status']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['expenses'])): ?>
        <div class="section">
            <h3>7. Production Expenses &amp; Assets (<?= count($data['expenses']) ?> items)</h3>
            <table>
                <thead><tr><th>Expense Title</th><th>Category</th><th>Unit</th><th class="right">Cost (₹)</th><th class="right">Paid (₹)</th><th class="right">Pending (₹)</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($data['expenses'] as $e): ?>
                        <tr><td><?= htmlspecialchars($e['title']) ?></td><td><?= htmlspecialchars($e['category']) ?></td><td><?= htmlspecialchars($e['production_unit']) ?></td><td class="right fw-bold">₹<?= number_format((float)$e['total_cost'], 2) ?></td><td class="right" style="color:green">₹<?= number_format((float)$e['paid_amount'], 2) ?></td><td class="right" style="color:red">₹<?= number_format((float)$e['pending_amount'], 2) ?></td><td><?= htmlspecialchars($e['payment_status']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div style="text-align:center;border-top:1px solid #ddd;padding-top:12px;font-size:11px;color:#777">
        GRM Apparel ERP · Confidential Production Intelligence Report · <?= date('Y') ?>
    </div>
</main>
</body>
</html>
