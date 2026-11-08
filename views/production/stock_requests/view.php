<?php
// Production Unit Warehouse Stock Requisition Detail View
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-box-arrow-in-down-right text-primary me-2"></i>Requisition #<?= e($req['request_number']) ?>
            </h4>
            <span class="text-muted small">Submitted for <?= e($req['warehouse_name']) ?> on <?= date('d M Y, h:i A', strtotime($req['created_at'])) ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('production/stock-requests') ?>" class="btn btn-outline-secondary btn-sm fw-semibold shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Requisitions
            </a>
            <?php if (hasPermission('batches', 'create')): ?>
            <a href="<?= url('production/batches/create') ?>" class="btn btn-success btn-sm fw-semibold shadow-sm">
                <i class="bi bi-box-seam me-1"></i> Pack &amp; Outward to Warehouse
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status & Overview Banner -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <span class="text-muted small d-block">Current Requisition Status</span>
                    <?php if ($req['status'] === 'PENDING'): ?>
                        <span class="badge bg-warning text-dark fs-6 px-3 py-1"><i class="bi bi-clock me-1"></i>Pending Review</span>
                    <?php elseif ($req['status'] === 'APPROVED'): ?>
                        <span class="badge bg-info text-white fs-6 px-3 py-1"><i class="bi bi-check me-1"></i>Approved</span>
                    <?php elseif ($req['status'] === 'IN_PRODUCTION'): ?>
                        <span class="badge bg-primary text-white fs-6 px-3 py-1"><i class="bi bi-gear me-1"></i>In Production</span>
                    <?php elseif ($req['status'] === 'READY_FOR_DISPATCH'): ?>
                        <span class="badge bg-success-subtle text-success border fs-6 px-3 py-1"><i class="bi bi-box-seam me-1"></i>Ready for Dispatch</span>
                    <?php elseif ($req['status'] === 'FULFILLED'): ?>
                        <span class="badge bg-success text-white fs-6 px-3 py-1"><i class="bi bi-check2-all me-1"></i>Fulfilled</span>
                    <?php else: ?>
                        <span class="badge bg-danger text-white fs-6 px-3 py-1">Rejected</span>
                    <?php endif; ?>
                </div>

                <div class="col-md-3">
                    <span class="text-muted small d-block">Priority Level</span>
                    <?php if ($req['priority'] === 'URGENT'): ?>
                        <span class="badge bg-danger text-white fw-bold">URGENT</span>
                    <?php elseif ($req['priority'] === 'HIGH'): ?>
                        <span class="badge bg-warning text-dark fw-bold">HIGH</span>
                    <?php else: ?>
                        <span class="badge bg-light text-dark border">NORMAL</span>
                    <?php endif; ?>
                </div>

                <div class="col-md-3">
                    <span class="text-muted small d-block">Required By Date</span>
                    <strong class="text-dark"><?= $req['required_by_date'] ? date('d M Y', strtotime($req['required_by_date'])) : 'Open' ?></strong>
                </div>

                <div class="col-md-3 text-md-end">
                    <span class="text-muted small d-block">Fulfilled vs Requested</span>
                    <h5 class="fw-bold text-dark mb-0">
                        <span class="text-success"><?= number_format((int)$req['fulfilled_pieces']) ?></span> / <?= number_format((int)$req['total_requested_pieces']) ?> pcs
                    </h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Requester Message / Note Banner -->
    <?php if (!empty($req['notes'])): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-4 border-primary bg-primary bg-opacity-10">
            <div class="card-body p-3 d-flex align-items-start gap-3">
                <i class="bi bi-chat-left-quote-fill fs-3 text-primary mt-1"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold text-dark mb-1">
                        Requisition Request Message from <?= e($req['warehouse_name']) ?> (<?= e($req['requested_by_name'] ?: 'Requester') ?>):
                    </div>
                    <div class="text-secondary fs-6 fst-italic bg-white p-2 rounded border">
                        "<?= nl2br(e($req['notes'])) ?>"
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Requested Items & Stock Availability Matrix -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-box2 text-primary me-2"></i>Requested Products &amp; Factory Stock Comparison
            </h6>
            <span class="text-muted small">Real-time check against QC-passed inventory in production</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Style / Product</th>
                            <th>SKU / Variant</th>
                            <th class="text-center">Requested Qty</th>
                            <th class="text-center">QC-Passed Factory Stock</th>
                            <th class="text-center">Status / Feasibility</th>
                            <th class="text-end">Factory Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <?php $inStock = ($it['available_stock'] ?? 0) >= (int)$it['requested_qty']; ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?= e($it['product_name']) ?></strong>
                                    <?php if ($it['size_name'] || $it['color_name']): ?>
                                        <div class="small text-muted">
                                            <?= $it['size_name'] ? 'Size: ' . e($it['size_name']) : '' ?> 
                                            <?= $it['color_name'] ? '· Color: ' . e($it['color_name']) : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= e($it['sku']) ?></span></td>
                                <td class="text-center fw-bold fs-6 text-dark"><?= (int)$it['requested_qty'] ?> pcs</td>
                                <td class="text-center">
                                    <strong class="<?= ($it['available_stock'] > 0) ? 'text-success' : 'text-danger' ?>">
                                        <?= (int)($it['available_stock'] ?? 0) ?> pcs
                                    </strong>
                                </td>
                                <td class="text-center">
                                    <?php if ($inStock): ?>
                                        <span class="badge bg-success-subtle text-success border">
                                            <i class="bi bi-check-circle me-1"></i>Stock Available
                                        </span>
                                    <?php elseif (($it['available_stock'] ?? 0) > 0): ?>
                                        <span class="badge bg-warning-subtle text-warning border">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Partial Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border">
                                            <i class="bi bi-x-circle me-1"></i>Needs Production
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        <?php if ($inStock || ($it['available_stock'] ?? 0) > 0): ?>
                                            <a href="<?= url('production/batches/create') ?>" class="btn btn-sm btn-outline-success fw-semibold" title="Pack into Outward Box">
                                                <i class="bi bi-box-seam me-1"></i> Pack Box
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= url('production/programs/create' . ($it['product_id'] ? '?product_id=' . $it['product_id'] : '')) ?>" class="btn btn-sm btn-outline-primary fw-semibold" title="Start Manufacturing Run">
                                            <i class="bi bi-plus-circle me-1"></i> New Program
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Status & Notes Form -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-pencil-square text-primary me-2"></i>Update Requisition Status &amp; Fulfillment</h6>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="<?= url('production/stock-requests/update-status/' . $req['id']) ?>">
                <?= CSRF::field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark mb-1">Update Status</label>
                        <select name="status" class="form-select fw-semibold" required>
                            <option value="PENDING" <?= $req['status'] === 'PENDING' ? 'selected' : '' ?>>PENDING (Awaiting Review)</option>
                            <option value="APPROVED" <?= $req['status'] === 'APPROVED' ? 'selected' : '' ?>>APPROVED (Scheduled)</option>
                            <option value="IN_PRODUCTION" <?= $req['status'] === 'IN_PRODUCTION' ? 'selected' : '' ?>>IN PRODUCTION (Manufacturing)</option>
                            <option value="READY_FOR_DISPATCH" <?= $req['status'] === 'READY_FOR_DISPATCH' ? 'selected' : '' ?>>READY FOR DISPATCH (In Boxes)</option>
                            <option value="FULFILLED" <?= $req['status'] === 'FULFILLED' ? 'selected' : '' ?>>FULFILLED (Dispatched)</option>
                            <option value="REJECTED" <?= $req['status'] === 'REJECTED' ? 'selected' : '' ?>>REJECTED (Cannot Fulfill)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark mb-1">Fulfilled Pieces Shipped</label>
                        <input type="number" min="0" max="<?= (int)$req['total_requested_pieces'] ?>" name="fulfilled_pieces" class="form-control fw-bold" value="<?= (int)$req['fulfilled_pieces'] ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-dark mb-1">Management Notes / Tracking</label>
                        <input type="text" name="notes" class="form-control" placeholder="Add dispatch reference or reason...">
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm">
                            <i class="bi bi-save me-1"></i> Save Status Update
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
