<style>
.stage-card {
    transition: all 0.25s ease-in-out;
    border: 1px solid #e2e8f0;
    cursor: pointer;
}
.stage-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
    border-color: #94a3b8;
}
.stage-icon-circle {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(0,0,0,0.06);
    background: #ffffff;
    border-radius: 12px;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.07);
}
</style>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h2 class="mb-0 fw-bold"><i class="bi bi-gear-wide-connected me-2 text-primary"></i>Production Dashboard</h2>
            <?php 
            $currUser = currentUser();
            $assignedPU = $currUser['production_unit_name'] ?? null;
            if ($assignedPU): ?>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5 rounded-pill fs-7 d-inline-flex align-items-center">
                    <i class="bi bi-buildings-fill me-1"></i> Unit: <?= e($assignedPU) ?>
                </span>
            <?php elseif (isSuperAdmin()): ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2.5 py-1.5 rounded-pill fs-7 d-inline-flex align-items-center">
                    <i class="bi bi-globe-americas me-1"></i> All Production Units (Enterprise Scope)
                </span>
            <?php endif; ?>
        </div>
        <span class="text-muted">Real-time manufacturing operations, throughput analytics, QC yield &amp; dispatch pipeline</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold">
            <i class="bi bi-activity me-1"></i> Factory Pipeline Active
        </span>
        <span class="text-muted small d-none d-sm-inline">
            <i class="bi bi-calendar3 me-1"></i> <?= date('d M Y') ?>
        </span>
    </div>
</div>

<!-- 1. KPI Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card p-3 shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon icon-primary bg-primary-subtle text-primary p-2 rounded-circle">
                    <i class="bi bi-kanban fs-5"></i>
                </div>
                <span class="badge bg-primary-subtle text-primary font-monospace">Programs</span>
            </div>
            <div class="stat-value fs-3 fw-bold text-dark"><?= number_format($activePrograms['count'] ?? 0) ?></div>
            <div class="stat-label text-muted small text-uppercase fw-semibold">Active Programs</div>
            <div class="small text-secondary mt-1"><i class="bi bi-bullseye me-1 text-primary"></i><?= number_format($activePrograms['total_planned'] ?? 0) ?> pcs planned</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card p-3 shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon icon-info bg-info-subtle text-info p-2 rounded-circle">
                    <i class="bi bi-scissors fs-5"></i>
                </div>
                <span class="badge bg-info-subtle text-info font-monospace">Cutting</span>
            </div>
            <div class="stat-value fs-3 fw-bold text-dark"><?= number_format($pendingCutting['count'] ?? 0) ?></div>
            <div class="stat-label text-muted small text-uppercase fw-semibold">Cutting Queue</div>
            <div class="small text-secondary mt-1"><i class="bi bi-layers me-1 text-info"></i><?= number_format($pendingCutting['total_qty'] ?? 0) ?> pcs in queue</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card p-3 shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon icon-warning bg-warning-subtle text-warning p-2 rounded-circle">
                    <i class="bi bi-threads fs-5"></i>
                </div>
                <span class="badge bg-warning-subtle text-warning font-monospace">Stitching</span>
            </div>
            <div class="stat-value fs-3 fw-bold text-dark"><?= number_format($pendingStitching['count'] ?? 0) ?></div>
            <div class="stat-label text-muted small text-uppercase fw-semibold">Active Stitching</div>
            <div class="small text-secondary mt-1"><i class="bi bi-cpu me-1 text-warning"></i><?= number_format($pendingStitching['total_qty'] ?? 0) ?> pcs sewing</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card p-3 shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon icon-danger bg-danger-subtle text-danger p-2 rounded-circle">
                    <i class="bi bi-patch-check fs-5"></i>
                </div>
                <span class="badge bg-danger-subtle text-danger font-monospace">QC</span>
            </div>
            <div class="stat-value fs-3 fw-bold text-dark"><?= number_format($pendingQC['count'] ?? 0) ?></div>
            <div class="stat-label text-muted small text-uppercase fw-semibold">Pending QC</div>
            <div class="small text-secondary mt-1"><i class="bi bi-search me-1 text-danger"></i><?= number_format($pendingQC['total_qty'] ?? 0) ?> pcs to audit</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card p-3 shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon icon-success bg-success-subtle text-success p-2 rounded-circle">
                    <i class="bi bi-box-seam fs-5"></i>
                </div>
                <span class="badge bg-success-subtle text-success font-monospace">Packed</span>
            </div>
            <div class="stat-value fs-3 fw-bold text-dark"><?= number_format($readyDispatch['count'] ?? 0) ?></div>
            <div class="stat-label text-muted small text-uppercase fw-semibold">Ready Dispatch</div>
            <div class="small text-secondary mt-1"><i class="bi bi-check2-circle me-1 text-success"></i><?= number_format($readyDispatch['total_qty'] ?? 0) ?> pcs boxed</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card p-3 shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon icon-primary bg-primary-subtle text-primary p-2 rounded-circle">
                    <i class="bi bi-truck fs-5"></i>
                </div>
                <span class="badge bg-primary-subtle text-primary font-monospace">Outward</span>
            </div>
            <div class="stat-value fs-3 fw-bold text-dark"><?= number_format($monthOutward['count'] ?? 0) ?></div>
            <div class="stat-label text-muted small text-uppercase fw-semibold">Month Dispatches</div>
            <div class="small text-secondary mt-1"><i class="bi bi-buildings me-1 text-primary"></i><?= number_format($monthOutward['total_qty'] ?? 0) ?> pcs to WH</div>
        </div>
    </div>
