<?php
// Production Unit Customer Returns & Exchanges View
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-arrow-return-left text-danger me-2"></i>Production Customer Returns &amp; Exchanges
            </h4>
            </div>
        <div class="d-flex gap-2">
            <a href="<?= url('production/returns/create') ?>" class="btn btn-danger btn-sm fw-semibold shadow-sm px-3">
                <i class="bi bi-plus-circle me-1"></i> Record Return / Exchange
            </a>
            
        </div>
    </div>

    <!-- KPI Summary Analytics Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Returns -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total Returns / Notes</span>
                        <div class="p-2 rounded-circle bg-danger-subtle text-danger">
                            <i class="bi bi-receipt-cutoff fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-danger mb-1"><?= number_format($stats['total_returns'] ?? 0) ?></h3>
                    <div class="small text-muted">Processed Returns &amp; Exchanges</div>
                </div>
            </div>
        </div>

        <!-- 2. Returned Pieces -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Returned Pieces</span>
                        <div class="p-2 rounded-circle bg-warning-subtle text-warning">
                            <i class="bi bi-box-seam fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= number_format($stats['total_pieces'] ?? 0) ?></h3>
                    <div class="small text-muted">Garments Returned / Exchanged</div>
                </div>
            </div>
        </div>

        <!-- 3. Total Credit Reversal -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total Refund / Credit</span>
                        <div class="p-2 rounded-circle bg-primary-subtle text-primary">
                            <i class="bi bi-currency-rupee fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-primary mb-1">₹ <?= number_format($stats['total_amount'] ?? 0, 2) ?></h3>
                    <div class="small text-muted">Net Credit Note Reversal</div>
                </div>
            </div>
        </div>

        <!-- 4. GST Reversal -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">GST Reversal</span>
                        <div class="p-2 rounded-circle bg-info-subtle text-info">
                            <i class="bi bi-percent fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-info mb-1">₹ <?= number_format($stats['total_gst_reversal'] ?? 0, 2) ?></h3>
                    <div class="small text-muted">Tax Adjusted via Credit Note</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('production/returns') ?>" class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="returnsSearchInput" class="form-control form-control-sm border-start-0" placeholder="Search Return #, Bill #, Customer..." value="<?= e($search) ?>" oninput="filterReturnsLive(this.value)">
                    </div>
                </div>
                <div class="col-lg-2 col-md-3">
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Return Types --</option>
                        <option value="CREDIT_NOTE" <?= $type === 'CREDIT_NOTE' ? 'selected' : '' ?>>Credit Note</option>
                        <option value="EXCHANGE" <?= $type === 'EXCHANGE' ? 'selected' : '' ?>>Exchange</option>
                        <option value="REFUND" <?= $type === 'REFUND' ? 'selected' : '' ?>>Refund</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>" title="From Date" onchange="this.form.submit()">
                </div>
                <div class="col-lg-2 col-md-3">
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>" title="To Date" onchange="this.form.submit()">
                </div>
                <div class="col-lg-2 col-md-3 text-end d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                    <?php if ($search || $type || $dateFrom || $dateTo): ?>
                    <a href="<?= url('production/returns') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Returns Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-list-check text-danger me-2"></i>Returns &amp; Credit Notes Log (<?= count($returns) ?>)
            </h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($returns)): ?>
                <div class="text-center py-5 text-muted">
                    <div class="d-inline-flex p-3 rounded-circle bg-light mb-2">
                        <i class="bi bi-inbox fs-1 text-secondary"></i>
                    </div>
                    <div class="fw-bold fs-6">No Return or Exchange Records Found</div>
                    <p class="small mb-3">When customers return items from factory bills, they will appear here.</p>
                    <a href="<?= url('production/returns/create') ?>" class="btn btn-sm btn-danger px-3">
                        <i class="bi bi-plus-circle me-1"></i> Record First Return
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="returnsTable">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th>Return #</th>
                                <th>Original Bill #</th>
                                <th>Customer / Company</th>
                                <th class="text-center">Type</th>
                                <th class="text-center">Disposition</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Taxable</th>
                                <th class="text-end">GST Reversal</th>
                                <th class="text-end">Net Credit</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="returnsTableBody">
                            <?php foreach ($returns as $r): ?>
                                <tr class="return-row">
                                    <td>
                                        <a href="<?= url('production/returns/view/' . $r['id']) ?>" class="fw-bold font-monospace text-danger text-decoration-none">
                                            <?= e($r['return_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($r['bill_id']): ?>
                                            <a href="<?= url('production/billing/view/' . $r['bill_id']) ?>" class="font-monospace text-primary text-decoration-none small">
                                                <?= e($r['bill_number']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small"><?= e($r['bill_number'] ?? '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($r['customer_name']) ?></div>
                                        <?php if ($r['customer_phone']): ?>
                                            <small class="text-muted"><?= e($r['customer_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-subtle text-secondary border">
                                            <?= e($r['return_type']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($r['disposition'] === 'RESTOCK_INVENTORY'): ?>
                                            <span class="badge bg-success-subtle text-success border">Restocked</span>
                                        <?php elseif ($r['disposition'] === 'REWORK_STITCHING'): ?>
                                            <span class="badge bg-warning-subtle text-warning border">Rework</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border">Scrap</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold"><?= (int)$r['total_returned_qty'] ?> pcs</td>
                                    <td class="text-end">₹<?= number_format((float)$r['subtotal'], 2) ?></td>
                                    <td class="text-end text-muted">₹<?= number_format((float)$r['gst_reversal_amount'], 2) ?></td>
                                    <td class="text-end fw-bold text-danger">₹<?= number_format((float)$r['net_amount'], 2) ?></td>
                                    <td class="small text-muted"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                                    <td class="text-end">
                                        <div class="action-btns-group justify-content-end">
                                            <a href="<?= url('production/returns/view/' . $r['id']) ?>" class="btn btn-outline-info btn-action-icon" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= url('production/returns/print/' . $r['id']) ?>" target="_blank" class="btn btn-outline-success btn-action-icon" title="Print Credit Note">
                                                <i class="bi bi-printer"></i>
                                            </a>
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
</div>

<script>
function filterReturnsLive(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#returnsTableBody .return-row');
    rows.forEach(r => {
        const text = (r.textContent || '').toLowerCase();
        r.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}
</script>
