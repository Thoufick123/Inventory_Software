<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-success me-2"></i>Edit WhatsApp Return / Exchange</h4>
            <div class="text-muted small">
                <?= htmlspecialchars($return['return_number']) ?> · Invoice
                <span class="font-monospace fw-semibold"><?= htmlspecialchars($return['invoice_number']) ?></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('warehouse/whatsapp-orders/returns/invoice/' . $return['id']) ?>" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-printer me-1"></i> Slip
            </a>
            <a href="<?= url('warehouse/whatsapp-orders/returns') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Register
            </a>
        </div>
    </div>

    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-shield-check me-2"></i>
        <?php if (!empty($hasPrintedLabels)): ?>
            Saving will void the existing printed labels and reverse their stock receipt if no later stock activity has affected those products. Print replacement labels after saving to return the products to inventory.
        <?php else: ?>
            Edits are blocked if later stock activity has affected returned products. Settlement changes are recorded for audit and do not move cash.
        <?php endif; ?>
    </div>

    <form method="POST" action="<?= url('warehouse/whatsapp-orders/returns/update/' . $return['id']) ?>" id="whatsappReturnEditForm">
        <?= CSRF::field() ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Return Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Action</label>
                        <select class="form-select" name="return_type" id="whatsappReturnType">
                            <option value="RETURN" <?= strtoupper((string)$return['return_type']) === 'RETURN' ? 'selected' : '' ?>>Return</option>
                            <option value="EXCHANGE" <?= strtoupper((string)$return['return_type']) === 'EXCHANGE' ? 'selected' : '' ?>>Exchange</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reason</label>
                        <input class="form-control" name="reason" maxlength="255" value="<?= htmlspecialchars($return['reason'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Settlement Method</label>
                        <select class="form-select" name="refund_mode">
                            <?php foreach (['CASH' => 'Cash', 'UPI' => 'UPI', 'CARD' => 'Card', 'ONLINE' => 'Online', 'CREDIT_NOTE' => 'Credit Note'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= strtoupper((string)($return['refund_mode'] ?? 'CASH')) === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Returned Billed Items</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice Item</th>
                            <th class="text-center">Billed</th>
                            <th style="width:120px">Return Qty</th>
                            <th style="width:150px">Condition</th>
                            <th style="min-width:220px">Piece QR Tags (optional)</th>
                            <th class="text-end">Unit Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saleItems as $item): ?>
                            <?php
                            $itemTags = trim((string)($item['edit_piece_tags'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($item['sku']) ?> · <?= htmlspecialchars($item['size_name']) ?> / <?= htmlspecialchars($item['color_name']) ?></small>
                                    <input type="hidden" name="items[<?= (int)$item['id'] ?>][sale_item_id]" value="<?= (int)$item['id'] ?>">
                                </td>
                                <td class="text-center"><?= (int)$item['quantity'] ?></td>
                                <td>
                                    <input class="form-control form-control-sm text-center" type="number" min="0" max="<?= (int)$item['max_return_quantity'] ?>" step="1" name="items[<?= (int)$item['id'] ?>][qty]" value="<?= (int)$item['edit_quantity'] ?>">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="items[<?= (int)$item['id'] ?>][condition]">
                                        <?php foreach (['GOOD' => 'Good', 'DAMAGED' => 'Damaged', 'DEFECTIVE' => 'Defective'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= strtoupper((string)$item['edit_condition']) === $value ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input class="form-control form-control-sm font-monospace" name="items[<?= (int)$item['id'] ?>][piece_tags]" value="<?= htmlspecialchars($itemTags) ?>" placeholder="Comma-separated billed piece tags"></td>
                                <td class="text-end">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2 small text-muted">Leave quantity at 0 for billed items not included in this return. The return price is taken from the original invoice.</div>
        </div>

        <div class="card border-0 shadow-sm mb-3" id="whatsappReplacementCard">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="fw-bold">Replacement Items</div>
                <button type="button" class="btn btn-sm btn-outline-success" id="addWhatsappReplacement">
                    <i class="bi bi-plus-lg me-1"></i>Add Replacement
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:270px">Product / Variant</th>
                            <th style="width:100px">Quantity</th>
                            <th style="width:140px">Unit Price</th>
                            <th style="min-width:200px">Piece QR / Product Code</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="whatsappReplacementItems">
                        <?php
                        $newExchangeItems = array_values(array_filter(
                            $exchangeItems,
                            static fn(array $item): bool => strtoupper((string)($item['item_type'] ?? '')) === 'NEW'
                        ));
                        foreach ($newExchangeItems as $index => $item):
                        ?>
                            <tr>
                                <td>
                                    <select class="form-select form-select-sm" name="replacement_items[<?= (int)$index ?>][variant_id]" required>
                                        <?php foreach ($catalogVariants as $variant): ?>
                                            <option value="<?= (int)$variant['id'] ?>" <?= (int)$variant['variant_id'] === (int)$variant['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($variant['product_name'] . ' / ' . $variant['sku'] . ' / ' . $variant['size_name'] . ' / ' . $variant['color_name'] . ' — ' . number_format((float)$variant['available_stock'], 0) . ' available') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input class="form-control form-control-sm text-center" type="number" min="1" step="1" name="replacement_items[<?= (int)$index ?>][quantity]" required value="<?= (int)$item['quantity'] ?>"></td>
                                <td><input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" name="replacement_items[<?= (int)$index ?>][unit_price]" required value="<?= htmlspecialchars((string)$item['unit_price']) ?>"></td>
                                <td><input class="form-control form-control-sm font-monospace" name="replacement_items[<?= (int)$index ?>][piece_tag]" value="<?= htmlspecialchars((string)($item['piece_tag'] ?? '')) ?>" required></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-whatsapp-replacement" title="Remove item"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2 small text-muted">Normal products require an available scanned piece QR and quantity 1. Series/complementary items require their product code and available stock.</div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= url('warehouse/whatsapp-orders/returns') ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-success px-4">
                <i class="bi bi-check2-circle me-1"></i>Save Return Changes
            </button>
        </div>
    </form>
</div>

<template id="whatsappReplacementTemplate">
    <tr>
        <td>
            <select class="form-select form-select-sm" required data-name="variant_id">
                <option value="">Select replacement</option>
                <?php foreach ($catalogVariants as $variant): ?>
                    <option value="<?= (int)$variant['id'] ?>" data-price="<?= htmlspecialchars((string)$variant['selling_price']) ?>">
                        <?= htmlspecialchars($variant['product_name'] . ' / ' . $variant['sku'] . ' / ' . $variant['size_name'] . ' / ' . $variant['color_name'] . ' — ' . number_format((float)$variant['available_stock'], 0) . ' available') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input class="form-control form-control-sm text-center" type="number" min="1" step="1" required value="1" data-name="quantity"></td>
        <td><input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" required value="0" data-name="unit_price"></td>
        <td><input class="form-control form-control-sm font-monospace" required data-name="piece_tag"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-whatsapp-replacement" title="Remove item"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<script>
(() => {
    const typeSelect = document.getElementById('whatsappReturnType');
    const replacementCard = document.getElementById('whatsappReplacementCard');
    const replacementBody = document.getElementById('whatsappReplacementItems');
    const template = document.getElementById('whatsappReplacementTemplate');
    let nextIndex = replacementBody ? replacementBody.querySelectorAll('tr').length : 0;
    if (!typeSelect || !replacementCard || !replacementBody || !template) return;

    const syncType = () => {
        const isExchange = typeSelect.value === 'EXCHANGE';
        replacementCard.classList.toggle('d-none', !isExchange);
        replacementCard.querySelectorAll('input, select').forEach((field) => {
            field.disabled = !isExchange;
        });
    };
    typeSelect.addEventListener('change', syncType);
    syncType();

    document.getElementById('addWhatsappReplacement').addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-name]').forEach((field) => {
            field.name = `replacement_items[${nextIndex}][${field.dataset.name}]`;
        });
        const select = row.querySelector('select');
        const priceInput = row.querySelector('[data-name="unit_price"]');
        select.addEventListener('change', () => {
            const option = select.selectedOptions[0];
            if (option && priceInput) priceInput.value = option.dataset.price || '0';
        });
        nextIndex++;
        replacementBody.appendChild(row);
    });

    replacementBody.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-whatsapp-replacement');
        if (button) button.closest('tr').remove();
    });

    document.getElementById('whatsappReturnEditForm').addEventListener('submit', (event) => {
        if (typeSelect.value === 'EXCHANGE' && !replacementBody.querySelector('tr')) {
            event.preventDefault();
            alert('Add at least one replacement item for an exchange.');
        }
    });
})();
</script>
