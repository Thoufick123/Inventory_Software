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
    const scannedQrRow = document.getElementById('adjustmentScannedQrRow');
    const scannedQrCodesDisplay = document.getElementById('adjustmentScannedQrCodes');
    const quantityMessage = document.getElementById('adjustmentQuantityMessage');
    const quantityLabel = document.getElementById('adjustmentQuantityLabel');
    const increaseControls = document.getElementById('adjustmentIncreaseControls');
    const generateQrControls = document.getElementById('adjustmentGenerateQrControls');
    const countOnlyNotice = document.getElementById('adjustmentCountOnlyNotice');
    const decreaseCountNotice = document.getElementById('adjustmentDecreaseCountNotice');
    const previewButton = document.getElementById('adjustmentPreviewButton');
    const previewMessage = document.getElementById('adjustmentPreviewMessage');
    const preview = document.getElementById('adjustmentQrPreview');
    const previewList = document.getElementById('adjustmentQrPreviewList');
    const previewSummary = document.getElementById('adjustmentPreviewSummary');
    let currentProduct = null;
    let previewedCodes = [];
    let scannedQrCodes = [];
    let lastProductLookupCode = '';

    function showMessage(element, message, isError = false) {
        element.textContent = message;
        element.className = `small mt-2 ${isError ? 'text-danger fw-semibold' : 'text-muted'}`;
    }

    function selectedWarehouseId() {
        return warehouseSelect.value || form.querySelector('input[name="warehouse_id"]')?.value || '';
    }

    function syncQrCodes() {
        const codes = typeSelect.value === 'DECREASE'
            ? scannedQrCodes.map(item => item.code)
            : previewedCodes;
        qrCodesInput.value = JSON.stringify(codes);
    }

    function clearPreview() {
        previewedCodes = [];
        preview.classList.add('d-none');
        previewList.replaceChildren();
        previewSummary.textContent = '';
        syncQrCodes();
    }

    function renderScannedQrCodes() {
        const isDecrease = typeSelect.value === 'DECREASE';
        const codes = isDecrease
            ? scannedQrCodes
            : (lastProductLookupCode ? [{ code: lastProductLookupCode }] : []);
        scannedQrCodesDisplay.replaceChildren();
        scannedQrRow.classList.toggle('d-none', codes.length === 0);

        codes.forEach((item, index) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-white text-dark border font-monospace text-break';
            badge.textContent = item.code;
            scannedQrCodesDisplay.appendChild(badge);

            if (isDecrease) {
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'btn btn-sm btn-outline-danger py-0 px-1';
                removeButton.setAttribute('aria-label', `Remove scanned QR ${item.code}`);
                removeButton.textContent = '×';
                removeButton.addEventListener('click', () => {
                    scannedQrCodes.splice(index, 1);
                    syncQrCodes();
                    renderScannedQrCodes();
                    updateDecreaseQuantity();
                    productScanInput.focus();
                });
                scannedQrCodesDisplay.appendChild(removeButton);
            }
        });
    }

    function updateDecreaseQuantity() {
        const quantity = scannedQrCodes.reduce((sum, item) => sum + item.quantity, 0);
        quantityInput.value = quantity > 0 ? String(quantity) : '';
        if (quantity > 0) {
            showMessage(quantityMessage, `${scannedQrCodes.length} QR tag(s) scanned · ${quantity} pcs will be removed.`);
        } else {
            showMessage(quantityMessage, 'Scan one or more eligible QR tags to calculate the decrease quantity.');
        }
    }

    function renderProductDetails(product) {
        productDetails.classList.remove('d-none');
        document.getElementById('adjustmentProductName').textContent = product.product_name;
        document.getElementById('adjustmentProductSku').textContent = product.sku;
        document.getElementById('adjustmentProductVariant').textContent = `${product.size_name || 'Free'} / ${product.color_name || 'Std'}`;
        document.getElementById('adjustmentAvailableStock').textContent = `${Number(product.available_stock).toLocaleString()} pcs`;
        document.getElementById('adjustmentQrFormat').textContent = product.is_series_or_complementary
            ? `Series / pack of ${product.quantity_per_tag}`
            : 'Individual label';
        const formatPrice = value => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'INR' }).format(Number(value) || 0);
        document.getElementById('adjustmentMrp').textContent = formatPrice(product.mrp);
        document.getElementById('adjustmentSellingPrice').textContent = formatPrice(product.selling_price);
        document.getElementById('adjustmentCostPrice').textContent = formatPrice(product.cost_price);
        const sourceBadge = document.getElementById('adjustmentProductSource');
        sourceBadge.textContent = product.source_label;
        sourceBadge.className = `badge ${product.source_type === 'EXTERNAL_PURCHASE' ? 'bg-info text-dark' : 'bg-primary'}`;
        const supplierRow = document.getElementById('adjustmentSupplierRow');
        supplierRow.classList.toggle('d-none', !product.supplier_name);
        document.getElementById('adjustmentSupplier').textContent = product.supplier_name || '';
        renderScannedQrCodes();
    }

    function updateIncreaseMode() {
        const countOnly = typeSelect.value === 'INCREASE'
            && Boolean(currentProduct && currentProduct.is_series_or_complementary);
        generateQrControls.classList.toggle('d-none', countOnly);
        countOnlyNotice.classList.toggle('d-none', !countOnly);
    }

    function setTypeState() {
        const isDecrease = typeSelect.value === 'DECREASE';
        typeValue.value = typeSelect.value;
        increaseControls.classList.toggle('d-none', isDecrease);
        decreaseCountNotice.classList.toggle('d-none', !isDecrease);
        updateIncreaseMode();
        quantityInput.readOnly = isDecrease;
        quantityInput.required = !isDecrease;
        quantityInput.title = isDecrease ? 'Calculated from the scanned QR tags.' : '';
        quantityLabel.textContent = isDecrease ? 'Quantity (Pcs) - calculated from QR scans' : 'Quantity (Pcs) *';
        quantityInput.placeholder = isDecrease ? 'Scan QR code(s)' : '0';
        clearPreview();
        if (isDecrease) {
            updateDecreaseQuantity();
        } else {
            quantityInput.value = '';
            quantityMessage.textContent = '';
        }
        renderScannedQrCodes();
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

    async function addDecreaseQr(code) {
        const result = await lookupProduct(code, 'decrease');
        const product = result.variant;
        if (scannedQrCodes.some(item => item.code.toUpperCase() === String(result.tag_code).toUpperCase())) {
            throw new Error(`QR code ${result.tag_code} has already been scanned.`);
        }
        if (scannedQrCodes.length && Number(currentProduct.id) !== Number(product.id)) {
            throw new Error('Scan QR codes for one product variant per stock adjustment.');
        }
        const quantity = Number(product.quantity_per_tag) || 1;
        const alreadyScanned = scannedQrCodes.reduce((sum, item) => sum + item.quantity, 0);
        if (alreadyScanned + quantity > Number(product.available_stock)) {
            throw new Error(`The scanned QR codes represent ${alreadyScanned + quantity} pcs, but only ${Number(product.available_stock)} pcs are available.`);
        }

        currentProduct = product;
        lastProductLookupCode = result.tag_code;
        variantIdInput.value = product.id;
        scannedQrCodes.push({ code: result.tag_code, quantity });
        syncQrCodes();
        updateIncreaseMode();
        renderProductDetails(product);
        updateDecreaseQuantity();
        productScanInput.value = '';
        showMessage(productScanMessage, `${result.tag_code} scanned. Scan another QR for this same product or save the decrease.`);
        productScanInput.focus();
    }

    async function findProduct() {
        const code = productScanInput.value.trim();
        if (!code) return;
        productScanButton.disabled = true;
        showMessage(productScanMessage, typeSelect.value === 'DECREASE'
            ? 'Validating scanned QR code and calculating its quantity...'
            : 'Looking up product in the selected warehouse...');
        try {
            if (typeSelect.value === 'DECREASE') {
                await addDecreaseQr(code);
                return;
            }

            const result = await lookupProduct(code);
            currentProduct = result.variant;
            lastProductLookupCode = result.tag_code || code;
            updateIncreaseMode();
            variantIdInput.value = currentProduct.id;
            renderProductDetails(currentProduct);
            productScanInput.value = '';
            clearPreview();
            quantityInput.value = '';
            quantityMessage.textContent = '';
            showMessage(productScanMessage, 'Product found. Confirm the type and quantity below.');
            quantityInput.focus();
        } catch (error) {
            if (typeSelect.value !== 'DECREASE') {
                currentProduct = null;
                lastProductLookupCode = '';
                variantIdInput.value = '';
                productDetails.classList.add('d-none');
                clearPreview();
                quantityInput.value = '';
                quantityMessage.textContent = '';
                renderScannedQrCodes();
            }
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
        lastProductLookupCode = '';
        scannedQrCodes = [];
        updateIncreaseMode();
        variantIdInput.value = '';
        productDetails.classList.add('d-none');
        clearPreview();
        renderScannedQrCodes();
        quantityInput.value = '';
        quantityMessage.textContent = '';
        showMessage(productScanMessage, 'Warehouse changed. Scan the product again.');
    });

    typeSelect.addEventListener('change', () => {
        scannedQrCodes = [];
        setTypeState();
        if (typeSelect.value === 'DECREASE' && currentProduct && lastProductLookupCode) {
            addDecreaseQr(lastProductLookupCode).catch(error => {
                showMessage(productScanMessage, `${error.message} Scan an eligible QR tag to set the decrease quantity.`, true);
                productScanInput.focus();
            });
        } else {
            productScanInput.focus();
        }
    });
    quantityInput.addEventListener('input', () => {
        clearPreview();
        quantityMessage.textContent = '';
        if (typeSelect.value !== 'DECREASE' || !currentProduct || !quantityInput.value) return;
        if (Number(quantityInput.value) > Number(currentProduct.available_stock)) {
            showMessage(quantityMessage, `Only ${Number(currentProduct.available_stock)} pcs are available in this warehouse.`, true);
        }
    });

    previewButton.addEventListener('click', async () => {
        const quantity = Number(quantityInput.value);
        if (!currentProduct) {
            showMessage(previewMessage, 'Scan a product before generating QR labels.', true);
            return;
        }
        if (currentProduct.is_series_or_complementary) {
            showMessage(previewMessage, 'Series and complementary stock increases do not generate new QR labels.', true);
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
        if (typeSelect.value === 'DECREASE' && scannedQrCodes.length === 0) {
            event.preventDefault();
            showMessage(productScanMessage, 'Scan at least one eligible product QR code before decreasing stock.', true);
            productScanInput.focus();
            return;
        }
        const quantity = Number(quantityInput.value);
        if (!Number.isInteger(quantity) || quantity <= 0) {
            event.preventDefault();
            showMessage(quantityMessage, 'Enter a positive whole-number quantity.', true);
            quantityInput.focus();
            return;
        }
        if (typeSelect.value === 'DECREASE'
            && quantity !== scannedQrCodes.reduce((sum, item) => sum + item.quantity, 0)) {
            event.preventDefault();
            showMessage(quantityMessage, 'The quantity must match the scanned QR codes. Scan them again before saving.', true);
            productScanInput.focus();
            return;
        }
        if (typeSelect.value === 'DECREASE' && quantity > Number(currentProduct.available_stock)) {
            event.preventDefault();
            showMessage(quantityMessage, `Only ${Number(currentProduct.available_stock)} pcs are available in this warehouse.`, true);
            quantityInput.focus();
            return;
        }
        if (typeSelect.value === 'INCREASE' && !currentProduct.is_series_or_complementary && !previewedCodes.length) {
            event.preventDefault();
            showMessage(previewMessage, 'Generate the QR preview before saving a stock increase.', true);
            previewButton.focus();
        }
    });

    document.getElementById('newAdjustmentModal').addEventListener('hidden.bs.modal', () => {
        currentProduct = null;
        lastProductLookupCode = '';
        scannedQrCodes = [];
        variantIdInput.value = '';
        productScanInput.value = '';
        productDetails.classList.add('d-none');
        typeSelect.value = form.querySelector('input[name="return_adjustment_type"]').value || 'INCREASE';
        setTypeState();
        qrCodesInput.value = '[]';
        renderScannedQrCodes();
        previewList.replaceChildren();
        productScanMessage.textContent = '';
        quantityMessage.textContent = '';
        previewMessage.textContent = '';
    });

    setTypeState();
    if (form.dataset.selectedProduct) {
        productScanInput.value = form.dataset.selectedProduct;
        findProduct();
    }
})();
