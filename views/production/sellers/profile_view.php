<div class="page-header mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h2 class="mb-0 fw-bold"><i class="bi bi-person-vcard me-2 text-primary"></i><?= e($seller['name']) ?></h2>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Raw Material Supplier</span>
        </div>
        <div class="text-muted small mt-1">
            <span class="fw-semibold text-dark"><?= e($seller['company'] ?: 'Independent Supplier') ?></span>
            <?php if (!empty($seller['city'])): ?>
                • <i class="bi bi-geo-alt me-0.5"></i><?= e(trim(($seller['city'] ?? '') . ', ' . ($seller['state'] ?? ''), ', ')) ?>
            <?php endif; ?>
            <?php if (!empty($seller['phone'])): ?>
                • <i class="bi bi-telephone me-0.5"></i><?= e($seller['phone']) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('production/sellers') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Suppliers
        </a>
        <button type="button" class="btn btn-outline-success" onclick="exportReplacedRollsCSV()" title="Export Rolls Replaced Tab Data">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Replaced Rolls
        </button>
        <a href="<?= url('production/purchases/create?seller_id=' . (int)$seller['id']) ?>" class="btn btn-outline-primary">
            <i class="bi bi-cart-plus me-1"></i>New Purchase
        </a>
        <a href="<?= url('production/sellers/edit/' . (int)$seller['id']) ?>" class="btn btn-primary">
            <i class="bi bi-pencil-square me-1"></i>Edit Supplier
        </a>
    </div>
</div>

<!-- Key Performance Indicators / Stat Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Rolls Bought</div>
                        <h3 class="mb-1 fw-bold text-dark mt-1"><?= number_format($totalPurchasedRollsCount) ?> <span class="fs-6 fw-normal text-muted">Rolls</span></h3>
                        <div class="small text-muted">
                            <span class="fw-semibold text-primary"><?= number_format($totalPurchasedQty) ?> pcs</span> • ₹<?= number_format($totalPurchasedValuation, 2) ?>
                        </div>
                    </div>
                    <div class="p-2.5 rounded-3 bg-primary-subtle text-primary">
                        <i class="bi bi-bag-check-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Replaced Rolls Count</div>
                        <h3 class="mb-1 fw-bold text-info mt-1"><?= number_format($totalReplacedRollsCount) ?> <span class="fs-6 fw-normal text-muted">Rolls</span></h3>
                        <div class="small text-muted">
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-1.5 py-0.5" style="font-size: 11px;">
                                <i class="bi bi-arrow-repeat me-1"></i>Damage Replacements
                            </span>
                            <?php if ($totalReplacedQty > 0): ?>
                                <span class="ms-1 fw-semibold text-dark"><?= number_format($totalReplacedQty) ?> pcs</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-2.5 rounded-3 bg-info-subtle text-info">
                        <i class="bi bi-arrow-repeat fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Active Inventory Rolls</div>
                        <h3 class="mb-1 fw-bold text-success mt-1"><?= number_format($totalActiveRollsCount) ?> <span class="fs-6 fw-normal text-muted">Rolls</span></h3>
                        <div class="small text-muted">Available in Production Inventory</div>
                    </div>
                    <div class="p-2.5 rounded-3 bg-success-subtle text-success">
                        <i class="bi bi-layers-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Reported Defects</div>
                        <h3 class="mb-1 fw-bold text-warning mt-1"><?= number_format($totalDamagesCount) ?> <span class="fs-6 fw-normal text-muted">Cases</span></h3>
                        <div class="small text-muted">
                            <?php if ($totalReplacedRollsCount > 0): ?>
                                <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i><?= $totalReplacedRollsCount ?> Replaced</span>
                            <?php else: ?>
                                <span>Fabric damage logs</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-2.5 rounded-3 bg-warning-subtle text-warning">
                        <i class="bi bi-shield-exclamation fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Supplier Business Details Card -->
