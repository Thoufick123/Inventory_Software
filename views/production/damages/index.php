<div class="page-header mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 w-100">
        <div>
            <h2 class="mb-0"><i class="bi bi-exclamation-octagon me-2 text-danger"></i>Fabric &amp; Roll Damage Management</h2>
            </div>
        <div class="d-flex gap-2 align-items-center ms-auto">
            <?php if (hasPermission('damages', 'create')): ?>
            <button type="button" class="btn btn-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#reportDamageModal">
                <i class="bi bi-plus-circle me-1"></i> Report Roll Damage
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- KPI Stats Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm bg-danger bg-opacity-10 h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase text-muted fw-semibold small d-block">Damaged Rolls</span>
                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($stats['total_damaged_records']) ?> <span class="fs-6 fw-normal text-muted">records</span></h3>
                    <small class="text-muted"><?= number_format($stats['total_damaged_weight'], 2) ?> kg total</small>
                </div>
                <div class="bg-danger text-white rounded-3 p-3">
                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10 h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase text-muted fw-semibold small d-block">Estimated Loss Value</span>
                    <h3 class="fw-bold text-warning mb-0 mt-1">₹<?= number_format($stats['total_loss_amount'], 2) ?></h3>
                    <small class="text-muted"><?= $stats['count_logged'] ?> pending action</small>
                </div>
                <div class="bg-warning text-dark rounded-3 p-3">
                    <i class="bi bi-currency-rupee fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm bg-info bg-opacity-10 h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase text-muted fw-semibold small d-block">Returns / Claims</span>
                    <h3 class="fw-bold text-info mb-0 mt-1"><?= number_format($stats['count_return_initiated']) ?> <span class="fs-6 fw-normal text-muted">in transit</span></h3>
                    <small class="text-muted"><?= $stats['count_replacement'] ?> replacements</small>
                </div>
                <div class="bg-info text-white rounded-3 p-3">
                    <i class="bi bi-arrow-left-right fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase text-muted fw-semibold small d-block">Refunds Recovered</span>
                    <h3 class="fw-bold text-success mb-0 mt-1">₹<?= number_format($stats['total_refund_received'], 2) ?></h3>
                    <small class="text-muted"><?= $stats['count_refunded'] ?> debit claims</small>
                </div>
                <div class="bg-success text-white rounded-3 p-3">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters & Search Card -->
