<div class="page-header mb-4">
    <h2><i class="bi bi-box-seam me-2 text-success"></i>Pack Finished Production Batch</h2>
   </div>

<?php if ($selectedQC && ($selectedQC['remaining_qty'] ?? 1) <= 0 && ($selectedQC['already_packed_qty'] ?? 0) > 0 && ($totalQCRework ?? 0) > 0): ?>
    <div class="alert alert-warning border border-warning-subtle shadow-sm p-3 mb-4 rounded-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-warning bg-opacity-20 text-warning rounded-circle">
                    <i class="bi bi-tools fs-2"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1 text-dark">Reworked Inventory Available for Next Batch</h5>
                    <p class="text-muted small mb-0">
                        All regular QC-passed pieces have been packed, but <strong><?= number_format($totalQCRework) ?> repaired / reworked pieces</strong> are available. Enable the <strong>Admin Rework Option</strong> toggle below to pack these reworked pieces into a new batch for Outward dispatch.
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($selectedQC && ($selectedQC['remaining_qty'] ?? 1) <= 0 && ($selectedQC['already_packed_qty'] ?? 0) > 0): ?>
    <div class="alert alert-info border border-info-subtle shadow-sm p-3 mb-4 rounded-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-info bg-opacity-10 text-info rounded-circle">
                    <i class="bi bi-info-circle-fill fs-2"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1 text-dark">QC Batch #<?= $selectedQC['id'] ?> is Already Fully Packed</h5>
                    <p class="text-muted small mb-0">
                        All <strong><?= number_format($selectedQC['total_qc_passed'] ?? $selectedQC['passed_qty']) ?> passed pieces</strong> from this QC inspection have already been packed into finished batch(es) and dispatched. There are 0 remaining pieces to pack.
                    </p>
                    <?php if (!empty($existingBatches)): ?>
                    <div class="mt-2 d-flex gap-2 flex-wrap align-items-center">
                        <span class="small fw-semibold text-dark">Packed In:</span>
                        <?php foreach ($existingBatches as $eb): ?>
                            <span class="badge bg-white text-dark border shadow-xs p-2">
                                <i class="bi bi-box-seam me-1 text-primary"></i> <strong>Batch #<?= e($eb['batch_number']) ?></strong> &bull; <?= $eb['total_qty'] ?> pcs &bull; Status: <span class="badge bg-<?= $eb['status'] === 'DISPATCHED' ? 'warning-subtle text-dark border' : 'success' ?>"><?= e($eb['status']) ?></span>
                                <?php if (!empty($eb['warehouse_name'])): ?>
                                    &rarr; <?= e($eb['warehouse_name']) ?>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('production/batches' . (!empty($existingBatches[0]['batch_number']) ? '?search=' . urlencode($existingBatches[0]['batch_number']) : '')) ?>" class="btn btn-primary fw-semibold">
                    <i class="bi bi-box-seam me-1"></i> View Finished Batch
                </a>
                <a href="<?= url('production/inventory') ?>" class="btn btn-outline-secondary fw-semibold">
                    <i class="bi bi-boxes me-1"></i> View Inventory
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="<?= url('production/batches/store') ?>" id="packBatchForm">
    <?= CSRF::field() ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Packing Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold required">Select QC Inspection Batch <span class="text-danger">*</span></label>
                        <select name="qc_batch_id" id="qc_batch_id" class="form-select <?= empty($selectedQC) ? 'border-primary' : '' ?>" required onchange="handleQCBatchChange(this.value)">
                            <option value="">-- Select QC Batch --</option>
                            <?php foreach ($qcBatches as $qb): ?>
                                <option value="<?= $qb['id'] ?>" <?= ($selectedQC && (int)$selectedQC['id'] === (int)$qb['id']) ? 'selected' : '' ?>>
                                    QC #<?= $qb['id'] ?> (<?= e($qb['program_number'] ?? '') ?> — <?= e($qb['product_name'] ?? '') ?>) [<?= number_format($qb['remaining_qty'] ?? $qb['passed_qty']) ?> pcs rem<?= !empty($qb['total_qc_rework']) ? ' · ' . number_format($qb['total_qc_rework']) . ' rework' : '' ?> / <?= number_format($qb['total_qc_passed'] ?? $qb['passed_qty']) ?> passed]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($qcBatches) && !$selectedQC): ?>
                            <div class="alert alert-warning py-2 px-3 small mt-2 mb-0">
                                <i class="bi bi-info-circle me-1"></i> All QC passed batches have already been fully packed into finished batches. <a href="<?= url('production/qc/create') ?>" class="alert-link">Create a QC inspection</a>.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Small Checkbox to Combine with Reworked/Completed Batch -->
                    <?php if ($selectedQC && (function_exists('isAdmin') ? isAdmin() : true)): ?>
                        <div class="form-check mt-2 mb-2">
                            <input class="form-check-input" type="checkbox" id="combineReworkCheck" name="is_multi_qc_check" value="1" <?= !empty($additionalQcIds) ? 'checked' : '' ?> onchange="toggleReworkDropdown(this.checked)">
                            <label class="form-check-label small fw-semibold text-secondary cursor-pointer" for="combineReworkCheck">
                                <i class="bi bi-patch-check text-primary me-1"></i> Combine with Reworked / Re-inspected Batch
                            </label>
                        </div>

                        <!-- Dynamic Dropdown Field for Completed Rework Batches -->
                        <div id="reworkDropdownContainer" class="<?= empty($additionalQcIds) ? 'd-none' : '' ?> mb-3 p-2.5 bg-light rounded border border-primary-subtle">
                            <label class="form-label small fw-bold text-dark mb-1">
                                Select Rework-Completed QC Batch <span class="text-danger">*</span>
                            </label>
                            <select name="additional_rework_qc_id" id="additional_qc_batch_dropdown" class="form-select form-select-sm" onchange="onReworkBatchSelect(this.value)">
                                <option value="">-- Choose Completed Rework / Repaired Batch --</option>
                                <?php foreach ($eligibleAdditionalBatches as $eab): 
                                    $isSelected = in_array((int)$eab['id'], $additionalQcIds ?? [], true);
                                ?>
                                    <option value="<?= $eab['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                        QC #<?= $eab['id'] ?> (<?= e($eab['program_number'] ?? '') ?> — <?= e($eab['product_name'] ?? '') ?>) [<?= number_format($eab['remaining_qty'] ?? $eab['passed_qty']) ?> pcs ready<?= !empty($eab['rework_completed_qty']) ? ' · ' . $eab['rework_completed_qty'] . ' rework-fixed' : '' ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($eligibleAdditionalBatches)): ?>
                                <div class="text-muted small mt-1" style="font-size: 11px;">
                                    <i class="bi bi-info-circle me-1"></i> No other batches with completed rework/re-inspected pieces found.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($selectedQC): ?>
                        <input type="hidden" name="program_id" id="program_id" value="<?= $selectedQC['program_id'] ?>">
                        <input type="hidden" name="is_multi_qc" value="<?= !empty($isMultiQC) && !empty($additionalQcIds) ? '1' : '0' ?>">
                        <?php if (!empty($additionalQcIds)): ?>
                            <?php foreach ($additionalQcIds as $aqId): ?>
                                <input type="hidden" name="additional_qc_batch_ids[]" value="<?= (int)$aqId ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="mb-3 p-3 bg-light rounded border">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="small text-muted fw-semibold">Program &amp; Product</span>
                                <?php if (!empty($isMultiQC) && !empty($additionalQCBatches)): ?>
                                    <span class="badge bg-primary px-2 py-1 small">
                                        <i class="bi bi-collection me-1"></i> <?= 1 + count($additionalQCBatches) ?> QC Batches Combined
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="fw-bold text-dark"><?= e($selectedQC['program_number']) ?> — <?= e($selectedQC['product_name']) ?></div>
                            
                            <?php if (!empty($isMultiQC) && !empty($additionalQCBatches)): ?>
                                <div class="mt-2 pt-2 border-top small">
                                    <div class="fw-semibold text-dark mb-1">Batches Combined in this Pack:</div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-primary text-white border">
                                            <i class="bi bi-check-circle me-1"></i>Primary: QC #<?= $selectedQC['id'] ?> (<?= number_format($selectedQC['remaining_qty']) ?> pcs)
                                        </span>
                                        <?php foreach ($additionalQCBatches as $ab): ?>
                                            <span class="badge bg-info-subtle text-dark border border-info">
                                                <i class="bi bi-plus me-1"></i>QC #<?= $ab['id'] ?> &bull; <?= e($ab['program_number'] ?? '') ?> (<?= number_format($ab['remaining_qty']) ?> pcs)
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="row g-2 mt-2 pt-2 border-top text-center">
                                <div class="col-3">
                                    <div class="small text-muted" style="font-size: 10px;">Total Passed</div>
                                    <div class="fw-bold text-dark small"><?= number_format(!empty($isMultiQC) && !empty($additionalQCBatches) ? ($combinedTotals['total_passed'] ?? 0) : ($selectedQC['total_qc_passed'] ?? $selectedQC['passed_qty'])) ?></div>
                                </div>
                                <div class="col-3">
                                    <div class="small text-muted" style="font-size: 10px;">Packed</div>
                                    <div class="fw-bold text-danger small"><?= number_format(!empty($isMultiQC) && !empty($additionalQCBatches) ? ($combinedTotals['packed'] ?? 0) : ($selectedQC['already_packed_qty'] ?? 0)) ?></div>
                                </div>
                                <div class="col-3">
                                    <div class="small text-muted" style="font-size: 10px;">Passed Rem.</div>
                                    <div class="fw-bold text-success small"><?= number_format(!empty($isMultiQC) && !empty($additionalQCBatches) ? ($combinedTotals['remaining'] ?? 0) : ($selectedQC['remaining_qty'] ?? $selectedQC['passed_qty'])) ?></div>
                                </div>
                                <div class="col-3">
                                    <div class="small text-muted" style="font-size: 10px;">Rework</div>
                                    <div class="fw-bold text-warning small"><?= number_format(!empty($isMultiQC) && !empty($additionalQCBatches) ? ($combinedTotals['rework'] ?? 0) : ($totalQCRework ?? 0)) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="program_id" id="program_id" value="">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold required">Destination Warehouse <span class="text-danger">*</span></label>
                        <select name="destination_warehouse_id" id="destination_warehouse_id" class="form-select" required>
                            <option value="">-- Select Warehouse --</option>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= $w['id'] ?>"><?= e($w['name']) ?> (<?= e($w['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold required">Total Box / Carton Count <span class="text-danger">*</span></label>
                        <input type="number" name="box_count" id="box_count" class="form-control" min="1" value="1" required placeholder="Enter number of boxes...">
                        <div class="form-text small text-muted">Total number of boxes or cartons packed for this batch.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm h-100 d-flex flex-column">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-grid-3x3 me-2 text-primary"></i>Packed Quantities</h5>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($selectedQC): ?>
                            <button type="button" class="btn btn-sm btn-outline-success fw-semibold shadow-sm" onclick="generateBillFromSelectedQC()" title="Generate Bill &amp; Invoice from checked products in this QC Batch">
                                <i class="bi bi-receipt-cutoff me-1"></i> Bill Generation
                            </button>
                        <?php endif; ?>
                        <span class="badge bg-success fs-6 px-3 py-2" id="totalPackedBadge">0 pcs Packed</span>
                    </div>
                </div>
                <?php if ($selectedQC && ($totalQCRework ?? 0) > 0): ?>
                    <div class="p-3 bg-warning bg-opacity-10 border-bottom border-warning-subtle">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="include_rework" id="toggleIncludeRework" value="1" <?= !empty($includeRework) ? 'checked' : '' ?> onchange="onToggleReworkOption(this.checked)">
                                <label class="form-check-label fw-bold text-dark cursor-pointer" for="toggleIncludeRework">
                                    <i class="bi bi-tools text-warning me-1"></i> Include Reworked / Repaired Garments in this Batch (Admin Option)
                                </label>
                            </div>
                            <div>
                                <span class="badge bg-warning text-dark border border-warning-subtle fw-semibold" id="reworkCountBadge">
                                    <i class="bi bi-arrow-repeat me-1"></i> <?= number_format($totalQCRework ?? 0) ?> Rework Pcs in this QC Batch
                                </span>
                            </div>
                        </div>
                        <div id="reworkNoticeBox" class="small text-muted mt-2 pt-2 border-top border-warning-subtle <?= empty($includeRework) ? 'd-none' : '' ?>">
                            <i class="bi bi-info-circle-fill text-warning me-1"></i>
                            <strong>Admin Rework Flow Active:</strong> Repaired and reworked pieces are unlocked into the packable pool. These items will be packed into the master cartons and loaded directly for Outward Dispatch to the Central Warehouse.
                        </div>
                    </div>
                <?php endif; ?>
                <div class="card-body p-0 flex-grow-1">
                    <?php if (!$selectedQC): ?>
                        <div class="p-5 text-center text-muted my-auto">
                            <div class="mb-3">
                                <div class="d-inline-flex p-3 rounded-circle bg-light">
                                    <i class="bi bi-arrow-left-circle fs-1 text-primary"></i>
                                </div>
                            </div>
                            <h5 class="fw-bold text-dark">No QC Batch Selected</h5>
                            <p class="text-muted mb-0">Please select a QC inspection batch from the dropdown on the left to load the approved variant quantities.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="packedItemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Variant</th>
                                        <th>QC Status &amp; Remaining</th>
                                        <th>Approved QR Code(s) to Pack</th>
                                        <th style="width: 180px;">Quantity to Pack <span class="text-danger">*</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $packableItems = [];
                                    foreach ($items as $idx => $it) {
                                        $reworkQty = (int)($it['rework_qty'] ?? 0);
                                        $remPassedQty = (int)($it['remaining_qty'] ?? $it['passed_qty']);
                                        $effectivePackable = $remPassedQty + (!empty($includeRework) ? $reworkQty : 0);
                                        $availablePieceTags = array_values($it['available_piece_tags'] ?? []);
                                        $hasAvailableTags = !empty($availablePieceTags);
                                        $packQty = $hasAvailableTags ? min($effectivePackable, count($availablePieceTags)) : $effectivePackable;
                                        if ($effectivePackable > 0 && $packQty > 0) {
                                            $it['computed_rework_qty'] = $reworkQty;
                                            $it['computed_rem_passed'] = $remPassedQty;
                                            $it['computed_effective'] = $effectivePackable;
                                            $it['computed_pack_qty'] = $packQty;
                                            $it['computed_available_tags'] = $availablePieceTags;
                                            $it['computed_has_tags'] = $hasAvailableTags;
                                            $packableItems[$idx] = $it;
                                        }
                                    }
                                    ?>
                                    <?php if (empty($packableItems)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                                <h6 class="fw-bold text-dark">All Passed Products Already Packed &amp; Outwarded</h6>
                                                <p class="small text-muted mb-0">All approved items for this QC Inspection batch have already been packed into production batches. No remaining pieces available to pack.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($packableItems as $idx => $it): 
                                            $reworkQty = $it['computed_rework_qty'];
                                            $remPassedQty = $it['computed_rem_passed'];
                                            $effectivePackable = $it['computed_effective'];
                                            $availablePieceTags = $it['computed_available_tags'];
                                            $hasAvailableTags = $it['computed_has_tags'];
                                            $packQty = $it['computed_pack_qty'];
                                            $rejectedQty = (int)($it['rejected_qty'] ?? 0);
                                            $rowPrice = (float)(!empty($it['discount_price']) ? $it['discount_price'] : (!empty($it['base_price']) ? $it['base_price'] : (!empty($it['selling_price']) ? $it['selling_price'] : (!empty($it['mrp']) ? $it['mrp'] : 0))));
                                            $rowGst = (float)(!empty($it['gst_percentage']) ? $it['gst_percentage'] : 5.0);
                                        ?>
                                            <tr class="variant-item-row" 
                                                data-variant-name="<?= e($it['size_name'] ?? $it['display_size'] ?? 'Standard') ?> / <?= e($it['color_name'] ?? $it['display_color'] ?? 'Assorted') ?>" 
                                                data-passed="<?= $packQty ?>" 
                                                data-passed-qty="<?= $remPassedQty ?>"
                                                data-rework-qty="<?= $reworkQty ?>"
                                                data-unit-price="<?= $rowPrice ?>"
                                                data-gst-rate="<?= $rowGst ?>"
                                                data-is-zero="0">
                                                <td>
                                                    <input type="hidden" name="items[<?= $idx ?>][qc_batch_id]" value="<?= $it['qc_batch_id'] ?? $selectedQC['id'] ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][qc_item_id]" value="<?= $it['id'] ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][variant_id]" value="<?= $it['variant_id'] ?? '' ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][size_name]" value="<?= e($it['size_name'] ?? $it['display_size'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][color_name]" value="<?= e($it['color_name'] ?? $it['display_color'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][sku]" value="<?= e($it['sku'] ?? '') ?>">
                                                    <div class="fw-bold text-dark"><?= e($it['size_name'] ?? $it['display_size'] ?? 'Standard') ?> / <?= e($it['color_name'] ?? $it['display_color'] ?? 'Assorted') ?></div>
                                                    <small class="text-muted font-monospace"><?= e($it['sku'] ?? '') ?></small>
                                                    <?php if (!empty($isMultiQC) && !empty($additionalQCBatches)): ?>
                                                        <div class="mt-1">
                                                            <span class="badge bg-<?= !empty($it['is_additional']) ? 'info' : 'primary' ?>-subtle text-<?= !empty($it['is_additional']) ? 'info-emphasis' : 'primary' ?> border border-<?= !empty($it['is_additional']) ? 'info' : 'primary' ?>-subtle font-monospace" style="font-size: 11px;">
                                                                <i class="bi bi-box-seam me-1"></i>QC #<?= $it['qc_batch_number'] ?? $selectedQC['id'] ?><?= !empty($it['is_additional']) ? ' (Combined)' : ' (Primary)' ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                            <i class="bi bi-box-seam me-1"></i><?= number_format($remPassedQty) ?> pcs Passed Available
                                                        </span>
                                                        <?php if ($reworkQty > 0): ?>
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 mt-1 d-inline-block">
                                                                <i class="bi bi-tools me-1"></i><?= number_format($reworkQty) ?> Rework Pcs <?= !empty($includeRework) ? '(Unlocked)' : '(Admin Toggle)' ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (($it['already_packed'] ?? 0) > 0): ?>
                                                        <small class="text-muted d-block mt-1">
                                                            Total Passed: <?= number_format($it['passed_qty']) ?> pcs (<?= number_format($it['already_packed']) ?> already packed)
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted d-block mt-1">
                                                            Total Passed: <?= number_format($it['passed_qty']) ?> pcs
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($hasAvailableTags): ?>
                                                        <div class="small fw-semibold text-success mb-1">
                                                            <i class="bi bi-patch-check-fill me-1"></i>
                                                            <?= number_format(count($availablePieceTags)) ?> approved QR code(s) available
                                                        </div>
                                                        <?php if ($rejectedQty > 0 || $reworkQty > 0): ?>
                                                            <div class="small text-danger mb-2">
                                                                <?= $rejectedQty > 0 ? number_format($rejectedQty) . ' rejected' : '' ?>
                                                                <?= $rejectedQty > 0 && $reworkQty > 0 ? ' · ' : '' ?>
                                                                <?= $reworkQty > 0 ? number_format($reworkQty) . ' need rework' : '' ?> QR code(s) excluded.
                                                                Re-inspect and pass them in QC before packing.
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="piece-tag-picker">
                                                            <input type="search"
                                                                   class="form-control form-control-sm piece-tag-search mb-2"
                                                                   placeholder="Search QR codes..."
                                                                   aria-label="Search QR codes for <?= e($it['sku'] ?? '') ?>"
                                                                   oninput="filterPieceTags(this)">
                                                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                                                <small class="text-muted piece-tag-count" aria-live="polite"></small>
                                                                <div class="d-flex gap-1">
                                                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" onclick="selectAllPieceTags(this)"><i class="bi bi-check-all me-1"></i>Select All</button>
                                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="clearPieceTags(this)">Clear</button>
                                                                </div>
                                                            </div>
                                                            <div class="piece-tag-options">
                                                            <?php foreach ($availablePieceTags as $tagIndex => $tag): ?>
                                                                <label class="piece-tag-option" data-piece-tag-option>
                                                                    <input type="checkbox"
                                                                           name="items[<?= $idx ?>][piece_tags][]"
                                                                           class="form-check-input piece-tags-input mt-0"
                                                                           value="<?= e($tag) ?>"
                                                                           <?= $tagIndex < $packQty ? 'checked' : '' ?>
                                                                           onchange="syncPieceTagSelection(this)">
                                                                    <span class="font-monospace"><?= e($tag) ?></span>
                                                                    <?php if (!empty($isMultiQC) && !empty($additionalQCBatches)): ?>
                                                                        <span class="badge bg-light text-muted border py-0 px-1 font-monospace" style="font-size: 9px;">#<?= $it['qc_batch_number'] ?? $selectedQC['id'] ?></span>
                                                                    <?php endif; ?>
                                                                </label>
                                                            <?php endforeach; ?>
                                                            </div>
                                                            <div class="small text-muted mt-1">Check QR codes to pack. The quantity updates to match your selection.</div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="small text-muted">Individual QC QR codes are unavailable.</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" 
                                                               name="items[<?= $idx ?>][quantity]" 
                                                               class="form-control form-control-sm packed-input fw-semibold" 
                                                               min="0" 
                                                               max="<?= $packQty ?>"
                                                               value="<?= $packQty ?>"
                                                               oninput="calcPackedTotal(); updatePieceTagCounts()">
                                                        <span class="input-group-text bg-light text-muted">pcs</span>
                                                    </div>
                                                    <small class="text-muted d-block mt-1">
                                                        Max: <span class="max-display-val"><?= number_format($packQty) ?></span> pcs
                                                        <?php if (!empty($includeRework) && $reworkQty > 0): ?>
                                                            <span class="text-warning fw-semibold">(incl. <?= $reworkQty ?> rework)</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Live Billing & Product Valuation Summary Before Save -->
                <?php if ($selectedQC): ?>
                    <div class="px-4 py-3 bg-light border-top border-bottom">
                        <div class="row g-3 align-items-center text-center text-md-start">
                            <div class="col-6 col-md-3">
                                <span class="small text-muted text-uppercase fw-bold d-block"><i class="bi bi-box-seam me-1 text-primary"></i>Product Count</span>
                                <span class="fs-5 fw-bold text-dark" id="calcSummaryProductCount">0 pcs</span>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="small text-muted text-uppercase fw-bold d-block"><i class="bi bi-currency-rupee me-1 text-secondary"></i>Taxable Value</span>
                                <span class="fs-5 fw-bold text-secondary" id="calcSummaryTaxable">₹0.00</span>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="small text-muted text-uppercase fw-bold d-block"><i class="bi bi-percent me-1 text-info"></i>GST Amount</span>
                                <span class="fs-5 fw-bold text-primary" id="calcSummaryGst">₹0.00</span>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="small text-muted text-uppercase fw-bold d-block"><i class="bi bi-receipt me-1 text-success"></i>Total Bill &amp; Invoice</span>
                                <span class="fs-4 fw-bold text-success" id="calcSummaryTotal">₹0.00</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center border-top flex-wrap gap-2">
                    <a href="<?= url('production/batches') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </a>
                    <?php if ($selectedQC && ($selectedQC['remaining_qty'] ?? 1) <= 0 && ($selectedQC['already_packed_qty'] ?? 0) > 0 && ($totalQCRework ?? 0) <= 0): ?>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-success px-4 fw-semibold shadow-sm" onclick="generateBillFromSelectedQC()">
                                <i class="bi bi-receipt-cutoff me-1"></i> Generate Bill &amp; Invoice
                            </button>
                            <a href="<?= url('production/batches' . (!empty($existingBatches[0]['batch_number']) ? '?search=' . urlencode($existingBatches[0]['batch_number']) : '')) ?>" class="btn btn-primary px-4 fw-semibold">
                                <i class="bi bi-box-seam me-1"></i> View Finished Batch (<?= e($existingBatches[0]['batch_number'] ?? '') ?>)
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($selectedQC): ?>
                                <button type="button" class="btn btn-outline-success px-3 fw-semibold shadow-sm" id="btnCreateBillFromQC" onclick="generateBillFromSelectedQC()" title="Generate Bill and Tax Invoice directly from selected products in this QC Batch">
                                    <i class="bi bi-receipt-cutoff me-1"></i> Generate Bill &amp; Invoice
                                </button>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnSavePackedBatch">
                                <i class="bi bi-check2-circle me-1"></i> Save Packed Batch &amp; Generate Bill / Invoice
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.piece-tag-options {
    max-height: 180px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.25rem 0.5rem;
}

.piece-tag-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.2rem 0;
    cursor: pointer;
    overflow-wrap: anywhere;
}

