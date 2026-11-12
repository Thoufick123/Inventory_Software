(() => {
    const form = document.getElementById('stockAdjustmentForm');
    if (!form) return;

    const typeSelect = document.getElementById('adjustmentTypeSelect');
    const typeValue = document.getElementById('adjustmentTypeValue');
    const quantityInput = document.getElementById('adjustmentQuantityInput');
    const qrCodesInput = document.getElementById('adjustmentQrCodes');
    const variantIdInput = document.getElementById('adjustmentVariantId');
    const warehouseSelect = document.getElementById('adjustmentWarehouseSelect');
    const productScanInput = document.getElementById('adjustmentProductScanInput');
    const productScanButton = document.getElementById('adjustmentProductScanButton');
    const productScanMessage = document.getElementById('adjustmentProductScanMessage');
    const productDetails = document.getElementById('adjustmentProductDetails');
    const increaseControls = document.getElementById('adjustmentIncreaseControls');
    const decreaseControls = document.getElementById('adjustmentDecreaseControls');
    const decreaseScanInput = document.getElementById('adjustmentDecreaseScanInput');
    const decreaseScanButton = document.getElementById('adjustmentDecreaseScanButton');
    const decreaseMessage = document.getElementById('adjustmentDecreaseMessage');
    const decreaseList = document.getElementById('adjustmentDecreaseList');
    const previewButton = document.getElementById('adjustmentPreviewButton');
    const previewMessage = document.getElementById('adjustmentPreviewMessage');
    const preview = document.getElementById('adjustmentQrPreview');
    const previewList = document.getElementById('adjustmentQrPreviewList');
    const previewSummary = document.getElementById('adjustmentPreviewSummary');
    const scannedTags = [];
    let currentProduct = null;
    let previewedCodes = [];

    function showMessage(element, message, isError = false) {
        element.textContent = message;
        element.className = `small mt-2 ${isError ? 'text-danger fw-semibold' : 'text-muted'}`;
    }

    function selectedWarehouseId() {
        return warehouseSelect.value || form.querySelector('input[name="warehouse_id"]')?.value || '';
    }

    function renderScannedTags() {
        decreaseList.replaceChildren();
        scannedTags.forEach((entry, index) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-dark font-monospace d-inline-flex align-items-center gap-1';
            const label = document.createElement('span');
            label.textContent = `${entry.tag} (${entry.quantity} pcs)`;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-close btn-close-white';
            remove.setAttribute('aria-label', `Remove QR ${entry.tag}`);
            remove.addEventListener('click', () => {
                scannedTags.splice(index, 1);
                updateQrState();
            });
            badge.append(label, remove);
            decreaseList.appendChild(badge);
        });
        qrCodesInput.value = JSON.stringify(scannedTags.map(entry => entry.tag));
        quantityInput.value = scannedTags.reduce((total, entry) => total + entry.quantity, 0) || '';
    }

    function updateQrState() {
        renderScannedTags();
        showMessage(decreaseMessage, scannedTags.length ? `${scannedTags.length} QR code(s) scanned.` : '');
    }

    function clearPreview() {
        previewedCodes = [];
        preview.classList.add('d-none');
        previewList.replaceChildren();
        previewSummary.textContent = '';
        if (typeSelect.value === 'INCREASE') qrCodesInput.value = '[]';
    }

    function setTypeState() {
        const isDecrease = typeSelect.value === 'DECREASE';
        typeValue.value = typeSelect.value;
        increaseControls.classList.toggle('d-none', isDecrease);
        decreaseControls.classList.toggle('d-none', !isDecrease);
        quantityInput.readOnly = isDecrease;
        quantityInput.title = isDecrease ? 'Quantity is set by the scanned QR code(s).' : '';
        scannedTags.splice(0, scannedTags.length);
        updateQrState();
        clearPreview();
        quantityInput.value = '';
    }

    async function lookupProduct(code, mode = 'product') {
        const warehouseId = selectedWarehouseId();
        if (!warehouseId) throw new Error('Select a warehouse first.');
        const endpoint = `${form.dataset.lookupUrl}?code=${encodeURIComponent(code)}&warehouse_id=${encodeURIComponent(warehouseId)}&mode=${encodeURIComponent(mode)}`;
        const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok || !result.success || !result.variant) {
            throw new Error(result.message || 'The product QR code or SKU could not be found.');
        }
        return result;
    }

    async function findProduct() {
        const code = productScanInput.value.trim();
        if (!code) return;
        productScanButton.disabled = true;
        showMessage(productScanMessage, 'Looking up product in the selected warehouse...');
        try {
            const result = await lookupProduct(code);
            currentProduct = result.variant;
            variantIdInput.value = currentProduct.id;
            productDetails.classList.remove('d-none');
            document.getElementById('adjustmentProductName').textContent = currentProduct.product_name;
            document.getElementById('adjustmentProductSku').textContent = currentProduct.sku;
            document.getElementById('adjustmentProductVariant').textContent = `${currentProduct.size_name || 'Free'} / ${currentProduct.color_name || 'Std'}`;
            document.getElementById('adjustmentAvailableStock').textContent = `${Number(currentProduct.available_stock).toLocaleString()} pcs`;
            document.getElementById('adjustmentQrFormat').textContent = currentProduct.is_series_or_complementary
                ? `Series / pack of ${currentProduct.quantity_per_tag}`
                : 'Individual label';
            const formatPrice = value => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'INR' }).format(Number(value) || 0);
            document.getElementById('adjustmentMrp').textContent = formatPrice(currentProduct.mrp);
            document.getElementById('adjustmentSellingPrice').textContent = formatPrice(currentProduct.selling_price);
            document.getElementById('adjustmentCostPrice').textContent = formatPrice(currentProduct.cost_price);
            const sourceBadge = document.getElementById('adjustmentProductSource');
            sourceBadge.textContent = currentProduct.source_label;
            sourceBadge.className = `badge ${currentProduct.source_type === 'EXTERNAL_PURCHASE' ? 'bg-info text-dark' : 'bg-primary'}`;
            const supplierRow = document.getElementById('adjustmentSupplierRow');
            supplierRow.classList.toggle('d-none', !currentProduct.supplier_name);
            document.getElementById('adjustmentSupplier').textContent = currentProduct.supplier_name || '';
            productScanInput.value = '';
            scannedTags.splice(0, scannedTags.length);
            updateQrState();
            clearPreview();
            quantityInput.value = '';
            showMessage(productScanMessage, 'Product found. Confirm the type and quantity below.');
            if (typeSelect.value === 'DECREASE') decreaseScanInput.focus();
            else quantityInput.focus();
        } catch (error) {
            currentProduct = null;
            variantIdInput.value = '';
            productDetails.classList.add('d-none');
            showMessage(productScanMessage, error.message || 'Product lookup failed.', true);
        } finally {
            productScanButton.disabled = false;
        }
    }

    productScanButton.addEventListener('click', findProduct);
    productScanInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            findProduct();
        }
    });

    warehouseSelect.addEventListener('change', () => {
        currentProduct = null;
        variantIdInput.value = '';
        productDetails.classList.add('d-none');
        scannedTags.splice(0, scannedTags.length);
        updateQrState();
        clearPreview();
        quantityInput.value = '';
        showMessage(productScanMessage, 'Warehouse changed. Scan the product again.');
    });

    typeSelect.addEventListener('change', setTypeState);
    quantityInput.addEventListener('input', clearPreview);

    async function addScannedQr() {
        const code = decreaseScanInput.value.trim();
        if (!code) return;
        decreaseScanButton.disabled = true;
        showMessage(decreaseMessage, 'Checking QR code and warehouse stock...');
        try {
            if (!currentProduct) throw new Error('Scan the product first.');
            const result = await lookupProduct(code, 'decrease');
            const variant = result.variant;
            const tag = String(result.tag_code || '').trim().toUpperCase();
            if (Number(variant.id) !== Number(currentProduct.id)) throw new Error('The scanned QR belongs to a different product variant.');
            if (scannedTags.some(entry => entry.tag === tag)) throw new Error(`QR code ${tag} is already in this request.`);
            const tagQuantity = Math.max(1, Number(variant.quantity_per_tag) || 1);
            const availableStock = Number(variant.available_stock) || 0;
            const currentQuantity = scannedTags.reduce((total, entry) => total + entry.quantity, 0);
            if (currentQuantity + tagQuantity > availableStock) {
                throw new Error(`Only ${availableStock} available pcs remain; this QR would exceed the available stock.`);
            }
            scannedTags.push({ tag, quantity: tagQuantity });
            decreaseScanInput.value = '';
            updateQrState();
            decreaseScanInput.focus();
        } catch (error) {
            showMessage(decreaseMessage, error.message || 'QR scan failed.', true);
        } finally {
            decreaseScanButton.disabled = false;
        }
    }

    decreaseScanButton.addEventListener('click', addScannedQr);
    decreaseScanInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addScannedQr();
        }
    });

    previewButton.addEventListener('click', async () => {
        const quantity = Number(quantityInput.value);
        if (!currentProduct) {
            showMessage(previewMessage, 'Scan a product before generating QR labels.', true);
            return;
        }
        if (!Number.isInteger(quantity) || quantity <= 0) {
            showMessage(previewMessage, 'Enter a positive whole-number increase quantity.', true);
            return;
        }
        previewButton.disabled = true;
        showMessage(previewMessage, 'Generating a non-mutating QR preview...');
        try {
            const csrfToken = form.querySelector('input[name="_csrf_token"]').value;
            const response = await fetch(form.dataset.previewUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    variant_id: currentProduct.id,
                    warehouse_id: selectedWarehouseId(),
                    quantity: String(quantity)
                })
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'QR preview could not be generated.');
            if (typeof QRCode === 'undefined') throw new Error('The QR display library could not be loaded. Refresh and try again.');
            previewedCodes = result.preview.tags.map(tag => tag.tag_code);
            qrCodesInput.value = JSON.stringify(previewedCodes);
            previewList.replaceChildren();
            result.preview.tags.forEach(tag => {
                const column = document.createElement('div');
                column.className = 'col-6 col-md-4 col-lg-3';
                const card = document.createElement('div');
                card.className = 'border rounded p-2 text-center h-100';
                const qrBox = document.createElement('div');
                qrBox.className = 'd-flex justify-content-center mb-2';
                const codeLabel = document.createElement('div');
                codeLabel.className = 'small fw-bold font-monospace text-break';
                codeLabel.textContent = tag.tag_code;
                card.append(qrBox, codeLabel);
                if (Number(tag.pack_quantity) > 1) {
                    const pack = document.createElement('div');
                    pack.className = 'small text-muted';
                    pack.textContent = `${tag.quantity} pcs · through ${tag.series_end_code}`;
                    card.appendChild(pack);
                }
                column.appendChild(card);
                previewList.appendChild(column);
                new QRCode(qrBox, {
                    text: tag.tag_code,
                    width: 96,
                    height: 96,
                    correctLevel: QRCode.CorrectLevel.M
                });
            });
            previewSummary.textContent = `${previewedCodes.length} label(s) · ${result.preview.quantity} pcs`;
            preview.classList.remove('d-none');
            showMessage(previewMessage, 'Preview ready. No QR labels or stock have been saved yet.');
        } catch (error) {
            clearPreview();
            showMessage(previewMessage, error.message || 'QR preview failed.', true);
        } finally {
            previewButton.disabled = false;
        }
    });

    form.addEventListener('submit', event => {
        if (!currentProduct || !variantIdInput.value) {
            event.preventDefault();
            showMessage(productScanMessage, 'Scan a product before saving this adjustment.', true);
            productScanInput.focus();
            return;
        }
        if (typeSelect.value === 'INCREASE' && !previewedCodes.length) {
            event.preventDefault();
            showMessage(previewMessage, 'Generate the QR preview before saving a stock increase.', true);
            previewButton.focus();
            return;
        }
        if (typeSelect.value === 'DECREASE' && scannedTags.length === 0) {
            event.preventDefault();
            showMessage(decreaseMessage, 'Scan at least one existing product QR code before saving a decrease.', true);
            decreaseScanInput.focus();
        }
    });

    document.getElementById('newAdjustmentModal').addEventListener('hidden.bs.modal', () => {
        scannedTags.splice(0, scannedTags.length);
        currentProduct = null;
        variantIdInput.value = '';
        productScanInput.value = '';
        productDetails.classList.add('d-none');
        typeSelect.value = form.querySelector('input[name="return_adjustment_type"]').value || 'INCREASE';
        setTypeState();
        qrCodesInput.value = '[]';
        decreaseList.replaceChildren();
        previewList.replaceChildren();
        productScanMessage.textContent = '';
        decreaseMessage.textContent = '';
        previewMessage.textContent = '';
    });

    setTypeState();
    if (form.dataset.selectedProduct) {
        productScanInput.value = form.dataset.selectedProduct;
        findProduct();
    }
})();