<div class="card shadow-sm border mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('production/damages') ?>" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="damageSearchInput" class="form-control" placeholder="Search damage #, roll, supplier..." value="<?= e($search) ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-3">
                <select name="seller_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Material Suppliers --</option>
                    <?php foreach ($sellers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $sellerId == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?><?= !empty($s['company']) ? ' (' . e($s['company']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="reason" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Defect Reasons --</option>
                    <?php foreach ($damageReasons as $damageReasonOption): ?>
                        <?php $reasonValue = $damageReasonOption['damage_reason']; ?>
                        <option value="<?= e($reasonValue) ?>" <?= $reason === $reasonValue ? 'selected' : '' ?>><?= e($reasonValue) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Statuses --</option>
                    <option value="LOGGED" <?= $status === 'LOGGED' ? 'selected' : '' ?>>Logged (Pending Action)</option>
                    <option value="RETURN_INITIATED" <?= $status === 'RETURN_INITIATED' ? 'selected' : '' ?>>Return Initiated</option>
                    <option value="RETURNED_TO_SELLER" <?= $status === 'RETURNED_TO_SELLER' ? 'selected' : '' ?>>Returned to Supplier</option>
                    <option value="REFUND_RECEIVED" <?= $status === 'REFUND_RECEIVED' ? 'selected' : '' ?>>Refund Received</option>
                    <option value="REPLACEMENT_RECEIVED" <?= $status === 'REPLACEMENT_RECEIVED' ? 'selected' : '' ?>>Replacement Received</option>
                    <option value="CLOSED" <?= $status === 'CLOSED' ? 'selected' : '' ?>>Closed / Resolved</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="<?= url('production/damages') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Damage Records Table Card -->
<div class="card shadow-sm border mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0 fw-bold">
            <i class="bi bi-list-check me-2 text-danger"></i>Fabric Roll Damage Log (<?= count($damages) ?>)
        </h5>
        <span class="text-muted small">Damaged roll weights are automatically deducted from production programs</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($damages)): ?>
            <div class="text-center py-5 text-muted">
                <div class="d-inline-flex p-3 rounded-circle bg-light mb-2">
                    <i class="bi bi-shield-check fs-1 text-success"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No Fabric Damages Recorded</h5>
                <p class="text-muted small mb-0">No fabric rolls match your filter criteria or have reported defect issues.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="damagesTable" style="font-size: 13px;">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Damage # &amp; Date</th>
                            <th>Supplier &amp; Lot</th>
                            <th>Roll Details</th>
                            <th>Defect Reason &amp; Notes</th>
                            <th class="text-end">Damaged Qty / Wt</th>
                            <th class="text-end">Loss Valuation</th>
                            <th class="text-center">Resolution &amp; Status</th>
                            <th class="text-center" style="width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($damages as $d): ?>
                            <tr class="damage-row">
                                <!-- Damage Number & Date -->
                                <td>
                                    <span class="badge bg-dark font-monospace fs-7 px-2 py-1"><?= e($d['damage_number']) ?></span>
                                    <div class="text-muted small mt-1">
                                        <i class="bi bi-clock me-1"></i><?= date('d M Y, h:i A', strtotime($d['created_at'])) ?>
                                    </div>
                                    <small class="text-muted d-block">By: <?= e($d['reported_by_name'] ?? 'Staff') ?></small>
                                </td>

                                <!-- Supplier & Mill / Origin -->
                                <td>
                                    <?php if (!empty($d['seller_name'])): ?>
                                        <a href="<?= url('production/sellers/view/' . $d['seller_id']) ?>" class="fw-bold text-decoration-none text-dark d-block">
                                            <?= e($d['seller_name']) ?>
                                        </a>
                                        <?php if (!empty($d['seller_company'])): ?>
                                            <small class="text-muted"><i class="bi bi-building me-1"></i><?= e($d['seller_company']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border"><i class="bi bi-box-seam me-1"></i>Finished Garment</span>
                                        <small class="text-muted d-block mt-0.5">Own Production</small>
                                    <?php endif; ?>
                                </td>

                                <!-- Roll / Product Details, Photo & QR Code -->
                                <td>
                                    <?php 
                                        $isSupplyDamage = (bool)preg_match('/^SP\d+/i', (string)($d['qr_code'] ?? ''));
                                        $isProductDamage = $isSupplyDamage || (($d['item_type'] ?? '') === 'PRODUCT' || !empty($d['product_id']) || !empty($d['variant_id']) || empty($d['seller_roll_id']));
                                        $qrCodeVal = !empty($d['qr_code']) ? $d['qr_code'] : (!empty($d['roll_qr_code']) ? $d['roll_qr_code'] : (!empty($d['roll_start_code']) ? $d['roll_start_code'] : ($d['roll_number'] ?? 'QR')));
                                        $displayName = $isProductDamage ? (!empty($d['product_name']) ? $d['product_name'] : ($d['catalog_product_name'] ?: ($isSupplyDamage ? 'Garment Supply' : 'Garment Product'))) : ($d['roll_number'] ?: 'Fabric Roll');
                                        $supplierOrOrigin = !empty($d['seller_company']) ? $d['seller_company'] : (!empty($d['seller_name']) ? $d['seller_name'] : 'Production Floor');
                                    ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <!-- Scannable QR Code Thumbnail -->
                                        <div class="damage-qr-thumb position-relative" style="width: 46px; height: 46px; background: #fff; border: 1.5px solid #0f172a; border-radius: 6px; padding: 2px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;" title="Click to view & scan Damaged Item QR Code" onclick="showDamageQrModal('<?= e($d['damage_number']) ?>', '<?= e($qrCodeVal) ?>', '<?= e(addslashes($displayName)) ?>', '<?= e(addslashes($d['damage_reason'])) ?>', '<?= number_format($d['damaged_weight'], 2) ?> kg', '<?= e(addslashes($supplierOrOrigin)) ?>')">
                                            <div id="damage-qr-<?= $d['id'] ?>" class="damage-qr-canvas" data-qr="<?= e($qrCodeVal) ?>" style="width: 40px; height: 40px;"></div>
                                        </div>

                                        <div>
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <span class="fw-bold text-dark fs-7"><?= e($displayName) ?></span>
                                                <span class="badge bg-danger text-white font-monospace" style="font-size: 10px; cursor: pointer;" title="Quarantined Damage QR - Click to inspect" onclick="showDamageQrModal('<?= e($d['damage_number']) ?>', '<?= e($qrCodeVal) ?>', '<?= e(addslashes($displayName)) ?>', '<?= e(addslashes($d['damage_reason'])) ?>', '<?= number_format($d['damaged_weight'], 2) ?> kg', '<?= e(addslashes($supplierOrOrigin)) ?>')">
                                                    <i class="bi bi-qr-code me-1"></i><?= e($qrCodeVal) ?>
                                                </span>
                                            </div>
                                            <div class="d-flex gap-1 flex-wrap mt-1">
                                                <?php if ($isProductDamage): ?>
                                                    <?php if (!empty($d['variant_size_name'])): ?>
                                                        <span class="badge bg-light text-dark border px-1">Size: <?= e($d['variant_size_name']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($d['variant_color_name'])): ?>
                                                        <span class="badge bg-info-subtle text-info border px-1"><?= e($d['variant_color_name']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($d['variant_sku'])): ?>
                                                        <span class="badge bg-secondary-subtle text-secondary border px-1"><?= e($d['variant_sku']) ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if (!empty($d['roll_color'])): ?>
                                                        <span class="badge bg-info-subtle text-info border px-1"><?= e($d['roll_color']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($d['roll_design'])): ?>
                                                        <span class="badge bg-warning-subtle text-warning border px-1"><?= e($d['roll_design']) ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if (!empty($d['photo_url'])): ?>
                                                    <a href="<?= uploadUrl($d['photo_url']) ?>" target="_blank" class="badge bg-light text-secondary border text-decoration-none px-1" title="View Defect Photo">
                                                        <i class="bi bi-image me-1"></i>Photo
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Defect Reason & Notes -->
                                <td>
                                    <span class="badge bg-danger-subtle text-danger border fw-bold mb-1 d-inline-block">
                                        <i class="bi bi-exclamation-diamond me-1"></i><?= e($d['damage_reason']) ?>
                                    </span>
                                    <?php if (!empty($d['defect_notes'])): ?>
                                        <div class="small text-muted text-wrap" style="max-width: 240px; line-height: 1.2;">
                                            <?= e($d['defect_notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Damaged Qty & Weight -->
                                <td class="text-end">
                                    <?php if ($isSupplyDamage): ?>
                                        <div class="fw-bold text-dark"><?= (int)$d['damaged_quantity'] ?> Pc(s)</div>
                                        <small class="text-muted">Supply Item</small>
                                    <?php else: ?>
                                        <div class="fw-bold text-danger"><?= number_format($d['damaged_weight'], 2) ?> kg</div>
                                        <small class="text-muted"><?= (int)$d['damaged_quantity'] ?> <?= $isProductDamage ? 'Pc(s)' : 'Roll(s)' ?></small>
                                    <?php endif; ?>
                                </td>

                                <!-- Loss Valuation -->
                                <td class="text-end">
                                    <div class="fw-bold text-dark fs-7">₹ <?= number_format($d['loss_amount'], 2) ?></div>
                                    <?php if ($d['refund_amount'] > 0): ?>
                                        <small class="text-success fw-semibold d-block">
                                            <i class="bi bi-check-all me-1"></i>₹<?= number_format($d['refund_amount'], 2) ?> Refunded
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <!-- Status & Resolution -->
                                <td class="text-center">
                                    <?php
                                    $statusBadgeClass = match($d['status']) {
                                        'LOGGED'               => 'bg-danger-subtle text-danger border-danger-subtle',
                                        'RETURN_INITIATED'     => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                        'RETURNED_TO_SELLER'   => 'bg-info-subtle text-info border-info-subtle',
                                        'REFUND_RECEIVED'      => 'bg-success-subtle text-success border-success-subtle',
                                        'REPLACEMENT_RECEIVED' => 'bg-primary-subtle text-primary border-primary-subtle',
                                        'CLOSED'               => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                        default                => 'bg-light text-dark border'
                                    };
                                    $statusLabel = match($d['status']) {
                                        'LOGGED'               => 'Logged (Pending)',
                                        'RETURN_INITIATED'     => 'Return Initiated',
                                        'RETURNED_TO_SELLER'   => 'Returned to Supplier',
                                        'REFUND_RECEIVED'      => 'Refund / Debit Claimed',
                                        'REPLACEMENT_RECEIVED' => 'Replacement Received',
                                        'CLOSED'               => 'Closed / Resolved',
                                        default                => $d['status']
                                    };
                                    ?>
                                    <span class="badge <?= $statusBadgeClass ?> px-2 py-1 mb-1 d-inline-block">
                                        <?= $statusLabel ?>
                                    </span>
                                    <?php if (!empty($d['debit_note_number'])): ?>
                                        <small class="text-muted d-block font-monospace">DN: <?= e($d['debit_note_number']) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($d['replacement_roll_number']) || !empty($d['replacement_roll_code'])): ?>
                                        <small class="text-primary d-block font-monospace">Repl: <?= e($d['replacement_roll_code'] ?? $d['replacement_roll_number']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="text-center text-nowrap">
                                    <div class="action-btns-group justify-content-center">
                                        <a href="<?= url('production/damages/view/' . $d['id']) ?>" class="btn btn-action-icon btn-outline-info" title="View Damage Details" aria-label="View Damage Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (hasPermission('damages', 'edit')): ?>
                                        <button type="button" class="btn btn-action-icon btn-outline-primary" title="Edit Damage Record" aria-label="Edit Damage Record" onclick='openEditDamageModal(<?= json_encode($d) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-action-icon btn-outline-success" title="Update Return / Refund / Resolution" aria-label="Update Return / Refund / Resolution" onclick='openResolveModal(<?= json_encode($d) ?>)'>
                                            <i class="bi bi-patch-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (hasPermission('damages', 'delete')): ?>
                                        <button type="button" class="btn btn-action-icon btn-outline-danger" title="Delete Damage Record" aria-label="Delete Damage Record" onclick="confirmDeleteDamage('<?= url('production/damages/delete/' . $d['id']) ?>', '<?= e(addslashes($d['damage_number'])) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls Footer -->
            <div class="card-footer bg-white py-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-3" id="damagesPaginationFooter">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="small text-muted fw-medium" id="damagesPageInfo">
                        Showing 1 to 10 of <?= count($damages) ?> records
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0 fw-medium">Show:</label>
                        <select id="damagesPageSizeSelect" class="form-select form-select-sm" style="width: auto;">
                            <option value="10" selected>10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                </div>
                <nav aria-label="Damages Pagination">
                    <ul class="pagination pagination-modern mb-0" id="damagesPaginationList">
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 1. Report Damage Modal -->
<div class="modal fade" id="reportDamageModal" tabindex="-1" aria-hidden="true" style="overflow-y: auto;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-danger">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Report Damage / Defect (Rolls &amp; Finished Products)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('production/damages/store') ?>" enctype="multipart/form-data" id="reportDamageForm">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                    <label for="damageRollQrScan" class="form-label fw-bold mb-0">
                                        <i class="bi bi-upc-scan text-success me-1"></i>Scan Fabric Roll Barcode / QR Tag
                                    </label>
                                    <small class="text-muted">Hardware barcode scanner</small>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-success border-end-0">
                                        <i class="bi bi-qr-code"></i>
                                    </span>
                                    <input type="text" id="damageRollQrScan" class="form-control border-start-0" placeholder="Scan a Roll QR or Finished Product Tag and press Enter" autocomplete="off">
                                    <button type="button" class="btn btn-success fw-semibold px-3" onclick="scanDamageRollQr()">
                                        <i class="bi bi-plus-circle me-1"></i>Scan / Add
                                    </button>
                                </div>
                            </div>
                            <div id="damageRollScanStatus" class="small mt-1" role="status" aria-live="polite"></div>
                            <div class="border rounded mt-2 p-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Scanned labels</strong>
                                    <span id="damageScannedCount" class="badge bg-secondary">0 pieces</span>
                                </div>
                                <div id="damageScannedLabels" class="list-group small" style="max-height: none; overflow: visible;">
                                    <div class="list-group-item text-muted" id="damageScannedEmpty">No labels scanned yet.</div>
                                </div>
                            </div>
                            <div id="damageQrInputs"></div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0 small">
                                <i class="bi bi-info-circle me-1"></i>Set a damage reason and attach a photo for each scanned label.
                            </div>
                        </div>

                        <!-- Damaged Weight (Disabled with clear visibility - Auto-calculated) -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                Damaged Weight <span class="badge bg-secondary-subtle text-secondary border ms-1">Auto</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="damaged_weight" id="inputDamagedWeight" class="form-control bg-light fw-bold text-dark" placeholder="0.00" readonly tabindex="-1" style="background-color: #f1f3f5 !important; cursor: not-allowed;">
                                <span class="input-group-text bg-light">kg</span>
                            </div>
                            <small class="text-muted d-block mt-1" id="reportWeightHelp">Rate: ₹0.00 / kg</small>
                        </div>

                        <!-- Estimated Loss Valuation (Disabled with clear visibility - Auto-calculated) -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-danger">
                                <i class="bi bi-currency-rupee me-1"></i>Estimated Loss Valuation <span class="badge bg-danger-subtle text-danger border ms-1">Auto</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger-subtle text-danger fw-bold">₹</span>
                                <input type="number" step="0.01" name="loss_amount" id="inputEstimatedLoss" class="form-control fw-bold text-danger bg-light fs-6" placeholder="0.00" readonly tabindex="-1" style="background-color: #f1f3f5 !important; cursor: not-allowed;">
                                <span class="input-group-text bg-light text-muted small" id="reportRateBadge">₹0.00 / kg</span>
                            </div>
                            <small class="text-muted d-block mt-1" id="reportLossCalcHelp">Calculated: 0.00 kg × ₹0.00/kg</small>
                        </div>

                        <!-- Shared notes are optional; reason and evidence are captured per scanned label. -->
                        <div class="col-12">
                            <label class="form-label fw-semibold" id="reportNotesLabel">
                                Additional Notes &amp; Observations <small class="text-muted fw-normal">(Optional, applies to all scanned labels)</small>
                            </label>
                            <textarea name="defect_notes" id="reportDefectNotes" class="form-control" rows="2" placeholder="Add additional observations if needed"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold shadow-sm">
                        <i class="bi bi-check-circle me-1"></i> Save Damage &amp; Deduct Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Edit Damage Modal -->
<div class="modal fade" id="editDamageModal" tabindex="-1" aria-hidden="true" style="overflow-y: auto;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-warning">
            <div class="modal-header bg-warning text-dark py-3">
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <h5 class="modal-title fw-bold fs-6 mb-0">
                            <i class="bi bi-pencil-square me-2"></i>Edit Damage / Defect (Rolls &amp; Finished Products)
                        </h5>
                        <small class="text-dark opacity-75" style="font-size: 11px;">Update reason, evidence, and notes</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editDamageForm" enctype="multipart/form-data">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <!-- Record & Status Header Ribbon -->
                    <div class="py-3 px-4 bg-light rounded-3 border mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted text-uppercase fw-semibold" style="font-size: 11px;">Record Number:</span>
                            <span class="badge bg-dark font-monospace" id="editDamageNo">DMG-ROL-XXXX</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted text-uppercase fw-semibold" style="font-size: 11px;">Status:</span>
                            <span class="badge bg-danger-subtle text-danger border" id="editDamageStatus">LOGGED</span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12" id="editDamageRollScanner">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                    <label for="editDamageRollQrScan" class="form-label fw-bold mb-0">
                                        <i class="bi bi-upc-scan text-success me-1"></i>Scan Fabric Roll QR to Select Roll
                                    </label>
                                    <small class="text-muted">Hardware barcode scanner</small>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-success border-end-0">
                                        <i class="bi bi-qr-code"></i>
                                    </span>
                                    <input type="text" id="editDamageRollQrScan" class="form-control border-start-0" placeholder="Scan a roll QR and press Enter to select it for this record" autocomplete="off">
                                    <button type="button" class="btn btn-warning text-dark fw-semibold px-3" onclick="scanEditDamageRollQr()">
                                        <i class="bi bi-check2-circle me-1"></i>Scan / Select
                                    </button>
                                </div>
                                <div id="editDamageRollScanStatus" class="small mt-1" role="status" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="d-none">
                            <select name="seller_id" id="editSellerSelect" onchange="loadSupplierRollsForEdit(this.value)">
                                <option value="">-- Choose Supplier --</option>
                                <?php foreach ($sellers as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= e($s['name']) ?><?= !empty($s['company']) ? ' (' . e($s['company']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="seller_roll_id" id="editRollSelect" onchange="handleEditRollSelectionChange(this)">
                                <option value="">-- Select Supplier First --</option>
                            </select>
                            <input type="hidden" name="damaged_quantity" id="editDamagedQty" value="1">
                        </div>

                        <div class="col-12">
                            <div class="border rounded mt-2 p-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Scanned labels</strong>
                                    <span class="badge bg-secondary">1 item</span>
                                </div>
                                <div class="list-group-item rounded small">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div class="text-break">
                                            <strong id="editDamageLabelCode" class="d-block">Existing damage item</strong>
                                            <div id="editDamageLabelMeta" class="text-muted mt-1"></div>
                                        </div>
                                        <span class="badge bg-secondary flex-shrink-0">Existing record</span>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="form-label fw-bold small text-dark mb-0" for="editReasonSelect">Damage / Defect Reason <span class="text-danger">*</span></label>
                                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-semibold" onclick="addDamageReason('editReasonSelect')" style="font-size: 11px;"><i class="bi bi-plus-circle me-1"></i>New</button>
                                            </div>
                                            <select name="damage_reason" id="editReasonSelect" class="form-select" required>
                                                <option value="">-- Select Reason --</option>
                                                <?php foreach ($damageReasons as $damageReasonOption): ?>
                                                    <option value="<?= e($damageReasonOption['damage_reason']) ?>"><?= e($damageReasonOption['damage_reason']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-7">
                                            <label class="form-label fw-bold small text-dark" id="editPhotoLabel">
                                                Defect Photo / Evidence <span class="text-danger" id="editPhotoReqStar" style="display:none;">*</span>
                                            </label>
                                            <input type="file" name="photo" id="editPhotoInput" class="form-control" accept="image/*,application/pdf">
                                            <div id="editCurrentPhotoBox" class="mt-2" style="display: none;">
                                                <small class="text-muted d-block mb-1" style="font-size: 11px;">Current Attached Proof:</small>
                                                <a href="#" target="_blank" id="editCurrentPhotoLink" class="d-inline-block">
                                                    <img src="" id="editCurrentPhotoImg" class="rounded border shadow-sm" style="max-height: 60px; max-width: 120px; object-fit: cover;">
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0 small">
                                <i class="bi bi-info-circle me-1"></i>This edits the selected damage record only; scanning will not create another damage entry.
                            </div>
                        </div>

                        <div class="col-md-6" id="editDamageWeightSummary">
                            <label class="form-label fw-bold text-dark">
                                Damaged Weight <span class="badge bg-secondary-subtle text-secondary border ms-1">Auto</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="damaged_weight" id="editDamagedWeight" class="form-control bg-light fw-bold text-dark" placeholder="0.00" readonly tabindex="-1">
                                <span class="input-group-text bg-light">kg</span>
                            </div>
                            <small class="text-muted d-block mt-1" id="editWeightHelp">Rate: ₹0.00 / kg</small>
                        </div>

                        <div class="col-md-6" id="editDamageLossSummary">
                            <label class="form-label fw-bold text-danger">
                                <i class="bi bi-currency-rupee me-1"></i><span id="editDamageLossLabel">Estimated Loss Valuation</span> <span class="badge bg-danger-subtle text-danger border ms-1">Auto</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger-subtle text-danger fw-bold">₹</span>
                                <input type="number" step="0.01" name="loss_amount" id="editEstimatedLoss" class="form-control fw-bold text-danger bg-light" placeholder="0.00" readonly tabindex="-1">
                                <span class="input-group-text bg-light text-muted small" id="editRateBadge">₹0.00 / kg</span>
                            </div>
                            <small class="text-muted d-block mt-1" id="editLossCalcHelp">Calculated from roll rate</small>
                        </div>

                        <!-- Defect Notes -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark" id="editNotesLabel">
                                Defect Notes &amp; Observations <small class="text-muted fw-normal">(Optional)</small>
                            </label>
                            <textarea name="defect_notes" id="editDefectNotes" class="form-control" rows="2" placeholder="Add additional observations if needed"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark px-4 fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Update Damage Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Resolve / Supplier Return & Refund Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-lg border-primary">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-arrow-repeat me-2"></i>Resolve Fabric Damage &amp; Supplier Return / Claim
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="resolveDamageForm" enctype="multipart/form-data">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <!-- Damage Summary Banner -->
                    <div class="p-3 bg-light rounded border mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="badge bg-dark font-monospace" id="resDamageNo">DMG-ROL-XXXX</span>
                            <strong class="text-dark ms-2" id="resSellerName">Supplier</strong>
                            <div class="small text-muted mt-1">
                                Roll: <span class="fw-semibold text-dark" id="resRollInfo">Roll #01</span> • 
                                Defect: <span class="badge bg-danger-subtle text-danger" id="resReason">Reason</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="small text-muted d-block">ORIGINAL LOSS VALUATION</span>
                            <span class="fs-5 fw-bold text-danger" id="resLossAmount">₹0.00</span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Action Status (Required) -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold required">Update Damage Status</label>
                            <select name="status" id="resStatusSelect" class="form-select form-select-lg" required onchange="handleResolutionStatusChange(this.value)">
                                <option value="">-- Choose Damage Status --</option>
                                <option value="LOGGED">Logged (Pending Action)</option>
                                <option value="RETURN_INITIATED">Return Initiated to Supplier</option>
                                <option value="RETURNED_TO_SELLER">Returned / Delivered to Supplier</option>
                                <option value="REFUND_RECEIVED">Refund / Debit Note Received</option>
                                <option value="REPLACEMENT_RECEIVED">Replacement Roll Received</option>
                                <option value="CLOSED">Closed / Resolved</option>
                            </select>
                        </div>

                        <!-- Resolution Type (Required) -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold required">Resolution Method</label>
                            <select name="resolution_type" id="resTypeSelect" class="form-select form-select-lg" required onchange="handleResolutionTypeChange(this.value)">
                                <option value="">-- Choose Resolution Method --</option>
                                <option value="SUPPLIER_REFUND">Supplier Refund / Debit Note (Amount Credited)</option>
                                <option value="SUPPLIER_REPLACEMENT">Supplier Replacement Roll (Exchange for New Roll)</option>
                                <option value="RATE_CONCESSION">Rate Concession / Discount Adjustment</option>
                                <option value="SCRAP_WRITE_OFF">Scrap / Internal Write-Off</option>
                            </select>
                        </div>

                        <!-- 1. Refund Fields (shown when Refund / Debit Note) -->
                        <div class="col-md-6 res-field-refund" id="fieldRefundAmount" style="display: none;">
                            <label class="form-label fw-bold text-success">Refund / Debit Amount Received (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success-subtle text-success">₹</span>
                                <input type="number" step="0.01" name="refund_amount" id="inputRefundAmount" class="form-control fw-bold text-success" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 res-field-refund" id="fieldDebitNote" style="display: none;">
                            <label class="form-label fw-semibold">Supplier Debit / Credit Note #</label>
                            <input type="text" name="debit_note_number" id="inputDebitNote" class="form-control" placeholder="e.g. DN-2026-8841">
                        </div>

                        <!-- 2. Replacement Roll Full Specification Fields (shown when Replacement Roll) -->
                        <div class="col-12 res-field-replacement" id="fieldReplacementBox" style="display: none;">
                            <div class="card border border-primary-subtle shadow-sm rounded-3">
                                <div class="card-header bg-primary-subtle text-primary py-2 px-3 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold small text-uppercase"><i class="bi bi-box-seam me-1"></i> Supplier Replacement Roll Details</span>
                                    <span class="badge bg-primary text-white font-monospace">New Inward Roll</span>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-lg-2 col-md-4">
                                            <label class="form-label required small fw-bold">Roll No. / Code *</label>
                                            <input type="text" name="replacement_roll_number" id="inputReplacementRoll" class="form-control text-uppercase" placeholder="ROLL #1" maxlength="50">
                                        </div>
                                        <div class="col-lg-2 col-md-4">
                                            <label class="form-label required small fw-bold">Color / Shade *</label>
                                            <input type="text" name="replacement_color" id="inputReplacementColor" class="form-control text-uppercase" placeholder="E.G. NAVY BLUE" maxlength="100">
                                        </div>
                                        <div class="col-lg-2 col-md-4">
                                            <label class="form-label required small fw-bold">Design Name / Pattern *</label>
                                            <input type="text" name="replacement_design_name" id="inputReplacementDesign" class="form-control text-uppercase" placeholder="E.G. FLORAL 01" maxlength="150">
                                        </div>
                                        <div class="col-lg-1 col-md-3">
                                            <label class="form-label required small fw-bold">Designs *</label>
                                            <input type="number" min="1" step="1" name="replacement_design_count" id="inputReplacementDesignCount" class="form-control" value="1">
                                        </div>
                                        <div class="col-lg-1 col-md-3">
                                            <label class="form-label required small fw-bold">RQ *</label>
                                            <input type="number" min="1" step="1" name="replacement_quantity" id="inputReplacementQuantity" class="form-control" value="1">
                                        </div>
                                        <div class="col-lg-2 col-md-3">
                                            <label class="form-label required small fw-bold">Weight (kg) *</label>
                                            <div class="input-group">
                                                <input type="text" inputmode="decimal" name="replacement_weight" id="inputReplacementWeight" class="form-control" placeholder="0.00">
                                                <span class="input-group-text">kg</span>
                                            </div>
                                        </div>
                                        <div class="col-lg-2 col-md-3">
                                            <label class="form-label required small fw-bold">Price (₹) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" min="0" step="0.01" name="replacement_price" id="inputReplacementPrice" class="form-control" placeholder="0.00">
                                            </div>
                                        </div>

                                        <div class="col-lg-3 col-md-4">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="form-label mb-0 small fw-bold">HSN Code</label>
                                                <button type="button" class="btn btn-link p-0 text-decoration-none small text-primary fw-semibold btn-add-hsn-trigger" style="font-size: 11px;" title="Add new HSN Code">
                                                    <i class="bi bi-plus-circle me-0.5"></i>+ Add HSN
                                                </button>
                                            </div>
                                            <div class="dropdown hsn-dropdown w-100 position-relative">
                                                <input type="hidden" name="replacement_hsn_code" id="inputReplacementHsn" class="roll-hsn" value="">
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

                                        <div class="col-lg-3 col-md-4">
                                            <label class="form-label small fw-bold">GST</label>
                                            <select name="replacement_gst_type" id="inputReplacementGstType" class="form-select">
                                                <option value="EXEMPTED">Exempted</option>
                                                <option value="SGST">SGST</option>
                                                <option value="CGST">CGST</option>
                                                <option value="IGST">IGST</option>
                                                <option value="BOTH">Both (CGST and SGST)</option>
                                            </select>
                                        </div>

                                        <div class="col-lg-6 col-md-8">
                                            <label class="form-label small fw-bold">Roll Sample Photo <small class="text-muted fw-normal">(Optional — JPG, PNG, WEBP)</small></label>
                                            <input type="file" name="replacement_photo" id="inputReplacementPhoto" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                            <div id="replacementPhotoPreview" class="small mt-1" style="display:none;">
                                                Current replacement photo: <a id="replacementPhotoPreviewLink" href="#" target="_blank" rel="noopener">View photo</a>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-2">
                                            <div class="alert alert-info border-0 bg-info-subtle p-2.5 rounded-3 mb-0 small text-secondary">
                                                <i class="bi bi-info-circle me-1 text-primary"></i>
                                                This replacement roll will be automatically registered in <strong>Supplier Active Rolls</strong> and added to the <strong>Purchase Module</strong> with dynamic Roll QR barcodes generated.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resolution Notes (Optional) -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Resolution Notes &amp; Supplier Remarks <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <textarea name="resolution_notes" id="inputResolutionNotes" class="form-control" rows="2" placeholder="e.g. Supplier agreed to issue debit note in next invoice / replacement roll delivered on..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save Resolution
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Quick Add HSN Code -->
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
                        <input type="number" step="0.01" min="0" max="28" id="quickHsnGstInput" class="form-control fw-bold" value="5.00" required>
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

<script>
let loadedEditRollsMap = {};
let damageScannedLabels = [];
let damageScanInProgress = false;
let editDamageRollScanInProgress = false;
let editDamageRollScanRequestId = 0;
let activeDamageReasonEditor = null;
const damageReasonOptions = <?= json_encode(array_values(array_column($damageReasons, 'damage_reason')), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

function setDamageRollScanStatus(message, isError = false) {
    const status = document.getElementById('damageRollScanStatus');
    if (!status) return;
    status.textContent = message;
    status.className = `small mt-1 ${isError ? 'text-danger' : 'text-success'}`;
}

function clearDamageRollSelection() {
    damageScannedLabels = [];
    renderDamageScannedLabels();
    document.getElementById('inputDamagedWeight').value = '';
    document.getElementById('inputEstimatedLoss').value = '';
}

function renderDamageScannedLabels() {
    const list = document.getElementById('damageScannedLabels');
    const inputs = document.getElementById('damageQrInputs');
    if (!list || !inputs) return;

    if (activeDamageReasonEditor && list.contains(activeDamageReasonEditor.container)) {
        closeDamageReasonEditor();
    }
    list.replaceChildren();
    inputs.replaceChildren();
    const count = document.getElementById('damageScannedCount');
    if (count) count.textContent = `${damageScannedLabels.length} ${damageScannedLabels.length === 1 ? 'item' : 'items'}`;

    if (damageScannedLabels.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'list-group-item text-muted';
        empty.textContent = 'No labels scanned yet.';
        list.appendChild(empty);
    }

    let totalWeight = 0;
    let totalLoss = 0;
    damageScannedLabels.forEach((item, index) => {
        totalWeight += item.weight;
        totalLoss += item.loss;

        const row = document.createElement('div');
        row.className = 'list-group-item';
        const header = document.createElement('div');
        header.className = 'd-flex justify-content-between align-items-start gap-2';
        const details = document.createElement('div');
        details.className = 'text-break flex-grow-1';
        const code = document.createElement('strong');
        code.textContent = item.code;
        const metadata = document.createElement('div');
        metadata.className = 'text-muted small mt-0.5';

        const isProd = (item.itemType === 'PRODUCT');
        const isSupply = (item.damageKind === 'SUPPLY');
        const typeBadge = document.createElement('span');
        typeBadge.className = `badge ${isSupply ? 'bg-info-subtle text-info' : (isProd ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary')} border me-1`;
        typeBadge.textContent = isSupply ? 'Supply' : (isProd ? 'Product' : 'Roll');
        metadata.append(typeBadge, document.createTextNode(
            isSupply
                ? `${item.sellerName} • ${item.rollNumber} • ${item.quantityLabel} • ₹${item.loss.toFixed(2)}`
                : `${item.sellerName} • ${item.rollNumber} • ${item.weight.toFixed(2)} kg • ₹${item.loss.toFixed(2)}`
        ));
        details.append(code, metadata);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-outline-danger flex-shrink-0';
        remove.textContent = 'Remove';
        remove.setAttribute('aria-label', `Remove ${item.code}`);
        remove.addEventListener('click', () => {
            damageScannedLabels.splice(index, 1);
            renderDamageScannedLabels();
        });
        header.append(details, remove);
        row.appendChild(header);

        const fields = document.createElement('div');
        fields.className = 'row g-2 mt-1';

        const reasonColumn = document.createElement('div');
        reasonColumn.className = 'col-md-6';
        const reasonLabel = document.createElement('label');
        reasonLabel.className = 'form-label small fw-semibold mb-1';
        reasonLabel.htmlFor = `damageReason_${index}`;
        reasonLabel.textContent = 'Damage / Defect Reason *';
        const reasonControls = document.createElement('div');
        reasonControls.className = 'd-flex gap-1';
        const reasonSelect = document.createElement('select');
        reasonSelect.id = `damageReason_${index}`;
        reasonSelect.name = `damage_reasons[${index}]`;
        reasonSelect.className = 'form-select form-select-sm';
        reasonSelect.required = true;
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- Select Reason --';
        reasonSelect.appendChild(placeholder);
        damageReasonOptions.forEach(reason => {
            const option = document.createElement('option');
            option.value = reason;
            option.textContent = reason;
            reasonSelect.appendChild(option);
        });
        reasonSelect.value = item.damageReason || '';
        reasonSelect.addEventListener('change', () => {
            item.damageReason = reasonSelect.value;
        });
        const addReasonButton = document.createElement('button');
        addReasonButton.type = 'button';
        addReasonButton.className = 'btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0';
        addReasonButton.style.width = '32px';
        addReasonButton.style.height = '32px';
        addReasonButton.title = 'Add damage reason';
        addReasonButton.setAttribute('aria-label', 'Add damage reason');
        addReasonButton.innerHTML = '<i class="bi bi-plus" aria-hidden="true"></i>';
        addReasonButton.addEventListener('click', () => addDamageReason(reasonSelect.id));
        reasonControls.append(reasonSelect, addReasonButton);
        reasonColumn.append(reasonLabel, reasonControls);

        const photoColumn = document.createElement('div');
        photoColumn.className = 'col-md-6';
        const photoLabel = document.createElement('label');
        photoLabel.className = 'form-label small fw-semibold mb-1';
        photoLabel.htmlFor = `damagePhoto_${index}`;
        photoLabel.textContent = 'Defect Photo / Evidence *';
        if (!item.photoInput) {
            item.photoInput = document.createElement('input');
            item.photoInput.type = 'file';
            item.photoInput.accept = 'image/*,application/pdf';
            item.photoInput.className = 'form-control form-control-sm';
            item.photoInput.required = true;
        }
        item.photoInput.id = `damagePhoto_${index}`;
        item.photoInput.name = `damage_photos[${index}]`;
        photoColumn.append(photoLabel, item.photoInput);
        fields.append(reasonColumn, photoColumn);
        row.appendChild(fields);
        list.appendChild(row);

        const hiddenCode = document.createElement('input');
        hiddenCode.type = 'hidden';
        hiddenCode.name = `qr_codes[${index}]`;
        hiddenCode.value = item.code;
        inputs.appendChild(hiddenCode);

        const hiddenSupplyItemId = document.createElement('input');
        hiddenSupplyItemId.type = 'hidden';
        hiddenSupplyItemId.name = `damage_supply_item_ids[${index}]`;
        hiddenSupplyItemId.value = item.supplyItemId || '';
        inputs.appendChild(hiddenSupplyItemId);
    });

    document.getElementById('inputDamagedWeight').value = totalWeight.toFixed(2);
    document.getElementById('inputEstimatedLoss').value = totalLoss.toFixed(2);
    const weightHelp = document.getElementById('reportWeightHelp');
    if (weightHelp) weightHelp.textContent = `${damageScannedLabels.length} scanned item(s)`;
    const rateBadge = document.getElementById('reportRateBadge');
    if (rateBadge) rateBadge.textContent = totalWeight > 0 ? `₹${(totalLoss / totalWeight).toFixed(2)} / kg` : '₹0.00 / kg';
    const lossHelp = document.getElementById('reportLossCalcHelp');
    if (lossHelp) lossHelp.textContent = `Estimated loss for ${damageScannedLabels.length} scanned item(s): ₹${totalLoss.toFixed(2)}`;
}

function scanDamageRollQr() {
    const scanInput = document.getElementById('damageRollQrScan');
    const scannedCode = scanInput ? scanInput.value.trim() : '';
    if (!scannedCode || damageScanInProgress) return;

    const normalizedCode = scannedCode.split('|', 1)[0].trim().toUpperCase().replace(/^#/, '');
    if (damageScannedLabels.some(item => item.code === normalizedCode)) {
        setDamageRollScanStatus(`QR label ${normalizedCode} is already in this damage list.`, true);
        if (scanInput) scanInput.select();
        return;
    }

    damageScanInProgress = true;
    setDamageRollScanStatus('Looking up item QR (roll or garment)…');
    fetch('<?= url('api/production/damages/scan-roll') ?>?code=' + encodeURIComponent(scannedCode))
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to find this item.');
            }
            return data;
        })
        .then(async data => {
            if (data.needs_supply_selection) {
                const candidate = (data.candidates || [])[0];
                if (!candidate) {
                    throw new Error('No matching purchase supply was found for this label.');
                }

                setDamageRollScanStatus('Verifying matching purchase supply…');
                const query = new URLSearchParams({code: data.code, supply_item_id: candidate.id});
                const response = await fetch('<?= url('api/production/damages/scan-roll') ?>?' + query.toString());
                const supplyData = await response.json();
                if (!response.ok || !supplyData.success || supplyData.needs_supply_selection) {
                    throw new Error(supplyData.message || 'The matching purchase supply could not be verified.');
                }
                addScannedDamageItem(supplyData, scanInput);
                return;
            }
            addScannedDamageItem(data, scanInput);
        })
        .catch(error => {
            setDamageRollScanStatus(error.message || 'Unable to scan this item.', true);
        })
        .finally(() => {
            damageScanInProgress = false;
        });
}

function addScannedDamageItem(data, scanInput) {
    const isProduct = data.item_type === 'PRODUCT';
    const pieceWeight = parseFloat(data.piece_weight !== undefined ? data.piece_weight : data.weight) || 0;
    const loss = parseFloat(data.piece_loss !== undefined ? data.piece_loss : (data.loss !== undefined ? data.loss : data.price)) || 0;

    damageScannedLabels.push({
        code: data.code,
        itemType: data.item_type || 'FABRIC_ROLL',
        damageKind: data.damage_kind || null,
        sellerName: data.seller_name || 'Own Production',
        rollNumber: data.roll_number,
        quantityLabel: data.quantity_label || '',
        supplyItemId: data.supply_item_id || null,
        weight: pieceWeight,
        loss: loss,
        damageReason: ''
    });
    renderDamageScannedLabels();
    const badgeType = data.damage_kind === 'SUPPLY' ? 'Other Garment Supply' : (isProduct ? 'Finished Product' : 'Fabric Roll');
    setDamageRollScanStatus(`Added [${badgeType}] ${data.code} — ${data.roll_number}. Scan another label or submit.`);
    if (scanInput) {
        scanInput.value = '';
        scanInput.focus();
    }
}

function getRollMetrics(r) {
    const totQty = parseInt(r.quantity) || 1;
    const totWeight = parseFloat(r.weight) || 0;
    const totPrice = parseFloat(r.price) || 0;
    const availQty = r.available_quantity !== undefined ? Math.max(0, parseInt(r.available_quantity, 10) || 0) : totQty;
    const availWeight = r.available_weight !== undefined ? Math.max(0, parseFloat(r.available_weight) || 0) : totWeight;
    
    const weightPerPc = totQty > 0 ? (totWeight / totQty) : totWeight;
    const ratePerKg = totWeight > 0 ? (totPrice / totWeight) : 0;
    
    return {
        totQty,
        totWeight,
        availQty,
        availWeight,
        usedQty: parseInt(r.used_quantity, 10) || 0,
        weightPerPc,
        ratePerKg
    };
}

function updateEditCalculations(m, weight) {
    if (!m) return;
    const lossVal = weight * m.ratePerKg;
    
    const lossInput = document.getElementById('editEstimatedLoss');
    if (lossInput) lossInput.value = lossVal.toFixed(2);

    const rateBadge = document.getElementById('editRateBadge');
    if (rateBadge) rateBadge.textContent = `₹${m.ratePerKg.toFixed(2)} / kg`;

    const weightHelp = document.getElementById('editWeightHelp');
    if (weightHelp) weightHelp.textContent = `Total: ${weight.toFixed(2)} kg`;

    const lossHelp = document.getElementById('editLossCalcHelp');
    if (lossHelp) lossHelp.textContent = `Valuation: ₹${lossVal.toFixed(2)} (${weight.toFixed(2)} kg @ ₹${m.ratePerKg.toFixed(2)}/kg)`;

    const sellerSelect = document.getElementById('editSellerSelect');
    const sellerOption = sellerSelect.options[sellerSelect.selectedIndex];
    const roll = loadedEditRollsMap[document.getElementById('editRollSelect').value];
    updateEditDamageLabelMeta(sellerOption ? sellerOption.textContent.trim() : '', roll ? roll.roll_number : '');
}

function updateEditDamageLabelMeta(sellerName, rollNumber) {
    const meta = document.getElementById('editDamageLabelMeta');
    if (!meta) return;

    const weight = parseFloat(document.getElementById('editDamagedWeight').value) || 0;
    const loss = parseFloat(document.getElementById('editEstimatedLoss').value) || 0;
    const details = [];
    if (sellerName) details.push(sellerName);
    if (rollNumber) details.push(`Roll ${rollNumber}`);
    if (weight > 0) details.push(`${weight.toFixed(2)} kg`);
    if (loss > 0) details.push(`₹${loss.toFixed(2)}`);
    meta.textContent = details.join(' • ');
}

function openEditDamageModal(damageData) {
    editDamageRollScanRequestId++;
    editDamageRollScanInProgress = false;
    const editForm = document.getElementById('editDamageForm');
    const isSupplyDamage = Boolean(
        (damageData.qr_code && /^SP\d+/i.test(String(damageData.qr_code).trim())) ||
        (damageData.item_type === 'PRODUCT' && !damageData.seller_roll_id && !damageData.product_id && !damageData.variant_id) ||
        (damageData.item_type === 'SUPPLY')
    );
    editForm.action = '<?= url('production/damages/update') ?>/' + damageData.id;
    editForm.dataset.damageId = String(damageData.id);
    editForm.dataset.originalRollId = String(damageData.seller_roll_id || '');
    editForm.dataset.qrLinked = damageData.qr_code ? '1' : '0';
    editForm.dataset.supplyDamage = isSupplyDamage ? '1' : '0';

    // Show/hide roll scanner and weight based on whether it is a supply
    const scannerEl = document.getElementById('editDamageRollScanner');
    if (scannerEl) scannerEl.classList.toggle('d-none', isSupplyDamage);

    const weightEl = document.getElementById('editDamageWeightSummary');
    if (weightEl) weightEl.classList.toggle('d-none', isSupplyDamage);

    const lossSummary = document.getElementById('editDamageLossSummary');
    if (lossSummary) {
        lossSummary.classList.toggle('col-md-6', !isSupplyDamage);
        lossSummary.classList.toggle('col-12', isSupplyDamage);
    }

    const lossLabel = document.getElementById('editDamageLossLabel');
    if (lossLabel) {
        lossLabel.textContent = isSupplyDamage ? 'Estimated Supply Loss Amount' : 'Estimated Loss Valuation';
    }

    const scanInput = document.getElementById('editDamageRollQrScan');
    if (scanInput) {
        scanInput.value = '';
        scanInput.disabled = isSupplyDamage;
    }

    setEditDamageRollScanStatus(
        isSupplyDamage
            ? 'Purchased supply damage record. Its purchase valuation is preserved when editing.'
            : (damageData.qr_code
                ? 'This QR-linked damage record must remain assigned to its original roll.'
                : 'Scan a roll QR to select the roll for this existing damage record.')
    );

    document.getElementById('editDamageNo').textContent = damageData.damage_number;
    document.getElementById('editDamageStatus').textContent = damageData.status;

    document.getElementById('editSellerSelect').value = damageData.seller_id || '';
    document.getElementById('editReasonSelect').value = damageData.damage_reason || '';
    document.getElementById('editDamagedQty').value = damageData.damaged_quantity || 1;
    document.getElementById('editDamagedWeight').value = parseFloat(damageData.damaged_weight || 0).toFixed(2);
    
    const savedLoss = parseFloat(damageData.loss_amount || 0).toFixed(2);
    document.getElementById('editEstimatedLoss').value = savedLoss;
    document.getElementById('editDefectNotes').value = damageData.defect_notes || '';
    document.getElementById('editDamageLabelCode').textContent =
        damageData.qr_code || damageData.roll_number || damageData.damage_number || 'Existing damage item';

    const rateBadge = document.getElementById('editRateBadge');
    const lossHelp = document.getElementById('editLossCalcHelp');
    const weightHelp = document.getElementById('editWeightHelp');

    if (isSupplyDamage) {
        if (rateBadge) rateBadge.textContent = 'Purchase cost';
        if (weightHelp) weightHelp.textContent = 'Purchased supply has no weight recorded';
        if (lossHelp) lossHelp.textContent = `Purchase valuation: ₹${savedLoss}`;
        const meta = document.getElementById('editDamageLabelMeta');
        if (meta) {
            meta.textContent = [damageData.variant_info, damageData.roll_number, '₹' + savedLoss].filter(Boolean).join(' • ');
        }
    } else {
        if (rateBadge) rateBadge.textContent = '₹0.00 / kg';
        if (weightHelp) weightHelp.textContent = 'Rate: ₹0.00 / kg';
        if (lossHelp) lossHelp.textContent = 'Calculated from roll rate';
        updateEditDamageLabelMeta(damageData.seller_name, damageData.roll_number);
    }

    // Photo preview
    const photoBox = document.getElementById('editCurrentPhotoBox');
    const photoLink = document.getElementById('editCurrentPhotoLink');
    const photoImg = document.getElementById('editCurrentPhotoImg');
    const photoInput = document.getElementById('editPhotoInput');
    const photoReqStar = document.getElementById('editPhotoReqStar');

    if (damageData.photo_url) {
        photoBox.style.display = '';
        const url = damageData.photo_url.startsWith('http') ? damageData.photo_url : ('<?= uploadUrl('') ?>/' + damageData.photo_url);
        photoLink.href = url;
        photoImg.src = url;
        if (photoInput) photoInput.removeAttribute('required');
        if (photoReqStar) photoReqStar.style.display = 'none';
    } else {
        photoBox.style.display = 'none';
        if (photoInput) photoInput.setAttribute('required', 'required');
        if (photoReqStar) photoReqStar.style.display = '';
    }

    // IMPORTANT: Only load supplier rolls if it is a FABRIC ROLL (never for supplies!)
    if (!isSupplyDamage && damageData.seller_id) {
        if (scanInput) scanInput.disabled = true;
        loadSupplierRollsForEdit(damageData.seller_id, damageData.seller_roll_id, damageData.id)
            .finally(() => {
                if (scanInput) {
                    scanInput.disabled = false;
                    if (document.getElementById('editDamageModal').classList.contains('show')) scanInput.focus();
                }
            });
    }

    const modal = new bootstrap.Modal(document.getElementById('editDamageModal'));
    modal.show();
}

function setEditDamageRollScanStatus(message, isError = false) {
    const status = document.getElementById('editDamageRollScanStatus');
    if (!status) return;
    status.textContent = message;
    status.className = `small mt-1 ${isError ? 'text-danger' : 'text-success'}`;
}

async function scanEditDamageRollQr() {
    const editForm = document.getElementById('editDamageForm');
    if (editForm && editForm.dataset.supplyDamage === '1') {
        setEditDamageRollScanStatus('This is a purchased supply record; roll scanning is not applicable.', true);
        return;
    }
    const scanInput = document.getElementById('editDamageRollQrScan');
    const scannedCode = scanInput ? scanInput.value.trim() : '';
    const editForm = document.getElementById('editDamageForm');
    const damageId = parseInt(editForm.dataset.damageId, 10) || 0;
    if (!scannedCode || editDamageRollScanInProgress) return;
    if (!damageId) {
        setEditDamageRollScanStatus('Open an existing damage record before scanning a roll.', true);
        return;
    }

    editDamageRollScanInProgress = true;
    const requestId = ++editDamageRollScanRequestId;
    const originalRollId = Number(editForm.dataset.originalRollId) || 0;
    const qrLinked = editForm.dataset.qrLinked === '1';
    if (scanInput) scanInput.disabled = true;
    setEditDamageRollScanStatus('Looking up fabric roll QR…');
    try {
        const scanUrl = '<?= url('api/production/damages/scan-roll-edit') ?>'
            + '?code=' + encodeURIComponent(scannedCode)
            + '&damage_id=' + encodeURIComponent(damageId);
        const response = await fetch(scanUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json();
        if (requestId !== editDamageRollScanRequestId) return;
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to find this fabric roll.');
        }
        if (result.item_type !== 'FABRIC_ROLL' || !result.roll_id || !result.seller_id) {
            throw new Error('This edit form can only select a fabric roll. Please scan a fabric roll QR label.');
        }

        const sellerSelect = document.getElementById('editSellerSelect');
        const rollSelect = document.getElementById('editRollSelect');
        const targetRollId = Number(result.roll_id);
        if (qrLinked && originalRollId !== targetRollId) {
            throw new Error('This QR-linked damage record cannot be moved to a different roll. Scan a QR from its original roll.');
        }
        if (!Array.from(sellerSelect.options).some(option => option.value === String(result.seller_id))) {
            throw new Error('The supplier for this fabric roll is not available in the edit form.');
        }

        const currentRollId = Number(rollSelect.value || originalRollId);
        const resetValues = currentRollId !== targetRollId;
        const rollData = await fetchSupplierRollsForEdit(result.seller_id, damageId);
        if (requestId !== editDamageRollScanRequestId) return;
        const selectedRoll = rollData && Array.isArray(rollData.rolls)
            ? rollData.rolls.find(roll => Number(roll.id) === targetRollId)
            : null;
        if (!selectedRoll || Number(selectedRoll.available_quantity) < 1) {
            throw new Error('This roll has no remaining damageable quantity.');
        }

        sellerSelect.value = String(result.seller_id);
        populateSupplierRollsForEdit(rollData, targetRollId, resetValues);

        if (scanInput) scanInput.value = '';
        setEditDamageRollScanStatus(`Selected ${result.roll_number} for this existing damage record.`);
    } catch (error) {
        if (requestId === editDamageRollScanRequestId) {
            setEditDamageRollScanStatus(error.message || 'Unable to select this fabric roll.', true);
        }
    } finally {
        if (requestId === editDamageRollScanRequestId) {
            editDamageRollScanInProgress = false;
        }
        if (scanInput && requestId === editDamageRollScanRequestId) {
            scanInput.disabled = false;
            scanInput.focus();
        }
    }
}

function fetchSupplierRollsForEdit(sellerId, excludeDamageId = 0) {
    return fetch('<?= url('api/sellers') ?>/' + sellerId + '/rolls?exclude_damage_id=' + encodeURIComponent(excludeDamageId))
        .then(response => {
            if (!response.ok) throw new Error('Unable to load rolls for this supplier.');
            return response.json();
        });
}

function populateSupplierRollsForEdit(data, selectedRollId = null, resetValues = false) {
    const rollSelect = document.getElementById('editRollSelect');
    rollSelect.innerHTML = '<option value="">-- Choose Fabric Roll --</option>';
    loadedEditRollsMap = {};

    if (Array.isArray(data.rolls) && data.rolls.length > 0) {
        data.rolls.forEach(r => {
            loadedEditRollsMap[r.id] = r;
            const opt = document.createElement('option');
            opt.value = r.id;
            const availWt = parseFloat(r.available_weight) || 0;
            const availQty = parseInt(r.available_quantity, 10) || 0;
            const usedQty = parseInt(r.used_quantity, 10) || 0;
            opt.textContent = `${r.roll_number} (${r.color || 'No Color'} - ${r.design_name || 'No Design'}) • Damageable: ${availQty} pcs / ${availWt.toFixed(2)} kg${usedQty > 0 ? ` • ${usedQty} pcs assigned` : ''}`;
            if (selectedRollId && Number(selectedRollId) === Number(r.id)) opt.selected = true;
            rollSelect.appendChild(opt);
        });

        if (selectedRollId && rollSelect.value) {
            handleEditRollSelectionChange(rollSelect, resetValues);
        }
    } else {
        rollSelect.innerHTML = '<option value="">No rolls found for this supplier</option>';
    }
}

function loadSupplierRollsForEdit(sellerId, selectedRollId = null, excludeDamageId = 0, resetValues = false) {
    const rollSelect = document.getElementById('editRollSelect');
    if (!sellerId) {
        rollSelect.innerHTML = '<option value="">-- Select Supplier First --</option>';
        const scanInput = document.getElementById('editDamageRollQrScan');
        if (scanInput) scanInput.disabled = false;
        return Promise.resolve(null);
    }

    const scanInput = document.getElementById('editDamageRollQrScan');
    if (scanInput) scanInput.disabled = true;
    rollSelect.innerHTML = '<option value="">Loading rolls...</option>';

    return fetchSupplierRollsForEdit(sellerId, excludeDamageId)
        .then(data => {
            populateSupplierRollsForEdit(data, selectedRollId, resetValues);
            return data;
        })
        .catch(err => {
            console.error('Error fetching edit rolls:', err);
            rollSelect.innerHTML = '<option value="">Failed to load rolls</option>';
            return null;
        })
        .finally(() => {
            if (scanInput) scanInput.disabled = false;
        });
}

function handleEditRollSelectionChange(selectEl, resetValues = true) {
    const rollId = selectEl.value;
    if (!rollId || !loadedEditRollsMap[rollId]) return;

    const r = loadedEditRollsMap[rollId];
    const m = getRollMetrics(r);

    const qtyInput = document.getElementById('editDamagedQty');
    if (qtyInput) {
        qtyInput.max = m.availQty;
        qtyInput.disabled = m.availQty < 1;
    }

    if (resetValues) {
        const defaultQty = parseInt(qtyInput.value, 10) || 1;
        const defaultWeight = defaultQty * m.weightPerPc;
        if (qtyInput) qtyInput.value = defaultQty;
        document.getElementById('editDamagedWeight').value = defaultWeight.toFixed(2);
        updateEditCalculations(m, defaultWeight);
    } else {
        const curQty = parseInt(document.getElementById('editDamagedQty').value) || 1;
        const curWeight = parseFloat(document.getElementById('editDamagedWeight').value) || (curQty * m.weightPerPc);
        updateEditCalculations(m, curWeight);
    }

}

function openResolveModal(damageData) {
    document.getElementById('resolveDamageForm').action = '<?= url('production/damages/resolve') ?>/' + damageData.id;
    document.getElementById('resDamageNo').textContent = damageData.damage_number;
    document.getElementById('resSellerName').textContent = damageData.seller_name;
    document.getElementById('resRollInfo').textContent = `${damageData.roll_number} (${damageData.damaged_weight} kg)`;
    document.getElementById('resReason').textContent = damageData.damage_reason;
    document.getElementById('resLossAmount').textContent = '₹ ' + parseFloat(damageData.loss_amount).toFixed(2);

    document.getElementById('resStatusSelect').value = damageData.status || 'LOGGED';
    document.getElementById('resTypeSelect').value = (damageData.resolution_type && damageData.resolution_type !== 'NONE') ? damageData.resolution_type : '';
    document.getElementById('inputRefundAmount').value = damageData.refund_amount > 0 ? damageData.refund_amount : damageData.loss_amount;
    document.getElementById('inputDebitNote').value = damageData.debit_note_number || '';

    const sourceWeight = Number(damageData.initial_roll_weight) || Number(damageData.damaged_weight) || 0;
    const sourcePrice = Number(damageData.initial_roll_price) || Number(damageData.loss_amount) || 0;
    document.getElementById('inputReplacementRoll').value =
        damageData.replacement_roll_code || damageData.replacement_roll_number ||
        (damageData.roll_number ? `${damageData.roll_number}-REPL` : 'ROLL-REPL');
    document.getElementById('inputReplacementColor').value =
        damageData.replacement_roll_color || damageData.roll_color || 'STANDARD';
    document.getElementById('inputReplacementDesign').value =
        damageData.replacement_roll_design || damageData.roll_design || 'PLAIN';
    document.getElementById('inputReplacementDesignCount').value =
        damageData.replacement_roll_design_count || damageData.roll_design_count || '1';
    document.getElementById('inputReplacementQuantity').value =
        damageData.replacement_roll_quantity || damageData.initial_roll_quantity || damageData.damaged_quantity || '1';
    const replacementWeight = Number(damageData.replacement_roll_weight) || sourceWeight;
    const hasSavedReplacementPrice = damageData.replacement_roll_price !== null &&
        damageData.replacement_roll_price !== undefined && String(damageData.replacement_roll_price) !== '';
    const replacementPrice = hasSavedReplacementPrice
        ? Number(damageData.replacement_roll_price)
        : sourcePrice;
    document.getElementById('inputReplacementWeight').value = replacementWeight > 0 ? replacementWeight.toFixed(2) : '';
    document.getElementById('inputReplacementPrice').value =
        Number.isFinite(replacementPrice) && replacementPrice >= 0 ? replacementPrice.toFixed(2) : '';

    const hsnHidden = document.getElementById('inputReplacementHsn');
    if (hsnHidden) {
        hsnHidden.value = damageData.replacement_roll_hsn_code || damageData.roll_hsn_code ||
            damageData.source_roll_purchase_hsn_code || '';
        const btnText = document.querySelector('#fieldReplacementBox .hsn-btn-text');
        if (btnText) {
            const selectedHsn = (window.hsnCodesList || []).find(hsn =>
                String(hsn.hsn_code).toUpperCase() === String(hsnHidden.value).toUpperCase()
            );
            btnText.textContent = selectedHsn
                ? `${selectedHsn.hsn_code}${selectedHsn.description ? ` (${selectedHsn.description})` : ''}`
                : (hsnHidden.value || '-- Select HSN --');
        }
    }
    const gstSelect = document.getElementById('inputReplacementGstType');
    const savedGstType = damageData.replacement_gst_type || damageData.source_roll_gst_type || 'EXEMPTED';
    gstSelect.value = Array.from(gstSelect.options).some(option => option.value === savedGstType)
        ? savedGstType
        : 'EXEMPTED';
    const replPhotoInp = document.getElementById('inputReplacementPhoto');
    if (replPhotoInp) replPhotoInp.value = '';
    const replPhotoPreview = document.getElementById('replacementPhotoPreview');
    const replPhotoLink = document.getElementById('replacementPhotoPreviewLink');
    if (replPhotoPreview && replPhotoLink && damageData.replacement_roll_photo_url) {
        const photoUrl = damageData.replacement_roll_photo_url.startsWith('http')
            ? damageData.replacement_roll_photo_url
            : ('<?= uploadUrl('') ?>/' + damageData.replacement_roll_photo_url);
        replPhotoLink.href = photoUrl;
        replPhotoPreview.style.display = '';
    } else if (replPhotoPreview) {
        replPhotoPreview.style.display = 'none';
    }
    document.getElementById('inputResolutionNotes').value = damageData.resolution_notes || '';

    handleResolutionStatusChange(document.getElementById('resStatusSelect').value);
    handleResolutionTypeChange(document.getElementById('resTypeSelect').value);

    const modal = new bootstrap.Modal(document.getElementById('resolveModal'));
    modal.show();
}

function handleResolutionStatusChange(status) {
    const resType = document.getElementById('resTypeSelect');
    if (status === 'REFUND_RECEIVED') {
        resType.value = 'SUPPLIER_REFUND';
    } else if (status === 'REPLACEMENT_RECEIVED') {
        resType.value = 'SUPPLIER_REPLACEMENT';
    }
    handleResolutionTypeChange(resType.value);
}

function handleResolutionTypeChange(type) {
    const refundFields = document.querySelectorAll('.res-field-refund');
    const replFields = document.querySelectorAll('.res-field-replacement');

    refundFields.forEach(el => el.style.display = (type === 'SUPPLIER_REFUND' || type === 'RATE_CONCESSION') ? '' : 'none');
    replFields.forEach(el => el.style.display = (type === 'SUPPLIER_REPLACEMENT') ? '' : 'none');

    const isRepl = (type === 'SUPPLIER_REPLACEMENT');
    const replInputs = ['inputReplacementRoll', 'inputReplacementColor', 'inputReplacementDesign', 'inputReplacementDesignCount', 'inputReplacementQuantity', 'inputReplacementWeight', 'inputReplacementPrice'];
    replInputs.forEach(id => {
        const inp = document.getElementById(id);
        if (inp) {
            if (isRepl) {
                inp.setAttribute('required', 'required');
            } else {
                inp.removeAttribute('required');
            }
        }
    });
}

window.hsnCodesList = <?= json_encode($hsnCodes ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
let activeHsnTarget = null;

function safeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function initHsnDropdown(container, hiddenSelector) {
    const hiddenInput = container.querySelector(hiddenSelector);
    const dropdownEl = container.querySelector('.hsn-dropdown');
    if (!hiddenInput || !dropdownEl) return;

    const btn = dropdownEl.querySelector('.hsn-dropdown-btn');
    const btnText = dropdownEl.querySelector('.hsn-btn-text');
    const searchInput = dropdownEl.querySelector('.hsn-search-input');
    const optionsList = dropdownEl.querySelector('.hsn-options-list');

    const triggerBtn = container.querySelector('.btn-add-hsn-trigger');
    if (triggerBtn) {
        triggerBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            activeHsnTarget = { hiddenInput, btnText, btn };
            openQuickAddHsnModal(searchInput ? searchInput.value.trim() : '');
        });
    }

    function renderOptions(query = '') {
        const q = query.trim().toUpperCase();
        const matches = (window.hsnCodesList || []).filter(h => {
            if (!q) return true;
            return (h.hsn_code && h.hsn_code.toUpperCase().includes(q)) ||
                   (h.description && h.description.toUpperCase().includes(q));
        });

        let html = '';
        html += `<div class="dropdown-item px-2 py-1.5 cursor-pointer small border-bottom select-hsn-item" data-code="" style="cursor:pointer;">
            <span class="text-muted fst-italic">-- No HSN Code --</span>
        </div>`;

        if (matches.length === 0) {
            html += `<div class="p-2 text-center text-muted small">No matching HSN codes found.</div>`;
        } else {
            matches.forEach(h => {
                const isSelected = String(hiddenInput.value).toUpperCase() === String(h.hsn_code).toUpperCase();
                html += `<div class="dropdown-item px-2 py-1.5 cursor-pointer d-flex justify-content-between align-items-center select-hsn-item ${isSelected ? 'bg-primary-subtle text-primary fw-bold' : ''}" data-code="${safeHtml(h.hsn_code)}" data-desc="${safeHtml(h.description || '')}" data-gst="${h.gst_rate || 0}" style="cursor:pointer; font-size: 0.82rem;">
                    <div>
                        <span class="font-monospace fw-semibold">${safeHtml(h.hsn_code)}</span>
                        ${h.description ? `<small class="d-block text-muted text-truncate" style="max-width: 200px;">${safeHtml(h.description)}</small>` : ''}
                    </div>
                    <span class="badge bg-light text-dark border ms-1 font-monospace" style="font-size:10px;">${parseFloat(h.gst_rate || 0).toFixed(1)}%</span>
                </div>`;
            });
        }

        html += `<div class="p-1.5 border-top bg-light text-center">
            <button type="button" class="btn btn-outline-primary btn-xs w-100 py-1 small fw-semibold btn-add-hsn-inline">
                <i class="bi bi-plus-circle me-1"></i>+ Add New HSN Code
            </button>
        </div>`;

        optionsList.innerHTML = html;

        optionsList.querySelector('.btn-add-hsn-inline')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const bsDropdown = bootstrap.Dropdown.getInstance(btn);
            if (bsDropdown) bsDropdown.hide();
            activeHsnTarget = { hiddenInput, btnText, btn };
            openQuickAddHsnModal(searchInput.value.trim());
        });

        optionsList.querySelectorAll('.select-hsn-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const code = item.dataset.code || '';
                const desc = item.dataset.desc || '';
                hiddenInput.value = code;
                if (!code) {
                    btnText.textContent = '-- Select HSN --';
                } else {
                    btnText.innerHTML = `<span class="fw-bold text-primary">${safeHtml(code)}</span> ${desc ? `<span class="text-muted small">(${safeHtml(desc).slice(0, 16)}${desc.length > 16 ? '...' : ''})</span>` : ''}`;
                }
                const bsDropdown = bootstrap.Dropdown.getInstance(btn);
                if (bsDropdown) bsDropdown.hide();
                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    dropdownEl.addEventListener('shown.bs.dropdown', () => {
        renderOptions(searchInput.value);
        setTimeout(() => searchInput.focus(), 50);
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            renderOptions(searchInput.value);
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstOption = optionsList.querySelector('.select-hsn-item[data-code]:not([data-code=""])');
                if (firstOption) {
                    firstOption.click();
                } else if (searchInput.value.trim()) {
                    const bsDropdown = bootstrap.Dropdown.getInstance(btn);
                    if (bsDropdown) bsDropdown.hide();
                    activeHsnTarget = { hiddenInput, btnText, btn };
                    openQuickAddHsnModal(searchInput.value.trim());
                }
            }
        });
    }

    renderOptions('');
}

function openQuickAddHsnModal(presetCode = '') {
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
        const gst = parseFloat(document.getElementById('quickHsnGstInput')?.value) || 0;
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
                        activeHsnTarget.btnText.innerHTML = `<span class="fw-bold text-primary">${safeHtml(newHsn.hsn_code)}</span> <span class="text-muted small">(${safeHtml(newHsn.description).slice(0, 16)}${newHsn.description.length > 16 ? '...' : ''})</span>`;
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
                alertBox.textContent = 'Server error while saving HSN code.';
                alertBox.classList.remove('d-none');
            }
        });
    });
}

