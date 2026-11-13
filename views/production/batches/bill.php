<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Delivery Bill - <?= htmlspecialchars($batch['batch_number'] ?? 'Batch') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 13px;
        }
        .bill-container {
            max-width: 960px;
            margin: 20px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        .bill-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .meta-box {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 12px 15px;
        }
        .table-bill th {
            background-color: #0f172a !important;
            color: #fff !important;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .signature-box {
            margin-top: 50px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            text-align: center;
        }
        @media print {
            body { background: #fff; }
            .bill-container { margin: 0; padding: 10px; box-shadow: none; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container my-3 no-print">
    <div class="d-flex justify-content-between align-items-center">
        <a href="<?= url('production/batches') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Batches
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer me-1"></i> Print Batch Bill
            </button>
        </div>
    </div>
</div>

<div class="bill-container">
    <!-- Header -->
    <div class="bill-header">
        <div class="row align-items-center">
            <div class="col-8">
                <?php if (!empty($prodUnit['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($prodUnit['logo_url']) ?>" alt="Logo" style="max-height: 50px;" class="mb-2">
                <?php endif; ?>
                <h4 class="fw-bold mb-1 text-uppercase"><?= htmlspecialchars($prodUnit['name'] ?? 'GRM Production Unit') ?></h4>
                <div class="text-muted small">
                    <?= htmlspecialchars($prodUnit['address'] ?? 'Apparel Production Facility') ?><br>
                    Phone: <?= htmlspecialchars($prodUnit['phone'] ?? '—') ?> | GSTIN: <?= htmlspecialchars($prodUnit['gst_number'] ?? '33AABCU9603R1ZU') ?>
                </div>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-dark text-white px-3 py-2 text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Finished Batch Transfer Bill</span>
                <div class="fs-5 fw-bold font-monospace text-primary"><?= htmlspecialchars($batch['batch_number']) ?></div>
                <div class="small text-muted">Date: <?= !empty($batch['created_at']) ? date('d M Y', strtotime($batch['created_at'])) : date('d M Y') ?></div>
            </div>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="meta-box h-100">
                <span class="small text-muted text-uppercase fw-bold d-block mb-1">Destination Warehouse (Consignee)</span>
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($batch['warehouse_name'] ?? 'Main Warehouse') ?> (<?= htmlspecialchars($batch['warehouse_code'] ?? 'WH') ?>)</div>
                <div class="small text-muted"><?= htmlspecialchars($batch['warehouse_address'] ?? 'Central Distribution Facility') ?></div>
                <div class="small text-muted mt-1">Status: <strong class="text-uppercase text-primary"><?= htmlspecialchars($batch['status']) ?></strong></div>
            </div>
        </div>
        <div class="col-6">
            <div class="meta-box h-100">
                <span class="small text-muted text-uppercase fw-bold d-block mb-1">Batch &amp; Logistics Particulars</span>
                <div><strong>Program:</strong> <span class="font-monospace"><?= htmlspecialchars($batch['program_number'] ?? '—') ?></span></div>
                <div><strong>Product Style:</strong> <?= htmlspecialchars($batch['product_name'] ?? 'Finished Garments') ?></div>
                <div><strong>Master Cartons:</strong> <span class="badge bg-primary font-monospace"><?= (int)($batch['box_count'] ?? 1) ?> BOXES</span></div>
                <div><strong>Packed By:</strong> <?= htmlspecialchars($batch['prepared_by_name'] ?? 'Staff') ?></div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-bill align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width: 4%;">#</th>
                    <th>Product &amp; SKU</th>
                    <th class="text-center" style="width: 9%;">HSN Code</th>
                    <th class="text-center" style="width: 7%;">Size</th>
                    <th class="text-center" style="width: 8%;">Color</th>
                    <th class="text-center" style="width: 9%;">Quantity</th>
                    <th class="text-end" style="width: 10%;">Rate (₹)</th>
                    <th class="text-center" style="width: 12%;">GST Format</th>
                    <th class="text-end" style="width: 13%;">Tax Amount (₹)</th>
                    <th class="text-end" style="width: 14%;">Valuation (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $puGstin = trim((string)($prodUnit['gst_number'] ?? ($batch['production_unit_gstin'] ?? '33AAACS1429B1ZQ')));
                    $whGstin = trim((string)($batch['warehouse_gstin'] ?? ''));
                    $puStateCode = strlen($puGstin) >= 2 ? substr($puGstin, 0, 2) : '33';
                    $whStateCode = strlen($whGstin) >= 2 ? substr($whGstin, 0, 2) : $puStateCode;
                    $isInterStateConsignment = ($whStateCode !== '' && $puStateCode !== '' && $whStateCode !== $puStateCode);

                    $totalQty = 0;
                    $totalTaxable = 0.0;
                    $totalCgst = 0.0;
                    $totalSgst = 0.0;
                    $totalIgst = 0.0;
                    $totalTax = 0.0;
                    $totalValuation = 0.0;

                    foreach ($items as $idx => $it):
                        $qty = (int)($it['quantity'] ?? 0);
                        $rate = (float)($it['qc_discount_price'] ?: ($it['qc_base_price'] ?: ($it['selling_price'] ?: ($it['mrp'] ?: 0))));
                        $grossValuation = round($qty * $rate, 2);
                        $hsn = !empty($it['hsn_code']) ? $it['hsn_code'] : '6109';
                        $gstPercent = isset($it['gst_rate']) && (float)$it['gst_rate'] > 0 
                            ? (float)$it['gst_rate'] 
                            : (isset($it['gst_percentage']) && (float)$it['gst_percentage'] > 0 ? (float)$it['gst_percentage'] : 5.0);

                        $itemGstType = !empty($it['gst_type']) ? strtoupper($it['gst_type']) : ($isInterStateConsignment ? 'IGST' : 'BOTH');

                        // GST is INCLUSIVE: Base = Gross / (1 + GST%), Tax = Gross - Base
                        if ($itemGstType !== 'EXEMPTED' && $gstPercent > 0) {
                            $taxable = round($grossValuation / (1 + ($gstPercent / 100)), 2);
                            $taxAmount = round($grossValuation - $taxable, 2);
                        } else {
                            $taxable = $grossValuation;
                            $taxAmount = 0.0;
                        }

                        $cgstRate = 0.0;
                        $sgstRate = 0.0;
                        $igstRate = 0.0;
                        $cgstAmount = 0.0;
                        $sgstAmount = 0.0;
                        $igstAmount = 0.0;

                        if ($itemGstType === 'IGST') {
                            $igstRate = $gstPercent;
                            $igstAmount = $taxAmount;
                        } elseif ($itemGstType === 'CGST') {
                            $cgstRate = $gstPercent;
                            $cgstAmount = $taxAmount;
                        } elseif ($itemGstType === 'SGST') {
                            $sgstRate = $gstPercent;
                            $sgstAmount = $taxAmount;
                        } elseif ($itemGstType === 'EXEMPTED' || $gstPercent <= 0) {
                            $taxAmount = 0.0;
                        } else {
                            $itemGstType = 'BOTH';
                            $cgstRate = round($gstPercent / 2, 2);
                            $sgstRate = round($gstPercent / 2, 2);
                            $cgstAmount = round($taxAmount / 2, 2);
                            $sgstAmount = round($taxAmount - $cgstAmount, 2);
                        }

                        $valuation = $grossValuation;

                        $totalQty += $qty;
                        $totalTaxable += $taxable;
                        $totalCgst += $cgstAmount;
                        $totalSgst += $sgstAmount;
                        $totalIgst += $igstAmount;
                        $totalTax += $taxAmount;
                        $totalValuation += $valuation;
                ?>
                    <tr>
                        <td class="text-center"><?= $idx + 1 ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($it['product_name'] ?? 'Finished Product') ?></div>
                            <div class="small text-muted font-monospace"><?= htmlspecialchars($it['sku'] ?? '—') ?></div>
                        </td>
                        <td class="text-center font-monospace small"><?= htmlspecialchars($hsn) ?></td>
                        <td class="text-center fw-semibold"><?= htmlspecialchars($it['size_name'] ?: 'Std') ?></td>
                        <td class="text-center fw-semibold"><?= htmlspecialchars($it['color_name'] ?: 'Std') ?></td>
                        <td class="text-center fw-bold text-dark fs-6"><?= number_format($qty) ?> pcs</td>
                        <td class="text-end font-monospace">₹<?= number_format($rate, 2) ?></td>
                        <td class="text-center font-monospace">
                            <?php if ($itemGstType === 'IGST'): ?>
                                <div class="fw-bold text-dark"><?= rtrim(rtrim(number_format($igstRate, 2), '0'), '.') ?>%</div>
                                <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 10px;">IGST</span>
                            <?php elseif ($itemGstType === 'BOTH'): ?>
                                <div class="fw-bold text-dark"><?= rtrim(rtrim(number_format($gstPercent, 2), '0'), '.') ?>%</div>
                                <div class="small text-muted mt-0.5" style="font-size: 10px; line-height: 1.25;">
                                    <span>CGST: <?= rtrim(rtrim(number_format($cgstRate, 2), '0'), '.') ?>%</span><br>
                                    <span>SGST: <?= rtrim(rtrim(number_format($sgstRate, 2), '0'), '.') ?>%</span>
                                </div>
                            <?php elseif ($itemGstType === 'CGST'): ?>
                                <div class="fw-bold text-dark"><?= rtrim(rtrim(number_format($cgstRate, 2), '0'), '.') ?>%</div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 10px;">CGST</span>
                            <?php elseif ($itemGstType === 'SGST'): ?>
                                <div class="fw-bold text-dark"><?= rtrim(rtrim(number_format($sgstRate, 2), '0'), '.') ?>%</div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 10px;">SGST</span>
                            <?php else: ?>
                                <span class="text-muted small">0% (Exempt)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end font-monospace">
                            <div class="fw-bold text-dark">₹<?= number_format($taxAmount, 2) ?></div>
                            <?php if ($itemGstType === 'BOTH' && $taxAmount > 0): ?>
                                <div class="small text-muted mt-0.5" style="font-size: 10px; line-height: 1.25;">
                                    <span>CGST: ₹<?= number_format($cgstAmount, 2) ?></span><br>
                                    <span>SGST: ₹<?= number_format($sgstAmount, 2) ?></span>
                                </div>
                            <?php elseif ($itemGstType === 'IGST' && $taxAmount > 0): ?>
                                <div class="small text-muted mt-0.5" style="font-size: 10px;">
                                    <span>IGST: ₹<?= number_format($igstAmount, 2) ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold font-monospace">₹<?= number_format($valuation, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold" style="background-color: #f8fafc;">
                    <td colspan="5" class="text-end text-uppercase">Total Packed Goods:</td>
                    <td class="text-center text-primary fs-6"><?= number_format($totalQty) ?> pcs</td>
                    <td class="text-end font-monospace">₹<?= number_format($totalValuation, 2) ?></td>
                    <td class="text-center font-monospace small">
                        <?php if ($totalIgst > 0 && $totalCgst <= 0): ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 10px;">IGST</span>
                        <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 10px;">CGST + SGST</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end font-monospace text-primary">
                        <div>₹<?= number_format($totalTax, 2) ?></div>
                        <?php if ($totalCgst > 0 || $totalSgst > 0): ?>
                            <div class="small text-muted fw-normal" style="font-size: 10px; line-height: 1.2;">
                                CGST: ₹<?= number_format($totalCgst, 2) ?><br>SGST: ₹<?= number_format($totalSgst, 2) ?>
                            </div>
                        <?php elseif ($totalIgst > 0): ?>
                            <div class="small text-muted fw-normal" style="font-size: 10px;">
                                IGST: ₹<?= number_format($totalIgst, 2) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-success fs-6 font-monospace">₹<?= number_format($totalValuation, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Signatures -->
    <div class="row text-center mt-5">
        <div class="col-4">
            <div class="signature-box">
                <div class="fw-bold text-dark"><?= htmlspecialchars($batch['prepared_by_name'] ?? 'Packing In-charge') ?></div>
                <div class="small text-muted">Packed By / In-charge</div>
            </div>
        </div>
        <div class="col-4">
            <div class="signature-box">
                <div class="fw-bold text-dark"><?= htmlspecialchars($batch['approved_by_name'] ?? 'Production Manager') ?></div>
                <div class="small text-muted">Quality / Unit Approved</div>
            </div>
        </div>
        <div class="col-4">
            <div class="signature-box">
                <div class="fw-bold text-dark">Warehouse Receiver Sign &amp; Date</div>
                <div class="small text-muted">Quality / Warehouse Approved</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
