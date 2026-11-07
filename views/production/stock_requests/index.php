<?php
// Production Unit Warehouse Stock Requisitions View
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-box-arrow-in-down-right text-primary me-2"></i>Warehouse Stock Requisitions &amp; Requests
            </h4>
            <span class="text-muted small">Monitor replenishment requests from Central Warehouse &amp; retail hubs, allocate available finished goods, or trigger production runs</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('production/stock-requests/create') ?>" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3">
                <i class="bi bi-plus-circle me-1"></i> New Stock Request
            </a>
            <a href="<?= url('production/inventory') ?>" class="btn btn-outline-success btn-sm fw-semibold shadow-sm">
                <i class="bi bi-box-seam me-1"></i> Factory Inventory
            </a>
        </div>
    </div>

    <!-- Alert Banner for Pending Requests -->
    <?php if (($stats['pending_count'] ?? 0) > 0): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
                <strong>Action Needed:</strong> There are <span class="badge bg-warning text-dark fw-bold"><?= $stats['pending_count'] ?> pending stock requisition(s)</span> from the warehouse awaiting factory approval or allocation!
            </div>
            <a href="<?= url('production/stock-requests?status=PENDING') ?>" class="btn btn-sm btn-dark fw-semibold">Review Pending</a>
        </div>
    <?php endif; ?>

    <!-- KPI Summary Analytics Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Requests -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total Requisitions</span>
                        <div class="p-2 rounded-circle bg-primary-subtle text-primary">
                            <i class="bi bi-journal-text fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= number_format($stats['total_requests'] ?? 0) ?></h3>
                    <div class="small text-muted">All Warehouse Requests</div>
                </div>
            </div>
        </div>

        <!-- 2. Pending Requisitions -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Pending Approval</span>
                        <div class="p-2 rounded-circle bg-warning-subtle text-warning">
                            <i class="bi bi-clock-history fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-warning mb-1"><?= number_format($stats['pending_count'] ?? 0) ?></h3>
                    <div class="small text-muted">Awaiting Factory Action</div>
                </div>
            </div>
        </div>

        <!-- 3. In Production -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">In Manufacturing</span>
                        <div class="p-2 rounded-circle bg-info-subtle text-info">
                            <i class="bi bi-gear-wide-connected fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-info mb-1"><?= number_format($stats['in_prod_count'] ?? 0) ?></h3>
                    <div class="small text-muted">Cutting &amp; Stitching Underway</div>
                </div>
            </div>
        </div>

        <!-- 4. Target Pieces Fulfilled -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Fulfillment Progress</span>
                        <div class="p-2 rounded-circle bg-success-subtle text-success">
                            <i class="bi bi-check-circle fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-success mb-1">
                        <?= number_format($stats['total_fulfilled'] ?? 0) ?> <span class="fs-6 text-muted">/ <?= number_format($stats['total_requested'] ?? 0) ?> pcs</span>
                    </h3>
                    <div class="small text-muted">Garments Shipped to Warehouse</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('production/stock-requests') ?>" class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="Search Requisition #, Warehouse, Notes..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Request Statuses --</option>
                        <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                        <option value="APPROVED" <?= $status === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                        <option value="IN_PRODUCTION" <?= $status === 'IN_PRODUCTION' ? 'selected' : '' ?>>In Production</option>
                        <option value="READY_FOR_DISPATCH" <?= $status === 'READY_FOR_DISPATCH' ? 'selected' : '' ?>>Ready for Dispatch</option>
                        <option value="FULFILLED" <?= $status === 'FULFILLED' ? 'selected' : '' ?>>Fulfilled</option>
                        <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-3">
                    <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Priorities --</option>
                        <option value="LOW" <?= $priority === 'LOW' ? 'selected' : '' ?>>Low</option>
                        <option value="NORMAL" <?= $priority === 'NORMAL' ? 'selected' : '' ?>>Normal</option>
                        <option value="HIGH" <?= $priority === 'HIGH' ? 'selected' : '' ?>>High</option>
                        <option value="URGENT" <?= $priority === 'URGENT' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 text-end d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                    <?php if ($search || $status || $priority): ?>
                    <a href="<?= url('production/stock-requests') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Requisitions Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-card-checklist text-primary me-2"></i>Stock Requisitions Log (<?= count($requests) ?>)
            </h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
                <div class="text-center py-5 text-muted">
                    <div class="d-inline-flex p-3 rounded-circle bg-light mb-2">
                        <i class="bi bi-inbox fs-1 text-secondary"></i>
                    </div>
                    <div class="fw-bold fs-6">No Warehouse Stock Requisitions Found</div>
                    <p class="small mb-3">Requisitions submitted from Central Warehouse or retail channels will appear here.</p>
                    <a href="<?= url('production/stock-requests/create') ?>" class="btn btn-sm btn-primary px-3">
                        <i class="bi bi-plus-circle me-1"></i> Create First Stock Request
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th>Request #</th>
                                <th>Warehouse Location</th>
                                <th>Requested By</th>
                                <th class="text-center">Priority</th>
                                <th class="text-center">Required By</th>
                                <th class="text-center">Requested</th>
                                <th class="text-center">Fulfilled</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('production/stock-requests/view/' . $r['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= e($r['request_number']) ?>
                                        </a>
                                        <div class="small text-muted"><?= date('d M Y', strtotime($r['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><i class="bi bi-building me-1 text-secondary"></i><?= e($r['warehouse_name']) ?></div>
                                    </td>
                                    <td><?= e($r['requested_by_name'] ?: 'Warehouse Staff') ?></td>
                                    <td class="text-center">
                                        <?php if ($r['priority'] === 'URGENT'): ?>
                                            <span class="badge bg-danger text-white">URGENT</span>
                                        <?php elseif ($r['priority'] === 'HIGH'): ?>
                                            <span class="badge bg-warning text-dark">High</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center small text-muted">
                                        <?= $r['required_by_date'] ? date('d M Y', strtotime($r['required_by_date'])) : '-' ?>
                                    </td>
                                    <td class="text-center fw-bold text-dark"><?= number_format((int)$r['total_requested_pieces']) ?> pcs</td>
                                    <td class="text-center fw-semibold text-success"><?= number_format((int)$r['fulfilled_pieces']) ?> pcs</td>
                                    <td class="text-center">
                                        <?php if ($r['status'] === 'PENDING'): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
                                        <?php elseif ($r['status'] === 'APPROVED'): ?>
                                            <span class="badge bg-info text-white"><i class="bi bi-check me-1"></i>Approved</span>
                                        <?php elseif ($r['status'] === 'IN_PRODUCTION'): ?>
                                            <span class="badge bg-primary text-white"><i class="bi bi-gear me-1"></i>In Production</span>
                                        <?php elseif ($r['status'] === 'READY_FOR_DISPATCH'): ?>
                                            <span class="badge bg-success-subtle text-success border"><i class="bi bi-box-seam me-1"></i>Ready</span>
                                        <?php elseif ($r['status'] === 'FULFILLED'): ?>
                                            <span class="badge bg-success text-white"><i class="bi bi-check2-all me-1"></i>Fulfilled</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('production/stock-requests/view/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary fw-semibold" title="View & Fulfill">
                                            <i class="bi bi-eye me-1"></i> View &amp; Action
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
