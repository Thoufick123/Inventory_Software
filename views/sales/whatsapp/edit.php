<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-success me-2"></i>Edit WhatsApp Order</h4>
            <div class="text-muted small">Invoice <span class="font-monospace fw-semibold"><?= htmlspecialchars($sale['invoice_number']) ?></span></div>
        </div>
        <a href="<?= url('sales/whatsapp/view/' . $sale['id']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Order
        </a>
    </div>

    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-shield-check me-2"></i>
        Editing reconciles the original channel stock deduction only when no later stock movement exists. Any recorded paid amount remains unchanged; settlement differences are audit-only.
    </div>

    <form method="POST" action="<?= url('sales/whatsapp/update/' . $sale['id']) ?>" id="whatsappOrderEditForm">
        <?= CSRF::field() ?>
        <input type="hidden" name="edit_sale_id" value="<?= (int)$sale['id'] ?>">
        <input type="hidden" name="customer_id" value="<?= (int)($sale['customer_id'] ?? 0) ?>">
        <input type="hidden" name="biller_id" value="<?= (int)($sale['biller_id'] ?? 0) ?>">
        <input type="hidden" name="billing_staff_id" value="<?= (int)($sale['billing_staff_id'] ?? 0) ?>">
        <input type="hidden" name="biller_name" value="<?= htmlspecialchars($sale['biller_name'] ?? '') ?>">
        <input type="hidden" name="discount_amount" value="0">
        <input type="hidden" name="payment_status" value="<?= htmlspecialchars($sale['payment_status'] ?? 'PENDING') ?>">

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Customer and Order Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Customer Name</label>
                        <input class="form-control" name="customer_name" required maxlength="150" value="<?= htmlspecialchars($sale['customer_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input class="form-control" name="customer_phone" required inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="<?= htmlspecialchars($sale['customer_phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Address</label>
                        <input class="form-control" name="customer_address" required value="<?= htmlspecialchars($sale['customer_db_address'] ?? $sale['customer_address'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">State</label>
                        <input class="form-control" name="customer_state" value="<?= htmlspecialchars($sale['customer_state'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pincode</label>
                        <input class="form-control" name="customer_pincode" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="<?= htmlspecialchars($sale['customer_pincode'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Order Take Staff Name</label>
                        <input class="form-control" name="employee_name" value="<?= htmlspecialchars($sale['employee_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Order Take Staff ID</label>
                        <input class="form-control" name="employee_code" value="<?= htmlspecialchars($sale['employee_code'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">GST Type</label>
                        <select class="form-select" name="gst_type">
                            <?php foreach (['CGST_SGST' => 'CGST + SGST', 'IGST' => 'IGST', 'EXEMPT' => 'Exempt'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= strtoupper((string)($sale['gst_type'] ?? 'CGST_SGST')) === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_mode">
                            <?php foreach (['CASH' => 'Cash', 'UPI' => 'UPI / QR', 'CARD' => 'Card / POS', 'CASH_UPI' => 'Cash + UPI'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= strtoupper((string)($sale['payment_mode'] ?? 'UPI')) === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">The paid amount is not changed by this edit.</small>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-4 p-2 ps-5 bg-light border rounded-3">
                            <input type="hidden" name="is_online_sale" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="isOnlineSale" name="is_online_sale" value="1" <?= !empty($sale['is_online_sale']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="isOnlineSale">Show this sale in the Online section</label>
                            <div class="form-text mt-0">Classification only; payment mode and paid amount remain unchanged.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Shipping Cost</label>
                        <input class="form-control text-end" type="number" step="0.01" min="0" name="shipping_cost" value="<?= htmlspecialchars((string)($sale['shipping_cost'] ?? 0)) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Order Notes</label>
                        <textarea class="form-control" name="notes" rows="2"><?= htmlspecialchars($sale['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="fw-bold">Billed Items</div>
                <button type="button" class="btn btn-sm btn-outline-success" id="addWhatsappEditItem">
                    <i class="bi bi-plus-lg me-1"></i>Add Item
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:220px">Product / Variant</th>
                            <th style="width:95px">Quantity</th>
                            <th style="width:120px">Unit Price</th>
                            <th style="width:110px">MRP</th>
                            <th style="width:90px">GST %</th>
                            <th style="min-width:180px">Piece QR Tags (comma separated)</th>
                            <th style="width:130px">HSN</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="whatsappEditItems">
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td>
                                    <select class="form-select form-select-sm" name="items[<?= (int)$index ?>][variant_id]" required>
                                        <?php foreach ($variants as $variant): ?>
                                            <?php
                                            $variantLabel = $variant['product_name'] . ' / ' . $variant['sku'] . ' / ' . $variant['size_name'] . ($variant['color_name'] ? ' / ' . $variant['color_name'] : '');
                                            $stock = (float)($variant['available_stock'] ?? 0) + (float)($stockCredits[(int)$variant['id']] ?? 0);
                                            ?>
                                            <option value="<?= (int)$variant['id'] ?>" <?= (int)$item['variant_id'] === (int)$variant['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($variantLabel . ' — ' . number_format($stock, 0) . ' available') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input class="form-control form-control-sm" type="number" min="1" step="1" name="items[<?= (int)$index ?>][quantity]" required value="<?= (int)$item['quantity'] ?>"></td>
                                <td><input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" name="items[<?= (int)$index ?>][unit_price]" required value="<?= htmlspecialchars((string)$item['unit_price']) ?>"></td>
                                <td><input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" name="items[<?= (int)$index ?>][mrp]" required value="<?= htmlspecialchars((string)($item['mrp'] ?? $item['variant_mrp'] ?? $item['unit_price'])) ?>"></td>
                                <td><input class="form-control form-control-sm text-end" type="number" min="0" max="100" step="0.01" name="items[<?= (int)$index ?>][gst_percentage]" required value="<?= htmlspecialchars((string)($item['gst_percentage'] ?? 0)) ?>"></td>
                                <td><input class="form-control form-control-sm font-monospace" name="items[<?= (int)$index ?>][scanned_qr_codes]" value="<?= htmlspecialchars((string)($item['scanned_qr_codes'] ?? '')) ?>"></td>
                                <td><input class="form-control form-control-sm font-monospace" maxlength="20" name="items[<?= (int)$index ?>][hsn_code]" value="<?= htmlspecialchars((string)($item['hsn_code'] ?? $item['resolved_hsn_code'] ?? '')) ?>"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-whatsapp-edit-item" title="Remove item"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted">Normal products require one billed piece QR tag per unit. Stock and tags are revalidated on save.</span>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check2-circle me-1"></i>Save Order Changes
                </button>
            </div>
        </div>
    </form>
</div>

<template id="whatsappEditItemTemplate">
    <tr>
        <td>
            <select class="form-select form-select-sm" required data-name="variant_id">
                <option value="">Select product</option>
                <?php foreach ($variants as $variant): ?>
                    <?php
                    $variantLabel = $variant['product_name'] . ' / ' . $variant['sku'] . ' / ' . $variant['size_name'] . ($variant['color_name'] ? ' / ' . $variant['color_name'] : '');
                    $stock = (float)($variant['available_stock'] ?? 0) + (float)($stockCredits[(int)$variant['id']] ?? 0);
                    ?>
                    <option value="<?= (int)$variant['id'] ?>"><?= htmlspecialchars($variantLabel . ' — ' . number_format($stock, 0) . ' available') ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input class="form-control form-control-sm" type="number" min="1" step="1" required value="1" data-name="quantity"></td>
        <td><input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" required value="0" data-name="unit_price"></td>
        <td><input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" required value="0" data-name="mrp"></td>
        <td><input class="form-control form-control-sm text-end" type="number" min="0" max="100" step="0.01" required value="0" data-name="gst_percentage"></td>
        <td><input class="form-control form-control-sm font-monospace" data-name="scanned_qr_codes"></td>
        <td><input class="form-control form-control-sm font-monospace" maxlength="20" data-name="hsn_code"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-whatsapp-edit-item" title="Remove item"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<script>
(() => {
    const body = document.getElementById('whatsappEditItems');
    const template = document.getElementById('whatsappEditItemTemplate');
    let nextIndex = body ? body.querySelectorAll('tr').length : 0;
    if (!body || !template) return;

    document.getElementById('addWhatsappEditItem').addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-name]').forEach((field) => {
            field.name = `items[${nextIndex}][${field.dataset.name}]`;
        });
        nextIndex++;
        body.appendChild(row);
    });

    body.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-whatsapp-edit-item');
        if (button) button.closest('tr').remove();
    });

    document.getElementById('whatsappOrderEditForm').addEventListener('submit', (event) => {
        if (!body.querySelector('tr')) {
            event.preventDefault();
            alert('Add at least one billed item before saving.');
        }
    });
})();
</script>
