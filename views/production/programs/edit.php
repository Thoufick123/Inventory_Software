<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Production Program</h2>
        <span class="text-muted">Update program details, raw material rolls, per-roll 3-tier category hierarchies, and schedule dates for <strong><?= e($program['program_number']) ?></strong></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('production/programs') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Programs
        </a>
    </div>
</div>

<form method="POST" action="<?= url('production/programs/update/' . $program['id']) ?>" id="programEditForm">
    <?= CSRF::field() ?>

    <div class="row g-4">
        <!-- LEFT COLUMN: Program Info & Raw Material Rolls with Per-Roll Catalog -->
        <div class="col-lg-6">
            <!-- 1. Program Identity -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-tag-fill me-2 text-primary"></i> 1. Program Overview
                    </h5>
                    <small class="text-muted">Define the unique program title and reference code</small>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold required">Program Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="name" 
                               id="program_name" 
                               class="form-control form-control-lg text-uppercase fw-bold" 
                               value="<?= e($program['name'] ?: $program['product_name'] ?? '') ?>" 
                               placeholder="e.g. SUMMER MATERNITY COLLECTION 2026" 
                               required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold required">Program Code / Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-hash"></i></span>
                                <input type="text" 
                                       name="program_number" 
                                       id="program_number" 
                                       class="form-control text-uppercase fw-bold" 
                                       value="<?= e($program['program_number']) ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold required">Person Name (Planner / Creator) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-person-fill"></i></span>
                                <input type="text" 
                                       name="person_name" 
                                       id="person_name" 
                                       class="form-control fw-bold" 
                                       placeholder="e.g. Supervisor Abi / John Doe" 
                                       value="<?= e($program['person_name'] ?? (Session::user()['full_name'] ?? '')) ?>" 
                                       required>
                            </div>
                            <div class="form-text text-muted">Person creating or planning this production program.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Raw Material Rolls (From Sellers) - MULTI-ROLL WITH PER-ROLL 3-TIER CATALOG -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-layers-fill me-2 text-primary"></i> 2. Raw Material Rolls &amp; Per-Roll Catalog Hierarchy
                        </h5>
                        <small class="text-muted">Assign fabric rolls and configure 3-tier catalog categories for each roll</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success fw-semibold" id="btnScanRoll" onclick="openScanRollModal()">
                            <i class="bi bi-qr-code-scan me-1"></i> Scan Roll
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnMultiSelectRolls" onclick="openMultiRollModal()">
                            <i class="bi bi-check2-square me-1"></i> Multi-Select Rolls
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddRollRow" onclick="addProgramRollRow()">
                            <i class="bi bi-plus-lg me-1"></i> Add Roll
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- Quick Barcode / Roll QR Gun Scanner Input Bar -->
                    <div class="p-3 bg-light rounded-3 border mb-4 shadow-xs">
                        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                            <span class="small fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 13px;">
                                <i class="bi bi-upc-scan text-success fs-5"></i>
                                <span>Scan Fabric Roll Barcode / QR Tag</span>
                                <span class="badge bg-success-subtle text-success border px-2 py-1" style="font-size: 10px;">Auto-Add Ready</span>
                            </span>
                            <small class="text-muted" style="font-size: 11px;">Hardware Gun &amp; Camera Scanner (e.g. <code>FB1126141</code>, <code>ROLL #1</code>)</small>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-success border-end-0">
                                <i class="bi bi-qr-code"></i>
                            </span>
                            <input type="text" id="quickRollScanInput" class="form-control border-start-0 font-monospace fw-semibold" placeholder="Scan roll QR or type piece tag (e.g. FB1126141) and press Enter to auto-add..." autocomplete="off">
                            <button type="button" class="btn btn-success fw-semibold px-3" onclick="triggerQuickRollScan()">
                                <i class="bi bi-plus-circle me-1"></i> Scan / Add
                            </button>
                        </div>
                        <div id="quickScanFeedbackAlert" class="small mt-2 d-none p-2 px-3 rounded-2" role="status"></div>
                    </div>

                    <!-- Container for dynamic roll items -->
                    <div id="programRollsContainer" class="d-flex flex-column gap-4 mb-4">
                        <!-- Dynamically populated by JS with independent Catalog Dropdowns -->
                    </div>

                    <!-- Hidden inputs for backward compatibility and total sums -->
                    <input type="hidden" name="seller_roll_id" id="primary_seller_roll_id" value="<?= e($program['seller_roll_id'] ?? '') ?>">
                    <input type="hidden" name="roll_weight" id="total_roll_weight" value="<?= e($program['roll_weight'] ?? '0.00') ?>">
                    <input type="hidden" name="roll_price" id="total_roll_price" value="<?= e($program['roll_price'] ?? '0.00') ?>">

                    <!-- Live Aggregated Roll Metrics Summary Bar -->
                    <div class="p-3 bg-light rounded border">
                        <div class="row g-2 text-center align-items-center">
                            <div class="col-6 col-sm-3 border-end">
                                <div class="text-muted small">Total Rolls</div>
                                <div class="fw-bold fs-6 text-primary mt-1" id="dispTotalRollCount">0 rolls</div>
                            </div>
                            <div class="col-6 col-sm-3 border-end">
                                <div class="text-muted small">Total Roll Weight</div>
                                <div class="fw-bold fs-6 text-dark mt-1" id="dispTotalRollWeight">0.00 kg</div>
                            </div>
                            <div class="col-6 col-sm-3 border-end">
                                <div class="text-muted small">Total Roll Price</div>
                                <div class="fw-bold fs-6 text-success mt-1" id="dispTotalRollPrice">₹0.00</div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="text-muted small">Avg Fabric Rate</div>
                                <div class="fw-bold fs-6 text-secondary mt-1" id="dispAvgRatePerKg">₹0.00 / kg</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Timeline & Additional Settings -->
        <div class="col-lg-6">
            <!-- 3. Timeline & Schedule -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-calendar-week me-2 text-primary"></i> 3. Programming Timeline &amp; Scheduling
                    </h5>
                    <small class="text-muted">Specify the manufacturing start and completion target dates</small>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold required">Programming Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= e($program['start_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold required">Target Completion / End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= e($program['end_date'] ?? $program['target_date'] ?? date('Y-m-d', strtotime('+14 days'))) ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Production Unit & Assignment -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-buildings-fill me-2 text-primary"></i> 4. Production Unit &amp; Assignment
                    </h5>
                    <small class="text-muted">Optional manufacturing unit assignment, priority, and supervisor</small>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <?php if (!empty($scopedUnitId)): 
                                $effectiveUnitId = !empty($program['production_unit_id']) ? $program['production_unit_id'] : $scopedUnitId;
                                $assignedUnitName = $program['production_unit_name'] ?? '';
                                if (!$assignedUnitName && !empty($units)) {
                                    foreach ($units as $u) {
                                        if ($u['id'] == $effectiveUnitId) { $assignedUnitName = $u['name']; break; }
                                    }
                                }
                                if (!$assignedUnitName) {
                                    $puRow = Database::fetchOne("SELECT name FROM production_units WHERE id = ?", [$effectiveUnitId]);
                                    $assignedUnitName = $puRow['name'] ?? 'Assigned Unit';
                                }
                            ?>
                                <label class="form-label fw-semibold">Production Unit</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-primary border-end-0">
                                        <i class="bi bi-buildings-fill"></i>
                                    </span>
                                    <input type="text" class="form-control bg-light border-start-0 fw-semibold text-dark" value="<?= e($assignedUnitName) ?>" readonly>
                                    <span class="input-group-text bg-light text-muted small border-start-0" title="Locked to your assigned unit">
                                        <i class="bi bi-lock-fill text-secondary me-1"></i> Locked
                                    </span>
                                </div>
                                <input type="hidden" name="production_unit_id" value="<?= e($effectiveUnitId) ?>">
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-shield-check text-success me-1"></i>Designated manufacturing unit (locked for staff)
                                </small>
                            <?php else: ?>
                                <label class="form-label fw-semibold">Production Unit</label>
                                <select name="production_unit_id" class="form-select">
                                    <option value="">Select Unit</option>
                                    <?php foreach ($units as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= ($program['production_unit_id'] == $u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-1">Optional manufacturing facility for this program</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="LOW" <?= ($program['priority'] === 'LOW') ? 'selected' : '' ?>>Low</option>
                                <option value="MEDIUM" <?= ($program['priority'] === 'MEDIUM' || empty($program['priority'])) ? 'selected' : '' ?>>Medium</option>
                                <option value="HIGH" <?= ($program['priority'] === 'HIGH') ? 'selected' : '' ?>>High</option>
                                <option value="URGENT" <?= ($program['priority'] === 'URGENT') ? 'selected' : '' ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Supervisor / Manager</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Select Staff</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($program['assigned_to'] == $u['id']) ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-semibold">Notes / Special Instructions</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Fabric specifications, special trims, packaging, or delivery instructions..."><?= e($program['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Sticky Actions -->
    <div class="card shadow-sm border-0 mb-5 sticky-bottom bg-white py-3 px-4 border-top">
        <div class="d-flex justify-content-between align-items-center">
            <a href="<?= url('production/programs/view/' . $program['id']) ?>" class="btn btn-outline-secondary px-4">
                <i class="bi bi-x-circle me-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary px-5 py-2 fw-semibold shadow-sm" id="btnUpdateProgram">
                <i class="bi bi-check2-circle me-1"></i> Update Production Program
            </button>
        </div>
    </div>
</form>

<!-- Multi-Select Rolls Modal -->
<div class="modal fade" id="multiRollSelectModal" tabindex="-1" aria-labelledby="multiRollSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check2-square fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0" id="multiRollSelectModalLabel">Select Multiple Raw Material Rolls</h5>
                    <span class="badge bg-white text-primary fw-bold ms-2 px-2 py-1" id="multiSelectedCountBadge">0 Selected</span>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Search & Filter Controls -->
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="multiRollSearchInput" class="form-control form-control-sm border-start-0" placeholder="Search by roll #, color, design, supplier, fabric..." oninput="filterMultiRollList()">
                            <button type="button" class="btn btn-outline-secondary border-start-0" onclick="clearMultiRollSearch()" title="Clear search">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-5 d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllFilteredRolls()">
                            <i class="bi bi-check-all me-1"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllMultiRolls()">
                            <i class="bi bi-dash-square me-1"></i> Deselect All
                        </button>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="table-responsive border rounded" style="max-height: 380px;">
                    <table class="table table-hover align-middle mb-0" id="multiRollSelectTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 40px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="masterMultiRollCheckbox" onchange="toggleMasterMultiRoll(this.checked)">
                                </th>
                                <th>Roll Details</th>
                                <th>Color &amp; Design</th>
                                <th>Supplier</th>
                                <th>Available Stock</th>
                                <th class="text-end">Weight / Price</th>
                            </tr>
                        </thead>
                        <tbody id="multiRollTableBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
                <div id="noMultiRollsAlert" class="text-center py-4 text-muted d-none">
                    <i class="bi bi-search fs-3 d-block mb-2 text-secondary"></i>
                    No matching fabric rolls found.
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted fw-semibold" id="multiRollFooterSummary">0 roll(s) ready to add</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm fw-semibold px-4" onclick="confirmMultiRollSelection()">
                        <i class="bi bi-plus-circle me-1"></i> Add Selected Rolls
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HTML5-QRCode Library for Web & Mobile Camera Scanning -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- Camera Scanner Modal for Program Rolls -->
<div class="modal fade" id="scanRollCameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-2.5 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-qr-code-scan fs-5"></i>
                    <h6 class="modal-title fw-bold mb-0">Camera QR Roll Scanner</h6>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="stopCameraScanner()"></button>
            </div>
            <div class="modal-body p-3 text-center">
                <div id="cameraScannerPreview" style="width: 100%; min-height: 240px; background: #000; border-radius: 8px; overflow: hidden;" class="mb-2"></div>
                <small class="text-muted d-block mb-2">Aim camera at Fabric Roll QR label or piece tag (e.g. <code>FB1126141</code>)</small>
                <div id="cameraScanFeedback" class="small p-2 rounded-2 d-none"></div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <span class="text-muted small" id="cameraScannedRollsCount">Auto-adds to roll table</span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" onclick="stopCameraScanner()">Done / Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Add Category Attribute Modal -->
<div class="modal fade" id="modalQuickAddCategoryAttribute" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-sliders fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-dark">Quick Add Category Attribute</h6>
                        <small class="text-muted" style="font-size: 11px;">Configure specifications &amp; options for this catalog level</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formQuickAddCategoryAttribute" onsubmit="handleSaveCategoryAttribute(event)">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <!-- Target Level Info Pill -->
                    <div class="p-2.5 px-3 bg-light rounded-3 border mb-3">
                        <div class="small text-muted text-uppercase fw-semibold mb-0.5" style="font-size: 10px;">Target Catalog Level:</div>
                        <div class="fw-bold text-primary small d-flex align-items-center gap-1 text-truncate" id="attrModalTargetBreadcrumb">
                            Main Category
                        </div>
                    </div>

                    <input type="hidden" id="attrModalTargetRowIdx" value="">
                    <input type="hidden" id="attrModalCategoryId" name="category_id" value="">
                    <input type="hidden" id="attrModalSubcategoryId" name="subcategory_id" value="">
                    <input type="hidden" id="attrModalSubSubcategoryId" name="sub_subcategory_id" value="">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark required">Attribute Name <span class="text-danger">*</span></label>
                        <input type="text" name="attr_name" id="attrModalName" class="form-control text-uppercase" placeholder="E.G. FABRIC TYPE, GSM, WEAVE, FINISH, PATTERN" required maxlength="100">
                        <div class="form-text text-muted" style="font-size: 11px;">Specification name (e.g. GSM, Fabric Type, Weave, Shrinkage).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark required">Input Type <span class="text-danger">*</span></label>
                        <select name="attr_type" id="attrModalType" class="form-select form-select-sm" onchange="handleAttrTypeChange(this.value)">
                            <option value="MULTI_SELECT" selected>Multiple Checkbox Pills / Sizes (Image 2 style)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="attrModalOptionsGroup">
                        <label class="form-label small fw-bold text-dark required">
                            Attribute Options <span class="text-danger">*</span>
                        </label>
                        <textarea name="attr_options" id="attrModalOptions" class="form-control text-uppercase" rows="2" placeholder="E.G. COTTON, RAYON, LINEN, POLYESTER, SILK"></textarea>
                        <div class="form-text text-muted" style="font-size: 11px;">Enter options separated by commas (e.g. Cotton, Linen, Rayon, Polyester).</div>
                    </div>

                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_required" id="attrModalRequired" value="1">
                        <label class="form-check-label small fw-semibold text-dark" for="attrModalRequired">Required in production program</label>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2.5 px-4 justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold shadow-sm" id="btnSubmitAttrModal">
                        <i class="bi bi-check2-circle me-1"></i> Save Attribute
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const baseUrl = (window.location.origin && window.location.origin !== 'null') ? (window.location.origin + '<?= parse_url(url(''), PHP_URL_PATH) ?: '' ?>') : '<?= rtrim(url(''), '/') ?>';
const availableSellerRolls = <?= json_encode($rolls ?? []) ?>;
const allCategories = <?= json_encode($categories ?? []) ?>;
const allSubcategories = <?= json_encode($allSubcategories ?? []) ?>;
const allSubSubcategories = <?= json_encode($allSubSubcategories ?? []) ?>;
const existingProgramRolls = <?= json_encode(!empty($programRolls) ? $programRolls : [['seller_roll_id' => $program['seller_roll_id'] ?? null, 'roll_weight' => $program['roll_weight'] ?? 0, 'roll_price' => $program['roll_price'] ?? 0, 'category_id' => $program['category_id'] ?? null, 'subcategory_id' => $program['subcategory_id'] ?? null, 'sub_subcategory_id' => $program['sub_subcategory_id'] ?? null]]) ?>;
let rollCounter = 0;

function buildCategoryOptionsHtml(selectedCatId = '') {
    let html = '<option value="">-- Select Main Category --</option>';
    if (allCategories && allCategories.length > 0) {
        allCategories.forEach(cat => {
            const isSel = (selectedCatId && String(cat.id) === String(selectedCatId)) ? 'selected' : '';
            html += `<option value="${cat.id}" ${isSel}>${escapeHtml(cat.name)}</option>`;
        });
    }
    return html;
}

function buildSubcategoryOptionsHtml(catId, selectedSubcatId = '') {
    let html = '<option value="">-- Select Sub Category --</option>';
    if (!catId) return html;
    const filtered = (allSubcategories || []).filter(s => String(s.category_id) === String(catId));
    if (filtered.length > 0) {
        filtered.forEach(item => {
            const isSel = (selectedSubcatId && String(item.id) === String(selectedSubcatId)) ? 'selected' : '';
            html += `<option value="${item.id}" ${isSel}>${escapeHtml(item.name)}</option>`;
        });
    } else {
        html = '<option value="">-- No Subcategories Found --</option>';
    }
    return html;
}

function buildSubSubcategoryOptionsHtml(subcatId, selectedSubSubcatId = '') {
    let html = '<option value="">-- Select Sub Sub Category --</option>';
    if (!subcatId) return html;
    const filtered = (allSubSubcategories || []).filter(s => String(s.subcategory_id) === String(subcatId));
    if (filtered.length > 0) {
        filtered.forEach(item => {
            const isSel = (selectedSubSubcatId && String(item.id) === String(selectedSubSubcatId)) ? 'selected' : '';
            html += `<option value="${item.id}" ${isSel}>${escapeHtml(item.name)}</option>`;
        });
    } else {
        html = '<option value="">-- No Sub-Subcategories --</option>';
    }
    return html;
}

function getRollBaseLabel(r) {
    let label = (r.roll_number || ('Roll #' + r.id)) + ' — ' + (r.material_type || 'Fabric');
    if (r.color) label += ' (' + r.color + ')';
    if (r.design_name) label += ' [' + r.design_name + ']';
    label += ' — Supplier: ' + (r.seller_name || 'Seller');
    return label;
}

function buildRollOptionsHtml(selectedRollId = '', currentRowIndex = null) {
    let optionsHtml = '<option value="">-- Select Fabric Roll --</option>';
    if (availableSellerRolls && Array.isArray(availableSellerRolls)) {
        availableSellerRolls.forEach(r => {
            const origQty = parseInt(r.quantity || 0);
            const totalAvail = (r.available_quantity !== undefined && r.available_quantity !== null) ? parseInt(r.available_quantity) : origQty;
            const origWeight = parseFloat(r.weight || 0);
            const origPrice = parseFloat(r.price || 0);

            let otherUsed = 0;
            if (currentRowIndex !== null) {
                const cards = document.querySelectorAll('.program-roll-card');
                cards.forEach(card => {
                    const cardIdMatch = card.id.match(/\d+$/);
                    const cIdx = cardIdMatch ? parseInt(cardIdMatch[0]) : null;
                    if (cIdx !== currentRowIndex) {
                        const sel = card.querySelector('.roll-select');
                        const qInp = card.querySelector('.roll-qty-input');
                        if (sel && String(sel.value) === String(r.id)) {
                            otherUsed += parseInt(qInp?.value) || 0;
                        }
                    }
                });
            }

            const remAvail = Math.max(0, totalAvail - otherUsed);
            const baseLabel = getRollBaseLabel(r);
            let label = baseLabel;
            const dmgQty = parseInt(r.damaged_quantity || 0);
            if (dmgQty > 0) {
                label += ' • [' + dmgQty + ' pcs Damaged]';
            }
            if (remAvail <= 0) {
                label += ' • [OUT OF STOCK - 0 pcs]';
            } else {
                const rangeStr = r.range_summary ? (' (' + r.range_summary + ')') : '';
                label += ' • Available Stock: ' + remAvail + ' pcs' + rangeStr;
            }

            const isSel = selectedRollId && String(selectedRollId) === String(r.id);
            optionsHtml += `<option value="${r.id}" 
                data-weight="${origWeight}" 
                data-price="${origPrice}" 
                data-qty="${remAvail}"
                data-avail-qty="${remAvail}"
                data-total-avail="${totalAvail}"
                data-orig-qty="${origQty}"
                data-orig-weight="${origWeight}"
                data-orig-price="${origPrice}"
                data-seller="${escapeHtml(r.seller_name || '')}" 
                data-company="${escapeHtml(r.seller_company || '')}" 
                data-material="${escapeHtml(r.material_type || 'Fabric')}" 
                data-color="${escapeHtml(r.color || '')}" 
                data-design="${escapeHtml(r.design_name || '')}" 
                data-rollno="${escapeHtml(r.roll_number || ('Roll #' + r.id))}"
                ${isSel ? 'selected' : ''}>
                ${escapeHtml(label)}
            </option>`;
        });
    }
    return optionsHtml;
}

// Dynamic Multi-Roll Manager with 3-Tier Catalog Selection
function addProgramRollRow(rollData = null) {
    rollCounter++;
    const idx = rollCounter;
    const container = document.getElementById('programRollsContainer');
    const rowDiv = document.createElement('div');
    rowDiv.className = 'p-4 bg-white rounded-3 border shadow-sm program-roll-card mb-4';
    rowDiv.id = 'program_roll_card_' + idx;

    const optionsHtml = buildRollOptionsHtml(rollData ? (rollData.seller_roll_id || rollData.id) : '', idx);

    const initQty = rollData ? (rollData.roll_qty || rollData.quantity || '1') : '';
    const initWeight = rollData ? parseFloat(rollData.roll_weight || rollData.weight || 0).toFixed(2) : '';
    const initPrice = rollData ? parseFloat(rollData.roll_price || rollData.price || 0).toFixed(2) : '';
    const catId = rollData ? (rollData.category_id || '') : '';
    const subcatId = rollData ? (rollData.subcategory_id || '') : '';
    const subSubcatId = rollData ? (rollData.sub_subcategory_id || '') : '';

    const categoryOptionsHtml = buildCategoryOptionsHtml(catId);
    const subcategoryOptionsHtml = buildSubcategoryOptionsHtml(catId, subcatId);
    const subSubcategoryOptionsHtml = buildSubSubcategoryOptionsHtml(subcatId, subSubcatId);

    rowDiv.innerHTML = `
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border fw-semibold roll-badge-num px-2.5 py-1.5" style="font-size: 12px;">Roll #${idx}</span>
                <span class="badge bg-success-subtle text-success border font-monospace px-2.5 py-1.5 roll-scanned-badge ${rollData?.allocated_qr_codes ? '' : 'd-none'}" id="roll_scanned_badge_${idx}" style="font-size: 11px;">
                    <i class="bi bi-qr-code me-1"></i>Label: <strong id="roll_scanned_code_label_${idx}">${escapeHtml(rollData?.allocated_qr_codes || '')}</strong>
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2.5 btn-remove-roll fw-semibold" onclick="removeProgramRollRow(${idx})" title="Remove Roll">
                <i class="bi bi-trash me-1"></i> Remove
            </button>
        </div>

        <div id="roll_scanned_banner_${idx}" class="p-2 px-3 bg-success-subtle border border-success-subtle rounded-2 small font-monospace text-success fw-bold d-flex align-items-center justify-content-between mb-3 ${rollData?.allocated_qr_codes ? '' : 'd-none'}">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-upc-scan fs-6"></i>
                <span>Scanned QR Label ID:</span>
                <span class="badge bg-success text-white px-2 py-1 font-monospace" style="font-size: 12px;" id="roll_scanned_banner_code_${idx}">${escapeHtml(rollData?.allocated_qr_codes || '')}</span>
            </div>
            <span class="badge bg-white text-success border border-success-subtle px-2 py-0.5" style="font-size: 10px;">Individual Roll Verified</span>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-semibold text-dark mb-2">Select Raw Material Roll <span class="text-danger">*</span></label>
            <select name="rolls[${idx}][seller_roll_id]" class="form-select roll-select" required onchange="handleRollRowChange(this, ${idx})">
                ${optionsHtml}
            </select>
        </div>

        <div class="row g-3 mb-4 align-items-start">
            <div class="col-12 col-sm-4">
                <label for="roll_qty_${idx}" class="form-label small fw-semibold text-muted mb-1 roll-field-label">
                    <span class="roll-field-label-text">Roll Qty (pcs)</span>
                    <span class="roll-field-meta"><span class="badge bg-light text-secondary border" id="disp_avail_qty_${idx}" style="font-size: 10px;">Stock: 0</span></span>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-box-seam"></i></span>
                    <input type="number" step="1" min="1" name="rolls[${idx}][roll_qty]" id="roll_qty_${idx}" class="form-control fw-bold roll-qty-input" placeholder="${rollData ? '1' : 'Select Roll First'}" value="${initQty}" oninput="handleRollQtyInput(${idx})" ${rollData ? '' : 'disabled'} required>
                    <span class="input-group-text bg-light">pcs</span>
                </div>
                <div id="roll_qty_warning_${idx}" class="text-danger small mt-1 fw-semibold d-none" style="font-size: 11px;"></div>
            </div>
            <div class="col-12 col-sm-4">
                <label for="roll_weight_${idx}" class="form-label small fw-semibold text-muted mb-1 roll-field-label">
                    <span class="roll-field-label-text">Roll Weight (kg)</span>
                    <span class="roll-field-meta" aria-hidden="true">&nbsp;</span>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-speedometer2"></i></span>
                    <input type="number" step="0.01" min="0" name="rolls[${idx}][roll_weight]" id="roll_weight_${idx}" class="form-control fw-bold roll-weight-input bg-light" placeholder="0.00" value="${initWeight}" readonly style="cursor: not-allowed;" required>
                    <span class="input-group-text bg-light">kg</span>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <label for="roll_price_${idx}" class="form-label small fw-semibold text-muted mb-1 roll-field-label">
                    <span class="roll-field-label-text">Roll Price (₹)</span>
                    <span class="roll-field-meta" aria-hidden="true">&nbsp;</span>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light">₹</span>
                    <input type="number" step="0.01" min="0" name="rolls[${idx}][roll_price]" id="roll_price_${idx}" class="form-control fw-bold text-primary roll-price-input bg-light" placeholder="0.00" value="${initPrice}" readonly style="cursor: not-allowed;" required>
                </div>
            </div>
        </div>

        <div id="roll_specs_${idx}" class="p-3 bg-light rounded-3 small text-muted d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 ${rollData ? '' : 'd-none'}">
            <div><i class="bi bi-building text-primary me-1"></i> <span id="roll_supplier_${idx}">-</span></div>
            <div><i class="bi bi-palette text-primary me-1"></i> <span id="roll_color_${idx}">-</span></div>
            <div><i class="bi bi-brush text-primary me-1"></i> <span id="roll_design_${idx}">-</span></div>
        </div>

        <!-- Individual Piece QR Codes Area for THIS Roll -->
        <div id="roll_pieces_section_${idx}" class="p-3 bg-white rounded-3 border mb-4 shadow-sm ${rollData ? '' : 'd-none'}">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <span class="small fw-bold text-dark d-flex align-items-center gap-1.5">
                    <i class="bi bi-qr-code text-primary fs-6"></i>
                    <span>Individual Piece QR Codes</span>
                    <span class="badge bg-primary-subtle text-primary border" id="roll_pieces_badge_${idx}">0 Selected</span>
                </span>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 11px;" onclick="selectAllRollPieces(${idx})">Select All</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 fw-semibold" style="font-size: 11px;" onclick="clearRollPieces(${idx})">Clear</button>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1.5 p-2.5 bg-light rounded-2 border align-items-center" id="roll_pieces_container_${idx}" style="max-height: 125px; overflow-y: auto;">
                <span class="text-muted small fst-italic">Select a fabric roll to load piece QR codes...</span>
            </div>
            <input type="hidden" name="rolls[${idx}][allocated_qr_codes]" id="roll_allocated_qrs_${idx}" value="${escapeHtml(rollData?.allocated_qr_codes || '')}">
            <div class="form-text text-muted small mt-1.5 d-flex align-items-center justify-content-between" style="font-size: 11px;">
                <span><i class="bi bi-info-circle me-1"></i>Click piece QR tags to toggle selection. Selected QR tags are saved with this program.</span>
            </div>
        </div>

        <!-- 3-Tier Catalog Selection for THIS specific Roll -->
        <div class="p-3 p-md-4 bg-light-subtle rounded-3 border my-4 shadow-xs">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
                <span class="small fw-bold text-primary"><i class="bi bi-diagram-3 me-1"></i>Catalog Hierarchy for this Roll</span>
                <span class="badge bg-secondary-subtle text-secondary border small roll-catalog-badge" id="roll_cat_badge_${idx}">Catalog</span>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12 mb-1">
                    <label class="form-label small text-muted mb-1 fw-semibold" style="font-size: 11px;">Main Category</label>
                    <select name="rolls[${idx}][category_id]" class="form-select form-select-sm fw-semibold roll-cat-select" id="roll_cat_${idx}" onchange="handleRollCategoryChange(${idx}, this.value)">
                        ${categoryOptionsHtml}
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small text-muted mb-1 fw-semibold" style="font-size: 11px;">Sub Category</label>
                    <select name="rolls[${idx}][subcategory_id]" class="form-select form-select-sm roll-subcat-select" id="roll_subcat_${idx}" onchange="handleRollSubcategoryChange(${idx}, this.value)" ${catId ? '' : 'disabled'}>
                        ${subcategoryOptionsHtml}
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small text-muted mb-1 fw-semibold" style="font-size: 11px;">Sub Sub Category</label>
                    <select name="rolls[${idx}][sub_subcategory_id]" class="form-select form-select-sm roll-subsubcat-select" id="roll_subsubcat_${idx}" onchange="handleRollSubSubcategoryChange(${idx}, this.value)" ${subcatId ? '' : 'disabled'}>
                        ${subSubcategoryOptionsHtml}
                    </select>
                </div>
            </div>

            <!-- Dynamic Category Attributes & Specifications -->
            <div class="mt-4 pt-4 border-top" id="roll_attrs_box_${idx}">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <span class="small fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size: 12px;">
                        <i class="bi bi-sliders text-primary"></i>
                        <span>Category Attributes &amp; Specifications</span>
                    </span>
                    <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2.5 fw-semibold btn-add-attr-trigger" style="font-size: 11px;" onclick="openAddAttributeModal(${idx})" title="Add new attribute for this category level">
                        <i class="bi bi-plus-circle me-1"></i>+ Add Attribute
                    </button>
                </div>
                <div id="roll_attrs_container_${idx}" class="roll-attributes-container d-flex flex-column gap-3">
                    <div class="text-muted small py-3 px-3 bg-white rounded-3 border border-dashed fst-italic" style="font-size: 11px;">
                        <i class="bi bi-info-circle me-1"></i>Select a Main Category above to view and assign category attributes.
                    </div>
                </div>
            </div>
        </div>
    `;

    container.appendChild(rowDiv);
    updateRollIndices();

    if (rollData) {
        const sel = rowDiv.querySelector('.roll-select');
        if (sel) sel.dataset.previousRollId = String(sel.value || '');
        handleRollRowChange(sel, idx, false);
    }

    if (catId) {
        loadRollSubcategories(idx, catId, subcatId, subSubcatId);
        loadRollCategoryAttributes(idx, catId, subcatId, subSubcatId, rollData?.attributes);
    } else {
        updateRollCatalogBadge(idx);
        loadRollCategoryAttributes(idx, '', '', '');
    }

    syncRollStockAllocations();
    renderRollPieces(idx);
}

function removeProgramRollRow(counterId) {
    const cards = document.querySelectorAll('.program-roll-card');
    if (cards.length <= 1) {
        alert('A production program must have at least one assigned fabric roll.');
        return;
    }
    const row = document.getElementById('program_roll_card_' + counterId);
    if (row) {
        row.remove();
        updateRollIndices();
        syncRollStockAllocations();
    }
}

function updateRollIndices() {
    const cards = document.querySelectorAll('.program-roll-card');
    cards.forEach((card, idx) => {
        const badge = card.querySelector('.roll-badge-num');
        if (badge) {
            badge.innerText = 'Roll #' + (idx + 1);
        }
    });
}

function handleRollQtyInput(counterId) {
    const card = document.getElementById('program_roll_card_' + counterId);
    if (!card) return;
    const select = card.querySelector('.roll-select');
    if (!select || !select.value) {
        calculateTotalRollMetrics();
        return;
    }
    syncRollStockAllocations();
    renderRollPieces(counterId);
}

function getSelectedRollPieceCodes(counterId) {
    const input = document.getElementById('roll_allocated_qrs_' + counterId);
    if (!input || !input.value.trim()) return [];
    return [...new Set(input.value.split(',').map(code => code.trim().toUpperCase()).filter(Boolean))];
}

function getOtherRollPieceCodes(counterId, rollId) {
    const codes = new Set();
    document.querySelectorAll('.program-roll-card').forEach(card => {
        const match = card.id.match(/\d+$/);
        const cardId = match ? parseInt(match[0], 10) : 0;
        const select = card.querySelector('.roll-select');
        if (cardId !== counterId && select && String(select.value) === String(rollId)) {
            getSelectedRollPieceCodes(cardId).forEach(code => codes.add(code));
        }
    });
    return codes;
}

function getRollRowRemainingStock(counterId, rollObj) {
    const totalAvailable = Number.parseInt(rollObj.available_quantity ?? rollObj.quantity ?? 0, 10) || 0;
    let otherRowsUsed = 0;
    document.querySelectorAll('.program-roll-card').forEach(card => {
        const match = card.id.match(/\d+$/);
        const cardId = match ? parseInt(match[0], 10) : 0;
        const select = card.querySelector('.roll-select');
        const quantity = card.querySelector('.roll-qty-input');
        if (cardId !== counterId && select && String(select.value) === String(rollObj.id)) {
            otherRowsUsed += Number.parseInt(quantity?.value || '0', 10) || 0;
        }
    });
    return Math.max(0, totalAvailable - otherRowsUsed);
}

function renderRollPieces(counterId) {
    const card = document.getElementById('program_roll_card_' + counterId);
    const container = document.getElementById('roll_pieces_container_' + counterId);
    const badge = document.getElementById('roll_pieces_badge_' + counterId);
    const select = card?.querySelector('.roll-select');
    const rollObj = select?.value
        ? availableSellerRolls.find(roll => String(roll.id) === String(select.value))
        : null;

    if (!container) return;
    const selectedCodes = new Set(getSelectedRollPieceCodes(counterId));
    const quantityInput = document.getElementById('roll_qty_' + counterId);
    if (quantityInput) quantityInput.readOnly = selectedCodes.size > 0;
    if (badge) badge.textContent = `${selectedCodes.size} Selected`;

    const scannedBadge = document.getElementById('roll_scanned_badge_' + counterId);
    const scannedLabel = document.getElementById('roll_scanned_code_label_' + counterId);
    const scannedBanner = document.getElementById('roll_scanned_banner_' + counterId);
    const scannedBannerCode = document.getElementById('roll_scanned_banner_code_' + counterId);

    if (selectedCodes.size > 0) {
        const codeList = [...selectedCodes].join(', ');
        if (scannedLabel) scannedLabel.textContent = codeList;
        if (scannedBadge) scannedBadge.classList.remove('d-none');
        if (scannedBannerCode) scannedBannerCode.textContent = codeList;
        if (scannedBanner) scannedBanner.classList.remove('d-none');
    } else {
        if (scannedBadge) scannedBadge.classList.add('d-none');
        if (scannedBanner) scannedBanner.classList.add('d-none');
    }

    if (!rollObj) {
        container.innerHTML = '<span class="text-muted small fst-italic">Select a fabric roll to load piece QR codes...</span>';
        return;
    }

    const pieces = Array.isArray(rollObj.pieces) ? rollObj.pieces : [];
    if (!pieces.length) {
        container.innerHTML = '<span class="text-muted small fst-italic">No individual piece QR codes are available for this roll.</span>';
        return;
    }

    const codesInOtherRows = getOtherRollPieceCodes(counterId, rollObj.id);
    container.innerHTML = pieces.map(piece => {
        const code = String(piece.code || '').toUpperCase();
        const isSelected = selectedCodes.has(code);
        const isAvailable = piece.status === 'AVAILABLE' && !codesInOtherRows.has(code);
        const disabled = !isSelected && !isAvailable;
        const statusText = isAvailable ? '' : (piece.meta || piece.status || 'Unavailable');
        return `
            <label class="btn btn-sm ${isSelected ? 'btn-primary text-white' : 'btn-outline-secondary'} d-inline-flex align-items-center gap-1" title="${escapeHtml(statusText)}">
                <input type="checkbox" class="form-check-input m-0" data-piece-code="${escapeHtml(code)}" ${isSelected ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                <span>${escapeHtml(code)}</span>
            </label>`;
    }).join('');

    container.querySelectorAll('input[data-piece-code]').forEach(input => {
        input.addEventListener('change', () => {
            updateRollPieceSelection(counterId, input.dataset.pieceCode, input.checked);
        });
    });
}

function updateRollPieceSelection(counterId, code, isSelected) {
    const card = document.getElementById('program_roll_card_' + counterId);
    const select = card?.querySelector('.roll-select');
    const input = document.getElementById('roll_allocated_qrs_' + counterId);
    const quantity = document.getElementById('roll_qty_' + counterId);
    const rollObj = select?.value
        ? availableSellerRolls.find(roll => String(roll.id) === String(select.value))
        : null;
    if (!rollObj || !input || !quantity) return false;

    const normalizedCode = String(code || '').trim().toUpperCase();
    const selectedCodes = new Set(getSelectedRollPieceCodes(counterId));
    const piece = (rollObj.pieces || []).find(item => String(item.code || '').toUpperCase() === normalizedCode);
    if (isSelected) {
        if (!piece || piece.status !== 'AVAILABLE') {
            showScanFeedback(`QR label ${escapeHtml(normalizedCode)} is not available for this program.`, 'warning');
            renderRollPieces(counterId);
            return false;
        }
        if (getOtherRollPieceCodes(counterId, rollObj.id).has(normalizedCode)) {
            showScanFeedback(`QR label ${escapeHtml(normalizedCode)} is already assigned to another roll row.`, 'warning');
            renderRollPieces(counterId);
            return false;
        }
        if (!selectedCodes.has(normalizedCode) && selectedCodes.size >= getRollRowRemainingStock(counterId, rollObj)) {
            showScanFeedback('The selected QR count cannot exceed the remaining roll stock.', 'warning');
            renderRollPieces(counterId);
            return false;
        }
        selectedCodes.add(normalizedCode);
    } else {
        selectedCodes.delete(normalizedCode);
    }

    input.value = [...selectedCodes].join(',');
    quantity.value = String(selectedCodes.size);
    syncRollStockAllocations();
    renderRollPieces(counterId);
    return true;
}

function selectAllRollPieces(counterId) {
    const card = document.getElementById('program_roll_card_' + counterId);
    const select = card?.querySelector('.roll-select');
    const input = document.getElementById('roll_allocated_qrs_' + counterId);
    const quantity = document.getElementById('roll_qty_' + counterId);
    const rollObj = select?.value
        ? availableSellerRolls.find(roll => String(roll.id) === String(select.value))
        : null;
    if (!rollObj || !input || !quantity) return;

    const codesInOtherRows = getOtherRollPieceCodes(counterId, rollObj.id);
    const maxQuantity = getRollRowRemainingStock(counterId, rollObj);
    const selectedCodes = (rollObj.pieces || [])
        .filter(piece => piece.status === 'AVAILABLE' && !codesInOtherRows.has(String(piece.code || '').toUpperCase()))
        .slice(0, maxQuantity)
        .map(piece => String(piece.code).toUpperCase());
    input.value = selectedCodes.join(',');
    quantity.value = String(selectedCodes.length);
    syncRollStockAllocations();
    renderRollPieces(counterId);
}

function clearRollPieces(counterId) {
    const input = document.getElementById('roll_allocated_qrs_' + counterId);
    const quantity = document.getElementById('roll_qty_' + counterId);
    if (input) input.value = '';
    if (quantity) quantity.value = '0';
    syncRollStockAllocations();
    renderRollPieces(counterId);
}

function handleRollRowChange(selectElement, counterId, autoPopulate = true) {
    const qtyInput = document.getElementById('roll_qty_' + counterId);
    const weightInput = document.getElementById('roll_weight_' + counterId);
    const priceInput = document.getElementById('roll_price_' + counterId);
    const allocatedCodesInput = document.getElementById('roll_allocated_qrs_' + counterId);
    const specsDiv = document.getElementById('roll_specs_' + counterId);
    const piecesSection = document.getElementById('roll_pieces_section_' + counterId);
    const warningDiv = document.getElementById('roll_qty_warning_' + counterId);
    const catSelect = document.getElementById('roll_cat_' + counterId);
    const previousRollId = selectElement.dataset.previousRollId;
    const currentSelectedRollId = String(selectElement.value || '');
    if (previousRollId !== undefined && previousRollId !== currentSelectedRollId && allocatedCodesInput) {
        allocatedCodesInput.value = '';
    }
    selectElement.dataset.previousRollId = currentSelectedRollId;

    if (!selectElement.value) {
        if (specsDiv) specsDiv.classList.add('d-none');
        if (piecesSection) piecesSection.classList.add('d-none');
        if (warningDiv) {
            warningDiv.innerHTML = '';
            warningDiv.classList.add('d-none');
        }
        if (qtyInput) {
            qtyInput.value = '';
            qtyInput.disabled = true;
            qtyInput.placeholder = 'Select Roll First';
            qtyInput.removeAttribute('max');
            qtyInput.classList.remove('is-invalid', 'border-danger');
        }
        if (weightInput) weightInput.value = '';
        if (priceInput) priceInput.value = '';
        if (catSelect) {
            catSelect.value = '';
            catSelect.disabled = true;
        }
        syncRollStockAllocations();
        renderRollPieces(counterId);
        return;
    }

    if (qtyInput) {
        qtyInput.disabled = false;
        qtyInput.placeholder = 'e.g. 1';
    }
    if (catSelect) {
        catSelect.disabled = false;
    }

    const rollObj = availableSellerRolls.find(r => String(r.id) === String(currentSelectedRollId));
    if (!rollObj) {
        syncRollStockAllocations();
        return;
    }
    const selectedQrCodes = getSelectedRollPieceCodes(counterId);
    if (selectedQrCodes.length && qtyInput) {
        qtyInput.value = String(selectedQrCodes.length);
    }

    const origQty = parseInt(rollObj.quantity || 0);
    const totalAvail = (rollObj.available_quantity !== undefined && rollObj.available_quantity !== null) ? parseInt(rollObj.available_quantity) : origQty;
    const origWeight = parseFloat(rollObj.weight || 0);
    const origPrice = parseFloat(rollObj.price || 0);

    let otherUsed = 0;
    const allCards = document.querySelectorAll('.program-roll-card');
    allCards.forEach(c => {
        const cIdMatch = c.id.match(/\d+$/);
        const cIdx = cIdMatch ? parseInt(cIdMatch[0]) : null;
        if (cIdx !== counterId) {
            const sel = c.querySelector('.roll-select');
            const qInp = c.querySelector('.roll-qty-input');
            if (sel && String(sel.value) === String(currentSelectedRollId)) {
                otherUsed += parseInt(qInp?.value) || 0;
            }
        }
    });

    const currentAvail = Math.max(0, totalAvail - otherUsed);

    const supSpan = document.getElementById('roll_supplier_' + counterId);
    const colSpan = document.getElementById('roll_color_' + counterId);
    const desSpan = document.getElementById('roll_design_' + counterId);
    if (supSpan) supSpan.innerText = rollObj.seller_name ? (rollObj.seller_name + (rollObj.seller_company ? ` (${rollObj.seller_company})` : '')) : (rollObj.seller_company || 'Supplier');
    if (colSpan) colSpan.innerText = rollObj.color || 'Plain';
    if (desSpan) desSpan.innerText = rollObj.design_name || 'Standard Design';
    if (specsDiv) specsDiv.classList.remove('d-none');

    const stockBadge = document.getElementById('disp_avail_qty_' + counterId);
    if (stockBadge) {
        stockBadge.innerText = `Stock: ${currentAvail} pcs`;
        stockBadge.className = `badge ${currentAvail > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'} ms-1`;
    }

    if (qtyInput) {
        qtyInput.setAttribute('max', totalAvail);
        if (autoPopulate && (!qtyInput.value || parseInt(qtyInput.value) <= 0)) {
            qtyInput.value = currentAvail > 0 ? 1 : 0;
        }
    }

    syncRollStockAllocations();
    renderRollPieces(counterId);
}

function syncRollStockAllocations() {
    const cards = document.querySelectorAll('.program-roll-card');
    const rollUsageMap = {};

    cards.forEach(card => {
        const sel = card.querySelector('.roll-select');
        const qInp = card.querySelector('.roll-qty-input');
        if (sel && sel.value) {
            const rollId = String(sel.value);
            const qty = parseInt(qInp?.value) || 0;
            rollUsageMap[rollId] = (rollUsageMap[rollId] || 0) + qty;
        }
    });

    cards.forEach(card => {
        const cardIdMatch = card.id.match(/\d+$/);
        const counterId = cardIdMatch ? parseInt(cardIdMatch[0]) : null;
        if (!counterId) return;

        const sel = card.querySelector('.roll-select');
        const qtyInp = document.getElementById('roll_qty_' + counterId);
        const weightInput = document.getElementById('roll_weight_' + counterId);
        const priceInput = document.getElementById('roll_price_' + counterId);
        const warningDiv = document.getElementById('roll_qty_warning_' + counterId);
        const stockBadge = document.getElementById('disp_avail_qty_' + counterId);

        if (!sel || !sel.value) {
            if (stockBadge) {
                stockBadge.innerText = 'Stock: 0 pcs';
                stockBadge.className = 'badge bg-light text-secondary border ms-1';
            }
            return;
        }

        const rollId = String(sel.value);
        const rollObj = availableSellerRolls.find(r => String(r.id) === rollId);
        if (!rollObj) return;

        const origQty = parseInt(rollObj.quantity || 0);
        const totalAvail = (rollObj.available_quantity !== undefined && rollObj.available_quantity !== null) ? parseInt(rollObj.available_quantity) : origQty;
        const origWeight = parseFloat(rollObj.weight || 0);
        const origPrice = parseFloat(rollObj.price || 0);

        // Keep supplier, color and design in sync
        const supSpan = document.getElementById('roll_supplier_' + counterId);
        const colSpan = document.getElementById('roll_color_' + counterId);
        const desSpan = document.getElementById('roll_design_' + counterId);
        const specsDiv = document.getElementById('roll_specs_' + counterId);
        if (supSpan) supSpan.innerText = rollObj.seller_name ? (rollObj.seller_name + (rollObj.seller_company ? ` (${rollObj.seller_company})` : '')) : (rollObj.seller_company || 'Supplier');
        if (colSpan) colSpan.innerText = rollObj.color || 'Plain';
        if (desSpan) desSpan.innerText = rollObj.design_name || 'Standard Design';
        if (specsDiv) specsDiv.classList.remove('d-none');

        const thisRowQty = parseInt(qtyInp?.value) || 0;
        const totalUsed = rollUsageMap[rollId] || 0;
        const otherRowsUsed = totalUsed - thisRowQty;
        const currentAvail = Math.max(0, totalAvail - otherRowsUsed);

        if (stockBadge) {
            stockBadge.innerText = `Stock: ${currentAvail} pcs`;
            stockBadge.className = `badge ${currentAvail >= thisRowQty && currentAvail > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'} ms-1`;
        }

        if (origQty > 0 && thisRowQty > 0) {
            const calculatedWeight = (origWeight / origQty) * thisRowQty;
            const calculatedPrice = (origPrice / origQty) * thisRowQty;
            if (weightInput) weightInput.value = calculatedWeight.toFixed(2);
            if (priceInput) priceInput.value = calculatedPrice.toFixed(2);
        } else {
            if (weightInput) weightInput.value = '0.00';
            if (priceInput) priceInput.value = '0.00';
        }

        if (warningDiv && qtyInp) {
            if (thisRowQty > currentAvail) {
                warningDiv.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>Exceeds available stock (${currentAvail} pcs available)`;
                warningDiv.classList.remove('d-none');
                qtyInp.classList.add('is-invalid', 'border-danger');
            } else if (thisRowQty <= 0) {
                warningDiv.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>Qty must be at least 1`;
                warningDiv.classList.remove('d-none');
                qtyInp.classList.add('is-invalid', 'border-danger');
            } else {
                warningDiv.innerHTML = '';
                warningDiv.classList.add('d-none');
                qtyInp.classList.remove('is-invalid', 'border-danger');
            }
        }

        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;
            const rId = String(opt.value);
            const rObj = availableSellerRolls.find(r => String(r.id) === rId);
            if (!rObj) return;

            const rOrigQty = parseInt(rObj.quantity || 0);
            const rTotalAvail = (rObj.available_quantity !== undefined && rObj.available_quantity !== null) ? parseInt(rObj.available_quantity) : rOrigQty;
            const rOtherUsed = (rId === rollId) ? otherRowsUsed : (rollUsageMap[rId] || 0);
            const rRemAvail = Math.max(0, rTotalAvail - rOtherUsed);

            let label = getRollBaseLabel(rObj);
            if (rRemAvail <= 0) {
                label += ' • [OUT OF STOCK - 0 pcs]';
            } else {
                label += ' • Available Stock: ' + rRemAvail + ' pcs';
            }
            opt.text = label;
            opt.setAttribute('data-avail-qty', rRemAvail);
        });
    });

    calculateTotalRollMetrics();
}

function calculateTotalRollMetrics() {
    let totalQty = 0;
    let totalWeight = 0;
    let totalPrice = 0;
    let count = 0;
    let firstRollId = '';

    document.querySelectorAll('.program-roll-card').forEach(card => {
        const select = card.querySelector('.roll-select');
        const qtyInp = card.querySelector('.roll-qty-input');
        const weightInp = card.querySelector('.roll-weight-input');
        const priceInp = card.querySelector('.roll-price-input');

        if (select && select.value) {
            count++;
            if (!firstRollId) firstRollId = select.value;
            totalQty += parseInt(qtyInp?.value) || 0;
            totalWeight += parseFloat(weightInp?.value) || 0;
            totalPrice += parseFloat(priceInp?.value) || 0;
        }
    });

    const avgRate = totalWeight > 0 ? (totalPrice / totalWeight) : 0;

    const dispCount = document.getElementById('dispTotalRollCount');
    if (dispCount) dispCount.innerText = count + ' roll(s) (' + totalQty + ' pcs)';
    const dispWeight = document.getElementById('dispTotalRollWeight');
    if (dispWeight) dispWeight.innerText = totalWeight.toFixed(2) + ' kg';
    const dispPrice = document.getElementById('dispTotalRollPrice');
    if (dispPrice) dispPrice.innerText = '₹' + totalPrice.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dispAvg = document.getElementById('dispAvgRatePerKg');
    if (dispAvg) dispAvg.innerText = '₹' + avgRate.toFixed(2) + ' / kg';

    const hiddenRollId = document.getElementById('primary_seller_roll_id');
    const hiddenWeight = document.getElementById('total_roll_weight');
    const hiddenPrice = document.getElementById('total_roll_price');

    if (hiddenRollId) hiddenRollId.value = firstRollId;
    if (hiddenWeight) hiddenWeight.value = totalWeight.toFixed(2);
    if (hiddenPrice) hiddenPrice.value = totalPrice.toFixed(2);
}

// 3-Tier Dynamic Catalog Handlers Per Roll Row
function handleRollCategoryChange(counterId, catId) {
    const subcatSelect = document.getElementById('roll_subcat_' + counterId);
    const subSubcatSelect = document.getElementById('roll_subsubcat_' + counterId);

    if (!catId) {
        if (subcatSelect) {
            subcatSelect.innerHTML = '<option value="">-- Sub Category --</option>';
            subcatSelect.disabled = true;
        }
        if (subSubcatSelect) {
            subSubcatSelect.innerHTML = '<option value="">-- Sub Sub Category --</option>';
            subSubcatSelect.disabled = true;
        }
        updateRollCatalogBadge(counterId);
        loadRollCategoryAttributes(counterId, '', '', '');
        return;
    }

    if (subcatSelect) {
        subcatSelect.disabled = false;
        subcatSelect.innerHTML = buildSubcategoryOptionsHtml(catId);
    }
    if (subSubcatSelect) {
        subSubcatSelect.innerHTML = '<option value="">-- Sub Sub Category --</option>';
        subSubcatSelect.disabled = true;
    }
    updateRollCatalogBadge(counterId);
    loadRollCategoryAttributes(counterId, catId, '', '');

    // Fetch via API to ensure latest catalog sync
    fetch(`${baseUrl}/api/subcategories/${catId}`)
        .then(res => res.json())
        .then(res => {
            const list = (res.success && res.data) ? res.data : (Array.isArray(res) ? res : []);
            if (list.length > 0 && subcatSelect) {
                const currentVal = subcatSelect.value;
                let options = '<option value="">-- Select Sub Category --</option>';
                list.forEach(item => {
                    const isSel = (currentVal && String(item.id) === String(currentVal)) ? 'selected' : '';
                    options += `<option value="${item.id}" ${isSel}>${escapeHtml(item.name)}</option>`;
                });
                subcatSelect.innerHTML = options;
            }
            updateRollCatalogBadge(counterId);
        })
        .catch(err => {
            console.warn('API subcategory notice for roll ' + counterId, err);
        });
}

function handleRollSubcategoryChange(counterId, subcatId) {
    const subSubcatSelect = document.getElementById('roll_subsubcat_' + counterId);
    const currentCatId = document.getElementById('roll_cat_' + counterId)?.value || '';

    if (!subcatId) {
        if (subSubcatSelect) {
            subSubcatSelect.innerHTML = '<option value="">-- Sub Sub Category --</option>';
            subSubcatSelect.disabled = true;
        }
        updateRollCatalogBadge(counterId);
        loadRollCategoryAttributes(counterId, currentCatId, '', '');
        return;
    }

    if (subSubcatSelect) {
        subSubcatSelect.disabled = false;
        subSubcatSelect.innerHTML = buildSubSubcategoryOptionsHtml(subcatId);
    }
    updateRollCatalogBadge(counterId);
    loadRollCategoryAttributes(counterId, currentCatId, subcatId, '');

    // Fetch via API to ensure latest catalog sync
    fetch(`${baseUrl}/api/sub-subcategories/${subcatId}`)
        .then(res => res.json())
        .then(res => {
            const list = (res.success && res.data) ? res.data : (Array.isArray(res) ? res : []);
            if (list.length > 0 && subSubcatSelect) {
                const currentVal = subSubcatSelect.value;
                let options = '<option value="">-- Select Sub Sub Category --</option>';
                list.forEach(item => {
                    const isSel = (currentVal && String(item.id) === String(currentVal)) ? 'selected' : '';
                    options += `<option value="${item.id}" ${isSel}>${escapeHtml(item.name)}</option>`;
                });
                subSubcatSelect.innerHTML = options;
            }
            updateRollCatalogBadge(counterId);
        })
        .catch(err => {
            console.warn('API sub-subcategory notice for roll ' + counterId, err);
        });
}

function handleRollSubSubcategoryChange(counterId, subSubcatId) {
    updateRollCatalogBadge(counterId);
    const currentCatId = document.getElementById('roll_cat_' + counterId)?.value || '';
    const currentSubcatId = document.getElementById('roll_subcat_' + counterId)?.value || '';
    loadRollCategoryAttributes(counterId, currentCatId, currentSubcatId, subSubcatId);
}

function loadRollSubcategories(counterId, catId, selectedSubcatId = '', selectedSubSubcatId = '') {
    const subcatSelect = document.getElementById('roll_subcat_' + counterId);
    if (!subcatSelect || !catId) return;

    subcatSelect.disabled = false;
    subcatSelect.innerHTML = buildSubcategoryOptionsHtml(catId, selectedSubcatId);

    if (selectedSubcatId) {
        loadRollSubSubcategories(counterId, selectedSubcatId, selectedSubSubcatId);
    } else {
        updateRollCatalogBadge(counterId);
    }

    // Secondary fetch from API to ensure sync
    fetch(`${baseUrl}/api/subcategories/${catId}`)
        .then(res => res.json())
        .then(res => {
            const list = (res.success && res.data) ? res.data : (Array.isArray(res) ? res : []);
            if (list.length > 0) {
                let options = '<option value="">-- Select Sub Category --</option>';
                list.forEach(item => {
                    const isSel = (selectedSubcatId && String(item.id) === String(selectedSubcatId)) ? 'selected' : '';
                    options += `<option value="${item.id}" ${isSel}>${escapeHtml(item.name)}</option>`;
                });
                subcatSelect.innerHTML = options;
                if (selectedSubcatId) {
                    loadRollSubSubcategories(counterId, selectedSubcatId, selectedSubSubcatId);
                }
            }
            updateRollCatalogBadge(counterId);
        })
        .catch(err => {
            console.warn('API subcategories fetch notice:', err);
        });
}

function loadRollSubSubcategories(counterId, subcatId, selectedSubSubcatId = '') {
    const subSubcatSelect = document.getElementById('roll_subsubcat_' + counterId);
    if (!subSubcatSelect || !subcatId) return;

    subSubcatSelect.disabled = false;
    subSubcatSelect.innerHTML = buildSubSubcategoryOptionsHtml(subcatId, selectedSubSubcatId);
    updateRollCatalogBadge(counterId);

    // Secondary fetch from API to ensure sync
    fetch(`${baseUrl}/api/sub-subcategories/${subcatId}`)
        .then(res => res.json())
        .then(res => {
            const list = (res.success && res.data) ? res.data : (Array.isArray(res) ? res : []);
            if (list.length > 0) {
                let options = '<option value="">-- Select Sub Sub Category --</option>';
                list.forEach(item => {
                    const isSel = (selectedSubSubcatId && String(item.id) === String(selectedSubSubcatId)) ? 'selected' : '';
                    options += `<option value="${item.id}" ${isSel}>${escapeHtml(item.name)}</option>`;
                });
                subSubcatSelect.innerHTML = options;
            }
            updateRollCatalogBadge(counterId);
        })
        .catch(err => {
            console.warn('API sub-subcategories fetch notice:', err);
        });
}

function updateRollCatalogBadge(counterId) {
    const catSelect = document.getElementById('roll_cat_' + counterId);
    const subcatSelect = document.getElementById('roll_subcat_' + counterId);
    const subSubcatSelect = document.getElementById('roll_subsubcat_' + counterId);
    const badge = document.getElementById('roll_cat_badge_' + counterId);

    if (!badge || !catSelect) return;

    const parts = [];
    if (catSelect.selectedIndex > 0) parts.push(catSelect.options[catSelect.selectedIndex].text);
    if (subcatSelect && subcatSelect.selectedIndex > 0 && subcatSelect.value) parts.push(subcatSelect.options[subcatSelect.selectedIndex].text);
    if (subSubcatSelect && subSubcatSelect.selectedIndex > 0 && subSubcatSelect.value) parts.push(subSubcatSelect.options[subSubcatSelect.selectedIndex].text);

    if (parts.length > 0) {
        badge.innerText = parts.join(' › ');
        badge.className = 'badge bg-primary-subtle text-primary border small roll-catalog-badge';
    } else {
        badge.innerText = 'No Catalog Selected';
        badge.className = 'badge bg-secondary-subtle text-secondary border small roll-catalog-badge';
    }
}

// MULTI-SELECT ROLLS MODAL HANDLERS
function openMultiRollModal() {
    const tableBody = document.getElementById('multiRollTableBody');
    if (!tableBody) return;

    // Determine which rolls are already assigned on page
    const currentlyAssignedRollIds = new Set();
    document.querySelectorAll('.program-roll-card .roll-select').forEach(sel => {
        if (sel.value) currentlyAssignedRollIds.add(String(sel.value));
    });

    let rowsHtml = '';
    if (availableSellerRolls && Array.isArray(availableSellerRolls)) {
        availableSellerRolls.forEach(r => {
            const origQty = parseInt(r.quantity || 0);
            const totalAvail = (r.available_quantity !== undefined && r.available_quantity !== null) ? parseInt(r.available_quantity) : origQty;
            const origWeight = parseFloat(r.weight || 0).toFixed(2);
            const origPrice = parseFloat(r.price || 0).toFixed(2);
            const isAssigned = currentlyAssignedRollIds.has(String(r.id));

            const searchString = [
                r.roll_number || ('Roll #' + r.id),
                r.material_type || 'Fabric',
                r.color || '',
                r.design_name || '',
                r.seller_name || '',
                r.seller_company || ''
            ].join(' ').toLowerCase();

            rowsHtml += `
                <tr class="multi-roll-item-row ${isAssigned ? 'table-light' : ''}" 
                    data-roll-id="${r.id}" 
                    data-search-text="${escapeHtml(searchString)}"
                    data-stock="${totalAvail}">
                    <td class="text-center">
                        <input type="checkbox" 
                               class="form-check-input multi-roll-checkbox" 
                               value="${r.id}" 
                               data-roll-id="${r.id}"
                               ${isAssigned ? 'checked disabled title="Already added to program"' : ''}
                               onchange="updateMultiSelectedCount()">
                    </td>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(r.roll_number || ('Roll #' + r.id))}</div>
                        <small class="text-muted">${escapeHtml(r.material_type || 'Fabric')}</small>
                        ${isAssigned ? '<span class="badge bg-primary-subtle text-primary border ms-1" style="font-size: 10px;">Already in Program</span>' : ''}
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(r.color || '—')}</div>
                        ${r.design_name ? `<span class="badge bg-light text-secondary border small">[${escapeHtml(r.design_name)}]</span>` : ''}
                    </td>
                    <td>
                        <div class="small fw-medium text-dark">${escapeHtml(r.seller_name || '—')}</div>
                        ${r.seller_company ? `<div class="small text-muted" style="font-size: 11px;">${escapeHtml(r.seller_company)}</div>` : ''}
                    </td>
                    <td>
                        <span class="badge ${totalAvail > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'}">
                            ${totalAvail} pcs Avail
                        </span>
                        ${(parseInt(r.damaged_quantity) > 0 || parseFloat(r.damaged_weight) > 0) ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block mt-1" style="font-size: 10px;"><i class="bi bi-exclamation-triangle me-1"></i>${r.damaged_quantity || 0} pcs Damaged</span>` : ''}
                    </td>
                    <td class="text-end">
                        <div class="fw-semibold text-dark small">${origWeight} kg</div>
                        <div class="text-success small fw-bold">₹${origPrice}</div>
                    </td>
                </tr>
            `;
        });
    }

    tableBody.innerHTML = rowsHtml || '<tr><td colspan="6" class="text-center py-4 text-muted">No seller fabric rolls found in database.</td></tr>';

    const searchInput = document.getElementById('multiRollSearchInput');
    if (searchInput) searchInput.value = '';
    filterMultiRollList();
    updateMultiSelectedCount();

    const modalEl = document.getElementById('multiRollSelectModal');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function filterMultiRollList() {
    const searchInput = document.getElementById('multiRollSearchInput');
    const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
    const rows = document.querySelectorAll('.multi-roll-item-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.getAttribute('data-search-text') || '';
        if (!term || text.includes(term)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const alertEl = document.getElementById('noMultiRollsAlert');
    if (alertEl) {
        alertEl.classList.toggle('d-none', visibleCount > 0 || rows.length === 0);
    }
    updateMultiSelectedCount();
}

function clearMultiRollSearch() {
    const searchInput = document.getElementById('multiRollSearchInput');
    if (searchInput) {
        searchInput.value = '';
        filterMultiRollList();
    }
}

function updateMultiSelectedCount() {
    const checkboxes = document.querySelectorAll('.multi-roll-checkbox:checked:not(:disabled)');
    const count = checkboxes.length;
    const badge = document.getElementById('multiSelectedCountBadge');
    const footer = document.getElementById('multiRollFooterSummary');
    if (badge) badge.textContent = `${count} Selected`;
    if (footer) footer.textContent = `${count} new roll(s) ready to add`;

    const visibleCheckboxes = Array.from(document.querySelectorAll('.multi-roll-item-row'))
        .filter(r => r.style.display !== 'none')
        .map(r => r.querySelector('.multi-roll-checkbox:not(:disabled)'))
        .filter(cb => cb !== null);

    const masterCheckbox = document.getElementById('masterMultiRollCheckbox');
    if (masterCheckbox && visibleCheckboxes.length > 0) {
        masterCheckbox.checked = visibleCheckboxes.every(cb => cb.checked);
        masterCheckbox.indeterminate = visibleCheckboxes.some(cb => cb.checked) && !masterCheckbox.checked;
    } else if (masterCheckbox) {
        masterCheckbox.checked = false;
        masterCheckbox.indeterminate = false;
    }
}

function toggleMasterMultiRoll(isChecked) {
    const visibleRows = Array.from(document.querySelectorAll('.multi-roll-item-row')).filter(r => r.style.display !== 'none');
    visibleRows.forEach(row => {
        const cb = row.querySelector('.multi-roll-checkbox:not(:disabled)');
        if (cb) cb.checked = isChecked;
    });
    updateMultiSelectedCount();
}

function selectAllFilteredRolls() {
    const visibleRows = Array.from(document.querySelectorAll('.multi-roll-item-row')).filter(r => r.style.display !== 'none');
    visibleRows.forEach(row => {
        const cb = row.querySelector('.multi-roll-checkbox:not(:disabled)');
        if (cb) cb.checked = true;
    });
    updateMultiSelectedCount();
}

function deselectAllMultiRolls() {
    document.querySelectorAll('.multi-roll-checkbox:not(:disabled)').forEach(cb => cb.checked = false);
    updateMultiSelectedCount();
}

function confirmMultiRollSelection() {
    const selectedCheckboxes = document.querySelectorAll('.multi-roll-checkbox:checked:not(:disabled)');
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one fabric roll using the checkboxes.');
        return;
    }

    const selectedRollIds = Array.from(selectedCheckboxes).map(cb => cb.value);

    // Check for existing cards
    const existingCards = Array.from(document.querySelectorAll('.program-roll-card'));
    const existingAssignedIds = new Set();
    let emptyCard = null;

    existingCards.forEach(card => {
        const sel = card.querySelector('.roll-select');
        if (sel && sel.value) {
            existingAssignedIds.add(String(sel.value));
        } else if (sel && !sel.value && !emptyCard) {
            emptyCard = card;
        }
    });

    selectedRollIds.forEach(rollId => {
        if (existingAssignedIds.has(String(rollId))) {
            return; // already assigned
        }

        const rollObj = availableSellerRolls.find(r => String(r.id) === String(rollId));
        if (!rollObj) return;

        if (emptyCard) {
            // Populate the empty initial card
            const cardIdMatch = emptyCard.id.match(/\d+$/);
            const idx = cardIdMatch ? parseInt(cardIdMatch[0]) : null;
            const sel = emptyCard.querySelector('.roll-select');
            if (sel && idx) {
                sel.value = rollId;
                handleRollRowChange(sel, idx, true);
                emptyCard = null;
                existingAssignedIds.add(String(rollId));
                return;
            }
        }

        // Add a new roll row
        addProgramRollRow({
            seller_roll_id: rollId,
            roll_qty: 1,
            roll_weight: rollObj.weight,
            roll_price: rollObj.price
        });
        existingAssignedIds.add(String(rollId));
    });

    updateRollIndices();
    calculateTotalRollMetrics();
    syncRollStockAllocations();

    const modalEl = document.getElementById('multiRollSelectModal');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', function() {
    if (existingProgramRolls && existingProgramRolls.length > 0) {
        existingProgramRolls.forEach(r => addProgramRollRow(r));
    } else {
        addProgramRollRow();
    }

    // Setup Enter key on quick scan input
    const scanInp = document.getElementById('quickRollScanInput');
    if (scanInp) {
        scanInp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                triggerQuickRollScan();
            }
        });
    }

    // Form submission validation
    const form = document.getElementById('programEditForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const nameInput = document.getElementById('program_name');
            if (!nameInput || !nameInput.value.trim()) {
                e.preventDefault();
                alert('Program Name is required. Please enter a descriptive title.');
                nameInput?.focus();
                return false;
            }

            const personInput = document.getElementById('person_name');
            if (!personInput || !personInput.value.trim()) {
                e.preventDefault();
                alert('Person Name (Planner / Creator) is required.');
                personInput?.focus();
                return false;
            }

            const cards = document.querySelectorAll('.program-roll-card');
            let hasValidRoll = false;

            for (let i = 0; i < cards.length; i++) {
                const card = cards[i];
                const sel = card.querySelector('.roll-select');
                const qtyInp = card.querySelector('.roll-qty-input');

                if (sel && sel.value) {
                    hasValidRoll = true;
                    const opt = sel.options[sel.selectedIndex];
                    const availableQtyAttr = opt.getAttribute('data-avail-qty');
                    const availQty = availableQtyAttr !== null
                        ? (parseFloat(availableQtyAttr) || 0)
                        : (parseFloat(opt.getAttribute('data-qty')) || 0);
                    const enteredQty = parseFloat(qtyInp?.value) || 0;

                    if (enteredQty <= 0) {
                        e.preventDefault();
                        alert(`Roll #${i + 1}: Quantity must be at least 1 pcs.`);
                        qtyInp?.focus();
                        return false;
                    }

                    if (enteredQty > availQty) {
                        e.preventDefault();
                        alert(`Roll #${i + 1}: Quantity cannot exceed available roll stock (${availQty} pcs).`);
                        qtyInp?.focus();
                        return false;
                    }
                }
            }

            if (!hasValidRoll) {
                e.preventDefault();
                alert('Please select at least one Raw Material Roll for this program.');
                return false;
            }
        });
    }
});

