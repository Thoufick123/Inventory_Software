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
        <?php if (hasPermission('stock_adjustments', 'create')): ?>
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
                <?php if (hasPermission('stock_adjustments', 'create')): ?>
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
                                    <?php
                                    $productType = WarehouseService::getAdjustmentProductType($adj);
                                    $productTypeBadgeClass = [
                                        'NORMAL' => 'bg-primary',
                                        'SERIES' => 'bg-info text-dark',
                                        'COMPLEMENTARY' => 'bg-warning text-dark',
                                    ][$productType] ?? 'bg-secondary';
                                    ?>
                                    <br><span class="badge <?= e($productTypeBadgeClass) ?>"><?= e(ucfirst(strtolower($productType))) ?> product</span>
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
                                    $adjustmentQrCodes = is_array($adjustmentQrCodes) ? $adjustmentQrCodes : [];
                                    if (!empty($adjustmentQrCodes)):
                                    ?>
                                        <br><small class="text-danger-emphasis fw-semibold"><?= count($adjustmentQrCodes) ?> QR tag(s)</small>
                                    <?php else: ?>
                                        <br><small class="text-muted">Quantity-only</small>
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
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                        <?php if (hasPermission('stock_adjustments', 'view')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary view-adjustment-btn" data-adjustment-id="<?= (int)$adj['id'] ?>">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                        <?php endif; ?>
                                        <?php if (hasPermission('stock_adjustments', 'edit')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary edit-adjustment-btn" data-adjustment-id="<?= (int)$adj['id'] ?>">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($adj['status'] === 'PENDING' && hasPermission('stock_adjustments', 'edit')): ?>
                                            <form method="POST" action="<?= url('warehouse/adjustments/approve/' . $adj['id']) ?>" class="d-inline">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="APPROVE">
                                                <input type="hidden" name="return_adjustment_type" value="<?= e($activeAdjustmentType) ?>">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this stock adjustment and update inventory balance?');">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($adj['status'] === 'PENDING' && hasPermission('stock_adjustments', 'delete')): ?>
                                            <form method="POST" action="<?= url('warehouse/adjustments/approve/' . $adj['id']) ?>" class="d-inline">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="REJECT">
                                                <input type="hidden" name="return_adjustment_type" value="<?= e($activeAdjustmentType) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this adjustment request?');">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($adj['status'] === 'PENDING'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis">Pending Review</span>
                                        <?php endif; ?>
                                        <?php if (hasPermission('stock_adjustments', 'delete')): ?>
                                            <form method="POST" action="<?= url('warehouse/adjustments/delete/' . $adj['id']) ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this stock adjustment record?');">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="return_adjustment_type" value="<?= e($activeAdjustmentType) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Adjustment Record">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="adjustmentDetailsModal" tabindex="-1" aria-labelledby="adjustmentDetailsModalLabel" aria-hidden="true" data-details-url="<?= url('api/warehouse/adjustments') ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="adjustmentDetailsModalLabel">Stock Adjustment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="adjustmentDetailsLoading" class="text-center py-4 d-none"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading adjustment details...</div>
                <div id="adjustmentDetailsError" class="alert alert-danger d-none" role="alert"></div>
                <div id="adjustmentDetailsContent" class="d-none">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <div class="fw-bold font-monospace text-primary fs-5" id="detailAdjustmentNumber"></div>
                            <div class="small text-muted" id="detailCreatedAt"></div>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge" id="detailAdjustmentType"></span>
                            <span class="badge" id="detailAdjustmentStatus"></span>
                        </div>
                    </div>
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><div class="small text-muted">Warehouse</div><div class="fw-semibold" id="detailWarehouse"></div></div>
                                <div class="col-md-6">
                                    <div class="small text-muted">Product</div>
                                    <div class="fw-semibold" id="detailProduct"></div>
                                    <div class="small text-muted" id="detailProductVariant"></div>
                                    <div class="small text-muted mt-2">Product Type</div>
                                    <span class="badge" id="detailProductType"></span>
                                </div>
                                <div class="col-md-6"><div class="small text-muted">Quantity</div><div class="fw-semibold" id="detailQuantity"></div></div>
                                <div class="col-md-6"><div class="small text-muted">Requested by</div><div class="fw-semibold" id="detailRequestedBy"></div><div class="small text-muted" id="detailApprovedBy"></div></div>
                                <div class="col-12"><div class="small text-muted">Reason</div><div class="fw-semibold" id="detailReason"></div></div>
                                <div class="col-12" id="detailNotesRow"><div class="small text-muted">Notes</div><div id="detailNotes" class="text-break"></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0" id="detailQrHeading">QR codes for this adjustment</h6>
                        <span class="badge bg-secondary" id="detailQrCount"></span>
                    </div>
                    <div id="detailQrEmpty" class="alert alert-light border small mb-0 d-none"></div>
                    <div class="row g-2" id="detailQrList"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (hasPermission('stock_adjustments', 'edit')): ?>
<div class="modal fade" id="editAdjustmentModal" tabindex="-1" aria-labelledby="editAdjustmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="#" id="editAdjustmentForm" data-metadata-url="<?= url('warehouse/adjustments') ?>">
            <?= CSRF::input() ?>
            <input type="hidden" name="return_adjustment_type" value="<?= e($activeAdjustmentType) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editAdjustmentModalLabel">Edit Adjustment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="editAdjustmentError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="alert alert-info py-2 small">Only the reason and notes can be edited. Stock quantity, adjustment type, and QR codes are not changed.</div>
                    <div class="mb-3">
                        <label for="editAdjustmentReason" class="form-label small fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea id="editAdjustmentReason" name="reason" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-0">
                        <label for="editAdjustmentNotes" class="form-label small fw-semibold">Notes</label>
                        <textarea id="editAdjustmentNotes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editAdjustmentSaveButton" disabled><i class="bi bi-check2-circle me-1"></i>Save Details</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<script src="<?= url('assets/js/qrcode.min.js') ?>"></script>
<script src="<?= asset('js/warehouse-adjustment-details.js') ?>?v=<?= file_exists(BASE_PATH . '/public/assets/js/warehouse-adjustment-details.js') ? filemtime(BASE_PATH . '/public/assets/js/warehouse-adjustment-details.js') : '1' ?>"></script>

<?php if (hasPermission('stock_adjustments', 'create')): ?>
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
                    <div class="card bg-light border-start border-4 border-primary shadow-sm mb-3 d-none" id="adjustmentProductDetails">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="small text-primary text-uppercase fw-semibold mb-1">Scanned product</div>
                                    <div class="fw-bold" id="adjustmentProductName"></div>
                                    <div class="small text-muted"><span id="adjustmentProductSku"></span> · <span id="adjustmentProductVariant"></span></div>
                                </div>
                                <span class="badge" id="adjustmentProductSource"></span>
                            </div>
                            <div class="small mt-2 d-none" id="adjustmentScannedQrRow">
                                <span class="text-muted">Scanned QR / product code(s):</span>
                                <div class="d-flex flex-wrap gap-1 mt-1" id="adjustmentScannedQrCodes"></div>
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
                            <label class="form-label small fw-semibold" id="adjustmentQuantityLabel">Quantity (Pcs) <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="adjustmentQuantityInput" class="form-control" min="1" step="1" value="" placeholder="0" required>
                        </div>
                    </div>
                    <div id="adjustmentQuantityMessage" class="small mb-3" role="status"></div>
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
                            <i class="bi bi-info-circle me-1"></i>Enter the additional piece count. Stock will increase without generating a new series/complementary QR label.
                        </div>
                    </div>
                    <div class="alert alert-warning py-2 small d-none" id="adjustmentDecreaseCountNotice">
                        <i class="bi bi-info-circle me-1"></i>Scan each QR code to remove its represented quantity. The quantity is calculated automatically; duplicate, unavailable, or already-used QR codes cannot be scanned again.
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

                <script src="<?= asset('js/warehouse-stock-adjustment.js') ?>?v=<?= file_exists(BASE_PATH . '/public/assets/js/warehouse-stock-adjustment.js') ? filemtime(BASE_PATH . '/public/assets/js/warehouse-stock-adjustment.js') : '1' ?>"></script>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save & Update Stock</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php if (!empty($selectedVariantId) && hasPermission('stock_adjustments', 'create')): ?>
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