</div>

<!-- 2. Production Lifecycle Stages (Clean UI Without Clunky Action Buttons) -->
<div class="card mb-4 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 fw-bold">
            <i class="bi bi-diagram-3 me-2 text-primary"></i>Production Lifecycle Stages
        </h5>
        <small class="text-muted">Standard Manufacturing Pipeline Flow</small>
    </div>
    <div class="card-body p-3">
        <div class="row text-center g-3">
            <div class="col-md-2 col-6">
                <a href="<?= url('production/programs') ?>" class="text-decoration-none">
                    <div class="p-3 rounded-3 bg-white stage-card h-100 text-center">
                        <div class="stage-icon-circle bg-primary-subtle text-primary mx-auto mb-2">
                            <i class="bi bi-file-earmark-text fs-4"></i>
                        </div>
                        <div class="fw-bold text-dark">1. Program</div>
                        <small class="text-muted d-block">Plan &amp; Approve</small>
                        <span class="badge bg-primary-subtle text-primary mt-2">Stage 1</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="<?= url('production/cutting') ?>" class="text-decoration-none">
                    <div class="p-3 rounded-3 bg-white stage-card h-100 text-center">
                        <div class="stage-icon-circle bg-info-subtle text-info mx-auto mb-2">
                            <i class="bi bi-scissors fs-4"></i>
                        </div>
                        <div class="fw-bold text-dark">2. Cutting</div>
                        <small class="text-muted d-block">Material Issue</small>
                        <span class="badge bg-info-subtle text-info mt-2">Stage 2</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="<?= url('production/stitching') ?>" class="text-decoration-none">
                    <div class="p-3 rounded-3 bg-white stage-card h-100 text-center">
                        <div class="stage-icon-circle bg-warning-subtle text-warning mx-auto mb-2">
                            <i class="bi bi-threads fs-4"></i>
                        </div>
                        <div class="fw-bold text-dark">3. Stitching</div>
                        <small class="text-muted d-block">Sewing &amp; Rework</small>
                        <span class="badge bg-warning-subtle text-warning mt-2">Stage 3</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="<?= url('production/labels') ?>" class="text-decoration-none">
                    <div class="p-3 rounded-3 bg-white stage-card h-100 text-center">
                        <div class="stage-icon-circle bg-secondary-subtle text-secondary mx-auto mb-2">
                            <i class="bi bi-tags fs-4"></i>
                        </div>
                        <div class="fw-bold text-dark">4. Labeling</div>
                        <small class="text-muted d-block">Tags &amp; Barcode</small>
                        <span class="badge bg-secondary-subtle text-secondary mt-2">Stage 4</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="<?= url('production/qc') ?>" class="text-decoration-none">
                    <div class="p-3 rounded-3 bg-white stage-card h-100 text-center">
                        <div class="stage-icon-circle bg-danger-subtle text-danger mx-auto mb-2">
                            <i class="bi bi-patch-check fs-4"></i>
                        </div>
                        <div class="fw-bold text-dark">5. QC Check</div>
                        <small class="text-muted d-block">Pass / Defect Audit</small>
                        <span class="badge bg-danger-subtle text-danger mt-2">Stage 5</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-6">
                <a href="<?= url('production/batches') ?>" class="text-decoration-none">
                    <div class="p-3 rounded-3 bg-white stage-card h-100 text-center">
                        <div class="stage-icon-circle bg-success-subtle text-success mx-auto mb-2">
                            <i class="bi bi-box-seam fs-4"></i>
                        </div>
                        <div class="fw-bold text-dark">6. Batch &amp; Outward</div>
                        <small class="text-muted d-block">Box &amp; Dispatch</small>
                        <span class="badge bg-success-subtle text-success mt-2">Stage 6</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. PRODUCTION ANALYTICS GRAPH UI (EVERY DETAIL VISUALIZED) -->