// =========================================================================
// ROLL QR & BARCODE SCANNER LOGIC
// =========================================================================
let html5ProgramScanner = null;
let isProgramCameraActive = false;

function triggerQuickRollScan() {
    const input = document.getElementById('quickRollScanInput');
    if (!input) return;
    const val = input.value.trim();
    if (!val) {
        showScanFeedback('Please enter or scan a roll QR / piece barcode.', 'warning');
        input.focus();
        return;
    }
    const success = processScannedRollCode(val);
    if (success) {
        input.value = '';
    }
    input.focus();
}

function showScanFeedback(message, type = 'info') {
    const el = document.getElementById('quickScanFeedbackAlert');
    if (!el) return;
    el.className = `small mt-1.5 p-1.5 px-2.5 rounded-2 alert alert-${type} mb-0 d-block`;
    el.innerHTML = message;
    el.classList.remove('d-none');
    setTimeout(() => {
        el.classList.add('d-none');
    }, 4500);
}

function processScannedRollCode(rawCode) {
    if (!rawCode) return false;
    const code = rawCode.trim().toUpperCase();

    if (!availableSellerRolls || !Array.isArray(availableSellerRolls) || availableSellerRolls.length === 0) {
        showScanFeedback('No raw material fabric rolls available in inventory stock.', 'danger');
        return false;
    }

    let matchedPiece = null;
    let matchedRoll = null;
    for (const roll of availableSellerRolls) {
        const piece = (roll.pieces || []).find(item => String(item.code || '').toUpperCase() === code);
        if (piece) {
            matchedRoll = roll;
            matchedPiece = piece;
            break;
        }
    }
    if (!matchedRoll) {
        matchedRoll = availableSellerRolls.find(roll => [
            roll.qr_code,
            roll.roll_number,
            roll.start_code,
            roll.end_code,
            roll.id
        ].some(value => String(value || '').trim().toUpperCase() === code));
    }

    if (!matchedRoll) {
        showScanFeedback(`No active raw material roll found matching "<strong>${escapeHtml(rawCode)}</strong>".`, 'danger');
        return false;
    }

    if (matchedPiece && matchedPiece.status !== 'AVAILABLE') {
        showScanFeedback(`QR label <strong>${escapeHtml(code)}</strong> is unavailable (${escapeHtml(matchedPiece.meta || matchedPiece.status)}).`, 'warning');
        return false;
    }

    const availStock = (matchedRoll.available_quantity !== undefined && matchedRoll.available_quantity !== null)
        ? (parseInt(matchedRoll.available_quantity, 10) || 0)
        : (parseInt(matchedRoll.quantity || 0, 10) || 0);

    if (availStock <= 0) {
        showScanFeedback(`Roll #${matchedRoll.roll_number} is OUT OF STOCK (0 pcs available).`, 'warning');
        return false;
    }

    // Check if this specific piece code is ALREADY scanned in any roll card
    const targetPieceCode = matchedPiece ? String(matchedPiece.code || '').toUpperCase() : null;
    if (targetPieceCode) {
        let isAlreadyScanned = false;
        let alreadyScannedCardNum = '';
        document.querySelectorAll('.program-roll-card').forEach(c => {
            const cId = c.id.match(/\d+$/)?.[0];
            const allocInp = document.getElementById('roll_allocated_qrs_' + cId);
            if (allocInp && allocInp.value) {
                const codes = allocInp.value.split(',').map(s => s.trim().toUpperCase());
                if (codes.includes(targetPieceCode)) {
                    isAlreadyScanned = true;
                    alreadyScannedCardNum = cId;
                }
            }
        });
        if (isAlreadyScanned) {
            showScanFeedback(`Piece QR <strong>${escapeHtml(targetPieceCode)}</strong> is already allocated in Roll #${alreadyScannedCardNum}.`, 'warning');
            return false;
        }
    }

    // Check remaining total stock across all rows for this roll
    let totalUsedAcrossAllCards = 0;
    document.querySelectorAll('.program-roll-card').forEach(card => {
        const sel = card.querySelector('.roll-select');
        const quantity = card.querySelector('.roll-qty-input');
        if (sel && String(sel.value) === String(matchedRoll.id)) {
            totalUsedAcrossAllCards += parseInt(quantity?.value || '0', 10) || 0;
        }
    });
    if (availStock - totalUsedAcrossAllCards <= 0) {
        showScanFeedback(`Roll <strong>${escapeHtml(matchedRoll.roll_number)}</strong> has no remaining stock for another roll allocation.`, 'warning');
        return false;
    }

    // User requirement: Every roll scan creates a SEPARATE new roll row!
    // Check if there is an empty unconfigured card (e.g. initial empty card)
    let emptyCard = null;
    document.querySelectorAll('.program-roll-card').forEach(card => {
        const sel = card.querySelector('.roll-select');
        if (sel && !sel.value && !emptyCard) {
            emptyCard = card;
        }
    });

    let targetCard = null;
    if (emptyCard) {
        targetCard = emptyCard;
    } else {
        // ALWAYS create a separate new roll card for each scan
        addProgramRollRow({
            seller_roll_id: matchedRoll.id,
            roll_qty: 1,
            roll_weight: matchedRoll.weight,
            roll_price: matchedRoll.price,
            allocated_qr_codes: targetPieceCode || ''
        });
        targetCard = document.getElementById('program_roll_card_' + rollCounter);
    }

    const cardIdMatch = targetCard?.id.match(/\d+$/);
    const counterId = cardIdMatch ? parseInt(cardIdMatch[0], 10) : 0;
    const select = targetCard?.querySelector('.roll-select');
    if (!counterId || !select) return false;

    select.value = String(matchedRoll.id);
    handleRollRowChange(select, counterId, true);

    // Enforce Quantity = 1 for this scanned roll item
    const quantity = document.getElementById('roll_qty_' + counterId);
    if (quantity) quantity.value = '1';

    if (matchedPiece) {
        const allocInput = document.getElementById('roll_allocated_qrs_' + counterId);
        if (allocInput) allocInput.value = matchedPiece.code;
    }

    // Render piece list and sync stock
    renderRollPieces(counterId);
    syncRollStockAllocations();

    // Show scanned label badge & banner prominently on this roll card
    const labelCode = matchedPiece ? matchedPiece.code : (matchedRoll.roll_number || code);
    const scannedBadge = document.getElementById('roll_scanned_badge_' + counterId);
    const scannedLabel = document.getElementById('roll_scanned_code_label_' + counterId);
    const scannedBanner = document.getElementById('roll_scanned_banner_' + counterId);
    const scannedBannerCode = document.getElementById('roll_scanned_banner_code_' + counterId);

    if (scannedBadge && scannedLabel) {
        scannedLabel.textContent = labelCode;
        scannedBadge.classList.remove('d-none');
    }
    if (scannedBanner && scannedBannerCode) {
        scannedBannerCode.textContent = labelCode;
        scannedBanner.classList.remove('d-none');
    }

    showScanFeedback(
        `✓ Created separate Roll #${counterId} for QR <strong>${escapeHtml(labelCode)}</strong> (Qty: 1 pcs).`,
        'success'
    );
    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    targetCard.classList.add('border-success', 'bg-success-subtle');
    setTimeout(() => targetCard.classList.remove('border-success', 'bg-success-subtle'), 1800);
    return true;
}