// Client-side form submit validation
document.addEventListener('DOMContentLoaded', () => {
    const replBox = document.getElementById('fieldReplacementBox');
    if (replBox) {
        initHsnDropdown(replBox, '#inputReplacementHsn');
    }
    initQuickAddHsnModalHandler();

    const editRollScanInput = document.getElementById('editDamageRollQrScan');
    if (editRollScanInput) {
        const editModal = document.getElementById('editDamageModal');
        editModal.addEventListener('shown.bs.modal', () => {
            if (document.getElementById('editDamageForm')?.dataset.supplyDamage !== '1') {
                editRollScanInput.focus();
            }
        });
        editModal.addEventListener('hidden.bs.modal', () => {
            editDamageRollScanRequestId++;
            editDamageRollScanInProgress = false;
            editRollScanInput.disabled = false;
            editRollScanInput.value = '';
            setEditDamageRollScanStatus('');
        });
        editRollScanInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                scanEditDamageRollQr();
            }
        });
        editRollScanInput.addEventListener('input', () => {
            if (!editDamageRollScanInProgress) setEditDamageRollScanStatus('');
        });
    }

    const scanInput = document.getElementById('damageRollQrScan');
    if (scanInput) {
        const damageModal = document.getElementById('reportDamageModal');
        damageModal.addEventListener('shown.bs.modal', () => scanInput.focus());
        damageModal.addEventListener('hidden.bs.modal', () => {
            closeDamageReasonEditor();
            clearDamageRollSelection();
            scanInput.value = '';
            setDamageRollScanStatus('');
        });
        scanInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                scanDamageRollQr();
            }
        });
        scanInput.addEventListener('input', () => {
            if (!damageScanInProgress) setDamageRollScanStatus('');
        });
    }

    const resolveForm = document.getElementById('resolveDamageForm');
    if (resolveForm) {
        resolveForm.addEventListener('submit', function(e) {
            const status = document.getElementById('resStatusSelect').value.trim();
            const resType = document.getElementById('resTypeSelect').value.trim();

            if (!status) {
                alert('Update Damage Status is required. Please select a status.');
                e.preventDefault();
                return false;
            }
            if (!resType || resType === 'NONE') {
                alert('Resolution Method is required. Please select a resolution method.');
                e.preventDefault();
                return false;
            }
        });
    }

    const reportForm = document.getElementById('reportDamageForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            if (damageScannedLabels.length === 0) {
                alert('Scan at least one valid fabric QR label first.');
                e.preventDefault();
                return false;
            }
            if (damageScanInProgress) {
                alert('Please wait for the current QR scan to finish.');
                e.preventDefault();
                return false;
            }
            for (const item of damageScannedLabels) {
                if (!item.damageReason || !item.damageReason.trim()) {
                    alert(`Please select a Damage / Defect Reason for QR label ${item.code}.`);
                    e.preventDefault();
                    return false;
                }
                if (!item.photoInput || !item.photoInput.files || item.photoInput.files.length === 0) {
                    alert(`Please attach a photo / evidence file for QR label ${item.code}.`);
                    e.preventDefault();
                    return false;
                }
            }
        });
    }

    const editForm = document.getElementById('editDamageForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const sellerId = document.getElementById('editSellerSelect').value;
            const rollId = document.getElementById('editRollSelect').value;
            const reason = document.getElementById('editReasonSelect').value.trim();
            const qty = parseInt(document.getElementById('editDamagedQty').value) || 0;
            const photoInput = document.getElementById('editPhotoInput');

            if (!sellerId) {
                alert('Please choose a Material Supplier / Mill.');
                e.preventDefault();
                return false;
            }
            if (!rollId) {
                alert('Please select a Defective Fabric Roll.');
                e.preventDefault();
                return false;
            }
            if (rollId && loadedEditRollsMap[rollId]) {
                const m = getRollMetrics(loadedEditRollsMap[rollId]);
                const maxQty = m.availQty;
                if (qty > maxQty) {
                    alert(`Damaged Quantity (${qty} pcs) cannot exceed the available roll quantity (${maxQty} pcs).`);
                    e.preventDefault();
                    return false;
                }
                if (qty < 1) {
                    alert('Damaged Quantity must be at least 1 pc.');
                    e.preventDefault();
                    return false;
                }
            }
            if (!reason) {
                alert('Please enter a Damage / Defect Reason.');
                e.preventDefault();
                return false;
            }
            if (photoInput && photoInput.hasAttribute('required') && (!photoInput.files || photoInput.files.length === 0)) {
                alert('Defect Photo / Evidence is required. Please attach a photo.');
                e.preventDefault();
                return false;
            }
        });
    }

    // Deep-linking from Supplier view
    const urlParams = new URLSearchParams(window.location.search);
    const reportSellerId = urlParams.get('report_seller_id');
    if (reportSellerId) {
        const modal = new bootstrap.Modal(document.getElementById('reportDamageModal'));
        modal.show();
        setDamageRollScanStatus('Scan the specific fabric QR label to report damage.');
    }

    // Initialize Damage Records Table Pagination
    if (typeof window.initClientPagination === 'function' && document.getElementById('damagesTable')) {
        const damagesPagination = window.initClientPagination({
            container: '#damagesTable tbody',
            itemSelector: 'tr.damage-row',
            footer: '#damagesPaginationFooter',
            infoEl: '#damagesPageInfo',
            paginationList: '#damagesPaginationList',
            pageSizeSelect: '#damagesPageSizeSelect',
            pageSize: 10,
            itemName: 'records'
        });

        const searchInput = document.getElementById('damageSearchInput');
        if (searchInput && damagesPagination) {
            let debounceTimer;
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const q = searchInput.value.trim().toLowerCase();
                    damagesPagination.filter(row => {
                        if (!q) return true;
                        const text = (row.textContent || '').toLowerCase();
                        return text.includes(q);
                    });
                }, 150);
            });
        }
    }
});

