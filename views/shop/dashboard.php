<?php
// views/shop/dashboard.php — Retail Shop Dashboard with Dynamic Week/Month/Custom Date Range Filtering & Interactive Revenue Analytics
$currFilter = $current_filter ?? [];
$activePeriod = $currFilter['period'] ?? 'today';
$activeStartDate = $currFilter['start_date'] ?? date('Y-m-d');
$activeEndDate = $currFilter['end_date'] ?? date('Y-m-d');
$filterTitle = $currFilter['period_title'] ?? "Today's Sales";
$filterSubtitle = $currFilter['period_subtitle'] ?? "Invoices Today";
$filterRevenue = $currFilter['total_revenue'] ?? $today_revenue;
$filterInvoices = $currFilter['total_invoices'] ?? $today_invoices;
$filterCash = $currFilter['cash_total'] ?? $cash_total;
$filterUpi = $currFilter['upi_total'] ?? $upi_total;
$filterDigital = $currFilter['digital_total'] ?? ($upi_total + $card_total);
$filterAov = $currFilter['avg_order_value'] ?? $avg_order_value_today;
$filterLowStock = $currFilter['low_stock_count'] ?? $low_stock_count;
?>
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <div class="badge bg-primary-subtle text-primary mb-1">
                <i class="bi bi-shop me-1"></i><?= e($shop['name']) ?> (<?= e($shop['code']) ?>)
            </div>
            <h3 class="fw-bold mb-1 text-dark">Retail Shop Dashboard</h3>
          
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (hasPermission('shop', 'view') || hasPermission('sales', 'view') || hasPermission('shop_stock', 'view')): ?>
            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold shadow-sm d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#shopReportExportModal">
                <i class="bi bi-file-earmark-arrow-down"></i> Export Reports
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?php if (!empty($shop['logo_url'])): ?>
                <img src="<?= e($shop['logo_url']) ?>" alt="<?= e($shop['name'] ?? 'Shop') ?> logo" style="max-width:140px;max-height:64px;object-fit:contain;">
            <?php else: ?>
                <div class="rounded-circle bg-primary-subtle text-primary p-3"><i class="bi bi-shop fs-4"></i></div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h5 class="mb-0 fw-bold"><?= e($shop['name'] ?? 'Shop') ?></h5>
                    <?php if (!empty($shop['code'])): ?><span class="badge bg-light text-dark border"><?= e($shop['code']) ?></span><?php endif; ?>
                </div>
                <div class="small text-muted mt-1">
                    <?php if (!empty($shop['manager_name'])): ?>Manager: <?= e($shop['manager_name']) ?> · <?php endif; ?>
                    <?php if (!empty($shop['address'])): ?><?= e($shop['address']) ?><?php endif; ?>
                    <?php if (!empty($shop['phone'])): ?><?= !empty($shop['address']) ? ' · ' : '' ?><?= e($shop['phone']) ?><?php endif; ?>
                    <?php if (!empty($shop['gst_number'])): ?><?= (!empty($shop['address']) || !empty($shop['phone'])) ? ' · ' : '' ?>GSTIN: <?= e($shop['gst_number']) ?><?php endif; ?>
                </div>
            </div>
            <?php if (!empty($shop['id']) && hasPermission('dashboard', 'view') && (hasPermission('dashboard', 'edit') || hasPermission('shop', 'edit'))): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/dashboard?edit_type=shop&edit_id=' . (int)$shop['id']) ?>"><i class="bi bi-pencil me-1"></i>Edit details</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- DYNAMIC FILTER TOOLBAR (TODAY / WEEK / MONTH / CUSTOM RANGE) -->
    <!-- ============================================================ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
                <!-- Left: Preset Buttons -->
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small fw-semibold me-1 d-none d-sm-inline">
                        <i class="bi bi-funnel-fill me-1 text-primary"></i>Filter Cards:
                    </span>
                    <div class="btn-group p-1 bg-light rounded-pill border" role="group" id="mainFilterBtnGroup">
                        <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold <?= $activePeriod === 'today' ? 'active btn-primary' : 'text-secondary' ?>" data-period="today" onclick="applyMetricFilter('today')">
                            <i class="bi bi-sun me-1"></i>Today
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold <?= $activePeriod === 'week' ? 'active btn-primary' : 'text-secondary' ?>" data-period="week" onclick="applyMetricFilter('week')">
                            <i class="bi bi-calendar-week me-1"></i>This Week
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold <?= $activePeriod === 'month' ? 'active btn-primary' : 'text-secondary' ?>" data-period="month" onclick="applyMetricFilter('month')">
                            <i class="bi bi-calendar-month me-1"></i>This Month
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold <?= $activePeriod === 'custom' ? 'active btn-primary' : 'text-secondary' ?>" data-period="custom" id="customFilterToggleBtn" onclick="toggleCustomDateForm()">
                            <i class="bi bi-calendar-range me-1"></i>Custom Range <i class="bi bi-chevron-down ms-1 small"></i>
                        </button>
                    </div>
                </div>

                <!-- Right: Active Period Badge -->
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2 small shadow-none" id="activeFilterBadge">
                        <i class="bi bi-calendar3 me-1 text-primary"></i>
                        <span id="activeFilterBadgeText">Showing: <?= e($filterSubtitle) ?> (<?= formatDate($activeStartDate, 'd M') ?> – <?= formatDate($activeEndDate, 'd M Y') ?>)</span>
                    </span>
                    <div id="filterLoadingSpinner" class="spinner-border spinner-border-sm text-primary" style="display: none;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Expandable Custom Date Range Picker Container -->
            <div id="customDateRangeBox" class="mt-3 pt-3 border-top" style="<?= $activePeriod === 'custom' ? 'display: block;' : 'display: none;' ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-sm-auto">
                        <small class="text-muted fw-semibold">From Date:</small>
                        <input type="date" class="form-control form-control-sm rounded-3 mt-1" id="filterStartDate" value="<?= e($activeStartDate) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12 col-sm-auto">
                        <small class="text-muted fw-semibold">To Date:</small>
                        <input type="date" class="form-control form-control-sm rounded-3 mt-1" id="filterEndDate" value="<?= e($activeEndDate) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12 col-sm-auto mt-sm-4 pt-sm-1 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold" onclick="applyCustomDateFilter()">
                            <i class="bi bi-check2-circle me-1"></i> Apply Custom Range
                        </button>
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" onclick="applyMetricFilter('today')">
                            Reset to Today
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- METRIC SUMMARY CARDS (DYNAMICALLY UPDATED) -->
    <!-- ============================================================ -->
    <div class="row g-3 mb-4" id="kpiCardsContainer">
        <!-- Sales Revenue -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100 kpi-card position-relative overflow-hidden">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary-subtle text-primary p-3 me-3 flex-shrink-0">
                        <i class="bi bi-currency-rupee fs-4"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase text-truncate" id="cardSalesTitle"><?= e($filterTitle) ?></div>
                        <div class="fs-4 fw-bold text-dark text-truncate" id="cardSalesAmount"><?= formatCurrency((float)$filterRevenue) ?></div>
                        <div class="small text-muted text-truncate" id="cardSalesSubtitle"><?= $filterInvoices ?> Invoices</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash Collected -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100 kpi-card position-relative overflow-hidden">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success-subtle text-success p-3 me-3 flex-shrink-0">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase text-truncate" id="cardCashTitle">Cash Collected</div>
                        <div class="fs-4 fw-bold text-dark text-truncate" id="cardCashAmount"><?= formatCurrency((float)$filterCash) ?></div>
                        <div class="small text-muted text-truncate" id="cardCashSubtitle">In-store Cash</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- UPI / Digital -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-info h-100 kpi-card position-relative overflow-hidden">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-info-subtle text-info p-3 me-3 flex-shrink-0">
                        <i class="bi bi-qr-code fs-4"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase text-truncate" id="cardDigitalTitle">UPI / Digital</div>
                        <div class="fs-4 fw-bold text-dark text-truncate" id="cardDigitalAmount"><?= formatCurrency((float)$filterDigital) ?></div>
                        <div class="small text-muted text-truncate" id="cardDigitalSubtitle">Online / QR / Card</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Order Value (AOV) -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning h-100 kpi-card position-relative overflow-hidden">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-warning-subtle text-warning p-3 me-3 flex-shrink-0">
                        <i class="bi bi-graph-up-arrow fs-4"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase text-truncate" id="cardAovTitle">Avg Order Value</div>
                        <div class="fs-4 fw-bold text-dark text-truncate" id="cardAovAmount"><?= formatCurrency((float)$filterAov) ?></div>
                        <div class="small text-muted text-truncate" id="cardAovSubtitle">Average bill size</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger h-100 kpi-card position-relative overflow-hidden">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-danger-subtle text-danger p-3 me-3 flex-shrink-0">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase text-truncate" id="cardStockTitle">Low Stock Alert</div>
                        <div class="fs-4 fw-bold text-danger text-truncate" id="cardStockAmount"><?= $filterLowStock ?> Items</div>
                        <div class="small text-muted text-truncate" id="cardStockSubtitle">Below threshold</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- REVENUE ANALYTICS & INTERACTIVE CHART SECTION -->
    <!-- ============================================================ -->
    <div class="row g-4 mb-4">
        <!-- Main Line/Bar Revenue Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Revenue & Sales Analytics
                        </h5>
                        <p class="text-muted small mb-0" id="chartSubtitle">Daily revenue and transaction performance trend</p>
                    </div>

                    <!-- Period Switcher Pills -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="btn-group btn-group-sm p-1 bg-light rounded-pill border" role="group" id="chartPeriodButtonGroup">
                            <button type="button" class="btn btn-sm rounded-pill px-3 active fw-semibold" data-chart-period="7days" onclick="switchRevenuePeriod('7days', this)">7 Days</button>
                            <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold text-secondary" data-chart-period="30days" onclick="switchRevenuePeriod('30days', this)">30 Days</button>
                            <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold text-secondary" data-chart-period="6months" onclick="switchRevenuePeriod('6months', this)">6 Months</button>
                            <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold text-secondary" data-chart-period="hourly" onclick="switchRevenuePeriod('hourly', this)">Hourly Today</button>
                        </div>
                        
                        <!-- Toggle Metric Mode (Revenue vs Invoices) -->
                        <div class="btn-group btn-group-sm p-1 bg-light rounded-pill border">
                            <button type="button" class="btn btn-sm rounded-pill px-2 active fw-semibold" id="metricRevBtn" onclick="toggleMetricMode('revenue')" title="Show Revenue Amount">₹</button>
                            <button type="button" class="btn btn-sm rounded-pill px-2 fw-semibold text-secondary" id="metricCountBtn" onclick="toggleMetricMode('invoices')" title="Show Invoice Volume">Qty</button>
                        </div>
                    </div>
                </div>

                <div class="card-body pt-0 pb-3">
                    <!-- Quick KPI Pill Bar over the Chart -->
                    <div class="row g-2 mb-3 p-3 bg-light rounded-4">
                        <div class="col-4 text-center border-end">
                            <small class="text-muted d-block">Period Revenue</small>
                            <span class="fw-bold text-dark fs-6" id="statPeriodTotal">₹0.00</span>
                        </div>
                        <div class="col-4 text-center border-end">
                            <small class="text-muted d-block">Avg / Day</small>
                            <span class="fw-bold text-primary fs-6" id="statPeriodAvg">₹0.00</span>
                        </div>
                        <div class="col-4 text-center">
                            <small class="text-muted d-block">Peak Spike</small>
                            <span class="fw-bold text-success fs-6" id="statPeriodPeak">₹0.00</span>
                        </div>
                    </div>

                    <!-- Canvas Container -->
                    <div class="position-relative" style="min-height: 280px; height: 320px;">
                        <canvas id="shopRevenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Breakdown & Top Selling Styles -->
        <div class="col-lg-4 d-flex flex-column gap-4">
            <!-- Payment Mode Doughnut -->
            <div class="card border-0 shadow-sm rounded-4 flex-grow-1">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pie-chart-fill me-2 text-info"></i>Payment Modes
                    </h6>
                    <span class="badge bg-light text-dark border rounded-pill small" id="paymentDonutBadge">Current Filter</span>
                </div>
                <div class="card-body pt-1 pb-3">
                    <div class="position-relative d-flex justify-content-center align-items-center" style="height: 180px;">
                        <canvas id="paymentDonutChart"></canvas>
                    </div>

                    <!-- Custom Doughnut Legend Breakdown Container -->
                    <div class="mt-3 pt-2 border-top" id="paymentLegendContainer">
                        <!-- Rendered dynamically via JS or PHP fallback -->
                    </div>
                </div>
            </div>

            <!-- Top Selling Garments (Month) -->
            <div class="card border-0 shadow-sm rounded-4" id="topSellingCard">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-trophy-fill me-2 text-warning"></i>Top Selling Garments
                    </h6>
                    <span class="badge bg-success-subtle text-success rounded-pill small">Top 5</span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush rounded-bottom-4" id="topSellingListContainer">
                        <?php if (empty($top_selling_products)): ?>
                            <li class="list-group-item text-center py-3 text-muted small">No product sales in period</li>
                        <?php else: ?>
                            <?php foreach ($top_selling_products as $idx => $tsp): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2 border-0 border-bottom">
                                <div class="d-flex align-items-center me-2 overflow-hidden">
                                    <span class="badge <?= $idx === 0 ? 'bg-warning text-dark' : ($idx === 1 ? 'bg-secondary' : 'bg-light text-dark border') ?> rounded-circle p-2 me-2" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 11px;">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div class="overflow-hidden">
                                        <div class="fw-semibold text-dark text-truncate small"><?= e($tsp['product_name']) ?></div>
                                        <span class="badge bg-light text-dark border font-monospace mt-0.5" style="font-size: 10px;">
                                            <i class="bi bi-qr-code text-primary me-1"></i><?= e($tsp['sku']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <span class="badge bg-primary-subtle text-primary rounded-pill"><?= (int)$tsp['total_sold_qty'] ?> pcs</span>
                                    <small class="text-muted d-block"><?= formatCurrency((float)$tsp['total_sold_amount']) ?></small>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- RECENT INVOICES & QUICK ACTION SHORTCUTS -->
    <!-- ============================================================ -->
    <div class="row g-4">
        <!-- Recent Invoices Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-receipt me-2 text-primary"></i>Today's Recent Invoices
                    </h5>
                    <?php if (hasPermission('pos', 'view') || hasPermission('pos', 'create')): ?>
                    <a href="<?= url('shop/pos') ?>" class="btn btn-sm btn-link text-decoration-none">New Sale <i class="bi bi-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Invoice #</th>
                                <th>Customer</th>
                                <th>Payment Mode</th>
                                <th>Amount</th>
                                <th>Time</th>
                                <th class="text-end pe-4">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_sales)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-receipt-cutoff fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        No sales recorded yet today. Click "Open POS Terminal" to scan your first product.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark">
                                            <a href="<?= url('shop/pos/invoice/' . $sale['id']) ?>" target="_blank" class="text-decoration-none">
                                                <?= e($sale['invoice_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                                            <?php if ($sale['customer_phone']): ?>
                                                <small class="text-muted"><?= e($sale['customer_phone']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $pm = strtoupper($sale['payment_mode'] ?? 'CASH');
                                            $pmBadge = match($pm) {
                                                'CASH' => 'bg-success-subtle text-success border-success-subtle',
                                                'UPI'  => 'bg-info-subtle text-info border-info-subtle',
                                                'CARD' => 'bg-primary-subtle text-primary border-primary-subtle',
                                                default => 'bg-light text-dark border',
                                            };
                                            ?>
                                            <span class="badge border rounded-pill px-2 py-1 <?= $pmBadge ?>">
                                                <?= e($sale['payment_mode']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark"><?= formatCurrency((float)$sale['total_amount']) ?></td>
                                        <td><small class="text-muted"><?= date('h:i A', strtotime($sale['created_at'])) ?></small></td>
                                        <td class="text-end pe-4">
                                            <a href="<?= url('shop/pos/invoice/' . $sale['id']) ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3 shadow-none" title="Print Invoice">
                                                <i class="bi bi-printer me-1 text-primary"></i> Print
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
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT: FILTERING & CHART.JS INTERACTIVITY -->
<!-- ============================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Initial State Data
    let activeCardPeriod = '<?= e($activePeriod) ?>';
    let currentChartPeriod = '7days';
    let currentMetric = 'revenue'; // 'revenue' or 'invoices'
    let customChartData = null; // Used when a custom filter is active

    let data7Days = <?= json_encode($daily_revenue_7days ?? []) ?>;
    let data30Days = <?= json_encode($daily_revenue_30days ?? []) ?>;
    let data6Months = <?= json_encode($monthly_revenue_6months ?? []) ?>;
    let dataHourly = <?= json_encode($hourly_revenue_today ?? []) ?>;
    let dataPaymentBreakdown = <?= json_encode($payment_breakdown ?? []) ?>;

    let revenueChart = null;
    let paymentChart = null;

    // Currency & Number Formatter Helpers
    const formatINR = (val) => '₹' + Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const formatCount = (val) => Number(val || 0).toLocaleString('en-IN') + ' Bills';

    // 2. Render Payment Doughnut & Custom Legend
    function renderPaymentDonut(paymentData) {
        const donutCtx = document.getElementById('paymentDonutChart').getContext('2d');
        const legendContainer = document.getElementById('paymentLegendContainer');
        
        const modeLabels = [];
        const modeTotals = [];
        const modeColors = [];
        
        const colorMap = {
            'CASH': ['#10b981', 'Cash'],
            'UPI': ['#06b6d4', 'UPI / QR'],
            'CARD': ['#6366f1', 'Card'],
            'MIXED': ['#f59e0b', 'Split / Mixed'],
            'ONLINE': ['#ec4899', 'Online'],
        };

        const list = Array.isArray(paymentData) ? paymentData : [];
        let totalSum = 0;
        list.forEach(i => totalSum += Number(i.total || 0));

        if (list.length === 0) {
            modeLabels.push('No Sales');
            modeTotals.push(1);
            modeColors.push('#e2e8f0');
            legendContainer.innerHTML = '<div class="text-center text-muted small py-2">No payment transactions in selected period.</div>';
        } else {
            let legendHtml = '<div class="d-flex flex-column gap-2">';
            list.forEach(item => {
                const m = (item.payment_mode || 'CASH').toUpperCase();
                const cCfg = colorMap[m] || ['#64748b', m];
                const itemTotal = Number(item.total || 0);
                const pPct = totalSum > 0 ? ((itemTotal / totalSum) * 100).toFixed(1) : '0.0';

                modeLabels.push(cCfg[1]);
                modeTotals.push(itemTotal);
                modeColors.push(cCfg[0]);

                legendHtml += `
                    <div class="d-flex justify-content-between align-items-center small">
                        <div class="d-flex align-items-center">
                            <span class="rounded-circle d-inline-block me-2" style="width: 10px; height: 10px; background-color: ${cCfg[0]};"></span>
                            <span class="fw-semibold text-dark">${cCfg[1]}</span>
                            <span class="text-muted ms-1">(${item.count || 0} bills)</span>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-dark">${formatINR(itemTotal)}</span>
                            <span class="badge bg-light text-secondary border rounded-pill ms-1">${pPct}%</span>
                        </div>
                    </div>`;
            });
            legendHtml += '</div>';
            legendContainer.innerHTML = legendHtml;
        }

        if (paymentChart) {
            paymentChart.destroy();
        }

        paymentChart = new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: modeLabels,
                datasets: [{
                    data: modeTotals,
                    backgroundColor: modeColors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#f8fafc',
                        bodyColor: '#38bdf8',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                if (list.length === 0) return ' No transactions';
                                return ' ' + context.label + ': ' + formatINR(context.raw);
                            }
                        }
                    }
                }
            }
        });
    }

    // 3. Render Revenue Chart
    function renderRevenueChart() {
        let activeData = data7Days;
        let subtitleText = "Daily revenue and transaction performance trend for the last 7 days";

        if (customChartData) {
            activeData = customChartData;
            subtitleText = "Custom filtered period daily performance trend";
        } else if (currentChartPeriod === '30days') {
            activeData = data30Days;
            subtitleText = "Daily revenue performance across the last 30 days";
        } else if (currentChartPeriod === '6months') {
            activeData = data6Months;
            subtitleText = "Month-over-month revenue growth comparison (Last 6 Months)";
        } else if (currentChartPeriod === 'hourly') {
            activeData = dataHourly;
            subtitleText = "Today's hourly sales volume and peak retail velocity";
        }

        document.getElementById('chartSubtitle').textContent = subtitleText;

        const labels = activeData.map(item => item.label || item.short_label || item.month || item.hour);
        const revenueValues = activeData.map(item => Number(item.revenue || 0));
        const invoiceValues = activeData.map(item => Number(item.invoices || 0));
        const plotValues = currentMetric === 'revenue' ? revenueValues : invoiceValues;

        const totalRevenue = revenueValues.reduce((a, b) => a + b, 0);
        const totalInvoices = invoiceValues.reduce((a, b) => a + b, 0);
        const peakVal = Math.max(...plotValues, 0);
        const avgVal = plotValues.length > 0 ? (plotValues.reduce((a, b) => a + b, 0) / plotValues.length) : 0;

        document.getElementById('statPeriodTotal').textContent = currentMetric === 'revenue' ? formatINR(totalRevenue) : totalInvoices + ' Invoices';
        document.getElementById('statPeriodAvg').textContent = currentMetric === 'revenue' ? formatINR(avgVal) : avgVal.toFixed(1) + ' / slot';
        document.getElementById('statPeriodPeak').textContent = currentMetric === 'revenue' ? formatINR(peakVal) : peakVal + ' Invoices';

        const ctx = document.getElementById('shopRevenueChart').getContext('2d');

        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        if (currentMetric === 'revenue') {
            gradient.addColorStop(0, 'rgba(37, 99, 235, 0.35)');
            gradient.addColorStop(1, 'rgba(37, 99, 235, 0.00)');
        } else {
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0.00)');
        }

        const borderColor = currentMetric === 'revenue' ? '#2563eb' : '#10b981';
        const pointBgColor = currentMetric === 'revenue' ? '#1d4ed8' : '#059669';

        if (revenueChart) {
            revenueChart.destroy();
        }

        revenueChart = new Chart(ctx, {
            type: currentChartPeriod === '6months' ? 'bar' : 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: currentMetric === 'revenue' ? 'Revenue (₹)' : 'Invoice Count',
                    data: plotValues,
                    borderColor: borderColor,
                    backgroundColor: currentChartPeriod === '6months' ? (currentMetric === 'revenue' ? '#3b82f6' : '#10b981') : gradient,
                    borderWidth: 2.5,
                    fill: currentChartPeriod !== '6months',
                    tension: 0.35,
                    pointRadius: activeData.length > 20 ? 2 : 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: pointBgColor,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    borderRadius: currentChartPeriod === '6months' ? 6 : 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#f8fafc',
                        bodyColor: '#38bdf8',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 13, weight: '600', family: 'Inter' },
                        bodyFont: { size: 14, weight: '700', family: 'Inter' },
                        callbacks: {
                            label: function(context) {
                                const val = context.raw || 0;
                                const invCount = invoiceValues[context.dataIndex] || 0;
                                if (currentMetric === 'revenue') {
                                    return [' Revenue: ' + formatINR(val), ' Invoices: ' + invCount + ' bills'];
                                } else {
                                    const revAmt = revenueValues[context.dataIndex] || 0;
                                    return [' Invoices: ' + val + ' bills', ' Revenue: ' + formatINR(revAmt)];
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: 'Inter', size: 11 },
                            color: '#64748b',
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12,
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.7)' },
                        ticks: {
                            font: { family: 'Inter', size: 11 },
                            color: '#64748b',
                            callback: function(value) {
                                if (currentMetric === 'revenue') {
                                    if (value >= 100000) return '₹' + (value / 100000).toFixed(1) + 'L';
                                    if (value >= 1000) return '₹' + (value / 1000).toFixed(0) + 'k';
                                    return '₹' + value;
                                }
                                return value;
                            }
                        }
                    }
                }
            }
        });
    }

    // 4. Update Top Selling Garments List
    function updateTopSellingList(items) {
        const container = document.getElementById('topSellingListContainer');
        if (!items || items.length === 0) {
            container.innerHTML = '<li class="list-group-item text-center py-3 text-muted small">No product sales in this period.</li>';
            return;
        }

        let html = '';
        items.forEach((tsp, idx) => {
            const badgeClass = idx === 0 ? 'bg-warning text-dark' : (idx === 1 ? 'bg-secondary' : 'bg-light text-dark border');
            html += `
                <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center me-2 overflow-hidden">
                        <span class="badge ${badgeClass} rounded-circle p-2 me-2" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 11px;">
                            ${idx + 1}
                        </span>
                        <div class="overflow-hidden">
                            <div class="fw-semibold text-dark text-truncate small">${tsp.product_name || 'Product'}</div>
                            <small class="text-muted">${tsp.sku || ''}</small>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <span class="badge bg-primary-subtle text-primary rounded-pill">${tsp.total_sold_qty || 0} pcs</span>
                        <small class="text-muted d-block">${formatINR(tsp.total_sold_amount || 0)}</small>
                    </div>
                </li>`;
        });
        container.innerHTML = html;
    }

    // 5. Apply Metric Filter (Today / Week / Month / Custom) via AJAX
    window.applyMetricFilter = function(period, customStart = null, customEnd = null) {
        activeCardPeriod = period;
        
        // Update Button Group States
        const btnGroup = document.getElementById('mainFilterBtnGroup');
        btnGroup.querySelectorAll('button').forEach(btn => {
            btn.classList.remove('active', 'btn-primary');
            btn.classList.add('text-secondary');
        });

        const activeBtn = btnGroup.querySelector(`button[data-period="${period}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active', 'btn-primary');
            activeBtn.classList.remove('text-secondary');
        }

        const dateBox = document.getElementById('customDateRangeBox');
        if (period === 'custom') {
            dateBox.style.display = 'block';
        } else {
            dateBox.style.display = 'none';
        }

        // Show spinner
        const spinner = document.getElementById('filterLoadingSpinner');
        spinner.style.display = 'inline-block';

        // Add subtle opacity to cards while fetching
        const kpiContainer = document.getElementById('kpiCardsContainer');
        kpiContainer.style.opacity = '0.6';

        // Build URL
        let fetchUrl = '<?= url("api/shop/dashboard/metrics") ?>?period=' + encodeURIComponent(period);
        if (period === 'custom') {
            const startVal = customStart || document.getElementById('filterStartDate').value;
            const endVal = customEnd || document.getElementById('filterEndDate').value;
            if (startVal) fetchUrl += '&start_date=' + encodeURIComponent(startVal);
            if (endVal) fetchUrl += '&end_date=' + encodeURIComponent(endVal);
        }

        fetch(fetchUrl)
            .then(res => res.json())
            .then(res => {
                spinner.style.display = 'none';
                kpiContainer.style.opacity = '1';

                if (res.success && res.data) {
                    const d = res.data;

                    // 1. Update Card 1: Sales
                    document.getElementById('cardSalesTitle').textContent = d.period_title || 'Period Sales';
                    document.getElementById('cardSalesAmount').textContent = formatINR(d.total_revenue);
                    document.getElementById('cardSalesSubtitle').textContent = (d.total_invoices || 0) + ' Invoices (' + (d.period_subtitle || '') + ')';

                    // 2. Update Card 2: Cash
                    document.getElementById('cardCashAmount').textContent = formatINR(d.cash_total);

                    // 3. Update Card 3: UPI / Digital
                    document.getElementById('cardDigitalAmount').textContent = formatINR(d.digital_total);

                    // 4. Update Card 4: Avg Order Value
                    document.getElementById('cardAovAmount').textContent = formatINR(d.avg_order_value);
                    document.getElementById('cardAovSubtitle').textContent = 'Avg per invoice';

                    // 5. Update Card 5: Low Stock Alert
                    document.getElementById('cardStockAmount').textContent = (d.low_stock_count || 0) + ' Items';

                    // Update Active Badge
                    document.getElementById('activeFilterBadgeText').textContent = 
                        `Showing: ${d.period_title} (${d.start_date} to ${d.end_date})`;

                    // Update Charts & Side Widgets
                    if (d.daily_chart_data && d.daily_chart_data.length > 0) {
                        customChartData = d.daily_chart_data;
                        renderRevenueChart();
                    }
                    if (d.payment_breakdown) {
                        renderPaymentDonut(d.payment_breakdown);
                    }
                    if (d.top_selling) {
                        updateTopSellingList(d.top_selling);
                    }
                }
            })
            .catch(err => {
                spinner.style.display = 'none';
                kpiContainer.style.opacity = '1';
                console.error('Filter request error:', err);
            });
    };

    window.toggleCustomDateForm = function() {
        const dateBox = document.getElementById('customDateRangeBox');
        if (dateBox.style.display === 'none' || dateBox.style.display === '') {
            dateBox.style.display = 'block';
        } else {
            dateBox.style.display = 'none';
        }
    };

    window.applyCustomDateFilter = function() {
        const startVal = document.getElementById('filterStartDate').value;
        const endVal = document.getElementById('filterEndDate').value;

        if (!startVal || !endVal) {
            alert('Please select both Start Date and End Date.');
            return;
        }
        if (startVal > endVal) {
            alert('Start Date cannot be greater than End Date.');
            return;
        }

        applyMetricFilter('custom', startVal, endVal);
    };

    // Chart-specific Period Switcher
    window.switchRevenuePeriod = function(period, btnEl) {
        currentChartPeriod = period;
        customChartData = null; // Reset custom data to use standard tabs

        const group = document.getElementById('chartPeriodButtonGroup');
        group.querySelectorAll('button').forEach(b => {
            b.classList.remove('active', 'btn-primary');
            b.classList.add('text-secondary');
        });
        btnEl.classList.add('active');
        btnEl.classList.remove('text-secondary');

        renderRevenueChart();
    };

    window.toggleMetricMode = function(mode) {
        currentMetric = mode;
        const revBtn = document.getElementById('metricRevBtn');
        const countBtn = document.getElementById('metricCountBtn');

        if (mode === 'revenue') {
            revBtn.classList.add('active');
            revBtn.classList.remove('text-secondary');
            countBtn.classList.remove('active');
            countBtn.classList.add('text-secondary');
        } else {
            countBtn.classList.add('active');
            countBtn.classList.remove('text-secondary');
            revBtn.classList.remove('active');
            revBtn.classList.add('text-secondary');
        }

        renderRevenueChart();
    };

    // Initial Renders
    renderRevenueChart();
    renderPaymentDonut(dataPaymentBreakdown);
});
</script>


<!-- SHOP REPORT EXPORT MODAL -->
<div class="modal fade" id="shopReportExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-3 px-4">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-file-earmark-arrow-down me-2"></i>Export Shop Operations &amp; Sales Reports
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET" action="<?= url('shop/reports/export') ?>" target="_blank">
                <div class="modal-body p-4">
                    <!-- 1. Period Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-2">
                            <i class="bi bi-calendar-range text-success me-1"></i> 1. Select Report Period
                        </label>
                        <div class="d-flex gap-4 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="period_type" id="shopPeriodTypeMonth" value="MONTH" checked onchange="toggleShopPeriodInputs()">
                                <label class="form-check-label fw-semibold" for="shopPeriodTypeMonth">Month-Wise Filter</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="period_type" id="shopPeriodTypeCustom" value="CUSTOM" onchange="toggleShopPeriodInputs()">
                                <label class="form-check-label fw-semibold" for="shopPeriodTypeCustom">Custom Date Range</label>
                            </div>
                        </div>

                        <!-- Month-wise Picker -->
                        <div class="row g-2" id="shopMonthPickerRow">
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
                        <div class="row g-2 d-none" id="shopCustomPickerRow">
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
                                <i class="bi bi-check2-square text-success me-1"></i> 2. Choose Shop Data Modules to Export
                            </label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-success fw-semibold" onclick="toggleAllShopExportModules(true)">Select All</button>
                                <span class="text-muted">|</span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-secondary" onclick="toggleAllShopExportModules(false)">Clear</button>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 border">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="sales" id="shopModSales" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModSales">
                                            <i class="bi bi-receipt text-primary me-1"></i> POS Sales Invoices &amp; Billing
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="sale_items" id="shopModSaleItems" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModSaleItems">
                                            <i class="bi bi-cart-check text-success me-1"></i> Selled Products (Itemized Lines)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="stock" id="shopModStock" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModStock">
                                            <i class="bi bi-boxes text-info me-1"></i> Shop Stock Balances &amp; Valuation
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="inward" id="shopModInward" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModInward">
                                            <i class="bi bi-box-arrow-in-down text-primary me-1"></i> Inwards from Central Warehouse
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="returns" id="shopModReturns" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModReturns">
                                            <i class="bi bi-arrow-counterclockwise text-warning me-1"></i> Customer Counter Returns &amp; Refunds
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="return_to_warehouse" id="shopModRetWh" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModRetWh">
                                            <i class="bi bi-arrow-return-left text-danger me-1"></i> Returns to Central Warehouse
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="customers" id="shopModCustomers" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModCustomers">
                                            <i class="bi bi-people text-secondary me-1"></i> Registered Customers Directory
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="cash_register" id="shopModCashReg" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModCashReg">
                                            <i class="bi bi-calculator text-dark me-1"></i> Cash Drawer Shifts &amp; Registers
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="cash_expenses" id="shopModExpenses" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModExpenses">
                                            <i class="bi bi-wallet2 text-warning me-1"></i> Store Petty Cash Expenses
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input shop-export-mod-cb" type="checkbox" name="modules[]" value="damages" id="shopModDamages" checked>
                                        <label class="form-check-label small fw-semibold" for="shopModDamages">
                                            <i class="bi bi-shield-x text-danger me-1"></i> In-Store Damaged Goods &amp; Losses
                                        </label>
                                    </div>
                                </div>
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
                                <input class="form-check-input" type="radio" name="format" id="shopFormatCsv" value="CSV" checked>
                                <label class="form-check-label fw-semibold" for="shopFormatCsv">
                                    <i class="bi bi-file-earmark-excel text-success me-1"></i> CSV Spreadsheet (Excel Compatible)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="shopFormatPrint" value="PRINT">
                                <label class="form-check-label fw-semibold" for="shopFormatPrint">
                                    <i class="bi bi-printer text-primary me-1"></i> Printable Master Summary Report
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success px-4 fw-semibold shadow-sm">
                        <i class="bi bi-download me-1"></i> Generate &amp; Download Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleShopPeriodInputs() {
    const isMonth = document.getElementById('shopPeriodTypeMonth').checked;
    const mRow = document.getElementById('shopMonthPickerRow');
    const cRow = document.getElementById('shopCustomPickerRow');
    if (isMonth) {
        mRow.classList.remove('d-none');
        cRow.classList.add('d-none');
    } else {
        mRow.classList.add('d-none');
        cRow.classList.remove('d-none');
    }
}

function toggleAllShopExportModules(check) {
    document.querySelectorAll('.shop-export-mod-cb').forEach(cb => cb.checked = check);
}
</script>