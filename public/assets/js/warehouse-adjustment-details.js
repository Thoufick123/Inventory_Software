(() => {
    const detailsModalElement = document.getElementById('adjustmentDetailsModal');
    if (!detailsModalElement) return;

    const detailsModal = new bootstrap.Modal(detailsModalElement);
    const loading = document.getElementById('adjustmentDetailsLoading');
    const detailsError = document.getElementById('adjustmentDetailsError');
    const detailsContent = document.getElementById('adjustmentDetailsContent');
    const qrList = document.getElementById('detailQrList');
    const qrEmpty = document.getElementById('detailQrEmpty');
    const qrCount = document.getElementById('detailQrCount');
    const editModalElement = document.getElementById('editAdjustmentModal');
    const editForm = document.getElementById('editAdjustmentForm');
    const editError = document.getElementById('editAdjustmentError');
    const editSaveButton = document.getElementById('editAdjustmentSaveButton');
    let editModal = null;

    if (editModalElement) editModal = new bootstrap.Modal(editModalElement);

    function showDetailsError(message) {
        loading.classList.add('d-none');
        detailsContent.classList.add('d-none');
        detailsError.textContent = message;
        detailsError.classList.remove('d-none');
    }

    async function fetchAdjustment(id) {
        const response = await fetch(`${detailsModalElement.dataset.detailsUrl}/${encodeURIComponent(id)}`, {
            headers: { Accept: 'application/json' }
        });
        const result = await response.json();
        if (!response.ok || !result.success || !result.adjustment) {
            throw new Error(result.message || 'Unable to load adjustment details.');
        }
        return result.adjustment;
    }

    function formatDate(value) {
        if (!value) return '—';
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString();
    }

    function renderQrCodes(adjustment) {
        qrList.replaceChildren();
        const codes = Array.isArray(adjustment.qr_codes)
            ? adjustment.qr_codes.filter(code => typeof code === 'string' || typeof code === 'number').map(String)
            : [];
        document.getElementById('detailQrHeading').textContent = adjustment.adjustment_type === 'INCREASE'
            ? 'QR codes added'
            : 'QR codes removed';
        qrCount.textContent = codes.length ? `${codes.length} QR tag(s)` : 'Quantity-only';
        qrEmpty.classList.toggle('d-none', codes.length > 0);
        qrEmpty.textContent = codes.length
            ? ''
            : 'No QR labels were attached to this adjustment; the stock quantity was adjusted without individual QR tags.';

        codes.forEach(code => {
            const column = document.createElement('div');
            column.className = 'col-6 col-sm-4 col-md-3 col-lg-2';
            const card = document.createElement('div');
            card.className = 'border rounded p-2 text-center h-100';
            const qrBox = document.createElement('div');
            qrBox.className = 'd-flex justify-content-center mb-2';
            const codeLabel = document.createElement('div');
            codeLabel.className = 'small font-monospace fw-semibold text-break';
            codeLabel.textContent = code;
            card.append(qrBox, codeLabel);
            column.appendChild(card);
            qrList.appendChild(column);

            if (typeof QRCode !== 'undefined') {
                new QRCode(qrBox, {
                    text: code,
                    width: 84,
                    height: 84,
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
    }

    function populateDetails(adjustment) {
        document.getElementById('detailAdjustmentNumber').textContent = adjustment.adjustment_number || '';
        document.getElementById('detailCreatedAt').textContent = `Created ${formatDate(adjustment.created_at)}`;
        const type = document.getElementById('detailAdjustmentType');
        type.textContent = adjustment.adjustment_type;
        type.className = `badge ${adjustment.adjustment_type === 'INCREASE' ? 'bg-success' : 'bg-danger'}`;
        const status = document.getElementById('detailAdjustmentStatus');
        status.textContent = adjustment.status;
        status.className = `badge ${adjustment.status === 'APPROVED' ? 'bg-success' : (adjustment.status === 'PENDING' ? 'bg-warning text-dark' : 'bg-danger')}`;
        document.getElementById('detailWarehouse').textContent = adjustment.warehouse_name || '';
        document.getElementById('detailProduct').textContent = adjustment.product_name || '';
        document.getElementById('detailProductVariant').textContent = `${adjustment.sku || ''} · ${adjustment.size_name || 'Free'} / ${adjustment.color_name || 'Std'}`;
        const sign = adjustment.adjustment_type === 'INCREASE' ? '+' : '−';
        document.getElementById('detailQuantity').textContent = `${sign}${Number(adjustment.quantity).toLocaleString()} pcs`;
        document.getElementById('detailRequestedBy').textContent = adjustment.requested_by_name || 'Staff';
        document.getElementById('detailApprovedBy').textContent = adjustment.approved_by_name
            ? `Approved by ${adjustment.approved_by_name}${adjustment.approved_at ? ` · ${formatDate(adjustment.approved_at)}` : ''}`
            : '';
        document.getElementById('detailReason').textContent = adjustment.reason || '';
        document.getElementById('detailNotes').textContent = adjustment.notes || '—';
        document.getElementById('detailNotesRow').classList.toggle('d-none', !adjustment.notes);
        renderQrCodes(adjustment);
        loading.classList.add('d-none');
        detailsError.classList.add('d-none');
        detailsContent.classList.remove('d-none');
    }

    document.querySelectorAll('.view-adjustment-btn').forEach(button => {
        button.addEventListener('click', async () => {
            detailsModalElement.querySelector('.modal-title').textContent = 'Stock Adjustment Details';
            detailsContent.classList.add('d-none');
            detailsError.classList.add('d-none');
            loading.classList.remove('d-none');
            detailsModal.show();
            button.disabled = true;
            try {
                populateDetails(await fetchAdjustment(button.dataset.adjustmentId));
            } catch (error) {
                showDetailsError(error.message || 'Unable to load adjustment details.');
            } finally {
                button.disabled = false;
            }
        });
    });

    if (editForm && editModal) {
        document.querySelectorAll('.edit-adjustment-btn').forEach(button => {
            button.addEventListener('click', async () => {
                editSaveButton.disabled = true;
                editError.classList.add('d-none');
                editModal.show();
                button.disabled = true;
                try {
                    const adjustment = await fetchAdjustment(button.dataset.adjustmentId);
                    document.getElementById('editAdjustmentReason').value = adjustment.reason || '';
                    document.getElementById('editAdjustmentNotes').value = adjustment.notes || '';
                    editForm.action = `${editForm.dataset.metadataUrl}/${encodeURIComponent(adjustment.id)}/metadata`;
                    editForm.dataset.loaded = 'true';
                    editSaveButton.disabled = false;
                } catch (error) {
                    editError.textContent = error.message || 'Unable to load adjustment details.';
                    editError.classList.remove('d-none');
                } finally {
                    button.disabled = false;
                }
            });
        });

        editForm.addEventListener('submit', event => {
            if (editForm.dataset.loaded !== 'true') {
                event.preventDefault();
                editError.textContent = 'Adjustment details have not loaded. Close this dialog and try again.';
                editError.classList.remove('d-none');
            }
        });

        editModalElement.addEventListener('hidden.bs.modal', () => {
            editForm.dataset.loaded = 'false';
            editForm.action = '#';
            editSaveButton.disabled = true;
            editError.classList.add('d-none');
        });
    }
})();