<div class="card shadow-sm border mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i>Supplier Business Details</h5>
        <span class="small text-muted">Supplier ID: #<?= (int)$seller['id'] ?></span>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 11px;">Contact Person</small>
                <div class="fw-bold text-dark fs-6 mt-1"><?= e($seller['name']) ?></div>
            </div>
            <div class="col-md-6 col-lg-4">
                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 11px;">Company</small>
                <div class="fw-bold text-dark fs-6 mt-1"><?= e($seller['company'] ?: '—') ?></div>
            </div>
            <div class="col-md-6 col-lg-4">
                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 11px;">Phone</small>
                <div class="fw-bold text-dark fs-6 mt-1"><?= e($seller['phone'] ?: '—') ?></div>
            </div>
            <div class="col-md-6 col-lg-4">
                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 11px;">Email</small>
                <div class="fw-bold text-dark fs-6 mt-1"><?= e($seller['email'] ?: '—') ?></div>
            </div>
            <div class="col-md-6 col-lg-4">
                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 11px;">GSTIN / Tax ID</small>
                <div class="fw-bold text-dark fs-6 mt-1 font-monospace"><?= e($seller['gst_number'] ?: '—') ?></div>
            </div>
            <div class="col-md-6 col-lg-4">
                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 11px;">Location</small>
                <div class="fw-bold text-dark fs-6 mt-1"><?= e(trim(($seller['city'] ?? '') . ', ' . ($seller['state'] ?? ''), ', ') ?: '—') ?></div>
            </div>
            <div class="col-12">
                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 11px;">Business Address</small>
                <div class="fw-bold text-dark mt-1"><?= e($seller['address'] ?: '—') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs for Roll Audit Log, Purchases, Replaced Rolls & Active Inventory -->