function openScanRollModal() {
    const modalEl = document.getElementById('scanRollCameraModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    setTimeout(startCameraScanner, 350);
}

function startCameraScanner() {
    if (typeof Html5Qrcode === 'undefined') {
        const fb = document.getElementById('cameraScanFeedback');
        if (fb) {
            fb.className = 'small p-2 rounded alert alert-warning d-block';
            fb.textContent = 'Camera scanner library not loaded. Please use the Quick Scan input bar.';
        }
        return;
    }

    if (isProgramCameraActive) return;

    try {
        html5ProgramScanner = new Html5Qrcode('cameraScannerPreview');
        const config = { fps: 12, qrbox: { width: 220, height: 220 } };

        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length > 0) {
                const backCam = devices.find(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('rear')) || devices[0];
                html5ProgramScanner.start(
                    backCam.id,
                    config,
                    (decodedText) => {
                        const fb = document.getElementById('cameraScanFeedback');
                        if (fb) {
                            fb.className = 'small p-2 rounded alert alert-success d-block';
                            fb.innerHTML = `Scanned: <strong>${escapeHtml(decodedText)}</strong>`;
                        }
                        const ok = processScannedRollCode(decodedText);
                        if (ok) {
                            setTimeout(() => {
                                stopCameraScanner();
                                const modal = bootstrap.Modal.getInstance(document.getElementById('scanRollCameraModal'));
                                if (modal) modal.hide();
                            }, 800);
                        }
                    },
                    () => {}
                ).then(() => {
                    isProgramCameraActive = true;
                }).catch(err => {
                    console.warn('Camera start error:', err);
                    const fb = document.getElementById('cameraScanFeedback');
                    if (fb) {
                        fb.className = 'small p-2 rounded alert alert-danger d-block';
                        fb.textContent = 'Camera permission denied or camera not found.';
                    }
                });
            } else {
                const fb = document.getElementById('cameraScanFeedback');
                if (fb) {
                    fb.className = 'small p-2 rounded alert alert-warning d-block';
                    fb.textContent = 'No camera device detected.';
                }
            }
        }).catch(err => {
            console.error('getCameras error:', err);
        });
    } catch (e) {
        console.error('Camera init error:', e);
    }
}

