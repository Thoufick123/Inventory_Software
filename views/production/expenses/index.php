<?php
// Production Expenses Management View (Unit 1 & Unit 2)
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-wallet2 text-primary me-2"></i>Production Expenses
            </h4>
            <span class="text-muted small">Manage Unit 1 &amp; Unit 2 factory floor expenses, machine maintenance, sewing trims, and utilities</span>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission('production_expenses', 'create')): ?>
            <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#recordExpenseModal">
                <i class="bi bi-plus-circle me-1"></i> Record Production Expense
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI Summary Analytics Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Production Expense Spend -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total Production Spend</span>
                        <div class="p-2 rounded-circle bg-primary-subtle text-primary">
                            <i class="bi bi-currency-rupee fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-primary mb-1">₹ <?= number_format($stats['total_cost'], 2) ?></h3>
                    <div class="small text-muted d-flex justify-content-between align-items-center">
                        <span><?= number_format($stats['total_records']) ?> Transaction(s)</span>
                        <span class="badge bg-light text-dark border">All Units Combined</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Unit 1 vs Unit 2 Spend -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Unit 1 &amp; Unit 2 Costs</span>
                        <div class="p-2 rounded-circle bg-info-subtle text-info">
                            <i class="bi bi-diagram-2 fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <div>
                            <span class="badge bg-primary text-white me-1">Unit 1</span>
                            <strong class="text-dark">₹ <?= number_format($stats['unit1_cost'], 2) ?></strong>
                        </div>
                        <div>
                            <span class="badge bg-info text-white me-1">Unit 2</span>
                            <strong class="text-dark">₹ <?= number_format($stats['unit2_cost'], 2) ?></strong>
                        </div>
                    </div>
                    <div class="small text-muted d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                        <span>Common/Facility: ₹<?= number_format($stats['both_units_cost'], 2) ?></span>
                        <span class="badge bg-info-subtle text-info border">By Unit</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Materials & Maintenance -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Materials &amp; Maintenance</span>
                        <div class="p-2 rounded-circle bg-success-subtle text-success">
                            <i class="bi bi-scissors fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <div>
                            <small class="text-muted d-block">Maintenance &amp; Repairs</small>
                            <strong class="text-danger">₹<?= number_format($stats['maintenance_cost'] + ($stats['machinery_cost'] ?? 0), 2) ?></strong>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Threads / Needles / Trims</small>
                            <strong class="text-success">₹<?= number_format($stats['sewing_materials_cost'], 2) ?></strong>
                        </div>
                    </div>
                    <div class="small text-muted d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                        <span>Operations &amp; Utilities: ₹<?= number_format($stats['operations_cost'], 2) ?></span>
                        <span class="badge bg-success-subtle text-success border">Floor Breakdown</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Settlement & Payables -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Settlement &amp; Payables</span>
                        <div class="p-2 rounded-circle bg-danger-subtle text-danger">
                            <i class="bi bi-clock-history fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <div>
                            <small class="text-muted d-block">Paid Settled</small>
                            <strong class="text-success">₹<?= number_format($stats['total_paid'], 2) ?></strong>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Vendor Due</small>
                            <strong class="text-danger">₹<?= number_format($stats['total_pending'], 2) ?></strong>
                        </div>
                    </div>
                    <div class="small text-muted d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                        <span><?= number_format(($stats['count_pending'] ?? 0) + ($stats['count_partial'] ?? 0)) ?> Unsettled Bills</span>
                        <span class="badge bg-danger-subtle text-danger border">Payables</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('production/expenses') ?>" class="row g-2 align-items-center">
                <!-- Search -->
                <div class="col-lg-3 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="expensesSearchInput" class="form-control form-control-sm border-start-0" placeholder="Search item, vendor, invoice..." value="<?= e($search) ?>" autocomplete="off">
                    </div>
                </div>

                <!-- Unit Filter -->
                <div class="col-lg-2 col-md-3">
                    <select name="production_unit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Production Units --</option>
                        <?php foreach ($productionUnitOptions as $unitValue => $unitLabel): ?>
                            <option value="<?= e($unitValue) ?>" <?= $productionUnit === $unitValue ? 'selected' : '' ?>><?= e($unitLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Category Filter -->
                <div class="col-lg-2 col-md-3">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Categories --</option>
                        <?php foreach ($expenseCategoryOptions as $categoryValue => $categoryLabel): ?>
                            <option value="<?= e($categoryValue) ?>" <?= $category === $categoryValue ? 'selected' : '' ?>><?= e($categoryLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Payment Status -->
                <div class="col-lg-2 col-md-3">
                    <select name="payment_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Payment Statuses --</option>
                        <option value="PAID" <?= $paymentStatus === 'PAID' ? 'selected' : '' ?>>Paid</option>
                        <option value="PARTIAL" <?= $paymentStatus === 'PARTIAL' ? 'selected' : '' ?>>Partial</option>
                        <option value="PENDING" <?= $paymentStatus === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="col-lg-1 col-md-3">
                    <input type="date" name="date_from" class="form-control form-control-sm" title="From Date" value="<?= e($dateFrom) ?>" onchange="this.form.submit()">
                </div>
                <div class="col-lg-1 col-md-3">
                    <input type="date" name="date_to" class="form-control form-control-sm" title="To Date" value="<?= e($dateTo) ?>" onchange="this.form.submit()">
                </div>

                <!-- Reset -->
                <div class="col-lg-1 col-md-2 text-end">
                    <a href="<?= url('production/expenses') ?>" class="btn btn-sm btn-outline-secondary w-100" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Production Expenses Table Log -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-card-list text-primary me-2"></i>Production Expenses Log (<?= count($expenses) ?>)
            </h6>
            <span class="text-muted small">Unit 1 &amp; Unit 2 floor expenses, maintenance, and material purchases</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($expenses)): ?>
                <div class="text-center py-5 text-muted">
                    <div class="d-inline-flex p-3 rounded-circle bg-light mb-2">
                        <i class="bi bi-inbox fs-1 text-secondary"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">No Production Expenses Found</h5>
                    <p class="text-muted small mb-0">No records match your filters. Click "Record Production Expense" to add a new floor or maintenance expense.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="expensesTable" style="font-size: 13px;">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th>Expense # &amp; Date</th>
                                <th>Unit &amp; Category</th>
                                <th>Item &amp; Reason</th>
                                <th>Paid To / Vendor</th>
                                <th class="text-end">Quantity &amp; Rate</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-center">Payment Status</th>
                                <th class="text-center">Bill / Photo</th>
                                <th class="text-center" style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $e): ?>
                                <tr class="expense-row">
                                    <!-- Expense # & Date -->
                                    <td>
                                        <span class="badge bg-dark font-monospace fs-7 px-2 py-1"><?= e($e['expense_number']) ?></span>
                                        <div class="text-muted small mt-1">
                                            <i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($e['expense_date'])) ?>
                                        </div>
                                        <?php if (!empty($e['invoice_number'])): ?>
                                            <small class="text-muted d-block">Bill #: <?= e($e['invoice_number']) ?></small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Unit & Category -->
                                    <td>
                                        <!-- Unit Badge -->
                                        <?php
                                        $unitBadge = match($e['production_unit']) {
                                            'PRODUCTION_1' => 'bg-primary text-white',
                                            'PRODUCTION_2' => 'bg-info text-white',
                                            'BOTH_UNITS'   => 'bg-dark text-white',
                                            default        => 'bg-secondary text-white'
                                        };
                                        $unitLabel = match($e['production_unit']) {
                                            'PRODUCTION_1' => 'Unit 1',
                                            'PRODUCTION_2' => 'Unit 2',
                                            'BOTH_UNITS'   => 'Both Units',
                                            default        => 'General'
                                        };
                                        ?>
                                        <span class="badge <?= $unitBadge ?> px-2 py-1 mb-1 d-inline-block">
                                            <i class="bi bi-building me-1"></i><?= $unitLabel ?>
                                        </span>

                                        <!-- Category Badge -->
                                        <div>
                                            <?php
                                            $catBadge = match($e['category']) {
                                                'MACHINERY_ASSET', 'MAINTENANCE_REPAIR' => 'bg-danger-subtle text-danger border-danger-subtle',
                                                'SEWING_MATERIALS'                      => 'bg-success-subtle text-success border-success-subtle',
                                                'OPERATIONAL_UTILITY'                   => 'bg-primary-subtle text-primary border-primary-subtle',
                                                default                                 => 'bg-secondary-subtle text-secondary border-secondary-subtle'
                                            };
                                            $catIcon = match($e['category']) {
                                                'MACHINERY_ASSET', 'MAINTENANCE_REPAIR' => 'bi-tools',
                                                'SEWING_MATERIALS'                      => 'bi-scissors',
                                                'OPERATIONAL_UTILITY'                   => 'bi-lightning-charge',
                                                default                                 => 'bi-tag'
                                            };
                                            $catLabel = match($e['category']) {
                                                'MACHINERY_ASSET', 'MAINTENANCE_REPAIR' => 'Maintenance & Repairs',
                                                'SEWING_MATERIALS'                      => 'Sewing Materials & Trims',
                                                'OPERATIONAL_UTILITY'                   => 'Operations & Utilities',
                                                default                                 => str_replace('_', ' ', $e['category'])
                                            };
                                            ?>
                                            <span class="badge <?= $catBadge ?> border small fw-semibold">
                                                <i class="bi <?= $catIcon ?> me-1"></i><?= e($catLabel) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Item Name & Reason -->
                                    <td>
                                        <strong class="text-dark d-block"><?= e($e['item_name']) ?></strong>
                                        <?php if (!empty($e['notes'])): ?>
                                            <small class="text-muted d-block"><i class="bi bi-chat-left-text me-1 text-primary"></i><?= e($e['notes']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($e['program_number'])): ?>
                                            <small class="text-primary d-block">
                                                <i class="bi bi-diagram-3 me-1"></i>Program: <?= e($e['program_number']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Vendor Details -->
                                    <td>
                                        <strong class="text-dark d-block"><?= e($e['vendor_name'] ?: 'Direct / Floor') ?></strong>
                                        <?php if (!empty($e['vendor_company'])): ?>
                                            <span class="text-muted small d-block"><i class="bi bi-building me-1"></i><?= e($e['vendor_company']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($e['vendor_phone'])): ?>
                                            <span class="text-muted small d-block"><i class="bi bi-telephone me-1"></i><?= e($e['vendor_phone']) ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Quantity & Rate -->
                                    <td class="text-end">
                                        <div class="fw-bold text-dark fs-7"><?= number_format($e['quantity'], 2) ?> <?= e($e['unit_type'] ?: 'PCS') ?></div>
                                        <small class="text-muted">@ ₹<?= number_format($e['unit_cost'], 2) ?> / <?= e($e['unit_type'] ?: 'PCS') ?></small>
                                    </td>

                                    <!-- Total Cost -->
                                    <td class="text-end">
                                        <div class="fw-bold text-primary fs-6">₹ <?= number_format($e['total_cost'], 2) ?></div>
                                        <?php if ($e['paid_amount'] > 0 && $e['paid_amount'] < $e['total_cost']): ?>
                                            <small class="text-muted d-block">Paid: ₹<?= number_format($e['paid_amount'], 2) ?></small>
                                            <small class="text-danger fw-semibold d-block">Due: ₹<?= number_format($e['total_cost'] - $e['paid_amount'], 2) ?></small>
                                        <?php elseif ($e['paid_amount'] >= $e['total_cost']): ?>
                                            <small class="text-success fw-semibold"><i class="bi bi-check-all me-1"></i>Settled</small>
                                        <?php else: ?>
                                            <small class="text-warning fw-semibold">Unpaid</small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Payment Status -->
                                    <td class="text-center">
                                        <?php
                                        $payBadge = match($e['payment_status']) {
                                            'PAID'    => 'bg-success text-white',
                                            'PARTIAL' => 'bg-warning text-dark',
                                            default   => 'bg-danger text-white'
                                        };
                                        ?>
                                        <span class="badge <?= $payBadge ?> px-2 py-1"><?= e($e['payment_status']) ?></span>
                                        <div class="small text-muted mt-1"><?= e($e['payment_method']) ?></div>
                                    </td>

                                    <!-- Proof Photo / Bill -->
                                    <td class="text-center">
                                        <?php if (!empty($e['photo_url'])): ?>
                                            <a href="<?= uploadUrl($e['photo_url']) ?>" target="_blank" title="View bill slip or receipt photo">
                                                <img src="<?= uploadUrl($e['photo_url']) ?>" alt="Proof" class="rounded border shadow-sm" style="width: 36px; height: 36px; object-fit: cover;">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <?php if (hasPermission('production_expenses', 'view')): ?>
                                            <button type="button" class="btn btn-outline-info" title="View Expense Details" onclick='openExpenseReceipt(<?= (int)$e['id'] ?>)'>
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php endif; ?>

                                            <!-- Print Voucher Button -->
                                            <?php if (hasPermission('production_expenses', 'print')): ?>
                                            <button type="button" class="btn btn-outline-secondary" title="Print Expense Voucher" onclick='openExpenseReceipt(<?= (int)$e['id'] ?>)'>
                                                <i class="bi bi-printer"></i>
                                            </button>
                                            <?php endif; ?>

                                            <?php if (hasPermission('production_expenses', 'edit')): ?>
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-outline-primary" title="Edit Expense Details" onclick='openEditExpenseModal(<?= json_encode($e) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>

                                            <?php if (hasPermission('production_expenses', 'delete')): ?>
                                            <!-- Delete Button -->
                                            <form method="POST" action="<?= url('production/expenses/delete/' . $e['id']) ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete Expense #<?= e(addslashes($e['expense_number'])) ?> (<?= e(addslashes($e['item_name'])) ?>)?');">
                                                <?= CSRF::field() ?>
                                                <button type="submit" class="btn btn-outline-danger" title="Delete Expense Record">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls Footer -->
                <div class="card-footer bg-white py-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-3" id="expensesPaginationFooter">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="small text-muted fw-medium" id="expensesPageInfo">
                            Showing 1 to 10 of <?= count($expenses) ?> expenses
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0 fw-medium">Show:</label>
                            <select id="expensesPageSizeSelect" class="form-select form-select-sm" style="width: auto;">
                                <option value="10" selected>10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                    </div>
                    <nav aria-label="Expenses table pagination">
                        <ul class="pagination pagination-sm mb-0 gap-1" id="expensesPaginationNav">
                            <!-- Populated dynamically via JavaScript -->
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. RECORD EXPENSE MODAL (Production Unit Operational Expense Form)        -->
<!-- ========================================================================= -->
<div class="modal fade" id="recordExpenseModal" tabindex="-1" aria-labelledby="recordExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="recordExpenseModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Record Production Unit Operational Expense
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('production/expenses/store') ?>" id="recordExpenseForm" enctype="multipart/form-data">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Production Unit -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Production Unit Location <span class="text-danger">*</span></label>
                            <select name="production_unit" class="form-select" required>
                                <?php foreach ($productionUnitOptions as $uVal => $uLbl): ?>
                                    <option value="<?= e($uVal) ?>" <?= $uVal === 'PRODUCTION_1' ? 'selected' : '' ?>>
                                        <?= e($uLbl) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Expense Category -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Expense Category <span class="text-danger">*</span></label>
                            <input type="text" name="category" class="form-control" list="prodCategoryList" placeholder="e.g. Logistics & Freight Shipping, Machine Maintenance..." required>
                            <datalist id="prodCategoryList">
                                <?php foreach ($expenseCategoryOptions as $catVal => $catLbl): ?>
                                    <option value="<?= e($catLbl) ?>"><?= e($catLbl) ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <!-- Particulars / Line Item -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Particulars / Item Description <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Staff Tea & Snacks / Carton Packaging / Sewing Needles" required>
                        </div>

                        <!-- Amount -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Amount (INR ₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control fw-bold font-monospace" placeholder="0.00" required>
                            </div>
                        </div>

                        <!-- Paid To / Vendor -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Paid To / Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" name="paid_to" class="form-control" placeholder="e.g. Sri Balaji Packaging / Tea Stall / Vendor" required>
                        </div>

                        <!-- Payment Method -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Payment Mode <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="CASH">Cash (Petty Cash)</option>
                                <option value="UPI">UPI (GPay / PhonePe)</option>
                                <option value="BANK_TRANSFER">Bank Transfer (NEFT/RTGS)</option>
                                <option value="CARD">Debit / Credit Card</option>
                                <option value="CHEQUE">Cheque</option>
                            </select>
                        </div>

                        <!-- Payment Status -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Payment Status</label>
                            <select name="payment_status" class="form-select">
                                <option value="PAID">PAID (Disbursed)</option>
                                <option value="PENDING">PENDING (Payable)</option>
                                <option value="PARTIAL">PARTIAL</option>
                            </select>
                        </div>

                        <!-- Expense Date -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Expense Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="col-12">
                            <details>
                                <summary class="small fw-semibold text-primary cursor-pointer">Additional details (optional)</summary>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Vendor Phone</label>
                                        <input type="text" name="vendor_phone" class="form-control" placeholder="e.g. 9840123456">
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded-3 border">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <h6 class="fw-bold text-primary mb-0 small text-uppercase">
                                                    <i class="bi bi-truck me-1"></i>Logistics &amp; Transport Details
                                                </h6>
                                                <span class="text-muted" style="font-size: 11px;">Skip for general expenses</span>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted mb-1">Transporter Name</label>
                                                    <input type="text" name="transporter_name" class="form-control form-control-sm" placeholder="e.g. VRL Logistics">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted mb-1">Vehicle #</label>
                                                    <input type="text" name="vehicle_number" class="form-control form-control-sm" placeholder="e.g. TN-01-AB-1234">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted mb-1">Bill / LR #</label>
                                                    <input type="text" name="invoice_number" class="form-control form-control-sm" placeholder="e.g. INV-9876">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between">
                                            <span><i class="bi bi-paperclip me-1"></i>Attach Bill / Receipt / Invoice Copy</span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.7rem;"><i class="bi bi-cloud-arrow-up me-1"></i>Cloudinary CDN</span>
                                        </label>
                                        <input type="file" name="receipt_file" class="form-control" accept="image/*,.pdf">
                                        <div class="form-text small text-muted" style="font-size: 11px;"><i class="bi bi-cloud-check text-success me-1"></i>JPG, PNG, WEBP or PDF.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small text-muted">Internal Remarks / Notes</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional details, approval remarks, or bill descriptions..."></textarea>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2.5">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold"><i class="bi bi-check-circle me-1"></i>Save &amp; Generate Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. EDIT EXPENSE MODAL                                                     -->
<!-- ========================================================================= -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-pencil-square me-2"></i>Edit Production Expense
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editExpenseForm" enctype="multipart/form-data">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label fw-bold text-dark required mb-1">Production Unit</label>
                            <select name="production_unit" id="editProductionUnit" class="form-select form-select-sm fw-bold">
                                <?php foreach ($productionUnitOptions as $uVal => $uLbl): ?>
                                    <option value="<?= e($uVal) ?>"><?= e($uLbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label fw-bold text-dark required mb-1">Expense Date</label>
                            <input type="date" name="expense_date" id="editExpenseDate" class="form-control form-control-sm fw-bold" required>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <label class="form-label fw-bold text-dark required mb-1">Category</label>
                            <select name="category" id="editCategory" class="form-select form-select-sm fw-bold">
                                <?php foreach ($expenseCategoryOptions as $catVal => $catLbl): ?>
                                    <option value="<?= e($catVal) ?>"><?= e($catLbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark required mb-1">Expense Item Name</label>
                        <input type="text" 
                               name="item_name" 
                               id="editItemName" 
                               class="form-control fw-bold" 
                               list="expenseTypesList" 
                               placeholder="Item or expense description..." 
                               required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-6">
                            <label class="form-label fw-bold text-dark required mb-1">Quantity</label>
                            <input type="number" 
                                   step="any" 
                                   min="0.01" 
                                   name="quantity" 
                                   id="editQuantity" 
                                   class="form-control fw-bold text-center" 
                                   required 
                                   oninput="calculateEditExpenseTotal()">
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label fw-bold text-dark mb-1">Unit</label>
                            <select name="unit_type" id="editUnitType" class="form-select fw-semibold">
                                <?php foreach ($unitsList as $u): ?>
                                    <option value="<?= e($u['abbreviation']) ?>"><?= e($u['abbreviation']) ?><?= (!empty($u['name']) && strtoupper($u['name']) !== strtoupper($u['abbreviation'])) ? ' (' . e($u['name']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label fw-bold text-dark required mb-1">Price (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">₹</span>
                                <input type="number" 
                                       step="any" 
                                       min="0" 
                                       name="unit_cost" 
                                       id="editUnitCost" 
                                       class="form-control fw-bold text-primary" 
                                       placeholder="0.00" 
                                       required 
                                       oninput="calculateEditExpenseTotal()">
                            </div>
                        </div>
                    </div>

                    <!-- Total Amount Live Display -->
                    <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold text-muted small text-uppercase">Total Cost:</span>
                        <div class="d-flex align-items-center">
                            <span class="fs-5 fw-bold text-primary me-1">₹</span>
                            <span class="fs-4 fw-bolder text-primary font-monospace" id="editDisplayTotal">0.00</span>
                            <input type="hidden" name="total_cost" id="editTotalCost" value="0.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label small fw-bold text-dark mb-1">Paid To / Vendor</label>
                            <input type="text" name="vendor_name" id="editVendorName" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label small fw-bold text-dark mb-1">Payment Method</label>
                            <select name="payment_method" id="editPaymentMethod" class="form-select form-select-sm">
                                <option value="CASH">Cash</option>
                                <option value="UPI">UPI / GPay / PhonePe</option>
                                <option value="BANK_TRANSFER">Bank Transfer / NEFT</option>
                                <option value="CREDIT">Credit / Due</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <label class="form-label small fw-bold text-dark mb-1">Payment Status</label>
                            <select name="payment_status" id="editPaymentStatus" class="form-select form-select-sm">
                                <option value="PAID">Paid / Settled</option>
                                <option value="PENDING">Pending</option>
                                <option value="PARTIAL">Partial</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label small fw-bold text-dark mb-1">Bill / Invoice #</label>
                            <input type="text" name="invoice_number" id="editInvoiceNumber" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-8 col-sm-6">
                            <label class="form-label small fw-bold text-dark mb-1">Reason / Notes</label>
                            <input type="text" name="notes" id="editNotes" class="form-control form-control-sm" placeholder="Reason or remarks">
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark mb-1">
                            <i class="bi bi-image text-primary me-1"></i>Receipt / Bill Proof Image
                        </label>
                        <input type="file" 
                               name="photo" 
                               id="editPhoto" 
                               class="form-control form-control-sm" 
                               accept="image/*,.pdf" 
                               onchange="previewExpenseImage(this, 'editPhotoPreview')">
                        <div id="editPhotoPreview" class="mt-2 text-center d-none p-2 bg-light rounded border">
                            <img src="" class="img-thumbnail rounded shadow-sm border" style="max-height: 100px;" alt="Selected receipt">
                            <div class="small text-muted mt-1" id="editPhotoStatus">Current Image</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                        <i class="bi bi-save me-1"></i> Update Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. PRINTABLE EXPENSE VOUCHER MODAL                                        -->
<!-- ========================================================================= -->
<div class="modal fade" id="expenseReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-receipt me-1"></i>Production Expense Voucher
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="printableExpenseVoucherArea">
                <div class="text-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark">GRM APPARELS</h5>
                    <small class="text-muted d-block">Production Expense Voucher</small>
                    <span class="badge bg-light text-dark border mt-1" id="recExpNo">EXP-2026-0001</span>
                </div>
                <hr class="my-2">
                <div class="row g-1 small mb-3">
                    <div class="col-6"><strong>Date:</strong> <span id="recExpDate">-</span></div>
                    <div class="col-6 text-end"><strong>Status:</strong> <span id="recExpStatus">-</span></div>
                    <div class="col-12"><strong>Production Unit:</strong> <span id="recExpUnit">-</span></div>
                    <div class="col-12"><strong>Category:</strong> <span id="recExpCategory">-</span></div>
                    <div class="col-12"><strong>Paid To / Vendor:</strong> <span id="recExpVendor">-</span></div>
                    <div class="col-12" id="recExpCompanyRow"><strong>Company:</strong> <span id="recExpCompany">-</span></div>
                    <div class="col-12" id="recExpProgramRow"><strong>Linked Program:</strong> <span id="recExpProgram">-</span></div>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Description</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Rate</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td id="recExpItem">-</td>
                                <td class="text-end" id="recExpQty">0.00</td>
                                <td class="text-end" id="recExpRate">₹0.00</td>
                                <td class="text-end fw-bold" id="recExpTotal">₹0.00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total Expense Cost:</th>
                                <th class="text-end text-primary" id="recExpTotalFooter">₹0.00</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Amount Paid:</th>
                                <th class="text-end text-success" id="recExpPaid">₹0.00</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Balance Due:</th>
                                <th class="text-end text-danger" id="recExpDue">₹0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="small text-muted mb-3" id="recExpNotes"></div>
                <div class="d-flex justify-content-between text-muted small pt-3 border-top">
                    <div>Receiver's Signature</div>
                    <div>Authorized Signatory</div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <?php if (hasPermission('production_expenses', 'print')): ?>
                <button type="button" class="btn btn-dark btn-sm px-3" onclick="printExpenseVoucherArea()">
                    <i class="bi bi-printer me-1"></i> Print Voucher
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 4. QUICK ADD EXPENSE CATEGORY MODAL -->
<div class="modal fade" id="modalQuickAddCategory" tabindex="-1" aria-labelledby="modalQuickAddCategoryLabel" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header py-2 px-3 bg-primary text-white">
                <h6 class="modal-title fw-bold" id="modalQuickAddCategoryLabel" style="font-size: 14px;">
                    <i class="bi bi-tag-fill me-1"></i>New Expense Category
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Category Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           id="quickCategoryNameInput" 
                           class="form-control form-control-sm fw-bold" 
                           placeholder="e.g. Embroidery Supplies, Generator Fuel" 
                           autocomplete="off" 
                           onkeydown="if(event.key==='Enter'){event.preventDefault();submitQuickAddCategory();}">
                </div>
                <div class="mb-1">
                    <label class="form-label small fw-bold text-dark mb-1">Description (Optional)</label>
                    <input type="text" 
                           id="quickCategoryDescInput" 
                           class="form-control form-control-sm" 
                           placeholder="e.g. Direct factory expense category" 
                           autocomplete="off" 
                           onkeydown="if(event.key==='Enter'){event.preventDefault();submitQuickAddCategory();}">
                </div>
                <div id="quickCategoryError" class="text-danger small mt-2 d-none"></div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold" id="btnSubmitQuickCategory" onclick="submitQuickAddCategory()">
                    <i class="bi bi-check-circle me-1"></i> Save Category
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 5. QUICK ADD MEASUREMENT UNIT MODAL -->
<div class="modal fade" id="modalQuickAddUnit" tabindex="-1" aria-labelledby="modalQuickAddUnitLabel" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header py-2 px-3 bg-primary text-white">
                <h6 class="modal-title fw-bold" id="modalQuickAddUnitLabel" style="font-size: 14px;">
                    <i class="bi bi-rulers me-1"></i>New Measurement Unit
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Unit Symbol / Code <span class="text-danger">*</span></label>
                    <input type="text" 
                           id="quickUnitCodeInput" 
                           class="form-control form-control-sm fw-bold font-monospace text-uppercase" 
                           placeholder="e.g. BDL, DRUM, PKG" 
                           maxlength="15" 
                           autocomplete="off" 
                           onkeydown="if(event.key==='Enter'){event.preventDefault();submitQuickAddUnit();}">
                </div>
                <div class="mb-1">
                    <label class="form-label small fw-bold text-dark mb-1">Full Unit Name (Optional)</label>
                    <input type="text" 
                           id="quickUnitNameInput" 
                           class="form-control form-control-sm" 
                           placeholder="e.g. Bundle, Drum, Package" 
                           autocomplete="off" 
                           onkeydown="if(event.key==='Enter'){event.preventDefault();submitQuickAddUnit();}">
                </div>
                <div id="quickUnitError" class="text-danger small mt-2 d-none"></div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold" id="btnSubmitQuickUnit" onclick="submitQuickAddUnit()">
                    <i class="bi bi-check-circle me-1"></i> Save Unit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let expenseTypesMaster = <?= json_encode($expenseItems ?? []) ?>;
let stagedExpenseItems = [];

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function calculateNewExpenseTotal() {
    const q = parseFloat(document.getElementById('newQuantity').value) || 0;
    const p = parseFloat(document.getElementById('newUnitCost').value) || 0;
    const total = q * p;
    const itemSubtotal = document.getElementById('newItemSubtotal');
    if (itemSubtotal) {
        itemSubtotal.textContent = '₹ ' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

function addExpenseItemToList() {
    const nameInput = document.getElementById('newItemName');
    const qtyInput = document.getElementById('newQuantity');
    const unitInput = document.getElementById('newUnitType');
    const priceInput = document.getElementById('newUnitCost');
    const reasonInput = document.getElementById('newReason');

    const name = nameInput.value.trim();
    const qty = parseFloat(qtyInput.value) || 0;
    const unit = unitInput ? unitInput.value.trim() : 'PCS';
    const price = parseFloat(priceInput.value);
    const reason = reasonInput ? reasonInput.value.trim() : '';

    if (!name) {
        alert('Please select or enter an Expense Type.');
        nameInput.focus();
        return false;
    }
    if (qty <= 0) {
        alert('Quantity must be greater than zero.');
        qtyInput.focus();
        return false;
    }
    if (isNaN(price) || price < 0) {
        alert('Please enter a valid price (zero or greater).');
        priceInput.focus();
        return false;
    }

    const amount = Math.round(qty * price * 100) / 100;
    stagedExpenseItems.push({
        name: name,
        qty: qty,
        unit: unit,
        price: price,
        amount: amount,
        reason: reason
    });

    renderStagedExpenses();

    // Reset inputs for next item addition
    nameInput.value = '';
    qtyInput.value = '1';
    priceInput.value = '';
    if (reasonInput) reasonInput.value = '';
    calculateNewExpenseTotal();
    nameInput.focus();
    return true;
}

function removeStagedExpenseItem(index) {
    if (index >= 0 && index < stagedExpenseItems.length) {
        stagedExpenseItems.splice(index, 1);
        renderStagedExpenses();
    }
}

function renderStagedExpenses() {
    const tbody = document.getElementById('stagedExpensesTbody');
    const container = document.getElementById('stagedExpensesContainer');
    const countSpan = document.getElementById('stagedItemCount');
    const grandTotalSpan = document.getElementById('stagedGrandTotal');
    const headerTotalSpan = document.getElementById('stagedGrandTotalHeader');
    const totalCostHidden = document.getElementById('newTotalCost');
    const submitBtn = document.getElementById('btnSubmitExpenses');

    if (!tbody || !container) return;

    tbody.innerHTML = '';

    if (stagedExpenseItems.length === 0) {
        container.classList.add('d-none');
        if (grandTotalSpan) grandTotalSpan.textContent = '₹ 0.00';
        if (headerTotalSpan) headerTotalSpan.textContent = '₹ 0.00';
        if (totalCostHidden) totalCostHidden.value = '0.00';
        if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save Expenses';
        return;
    }

    container.classList.remove('d-none');
    if (countSpan) countSpan.textContent = stagedExpenseItems.length;

    let grandTotal = 0;
    stagedExpenseItems.forEach((item, idx) => {
        grandTotal += item.amount;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-center text-muted fw-bold small">${idx + 1}</td>
            <td>
                <span class="fw-bold text-dark">${escapeHtml(item.name)}</span>
                <input type="hidden" name="item_name[]" value="${escapeHtml(item.name)}">
                <input type="hidden" name="quantity[]" value="${item.qty}">
                <input type="hidden" name="unit_type[]" value="${escapeHtml(item.unit)}">
                <input type="hidden" name="unit_cost[]" value="${item.price.toFixed(2)}">
                <input type="hidden" name="notes[]" value="${escapeHtml(item.reason)}">
            </td>
            <td class="text-center font-monospace">${item.qty} ${escapeHtml(item.unit)}</td>
            <td class="text-end font-monospace">₹${item.price.toFixed(2)}</td>
            <td class="text-end fw-bold text-primary font-monospace">₹${item.amount.toFixed(2)}</td>
            <td>
                <span class="small text-muted">${item.reason ? escapeHtml(item.reason) : '<span class="text-secondary opacity-50">—</span>'}</span>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1.5" title="Remove Item" onclick="removeStagedExpenseItem(${idx})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    const formattedTotal = '₹ ' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (grandTotalSpan) grandTotalSpan.textContent = formattedTotal;
    if (headerTotalSpan) headerTotalSpan.textContent = formattedTotal;
    if (totalCostHidden) totalCostHidden.value = grandTotal.toFixed(2);
    if (submitBtn) {
        submitBtn.innerHTML = `<i class="bi bi-check-circle me-1"></i> Save ${stagedExpenseItems.length} Expense${stagedExpenseItems.length > 1 ? 's' : ''} (${formattedTotal})`;
    }
}

function cancelExpenseEntry() {
    document.getElementById('newItemName').value = '';
    document.getElementById('newQuantity').value = '1';
    document.getElementById('newUnitCost').value = '';
    const reasonInput = document.getElementById('newReason');
    if (reasonInput) reasonInput.value = '';
    calculateNewExpenseTotal();
    document.getElementById('newItemName').focus();
}

document.getElementById('recordExpenseForm')?.addEventListener('submit', function(event) {
    const itemName = document.getElementById('newItemName')?.value.trim();
    if (itemName) {
        // Auto-add active inputs before submitting so single or final item is captured
        addExpenseItemToList();
    }

    if (stagedExpenseItems.length === 0) {
        event.preventDefault();
        alert('Please enter and add at least one expense item.');
        document.getElementById('newItemName')?.focus();
    }
});

function calculateEditExpenseTotal() {
    const q = parseFloat(document.getElementById('editQuantity')?.value) || 0;
    const p = parseFloat(document.getElementById('editUnitCost')?.value) || 0;
    const total = q * p;
    const totalHidden = document.getElementById('editTotalCost');
    const totalDisplay = document.getElementById('editDisplayTotal');
    if (totalHidden) totalHidden.value = total.toFixed(2);
    if (totalDisplay) totalDisplay.textContent = total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function previewExpenseImage(input, previewContainerId) {
    const container = document.getElementById(previewContainerId);
    if (!container) return;
    const img = container.querySelector('img');
    const status = container.querySelector('.small');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (img) img.src = e.target.result;
                if (status) status.textContent = file.name;
                container.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        } else {
            if (img) img.src = '';
            if (status) status.textContent = 'Document selected: ' + file.name;
            container.classList.remove('d-none');
        }
    }
}

function openEditExpenseModal(data) {
    document.getElementById('editExpenseForm').action = '<?= url('production/expenses/update') ?>/' + data.id;
    
    if (document.getElementById('editProductionUnit')) {
        document.getElementById('editProductionUnit').value = data.production_unit || 'PRODUCTION_1';
    }
    if (document.getElementById('editExpenseDate')) {
        document.getElementById('editExpenseDate').value = data.expense_date || '';
    }
    if (document.getElementById('editCategory')) {
        document.getElementById('editCategory').value = data.category || 'SEWING_MATERIALS';
    }
    
    document.getElementById('editItemName').value = data.item_name || '';
    document.getElementById('editQuantity').value = parseFloat(data.quantity || 1).toString();
    if (document.getElementById('editUnitType')) {
        document.getElementById('editUnitType').value = (data.unit_type || 'PCS').toUpperCase();
    }
    document.getElementById('editUnitCost').value = parseFloat(data.unit_cost || 0).toFixed(2);
    calculateEditExpenseTotal();

    if (document.getElementById('editVendorName')) {
        document.getElementById('editVendorName').value = data.vendor_name || '';
    }
    if (document.getElementById('editPaymentMethod')) {
        document.getElementById('editPaymentMethod').value = data.payment_method || 'CASH';
    }
    if (document.getElementById('editPaymentStatus')) {
        document.getElementById('editPaymentStatus').value = data.payment_status || 'PAID';
    }
    if (document.getElementById('editInvoiceNumber')) {
        document.getElementById('editInvoiceNumber').value = data.invoice_number || '';
    }
    if (document.getElementById('editNotes')) {
        document.getElementById('editNotes').value = data.notes || '';
    }

    const preview = document.getElementById('editPhotoPreview');
    const img = preview ? preview.querySelector('img') : null;
    const status = preview ? preview.querySelector('.small') : null;
    const fileInput = document.getElementById('editPhoto');
    if (fileInput) fileInput.value = '';

    if (preview && data.photo_url) {
        let photoUrl = data.photo_url;
        if (!photoUrl.startsWith('http://') && !photoUrl.startsWith('https://')) {
            photoUrl = '<?= url('') ?>/' + photoUrl.replace(/^\/+/, '');
        }
        if (img) img.src = photoUrl;
        if (status) status.textContent = 'Current Image (choose new file to replace)';
        preview.classList.remove('d-none');
    } else if (preview) {
        preview.classList.add('d-none');
        if (img) img.src = '';
    }

    const modal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
    modal.show();
}

function openExpenseReceipt(id) {
    fetch('<?= url('production/expenses/receipt') ?>/' + id)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.expense) {
                const e = res.expense;
                document.getElementById('recExpNo').textContent = e.expense_number;
                document.getElementById('recExpDate').textContent = e.expense_date;
                document.getElementById('recExpStatus').textContent = e.payment_status;
                
                const unitLabels = {
                    'PRODUCTION_1': 'Production Unit 1',
                    'PRODUCTION_2': 'Production Unit 2',
                    'BOTH_UNITS': 'Both Units / Common',
                    'GENERAL': 'General Facility'
                };
                document.getElementById('recExpUnit').textContent = unitLabels[e.production_unit] || e.production_unit;
                document.getElementById('recExpCategory').textContent = (e.category || '').replace(/_/g, ' ');
                document.getElementById('recExpVendor').textContent = e.vendor_name || 'Direct';

                if (e.vendor_company) {
                    document.getElementById('recExpCompanyRow').classList.remove('d-none');
                    document.getElementById('recExpCompany').textContent = e.vendor_company;
                } else {
                    document.getElementById('recExpCompanyRow').classList.add('d-none');
                }

                if (e.program_number) {
                    document.getElementById('recExpProgramRow').classList.remove('d-none');
                    document.getElementById('recExpProgram').textContent = e.program_number + (e.program_name ? ' (' + e.program_name + ')' : '');
                } else {
                    document.getElementById('recExpProgramRow').classList.add('d-none');
                }

                document.getElementById('recExpItem').textContent = e.item_name;
                document.getElementById('recExpQty').textContent = parseFloat(e.quantity || 1) + ' ' + (e.unit_type || 'PCS');
                document.getElementById('recExpRate').textContent = '₹' + parseFloat(e.unit_cost || 0).toFixed(2);
                document.getElementById('recExpTotal').textContent = '₹' + parseFloat(e.total_cost || 0).toFixed(2);
                document.getElementById('recExpTotalFooter').textContent = '₹' + parseFloat(e.total_cost || 0).toFixed(2);
                document.getElementById('recExpPaid').textContent = '₹' + parseFloat(e.paid_amount || 0).toFixed(2);
                
                const due = Math.max(0, parseFloat(e.total_cost || 0) - parseFloat(e.paid_amount || 0));
                document.getElementById('recExpDue').textContent = '₹' + due.toFixed(2);
                document.getElementById('recExpNotes').textContent = e.notes ? ('Remarks: ' + e.notes) : '';

                const modal = new bootstrap.Modal(document.getElementById('expenseReceiptModal'));
                modal.show();
            } else {
                alert('Could not load expense voucher details.');
            }
        })
        .catch(err => {
            console.error('Error loading voucher:', err);
            alert('Error loading expense voucher.');
        });
}

function printExpenseVoucherArea() {
    const printContent = document.getElementById('printableExpenseVoucherArea').innerHTML;
    const printWin = window.open('', '_blank', 'width=750,height=600');
    printWin.document.write('<html><head><title>Print Voucher</title>');
    printWin.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    printWin.document.write('<style>body{padding:20px;font-family:sans-serif;} @media print{.no-print{display:none;}}</style>');
    printWin.document.write('</head><body>');
    printWin.document.write(printContent);
    printWin.document.write('</body></html>');
    printWin.document.close();
    printWin.focus();
    setTimeout(() => {
        printWin.print();
        printWin.close();
    }, 400);
}

function onExpenseTypeSelect(val) {
    if (!val) return;
    const clean = val.trim().toLowerCase();
    const match = expenseTypesMaster.find(it => it.name.toLowerCase() === clean);
    if (match) {
        if (match.default_rate && parseFloat(match.default_rate) > 0) {
            document.getElementById('newUnitCost').value = parseFloat(match.default_rate).toFixed(2);
        }
        if (match.unit_type && document.getElementById('newUnitType')) {
            document.getElementById('newUnitType').value = match.unit_type.toUpperCase();
        }
        calculateNewExpenseTotal();
    }
}

let lastSelectedCategory = 'SEWING_MATERIALS';
function onCategorySelectChange(select) {
    if (select.value === '__ADD_NEW_CATEGORY__') {
        select.value = lastSelectedCategory;
        openQuickAddCategoryModal();
    } else {
        lastSelectedCategory = select.value;
    }
}

function openQuickAddCategoryModal() {
    const input = document.getElementById('quickCategoryNameInput');
    const desc = document.getElementById('quickCategoryDescInput');
    const err = document.getElementById('quickCategoryError');
    if (input) input.value = '';
    if (desc) desc.value = '';
    if (err) { err.textContent = ''; err.classList.add('d-none'); }
    const modalEl = document.getElementById('modalQuickAddCategory');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        setTimeout(() => input?.focus(), 250);
    }
}

function submitQuickAddCategory() {
    const input = document.getElementById('quickCategoryNameInput');
    const desc = document.getElementById('quickCategoryDescInput');
    const err = document.getElementById('quickCategoryError');
    const btn = document.getElementById('btnSubmitQuickCategory');

    const name = input ? input.value.trim() : '';
    const description = desc ? desc.value.trim() : '';

    if (!name) {
        if (err) {
            err.textContent = 'Please enter a category name.';
            err.classList.remove('d-none');
        } else {
            alert('Please enter a category name.');
        }
        input?.focus();
        return;
    }

    if (err) err.classList.add('d-none');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    }

    fetch('<?= url('api/production/expenses/quick-add-category') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            name: name,
            description: description
        })
    })
    .then(r => r.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save Category';
        }
        if (data.success && data.category) {
            const catCode = data.category.code;
            const catName = data.category.name;

            // Add to newCategory dropdown
            const newCatSelect = document.getElementById('newCategory');
            if (newCatSelect) {
                let opt = Array.from(newCatSelect.options).find(o => o.value === catCode);
                if (!opt) {
                    opt = document.createElement('option');
                    opt.value = catCode;
                    opt.textContent = catName;
                    const lastOpt = newCatSelect.querySelector('option[value="__ADD_NEW_CATEGORY__"]');
                    if (lastOpt) {
                        newCatSelect.insertBefore(opt, lastOpt);
                    } else {
                        newCatSelect.appendChild(opt);
                    }
                }
                newCatSelect.value = catCode;
                lastSelectedCategory = catCode;
            }

            // Also add to editCategory if exists
            const editCatSelect = document.getElementById('editCategory');
            if (editCatSelect && !Array.from(editCatSelect.options).some(o => o.value === catCode)) {
                const opt = document.createElement('option');
                opt.value = catCode;
                opt.textContent = catName;
                editCatSelect.appendChild(opt);
            }

            // Also add to filter select if exists
            const filterCatSelect = document.querySelector('select[name="category"]');
            if (filterCatSelect && !Array.from(filterCatSelect.options).some(o => o.value === catCode)) {
                const opt = document.createElement('option');
                opt.value = catCode;
                opt.textContent = catName;
                filterCatSelect.appendChild(opt);
            }

            const modalEl = document.getElementById('modalQuickAddCategory');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        } else {
            if (err) {
                err.textContent = data.message || 'Could not save category.';
                err.classList.remove('d-none');
            } else {
                alert(data.message || 'Could not save category.');
            }
        }
    })
    .catch(error => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save Category';
        }
        console.error(error);
        if (err) {
            err.textContent = 'Server communication error. Please try again.';
            err.classList.remove('d-none');
        } else {
            alert('Server communication error.');
        }
    });
}