<div class="card shadow-sm border mb-4">
    <div class="card-header bg-white p-0 border-bottom">
        <ul class="nav nav-tabs card-header-tabs m-0 px-3 pt-2" id="supplierTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold text-dark d-flex align-items-center gap-2 py-3 px-3" id="tab-audit-log" data-bs-toggle="tab" data-bs-target="#pane-audit-log" type="button" role="tab" aria-selected="true">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span>Rolls Audit Log</span>
                    <span class="badge bg-primary text-white rounded-pill ms-1" style="font-size: 11px;"><?= count($auditLog) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold text-dark d-flex align-items-center gap-2 py-3 px-3" id="tab-purchased-rolls" data-bs-toggle="tab" data-bs-target="#pane-purchased-rolls" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-cart-check text-success"></i>
                    <span>Rolls Bought</span>
                    <span class="badge bg-success-subtle text-success rounded-pill ms-1" style="font-size: 11px;"><?= count($purchasedRolls) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold text-dark d-flex align-items-center gap-2 py-3 px-3" id="tab-replaced-rolls" data-bs-toggle="tab" data-bs-target="#pane-replaced-rolls" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-arrow-repeat text-info"></i>
                    <span>Rolls Replaced</span>
                    <span class="badge bg-info text-dark rounded-pill ms-1" style="font-size: 11px;"><?= count($replacedRolls) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold text-dark d-flex align-items-center gap-2 py-3 px-3" id="tab-active-rolls" data-bs-toggle="tab" data-bs-target="#pane-active-rolls" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-layers text-secondary"></i>
                    <span>Active Inventory</span>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1" style="font-size: 11px;"><?= count($activeRolls) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold text-dark d-flex align-items-center gap-2 py-3 px-3" id="tab-purchases" data-bs-toggle="tab" data-bs-target="#pane-purchases" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-receipt text-dark"></i>
                    <span>Purchase Invoices</span>
                    <span class="badge bg-dark-subtle text-dark rounded-pill ms-1" style="font-size: 11px;"><?= count($purchases) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content" id="supplierTabsContent">

            <!-- TAB 1: COMPLETE TRANSACTION AUDIT LOG (WITH DATE & TIME) -->
            <div class="tab-pane fade show active p-3 p-md-4" id="pane-audit-log" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text me-1.5 text-primary"></i>Roll Buying &amp; Replacement Audit Log</h6>
                        <small class="text-muted">Chronological audit trail of all rolls bought and replacement rolls received with exact date and time.</small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="input-group input-group-sm" style="max-width: 280px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" id="auditLogSearch" placeholder="Filter audit log..." oninput="filterAuditLogTable(this.value)">
                        </div>
                    </div>
                </div>

                <?php if (empty($auditLog)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        <h6 class="fw-bold text-dark">No Roll Transactions Logged</h6>
                        <p class="small mb-0">No roll purchases or damage replacements have been recorded for this supplier yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="auditLogTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 170px;">Date &amp; Time</th>
                                    <th style="width: 130px;">Action Type</th>
                                    <th>Roll No. / Code</th>
                                    <th>Fabric Details</th>
                                    <th class="text-center" style="width: 120px;">Qty &amp; Weight</th>
                                    <th class="text-end" style="width: 110px;">Valuation</th>
                                    <th>QR Sequence Range</th>
                                    <th>Reference Document</th>
                                    <th>Logged Details / Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLog as $entry): ?>
                                    <tr class="audit-row">
                                        <td>
                                            <div class="fw-semibold text-dark">
                                                <i class="bi bi-calendar-event me-1 text-primary"></i><?= date('d M Y', strtotime($entry['datetime'])) ?>
                                            </div>
                                            <div class="small text-muted font-monospace mt-0.5">
                                                <i class="bi bi-clock me-1 text-secondary"></i><?= date('h:i:s A', strtotime($entry['datetime'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($entry['type'] === 'REPLACED'): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                                    <i class="bi bi-arrow-repeat me-1"></i>REPLACED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                    <i class="bi bi-bag-check me-1"></i>BUYING
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold font-monospace text-dark fs-6"><?= e($entry['roll_number']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($entry['item_name']) ?></div>
                                            <div class="small text-muted">
                                                <span class="me-2">Color: <strong><?= e($entry['color']) ?></strong></span>
                                                <span>Design: <strong><?= e($entry['design']) ?></strong></span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="fw-bold text-dark"><?= number_format($entry['quantity']) ?> pcs</div>
                                            <?php if ($entry['weight'] > 0): ?>
                                                <small class="text-muted"><?= number_format($entry['weight'], 2) ?> kg</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end font-monospace">
                                            <div class="fw-bold text-dark">₹<?= number_format($entry['total_price'], 2) ?></div>
                                            <?php if ($entry['unit_price'] > 0): ?>
                                                <small class="text-muted">₹<?= number_format($entry['unit_price'], 2) ?>/pc</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($entry['start_code']) && !empty($entry['end_code'])): ?>
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                                                    <?= e($entry['start_code']) ?> → <?= e($entry['end_code']) ?>
                                                </span>
                                            <?php elseif (!empty($entry['qr_code']) && $entry['qr_code'] !== '—'): ?>
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                                                    <?= e($entry['qr_code']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= $entry['reference_url'] ?>" class="fw-bold text-decoration-none font-monospace">
                                                <i class="bi bi-box-arrow-up-right me-1" style="font-size: 10px;"></i><?= e($entry['reference_code']) ?>
                                            </a>
                                            <div class="small text-muted mt-0.5" style="font-size: 11px;">
                                                By: <?= e($entry['user_name']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small text-dark text-break" style="max-width: 250px;">
                                                <?= e($entry['notes'] ?: 'Standard roll inward record.') ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: ROLLS PURCHASED ("WHICH ROLL BUYING") -->
            <div class="tab-pane fade p-3 p-md-4" id="pane-purchased-rolls" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-cart-check me-1.5 text-success"></i>Purchased / Bought Rolls History</h6>
                        <small class="text-muted">Detailed register of every roll bought from this supplier with QR code sequences and purchase references.</small>
                    </div>
                    <span class="badge bg-success-subtle text-success px-2 py-1">
                        Total: <?= number_format($totalPurchasedRollsCount) ?> Rolls
                    </span>
                </div>

                <?php if (empty($purchasedRolls)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary"></i>
                        <h6 class="fw-bold text-dark">No Purchased Rolls Found</h6>
                        <p class="small mb-0">No purchase orders have been logged for this supplier yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th>Roll No. / Name</th>
                                    <th>Purchase Date &amp; Time</th>
                                    <th>Fabric Specifications</th>
                                    <th class="text-center">Quantity (RQ)</th>
                                    <th class="text-center">Weight (kg)</th>
                                    <th class="text-end">Price / Rate</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>QR Barcode Sequence</th>
                                    <th>Purchase #</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchasedRolls as $idx => $pr): ?>
                                    <tr>
                                        <td class="text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-bold text-dark font-monospace fs-6">
                                                <?= e($pr['roll_number'] ?: ($pr['sr_roll_number'] ?: '—')) ?>
                                            </div>
                                            <div class="small text-muted"><?= e($pr['item_name']) ?></div>
                                            <?php if (!empty($pr['is_replacement_inward'])): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle mt-1" style="font-size: 10px;">
                                                    <i class="bi bi-arrow-repeat me-1"></i>Damage Replacement
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">
                                                <i class="bi bi-calendar-event me-1 text-primary"></i><?= date('d M Y', strtotime($pr['purchase_created_at'] ?: $pr['purchase_date'])) ?>
                                            </div>
                                            <div class="small text-muted font-monospace">
                                                <i class="bi bi-clock me-1 text-secondary"></i><?= date('h:i:s A', strtotime($pr['purchase_created_at'] ?: $pr['purchase_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>Color: <strong class="text-dark"><?= e($pr['color'] ?: '—') ?></strong></div>
                                            <div class="small text-muted">Design: <strong class="text-dark"><?= e($pr['design_name'] ?: '—') ?></strong></div>
                                            <?php if (!empty($pr['hsn_code'])): ?>
                                                <div class="small text-muted font-monospace">HSN: <?= e($pr['hsn_code']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold text-dark"><?= number_format((float)$pr['quantity']) ?> pcs</td>
                                        <td class="text-center font-monospace"><?= number_format((float)($pr['weight_kg'] ?? 0), 2) ?> kg</td>
                                        <td class="text-end font-monospace">₹<?= number_format((float)($pr['unit_price'] ?? 0), 2) ?></td>
                                        <td class="text-end fw-bold font-monospace text-dark">₹<?= number_format((float)($pr['total_price'] ?? 0), 2) ?></td>
                                        <td>
                                            <?php if (!empty($pr['start_code']) && !empty($pr['end_code'])): ?>
                                                <div class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                                                    <?= e($pr['start_code']) ?> → <?= e($pr['end_code']) ?>
                                                </div>
                                            <?php elseif (!empty($pr['sr_qr_code'])): ?>
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;"><?= e($pr['sr_qr_code']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= url('production/purchases/view/' . (int)$pr['purchase_id']) ?>" class="fw-bold font-monospace text-decoration-none">
                                                <?= e($pr['purchase_number']) ?>
                                            </a>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= url('production/purchases/view/' . (int)$pr['purchase_id']) ?>" class="btn btn-sm btn-outline-primary" title="View Purchase Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 3: ROLLS REPLACED ("WHICH ROLL REPLACED" & COUNT) -->
            <div class="tab-pane fade p-3 p-md-4" id="pane-replaced-rolls" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-arrow-repeat me-1.5 text-info"></i>Damage Replacement Rolls Log</h6>
                        <small class="text-muted">Register of all replacement rolls received from this supplier to replace defective or damaged fabric rolls.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1.5 fs-6">
                            <i class="bi bi-arrow-repeat me-1"></i>Replaced Count: <strong><?= number_format($totalReplacedRollsCount) ?> Rolls</strong>
                        </span>
                        <button type="button" class="btn btn-sm btn-success fw-semibold" onclick="exportReplacedRollsCSV()" title="Export Replaced Rolls Data Only">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i>Export Replaced (CSV)
                        </button>
                    </div>
                </div>

                <?php if (empty($replacedRolls)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-shield-check fs-1 d-block mb-2 text-success"></i>
                        <h6 class="fw-bold text-dark">No Replacement Rolls Recorded</h6>
                        <p class="small mb-0">This supplier has no damaged roll replacements on file.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="replacedRollsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 4%;">#</th>
                                    <th>Replacement Roll</th>
                                    <th>Replaced For (Damaged Roll)</th>
                                    <th>Defect Reason / Cause</th>
                                    <th class="text-center">Quantity (pcs)</th>
                                    <th class="text-center">Weight (kg)</th>
                                    <th>Replacement QR Barcodes</th>
                                    <th>Replaced Date &amp; Time</th>
                                    <th>Damage Claim #</th>
                                    <th>Purchase Inward #</th>
                                    <th>Resolved By</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($replacedRolls as $idx => $rr): ?>
                                    <?php
                                        // Dynamically resolve damaged item title
                                        $damagedTitle = '';
                                        if (!empty($rr['damaged_roll_number'])) {
                                            $damagedTitle = $rr['damaged_roll_number'];
                                        } elseif (!empty($rr['product_name'])) {
                                            $damagedTitle = $rr['product_name'];
                                        } elseif (!empty($rr['catalog_product_name'])) {
                                            $damagedTitle = $rr['catalog_product_name'];
                                        } elseif (!empty($rr['seller_roll_id'])) {
                                            $damagedTitle = 'Roll #' . $rr['seller_roll_id'];
                                        } elseif (!empty($rr['qr_code'])) {
                                            $damagedTitle = 'Item [' . $rr['qr_code'] . ']';
                                        } else {
                                            $damagedTitle = 'Damaged Item #' . $rr['id'];
                                        }

                                        // Dynamically resolve damaged specifications
                                        $damagedSpecParts = [];
                                        if (!empty($rr['damaged_color']) && $rr['damaged_color'] !== '—') {
                                            $damagedSpecParts[] = 'Color: <strong>' . e($rr['damaged_color']) . '</strong>';
                                            if (!empty($rr['damaged_design']) && $rr['damaged_design'] !== '—') {
                                                $damagedSpecParts[] = 'Design: ' . e($rr['damaged_design']);
                                            }
                                        } elseif (!empty($rr['variant_color_name'])) {
                                            $damagedSpecParts[] = 'Color: <strong>' . e($rr['variant_color_name']) . '</strong>';
                                        }
                                        if (!empty($rr['variant_info'])) {
                                            $damagedSpecParts[] = e($rr['variant_info']);
                                        }
                                        if (!empty($rr['qr_code']) && strpos($damagedTitle, $rr['qr_code']) === false) {
                                            $damagedSpecParts[] = '<span class="font-monospace text-secondary">' . e($rr['qr_code']) . '</span>';
                                        }
                                        $damagedSpecHtml = implode(' • ', $damagedSpecParts);
                                    ?>
                                    <tr>
                                        <td class="text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-bold text-info font-monospace fs-6">
                                                <?= e($rr['repl_roll_number'] ?: ($rr['replacement_roll_number'] ?: '—')) ?>
                                            </div>
                                            <div class="small text-muted">
                                                Color: <strong><?= e($rr['repl_color'] ?: ($rr['damaged_color'] ?: '—')) ?></strong>
                                                <?php if (!empty($rr['repl_design'])): ?>
                                                    • Design: <strong><?= e($rr['repl_design']) ?></strong>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-danger font-monospace fs-6">
                                                <i class="bi bi-x-circle me-1"></i><?= e($damagedTitle) ?>
                                            </div>
                                            <?php if ($damagedSpecHtml !== ''): ?>
                                                <div class="small text-muted mt-0.5">
                                                    <?= $damagedSpecHtml ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 10px;">
                                                    Defect Qty: <?= number_format((float)($rr['damaged_quantity'] ?: 1)) ?> pcs
                                                    <?php if ((float)($rr['damaged_weight'] ?? 0) > 0): ?>
                                                        (<?= number_format((float)$rr['damaged_weight'], 2) ?> kg)
                                                    <?php endif; ?>
                                                </span>
                                                <a href="<?= url('production/damages') ?>?search=<?= urlencode($rr['damage_number']) ?>" class="badge bg-light text-primary border text-decoration-none" style="font-size: 10px;" title="View damage case">
                                                    <i class="bi bi-eye me-0.5"></i><?= e($rr['damage_number']) ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                <?= e($rr['damage_reason'] ?: 'Fabric Defect') ?>
                                            </span>
                                            <?php if (!empty($rr['defect_notes'])): ?>
                                                <div class="small text-muted mt-1" style="font-size: 11px; max-width: 200px;">
                                                    <?= e($rr['defect_notes']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold text-dark">
                                            <?= number_format((float)($rr['repl_quantity'] ?: ($rr['damaged_quantity'] ?? 0))) ?> pcs
                                        </td>
                                        <td class="text-center font-monospace">
                                            <?= number_format((float)($rr['repl_weight'] ?: ($rr['damaged_weight'] ?? 0)), 2) ?> kg
                                        </td>
                                        <td>
                                            <?php if (!empty($rr['repl_start_code']) && !empty($rr['repl_end_code'])): ?>
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                                                    <?= e($rr['repl_start_code']) ?> → <?= e($rr['repl_end_code']) ?>
                                                </span>
                                            <?php elseif (!empty($rr['repl_qr_code'])): ?>
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                                                    <?= e($rr['repl_qr_code']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $resDate = $rr['resolved_at'] ?: ($rr['updated_at'] ?: $rr['created_at']); ?>
                                            <div class="fw-semibold text-dark">
                                                <i class="bi bi-calendar-check me-1 text-success"></i><?= date('d M Y', strtotime($resDate)) ?>
                                            </div>
                                            <div class="small text-muted font-monospace">
                                                <i class="bi bi-clock me-1 text-secondary"></i><?= date('h:i:s A', strtotime($resDate)) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="<?= url('production/damages') ?>" class="fw-bold font-monospace text-decoration-none">
                                                <?= e($rr['damage_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($rr['replacement_purchase_id'])): ?>
                                                <a href="<?= url('production/purchases/view/' . (int)$rr['replacement_purchase_id']) ?>" class="fw-bold font-monospace text-decoration-none text-primary">
                                                    <?= e($rr['replacement_purchase_number']) ?>
                                                </a>
                                            <?php elseif (!empty($rr['replacement_purchase_number'])): ?>
                                                <span class="font-monospace text-dark"><?= e($rr['replacement_purchase_number']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small text-muted">
                                                <?= e($rr['resolved_by_name'] ?: ($rr['reported_by_name'] ?: 'System Administrator')) ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= url('production/damages') ?>?search=<?= urlencode($rr['damage_number']) ?>" class="btn btn-outline-danger btn-sm" title="View Damage Claim">
                                                    <i class="bi bi-shield-exclamation"></i>
                                                </a>
                                                <?php if (!empty($rr['replacement_purchase_id'])): ?>
                                                    <a href="<?= url('production/purchases/view/' . (int)$rr['replacement_purchase_id']) ?>" class="btn btn-outline-primary btn-sm" title="View Replacement Inward Purchase">
                                                        <i class="bi bi-cart-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 4: ACTIVE INVENTORY ROLLS -->
            <div class="tab-pane fade p-3 p-md-4" id="pane-active-rolls" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-layers me-1.5 text-secondary"></i>Active Fabric Rolls in Inventory</h6>
                        <small class="text-muted">Current rolls registered under this supplier ready for production cutting and program assignment.</small>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1">
                        Active Rolls: <?= number_format($totalActiveRollsCount) ?>
                    </span>
                </div>

                <?php if (empty($activeRolls)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-layers fs-1 d-block mb-2 text-secondary"></i>
                        <h6 class="fw-bold text-dark">No Active Rolls In Stock</h6>
                        <p class="small mb-0">All fabric rolls from this supplier have been allocated or none are active.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th>Roll Number</th>
                                    <th>Color</th>
                                    <th>Design / Pattern</th>
                                    <th class="text-center">Quantity (pcs)</th>
                                    <th class="text-center">Weight (kg)</th>
                                    <th class="text-end">Price / Rate</th>
                                    <th>QR Sequence Range</th>
                                    <th>Registered Date &amp; Time</th>
                                    <th>Programs Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeRolls as $idx => $ar): ?>
                                    <tr>
                                        <td class="text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-bold text-dark font-monospace fs-6"><?= e($ar['roll_number']) ?></div>
                                            <div class="small text-muted"><?= e($ar['material_type'] ?: 'STANDARD FABRIC') ?></div>
                                        </td>
                                        <td><span class="fw-semibold"><?= e($ar['color'] ?: '—') ?></span></td>
                                        <td><?= e($ar['design_name'] ?: '—') ?></td>
                                        <td class="text-center fw-bold text-dark"><?= number_format((float)$ar['quantity']) ?> pcs</td>
                                        <td class="text-center font-monospace"><?= number_format((float)($ar['weight'] ?? 0), 2) ?> kg</td>
                                        <td class="text-end font-monospace">₹<?= number_format((float)($ar['price'] ?? 0), 2) ?></td>
                                        <td>
                                            <?php if (!empty($ar['start_code']) && !empty($ar['end_code'])): ?>
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                                                    <?= e($ar['start_code']) ?> → <?= e($ar['end_code']) ?>
                                                </span>
                                            <?php elseif (!empty($ar['qr_code'])): ?>
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;"><?= e($ar['qr_code']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small text-dark fw-semibold">
                                                <?= !empty($ar['created_at']) ? date('d M Y', strtotime($ar['created_at'])) : '—' ?>
                                            </div>
                                            <div class="small text-muted font-monospace">
                                                <?= !empty($ar['created_at']) ? date('h:i:s A', strtotime($ar['created_at'])) : '' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ((int)$ar['programs_count'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <?= (int)$ar['programs_count'] ?> Programs
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 5: PURCHASE INVOICES -->
            <div class="tab-pane fade p-3 p-md-4" id="pane-purchases" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt me-1.5 text-dark"></i>Supplier Purchase Orders</h6>
                        <small class="text-muted">All purchase orders created with this supplier.</small>
                    </div>
                    <a href="<?= url('production/purchases/create?seller_id=' . (int)$seller['id']) ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Create New Purchase
                    </a>
                </div>

                <?php if (empty($purchases)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-receipt fs-1 d-block mb-2 text-secondary"></i>
                        <h6 class="fw-bold text-dark">No Purchase Orders</h6>
                        <p class="small mb-0">No purchase invoices on record for this supplier.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Purchase #</th>
                                    <th>Date &amp; Time</th>
                                    <th>Notes &amp; Description</th>
                                    <th class="text-center">Items Count</th>
                                    <th class="text-center">Total Quantity</th>
                                    <th class="text-center">Total Weight</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>Created By</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $p): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= url('production/purchases/view/' . (int)$p['id']) ?>" class="fw-bold font-monospace text-decoration-none">
                                                <?= e($p['purchase_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($p['created_at'] ?: $p['purchase_date'])) ?></div>
                                            <div class="small text-muted font-monospace"><?= date('h:i:s A', strtotime($p['created_at'] ?: $p['purchase_date'])) ?></div>
                                        </td>
                                        <td>
                                            <div class="small text-dark text-break" style="max-width: 250px;">
                                                <?= e($p['notes'] ?: '—') ?>
                                            </div>
                                        </td>
                                        <td class="text-center"><?= (int)$p['items_count'] ?> items</td>
                                        <td class="text-center fw-semibold"><?= number_format((float)$p['total_qty']) ?> pcs</td>
                                        <td class="text-center font-monospace"><?= number_format((float)$p['total_weight'], 2) ?> kg</td>
                                        <td class="text-end fw-bold font-monospace text-dark">₹<?= number_format((float)$p['total_amount'], 2) ?></td>
                                        <td><span class="small text-muted"><?= e($p['created_by_name'] ?: 'System') ?></span></td>
                                        <td class="text-end">
                                            <a href="<?= url('production/purchases/view/' . (int)$p['id']) ?>" class="btn btn-sm btn-outline-primary" title="View Purchase">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function filterAuditLogTable(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#auditLogTable tbody tr.audit-row');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = (q === '' || text.includes(q)) ? '' : 'none';
    });
}

// Replaced Rolls dataset formatted specifically for CSV export
const replacedRollsData = <?= json_encode(array_map(function($idx, $rr) {
    // Dynamic damaged title
    $damagedTitle = '';
    if (!empty($rr['damaged_roll_number'])) {
        $damagedTitle = $rr['damaged_roll_number'];
    } elseif (!empty($rr['product_name'])) {
        $damagedTitle = $rr['product_name'];
    } elseif (!empty($rr['catalog_product_name'])) {
        $damagedTitle = $rr['catalog_product_name'];
    } elseif (!empty($rr['seller_roll_id'])) {
        $damagedTitle = 'Roll #' . $rr['seller_roll_id'];
    } elseif (!empty($rr['qr_code'])) {
        $damagedTitle = 'Item [' . $rr['qr_code'] . ']';
    } else {
        $damagedTitle = 'Damaged Item #' . $rr['id'];
    }

    $damagedSpecParts = [];
    if (!empty($rr['damaged_color']) && $rr['damaged_color'] !== '—') {
        $damagedSpecParts[] = 'Color: ' . $rr['damaged_color'];
    } elseif (!empty($rr['variant_color_name'])) {
        $damagedSpecParts[] = 'Color: ' . $rr['variant_color_name'];
    }
    if (!empty($rr['damaged_design'])) {
        $damagedSpecParts[] = 'Design: ' . $rr['damaged_design'];
    }
    if (!empty($rr['variant_size_name'])) {
        $damagedSpecParts[] = 'Size: ' . $rr['variant_size_name'];
    }
    if (!empty($rr['variant_info'])) {
        $damagedSpecParts[] = $rr['variant_info'];
    }
    if (!empty($rr['qr_code']) && strpos($damagedTitle, $rr['qr_code']) === false) {
        $damagedSpecParts[] = 'QR: ' . $rr['qr_code'];
    }
    $damagedSpec = implode(' | ', $damagedSpecParts);

    // Replacement Roll No
    $replRoll = $rr['repl_roll_number'] ?: ($rr['replacement_roll_number'] ?: ('Roll #' . ($rr['replacement_roll_id'] ?: '—')));
    $replColor = $rr['repl_color'] ?: '—';
    $replDesign = $rr['repl_design'] ?: '—';
    $replQty = (float)($rr['repl_quantity'] ?: ($rr['damaged_quantity'] ?: 0));
    $replWeight = (float)($rr['repl_weight'] ?: ($rr['damaged_weight'] ?: 0));
    $replPrice = (float)($rr['repl_price'] ?: 0);
    $replValuation = $replQty > 0 && $replPrice > 0 ? ($replQty * $replPrice) : (float)($rr['replacement_valuation'] ?: ($rr['loss_valuation'] ?: 0));

    // QR range
    $replQrRange = '—';
    if (!empty($rr['repl_start_code']) && !empty($rr['repl_end_code'])) {
        $replQrRange = $rr['repl_start_code'] . ' -> ' . $rr['repl_end_code'];
    } elseif (!empty($rr['repl_qr_code'])) {
        $replQrRange = $rr['repl_qr_code'];
    }

    $dateStr = date('d-m-Y H:i:s', strtotime($rr['resolved_at'] ?: ($rr['updated_at'] ?: $rr['created_at'])));

    return [
        'sl_no' => $idx + 1,
        'replacement_roll' => $replRoll,
        'color' => $replColor,
        'design' => $replDesign,
        'replaced_for_damaged' => $damagedTitle,
        'damaged_details' => $damagedSpec,
        'defect_qty' => number_format((float)($rr['damaged_quantity'] ?: 1), 2) . ' pcs',
        'defect_weight' => (float)($rr['damaged_weight'] ?? 0) > 0 ? number_format((float)$rr['damaged_weight'], 2) . ' kg' : '—',
        'defect_reason' => $rr['defect_type'] ?: '—',
        'defect_notes' => $rr['defect_notes'] ?: ($rr['resolution_notes'] ?: '—'),
        'quantity_pcs' => $replQty,
        'weight_kg' => $replWeight,
        'valuation_inr' => $replValuation,
        'replacement_qr_codes' => $replQrRange,
        'replaced_date' => $dateStr,
        'damage_claim_no' => $rr['damage_number'] ?: '—',
        'purchase_inward_no' => $rr['replacement_purchase_number'] ?: '—',
        'resolved_by' => $rr['resolved_by_name'] ?: ($rr['reported_by_name'] ?: 'System Administrator')
    ];
}, array_keys($replacedRolls), $replacedRolls), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function exportReplacedRollsCSV() {
    if (!replacedRollsData || replacedRollsData.length === 0) {
        alert('No replaced rolls data found to export.');
        return;
    }

    const headers = [
        '#',
        'Replacement Roll No',
        'Color',
        'Design / Pattern',
        'Replaced For (Damaged Roll / Item)',
        'Damaged Item Specifications',
        'Defect Quantity',
        'Defect Weight',
        'Defect Reason / Cause',
        'Defect / Resolution Notes',
        'Quantity (pcs)',
        'Weight (kg)',
        'Valuation (INR)',
        'Replacement QR Codes',
        'Replaced Date & Time',
        'Damage Claim #',
        'Purchase Inward #',
        'Resolved By'
    ];

    function escapeCSV(val) {
        if (val === null || val === undefined) return '""';
        let str = String(val).replace(/"/g, '""');
        return `"${str}"`;
    }

    let csvContent = '\uFEFF'; // UTF-8 BOM for Excel compatibility
    csvContent += headers.map(escapeCSV).join(',') + '\r\n';

    replacedRollsData.forEach(row => {
        const line = [
            row.sl_no,
            row.replacement_roll,
            row.color,
            row.design,
            row.replaced_for_damaged,
            row.damaged_details,
            row.defect_qty,
            row.defect_weight,
            row.defect_reason,
            row.defect_notes,
            row.quantity_pcs,
            row.weight_kg,
            row.valuation_inr,
            row.replacement_qr_codes,
            row.replaced_date,
            row.damage_claim_no,
            row.purchase_inward_no,
            row.resolved_by
        ];
        csvContent += line.map(escapeCSV).join(',') + '\r\n';
    });

    const supplierName = <?= json_encode(preg_replace('/[^a-zA-Z0-9_-]/', '_', $seller['name'] ?? 'Supplier')) ?>;
    const dateStamp = new Date().toISOString().slice(0, 10);
    const filename = `Replaced_Rolls_${supplierName}_${dateStamp}.csv`;

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
</script>