function stopCameraScanner() {
    if (html5ProgramScanner && isProgramCameraActive) {
        html5ProgramScanner.stop().then(() => {
            html5ProgramScanner.clear();
            isProgramCameraActive = false;
        }).catch(err => {
            console.warn('Camera stop error:', err);
            isProgramCameraActive = false;
        });
    }
}

// =========================================================================
// DYNAMIC CATEGORY ATTRIBUTES & SPECIFICATIONS LOGIC
// =========================================================================
function loadRollCategoryAttributes(idx, catId, subcatId = '', subSubcatId = '', savedValues = null) {
    const container = document.getElementById('roll_attrs_container_' + idx);
    if (!container) return;

    if (!catId) {
        container.innerHTML = `<div class="text-muted small py-2.5 px-3 bg-white rounded-3 border border-dashed fst-italic" style="font-size: 11px;">
            <i class="bi bi-info-circle me-1 text-secondary"></i>Select a Main Category above to view and assign category attributes.
        </div>`;
        return;
    }

    container.innerHTML = `<div class="py-3 text-center text-muted small" style="font-size: 11px;">
        <span class="spinner-border spinner-border-sm text-primary me-1" role="status"></span> Loading category attributes &amp; sizes...
    </div>`;

    let url = `${baseUrl}/api/categories/attributes/${encodeURIComponent(catId)}`;
    const qParams = [];
    if (subcatId) qParams.push('subcategory_id=' + encodeURIComponent(subcatId));
    if (subSubcatId) qParams.push('sub_subcategory_id=' + encodeURIComponent(subSubcatId));
    if (qParams.length > 0) url += '?' + qParams.join('&');

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.attributes || data.attributes.length === 0) {
                container.innerHTML = `
                    <div class="p-3 bg-white rounded-3 text-center small text-muted border border-dashed" style="font-size: 11px;">
                        <i class="bi bi-sliders me-1 text-secondary"></i>No pre-set attributes for this category level yet. 
                        <a href="javascript:void(0)" onclick="openAddAttributeModal(${idx})" class="fw-semibold text-primary text-decoration-none ms-1">
                            <i class="bi bi-plus-circle"></i> + Add Attribute
                        </a>
                    </div>
                `;
                return;
            }

            let parsedSaved = savedValues;
            if (typeof parsedSaved === 'string') {
                try { parsedSaved = JSON.parse(parsedSaved); } catch(e) { parsedSaved = {}; }
            }

            let html = '';
            data.attributes.forEach(attr => {
                const isReq = attr.is_required == 1;
                const isSize = /^(SIZE|SIZES)$/i.test(attr.attr_name.trim());
                const isColor = /^(COLOR|COLORS)$/i.test(attr.attr_name.trim());
                // Fabric roll colors are tracked per-roll on the roll card, skip rendering COLOR attribute in roll cutting attributes
                if (isColor) return;
                const cleanAttrName = isSize ? 'SIZE' : attr.attr_name.replace(/[^a-zA-Z0-9_]/g, '_');
                const opts = attr.attr_options ? attr.attr_options.split(',').map(s => s.trim()).filter(Boolean) : [];

                // Skip empty select/multi_select attributes with no options
                if (opts.length === 0 && (attr.attr_type === 'MULTI_SELECT' || attr.attr_type === 'SELECT')) {
                    return;
                }

                // Retrieve saved value/values for this attribute
                let curVal = '';
                if (parsedSaved && typeof parsedSaved === 'object') {
                    curVal = parsedSaved[attr.attr_name] || parsedSaved[attr.id] || (isSize ? (parsedSaved['SIZE'] || parsedSaved['SIZES']) : '') || (isColor ? (parsedSaved['COLOR'] || parsedSaved['COLORS']) : '') || '';
                }
                let curArr = [];
                if (Array.isArray(curVal)) {
                    curArr = curVal.map(v => String(v).toUpperCase().trim());
                } else if (typeof curVal === 'string' && curVal !== '') {
                    curArr = curVal.split(',').map(v => v.toUpperCase().trim());
                }

                // If attribute has options, or is SIZE/COLOR, or is MULTI_SELECT:
                // Render with multi-checkbox pills/chips UI like Catalog Edit!
                if (isSize || isColor || attr.attr_type === 'MULTI_SELECT' || (opts.length > 0 && attr.attr_type !== 'TEXT' && attr.attr_type !== 'NUMBER')) {
                    const attrLabelTitle = isSize ? 'Size Attributes:' : (isColor ? 'Color Attributes:' : `${escapeHtml(attr.attr_name)} Attributes:`);
                    const inputAttrName = isSize ? 'SIZE' : (isColor ? 'COLOR' : attr.attr_name);

                    html += `
                    <div class="p-3 bg-white rounded-3 border shadow-xs roll-attr-block mb-3" id="roll_attr_block_${idx}_${cleanAttrName}">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <label class="form-label fw-bold mb-0 text-dark small d-flex align-items-center gap-1.5" style="font-size: 12px;">
                                <i class="bi bi-${isSize ? 'rulers' : (isColor ? 'palette' : 'tags')} text-primary"></i>
                                <span>${attrLabelTitle}</span>
                                ${isReq ? '<span class="text-danger">*</span>' : ''}
                            </label>
                        </div>
                        <div class="p-3 px-4 border rounded-3 bg-light d-flex flex-wrap gap-2 align-items-center" id="attr_chips_container_${idx}_${cleanAttrName}" style="min-height: 50px;">`;

                    if (opts.length === 0) {
                        html += `
                            <div class="w-100 text-center py-2 text-muted small empty-attr-prompt" style="font-size: 11px;">
                                No ${escapeHtml(attr.attr_name)} options configured for this category level.
                            </div>
                        `;
                    } else {
                        opts.forEach(opt => {
                            const cleanOpt = opt.replace(/[^a-zA-Z0-9_]/g, '_');
                            const isChecked = curArr.includes(opt.toUpperCase().trim());
                            html += `
                            <label class="btn btn-outline-secondary size-check-btn d-inline-flex align-items-center gap-1.5 py-1.5 px-3 rounded-2 bg-white text-dark border-secondary-subtle fw-semibold shadow-2xs" style="cursor: pointer; font-size: 12px; transition: all 0.15s ease-in-out;" id="attr_chip_${idx}_${cleanAttrName}_${cleanOpt}">
                                <input type="checkbox" name="rolls[${idx}][attributes][${escapeHtml(inputAttrName)}][]" class="form-check-input m-0 size-checkbox attr-cb-${idx}-${cleanAttrName}" value="${escapeHtml(opt)}" ${isChecked ? 'checked' : ''}>
                                <span class="fw-bold ms-1">${escapeHtml(opt)}</span>
                            </label>`;
                        });
                    }

                    html += `
                        </div>
                    </div>`;
                } else if (attr.attr_type === 'NUMBER') {
                    html += `
                    <div class="p-3 bg-white rounded-3 border shadow-xs roll-attr-block mb-3">
                        <label class="form-label small text-muted mb-2 fw-semibold" style="font-size: 11px;">
                            ${escapeHtml(attr.attr_name)} ${isReq ? '<span class="text-danger">*</span>' : ''}
                        </label>
                        <input type="number" step="any" name="rolls[${idx}][attributes][${escapeHtml(attr.attr_name)}]" class="form-control form-control-sm" style="font-size: 12px; height: 36px;" value="${escapeHtml(curVal)}" placeholder="Enter ${escapeHtml(attr.attr_name)}" ${isReq ? 'required' : ''}>
                    </div>`;
                } else {
                    html += `
                    <div class="p-3 bg-white rounded-3 border shadow-xs roll-attr-block mb-3">
                        <label class="form-label small text-muted mb-2 fw-semibold" style="font-size: 11px;">
                            ${escapeHtml(attr.attr_name)} ${isReq ? '<span class="text-danger">*</span>' : ''}
                        </label>
                        <input type="text" name="rolls[${idx}][attributes][${escapeHtml(attr.attr_name)}]" class="form-control form-control-sm" style="font-size: 12px; height: 36px;" value="${escapeHtml(curVal)}" placeholder="Enter ${escapeHtml(attr.attr_name)}" ${isReq ? 'required' : ''}>
                    </div>`;
                }
            });

            container.innerHTML = html;
        })
        .catch(err => {
            console.error('Error loading attributes:', err);
            container.innerHTML = `<div class="text-danger small py-2" style="font-size: 11px;">Failed to load attributes.</div>`;
        });
}

