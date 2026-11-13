<?php
$isDispatched = ($batch['status'] === 'DISPATCHED' || $batch['status'] === 'RECEIVED' || !empty($batch['outward_id']) || !empty($batch['production_outward_id']));
$statusClass = match ($batch['status']) {
    'APPROVED'   => 'bg-success text-white',
    'DISPATCHED' => 'bg-primary text-white',
    'RECEIVED'   => 'bg-info text-dark',
    'CANCELLED'  => 'bg-danger text-white',
    default      => 'bg-warning text-dark',
};
$totalValuation = 0.0;
$totalTaxable = 0.0;
$totalTax = 0.0;
foreach ($items as $it) {
    $qty = (int)($it['quantity'] ?? 0);
    $price = (float)($it['qc_discount_price'] ?: ($it['qc_base_price'] ?: ($it['selling_price'] ?: ($it['mrp'] ?: 0))));
    $gst = 5.0;
    $taxable = $qty * $price;
    $tax = $taxable * ($gst / 100);
    $totalTaxable += $taxable;
    $totalTax += $tax;
    $totalValuation += ($taxable + $tax);
}
if (!empty($bill['total_amount'])) {
    $totalValuation = (float)$bill['total_amount'];
}
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Batch <?= e($batch['batch_number'] ?? ('#' . $batch['id'])) ?></h2>
            <span class="badge <?= $statusClass ?> fs-6 px-3 py-1.5"><?= e($batch['status']) ?></span>
        </div>
        <span class="text-muted">Finished production packing details, carton allocation, piece QR codes &amp; billing</span>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= url('production/batches') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Batches
        </a>
        <a href="<?= url('production/batches/label/' . $batch['id']) ?>" target="_blank" class="btn btn-outline-dark">
            <i class="bi bi-qr-code me-1"></i> Carton QR Label
        </a>
        <a href="<?= url('production/batches/bill/' . $batch['id']) ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-ruled me-1"></i> Batch Transfer Bill
        </a>
        <?php if (!empty($batch['production_bill_id'])): ?>
            <a href="<?= url('production/billing/print/' . (int)$batch['production_bill_id']) ?>" target="_blank" class="btn btn-success">
                <i class="bi bi-receipt me-1"></i> Print Tax Invoice
            </a>
        <?php else: ?>
            <a href="<?= url('production/batches/invoice/' . $batch['id']) ?>" target="_blank" class="btn btn-outline-success">
                <i class="bi bi-receipt me-1"></i> Tax Invoice
            </a>
        <?php endif; ?>
        <?php if (!$isDispatched && hasPermission('batches', 'delete')): ?>
            <form method="POST" action="<?= url('production/batches/delete/' . $batch['id']) ?>" class="d-inline" onsubmit="return confirm('Delete Batch <?= e($batch['batch_number']) ?>? All garments and QR codes will be returned to QC inventory.');">
                <?= CSRF::field() ?>
                <button type="submit" class="btn btn-outline-danger" title="Delete Batch">
                    <i class="bi bi-trash3 me-1"></i> Delete
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-3 bg-primary-subtle text-primary p-3 me-3">
                    <i class="bi bi-layers fs-3"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block">Total Garments</span>
                    <h3 class="mb-0 fw-bold text-dark"><?= number_format((int)($batch['total_qty'] ?? $batch['quantity'] ?? 0)) ?> <span class="fs-6 text-muted fw-normal">pcs</span></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-3 bg-warning-subtle text-warning-emphasis p-3 me-3">
                    <i class="bi bi-boxes fs-3"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block">Carton / Boxes</span>
                    <h3 class="mb-0 fw-bold text-dark"><?= number_format((int)($batch['box_count'] ?? 1)) ?> <span class="fs-6 text-muted fw-normal">boxes</span></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-3 bg-info-subtle text-info-emphasis p-3 me-3">
                    <i class="bi bi-building fs-3"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block">Destination WH</span>
                    <h5 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 170px;" title="<?= e($batch['warehouse_name'] ?? 'Unassigned') ?>"><?= e($batch['warehouse_name'] ?? 'Unassigned') ?></h5>
                    <small class="text-muted font-monospace"><?= e($batch['warehouse_code'] ?? '') ?></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-3 bg-success-subtle text-success p-3 me-3">
                    <i class="bi bi-receipt-cutoff fs-3"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block">Bill &amp; Invoice Total</span>
                    <h3 class="mb-0 fw-bold text-success">₹<?= number_format($totalValuation, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Batch Details Column -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="bi bi-info-circle me-2 text-primary"></i>Batch Specifications &amp; Production Source
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="text-muted fw-normal" style="width: 40%;">Batch Number:</th>
                                <td class="font-monospace fw-bold text-dark"><?= e($batch['batch_number']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Production Program:</th>
                                <td>
                                    <span class="fw-semibold text-primary"><?= e($batch['program_number'] ?? '—') ?></span>
                                    <?php if (!empty($batch['program_name'])): ?>
                                        <span class="text-muted small d-block"><?= e($batch['program_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Product / Style:</th>
                                <td class="fw-bold text-dark"><?= e($batch['product_name'] ?? 'Finished Product') ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">QC Batch Ref:</th>
                                <td>
                                    <?php if (!empty($batch['qc_batch_id'])): ?>
                                        <span class="badge bg-light text-dark border font-monospace">QC #<?= (int)$batch['qc_batch_id'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Packed By:</th>
                                <td><?= e($batch['prepared_by_name'] ?? 'Production Staff') ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Packed Date:</th>
                                <td><?= !empty($batch['created_at']) ? date('d M Y, h:i A', strtotime($batch['created_at'])) : '—' ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Approved By:</th>
                                <td>
                                    <?php if (!empty($batch['approved_by_name'])): ?>
                                        <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i><?= e($batch['approved_by_name']) ?></span>
                                        <small class="text-muted d-block"><?= !empty($batch['approved_at']) ? date('d M Y, h:i A', strtotime($batch['approved_at'])) : '' ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Pending Approval</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($batch['notes'])): ?>
                                <tr>
                                    <th class="text-muted fw-normal align-top">Batch Notes:</th>
                                    <td class="text-dark small bg-light p-2 rounded"><?= nl2br(e($batch['notes'])) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Linked Bill & Invoice Column -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="fw-bold"><i class="bi bi-receipt me-2 text-success"></i>Official Bill &amp; Tax Invoice</span>
                <?php if ($bill): ?>
                    <a href="<?= url('production/billing/print/' . (int)$bill['id']) ?>" target="_blank" class="btn btn-outline-success btn-sm fw-semibold">
                        <i class="bi bi-printer me-1"></i> Print Invoice
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-3">
                <?php if ($bill): ?>
                    <div class="table-responsive">
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th class="text-muted fw-normal" style="width: 40%;">Invoice Number:</th>
                                    <td class="font-monospace fw-bold text-success fs-6"><?= e($bill['bill_number']) ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Invoice Date:</th>
                                    <td><?= date('d M Y', strtotime($bill['bill_date'])) ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Buyer / Destination:</th>
                                    <td class="fw-semibold text-dark"><?= e($bill['company_name'] ?: ($bill['customer_name'] ?? '—')) ?></td>
                                </tr>
                                <?php if (!empty($bill['company_gstin']) || !empty($bill['customer_gstin'])): ?>
                                    <tr>
                                        <th class="text-muted fw-normal">GSTIN:</th>
                                        <td class="font-monospace"><?= e($bill['company_gstin'] ?: $bill['customer_gstin']) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <th class="text-muted fw-normal">Payment Status:</th>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><?= e($bill['payment_status'] ?? 'PAID') ?></span>
                                        <span class="text-muted small ms-1">(<?= e($bill['payment_method'] ?? 'CREDIT') ?>)</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Taxable Subtotal:</th>
                                    <td class="fw-bold text-dark">₹<?= number_format((float)($bill['subtotal'] ?? 0), 2) ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">GST Breakdown:</th>
                                    <td class="small text-muted">
                                        CGST: ₹<?= number_format((float)($bill['cgst_amount'] ?? 0), 2) ?> · SGST: ₹<?= number_format((float)($bill['sgst_amount'] ?? 0), 2) ?>
                                        <?php if ((float)($bill['igst_amount'] ?? 0) > 0): ?> · IGST: ₹<?= number_format((float)$bill['igst_amount'], 2) ?><?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Total Invoice Value:</th>
                                    <td class="fs-5 fw-bold text-success">₹<?= number_format((float)($bill['total_amount'] ?? 0), 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-receipt text-secondary fs-1 d-block mb-2"></i>
                        <p class="mb-3">No official production bill is linked to this batch yet.</p>
                        <a href="<?= url('production/billing/create?batch_id=' . $batch['id']) ?>" class="btn btn-outline-success fw-semibold">
                            <i class="bi bi-plus-circle me-1"></i> Generate Bill &amp; Invoice Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Packed Products & QR Piece Codes -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0 fw-bold">
            <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Packed Garments Breakdown &amp; Piece QR Codes
        </h5>
        <span class="badge bg-primary fs-6 px-3 py-1.5"><?= number_format(count($items)) ?> Variants Packed</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">Product Variant / SKU</th>
                    <th style="width: 12%;">Size &amp; Color</th>
                    <th class="text-center" style="width: 10%;">Packed Qty</th>
                    <th class="text-end" style="width: 12%;">Unit Price</th>
                    <th class="text-end" style="width: 12%;">Subtotal</th>
                    <th style="width: 24%;">Packed Piece QR Codes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No variant items found in this batch.</td></tr>
                <?php else: ?>
                    <?php 
                        $totalQtyAll = 0;
                        $totalSumAll = 0;
                        foreach ($items as $idx => $it): 
                            $qty = (int)($it['quantity'] ?? 0);
                            $price = (float)($it['qc_discount_price'] ?: ($it['qc_base_price'] ?: ($it['selling_price'] ?: ($it['mrp'] ?: 0))));
                            $rowTot = $qty * $price;
                            $totalQtyAll += $qty;
                            $totalSumAll += $rowTot;

                            $rawTags = $it['packed_piece_tags'] ?? $it['piece_tags'] ?? [];
                            $tags = is_array($rawTags) ? $rawTags : (json_decode($rawTags, true) ?: explode(',', (string)$rawTags));
                            $tags = array_values(array_filter(array_map('trim', $tags)));
                    ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($it['product_name'] ?? 'Finished Product') ?></div>
                                <div class="small text-muted font-monospace"><?= e($it['sku'] ?? '—') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= e($it['size_name'] ?: 'Std') ?></span>
                                <span class="badge bg-light text-dark border"><?= e($it['color_name'] ?: 'Std') ?></span>
                            </td>
                            <td class="text-center fw-bold text-success fs-6"><?= number_format($qty) ?> pcs</td>
                            <td class="text-end">₹<?= number_format($price, 2) ?></td>
                            <td class="text-end fw-bold">₹<?= number_format($rowTot, 2) ?></td>
                            <td>
                                <?php if (empty($tags)): ?>
                                    <span class="text-muted small">No specific piece tags logged</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1" style="max-height: 90px; overflow-y: auto;">
                                        <?php foreach ($tags as $t): ?>
                                            <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;"><?= e($t) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted mt-1 d-block"><?= count($tags) ?> piece QR code(s)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3" class="text-end">Total Packed Garments:</td>
                    <td class="text-center text-success"><?= number_format($totalQtyAll ?? 0) ?> pcs</td>
                    <td class="text-end">Total Taxable:</td>
                    <td class="text-end">₹<?= number_format($totalSumAll ?? 0, 2) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if ($outward): ?>
<!-- Outward Dispatch Details -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white py-3 fw-bold">
        <i class="bi bi-truck me-2"></i>Outward Dispatch to Warehouse
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            <div class="col-md-3">
                <span class="small text-muted text-uppercase fw-semibold d-block">Outward Document #</span>
                <span class="font-monospace fw-bold text-primary fs-6"><?= e($outward['outward_number'] ?? ('OUT-' . $outward['id'])) ?></span>
            </div>
            <div class="col-md-3">
                <span class="small text-muted text-uppercase fw-semibold d-block">Vehicle / Transport #</span>
                <span class="badge bg-dark font-monospace fs-6"><?= e($outward['vehicle_number'] ?? '—') ?></span>
            </div>
            <div class="col-md-3">
                <span class="small text-muted text-uppercase fw-semibold d-block">Dispatch Date</span>
                <span class="text-dark fw-bold"><?= !empty($outward['dispatch_date']) ? date('d M Y, h:i A', strtotime($outward['dispatch_date'])) : '—' ?></span>
            </div>
            <div class="col-md-3">
                <span class="small text-muted text-uppercase fw-semibold d-block">Transporter / LR #</span>
                <span class="text-dark"><?= e($outward['transporter_name'] ?? '—') ?> <?= !empty($outward['lr_number']) ? '(' . e($outward['lr_number']) . ')' : '' ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
