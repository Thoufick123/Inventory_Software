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
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete supplier" onclick="openDeleteSupplierModal(<?= (int)$seller['id'] ?>, '<?= e(addslashes($seller['name'])) ?>', '<?= e(addslashes($seller['company'] ?? '')) ?>', '<?= e(addslashes($seller['phone'] ?? '')) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
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
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 pb-0 pt-3 px-3 justify-content-end">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pt-0 pb-3 text-center">
                <div class="mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width: 70px; height: 70px; background: #fee2e2; color: #dc2626; border: 4px solid #fecaca;">
                    <i class="bi bi-trash3-fill" style="font-size: 1.85rem;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1" id="deleteSupplierModalLabel">Delete Supplier?</h4>
                <p class="text-muted small mb-3">Are you sure you want to remove this supplier profile?</p>

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

                <div class="alert alert-warning border-warning-subtle py-2 px-3 small text-start mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning flex-shrink-0 fs-5"></i>
                    <div>This action cannot be undone. Any linked historical data will be safely preserved.</div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary px-3 py-1.5 fw-semibold" data-bs-dismiss="modal">
                    Cancel
                </button>
                <form id="deleteSupplierForm" method="POST" action="" class="m-0">
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

    if (form) {
        form.action = '<?= url("production/sellers/delete/") ?>' + id;
    }
    if (nameEl) nameEl.textContent = name || 'Supplier';
    if (companyEl) {
        companyEl.textContent = company || '—';
        if (companyRow) companyRow.style.display = company ? 'flex' : 'none';
    }
    if (phoneEl) {
        phoneEl.textContent = phone || '—';
        if (phoneRow) phoneRow.style.display = phone ? 'flex' : 'none';
    }

    const modalEl = document.getElementById('deleteSupplierModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
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