function selectAllAttrOptions(idx, cleanAttrName) {
    document.querySelectorAll(`.attr-cb-${idx}-${cleanAttrName}`).forEach(cb => cb.checked = true);
}

function clearAllAttrOptions(idx, cleanAttrName) {
    document.querySelectorAll(`.attr-cb-${idx}-${cleanAttrName}`).forEach(cb => cb.checked = false);
}

function openQuickAddOptionModal(idx, attrName, attrId) {
    const isSize = (attrName.toUpperCase() === 'SIZE');
    const title = isSize ? 'Add Size(s)' : `Add ${attrName} Option(s)`;
    const promptText = `Enter one or more ${title} separated by commas (e.g. ${isSize ? 'S, M, L, XL, XXL' : 'Cotton, Linen, Rayon'}):`;
    const inputVal = prompt(promptText);
    if (!inputVal || !inputVal.trim()) return;

    const card = document.getElementById('program_roll_card_' + idx);
    const categoryId = card?.querySelector('.roll-cat-select')?.value || '';
    const subcategoryId = card?.querySelector('.roll-subcat-select')?.value || '';
    const subSubcategoryId = card?.querySelector('.roll-subsubcat-select')?.value || '';
    const cleanAttrName = attrName.replace(/[^a-zA-Z0-9_]/g, '_');
    const chipsBox = document.getElementById(`attr_chips_container_${idx}_${cleanAttrName}`);
    const currentOptions = chipsBox
        ? Array.from(chipsBox.querySelectorAll('input[type="checkbox"]')).map(input => input.value)
        : [];
    const formData = new FormData();
    formData.append('attribute_id', attrId);
    formData.append('attr_name', attrName);
    formData.append('options', inputVal.trim());
    formData.append('category_id', categoryId);
    formData.append('subcategory_id', subcategoryId);
    formData.append('sub_subcategory_id', subSubcategoryId);
    formData.append('current_options', currentOptions.join(','));

    fetch(`${baseUrl}/api/categories/attributes/add-option`, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.new_options && data.new_options.length > 0) {
            const cleanAttrName = attrName.replace(/[^a-zA-Z0-9_]/g, '_');
            const chipsBox = document.getElementById(`attr_chips_container_${idx}_${cleanAttrName}`);
            if (chipsBox) {
                const emptyPrompt = chipsBox.querySelector('.empty-attr-prompt');
                if (emptyPrompt) emptyPrompt.remove();

                data.new_options.forEach(opt => {
                    const cleanOpt = opt.replace(/[^a-zA-Z0-9_]/g, '_');
                    if (!document.getElementById(`attr_chip_${idx}_${cleanAttrName}_${cleanOpt}`)) {
                        const chipDiv = document.createElement('div');
                        chipDiv.className = 'btn-group btn-group-sm size-chip-group';
                        chipDiv.id = `attr_chip_${idx}_${cleanAttrName}_${cleanOpt}`;
                        chipDiv.innerHTML = `
                            <label class="btn btn-outline-dark size-check-btn d-flex align-items-center gap-1.5 py-1 px-2.5 bg-white text-dark border-secondary-subtle" style="cursor: pointer; font-size: 11px;">
                                <input type="checkbox" name="rolls[${idx}][attributes][${escapeHtml(attrName)}][]" class="form-check-input me-1 size-checkbox attr-cb-${idx}-${cleanAttrName}" value="${escapeHtml(opt)}" checked>
                                <span class="fw-bold">${escapeHtml(opt)}</span>
                            </label>
                            <button type="button" class="btn btn-outline-danger btn-delete-size p-1 px-2" onclick="deleteRollAttrOption(${idx}, '${escapeHtml(attrName)}', '${escapeHtml(opt)}', ${attrId})" title="Delete option">
                                <i class="bi bi-trash"></i>
                            </button>
                        `;
                        chipsBox.appendChild(chipDiv);
                    }
                });
            }
        } else if (data.success) {
            alert('Option(s) already exist.');
        } else {
            alert(data.message || 'Error adding option');
        }
    })
    .catch(err => {
        console.error('Error adding option:', err);
        alert('Server error while adding option.');
    });
}

