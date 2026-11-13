<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Damage Case</h2>
        <span class="text-muted">Update the scanned product and keep quarantined stock in sync.</span>
    </div>
    <a href="<?= url('warehouse/damage/view/' . (int)$damage['id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Case
    </a>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold"><?= e($damage['damage_number']) ?></h5>
        <small class="text-muted"><?= e($damage['warehouse_name']) ?> · <?= e($damage['status']) ?></small>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="<?= url('warehouse/damage/update/' . (int)$damage['id']) ?>">
            <?= CSRF::input() ?>
            <div class="row g-3">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold" for="damageVariant">Scanned product / variant</label>
                    <select class="form-select" id="damageVariant" name="variant_id" required>
                        <option value="">Select product</option>
                        <?php foreach ($variants as $variant): ?>
                            <option value="<?= (int)$variant['id'] ?>" <?= (int)$variant['id'] === (int)$damage['variant_id'] ? 'selected' : '' ?>>
                                <?= e($variant['product_name']) ?> · <?= e($variant['sku']) ?> · <?= e($variant['size_name'] ?? 'Free') ?> / <?= e($variant['color_name'] ?? 'Std') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label fw-semibold" for="damageQuantity">Damaged quantity</label>
                    <input class="form-control" type="number" id="damageQuantity" name="quantity" min="1" max="500" step="1" value="<?= (int)$damage['quantity'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="damageTagCode">Scanned QR / piece tags</label>
                    <input class="form-control font-monospace" id="damageTagCode" name="tag_code" value="<?= e($damage['tag_code'] ?? '') ?>" maxlength="100" placeholder="Comma-separated scanned tags">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="damageCost">Unit cost (₹)</label>
                    <input class="form-control" type="number" id="damageCost" name="cost_per_unit" min="0" step="0.01" value="<?= number_format((float)($damage['cost_per_unit'] ?? 0), 2, '.', '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="damageReason">Damage reason</label>
                    <input class="form-control" id="damageReason" name="damage_reason" value="<?= e($damage['damage_reason']) ?>" maxlength="255" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="damageNotes">Inspector notes</label>
                    <textarea class="form-control" id="damageNotes" name="defect_notes" rows="4"><?= e($damage['defect_notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= url('warehouse/damage') ?>" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Save Damage Case
                </button>
            </div>
        </form>
    </div>
</div>
