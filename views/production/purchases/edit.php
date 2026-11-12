<div class="page-header mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit <?= e($purchase['purchase_number']) ?></h2>
    </div>
</div>

<?php
$fabricItems = array_values(array_filter($items, static fn(array $item): bool => $item['item_type'] === 'FABRIC_ROLL'));
$supplyItems = array_values(array_filter($items, static fn(array $item): bool => $item['item_type'] === 'GENERAL_SUPPLY'));
$fabricDescription = $fabricItems[0]['item_name'] ?? '';
$knownUnits = ['PCS', 'KG', 'METRE', 'LITRE', 'BOX', 'PACK', 'PAIR', 'SET', 'BUNDLE'];
$taxTypeForItem = static function (array $item): string {
    $type = $item['gst_type'] ?? '';
    if (in_array($type, ['SGST', 'CGST', 'IGST', 'BOTH', 'EXEMPTED'], true)) return $type;
    if ((float)($item['sgst_amount'] ?? 0) > 0 && (float)($item['cgst_amount'] ?? 0) > 0) return 'BOTH';
    if ((float)($item['igst_amount'] ?? 0) > 0) return 'IGST';
    if ((float)($item['sgst_amount'] ?? 0) > 0) return 'SGST';
    if ((float)($item['cgst_amount'] ?? 0) > 0) return 'CGST';
    return 'EXEMPTED';
};
$taxValueForItem = static function (array $item, string $type): float {
    if ($type === 'IGST') return (float)($item['igst_value'] ?? 0);
    if ($type === 'CGST') return (float)($item['cgst_value'] ?? 0);
    return (float)($item['sgst_value'] ?? 0);
};
?>

