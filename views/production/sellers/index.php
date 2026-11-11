<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-truck-flatbed me-2 text-primary"></i>Production Suppliers</h2>
        </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('sellers', 'create')): ?>
            <a href="<?= url('production/sellers/create') ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Supplier</a>
        <?php endif; ?>
    </div>
</div>

<div class="card border shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('production/sellers') ?>" class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-6">
                <label class="form-label" for="supplierSearch">Search supplier details</label>
                <input type="search" class="form-control" id="supplierSearch" name="search" value="<?= e($search) ?>" placeholder="Name, company, phone, address or GSTIN" oninput="filterSuppliersLive(this.value)">
            </div>
            <div class="col-lg-3 col-md-3">
                <label class="form-label" for="supplierCity">City</label>
                <select class="form-select" id="supplierCity" name="city">
                    <option value="">All cities</option>
                    <?php foreach ($cities as $cityOption): ?>
                        <?php if (!empty($cityOption['city'])): ?>
                            <option value="<?= e($cityOption['city']) ?>" <?= $city === $cityOption['city'] ? 'selected' : '' ?>><?= e($cityOption['city']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
          
            <div class="col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Filter</button>
                <?php if ($search !== '' || $city !== ''): ?>
                    <a href="<?= url('production/sellers') ?>" class="btn btn-outline-secondary" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card border shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-person-vcard me-2 text-primary"></i>Supplier Profiles</h5>
        <span class="badge bg-primary-subtle text-primary"><?= number_format(count($sellers)) ?> suppliers</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Supplier / Contact</th><th>Company</th><th>Contact Details</th><th>Location</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($sellers)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        No suppliers match these filters.
                        <?php if (hasPermission('sellers', 'create')): ?>
                            <div class="mt-3"><a href="<?= url('production/sellers/create') ?>" class="btn btn-sm btn-primary">Add Supplier</a></div>
                        <?php endif; ?>
                    </td></tr>
                <?php else: foreach ($sellers as $seller): ?>
                    <tr class="seller-row" data-search="<?= strtolower(e($seller['name'] . ' ' . ($seller['company'] ?? '') . ' ' . ($seller['phone'] ?? '') . ' ' . ($seller['email'] ?? '') . ' ' . ($seller['city'] ?? '') . ' ' . ($seller['state'] ?? '') . ' ' . ($seller['gst_number'] ?? ''))) ?>">
                        <td class="fw-semibold"><?= e($seller['name']) ?></td>
                        <td><?= e($seller['company'] ?: '—') ?></td>
                        <td>
                            <div><?= e($seller['phone']) ?></div>
                            <?php if (!empty($seller['email'])): ?><small class="text-muted"><?= e($seller['email']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?= e($seller['city'] ?: '—') ?><?= !empty($seller['state']) ? ', ' . e($seller['state']) : '' ?>
                            <?php if (!empty($seller['gst_number'])): ?><small class="d-block text-muted">GSTIN: <?= e($seller['gst_number']) ?></small><?php endif; ?>
                        </td>
                        
                        <td class="text-end text-nowrap">
                            <?php if (hasPermission('sellers', 'view')): ?>
                                <a href="<?= url('production/sellers/view/' . $seller['id']) ?>" class="btn btn-sm btn-outline-secondary" title="View supplier"><i class="bi bi-eye"></i></a>
                            <?php endif; ?>
                            <?php if (hasPermission('sellers', 'edit')): ?>
                                <a href="<?= url('production/sellers/edit/' . $seller['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit supplier"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                            <?php if (hasPermission('sellers', 'delete')): ?>
                                <a href="<?= url('production/sellers/delete/' . $seller['id']) ?>" class="btn btn-sm btn-outline-danger" title="Delete supplier" onclick="event.preventDefault(); openDeleteSupplierModal(<?= (int)$seller['id'] ?>, '<?= e(addslashes($seller['name'])) ?>', '<?= e(addslashes($seller['company'] ?? '')) ?>', '<?= e(addslashes($seller['phone'] ?? '')) ?>')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Delete Supplier Confirmation Popup -->
<div class="modal fade" id="deleteSupplierModal" tabindex="-1" aria-labelledby="deleteSupplierModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 pb-0 pt-3 px-3 justify-content-end">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pt-0 pb-3 text-center">
                <div class="mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle" id="modalDelIconWrapper" style="width: 72px; height: 72px; background: #fee2e2; color: #dc2626; border: 4px solid #fecaca; transition: all 0.3s ease;">
                    <i class="bi bi-trash3-fill" id="modalDelIcon" style="font-size: 1.85rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1" id="deleteSupplierModalLabel">Delete Supplier?</h4>
                <p class="text-muted small mb-3" id="modalDelSubtext">Checking connected records across modules...</p>

                <div class="p-3 bg-light rounded-3 border text-start mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small">Supplier / Contact:</span>
                        <strong class="text-dark" id="modalDelSupplierName">—</strong>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-1" id="modalDelCompanyRow">
                        <span class="text-muted small">Company / Mill:</span>
                        <span class="text-dark fw-semibold" id="modalDelSupplierCompany">—</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between" id="modalDelPhoneRow">
                        <span class="text-muted small">Contact Phone:</span>
                        <span class="text-muted font-monospace small" id="modalDelSupplierPhone">—</span>
                    </div>
                </div>

                <!-- Dynamic Dependency / Warning Box -->
                <div id="modalDelNoticeBox" class="alert alert-info border-info-subtle py-2.5 px-3 small text-start mb-0">
                    <div class="d-flex align-items-center gap-2">
                        <div class="spinner-border spinner-border-sm text-primary flex-shrink-0" role="status"></div>
                        <div id="modalDelNoticeText">Checking if supplier has purchases, orders or catalog products...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary px-3 py-1.5 fw-semibold" data-bs-dismiss="modal" id="btnModalCancel">
                    Cancel
                </button>
                <a href="#" id="btnModalViewProfile" class="btn btn-outline-primary px-3 py-1.5 fw-semibold" style="display: none;">
                    <i class="bi bi-eye me-1"></i>View Profile
                </a>
                <form id="deleteSupplierForm" method="POST" action="" class="m-0" style="display: none;">
                    <?= CSRF::field() ?>
                    <button type="submit" class="btn btn-danger px-3.5 py-1.5 fw-semibold shadow-sm" id="btnConfirmDeleteSupplier">
                        <i class="bi bi-trash3 me-1"></i>Yes, Delete Supplier
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openDeleteSupplierModal(id, name, company, phone) {
    const form = document.getElementById('deleteSupplierForm');
    const nameEl = document.getElementById('modalDelSupplierName');
    const companyEl = document.getElementById('modalDelSupplierCompany');
    const companyRow = document.getElementById('modalDelCompanyRow');
    const phoneEl = document.getElementById('modalDelSupplierPhone');
    const phoneRow = document.getElementById('modalDelPhoneRow');
    const labelEl = document.getElementById('deleteSupplierModalLabel');
    const subtextEl = document.getElementById('modalDelSubtext');
    const iconWrapper = document.getElementById('modalDelIconWrapper');
    const iconEl = document.getElementById('modalDelIcon');
    const noticeBox = document.getElementById('modalDelNoticeBox');
    const noticeText = document.getElementById('modalDelNoticeText');
    const viewBtn = document.getElementById('btnModalViewProfile');
    const cancelBtn = document.getElementById('btnModalCancel');

    if (form) {
        form.action = '<?= url("production/sellers/delete/") ?>' + id;
        form.style.display = 'none';
    }
    if (viewBtn) {
        viewBtn.href = '<?= url("production/sellers/view/") ?>' + id;
        viewBtn.style.display = 'none';
    }
    if (cancelBtn) cancelBtn.textContent = 'Cancel';

    if (nameEl) nameEl.textContent = name || 'Supplier';
    if (companyEl) {
        companyEl.textContent = company || '—';
        if (companyRow) companyRow.style.display = company ? 'flex' : 'none';
    }
    if (phoneEl) {
        phoneEl.textContent = phone || '—';
        if (phoneRow) phoneRow.style.display = phone ? 'flex' : 'none';
    }

    // Reset to checking state
    if (labelEl) labelEl.textContent = 'Checking Dependencies...';
    if (subtextEl) subtextEl.textContent = 'Verifying if supplier has active records in other modules...';
    if (iconWrapper) {
        iconWrapper.style.background = '#e0f2fe';
        iconWrapper.style.color = '#0284c7';
        iconWrapper.style.border = '4px solid #bae6fd';
    }
    if (iconEl) iconEl.className = 'bi bi-arrow-repeat';
    if (noticeBox) {
        noticeBox.className = 'alert alert-info border-info-subtle py-2.5 px-3 small text-start mb-0';
        noticeBox.innerHTML = '<div class="d-flex align-items-center gap-2"><div class="spinner-border spinner-border-sm text-primary flex-shrink-0" role="status"></div><div>Checking if supplier has purchases, orders, fabric rolls or products...</div></div>';
    }

    const modalEl = document.getElementById('deleteSupplierModal');
    let modalInstance = null;
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();
    }

    // Perform check via AJAX
    fetch('<?= url("api/production/sellers/check-deletable") ?>?id=' + id)
        .then(res => res.json())
        .then(data => {
            if (!data.can_delete) {
                // ACTION BLOCKED: has connected records
                if (labelEl) labelEl.textContent = 'Cannot Delete Supplier';
                if (subtextEl) subtextEl.textContent = 'Active dependencies detected in other modules.';
                if (iconWrapper) {
                    iconWrapper.style.background = '#fef3c7';
                    iconWrapper.style.color = '#d97706';
                    iconWrapper.style.border = '4px solid #fde68a';
                }
                if (iconEl) iconEl.className = 'bi bi-shield-fill-x';

                let reasonsHtml = '<strong class="d-block text-dark mb-1">Cannot be deleted because linked to:</strong><ul class="mb-0 ps-3 small">';
                if (Array.isArray(data.reasons) && data.reasons.length > 0) {
                    data.reasons.forEach(r => {
                        reasonsHtml += `<li class="text-dark fw-medium mb-0.5">${r}</li>`;
                    });
                } else {
                    reasonsHtml += `<li class="text-dark">${data.message || 'Connected records exist'}</li>`;
                }
                reasonsHtml += '</ul><div class="mt-2 text-muted" style="font-size: 0.8rem;">Please remove or reassign these linked records before deleting.</div>';

                if (noticeBox) {
                    noticeBox.className = 'alert alert-warning border-warning-subtle py-2.5 px-3 small text-start mb-0';
                    noticeBox.innerHTML = `<div class="d-flex align-items-start gap-2"><i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0 mt-0.5"></i><div>${reasonsHtml}</div></div>`;
                }

                if (form) form.style.display = 'none';
                if (viewBtn) viewBtn.style.display = 'inline-block';
                if (cancelBtn) cancelBtn.textContent = 'Close';
            } else {
                if (labelEl) labelEl.textContent = 'Delete Supplier?';
                if (subtextEl) subtextEl.textContent = 'Are you sure you want to remove this supplier from active production suppliers?';
                if (iconWrapper) {
                    iconWrapper.style.background = '#fee2e2';
                    iconWrapper.style.color = '#dc2626';
                    iconWrapper.style.border = '4px solid #fecaca';
                }
                if (iconEl) iconEl.className = 'bi bi-trash3-fill';

                if (noticeBox) {
                    noticeBox.className = 'alert alert-warning border-warning-subtle py-2 px-3 small text-start mb-0 d-flex align-items-center gap-2';
                    noticeBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-warning flex-shrink-0 fs-5"></i><div>This action cannot be undone here. Any linked historical data will be safely preserved.</div>';
                }

                if (form) form.style.display = 'inline-block';
                if (viewBtn) viewBtn.style.display = 'none';
                if (cancelBtn) cancelBtn.textContent = 'Cancel';
            }
        })
        .catch(err => {
            // Fallback: allow delete submit if check request fails
            if (labelEl) labelEl.textContent = 'Delete Supplier?';
            if (subtextEl) subtextEl.textContent = 'Are you sure you want to remove this supplier from active production suppliers?';
            if (noticeBox) {
                noticeBox.className = 'alert alert-warning border-warning-subtle py-2 px-3 small text-start mb-0 d-flex align-items-center gap-2';
                noticeBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-warning flex-shrink-0 fs-5"></i><div>Any linked historical data will be safely preserved.</div>';
            }
            if (form) form.style.display = 'inline-block';
        });
}

function filterSuppliersLive(query) {
    const q = query.toLowerCase().trim();
    const rows = document.querySelectorAll('tbody .seller-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || (r.textContent || '').toLowerCase();
        r.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}
</script>
