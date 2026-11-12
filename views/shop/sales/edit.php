<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="<?= url('shop/sales') ?>" class="btn btn-sm btn-light border mb-2">
                <i class="bi bi-arrow-left me-1"></i> Back to invoices
            </a>
            <h4 class="fw-bold mb-0">Edit Invoice <span class="font-monospace text-primary"><?= e($sale['invoice_number']) ?></span></h4>
            <small class="text-muted"><?= e($shop['name'] ?? 'Shop') ?> · Editing updates the complete invoice, stock, and payment history.</small>
        </div>
    </div>

    <form method="POST" action="<?= url('shop/sales/update/' . (int)$sale['id']) ?>" id="editInvoiceForm">
        <?= CSRF::field() ?>
        <div class="row g-3">
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="fw-bold mb-0">Customer details</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Customer name <span class="text-danger">*</span></label>
                            <input class="form-control" name="customer_name" value="<?= e($sale['customer_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input class="form-control" name="customer_phone" value="<?= e($sale['customer_phone'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="customer_address" rows="2"><?= e($sale['customer_address'] ?? '') ?></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input class="form-control" type="email" name="customer_email" value="<?= e($sale['customer_email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input class="form-control" name="customer_city" value="<?= e($sale['customer_city'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">GSTIN</label>
                            <input class="form-control text-uppercase" name="customer_gst" value="<?= e($sale['customer_gst'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">GST treatment</label>
                            <select class="form-select" name="gst_type">
                                <option value="CGST_SGST" <?= ($sale['gst_type'] ?? 'CGST_SGST') === 'CGST_SGST' ? 'selected' : '' ?>>CGST + SGST</option>
                                <option value="IGST" <?= ($sale['gst_type'] ?? '') === 'IGST' ? 'selected' : '' ?>>IGST</option>
                                <option value="EXEMPT" <?= ($sale['gst_type'] ?? '') === 'EXEMPT' ? 'selected' : '' ?>>Exempt</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Billing staff</label>
                            <input class="form-control" name="employee_name" value="<?= e($sale['employee_name'] ?? $sale['staff_name'] ?? '') ?>">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"><?= e($notes ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Invoice items</h5>
                            <small class="text-muted">Change quantities, prices, GST, remove lines, or add in-stock products.</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-8 position-relative">
                                <label class="form-label">Scan a good-stock QR / SKU, or search for a product</label>
                                <input id="variantSearch" class="form-control" autocomplete="off" placeholder="Scan and press Enter, or type at least 2 characters">
                                <div id="variantSearchResults" class="list-group position-absolute top-100 start-0 end-0 shadow z-3 d-none" style="max-height: 280px; overflow-y: auto;"></div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="alert alert-info py-2 px-3 mb-0 w-100 small">
                                    <i class="bi bi-info-circle me-1"></i>Only good stock can be scanned. Series and complementary products need quantity greater than zero.
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th style="width: 85px;">Qty</th>
                                        <th style="width: 110px;">MRP</th>
                                        <th style="width: 120px;">Unit price</th>
                                        <th style="width: 85px;">GST %</th>
                                        <th style="width: 110px;">HSN</th>
                                        <th style="width: 150px;">Piece tags</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="invoiceItems">
                                    <?php foreach ($items as $index => $item):
                                        $quantity = max(1, (int)($item['quantity'] ?? 1));
                                        $unitBilled = $quantity > 0 ? (float)($item['total_amount'] ?? 0) / $quantity : 0;
                                        $scannedTags = (string)($item['scanned_qr_codes'] ?? '');
                                        $decodedScannedTags = json_decode($scannedTags, true);
                                        if (is_array($decodedScannedTags)) {
                                            $scannedTags = implode(',', array_map(static function ($tag) {
                                                return is_array($tag)
                                                    ? (string)($tag['tag_code'] ?? $tag['qr_code'] ?? '')
                                                    : (string)$tag;
                                            }, $decodedScannedTags));
                                        }
                                    ?>
                                        <tr class="invoice-item-row" data-variant-id="<?= (int)$item['variant_id'] ?>">
                                            <td>
                                                <input type="hidden" name="items[<?= (int)$index ?>][variant_id]" value="<?= (int)$item['variant_id'] ?>">
                                                <div class="fw-semibold"><?= e($item['product_name'] ?? 'Product') ?></div>
                                                <small class="text-muted"><?= e($item['sku'] ?? '') ?><?= !empty($item['size_name']) ? ' · ' . e($item['size_name']) : '' ?><?= !empty($item['color_name']) ? ' · ' . e($item['color_name']) : '' ?></small>
                                            </td>
                                            <td><input class="form-control form-control-sm item-quantity" type="number" min="1" step="1" required name="items[<?= (int)$index ?>][quantity]" value="<?= $quantity ?>"></td>
                                            <td><input class="form-control form-control-sm item-mrp" type="number" min="0" step="0.01" required name="items[<?= (int)$index ?>][mrp]" value="<?= number_format((float)($item['mrp'] ?? 0), 2, '.', '') ?>"></td>
                                            <td><input class="form-control form-control-sm item-price" type="number" min="0" step="0.01" required name="items[<?= (int)$index ?>][billed_price]" value="<?= number_format($unitBilled, 2, '.', '') ?>"></td>
                                            <td><input class="form-control form-control-sm item-gst" type="number" min="0" max="100" step="0.01" required name="items[<?= (int)$index ?>][gst_percentage]" value="<?= number_format((float)($item['gst_percentage'] ?? 0), 2, '.', '') ?>"></td>
                                            <td><input class="form-control form-control-sm" name="items[<?= (int)$index ?>][hsn_code]" maxlength="20" value="<?= e($item['hsn_code'] ?? $item['resolved_hsn_code'] ?? '') ?>"></td>
                                            <td><input class="form-control form-control-sm" name="items[<?= (int)$index ?>][scanned_tags]" value="<?= e($scannedTags) ?>" placeholder="Scanned QR tags"></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-invoice-item" title="Remove item"><i class="bi bi-x-lg"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end fw-bold">Estimated invoice total: <span id="estimatedTotal">₹0.00</span></div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Payment mode</label>
                                <select class="form-select" id="paymentMode" name="payment_mode" required>
                                    <?php foreach (['CASH' => 'Cash', 'UPI' => 'UPI', 'CARD' => 'Card', 'CASH_UPI' => 'Cash + UPI'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= ($sale['payment_mode'] ?? 'CASH') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 split-payment-field">
                                <label class="form-label">Cash amount</label>
                                <input class="form-control split-payment-input" type="number" min="0" step="0.01" name="cash_amount" value="<?= number_format((float)($splitCash ?? 0), 2, '.', '') ?>">
                            </div>
                            <div class="col-md-4 split-payment-field">
                                <label class="form-label">UPI amount</label>
                                <input class="form-control split-payment-input" type="number" min="0" step="0.01" name="upi_amount" value="<?= number_format((float)($splitUpi ?? 0), 2, '.', '') ?>">
                            </div>
                            <div class="col-md-4 split-payment-field">
                                <label class="form-label">UPI reference</label>
                                <input class="form-control" name="upi_reference" value="<?= e($upiReference ?? '') ?>">
                            </div>
                            <div class="col-md-4" id="paymentReferenceField">
                                <label class="form-label">Payment reference</label>
                                <input class="form-control" name="payment_ref" value="<?= e($paymentReference ?? '') ?>">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a class="btn btn-light border" href="<?= url('shop/sales') ?>">Cancel</a>
                            <button class="btn btn-primary px-4" type="submit">
                                <i class="bi bi-save me-1"></i> Save invoice changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(() => {
    const itemBody = document.getElementById('invoiceItems');
    const searchInput = document.getElementById('variantSearch');
    const searchResults = document.getElementById('variantSearchResults');
    const paymentMode = document.getElementById('paymentMode');
    const estimatedTotal = document.getElementById('estimatedTotal');
    let nextItemIndex = <?= count($items) ?>;
    let searchTimer;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[char]);

    function updateEstimate() {
        let total = 0;
        itemBody.querySelectorAll('.invoice-item-row').forEach(row => {
            const quantity = Math.max(0, Number(row.querySelector('.item-quantity').value) || 0);
            const price = Math.max(0, Number(row.querySelector('.item-price').value) || 0);
            total += quantity * price;
        });
        const roundedTotal = Math.ceil(total);
        estimatedTotal.textContent = '₹' + roundedTotal.toFixed(2);
        return roundedTotal;
    }

    function updateSplitFromEditedAmount(editedInput) {
        if (paymentMode.value !== 'CASH_UPI') return;
        const total = updateEstimate();
        const cashInput = document.querySelector('[name="cash_amount"]');
        const upiInput = document.querySelector('[name="upi_amount"]');
        if (editedInput === cashInput) {
            const cash = Math.min(total, Math.max(0, Number(cashInput.value) || 0));
            cashInput.value = cash.toFixed(2);
            upiInput.value = (total - cash).toFixed(2);
        } else {
            const upi = Math.min(total, Math.max(0, Number(upiInput.value) || 0));
            upiInput.value = upi.toFixed(2);
            cashInput.value = (total - upi).toFixed(2);
        }
    }

    function updateSplitFields() {
        const visible = paymentMode.value === 'CASH_UPI';
        document.querySelectorAll('.split-payment-field').forEach(field => {
            field.classList.toggle('d-none', !visible);
        });
        document.querySelectorAll('.split-payment-input').forEach(input => {
            input.required = visible;
        });
        document.getElementById('paymentReferenceField').classList.toggle('d-none', visible);
        if (visible) {
            const total = updateEstimate();
            const cashInput = document.querySelector('[name="cash_amount"]');
            const upiInput = document.querySelector('[name="upi_amount"]');
            const upi = Math.min(total, Math.max(0, Number(upiInput.value) || 0));
            upiInput.value = upi.toFixed(2);
            cashInput.value = (total - upi).toFixed(2);
        }
    }

    function addProduct(product, scannedTag = '') {
        const variantId = Number(product.variant_id || product.id);
        const categoryType = String(product.category_type || 'NORMAL').toUpperCase();
        const isComplementary = Boolean(Number(product.is_complementary)) || categoryType === 'COMPLEMENTARY';
        const isSeries = !isComplementary && (
            Boolean(Number(product.is_series))
            || categoryType === 'SERIES'
            || product.qr_type === 'SERIES_BATCH'
            || Number(product.pack_quantity) > 1
        );
        const isAggregate = isComplementary || isSeries;
        const pieceTag = isAggregate ? '' : String(scannedTag || product.scanned_piece_tag || '').trim();
        const existing = itemBody.querySelector(`.invoice-item-row[data-variant-id="${variantId}"]`);
        if (existing) {
            let tags = [];
            let tagInput = null;
            if (pieceTag) {
                tagInput = existing.querySelector('input[name$="[scanned_tags]"]');
                tags = (tagInput.value || '').split(',').map(tag => tag.trim()).filter(Boolean);
                if (tags.some(tag => tag.toUpperCase() === pieceTag.toUpperCase())) {
                    throw new Error(`Piece QR ${pieceTag} is already in this invoice.`);
                }
                tags.push(pieceTag);
            }
            const quantity = existing.querySelector('.item-quantity');
            quantity.value = String((Number(quantity.value) || 0) + 1);
            if (tagInput) {
                tagInput.value = tags.join(',');
            }
            updateEstimate();
            return;
        }

        const index = nextItemIndex++;
        const name = escapeHtml(product.product_name);
        const sku = escapeHtml(product.sku);
        const variantLabel = [product.size_name, product.color_name].filter(Boolean).map(escapeHtml).join(' · ');
        const mrp = isComplementary ? 0 : (Number(product.mrp) || 0);
        const price = isComplementary ? 0 : (Number(product.selling_price) || mrp);
        const gst = isComplementary ? 0 : (Number(product.gst_percentage) || 0);
        const hsn = escapeHtml(product.resolved_hsn_code || '');
        const row = document.createElement('tr');
        row.className = 'invoice-item-row';
        row.dataset.variantId = String(variantId);
        row.innerHTML = `
            <td>
                <input type="hidden" name="items[${index}][variant_id]" value="${variantId}">
                <div class="fw-semibold">${name}</div>
                <small class="text-muted">${sku}${variantLabel ? ' · ' + variantLabel : ''} · ${Number(product.available_stock) || 0} in stock</small>
            </td>
            <td><input class="form-control form-control-sm item-quantity" type="number" min="1" step="1" required name="items[${index}][quantity]" value="1"></td>
            <td><input class="form-control form-control-sm item-mrp" type="number" min="0" step="0.01" required name="items[${index}][mrp]" value="${mrp.toFixed(2)}"></td>
            <td><input class="form-control form-control-sm item-price" type="number" min="0" step="0.01" required name="items[${index}][billed_price]" value="${price.toFixed(2)}"></td>
            <td><input class="form-control form-control-sm item-gst" type="number" min="0" max="100" step="0.01" required name="items[${index}][gst_percentage]" value="${gst.toFixed(2)}"></td>
            <td><input class="form-control form-control-sm" name="items[${index}][hsn_code]" maxlength="20" value="${hsn}"></td>
            <td><input class="form-control form-control-sm" name="items[${index}][scanned_tags]" value="${escapeHtml(pieceTag)}" placeholder="Scanned QR tags"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-invoice-item" title="Remove item"><i class="bi bi-x-lg"></i></button></td>
        `;
        itemBody.appendChild(row);
        updateEstimate();
    }

    async function scanProduct(code) {
        searchResults.classList.remove('d-none');
        searchResults.innerHTML = '<div class="list-group-item small text-muted">Checking good stock…</div>';
        try {
            const response = await fetch(`<?= url('api/shop/sales/products/scan') ?>?code=${encodeURIComponent(code)}`);
            const result = await response.json();
            if (!response.ok || !result.success || !result.data) {
                throw new Error(result.message || 'This product cannot be scanned.');
            }

            const product = result.data;
            product.variant_id = product.variant_id || product.id;
            const categoryType = String(product.category_type || 'NORMAL').toUpperCase();
            const isAggregate = Boolean(Number(product.is_complementary))
                || Boolean(Number(product.is_series))
                || categoryType === 'SERIES'
                || categoryType === 'COMPLEMENTARY';
            const pieceTag = isAggregate ? '' : (product.scanned_piece_tag || code);
            if (Number(product.available_stock) <= 0) {
                throw new Error('This product has no good stock available in the shop.');
            }
            addProduct(product, pieceTag);
            searchInput.value = '';
            searchResults.classList.add('d-none');
            searchInput.focus();
        } catch (error) {
            searchResults.innerHTML = `<div class="list-group-item small text-danger">${escapeHtml(error.message || 'Product scan failed.')}</div>`;
        }
    }

    async function searchProducts(query) {
        if (query.length < 2) {
            searchResults.classList.add('d-none');
            searchResults.replaceChildren();
            return;
        }
        searchResults.classList.remove('d-none');
        searchResults.innerHTML = '<div class="list-group-item small text-muted">Searching products…</div>';
        try {
            const response = await fetch(`<?= url('api/shop/sales/products/search') ?>?q=${encodeURIComponent(query)}`);
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Product search failed.');
            }
            const products = Array.isArray(result.data) ? result.data : [];
            searchResults.replaceChildren();
            if (products.length === 0) {
                searchResults.innerHTML = '<div class="list-group-item small text-muted">No in-stock products found.</div>';
            } else {
                products.forEach(product => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action';
                    button.innerHTML = `<span class="fw-semibold">${escapeHtml(product.product_name)}</span>
                        <small class="d-block text-muted">${escapeHtml(product.sku)} · ${escapeHtml(product.size_name || '')} ${escapeHtml(product.color_name || '')} · ${Number(product.available_stock) || 0} in stock · ₹${Number(product.selling_price || 0).toFixed(2)}</small>`;
                    button.addEventListener('click', () => {
                        addProduct(product);
                        searchInput.value = '';
                        searchResults.classList.add('d-none');
                    });
                    searchResults.appendChild(button);
                });
            }
            searchResults.classList.remove('d-none');
        } catch (error) {
            searchResults.innerHTML = `<div class="list-group-item small text-danger">${escapeHtml(error.message || 'Product search failed.')}</div>`;
        }
    }

    itemBody.addEventListener('input', () => {
        const total = updateEstimate();
        if (paymentMode.value === 'CASH_UPI') {
            const upiInput = document.querySelector('[name="upi_amount"]');
            const upi = Math.min(total, Math.max(0, Number(upiInput.value) || 0));
            upiInput.value = upi.toFixed(2);
            document.querySelector('[name="cash_amount"]').value = (total - upi).toFixed(2);
        }
    });
    itemBody.addEventListener('click', event => {
        const removeButton = event.target.closest('.remove-invoice-item');
        if (removeButton) {
            removeButton.closest('.invoice-item-row').remove();
            updateEstimate();
        }
    });
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => searchProducts(searchInput.value.trim()), 250);
    });
    searchInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            const code = searchInput.value.trim();
            if (code) {
                clearTimeout(searchTimer);
                scanProduct(code);
            }
        }
    });
    searchInput.addEventListener('blur', () => setTimeout(() => searchResults.classList.add('d-none'), 150));
    searchInput.addEventListener('focus', () => {
        if (searchResults.childElementCount > 0) searchResults.classList.remove('d-none');
    });
    paymentMode.addEventListener('change', updateSplitFields);
    document.querySelectorAll('.split-payment-input').forEach(input => {
        input.addEventListener('input', () => updateSplitFromEditedAmount(input));
    });
    document.getElementById('editInvoiceForm').addEventListener('submit', event => {
        if (itemBody.querySelectorAll('.invoice-item-row').length === 0) {
            event.preventDefault();
            window.alert('An invoice must contain at least one product.');
        }
    });

    updateSplitFields();
    const initialTotal = updateEstimate();
    if (paymentMode.value === 'CASH_UPI') {
        const cashInput = document.querySelector('[name="cash_amount"]');
        const upiInput = document.querySelector('[name="upi_amount"]');
        if ((Number(cashInput.value) || 0) + (Number(upiInput.value) || 0) <= 0) {
            cashInput.value = initialTotal.toFixed(2);
            upiInput.value = '0.00';
        }
    }
})();
</script>
