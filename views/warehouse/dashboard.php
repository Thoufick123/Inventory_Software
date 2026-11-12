<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h2 class="mb-0"><i class="bi bi-buildings me-2 text-primary"></i>Warehouse Operations Dashboard</h2>
            <?php 
            $currUser = currentUser();
            $assignedWH = $currUser['warehouse_name'] ?? null;
            if ($assignedWH): ?>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5 rounded-pill fs-7 d-inline-flex align-items-center">
                    <i class="bi bi-building-fill-check me-1"></i> Warehouse: <?= e($assignedWH) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        
        <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3 d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#warehouseReportExportModal">
            <i class="bi bi-file-earmark-arrow-down"></i> Export Reports
        </button>
        <span class="text-muted small d-none d-sm-inline ms-1">
            <i class="bi bi-calendar3 me-1"></i> <?= date('d M Y') ?>
        </span>
    </div>
</div>

<?php if (!empty($warehouseProfile)): ?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?php if (!empty($warehouseProfile['logo_url'])): ?>
            <img src="<?= e($warehouseProfile['logo_url']) ?>" alt="<?= e($warehouseProfile['name'] ?? 'Warehouse') ?> logo" style="max-width:140px;max-height:64px;object-fit:contain;">
        <?php else: ?>
            <div class="rounded-circle bg-primary-subtle text-primary p-3"><i class="bi bi-buildings fs-4"></i></div>
        <?php endif; ?>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h5 class="mb-0 fw-bold"><?= e($warehouseProfile['name'] ?? 'Warehouse') ?></h5>
                <?php if (!empty($warehouseProfile['code'])): ?><span class="badge bg-light text-dark border"><?= e($warehouseProfile['code']) ?></span><?php endif; ?>
            </div>
            <div class="small text-muted mt-1">
                <?php if (!empty($warehouseProfile['manager_name'])): ?>Manager: <?= e($warehouseProfile['manager_name']) ?> · <?php endif; ?>
                <?php if (!empty($warehouseProfile['address'])): ?><?= e($warehouseProfile['address']) ?><?php endif; ?>
                <?php if (!empty($warehouseProfile['phone'])): ?><?= !empty($warehouseProfile['address']) ? ' · ' : '' ?><?= e($warehouseProfile['phone']) ?><?php endif; ?>
                <?php if (!empty($warehouseProfile['gst_number'])): ?><?= (!empty($warehouseProfile['address']) || !empty($warehouseProfile['phone'])) ? ' · ' : '' ?>GSTIN: <?= e($warehouseProfile['gst_number']) ?><?php endif; ?>
            </div>
        </div>
        <?php if (!empty($warehouseProfile['id']) && hasPermission('dashboard', 'view') && (hasPermission('dashboard', 'edit') || hasPermission('warehouse', 'edit'))): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/dashboard?edit_type=warehouse&edit_id=' . (int)$warehouseProfile['id']) ?>"><i class="bi bi-pencil me-1"></i>Edit details</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Row 1: Stock & Vendor Inward Overview -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <?php if (hasPermission('stock', 'view')): ?><a href="<?= url('warehouse/stock') ?>" class="text-decoration-none"><?php endif; ?>
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #e6f4ea; color: #059669;">
                    <i class="bi bi-boxes fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Available Stock Units</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format((float)($total_stock_units ?? $totalStock['total_qty'] ?? 0)) ?></h3>
                    <small class="text-muted"><?= number_format((int)($totalStock['total_skus'] ?? 0)) ?> unique SKUs</small>
                </div>
            </div>
        </div>
        <?php if (hasPermission('stock', 'view')): ?></a><?php endif; ?>
    </div>
    <div class="col-sm-6 col-xl-3">
        <?php if (hasPermission('stock', 'view')): ?><a href="<?= url('warehouse/stock') ?>" class="text-decoration-none"><?php endif; ?>
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #eff6ff; color: #2563eb;">
                    <i class="bi bi-currency-rupee fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Inventory Valuation</div>
                    <h3 class="fw-bold mb-0 text-dark">₹<?= number_format((float)($stockValuation['total_value'] ?? 0), 2) ?></h3>
                    <small class="text-muted">Retail selling valuation</small>
                </div>
            </div>
        </div>
        <?php if (hasPermission('stock', 'view')): ?></a><?php endif; ?>
    </div>
    <div class="col-sm-6 col-xl-3">
        <?php if (hasPermission('inward', 'view')): ?><a href="<?= url('warehouse/inward') ?>" class="text-decoration-none"><?php endif; ?>
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #fef3c7; color: #d97706;">
                    <i class="bi bi-hourglass-split fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Inward Dispatches</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format((int)($pendingInwards['count'] ?? 0)) ?></h3>
                    <small class="text-muted"><?= number_format((float)($pendingInwards['total_qty'] ?? 0)) ?> pcs in transit</small>
                </div>
            </div>
        </div>
        <?php if (hasPermission('inward', 'view')): ?></a><?php endif; ?>
    </div>
    <div class="col-sm-6 col-xl-3">
        <?php if (hasPermission('wh_sellers', 'view')): ?><a href="<?= url('warehouse/sellers') ?>" class="text-decoration-none"><?php endif; ?>
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #f3e8ff; color: #7c3aed;">
                    <i class="bi bi-shop fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Third-Party Sellers</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format((int)($sellerStats['total_sellers'] ?? 0)) ?></h3>
                    <?php if (hasPermission('wh_sellers', 'view')): ?>
                        <small class="text-muted"><span class="text-decoration-none text-primary">View All Sellers &rarr;</span></small>
                    <?php else: ?>
                        <small class="text-muted">Active registered suppliers</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (hasPermission('wh_sellers', 'view')): ?></a><?php endif; ?>
    </div>