.piece-tag-option:hover {
    background: #f8f9fa;
}
</style>

<script>
function toggleReworkDropdown(show) {
    const container = document.getElementById('reworkDropdownContainer');
    const dropdown = document.getElementById('additional_qc_batch_dropdown');
    if (show) {
        if (container) container.classList.remove('d-none');
    } else {
        if (container) container.classList.add('d-none');
        if (dropdown) dropdown.value = '';
        const qcId = document.getElementById('qc_batch_id')?.value;
        if (qcId) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('multi_qc');
            currentUrl.searchParams.delete('additional_qc_batch_ids');
            window.location.href = currentUrl.toString();
        }
    }
}

function onReworkBatchSelect(val) {
    const qcId = document.getElementById('qc_batch_id')?.value;
    if (!qcId) return;
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('qc_batch_id', qcId);
    if (val) {
        currentUrl.searchParams.set('multi_qc', '1');
        currentUrl.searchParams.set('additional_qc_batch_ids', val);
    } else {
        currentUrl.searchParams.delete('multi_qc');
        currentUrl.searchParams.delete('additional_qc_batch_ids');
    }
    window.location.href = currentUrl.toString();
}

function onToggleReworkOption(isChecked) {
    const currentUrl = new URL(window.location.href);
    if (isChecked) {
        currentUrl.searchParams.set('include_rework', '1');
    } else {
        currentUrl.searchParams.delete('include_rework');
    }
    window.location.href = currentUrl.toString();
}

