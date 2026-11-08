<?php
// Patch create.php and edit.php for Warehouse Catalog

function patchWarehouseCatalogView($filePath, $isEdit = false) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return;
    }
    $content = file_get_contents($filePath);
    $isCrlf = (strpos($content, "\r\n") !== false);

    // 1. Replace hidden hsn_code
    $hsnTarget = $isEdit 
        ? '<input type="hidden" name="hsn_code" id="hsn_code" value="<?= e($product[\'hsn_code\'] ?? \'\') ?>">'
        : '<input type="hidden" name="hsn_code" id="hsn_code" value="">';

    $hsnHtml = <<<'HTML'
<!-- HSN Code & GST Tax Classification (Dual Support: Category-Default or Product-Specific) -->
                        <div class="col-md-12 mt-2 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <label class="form-label fw-bold text-dark mb-0">
                                    <i class="bi bi-receipt-cutoff text-success me-1"></i> HSN Code &amp; GST Tax Classification
                                </label>
                                <span class="badge bg-secondary-subtle text-secondary border small">
                                    <i class="bi bi-layers-half me-1"></i> Dual HSN: Category Default &amp; Product-Specific Override
                                </span>
                            </div>

                            <!-- Category Default HSN Quick Alert -->
                            <div id="categoryHsnAlert" class="alert alert-primary py-2 px-3 small d-flex align-items-center justify-content-between mb-3" style="display: none;">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-info-circle-fill text-primary"></i>
                                    <span>Category Default: <strong id="catHsnCodeDisplay">6109</strong> (<span id="catHsnGstDisplay">5.00</span>% GST)</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary py-0 px-2 fw-semibold" onclick="applyCategoryHsn()" style="font-size: 11px;">
                                    <i class="bi bi-arrow-down-left me-1"></i> Apply Category HSN
                                </button>
                            </div>

                            <div class="row g-3">
                                <!-- Live Searchable HSN Selector -->
                                <div class="col-md-5">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label small fw-semibold text-dark mb-0">
                                            <i class="bi bi-search me-1 text-primary"></i>Search HSN Code or Commodity
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:11px;" data-bs-toggle="modal" data-bs-target="#modalAddHsnCode">
                                            <i class="bi bi-plus-circle me-1"></i>+ Add New HSN
                                        </button>
                                    </div>
                                    <div class="position-relative" id="hsnSearchContainer">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                            <input type="text" id="hsnSearchInput" class="form-control border-start-0 ps-0" placeholder="Type HSN code or description (e.g. 6109, Shirt, Pants)..." autocomplete="off" onfocus="openHsnDropdown()" oninput="filterHsnList(this.value)">
                                            <button class="btn btn-outline-secondary" type="button" onclick="toggleHsnDropdown()" title="Toggle HSN list">
                                                <i class="bi bi-chevron-down" id="hsnDropdownArrow"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Searchable Dropdown List -->
                                        <div id="hsnDropdownMenu" class="dropdown-menu shadow-lg w-100 p-0" style="max-height: 260px; overflow-y: auto; display: none; position: absolute; z-index: 1050; left: 0; right: 0; top: 100%; margin-top: 4px;">
                                            <div class="p-2 border-bottom bg-light small d-flex justify-content-between align-items-center text-muted sticky-top">
                                                <span><i class="bi bi-funnel me-1"></i>Select or search HSN code</span>
                                                <span class="badge bg-secondary-subtle text-dark border" id="hsnCountBadge">All</span>
                                            </div>
                                            <div id="hsnOptionsList" class="list-group list-group-flush small">
                                                <?php if (!empty($hsnCodes)): ?>
                                                    <?php foreach ($hsnCodes as $hsn): ?>
                                                        <a href="javascript:void(0)" class="list-group-item list-group-item-action py-2 px-3 hsn-option-item"
                                                           data-code="<?= e($hsn['hsn_code']) ?>"
                                                           data-gst="<?= number_format((float)$hsn['gst_rate'], 2, '.', '') ?>"
                                                           data-desc="<?= e($hsn['description']) ?>"
                                                           data-search="<?= strtolower(e($hsn['hsn_code'] . ' ' . $hsn['description'])) ?>"
                                                           onclick="selectHsnOption('<?= e($hsn['hsn_code']) ?>', '<?= number_format((float)$hsn['gst_rate'], 2, '.', '') ?>', '<?= e(addslashes($hsn['description'])) ?>')">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <span class="badge bg-primary-subtle text-primary border me-1 font-monospace fw-bold">HSN <?= e($hsn['hsn_code']) ?></span>
                                                                    <span class="text-dark fw-semibold"><?= e($hsn['description']) ?></span>
                                                                </div>
                                                                <span class="badge bg-success-subtle text-success border ms-2"><?= number_format((float)$hsn['gst_rate'], 2) ?>% GST</span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php
                                                    $defaultHsns = [
                                                        ['hsn_code' => '6109', 'description' => "T-Shirts, Singlets & Vests (Knitted)", 'gst_rate' => 5.00],
                                                        ['hsn_code' => '6205', 'description' => "Men's / Boys' Woven Shirts", 'gst_rate' => 5.00],
                                                        ['hsn_code' => '6203', 'description' => "Men's Trousers, Suits & Shorts", 'gst_rate' => 5.00],
                                                        ['hsn_code' => '6204', 'description' => "Women's Dresses, Kurtis & Skirts", 'gst_rate' => 5.00],
                                                        ['hsn_code' => '6104', 'description' => "Women's Knitted Suits & Nightwear", 'gst_rate' => 5.00],
                                                        ['hsn_code' => '6115', 'description' => "Socks & Pantyhose", 'gst_rate' => 5.00],
                                                        ['hsn_code' => '6211', 'description' => "Tracksuits & Sportswear", 'gst_rate' => 5.00],
                                                        ['hsn_code' => '5208', 'description' => "Woven Cotton Fabrics", 'gst_rate' => 5.00],
                                                        ['hsn_code' => '6302', 'description' => "Bed, Table & Toilet Linen", 'gst_rate' => 5.00],
                                                    ];
                                                    foreach ($defaultHsns as $dh): ?>
                                                        <a href="javascript:void(0)" class="list-group-item list-group-item-action py-2 px-3 hsn-option-item"
                                                           data-code="<?= $dh['hsn_code'] ?>"
                                                           data-gst="<?= number_format($dh['gst_rate'], 2, '.', '') ?>"
                                                           data-desc="<?= $dh['description'] ?>"
                                                           data-search="<?= strtolower($dh['hsn_code'] . ' ' . $dh['description']) ?>"
                                                           onclick="selectHsnOption('<?= $dh['hsn_code'] ?>', '<?= number_format($dh['gst_rate'], 2, '.', '') ?>', '<?= addslashes($dh['description']) ?>')">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <span class="badge bg-primary-subtle text-primary border me-1 font-monospace fw-bold">HSN <?= $dh['hsn_code'] ?></span>
                                                                    <span class="text-dark fw-semibold"><?= $dh['description'] ?></span>
                                                                </div>
                                                                <span class="badge bg-success-subtle text-success border ms-2"><?= number_format($dh['gst_rate'], 2) ?>% GST</span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <div id="hsnNoMatch" class="p-3 text-center text-muted" style="display:none;">
                                                    <i class="bi bi-emoji-neutral me-1"></i> No matching HSN code found. Click <strong>+ Add New HSN</strong> above to register it.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted" style="font-size:0.75rem;">Type to live filter HSN codes or click to open dropdown</small>
                                </div>

                                <!-- HSN Code -->
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-dark mb-1">
                                        HSN Code <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" name="hsn_code" id="hsn_code" class="form-control text-uppercase fw-bold" placeholder="e.g. 6109" maxlength="15" value="<?= e($product['hsn_code'] ?? '') ?>" required>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="clearHsnFields()" title="Clear HSN">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted" style="font-size:0.75rem;">Harmonized System of Nomenclature code for tax invoices</small>
                                </div>

                                <!-- Default GST Rate -->
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-dark mb-1">Default GST Rate (%)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="28" name="gst_percentage" id="gst_percentage" class="form-control fw-bold" value="<?= number_format((float)($product['gst_percentage'] ?? 5.00), 2, '.', '') ?>" placeholder="5.00">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted" style="font-size:0.75rem;">Standard garment slab (5% / 12%)</small>
                                </div>
                            </div>
                        </div>
