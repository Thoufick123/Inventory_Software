<?php
$canDelete = hasPermission('shop_inward', 'delete');
$damageTagsByVariant = [];
foreach ($damages ?? [] as $damage) {
    if (($damage['status'] ?? '') === 'CANCELLED') {
        continue;
    }
    $variantId = (int)($damage['variant_id'] ?? 0);
    if ($variantId > 0) {
        $damageTagsByVariant[$variantId] = array_merge(
            $damageTagsByVariant[$variantId] ?? [],
            $damage['piece_tags'] ?? ShopDamage::resolvePieceTags($damage)
        );
    }
}
?>
<div class="container-fluid px-0 px-md-3 py-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('shop/dashboard') ?>">Shop</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('shop/inward') ?>?shop_id=<?= (int)$shop['id'] ?>">Inward / GRN</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('shop/inward/view/' . $allocation['id']) ?>?shop_id=<?= (int)$shop['id'] ?>"><?= e($allocation['allocation_number']) ?></a></li>
                    <li class="breadcrumb-item active">Edit Inward</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Shop Inward <span class="fs-6 font-monospace text-muted">#<?= e($allocation['allocation_number']) ?></span></h3>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('shop/inward/view/' . $allocation['id']) ?>?shop_id=<?= (int)$shop['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Inward</a>
            <?php if ($canDelete): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteInwardModal"><i class="bi bi-trash3 me-1"></i>Delete Inward Receipt</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-warning border-0 shadow-sm small">
        <i class="bi bi-shield-exclamation me-2"></i><strong>Stock reconciliation:</strong>
        Saving changes adjusts available and damaged shop stock. A reason is required for the audit log.
    </div>

    <form method="POST" action="<?= url('shop/inward/update/' . $allocation['id']) ?>" id="editShopInwardForm">
        <?= CSRF::field() ?>
        <input type="hidden" name="shop_id" value="<?= (int)$shop['id'] ?>">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between flex-wrap gap-2">
                <h5 class="mb-0 fw-bold"><i class="bi bi-sliders2 text-primary me-2"></i>Variant Breakdown &amp; Inward Reconcile</h5>
                <span class="text-muted small">Good and damaged quantities are piece counts; missing is calculated automatically.</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Product &amp; SKU</th><th class="text-center">Dispatched</th>
                            <th class="text-center">Prev. Good / Damaged</th><th class="text-center">Received Good</th>
                            <th class="text-center">Damaged</th><th class="text-center">Missing</th><th>Piece QR Tags</th><th>Item Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <?php
                        $variantId = (int)$item['variant_id'];
                        $expected = (int)($item['dispatched_qty'] > 0 ? $item['dispatched_qty'] : $item['requested_qty']);
                        $receivedTotal = (int)($item['received_qty'] ?? 0);
                        $damaged = (int)($item['damaged_qty'] ?? 0);
                        if (in_array($allocation['status'], ['RECEIVED', 'COMPLETED', 'DELIVERED'], true) && $receivedTotal === 0 && $damaged === 0) {
                            $receivedTotal = $expected;
                        }
                        $good = max(0, $receivedTotal - $damaged);
                        $rawReceivedTags = trim((string)($item['received_qr_codes'] ?? ''));
                        $decodedReceivedTags = json_decode($rawReceivedTags, true);
                        $allTags = is_array($item['received_qr_codes'] ?? null)
                            ? $item['received_qr_codes']
                            : (is_array($decodedReceivedTags) ? $decodedReceivedTags : ($rawReceivedTags !== '' ? (preg_split('/[,|]/', $rawReceivedTags) ?: []) : []));
                        $allTags = array_map(static function ($tag) {
                            return is_array($tag) ? ($tag['tag_code'] ?? ($tag['qr_code'] ?? '')) : $tag;
                        }, $allTags);
                        $damageTags = array_values(array_unique($damageTagsByVariant[$variantId] ?? []));
                        $goodTags = array_values(array_diff($allTags, $damageTags));
                        ?>
                        <tr class="shop-inward-edit-row" data-expected="<?= $expected ?>">
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= e($item['product_name']) ?></strong><br><span class="font-monospace text-muted small"><?= e($item['sku']) ?></span><br><small><?= e($item['size_name'] ?? 'Free') ?> / <?= e($item['color_name'] ?? 'Std') ?></small></td>
                            <td class="text-center fw-bold"><?= $expected ?></td>
                            <td class="text-center"><span class="text-success"><?= $good ?></span> / <span class="text-danger"><?= $damaged ?></span></td>
                            <td><input type="number" class="form-control form-control-sm text-center edit-good" name="items[<?= $variantId ?>][received_qty]" value="<?= $good ?>" min="0" max="<?= $expected ?>" required></td>
                            <td><input type="number" class="form-control form-control-sm text-center edit-damaged" name="items[<?= $variantId ?>][damaged_qty]" value="<?= $damaged ?>" min="0" max="<?= $expected ?>" required></td>
                            <td class="text-center"><span class="edit-missing"><?= max(0, $expected - $good - $damaged) ?></span></td>
                            <td style="min-width:180px">
                                <label class="small text-success">Good</label>
                                <textarea class="form-control form-control-sm mb-1" rows="2" name="items[<?= $variantId ?>][good_tags]"><?= e(implode(', ', $goodTags)) ?></textarea>
                                <label class="small text-danger">Damaged</label>
                                <textarea class="form-control form-control-sm" rows="2" name="items[<?= $variantId ?>][damage_tags]"><?= e(implode(', ', $damageTags)) ?></textarea>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" name="items[<?= $variantId ?>][notes]" value="<?= e($item['notes'] ?? '') ?>" placeholder="Notes"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr><td colspan="2">Totals</td><td class="text-center"><?= number_format($totalExpected) ?></td><td></td><td id="totalGood" class="text-center"><?= number_format($totalReceived) ?></td><td id="totalDamaged" class="text-center"><?= number_format($totalDamaged) ?></td><td id="totalMissing" class="text-center"><?= number_format($totalMissing) ?></td><td colspan="2"></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Inward Notes</label>
                <textarea class="form-control" rows="3" name="notes"><?= e($allocation['notes'] ?? '') ?></textarea>
            </div>
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Reason for Modification <span class="text-danger">*</span></label>
                <textarea class="form-control" rows="3" name="edit_reason" id="editReason" minlength="4" required placeholder="Explain why this inward receipt is being modified"></textarea>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?= url('shop/inward/view/' . $allocation['id']) ?>?shop_id=<?= (int)$shop['id'] ?>" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Save &amp; Reconcile Stock</button>
        </div>
    </form>