function handleQCBatchChange(qcBatchId) {
    if (qcBatchId) {
        window.location.href = '<?= url('production/batches/create') ?>?qc_batch_id=' + encodeURIComponent(qcBatchId);
    } else {
        window.location.href = '<?= url('production/batches/create') ?>';
    }
}

function calcPackedTotal() {
    let totalQty = 0;
    let totalTaxable = 0;
    let totalGst = 0;

    document.querySelectorAll('.variant-item-row').forEach(row => {
        const inp = row.querySelector('.packed-input');
        if (!inp || inp.disabled) return;
        const qty = parseInt(inp.value, 10) || 0;
        if (qty <= 0) return;

        totalQty += qty;
        const unitPrice = parseFloat(row.getAttribute('data-unit-price')) || 0;
        const gstRate = parseFloat(row.getAttribute('data-gst-rate')) || 5.0;

        const rowTaxable = qty * unitPrice;
        const rowGst = rowTaxable * (gstRate / 100);

        totalTaxable += rowTaxable;
        totalGst += rowGst;
    });

    const badge = document.getElementById('totalPackedBadge');
    if (badge) {
        badge.innerText = totalQty.toLocaleString() + ' pcs Packed';
    }

    const elCount = document.getElementById('calcSummaryProductCount');
    if (elCount) elCount.innerText = totalQty.toLocaleString() + ' pcs';

    const elTaxable = document.getElementById('calcSummaryTaxable');
    if (elTaxable) elTaxable.innerText = '₹' + totalTaxable.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const elGst = document.getElementById('calcSummaryGst');
    if (elGst) elGst.innerText = '₹' + totalGst.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const elTotal = document.getElementById('calcSummaryTotal');
    if (elTotal) elTotal.innerText = '₹' + (totalTaxable + totalGst).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    return totalQty;
}

