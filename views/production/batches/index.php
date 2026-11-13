<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-box-seam me-2 text-success"></i>Finished Production Batches</h2>
        <span class="text-muted">Finished goods packing boxes, destination warehouse assignment & approval</span>
    </div>
    <?php if (hasPermission('batches', 'create')): ?>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= url('production/billing/create') ?>" class="btn btn-outline-success">
            <i class="bi bi-receipt me-1"></i> New Production Bill
        </a>
        <a href="<?= url('production/batches/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Pack New Batch
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('production/batches') ?>" id="batchesFilterForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="batchesStatusFilter" class="form-label small text-muted fw-semibold">Status</label>
                <select name="status" id="batchesStatusFilter" class="form-select form-select-sm" onchange="document.getElementById('batchesFilterForm').submit()">
                    <option value="">All Statuses</option>
                    <option value="PREPARING" <?= ($filters['status'] ?? '') === 'PREPARING' ? 'selected' : '' ?>>Preparing / Packed</option>
                    <option value="APPROVED" <?= ($filters['status'] ?? '') === 'APPROVED' ? 'selected' : '' ?>>Approved for Dispatch</option>
                    <option value="DISPATCHED" <?= ($filters['status'] ?? '') === 'DISPATCHED' ? 'selected' : '' ?>>Dispatched</option>
                    <option value="RECEIVED" <?= ($filters['status'] ?? '') === 'RECEIVED' ? 'selected' : '' ?>>Received at WH</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="batchesWarehouseFilter" class="form-label small text-muted fw-semibold">Destination Warehouse</label>
                <select name="warehouse_id" id="batchesWarehouseFilter" class="form-select form-select-sm" onchange="document.getElementById('batchesFilterForm').submit()">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= $w['id'] ?>" <?= (($filters['destination_warehouse_id'] ?? $filters['warehouse_id'] ?? '') == $w['id']) ? 'selected' : '' ?>>
                            <?= e($w['name']) ?> (<?= e($w['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="batchesSearchInput" class="form-label small text-muted fw-semibold">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           name="search" 
                           id="batchesSearchInput" 
                           class="form-control form-control-sm border-start-0" 
                           placeholder="Batch #, Program #, Product name, Warehouse..." 
                           value="<?= e($filters['search'] ?? '') ?>" 
                           autocomplete="off">
                    <?php if (!empty($filters['search'])): ?>
                        <button type="button" class="btn btn-outline-secondary border-start-0" onclick="clearBatchSearch()" title="Clear search">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Filter"><i class="bi bi-funnel me-1"></i> Filter</button>
                <?php if (!empty($filters['status']) || !empty($filters['destination_warehouse_id']) || !empty($filters['warehouse_id']) || !empty($filters['search'])): ?>
                    <a href="<?= url('production/batches') ?>" class="btn btn-outline-danger btn-sm" title="Reset All Filters"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Batches Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="prodBatchesTable">
                <thead class="table-light">
                    <tr>
                        <th>Batch #</th>
                        <th>Program</th>
                        <th>Product</th>
                        <th>Total Pieces</th>
                        <th>Box Count</th>
                        <th>Destination Warehouse</th>
                        <th>Packed By</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="batchesTableBody">
                    <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                <h6 class="fw-semibold mb-1"><?= (!empty($filters['status']) || !empty($filters['destination_warehouse_id']) || !empty($filters['search'])) ? 'No Matching Production Batches Found' : 'No Finished Production Batches Found' ?></h6>
                                <p class="small text-muted mb-3">
                                    <?= (!empty($filters['status']) || !empty($filters['destination_warehouse_id']) || !empty($filters['search'])) 
                                        ? 'Try clearing or changing your filter criteria.' 
                                        : 'Batches will appear here once goods are packed from QC inspection.' ?>
                                </p>
                                <?php if (!empty($filters['status']) || !empty($filters['destination_warehouse_id']) || !empty($filters['search'])): ?>
                                    <a href="<?= url('production/batches') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <?php 
                                $statusClass = match($b['status']) {
                                    'APPROVED'   => 'bg-success',
                                    'DISPATCHED' => 'bg-primary',
                                    'RECEIVED'   => 'bg-info text-dark',
                                    default      => 'bg-warning text-dark'
                                };

                                $searchData = strtolower(
                                    ($b['batch_number'] ?? '') . ' ' . 
                                    ($b['program_number'] ?? '') . ' ' . 
                                    ($b['product_name'] ?? '') . ' ' . 
                                    ($b['warehouse_name'] ?? '') . ' ' . 
                                    ($b['warehouse_code'] ?? '') . ' ' . 
                                    ($b['prepared_by_name'] ?? '') . ' ' . 
                                    ($b['status'] ?? '') . ' ' . 
                                    'batch #' . ($b['id'] ?? '') . ' ' . 
                                    'program #' . ($b['program_id'] ?? '')
                                );
                            ?>
                            <tr class="prod-batch-row" 
                                data-batch-id="<?= $b['id'] ?>"
                                data-status="<?= e($b['status']) ?>"
                                data-warehouse-id="<?= $b['destination_warehouse_id'] ?? '' ?>"
                                data-search-text="<?= e($searchData) ?>">
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?= e($b['batch_number']) ?>
                                        <?php if (!empty($b['is_multi_qc'])): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 10px;" title="Combined Multiple QC Batches">
                                                <i class="bi bi-collection me-1"></i>Multi-QC
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= formatDate($b['created_at']) ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($b['program_id'])): ?>
                                        <a href="<?= url('production/programs/view/' . $b['program_id']) ?>" class="text-decoration-none fw-semibold">
                                            <?= e($b['program_number'] ?: ('Program #' . $b['program_id'])) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($b['product_name'] ?: '—') ?></div>
                                </td>
                                <td class="text-success fw-bold"><?= number_format($b['total_qty']) ?> pcs</td>
                                <td><span class="fw-semibold"><?= number_format($b['box_count']) ?></span> boxes</td>
                                <td><span class="badge bg-light text-dark border"><?= e($b['warehouse_name'] ?: 'Unassigned') ?></span></td>
                                <td><?= e($b['prepared_by_name'] ?: 'Staff') ?></td>
                                <td><span class="badge <?= $statusClass ?>"><?= e($b['status']) ?></span></td>
                                <td class="text-end">
                                    <?php
                                        $isDispatched = ($b['status'] === 'DISPATCHED' || $b['status'] === 'RECEIVED' || !empty($b['outward_id']) || !empty($b['production_outward_id']));
                                        $billUrl = url('production/batches/bill/' . $b['id']);
                                        $invUrl = !empty($b['production_bill_id']) ? url('production/billing/print/' . (int)$b['production_bill_id']) : url('production/batches/invoice/' . $b['id']);
                                    ?>
                                    <div class="action-btns-group justify-content-end d-flex gap-1">
                                        <!-- 1. View Batch Details -->
                                        <a href="<?= url('production/batches/view/' . $b['id']) ?>" class="btn btn-outline-info btn-action-icon" title="View Batch Details">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- 2. Edit Batch Details -->
                                        <?php if (hasPermission('batches', 'edit')): ?>
                                            <button type="button" class="btn btn-outline-primary btn-action-icon" onclick='openEditBatchModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Batch Details">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- 3. Batch Transfer Bill -->
                                        <a href="<?= $billUrl ?>" target="_blank" class="btn btn-outline-secondary btn-action-icon" title="Print Batch Delivery Bill / Challan">
                                            <i class="bi bi-file-earmark-ruled"></i>
                                        </a>

                                        <!-- 4. Tax Invoice for Products -->
                                        <a href="<?= $invUrl ?>" target="_blank" class="btn btn-outline-success btn-action-icon" title="Print Tax Invoice for this Batch">
                                            <i class="bi bi-receipt"></i>
                                        </a>

                                        <!-- 5. Dispatch / Approve Action (Current unchanged) -->
                                        <?php if ($b['status'] === 'PREPARING' && hasPermission('batches', 'approve')): ?>
                                            <form method="POST" action="<?= url('production/batches/approve/' . $b['id']) ?>" class="d-inline" onsubmit="return confirm('Approve this batch for outward dispatch?');">
                                                <?= CSRF::field() ?>
                                                <button type="submit" class="btn btn-outline-success btn-action-icon" title="Approve Batch for Dispatch">
                                                    <i class="bi bi-patch-check"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($b['status'] === 'APPROVED' && hasPermission('outward', 'create')): ?>
                                            <a href="<?= url('production/outward') ?>" class="btn btn-outline-success btn-action-icon" title="Dispatch Outward">
                                                <i class="bi bi-truck"></i>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary btn-action-icon" onclick="viewBatchDispatchDetails(<?= $b['id'] ?>)" title="Outward Dispatched Details">
                                                <i class="bi bi-truck-flatbed"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- 6. Print Carton QR Label -->
                                        <a href="<?= url('production/batches/label/' . $b['id']) ?>" target="_blank" class="btn btn-outline-dark btn-action-icon" title="Print Carton QR Label">
                                            <i class="bi bi-qr-code"></i>
                                        </a>

                                        <!-- 7. Delete Batch -->
                                        <?php if (hasPermission('batches', 'delete')): ?>
                                            <form method="POST" action="<?= url('production/batches/delete/' . $b['id']) ?>" class="d-inline" onsubmit="return confirm('Delete Batch <?= e($b['batch_number']) ?>? All garments and QR codes will be returned to QC inventory.');">
                                                <?= CSRF::field() ?>
                                                <button type="submit" class="btn btn-outline-danger btn-action-icon" title="Delete Batch (Return to QC)">
                                                    <i class="bi bi-trash3"></i>
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
    <!-- Pagination Controls Footer -->
    <div class="card-footer bg-white py-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-3" id="batchesPaginationFooter">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="small text-muted fw-medium" id="batchesPageInfo">
                Showing 1 to 10 of <?= count($batches) ?> batches
            </span>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0 fw-medium">Show:</label>
                <select id="batchesPageSizeSelect" class="form-select form-select-sm" style="width: auto;">
                    <option value="10" selected>10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                    <option value="all">All</option>
                </select>
            </div>
        </div>
        <nav aria-label="Batches Pagination">
            <ul class="pagination pagination-modern mb-0" id="batchesPaginationList">
            </ul>
        </nav>
    </div>
</div>

<!-- Modal: View Dispatched Batch & Product Breakdown -->
<div class="modal fade" id="viewBatchDispatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="vbdModalTitle">
                    <i class="bi bi-truck me-2 text-primary"></i>Batch Outward Dispatch Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="vbdModalBody">
                <div class="text-center py-4 text-muted" id="vbdLoadingSpinner">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div>Loading dispatch details...</div>
                </div>
                <div id="vbdContent" style="display: none;">
                    <!-- Status & Outward Header Banner -->
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light border mb-4 flex-wrap gap-2">
                        <div>
                            <span class="text-uppercase small text-muted fw-bold d-block">Outward Document #</span>
                            <span class="fs-5 fw-bold text-primary font-monospace" id="vbdOutwardNumber">—</span>
                        </div>
                        <div>
                            <span class="badge px-3 py-2 fs-7" id="vbdStatusBadge">DISPATCHED</span>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-box-seam me-1 text-primary"></i> Batch &amp; Product Info</div>
                                <div class="mb-1"><strong>Batch Number:</strong> <span class="font-monospace text-dark fw-bold" id="vbdBatchNumber">—</span></div>
                                <div class="mb-1"><strong>Product:</strong> <span class="text-dark fw-bold" id="vbdProductName">—</span></div>
                                <div class="mb-1"><strong>Program:</strong> <span class="text-primary fw-bold" id="vbdProgramNumber">—</span></div>
                                <div><strong>Destination:</strong> <span class="badge bg-primary-subtle text-primary border" id="vbdWarehouseName">—</span></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-truck me-1 text-primary"></i> Transport &amp; Dispatch Details</div>
                                <div class="mb-1"><strong>Vehicle / Courier #:</strong> <span class="badge bg-dark font-monospace" id="vbdVehicleNumber">—</span></div>
                                <div class="mb-1"><strong>Dispatched Date:</strong> <span class="text-dark" id="vbdDispatchDate">—</span></div>
                                <div class="mb-1"><strong>Dispatched By:</strong> <span class="text-dark" id="vbdDispatchedBy">—</span></div>
                                <div><strong>Packed Quantity:</strong> <span class="text-success fw-bold" id="vbdTotalQty">—</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Dispatch Notes -->
                    <div class="mb-4" id="vbdNotesContainer" style="display: none;">
                        <div class="small fw-bold text-muted mb-1">Dispatch Notes / Gate Pass:</div>
                        <div class="p-2 bg-white border rounded small text-dark" id="vbdNotes"></div>
                    </div>

                    <!-- Variant Items Breakdown Table -->
                    <div>
                        <h6 class="fw-bold mb-2"><i class="bi bi-layers-fill text-primary me-1"></i> Dispatched Variant Items Breakdown</h6>
                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>SKU / Variant</th>
                                        <th>Size</th>
                                        <th>Color</th>
                                        <th class="text-end">Quantity (pcs)</th>
                                        <th>Outward Piece QR Codes</th>
                                    </tr>
                                </thead>
                                <tbody id="vbdItemsTableBody">
                                    <!-- Populated via Ajax -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Edit Finished Production Batch -->
<div class="modal fade" id="editBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil-square me-2"></i>Edit Finished Production Batch
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editBatchForm" action="">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3 p-3 rounded-3 bg-light border mb-4">
                        <div class="col-md-5 col-12">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="bi bi-upc-scan text-primary me-1"></i>Batch Number / Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="batch_number" id="editBatchNumberInput" class="form-control form-control-sm font-monospace fw-bold text-uppercase" placeholder="e.g. BAT-20261110-0001" required>
                        </div>
                        <div class="col-md-7 col-12">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Program &amp; Product</label>
                            <div class="form-control form-control-sm bg-white fw-semibold text-truncate text-dark" id="editBatchProgramProduct" readonly>—</div>
                        </div>
                    </div>

                    <!-- Multiple Batch Selection & Combined QC Batches -->
                    <div class="p-3 rounded-3 bg-light border mb-4" id="editBatchMultiBatchCard">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <label class="form-label small fw-bold text-dark mb-0">
                                <i class="bi bi-collection text-primary me-1"></i>Multiple Batch Selection (Combined QC Batches)
                            </label>
                            <span class="badge bg-primary text-white" id="editBatchMultiCountBadge" style="display: none;">
                                <i class="bi bi-patch-check me-1"></i><span id="editBatchCombinedCount">1</span> QC Batches Combined
                            </span>
                        </div>
                        
                        <!-- Combined Batches Chips Display -->
                        <div class="mb-2">
                            <span class="small text-muted fw-semibold d-block mb-1">Batches Combined in this Pack:</span>
                            <div class="d-flex flex-wrap gap-1 align-items-center" id="editBatchCombinedChips">
                                <span class="badge bg-primary text-white border" id="editBatchPrimaryBadge">
                                    <i class="bi bi-check-circle me-1"></i>Primary: QC #—
                                </span>
                            </div>
                        </div>

                        <!-- Additional QC Batches Multi-Select Dropdown -->
                        <div class="mt-2 pt-2 border-top" id="editBatchCombineSelectArea">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="bi bi-plus-circle me-1 text-primary"></i>Combine Additional QC Batches:
                            </label>
                            <select name="additional_qc_batch_ids[]" id="editBatchAdditionalQcSelect" class="form-select form-select-sm" multiple size="3" onchange="onEditBatchMultiSelectChange()">
                                <!-- Dynamically populated -->
                            </select>
                            <div class="form-text small text-muted mt-1" style="font-size: 11px;">
                                <i class="bi bi-info-circle me-1"></i>Hold <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd>) to select or unselect multiple QC batches to combine into this pack.
                            </div>
                            <div id="editBatchNoEligibleMsg" class="small text-muted mt-1 fst-italic" style="display: none;">
                                <i class="bi bi-check2-circle text-success me-1"></i>No other pending QC batches available for this program.
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Destination Warehouse <span class="text-danger">*</span></label>
                            <select name="destination_warehouse_id" id="editBatchWarehouse" class="form-select fw-semibold" required>
                                <option value="">Select Destination Warehouse</option>
                                <?php foreach ($warehouses as $w): ?>
                                    <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?> (<?= e($w['code'] ?? 'WH') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold text-dark">Total Boxes <span class="text-danger">*</span></label>
                            <input type="number" name="box_count" id="editBatchBoxCount" class="form-control fw-bold" min="1" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold text-dark">Batch Status</label>
                            <select name="status" id="editBatchStatus" class="form-select fw-semibold">
                                <option value="PREPARING">PREPARING</option>
                                <option value="APPROVED">APPROVED</option>
                                <option value="DISPATCHED">DISPATCHED</option>
                                <option value="RECEIVED">RECEIVED</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark">Packing Notes / Special Instructions</label>
                            <textarea name="notes" id="editBatchNotes" class="form-control" rows="2" placeholder="e.g. Box labeling details, barcode stickers attached, destination instructions..."></textarea>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0"><i class="bi bi-layers-fill text-primary me-1"></i> Packed Variant Items Breakdown</h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">Editable Quantities</span>
                        </div>
                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th><i class="bi bi-qr-code me-1 text-primary"></i>QR Code</th>
                                        <th>Size</th>
                                        <th>Color</th>
                                        <th class="text-end" style="width: 140px;">Quantity (pcs)</th>
                                    </tr>
                                </thead>
                                <tbody id="editBatchItemsTbody">
                                    <tr>
                                        <td colspan="5" class="text-center py-2 text-muted small">Loading items...</td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td colspan="4" class="text-end small">Total Packed Quantity:</td>
                                        <td class="text-end text-primary"><span id="editBatchTotalQtyDisplay">0</span> pcs</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 bg-light border">
                        <h6 class="fw-bold mb-3 text-dark small text-uppercase"><i class="bi bi-truck text-primary me-1"></i> Logistics &amp; Transport Details (Optional)</h6>
                        <div class="row g-2">
                            <div class="col-md-6 col-12">
                                <label class="form-label small text-muted mb-1">Transporter Name</label>
                                <input type="text" name="transporter_name" id="editBatchTransporter" class="form-control form-control-sm" placeholder="e.g. VRL Logistics, Self Vehicle">
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label small text-muted mb-1">Vehicle Number</label>
                                <input type="text" name="vehicle_number" id="editBatchVehicle" class="form-control form-control-sm text-uppercase" placeholder="e.g. TN-38-AA-1234">
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label small text-muted mb-1">LR / Tracking / Waybill #</label>
                                <input type="text" name="lr_number" id="editBatchLrNumber" class="form-control form-control-sm" placeholder="e.g. LR-99201">
                            </div>
                            <div class="col-md-4 col-6">
                                <label class="form-label small text-muted mb-1">Driver Name</label>
                                <input type="text" name="driver_name" id="editBatchDriverName" class="form-control form-control-sm" placeholder="e.g. Ramesh">
                            </div>
                            <div class="col-md-4 col-6">
                                <label class="form-label small text-muted mb-1">Driver Contact Phone</label>
                                <input type="text" name="driver_contact" id="editBatchDriverContact" class="form-control form-control-sm" placeholder="e.g. 9876543210">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-check-circle me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function recalcEditBatchTotal() {
    let total = 0;
    document.querySelectorAll('.batch-item-qty').forEach(inp => {
        total += parseInt(inp.value || 0, 10);
    });
    const totalEl = document.getElementById('editBatchTotalQtyDisplay');
    if (totalEl) totalEl.textContent = total;
}

let currentEditBatchQcId = null;

function onEditBatchMultiSelectChange() {
    const select = document.getElementById('editBatchAdditionalQcSelect');
    const chipsContainer = document.getElementById('editBatchCombinedChips');
    const multiCountBadge = document.getElementById('editBatchMultiCountBadge');
    const countSpan = document.getElementById('editBatchCombinedCount');
    if (!chipsContainer || !select) return;

    let selectedOptions = Array.from(select.selectedOptions);
    let count = 1 + selectedOptions.length;

    // Base Primary Badge
    let chipsHtml = `<span class="badge bg-primary text-white border" id="editBatchPrimaryBadge">
        <i class="bi bi-check-circle me-1"></i>Primary: QC #${currentEditBatchQcId || '—'}
    </span>`;

    selectedOptions.forEach(opt => {
        chipsHtml += `<span class="badge bg-info-subtle text-dark border border-info">
            <i class="bi bi-collection me-1"></i>${escapeHtml(opt.text.split('[')[0].trim())}
        </span>`;
    });

    chipsContainer.innerHTML = chipsHtml;

    if (selectedOptions.length > 0) {
        if (multiCountBadge) multiCountBadge.style.display = 'inline-block';
        if (countSpan) countSpan.textContent = count;
    } else {
        if (multiCountBadge) multiCountBadge.style.display = 'none';
    }
}

const canEditBatches = <?= json_encode(hasPermission('batches', 'edit')) ?>;

function openEditBatchModal(batch) {
    if (!canEditBatches) {
        alert('Action restricted: You do not have permission to edit packaging batches.');
        return;
    }
    if (!batch) return;
    const form = document.getElementById('editBatchForm');
    if (form) {
        form.action = '<?= url('production/batches/update') ?>/' + batch.id;
    }
    const batchNumInput = document.getElementById('editBatchNumberInput');
    if (batchNumInput) {
        batchNumInput.value = batch.batch_number || '';
    }
    document.getElementById('editBatchProgramProduct').textContent = (batch.program_number ? batch.program_number + ' — ' : '') + (batch.product_name || 'Finished Goods');

    const whSelect = document.getElementById('editBatchWarehouse');
    if (whSelect) {
        whSelect.value = batch.destination_warehouse_id || '';
    }
    const boxInput = document.getElementById('editBatchBoxCount');
    if (boxInput) {
        boxInput.value = batch.box_count || 1;
    }
    const statusSelect = document.getElementById('editBatchStatus');
    if (statusSelect) {
        statusSelect.value = batch.status || 'PREPARING';
    }
    const notesInput = document.getElementById('editBatchNotes');
    if (notesInput) {
        notesInput.value = batch.notes || '';
    }

    // Reset logistics fields
    const transporterInput = document.getElementById('editBatchTransporter');
    const vehicleInput = document.getElementById('editBatchVehicle');
    const lrInput = document.getElementById('editBatchLrNumber');
    const driverNameInput = document.getElementById('editBatchDriverName');
    const driverContactInput = document.getElementById('editBatchDriverContact');
    if (transporterInput) transporterInput.value = '';
    if (vehicleInput) vehicleInput.value = '';
    if (lrInput) lrInput.value = '';
    if (driverNameInput) driverNameInput.value = '';
    if (driverContactInput) driverContactInput.value = '';

    const itemsTbody = document.getElementById('editBatchItemsTbody');
    const totalEl = document.getElementById('editBatchTotalQtyDisplay');
    if (totalEl) totalEl.textContent = parseInt(batch.total_qty || batch.quantity || 0, 10);

    if (itemsTbody) {
        itemsTbody.innerHTML = '<tr><td colspan="5" class="text-center py-2 text-muted small"><div class="spinner-border spinner-border-sm me-1"></div>Loading items...</td></tr>';
        fetch('<?= url('production/batches/view-ajax') ?>/' + batch.id)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.outward) {
                        if (transporterInput) transporterInput.value = data.outward.transporter_name || '';
                        if (vehicleInput) vehicleInput.value = data.outward.vehicle_number || '';
                        if (lrInput) lrInput.value = data.outward.lr_number || '';
                        if (driverNameInput) driverNameInput.value = data.outward.driver_name || '';
                        if (driverContactInput) driverContactInput.value = data.outward.driver_contact || '';
                    }

                    // Populate Multiple QC Batches
                    currentEditBatchQcId = batch.qc_batch_id || (data.qc_batch ? data.qc_batch.id : null);
                    const primaryBadge = document.getElementById('editBatchPrimaryBadge');
                    if (primaryBadge) {
                        primaryBadge.innerHTML = `<i class="bi bi-check-circle me-1"></i>Primary: QC #${currentEditBatchQcId || '—'}`;
                    }

                    const addSelect = document.getElementById('editBatchAdditionalQcSelect');
                    const noEligibleMsg = document.getElementById('editBatchNoEligibleMsg');
                    const selectedAddIds = Array.isArray(data.additional_qc_ids) ? data.additional_qc_ids.map(Number) : [];

                    if (addSelect) {
                        addSelect.innerHTML = '';
                        const batchMap = new Map();
                        if (data.multi_qc_batches && Array.isArray(data.multi_qc_batches)) {
                            data.multi_qc_batches.forEach(b => {
                                if (b && b.id && Number(b.id) !== Number(currentEditBatchQcId)) {
                                    batchMap.set(Number(b.id), b);
                                }
                            });
                        }
                        if (data.eligible_qc_batches && Array.isArray(data.eligible_qc_batches)) {
                            data.eligible_qc_batches.forEach(b => {
                                if (b && b.id && Number(b.id) !== Number(currentEditBatchQcId)) {
                                    if (!batchMap.has(Number(b.id))) {
                                        batchMap.set(Number(b.id), b);
                                    }
                                }
                            });
                        }

                        if (batchMap.size > 0) {
                            if (noEligibleMsg) noEligibleMsg.style.display = 'none';
                            addSelect.style.display = 'block';
                            batchMap.forEach((qb, qbId) => {
                                const opt = document.createElement('option');
                                opt.value = qbId;
                                const isSel = selectedAddIds.includes(qbId);
                                opt.selected = isSel;
                                const prog = qb.program_number ? (qb.program_number + ' — ') : '';
                                const prod = qb.product_name ? (qb.product_name + ' ') : '';
                                const qty = qb.passed_qty || qb.total_qc_passed || 0;
                                opt.textContent = `QC #${qbId} (${prog}${prod}) [${qty} pcs]`;
                                addSelect.appendChild(opt);
                            });
                        } else {
                            if (noEligibleMsg) noEligibleMsg.style.display = 'block';
                            addSelect.style.display = 'none';
                        }

                        onEditBatchMultiSelectChange();
                    }

                    if (data.items && data.items.length) {
                        let html = '';
                        let runTotal = 0;
                        data.items.forEach((it, idx) => {
                            const q = parseInt(it.quantity || 0, 10);
                            runTotal += q;
                            const qrCodeVal = (it.qr_code && it.qr_code.trim() !== '') ? it.qr_code : (it.sku || '—');
                            const skuVal = it.sku || '';
                            const hasDifferentSku = skuVal && skuVal !== qrCodeVal;

                            html += `<tr>
                                <td class="small text-muted">${idx + 1}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-light text-dark border font-monospace fw-bold" style="font-size: 11.5px; letter-spacing: 0.5px;">
                                            <i class="bi bi-qr-code text-primary me-1"></i>${escapeHtml(qrCodeVal)}
                                        </span>
                                    </div>
                                    ${hasDifferentSku ? `<div class="text-muted small mt-0.5" style="font-size: 10.5px;">SKU: ${escapeHtml(skuVal)}</div>` : ''}
                                </td>
                                <td class="small"><span class="badge bg-light text-dark border">${escapeHtml(it.size_name || 'STD')}</span></td>
                                <td class="small">${escapeHtml(it.color_name || 'Standard')}</td>
                                <td class="text-end">
                                    <input type="number" name="item_qty[${it.id}]" class="form-control form-control-sm text-end fw-bold batch-item-qty text-primary" min="0" value="${q}" oninput="recalcEditBatchTotal()">
                                </td>
                            </tr>`;
                        });
                        itemsTbody.innerHTML = html;
                        if (totalEl) totalEl.textContent = runTotal;
                    } else {
                        itemsTbody.innerHTML = '<tr><td colspan="5" class="text-center py-2 text-muted small">No variant items found</td></tr>';
                    }
                } else {
                    itemsTbody.innerHTML = '<tr><td colspan="5" class="text-center py-2 text-danger small">Failed to load batch details</td></tr>';
                }
            })
            .catch(() => {
                itemsTbody.innerHTML = '<tr><td colspan="5" class="text-center py-2 text-danger small">Error loading items</td></tr>';
            });
    }

    const modalEl = document.getElementById('editBatchModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function clearBatchSearch() {
    const searchInput = document.getElementById('batchesSearchInput');
    if (searchInput) {
        searchInput.value = '';
        document.getElementById('batchesFilterForm').submit();
    }
}

