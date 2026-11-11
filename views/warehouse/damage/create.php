<style>
.custom-modal-scroll {
    max-height: 220px;
    overflow-y: auto !important;
    overflow-x: hidden;
    padding-right: 4px;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}
.custom-modal-scroll::-webkit-scrollbar {
    width: 6px;
}
.custom-modal-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.custom-modal-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-modal-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Damage QR Scanner Styles - exact matching allocation scanner flow */
.scanner-active-glow {
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
    border-color: #dc3545 !important;
}
.card-duplicate-shake {
    animation: duplicateShake 0.5s cubic-bezier(.36,.07,.19,.97) both;
    border-color: #dc3545 !important;
    background-color: #f8d7da !important;
}
@keyframes duplicateShake {
    10%, 90% { transform: translate3d(-2px, 0, 0); }
    20%, 80% { transform: translate3d(3px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
    40%, 60% { transform: translate3d(4px, 0, 0); }
}
.row-scan-highlight {
    animation: rowGlow 1.5s ease-out;
}
@keyframes rowGlow {
    0% { background-color: rgba(25, 135, 84, 0.25); }
    100% { background-color: transparent; }
}
#itemsTable th,
#itemsTable td {
    vertical-align: middle !important;
}
.fw-mono {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 mb-0 text-gray-800"><i class="bi bi-qr-code-scan me-2 text-danger"></i>Log Damaged Stock &amp; Returns Entry</h2>
        <p class="text-muted small mb-0">Scan warehouse stock QR tags to dynamically look up styles, inspect available stock, and quarantine damaged pieces or dispatch to rework.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('warehouse/damage') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Damage List
        </a>
    </div>
</div>

<form method="POST" action="<?= url('warehouse/damage/store') ?>" enctype="multipart/form-data" id="damageEntryForm" class="card border-0 shadow-sm rounded-3 p-4">
    <?= CSRF::field() ?>
    <input type="hidden" name="items" id="hiddenItemsJson" value="[]">

    <!-- WAREHOUSE SELECTION -->
    <div class="row g-2 mb-4">
        <div class="col-md-4">
            <label class="form-label fw-bold mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Source Warehouse <span class="text-danger">*</span></label>
            <select name="warehouse_id" class="form-select form-select-lg fw-bold" id="warehouseSelect" required <?= ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1) ? 'disabled' : '' ?>>
                <?php if (!function_exists('currentWarehouseId') || currentWarehouseId() === null && count($warehouses) > 1): ?>
                    <option value="">-- Select Source Warehouse --</option>
                <?php endif; ?>
                <?php foreach ($warehouses as $idx => $wh): ?>
                    <option value="<?= $wh['id'] ?>" <?= ($idx === 0 || (function_exists('currentWarehouseId') && currentWarehouseId() == $wh['id'])) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($wh['name']) ?><?= !empty($wh['code']) ? ' (' . htmlspecialchars($wh['code']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1): ?>
                <input type="hidden" name="warehouse_id" value="<?= htmlspecialchars((string)($warehouses[0]['id'] ?? (function_exists('currentWarehouseId') ? currentWarehouseId() : ''))) ?>">
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- LIVE QR / BARCODE SCANNER BAR (EXACT ALLOCATION FLOW) -->
    <!-- ========================================================================= -->
    <div class="card border-0 shadow-sm mb-4 rounded-3" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.05) 0%, rgba(220, 53, 69, 0.12) 100%); border-left: 4px solid #dc3545 !important;">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger text-white p-2 rounded-circle shadow-sm">
                        <i class="bi bi-qr-code-scan fs-5"></i>
                    </span>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Live QR Code &amp; Barcode Gun Scanner</h6>
                        <small class="text-muted">Scan piece tag sticker or enter tag / SKU (e.g. <code>TTS092601</code>, <code>RMT092606</code>) to dynamically add product &amp; log damaged stock</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="autoFocusSwitch" checked role="switch">
                        <label class="form-check-label small fw-bold text-muted" for="autoFocusSwitch">Continuous Auto-Focus</label>
                    </div>
                    <span class="badge bg-light text-dark border px-2.5 py-1.5 small shadow-sm" id="scanCounterBadge">
                        <i class="bi bi-check2-circle text-success me-1"></i><span id="totalScannedCount" class="fw-bold">0</span> pieces scanned
                    </span>
                </div>
            </div>

            <div class="input-group input-group-lg shadow-sm">
                <span class="input-group-text bg-white border-danger border-opacity-25 text-danger">
                    <i class="bi bi-upc-scan fs-4"></i>
                </span>
                <input type="text" 
                       id="damage_qr_input" 
                       class="form-control form-control-lg border-danger border-opacity-25 font-monospace fw-bold" 
                       placeholder="Scan QR Code or Type Piece Tag / SKU (e.g. TTS092601, RMT092606)..." 
                       autocomplete="off" 
                       autofocus>
                <button type="button" class="btn btn-danger px-4 fw-bold d-flex align-items-center gap-2" id="triggerScanBtn">
                    <i class="bi bi-lightning-charge-fill"></i> Scan / Add
                </button>
                <button type="button" class="btn btn-outline-secondary px-3" id="clearScanInputBtn" title="Clear input">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Dynamic Scan Feedback / Duplicate Warning Alerts -->
            <div id="scanStatusBanner" class="mt-2.5 d-none">
                <!-- Injected via JavaScript -->
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- DAMAGED / REWORK ITEMS TABLE -->
    <!-- ========================================================================= -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-box-seam me-2 text-danger"></i>Damaged / Rework Items Queue</h5>
            <span class="badge bg-danger rounded-pill px-2.5 py-1" id="queueBadgeCount">0 items</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 14%;">QR NO / SKU</th>
                            <th style="width: 14%;">CATEGORY</th>
                            <th style="width: 26%;">PRODUCT NAME / VARIANT <span class="text-danger">*</span></th>
                            <th style="width: 8%;" class="text-center">SIZE</th>
                            <th style="width: 9%;" class="text-center">AVAILABLE</th>
                            <th style="width: 15%;">DAMAGE REASON <span class="text-danger">*</span></th>
                            <th style="width: 8%;" class="text-center">QTY (PCS) <span class="text-danger">*</span></th>
                            <th style="width: 6%;" class="text-center">UNIT (₹)</th>
                            <th style="width: 6%;" class="text-end">LOSS (₹)</th>
                            <th style="width: 4%;" class="text-center">ACTION</th>
                        </tr>
                    </thead>
                    <tbody id="damageItemsContainer">
                        <tr id="emptyRowPlaceholder">
                            <td colspan="10" class="text-center py-5 text-muted">
                                <div class="bg-light d-inline-flex p-4 rounded-circle mb-3 shadow-xs">
                                    <i class="bi bi-upc-scan fs-1 text-danger"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">No items scanned in queue</h6>
                                <p class="small text-muted mb-0">Point barcode gun scanner or enter piece QR tags above to add items into this damage batch.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Summary Bar -->
    <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-light">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-4">
                <div>
                    <small class="text-muted d-block fw-semibold">Total Unique Items</small>
                    <span class="fw-bold text-dark fs-6" id="totalItemsDisplay">0 items</span>
                </div>
                <div>
                    <small class="text-muted d-block fw-semibold">Total Damaged Pieces</small>
                    <span class="badge bg-danger fs-6 px-3 py-2" id="totalQtyDisplay">0 pcs</span>
                </div>
                <div>
                    <small class="text-muted d-block fw-semibold">Total Valuation Loss</small>
                    <span class="fw-bold text-danger fs-6" id="totalLossDisplay">₹0.00</span>
                </div>
            </div>
            <div class="text-muted small">
                <i class="bi bi-shield-check text-success me-1"></i> 
                <strong>Stock Deduction:</strong> Available warehouse inventory will be automatically deducted and quarantined into DAMAGED stock upon confirmation.
            </div>
        </div>
    </div>

    <!-- Defect Notes & Photo Evidence -->
    <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <label class="form-label small fw-bold text-uppercase text-muted">Batch Defect Notes / Inspector Observations</label>
                <textarea name="defect_notes" class="form-control" rows="2" placeholder="e.g. Packing box damaged during inward transit, zippers missing on batch..."></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-uppercase text-muted d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-camera me-1 text-primary"></i>Batch Photo Evidence (Optional)</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.7rem;"><i class="bi bi-cloud-arrow-up me-1"></i>Cloudinary CDN</span>
                </label>
                <input type="file" name="photo" id="damagePhotoInput" class="form-control" accept="image/jpeg,image/png,image/webp">
                <div id="photoPreviewContainer" class="mt-2 d-none text-center">
                    <img id="photoPreviewImg" src="" alt="Damage Preview" class="img-thumbnail shadow-xs rounded" style="max-height: 120px;">
                    <div class="small text-muted mt-1"><i class="bi bi-check-circle-fill text-success me-1"></i>Ready for Cloudinary upload</div>
                </div>
                <small class="text-muted d-block mt-1">Uploaded securely to Cloudinary cloud storage (Max 5MB).</small>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 border-top">
            <div>
                <span class="fw-bold text-dark d-block fs-6">Select Batch Disposition Action:</span>
                <span class="text-muted small">Choose whether to quarantine as warehouse damage or dispatch directly to factory rework.</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('warehouse/damage') ?>" class="btn btn-light px-3 py-2">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </a>

                <!-- FACTORY REWORK BUTTON -->
                <button type="submit" name="action_type" value="rework" class="btn btn-warning px-4 py-2.5 fw-bold text-dark shadow-sm d-inline-flex align-items-center gap-2" id="btnSubmitRework" disabled>
                    <i class="bi bi-arrow-repeat fs-5"></i>
                    <span>Send All to Factory Rework &amp; Deduct Stock</span>
                </button>

                <!-- CONFIRM SCRAP / DAMAGE BUTTON -->
                <button type="submit" name="action_type" value="damage" class="btn btn-danger px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2" id="btnSubmitDamage" disabled>
                    <i class="bi bi-slash-circle fs-5"></i>
                    <span>Log All Damage (Quarantine) &amp; Deduct Stock</span>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