function updatePieceTagCounts() {
    document.querySelectorAll('.variant-item-row').forEach(row => {
        const options = row.querySelector('.piece-tag-options');
        const countLabel = row.querySelector('.piece-tag-count');
        const quantityInput = row.querySelector('.packed-input');
        if (!options || !countLabel || !quantityInput) return;

        const selectedCount = options.querySelectorAll('.piece-tags-input:checked').length;
        countLabel.textContent = `${selectedCount} selected / ${quantityInput.value || 0} qty`;
    });
}

function syncPieceTagSelection(input) {
    const row = input.closest('.variant-item-row');
    const options = row?.querySelector('.piece-tag-options');
    const quantityInput = row?.querySelector('.packed-input');
    if (!options || !quantityInput) return;

    quantityInput.value = options.querySelectorAll('.piece-tags-input:checked').length;
    quantityInput.classList.remove('is-invalid', 'border-danger');
    options.classList.remove('is-invalid', 'border-danger');
    updatePieceTagCounts();
    calcPackedTotal();
}

function filterPieceTags(input) {
    const search = input.value.trim().toLowerCase();
    const options = input.closest('.piece-tag-picker')?.querySelectorAll('[data-piece-tag-option]') ?? [];
    options.forEach(option => {
        option.hidden = !option.textContent.toLowerCase().includes(search);
    });
}

