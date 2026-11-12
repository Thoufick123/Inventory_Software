<?php
/**
 * Warehouse Inward Edit / Stock Reconciliation View
 * Allows authorized staff to modify received and damaged quantities, box count, and notes.
 * Automatically reconciles warehouse stock deltas and logs full audit trails.
 */

$canDelete = hasPermission('inward', 'delete');
$displayRec = (int)($totalReceived ?? ($batch['received_qty'] ?? 0));
$displayDmg = (int)($totalDamaged ?? ($batch['damaged_qty'] ?? 0));
$displayMis = (int)($totalMissing ?? ($batch['missing_qty'] ?? 0));
?>

<div class="container-fluid px-0 px-md-3 py-3">

    <!-- Page Breadcrumb & Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('warehouse/dashboard') ?>" class="text-decoration-none">Warehouse</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('warehouse/inward') ?>" class="text-decoration-none">Inward / GRN</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('warehouse/inward/view/' . $batch['id']) ?>" class="text-decoration-none">Batch #<?= e($batch['batch_number']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit & Reconcile</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0 text-dark">
                <i class="bi bi-pencil-square text-warning me-2"></i>Edit Inward Shipment
                <span class="fs-6 font-monospace text-muted fw-normal ms-2">#<?= e($batch['batch_number']) ?></span>
            </h3>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= url('warehouse/inward/view/' . $batch['id']) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Batch View
            </a>
            <?php if ($canDelete): ?>
                <button type="button" class="btn btn-outline-danger" onclick="openDeleteInwardModal(<?= htmlspecialchars(json_encode([
                    'id'        => (int)$batch['id'],
                    'number'    => $batch['batch_number'],
                    'product'   => $batch['product_name'] ?? '',
                    'warehouse' => $batch['warehouse_name'] ?? '',
                    'received'  => $displayRec,
                    'damaged'   => $displayDmg,
                    'status'    => $batch['status']
                ]), ENT_QUOTES, 'UTF-8') ?>)">
                    <i class="bi bi-trash3 me-1"></i> Delete Inward Receipt
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert / Info Notice -->
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-3 py-2 px-3 mb-4 rounded-3">
        <i class="bi bi-shield-exclamation fs-3 text-warning"></i>
        <div class="small">
            <strong class="text-dark">Stock Reconciliation Notice:</strong> 
            Adjusting received or damaged counts will automatically recalculate warehouse available inventory and quarantine damage balances. A valid mandatory reason is required for system audit tracking.
        </div>
    </div>

    <!-- Batch Summary Meta Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-primary">
                <div class="small text-muted text-uppercase fw-semibold mb-1">Batch Identifier</div>
                <div class="fw-bold text-dark font-monospace fs-6 mb-1"><?= e($batch['batch_number']) ?></div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= $batch['status'] === 'RECEIVED' ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= e($batch['status']) ?>
                    </span>
                    <span class="small text-muted"><?= number_format($batch['total_qty'] ?? $batch['quantity'] ?? 0) ?> Expected</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-info">
                <div class="small text-muted text-uppercase fw-semibold mb-1">Source Production Facility</div>
                <div class="fw-bold text-dark fs-6 text-truncate mb-1">
                    <?= e($outward['production_unit_name'] ?? $batch['production_unit_name'] ?? 'Production Unit') ?>
                </div>
                <div class="small text-muted">
                    Gatepass: <?= !empty($outward['outward_number']) ? '#' . e($outward['outward_number']) : 'N/A' ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-success">
                <div class="small text-muted text-uppercase fw-semibold mb-1">Destination Warehouse</div>
                <div class="fw-bold text-dark fs-6 text-truncate mb-1">
                    <?= e($batch['warehouse_name'] ?? 'Warehouse') ?>
                </div>
                <div class="small text-muted">
                    Code: <?= e($batch['warehouse_code'] ?? 'WH-01') ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-12 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-secondary">
                <div class="small text-muted text-uppercase fw-semibold mb-1">Current Inward Status</div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                        <i class="bi bi-check-circle me-1"></i><?= number_format($displayRec) ?> Good
                    </span>
                    <span class="badge <?= $displayDmg > 0 ? 'bg-danger text-white' : 'bg-light text-muted border' ?> px-2 py-1">
                        <i class="bi bi-slash-circle me-1"></i><?= number_format($displayDmg) ?> Dmg
                    </span>
                    <span class="badge <?= $displayMis > 0 ? 'bg-warning text-dark' : 'bg-light text-muted border' ?> px-2 py-1">
                        <?= number_format($displayMis) ?> Short
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Edit Form -->
    <form method="POST" action="<?= url('warehouse/inward/update/' . $batch['id']) ?>" id="editInwardForm" onsubmit="return validateEditInwardForm()">
        <?= CSRF::field() ?>

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-sliders2 text-primary me-2"></i>Variant Breakdown & Inward Reconcile Table
                </h5>
                <span class="text-muted small">Update Received & Damaged counts below</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="inwardEditTable">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 40px;">#</th>
                            <th style="min-width: 240px;">Product & Variant Details</th>
                            <th class="text-center" style="width: 110px;">Dispatched</th>
                            <th class="text-center" style="width: 110px;">Prev Credited</th>
                            <th class="text-center" style="width: 170px;">Received (Good) <span class="text-danger">*</span></th>
                            <th class="text-center" style="width: 170px;">Damaged <span class="text-danger">*</span></th>
                            <th class="text-center" style="width: 110px;">Missing</th>
                            <th class="text-center" style="width: 140px;">Stock Delta</th>
                            <th class="pe-3" style="min-width: 160px;">Item Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowIndex = 1;
                        foreach ($items as $item): 
                            $vid = (int)$item['variant_id'];
                            $expQty = (int)($item['quantity'] ?? 0);
                            $initRec = (int)($item['received_qty'] ?? 0);
                            $initDmg = (int)($item['damaged_qty'] ?? 0);
                            $initMis = isset($item['missing_qty']) ? (int)$item['missing_qty'] : max(0, $expQty - ($initRec + $initDmg));

                            if ($batch['status'] === 'RECEIVED' && $initRec === 0 && $initDmg === 0 && $initMis === 0) {
                                $initRec = $expQty;
                                $initMis = 0;
                            }
                        ?>
                            <tr class="variant-edit-row" data-variant-id="<?= $vid ?>" data-expected="<?= $expQty ?>" data-prev-rec="<?= $initRec ?>" data-prev-dmg="<?= $initDmg ?>">
                                <td class="ps-3 text-muted small fw-semibold"><?= $rowIndex++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark fs-7"><?= e($item['product_name'] ?? 'Product') ?></div>
                                    <div class="small font-monospace text-muted"><?= e($item['sku']) ?></div>
                                    <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                        <?php if (!empty($item['color_name'])): ?>
                                            <span class="badge bg-light text-dark border py-0 px-1" style="font-size: 0.72rem;">
                                                <span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background-color: <?= e($item['color_hex'] ?? '#ccc') ?>; vertical-align: middle;"></span>
                                                <?= e($item['color_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['size_name'])): ?>
                                            <span class="badge bg-light text-secondary border py-0 px-1" style="font-size: 0.72rem;">
                                                Size: <?= e($item['size_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center font-monospace fw-bold text-secondary">
                                    <?= number_format($expQty) ?>
                                </td>
                                <td class="text-center small">
                                    <span class="badge bg-light text-success border border-success-subtle px-1.5 py-0.5 d-block mb-1">
                                        <?= number_format($initRec) ?> Good
                                    </span>
                                    <?php if ($initDmg > 0): ?>
                                        <span class="badge bg-light text-danger border border-danger-subtle px-1.5 py-0.5 d-block">
                                            <?= number_format($initDmg) ?> Dmg
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <!-- Received Good Qty -->
                                <td>
                                    <div class="input-group input-group-sm justify-content-center">
                                        <button class="btn btn-outline-secondary btn-step-down" type="button" onclick="adjustEditQty(<?= $vid ?>, 'rec', -1)">-</button>
                                        <input type="number" 
                                               name="items[<?= $vid ?>][received_qty]" 
                                               id="editRecv_<?= $vid ?>" 
                                               class="form-control text-center fw-bold text-success font-monospace edit-rec-input" 
                                               value="<?= $initRec ?>" 
                                               min="0" 
                                               max="<?= $expQty ?>" 
                                               style="max-width: 70px;"
                                               oninput="onEditQtyChanged(<?= $vid ?>)"
                                               required>
                                        <button class="btn btn-outline-secondary btn-step-up" type="button" onclick="adjustEditQty(<?= $vid ?>, 'rec', 1)">+</button>
                                    </div>
                                </td>
                                <!-- Damaged Qty -->
                                <td>
                                    <div class="input-group input-group-sm justify-content-center">
                                        <button class="btn btn-outline-secondary btn-step-down" type="button" onclick="adjustEditQty(<?= $vid ?>, 'dmg', -1)">-</button>
                                        <input type="number" 
                                               name="items[<?= $vid ?>][damaged_qty]" 
                                               id="editDmg_<?= $vid ?>" 
                                               class="form-control text-center fw-bold text-danger font-monospace edit-dmg-input" 
                                               value="<?= $initDmg ?>" 
                                               min="0" 
                                               max="<?= $expQty ?>" 
                                               style="max-width: 70px;"
                                               oninput="onEditQtyChanged(<?= $vid ?>)"
                                               required>
                                        <button class="btn btn-outline-secondary btn-step-up" type="button" onclick="adjustEditQty(<?= $vid ?>, 'dmg', 1)">+</button>
                                    </div>
                                </td>
                                <!-- Missing Qty -->
                                <td class="text-center">
                                    <span id="badgeMissing_<?= $vid ?>" class="badge <?= $initMis > 0 ? 'bg-warning text-dark' : 'bg-light text-muted border' ?> font-monospace">
                                        <?= number_format($initMis) ?>
                                    </span>
                                </td>
                                <!-- Stock Delta -->
                                <td class="text-center">
                                    <span id="badgeDelta_<?= $vid ?>" class="badge bg-light text-secondary border font-monospace" style="font-size: 0.75rem;">
                                        0 pcs
                                    </span>
                                </td>
                                <!-- Item Notes -->
                                <td class="pe-3">
                                    <input type="text" name="items[<?= $vid ?>][notes]" class="form-control form-control-sm" placeholder="Notes..." value="<?= e($item['notes'] ?? '') ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2" class="ps-3 text-uppercase">Totals Live Preview:</td>
                            <td class="text-center font-monospace" id="totalDispatchedCount"><?= number_format($totalExpected) ?></td>
                            <td class="text-center font-monospace text-muted small"><?= number_format($displayRec) ?>G / <?= number_format($displayDmg) ?>D</td>
                            <td class="text-center font-monospace text-success" id="totalNewRecCount"><?= number_format($displayRec) ?></td>
                            <td class="text-center font-monospace text-danger" id="totalNewDmgCount"><?= number_format($displayDmg) ?></td>
                            <td class="text-center font-monospace text-warning" id="totalNewMisCount"><?= number_format($displayMis) ?></td>
                            <td class="text-center font-monospace" id="totalStockDeltaBadge"><span class="badge bg-secondary">0 pcs net</span></td>
                            <td class="pe-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Additional Inward Details & Reason for Edit -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-box-seam me-2 text-primary"></i>Packaging & General Notes
                    </h6>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Box / Carton Count</label>
                        <input type="number" name="box_count" class="form-control form-control-sm" min="1" value="<?= (int)($batch['box_count'] ?? 1) ?>">
                    </div>
                    <div>
                        <label class="form-label small fw-semibold text-muted">Inward Shipment Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Additional remarks or notes regarding this consignment..."><?= e($batch['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-warning">
                    <h6 class="fw-bold text-dark mb-2">
                        <i class="bi bi-journal-text me-2 text-warning"></i>Audit Trail: Reason for Modification <span class="text-danger">*</span>
                    </h6>
                    <p class="small text-muted mb-2">
                        State clearly why this inward consignment count is being modified. This explanation will be permanently recorded in the Enterprise Audit Log alongside previous and updated warehouse balances.
                    </p>
                    <div class="mb-2">
                        <textarea name="edit_reason" 
                                  id="inwardEditReason" 
                                  class="form-control" 
                                  rows="3" 
                                  required 
                                  minlength="4" 
                                  placeholder="Provide the mandatory reason for this modification (e.g. Physical recount revealed 2 additional intact pieces; carton 2 damaged in transit corrected; duplicate scan removed)..."></textarea>
                    </div>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2">
                        <div class="small text-muted">
                            <i class="bi bi-clock-history me-1"></i> Changes will be attributed to <strong><?= e(Session::user()['full_name'] ?? 'Current Staff') ?></strong>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= url('warehouse/inward/view/' . $batch['id']) ?>" class="btn btn-outline-secondary px-3">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm" id="btnSubmitInwardEdit">
                                <i class="bi bi-check2-circle me-1"></i> Save Inward Adjustments
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CONFIRM DELETE INWARD SHIPMENT WITH MANDATORY REASON -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalDeleteInward" tabindex="-1" aria-labelledby="modalDeleteInwardLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" id="formDeleteInward" action="">
                <?= CSRF::field() ?>
                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fw-bold" id="modalDeleteInwardLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete / Reverse Inward Receipt
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-danger-subtle border border-danger-subtle text-danger small py-2 px-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-shield-x fs-4 flex-shrink-0"></i>
                        <div>
                            <strong>Critical Action:</strong> Deleting this inward receipt will deduct and reverse all credited stock from warehouse inventory and cancel quarantined damage records.
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Batch Number:</span>
                            <span class="fw-bold font-monospace text-dark" id="delInwardNumber">#</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Product:</span>
                            <span class="fw-semibold text-dark text-truncate" style="max-width: 230px;" id="delInwardProduct">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Warehouse:</span>
                            <span class="fw-semibold text-dark" id="delInwardWarehouse">-</span>
                        </div>
                        <div class="d-flex justify-content-between pt-1 border-top">
                            <span class="text-muted small">Credited Stock to Reverse:</span>
                            <span class="fw-bold text-danger font-monospace" id="delInwardCredited">0 pcs</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="return_to_production" id="delInwardReturnProg" value="1">
                            <label class="form-check-label small fw-semibold text-dark" for="delInwardReturnProg">
                                Also return consignment back to Production Facility
                            </label>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.74rem;">
                            If checked, the consignment status will be reverted to Approved and removed from Warehouse Inward. If unchecked, it resets to Awaiting GRN.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">
                            Mandatory Reason for Deletion <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason" 
                                  id="delInwardReason" 
                                  class="form-control" 
                                  rows="3" 
                                  required 
                                  minlength="4" 
                                  placeholder="Please provide the exact reason why this inward shipment is being deleted/reversed (e.g. Entered incorrect consignment, duplicate GRN verification, wrong batch received from production)..."></textarea>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            This reason is mandatory and will be logged into the System Audit Log.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold" id="btnConfirmDeleteInward">
                        <i class="bi bi-trash3-fill me-1"></i> Confirm & Reverse Inward
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function adjustEditQty(vid, type, step) {
    const input = document.getElementById(type === 'rec' ? 'editRecv_' + vid : 'editDmg_' + vid);
    if (!input) return;
    let val = parseInt(input.value, 10) || 0;
    const min = parseInt(input.min, 10) || 0;
    const max = parseInt(input.max, 10) || 999999;
    val += step;
    if (val < min) val = min;
    if (val > max) val = max;
    input.value = val;
    onEditQtyChanged(vid);
}

function onEditQtyChanged(vid) {
    const row = document.querySelector('tr[data-variant-id="' + vid + '"]');
    if (!row) return;

    const expected = parseInt(row.getAttribute('data-expected'), 10) || 0;
    const prevRec = parseInt(row.getAttribute('data-prev-rec'), 10) || 0;
    const prevDmg = parseInt(row.getAttribute('data-prev-dmg'), 10) || 0;

    const recvInput = document.getElementById('editRecv_' + vid);
    const dmgInput = document.getElementById('editDmg_' + vid);

    let recVal = parseInt(recvInput.value, 10) || 0;
    let dmgVal = parseInt(dmgInput.value, 10) || 0;

    if (recVal < 0) { recVal = 0; recvInput.value = 0; }
    if (dmgVal < 0) { dmgVal = 0; dmgInput.value = 0; }

    // Enforce expected bounds
    if ((recVal + dmgVal) > expected) {
        // If current edit pushed total over expected, cap the active one
        if (document.activeElement === dmgInput) {
            dmgVal = Math.max(0, expected - recVal);
            dmgInput.value = dmgVal;
        } else {
            recVal = Math.max(0, expected - dmgVal);
            recvInput.value = recVal;
        }
    }

    const missing = Math.max(0, expected - (recVal + dmgVal));
    const badgeMis = document.getElementById('badgeMissing_' + vid);
    if (badgeMis) {
        badgeMis.textContent = missing;
        badgeMis.className = 'badge ' + (missing > 0 ? 'bg-warning text-dark' : 'bg-light text-muted border') + ' font-monospace';
    }

    // Delta compared to previously received available stock
    const delta = recVal - prevRec;
    const badgeDelta = document.getElementById('badgeDelta_' + vid);
    if (badgeDelta) {
        if (delta > 0) {
            badgeDelta.textContent = '+' + delta + ' pcs';
            badgeDelta.className = 'badge bg-success-subtle text-success border border-success font-monospace';
        } else if (delta < 0) {
            badgeDelta.textContent = delta + ' pcs';
            badgeDelta.className = 'badge bg-danger-subtle text-danger border border-danger font-monospace';
        } else {
            badgeDelta.textContent = '0 pcs';
            badgeDelta.className = 'badge bg-light text-secondary border font-monospace';
        }
    }

    recalcEditTableTotals();
}

function recalcEditTableTotals() {
    let totRec = 0;
    let totDmg = 0;
    let totMis = 0;
    let totDelta = 0;

    document.querySelectorAll('.variant-edit-row').forEach(row => {
        const vid = row.getAttribute('data-variant-id');
        const expected = parseInt(row.getAttribute('data-expected'), 10) || 0;
        const prevRec = parseInt(row.getAttribute('data-prev-rec'), 10) || 0;

        const recVal = parseInt(document.getElementById('editRecv_' + vid)?.value, 10) || 0;
        const dmgVal = parseInt(document.getElementById('editDmg_' + vid)?.value, 10) || 0;
        const misVal = Math.max(0, expected - (recVal + dmgVal));

        totRec += recVal;
        totDmg += dmgVal;
        totMis += misVal;
        totDelta += (recVal - prevRec);
    });

    const elRec = document.getElementById('totalNewRecCount');
    const elDmg = document.getElementById('totalNewDmgCount');
    const elMis = document.getElementById('totalNewMisCount');
    const elDelta = document.getElementById('totalStockDeltaBadge');

    if (elRec) elRec.textContent = totRec;
    if (elDmg) elDmg.textContent = totDmg;
    if (elMis) elMis.textContent = totMis;

    if (elDelta) {
        if (totDelta > 0) {
            elDelta.innerHTML = '<span class="badge bg-success">+' + totDelta + ' pcs net increase</span>';
        } else if (totDelta < 0) {
            elDelta.innerHTML = '<span class="badge bg-danger">' + totDelta + ' pcs net reduction</span>';
        } else {
            elDelta.innerHTML = '<span class="badge bg-secondary">0 pcs net</span>';
        }
    }
}

function validateEditInwardForm() {
    const reason = document.getElementById('inwardEditReason')?.value?.trim();
    if (!reason || reason.length < 4) {
        alert('Please provide a mandatory reason for modifying this inward shipment (at least 4 characters).');
        document.getElementById('inwardEditReason')?.focus();
        return false;
    }
    return confirm('Are you sure you want to update this inward consignment? Stock balances will be automatically adjusted to reflect these changes.');
}

function openDeleteInwardModal(data) {
    if (!data || !data.id) return;
    const form = document.getElementById('formDeleteInward');
    if (form) {
        form.action = '<?= url("warehouse/inward/delete/") ?>' + data.id;
    }
    document.getElementById('delInwardNumber').textContent = data.number || ('#' + data.id);
    document.getElementById('delInwardProduct').textContent = data.product || '-';
    document.getElementById('delInwardWarehouse').textContent = data.warehouse || '-';
    
    const rec = parseInt(data.received, 10) || 0;
    const dmg = parseInt(data.damaged, 10) || 0;
    document.getElementById('delInwardCredited').textContent = rec + ' Good / ' + dmg + ' Damaged';

    document.getElementById('delInwardReason').value = '';

    const modalEl = document.getElementById('modalDeleteInward');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

// Initial calculation on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.variant-edit-row').forEach(row => {
        const vid = row.getAttribute('data-variant-id');
        onEditQtyChanged(vid);
    });
});
</script>