</div>

<!-- Row: Complementary Products & Sales Revenue -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-6">
        <?php if (hasPermission('stock', 'view')): ?>
        <a href="<?= url('warehouse/stock?tab=complementary') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white border-start border-4 border-warning">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #fffbeb; color: #d97706;">
                        <i class="bi bi-gift-fill fs-3 text-warning"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-dark small fw-bold text-uppercase d-flex align-items-center gap-1">
                                <i class="bi bi-gift-fill text-warning me-1"></i>Complementary Product Stock
                            </span>
                            <span class="badge bg-warning-subtle text-dark border border-warning small font-monospace"><?= number_format((int)($compStock['total_skus'] ?? 0)) ?> SKUs</span>
                        </div>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format((float)($compStock['total_qty'] ?? 0)) ?> <span class="fs-6 fw-normal text-muted">pcs available</span></h3>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Est. Potential Revenue: <strong class="text-success">₹<?= number_format((float)($compStock['total_valuation'] ?? 0), 2) ?></strong></small>
                            <small class="text-warning-emphasis fw-semibold">View Inventory &rarr;</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
        <?php endif; ?>
    </div>
    <div class="col-sm-6 col-xl-6">
        <?php if (hasPermission('stock', 'view')): ?>
        <a href="<?= url('warehouse/stock?tab=complementary') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white border-start border-4 border-success">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #ecfdf5; color: #059669;">
                        <i class="bi bi-cash-coin fs-3 text-success"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-dark small fw-bold text-uppercase d-flex align-items-center gap-1">
                                <i class="bi bi-graph-up-arrow text-success me-1"></i>Complementary Sales Revenue
                            </span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle small font-monospace"><?= number_format((float)($compSales['total_sold_qty'] ?? 0)) ?> pcs sold</span>
                        </div>
                        <h3 class="fw-bold mb-0 text-success">₹<?= number_format((float)($compSales['total_sold_revenue'] ?? 0), 2) ?></h3>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Realized revenue from sold complementary products</small>
                            <small class="text-success fw-semibold"><?= number_format((int)($compSales['total_orders'] ?? 0)) ?> orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Row 2: Dispatch, Allocations & Damage -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <?php if (hasPermission('allocation', 'view')): ?><a href="<?= url('warehouse/allocation') ?>" class="text-decoration-none"><?php endif; ?>
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #e0f2fe; color: #0284c7;">
                    <i class="bi bi-bag-check fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Active Shop Allocations</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format((int)($active_allocations ?? 0)) ?></h3>
                    <small class="text-muted">Requested / Picking / Packing</small>
                </div>
            </div>
        </div>
        <?php if (hasPermission('allocation', 'view')): ?></a><?php endif; ?>
    </div>
    <div class="col-sm-6 col-lg-4">
        <?php if (hasPermission('allocation_outward', 'view')): ?><a href="<?= url('warehouse/allocation/outward') ?>" class="text-decoration-none"><?php endif; ?>
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #ffe4e6; color: #e11d48;">
                    <i class="bi bi-truck fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Outward</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format((int)($total_outwards ?? $in_transit_transfers ?? 0)) ?></h3>
                    <small class="text-muted">Total outward dispatches</small>
                </div>
            </div>
        </div>
        <?php if (hasPermission('allocation_outward', 'view')): ?></a><?php endif; ?>
    </div>
    <div class="col-sm-6 col-lg-4">
        <?php if (hasPermission('damage', 'view')): ?><a href="<?= url('warehouse/damage') ?>" class="text-decoration-none"><?php endif; ?>
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 p-3 rounded-3 me-3" style="background-color: #f1f5f9; color: #475569;">
                    <i class="bi bi-exclamation-triangle fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Damaged / Quarantine</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format((float)($damagedStock['total_qty'] ?? $damaged_units ?? 0)) ?></h3>
                    <small class="text-muted">Pcs in quarantine / defect</small>
                </div>
            </div>
        </div>
        <?php if (hasPermission('damage', 'view')): ?></a><?php endif; ?>
    </div>