function selectAllPieceTags(button) {
    const row = button.closest('.variant-item-row');
    const options = row?.querySelector('.piece-tag-options');
    if (!options) return;

    options.querySelectorAll('.piece-tags-input').forEach(checkbox => {
        checkbox.checked = true;
    });
    const firstCheckbox = options.querySelector('.piece-tags-input');
    if (firstCheckbox) {
        syncPieceTagSelection(firstCheckbox);
    }
}

function selectPieceTagsForQuantity(button) {
    selectAllPieceTags(button);
}

function clearPieceTags(button) {
    const row = button.closest('.variant-item-row');
    const options = row?.querySelector('.piece-tag-options');
    if (!options) return;

    options.querySelectorAll('.piece-tags-input').forEach(checkbox => {
        checkbox.checked = false;
    });
    syncPieceTagSelection(options.querySelector('.piece-tags-input'));
}

document.addEventListener('DOMContentLoaded', function() {
    calcPackedTotal();
    updatePieceTagCounts();

    const form = document.getElementById('packBatchForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const qcBatchSelect = document.getElementById('qc_batch_id');
            const warehouseSelect = document.getElementById('destination_warehouse_id');
            const boxCountInput = document.getElementById('box_count');

            // 1. Validate QC Batch selection
            if (!qcBatchSelect || !qcBatchSelect.value) {
                e.preventDefault();
                alert('Please select a QC Inspection Batch first.');
                if (qcBatchSelect) {
                    qcBatchSelect.classList.add('is-invalid', 'border-danger');
                    qcBatchSelect.focus();
                }
                return false;
            } else {
                qcBatchSelect.classList.remove('is-invalid', 'border-danger');
            }

            // Validate Combine Rework selection if checked
            const reworkCheck = document.getElementById('combineReworkCheck');
            const reworkDropdown = document.getElementById('additional_qc_batch_dropdown');
            if (reworkCheck && reworkCheck.checked && (!reworkDropdown || !reworkDropdown.value)) {
                e.preventDefault();
                alert('Please select a Rework-Completed QC Batch from the dropdown, or uncheck the combine option.');
                if (reworkDropdown) {
                    reworkDropdown.classList.add('is-invalid', 'border-danger');
                    reworkDropdown.focus();
                }
                return false;
            } else if (reworkDropdown) {
                reworkDropdown.classList.remove('is-invalid', 'border-danger');
            }

            // 2. Validate Destination Warehouse
            if (!warehouseSelect || !warehouseSelect.value) {
                e.preventDefault();
                alert('Please select a Destination Warehouse.');
                if (warehouseSelect) {
                    warehouseSelect.classList.add('is-invalid', 'border-danger');
                    warehouseSelect.focus();
                }
                return false;
            } else {
                warehouseSelect.classList.remove('is-invalid', 'border-danger');
            }

            // 3. Validate Box Count
            const boxVal = parseInt(boxCountInput?.value, 10);
            if (!boxCountInput || isNaN(boxVal) || boxVal < 1) {
                e.preventDefault();
                alert('Total Box / Carton Count is required and must be at least 1.');
                if (boxCountInput) {
                    boxCountInput.classList.add('is-invalid', 'border-danger');
                    boxCountInput.focus();
                }
                return false;
            } else {
                boxCountInput?.classList.remove('is-invalid', 'border-danger');
            }

            // 4. Validate Variant Quantities
            const packedInputs = document.querySelectorAll('.packed-input');
            if (packedInputs.length === 0) {
                e.preventDefault();
                alert('No variant items available in the selected QC batch. Please choose a valid QC batch.');
                return false;
            }

            let totalPacked = 0;
            let hasError = false;

            packedInputs.forEach(inp => {
                if (inp.disabled) return;

                const tr = inp.closest('.variant-item-row');
                const variantLabel = tr ? tr.getAttribute('data-variant-name') : 'Variant';
                const maxPassed = tr ? parseInt(tr.getAttribute('data-passed'), 10) : 0;
                const val = parseInt(inp.value, 10);

                if (isNaN(val) || val < 0) {
                    e.preventDefault();
                    alert(`Invalid quantity for ${variantLabel}. Quantity must be 0 or greater.`);
                    inp.classList.add('is-invalid', 'border-danger');
                    inp.focus();
                    hasError = true;
                    return false;
                }

                if (val > maxPassed) {
                    e.preventDefault();
                    alert(`Quantity to pack for ${variantLabel} (${val} pcs) cannot exceed the remaining available quantity (${maxPassed} pcs).`);
                    inp.classList.add('is-invalid', 'border-danger');
                    inp.focus();
                    hasError = true;
                    return false;
                }

                const pieceTagOptions = tr ? tr.querySelector('.piece-tag-options') : null;
                const selectedPieceTagCount = pieceTagOptions ? pieceTagOptions.querySelectorAll('.piece-tags-input:checked').length : 0;
                if (pieceTagOptions && selectedPieceTagCount !== val) {
                    e.preventDefault();
                    alert(`Select exactly ${val} QR code(s) for ${variantLabel}.`);
                    pieceTagOptions.classList.add('is-invalid', 'border-danger');
                    pieceTagOptions.querySelector('.piece-tags-input')?.focus();
                    hasError = true;
                    return false;
                }
                if (pieceTagOptions) pieceTagOptions.classList.remove('is-invalid', 'border-danger');

                inp.classList.remove('is-invalid', 'border-danger');
                totalPacked += val;
            });

            if (hasError) return false;

            if (totalPacked <= 0) {
                e.preventDefault();
                alert('Please enter a packed quantity greater than 0 for at least one available variant item.');
                const firstEnabled = document.querySelector('.packed-input:not([disabled])');
                if (firstEnabled) firstEnabled.focus();
                return false;
            }

            return true;
        });
    }
});