</div>

<?php if ($canDelete): ?>
<div class="modal fade" id="deleteInwardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= url('shop/inward/delete/' . $allocation['id']) ?>" onsubmit="return confirmShopInwardDelete()">
                <?= CSRF::field() ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete / Reverse Inward</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Allocation <strong><?= e($allocation['allocation_number']) ?></strong> will be reset to dispatched. Credited stock will be reversed only if the balances remain available.</p>
                    <label class="form-label">Mandatory reason</label>
                    <textarea class="form-control" name="reason" id="deleteInwardReason" rows="3" required minlength="4"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reverse Inward</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function confirmShopInwardDelete() {
    const reason = document.getElementById('deleteInwardReason').value.trim();
    return reason.length >= 4 && confirm('Reverse this inward receipt and deduct its credited stock?');
}
</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('.shop-inward-edit-row'));
    function updateTotals() {
        let goodTotal = 0, damagedTotal = 0, missingTotal = 0;
        rows.forEach(function (row) {
            const expected = Number(row.dataset.expected) || 0;
            const goodInput = row.querySelector('.edit-good');
            const damagedInput = row.querySelector('.edit-damaged');
            let good = Math.max(0, Number(goodInput.value) || 0);
            let damaged = Math.max(0, Number(damagedInput.value) || 0);
            if (good + damaged > expected) {
                if (document.activeElement === damagedInput) damaged = expected - good;
                else good = expected - damaged;
                goodInput.value = good;
                damagedInput.value = damaged;
            }
            const missing = Math.max(0, expected - good - damaged);
            row.querySelector('.edit-missing').textContent = missing;
            goodTotal += good; damagedTotal += damaged; missingTotal += missing;
        });
        document.getElementById('totalGood').textContent = goodTotal;
        document.getElementById('totalDamaged').textContent = damagedTotal;
        document.getElementById('totalMissing').textContent = missingTotal;
    }
    document.querySelectorAll('.edit-good, .edit-damaged').forEach(input => input.addEventListener('input', updateTotals));
});
</script>
