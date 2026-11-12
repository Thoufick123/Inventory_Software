<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <span class="badge bg-success-subtle text-success border px-3 py-1 rounded-pill"><?= e($channel['name'] ?? 'WhatsApp') ?></span>
        <h2 class="h4 fw-bold mt-2 mb-0">Edit Damage Record <?= e($damage['damage_number']) ?></h2>
        <div class="small text-muted mt-1">Workflow status and allocation links are managed separately.</div>
    </div>
    <a href="<?= url('warehouse/whatsapp-orders/damage') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<form method="POST" action="<?= url('warehouse/whatsapp-orders/damage/update/' . (int)$damage['id']) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Product variant</label>
                    <select class="form-select" name="variant_id" required>
                        <?php foreach ($variants as $variant): ?>
                            <option value="<?= (int)$variant['id'] ?>" <?= (int)$variant['id'] === (int)$damage['variant_id'] ? 'selected' : '' ?>>
                                <?= e($variant['product_name']) ?> — <?= e($variant['sku']) ?>
                                (<?= e($variant['size_name'] ?? 'Free') ?> / <?= e($variant['color_name'] ?? 'Std') ?>; available <?= number_format((float)$variant['available_qty'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Damage source</label>
                    <select class="form-select" name="source_type" required>
                        <?php foreach (['STORAGE_HANDLING' => 'Storage / handling', 'CUSTOMER_RETURN' => 'Customer return', 'EXPIRED_SPOILED' => 'Expired / spoiled', 'OTHER' => 'Other'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $damage['source_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Piece tag / QR</label>
                    <input class="form-control" name="tag_code" value="<?= e($damage['tag_code'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Defect type</label>
                    <select class="form-select" name="defect_type" required>
                        <?php foreach (['FABRIC_DEFECT', 'COLOR_FADE', 'STITCHING_ERROR', 'PACKAGING_DAMAGE', 'WATER_STAIN', 'TRANSIT_DAMAGE', 'EXPIRED', 'OTHER'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= $damage['defect_type'] === $type ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity</label>
                    <input class="form-control" type="number" min="1" step="1" name="quantity" value="<?= (int)$damage['quantity'] ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cost per unit</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="cost_per_unit" value="<?= e($damage['cost_per_unit']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total loss</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="total_loss" value="<?= e($damage['total_loss']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Photo evidence</label>
                    <input class="form-control" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
                    <?php if (!empty($damage['photo_url'])): ?>
                        <a href="<?= e($damage['photo_url']) ?>" target="_blank" rel="noopener" class="small">View current photo</a>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Damage reason</label>
                    <input class="form-control" name="damage_reason" maxlength="255" value="<?= e($damage['damage_reason']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Defect notes</label>
                    <textarea class="form-control" name="defect_notes" rows="4"><?= e($damage['defect_notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="<?= url('warehouse/whatsapp-orders/damage') ?>" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save changes</button>
        </div>
    </div>
</form>
