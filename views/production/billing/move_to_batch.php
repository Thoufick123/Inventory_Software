<?php
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$totalPieces = array_sum(array_map(static fn(array $item): int => (int)($item['quantity'] ?? 0), $billItems ?? []));
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Move Bill to Batch</h4>
        <div class="text-muted small"><?= $escape($bill['bill_number']) ?> · <?= number_format($totalPieces) ?> billed piece(s)</div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= url('production/billing') ?>"><i class="bi bi-arrow-left me-1"></i>Back to Billing</a>
</div>

<div class="alert alert-info py-2" role="status">
    The bill's exact QR items will be packed into linked production batches. Batches require approval before they can be outward-dispatched.
</div>

<form method="POST" action="<?= url('production/billing/move-to-batch/' . (int)$bill['id']) ?>" id="billDispatchForm">
    <?= CSRF::input() ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Billed Items</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th>Product</th><th>QR tags</th><th class="text-end">Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($billItems as $item): ?>
                            <tr>
                                <td><div class="fw-semibold"><?= $escape($item['product_name'] ?? '') ?></div><small class="text-muted"><?= $escape(trim(($item['sku'] ?? '') . ' ' . ($item['size_name'] ?? '') . ' ' . ($item['color_name'] ?? ''))) ?></small></td>
                                <td class="small font-monospace"><?= $escape(implode(', ', $item['tags'] ?? [])) ?></td>
                                <td class="text-end"><?= (int)($item['quantity'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$billItems): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">This bill has no item rows.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Dispatch Batch Setup</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="destinationWarehouse">Destination warehouse</label>
                        <select class="form-select" name="destination_warehouse_id" id="destinationWarehouse" required>
                            <option value="">Select warehouse</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?= (int)$warehouse['id'] ?>"><?= $escape($warehouse['name']) ?><?= !empty($warehouse['code']) ? ' (' . $escape($warehouse['code']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="boxCount">Boxes per program batch</label>
                        <input class="form-control" type="number" name="box_count" id="boxCount" min="1" max="10000" step="1" value="1" required>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <span class="text-muted">Pieces to pack</span><strong><?= number_format($totalPieces) ?></strong>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a class="btn btn-outline-secondary" href="<?= url('production/billing') ?>">Cancel</a>
                        <button class="btn btn-success" type="submit" <?= (!$billItems || !$warehouses) ? 'disabled' : '' ?>><i class="bi bi-box-seam me-1"></i>Create Linked Batch</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