let lastSelectedUnit = 'PCS';
function onUnitSelectChange(select) {
    if (select.value === '__ADD_NEW_UNIT__') {
        select.value = lastSelectedUnit;
        openQuickAddUnitModal();
    } else {
        lastSelectedUnit = select.value;
    }
}

function openQuickAddUnitModal() {
    const code = document.getElementById('quickUnitCodeInput');
    const name = document.getElementById('quickUnitNameInput');
    const err = document.getElementById('quickUnitError');
    if (code) code.value = '';
    if (name) name.value = '';
    if (err) { err.textContent = ''; err.classList.add('d-none'); }
    const modalEl = document.getElementById('modalQuickAddUnit');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        setTimeout(() => code?.focus(), 250);
    }
}

function submitQuickAddUnit() {
    const codeInput = document.getElementById('quickUnitCodeInput');
    const nameInput = document.getElementById('quickUnitNameInput');
    const err = document.getElementById('quickUnitError');
    const btn = document.getElementById('btnSubmitQuickUnit');

    const abbreviation = codeInput ? codeInput.value.trim().toUpperCase() : '';
    const name = nameInput ? nameInput.value.trim() : '';

    if (!abbreviation) {
        if (err) {
            err.textContent = 'Please enter a unit code (e.g. BDL, DRUM).';
            err.classList.remove('d-none');
        } else {
            alert('Please enter a unit code.');
        }
        codeInput?.focus();
        return;
    }

    if (err) err.classList.add('d-none');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    }

    fetch('<?= url('api/production/expenses/quick-add-unit') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            abbreviation: abbreviation,
            name: name || abbreviation
        })
    })
    .then(r => r.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save Unit';
        }
        if (data.success && data.unit) {
            const uAbbr = data.unit.abbreviation;
            const uName = data.unit.name;
            const label = (uName && uName.toUpperCase() !== uAbbr) ? `${uAbbr} (${uName})` : uAbbr;

            // Add to newUnitType dropdown
            const newUnitSelect = document.getElementById('newUnitType');
            if (newUnitSelect) {
                let opt = Array.from(newUnitSelect.options).find(o => o.value === uAbbr);
                if (!opt) {
                    opt = document.createElement('option');
                    opt.value = uAbbr;
                    opt.textContent = label;
                    const lastOpt = newUnitSelect.querySelector('option[value="__ADD_NEW_UNIT__"]');
                    if (lastOpt) {
                        newUnitSelect.insertBefore(opt, lastOpt);
                    } else {
                        newUnitSelect.appendChild(opt);
                    }
                }
                newUnitSelect.value = uAbbr;
                lastSelectedUnit = uAbbr;
            }

            // Also add to editUnitType if exists
            const editUnitSelect = document.getElementById('editUnitType');
            if (editUnitSelect && !Array.from(editUnitSelect.options).some(o => o.value === uAbbr)) {
                const opt = document.createElement('option');
                opt.value = uAbbr;
                opt.textContent = label;
                editUnitSelect.appendChild(opt);
            }

            const modalEl = document.getElementById('modalQuickAddUnit');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        } else {
            if (err) {
                err.textContent = data.message || 'Could not save unit.';
                err.classList.remove('d-none');
            } else {
                alert(data.message || 'Could not save unit.');
            }
        }
    })
    .catch(error => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save Unit';
        }
        console.error(error);
        if (err) {
            err.textContent = 'Server communication error. Please try again.';
            err.classList.remove('d-none');
        } else {
            alert('Server communication error.');
        }
    });
}

