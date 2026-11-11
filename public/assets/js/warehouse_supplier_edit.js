document.addEventListener('DOMContentLoaded', function () {
    const nameFields = [
        document.getElementById('supplierNameInput'),
        document.getElementById('supplierCompanyInput')
    ].filter(Boolean);

    nameFields.forEach(function (input) {
        input.addEventListener('input', function () {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = input.value.toUpperCase();
            if (start !== null && end !== null) input.setSelectionRange(start, end);
        });
    });

    const form = document.getElementById('supplierEditForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            nameFields.forEach(function (input) {
                input.value = input.value.toUpperCase();
            });
            if (form.querySelectorAll('.is-invalid').length > 0) {
                e.preventDefault();
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                alert('Please fix highlighted errors before saving changes.');
                return false;
            }
        });
    }

    setupWarehouseSupplierEditValidation();
});

function handleExistingRowQRModeChange(select) {
    const row = select.closest('tr');
    if (!row) return;
    const seriesBox = row.querySelector('.existing-series-box');
    if (select.value === 'SERIES_BATCH') {
        if (seriesBox) seriesBox.classList.remove('d-none');
    } else {
        if (seriesBox) seriesBox.classList.add('d-none');
    }
}

function handleNewRowQRModeChange(select) {
    const row = select.closest('tr');
    if (!row) return;
    const seriesBox = row.querySelector('.row-series-box');
    if (select.value === 'SERIES_BATCH') {
        if (seriesBox) seriesBox.classList.remove('d-none');
        const skuInput = row.querySelector('.sku-input');
        const startInput = row.querySelector('.series-start-input');
        const endInput = row.querySelector('.series-end-input');
        let sku = (skuInput && skuInput.value ? skuInput.value.trim() : '');
        if (!sku) {
            const name = row.querySelector('.item-name-input')?.value || document.getElementById('supplierCompanyInput')?.value || 'ITEM';
            const color = row.querySelector('.color-input')?.value || '';
            const size = row.querySelector('.size-input')?.value || '';
            sku = generateCleanSku(name, color, size);
            if (skuInput) skuInput.value = sku;
        }
        if (startInput && !startInput.value.trim()) startInput.value = sku + '-001';
        if (endInput && !endInput.value.trim()) endInput.value = sku + '-010';
    } else {
        if (seriesBox) seriesBox.classList.add('d-none');
    }
}

function generateCleanSku(name, color, size) {
    name = (name || '').trim();
    color = (color || '').trim();
    size = (size || '').trim();
    const cleanName = name.replace(/[^A-Za-z0-9]/g, '').substring(0, 5).toUpperCase() || 'ITEM';
    const cleanColor = color ? '-' + color.replace(/[^A-Za-z0-9]/g, '').substring(0, 3).toUpperCase() : '';
    const cleanSize = size ? '-' + size.replace(/[^A-Za-z0-9]/g, '').substring(0, 4).toUpperCase() : '';
    const rand = Math.floor(100 + Math.random() * 900);
    return 'TP-' + cleanName + cleanColor + cleanSize + '-' + rand;
}

function autoGenerateNewSku(btn) {
    const row = btn.closest('tr');
    const name = row.querySelector('.item-name-input')?.value || document.getElementById('supplierCompanyInput')?.value || 'ITEM';
    const color = row.querySelector('.color-input')?.value || '';
    const size = row.querySelector('.size-input')?.value || '';
    const skuInput = row.querySelector('.sku-input');
    if (skuInput) skuInput.value = generateCleanSku(name, color, size);
}

function handleNewProductNameInput(input) {
    const row = input.closest('tr');
    const skuInput = row.querySelector('.sku-input');
    if (skuInput && !skuInput.value.trim()) {
        const color = row.querySelector('.color-input')?.value || '';
        const size = row.querySelector('.size-input')?.value || '';
        const newSku = generateCleanSku(input.value, color, size);
        skuInput.value = newSku;
    }
}