// Available variants registry passed from backend
const variantsList = <?= json_encode($variants ?? []) ?>;

// Global tracking registries matching allocation scanner
const SCANNED_DAMAGE_TAGS = new Set();
const PENDING_DAMAGE_TAGS = new Set();

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function playSuccessSound() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // A5
        osc.frequency.setValueAtTime(1200, audioCtx.currentTime + 0.08); // High chirp
        gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.18);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.18);
    } catch(e) {}
}

function playDuplicateSound() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(280, audioCtx.currentTime);
        osc.frequency.setValueAtTime(200, audioCtx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.18, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.28);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.28);
    } catch(e) {}
}

function getRowScannedQrs(row) {
    if (!row) return [];
    try {
        return JSON.parse(row.dataset.scannedQrs || '[]');
    } catch(e) {
        return [];
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const warehouseSelect = document.getElementById('warehouseSelect');
    const scanInput = document.getElementById('damage_qr_input');
    const triggerScanBtn = document.getElementById('triggerScanBtn');
    const clearScanBtn = document.getElementById('clearScanInputBtn');
    const autoFocusSwitch = document.getElementById('autoFocusSwitch');
    const totalScannedBadge = document.getElementById('totalScannedCount');
    const damageForm = document.getElementById('damageEntryForm');

    function updateGlobalScannedCounter() {
        if (totalScannedBadge) {
            totalScannedBadge.textContent = SCANNED_DAMAGE_TAGS.size;
        }
    }

    function showScanAlert(type, message) {
        const banner = document.getElementById('scanStatusBanner');
        if (!banner) return;

        let bgClass = 'alert-success border-success text-success-emphasis';
        let icon = 'bi-check-circle-fill text-success';
        if (type === 'duplicate' || type === 'warning') {
            bgClass = 'alert-warning border-warning text-warning-emphasis';
            icon = 'bi-exclamation-triangle-fill text-warning';
        } else if (type === 'danger' || type === 'error') {
            bgClass = 'alert-danger border-danger text-danger-emphasis';
            icon = 'bi-x-circle-fill text-danger';
        }

        banner.className = `alert ${bgClass} py-2 px-3 mb-0 rounded-3 shadow-sm d-flex align-items-center justify-content-between`;
        banner.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <i class="bi ${icon} fs-5"></i>
                <div class="small">${message}</div>
            </div>
            <button type="button" class="btn-close btn-sm ms-2" onclick="this.closest('.alert').classList.add('d-none')"></button>
        `;
        banner.classList.remove('d-none');
    }

    function clearAndRefocusScanInput() {
        if (scanInput) {
            scanInput.value = '';
            if (autoFocusSwitch && autoFocusSwitch.checked) {
                scanInput.focus();
            }
        }
    }

    function supportsAdjustableDamageQty(product) {
        if (!product) return false;
        const categoryType = getProductType(product);
        const qrType = String(product.qr_type || product.qrType || product.dataset?.qrType || '').trim().toUpperCase();
        const isSeries = product.is_series === true || product.is_series === 1 || product.is_series === '1'
            || product.isSeries === true || product.isSeries === 1 || product.isSeries === '1'
            || product.dataset?.isSeries === '1';
        const isComp = product.is_complementary === true || product.is_complementary === 1 || product.is_complementary === '1'
            || product.isComplementary === true || product.isComplementary === 1 || product.isComplementary === '1'
            || product.dataset?.isComplementary === '1';
        const packQty = parseInt(product.pack_quantity || product.dataset?.packQuantity || 1) || 1;

        return categoryType === 'SERIES'
            || categoryType === 'COMPLEMENTARY'
            || qrType === 'SERIES_BATCH'
            || isSeries
            || isComp
            || packQty > 1;
    }

    function getProductType(product) {
        const categoryType = String(product.category_type || product.categoryType || product.dataset?.categoryType || '').trim().toUpperCase();
        const qrType = String(product.qr_type || product.qrType || product.dataset?.qrType || '').trim().toUpperCase();
        const isComplementary = product.is_complementary === true
            || product.is_complementary === 1
            || product.is_complementary === '1'
            || product.isComplementary === '1'
            || product.dataset?.isComplementary === '1';
        if (isComplementary || categoryType === 'COMPLEMENTARY') return 'COMPLEMENTARY';
        if (qrType === 'SERIES_BATCH' || categoryType === 'SERIES') return 'SERIES';
        return categoryType || 'NORMAL';
    }

    // =========================================================================
    // TABLE ROW MANAGEMENT & DYNAMIC VARIANT SELECTION (EXACT ALLOCATION FLOW)
    // =========================================================================
    window.addDamageItemRow = function(selectedVariantId = null, initialTag = null, initialQty = 1, initialReason = 'Fabric Tear / Cut', unitCost = 0) {
        const container = document.getElementById('damageItemsContainer');
        if (!container) return null;

        const emptyPlaceholder = document.getElementById('emptyRowPlaceholder');
        if (emptyPlaceholder) {
            emptyPlaceholder.remove();
        }

        let opts = '<option value="">-- Select Product Variant --</option>';
        let selectedVariantObj = null;

        variantsList.forEach(v => {
            const isSel = (selectedVariantId && String(selectedVariantId) === String(v.id)) ? 'selected' : '';
            if (isSel) {
                selectedVariantObj = v;
            }

            const stock = parseFloat(v.total_warehouse_stock || 0);
            const categoryType = getProductType(v);
            const isComplementary = categoryType === 'COMPLEMENTARY';
            const isSeries = categoryType === 'SERIES' || v.qr_type === 'SERIES_BATCH';

            let catDisplay = v.sub_subcategory_name || v.subcategory_name || v.category_name || 'General';
            let label = `${v.product_name} | SKU: ${v.sku} (${v.color_name ? v.color_name + ' | ' : ''}Size: ${v.size_name || 'Std'}, Stock: ${stock} pcs)`;

            opts += `<option value="${v.id}" 
                             data-sku="${v.sku || ''}" 
                             data-qr="${v.barcode || v.sku || ''}"
                             data-category="${escapeHtml(catDisplay)}"
                             data-size="${escapeHtml(v.size_name || 'Free')}"
                             data-category-type="${escapeHtml(categoryType)}"
                             data-qr-type="${escapeHtml(v.qr_type || 'INDIVIDUAL')}"
                             data-pack-quantity="${parseInt(v.pack_quantity || 1)}"
                             data-is-series="${isSeries ? '1' : '0'}"
                             data-is-complementary="${isComplementary ? '1' : '0'}"
                             data-stock="${stock}" ${isSel}>
                        ${escapeHtml(label)}
                     </option>`;
        });

        const rowQty = parseInt(initialQty) > 0 ? parseInt(initialQty) : 1;
        const cost = parseFloat(unitCost) > 0 ? parseFloat(unitCost) : 
            (selectedVariantObj ? parseFloat(selectedVariantObj.selling_price || selectedVariantObj.mrp || 0) : 0);
        const canAdjustQty = selectedVariantObj ? supportsAdjustableDamageQty(selectedVariantObj) : false;
        const availableStock = selectedVariantObj ? parseFloat(selectedVariantObj.total_warehouse_stock || 0) : 0;
        const catDisplay = selectedVariantObj ? (selectedVariantObj.sub_subcategory_name || selectedVariantObj.subcategory_name || selectedVariantObj.category_name || 'General') : '-';
        const sizeDisplay = selectedVariantObj ? (selectedVariantObj.size_name || 'Free Size') : '-';

        const tr = document.createElement('tr');
        tr.className = 'item-row align-middle';
        tr.innerHTML = `
            <td class="qr-sku-cell">
                <span class="badge bg-dark font-monospace text-warning px-2.5 py-1.5 shadow-xs d-inline-flex align-items-center gap-1 qr-badge">
                    <i class="bi bi-qr-code"></i> ${initialTag ? escapeHtml(initialTag) : 'No Tag'}
                </span>
                <input type="hidden" name="selected_qr_codes[]" class="scanned-qrs-input" value="${initialTag ? escapeHtml(initialTag) : ''}">
                <input type="hidden" name="tag_codes[]" class="tag-code-input" value="${initialTag ? escapeHtml(initialTag) : ''}">
            </td>
            <td>
                <span class="badge bg-secondary-subtle text-secondary category-badge">${escapeHtml(catDisplay)}</span>
            </td>
            <td>
                <input type="hidden" name="variant_ids[]" class="variant-id-input" value="${selectedVariantId || ''}">
                <select class="form-select variant-select form-select-sm" aria-label="Selected product variant" disabled>
                    ${opts}
                </select>
            </td>
            <td class="text-center">
                <span class="fw-bold text-dark size-badge">${escapeHtml(sizeDisplay)}</span>
            </td>
            <td class="text-center">
                <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold fs-6 total-stock-badge">${availableStock} pcs</span>
            </td>
            <td>
                <select name="damage_reasons[]" class="form-select form-select-sm damage-reason-select" onchange="calculateGrandTotal()">
                    <option value="Fabric Tear / Cut" ${initialReason === 'Fabric Tear / Cut' ? 'selected' : ''}>Fabric Tear / Cut</option>
                    <option value="Stitching / Seam Opening" ${initialReason === 'Stitching / Seam Opening' ? 'selected' : ''}>Stitching / Seam Opening</option>
                    <option value="Water / Moisture Contamination" ${initialReason === 'Water / Moisture Contamination' ? 'selected' : ''}>Water / Moisture</option>
                    <option value="Stain / Oil Contamination" ${initialReason === 'Stain / Oil Contamination' ? 'selected' : ''}>Stain / Oil</option>
                    <option value="Color Bleed / Discoloration" ${initialReason === 'Color Bleed / Discoloration' ? 'selected' : ''}>Color Bleed</option>
                    <option value="Broken Button / Zipper / Trims" ${initialReason === 'Broken Button / Zipper / Trims' ? 'selected' : ''}>Broken Button/Zipper</option>
                    <option value="Crushed / Compromised Packaging" ${initialReason === 'Crushed / Compromised Packaging' ? 'selected' : ''}>Crushed Packaging</option>
                    <option value="Transit Physical Impact" ${initialReason === 'Transit Physical Impact' ? 'selected' : ''}>Transit Impact</option>
                    <option value="Other Defect" ${initialReason === 'Other Defect' ? 'selected' : ''}>Other Defect</option>
                </select>
            </td>
            <td class="text-center">
                <div class="input-group input-group-sm mx-auto shadow-xs" style="max-width: 100px;">
                    <button type="button" class="btn btn-outline-secondary px-2 py-0 fw-bold qty-stepper ${canAdjustQty ? '' : 'd-none'}" onclick="changeRowQty(this, -1)" title="Decrease quantity">-</button>
                    <input type="number" 
                           name="requested_qty[]" 
                           class="form-control text-center fw-bold qty-input fs-6 px-1" 
                           value="${rowQty}" 
                           min="1" 
                           max="${availableStock > 0 ? availableStock : 9999}"
                           ${canAdjustQty ? '' : 'readonly'}
                           required 
                           oninput="updateRowStockCalculations(this.closest('tr'))" 
                           onchange="updateRowStockCalculations(this.closest('tr'))">
                    <button type="button" class="btn btn-outline-secondary px-2 py-0 fw-bold qty-stepper ${canAdjustQty ? '' : 'd-none'}" onclick="changeRowQty(this, 1)" title="Increase quantity">+</button>
                </div>
                ${!canAdjustQty ? '<small class="text-muted d-block mt-0.5 text-center" style="font-size: 0.65rem;"><i class="bi bi-lock-fill me-0.5"></i>1 pc fixed</small>' : ''}
            </td>
            <td class="text-center">
                <span class="fw-semibold text-muted unit-cost-badge">₹${cost.toFixed(2)}</span>
                <input type="hidden" name="cost_per_units[]" class="cost-per-unit-input" value="${cost.toFixed(2)}">
            </td>
            <td class="text-end pe-2">
                <span class="fw-bold text-danger total-loss-badge">₹${(rowQty * cost).toFixed(2)}</span>
                <input type="hidden" name="total_losses[]" class="total-loss-input" value="${(rowQty * cost).toFixed(2)}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row" onclick="removeDamageItemRow(this)" title="Remove item">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        container.appendChild(tr);

        if (initialTag) {
            tr.dataset.scannedQrs = JSON.stringify([initialTag]);
        }

        updateRowStockCalculations(tr);
        calculateGrandTotal();
        return tr;
    };

    window.changeRowQty = function(btn, delta) {
        const row = btn.closest('tr');
        if (!row) return;
        const input = row.querySelector('.qty-input');
        if (!input) return;
        const sel = row.querySelector('.variant-select');
        const opt = sel ? sel.options[sel.selectedIndex] : null;
        if (!opt || !supportsAdjustableDamageQty(opt.dataset)) return;
        let currentVal = parseInt(input.value) || 0;
        let newVal = Math.max(1, currentVal + delta);

        if (opt.value) {
            const maxAllowed = parseFloat(input.max || opt.dataset.stock || '0');
            if (maxAllowed > 0 && newVal > maxAllowed) {
                newVal = maxAllowed;
            }
        }
        input.value = newVal;
        updateRowStockCalculations(row);
        calculateGrandTotal();
    };

    window.removeDamageItemRow = function(btn) {
        const container = document.getElementById('damageItemsContainer');
        const row = btn.closest('tr');

        if (row) {
            let scannedQrs = getRowScannedQrs(row);
            scannedQrs.forEach(q => SCANNED_DAMAGE_TAGS.delete(q));
            row.remove();

            const remainingRows = container.querySelectorAll('tr.item-row');
            if (remainingRows.length === 0) {
                container.innerHTML = `
                    <tr id="emptyRowPlaceholder">
                        <td colspan="10" class="text-center py-5 text-muted">
                            <div class="bg-light d-inline-flex p-4 rounded-circle mb-3 shadow-xs">
                                <i class="bi bi-upc-scan fs-1 text-danger"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">No items scanned in queue</h6>
                            <p class="small text-muted mb-0">Point barcode gun scanner or enter piece QR tags above to add items into this damage batch.</p>
                        </td>
                    </tr>
                `;
            }

            calculateGrandTotal();
            updateGlobalScannedCounter();
        }
    };

    window.updateRowStockCalculations = function(row) {
        if (!row) return;
        const qtyInput = row.querySelector('.qty-input');
        const costInput = row.querySelector('.cost-per-unit-input');
        const lossBadge = row.querySelector('.total-loss-badge');
        const lossInput = row.querySelector('.total-loss-input');

        const qty = parseInt(qtyInput ? qtyInput.value : 1) || 1;
        const unitCost = parseFloat(costInput ? costInput.value : 0) || 0;
        const totalLoss = qty * unitCost;

        if (lossBadge) lossBadge.textContent = '₹' + totalLoss.toFixed(2);
        if (lossInput) lossInput.value = totalLoss.toFixed(2);
    };

    function calculateGrandTotal() {
        const rows = document.querySelectorAll('#damageItemsContainer tr.item-row');
        let totalItems = 0;
        let totalPieces = 0;
        let totalLoss = 0.0;
        const itemsQueue = [];

        rows.forEach(r => {
            const vId = r.querySelector('.variant-id-input')?.value;
            const tagCode = r.querySelector('.tag-code-input')?.value || '';
            const qtyInput = r.querySelector('.qty-input');
            const costInput = r.querySelector('.cost-per-unit-input');
            const lossInput = r.querySelector('.total-loss-input');
            const reasonSelect = r.querySelector('.damage-reason-select');

            const qty = parseInt(qtyInput ? qtyInput.value : 0) || 0;
            const cost = parseFloat(costInput ? costInput.value : 0) || 0;
            const loss = parseFloat(lossInput ? lossInput.value : (qty * cost)) || 0;
            const reason = reasonSelect ? reasonSelect.value : 'Fabric Tear / Cut';

            if (vId && qty > 0) {
                totalItems++;
                totalPieces += qty;
                totalLoss += loss;

                itemsQueue.push({
                    variant_id: parseInt(vId),
                    tag_code: tagCode,
                    quantity: qty,
                    cost_per_unit: cost,
                    total_loss: loss,
                    damage_reason: reason,
                    source_type: 'STORAGE_HANDLING',
                    defect_notes: ''
                });
            }
        });

        const itemsDisplay = document.getElementById('totalItemsDisplay');
        const qtyDisplay = document.getElementById('totalQtyDisplay');
        const lossDisplay = document.getElementById('totalLossDisplay');
        const queueBadge = document.getElementById('queueBadgeCount');
        const btnSubmitDamage = document.getElementById('btnSubmitDamage');
        const btnSubmitRework = document.getElementById('btnSubmitRework');
        const hiddenJson = document.getElementById('hiddenItemsJson');

        if (itemsDisplay) itemsDisplay.textContent = `${totalItems} items`;
        if (qtyDisplay) qtyDisplay.textContent = `${totalPieces} pcs`;
        if (lossDisplay) lossDisplay.textContent = '₹' + totalLoss.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (queueBadge) queueBadge.textContent = `${totalItems} items`;
        if (hiddenJson) hiddenJson.value = JSON.stringify(itemsQueue);

        const hasValid = totalItems > 0;
        if (btnSubmitDamage) btnSubmitDamage.disabled = !hasValid;
        if (btnSubmitRework) btnSubmitRework.disabled = !hasValid;
    }

    // =========================================================================
    // LIVE QR & BARCODE GUN SCANNER HANDLER (EXACT ALLOCATION FLOW)
    // =========================================================================
    function handleQrScanInput(rawCode) {
        rawCode = (rawCode || '').trim();
        if (!rawCode) return;

        const whId = (warehouseSelect && warehouseSelect.value ? warehouseSelect.value : '') 
                  || (document.querySelector('input[name="warehouse_id"]') ? document.querySelector('input[name="warehouse_id"]').value : '') 
                  || '1';

        if (!whId) {
            playDuplicateSound();
            showScanAlert('warning', '⚠️ <strong>Select Source Warehouse First:</strong> Please choose a Source Warehouse at top before scanning QR codes.');
            if (warehouseSelect) {
                warehouseSelect.focus();
                warehouseSelect.classList.add('scanner-active-glow');
                setTimeout(() => warehouseSelect.classList.remove('scanner-active-glow'), 2500);
            }
            return;
        }

        // Extract potential tag code for duplicate check
        let tagCode = rawCode;
        if (rawCode.startsWith('{') && rawCode.endsWith('}')) {
            try {
                const j = JSON.parse(rawCode);
                tagCode = j.tag || j.tag_code || j.code || j.sku || rawCode;
            } catch(e) {}
        } else if (rawCode.includes('|')) {
            const parts = rawCode.split('|').map(p => p.trim());
            if (parts[0].toUpperCase() === 'SERIES') {
                tagCode = parts[3] || parts[2] || rawCode;
            } else {
                tagCode = parts[0];
            }
        }
        tagCode = tagCode.toUpperCase();

        if (PENDING_DAMAGE_TAGS.has(tagCode)) {
            playDuplicateSound();
            showScanAlert('duplicate', `⚠️ <strong>Scan in Progress:</strong> Piece Tag <code>${tagCode}</code> is already being processed.`);
            clearAndRefocusScanInput();
            return;
        }

        // 1. DUPLICATE CHECK IN SCANNED ITEMS
        if (SCANNED_DAMAGE_TAGS.has(tagCode)) {
            let existingPackRow = null;
            const rows = document.querySelectorAll('#damageItemsContainer tr.item-row');
            rows.forEach(r => {
                let scannedQrs = getRowScannedQrs(r);
                if (scannedQrs.map(s => s.toUpperCase()).includes(tagCode)) {
                    const sel = r.querySelector('.variant-select');
                    const vId = sel ? sel.value : null;
                    const vObj = variantsList.find(v => String(v.id) === String(vId));
                    const opt = sel ? sel.options[sel.selectedIndex] : null;
                    if ((vObj && supportsAdjustableDamageQty(vObj)) || (opt && supportsAdjustableDamageQty(opt.dataset))) {
                        existingPackRow = r;
                    }
                }
            });

            if (existingPackRow) {
                const qtyInput = existingPackRow.querySelector('.qty-input');
                const sel = existingPackRow.querySelector('.variant-select');
                const opt = sel ? sel.options[sel.selectedIndex] : null;
                const totalStock = opt ? parseFloat(opt.getAttribute('data-stock') || 0) : 0;
                let currentVal = parseInt(qtyInput ? qtyInput.value : 1) || 1;

                if (currentVal + 1 <= totalStock) {
                    qtyInput.value = currentVal + 1;
                    updateRowStockCalculations(existingPackRow);
                    calculateGrandTotal();
                    playSuccessSound();
                    showScanAlert('success', `📦 Pack QR <code>${tagCode}</code> re-scanned: Damage quantity increased to <strong>${currentVal + 1} pcs</strong> (Remaining in warehouse: ${totalStock - (currentVal + 1)} pcs).`);
                    existingPackRow.classList.add('row-scan-highlight');
                    setTimeout(() => existingPackRow.classList.remove('row-scan-highlight'), 1500);
                    existingPackRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    playDuplicateSound();
                    showScanAlert('warning', `⚠️ <strong>Warehouse Stock Limit:</strong> Series Tag <code>${tagCode}</code> has only <strong>${totalStock} pcs</strong> available in this warehouse. All available pieces are already added!`);
                }
                clearAndRefocusScanInput();
                return;
            }

            playDuplicateSound();
            showScanAlert('duplicate', `⚠️ <strong>Duplicate Scan Blocked:</strong> Piece Tag <code>${tagCode}</code> has already been added in this damage entry!`);
            clearAndRefocusScanInput();
            return;
        }

        // Helper to apply matched variant to table
        function applyScannedVariant(data, resolvedTag) {
            resolvedTag = (resolvedTag || tagCode).toUpperCase();

            const isSeriesOrComp = supportsAdjustableDamageQty(data);

            if (SCANNED_DAMAGE_TAGS.has(resolvedTag) && !isSeriesOrComp) {
                playDuplicateSound();
                showScanAlert('duplicate', `⚠️ <strong>Duplicate Scan Blocked:</strong> Piece Tag <code>${resolvedTag}</code> has already been added in this damage entry!`);
                clearAndRefocusScanInput();
                return;
            }

            const vId = data.variant_id || data.id;
            const packQty = parseInt(data.scan_qty || data.damage_quantity || data.pack_quantity || 1);
            const targetQty = isSeriesOrComp ? (data.is_series ? 1 : packQty) : 1;
            const unitCost = parseFloat(data.cost_per_unit || data.selling_price || data.mrp || 0);

            let cachedVariant = variantsList.find(v => String(v.id) === String(vId));
            const scannedVariantData = {
                id: vId,
                sku: data.sku || '',
                barcode: data.barcode || data.sku || '',
                product_name: data.product_name || data.sku || '',
                size_name: data.size_name || 'Free',
                color_name: data.color_name || '',
                category_name: data.category_name || '',
                subcategory_name: data.subcategory_name || '',
                sub_subcategory_name: data.sub_subcategory_name || '',
                category_type: getProductType(data),
                is_complementary: getProductType(data) === 'COMPLEMENTARY' ? 1 : 0,
                total_warehouse_stock: parseFloat(data.available_stock ?? data.total_stock ?? 0),
                is_series: (getProductType(data) === 'SERIES' || data.qr_type === 'SERIES_BATCH') ? 1 : 0,
                qr_type: data.qr_type || 'INDIVIDUAL',
                pack_quantity: packQty
            };
            if (cachedVariant) {
                Object.assign(cachedVariant, scannedVariantData);
            } else {
                variantsList.push(scannedVariantData);
            }

            const targetRow = addDamageItemRow(vId, resolvedTag, targetQty, 'Fabric Tear / Cut', unitCost);

            SCANNED_DAMAGE_TAGS.add(resolvedTag);

            if (targetRow) {
                targetRow.dataset.scannedQrs = JSON.stringify([resolvedTag]);
                targetRow.classList.add('row-scan-highlight');
                setTimeout(() => targetRow.classList.remove('row-scan-highlight'), 1500);
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            playSuccessSound();
            const packBadge = isSeriesOrComp ? ` <span class="badge bg-danger ms-1">📦 Pack (${data.available_stock || packQty} Pcs in Warehouse)</span>` : '';
            const quantityMessage = isSeriesOrComp
                ? 'You can adjust/split the damaged quantity as needed.'
                : 'Individual QR quantity is fixed at 1.';
            showScanAlert('success', `✅ Scanned <strong class="font-monospace">${resolvedTag}</strong> &rarr; ${data.product_name || data.sku} (${data.size_name || 'Std'})${packBadge} added! ${quantityMessage}`);
            updateGlobalScannedCounter();
            clearAndRefocusScanInput();
        }

        // 2. Fetch backend API matcher with client-side fallback
        PENDING_DAMAGE_TAGS.add(tagCode);
        fetch(`<?= url("api/warehouse/damage/lookup-scan") ?>?warehouse_id=${whId}&code=${encodeURIComponent(rawCode)}`)
            .then(res => {
                if (!res.ok) throw new Error('API lookup failed');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    applyScannedVariant(data, (data.tag_code || tagCode).toUpperCase());
                } else if (data.is_already_damaged) {
                    playDuplicateSound();
                    showScanAlert('duplicate', `🚫 <strong>Piece Already Damaged:</strong> ${data.message}`);
                    clearAndRefocusScanInput();
                } else if (data.is_already_allocated) {
                    playDuplicateSound();
                    showScanAlert('duplicate', `🚫 <strong>Piece Already Allocated:</strong> Tag <code class="fw-bold">${data.tag_code || tagCode}</code> has ALREADY been allocated in Pass <strong>#${data.allocation_number}</strong> (${data.allocation_status}) at <em>${data.allocated_warehouse || 'Warehouse'}</em> for ${data.allocated_product || 'item'}. Cannot damage an allocated piece.`);
                    clearAndRefocusScanInput();
                } else if (data.is_exchanged_out) {
                    playDuplicateSound();
                    showScanAlert('danger', `🚫 <strong>Piece Exchanged Out:</strong> ${data.message}`);
                    clearAndRefocusScanInput();
                } else if (data.is_no_stock) {
                    playDuplicateSound();
                    showScanAlert('danger', `❌ <strong>Scan Failed:</strong> ${data.message}`);
                    clearAndRefocusScanInput();
                } else if (data.is_ambiguous) {
                    playDuplicateSound();
                    showScanAlert('danger', `❌ <strong>Scan Failed:</strong> ${data.message}`);
                    clearAndRefocusScanInput();
                } else {
                    throw new Error(data.message || 'Variant not found');
                }
            })
            .catch(err => {
                // Client-side fallback matching
                const clean = tagCode.replace(/[-_]\d+$/, '');
                let local = variantsList.find(v => (v.sku || '').toUpperCase() === tagCode || (v.barcode || '').toUpperCase() === tagCode);
                if (!local) local = variantsList.find(v => (v.sku || '').toUpperCase() === clean || (v.barcode || '').toUpperCase() === clean);
                if (!local) local = variantsList.find(v => tagCode.startsWith((v.sku || '').toUpperCase()) || (v.sku && clean.startsWith((v.sku || '').toUpperCase())));

                if (local) {
                    if (parseFloat(local.total_warehouse_stock || 0) <= 0) {
                        playDuplicateSound();
                        showScanAlert('danger', `❌ <strong>Scan Failed:</strong> Product <strong>${local.product_name || local.sku}</strong> is out of stock in this warehouse.`);
                        clearAndRefocusScanInput();
                        return;
                    }
                    applyScannedVariant(local, tagCode);
                } else {
                    playDuplicateSound();
                    showScanAlert('danger', `❌ <strong>Scan Failed:</strong> No product variant found matching <code>${tagCode}</code>.`);
                    clearAndRefocusScanInput();
                }
            })
            .finally(() => {
                PENDING_DAMAGE_TAGS.delete(tagCode);
            });
    }

    window.handleQrScanInput = handleQrScanInput;

    if (scanInput) {
        scanInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleQrScanInput(this.value);
            }
        });
        scanInput.addEventListener('paste', function(e) {
            setTimeout(() => {
                handleQrScanInput(this.value);
            }, 50);
        });
    }

    if (triggerScanBtn) {
        triggerScanBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (scanInput) handleQrScanInput(scanInput.value);
        });
    }

    if (clearScanBtn) {
        clearScanBtn.addEventListener('click', function() {
            if (scanInput) {
                scanInput.value = '';
                scanInput.focus();
            }
            const banner = document.getElementById('scanStatusBanner');
            if (banner) banner.classList.add('d-none');
        });
    }

    // Photo preview listener
    const photoInput = document.getElementById('damagePhotoInput');
    const photoContainer = document.getElementById('photoPreviewContainer');
    const photoImg = document.getElementById('photoPreviewImg');
    if (photoInput && photoContainer && photoImg) {
        photoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    alert('Photo file size exceeds 5MB. Please choose a smaller image.');
                    this.value = '';
                    photoContainer.classList.add('d-none');
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoImg.src = e.target.result;
                    photoContainer.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                photoContainer.classList.add('d-none');
            }
        });
    }

    // Form submit validation
    if (damageForm) {
        damageForm.addEventListener('submit', function(e) {
            calculateGrandTotal();
            const rows = document.querySelectorAll('#damageItemsContainer tr.item-row');
            if (rows.length === 0) {
                e.preventDefault();
                alert('Please scan or add at least one product before submitting.');
                if (scanInput) scanInput.focus();
                return false;
            }
        });
    }
});
</script>
