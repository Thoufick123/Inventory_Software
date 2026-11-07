<?php
// Production Unit Customer Return & Credit Note Detail View
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-receipt-cutoff text-danger me-2"></i>Credit Note #<?= e($return['return_number']) ?>
            </h4>
            <span class="text-muted small">Generated on <?= date('d M Y, h:i A', strtotime($return['created_at'])) ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('production/returns') ?>" class="btn btn-outline-secondary btn-sm fw-semibold shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Returns Log
            </a>
            <a href="<?= url('production/returns/print/' . $return['id']) ?>" target="_blank" class="btn btn-primary btn-sm fw-semibold shadow-sm">
                <i class="bi bi-printer me-1"></i> Print Credit Note
            </a>
        </div>
    </div>

    <!-- Summary Details Cards -->
    <div class="row g-3 mb-4">
        <!-- Return Overview -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-info-circle text-primary me-2"></i>Return Summary</h6>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 160px;">Credit Note #:</td>
                            <td><strong class="font-monospace text-danger"><?= e($return['return_number']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Original Bill #:</td>
                            <td>
                                <?php if ($return['bill_id']): ?>
                                    <a href="<?= url('production/billing/view/' . $return['bill_id']) ?>" class="font-monospace text-primary fw-semibold text-decoration-none">
                                        <?= e($return['bill_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="font-monospace"><?= e($return['bill_number'] ?? '-') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Customer / Entity:</td>
                            <td><strong><?= e($return['customer_name']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Contact Phone:</td>
                            <td><?= e($return['customer_phone'] ?: '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Return Type:</td>
                            <td><span class="badge bg-secondary-subtle text-secondary border"><?= e($return['return_type']) ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Stock Disposition:</td>
                            <td>
                                <?php if ($return['disposition'] === 'RESTOCK_INVENTORY'): ?>
                                    <span class="badge bg-success-subtle text-success border">Restocked to Inventory</span>
                                <?php elseif ($return['disposition'] === 'REWORK_STITCHING'): ?>
                                    <span class="badge bg-warning-subtle text-warning border">Sent to Sewing Rework</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border">Scrapped</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Financial Breakdown -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-cash-stack text-success me-2"></i>Financial &amp; GST Adjustment</h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Total Returned Quantity:</span>
                        <strong class="text-dark"><?= (int)$return['total_returned_qty'] ?> pieces</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Taxable Value Reversal:</span>
                        <span class="fw-semibold">₹<?= number_format((float)$return['subtotal'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">GST Tax Reversal:</span>
                        <span class="fw-semibold text-info">₹<?= number_format((float)$return['gst_reversal_amount'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="fw-bold fs-6 text-dark">Net Credit / Refund Amount:</span>
                        <strong class="text-danger fs-5">₹<?= number_format((float)$return['net_amount'], 2) ?></strong>
                    </div>
                    <div class="small text-muted mt-2">
                        Settlement Mode: <strong class="text-dark"><?= e($return['refund_mode']) ?></strong> · Status: <span class="badge bg-success"><?= e($return['status']) ?></span>
                    </div>
                    <?php if (!empty($return['notes'])): ?>
                        <div class="mt-2 p-2 bg-light rounded text-muted small">
                            <strong>Notes:</strong> <?= nl2br(e($return['notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Returned Items Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-box-seam text-danger me-2"></i>Returned Garments &amp; Verified Piece Tags (<?= count($items) ?>)
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Item Details</th>
                            <th>HSN</th>
                            <th class="text-center">Piece Tag</th>
                            <th class="text-center">Condition</th>
                            <th class="text-center">Action Taken</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Taxable</th>
                            <th class="text-end">GST</th>
                            <th class="text-end">Total Refund</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($it['product_name']) ?></div>
                                    <div class="small text-muted"><?= e($it['sku'] ?? '') ?> · Reason: <em><?= e($it['reason']) ?></em></div>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= e($it['hsn_code'] ?? '6109') ?></span></td>
                                <td class="text-center">
                                    <?php if ($it['tag_code']): ?>
                                        <span class="badge bg-dark font-monospace"><?= e($it['tag_code']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= (int)$it['quantity'] ?> pcs</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= e($it['item_condition']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($it['action_taken'] === 'RESTOCKED'): ?>
                                        <span class="badge bg-success text-white">Restocked</span>
                                    <?php elseif ($it['action_taken'] === 'SENT_TO_REWORK'): ?>
                                        <span class="badge bg-warning text-dark">Rework</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger text-white">Scrapped</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">₹<?= number_format((float)$it['unit_price'], 2) ?></td>
                                <td class="text-end">₹<?= number_format((float)$it['taxable_amount'], 2) ?></td>
                                <td class="text-end text-muted">₹<?= number_format((float)$it['tax_amount'], 2) ?> (<?= (float)$it['gst_percentage'] ?>%)</td>
                                <td class="text-end fw-bold text-danger">₹<?= number_format((float)$it['total_amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
