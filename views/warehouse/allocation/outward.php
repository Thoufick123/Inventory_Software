<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="h3 mb-1 text-gray-800"><i class="bi bi-truck me-2 text-primary"></i>Warehouse Outward & Dispatch</h2>
        <span class="text-muted small">Dispatch finished allocation boxes to retail shops and sales channels with vehicle tracking</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('allocation', 'view')): ?>
        <a href="<?= url('warehouse/allocation') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i> View Requisitions
        </a>
        <?php endif; ?>
        <?php if (!empty($approvedAllocations) && hasPermission('allocation_outward', 'create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAllocationOutwardModal">
                <i class="bi bi-plus-circle me-1"></i> New Dispatch Outward
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Stat Notice if Approved Allocations Ready -->
<?php if (!empty($approvedAllocations)): ?>
    <div class="alert alert-info d-flex align-items-center justify-content-between mb-4 shadow-sm border-0 rounded-3">
        <div>
            <i class="bi bi-info-circle-fill me-2 fs-5 text-info"></i>
            <strong><?= count($approvedAllocations) ?> Approved / Packed Allocation(s)</strong> ready for dispatch to shops or channels.
        </div>
        <?php if (hasPermission('allocation_outward', 'create')): ?>
        <button type="button" class="btn btn-sm btn-info text-white fw-bold" data-bs-toggle="modal" data-bs-target="#newAllocationOutwardModal">
            Dispatch Outward Now &rarr;
        </button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Filter & Search Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('warehouse/allocation/outward') ?>" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search outward #, allocation #, vehicle, or shop..." value="<?= htmlspecialchars($search ?? '') ?>">
            </div>
            <div class="col-md-4">
                <select name="warehouse_id" class="form-select" <?= ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1) ? 'disabled' : '' ?>>
                    <?php if (!function_exists('currentWarehouseId') || currentWarehouseId() === null && count($warehouses) > 1): ?>
                        <option value="">-- All Source Warehouses --</option>
                    <?php endif; ?>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= $wh['id'] ?>" <?= (($warehouseId ?? 0) == $wh['id'] || (function_exists('currentWarehouseId') && currentWarehouseId() == $wh['id'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wh['name']) ?><?= !empty($wh['code']) ? ' (' . htmlspecialchars($wh['code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1): ?>
                    <input type="hidden" name="warehouse_id" value="<?= htmlspecialchars((string)($warehouseId ?: ($warehouses[0]['id'] ?? ''))) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Filter Outwards</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- READY FOR DISPATCH: APPROVED & PACKED ALLOCATIONS TABLE -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 mb-4" style="border-left: 4px solid #0d6efd !important;">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i>Ready for Dispatch (Approved & Packed Allocations)
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">
                <?= count($approvedAllocations ?? []) ?> Batches Ready
            </span>
        </div>
        <?php if (!empty($approvedAllocations) && hasPermission('allocation_outward', 'create')): ?>
            <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newAllocationOutwardModal">
                <i class="bi bi-plus-circle me-1"></i> Dispatch Batch
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 18%;">Allocation #</th>
                        <th style="width: 18%;">Source Warehouse</th>
                        <th style="width: 20%;">Destination</th>
                        <th style="width: 14%;">Total Quantity</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 18%;" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($approvedAllocations)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i>
                                <span>No pending approved allocations waiting for dispatch. All batches are dispatched!</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($approvedAllocations as $ab): 
                            $dest = !empty($ab['shop_name']) ? $ab['shop_name'] : (!empty($ab['channel_name']) ? $ab['channel_name'] : (!empty($ab['dest_warehouse_name']) ? $ab['dest_warehouse_name'] : ($ab['destination_type'] ?? 'Destination')));
                            
                            $statusBg = 'bg-secondary';
                            if ($ab['status'] === 'APPROVED') $statusBg = 'bg-success';
                            elseif ($ab['status'] === 'PACKING') $statusBg = 'bg-info text-dark';
                            elseif ($ab['status'] === 'PICKING') $statusBg = 'bg-warning text-dark';
                            elseif ($ab['status'] === 'REQUESTED') $statusBg = 'bg-primary';
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark font-monospace"><?= htmlspecialchars($ab['allocation_number']) ?></div>
                                    <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y, h:i A', strtotime($ab['created_at'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($ab['warehouse_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary text-white"><?= htmlspecialchars($dest) ?></span>
                                    <small class="text-muted d-block mt-0.5"><?= htmlspecialchars($ab['destination_type']) ?></small>
                                </td>
                                <td>
                                    <span class="fw-bold text-success fs-6"><?= number_format($ab['total_qty']) ?> pcs</span>
                                    <?php if (!empty($ab['total_items'])): ?>
                                        <small class="text-muted d-block">(<?= $ab['total_items'] ?> SKU<?= $ab['total_items'] > 1 ? 's' : '' ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $statusBg ?> px-2.5 py-1.5"><?= htmlspecialchars($ab['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <?php if (hasPermission('allocation_outward', 'create')): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-primary fw-bold dispatch-batch-btn shadow-sm" 
                                                data-allocation-id="<?= $ab['id'] ?>" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#newAllocationOutwardModal" 
                                                title="Dispatch this allocation outward">
                                            <i class="bi bi-truck me-1"></i> Dispatch Outward
                                        </button>
                                        <?php endif; ?>
                                        <a href="<?= url('warehouse/allocation/view/' . $ab['id']) ?>" class="btn btn-sm btn-outline-secondary" title="View Allocation Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Outward History Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h5 class="card-title mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Outward Dispatch History</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Outward #</th>
                        <th>Allocation #</th>
                        <th>Source Warehouse</th>
                        <th>Destination</th>
                        <th>Total Pieces</th>
                        <th>Vehicle / Tracking</th>
                        <th>Dispatched At</th>
                        <th>Status</th>
                        <th class="text-end" style="min-width: 220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outwards)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i> No warehouse outward dispatches recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($outwards as $o): 
                            $destName = !empty($o['shop_name']) ? $o['shop_name'] : (!empty($o['channel_name']) ? $o['channel_name'] : ($o['alloc_dest_type'] ?? 'Destination'));
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($o['outward_number']) ?></div>
                                    <small class="text-muted">By <?= htmlspecialchars($o['created_by_name'] ?? 'Staff') ?></small>
                                </td>
                                <td><code class="text-primary fw-bold"><?= htmlspecialchars($o['allocation_number']) ?></code></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($o['warehouse_name']) ?></span></td>
                                <td><span class="badge bg-primary text-white"><?= htmlspecialchars($destName) ?></span></td>
                                <td class="text-success fw-bold"><?= number_format($o['total_quantity']) ?> pcs (<?= $o['total_boxes'] ?> box)</td>
                                <td><code><?= htmlspecialchars($o['vehicle_number'] ?: 'Handover') ?></code></td>
                                <td><?= date('d M Y, h:i A', strtotime($o['dispatch_date'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= ($o['status'] ?? '') === 'DELIVERED' ? 'success' : 'primary' ?>">
                                        <?= htmlspecialchars($o['status'] ?? 'DISPATCHED') ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        <?php if (hasPermission('allocation_outward', 'view')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" onclick="viewAllocationOutwardDetails(<?= (int)$o['id'] ?>)" title="View Outward Details &amp; Items Breakdown">
                                                <i class="bi bi-eye me-1"></i> View
                                            </button>
                                        <?php endif; ?>

                                        <?php if (hasPermission('allocation_outward', 'edit')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" onclick='openEditAllocationOutwardModal(<?= json_encode($o, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Vehicle &amp; Dispatch Details">
                                                <i class="bi bi-pencil me-1"></i> Edit
                                            </button>
                                        <?php endif; ?>

                                        <?php if (hasPermission('allocation_outward', 'gatepass') || hasPermission('allocation_outward', 'view')): ?>
                                            <a href="<?= url('warehouse/allocation/gatepass/' . $o['allocation_id']) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Print QR Gatepass / Labels">
                                                <i class="bi bi-qr-code-scan"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (hasPermission('allocation_outward', 'delete')): ?>
                                            <form method="POST" action="<?= url('warehouse/allocation/outward/delete/' . (int)$o['id']) ?>" class="d-inline mb-0" onsubmit="return confirm('Are you sure you want to delete Outward Dispatch #<?= htmlspecialchars($o['outward_number'], ENT_QUOTES) ?>? This will cancel the outward and revert allocation status to Approved.');">
                                                <?= CSRF::field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Outward Dispatch">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for New Allocation Outward Dispatch -->
<?php if (!empty($approvedAllocations)): ?>
<div class="modal fade" id="newAllocationOutwardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= url('warehouse/allocation/outward/store') ?>" id="outwardDispatchForm" novalidate>
                <?= CSRF::field() ?>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>Dispatch Warehouse Allocation Outward</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Approved Allocation Batch <span class="text-danger">*</span></label>
                        <select name="allocation_id" id="outward_allocation_id" class="form-select" required>
                            <option value="">-- Select Approved Allocation Batch --</option>
                            <?php foreach ($approvedAllocations as $ab): 
                                $dest = !empty($ab['shop_name']) ? $ab['shop_name'] : (!empty($ab['channel_name']) ? $ab['channel_name'] : (!empty($ab['dest_warehouse_name']) ? $ab['dest_warehouse_name'] : ($ab['destination_type'] ?? 'Destination')));
                            ?>
                                <option value="<?= $ab['id'] ?>">
                                    <?= htmlspecialchars($ab['allocation_number']) ?> — <?= htmlspecialchars($ab['warehouse_name']) ?> &rarr; <?= htmlspecialchars($dest) ?> (<?= number_format($ab['total_qty']) ?> pcs)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select an approved allocation batch.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Vehicle / Transport Number <span class="text-danger">*</span></label>
                            <input type="text" name="vehicle_number" id="outward_vehicle_number" class="form-control text-uppercase" placeholder="e.g. TN-38-AB-1234 or Courier Ref" required>
                            <div class="invalid-feedback">Vehicle / Transport Number is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Driver Name <span class="text-danger">*</span></label>
                            <input type="text" name="driver_name" id="outward_driver_name" class="form-control" placeholder="e.g. Ramesh" required>
                            <div class="invalid-feedback">Driver Name is required.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Driver Contact Number <span class="text-danger">*</span></label>
                            <input type="text" name="driver_contact" id="outward_driver_contact" class="form-control" placeholder="e.g. 9876543210" maxlength="10" required>
                            <div class="invalid-feedback">Please enter a valid 10-digit mobile number.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Transporter / Logistics Name <span class="text-danger">*</span></label>
                            <input type="text" name="transporter_name" id="outward_transporter_name" class="form-control" placeholder="e.g. VRL Logistics / BlueDart" required>
                            <div class="invalid-feedback">Transporter / Logistics Name is required.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">LR / Docket / Tracking Number <span class="text-muted small fw-normal">(Optional)</span></label>
                            <input type="text" name="lr_number" id="outward_lr_number" class="form-control" placeholder="e.g. LR-987654">
                            <div class="form-text text-muted">Optional tracking reference if available</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Total Box / Carton Count <span class="text-danger">*</span></label>
                            <input type="number" name="total_boxes" id="outward_total_boxes" class="form-control" value="1" min="1" required>
                            <div class="invalid-feedback">Total Box / Carton Count must be at least 1.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Dispatch Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="dispatch_date" id="outward_dispatch_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                            <div class="invalid-feedback">Dispatch Date & Time is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Delivery / Dispatch Notes <span class="text-muted small fw-normal">(Optional)</span></label>
                            <textarea name="notes" id="outward_notes" class="form-control" rows="1" placeholder="e.g. Handle with care..."></textarea>
                            <div class="form-text text-muted">Optional instructions or delivery remarks</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="submitOutwardBtn"><i class="bi bi-check2 me-1"></i> Confirm & Dispatch Outward</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('outwardDispatchForm');
    if (!form) return;

    const allocationSelect = document.getElementById('outward_allocation_id');
    const vehicleInput = document.getElementById('outward_vehicle_number');
    const driverNameInput = document.getElementById('outward_driver_name');
    const driverContactInput = document.getElementById('outward_driver_contact');
    const transporterInput = document.getElementById('outward_transporter_name');
    const totalBoxesInput = document.getElementById('outward_total_boxes');
    const dispatchDateInput = document.getElementById('outward_dispatch_date');

    function setFieldStatus(input, isValid, errorMsg = '') {
        if (!input) return;
        const parent = input.closest('.col-md-6') || input.parentElement;
        const feedback = parent ? parent.querySelector('.invalid-feedback') : null;
        if (isValid) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            if (feedback && errorMsg) {
                feedback.textContent = errorMsg;
            }
        }
    }

    function validateAllocation() {
        if (!allocationSelect) return true;
        const isValid = allocationSelect.value.trim() !== '';
        setFieldStatus(allocationSelect, isValid, 'Please select an approved allocation batch.');
        return isValid;
    }

    function validateVehicle() {
        if (!vehicleInput) return true;
        const isValid = vehicleInput.value.trim().length >= 2;
        setFieldStatus(vehicleInput, isValid, 'Vehicle / Transport Number is required.');
        return isValid;
    }

    function validateDriverName() {
        if (!driverNameInput) return true;
        const isValid = driverNameInput.value.trim().length >= 2;
        setFieldStatus(driverNameInput, isValid, 'Driver Name is required.');
        return isValid;
    }

    function validateDriverContact() {
        if (!driverContactInput) return true;
        const clean = driverContactInput.value.replace(/[^0-9]/g, '');
        driverContactInput.value = clean.slice(0, 10);
        const isValid = clean.length === 10;
        setFieldStatus(driverContactInput, isValid, 'Please enter a valid 10-digit mobile number.');
        return isValid;
    }

    function validateTransporter() {
        if (!transporterInput) return true;
        const isValid = transporterInput.value.trim().length >= 2;
        setFieldStatus(transporterInput, isValid, 'Transporter / Logistics Name is required.');
        return isValid;
    }

    function validateTotalBoxes() {
        if (!totalBoxesInput) return true;
        const val = parseInt(totalBoxesInput.value, 10);
        const isValid = !isNaN(val) && val >= 1;
        setFieldStatus(totalBoxesInput, isValid, 'Total Box / Carton Count must be at least 1.');
        return isValid;
    }

    function validateDispatchDate() {
        if (!dispatchDateInput) return true;
        const isValid = dispatchDateInput.value.trim() !== '';
        setFieldStatus(dispatchDateInput, isValid, 'Dispatch Date & Time is required.');
        return isValid;
    }

    // Real-time inline event listeners
    if (allocationSelect) {
        allocationSelect.addEventListener('change', validateAllocation);
    }
    if (vehicleInput) {
        vehicleInput.addEventListener('input', validateVehicle);
        vehicleInput.addEventListener('blur', validateVehicle);
    }
    if (driverNameInput) {
        driverNameInput.addEventListener('input', validateDriverName);
        driverNameInput.addEventListener('blur', validateDriverName);
    }
    if (driverContactInput) {
        driverContactInput.addEventListener('input', validateDriverContact);
        driverContactInput.addEventListener('blur', validateDriverContact);
    }
    if (transporterInput) {
        transporterInput.addEventListener('input', validateTransporter);
        transporterInput.addEventListener('blur', validateTransporter);
    }
    if (totalBoxesInput) {
        totalBoxesInput.addEventListener('input', validateTotalBoxes);
        totalBoxesInput.addEventListener('blur', validateTotalBoxes);
    }
    if (dispatchDateInput) {
        dispatchDateInput.addEventListener('input', validateDispatchDate);
        dispatchDateInput.addEventListener('change', validateDispatchDate);
    }

    // Preselect allocation batch when clicking Dispatch Outward from table row
    document.querySelectorAll('.dispatch-batch-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const allocId = this.getAttribute('data-allocation-id');
            if (allocId && allocationSelect) {
                allocationSelect.value = allocId;
                validateAllocation();
            }
        });
    });

    // Form submit validation
    form.addEventListener('submit', function(e) {
        const isAllocValid = validateAllocation();
        const isVehValid = validateVehicle();
        const isDriverValid = validateDriverName();
        const isContactValid = validateDriverContact();
        const isTransValid = validateTransporter();
        const isBoxesValid = validateTotalBoxes();
        const isDateValid = validateDispatchDate();

        const allValid = isAllocValid && isVehValid && isDriverValid && isContactValid && isTransValid && isBoxesValid && isDateValid;

        if (!allValid) {
            e.preventDefault();
            e.stopPropagation();

            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        const submitBtn = document.getElementById('submitOutwardBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Dispatching...';
        }
    });
});
</script>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAL: VIEW ALLOCATION OUTWARD DETAILS & ITEMS BREAKDOWN -->
<!-- ========================================================================= -->
<div class="modal fade" id="viewAllocationOutwardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-truck me-2 text-primary"></i>Outward Dispatch Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center py-4 text-muted" id="vaodLoadingSpinner">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div>Loading outward dispatch details...</div>
                </div>
                <div id="vaodContent" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light border mb-3 flex-wrap gap-2">
                        <div>
                            <span class="text-uppercase small text-muted fw-bold d-block">Outward Document #</span>
                            <span class="fs-5 fw-bold text-primary font-monospace" id="vaodOutwardNumber">—</span>
                        </div>
                        <div>
                            <span class="badge bg-success px-3 py-2 fs-7" id="vaodStatusBadge">DISPATCHED</span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <div class="card bg-light border-0 p-3 rounded-3 h-100">
                                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-diagram-3 text-primary me-1"></i>Allocation &amp; Route</h6>
                                <div class="small mb-1"><span class="text-muted">Allocation #:</span> <strong id="vaodAllocNumber" class="font-monospace text-primary"></strong></div>
                                <div class="small mb-1"><span class="text-muted">Source Warehouse:</span> <strong id="vaodSourceWh"></strong></div>
                                <div class="small mb-1"><span class="text-muted">Destination:</span> <strong id="vaodDestination"></strong></div>
                                <div class="small mb-1"><span class="text-muted">Dispatched By:</span> <span id="vaodDispatchedBy"></span></div>
                                <div class="small"><span class="text-muted">Dispatch Date:</span> <span id="vaodDispatchDate"></span></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="card bg-light border-0 p-3 rounded-3 h-100">
                                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-truck text-success me-1"></i>Vehicle &amp; Transport Info</h6>
                                <div class="small mb-1"><span class="text-muted">Vehicle #:</span> <strong id="vaodVehicleNum" class="font-monospace text-dark"></strong></div>
                                <div class="small mb-1"><span class="text-muted">Transporter:</span> <span id="vaodTransporter"></span></div>
                                <div class="small mb-1"><span class="text-muted">Driver Name &amp; Contact:</span> <span id="vaodDriver"></span></div>
                                <div class="small mb-1"><span class="text-muted">LR / Note #:</span> <span id="vaodLrNum" class="font-monospace"></span></div>
                                <div class="small"><span class="text-muted">Total Pieces / Boxes:</span> <strong id="vaodPiecesBoxes" class="text-success"></strong></div>
                            </div>
                        </div>
                    </div>

                    <div id="vaodNotesContainer" class="alert alert-info py-2 px-3 small mb-3" style="display:none;">
                        <strong><i class="bi bi-info-circle me-1"></i>Dispatch Notes:</strong> <span id="vaodNotes"></span>
                    </div>

                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-boxes text-primary me-1"></i>Dispatched Products Breakdown</h6>
                    <div class="table-responsive border rounded-3 mb-2" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>#</th>
                                    <th>Product / Style</th>
                                    <th>SKU &amp; Barcode</th>
                                    <th>Size / Color</th>
                                    <th class="text-end">Dispatched Qty</th>
                                </tr>
                            </thead>
                            <tbody id="vaodItemsTbody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
                <a id="vaodGatepassBtn" href="#" target="_blank" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-printer me-1"></i> Print Transport Gatepass / QR
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDIT ALLOCATION OUTWARD DISPATCH -->
<!-- ========================================================================= -->
<div class="modal fade" id="editAllocationOutwardModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div style="background:#4f46e5;color:#fff;width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white">Edit Warehouse Outward Dispatch</h5>
                        <small class="text-white-50">Update vehicle details, transporter, boxes, status and tracking notes</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAllocationOutwardForm" method="POST" action="">
                <?= CSRF::field() ?>
                <div class="modal-body p-4 bg-light">
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-white border mb-3 flex-wrap gap-2">
                        <div>
                            <span class="text-uppercase small text-muted fw-bold d-block">Outward #</span>
                            <span class="fs-6 fw-bold text-primary font-monospace" id="editAoOutwardNumber">—</span>
                        </div>
                        <div>
                            <span class="text-uppercase small text-muted fw-bold d-block">Allocation #</span>
                            <span class="badge bg-secondary font-monospace" id="editAoAllocNumber">—</span>
                        </div>
                    </div>

                    <div class="card border-0 shadow-xs rounded-3 bg-white mb-3">
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                <i class="bi bi-truck text-primary me-1.5"></i>Vehicle &amp; Transporter Information
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Vehicle / Transport Reg Number <span class="text-danger">*</span></label>
                                    <input type="text" id="editAoVehicleNumber" name="vehicle_number" class="form-control form-control-sm text-uppercase fw-semibold" placeholder="e.g. TN38AB1234 or Handover" required>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Transporter / Logistics Name</label>
                                    <input type="text" id="editAoTransporterName" name="transporter_name" class="form-control form-control-sm" placeholder="e.g. VRL Logistics, Professional Courier">
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Driver / Courier Person Name</label>
                                    <input type="text" id="editAoDriverName" name="driver_name" class="form-control form-control-sm" placeholder="Driver name">
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Driver / Dispatch Contact Phone</label>
                                    <input type="tel" id="editAoDriverContact" name="driver_contact" class="form-control form-control-sm font-monospace" placeholder="10-digit mobile number">
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">LR / Consignment Tracking Number</label>
                                    <input type="text" id="editAoLrNumber" name="lr_number" class="form-control form-control-sm font-monospace" placeholder="LR / AWB Number">
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Total Packed Boxes / Cartons <span class="text-danger">*</span></label>
                                    <input type="number" id="editAoTotalBoxes" name="total_boxes" class="form-control form-control-sm text-center fw-bold" min="1" step="1" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-xs rounded-3 bg-white">
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                <i class="bi bi-clock-history text-success me-1.5"></i>Dispatch Schedule &amp; Delivery Status
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Dispatch Date &amp; Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" id="editAoDispatchDate" name="dispatch_date" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Delivery Status</label>
                                    <select id="editAoStatus" name="status" class="form-select form-select-sm fw-semibold">
                                        <option value="DISPATCHED">DISPATCHED (In Transit)</option>
                                        <option value="DELIVERED">DELIVERED (Delivered &amp; Received)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Outward Notes / Special Instructions</label>
                                    <textarea id="editAoNotes" name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Fragile garments, seal sticker verified"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="bi bi-info-circle me-1 text-primary"></i>Changes will update the outward dispatch ledger record.</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-semibold px-4 shadow-sm" id="btnSubmitEditAllocationOutward">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let vaodModalInstance = null;
let editAoModalInstance = null;

function viewAllocationOutwardDetails(outwardId) {
    const modalEl = document.getElementById('viewAllocationOutwardModal');
    if (!vaodModalInstance) vaodModalInstance = new bootstrap.Modal(modalEl);

    document.getElementById('vaodLoadingSpinner').style.display = 'block';
    document.getElementById('vaodContent').style.display = 'none';
    vaodModalInstance.show();

    fetch('<?= url("warehouse/allocation/outward/view-ajax/") ?>' + outwardId)
        .then(r => r.json())
        .then(data => {
            document.getElementById('vaodLoadingSpinner').style.display = 'none';
            if (!data.success) {
                alert(data.message || 'Error loading outward details.');
                vaodModalInstance.hide();
                return;
            }

            const o = data.outward || {};
            const items = data.items || [];

            document.getElementById('vaodOutwardNumber').textContent = o.outward_number || ('#' + o.id);
            const statusBadge = document.getElementById('vaodStatusBadge');
            statusBadge.textContent = o.status || 'DISPATCHED';
            statusBadge.className = 'badge px-3 py-2 fs-7 ' + (o.status === 'DELIVERED' ? 'bg-success' : 'bg-primary');

            document.getElementById('vaodAllocNumber').textContent = o.allocation_number || ('ALC #' + o.allocation_id);
            document.getElementById('vaodSourceWh').textContent = (o.warehouse_name || '') + (o.warehouse_code ? ' (' + o.warehouse_code + ')' : '');
            document.getElementById('vaodDestination').textContent = o.destination_display || o.alloc_dest_type || '—';
            document.getElementById('vaodDispatchedBy').textContent = o.created_by_name || 'Staff';
            document.getElementById('vaodDispatchDate').textContent = o.dispatch_date ? new Date(o.dispatch_date).toLocaleString() : '—';

            document.getElementById('vaodVehicleNum').textContent = o.vehicle_number || 'Handover';
            document.getElementById('vaodTransporter').textContent = o.transporter_name || '—';
            document.getElementById('vaodDriver').textContent = (o.driver_name || '—') + (o.driver_contact ? ' (' + o.driver_contact + ')' : '');
            document.getElementById('vaodLrNum').textContent = o.lr_number || '—';
            document.getElementById('vaodPiecesBoxes').textContent = (o.total_quantity || 0) + ' pcs (' + (o.total_boxes || 1) + ' box)';

            const notesEl = document.getElementById('vaodNotes');
            const notesCont = document.getElementById('vaodNotesContainer');
            if (o.notes) {
                notesEl.textContent = o.notes;
                notesCont.style.display = 'block';
            } else {
                notesCont.style.display = 'none';
            }

            // Populate items table
            const tbody = document.getElementById('vaodItemsTbody');
            tbody.innerHTML = '';
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No line items found.</td></tr>';
            } else {
                items.forEach((it, idx) => {
                    const row = document.createElement('tr');
                    const qty = it.dispatched_qty > 0 ? it.dispatched_qty : (it.packed_qty > 0 ? it.packed_qty : it.requested_qty);
                    const colorDot = it.color_hex ? `<span class="rounded-circle border d-inline-block me-1" style="width:10px;height:10px;background:${it.color_hex};vertical-align:middle;"></span>` : '';
                    row.innerHTML = `
                        <td class="text-muted">${idx + 1}</td>
                        <td>
                            <strong class="text-dark">${it.product_name || 'Product'}</strong>
                            <small class="text-muted d-block">${it.product_code || ''}</small>
                        </td>
                        <td>
                            <span class="font-monospace fw-semibold text-primary">${it.sku || '—'}</span>
                            ${it.barcode ? `<small class="text-muted d-block font-monospace"><i class="bi bi-qr-code me-0.5"></i>${it.barcode}</small>` : ''}
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">${it.size_name || 'Free'}</span>
                            <span class="small text-muted ms-1">${colorDot}${it.color_name || 'Std'}</span>
                        </td>
                        <td class="text-end fw-bold text-success fs-7">
                            ${qty} pcs
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }

            const gatepassBtn = document.getElementById('vaodGatepassBtn');
            if (data.gatepass_url) {
                gatepassBtn.href = data.gatepass_url;
                gatepassBtn.style.display = 'inline-block';
            } else {
                gatepassBtn.style.display = 'none';
            }

            document.getElementById('vaodContent').style.display = 'block';
        })
        .catch(err => {
            document.getElementById('vaodLoadingSpinner').style.display = 'none';
            alert('Request failed: ' + err.message);
            vaodModalInstance.hide();
        });
}

function openEditAllocationOutwardModal(o) {
    if (!o) return;
    const modalEl = document.getElementById('editAllocationOutwardModal');
    if (!editAoModalInstance) editAoModalInstance = new bootstrap.Modal(modalEl);

    document.getElementById('editAllocationOutwardForm').action = '<?= url("warehouse/allocation/outward/update/") ?>' + o.id;
    document.getElementById('editAoOutwardNumber').textContent = o.outward_number || ('#' + o.id);
    document.getElementById('editAoAllocNumber').textContent = o.allocation_number || ('ALC #' + o.allocation_id);

    document.getElementById('editAoVehicleNumber').value = o.vehicle_number || '';
    document.getElementById('editAoTransporterName').value = o.transporter_name || '';
    document.getElementById('editAoDriverName').value = o.driver_name || '';
    document.getElementById('editAoDriverContact').value = o.driver_contact || '';
    document.getElementById('editAoLrNumber').value = o.lr_number || '';
    document.getElementById('editAoTotalBoxes').value = o.total_boxes || 1;

    // Format dispatch_date for datetime-local (YYYY-MM-DDTHH:MM)
    if (o.dispatch_date) {
        const d = new Date(o.dispatch_date);
        const pad = num => String(num).padStart(2, '0');
        const formatted = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        document.getElementById('editAoDispatchDate').value = formatted;
    } else {
        document.getElementById('editAoDispatchDate').value = '';
    }

    document.getElementById('editAoStatus').value = o.status || 'DISPATCHED';
    document.getElementById('editAoNotes').value = o.notes || '';

    editAoModalInstance.show();
}
</script>