// Preserve modal scrolling when stacking modals
document.addEventListener('DOMContentLoaded', function () {
    ['modalQuickAddCategory', 'modalQuickAddUnit'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('hidden.bs.modal', function () {
                if (document.getElementById('recordExpenseModal')?.classList.contains('show') ||
                    document.getElementById('editExpenseModal')?.classList.contains('show')) {
                    document.body.classList.add('modal-open');
                }
            });
        }
    });
});

// Table Pagination System with Live Search
let currentPage = 1;
let pageSize = 10;
const tableRows = Array.from(document.querySelectorAll('#expensesTable tbody tr.expense-row'));
let filteredRows = [...tableRows];

function initExpensesTablePagination() {
    const sizeSelect = document.getElementById('expensesPageSizeSelect');
    if (sizeSelect) {
        sizeSelect.addEventListener('change', function() {
            pageSize = parseInt(this.value, 10) || 10;
            currentPage = 1;
            renderExpensesTablePage();
        });
    }

    const searchInput = document.getElementById('expensesSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterExpensesLive(this.value);
        });
    }

    renderExpensesTablePage();
}

function filterExpensesLive(query) {
    const q = (query || '').toLowerCase().trim();
    if (!q) {
        filteredRows = [...tableRows];
    } else {
        filteredRows = tableRows.filter(row => {
            const text = (row.textContent || '').toLowerCase();
            return text.includes(q);
        });
    }
    currentPage = 1;
    renderExpensesTablePage();
}

