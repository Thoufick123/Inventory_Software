<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="<?= url('shop/returns') ?>" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left me-1"></i> Back to Returns
            </a>
            <h3 class="fw-bold mb-1">Edit Return / Exchange</h3>
            <p class="text-muted mb-0"><?= e($return['return_number']) ?> · Invoice <?= e($return['invoice_number']) ?></p>
        </div>
    </div>

    <div class="alert alert-warning">
        <i class="bi bi-shield-exclamation me-1"></i>
        Editing is blocked after a returned-product QR label is printed or if an exchange replacement has already moved from available stock.
        Any changes to the recorded settlement are saved to the audit log.
    </div>

    <form method="post" action="<?= url('shop/returns/update/' . $return['id']) ?>" id="editShopReturnForm">
        <?= CSRF::field() ?>
        <input type="hidden" name="return_id" value="<?= (int)$return['id'] ?>">
        <input type="hidden" name="sale_id" value="<?= (int)$return['sale_id'] ?>">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Return details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Action</label>
                        <select name="return_type" id="editReturnType" class="form-select">
                            <option value="RETURN" <?= $return['return_type'] !== 'EXCHANGE' ? 'selected' : '' ?>>Direct Return</option>
                            <option value="EXCHANGE_DIFFERENT" <?= $return['return_type'] === 'EXCHANGE' ? 'selected' : '' ?>>Exchange</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Refund / Settlement mode</label>
                        <?php $refundMode = strtoupper((string)($return['refund_mode'] ?? 'CASH')); ?>
                        <select name="refund_mode" class="form-select">
                            <?php foreach (['CASH', 'UPI', 'CARD', 'CASH_UPI', 'SPLIT', 'ONLINE', 'CREDIT_NOTE'] as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= $refundMode === $mode ? 'selected' : '' ?>><?= e(str_replace('_', ' / ', $mode)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" value="<?= e($return['reason'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= e($return['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Returned items</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice item</th>
                            <th class="text-end">Rate</th>
                            <th class="text-center">Return quantity</th>
                            <th>Condition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saleItems as $item): ?>
                            <?php $saleItemId = (int)$item['id']; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                                    <small class="text-muted"><?= e($item['sku']) ?> · <?= e($item['size_name']) ?> · <?= e($item['color_name']) ?></small>
                                    <small class="d-block text-muted">Sold <?= (int)$item['quantity'] ?> · Can return up to <?= (int)$item['max_return_quantity'] ?></small>
                                    <?php foreach (array_filter(array_map('trim', explode(',', (string)$item['edit_piece_tags']))) as $pieceTag): ?>
                                        <input type="hidden" name="items[<?= $saleItemId ?>][piece_tags][]" value="<?= e($pieceTag) ?>">
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-end">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm mx-auto text-center"
                                           name="items[<?= $saleItemId ?>][qty]" min="0"
                                           max="<?= (int)$item['max_return_quantity'] ?>"
                                           value="<?= (int)$item['edit_quantity'] ?>" style="max-width:100px;">
                                </td>
                                <td>
                                    <?php $condition = strtoupper((string)$item['edit_condition']); ?>
                                    <select name="items[<?= $saleItemId ?>][condition]" class="form-select form-select-sm">
                                        <?php foreach (['GOOD', 'DAMAGED', 'DEFECTIVE'] as $option): ?>
                                            <option value="<?= e($option) ?>" <?= $condition === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4" id="replacementCard">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Exchange replacement items</strong>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addReplacementRow">
                    <i class="bi bi-plus-lg me-1"></i>Add replacement
                </button>
            </div>
            <div class="card-body">
                <p class="small text-muted">Scan or enter each replacement piece tag. The server verifies that every replacement is still available at this shop.</p>
                <div id="replacementRows">
                    <?php foreach ($exchangeItems as $index => $item): ?>
                        <div class="row g-2 align-items-end replacement-row mb-2">
                            <div class="col-md-6">
                                <label class="form-label small">Product variant</label>
                                <select name="replacement_items[<?= (int)$index ?>][variant_id]" class="form-select form-select-sm replacement-variant">
                                    <option value="">Select a variant</option>
                                    <?php foreach ($catalogVariants as $variant): ?>
                                        <option value="<?= (int)$variant['id'] ?>" <?= (int)$variant['id'] === (int)$item['variant_id'] ? 'selected' : '' ?>>
                                            <?= e($variant['product_name'] . ' · ' . $variant['sku'] . ' · ' . $variant['size_name'] . ' · ' . $variant['color_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Quantity</label>
                                <input type="number" name="replacement_items[<?= (int)$index ?>][quantity]" class="form-control form-control-sm replacement-quantity" min="1" value="<?= (int)$item['quantity'] ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Piece tag / scan</label>
                                <input type="text" name="replacement_items[<?= (int)$index ?>][piece_tag]" class="form-control form-control-sm replacement-tag" value="<?= e($item['piece_tag'] ?? '') ?>">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-replacement" aria-label="Remove replacement">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($exchangeItems)): ?>
                        <div class="row g-2 align-items-end replacement-row mb-2">
                            <div class="col-md-6">
                                <label class="form-label small">Product variant</label>
                                <select name="replacement_items[0][variant_id]" class="form-select form-select-sm replacement-variant">
                                    <option value="">Select a variant</option>
                                    <?php foreach ($catalogVariants as $variant): ?>
                                        <option value="<?= (int)$variant['id'] ?>"><?= e($variant['product_name'] . ' · ' . $variant['sku'] . ' · ' . $variant['size_name'] . ' · ' . $variant['color_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Quantity</label>
                                <input type="number" name="replacement_items[0][quantity]" class="form-control form-control-sm replacement-quantity" min="1" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Piece tag / scan</label>
                                <input type="text" name="replacement_items[0][piece_tag]" class="form-control form-control-sm replacement-tag">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-replacement" aria-label="Remove replacement"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <template id="replacementRowTemplate">
                    <div class="row g-2 align-items-end replacement-row mb-2">
                        <div class="col-md-6">
                            <label class="form-label small">Product variant</label>
                            <select class="form-select form-select-sm replacement-variant">
                                <option value="">Select a variant</option>
                                <?php foreach ($catalogVariants as $variant): ?>
                                    <option value="<?= (int)$variant['id'] ?>"><?= e($variant['product_name'] . ' · ' . $variant['sku'] . ' · ' . $variant['size_name'] . ' · ' . $variant['color_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><label class="form-label small">Quantity</label><input type="number" class="form-control form-control-sm replacement-quantity" min="1" value="1"></div>
                        <div class="col-md-3"><label class="form-label small">Piece tag / scan</label><input type="text" class="form-control form-control-sm replacement-tag"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-replacement" aria-label="Remove replacement"><i class="bi bi-trash"></i></button></div>
                    </div>
                </template>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?= url('shop/returns') ?>" class="btn btn-light border">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save transaction</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('editShopReturnForm');
    const action = document.getElementById('editReturnType');
    const replacementCard = document.getElementById('replacementCard');
    const rows = document.getElementById('replacementRows');
    const template = document.getElementById('replacementRowTemplate');

    function syncReplacementMode() {
        const enabled = action.value !== 'RETURN';
        replacementCard.classList.toggle('opacity-50', !enabled);
        rows.querySelectorAll('input, select, button').forEach(function (control) {
            control.disabled = !enabled;
        });
    }

    function renumberReplacementRows() {
        rows.querySelectorAll('.replacement-row').forEach(function (row, index) {
            row.querySelector('.replacement-variant').name = `replacement_items[${index}][variant_id]`;
            row.querySelector('.replacement-quantity').name = `replacement_items[${index}][quantity]`;
            row.querySelector('.replacement-tag').name = `replacement_items[${index}][piece_tag]`;
        });
    }

    document.getElementById('addReplacementRow').addEventListener('click', function () {
        rows.appendChild(template.content.cloneNode(true));
        renumberReplacementRows();
        syncReplacementMode();
    });

    rows.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-replacement');
        if (button && rows.querySelectorAll('.replacement-row').length > 1) {
            button.closest('.replacement-row').remove();
            renumberReplacementRows();
        }
    });

    action.addEventListener('change', syncReplacementMode);
    form.addEventListener('submit', function (event) {
        if (action.value !== 'RETURN') {
            const replacements = Array.from(rows.querySelectorAll('.replacement-row'));
            const hasValidReplacement = replacements.some(function (row) {
                return row.querySelector('.replacement-variant').value
                    && Number(row.querySelector('.replacement-quantity').value) > 0;
            });
            if (!hasValidReplacement) {
                event.preventDefault();
                alert('Select at least one valid replacement product for an exchange.');
                return;
            }
            replacements.forEach(function (row) {
                if (!row.querySelector('.replacement-variant').value) row.remove();
            });
            renumberReplacementRows();
        }
    });

    renumberReplacementRows();
    syncReplacementMode();
});
</script>
