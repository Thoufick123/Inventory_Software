<div class="page-header mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h2 class="mb-1 text-danger"><i class="bi bi-trash3 me-2"></i>Delete Supplier Profile</h2>
        <span class="text-muted">Review supplier dependencies and confirm deletion</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('production/sellers') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Suppliers
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <div class="card-body p-4 p-md-5">
                
                <?php if (!$canDelete): ?>
                    <!-- ACTION BLOCKED: Supplier has connected records -->
                    <div class="text-center mb-4">
                        <div class="mx-auto d-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 74px; height: 74px; background: #fef3c7; color: #d97706; border: 4px solid #fde68a;">
                            <i class="bi bi-shield-fill-x" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Cannot Delete Supplier</h4>
                        <p class="text-muted small">
                            Supplier <strong><?= e($seller['name']) ?></strong> is actively connected to other modules and cannot be deleted.
                        </p>
                    </div>

                    <div class="alert alert-warning border-warning-subtle py-3 px-3.5 mb-4 rounded-3 text-start">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0 mt-0.5"></i>
                            <div>
                                <strong class="d-block text-dark mb-1">Connected Records Found:</strong>
                                <ul class="mb-0 ps-3 small text-muted">
                                    <?php foreach ($connections['reasons'] as $reason): ?>
                                        <li class="mb-1 text-dark fw-medium"><?= e($reason) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Summary Details -->
                    <div class="p-3 bg-light rounded-3 border text-start mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted small">Supplier / Contact:</span>
                            <strong class="text-dark"><?= e($seller['name']) ?></strong>
                        </div>
                        <?php if (!empty($seller['company'])): ?>
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="text-muted small">Company / Mill:</span>
                                <span class="text-dark fw-semibold"><?= e($seller['company']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($seller['phone'])): ?>
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="text-muted small">Phone:</span>
                                <span class="font-monospace small"><?= e($seller['phone']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($seller['gst_number'])): ?>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small">GSTIN:</span>
                                <span class="font-monospace small"><?= e($seller['gst_number']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-muted small text-center mb-4">
                        <i class="bi bi-info-circle me-1"></i> To delete this supplier, you must first remove or reassign the connected purchases, orders, rolls, or products listed above.
                    </div>

                    <div class="d-flex justify-content-center gap-2">
                        <a href="<?= url('production/sellers') ?>" class="btn btn-primary px-4 py-2 fw-semibold">
                            <i class="bi bi-arrow-left me-1"></i>Back to Suppliers List
                        </a>
                        <a href="<?= url('production/sellers/view/' . (int)$seller['id']) ?>" class="btn btn-outline-secondary px-3 py-2 fw-semibold">
                            <i class="bi bi-eye me-1"></i>View Supplier Profile
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Supplier history is retained; only the active production profile is retired. -->
                    <div class="text-center mb-4">
                        <div class="mx-auto d-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 74px; height: 74px; background: #fee2e2; color: #dc2626; border: 4px solid #fecaca;">
                            <i class="bi bi-trash3-fill" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Delete Supplier?</h4>
                        <p class="text-muted small">
                            Are you sure you want to remove supplier <strong><?= e($seller['name']) ?></strong> from active production suppliers?
                        </p>
                    </div>

                    <!-- Supplier Details Card -->
                    <div class="p-3 bg-light rounded-3 border text-start mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted small">Supplier / Contact:</span>
                            <strong class="text-dark"><?= e($seller['name']) ?></strong>
                        </div>
                        <?php if (!empty($seller['company'])): ?>
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="text-muted small">Company / Mill:</span>
                                <span class="text-dark fw-semibold"><?= e($seller['company']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($seller['phone'])): ?>
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="text-muted small">Phone:</span>
                                <span class="font-monospace small"><?= e($seller['phone']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($seller['gst_number'])): ?>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small">GSTIN:</span>
                                <span class="font-monospace small"><?= e($seller['gst_number']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="alert alert-warning border-warning-subtle py-2 px-3 small text-start mb-4">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-warning flex-shrink-0 fs-5"></i>
                            <div>
                                This action cannot be undone here. Any linked historical data will be safely preserved.
                                <?php if (!empty($connections['reasons'])): ?>
                                    <ul class="mb-0 mt-2 ps-3">
                                        <?php foreach ($connections['reasons'] as $reason): ?>
                                            <li><?= e($reason) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-2">
                        <a href="<?= url('production/sellers') ?>" class="btn btn-outline-secondary px-4 py-2 fw-semibold">
                            Cancel
                        </a>
                        <form method="POST" action="<?= url('production/sellers/delete/' . (int)$seller['id']) ?>" class="m-0">
                            <?= CSRF::field() ?>
                            <button type="submit" class="btn btn-danger px-4 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-trash3 me-1"></i>Yes, Delete Supplier
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