<!-- ========================================================================= -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Manufacturing Throughput Trends (Last 7 Days) -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-graph-up-arrow text-primary me-2"></i>Manufacturing Throughput Trends
                    </h5>
                    <small class="text-muted">Daily output comparison: Cutting, Stitching, QC Passed, and WH Dispatches</small>
                </div>
                <span class="badge bg-light text-secondary border font-monospace">7-Day Trend</span>
            </div>
            <div class="card-body p-3">
                <div style="height: 300px; position: relative;">
                    <canvas id="productionThroughputChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 2: QC Inspection & Quality Yield Distribution -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-shield-check text-success me-2"></i>Quality &amp; Yield Rate
                    </h5>
                    <small class="text-muted">First-pass pass vs rework vs rejection</small>
                </div>
                <?php
                $inspectedTotal = (int)($qcBreakdown['total_inspected'] ?? 0);
                $passedTotal = (int)($qcBreakdown['total_passed'] ?? 0);
                $reworkTotal = (int)($qcBreakdown['total_rework'] ?? 0);
                $rejectedTotal = (int)($qcBreakdown['total_rejected'] ?? 0);
                $yieldPct = $inspectedTotal > 0 ? round(($passedTotal / $inspectedTotal) * 100, 1) : 100;
                ?>
                <span class="badge <?= $yieldPct >= 95 ? 'bg-success' : ($yieldPct >= 85 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                    <?= $yieldPct ?>% Yield
                </span>
            </div>
            <div class="card-body p-3 d-flex flex-column align-items-center justify-content-center">
                <div style="height: 200px; width: 100%; position: relative;">
                    <canvas id="qcYieldChart"></canvas>
                </div>
                <div class="row w-100 text-center mt-3 g-2 border-top pt-2">
                    <div class="col-4">
                        <div class="text-muted small">Passed</div>
                        <strong class="text-success"><?= number_format($passedTotal) ?></strong>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Rework</div>
                        <strong class="text-warning"><?= number_format($reworkTotal) ?></strong>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Rejected</div>
                        <strong class="text-danger"><?= number_format($rejectedTotal) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graph Row 2: Pipeline WIP Stage Distribution & Active Program Fulfillment -->
