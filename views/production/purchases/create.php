<div class="page-header mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><i class="bi bi-bag-plus me-2 text-primary"></i>Record Production Purchase</h2>
        </div>
    <a href="<?= url('production/purchases') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Purchases</a>
</div>

<form method="POST" action="<?= url('production/purchases/store') ?>" enctype="multipart/form-data" id="productionPurchaseForm">
    <?= CSRF::field() ?>
    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2 text-primary"></i>Purchase &amp; Supplier Details</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label required" for="purchaseSeller">Supplier *</label>
                    <select class="d-none" name="seller_id" id="purchaseSeller" required>
                        <option value="">-- Select registered supplier --</option>
                        <?php foreach ($sellers as $seller): ?>
                            <option value="<?= (int)$seller['id'] ?>"
                                data-supplier-name="<?= e($seller['name'] ?? '') ?>"
                                data-supplier-company="<?= e($seller['company'] ?? '') ?>"
                                data-supplier-phone="<?= e($seller['phone'] ?? '') ?>"
                                data-supplier-email="<?= e($seller['email'] ?? '') ?>"
                                data-supplier-gst="<?= e($seller['gst_number'] ?? '') ?>"
                                data-supplier-city="<?= e($seller['city'] ?? '') ?>"
                                data-supplier-state="<?= e($seller['state'] ?? '') ?>"
                                data-supplier-address="<?= e($seller['address'] ?? '') ?>"><?= e($seller['company'] ?: $seller['name']) ?><?= $seller['company'] ? ' — ' . e($seller['name']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Searchable Supplier Dropdown UI -->
                    <div class="dropdown w-100" id="supplierSearchDropdown">
                        <button type="button" class="form-select text-start d-flex justify-content-between align-items-center" id="supplierDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                            <span id="supplierDropdownSelectedText" class="text-truncate">-- Select registered supplier --</span>
                        </button>
                        <div class="dropdown-menu shadow w-100 p-2" id="supplierDropdownMenu" style="max-height: 360px;">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" id="supplierSearchInput" placeholder="Search supplier by name, company, phone..." autocomplete="off">
                            </div>
                            <div class="overflow-auto" id="supplierListContainer" style="max-height: 250px;">
                                <button type="button" class="dropdown-item py-2 border-bottom supplier-opt-item active" data-id="" data-display="-- Select registered supplier --">
                                    <span class="text-muted">-- Select registered supplier --</span>
                                </button>
                                <?php foreach ($sellers as $seller): ?>
                                    <button type="button" class="dropdown-item py-2 border-bottom supplier-opt-item"
                                            data-id="<?= (int)$seller['id'] ?>"
                                            data-display="<?= e($seller['company'] ?: $seller['name']) ?><?= $seller['company'] ? ' — ' . e($seller['name']) : '' ?>"
                                            data-search="<?= e(strtolower(($seller['company'] ?? '') . ' ' . ($seller['name'] ?? '') . ' ' . ($seller['phone'] ?? '') . ' ' . ($seller['city'] ?? '') . ' ' . ($seller['gst_number'] ?? ''))) ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-dark"><?= e($seller['company'] ?: $seller['name']) ?></strong>
                                            <?php if (!empty($seller['city'])): ?>
                                                <span class="badge bg-light text-secondary border"><?= e($seller['city']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted d-flex justify-content-between flex-wrap gap-1">
                                            <span><i class="bi bi-person me-1"></i><?= e($seller['name']) ?></span>
                                            <span><i class="bi bi-telephone me-1"></i><?= e($seller['phone']) ?></span>
                                        </div>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center text-muted py-3 small d-none" id="supplierNoResults">
                                <i class="bi bi-inbox d-block fs-5 mb-1"></i> No suppliers match your search.
                            </div>
                        </div>
                    </div>
                    <?php if (empty($sellers)): ?><small class="text-danger d-block mt-1">Add a supplier profile before recording a purchase.</small><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="purchaseDate">Purchase Date *</label>
                    <input type="date" class="form-control" name="purchase_date" id="purchaseDate" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="invoiceNumber">Invoice / Bill Number</label>
                    <input type="text" class="form-control" name="invoice_number" id="invoiceNumber" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="purchaseDocument">Tax Invoice / Bill Attachment (Optional)</label>
                    <input type="file" class="form-control" name="document" id="purchaseDocument" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-1">
                        <small class="text-muted">JPG, PNG, WEBP or PDF up to 5 MB.</small>
                        <div id="documentFilePreviewContainer" class="d-none align-items-center gap-1">
                            <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1 py-1 px-2">
                                <i class="bi bi-file-earmark-check"></i>
                                <span id="docPreviewFilename" class="text-truncate" style="max-width: 150px;">invoice.pdf</span>
                            </span>
                            <button type="button" class="btn btn-xs btn-outline-primary" id="btnViewSelectedDocument" onclick="viewSelectedDocumentFile()" title="View selected document">
                                <i class="bi bi-eye me-1"></i>View Selected File
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="clearSelectedDocumentFile()" title="Clear file">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                    <div id="documentImagePreview" class="d-none mt-2">
                        <img id="docPreviewImage" class="img-thumbnail" alt="Selected invoice image preview" style="max-width:100%;max-height:220px;object-fit:contain;">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="purchaseNotes">Purchase Notes</label>
                    <input type="text" class="form-control" name="notes" id="purchaseNotes" maxlength="500">
                </div>
                <div class="col-12 d-none" id="selectedSupplierDetails" aria-live="polite">
                    <div class="border rounded bg-light p-3">
                        <div class="fw-semibold mb-3"><i class="bi bi-person-vcard me-1 text-primary"></i>Selected Supplier Information</div>
                        <div class="row g-3">
                            <div class="col-md-4"><small class="text-muted d-block">Contact Person</small><span data-supplier-detail="name">—</span></div>
                            <div class="col-md-4"><small class="text-muted d-block">Company</small><span data-supplier-detail="company">—</span></div>
                            <div class="col-md-4"><small class="text-muted d-block">Phone</small><span data-supplier-detail="phone">—</span></div>
                            <div class="col-md-4"><small class="text-muted d-block">Email</small><span data-supplier-detail="email">—</span></div>
                            <div class="col-md-4"><small class="text-muted d-block">GSTIN / Tax ID</small><span data-supplier-detail="gst">—</span></div>
                            <div class="col-md-4"><small class="text-muted d-block">City / State</small><span data-supplier-detail="location">—</span></div>
                            <div class="col-12"><small class="text-muted d-block">Business Address</small><span data-supplier-detail="address">—</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-white py-3">
            <div class="row g-3 align-items-center">
                <div class="col">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-layers-half me-2 text-primary"></i>Fabric Roll &amp; Material Supply Details</h5>
                   </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-primary" id="addFabricRoll"><i class="bi bi-plus-circle me-1"></i>Add Fabric Roll</button>
                </div>
                <div class="col-12">
                    <label class="form-label required" for="fabricDescription">Fabric / Material Description *</label>
                    <input type="text" name="fabric_description" id="fabricDescription" class="form-control text-uppercase" maxlength="150" placeholder="E.G. 100% COMBED COTTON 30S SINGLE JERSEY (160 GSM)" list="materialTypeSuggestions">
                    <datalist id="materialTypeSuggestions">
                        <option value="100% COMBED COTTON 30S SINGLE JERSEY (160 GSM)">
                        <option value="100% COTTON 24S SINGLE JERSEY (180 GSM)">
                        <option value="95% COTTON 5% SPANDEX/LYCRA (190 GSM)">
                        <option value="100% COTTON FRENCH TERRY (240 GSM)">
                        <option value="100% COTTON 3-THREAD FLEECE (320 GSM)">
                        <option value="COTTON-POLYESTER BLEND 60/40 (180 GSM)">
                        <option value="100% COTTON PIQUE POLO KNIT (220 GSM)">
                        <option value="100% COTTON 2X2 RIB KNIT (220 GSM)">
                        <option value="DENIM TWILL 12OZ INDIGO">
                        <option value="RAYON / VISCOSE PRINTED FABRIC">
                    </datalist>
                    <small class="text-muted">Select a standard grade or enter a custom fabric name and GSM.</small>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2 flex-wrap gap-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-stack me-1 text-primary"></i>Individual Fabric Rolls (<span id="rollCountBadge">0</span>)</h6>
                <span class="text-muted small">Add each roll received on this supplier invoice.</span>
            </div>
            <div id="rollsContainer" class="d-flex flex-column gap-3 mb-4"></div>
            <div class="card border shadow-sm mb-4" id="rollDetailsTableContainer">
                <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                    <span class="fw-bold small"><i class="bi bi-table me-1 text-primary"></i>Fabric Rolls Inventory &amp; Details Table</span>
                    
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0" style="font-size:13px">
                        <thead class="table-light small text-uppercase">
                            <tr><th class="text-center">#</th><th>Photo</th><th>Roll Code</th><th>HSN</th><th>Color / Shade</th><th>Design / Pattern</th><th class="text-center">Designs</th><th class="text-end">Total RQ</th><th class="text-end">Weight</th><th class="text-end">Price</th><th>GST Type</th><th class="text-end">Tax</th><th class="text-end bg-success-subtle text-success">Total Value</th><th class="text-center">Status</th></tr>
                        </thead>
                        <tbody id="rollDetailsTableBody"><tr><td colspan="14" class="text-center text-muted py-4">Add fabric rolls to see their inventory details.</td></tr></tbody>
                        <tfoot id="rollDetailsTableFoot" class="table-light fw-bold"></tfoot>
                    </table>
                </div>
            </div>
            <div class="p-3 bg-primary-subtle rounded border d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div><strong class="text-primary"><i class="bi bi-calculator me-1"></i>Batch Metrics Summary</strong><small class="d-block text-muted">Totals are calculated from the rolls in this purchase.</small></div>
                <div class="d-flex flex-wrap gap-4">
                    <div><small class="text-muted d-block">Total Rolls</small><strong id="summaryTotalRolls">0 Rolls</strong></div>
                    <div><small class="text-muted d-block">Total RQ</small><strong id="summaryTotalRQ">0 RQ</strong></div>
                    <div><small class="text-muted d-block">Colors / Designs</small><strong id="summaryColorsDesigns">0 Colors • 0 Designs</strong></div>
                    <div><small class="text-muted d-block">Total Weight</small><strong id="summaryTotalWeight">0.00 kg</strong></div>
                    <div><small class="text-muted d-block">Total incl. Tax</small><strong id="summaryTotalPrice" class="text-success">₹0.00</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-success"></i>Other Garment Supplies</h5><small class="text-muted">Optional non-fabric products received on this invoice.</small></div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addSupply"><i class="bi bi-plus-circle me-1"></i>Add Supply</button>
        </div>
        <div class="card-body" id="suppliesContainer">
            <div id="emptySupplyMessage" class="small text-muted mb-3">No general supplies added.</div>
            <div id="supplyRows" class="d-flex flex-column gap-3 mb-4"></div>
            <div class="card border shadow-sm mt-3">
                <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                    <span class="fw-bold small"><i class="bi bi-table me-1 text-success"></i>Other Garment Supplies Details Table</span>
                    <span class="badge bg-secondary-subtle text-secondary border" id="supplyTableCountBadge">0 Items</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0" style="font-size:13px">
                        <thead class="table-light small text-uppercase">
                            <tr><th class="text-center">#</th><th>Item Name / Description</th><th>HSN</th><th class="text-end">Quantity</th><th>Unit</th><th class="text-end">Unit Price</th><th class="text-end">Base Value</th><th>GST Type</th><th class="text-end">Tax</th><th class="text-end bg-success-subtle text-success">Total Value</th></tr>
                        </thead>
                        <tbody id="supplyDetailsTableBody">
                            <tr><td colspan="10" class="text-center text-muted py-4">Add garment supplies to see their purchase details.</td></tr>
                        </tbody>
                        <tfoot id="supplyDetailsTableFoot" class="table-light fw-bold"></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span>Subtotal</span><strong id="purchaseSubtotal">₹0.00</strong>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span>Taxes</span><strong id="purchaseTaxes">₹0.00</strong>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
                <strong>Total Price</strong><strong class="fs-5 text-success" id="purchaseTotal">₹0.00</strong>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= url('production/purchases') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Purchase</button>
    </div>
</form>

<div class="modal fade" id="purchaseLabelsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-qr-code me-2 text-warning"></i>Fabric Roll Labels Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light"><div id="purchaseLabelCards" class="d-flex flex-wrap justify-content-center gap-3"></div></div>
            <div class="modal-footer"><span class="small text-muted me-auto" id="purchaseLabelCount"></span><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQuickAddHsn" tabindex="-1" aria-labelledby="modalQuickAddHsnLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fs-6 fw-bold" id="modalQuickAddHsnLabel">
                    <i class="bi bi-plus-circle me-1"></i> Add New HSN Code &amp; GST Rate
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">HSN Code <span class="text-danger">*</span></label>
                    <input type="text" id="quickHsnCodeInput" class="form-control text-uppercase fw-bold font-monospace" placeholder="e.g. 6206 or 61091000" maxlength="15" required>
                    <small class="text-muted" style="font-size:0.75rem;">Harmonized System of Nomenclature code for invoices</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">Commodity / Description <span class="text-danger">*</span></label>
                    <input type="text" id="quickHsnDescInput" class="form-control" placeholder="e.g. Cotton Woven Fabric / Sewing Thread / Buttons" maxlength="150" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">Default GST Rate (%) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" max="28" id="quickHsnGstInput" class="form-control fw-bold numeric-text-input" value="5.00" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted" style="font-size:0.75rem;">Standard garment slab: 5.00%, 12.00%, 18.00%</small>
                </div>
                <div id="quickHsnAlert" class="alert alert-danger py-2 small d-none mb-0"></div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm fw-semibold" id="btnSaveQuickHsn">
                    <i class="bi bi-check2-circle me-1"></i> Save &amp; Select HSN
                </button>
            </div>
        </div>
    </div>
</div>
<style>
.roll-label-card {
    width: 275px;
    min-height: 155px;
    background: #ffffff;
    border: 1.5px solid #0f172a;
    border-radius: 8px;
    padding: 10px 12px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    box-sizing: border-box;
}
.label-header {
    border-bottom: 1px dashed #cbd5e1;
    padding-bottom: 4px;
    margin-bottom: 6px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.company-tag {
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #1e293b;
    text-transform: uppercase;
    line-height: 1.2;
    max-width: 175px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.po-tag {
    font-size: 8px;
    font-weight: 700;
    color: #0284c7;
    font-family: monospace;
    background: #e0f2fe;
    padding: 1px 4px;
    border-radius: 3px;
}
.label-body {
    display: flex;
    align-items: center;
    gap: 10px;
}
.qr-box {
    width: 72px;
    height: 72px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    padding: 2px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
}
.qr-box img, .qr-box canvas {
    width: 100% !important;
    height: 100% !important;
}
.label-meta {
    flex-grow: 1;
    font-size: 9.5px;
    line-height: 1.35;
    min-width: 0;
}
.roll-badge-num {
    font-family: monospace;
    font-size: 11px;
    font-weight: 900;
    color: #0f172a;
    display: block;
    margin-bottom: 2px;
    letter-spacing: 0.5px;
}
.meta-line {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.meta-line strong {
    color: #0f172a;
}
.label-footer {
    border-top: 1px dashed #cbd5e1;
    padding-top: 4px;
    margin-top: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 8px;
    color: #64748b;
}
#supplierSearchDropdown .supplier-opt-item:hover,
#supplierSearchDropdown .supplier-opt-item:focus,
#supplierSearchDropdown .supplier-opt-item.active,
#supplierSearchDropdown .supplier-opt-item:active {
    background-color: #0d6efd !important;
    color: #ffffff !important;
}
#supplierSearchDropdown .supplier-opt-item:hover *,
#supplierSearchDropdown .supplier-opt-item:focus *,
#supplierSearchDropdown .supplier-opt-item.active *,
#supplierSearchDropdown .supplier-opt-item:active * {
    color: #ffffff !important;
}
#supplierSearchDropdown .supplier-opt-item:hover .badge,
#supplierSearchDropdown .supplier-opt-item:focus .badge,
#supplierSearchDropdown .supplier-opt-item.active .badge,
#supplierSearchDropdown .supplier-opt-item:active .badge {
    background-color: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
}

.hsn-dropdown .hsn-opt-item:hover,
.hsn-dropdown .hsn-opt-item:focus,
.hsn-dropdown .hsn-opt-item.active,
.hsn-dropdown .hsn-opt-item:active {
    background-color: #0d6efd !important;
    color: #ffffff !important;
}
.hsn-dropdown .hsn-opt-item:hover *,
.hsn-dropdown .hsn-opt-item:focus *,
.hsn-dropdown .hsn-opt-item.active *,
.hsn-dropdown .hsn-opt-item:active * {
    color: #ffffff !important;
}
.hsn-dropdown .hsn-opt-item:hover .badge,
.hsn-dropdown .hsn-opt-item:focus .badge,
.hsn-dropdown .hsn-opt-item.active .badge,
.hsn-dropdown .hsn-opt-item:active .badge {
    background-color: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
}
</style>

<template id="fabricRollTemplate">
    <div class="roll-card card border shadow-sm" data-index="">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <strong><i class="bi bi-layers text-primary me-1"></i><span class="roll-title">ROLL #1</span> <span class="badge bg-dark roll-code-text"></span></strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-roll"><i class="bi bi-trash me-1"></i>Delete</button>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-lg-2 col-md-4"><label class="form-label required">Roll No. / Code *</label><input class="form-control roll-number text-uppercase" maxlength="50" required></div>
                <div class="col-lg-2 col-md-4"><label class="form-label required">Color / Shade *</label><input class="form-control roll-color text-uppercase" maxlength="100" required placeholder="E.G. NAVY BLUE"></div>
                <div class="col-lg-2 col-md-4"><label class="form-label required">Design Name / Pattern *</label><input class="form-control roll-design text-uppercase" maxlength="150" required placeholder="E.G. FLORAL 01"></div>
                <div class="col-lg-1 col-md-3"><label class="form-label required">Designs *</label><input type="text" inputmode="numeric" pattern="[0-9]+" data-min="1" data-step="1" class="form-control roll-design-count numeric-text-input" value="1" required></div>
                <div class="col-lg-1 col-md-3"><label class="form-label required">RQ *</label><input type="text" inputmode="numeric" pattern="[0-9]+" data-min="1" data-step="1" class="form-control roll-quantity numeric-text-input" value="1" required></div>
                <div class="col-lg-2 col-md-3"><label class="form-label required">Weight (kg) *</label><div class="input-group"><input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0.01" data-step="0.01" class="form-control roll-weight numeric-text-input" required><span class="input-group-text">kg</span></div></div>
                <div class="col-lg-2 col-md-3"><label class="form-label required">Price (₹) *</label><div class="input-group"><span class="input-group-text">₹</span><input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0.01" data-step="0.01" class="form-control roll-price numeric-text-input" required></div></div>
                <div class="col-lg-2 col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0">HSN Code</label>
                        <button type="button" class="btn btn-link p-0 text-decoration-none small text-primary fw-semibold btn-add-hsn-trigger" style="font-size: 11px;" title="Add new HSN Code">
                            <i class="bi bi-plus-circle me-0.5"></i>+ Add HSN
                        </button>
                    </div>
                    <div class="dropdown hsn-dropdown w-100 position-relative">
                        <input type="hidden" class="roll-hsn">
                        <button type="button" class="form-select form-select-sm text-start d-flex justify-content-between align-items-center hsn-dropdown-btn font-monospace py-1.5 px-2" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="font-size: 0.82rem; height: 38px;">
                            <span class="hsn-btn-text text-truncate">-- Select HSN --</span>
                        </button>
                        <div class="dropdown-menu shadow-lg p-2 hsn-dropdown-menu" style="min-width: 280px; max-width: 340px; z-index: 1055;">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 hsn-search-input" placeholder="Search HSN code or item..." autocomplete="off">
                            </div>
                            <div class="overflow-auto hsn-options-list" style="max-height: 200px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3"><label class="form-label">GST</label><select class="form-select item-gst-type"><option value="SGST">SGST</option><option value="IGST">IGST</option><option value="CGST">CGST</option><option value="BOTH">Both (CGST and SGST)</option><option value="EXEMPTED" selected>Exempted</option></select></div>
                <div class="col-lg-2 col-md-3 tax-mode-field d-none"><label class="form-label">Tax Input Type</label><select class="form-select item-tax-mode"><option value="PERCENT">Percentage (%)</option><option value="AMOUNT">Amount (₹)</option></select></div>
                <div class="col-lg-2 col-md-3 tax-value-field d-none"><label class="form-label item-tax-label">Tax</label><input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-max="100" data-step="0.01" class="form-control item-tax-value numeric-text-input" value="0"></div>
                <div class="col-lg-2 col-md-3 tax-cgst-field d-none"><label class="form-label item-cgst-label">CGST</label><input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-max="100" data-step="0.01" class="form-control item-cgst-value numeric-text-input" value="0"></div>
                <div class="col-md-6"><label class="form-label">Roll Sample Photo <small class="text-muted">(Optional — JPG, PNG, WEBP)</small></label><input type="file" class="form-control roll-photo" accept=".jpg,.jpeg,.png,.webp"></div>
                <div class="col-md-6 roll-preview d-none"><img class="img-thumbnail" style="height:54px" alt="Roll photo preview"></div>
            </div>
        </div>
    </div>
</template>

<template id="supplyTemplate">
    <div class="supply-card card border shadow-sm mb-3 supply-row" data-index="">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <strong><i class="bi bi-box-seam text-success me-1"></i><span class="supply-title">SUPPLY #1</span> <span class="badge bg-secondary-subtle text-secondary border supply-name-badge d-none"></span></strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-supply"><i class="bi bi-trash me-1"></i>Delete</button>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label required">Item Name / Description *</label>
                    <input class="form-control supply-name text-uppercase" maxlength="255" placeholder="E.G. SEWING NEEDLES, THREAD, BUTTONS" required>
                </div>
                <div class="col-lg-2 col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0">HSN Code</label>
                        <button type="button" class="btn btn-link p-0 text-decoration-none small text-primary fw-semibold btn-add-hsn-trigger" style="font-size: 11px;" title="Add new HSN Code">
                            <i class="bi bi-plus-circle me-0.5"></i>+ Add HSN
                        </button>
                    </div>
                    <div class="dropdown hsn-dropdown w-100 position-relative">
                        <input type="hidden" class="supply-hsn">
                        <button type="button" class="form-select form-select-sm text-start d-flex justify-content-between align-items-center hsn-dropdown-btn font-monospace py-1.5 px-2" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="font-size: 0.82rem; height: 38px;">
                            <span class="hsn-btn-text text-truncate">-- Select HSN --</span>
                        </button>
                        <div class="dropdown-menu shadow-lg p-2 hsn-dropdown-menu" style="min-width: 280px; max-width: 340px; z-index: 1055;">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 hsn-search-input" placeholder="Search HSN code or item..." autocomplete="off">
                            </div>
                            <div class="overflow-auto hsn-options-list" style="max-height: 200px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label required">Quantity *</label>
                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0.001" data-step="0.001" class="form-control supply-quantity numeric-text-input" value="1" required>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label required">Unit *</label>
                    <select class="form-select supply-unit">
                        <option value="PCS">Pieces</option>
                        <option value="KG">Kilograms</option>
                        <option value="METRE">Metres</option>
                        <option value="LITRE">Litres</option>
                        <option value="BOX">Boxes</option>
                        <option value="PACK">Packs</option>
                        <option value="PAIR">Pairs</option>
                        <option value="SET">Sets</option>
                        <option value="BUNDLE">Bundles</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-3 custom-unit d-none">
                    <label class="form-label required">Unit Name *</label>
                    <input class="form-control custom-unit-name text-uppercase" maxlength="30" placeholder="e.g. CONE, PACK">
                </div>
                <div class="col-lg-3 col-md-3">
                    <label class="form-label required">Unit Price (₹) *</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-step="0.01" class="form-control supply-price numeric-text-input" value="0" required>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label">GST</label>
                    <select class="form-select item-gst-type">
                        <option value="SGST">SGST</option>
                        <option value="IGST">IGST</option>
                        <option value="CGST">CGST</option>
                        <option value="BOTH">Both (CGST and SGST)</option>
                        <option value="EXEMPTED" selected>Exempted</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 tax-mode-field d-none">
                    <label class="form-label">Tax Input Type</label>
                    <select class="form-select item-tax-mode">
                        <option value="PERCENT">Percentage (%)</option>
                        <option value="AMOUNT">Amount (₹)</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 tax-value-field d-none">
                    <label class="form-label item-tax-label">Tax</label>
                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-max="100" data-step="0.01" class="form-control item-tax-value numeric-text-input" value="0">
                </div>
                <div class="col-lg-2 col-md-3 tax-cgst-field d-none">
                    <label class="form-label item-cgst-label">CGST</label>
                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-max="100" data-step="0.01" class="form-control item-cgst-value numeric-text-input" value="0">
                </div>
            </div>
        </div>
    </div>
</template>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
(() => {
    const form = document.getElementById('productionPurchaseForm');
    const rollsContainer = document.getElementById('rollsContainer');
    const suppliesContainer = document.getElementById('suppliesContainer');
    const rollTemplate = document.getElementById('fabricRollTemplate');
    const supplyTemplate = document.getElementById('supplyTemplate');
    const supplierSelect = document.getElementById('purchaseSeller');
    const supplierDetails = document.getElementById('selectedSupplierDetails');
    let rollIndex = 0;
    let supplyIndex = 0;
    let nextSequence = 1;
    let sequencePrefix = 'FB<?= date('my') ?>';
    window.hsnCodesList = <?= json_encode($hsnCodes ?? []) ?>;
    const money = value => '₹' + (Number(value) || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
    const safe = value => String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const numericTextPattern = /^(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)$/;
    let activeHsnTarget = null;

    function sanitizeNumericInput(input) {
        if (!input || !input.matches('.numeric-text-input')) return;
        const isIntegerOnly = input.matches('.roll-design-count, .roll-quantity') || input.dataset.step === '1' || input.getAttribute('step') === '1' || input.getAttribute('inputmode') === 'numeric';
        const raw = input.value;
        let clean = '';
        if (isIntegerOnly) {
            clean = raw.replace(/\D/g, '');
        } else {
            clean = raw.replace(/[^0-9.]/g, '');
            const parts = clean.split('.');
            if (parts.length > 2) {
                clean = parts[0] + '.' + parts.slice(1).join('');
            }
        }
        if (raw !== clean) {
            let start = null;
            try { start = input.selectionStart; } catch (_) {}
            input.value = clean;
            if (start !== null) {
                try {
                    const newPos = Math.min(start - (raw.length - clean.length), clean.length);
                    input.setSelectionRange(Math.max(0, newPos), Math.max(0, newPos));
                } catch (_) {}
            }
        }
    }

    function handleNumericKeyDown(event) {
        const input = event.target;
        if (!input || !input.matches('.numeric-text-input, input[type="number"]')) return;

        if (
            event.ctrlKey || event.metaKey || event.altKey ||
            ['Backspace', 'Delete', 'Tab', 'Enter', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)
        ) {
            return;
        }

        // Strictly block exponential notation 'e', 'E' and sign keys '+', '-'
        if (['e', 'E', '+', '-'].includes(event.key)) {
            event.preventDefault();
            return;
        }

        const isIntegerOnly = input.matches('.roll-design-count, .roll-quantity') || input.dataset.step === '1' || input.getAttribute('step') === '1' || input.getAttribute('inputmode') === 'numeric';

        if (isIntegerOnly) {
            if (!/^[0-9]$/.test(event.key)) {
                event.preventDefault();
            }
        } else {
            if (event.key === '.') {
                if (input.value && input.value.includes('.')) {
                    let selectedText = '';
                    try {
                        const selStart = input.selectionStart;
                        const selEnd = input.selectionEnd;
                        selectedText = (selStart !== null && selEnd !== null) ? input.value.substring(selStart, selEnd) : '';
                    } catch (_) {}
                    if (!selectedText.includes('.')) {
                        event.preventDefault();
                    }
                }
            } else if (!/^[0-9]$/.test(event.key)) {
                event.preventDefault();
            }
        }
    }

    function handleNumericBeforeInput(event) {
        const input = event.target;
        if (!input || !input.matches('.numeric-text-input, input[type="number"]')) return;
        if (!event.data) return;

        if (['e', 'E', '+', '-'].some(ch => event.data.includes(ch))) {
            event.preventDefault();
            return;
        }

        const isIntegerOnly = input.matches('.roll-design-count, .roll-quantity') || input.dataset.step === '1' || input.getAttribute('step') === '1' || input.getAttribute('inputmode') === 'numeric';

        if (isIntegerOnly) {
            if (/[^0-9]/.test(event.data)) {
                event.preventDefault();
            }
        } else {
            if (/[^0-9.]/.test(event.data)) {
                event.preventDefault();
            } else if (event.data.includes('.') && input.value && input.value.includes('.')) {
                let selectedText = '';
                try {
                    const selStart = input.selectionStart;
                    const selEnd = input.selectionEnd;
                    selectedText = (selStart !== null && selEnd !== null) ? input.value.substring(selStart, selEnd) : '';
                } catch (_) {}
                if (!selectedText.includes('.')) {
                    event.preventDefault();
                }
            }
        }
    }

    function handleNumericPaste(event) {
        const input = event.target;
        if (!input || !input.matches('.numeric-text-input')) return;
        setTimeout(() => {
            sanitizeNumericInput(input);
            validateNumericTextInput(input);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }, 0);
    }

    document.addEventListener('keydown', handleNumericKeyDown);
    document.addEventListener('beforeinput', handleNumericBeforeInput);
    document.addEventListener('paste', handleNumericPaste);

    function validateNumericTextInput(input) {
        const value = input.value.trim();
        let message = '';
        if (value === '') {
            message = input.required ? 'Enter a value.' : '';
        } else if (!numericTextPattern.test(value) || !Number.isFinite(Number(value))) {
            message = 'Enter a valid number.';
        } else {
            const number = Number(value);
            const minAttribute = input.dataset.min;
            const maxAttribute = input.dataset.max;
            const stepAttribute = input.dataset.step;
            if (minAttribute !== undefined && number < Number(minAttribute)) {
                message = `Value must be at least ${minAttribute}.`;
            } else if (maxAttribute !== undefined && number > Number(maxAttribute)) {
                message = `Value must be no more than ${maxAttribute}.`;
            } else if (stepAttribute && stepAttribute !== 'any') {
                const step = Number(stepAttribute);
                const base = minAttribute !== undefined ? Number(minAttribute) : 0;
                const stepCount = (number - base) / step;
                if (step > 0 && Math.abs(stepCount - Math.round(stepCount)) > 1e-8) {
                    message = `Value must use increments of ${stepAttribute}.`;
                }
            }
        }
        input.setCustomValidity(message);
        return message === '';
    }

    form.addEventListener('input', event => {
        if (event.target.matches('.numeric-text-input')) {
            sanitizeNumericInput(event.target);
            validateNumericTextInput(event.target);
        }
    });
    form.addEventListener('submit', event => {
        const invalidInput = Array.from(form.querySelectorAll('.numeric-text-input'))
            .find(input => !validateNumericTextInput(input));
        if (invalidInput) {
            event.preventDefault();
            invalidInput.reportValidity();
        }
    }, true);

    function openQuickAddHsnModal(dropdownEl, hiddenInput, presetCode = '') {
        activeHsnTarget = {
            dropdownEl,
            hiddenInput,
            btn: dropdownEl.querySelector('.hsn-dropdown-btn'),
            btnText: dropdownEl.querySelector('.hsn-btn-text')
        };
        const codeInp = document.getElementById('quickHsnCodeInput');
        const descInp = document.getElementById('quickHsnDescInput');
        const gstInp = document.getElementById('quickHsnGstInput');
        const alertBox = document.getElementById('quickHsnAlert');
        if (codeInp) codeInp.value = presetCode || '';
        if (descInp) descInp.value = '';
        if (gstInp) gstInp.value = '5.00';
        if (alertBox) alertBox.classList.add('d-none');
        const modalEl = document.getElementById('modalQuickAddHsn');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            setTimeout(() => {
                if (presetCode && descInp) descInp.focus();
                else if (codeInp) codeInp.focus();
            }, 300);
        }
    }

    function initQuickAddHsnModalHandler() {
        const btnSave = document.getElementById('btnSaveQuickHsn');
        if (!btnSave) return;
        btnSave.addEventListener('click', () => {
            const code = (document.getElementById('quickHsnCodeInput')?.value || '').trim().toUpperCase();
            const desc = (document.getElementById('quickHsnDescInput')?.value || '').trim();
            const gstInput = document.getElementById('quickHsnGstInput');
            if (gstInput && !validateNumericTextInput(gstInput)) {
                gstInput.reportValidity();
                return;
            }
            const gst = Number(gstInput?.value) || 0;
            const alertBox = document.getElementById('quickHsnAlert');

            if (!code || code.length < 2) {
                if (alertBox) { alertBox.textContent = 'Please enter a valid HSN code (at least 2 characters).'; alertBox.classList.remove('d-none'); }
                return;
            }
            if (!desc) {
                if (alertBox) { alertBox.textContent = 'Please enter a commodity / item description.'; alertBox.classList.remove('d-none'); }
                return;
            }

            btnSave.disabled = true;
            btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

            const fd = new FormData();
            fd.append('hsn_code', code);
            fd.append('description', desc);
            fd.append('gst_rate', gst.toFixed(2));
            const csrf = document.querySelector('input[name="csrf_token"]');
            if (csrf) fd.append('csrf_token', csrf.value);

            fetch('<?= url("api/hsn-codes/store") ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save &amp; Select HSN';

                if (res.success && res.data) {
                    const newHsn = res.data;
                    const existingIdx = (window.hsnCodesList || []).findIndex(h => String(h.hsn_code).toUpperCase() === String(newHsn.hsn_code).toUpperCase());
                    if (existingIdx >= 0) {
                        window.hsnCodesList[existingIdx] = newHsn;
                    } else {
                        if (!window.hsnCodesList) window.hsnCodesList = [];
                        window.hsnCodesList.push(newHsn);
                    }

                    if (activeHsnTarget && activeHsnTarget.hiddenInput) {
                        activeHsnTarget.hiddenInput.value = newHsn.hsn_code;
                        if (activeHsnTarget.btnText) {
                            activeHsnTarget.btnText.innerHTML = `<span class="fw-bold text-primary">${safe(newHsn.hsn_code)}</span> <span class="text-muted small">(${safe(newHsn.description).slice(0, 16)}${newHsn.description.length > 16 ? '...' : ''})</span>`;
                        }
                        const bsDropdown = bootstrap.Dropdown.getInstance(activeHsnTarget.btn);
                        if (bsDropdown) bsDropdown.hide();
                        activeHsnTarget.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        activeHsnTarget.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    const modalEl = document.getElementById('modalQuickAddHsn');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }
                } else {
                    if (alertBox) {
                        alertBox.textContent = res.message || 'Failed to save HSN Code.';
                        alertBox.classList.remove('d-none');
                    }
                }
            })
            .catch(err => {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save &amp; Select HSN';
                if (alertBox) {
                    alertBox.textContent = 'Server error occurred while saving HSN Code.';
                    alertBox.classList.remove('d-none');
                }
            });
        });
    }
    initQuickAddHsnModalHandler();

    function initHsnDropdown(container, hiddenSelector) {
        const dropdownEl = container.querySelector('.hsn-dropdown');
        if (!dropdownEl) return;
        const hiddenInput = dropdownEl.querySelector(hiddenSelector);
        const btn = dropdownEl.querySelector('.hsn-dropdown-btn');
        const btnText = dropdownEl.querySelector('.hsn-btn-text');
        const searchInput = dropdownEl.querySelector('.hsn-search-input');
        const listContainer = dropdownEl.querySelector('.hsn-options-list');

        const triggerBtn = container.querySelector('.btn-add-hsn-trigger');
        if (triggerBtn) {
            triggerBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openQuickAddHsnModal(dropdownEl, hiddenInput);
            });
        }

        function updateBtnDisplay(code, desc) {
            if (code) {
                btnText.innerHTML = `<span class="fw-bold text-primary">${safe(code)}</span> ${desc ? `<span class="text-muted small">(${safe(desc).slice(0, 16)}${desc.length > 16 ? '...' : ''})</span>` : ''}`;
            } else {
                btnText.textContent = '-- Select HSN --';
            }
        }

        if (hiddenInput && hiddenInput.value) {
            const found = (window.hsnCodesList || []).find(h => String(h.hsn_code).toUpperCase() === hiddenInput.value.toUpperCase());
            updateBtnDisplay(hiddenInput.value, found ? found.description : '');
        }

        function renderOptions(filter = '') {
            const q = filter.trim().toLowerCase();
            let html = `
                <button type="button" class="dropdown-item py-1.5 px-2 small border-bottom hsn-opt-item text-muted" data-code="" data-desc="">
                    <em>-- Clear / No HSN --</em>
                </button>
            `;
            let matchCount = 0;
            const currentVal = (hiddenInput ? hiddenInput.value : '').trim();

            (window.hsnCodesList || []).forEach(h => {
                const code = String(h.hsn_code || '').trim();
                const desc = String(h.description || '').trim();
                const gst = h.gst_rate ? Number(h.gst_rate) : null;
                const searchStr = (code + ' ' + desc).toLowerCase();
                if (q === '' || searchStr.includes(q)) {
                    matchCount++;
                    const isSelected = currentVal && currentVal.toUpperCase() === code.toUpperCase();
                    html += `
                        <button type="button" class="dropdown-item py-1.5 px-2 small border-bottom hsn-opt-item ${isSelected ? 'active' : ''}" 
                                data-code="${safe(code)}" data-desc="${safe(desc)}" data-gst="${gst || 0}">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-monospace fw-bold text-primary">${safe(code)}</span>
                                ${gst !== null ? `<span class="badge bg-light text-secondary border fs-8">${gst}% GST</span>` : ''}
                            </div>
                            <div class="text-truncate small text-muted" style="max-width: 250px;">${safe(desc)}</div>
                        </button>
                    `;
                }
            });

            if (q !== '' && !matchCount) {
                html += `
                    <div class="text-muted p-2 text-center small">No matching standard HSN found.</div>
                    <button type="button" class="dropdown-item py-1.5 px-2 small text-primary fw-semibold hsn-quick-add-btn border-top" data-preset="${safe(q.toUpperCase())}">
                        <i class="bi bi-plus-circle me-1"></i>+ Add "${safe(q.toUpperCase())}" to Database
                    </button>
                `;
            }

            html += `
                <div class="border-top pt-1 mt-1 text-center bg-light rounded-bottom">
                    <button type="button" class="dropdown-item py-1.5 px-2 small text-primary fw-semibold text-center hsn-quick-add-btn" data-preset="${safe(q.toUpperCase())}">
                        <i class="bi bi-plus-circle me-1"></i>+ Add New HSN Code
                    </button>
                </div>
            `;

            listContainer.innerHTML = html;

            listContainer.querySelectorAll('.hsn-quick-add-btn').forEach(btnEl => {
                btnEl.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const bsDropdown = bootstrap.Dropdown.getInstance(btn);
                    if (bsDropdown) bsDropdown.hide();
                    openQuickAddHsnModal(dropdownEl, hiddenInput, btnEl.dataset.preset || '');
                });
            });

            listContainer.querySelectorAll('.hsn-opt-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const code = item.dataset.code || '';
                    const desc = item.dataset.desc || '';
                    if (hiddenInput) {
                        hiddenInput.value = code;
                    }
                    updateBtnDisplay(code, desc);
                    const bsDropdown = bootstrap.Dropdown.getInstance(btn);
                    if (bsDropdown) bsDropdown.hide();
                    if (hiddenInput) {
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });
        }

        renderOptions();

        dropdownEl.addEventListener('shown.bs.dropdown', () => {
            if (searchInput) {
                searchInput.value = '';
                renderOptions('');
                searchInput.focus();
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                renderOptions(searchInput.value);
            });
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const first = listContainer.querySelector('.hsn-opt-item:not(.text-muted)');
                    if (first) first.click();
                }
            });
        }
    }
    function calculateItemTax(item, base) {
        const type = item.querySelector('.item-gst-type').value;
        const mode = item.querySelector('.item-tax-mode').value;
        const value = Number(item.querySelector('.item-tax-value').value) || 0;
        const cgstValue = Number(item.querySelector('.item-cgst-value').value) || 0;
        const toAmount = taxValue => mode === 'AMOUNT' ? taxValue : Math.round(base * taxValue) / 100;
        const sgstAmount = ['SGST', 'BOTH'].includes(type) ? toAmount(value) : 0;
        const cgstAmount = ['CGST', 'BOTH'].includes(type) ? toAmount(type === 'BOTH' ? cgstValue : value) : 0;
        const igstAmount = type === 'IGST' ? toAmount(value) : 0;
        return {type, mode, value, sgstAmount, cgstAmount, igstAmount, amount: sgstAmount + cgstAmount + igstAmount};
    }

    function updateTaxInputs(item) {
        const type = item.querySelector('.item-gst-type').value;
        const active = type !== 'EXEMPTED';
        const modeField = item.querySelector('.tax-mode-field');
        const valueField = item.querySelector('.tax-value-field');
        const cgstField = item.querySelector('.tax-cgst-field');
        const valueInput = item.querySelector('.item-tax-value');
        const cgstInput = item.querySelector('.item-cgst-value');
        modeField.classList.toggle('d-none', !active);
        valueField.classList.toggle('d-none', !active);
        cgstField.classList.toggle('d-none', type !== 'BOTH');
        valueInput.required = active;
        cgstInput.required = type === 'BOTH';
        item.querySelector('.item-tax-label').textContent = type === 'EXEMPTED'
            ? 'Tax'
            : `${type === 'BOTH' ? 'SGST' : type} (${item.querySelector('.item-tax-mode').value === 'PERCENT' ? '%' : '₹'})`;
        item.querySelector('.item-cgst-label').textContent = `CGST (${item.querySelector('.item-tax-mode').value === 'PERCENT' ? '%' : '₹'})`;
        [valueInput, cgstInput].forEach(input => {
            if (item.querySelector('.item-tax-mode').value === 'PERCENT') input.dataset.max = '100';
            else delete input.dataset.max;
        });
    }

    function updateSupplierDetails() {
        const selected = supplierSelect.options[supplierSelect.selectedIndex];
        if (!selected || !selected.value) {
            supplierDetails.classList.add('d-none');
            return;
        }

        const fields = {
            name: selected.dataset.supplierName,
            company: selected.dataset.supplierCompany,
            phone: selected.dataset.supplierPhone,
            email: selected.dataset.supplierEmail,
            gst: selected.dataset.supplierGst,
            location: [selected.dataset.supplierCity, selected.dataset.supplierState].filter(Boolean).join(', '),
            address: selected.dataset.supplierAddress
        };
        Object.entries(fields).forEach(([field, value]) => {
            const el = supplierDetails.querySelector(`[data-supplier-detail="${field}"]`);
            if (el) el.textContent = value || '—';
        });
        supplierDetails.classList.remove('d-none');
    }

    function updateSummary() {
        const cards = Array.from(rollsContainer.querySelectorAll('.roll-card'));
        document.getElementById('fabricDescription').required = cards.length > 0;
        const totalWeight = cards.reduce((sum, card) => sum + (Number(card.querySelector('.roll-weight').value) || 0), 0);
        const totalPrice = cards.reduce((sum, card) => {
            const price = Number(card.querySelector('.roll-price').value) || 0;
            const tax = calculateItemTax(card, price);
            return sum + price + tax.amount;
        }, 0);
        const totalRq = cards.reduce((sum, card) => sum + (Number(card.querySelector('.roll-quantity').value) || 0), 0);
        const colors = new Set(cards.map(card => card.querySelector('.roll-color').value.trim().toUpperCase()).filter(Boolean));
        const designs = new Set(cards.map(card => card.querySelector('.roll-design').value.trim().toUpperCase()).filter(Boolean));
        const countBadge = document.getElementById('rollCountBadge');
        if (countBadge) countBadge.textContent = cards.length;
        const tblCountBadge = document.getElementById('tableRollCountBadge');
        if (tblCountBadge) tblCountBadge.textContent = cards.length + (cards.length === 1 ? ' Roll' : ' Rolls');
        const sumRolls = document.getElementById('summaryTotalRolls');
        if (sumRolls) sumRolls.textContent = cards.length + (cards.length === 1 ? ' Roll' : ' Rolls');
        const sumRQ = document.getElementById('summaryTotalRQ');
        if (sumRQ) sumRQ.textContent = totalRq.toLocaleString('en-IN') + ' RQ';
        const sumColDes = document.getElementById('summaryColorsDesigns');
        if (sumColDes) sumColDes.textContent = colors.size + ' Colors • ' + designs.size + ' Designs';
        const sumWeight = document.getElementById('summaryTotalWeight');
        if (sumWeight) sumWeight.textContent = totalWeight.toFixed(2) + ' kg';
        const sumPrice = document.getElementById('summaryTotalPrice');
        if (sumPrice) sumPrice.textContent = money(totalPrice);

        const body = document.getElementById('rollDetailsTableBody');
        if (body) {
            if (!cards.length) {
                body.innerHTML = '<tr><td colspan="13" class="text-center text-muted py-4">Add fabric rolls to see their inventory details.</td></tr>';
            } else {
                body.innerHTML = cards.map((card, index) => {
                    const rollNum = card.querySelector('.roll-number')?.value || ('ROLL #' + (index + 1));
                    const hsn = card.querySelector('.roll-hsn')?.value?.trim() || '—';
                    const color = card.querySelector('.roll-color')?.value || '—';
                    const design = card.querySelector('.roll-design')?.value || '—';
                    const designCount = Number(card.querySelector('.roll-design-count')?.value) || 1;
                    const qty = Number(card.querySelector('.roll-quantity')?.value) || 0;
                    const weight = Number(card.querySelector('.roll-weight')?.value) || 0;
                    const price = Number(card.querySelector('.roll-price')?.value) || 0;
                    const tax = calculateItemTax(card, price);
                    const totalValue = price + tax.amount;
                    const start = Number(card.dataset.startSeq) || 0;
                    const end = start + Math.max(qty, 1) - 1;
                    const codeStart = start ? sequencePrefix + String(start).padStart(2, '0') : '—';
                    const codeEnd = start ? sequencePrefix + String(end).padStart(2, '0') : '—';
                    const photo = card.querySelector('.roll-photo')?.files[0];
                    const previewImg = card.querySelector('.roll-preview img');
                    const photoHtml = photo && previewImg && previewImg.src ? `<img src="${previewImg.src}" alt="Roll" style="width:38px;height:38px;object-fit:cover;border-radius:4px;">` : '<span class="text-muted"><i class="bi bi-camera"></i></span>';
                    const taxLabel = tax.type === 'BOTH' ? 'SGST + CGST' : tax.type;
                    return `<tr>
                        <td class="text-center fw-bold">${index + 1}</td>
                        <td class="text-center">${photoHtml}</td>
                        <td><strong>${safe(rollNum)}</strong><small class="d-block font-monospace text-primary">${safe(codeStart)}${codeStart !== codeEnd ? '–' + safe(codeEnd) : ''}</small></td>
                        <td><span class="badge bg-light text-dark border font-monospace">${safe(hsn)}</span></td>
                        <td><span class="badge bg-light text-dark border">${safe(color)}</span></td>
                        <td>${safe(design)}</td>
                        <td class="text-center">${designCount}</td>
                        <td class="text-end fw-semibold">${qty.toLocaleString('en-IN')} RQ</td>
                        <td class="text-end fw-bold" style="color:#0284c7">${weight.toFixed(2)} kg</td>
                        <td class="text-end">${money(price)}</td>
                        <td><small class="badge bg-secondary-subtle text-secondary">${taxLabel}</small></td>
                        <td class="text-end">${money(tax.amount)}</td>
                        <td class="text-end fw-bold text-success">${money(totalValue)}</td>
                        <td class="text-center"><span class="badge bg-success-subtle text-success border border-success">Ready (${qty} RQ)</span></td>
                    </tr>`;
                }).join('');
            }
        }
        updateTotal();
    }

    function updateTotal() {
        const rollTotals = Array.from(rollsContainer.querySelectorAll('.roll-card')).reduce((totals, card) => {
            const base = Number(card.querySelector('.roll-price').value) || 0;
            const tax = calculateItemTax(card, base).amount;
            totals.base += base;
            totals.tax += tax;
            totals.total += base + tax;
            return totals;
        }, {base: 0, tax: 0, total: 0});
        const supplyRows = Array.from(suppliesContainer.querySelectorAll('.supply-row'));
        const supplyTotals = {base: 0, tax: 0, total: 0};
        const tableBody = document.getElementById('supplyDetailsTableBody');
        const tableFoot = document.getElementById('supplyDetailsTableFoot');
        document.getElementById('supplyTableCountBadge').textContent = supplyRows.length + (supplyRows.length === 1 ? ' Item' : ' Items');
        if (!supplyRows.length) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Add garment supplies to see their purchase details.</td></tr>';
            tableFoot.innerHTML = '';
        } else {
            tableBody.innerHTML = supplyRows.map((row, index) => {
                const quantity = Number(row.querySelector('.supply-quantity').value) || 0;
                const unitPrice = Number(row.querySelector('.supply-price').value) || 0;
                const hsn = row.querySelector('.supply-hsn')?.value?.trim() || '—';
                const base = quantity * unitPrice;
                const tax = calculateItemTax(row, base);
                const total = base + tax.amount;
                supplyTotals.base += base;
                supplyTotals.tax += tax.amount;
                supplyTotals.total += total;
                const taxLabel = tax.type === 'BOTH' ? 'SGST + CGST' : tax.type;
                return `<tr><td class="text-center">${index + 1}</td><td>${safe(row.querySelector('.supply-name').value.trim() || '—')}</td><td><span class="badge bg-light text-dark border font-monospace">${safe(hsn)}</span></td><td class="text-end">${quantity.toLocaleString('en-IN', {maximumFractionDigits:3})}</td><td>${safe(row.querySelector('.supply-unit').selectedOptions[0].textContent)}</td><td class="text-end">${money(unitPrice)}</td><td class="text-end">${money(base)}</td><td>${taxLabel}</td><td class="text-end">${money(tax.amount)}</td><td class="text-end fw-bold text-success">${money(total)}</td></tr>`;
            }).join('');
            tableFoot.innerHTML = `<tr><td colspan="6" class="text-end">Totals</td><td class="text-end">${money(supplyTotals.base)}</td><td></td><td class="text-end">${money(supplyTotals.tax)}</td><td class="text-end text-success">${money(supplyTotals.total)}</td></tr>`;
        }
        const subtotal = rollTotals.base + supplyTotals.base;
        const totalTax = rollTotals.tax + supplyTotals.tax;
        document.getElementById('purchaseSubtotal').textContent = money(subtotal);
        document.getElementById('purchaseTaxes').textContent = money(totalTax);
        document.getElementById('purchaseTotal').textContent = money(subtotal + totalTax);
    }

    function updateCodes() {
        let sequence = nextSequence;
        rollsContainer.querySelectorAll('.roll-card').forEach(card => {
            card.dataset.startSeq = sequence;
            const quantity = Math.max(1, Number(card.querySelector('.roll-quantity').value) || 1);
            const first = sequencePrefix + String(sequence).padStart(2, '0');
            const last = sequencePrefix + String(sequence + quantity - 1).padStart(2, '0');
            card.querySelector('.roll-code-text').textContent = first === last ? first : first + '–' + last;
            sequence += quantity;
        });
        updateSummary();
    }

    function addRoll() {
        const index = rollIndex++;
        const card = rollTemplate.content.firstElementChild.cloneNode(true);
        card.dataset.index = index;
        const rollCode = 'ROLL #' + (rollsContainer.children.length + 1);
        card.querySelector('.roll-title').textContent = rollCode;
        card.querySelector('.roll-number').value = rollCode;
        const names = {
            '.roll-number': 'roll_number', '.roll-color': 'color', '.roll-design': 'design_name',
            '.roll-design-count': 'design_count', '.roll-quantity': 'quantity', '.roll-weight': 'weight_kg',
            '.roll-price': 'unit_price', '.roll-hsn': 'hsn_code', '.item-gst-type': 'gst_type', '.item-tax-mode': 'tax_mode',
            '.item-tax-value': 'tax_value', '.item-cgst-value': 'cgst_tax_value'
        };
        Object.entries(names).forEach(([selector, field]) => {
            const input = card.querySelector(selector);
            if (input && field) input.name = `items[${index}][${field}]`;
        });
        const type = document.createElement('input');
        type.type = 'hidden';
        type.name = `items[${index}][item_type]`;
        type.value = 'FABRIC_ROLL';
        card.appendChild(type);

        initHsnDropdown(card, '.roll-hsn');

        card.querySelector('.remove-roll').addEventListener('click', () => {
            card.remove();
            Array.from(rollsContainer.children).forEach((item, i) => item.querySelector('.roll-title').textContent = 'ROLL #' + (i + 1));
            updateCodes();
        });
        card.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('input', updateCodes);
            input.addEventListener('change', () => {
                updateTaxInputs(card);
                updateCodes();
            });
            input.addEventListener('keyup', updateCodes);
        });
        updateTaxInputs(card);
        card.querySelector('.roll-photo').addEventListener('change', event => {
            const file = event.target.files[0];
            const preview = card.querySelector('.roll-preview');
            if (!file) {
                event.target.removeAttribute('name');
                preview.classList.add('d-none');
                updateSummary();
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Roll photo must not exceed 5 MB.');
                event.target.value = '';
                event.target.removeAttribute('name');
                preview.classList.add('d-none');
                updateSummary();
                return;
            }
            event.target.name = `roll_photo_${index}`;
            const reader = new FileReader();
            reader.onload = () => { card.querySelector('.roll-preview img').src = reader.result; preview.classList.remove('d-none'); updateSummary(); };
            reader.readAsDataURL(file);
        });
        rollsContainer.appendChild(card);
        updateCodes();
    }

    function updateSupplyTitles() {
        const supplyTarget = document.getElementById('supplyRows') || suppliesContainer;
        const rows = Array.from(supplyTarget.querySelectorAll('.supply-row'));
        rows.forEach((row, i) => {
            const title = row.querySelector('.supply-title');
            if (title) title.textContent = 'SUPPLY #' + (i + 1);
        });
    }

    function addSupply() {
        const index = supplyIndex++;
        const row = supplyTemplate.content.firstElementChild.cloneNode(true);
        row.querySelector('.supply-name').name = `items[${index + 1000}][item_name]`;
        row.querySelector('.supply-hsn').name = `items[${index + 1000}][hsn_code]`;
        row.querySelector('.supply-quantity').name = `items[${index + 1000}][quantity]`;
        row.querySelector('.supply-unit').name = `items[${index + 1000}][unit]`;
        row.querySelector('.supply-price').name = `items[${index + 1000}][unit_price]`;
        row.querySelector('.item-gst-type').name = `items[${index + 1000}][gst_type]`;
        row.querySelector('.item-tax-mode').name = `items[${index + 1000}][tax_mode]`;
        row.querySelector('.item-tax-value').name = `items[${index + 1000}][tax_value]`;
        row.querySelector('.item-cgst-value').name = `items[${index + 1000}][cgst_tax_value]`;
        const type = document.createElement('input');
        type.type = 'hidden'; type.name = `items[${index + 1000}][item_type]`; type.value = 'GENERAL_SUPPLY'; row.appendChild(type);

        initHsnDropdown(row, '.supply-hsn');
        row.querySelector('.supply-name').addEventListener('input', (e) => {
            const badge = row.querySelector('.supply-name-badge');
            if (badge) {
                const val = e.target.value.trim().toUpperCase();
                badge.textContent = val;
                badge.classList.toggle('d-none', !val);
            }
        });
        row.querySelector('.supply-unit').addEventListener('change', event => {
            const custom = event.target.value === 'OTHER';
            const unitContainer = row.querySelector('.custom-unit');
            unitContainer.classList.toggle('d-none', !custom);
            const input = row.querySelector('.custom-unit-name');
            input.required = custom;
            if (custom) input.name = `items[${index + 1000}][custom_unit]`;
            else input.removeAttribute('name');
            updateTotal();
        });
        row.querySelectorAll('input').forEach(input => input.addEventListener('input', updateTotal));
        row.querySelector('.item-gst-type').addEventListener('change', () => {
            updateTaxInputs(row);
            updateTotal();
        });
        row.querySelector('.item-tax-mode').addEventListener('change', () => {
            updateTaxInputs(row);
            updateTotal();
        });
        updateTaxInputs(row);
        row.querySelector('.remove-supply').addEventListener('click', () => {
            row.remove();
            const supplyTarget = document.getElementById('supplyRows') || suppliesContainer;
            if (!supplyTarget.querySelector('.supply-row')) document.getElementById('emptySupplyMessage').classList.remove('d-none');
            updateSupplyTitles();
            updateTotal();
        });
        document.getElementById('emptySupplyMessage').classList.add('d-none');
        const supplyTarget = document.getElementById('supplyRows') || suppliesContainer;
        supplyTarget.appendChild(row);
        updateSupplyTitles();
        updateTotal();
    }

    function previewLabels() {
        const material = document.getElementById('fabricDescription').value.trim().toUpperCase();
        if (!material) { document.getElementById('fabricDescription').focus(); return; }
        const rolls = Array.from(rollsContainer.querySelectorAll('.roll-card'));
        if (!rolls.length) { alert('Add a fabric roll before previewing labels.'); return; }
        const labelCards = document.getElementById('purchaseLabelCards');
        labelCards.innerHTML = '';
        let labelCount = 0;
        const supplierName = supplier.options[supplier.selectedIndex]?.text?.trim() || 'SUPPLIER';

        rolls.forEach((card, index) => {
            const qty = Number(card.querySelector('.roll-quantity').value) || 1;
            const start = Number(card.dataset.startSeq) || nextSequence;
            const roll = card.querySelector('.roll-number').value || 'ROLL #' + (index + 1);
            const weight = Number(card.querySelector('.roll-weight').value) || 0;
            const pieceWeight = qty > 0 ? (weight / qty).toFixed(2) : weight.toFixed(2);
            const color = (card.querySelector('.roll-color')?.value?.trim() || 'STANDARD').toUpperCase();
            const design = (card.querySelector('.roll-design')?.value?.trim() || '').toUpperCase();

            for (let piece = 0; piece < qty; piece++) {
                const code = sequencePrefix + String(start + piece).padStart(2, '0');
                const pieceLabel = qty > 1 ? `${roll} (${piece + 1}/${qty})` : roll;

                const sticker = document.createElement('div');
                sticker.className = 'roll-label-card';
                sticker.innerHTML = `
                    <div class="label-header">
                        <div class="company-tag" title="${safe(supplierName)}">${safe(supplierName)}</div>
                        <div class="po-tag">PO PREVIEW</div>
                    </div>
                    <div class="label-body">
                        <div class="qr-box"></div>
                        <div class="label-meta">
                            <span class="roll-badge-num">${safe(pieceLabel)}</span>
                            <div class="meta-line"><span>Fabric:</span> <strong>${safe(material.length > 16 ? material.substring(0, 16) + '...' : material)}</strong></div>
                            <div class="meta-line"><span>Color:</span> <strong>${safe(color)}</strong></div>
                            <div class="meta-line"><span>Weight:</span> <strong style="color:#0284c7">${pieceWeight} kg</strong></div>
                            <div class="meta-line"><span>Code:</span> <span class="font-monospace text-muted" style="font-size:8px">${safe(code)}</span></div>
                        </div>
                    </div>
                    <div class="label-footer">
                        <span>GRM MFG</span>
                    </div>
                `;
                labelCards.appendChild(sticker);
                const qrTarget = sticker.querySelector('.qr-box');
                if (window.QRCode) {
                    new QRCode(qrTarget, {
                        text: code,
                        width: 72,
                        height: 72,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.L
                    });
                } else {
                    qrTarget.textContent = code;
                }
                labelCount++;
            }
        });
        document.getElementById('purchaseLabelCount').textContent = labelCount + (labelCount === 1 ? ' label' : ' labels');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('purchaseLabelsModal')).show();
    }

    document.getElementById('addFabricRoll').addEventListener('click', addRoll);
    document.getElementById('addSupply').addEventListener('click', addSupply);
    document.getElementById('previewLabels')?.addEventListener('click', previewLabels);
    supplierSelect.addEventListener('change', updateSupplierDetails);
    updateSupplierDetails();

    // Searchable Supplier Dropdown Controller
    const supplierDropdownBtn = document.getElementById('supplierDropdownBtn');
    const supplierDropdownSelectedText = document.getElementById('supplierDropdownSelectedText');
    const supplierSearchInput = document.getElementById('supplierSearchInput');
    const supplierOptItems = document.querySelectorAll('.supplier-opt-item');
    const supplierNoResults = document.getElementById('supplierNoResults');
    const supplierDropdown = document.getElementById('supplierSearchDropdown');

    if (supplierDropdown && supplierSearchInput) {
        supplierDropdown.addEventListener('shown.bs.dropdown', function () {
            supplierSearchInput.focus();
            supplierSearchInput.select();
        });

        supplierSearchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            supplierOptItems.forEach(item => {
                const searchData = item.dataset.search || '';
                const isDefault = item.dataset.id === '';
                if (isDefault) {
                    item.style.display = query === '' ? '' : 'none';
                    return;
                }
                if (query === '' || searchData.includes(query)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (supplierNoResults) {
                supplierNoResults.classList.toggle('d-none', visibleCount > 0 || query === '');
            }
        });

        supplierOptItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const id = this.dataset.id || '';
                const displayText = this.dataset.display || '-- Select registered supplier --';

                supplierOptItems.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');

                if (supplierDropdownSelectedText) {
                    supplierDropdownSelectedText.textContent = displayText;
                }

                supplierSelect.value = id;
                supplierSelect.dispatchEvent(new Event('change', { bubbles: true }));

                if (supplierDropdownBtn) {
                    const bsDropdown = bootstrap.Dropdown.getInstance(supplierDropdownBtn) || new bootstrap.Dropdown(supplierDropdownBtn);
                    bsDropdown.hide();
                }
            });
        });
    }
    fetch('<?= url("api/sellers/next-roll-codes") ?>')
        .then(response => { if (!response.ok) throw new Error('Unable to fetch roll code sequence.'); return response.json(); })
        .then(response => {
            if (!response.success || !response.data || !response.data.prefix || !Number.isInteger(Number(response.data.start_seq))) throw new Error('Invalid roll code sequence response.');
            sequencePrefix = response.data.prefix;
            nextSequence = Number(response.data.start_seq);
            updateCodes();
        })
        .catch(error => { console.error(error); alert('Could not load the next fabric roll code. Refresh the page before recording the purchase.'); });

    form.addEventListener('submit', event => {
        const hasRoll = rollsContainer.querySelector('.roll-card');
        const hasSupply = suppliesContainer.querySelector('.supply-row');
        if (!hasRoll && !hasSupply) {
            event.preventDefault();
            alert('Add at least one fabric roll or general supply.');
            return;
        }
        if (hasRoll && !document.getElementById('fabricDescription').value.trim()) {
            event.preventDefault();
            document.getElementById('fabricDescription').focus();
            return;
        }

        // Pack items into items_payload JSON to completely avoid PHP max_input_vars limit!
        const itemsPayload = [];
        rollsContainer.querySelectorAll('.roll-card').forEach((card, idx) => {
            const photoInput = card.querySelector('.roll-photo');
            let formIdx = String(idx);
            if (photoInput && photoInput.files && photoInput.files.length > 0) {
                photoInput.name = `roll_photo_${idx}`;
                formIdx = String(idx);
            } else if (photoInput) {
                photoInput.removeAttribute('name');
            }

            itemsPayload.push({
                item_type: 'FABRIC_ROLL',
                roll_number: card.querySelector('.roll-number')?.value?.trim() || ('ROLL #' + (idx + 1)),
                color: card.querySelector('.roll-color')?.value?.trim() || '',
                design_name: card.querySelector('.roll-design')?.value?.trim() || '',
                design_count: Number(card.querySelector('.roll-design-count')?.value) || 1,
                quantity: Number(card.querySelector('.roll-quantity')?.value) || 1,
                weight_kg: Number(card.querySelector('.roll-weight')?.value) || 0,
                unit_price: Number(card.querySelector('.roll-price')?.value) || 0,
                hsn_code: card.querySelector('.roll-hsn')?.value?.trim() || '',
                gst_type: card.querySelector('.item-gst-type')?.value || 'EXEMPTED',
                tax_mode: card.querySelector('.item-tax-mode')?.value || 'PERCENT',
                tax_value: card.querySelector('.item-tax-value')?.value || '0',
                cgst_tax_value: card.querySelector('.item-cgst-value')?.value || '0',
                form_index: formIdx
            });
        });

        suppliesContainer.querySelectorAll('.supply-row').forEach((row, idx) => {
            const unitSelect = row.querySelector('.supply-unit');
            const unit = unitSelect ? unitSelect.value : 'PCS';
            const customUnit = row.querySelector('.custom-unit-name')?.value?.trim() || '';
            itemsPayload.push({
                item_type: 'GENERAL_SUPPLY',
                item_name: row.querySelector('.supply-name')?.value?.trim() || '',
                hsn_code: row.querySelector('.supply-hsn')?.value?.trim() || '',
                quantity: Number(row.querySelector('.supply-quantity')?.value) || 1,
                unit: unit === 'OTHER' ? customUnit : unit,
                unit_price: Number(row.querySelector('.supply-price')?.value) || 0,
                gst_type: row.querySelector('.item-gst-type')?.value || 'EXEMPTED',
                tax_mode: row.querySelector('.item-tax-mode')?.value || 'PERCENT',
                tax_value: row.querySelector('.item-tax-value')?.value || '0',
                cgst_tax_value: row.querySelector('.item-cgst-value')?.value || '0'
            });
        });

        let payloadInput = document.getElementById('itemsPayloadInput');
        if (!payloadInput) {
            payloadInput = document.createElement('input');
            payloadInput.type = 'hidden';
            payloadInput.name = 'items_payload';
            payloadInput.id = 'itemsPayloadInput';
            form.appendChild(payloadInput);
        }
        payloadInput.value = JSON.stringify(itemsPayload);
    });

    // Tax Invoice Document Preview
    let selectedDocBlobUrl = null;
    const docInput = document.getElementById('purchaseDocument');
    const docPreviewContainer = document.getElementById('documentFilePreviewContainer');
    const docFilenameSpan = document.getElementById('docPreviewFilename');
    const docImagePreview = document.getElementById('documentImagePreview');
    const docPreviewImage = document.getElementById('docPreviewImage');

    if (docInput) {
        docInput.addEventListener('change', function() {
            if (selectedDocBlobUrl) {
                URL.revokeObjectURL(selectedDocBlobUrl);
                selectedDocBlobUrl = null;
            }
            const file = docInput.files && docInput.files[0];
            if (file) {
                selectedDocBlobUrl = URL.createObjectURL(file);
                if (docFilenameSpan) docFilenameSpan.textContent = file.name;
                const isImage = /^image\/(jpeg|png|webp)$/i.test(file.type) || /\.(jpe?g|png|webp)$/i.test(file.name);
                if (docImagePreview && docPreviewImage && isImage) {
                    docPreviewImage.src = selectedDocBlobUrl;
                    docImagePreview.classList.remove('d-none');
                } else if (docImagePreview && docPreviewImage) {
                    docPreviewImage.removeAttribute('src');
                    docImagePreview.classList.add('d-none');
                }
                if (docPreviewContainer) {
                    docPreviewContainer.classList.remove('d-none');
                    docPreviewContainer.classList.add('d-inline-flex');
                }
            } else {
                if (docPreviewImage) docPreviewImage.removeAttribute('src');
                if (docImagePreview) docImagePreview.classList.add('d-none');
                if (docPreviewContainer) {
                    docPreviewContainer.classList.add('d-none');
                    docPreviewContainer.classList.remove('d-inline-flex');
                }
            }
        });
    }

    window.viewSelectedDocumentFile = function() {
        if (selectedDocBlobUrl) {
            window.open(selectedDocBlobUrl, '_blank', 'noopener,noreferrer');
        }
    };

    window.clearSelectedDocumentFile = function() {
        if (docInput) docInput.value = '';
        if (selectedDocBlobUrl) {
            URL.revokeObjectURL(selectedDocBlobUrl);
            selectedDocBlobUrl = null;
        }
        if (docPreviewImage) docPreviewImage.removeAttribute('src');
        if (docImagePreview) docImagePreview.classList.add('d-none');
        if (docPreviewContainer) {
            docPreviewContainer.classList.add('d-none');
            docPreviewContainer.classList.remove('d-inline-flex');
        }
    };

    addRoll();
})();
</script>