function deleteRollAttrOption(idx, attrName, optionVal, attrId) {
    if (!confirm(`Are you sure you want to remove "${optionVal}" from ${attrName}?`)) return;

    const card = document.getElementById('program_roll_card_' + idx);
    const formData = new FormData();
    formData.append('attribute_id', attrId);
    formData.append('attr_name', attrName);
    formData.append('option', optionVal);
    formData.append('category_id', card?.querySelector('.roll-cat-select')?.value || '');
    formData.append('subcategory_id', card?.querySelector('.roll-subcat-select')?.value || '');
    formData.append('sub_subcategory_id', card?.querySelector('.roll-subsubcat-select')?.value || '');

    fetch(`${baseUrl}/api/categories/attributes/delete-option`, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const cleanAttrName = attrName.replace(/[^a-zA-Z0-9_]/g, '_');
            const cleanOpt = optionVal.replace(/[^a-zA-Z0-9_]/g, '_');
            const chip = document.getElementById(`attr_chip_${idx}_${cleanAttrName}_${cleanOpt}`);
            if (chip) chip.remove();
        } else {
            alert(data.message || 'Error deleting option');
        }
    })
    .catch(err => {
        console.error('Error deleting option:', err);
        alert('Server error while deleting option.');
    });
}

function openAddAttributeModal(idx) {
    const card = document.getElementById('program_roll_card_' + idx);
    if (!card) return;

    const catSelect = card.querySelector('.roll-cat-select');
    const subcatSelect = card.querySelector('.roll-subcat-select');
    const subsubcatSelect = card.querySelector('.roll-subsubcat-select');

    const catId = catSelect ? catSelect.value : '';
    const subcatId = subcatSelect ? subcatSelect.value : '';
    const subsubcatId = subsubcatSelect ? subsubcatSelect.value : '';

    if (!catId) {
        alert('Please select a Main Category for this Roll first before adding attributes.');
        if (catSelect) catSelect.focus();
        return;
    }

    const catName = catSelect.options[catSelect.selectedIndex]?.text || 'Main Category';
    let breadcrumb = `<strong>${escapeHtml(catName)}</strong>`;
    if (subcatId && subcatSelect.options[subcatSelect.selectedIndex]) {
        breadcrumb += ' &rsaquo; ' + escapeHtml(subcatSelect.options[subcatSelect.selectedIndex].text);
    }
    if (subsubcatId && subsubcatSelect.options[subsubcatSelect.selectedIndex]) {
        breadcrumb += ' &rsaquo; ' + escapeHtml(subsubcatSelect.options[subsubcatSelect.selectedIndex].text);
    }

    document.getElementById('attrModalTargetRowIdx').value = idx;
    document.getElementById('attrModalCategoryId').value = catId;
    document.getElementById('attrModalSubcategoryId').value = subcatId;
    document.getElementById('attrModalSubSubcategoryId').value = subsubcatId;
    document.getElementById('attrModalTargetBreadcrumb').innerHTML = breadcrumb;

    document.getElementById('attrModalName').value = '';
    document.getElementById('attrModalType').value = 'MULTI_SELECT';
    document.getElementById('attrModalOptions').value = '';
    document.getElementById('attrModalOptionsGroup').style.display = '';
    document.getElementById('attrModalRequired').checked = false;

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQuickAddCategoryAttribute'));
    modal.show();
}