function viewBatchDispatchDetails(batchId) {
    const modalEl = document.getElementById('viewBatchDispatchModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const spinner = document.getElementById('vbdLoadingSpinner');
    const content = document.getElementById('vbdContent');
    if (spinner) spinner.style.display = 'block';
    if (content) content.style.display = 'none';

    fetch('<?= url("production/batches/view-ajax/") ?>/' + batchId)
        .then(res => {
            if (!res.ok) {
                throw new Error('Server returned status ' + res.status);
            }
            return res.json();
        })
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Error loading batch details.');
                modal.hide();
                return;
            }

            const batch = data.batch || {};
            const outward = data.outward || {};
            const items = data.items || [];

            const elBatchNum = document.getElementById('vbdBatchNumber');
            if (elBatchNum) elBatchNum.textContent = batch.batch_number || ('Batch #' + batch.id);

            const elProdName = document.getElementById('vbdProductName');
            if (elProdName) elProdName.textContent = batch.product_name || 'Finished Product';

            const elProgNum = document.getElementById('vbdProgramNumber');
            if (elProgNum) elProgNum.textContent = batch.program_number || ('Program #' + batch.program_id);

            const elWhName = document.getElementById('vbdWarehouseName');
            if (elWhName) elWhName.textContent = batch.warehouse_name || 'Unassigned';
            
            const totalQty = parseInt(batch.total_qty || 0);
            const boxCount = parseInt(batch.box_count || 1);
            const elTotalQty = document.getElementById('vbdTotalQty');
            if (elTotalQty) elTotalQty.textContent = totalQty.toLocaleString() + ' pcs (' + boxCount + ' ' + (boxCount === 1 ? 'box' : 'boxes') + ')';

            // Outward info
            const outNum = outward.outward_number || batch.outward_number || 'Direct Dispatch';
            const elOutNum = document.getElementById('vbdOutwardNumber');
            if (elOutNum) elOutNum.textContent = outNum;

            const vehicle = outward.vehicle_number || batch.vehicle_number || 'Direct Handover';
            const elVeh = document.getElementById('vbdVehicleNumber');
            if (elVeh) elVeh.textContent = vehicle;

            const dispDate = outward.dispatch_date || batch.dispatch_date || batch.updated_at || '—';
            const elDispDate = document.getElementById('vbdDispatchDate');
            if (elDispDate) elDispDate.textContent = dispDate;

            const dispBy = outward.created_by_name || batch.dispatched_by_name || 'Staff';
            const elDispBy = document.getElementById('vbdDispatchedBy');
            if (elDispBy) elDispBy.textContent = dispBy;

            // Status badge
            const statusBadge = document.getElementById('vbdStatusBadge');
            if (statusBadge) {
                const status = (batch.status || 'DISPATCHED').toUpperCase();
                statusBadge.textContent = status;
                if (status === 'RECEIVED') {
                    statusBadge.className = 'badge bg-info text-dark px-3 py-2 fs-7';
                } else {
                    statusBadge.className = 'badge bg-success px-3 py-2 fs-7';
                }
            }

            // Notes
            const notes = outward.notes || batch.outward_notes || '';
            const notesContainer = document.getElementById('vbdNotesContainer');
            const notesEl = document.getElementById('vbdNotes');
            if (notesContainer && notesEl) {
                if (notes.trim()) {
                    notesEl.textContent = notes;
                    notesContainer.style.display = 'block';
                } else {
                    notesContainer.style.display = 'none';
                }
            }

            // Variant Breakdown Table
            const tbody = document.getElementById('vbdItemsTableBody');
            if (tbody) {
                tbody.innerHTML = '';
                if (items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No individual variant breakdown records found.</td></tr>';
                } else {
                    items.forEach((it, idx) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="text-muted small">${idx + 1}</td>
                            <td class="font-monospace fw-bold text-dark">${escapeHtml(it.sku || it.product_sku || '—')}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary border font-monospace">${escapeHtml(it.size_name || 'Std')}</span></td>
                            <td><span class="badge bg-info-subtle text-info border font-monospace">${escapeHtml(it.color_name || 'Std')}</span></td>
                            <td class="text-end fw-bold text-success">${parseInt(it.quantity || 0).toLocaleString()} pcs</td>
                            <td><div class="d-flex flex-wrap gap-2 vbd-piece-qrs"></div></td>
                        `;
                        tbody.appendChild(row);

                        const qrList = row.querySelector('.vbd-piece-qrs');
                        const pieceTags = Array.isArray(it.outward_piece_tags) ? it.outward_piece_tags : [];
                        if (!pieceTags.length) {
                            qrList.innerHTML = '<span class="small text-muted">Individual piece QR tags are unavailable.</span>';
                            return;
                        }

                        pieceTags.forEach((tag, pieceIndex) => {
                            const pieceCard = document.createElement('div');
                            pieceCard.className = 'border rounded-2 p-1 text-center bg-white';
                            pieceCard.style.width = '76px';
                            pieceCard.title = `Piece ${pieceIndex + 1}: ${tag}`;

                            const qr = document.createElement('div');
                            qr.className = 'vbd-piece-qr mx-auto';
                            qr.setAttribute('aria-label', `QR code for ${tag}`);
                            const code = document.createElement('div');
                            code.className = 'font-monospace text-dark text-break mt-1';
                            code.style.fontSize = '0.62rem';
                            code.textContent = tag;
                            pieceCard.append(qr, code);
                            qrList.appendChild(pieceCard);

                            if (typeof QRCode === 'undefined') {
                                qr.textContent = 'QR unavailable';
                                qr.className += ' small text-danger';
                                return;
                            }
                            new QRCode(qr, {
                                text: tag,
                                width: 60,
                                height: 60,
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        });
                    });
                }
            }

            if (spinner) spinner.style.display = 'none';
            if (content) content.style.display = 'block';
        })
        .catch(err => {
            console.error('Batch dispatch modal error:', err);
            alert('Failed to fetch batch dispatch details: ' + (err.message || ''));
            modal.hide();
        });
}

let batchesPagination;
function initBatchesPagination() {
    const searchInput = document.getElementById('batchesSearchInput');
    const tableBody = document.getElementById('batchesTableBody');
    if (!tableBody) return;

    if (typeof window.initClientPagination === 'function') {
        batchesPagination = window.initClientPagination({
            container: '#batchesTableBody',
            itemSelector: 'tr.prod-batch-row',
            footer: '#batchesPaginationFooter',
            infoEl: '#batchesPageInfo',
            paginationList: '#batchesPaginationList',
            pageSizeSelect: '#batchesPageSizeSelect',
            emptyStateEl: '#noLiveBatchesResultsRow',
            pageSize: 10,
            itemName: 'batches'
        });
    }

    if (searchInput) {
        function filterRows() {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;
            const rows = tableBody.querySelectorAll('.prod-batch-row');
            const totalCount = rows.length;

            if (batchesPagination) {
                visibleCount = batchesPagination.filter(row => {
                    if (!query) return true;
                    const searchText = (row.getAttribute('data-search-text') || row.textContent || '').toLowerCase();
                    return searchText.includes(query);
                });
            } else {
                rows.forEach(function(row) {
                    const searchText = (row.getAttribute('data-search-text') || row.textContent || '').toLowerCase();
                    if (!query || searchText.includes(query)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            let noResultRow = document.getElementById('noLiveBatchesResultsRow');
            if (totalCount > 0 && visibleCount === 0 && query !== '') {
                if (!noResultRow) {
                    noResultRow = document.createElement('tr');
                    noResultRow.id = 'noLiveBatchesResultsRow';
                    noResultRow.innerHTML = `<td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-search fs-2 d-block mb-2 text-secondary"></i>
                        <h6 class="fw-bold text-secondary">No production batches matching "${escapeHtml(searchInput.value)}"</h6>
                        <small class="text-muted d-block mb-2">Try searching with a different keyword or clear the search filter.</small>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="clearBatchSearchLive()">Clear Search</button>
                    </td>`;
                    tableBody.appendChild(noResultRow);
                } else {
                    noResultRow.style.display = '';
                    const h6 = noResultRow.querySelector('h6');
                    if (h6) h6.textContent = `No production batches matching "${searchInput.value}"`;
                }
            } else if (noResultRow) {
                noResultRow.style.display = 'none';
            }
        }

        window.clearBatchSearchLive = function() {
            if (searchInput) {
                searchInput.value = '';
                filterRows();
            }
        };

        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(filterRows, 150);
        });

        if (searchInput.value.trim() !== '') {
            filterRows();
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBatchesPagination);
} else {
    initBatchesPagination();
}
</script>
