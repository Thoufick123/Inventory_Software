<div class="page-header mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><i class="bi bi-patch-check me-2 text-danger"></i>Initialize QC Inspection Batch</h2>
        <span class="text-muted">Select completed labeling batch or stitching batch to begin quality control inspection</span>
    </div>
    <a href="<?= url('production/qc') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to QC List
    </a>
</div>

<form method="POST" action="<?= url('production/qc/store') ?>">
    <?= CSRF::field() ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>QC Batch Info</h5>
                </div>
                <div class="card-body p-4">
                    <!-- Source Selection: Label Batch or Stitching Batch -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Source Batch <span class="text-danger">*</span></label>
                        <select name="label_batch_id" id="labelBatchSelect" class="form-select form-select-lg border-primary mb-2" onchange="if(this.value) window.location.href='<?= url('production/qc/create?label_batch_id=') ?>' + this.value">
                            <option value="">-- Choose Label Batch --</option>
                            <?php foreach ($labelBatches as $lb): ?>
                                <option value="<?= $lb['id'] ?>" <?= ($selectedLabel && $selectedLabel['id'] == $lb['id']) ? 'selected' : '' ?>>
                                    <?= e($lb['batch_number']) ?> (<?= e($lb['program_number'] ?? '') ?> — <?= e($lb['product_name'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="text-center text-muted small my-2 fw-semibold">— OR SELECT STITCHING BATCH —</div>

                        <select name="stitching_batch_id" id="stitchingBatchSelect" class="form-select border-secondary" onchange="if(this.value) window.location.href='<?= url('production/qc/create?stitching_batch_id=') ?>' + this.value">
                            <option value="">-- Choose Stitching Batch --</option>
                            <?php foreach ($stitchingBatches as $sb): ?>
                                <option value="<?= $sb['id'] ?>" <?= ($selectedStitching && $selectedStitching['id'] == $sb['id']) ? 'selected' : '' ?>>
                                    Batch #<?= $sb['id'] ?> (<?= e($sb['batch_number']) ?> &bull; <?= e($sb['product_name'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($labelBatches) && empty($stitchingBatches) && !$selectedLabel && !$selectedStitching): ?>
                            <div class="alert alert-warning py-2 px-3 small mt-2 mb-0">
                                <i class="bi bi-info-circle me-1"></i> All available label and stitching batches have already undergone QC inspection.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold mb-0" for="qcInspectorSelect">
                                <i class="bi bi-person-badge text-primary me-1"></i>QC Inspector
                            </label>
                            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold" id="btnToggleAddInspector" onclick="toggleQuickAddInspector()">
                                <i class="bi bi-person-plus-fill me-1"></i>+ Add New Inspector
                            </button>
                        </div>
                        <select name="inspector_id" id="qcInspectorSelect" class="form-select" onchange="handleInspectorSelectChange(this)">
                            <option value="">Select Inspector Name</option>
                            <option value="__NEW__" class="fw-bold text-primary bg-light">+ Add New QC Inspector...</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Inline Quick Add QC Inspector Box -->
                        <div id="quickAddInspectorBox" class="mt-2 p-3 rounded-3 border border-primary bg-light shadow-sm" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-primary-subtle">
                                <span class="fw-bold small text-primary">
                                    <i class="bi bi-person-plus-fill me-1"></i>Quick Add New QC Inspector
                                </span>
                                <button type="button" class="btn-close btn-sm" onclick="closeQuickAddInspector()" aria-label="Close" style="font-size: 10px;"></button>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-dark mb-1">Inspector Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="quickInspectorName" class="form-control form-control-sm" placeholder="e.g. Ramesh Kumar / Inspector Name" autocomplete="off" onkeyup="if(event.key==='Enter') saveQuickQcInspector()">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-dark mb-1">Phone Number (Optional)</label>
                                    <input type="text" id="quickInspectorPhone" class="form-control form-control-sm" placeholder="e.g. 9876543210" maxlength="15" autocomplete="off" onkeyup="if(event.key==='Enter') saveQuickQcInspector()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-dark mb-1">Emp / ID Code (Optional)</label>
                                    <input type="text" id="quickInspectorEmpId" class="form-control form-control-sm" placeholder="Auto-generated" autocomplete="off" onkeyup="if(event.key==='Enter') saveQuickQcInspector()">
                                </div>
                            </div>
                            <div class="d-flex justify-content-end align-items-center gap-2 mt-3 pt-2 border-top">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeQuickAddInspector()">Cancel</button>
                                <button type="button" class="btn btn-primary btn-sm fw-semibold" id="btnSaveQuickInspector" onclick="saveQuickQcInspector()">
                                    <i class="bi bi-check-circle me-1"></i> Save &amp; Select
                                </button>
                            </div>
                            <div id="quickInspectorAlert" class="small mt-2" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">General Inspection Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Fabric, seam standards, or inspection batch notes..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom flex-wrap gap-2">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="bi bi-grid-3x3 me-2 text-primary"></i>Items Ready for Inspection</h5>
                    <?php if (!empty($distinctCatalogs)): ?>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="badge bg-light text-dark border"><i class="bi bi-diagram-3 me-1 text-primary"></i><?= count($distinctCatalogs) ?> Catalog(s)</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($distinctCatalogs) && count($distinctCatalogs) > 1): ?>
                    <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center gap-1 flex-wrap">
                        <span class="small fw-semibold text-muted me-2"><i class="bi bi-funnel me-1"></i>Filter:</span>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-primary active btn-qc-cat-filter" onclick="filterQCCreateTable('ALL', this)">
                                All Catalogs (<?= count($items) ?>)
                            </button>
                            <?php foreach ($distinctCatalogs as $c): ?>
                                <button type="button" class="btn btn-outline-primary btn-qc-cat-filter text-truncate" style="max-width: 250px;" onclick="filterQCCreateTable('<?= e(addslashes($c['name'])) ?>', this)">
                                    <?= e($c['name']) ?> (<?= $c['total_qty'] ?> pcs)
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card-body p-0">
                    <?php if (!$selectedStitching && !$selectedLabel): ?>
                        <div class="p-5 text-center text-muted">
                            <div class="mb-3">
                                <div class="d-inline-flex p-3 rounded-circle bg-light">
                                    <i class="bi bi-qr-code-scan fs-1 text-primary"></i>
                                </div>
                            </div>
                            <h5 class="fw-bold text-dark">Select a Label or Stitching Batch</h5>
                            <p class="text-muted mb-0">Choose a batch on the left to initialize QC inspection for all sizes and colors.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="qcCreateItemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Catalog &amp; Variant Attribute (Size / Color)</th>
                                        <th style="width: 130px;" class="text-center">Total Quantity</th>
                                        <th style="width: 140px;" class="text-center text-secondary">Initial Passed (pcs)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $idx => $it): 
                                        $catHierarchy = $it['catalog_hierarchy'] ?? 'General Apparel';
                                    ?>
                                        <tr class="qc-create-row" data-catalog="<?= e($catHierarchy) ?>">
                                            <td>
                                                <input type="hidden" name="items[<?= $idx ?>][variant_id]" value="<?= e($it['variant_id'] ?? '') ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][category_id]" value="<?= e($it['category_id'] ?? '') ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][subcategory_id]" value="<?= e($it['subcategory_id'] ?? '') ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][sub_subcategory_id]" value="<?= e($it['sub_subcategory_id'] ?? '') ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][catalog_hierarchy]" value="<?= e($catHierarchy) ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][size_name]" value="<?= e($it['size_name']) ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][color_name]" value="<?= e($it['color_name']) ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][base_price]" value="<?= e($it['base_price']) ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][discount_price]" value="<?= e($it['discount_price']) ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][start_seq]" value="<?= e($it['start_seq'] ?? '') ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][end_seq]" value="<?= e($it['end_seq'] ?? '') ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][start_code]" value="<?= e($it['start_code'] ?? '') ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][end_code]" value="<?= e($it['end_code'] ?? '') ?>">
                                                
                                                <div class="mb-1">
                                                    <span class="badge bg-primary-subtle text-primary border text-wrap text-start" style="font-size: 0.72rem; line-height: 1.2;">
                                                        <i class="bi bi-diagram-3-fill me-1 text-primary"></i><?= e($catHierarchy) ?>
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><?= e($it['size_name']) ?></span>
                                                    <span class="badge bg-dark-subtle text-dark border px-2 py-1"><?= e($it['color_name']) ?></span>
                                                    <code class="text-muted fs-8 ms-1"><?= e($it['sku']) ?></code>
                                                    <?php if (!empty($it['start_code'])): ?>
                                                        <span class="badge bg-dark text-white font-monospace ms-1" style="font-size: 0.75rem;">
                                                            <i class="bi bi-tag-fill me-1 text-warning"></i><?= e($it['start_code']) ?> &ndash; <?= e($it['end_code']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" name="items[<?= $idx ?>][inspected_qty]" class="form-control form-control-sm text-center fw-bold bg-light" value="<?= (int)$it['stitched_qty'] ?>" readonly>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" name="items[<?= $idx ?>][passed_qty]" class="form-control form-control-sm text-center text-secondary fw-bold bg-light" min="0" value="0" placeholder="0" readonly>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($selectedStitching || $selectedLabel): ?>
                    <div class="card-footer bg-white p-3 d-flex justify-content-between border-top">
                        <a href="<?= url('production/qc') ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="bi bi-play-circle me-1"></i> Start QC Inspection
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<script>
function filterQCCreateTable(targetCat, btn) {
    document.querySelectorAll('.btn-qc-cat-filter').forEach(b => {
        b.classList.remove('btn-primary', 'active');
        b.classList.add('btn-outline-primary');
    });
    if (btn) {
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-primary', 'active');
    }
    document.querySelectorAll('#qcCreateItemsTable tr.qc-create-row').forEach(tr => {
        const cat = tr.getAttribute('data-catalog') || '';
        if (targetCat === 'ALL' || cat === targetCat) {
            tr.style.display = '';
        } else {
            tr.style.display = 'none';
        }
    });
}

// Quick Add QC Inspector Handler
let previousInspectorValue = '';

function handleInspectorSelectChange(selectEl) {
    if (selectEl.value === '__NEW__') {
        openQuickAddInspector();
        selectEl.value = previousInspectorValue;
    } else {
        previousInspectorValue = selectEl.value;
    }
}

function toggleQuickAddInspector() {
    const box = document.getElementById('quickAddInspectorBox');
    if (box.style.display === 'none' || box.style.display === '') {
        openQuickAddInspector();
    } else {
        closeQuickAddInspector();
    }
}

function openQuickAddInspector() {
    const box = document.getElementById('quickAddInspectorBox');
    box.style.display = 'block';
    const nameInput = document.getElementById('quickInspectorName');
    nameInput.focus();
}

function closeQuickAddInspector() {
    const box = document.getElementById('quickAddInspectorBox');
    box.style.display = 'none';
    const alertEl = document.getElementById('quickInspectorAlert');
    alertEl.style.display = 'none';
    alertEl.className = 'small mt-2';
    alertEl.textContent = '';
}

function saveQuickQcInspector() {
    const nameInput = document.getElementById('quickInspectorName');
    const phoneInput = document.getElementById('quickInspectorPhone');
    const empInput = document.getElementById('quickInspectorEmpId');
    const alertEl = document.getElementById('quickInspectorAlert');
    const saveBtn = document.getElementById('btnSaveQuickInspector');
    const selectEl = document.getElementById('qcInspectorSelect');

    const fullName = (nameInput.value || '').trim();
    const phone = (phoneInput.value || '').trim();
    const empId = (empInput.value || '').trim();

    if (!fullName || fullName.length < 2) {
        alertEl.className = 'small mt-2 text-danger fw-semibold';
        alertEl.textContent = 'Please enter inspector full name (at least 2 characters).';
        alertEl.style.display = 'block';
        nameInput.focus();
        return;
    }

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving...';
    alertEl.style.display = 'none';

    const formData = new FormData();
    formData.append('full_name', fullName);
    formData.append('phone', phone);
    formData.append('emp_id', empId);

    // Append CSRF token if present
    const csrfInput = document.querySelector('input[name="_csrf_token"]');
    if (csrfInput) {
        formData.append('_csrf_token', csrfInput.value);
    }

    fetch('<?= url('production/qc/ajax-add-inspector') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save &amp; Select';

        if (data.success && data.inspector) {
            // Dynamically add option to select
            const newOpt = document.createElement('option');
            newOpt.value = data.inspector.id;
            newOpt.textContent = data.inspector.full_name;
            newOpt.selected = true;

            selectEl.appendChild(newOpt);
            selectEl.value = data.inspector.id;
            previousInspectorValue = data.inspector.id;

            // Reset inputs
            nameInput.value = '';
            phoneInput.value = '';
            empInput.value = '';

            // Brief success feedback
            alertEl.className = 'small mt-2 text-success fw-semibold';
            alertEl.textContent = 'QC Inspector "' + data.inspector.full_name + '" added & selected!';
            alertEl.style.display = 'block';

            setTimeout(() => {
                closeQuickAddInspector();
            }, 1200);
        } else {
            alertEl.className = 'small mt-2 text-danger fw-semibold';
            alertEl.textContent = data.message || 'Failed to save inspector.';
            alertEl.style.display = 'block';
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save &amp; Select';
        alertEl.className = 'small mt-2 text-danger fw-semibold';
        alertEl.textContent = 'Network or server error while adding inspector.';
        alertEl.style.display = 'block';
        console.error('Error adding QC inspector:', err);
    });
}
</script>