<form method="POST" action="<?= url('production/purchases/update/' . $purchase['id']) ?>" enctype="multipart/form-data" id="editProductionPurchaseForm">
    <?= CSRF::field() ?>
    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2 text-primary"></i>Purchase &amp; Supplier Details</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Supplier *</label>
                    <input class="form-control" value="<?= e($purchase['seller_company'] ?: $purchase['seller_name']) ?><?= $purchase['seller_company'] ? ' — ' . e($purchase['seller_name']) : '' ?>" disabled>
                </div>
                <div class="col-md-3"><label class="form-label required">Purchase Date *</label><input type="date" name="purchase_date" class="form-control" value="<?= e($purchase['purchase_date']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Invoice / Bill Number</label><input name="invoice_number" class="form-control" maxlength="100" value="<?= e($purchase['invoice_number']) ?>"></div>
                <div class="col-md-6">
                    <label class="form-label" for="editPurchaseDocument">Tax Invoice / Bill Attachment (Optional)</label>
                    <input type="file" name="document" id="editPurchaseDocument" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="mt-1">
                        <small class="text-muted">JPG, PNG, WEBP or PDF up to 5 MB.</small>
                        <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
                            <?php if (!empty($purchase['document_url'])): ?>
                                <span class="small text-muted me-1" id="existingDocWrapper">Current:
                                    <a href="<?= uploadUrl($purchase['document_url']) ?>" target="_blank" rel="noopener" class="badge bg-primary-subtle text-primary border text-decoration-none px-2 py-1" id="existingDocLink" style="font-size:11px;">
                                        <i class="bi bi-eye me-1"></i>View File
                                    </a>
                                </span>
                            <?php endif; ?>
                            <div id="editDocPreviewContainer" class="d-none align-items-center flex-wrap gap-1">
                                <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size:11px;max-width:190px;">
                                    <i class="bi bi-file-earmark-check flex-shrink-0"></i>
                                    <span id="editDocPreviewFilename" class="text-truncate" style="max-width: 160px;">file.pdf</span>
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2" id="btnViewEditSelectedDoc" onclick="viewEditSelectedDoc()" title="View selected document" style="font-size:12px;line-height:1.2;">
                                    <i class="bi bi-eye me-1"></i>View Selected File
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center p-1" onclick="clearEditSelectedDoc()" title="Clear file" aria-label="Clear selected file" style="width:30px;height:30px;">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="editDocumentImagePreview" class="d-none mt-2">
                        <img id="editDocPreviewImage" class="img-thumbnail" alt="Selected invoice image preview" style="max-width:100%;max-height:220px;object-fit:contain;">
                    </div>
                </div>
                <div class="col-md-6"><label class="form-label">Purchase Notes</label><input name="notes" class="form-control" maxlength="500" value="<?= e($purchase['notes']) ?>"></div>
                <div class="col-12">
                    <div class="border rounded bg-light p-3">
                        <div class="fw-semibold mb-3"><i class="bi bi-person-vcard me-1 text-primary"></i>Selected Supplier Information</div>
                        <div class="row g-3">
                            <div class="col-md-4"><small class="text-muted d-block">Contact Person</small><?= e($purchase['seller_name'] ?: '—') ?></div>
                            <div class="col-md-4"><small class="text-muted d-block">Company</small><?= e($purchase['seller_company'] ?: '—') ?></div>
                            <div class="col-md-4"><small class="text-muted d-block">Phone</small><?= e($purchase['seller_phone'] ?: '—') ?></div>
                            <div class="col-md-4"><small class="text-muted d-block">Email</small><?= e($purchase['seller_email'] ?: '—') ?></div>
                            <div class="col-md-4"><small class="text-muted d-block">GSTIN / Tax ID</small><?= e($purchase['seller_gst_number'] ?: '—') ?></div>
                            <div class="col-md-4"><small class="text-muted d-block">City / State</small><?= e(trim(($purchase['seller_city'] ?? '') . ', ' . ($purchase['seller_state'] ?? ''), ', ') ?: '—') ?></div>
                            <div class="col-12"><small class="text-muted d-block">Business Address</small><?= e($purchase['seller_address'] ?: '—') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-layers-half me-2 text-primary"></i>Fabric Roll &amp; Material Supply Details</h5>
            <small class="text-muted">Sticker ranges for existing rolls stay unchanged.</small>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label required">Fabric / Material Description *</label>
                <input type="text" id="editFabricDescription" class="form-control text-uppercase" maxlength="150" value="<?= e($fabricDescription) ?>" <?= $fabricItems ? 'required' : '' ?>>
                <small class="text-muted">The description is applied to all fabric rolls in this purchase.</small>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2 flex-wrap gap-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-stack me-1 text-primary"></i>Individual Fabric Rolls (<span id="rollCountBadge"><?= count($fabricItems) ?></span>)</h6>
                <span class="text-muted small">Edit roll details, quantity (RQ), and specifications.</span>
            </div>
            <div id="rollsContainer" class="d-flex flex-column gap-3 mb-4">
                <?php foreach ($fabricItems as $index => $item): ?>
                    <?php
                    $key = (int)$item['id'];
                    $gstType = $taxTypeForItem($item);
                    $taxValue = $taxValueForItem($item, $gstType);
                    $cgstValue = $gstType === 'BOTH' ? (float)($item['cgst_value'] ?? 0) : 0;
                    $startCode = $item['start_code'] ?? '';
                    $prefix = '';
                    if (!empty($startCode) && preg_match('/^([A-Za-z]{2}\d{4})/', $startCode, $m)) {
                        $prefix = $m[1];
                    } elseif (!empty($startCode) && preg_match('/^([A-Za-z0-9_-]+?)(\d{2,})$/', $startCode, $m)) {
                        $prefix = $m[1];
                    } else {
                        $prefix = 'FB' . date('my', strtotime($purchase['purchase_date']));
                    }
                    $startSeq = (int)($item['start_seq'] ?? 0);
                    if ($startSeq <= 0 && !empty($startCode) && preg_match('/(\d+)$/', $startCode, $m)) {
                        $startSeq = (int)$m[1];
                    }
                    if ($startSeq <= 0) $startSeq = 1;
                    ?>
                    <div class="roll-card card border shadow-sm" data-item-key="<?= $key ?>" data-item-id="<?= $key ?>" data-sequence-start="<?= $startSeq ?>" data-code-prefix="<?= e($prefix) ?>" data-start-code="<?= e($item['start_code'] ?? '') ?>" data-photo-url="<?= e(!empty($item['photo_url']) ? uploadUrl($item['photo_url']) : '') ?>">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <strong><i class="bi bi-layers text-primary me-1"></i><span class="roll-title">ROLL #<?= $index + 1 ?></span>
                                <span class="badge bg-dark roll-code-text"><?= e($item['start_code'] ?? '') ?><?= !empty($item['end_code']) && $item['end_code'] !== ($item['start_code'] ?? '') ? '–' . e($item['end_code']) : '' ?></span>
                            </strong>
                        </div>
                        <div class="card-body p-3">
                            <input type="hidden" name="items[<?= $key ?>][id]" value="<?= $key ?>">
                            <input type="hidden" name="items[<?= $key ?>][item_type]" value="FABRIC_ROLL">
                            <input type="hidden" name="items[<?= $key ?>][item_name]" class="roll-item-name" value="<?= e($item['item_name']) ?>">
                            <input type="hidden" name="items[<?= $key ?>][unit]" value="PCS">
                            <div class="row g-3">
                                <div class="col-lg-2 col-md-4"><label class="form-label required">Roll No. / Code *</label><input name="items[<?= $key ?>][roll_number]" class="form-control roll-number text-uppercase" maxlength="50" value="<?= e($item['roll_number']) ?>" required></div>
                                <div class="col-lg-2 col-md-4"><label class="form-label required">Color / Shade *</label><input name="items[<?= $key ?>][color]" class="form-control roll-color text-uppercase" maxlength="100" value="<?= e($item['color']) ?>" required></div>
                                <div class="col-lg-2 col-md-4"><label class="form-label required">Design Name / Pattern *</label><input name="items[<?= $key ?>][design_name]" class="form-control roll-design text-uppercase" maxlength="150" value="<?= e($item['design_name']) ?>" required></div>
                                <div class="col-lg-1 col-md-3"><label class="form-label required">Designs *</label><input type="text" inputmode="numeric" pattern="[0-9]+" data-min="1" data-step="1" name="items[<?= $key ?>][design_count]" class="form-control roll-design-count numeric-text-input" value="<?= (int)$item['design_count'] ?>" required></div>
                                <div class="col-lg-1 col-md-3">
                                    <label class="form-label required">RQ *</label>
                                    <input type="text" inputmode="numeric" pattern="[0-9]+" data-min="1" data-step="1" name="items[<?= $key ?>][quantity]" class="form-control roll-quantity numeric-text-input" value="<?= max(1, (int)$item['quantity']) ?>" required>
                                </div>
                                <div class="col-lg-2 col-md-3"><label class="form-label required">Weight (kg) *</label><div class="input-group"><input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0.01" data-step="0.01" name="items[<?= $key ?>][weight_kg]" class="form-control roll-weight numeric-text-input" value="<?= e($item['weight_kg']) ?>" required><span class="input-group-text">kg</span></div></div>
                                <div class="col-lg-2 col-md-3"><label class="form-label required">Price (₹) *</label><div class="input-group"><span class="input-group-text">₹</span><input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-step="0.01" name="items[<?= $key ?>][unit_price]" class="roll-price form-control numeric-text-input" value="<?= e($item['unit_price']) ?>" required></div></div>
                                <div class="col-lg-2 col-md-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0">HSN Code</label>
                                        <button type="button" class="btn btn-link p-0 text-decoration-none small text-primary fw-semibold btn-add-hsn-trigger" style="font-size: 11px;" title="Add new HSN Code">
                                            <i class="bi bi-plus-circle me-0.5"></i>+ Add HSN
                                        </button>
                                    </div>
                                    <div class="dropdown hsn-dropdown w-100 position-relative">
                                        <input type="hidden" name="items[<?= $key ?>][hsn_code]" class="roll-hsn" value="<?= e($item['hsn_code'] ?? '') ?>">
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
                                <div class="col-lg-2 col-md-3"><label class="form-label">GST</label><select name="items[<?= $key ?>][gst_type]" class="form-select item-gst-type">
                                    <?php foreach (['SGST'=>'SGST','IGST'=>'IGST','CGST'=>'CGST','BOTH'=>'Both (CGST and SGST)','EXEMPTED'=>'Exempted'] as $type => $label): ?><option value="<?= e($type) ?>" <?= $gstType === $type ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                                </select></div>
                                <div class="col-lg-3 col-md-4 tax-mode-field <?= ($item['tax_input_type'] ?? 'PERCENT') === '' || $gstType === 'EXEMPTED' ? 'd-none' : '' ?>"><label class="form-label">Tax Input Type</label><select name="items[<?= $key ?>][tax_mode]" class="form-select item-tax-mode"><option value="PERCENT" <?= ($item['tax_input_type'] ?? '') !== 'AMOUNT' ? 'selected' : '' ?>>Percentage (%)</option><option value="AMOUNT" <?= ($item['tax_input_type'] ?? '') === 'AMOUNT' ? 'selected' : '' ?>>Amount (₹)</option></select></div>
                                <div class="col-lg-3 col-md-4 tax-value-field <?= $gstType === 'EXEMPTED' ? 'd-none' : '' ?>"><label class="form-label item-tax-label"><?= $gstType === 'BOTH' ? 'SGST' : e($gstType) ?></label><input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-max="100" data-step="0.01" name="items[<?= $key ?>][tax_value]" class="form-control item-tax-value numeric-text-input" value="<?= e($taxValue) ?>"></div>
                                <div class="col-lg-3 col-md-4 tax-cgst-field <?= $gstType !== 'BOTH' ? 'd-none' : '' ?>"><label class="form-label item-cgst-label">CGST</label><input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-max="100" data-step="0.01" name="items[<?= $key ?>][cgst_tax_value]" class="form-control item-cgst-value numeric-text-input" value="<?= e($cgstValue) ?>"></div>
                                <div class="col-md-6">
                                    <label class="form-label">Roll Sample Photo <small class="text-muted">(Optional — JPG, PNG, WEBP)</small></label>
                                    <input type="file" name="roll_photo_<?= $key ?>" class="form-control roll-photo" accept=".jpg,.jpeg,.png,.webp">
                                    <?php if (!empty($item['photo_url'])): ?><small class="d-block mt-1"><a href="<?= uploadUrl($item['photo_url']) ?>" target="_blank" rel="noopener">View current roll photo</a></small><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                    <span class="fw-bold small"><i class="bi bi-table me-1 text-primary"></i>Fabric Rolls Inventory &amp; Details Table</span>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-dark" id="previewPurchaseLabels"><i class="bi bi-qr-code text-warning me-1"></i>Preview Labels</button>
                        <span class="badge bg-secondary-subtle text-secondary border" id="tableRollCountBadge"><?= count($fabricItems) ?> <?= count($fabricItems) === 1 ? 'Roll' : 'Rolls' ?></span>
                    </div>
                </div>
                <div class="table-responsive"><table class="table table-hover table-bordered align-middle mb-0" style="font-size:13px">
                    <thead class="table-light small text-uppercase"><tr><th class="text-center">#</th><th>Photo</th><th>Roll Code</th><th>HSN</th><th>Color / Shade</th><th>Design / Pattern</th><th class="text-center">Designs</th><th class="text-end">Total RQ</th><th class="text-end">Weight</th><th class="text-end">Price</th><th>GST Type</th><th class="text-end">Tax</th><th class="text-end bg-success-subtle text-success">Total Value</th><th class="text-center">Status</th></tr></thead>
                    <tbody id="rollDetailsTableBody"></tbody>
                </table></div>
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
            <div id="emptySupplyMessage" class="small text-muted <?= $supplyItems ? 'd-none' : '' ?>">No general supplies added.</div>
            <div id="supplyRows" class="d-flex flex-column gap-3 mb-4">
                <?php foreach ($supplyItems as $index => $item): ?>
                    <?php
                    $key = (int)$item['id'];
                    $gstType = $taxTypeForItem($item);
                    $taxValue = $taxValueForItem($item, $gstType);
                    $cgstValue = $gstType === 'BOTH' ? (float)($item['cgst_value'] ?? 0) : 0;
                    $knownUnit = in_array($item['unit'], $knownUnits, true);
                    ?>
                    <div class="supply-card card border shadow-sm supply-row" data-item-id="<?= $key ?>">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <strong><i class="bi bi-box-seam text-success me-1"></i><span class="supply-title">SUPPLY #<?= $index + 1 ?></span>
                                <span class="badge bg-secondary-subtle text-secondary border supply-name-badge <?= empty($item['item_name']) ? 'd-none' : '' ?>"><?= e($item['item_name']) ?></span>
                            </strong>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-supply"><i class="bi bi-trash me-1"></i>Delete</button>
                        </div>
                        <div class="card-body p-3">
                            <input type="hidden" name="items[<?= $key ?>][id]" value="<?= $key ?>">
                            <input type="hidden" name="items[<?= $key ?>][item_type]" value="GENERAL_SUPPLY">
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label required">Item Name / Description *</label>
                                    <input name="items[<?= $key ?>][item_name]" class="form-control supply-name text-uppercase" maxlength="255" value="<?= e($item['item_name']) ?>" required>
                                </div>
                                <div class="col-lg-2 col-md-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0">HSN Code</label>
                                        <button type="button" class="btn btn-link p-0 text-decoration-none small text-primary fw-semibold btn-add-hsn-trigger" style="font-size: 11px;" title="Add new HSN Code">
                                            <i class="bi bi-plus-circle me-0.5"></i>+ Add HSN
                                        </button>
                                    </div>
                                    <div class="dropdown hsn-dropdown w-100 position-relative">
                                        <input type="hidden" name="items[<?= $key ?>][hsn_code]" class="supply-hsn" value="<?= e($item['hsn_code'] ?? '') ?>">
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
                                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0.001" data-step="0.001" name="items[<?= $key ?>][quantity]" class="form-control supply-quantity numeric-text-input" value="<?= e($item['quantity']) ?>" required>
                                </div>
                                <div class="col-lg-2 col-md-3">
                                    <label class="form-label required">Unit *</label>
                                    <select name="items[<?= $key ?>][unit]" class="form-select supply-unit" required>
                                        <?php foreach (['PCS'=>'Pieces','KG'=>'Kilograms','METRE'=>'Metres','LITRE'=>'Litres','BOX'=>'Boxes','PACK'=>'Packs','PAIR'=>'Pairs','SET'=>'Sets','BUNDLE'=>'Bundles'] as $unit => $label): ?><option value="<?= e($unit) ?>" <?= $item['unit'] === $unit ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                                        <?php if (!$knownUnit): ?><option value="OTHER" selected>Other</option><?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-3 custom-unit <?= $knownUnit ? 'd-none' : '' ?>">
                                    <label class="form-label required">Unit Name *</label>
                                    <input name="items[<?= $key ?>][custom_unit]" class="form-control custom-unit-name text-uppercase" maxlength="30" value="<?= !$knownUnit ? e($item['unit']) : '' ?>" <?= !$knownUnit ? 'required' : '' ?>>
                                </div>
                                <div class="col-lg-3 col-md-3">
                                    <label class="form-label required">Unit Price (₹) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-step="0.01" name="items[<?= $key ?>][unit_price]" class="form-control supply-price numeric-text-input" value="<?= e($item['unit_price']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-3">
                                    <label class="form-label">GST</label>
                                    <select name="items[<?= $key ?>][gst_type]" class="form-select item-gst-type"><?php foreach (['SGST'=>'SGST','IGST'=>'IGST','CGST'=>'CGST','BOTH'=>'Both (CGST and SGST)','EXEMPTED'=>'Exempted'] as $type => $label): ?><option value="<?= e($type) ?>" <?= $gstType === $type ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                </div>
                                <div class="col-lg-2 col-md-3 tax-mode-field <?= $gstType === 'EXEMPTED' ? 'd-none' : '' ?>">
                                    <label class="form-label">Tax Input Type</label>
                                    <select name="items[<?= $key ?>][tax_mode]" class="form-select item-tax-mode"><option value="PERCENT" <?= ($item['tax_input_type'] ?? '') !== 'AMOUNT' ? 'selected' : '' ?>>Percentage (%)</option><option value="AMOUNT" <?= ($item['tax_input_type'] ?? '') === 'AMOUNT' ? 'selected' : '' ?>>Amount (₹)</option></select>
                                </div>
                                <div class="col-lg-2 col-md-3 tax-value-field <?= $gstType === 'EXEMPTED' ? 'd-none' : '' ?>">
                                    <label class="form-label item-tax-label"><?= $gstType === 'BOTH' ? 'SGST' : e($gstType) ?></label>
                                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-step="0.01" name="items[<?= $key ?>][tax_value]" class="form-control item-tax-value numeric-text-input" value="<?= e($taxValue) ?>">
                                </div>
                                <div class="col-lg-2 col-md-3 tax-cgst-field <?= $gstType !== 'BOTH' ? 'd-none' : '' ?>">
                                    <label class="form-label item-cgst-label">CGST</label>
                                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-min="0" data-step="0.01" name="items[<?= $key ?>][cgst_tax_value]" class="form-control item-cgst-value numeric-text-input" value="<?= e($cgstValue) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card border shadow-sm mt-3">
                <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between"><span class="fw-bold small"><i class="bi bi-table me-1 text-success"></i>Other Garment Supplies Details Table</span><span class="badge bg-secondary-subtle text-secondary border" id="supplyTableCountBadge">0 Items</span></div>
                <div class="table-responsive"><table class="table table-hover table-bordered align-middle mb-0" style="font-size:13px">
                    <thead class="table-light small text-uppercase"><tr><th class="text-center">#</th><th>Item Name / Description</th><th>HSN</th><th class="text-end">Quantity</th><th>Unit</th><th class="text-end">Unit Price</th><th class="text-end">Base Value</th><th>GST Type</th><th class="text-end">Tax</th><th class="text-end bg-success-subtle text-success">Total Value</th></tr></thead>
                    <tbody id="supplyDetailsTableBody"></tbody><tfoot id="supplyDetailsTableFoot" class="table-light fw-bold"></tfoot>
                </table></div>
            </div>
        </div>
    </div>

    <div class="card border shadow-sm mb-4"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2"><span>Subtotal</span><strong id="purchaseSubtotal">₹0.00</strong></div>
        <div class="d-flex justify-content-between align-items-center mb-2"><span>Taxes</span><strong id="purchaseTaxes">₹0.00</strong></div>
        <hr class="my-2">
        <div class="d-flex justify-content-between align-items-center"><strong>Total Price</strong><strong class="fs-5 text-success" id="purchaseTotal">₹0.00</strong></div>
    </div></div>
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= url('production/purchases') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Purchase Changes</button>
    </div>
