<?php
$activeAdjustmentType = strtoupper((string)($type ?? ''));
if (!in_array($activeAdjustmentType, ['INCREASE', 'DECREASE'], true)) {
    $activeAdjustmentType = '';
}
$selectedVariantSku = '';
foreach ($variants as $variantOption) {
    if ((int)$variantOption['id'] === (int)($selectedVariantId ?? 0)) {
        $selectedVariantSku = (string)$variantOption['sku'];
        break;
    }
}
?>
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-sliders me-2 text-primary"></i>Stock Adjustments</h2>
        <span class="text-muted">Scan products, preview QR labels for increases, and post stock changes immediately</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('stock', 'adjust')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAdjustmentModal">
            <i class="bi bi-plus-circle me-1"></i> New Adjustment
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('warehouse/adjustments') ?>" class="row g-3">
            <?php if ($activeAdjustmentType !== ''): ?>
                <input type="hidden" name="adjustment_type" value="<?= e($activeAdjustmentType) ?>">
            <?php endif; ?>
            <div class="<?= $activeAdjustmentType === '' ? 'col-md-4' : 'col-md-5' ?>">
                <label class="form-label small fw-semibold">Warehouse</label>
                <select name="warehouse_id" class="form-select" <?= ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1) ? 'disabled' : '' ?>>
                    <?php if (!function_exists('currentWarehouseId') || currentWarehouseId() === null && count($warehouses) > 1): ?>
                        <option value="">All Warehouses</option>
                    <?php endif; ?>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= $wh['id'] ?>" <?= (($warehouseId ?? '') == $wh['id'] || (function_exists('currentWarehouseId') && currentWarehouseId() == $wh['id'])) ? 'selected' : '' ?>>
                            <?= e($wh['name']) ?><?= !empty($wh['code']) ? ' (' . e($wh['code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1): ?>
                    <input type="hidden" name="warehouse_id" value="<?= e($warehouseId ?? ($warehouses[0]['id'] ?? '')) ?>">
                <?php endif; ?>
            </div>
            <div class="<?= $activeAdjustmentType === '' ? 'col-md-3' : 'col-md-5' ?>">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="PENDING" <?= ($status ?? '') === 'PENDING' ? 'selected' : '' ?>>PENDING (Awaiting Approval)</option>
                    <option value="APPROVED" <?= ($status ?? '') === 'APPROVED' ? 'selected' : '' ?>>APPROVED (Stock Updated)</option>
                    <option value="REJECTED" <?= ($status ?? '') === 'REJECTED' ? 'selected' : '' ?>>REJECTED</option>
                </select>
            </div>
            <?php if ($activeAdjustmentType === ''): ?>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Adjustment Type</label>
                <select name="adjustment_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="INCREASE" <?= ($type ?? '') === 'INCREASE' ? 'selected' : '' ?>>INCREASE (+ Stock Found)</option>
                    <option value="DECREASE" <?= ($type ?? '') === 'DECREASE' ? 'selected' : '' ?>>DECREASE (- Discrepancy / Damage)</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= url('warehouse/adjustments') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Adjustments Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($adjustments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-check-circle fs-1 text-success d-block mb-3"></i>
                <h5 class="text-muted">No Adjustment Requests Found</h5>
                <p class="text-muted small">Submit an adjustment request whenever a physical audit variance is detected.</p>
                <?php if (hasPermission('stock', 'adjust')): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAdjustmentModal">
                        Create First Adjustment
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Adjustment #</th>
                            <th>Warehouse</th>
                            <th>Product / SKU</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reason & Notes</th>
                            <th>Requested By</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adjustments as $adj): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold font-monospace text-primary"><?= e($adj['adjustment_number']) ?></span>
                                    <br><small class="text-muted"><?= date('d M Y, h:i A', strtotime($adj['created_at'])) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= e($adj['warehouse_name']) ?></span></td>
                                <td>
                                    <span class="fw-bold font-monospace"><?= e($adj['sku']) ?></span>
                                    <br><small class="text-muted"><?= e($adj['product_name']) ?> (<?= e($adj['size_name']) ?>, <?= e($adj['color_name']) ?>)</small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $adj['adjustment_type'] === 'INCREASE' ? 'success' : 'danger' ?>">
                                        <?= e($adj['adjustment_type']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold fs-6 <?= $adj['adjustment_type'] === 'INCREASE' ? 'text-success' : 'text-danger' ?>">
                                    <?= ($adj['adjustment_type'] === 'INCREASE' ? '+' : '-') . number_format($adj['quantity']) ?> pcs
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= e($adj['reason']) ?></span>
                                    <?php
                                    $adjustmentQrCodes = json_decode((string)($adj['qr_codes'] ?? '[]'), true);
                                    if (is_array($adjustmentQrCodes) && !empty($adjustmentQrCodes)):
                                    ?>
                                        <br><small class="text-danger-emphasis fw-semibold"><?= $adj['status'] === 'APPROVED' ? ($adj['adjustment_type'] === 'INCREASE' ? 'Added QR' : 'Removed QR') : 'QR tags' ?>: <?= e(implode(', ', $adjustmentQrCodes)) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($adj['notes'])): ?>
                                        <br><small class="text-muted"><?= e($adj['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="fw-semibold text-dark d-block"><?= e($adj['requested_by_name'] ?? 'Staff') ?></small>
                                    <?php if (!empty($adj['approved_by_name'])): ?>
                                        <small class="text-muted">Approved by: <?= e($adj['approved_by_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $adj['status'] === 'APPROVED' ? 'success' : ($adj['status'] === 'PENDING' ? 'warning text-dark' : 'danger') ?>">
                                        <?= e($adj['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($adj['status'] === 'PENDING' && hasPermission('stock', 'adjust')): ?>
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="<?= url('warehouse/adjustments/approve/' . $adj['id']) ?>" class="d-inline">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="APPROVE">
                                                <input type="hidden" name="return_adjustment_type" value="<?= e($activeAdjustmentType) ?>">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this stock adjustment and update inventory balance?');">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="<?= url('warehouse/adjustments/approve/' . $adj['id']) ?>" class="d-inline">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="REJECT">
                                                <input type="hidden" name="return_adjustment_type" value="<?= e($activeAdjustmentType) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this adjustment request?');">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($adj['status'] === 'PENDING'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Pending Review</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (hasPermission('stock', 'adjust')): ?>
<!-- Modal: Stock Adjustment -->
<div class="modal fade" id="newAdjustmentModal" tabindex="-1" aria-labelledby="newAdjustmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="<?= url('warehouse/adjustments/apply') ?>" id="stockAdjustmentForm" data-lookup-url="<?= url('api/warehouse/adjustments/lookup') ?>" data-preview-url="<?= url('api/warehouse/adjustments/preview-increase') ?>" data-selected-product="<?= e($selectedVariantSku) ?>">
            <?= CSRF::input() ?>
            <input type="hidden" name="qr_codes" id="adjustmentQrCodes" value="[]">
            <input type="hidden" name="variant_id" id="adjustmentVariantId" value="">
            <input type="hidden" name="adjustment_type" id="adjustmentTypeValue" value="<?= e($activeAdjustmentType ?: 'INCREASE') ?>">
            <input type="hidden" name="return_adjustment_type" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newAdjustmentModalLabel"><i class="bi bi-sliders me-2 text-primary"></i>Stock Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php $adjustmentWarehouseDisabled = (function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1; ?>
                    <?php $adjustmentWarehouseValue = $scopedWarehouseId ?? ($warehouseId ?: ($warehouses[0]['id'] ?? 0)); ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Warehouse <span class="text-danger">*</span></label>
                        <select <?= $adjustmentWarehouseDisabled ? '' : 'name="warehouse_id"' ?> id="adjustmentWarehouseSelect" class="form-select" required <?= $adjustmentWarehouseDisabled ? 'disabled' : '' ?>>
                            <?php if (!$adjustmentWarehouseDisabled): ?>
                                <option value="">Select warehouse</option>
                            <?php endif; ?>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>" <?= (int)$wh['id'] === (int)$adjustmentWarehouseValue ? 'selected' : '' ?>><?= e($wh['name']) ?><?= !empty($wh['code']) ? ' (' . e($wh['code']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($adjustmentWarehouseDisabled): ?>
                            <input type="hidden" name="warehouse_id" value="<?= e($adjustmentWarehouseValue) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="adjustmentProductScanInput" class="form-label small fw-semibold">Scan product QR / enter SKU <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" id="adjustmentProductScanInput" class="form-control" placeholder="Scan a product label or enter its SKU..." autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" id="adjustmentProductScanButton"><i class="bi bi-upc-scan me-1"></i>Find Product</button>
                        </div>
                        <div id="adjustmentProductScanMessage" class="small mt-2" role="status"></div>
                    </div>
                    <div class="card bg-light border-0 mb-3 d-none" id="adjustmentProductDetails">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-bold" id="adjustmentProductName"></div>
                                    <div class="small text-muted"><span id="adjustmentProductSku"></span> · <span id="adjustmentProductVariant"></span></div>
                                </div>
                                <span class="badge" id="adjustmentProductSource"></span>
                            </div>
                            <div class="row g-2 small mt-1">
                                <div class="col-sm-6"><span class="text-muted">Available in warehouse:</span> <strong id="adjustmentAvailableStock">0 pcs</strong></div>
                                <div class="col-sm-6"><span class="text-muted">QR format:</span> <strong id="adjustmentQrFormat"></strong></div>
                                <div class="col-sm-6 d-none" id="adjustmentSupplierRow"><span class="text-muted">Supplier:</span> <strong id="adjustmentSupplier"></strong></div>
                                <div class="col-sm-4"><span class="text-muted">MRP:</span> <strong id="adjustmentMrp"></strong></div>
                                <div class="col-sm-4"><span class="text-muted">Selling price:</span> <strong id="adjustmentSellingPrice"></strong></div>
                                <div class="col-sm-4"><span class="text-muted">Cost:</span> <strong id="adjustmentCostPrice"></strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Adjustment Type <span class="text-danger">*</span></label>
                            <select id="adjustmentTypeSelect" class="form-select" required>
                                <option value="INCREASE" <?= $activeAdjustmentType === 'INCREASE' || $activeAdjustmentType === '' ? 'selected' : '' ?>>INCREASE (+ Found Stock)</option>
                                <option value="DECREASE" <?= $activeAdjustmentType === 'DECREASE' ? 'selected' : '' ?>>DECREASE (- Discrepancy / Damage)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Quantity (Pcs) <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="adjustmentQuantityInput" class="form-control" min="1" step="1" value="" placeholder="0" required>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 small" id="adjustmentImmediateNotice">
                        <i class="bi bi-info-circle me-1"></i>Saving this form updates the selected warehouse stock immediately.
                    </div>
                    <div class="mb-3 d-none" id="adjustmentIncreaseControls">
                        <div id="adjustmentGenerateQrControls">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="adjustmentPreviewButton"><i class="bi bi-qr-code me-1"></i>Generate QR Preview</button>
                            <div id="adjustmentPreviewMessage" class="small mt-2" role="status"></div>
                            <div class="d-none mt-3" id="adjustmentQrPreview">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold small">Live QR label preview</span>
                                    <span class="badge bg-success-subtle text-success-emphasis" id="adjustmentPreviewSummary"></span>
                                </div>
                                <div class="row g-2" id="adjustmentQrPreviewList"></div>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 small d-none mb-0" id="adjustmentCountOnlyNotice">
                            <i class="bi bi-info-circle me-1"></i>Series and complementary stock increases update the quantity only. No new QR labels are generated.
                        </div>
                    </div>
                    <div class="mb-3 d-none" id="adjustmentDecreaseControls">
                        <label class="form-label small fw-semibold">Scan existing product QR codes to remove <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" id="adjustmentDecreaseScanInput" class="form-control" placeholder="Scan an existing product QR code..." autocomplete="off">
                            <button type="button" class="btn btn-outline-danger" id="adjustmentDecreaseScanButton"><i class="bi bi-upc-scan me-1"></i>Scan QR</button>
                        </div>
                        <small class="form-text text-muted">Only available tags from the selected warehouse can be removed. Pack/series tags remove their full pack quantity.</small>
                        <div id="adjustmentDecreaseMessage" class="small mt-2" role="status"></div>
                        <div id="adjustmentDecreaseList" class="d-flex flex-wrap gap-1 mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Primary Reason <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select" required>
                            <option value="Physical Count Discrepancy">Physical Count Discrepancy (Stock Audit)</option>
                            <option value="Damaged / Spoiled in Warehouse">Damaged / Spoiled in Warehouse</option>
                            <option value="Lost / Missing Inventory">Lost / Missing Inventory</option>
                            <option value="Inventory Found / Corrected">Inventory Found / Corrected</option>
                            <option value="Sample / Showroom Pull">Sample / Showroom Pull</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Detailed Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Auditor name, audit batch reference..."></textarea>
                    </div>
                </div>

                <script src="<?= url('assets/js/qrcode.min.js') ?>"></script>
                <script src="<?= url('assets/js/warehouse-stock-adjustment.js') ?>"></script>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save & Update Stock</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php if (!empty($selectedVariantId) && hasPermission('stock', 'adjust')): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('newAdjustmentModal');
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
});
</script>
<?php endif; ?>
<?php endif; ?>
