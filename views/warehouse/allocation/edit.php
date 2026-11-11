<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 mb-0 text-gray-800">
            <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Allocation #<?= htmlspecialchars($allocation['allocation_number']) ?>
        </h2>
        <p class="text-muted small mb-0">Modify allocation requisition destination, notes, or items.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('warehouse/allocation/view/' . $allocation['id']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-eye me-1"></i> View Details
        </a>
        <a href="<?= url('warehouse/allocation') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Allocations
        </a>
    </div>
</div>

<form method="POST" action="<?= url('warehouse/allocation/update/' . $allocation['id']) ?>" id="editAllocationForm" class="card border-0 shadow-sm rounded-3 p-4">
    <?= CSRF::field() ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label fw-bold mb-1">Source Warehouse</label>
            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($allocation['warehouse_name'] ?? 'Warehouse') ?>" readonly>
            <input type="hidden" name="warehouse_id" value="<?= (int)$allocation['warehouse_id'] ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold mb-1">Destination Channel / Shop <span class="text-danger">*</span></label>
            <select name="destination_type" class="form-select" required>
                <optgroup label="Retail Shops">
                    <?php foreach ($shops as $sh): ?>
                        <?php 
                        $val = 'SHOP_' . $sh['id'];
                        $isSel = ($allocation['shop_id'] == $sh['id'] || ($allocation['destination_type'] === 'SHOP' && $allocation['destination_id'] == $sh['id']));
                        ?>
                        <option value="<?= $val ?>" <?= $isSel ? 'selected' : '' ?>>
                            Shop: <?= htmlspecialchars($sh['name']) ?> (<?= htmlspecialchars($sh['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Other Warehouses">
                    <?php foreach ($warehouses as $wh): if ($wh['id'] != $allocation['warehouse_id']): ?>
                        <?php 
                        $val = 'WAREHOUSE_' . $wh['id'];
                        $isSel = ($allocation['destination_type'] === 'WAREHOUSE' && $allocation['destination_id'] == $wh['id']);
                        ?>
                        <option value="<?= $val ?>" <?= $isSel ? 'selected' : '' ?>>
                            Warehouse: <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['code']) ?>)
                        </option>
                    <?php endif; endforeach; ?>
                </optgroup>
                <optgroup label="Sales Channels">
                    <?php foreach ($salesChannels as $sc): ?>
                        <?php 
                        $val = 'CHANNEL_' . $sc['id'];
                        $isSel = ($allocation['channel_id'] == $sc['id'] || ($allocation['destination_type'] === 'CHANNEL' && $allocation['destination_id'] == $sc['id']));
                        ?>
                        <option value="<?= $val ?>" <?= $isSel ? 'selected' : '' ?>>
                            Channel: <?= htmlspecialchars($sc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold mb-1">Status</label>
            <?php
            $badgeClass = match($allocation['status']) {
                'REQUESTED' => 'bg-warning text-dark',
                'APPROVED' => 'bg-info text-dark',
                'PICKING', 'PACKING' => 'bg-primary text-white',
                'DISPATCHED' => 'bg-success text-white',
                'COMPLETED' => 'bg-dark text-white',
                default => 'bg-secondary text-white'
            };
            ?>
            <div class="pt-1">
                <span class="badge <?= $badgeClass ?> px-3 py-2 fs-6"><?= htmlspecialchars($allocation['status']) ?></span>
            </div>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold mb-1">Notes / Instructions</label>
            <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($allocation['notes'] ?? '') ?>" placeholder="Optional notes...">
        </div>
    </div>

    <!-- Items Table -->
    <div class="card border border-light-subtle rounded-3 mb-4">
        <div class="card-header bg-light py-2.5">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Allocated Items</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 40px;">#</th>
                        <th>Product / SKU</th>
                        <th>Scanned Piece QRs</th>
                        <th class="text-end pe-3" style="width: 140px;">Requested Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allocation['items'] as $idx => $it): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($it['product_name'] ?? ('Variant #' . $it['variant_id'])) ?></div>
                                <div class="font-monospace text-secondary small"><?= htmlspecialchars($it['sku'] ?? '') ?></div>
                                <input type="hidden" name="item_ids[]" value="<?= (int)$it['id'] ?>">
                            </td>
                            <td>
                                <?php if (!empty($it['selected_qr_codes'])): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach (explode(',', $it['selected_qr_codes']) as $tag): if(trim($tag)): ?>
                                            <span class="badge bg-dark font-monospace text-warning px-1.5 py-0.5" style="font-size: 11px;">
                                                <i class="bi bi-qr-code-scan me-1"></i><?= htmlspecialchars(trim($tag)) ?>
                                            </span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">No specific piece tags scanned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if (in_array($allocation['status'], ['REQUESTED', 'APPROVED'])): ?>
                                    <div class="input-group input-group-sm justify-content-end">
                                        <input type="number" name="requested_qty[<?= (int)$it['id'] ?>]" class="form-control text-end fw-bold font-monospace" min="1" step="1" value="<?= (int)$it['requested_qty'] ?>" style="max-width: 90px;">
                                        <span class="input-group-text">pcs</span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-dark font-monospace fs-6 px-2.5 py-1">
                                        <?= (int)$it['requested_qty'] ?> pcs
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="<?= url('warehouse/allocation') ?>" class="btn btn-secondary px-3">
            <i class="bi bi-x-lg me-1"></i> Cancel
        </a>
        <button type="submit" class="btn btn-primary px-4 fw-bold">
            <i class="bi bi-check-lg me-1"></i> Save Changes
        </button>
    </div>
</form>