function addDamageReason(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;

    if (activeDamageReasonEditor && activeDamageReasonEditor.select === select) {
        closeDamageReasonEditor();
        return;
    }
    closeDamageReasonEditor();

    const editor = document.createElement('div');
    editor.className = 'mt-2 p-2 border rounded bg-light';
    const label = document.createElement('label');
    label.className = 'form-label small fw-semibold mb-1';
    label.textContent = 'New Damage / Defect Reason';
    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 255;
    input.className = 'form-control form-control-sm';
    input.placeholder = 'Enter a damage reason';
    const actions = document.createElement('div');
    actions.className = 'd-flex justify-content-end gap-2 mt-2';
    const cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'btn btn-sm btn-outline-secondary';
    cancelButton.textContent = 'Cancel';
    cancelButton.addEventListener('click', () => closeDamageReasonEditor());
    const saveButton = document.createElement('button');
    saveButton.type = 'button';
    saveButton.className = 'btn btn-sm btn-primary';
    saveButton.textContent = 'Save Reason';
    saveButton.addEventListener('click', () => saveDamageReason());
    const status = document.createElement('div');
    status.className = 'small mt-1 d-none';
    status.setAttribute('role', 'status');
    actions.append(cancelButton, saveButton);
    editor.append(label, input, actions, status);
    (select.closest('.col-md-6') || select.parentElement).appendChild(editor);

    activeDamageReasonEditor = { container: editor, select, input, saveButton, cancelButton, status };
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            saveDamageReason();
        } else if (event.key === 'Escape') {
            closeDamageReasonEditor();
        }
    });
    input.focus();
}

