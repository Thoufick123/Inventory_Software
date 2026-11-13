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
            max-width: 850px;
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
                    <th class="text-center" style="width: 5%;">#</th>
                    <th>Product &amp; SKU</th>
                    <th class="text-center" style="width: 12%;">Size</th>
                    <th class="text-center" style="width: 12%;">Color</th>
                    <th class="text-center" style="width: 15%;">Quantity</th>
                    <th class="text-end" style="width: 15%;">Rate (₹)</th>
                    <th class="text-end" style="width: 18%;">Valuation (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $totalQty = 0;
                    $totalAmt = 0.0;
                    foreach ($items as $idx => $it):
                        $qty = (int)($it['quantity'] ?? 0);
                        $rate = (float)($it['qc_discount_price'] ?: ($it['qc_base_price'] ?: ($it['selling_price'] ?: ($it['mrp'] ?: 0))));
                        $amt = $qty * $rate;
                        $totalQty += $qty;
                        $totalAmt += $amt;
                ?>
                    <tr>
                        <td class="text-center"><?= $idx + 1 ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($it['product_name'] ?? 'Finished Product') ?></div>
                            <div class="small text-muted font-monospace"><?= htmlspecialchars($it['sku'] ?? '—') ?></div>
                        </td>
                        <td class="text-center fw-semibold"><?= htmlspecialchars($it['size_name'] ?: 'Std') ?></td>
                        <td class="text-center fw-semibold"><?= htmlspecialchars($it['color_name'] ?: 'Std') ?></td>
                        <td class="text-center fw-bold text-dark fs-6"><?= number_format($qty) ?> pcs</td>
                        <td class="text-end font-monospace">₹<?= number_format($rate, 2) ?></td>
                        <td class="text-end fw-bold font-monospace">₹<?= number_format($amt, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold" style="background-color: #f8fafc;">
                    <td colspan="4" class="text-end text-uppercase">Total Packed Goods:</td>
                    <td class="text-center text-primary fs-6"><?= number_format($totalQty) ?> pcs</td>
                    <td class="text-end">Est. Valuation:</td>
                    <td class="text-end text-success fs-6">₹<?= number_format($totalAmt, 2) ?></td>
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
                <div class="fw-bold text-dark">___________________</div>
                <div class="small text-muted">Warehouse Receiver Sign &amp; Date</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