</form>

<div class="modal fade" id="editPurchaseLabelsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white"><h5 class="modal-title"><i class="bi bi-qr-code me-2 text-warning"></i>Fabric Roll Labels Preview</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body bg-light"><div id="editPurchaseLabelCards" class="d-flex flex-wrap justify-content-center gap-3"></div></div>
            <div class="modal-footer"><span class="small text-muted me-auto" id="editPurchaseLabelCount"></span><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
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
                        <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" data-step="0.01" data-min="0" data-max="28" id="quickHsnGstInput" class="form-control fw-bold numeric-text-input" value="5.00" required>
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
<template id="editSupplyTemplate">
    <div class="supply-card card border shadow-sm mb-3 supply-row">
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
                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" class="form-control supply-quantity numeric-text-input" data-min="0.001" data-step="0.001" value="1" required>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label required">Unit *</label>
                    <select class="form-select supply-unit" required>
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
                        <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" class="form-control supply-price numeric-text-input" data-min="0" data-step="0.01" value="0" required>
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
                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" class="form-control item-tax-value numeric-text-input" data-min="0" data-max="100" data-step="0.01" value="0">
                </div>
                <div class="col-lg-2 col-md-3 tax-cgst-field d-none">
                    <label class="form-label item-cgst-label">CGST</label>
                    <input type="text" inputmode="decimal" pattern="([0-9]+([.][0-9]*)?|[.][0-9]+)" class="form-control item-cgst-value numeric-text-input" data-min="0" data-max="100" data-step="0.01" value="0">
                </div>
            </div>
        </div>
    </div>