function handleNewVariantPropInput(input) {
    const row = input.closest('tr');
    const skuInput = row.querySelector('.sku-input');
    if (skuInput && (!skuInput.value.trim() || skuInput.value.startsWith('TP-'))) {
        const name = row.querySelector('.item-name-input')?.value || document.getElementById('supplierCompanyInput')?.value || 'ITEM';
        const color = row.querySelector('.color-input')?.value || '';
        const size = row.querySelector('.size-input')?.value || '';
        skuInput.value = generateCleanSku(name, color, size);
    }
}

function addNewProductRow() {
    const placeholder = document.getElementById('noNewRowsPlaceholder');
    if (placeholder) placeholder.style.display = 'none';

    const template = document.getElementById('newProductRowTemplate');
    const tbody = document.getElementById('newProductsTableBody');
    const clone = template.content.cloneNode(true);
    tbody.appendChild(clone);
    updateNewRowNumbers();
}

function removeNewProductRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    updateNewRowNumbers();
    const rows = document.querySelectorAll('#newProductsTableBody tr.new-product-row');
    if (rows.length === 0) {
        const placeholder = document.getElementById('noNewRowsPlaceholder');
        if (placeholder) placeholder.style.display = '';
    }
}

function updateNewRowNumbers() {
    const rows = document.querySelectorAll('#newProductsTableBody tr.new-product-row');
    rows.forEach((row, idx) => {
        const numEl = row.querySelector('.new-row-num');
        if (numEl) numEl.textContent = idx + 1;
    });
}

/**
 * Real-time Inline & Duplicate Validation for Warehouse Supplier Edit Screen.
 */