HTML;

    if ($isCrlf) {
        $hsnTarget = str_replace("\n", "\r\n", $hsnTarget);
        $hsnHtml = str_replace("\n", "\r\n", $hsnHtml);
    }

    if (strpos($content, $hsnTarget) !== false) {
        $content = str_replace($hsnTarget, $hsnHtml, $content);
        echo "Patched HSN HTML in " . basename($filePath) . "\n";
    } else {
        echo "HSN target not found in " . basename($filePath) . "\n";
    }

    // 2. Update masterCategoriesList to include hsn_code and gst_rate
    $catListSearch = "let masterCategoriesList = <?= json_encode(array_map(fn(\$c) => ['id' => \$c['id'], 'name' => \$c['name'], 'code' => \$c['code'] ?? ''], \$categories ?? [])) ?>;";
    $catListReplace = "let masterCategoriesList = <?= json_encode(array_map(fn(\$c) => ['id' => \$c['id'], 'name' => \$c['name'], 'code' => \$c['code'] ?? '', 'hsn_code' => \$c['hsn_code'] ?? '', 'gst_rate' => \$c['gst_rate'] ?? '5.00'], \$categories ?? [])) ?>;";

    if ($isCrlf) {
        $catListSearch = str_replace("\n", "\r\n", $catListSearch);
        $catListReplace = str_replace("\n", "\r\n", $catListReplace);
    }

    if (strpos($content, $catListSearch) !== false) {
        $content = str_replace($catListSearch, $catListReplace, $content);
        echo "Patched masterCategoriesList in " . basename($filePath) . "\n";
    } else {
        echo "masterCategoriesList search not found in " . basename($filePath) . "\n";
    }

    // 3. Add modal #modalAddHsnCode if not present
    if (strpos($content, 'id="modalAddHsnCode"') === false) {
        $modalCode = <<<'MODAL'

<!-- Quick Add HSN Code Modal -->
<div class="modal fade" id="modalAddHsnCode" tabindex="-1" aria-labelledby="modalAddHsnCodeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fs-6 fw-bold" id="modalAddHsnCodeLabel">
                    <i class="bi bi-plus-circle me-1"></i> Add New HSN Code &amp; GST Rate
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">HSN Code <span class="text-danger">*</span></label>
                    <input type="text" id="new_hsn_code" class="form-control text-uppercase fw-bold" placeholder="e.g. 6206 or 61091000" maxlength="15" required>
                    <small class="text-muted" style="font-size:0.75rem;">Harmonized System of Nomenclature code for invoices</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">Commodity / Description <span class="text-danger">*</span></label>
                    <input type="text" id="new_hsn_desc" class="form-control" placeholder="e.g. Women's Blouses, Shirts &amp; Kurtas" maxlength="150" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">Default GST Rate (%) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" max="28" id="new_hsn_gst" class="form-control fw-bold" value="5.00" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted" style="font-size:0.75rem;">Standard garment slab: 5.00%, 12.00%, 18.00%</small>
                </div>
                <div id="newHsnAlert" class="alert alert-danger py-2 small d-none mb-0"></div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm fw-semibold" id="btnSaveNewHsn" onclick="saveNewHsnCode()">
                    <i class="bi bi-check2-circle me-1"></i> Save &amp; Select HSN
                </button>
            </div>
        </div>
    </div>
</div>
MODAL;
        // Insert before <script>
        $scriptPos = strrpos($content, '<script');
        if ($scriptPos !== false) {
            $content = substr($content, 0, $scriptPos) . ($isCrlf ? str_replace("\n", "\r\n", $modalCode) : $modalCode) . "\n\n" . substr($content, $scriptPos);
            echo "Inserted modalAddHsnCode in " . basename($filePath) . "\n";
        }
    }

    // 4. Add JavaScript functions for HSN if not present
    if (strpos($content, 'function selectHsnOption') === false) {
        $jsCode = <<<'JS'

// ==========================================
// DUAL HSN & SEARCHABLE DROPDOWN SYSTEM
// ==========================================
function openHsnDropdown() {
    const menu = document.getElementById('hsnDropdownMenu');
    if (menu) menu.style.display = 'block';
    const arrow = document.getElementById('hsnDropdownArrow');
    if (arrow) arrow.className = 'bi bi-chevron-up';
}

function closeHsnDropdown() {
    const menu = document.getElementById('hsnDropdownMenu');
    if (menu) menu.style.display = 'none';
    const arrow = document.getElementById('hsnDropdownArrow');
    if (arrow) arrow.className = 'bi bi-chevron-down';
}

function toggleHsnDropdown() {
    const menu = document.getElementById('hsnDropdownMenu');
    if (!menu) return;
    if (menu.style.display === 'block') {
        closeHsnDropdown();
    } else {
        openHsnDropdown();
        const searchInput = document.getElementById('hsnSearchInput');
        if (searchInput) searchInput.focus();
    }
}

function filterHsnList(val) {
    openHsnDropdown();
    const query = val.toLowerCase().trim();
    const items = document.querySelectorAll('.hsn-option-item');
    let matchCount = 0;
    items.forEach(it => {
        const text = it.getAttribute('data-search') || '';
        if (!query || text.includes(query)) {
            it.style.display = '';
            matchCount++;
        } else {
            it.style.display = 'none';
        }
    });

    const noMatch = document.getElementById('hsnNoMatch');
    if (noMatch) noMatch.style.display = (matchCount === 0) ? 'block' : 'none';
    const badge = document.getElementById('hsnCountBadge');
    if (badge) badge.textContent = query ? `${matchCount} matches` : 'All';
}

function selectHsnOption(code, gst, desc) {
    document.getElementById('hsn_code').value = code;
    if (document.getElementById('gst_percentage')) {
        document.getElementById('gst_percentage').value = gst;
    }
    const input = document.getElementById('hsnSearchInput');
    if (input) input.value = `HSN ${code} - ${desc} [${gst}%]`;
    
    // Also sync to Quick Fill All HSN input
    const batchInp = document.getElementById('batchFillHsn');
    if (batchInp) batchInp.value = code;

    closeHsnDropdown();
}

function clearHsnFields() {
    document.getElementById('hsn_code').value = '';
    if (document.getElementById('gst_percentage')) {
        document.getElementById('gst_percentage').value = '5.00';
    }
    const input = document.getElementById('hsnSearchInput');
    if (input) input.value = '';
    filterHsnList('');
}

function checkCategoryDefaultHsn() {
    const alertBox = document.getElementById('categoryHsnAlert');
    if (!alertBox) return;

    let catId = document.getElementById('category_id')?.value;
    if (!catId) {
        const firstMain = document.querySelector('.card-cat-main');
        if (firstMain && firstMain.value) catId = firstMain.value;
    }
    if (!catId && typeof masterCategoriesList !== 'undefined' && masterCategoriesList.length > 0) {
        catId = masterCategoriesList[0].id;
    }

    const cat = (typeof masterCategoriesList !== 'undefined') ? masterCategoriesList.find(c => String(c.id) === String(catId)) : null;
    if (cat && cat.hsn_code) {
        document.getElementById('catHsnCodeDisplay').textContent = cat.hsn_code;
        document.getElementById('catHsnGstDisplay').textContent = cat.gst_rate || '5.00';
        alertBox.style.display = 'flex';

        const hsnInp = document.getElementById('hsn_code');
        if (hsnInp && !hsnInp.value) {
            hsnInp.value = cat.hsn_code;
            if (document.getElementById('gst_percentage')) {
                document.getElementById('gst_percentage').value = cat.gst_rate || '5.00';
            }
            const searchInput = document.getElementById('hsnSearchInput');
            if (searchInput) searchInput.value = `Category Default: HSN ${cat.hsn_code} [${cat.gst_rate || '5.00'}%]`;
            const batchInp = document.getElementById('batchFillHsn');
            if (batchInp) batchInp.value = cat.hsn_code;
        }
    } else {
        alertBox.style.display = 'none';
    }
}

function applyCategoryHsn() {
    let catId = document.getElementById('category_id')?.value;
    if (!catId) {
        const firstMain = document.querySelector('.card-cat-main');
        if (firstMain && firstMain.value) catId = firstMain.value;
    }
    if (!catId && typeof masterCategoriesList !== 'undefined' && masterCategoriesList.length > 0) {
        catId = masterCategoriesList[0].id;
    }

    const cat = (typeof masterCategoriesList !== 'undefined') ? masterCategoriesList.find(c => String(c.id) === String(catId)) : null;
    if (cat && cat.hsn_code) {
        selectHsnOption(cat.hsn_code, cat.gst_rate || '5.00', cat.name);
        
        // Also update all individual variant matrix HSN inputs
        document.querySelectorAll('.matrix-hsn-input').forEach(inp => {
            inp.value = cat.hsn_code;
            const key = inp.dataset.key;
            if (key && window.variantStateMap && window.variantStateMap[key]) {
                window.variantStateMap[key].hsn_code = cat.hsn_code;
            }
        });
    }
}

function saveNewHsnCode() {
    const code = document.getElementById('new_hsn_code').value.trim().toUpperCase();
    const desc = document.getElementById('new_hsn_desc').value.trim();
    const gst = parseFloat(document.getElementById('new_hsn_gst').value) || 0;
    const alertBox = document.getElementById('newHsnAlert');
    const saveBtn = document.getElementById('btnSaveNewHsn');

    alertBox.classList.add('d-none');
    alertBox.textContent = '';

    if (!code) {
        alertBox.textContent = 'Please enter a valid HSN code.';
        alertBox.classList.remove('d-none');
        document.getElementById('new_hsn_code').focus();
        return;
    }
    if (!desc) {
        alertBox.textContent = 'Please enter a commodity description.';
        alertBox.classList.remove('d-none');
        document.getElementById('new_hsn_desc').focus();
        return;
    }

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    const formData = new FormData();
    formData.append('hsn_code', code);
    formData.append('description', desc);
    formData.append('gst_rate', gst.toFixed(2));

    fetch('<?= url("api/hsn-codes/store") ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save &amp; Select HSN';

        if (res.success && res.data) {
            const list = document.getElementById('hsnOptionsList');
            if (list) {
                const optHtml = `
                    <a href="javascript:void(0)" class="list-group-item list-group-item-action py-2 px-3 hsn-option-item"
                       data-code="${res.data.hsn_code}"
                       data-gst="${res.data.gst_rate}"
                       data-desc="${res.data.description}"
                       data-search="${(res.data.hsn_code + ' ' + res.data.description).toLowerCase()}"
                       onclick="selectHsnOption('${res.data.hsn_code}', '${res.data.gst_rate}', '${res.data.description.replace(/'/g, "\\'")}')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-primary-subtle text-primary border me-1 font-monospace fw-bold">HSN ${res.data.hsn_code}</span>
                                <span class="text-dark fw-semibold">${res.data.description}</span>
                            </div>
                            <span class="badge bg-success-subtle text-success border ms-2">${res.data.gst_rate}% GST</span>
                        </div>
                    </a>
                `;
                list.insertAdjacentHTML('afterbegin', optHtml);
            }

            selectHsnOption(res.data.hsn_code, res.data.gst_rate, res.data.description);

            // Close modal
            const modalEl = document.getElementById('modalAddHsnCode');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();

            // Clear modal fields
            document.getElementById('new_hsn_code').value = '';
            document.getElementById('new_hsn_desc').value = '';
            document.getElementById('new_hsn_gst').value = '5.00';
        } else {
            alertBox.textContent = res.message || 'Failed to save HSN code.';
            alertBox.classList.remove('d-none');
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save &amp; Select HSN';
        alertBox.textContent = 'Server error occurred while saving HSN code.';
        alertBox.classList.remove('d-none');
    });
}

document.addEventListener('click', function(e) {
    const container = document.getElementById('hsnSearchContainer');
    if (container && !container.contains(e.target)) {
        closeHsnDropdown();
    }
});

// Run category HSN check on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(checkCategoryDefaultHsn, 200);
});
JS;
        // Insert right before </script> at the end
        $endScriptPos = strrpos($content, '</script>');
        if ($endScriptPos !== false) {
            $content = substr($content, 0, $endScriptPos) . "\n" . ($isCrlf ? str_replace("\n", "\r\n", $jsCode) : $jsCode) . "\n" . substr($content, $endScriptPos);
            echo "Inserted HSN JS functions in " . basename($filePath) . "\n";
        }
    }

    file_put_contents($filePath, $content);
}

patchWarehouseCatalogView('C:/xampp/htdocs/test/Inventory_Software/views/warehouse/catalog/create.php', false);
patchWarehouseCatalogView('C:/xampp/htdocs/test/Inventory_Software/views/warehouse/catalog/edit.php', true);
