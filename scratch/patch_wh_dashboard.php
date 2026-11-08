<?php
$filePath = 'C:/xampp/htdocs/test/Inventory_Software/views/warehouse/dashboard.php';
$content = file_get_contents($filePath);
$isCrlf = (strpos($content, "\r\n") !== false);

$headerSearch = <<<'SEARCH'
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h2 class="mb-0"><i class="bi bi-buildings me-2 text-primary"></i>Warehouse Operations Dashboard</h2>
            <?php 
            $currUser = currentUser();
            $assignedWH = $currUser['warehouse_name'] ?? null;
            if ($assignedWH): ?>
                <!-- <span class="badge bg-warning bg-opacity-15 text-warning-emphasis border border-warning border-opacity-25 px-2.5 py-1.5 rounded-pill fs-7 d-inline-flex align-items-center">
                    <i class="bi bi-building-fill-check me-1"></i> Warehouse: <?= e($assignedWH) ?>
                </span> -->
            <?php elseif (isSuperAdmin()): ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2.5 py-1.5 rounded-pill fs-7 d-inline-flex align-items-center">
                    <i class="bi bi-globe-americas me-1"></i> All Warehouses (Admin Scope)
                </span>
            <?php endif; ?>
        </div>
        
            </div>
</div>
SEARCH;

$headerReplace = <<<'REPLACE'
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
            <?php elseif (isSuperAdmin()): ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2.5 py-1.5 rounded-pill fs-7 d-inline-flex align-items-center">
                    <i class="bi bi-globe-americas me-1"></i> All Warehouses (Admin Scope)
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
REPLACE;

if ($isCrlf) {
    $headerSearch = str_replace("\n", "\r\n", $headerSearch);
    $headerReplace = str_replace("\n", "\r\n", $headerReplace);
}

if (strpos($content, $headerSearch) !== false) {
    $content = str_replace($headerSearch, $headerReplace, $content);
    echo "Patched header successfully\n";
} else {
    echo "Header search string not matched\n";
}

if (strpos($content, 'id="warehouseReportExportModal"') === false) {
    $modalHtml = <<<'MODAL'


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
MODAL;

    if ($isCrlf) {
        $modalHtml = str_replace("\n", "\r\n", $modalHtml);
    }
    $content .= $modalHtml;
    echo "Appended modal successfully\n";
}

file_put_contents($filePath, $content);