function handleAttrTypeChange(type) {
    const group = document.getElementById('attrModalOptionsGroup');
    const optInput = document.getElementById('attrModalOptions');
    if (type === 'SELECT' || type === 'MULTI_SELECT') {
        group.style.display = '';
        if (optInput) optInput.setAttribute('required', 'required');
    } else {
        group.style.display = 'none';
        if (optInput) optInput.removeAttribute('required');
    }
}

function refreshProgramRollAttributePanels(catId, subcatId = '', subsubcatId = '') {
    const cards = document.querySelectorAll('.program-roll-card');
    let matched = false;

    cards.forEach(card => {
        const cMatch = card.id.match(/\d+$/);
        if (!cMatch) return;

        const cIdx = parseInt(cMatch[0], 10);
        const cCat = card.querySelector('.roll-cat-select')?.value || '';
        const cSub = card.querySelector('.roll-subcat-select')?.value || '';
        const cSubSub = card.querySelector('.roll-subsubcat-select')?.value || '';

        const sameCategory = String(cCat) === String(catId);
        const exactHierarchyMatch = sameCategory && (!subcatId || String(cSub) === String(subcatId)) && (!subsubcatId || String(cSubSub) === String(subsubcatId));
        const categoryLevelMatch = sameCategory && (!subcatId || String(cSub) === String(subcatId) || !cSub || String(cSub) === '');

        if (exactHierarchyMatch || (sameCategory && categoryLevelMatch)) {
            matched = true;
            loadRollCategoryAttributes(cIdx, cCat, cSub, cSubSub);
        }
    });

    if (!matched && catId) {
        cards.forEach(card => {
            const cMatch = card.id.match(/\d+$/);
            if (!cMatch) return;
            const cIdx = parseInt(cMatch[0], 10);
            const cCat = card.querySelector('.roll-cat-select')?.value || '';
            if (String(cCat) === String(catId)) {
                loadRollCategoryAttributes(cIdx, cCat, card.querySelector('.roll-subcat-select')?.value || '', card.querySelector('.roll-subsubcat-select')?.value || '');
            }
        });
    }
}