<div class="row g-4 mb-4">
    <!-- Chart 3: Pipeline Work-in-Progress (WIP) Volume by Stage -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-bar-chart-steps text-info me-2"></i>Work-in-Progress (WIP) Stage Distribution
                    </h5>
                    <small class="text-muted">Live garment volume currently active across each manufacturing phase</small>
                </div>
                <span class="badge bg-info-subtle text-info border border-info-subtle">Live Pipeline</span>
            </div>
            <div class="card-body p-3">
                <div style="height: 250px; position: relative;">
                    <canvas id="wipDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 4: Program Target Fulfillment -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-bullseye text-primary me-2"></i>Program Target Fulfillment
                    </h5>
                    <small class="text-muted">Completed pieces vs pending planned volume</small>
                </div>
            </div>
            <div class="card-body p-3 d-flex flex-column align-items-center justify-content-center">
                <div style="height: 180px; width: 100%; position: relative;">
                    <canvas id="programFulfillmentChart"></canvas>
                </div>
                <?php
                $plannedPcs = (int)($activePrograms['total_planned'] ?? 0);
                $completedPcs = (int)($activePrograms['total_completed'] ?? 0);
                $pendingPcs = max(0, $plannedPcs - $completedPcs);
                $progCompletionRate = $plannedPcs > 0 ? min(100, round(($completedPcs / $plannedPcs) * 100, 1)) : 0;
                ?>
                <div class="d-flex justify-content-between w-100 mt-3 pt-2 border-top small">
                    <div><i class="bi bi-circle-fill text-success me-1"></i> Completed: <strong><?= number_format($completedPcs) ?> pcs</strong></div>
                    <div><i class="bi bi-circle-fill text-primary me-1"></i> Pending: <strong><?= number_format($pendingPcs) ?> pcs</strong></div>
                    <div><span class="badge bg-light text-dark border"><?= $progCompletionRate ?>% Finished</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. Active Programs & Operational Feeds -->
