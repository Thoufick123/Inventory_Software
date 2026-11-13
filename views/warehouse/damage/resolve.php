<?php
$damageTags = array_values(array_filter(array_map('trim', explode(',', (string)($damage['tag_code'] ?? '')))));
$pendingReplacement = strtoupper((string)($damage['resolution_action'] ?? '')) === 'REPLACEMENT'
    && empty($damage['replacement_inwarded_at'])
    && !empty($damage['replacement_qr_code']);
$replacementVariant = null;
if (!empty($damage['replacement_variant_id'])) {
    foreach ($variants as $variant) {
        if ((int)$variant['id'] === (int)$damage['replacement_variant_id']) {
            $replacementVariant = $variant;
            break;
        }
    }
}
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1 fw-bold text-dark"><i class="bi bi-shield-check me-2 text-success"></i>Resolve Damage Case</h2>
        <span class="text-muted">Case <?= e($damage['damage_number']) ?> · <?= e($damage['warehouse_name']) ?></span>
    </div>
    <a href="<?= url('warehouse/damage/view/' . (int)$damage['id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> View Case
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-qr-code-scan me-2 text-danger"></i>Scanned Damage Product</h5>
            </div>
            <div class="card-body">
                <div class="fw-bold fs-5 text-dark"><?= e($damage['product_name']) ?></div>
                <div class="font-monospace text-primary fw-semibold mt-1"><?= e($damage['sku']) ?></div>
                <div class="text-muted small mt-1"><?= e($damage['size_name'] ?? 'Free') ?> / <?= e($damage['color_name'] ?? 'Std') ?></div>
                <hr>
                <dl class="row mb-0">
                    <dt class="col-6 text-muted">Quantity</dt>
                    <dd class="col-6 text-end fw-bold"><?= number_format((int)$damage['quantity']) ?> pcs</dd>
                    <dt class="col-6 text-muted">Damage reason</dt>
                    <dd class="col-6 text-end"><?= e($damage['damage_reason']) ?></dd>
                    <dt class="col-6 text-muted">Status</dt>
                    <dd class="col-6 text-end"><span class="badge bg-danger-subtle text-danger"><?= e($damage['status']) ?></span></dd>
                </dl>
                <?php if ($damageTags): ?>
                    <div class="mt-3">
                        <div class="small fw-semibold text-muted mb-2">Scanned QR tags</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($damageTags as $tag): ?>
                                <span class="badge bg-dark text-warning font-monospace"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if ($pendingReplacement): ?>
            <div class="card border-0 shadow-sm rounded-3 border-top border-4 border-success">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-success"></i>Replacement QR Ready</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">The replacement QR is ready. Open it in Warehouse Labels to print the label and complete the inward there.</p>
                    <div class="row align-items-center g-3">
                        <div class="col-md-8">
                            <div class="fw-bold fs-5"><?= e($replacementVariant['product_name'] ?? 'Replacement product') ?></div>
                            <div class="font-monospace text-primary"><?= e($replacementVariant['sku'] ?? '') ?></div>
                            <div class="small text-muted mt-2">Replacement quantity: <?= number_format((int)$damage['quantity']) ?> pcs</div>
                            <div class="small text-muted">Resolution reason: <?= e(str_replace('_', ' ', (string)$damage['resolution_reason'])) ?></div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a class="btn btn-success fw-semibold" href="<?= url('warehouse/labels?damage_id=' . (int)$damage['id']) ?>">
                                <i class="bi bi-qr-code-scan me-1"></i> Open in Warehouse Labels
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($damage['resolution_action'])): ?>
            <div class="alert alert-info border-0 shadow-sm rounded-3">
                <div class="fw-bold mb-1">This case already has a resolution in progress or completed.</div>
                <div><?= e(str_replace('_', ' ', (string)$damage['resolution_action'])) ?> · <?= e(str_replace('_', ' ', (string)$damage['resolution_reason'])) ?></div>
                <?php if (!empty($damage['replacement_inwarded_at'])): ?>
                    <small class="d-block mt-2">Replacement inwarded <?= e($damage['replacement_inwarded_at']) ?> using QR <?= e($damage['replacement_qr_code']) ?>.</small>
                <?php endif; ?>
            </div>
        <?php elseif (!in_array(strtoupper((string)$damage['status']), ['LOGGED', 'INSPECTED'], true)): ?>
            <div class="alert alert-secondary border-0 shadow-sm rounded-3">This damage case is no longer awaiting a resolution.</div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i>Choose Resolution</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('warehouse/damage/resolve/' . (int)$damage['id']) ?>">
                        <?= CSRF::input() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="resolutionAction">Resolution action</label>
                            <select class="form-select" name="resolution_action" id="resolutionAction" required onchange="updateDamageResolutionFields()">
                                <option value="">Select an action</option>
                                <option value="REPLACEMENT">Supplier replacement (generate QR, then inward in Labels)</option>
                                <option value="REFUND">Refund</option>
                                <option value="SUPPLIER_CREDIT">Supplier credit</option>
                                <option value="REWORK">Repair / factory rework</option>
                                <option value="WRITE_OFF">Write off</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="resolutionReason">Resolution reason</label>
                            <select class="form-select" name="resolution_reason" id="resolutionReason" required>
                                <option value="">Select a reason</option>
                                <option value="QUALITY_ISSUE">Quality issue</option>
                                <option value="TRANSIT_DAMAGE">Transit damage</option>
                                <option value="MANUFACTURING_DEFECT">Manufacturing defect</option>
                                <option value="WRONG_ITEM">Wrong product / variant received</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="mb-3 p-3 bg-success bg-opacity-10 border border-success-subtle rounded-3" id="replacementProductGroup" style="display:none;">
                            <label class="form-label fw-semibold" for="replacementVariant">Replacement product</label>
                            <select class="form-select" name="replacement_variant_id" id="replacementVariant" onchange="updateDamageReplacementDetails()">
                                <option value="">Select a replacement product</option>
                                <?php foreach ($variants as $variant): ?>
                                    <?php
                                        $categoryHierarchy = implode(' › ', array_filter([
                                            $variant['category_name'] ?? '',
                                            $variant['subcategory_name'] ?? '',
                                            $variant['sub_subcategory_name'] ?? '',
                                        ]));
                                    ?>
                                    <option value="<?= (int)$variant['id'] ?>"
                                        data-product-name="<?= e($variant['product_name']) ?>"
                                        data-category="<?= e($categoryHierarchy) ?>"
                                        data-category-type="<?= e(strtoupper((string)$variant['category_type'])) ?>"
                                        data-hsn="<?= e($variant['variant_hsn_code'] ?: ($variant['product_hsn_code'] ?? '')) ?>"
                                        data-gst="<?= e($variant['gst_rate'] ?? $variant['gst_percentage'] ?? '5.00') ?>"
                                        data-weight="<?= e($variant['variant_weight'] ?? $variant['product_weight'] ?? '0.000') ?>"
                                        data-variant="<?= e(($variant['size_name'] ?? 'Free') . ' / ' . ($variant['color_name'] ?? 'Std')) ?>"
                                        data-mrp="<?= e($variant['mrp'] ?? '0.00') ?>"
                                        data-selling="<?= e($variant['selling_price'] ?? '0.00') ?>"
                                        data-cost="<?= e($variant['purchase_price'] ?? '0.00') ?>">
                                        <?= e($variant['product_name']) ?> · <?= e($variant['sku']) ?> · <?= e($variant['size_name'] ?? 'Free') ?> / <?= e($variant['color_name'] ?? 'Std') ?> — <?= e($variant['supplier_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="replacementProductDetails" class="mt-3" style="display:none;">
                                <div class="small fw-bold text-muted mb-2">Replacement product details</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle text-nowrap mb-2">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Category hierarchy</th><th>Category type</th><th>Product name</th>
                                                <th>HSN</th><th>GST (%)</th><th>Weight (kg)</th><th>Variant / size</th>
                                                <th>Base MRP (₹)</th><th>Selling price (₹)</th><th>Supplier cost (₹)</th>
                                                <th>Qty</th><th>Line retail value (₹)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><input class="form-control form-control-sm" id="replacementCategory" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementCategoryType" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementProductName" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementHsn" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementGst" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementWeight" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementVariantName" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementMrp" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementSelling" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementCost" readonly></td>
                                                <td><input class="form-control form-control-sm" value="<?= (int)$damage['quantity'] ?>" readonly></td>
                                                <td><input class="form-control form-control-sm" id="replacementRetailValue" readonly></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">Details are loaded from the selected catalog variant. The QR is reserved for <?= number_format((int)$damage['quantity']) ?> pcs; stock is updated only when you inward it from Warehouse Labels.</small>
                            </div>
                        </div>
                        <div class="mb-3" id="resolutionAmountGroup" style="display:none;">
                            <label class="form-label fw-semibold" for="resolutionAmount">Refund / credit amount (₹)</label>
                            <input class="form-control" type="number" min="0" step="0.01" name="resolution_amount" id="resolutionAmount" value="<?= number_format((float)($damage['total_loss'] ?? 0), 2, '.', '') ?>">
                        </div>
                        <div class="mb-3 p-3 bg-warning bg-opacity-10 border border-warning-subtle rounded-3" id="reworkUnitGroup" style="display:none;">
                            <label class="form-label fw-semibold" for="productionUnit">Production unit for rework</label>
                            <select class="form-select" name="production_unit_id" id="productionUnit">
                                <option value="">Select a production unit</option>
                                <?php foreach ($productionUnits as $unit): ?>
                                    <option value="<?= (int)$unit['id'] ?>">[<?= e($unit['code']) ?>] <?= e($unit['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="resolutionNotes">Resolution notes</label>
                            <textarea class="form-control" name="resolution_notes" id="resolutionNotes" rows="3" placeholder="Supplier reference, refund details, or inspection notes"></textarea>
                        </div>
                        <button type="submit" id="damageResolveSubmit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Save Resolution
                        </button>
                    </form>
                </div>
            </div>
            <script>
                function updateDamageResolutionFields() {
                    const action = document.getElementById('resolutionAction').value;
                    const replacementGroup = document.getElementById('replacementProductGroup');
                    const replacementSelect = document.getElementById('replacementVariant');
                    const amountGroup = document.getElementById('resolutionAmountGroup');
                    const amount = document.getElementById('resolutionAmount');
                    const reworkGroup = document.getElementById('reworkUnitGroup');
                    const unitSelect = document.getElementById('productionUnit');
                    replacementGroup.style.display = action === 'REPLACEMENT' ? '' : 'none';
                    replacementSelect.required = action === 'REPLACEMENT';
                    document.getElementById('damageResolveSubmit').innerHTML = action === 'REPLACEMENT'
                        ? '<i class="bi bi-qr-code me-1"></i> Generate Replacement QR'
                        : '<i class="bi bi-check2-circle me-1"></i> Save Resolution';
                    amountGroup.style.display = ['REFUND', 'SUPPLIER_CREDIT'].includes(action) ? '' : 'none';
                    amount.required = ['REFUND', 'SUPPLIER_CREDIT'].includes(action);
                    reworkGroup.style.display = action === 'REWORK' ? '' : 'none';
                    unitSelect.required = action === 'REWORK';
                    if (action !== 'REPLACEMENT') {
                        document.getElementById('replacementProductDetails').style.display = 'none';
                    } else if (replacementSelect.value) {
                        updateDamageReplacementDetails();
                    }
                }
                function updateDamageReplacementDetails() {
                    const select = document.getElementById('replacementVariant');
                    const option = select.options[select.selectedIndex];
                    const details = document.getElementById('replacementProductDetails');
                    if (!option || !option.value) {
                        details.style.display = 'none';
                        return;
                    }
                    const values = {
                        replacementCategory: option.dataset.category,
                        replacementCategoryType: option.dataset.categoryType,
                        replacementProductName: option.dataset.productName,
                        replacementHsn: option.dataset.hsn,
                        replacementGst: option.dataset.gst,
                        replacementWeight: option.dataset.weight,
                        replacementVariantName: option.dataset.variant,
                        replacementMrp: option.dataset.mrp,
                        replacementSelling: option.dataset.selling,
                        replacementCost: option.dataset.cost
                    };
                    Object.keys(values).forEach(function(id) {
                        document.getElementById(id).value = values[id] || '';
                    });
                    const quantity = <?= (int)$damage['quantity'] ?>;
                    const mrp = Number(option.dataset.mrp || 0);
                    document.getElementById('replacementRetailValue').value = (mrp * quantity).toFixed(2);
                    details.style.display = '';
                }
                updateDamageResolutionFields();
            </script>
        <?php endif; ?>
    </div>
</div>