function renderExpensesTablePage() {
    tableRows.forEach(row => {
        row.style.display = 'none';
    });

    const totalRows = filteredRows.length;
    const infoSpan = document.getElementById('expensesPageInfo');
    const nav = document.getElementById('expensesPaginationNav');
    const tbody = document.querySelector('#expensesTable tbody');

    let noMatchRow = document.getElementById('expensesNoMatchRow');

    if (totalRows === 0) {
        if (tableRows.length > 0) {
            if (!noMatchRow && tbody) {
                noMatchRow = document.createElement('tr');
                noMatchRow.id = 'expensesNoMatchRow';
                noMatchRow.innerHTML = `<td colspan="9" class="text-center py-5 text-muted">
                    <i class="bi bi-search fs-2 d-block mb-2 text-secondary opacity-75"></i>
                    <h6 class="fw-bold text-secondary mb-1">No matching expenses found</h6>
                    <small class="text-muted">No records match your search criteria. Try a different term.</small>
                </td>`;
                tbody.appendChild(noMatchRow);
            }
            if (noMatchRow) noMatchRow.style.display = '';
        }
        if (infoSpan) infoSpan.textContent = `Showing 0 expenses`;
        if (nav) nav.innerHTML = '';
        return;
    }

    if (noMatchRow) noMatchRow.style.display = 'none';

    const totalPages = Math.ceil(totalRows / pageSize);
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, totalRows);

    for (let i = startIndex; i < endIndex; i++) {
        if (filteredRows[i]) {
            filteredRows[i].style.display = '';
        }
    }

    if (infoSpan) {
        infoSpan.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalRows} expenses`;
    }

    if (nav) {
        nav.innerHTML = '';
        
        // Previous Button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<button class="page-link" type="button" aria-label="Previous">&laquo;</button>`;
        if (currentPage > 1) {
            prevLi.querySelector('button').addEventListener('click', () => {
                currentPage--;
                renderExpensesTablePage();
            });
        }
        nav.appendChild(prevLi);

        // Page Numbers
        for (let i = 1; i <= totalPages; i++) {
            if (totalPages > 7) {
                if (i !== 1 && i !== totalPages && Math.abs(i - currentPage) > 2) {
                    if (i === 2 || i === totalPages - 1) {
                        const dotsLi = document.createElement('li');
                        dotsLi.className = 'page-item disabled';
                        dotsLi.innerHTML = '<span class="page-link">...</span>';
                        nav.appendChild(dotsLi);
                    }
                    continue;
                }
            }

            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
            pageLi.innerHTML = `<button class="page-link" type="button">${i}</button>`;
            const pageNum = i;
            pageLi.querySelector('button').addEventListener('click', () => {
                currentPage = pageNum;
                renderExpensesTablePage();
            });
            nav.appendChild(pageLi);
        }

        // Next Button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<button class="page-link" type="button" aria-label="Next">&raquo;</button>`;
        if (currentPage < totalPages) {
            nextLi.querySelector('button').addEventListener('click', () => {
                currentPage++;
                renderExpensesTablePage();
            });
        }
        nav.appendChild(nextLi);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initExpensesTablePagination();
});
</script>