<div class="row g-4">
    <!-- Active Programs Table -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-list-task me-2 text-primary"></i>Active Production Programs</h5>
                <a href="<?= url('production/programs') ?>" class="btn btn-sm btn-link text-decoration-none">View All Programs &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase text-muted">
                            <tr>
                                <th class="ps-3">Program #</th>
                                <th>Product Details</th>
                                <th>Facility Unit</th>
                                <th>Progress</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPrograms)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                        No active production programs in progress.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentPrograms as $prog): ?>
                                    <?php 
                                        $plannedQuantity = (int)($prog['planned_quantity'] ?? 0);
                                        $completedQuantity = max(0, (int)($prog['completed_quantity'] ?? 0));
                                        $pct = $plannedQuantity > 0
                                            ? min(100, (int)round(($completedQuantity / $plannedQuantity) * 100))
                                            : 0;
                                        $prioClass = match($prog['priority']) {
                                            'URGENT' => 'bg-danger',
                                            'HIGH'   => 'bg-warning text-dark',
                                            'MEDIUM' => 'bg-info text-dark',
                                            default  => 'bg-secondary'
                                        };
                                        $statusClass = match($prog['status']) {
                                            'DRAFT'       => 'bg-secondary',
                                            'APPROVED'    => 'bg-info text-dark',
                                            'SCHEDULED'   => 'bg-primary',
                                            'IN_PROGRESS' => 'bg-warning text-dark',
                                            'COMPLETED'   => 'bg-success',
                                            'HOLD'        => 'bg-danger',
                                            default       => 'bg-dark'
                                        };
                                    ?>
                                    <tr>
                                        <td class="ps-3">
                                            <a href="<?= url('production/programs/view/' . $prog['id']) ?>" class="fw-bold text-decoration-none">
                                                <?= e($prog['program_number']) ?>
                                            </a>
                                            <div class="small text-muted"><?= formatDate($prog['created_at']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($prog['product_name']) ?></div>
                                            <small class="text-muted font-monospace"><?= e($prog['product_code']) ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($prog['unit_name'] ?? 'Unit') ?></span></td>
                                        <td style="min-width: 140px;">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span><?= number_format($completedQuantity) ?> / <?= number_format($plannedQuantity) ?></span>
                                                <span class="fw-bold"><?= $pct ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: <?= min(100, $pct) ?>%"></div>
                                            </div>
                                        </td>
                                        <td><span class="badge <?= $prioClass ?>"><?= e($prog['priority']) ?></span></td>
                                        <td><span class="badge <?= $statusClass ?>"><?= e($prog['status']) ?></span></td>
                                        <td class="text-end pe-3">
                                            <a href="<?= url('production/programs/view/' . $prog['id']) ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent QC & Outwards Side Column -->
    <div class="col-lg-4">
        <!-- Recent QC -->
        <div class="card mb-4 border-0 shadow-sm rounded-3">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom">
                <h6 class="card-title mb-0 fw-bold"><i class="bi bi-patch-check me-2 text-danger"></i>Recent QC Checks</h6>
                <a href="<?= url('production/qc') ?>" class="btn btn-sm btn-link text-decoration-none p-0">All QC &rarr;</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($recentQC)): ?>
                        <li class="list-group-item text-center text-muted py-4">No recent inspections</li>
                    <?php else: ?>
                        <?php foreach ($recentQC as $qc): ?>
                            <?php 
                                $qcBadge = match($qc['status']) {
                                    'PASSED'   => 'bg-success',
                                    'REJECTED' => 'bg-danger',
                                    'REWORK'   => 'bg-warning text-dark',
                                    'PARTIAL'  => 'bg-info text-dark',
                                    default    => 'bg-secondary'
                                };
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                <div>
                                    <div class="fw-semibold text-dark">QC #<?= $qc['id'] ?> — <?= e($qc['product_name']) ?></div>
                                    <small class="text-muted">Passed: <?= $qc['passed_qty'] ?> / Total: <?= $qc['total_qty'] ?></small>
                                </div>
                                <span class="badge <?= $qcBadge ?>"><?= e($qc['status']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Recent Outwards -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom">
                <h6 class="card-title mb-0 fw-bold"><i class="bi bi-truck me-2 text-success"></i>Recent Outward Dispatches</h6>
                <a href="<?= url('production/outward') ?>" class="btn btn-sm btn-link text-decoration-none p-0">All Outwards &rarr;</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($recentOutwards)): ?>
                        <li class="list-group-item text-center text-muted py-4">No dispatches yet</li>
                    <?php else: ?>
                        <?php foreach ($recentOutwards as $out): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                <div>
                                    <div class="fw-semibold text-dark"><?= e($out['outward_number']) ?></div>
                                    <small class="text-muted"><?= number_format($out['total_qty']) ?> pcs &rarr; <?= e($out['warehouse_name']) ?></small>
                                </div>
                                <small class="text-muted"><?= formatDate($out['created_at'], 'd M') ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 5. CHART.JS INITIALIZATION SCRIPTS -->
<!-- ========================================================================= -->
<?php
$trendLabels = array_column($dailyTrends ?? [], 'label');
$trendCut = array_column($dailyTrends ?? [], 'cut');
$trendStitched = array_column($dailyTrends ?? [], 'stitched');
$trendQc = array_column($dailyTrends ?? [], 'qc_passed');
$trendOutward = array_column($dailyTrends ?? [], 'outward');