function closeDamageReasonEditor(expectedEditor = null) {
    if (!activeDamageReasonEditor || (expectedEditor && activeDamageReasonEditor.container !== expectedEditor.container)) return;
    activeDamageReasonEditor.container.remove();
    activeDamageReasonEditor = null;
}

async function saveDamageReason() {
    const editor = activeDamageReasonEditor;
    if (!editor) return;
    const reason = editor.input.value.trim();
    if (!reason) {
        editor.status.textContent = 'Please enter a damage reason.';
        editor.status.className = 'small mt-1 text-danger';
        editor.input.focus();
        return;
    }

    editor.saveButton.disabled = true;
    editor.cancelButton.disabled = true;
    editor.saveButton.textContent = 'Saving...';
    editor.status.textContent = '';
    editor.status.className = 'small mt-1 d-none';

    const formData = new FormData();
    formData.append('_csrf_token', '<?= CSRF::token() ?>');
    formData.append('reason', reason);

    try {
        const response = await fetch('<?= url('production/damages/reasons/store') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to save this damage reason.');
        }

        const savedReason = result.reason;
        if (!damageReasonOptions.some(option => option.toLowerCase() === savedReason.toLowerCase())) {
            damageReasonOptions.push(savedReason);
        }
        if (!Array.from(editor.select.options).some(option => option.value.toLowerCase() === savedReason.toLowerCase())) {
            const option = document.createElement('option');
            option.value = savedReason;
            option.textContent = savedReason;
            editor.select.appendChild(option);
        }
        editor.select.value = savedReason;
        editor.select.dispatchEvent(new Event('change', { bubbles: true }));
        closeDamageReasonEditor(editor);
    } catch (error) {
        editor.status.textContent = error.message || 'Unable to save this damage reason.';
        editor.status.className = 'small mt-1 text-danger';
        editor.saveButton.disabled = false;
        editor.cancelButton.disabled = false;
        editor.saveButton.textContent = 'Save Reason';
    }
}
</script>