function handleSaveCategoryAttribute(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitAttrModal');
    const catId = document.getElementById('attrModalCategoryId').value;
    const subcatId = document.getElementById('attrModalSubcategoryId').value;
    const subsubcatId = document.getElementById('attrModalSubSubcategoryId').value;
    const name = document.getElementById('attrModalName').value.trim();
    const type = document.getElementById('attrModalType').value;
    const options = document.getElementById('attrModalOptions').value.trim();
    const isReq = document.getElementById('attrModalRequired').checked ? 1 : 0;

    if (!name) {
        alert('Attribute name is required.');
        return;
    }
    if ((type === 'SELECT' || type === 'MULTI_SELECT') && !options) {
        alert('Please enter at least one attribute option.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    const formData = new FormData();
    formData.append('category_id', catId);
    if (subcatId) formData.append('subcategory_id', subcatId);
    if (subsubcatId) formData.append('sub_subcategory_id', subsubcatId);
    formData.append('attr_name', name);
    formData.append('attr_type', type);
    formData.append('attr_options', options);
    formData.append('is_required', isReq);

    fetch(`${baseUrl}/api/categories/attributes/quick-add`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save Attribute';

        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalQuickAddCategoryAttribute'));
            if (modal) modal.hide();
            refreshProgramRollAttributePanels(catId, subcatId, subsubcatId);
        } else {
            alert(data.message || 'Error saving category attribute.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save Attribute';
        console.error('Error saving attribute:', err);
        alert('Network error while saving attribute.');
    });
}
</script>
<style>
.roll-field-label {
    display: block;
    min-height: 2.45rem;
}

.roll-field-label-text,
.roll-field-meta {
    display: block;
    line-height: 1.15;
}

.roll-field-meta {
    min-height: 14px;
    margin-top: 2px;
}

.size-check-btn {
    transition: all 0.15s ease-in-out;
}
.size-check-btn:hover {
    border-color: #0d6efd !important;
    background-color: #f8faff !important;
}
.size-check-btn:has(input:checked) {
    background-color: #e8f0fe !important;
    border-color: #0d6efd !important;
    color: #0d6efd !important;
}
</style>