$wipLabels = array_keys($wipDistribution ?? []);
$wipValues = array_values($wipDistribution ?? []);
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded');
        return;
    }

    // Common Chart Defaults
    Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    Chart.defaults.color = '#64748b';

    // -------------------------------------------------------------
    // Chart 1: Manufacturing Throughput Trends (Line / Area Chart)
    // -------------------------------------------------------------
    const ctxThroughput = document.getElementById('productionThroughputChart');
    if (ctxThroughput) {
        new Chart(ctxThroughput, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [
                    {
                        label: 'Pieces Cut',
                        data: <?= json_encode($trendCut) ?>,
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.08)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Pieces Stitched',
                        data: <?= json_encode($trendStitched) ?>,
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217, 119, 6, 0.08)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'QC Passed',
                        data: <?= json_encode($trendQc) ?>,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.08)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Dispatched to WH',
                        data: <?= json_encode($trendOutward) ?>,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.08)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, usePointStyle: true, padding: 16 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + Number(context.raw).toLocaleString() + ' pcs';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            precision: 0,
                            callback: function(value) { return value + ' pcs'; }
                        }
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------
    // Chart 2: QC Inspection & Quality Yield (Doughnut Chart)
    // -------------------------------------------------------------
    const ctxQc = document.getElementById('qcYieldChart');
    if (ctxQc) {
        const passedQty = <?= (int)($qcBreakdown['total_passed'] ?? 0) ?>;
        const reworkQty = <?= (int)($qcBreakdown['total_rework'] ?? 0) ?>;
        const rejectedQty = <?= (int)($qcBreakdown['total_rejected'] ?? 0) ?>;
        const hasQcData = (passedQty + reworkQty + rejectedQty) > 0;

        new Chart(ctxQc, {
            type: 'doughnut',
            data: {
                labels: hasQcData ? ['Passed', 'Rework Required', 'Defect Rejected'] : ['No Inspection Records'],
                datasets: [{
                    data: hasQcData ? [passedQty, reworkQty, rejectedQty] : [1],
                    backgroundColor: hasQcData ? ['#16a34a', '#f59e0b', '#dc2626'] : ['#e2e8f0'],
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: hasQcData,
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + Number(context.raw).toLocaleString() + ' pcs';
                            }
                        }
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------
    // Chart 3: Pipeline WIP Stage Distribution (Horizontal Bar Chart)
    // -------------------------------------------------------------
    const ctxWip = document.getElementById('wipDistributionChart');
    if (ctxWip) {
        new Chart(ctxWip, {
            type: 'bar',
            data: {
                labels: <?= json_encode($wipLabels) ?>,
                datasets: [{
                    label: 'Garments in Pipeline',
                    data: <?= json_encode($wipValues) ?>,
                    backgroundColor: [
                        'rgba(14, 165, 233, 0.85)',
                        'rgba(6, 182, 212, 0.85)',
                        'rgba(245, 158, 11, 0.85)',
                        'rgba(239, 68, 68, 0.85)',
                        'rgba(34, 197, 94, 0.85)',
                        'rgba(99, 102, 241, 0.85)'
                    ],
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Volume: ' + Number(context.raw).toLocaleString() + ' pcs';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            precision: 0,
                            callback: function(value) { return value + ' pcs'; }
                        }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------
    // Chart 4: Active Program Target Fulfillment (Doughnut Gauge)
    // -------------------------------------------------------------
    const ctxProg = document.getElementById('programFulfillmentChart');
    if (ctxProg) {
        const completedPcs = <?= (int)($completedPcs ?? 0) ?>;
        const pendingPcs = <?= (int)($pendingPcs ?? 0) ?>;
        const hasProgData = (completedPcs + pendingPcs) > 0;

        new Chart(ctxProg, {
            type: 'doughnut',
            data: {
                labels: hasProgData ? ['Completed & QC Approved', 'Pending Production'] : ['No Active Targets'],
                datasets: [{
                    data: hasProgData ? [completedPcs, pendingPcs] : [1],
                    backgroundColor: hasProgData ? ['#16a34a', '#0284c7'] : ['#e2e8f0'],
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '74%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, usePointStyle: true, padding: 12 }
                    },
                    tooltip: {
                        enabled: hasProgData,
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + Number(context.raw).toLocaleString() + ' pcs';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>