<!-- Modal to View Damaged Item QR Code Details -->
<div class="modal fade" id="damageQrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-qr-code-scan me-2"></i>Damaged Item Quarantined QR Code
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="alert alert-danger py-1 px-3 mb-3 d-inline-block fw-bold small">
                    <i class="bi bi-shield-x me-1"></i> QUARANTINED DEFECTIVE ITEM · NOT FOR SALE
                </div>

                <!-- Big QR Code Container -->
                <div class="d-flex justify-content-center mb-3">
                    <div id="modalDamageQrBox" class="p-2 border rounded bg-white shadow-sm" style="width: 170px; height: 170px; display: flex; align-items: center; justify-content: center;"></div>
                </div>

                <h4 class="fw-bold font-monospace text-dark mb-1" id="modalDamageQrText"></h4>
                <div class="text-danger fw-bold small mb-3" id="modalDamageNum"></div>

                <div class="bg-light p-3 rounded text-start small border">
                    <div class="row g-2">
                        <div class="col-6"><span class="text-muted d-block">Roll Number:</span><strong id="modalDamageRoll"></strong></div>
                        <div class="col-6"><span class="text-muted d-block">Damaged Weight:</span><strong id="modalDamageWeight" class="text-danger"></strong></div>
                        <div class="col-6"><span class="text-muted d-block">Supplier:</span><strong id="modalDamageSupplier"></strong></div>
                        <div class="col-6"><span class="text-muted d-block">Defect Reason:</span><strong id="modalDamageReason" class="text-danger"></strong></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 justify-content-between">
                <span class="text-muted small">Billing restriction active for this QR code.</span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generate clean QR thumbnails for all damage rows
    document.querySelectorAll('.damage-qr-canvas').forEach(function(box) {
        const qrText = box.getAttribute('data-qr');
        if (qrText && typeof QRCode !== 'undefined') {
            try {
                box.innerHTML = '';
                new QRCode(box, {
                    text: qrText,
                    width: 40,
                    height: 40,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
                setTimeout(function() {
                    box.removeAttribute('title');
                    box.querySelectorAll('img, canvas').forEach(function(el) { el.removeAttribute('title'); });
                }, 40);
            } catch (err) {
                console.error("Damage QR Thumb Error:", err);
            }
        }
    });
});

