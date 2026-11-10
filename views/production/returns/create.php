<?php
// Production Unit Record Return / Exchange Form
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-arrow-return-left text-danger me-2"></i>Record Customer Return / Exchange
            </h4>
            <span class="text-muted small">Select an existing production bill, choose garments to return, and generate a GST credit note</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('production/returns') ?>" class="btn btn-outline-secondary btn-sm fw-semibold shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Returns Log
            </a>
        </div>
    </div>

    <!-- Step 1: Bill Lookup Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-search text-primary me-2"></i>Step 1: Lookup Production Bill / Invoice
            </h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-bold text-dark mb-1">Enter Bill Number or Customer Phone</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-receipt"></i></span>
                        <input type="text" id="billSearchInput" class="form-control fw-bold" placeholder="e.g. PB-2026-..., or customer mobile number..." autocomplete="off">
                        <button type="button" class="btn btn-primary px-4 fw-semibold" id="btnLookupBill" onclick="lookupBill()">
                            <i class="bi bi-search me-1"></i> Find Bill
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div id="lookupStatus" class="small text-muted">Enter a bill number to load items and verified QR piece tags.</div>
                </div>
            </div>

            <!-- Bill Info Banner (Hidden until found) -->
            <div id="billInfoBanner" class="alert alert-light border mt-3 mb-0 d-none">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <strong class="text-dark fs-6" id="bInfoNumber"></strong>
                        <span class="badge bg-success ms-2" id="bInfoDate"></span>
                        <div class="text-muted small mt-1">
                            Customer: <strong class="text-dark" id="bInfoCustomer"></strong> | Phone: <span id="bInfoPhone"></span>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small d-block">Original Bill Total</span>
                        <strong class="text-primary fs-5" id="bInfoTotal"></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Return Items Form (Hidden until bill loaded) -->
    <form method="POST" action="<?= url('production/returns/store') ?>" id="returnForm" class="d-none">
        <?= CSRF::field() ?>
        <input type="hidden" name="bill_id" id="hiddenBillId" value="">

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-check2-square text-danger me-2"></i>Step 2: Select Items &amp; Tags for Return
                    </h6>
                    <span class="text-muted small">Check the box for each specific garment piece being returned</span>
                </div>
                <div>
                    <span class="badge bg-secondary px-3 py-2 fs-6" id="returnSelectedBadge">0 pieces selected (₹0.00)</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="fullyReturnedNotice" class="d-none m-3 alert alert-warning border-warning d-flex align-items-center mb-0">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
                    <div>
                        <strong>All items on this bill have already been returned!</strong>
                        <div class="small">A credit note has already been processed for all pieces on this bill. No further returns can be created.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="returnItemsTable">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th style="width: 50px;" class="text-center">Select</th>
                                <th>Product Style / Details</th>
                                <th>HSN</th>
                                <th class="text-center">Scanned Tag Code</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-center">Condition</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody id="returnItemsTbody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Step 3: Return Disposition & Settlement -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-gear text-primary me-2"></i>Step 3: Return Disposition &amp; Settlement
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark mb-1">Return Action / Type</label>
                        <select name="return_type" class="form-select fw-semibold" required>
                            <option value="CREDIT_NOTE">GST Credit Note (Store Credit for next bill)</option>
                            <option value="EXCHANGE">Product Exchange (Swap size / color)</option>
                            <option value="REFUND">Cash / Direct Bank Refund</option>
                        </select>
                        <small class="text-muted">Determines how customer balance is reconciled</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark mb-1">Stock Disposition (What to do with items?)</label>
                        <select name="disposition" class="form-select fw-semibold" required>
                            <option value="RESTOCK_INVENTORY">Restock to Production Inventory (Good condition)</option>
                            <option value="REWORK_STITCHING">Send to Rework (Minor sewing defect/alteration)</option>
                            <option value="SCRAP_DAMAGE">Mark as Damaged / Production Scrap</option>
                        </select>
                        <small class="text-muted">Restocked pieces become available again in inventory</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark mb-1">Refund / Settlement Mode</label>
                        <select name="refund_mode" class="form-select fw-semibold" required>
                            <option value="STORE_CREDIT">Store Credit Voucher / Account Ledger</option>
                            <option value="CASH">Cash Refund</option>
                            <option value="UPI">UPI Refund</option>
                            <option value="BANK_TRANSFER">NEFT / Bank Transfer</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark mb-1">Notes / Management Remarks</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Customer returned 3 shirts due to size L fitting; approved by Production Floor Supervisor."></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="<?= url('production/returns') ?>" class="btn btn-outline-secondary px-3">Cancel</a>
                <button type="submit" class="btn btn-danger px-4 py-2 fw-semibold shadow-sm" id="btnSubmitReturn">
                    <i class="bi bi-check-circle-fill me-1"></i> Process Return &amp; Issue Credit Note
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function lookupBill() {
    const q = document.getElementById('billSearchInput').value.trim();
    if (!q) {
        alert('Please enter a bill number or customer phone.');
        return;
    }

    const statusEl = document.getElementById('lookupStatus');
    statusEl.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-1"></span> Searching...';

    fetch('<?= url("production/returns/lookup") ?>?q=' + encodeURIComponent(q))
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i> ' + (data.message || 'Bill not found') + '</span>';
                document.getElementById('billInfoBanner').classList.add('d-none');
                document.getElementById('returnForm').classList.add('d-none');
                return;
            }

            statusEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i> Bill found!</span>';
            const b = data.bill;
            document.getElementById('hiddenBillId').value = b.id;
            document.getElementById('bInfoNumber').textContent = b.bill_number;
            document.getElementById('bInfoDate').textContent = b.bill_date;
            document.getElementById('bInfoCustomer').textContent = b.customer_name || b.company_name || 'B2B Client';
            document.getElementById('bInfoPhone').textContent = b.customer_phone || b.company_phone || '-';
            document.getElementById('bInfoTotal').textContent = '₹' + parseFloat(b.total_amount).toFixed(2);
            document.getElementById('billInfoBanner').classList.remove('d-none');

            // Render items
            const tbody = document.getElementById('returnItemsTbody');
            tbody.innerHTML = '';

            const fullyReturnedNotice = document.getElementById('fullyReturnedNotice');
            const btnSubmit = document.getElementById('btnSubmitReturn');

            if (data.is_fully_returned) {
                fullyReturnedNotice.classList.remove('d-none');
                btnSubmit.disabled = true;
                btnSubmit.dataset.blocked = "1";
            } else {
                fullyReturnedNotice.classList.add('d-none');
                btnSubmit.disabled = false;
                delete btnSubmit.dataset.blocked;
            }

            let hasItems = false;
            data.items.forEach(item => {
                const tags = item.tags || [];
                if (tags.length > 0) {
                    tags.forEach((tagObj) => {
                        hasItems = true;
                        const tr = document.createElement('tr');
                        const isReturned = !!tagObj.is_returned;
                        tr.innerHTML = `
                            <td class="text-center">
                                ${isReturned ? '<span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><i class="bi bi-check2"></i> Returned</span>' : `
                                    <input type="checkbox" name="selected_tags[]" value="${tagObj.tag_code}" class="form-check-input return-checkbox" id="chk_tag_${tagObj.tag_code}" onchange="calcReturnTotals()" data-price="${item.unit_price}">
                                    <input type="hidden" name="tags[${tagObj.tag_code}][bill_item_id]" value="${item.id}">
                                    <input type="hidden" name="tags[${tagObj.tag_code}][tag_code]" value="${tagObj.tag_code}">
                                `}
                            </td>
                            <td>
                                <strong class="text-dark">${item.product_name}</strong>
                                <div class="small text-muted">${item.sku || ''} ${item.size_name ? '· Size: ' + item.size_name : ''} ${item.color_name ? '· Color: ' + item.color_name : ''}</div>
                            </td>
                            <td><span class="badge bg-light text-dark border font-monospace">${item.hsn_code || '6109'}</span></td>
                            <td class="text-center"><span class="font-monospace badge bg-dark text-white px-2 py-1">${tagObj.tag_code}</span></td>
                            <td class="text-end fw-semibold">₹${parseFloat(item.unit_price).toFixed(2)}</td>
                            <td class="text-center">
                                <select name="tags[${tagObj.tag_code}][condition]" class="form-select form-select-sm" style="width: 140px; margin: 0 auto;" ${isReturned ? 'disabled' : ''}>
                                    <option value="GOOD">Good (Resellable)</option>
                                    <option value="MINOR_DEFECT">Minor Defect</option>
                                    <option value="DAMAGED">Damaged / Scrap</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="tags[${tagObj.tag_code}][reason]" class="form-control form-control-sm" placeholder="e.g. Size mismatch" value="Customer request" ${isReturned ? 'disabled' : ''}>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    hasItems = true;
                    const tr = document.createElement('tr');
                    const remQty = parseInt(item.remaining_qty ?? item.quantity);
                    const isFullyReturned = remQty <= 0;
                    tr.innerHTML = `
                        <td class="text-center">
                            ${isFullyReturned ? '<span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><i class="bi bi-check2"></i> Returned</span>' : `
                                <input type="checkbox" name="selected_items[]" value="${item.id}" class="form-check-input return-checkbox" id="chk_item_${item.id}" onchange="calcReturnTotals()" data-price="${item.unit_price}">
                            `}
                        </td>
                        <td>
                            <strong class="text-dark">${item.product_name}</strong>
                            <div class="small text-muted">${item.sku || ''} ${item.size_name ? '· Size: ' + item.size_name : ''} ${item.color_name ? '· Color: ' + item.color_name : ''}</div>
                        </td>
                        <td><span class="badge bg-light text-dark border font-monospace">${item.hsn_code || '6109'}</span></td>
                        <td class="text-center">
                            ${isFullyReturned ? '<span class="text-muted small">0 / ' + item.quantity + ' pcs</span>' : `
                                <div class="input-group input-group-sm" style="width: 110px; margin: 0 auto;">
                                    <input type="number" min="1" max="${remQty}" name="items[${item.id}][qty]" id="qty_item_${item.id}" value="1" class="form-control text-center" onchange="calcReturnTotals()">
                                    <span class="input-group-text small text-muted">/ ${remQty}</span>
                                </div>
                            `}
                        </td>
                        <td class="text-end fw-semibold">₹${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td class="text-center">
                            <select name="items[${item.id}][condition]" class="form-select form-select-sm" style="width: 140px; margin: 0 auto;" ${isFullyReturned ? 'disabled' : ''}>
                                <option value="GOOD">Good (Resellable)</option>
                                <option value="MINOR_DEFECT">Minor Defect</option>
                                <option value="DAMAGED">Damaged / Scrap</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="items[${item.id}][reason]" class="form-control form-control-sm" placeholder="Reason" value="Customer return" ${isFullyReturned ? 'disabled' : ''}>
                        </td>
                    `;
                    tbody.appendChild(tr);
                }
            });

            if (hasItems) {
                document.getElementById('returnForm').classList.remove('d-none');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No items found on this bill.</td></tr>';
                document.getElementById('returnForm').classList.remove('d-none');
            }

            calcReturnTotals();
        })
        .catch(err => {
            statusEl.innerHTML = '<span class="text-danger">Error: ' + err.message + '</span>';
        });
}

function calcReturnTotals() {
    let totalPcs = 0;
    let totalAmount = 0.0;

    document.querySelectorAll('input[name="selected_tags[]"]:checked').forEach(cb => {
        totalPcs += 1;
        totalAmount += parseFloat(cb.dataset.price || 0);
    });

    document.querySelectorAll('input[name="selected_items[]"]:checked').forEach(cb => {
        const itemId = cb.value;
        const qtyInput = document.getElementById('qty_item_' + itemId);
        const qty = qtyInput ? parseInt(qtyInput.value || 1) : 1;
        totalPcs += qty;
        totalAmount += qty * parseFloat(cb.dataset.price || 0);
    });

    const badge = document.getElementById('returnSelectedBadge');
    if (badge) {
        badge.textContent = `${totalPcs} piece(s) selected (₹${totalAmount.toFixed(2)})`;
        badge.className = totalPcs > 0 ? 'badge bg-success px-3 py-2 fs-6' : 'badge bg-secondary px-3 py-2 fs-6';
    }

    const btnSubmit = document.getElementById('btnSubmitReturn');
    if (btnSubmit && !btnSubmit.dataset.blocked) {
        btnSubmit.disabled = (totalPcs === 0);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const returnForm = document.getElementById('returnForm');
    if (returnForm) {
        returnForm.addEventListener('submit', function(e) {
            const checkedTags = document.querySelectorAll('input[name="selected_tags[]"]:checked');
            const checkedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            if (checkedTags.length === 0 && checkedItems.length === 0) {
                e.preventDefault();
                alert('Please select at least one garment piece to return.');
                return false;
            }
        });
    }

    const billSearchInput = document.getElementById('billSearchInput');
    if (billSearchInput) {
        billSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupBill();
            }
        });
    }
});
</script>