function setupWarehouseSupplierEditValidation() {
    const supplierId = window.currentWarehouseSupplierId || 0;
    const phoneInput = document.getElementById('supplierPhoneInput');
    const phoneFeedback = document.getElementById('supplierPhoneFeedback');
    const phoneValidFeedback = document.getElementById('supplierPhoneValidFeedback');

    const gstInput = document.getElementById('supplierGstInput');
    const gstFeedback = document.getElementById('supplierGstFeedback');
    const gstValidFeedback = document.getElementById('supplierGstValidFeedback');

    const nameInput = document.getElementById('supplierNameInput');
    const nameFeedback = document.getElementById('supplierNameFeedback');
    const nameValidFeedback = document.getElementById('supplierNameValidFeedback');

    const companyInput = document.getElementById('supplierCompanyInput');
    const companyFeedback = document.getElementById('supplierCompanyFeedback');
    const companyValidFeedback = document.getElementById('supplierCompanyValidFeedback');

    const emailInput = document.getElementById('supplierEmailInput');
    const emailFeedback = document.getElementById('supplierEmailFeedback');

    let phoneTimer = null;
    let gstTimer = null;
    let companyTimer = null;

    if (phoneInput) {
        function checkPhoneInline() {
            phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 10);
            const val = phoneInput.value.trim();

            if (!val || val.length !== 10) {
                phoneInput.classList.remove('is-valid');
                phoneInput.classList.add('is-invalid');
                if (phoneFeedback) {
                    phoneFeedback.textContent = `Phone must be exactly 10 digits (currently ${val.length}).`;
                    phoneFeedback.classList.remove('d-none');
                }
                if (phoneValidFeedback) phoneValidFeedback.classList.add('d-none');
                return;
            }

            if (!['6','7','8','9'].includes(val[0])) {
                phoneInput.classList.remove('is-valid');
                phoneInput.classList.add('is-invalid');
                if (phoneFeedback) {
                    phoneFeedback.textContent = 'Indian mobile number must start with 6, 7, 8, or 9.';
                    phoneFeedback.classList.remove('d-none');
                }
                if (phoneValidFeedback) phoneValidFeedback.classList.add('d-none');
                return;
            }

            clearTimeout(phoneTimer);
            phoneTimer = setTimeout(() => {
                fetch(`/api/warehouse/sellers/check-duplicate?field=phone&value=${encodeURIComponent(val)}&except_id=${supplierId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.valid) {
                            phoneInput.classList.remove('is-valid');
                            phoneInput.classList.add('is-invalid');
                            if (phoneFeedback) { phoneFeedback.textContent = data.message; phoneFeedback.classList.remove('d-none'); }
                            if (phoneValidFeedback) phoneValidFeedback.classList.add('d-none');
                        } else if (!data.available) {
                            phoneInput.classList.remove('is-valid');
                            phoneInput.classList.add('is-invalid');
                            if (phoneFeedback) { phoneFeedback.textContent = `⚠️ Duplicate: ${data.message}`; phoneFeedback.classList.remove('d-none'); }
                            if (phoneValidFeedback) phoneValidFeedback.classList.add('d-none');
                        } else {
                            phoneInput.classList.remove('is-invalid');
                            phoneInput.classList.add('is-valid');
                            if (phoneFeedback) phoneFeedback.classList.add('d-none');
                            if (phoneValidFeedback) { phoneValidFeedback.textContent = '✓ Phone number is valid & available.'; phoneValidFeedback.classList.remove('d-none'); }
                        }
                    }).catch(() => {});
            }, 300);
        }

        phoneInput.addEventListener('input', checkPhoneInline);
        phoneInput.addEventListener('blur', checkPhoneInline);
    }

    if (gstInput) {
        function checkGstInline() {
            gstInput.value = gstInput.value.toUpperCase().trim();
            const val = gstInput.value;

            if (!val) {
                gstInput.classList.remove('is-invalid', 'is-valid');
                if (gstFeedback) gstFeedback.classList.add('d-none');
                if (gstValidFeedback) gstValidFeedback.classList.add('d-none');
                return;
            }

            if (!/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/.test(val)) {
                gstInput.classList.remove('is-valid');
                gstInput.classList.add('is-invalid');
                if (gstFeedback) {
                    gstFeedback.textContent = 'Invalid GSTIN format (15 characters alphanumeric).';
                    gstFeedback.classList.remove('d-none');
                }
                if (gstValidFeedback) gstValidFeedback.classList.add('d-none');
                return;
            }

            clearTimeout(gstTimer);
            gstTimer = setTimeout(() => {
                fetch(`/api/warehouse/sellers/check-duplicate?field=gst_number&value=${encodeURIComponent(val)}&except_id=${supplierId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.valid) {
                            gstInput.classList.remove('is-valid');
                            gstInput.classList.add('is-invalid');
                            if (gstFeedback) { gstFeedback.textContent = data.message; gstFeedback.classList.remove('d-none'); }
                            if (gstValidFeedback) gstValidFeedback.classList.add('d-none');
                        } else if (!data.available) {
                            gstInput.classList.remove('is-valid');
                            gstInput.classList.add('is-invalid');
                            if (gstFeedback) { gstFeedback.textContent = `⚠️ Duplicate: ${data.message}`; gstFeedback.classList.remove('d-none'); }
                            if (gstValidFeedback) gstValidFeedback.classList.add('d-none');
                        } else {
                            gstInput.classList.remove('is-invalid');
                            gstInput.classList.add('is-valid');
                            if (gstFeedback) gstFeedback.classList.add('d-none');
                            if (gstValidFeedback) { gstValidFeedback.textContent = '✓ GSTIN is valid & available.'; gstValidFeedback.classList.remove('d-none'); }
                        }
                    }).catch(() => {});
            }, 300);
        }

        gstInput.addEventListener('input', checkGstInline);
        gstInput.addEventListener('blur', checkGstInline);
    }

    if (nameInput) {
        function checkNameInline() {
            const val = nameInput.value.trim();
            if (val.length < 2) {
                nameInput.classList.remove('is-valid');
                nameInput.classList.add('is-invalid');
                if (nameFeedback) { nameFeedback.textContent = 'Supplier name is required (min 2 chars).'; nameFeedback.classList.remove('d-none'); }
                if (nameValidFeedback) nameValidFeedback.classList.add('d-none');
            } else {
                nameInput.classList.remove('is-invalid');
                nameInput.classList.add('is-valid');
                if (nameFeedback) nameFeedback.classList.add('d-none');
                if (nameValidFeedback) { nameValidFeedback.textContent = '✓ Name is valid.'; nameValidFeedback.classList.remove('d-none'); }
            }
        }
        nameInput.addEventListener('input', checkNameInline);
        nameInput.addEventListener('blur', checkNameInline);
    }

    if (companyInput) {
        function checkCompanyInline() {
            const val = companyInput.value.trim();
            if (!val) {
                companyInput.classList.remove('is-invalid', 'is-valid');
                if (companyFeedback) companyFeedback.classList.add('d-none');
                if (companyValidFeedback) companyValidFeedback.classList.add('d-none');
                return;
            }

            clearTimeout(companyTimer);
            companyTimer = setTimeout(() => {
                fetch(`/api/warehouse/sellers/check-duplicate?field=company&value=${encodeURIComponent(val)}&except_id=${supplierId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.available) {
                            companyInput.classList.remove('is-valid');
                            companyInput.classList.add('is-invalid');
                            if (companyFeedback) { companyFeedback.textContent = `⚠️ Duplicate: ${data.message}`; companyFeedback.classList.remove('d-none'); }
                            if (companyValidFeedback) companyValidFeedback.classList.add('d-none');
                        } else {
                            companyInput.classList.remove('is-invalid');
                            companyInput.classList.add('is-valid');
                            if (companyFeedback) companyFeedback.classList.add('d-none');
                            if (companyValidFeedback) { companyValidFeedback.textContent = '✓ Company name is available.'; companyValidFeedback.classList.remove('d-none'); }
                        }
                    }).catch(() => {});
            }, 350);
        }
        companyInput.addEventListener('input', checkCompanyInline);
        companyInput.addEventListener('blur', checkCompanyInline);
    }

    if (emailInput) {
        function checkEmailInline() {
            const val = emailInput.value.trim();
            if (!val) {
                emailInput.classList.remove('is-invalid');
                if (emailFeedback) emailFeedback.classList.add('d-none');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                emailInput.classList.add('is-invalid');
                if (emailFeedback) { emailFeedback.textContent = 'Please enter a valid email address.'; emailFeedback.classList.remove('d-none'); }
            } else {
                emailInput.classList.remove('is-invalid');
                if (emailFeedback) emailFeedback.classList.add('d-none');
            }
        }
        emailInput.addEventListener('input', checkEmailInline);
        emailInput.addEventListener('blur', checkEmailInline);
    }

    // Attach real-time duplicate checks on product SKU inputs in both tables
    function checkTableSkuDuplicates() {
        const skuInputs = Array.from(document.querySelectorAll('input[name="existing_sku[]"], input[name="new_sku[]"]'));
        const skuCounts = new Map();

        skuInputs.forEach(inp => {
            const val = inp.value.trim().toUpperCase();
            if (val) {
                skuCounts.set(val, (skuCounts.get(val) || 0) + 1);
            }
        });

        skuInputs.forEach(inp => {
            const val = inp.value.trim().toUpperCase();
            if (!val) {
                inp.classList.remove('is-invalid');
                inp.removeAttribute('title');
            } else if (skuCounts.get(val) > 1) {
                inp.classList.add('is-invalid');
                inp.title = 'Duplicate SKU in this supplier product list!';
            } else {
                inp.classList.remove('is-invalid');
                inp.removeAttribute('title');
            }
        });
    }

    document.addEventListener('input', function(e) {
        if (e.target && (e.target.name === 'existing_sku[]' || e.target.name === 'new_sku[]')) {
            checkTableSkuDuplicates();
        }
        if (e.target && (e.target.name === 'existing_cost[]' || e.target.name === 'new_cost[]')) {
            const num = parseFloat(e.target.value);
            if (isNaN(num) || num < 0) {
                e.target.classList.add('is-invalid');
            } else {
                e.target.classList.remove('is-invalid');
            }
        }
    });
}