function showDamageQrModal(damageNumber, qrCode, rollNumber, reason, weight, supplier) {
    document.getElementById('modalDamageNum').textContent = 'Damage Record: ' + damageNumber;
    document.getElementById('modalDamageQrText').textContent = qrCode;
    document.getElementById('modalDamageRoll').textContent = rollNumber;
    document.getElementById('modalDamageReason').textContent = reason;
    document.getElementById('modalDamageWeight').textContent = weight;
    document.getElementById('modalDamageSupplier').textContent = supplier;

    const qrBox = document.getElementById('modalDamageQrBox');
    qrBox.innerHTML = '';
    if (typeof QRCode !== 'undefined') {
        new QRCode(qrBox, {
            text: qrCode,
            width: 154,
            height: 154,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.L
        });
        setTimeout(function() {
            qrBox.removeAttribute('title');
            qrBox.querySelectorAll('img, canvas').forEach(function(el) { el.removeAttribute('title'); });
        }, 40);
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('damageQrModal')).show();
}

function confirmDeleteDamage(actionUrl, damageNumber) {
    const modalEl = document.getElementById('modalConfirmDeleteDamage');
    const formEl = document.getElementById('deleteDamageForm');
    const numEl = document.getElementById('deleteDamageNumber');
    if (formEl) formEl.action = actionUrl;
    if (numEl) numEl.textContent = '#' + damageNumber;
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}
</script>

<!-- Modal: Modern Confirmation Popup for Deleting Damage Records -->
<div class="modal fade" id="modalConfirmDeleteDamage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 68px; height: 68px;">
                    <i class="bi bi-trash3-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Delete Damage Record</h5>
                <p class="text-secondary small mb-3">
                    Are you sure you want to delete Damage Record <strong class="text-dark" id="deleteDamageNumber"></strong>?
                </p>
                <div class="alert alert-info border-0 bg-info-subtle text-start p-3 rounded-3 small mb-4">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-arrow-counterclockwise text-primary fs-5 flex-shrink-0 mt-n1"></i>
                        <div>
                            <strong class="text-primary">Inventory Roll Restoration:</strong>
                            <div class="text-secondary mt-1">
                                Deleting this record will automatically restore the damaged roll quantity and weight back to the active fabric inventory stock.
                            </div>
                        </div>
                    </div>
                </div>
                <form method="POST" id="deleteDamageForm" action="">
                    <?= CSRF::field() ?>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light px-4 fw-semibold border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4 fw-semibold shadow-sm d-flex align-items-center gap-1">
                            <i class="bi bi-trash me-1"></i> Delete &amp; Restore
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>