</template>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
(() => {
    const form = document.getElementById('editProductionPurchaseForm');
    const rollsContainer = document.getElementById('rollsContainer');
    const suppliesContainer = document.getElementById('supplyRows');
    window.hsnCodesList = <?= json_encode($hsnCodes ?? []) ?>;
    const money = value => '₹' + (Number(value) || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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

    function calculateTax(row, base) {
        const type = row.querySelector('.item-gst-type').value;
        const mode = row.querySelector('.item-tax-mode').value;
        const value = Number(row.querySelector('.item-tax-value').value) || 0;
        const cgstValue = Number(row.querySelector('.item-cgst-value').value) || 0;
        const taxAmount = amount => mode === 'AMOUNT' ? amount : Math.round(base * amount) / 100;
        const sgst = ['SGST', 'BOTH'].includes(type) ? taxAmount(value) : 0;
        const cgst = type === 'CGST' ? taxAmount(value) : (type === 'BOTH' ? taxAmount(cgstValue) : 0);
        const igst = type === 'IGST' ? taxAmount(value) : 0;
        return {type, sgst, cgst, igst, amount: sgst + cgst + igst};
    }

    function updateTaxFields(row) {
        const type = row.querySelector('.item-gst-type').value;
        const mode = row.querySelector('.item-tax-mode').value;
        const active = type !== 'EXEMPTED';
        row.querySelector('.tax-mode-field').classList.toggle('d-none', !active);
        row.querySelector('.tax-value-field').classList.toggle('d-none', !active);
        row.querySelector('.tax-cgst-field').classList.toggle('d-none', type !== 'BOTH');
        row.querySelector('.item-tax-value').required = active;
        row.querySelector('.item-cgst-value').required = type === 'BOTH';
        row.querySelector('.item-tax-label').textContent = `${type === 'BOTH' ? 'SGST' : type} (${mode === 'PERCENT' ? '%' : '₹'})`;
        row.querySelector('.item-cgst-label').textContent = `CGST (${mode === 'PERCENT' ? '%' : '₹'})`;
        [row.querySelector('.item-tax-value'), row.querySelector('.item-cgst-value')].forEach(input => {
            if (mode === 'PERCENT') input.dataset.max = '100';
            else delete input.dataset.max;
        });
    }

    function updateSummary() {
        const rolls = Array.from(rollsContainer.querySelectorAll('.roll-card'));
        const supplyRows = Array.from(suppliesContainer.querySelectorAll('.supply-row')).filter(row => !row.classList.contains('d-none'));
        const rollTableBody = document.getElementById('rollDetailsTableBody');
        const rollTableRows = [];
        let subtotal = 0, taxes = 0, total = 0, totalWeight = 0, totalRq = 0;
        const colors = new Set(), designs = new Set();
        document.getElementById('editFabricDescription').required = rolls.length > 0;
        rolls.forEach((card, index) => {
            const key = card.dataset.itemKey;
            const nameInput = card.querySelector('.roll-item-name');
            nameInput.value = document.getElementById('editFabricDescription').value.trim().toUpperCase();
            const price = Number(card.querySelector('.roll-price').value) || 0;
            const weight = Number(card.querySelector('.roll-weight').value) || 0;
            const quantity = Number(card.querySelector('.roll-quantity')?.value) || Number(card.querySelector(`[name="items[${key}][quantity]"]`)?.value) || 0;
            const tax = calculateTax(card, price);

            // Dynamically recalculate roll code range based on editable RQ
            const seqStart = Number(card.dataset.sequenceStart) || 1;
            const prefix = card.dataset.codePrefix || '';
            const baseStartCode = card.dataset.startCode || '';
            const endSeq = seqStart + Math.max(1, quantity) - 1;
            let endCode = baseStartCode;
            if (prefix && baseStartCode) {
                const padLen = Math.max(2, baseStartCode.length - prefix.length);
                endCode = prefix + String(endSeq).padStart(padLen, '0');
            }
            const codeBadgeText = (quantity > 1 && endCode !== baseStartCode) ? `${baseStartCode}–${endCode}` : baseStartCode;
            const codeBadge = card.querySelector('.roll-code-text');
            if (codeBadge) {
                codeBadge.textContent = codeBadgeText;
            }

            const photoFile = card.querySelector('.roll-photo').files[0];
            const photoUrl = card.dataset.photoUrl;
            const photoHtml = photoFile
                ? '<span class="text-muted"><i class="bi bi-image"></i></span>'
                : (photoUrl ? `<a href="${safe(photoUrl)}" target="_blank" rel="noopener"><img src="${safe(photoUrl)}" alt="Roll" style="width:38px;height:38px;object-fit:cover"></a>` : '<span class="text-muted"><i class="bi bi-camera"></i></span>');
            subtotal += price;
            taxes += tax.amount;
            total += price + tax.amount;
            totalWeight += weight;
            totalRq += quantity;
            if (card.querySelector('.roll-color').value.trim()) colors.add(card.querySelector('.roll-color').value.trim().toUpperCase());
            if (card.querySelector('.roll-design').value.trim()) designs.add(card.querySelector('.roll-design').value.trim().toUpperCase());
            const taxLabel = tax.type === 'BOTH' ? 'SGST + CGST' : tax.type;
            card.querySelector('.roll-title').textContent = `ROLL #${index + 1}`;
            const hsn = card.querySelector('.roll-hsn')?.value?.trim() || '—';
            rollTableRows.push(`<tr><td class="text-center">${index + 1}</td><td>${photoHtml}</td><td><strong>${safe(card.querySelector('.roll-number').value)}</strong><small class="d-block font-monospace">${safe(codeBadgeText || '—')}</small></td><td><span class="badge bg-light text-dark border font-monospace">${safe(hsn)}</span></td><td>${safe(card.querySelector('.roll-color').value || '—')}</td><td>${safe(card.querySelector('.roll-design').value || '—')}</td><td class="text-center">${Number(card.querySelector('.roll-design-count').value) || 0}</td><td class="text-end">${quantity.toLocaleString('en-IN')} RQ</td><td class="text-end">${weight.toFixed(2)} kg</td><td class="text-end">${money(price)}</td><td>${taxLabel}</td><td class="text-end">${money(tax.amount)}</td><td class="text-end fw-bold text-success">${money(price + tax.amount)}</td><td class="text-center"><span class="badge bg-info-subtle text-info">Existing roll</span></td></tr>`);
        });
        document.getElementById('rollCountBadge').textContent = rolls.length;
        document.getElementById('tableRollCountBadge').textContent = `${rolls.length} ${rolls.length === 1 ? 'Roll' : 'Rolls'}`;
        document.getElementById('summaryTotalRolls').textContent = `${rolls.length} ${rolls.length === 1 ? 'Roll' : 'Rolls'}`;
        document.getElementById('summaryTotalRQ').textContent = `${totalRq.toLocaleString('en-IN')} RQ`;
        document.getElementById('summaryColorsDesigns').textContent = `${colors.size} Colors • ${designs.size} Designs`;
        document.getElementById('summaryTotalWeight').textContent = `${totalWeight.toFixed(2)} kg`;
        document.getElementById('summaryTotalPrice').textContent = money(rolls.reduce((sum, row) => {
            const price = Number(row.querySelector('.roll-price').value) || 0;
            return sum + price + calculateTax(row, price).amount;
        }, 0));
        rollTableBody.innerHTML = rollTableRows.length ? rollTableRows.join('') : '<tr><td colspan="14" class="text-center text-muted py-4">No fabric rolls in this purchase.</td></tr>';

        const supplyTableBody = document.getElementById('supplyDetailsTableBody');
        const supplyTableFoot = document.getElementById('supplyDetailsTableFoot');
        const supplyTableRows = [];
        let supplyBase = 0, supplyTax = 0, supplyTotal = 0;
        supplyRows.forEach((row, index) => {
            const quantity = Number(row.querySelector('.supply-quantity').value) || 0;
            const unitPrice = Number(row.querySelector('.supply-price').value) || 0;
            const hsn = row.querySelector('.supply-hsn')?.value?.trim() || '—';
            const base = quantity * unitPrice;
            const tax = calculateTax(row, base);
            const unitOption = row.querySelector('.supply-unit').selectedOptions[0];
            const unit = unitOption.value === 'OTHER' ? row.querySelector('.custom-unit-name').value : unitOption.textContent;
            supplyBase += base;
            supplyTax += tax.amount;
            supplyTotal += base + tax.amount;
            supplyTableRows.push(`<tr><td class="text-center">${index + 1}</td><td>${safe(row.querySelector('.supply-name').value.trim() || '—')}</td><td><span class="badge bg-light text-dark border font-monospace">${safe(hsn)}</span></td><td class="text-end">${quantity.toLocaleString('en-IN', {maximumFractionDigits: 3})}</td><td>${safe(unit)}</td><td class="text-end">${money(unitPrice)}</td><td class="text-end">${money(base)}</td><td>${tax.type === 'BOTH' ? 'SGST + CGST' : tax.type}</td><td class="text-end">${money(tax.amount)}</td><td class="text-end fw-bold text-success">${money(base + tax.amount)}</td></tr>`);
        });
        document.getElementById('supplyTableCountBadge').textContent = `${supplyRows.length} ${supplyRows.length === 1 ? 'Item' : 'Items'}`;
        supplyTableBody.innerHTML = supplyTableRows.length ? supplyTableRows.join('') : '<tr><td colspan="10" class="text-center text-muted py-4">Add garment supplies to see their purchase details.</td></tr>';
        supplyTableFoot.innerHTML = supplyTableRows.length ? `<tr><td colspan="6" class="text-end">Totals</td><td class="text-end">${money(supplyBase)}</td><td></td><td class="text-end">${money(supplyTax)}</td><td class="text-end text-success">${money(supplyTotal)}</td></tr>` : '';

        subtotal += supplyBase;
        taxes += supplyTax;
        total += supplyTotal;
        document.getElementById('purchaseSubtotal').textContent = money(subtotal);
        document.getElementById('purchaseTaxes').textContent = money(taxes);
        document.getElementById('purchaseTotal').textContent = money(total);
    }

    function updateSupplyTitles() {
        const visibleRows = Array.from(suppliesContainer.querySelectorAll('.supply-row:not(.d-none)'));
        visibleRows.forEach((row, i) => {
            const title = row.querySelector('.supply-title');
            if (title) title.textContent = 'SUPPLY #' + (i + 1);
        });
    }

    function addSupplyRow() {
        const row = document.getElementById('editSupplyTemplate').content.firstElementChild.cloneNode(true);
        const key = `new-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
        const itemKey = `new_${key.replace(/[^A-Za-z0-9_]/g, '')}`;
        const names = {'.supply-name':'item_name','.supply-hsn':'hsn_code','.supply-quantity':'quantity','.supply-unit':'unit','.supply-price':'unit_price','.item-gst-type':'gst_type','.item-tax-mode':'tax_mode','.item-tax-value':'tax_value','.item-cgst-value':'cgst_tax_value'};
        Object.entries(names).forEach(([selector, field]) => {
            const input = row.querySelector(selector);
            if (input) input.name = `items[${itemKey}][${field}]`;
        });
        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = `items[${itemKey}][item_type]`;
        typeInput.value = 'GENERAL_SUPPLY';
        row.appendChild(typeInput);
        initHsnDropdown(row, '.supply-hsn');
        configureSupplyRow(row);
        suppliesContainer.appendChild(row);
        document.getElementById('emptySupplyMessage').classList.add('d-none');
        updateSupplyTitles();
        updateSummary();
    }

    function previewLabels() {
        const cards = document.getElementById('editPurchaseLabelCards');
        cards.innerHTML = '';
        let labelCount = 0;
        const material = document.getElementById('editFabricDescription').value.trim().toUpperCase() || 'FABRIC';
        const supplier = <?= json_encode($purchase['seller_company'] ?: ($purchase['seller_name'] ?? 'SUPPLIER'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const poNumber = <?= json_encode($purchase['purchase_number'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const purchaseDate = <?= json_encode(date('d M Y', strtotime($purchase['purchase_date'])), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        rollsContainer.querySelectorAll('.roll-card').forEach((card, index) => {
            const quantity = Number(card.querySelector(`[name="items[${card.dataset.itemKey}][quantity]"]`)?.value) || 1;
            const start = Number(card.dataset.sequenceStart) || 1;
            let prefix = card.dataset.codePrefix;
            if (!prefix) {
                const sc = card.dataset.startCode || '';
                const m = sc.match(/^([A-Za-z]{2}\d{4})/);
                prefix = m ? m[1] : (sc.replace(/\d{2,}$/, '') || 'FB');
            }
            const rollNum = card.querySelector('.roll-number')?.value?.trim() || `ROLL #${index + 1}`;
            const color = (card.querySelector('.roll-color')?.value?.trim() || 'STANDARD').toUpperCase();
            const design = (card.querySelector('.roll-design')?.value?.trim() || '').toUpperCase();
            const totalWeight = Number(card.querySelector('.roll-weight')?.value) || 0;
            const pieceWeight = quantity > 0 ? (totalWeight / quantity).toFixed(2) : totalWeight.toFixed(2);

            for (let piece = 0; piece < quantity; piece++) {
                const code = `${prefix}${String(start + piece).padStart(2, '0')}`;
                const pieceLabel = quantity > 1 ? `${rollNum} (${piece + 1}/${quantity})` : rollNum;

                const sticker = document.createElement('div');
                sticker.className = 'roll-label-card';
                sticker.innerHTML = `
                    <div class="label-header">
                        <div class="company-tag" title="${safe(supplier)}">${safe(supplier)}</div>
                        <div class="po-tag">${safe(poNumber)}</div>
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
                        <span>GRM MFG · ${safe(purchaseDate)}</span>
                    </div>
                `;
                cards.appendChild(sticker);
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
        document.getElementById('editPurchaseLabelCount').textContent = `${labelCount} ${labelCount === 1 ? 'label' : 'labels'}`;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editPurchaseLabelsModal')).show();
    }

    function configureSupplyRow(row) {
        row.querySelectorAll('input,select').forEach(input => {
            input.addEventListener('input', updateSummary);
            input.addEventListener('change', updateSummary);
        });
        row.querySelector('.supply-name')?.addEventListener('input', (e) => {
            const badge = row.querySelector('.supply-name-badge');
            if (badge) {
                const val = e.target.value.trim().toUpperCase();
                badge.textContent = val;
                badge.classList.toggle('d-none', !val);
            }
        });
        row.querySelector('.item-gst-type').addEventListener('change', () => { updateTaxFields(row); updateSummary(); });
        row.querySelector('.item-tax-mode').addEventListener('change', () => { updateTaxFields(row); updateSummary(); });
        row.querySelector('.supply-unit').addEventListener('change', event => {
            const custom = event.target.value === 'OTHER';
            const field = row.querySelector('.custom-unit');
            const input = row.querySelector('.custom-unit-name');
            field.classList.toggle('d-none', !custom);
            input.required = custom;
            if (custom) input.name = event.target.name.replace('[unit]', '[custom_unit]');
            else input.removeAttribute('name');
            updateSummary();
        });
        row.querySelector('.remove-supply').addEventListener('click', () => {
            const itemId = row.dataset.itemId;
            if (itemId) {
                let removeField = row.querySelector('.remove-item-value');
                if (!removeField) {
                    removeField = document.createElement('input');
                    removeField.type = 'hidden';
                    removeField.name = `items[${itemId}][remove_item]`;
                    removeField.className = 'remove-item-value';
                    removeField.value = '1';
                    row.appendChild(removeField);
                }
                row.classList.add('d-none');
                row.querySelectorAll('input,select').forEach(input => {
                    if (!input.classList.contains('remove-item-value') && !input.name.endsWith('[id]') && !input.name.endsWith('[item_type]')) input.disabled = true;
                });
            } else {
                row.remove();
            }
            if (!suppliesContainer.querySelector('.supply-row:not(.d-none)')) document.getElementById('emptySupplyMessage').classList.remove('d-none');
            updateSupplyTitles();
            updateSummary();
        });
        updateTaxFields(row);
    }

    rollsContainer.querySelectorAll('.roll-card').forEach(card => {
        initHsnDropdown(card, '.roll-hsn');
        card.querySelectorAll('input,select').forEach(input => {
            input.addEventListener('input', updateSummary);
            input.addEventListener('change', updateSummary);
        });
        card.querySelector('.item-gst-type').addEventListener('change', () => { updateTaxFields(card); updateSummary(); });
        card.querySelector('.item-tax-mode').addEventListener('change', () => { updateTaxFields(card); updateSummary(); });
        updateTaxFields(card);
    });
    suppliesContainer.querySelectorAll('.supply-row').forEach(row => {
        initHsnDropdown(row, '.supply-hsn');
        configureSupplyRow(row);
    });
    updateSupplyTitles();
    document.getElementById('editFabricDescription').addEventListener('input', updateSummary);
    document.getElementById('addSupply').addEventListener('click', addSupplyRow);
    document.getElementById('previewPurchaseLabels').addEventListener('click', previewLabels);
    form.addEventListener('submit', event => {
        const hasRoll = rollsContainer.querySelector('.roll-card');
        const hasSupply = suppliesContainer.querySelector('.supply-row:not(.d-none)');
        if (!hasRoll && !hasSupply) {
            event.preventDefault();
            alert('Add at least one fabric roll or general supply.');
            return;
        }

        // Clean up empty roll photo inputs so max_file_uploads is never exceeded
        rollsContainer.querySelectorAll('.roll-card').forEach(card => {
            const photoInput = card.querySelector('.roll-photo');
            if (photoInput && (!photoInput.files || photoInput.files.length === 0)) {
                photoInput.removeAttribute('name');
            }
        });
    });

    // Tax Invoice Document Preview
    let selectedDocBlobUrl = null;
    const docInput = document.getElementById('editPurchaseDocument');
    const docPreviewContainer = document.getElementById('editDocPreviewContainer');
    const docFilenameSpan = document.getElementById('editDocPreviewFilename');
    const docImagePreview = document.getElementById('editDocumentImagePreview');
    const docPreviewImage = document.getElementById('editDocPreviewImage');

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

    window.viewEditSelectedDoc = function() {
        if (selectedDocBlobUrl) {
            window.open(selectedDocBlobUrl, '_blank', 'noopener,noreferrer');
        }
    };

    window.clearEditSelectedDoc = function() {
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

    updateSummary();
})();
</script>