</div>

<!-- 💥 DEDICATED INCOMING SHOP RETURNS & DISPATCHES SECTION -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger rounded-pill px-3 py-2 fw-bold">
                <i class="bi bi-arrow-return-left me-1"></i> Incoming Shop Returns &amp; Dispatches
            </span>
            <span class="text-muted small">Awaiting Warehouse QC Receipt &amp; Stock Reclassification</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (($pending_returns ?? 0) > 0): ?>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-2 fw-semibold">
                    <i class="bi bi-hourglass-split me-1"></i> <?= (int)$pending_returns ?> Pending QC
                </span>
            <?php endif; ?>
            <?php if (hasPermission('returns', 'view')): ?>
                <a href="<?= url('warehouse/returns') ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                    View All Returns <i class="bi bi-arrow-right ms-1"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentReturns)): ?>
            <div class="text-center py-5 text-muted">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-2 shadow-sm" style="width: 50px; height: 50px;">
                    <i class="bi bi-check-circle text-success fs-3"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">No Pending Shop Returns</h6>
                <p class="text-muted small mb-0">All shop return dispatches have been inspected and reconciled into warehouse stock.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Return Number</th>
                            <th>Originating Shop Store</th>
                            <th>Destination Warehouse</th>
                            <th>Return Reason</th>
                            <th>Returned Volume</th>
                            <th>QC Status</th>
                            <th>Dispatched Date</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReturns as $ret): ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="font-monospace fw-bold text-danger"><?= e($ret['return_number']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width: 30px; height: 30px;">
                                            <i class="bi bi-shop small"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark small"><?= e($ret['source_name'] ?? 'Retail Store') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= e($ret['warehouse_name'] ?? 'WH1') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary small"><?= e($ret['reason'] ?? 'Store Return') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold">
                                        <?= (int)($ret['item_count'] ?? 1) ?> item(s) • <?= number_format((float)($ret['total_returned_qty'] ?? 0)) ?> pcs
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        $retStatus = $ret['status'] ?? 'RECEIVED';
                                        $stBadge = match($retStatus) {
                                            'RECEIVED'   => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                            'INSPECTING' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                            'CLASSIFIED', 'COMPLETED' => 'bg-success-subtle text-success border border-success-subtle',
                                            default      => 'bg-secondary-subtle text-secondary'
                                        };
                                    ?>
                                    <span class="badge <?= $stBadge ?> rounded-pill px-3 py-1 font-monospace">
                                        <?= e($retStatus) ?>
                                    </span>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= !empty($ret['created_at']) ? date('d M Y, h:i A', strtotime($ret['created_at'])) : '-' ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <?php if (hasPermission('returns', 'inspect')): ?>
                                            <a href="<?= url('warehouse/returns/inspect/' . $ret['id']) ?>" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm fw-semibold" title="Inspect and classify returned pieces into available or damaged pool">
                                                <i class="bi bi-search me-1"></i> Inspect &amp; QC
                                            </a>
                                        <?php elseif (hasPermission('returns', 'view')): ?>
                                            <a href="<?= url('warehouse/returns/view/' . $ret['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold" title="View return details">
                                                <i class="bi bi-eye me-1"></i> View
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
</div>

<!-- Dashboard Recent Activity Grid -->
<div class="row g-4 mb-4">
    <!-- Active Shop Allocations -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold text-gray-800"><i class="bi bi-shop me-2 text-primary"></i>Recent Shop Allocations</h5>
                <?php if (hasPermission('allocation', 'view')): ?>
                    <a href="<?= url('warehouse/allocation') ?>" class="btn btn-sm btn-link text-decoration-none">View All</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Allocation #</th>
                            <th>Destination</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAllocations)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No active allocations found.</td></tr>
                        <?php else: foreach ($recentAllocations as $alc): ?>
                            <tr>
                                <td class="fw-medium text-primary"><?= htmlspecialchars($alc['allocation_number']) ?></td>
                                <td><?= htmlspecialchars($alc['destination_name'] ?? $alc['destination_type']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= $alc['item_count'] ?> SKUs</span></td>
                                <td>
                                    <span class="badge bg-<?= $alc['status'] === 'DISPATCHED' ? 'success' : ($alc['status'] === 'REQUESTED' ? 'warning' : 'info') ?>">
                                        <?= htmlspecialchars($alc['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if (hasPermission('allocation', 'view')): ?>
                                        <a href="<?= url('warehouse/allocation/view/' . $alc['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pending Inward Queue -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold"><i class="bi bi-truck me-2 text-warning"></i>Inbound Dispatches Awaiting GRN</h6>
                <?php if (hasPermission('inward', 'view')): ?>
                    <a href="<?= url('warehouse/inward') ?>" class="btn btn-sm btn-link text-decoration-none">View All</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentInwardQueue)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
                        No pending dispatches awaiting receipt.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Outward #</th>
                                    <th>Batch / Product</th>
                                    <th>Warehouse</th>
                                    <th>Qty</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentInwardQueue as $inw): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary"><?= e($inw['outward_number'] ?? 'N/A') ?></span>
                                            <br><small class="text-muted"><?= e($inw['vehicle_number'] ?? 'Direct') ?></small>
                                        </td>
                                        <td>
                                            <span class="fw-semibold"><?= e($inw['batch_number']) ?></span>
                                            <br><small class="text-muted"><?= e($inw['product_name']) ?></small>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= e($inw['warehouse_name']) ?></span></td>
                                        <td><span class="badge bg-primary fs-6"><?= number_format($inw['total_qty']) ?> pcs</span></td>
                                        <td>
                                            <?php if (hasPermission('inward', 'view')): 
                                                $canInwVerify = hasPermission('inward', 'verify') || hasPermission('inward', 'create') || hasPermission('inward', 'edit');
                                            ?>
                                                <a href="<?= url('warehouse/inward/view/' . $inw['id']) ?>" class="btn btn-sm <?= $canInwVerify ? 'btn-success' : 'btn-outline-primary' ?>">
                                                    <i class="bi <?= $canInwVerify ? 'bi-check2-circle' : 'bi-eye' ?> me-1"></i><?= $canInwVerify ? 'Verify' : 'View' ?>
                                                </a>
                                            <?php endif; ?>
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

<div class="row g-4">
    <!-- Recent Outward Dispatches -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold text-gray-800"><i class="bi bi-truck me-2 text-primary"></i>Recent Outward Dispatches</h5>
                <?php if (hasPermission('allocation_outward', 'view')): ?>
                    <a href="<?= url('warehouse/allocation/outward') ?>" class="btn btn-sm btn-link text-decoration-none">View All</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Outward #</th>
                            <th>Destination / Channel</th>
                            <th>Boxes / Qty</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOutwards)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No outward dispatches logged.</td></tr>
                        <?php else: foreach ($recentOutwards as $ow): ?>
                            <tr>
                                <td class="fw-medium text-dark">
                                    <?php if (hasPermission('allocation', 'view')): ?>
                                        <a href="<?= url('warehouse/allocation/view/' . $ow['allocation_id']) ?>" class="text-decoration-none text-dark hover-primary">
                                            <?= htmlspecialchars($ow['outward_number'] ?? $ow['allocation_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ow['outward_number'] ?? $ow['allocation_number']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?= htmlspecialchars($ow['destination_name'] ?? $ow['shop_name'] ?? $ow['channel_name'] ?? 'Retail Shop / Channel') ?>
                                </td>
                                <td class="small">
                                    <?php $pcsCount = (int)($ow['real_total_pcs'] ?? ($ow['total_quantity'] > 0 ? $ow['total_quantity'] : ($ow['total_qty'] ?? 0))); ?>
                                    <span class="fw-bold text-success"><?= number_format($pcsCount) ?> pcs</span>
                                    <small class="text-muted d-block">(<?= (int)($ow['total_boxes'] ?? 1) ?> box)</small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= ($ow['status'] ?? '') === 'DELIVERED' || ($ow['status'] ?? '') === 'COMPLETED' ? 'success' : 'primary' ?>">
                                        <?= htmlspecialchars($ow['status'] ?? 'DISPATCHED') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Stock Movements -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>Recent Stock Movements</h6>
                <?php if (hasPermission('stock', 'view')): ?>
                    <a href="<?= url('warehouse/stock') ?>" class="btn btn-sm btn-link text-decoration-none">Full Ledger</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentTransactions)): ?>
                    <div class="text-center py-4 text-muted">No stock transactions recorded yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Product / SKU</th>
                                    <th>Qty</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $tx): ?>
                                    <tr>
                                        <td><small class="text-muted"><?= date('d M H:i', strtotime($tx['created_at'])) ?></small></td>
                                        <td>
                                            <span class="badge bg-<?= str_contains($tx['transaction_type'], 'GRN') || str_contains($tx['transaction_type'], 'IN') ? 'success' : (str_contains($tx['transaction_type'], 'SALE') || str_contains($tx['transaction_type'], 'OUT') ? 'danger' : 'info') ?>">
                                                <?= e($tx['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold"><?= e($tx['sku']) ?></span>
                                            <br><small class="text-muted"><?= e($tx['product_name']) ?></small>
                                        </td>
                                        <td class="fw-bold <?= $tx['quantity'] > 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= ($tx['quantity'] > 0 ? '+' : '') . number_format($tx['quantity']) ?>
                                        </td>
                                        <td class="fw-semibold"><?= number_format($tx['balance_after']) ?></td>
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


<!-- WAREHOUSE REPORT EXPORT MODAL -->
<div class="modal fade" id="warehouseReportExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-file-earmark-arrow-down me-2"></i>Export Warehouse Operations &amp; Inventory Reports
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET" action="<?= url('warehouse/reports/export') ?>" target="_blank">
                <div class="modal-body p-4">
                    <!-- Warehouse Scope Selector (if multiple warehouses available) -->
                    <?php if (!empty($warehouses) && count($warehouses) > 1 && empty($scopedWarehouseId)): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark mb-1">
                            <i class="bi bi-building me-1 text-primary"></i> Target Warehouse Facility
                        </label>
                        <select name="warehouse_id" class="form-select form-select-sm fw-semibold">
                            <option value="">All Warehouses (Consolidated View)</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= e($wh['name']) ?> (<?= e($wh['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php elseif (!empty($scopedWarehouseId)): ?>
                        <input type="hidden" name="warehouse_id" value="<?= (int)$scopedWarehouseId ?>">
                    <?php endif; ?>

                    <!-- 1. Period Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-2">
                            <i class="bi bi-calendar-range text-primary me-1"></i> 1. Select Report Period
                        </label>
                        <div class="d-flex gap-4 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="period_type" id="whPeriodTypeMonth" value="MONTH" checked onchange="toggleWarehousePeriodInputs()">
                                <label class="form-check-label fw-semibold" for="whPeriodTypeMonth">Month-Wise Filter</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="period_type" id="whPeriodTypeCustom" value="CUSTOM" onchange="toggleWarehousePeriodInputs()">
                                <label class="form-check-label fw-semibold" for="whPeriodTypeCustom">Custom Date Range</label>
                            </div>
                        </div>

                        <!-- Month-wise Picker -->
                        <div class="row g-2" id="whMonthPickerRow">
                            <div class="col-sm-7">
                                <label class="form-label small text-muted mb-1">Select Month</label>
                                <select name="selected_month" class="form-select form-select-sm fw-semibold">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= ((int)date('n') === $m) ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label small text-muted mb-1">Select Year</label>
                                <select name="selected_year" class="form-select form-select-sm fw-semibold">
                                    <?php 
                                    $curYear = (int)date('Y');
                                    for ($y = $curYear - 2; $y <= $curYear + 2; $y++): ?>
                                        <option value="<?= $y ?>" <?= ($y === $curYear) ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Custom Date Picker -->
                        <div class="row g-2 d-none" id="whCustomPickerRow">
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-1">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- 2. Data Modules to Include -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-dark mb-0">
                                <i class="bi bi-check2-square text-primary me-1"></i> 2. Choose Warehouse Data Modules to Export
                            </label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" onclick="toggleAllWarehouseExportModules(true)">Select All</button>
                                <span class="text-muted">|</span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-secondary" onclick="toggleAllWarehouseExportModules(false)">Clear</button>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 border">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="inward" id="whModInward" checked>
                                        <label class="form-check-label small fw-semibold" for="whModInward">
                                            <i class="bi bi-box-arrow-in-down text-primary me-1"></i> Inward Shipments &amp; GRN (Factory &amp; Vendor)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="stock" id="whModStock" checked>
                                        <label class="form-check-label small fw-semibold" for="whModStock">
                                            <i class="bi bi-boxes text-success me-1"></i> Stock Balances &amp; Valuations (Cost &amp; MRP)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="catalog" id="whModCatalog" checked>
                                        <label class="form-check-label small fw-semibold" for="whModCatalog">
                                            <i class="bi bi-journal-album text-info me-1"></i> Warehouse Catalog &amp; Styles (HSN &amp; GST)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="suppliers" id="whModSuppliers" checked>
                                        <label class="form-check-label small fw-semibold" for="whModSuppliers">
                                            <i class="bi bi-people text-secondary me-1"></i> Suppliers &amp; Vendors Directory (GST &amp; Spend)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="purchases" id="whModPurchases" checked>
                                        <label class="form-check-label small fw-semibold" for="whModPurchases">
                                            <i class="bi bi-cart-check text-primary me-1"></i> Direct Vendor Purchases (POs &amp; Invoices)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="allocations" id="whModAllocations" checked>
                                        <label class="form-check-label small fw-semibold" for="whModAllocations">
                                            <i class="bi bi-diagram-3 text-warning me-1"></i> Shop Allocations &amp; Requisitions
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="outward" id="whModOutward" checked>
                                        <label class="form-check-label small fw-semibold" for="whModOutward">
                                            <i class="bi bi-truck text-success me-1"></i> Outward Dispatches to Outlets
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="damages" id="whModDamages" checked>
                                        <label class="form-check-label small fw-semibold" for="whModDamages">
                                            <i class="bi bi-exclamation-triangle text-danger me-1"></i> Damaged &amp; Quarantine Stock (Cost Loss)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="returns" id="whModReturns" checked>
                                        <label class="form-check-label small fw-semibold" for="whModReturns">
                                            <i class="bi bi-arrow-return-left text-info me-1"></i> Shop Returns QC (Restocked &amp; Defective)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="expenses" id="whModExpenses" checked>
                                        <label class="form-check-label small fw-semibold" for="whModExpenses">
                                            <i class="bi bi-wallet2 text-warning me-1"></i> Warehouse Operational Expenses
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="complementary" id="whModComplementary" checked>
                                        <label class="form-check-label small fw-semibold" for="whModComplementary">
                                            <i class="bi bi-gift text-purple me-1" style="color: #8b5cf6;"></i> Complementary Products &amp; Valuations
                                        </label>
                                    </div>
                                </div>
                                <?php if (hasPermission('stock_adjustments', 'view')): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input wh-export-mod-cb" type="checkbox" name="modules[]" value="adjustments" id="whModAdjustments" checked>
                                        <label class="form-check-label small fw-semibold" for="whModAdjustments">
                                            <i class="bi bi-sliders text-warning me-1"></i> Stock Adjustments &amp; QR Codes
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Export Format -->
                    <div>
                        <label class="form-label fw-bold text-dark mb-2">
                            <i class="bi bi-file-earmark-spreadsheet text-success me-1"></i> 3. Export Format
                        </label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="whFormatCsv" value="CSV" checked>
                                <label class="form-check-label fw-semibold" for="whFormatCsv">
                                    <i class="bi bi-file-earmark-excel text-success me-1"></i> CSV Spreadsheet (Excel Compatible)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="whFormatPrint" value="PRINT">
                                <label class="form-check-label fw-semibold" for="whFormatPrint">
                                    <i class="bi bi-printer text-primary me-1"></i> Printable Master Summary Report
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                        <i class="bi bi-download me-1"></i> Generate &amp; Download Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleWarehousePeriodInputs() {
    const isMonth = document.getElementById('whPeriodTypeMonth').checked;
    const mRow = document.getElementById('whMonthPickerRow');
    const cRow = document.getElementById('whCustomPickerRow');
    if (isMonth) {
        mRow.classList.remove('d-none');
        cRow.classList.add('d-none');
    } else {
        mRow.classList.add('d-none');
        cRow.classList.remove('d-none');
    }
}

function toggleAllWarehouseExportModules(check) {
    document.querySelectorAll('.wh-export-mod-cb').forEach(cb => cb.checked = check);
}
</script>