<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Production Billing</h4>
        <div class="text-muted small">Bills from QC-passed Production Inventory</div>
    </div>
    <?php if (hasPermission('production_billing', 'create')): ?>
        <a class="btn btn-primary" href="<?= url('production/billing/create') ?>"><i class="bi bi-plus-lg me-1"></i>Create Bill</a>
    <?php endif; ?>
</div>

<!-- Production Billing KPI Summary Cards -->
<div class="row g-3 mb-4">
    <!-- Card 1: Total Revenue -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Billed Revenue</span>
                    <div class="p-2 bg-success bg-opacity-10 text-success rounded-circle">
                        <i class="bi bi-currency-rupee fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-1">₹<?= number_format($stats['total_revenue'] ?? 0, 2) ?></h3>
                <div class="small text-muted d-flex align-items-center gap-1">
                    <span class="text-success fw-semibold">₹<?= number_format($stats['paid_revenue'] ?? 0, 2) ?></span> Paid
                    <span class="text-muted">&bull;</span>
                    <span class="text-warning fw-semibold">₹<?= number_format($stats['pending_revenue'] ?? 0, 2) ?></span> Pending
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Bills -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Invoices / Bills</span>
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle">
                        <i class="bi bi-receipt-cutoff fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?= number_format($stats['total_bills'] ?? 0) ?></h3>
                <div class="small text-muted d-flex align-items-center gap-1">
                    <span class="badge bg-success-subtle text-success"><?= $stats['paid_count'] ?? 0 ?> Settled</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis"><?= $stats['pending_count'] ?? 0 ?> Unsettled</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Bill-based Customers -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Billed Customers</span>
                    <div class="p-2 bg-info bg-opacity-10 text-info rounded-circle">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?= number_format($stats['unique_customers'] ?? 0) ?></h3>
                <div class="small text-muted">
                    <span class="fw-semibold text-info">Unique Clients</span> billed across QC inventory
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Realization Ratio -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Realization Ratio</span>
                    <div class="p-2 bg-warning bg-opacity-10 text-warning rounded-circle">
                        <i class="bi bi-check2-circle fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?= !empty($stats['total_revenue']) ? round(($stats['paid_revenue'] / $stats['total_revenue']) * 100, 1) . '%' : '0%' ?></h3>
                <div class="small text-muted">
                    <span>Payment collection conversion rate</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Production Invoices &amp; Bills</h6>
        <div class="d-flex align-items-center gap-2">
            <input type="text" id="billingSearchInput" class="form-control form-control-sm" placeholder="🔍 Search bill #, buyer, amount..." style="width: 250px;" oninput="filterBillingLive(this.value)">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="billingTable">
            <thead class="table-light">
                <tr>
                    <th>Bill No.</th>
                    <th>Date</th>
                    <th>Bill To</th>
                    <th class="text-center">Lines</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="billingTableBody">
                <?php if (empty($bills)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No Production Bills have been created.</td></tr>
                <?php else: ?>
                    <?php foreach ($bills as $bill): ?>
                        <tr class="billing-row">
                            <td class="fw-semibold"><?= htmlspecialchars($bill['bill_number']) ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($bill['bill_date']))) ?></td>
                            <td>
                                <span class="badge text-bg-light"><?= htmlspecialchars($bill['buyer_type'] ?? 'COMPANY') ?></span>
                                <div class="fw-semibold"><?= htmlspecialchars(($bill['buyer_type'] ?? 'COMPANY') === 'CUSTOMER' ? ($bill['customer_name'] ?? '') : ($bill['company_name'] ?? '')) ?></div>
                                <small class="text-muted"><?= htmlspecialchars(($bill['buyer_type'] ?? 'COMPANY') === 'CUSTOMER' ? ($bill['customer_phone'] ?? '') : ($bill['company_phone'] ?? '')) ?></small>
                            </td>
                            <td class="text-center"><?= (int)$bill['line_count'] ?></td>
                            <td class="text-end fw-semibold">₹<?= number_format((float)$bill['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge <?= $bill['payment_status'] === 'PAID' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>">
                                    <?= htmlspecialchars($bill['payment_status']) ?>
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="action-btns-group justify-content-end">
                                    <?php if (hasPermission('production_billing', 'view')): ?>
                                        <a class="btn btn-outline-info btn-action-icon" title="View" href="<?= url('production/billing/view/' . (int)$bill['id']) ?>"><i class="bi bi-eye"></i></a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('production_billing', 'edit')): ?>
                                        <a class="btn btn-outline-primary btn-action-icon" title="Edit" href="<?= url('production/billing/edit/' . (int)$bill['id']) ?>"><i class="bi bi-pencil"></i></a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('production_billing', 'print')): ?>
                                        <a class="btn btn-outline-success btn-action-icon" title="Print" target="_blank" href="<?= url('production/billing/print/' . (int)$bill['id']) ?>"><i class="bi bi-printer"></i></a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('production_billing', 'delete')): ?>
                                        <form class="d-inline" method="POST" action="<?= url('production/billing/delete/' . (int)$bill['id']) ?>" onsubmit="return confirm('Delete this bill? Its billed QR pieces will become available again.')">
                                            <?= CSRF::input() ?>
                                            <button class="btn btn-outline-danger btn-action-icon" title="Delete"><i class="bi bi-trash"></i></button>
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

<script>
function filterBillingLive(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#billingTableBody .billing-row');
    rows.forEach(r => {
        const text = (r.textContent || '').toLowerCase();
        r.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}
</script>