function generateBillFromSelectedQC() {
    const qcSelect = document.getElementById('qc_batch_id');
    const qcBatchId = qcSelect ? qcSelect.value : '<?= $selectedQC['id'] ?? '' ?>';
    if (!qcBatchId) {
        alert('Please select a QC Inspection Batch first.');
        if (qcSelect) qcSelect.focus();
        return;
    }

    // Collect all checked piece tags
    const checkedTags = [];
    document.querySelectorAll('.piece-tags-input:checked').forEach(cb => {
        const val = (cb.value || '').trim();
        if (val) checkedTags.push(val);
    });

    const hasRows = document.querySelectorAll('.variant-item-row').length > 0;
    if (!hasRows && checkedTags.length === 0) {
        alert('No QC products available to bill.');
        return;
    }

    // Dynamically create form and submit to production/billing/create
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= url("production/billing/create") ?>';

    const csrf = document.querySelector('input[name="csrf_token"]');
    if (csrf) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrf.value;
        form.appendChild(csrfInput);
    }

    const qcInput = document.createElement('input');
    qcInput.type = 'hidden';
    qcInput.name = 'qc_batch_id';
    qcInput.value = qcBatchId;
    form.appendChild(qcInput);

    checkedTags.forEach(tag => {
        const tagInput = document.createElement('input');
        tagInput.type = 'hidden';
        tagInput.name = 'selected_tags[]';
        tagInput.value = tag;
        form.appendChild(tagInput);
    });

    document.body.appendChild(form);
    form.submit();
}
</script>
