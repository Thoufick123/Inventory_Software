<?php
$dashFile = 'C:/xampp/htdocs/test/Inventory_Software/views/production/dashboard.php';
$content = file_get_contents($dashFile);

// 1. Add Export button in header
$headerFind = '    <div class="d-flex align-items-center gap-2">
      
        <span class="text-muted small d-none d-sm-inline">';

$headerReplace = '    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3 d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#productionReportExportModal">
            <i class="bi bi-file-earmark-arrow-down"></i> Export Reports
        </button>
        <span class="text-muted small d-none d-sm-inline ms-1">';

$content = str_replace($headerFind, $headerReplace, $content);

// 2. Append modal to the end of dashboard.php
$modalHtml = <<<'HTML'

<!-- PRODUCTION REPORT EXPORT MODAL -->
<div class="modal fade" id="productionReportExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-file-earmark-arrow-down me-2"></i>Export Production Unit Reports
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET" action="<?= url('production/reports/export') ?>" target="_blank">
                <div class="modal-body p-4">
                    <!-- 1. Period Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-2">
                            <i class="bi bi-calendar-range text-primary me-1"></i> 1. Select Report Period
                        </label>
                        <div class="d-flex gap-4 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="period_type" id="periodTypeMonth" value="MONTH" checked onchange="togglePeriodInputs()">
                                <label class="form-check-label fw-semibold" for="periodTypeMonth">Month-Wise Filter</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="period_type" id="periodTypeCustom" value="CUSTOM" onchange="togglePeriodInputs()">
                                <label class="form-check-label fw-semibold" for="periodTypeCustom">Custom Date Range</label>
                            </div>
                        </div>

                        <!-- Month-wise Picker -->
                        <div class="row g-2" id="monthPickerRow">
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
                        <div class="row g-2 d-none" id="customPickerRow">
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
                                <i class="bi bi-check2-square text-primary me-1"></i> 2. Choose Production Data Modules to Export
                            </label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" onclick="toggleAllExportModules(true)">Select All</button>
                                <span class="text-muted">|</span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-secondary" onclick="toggleAllExportModules(false)">Clear</button>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 border">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="rolls" id="modRolls" checked>
                                        <label class="form-check-label small fw-semibold" for="modRolls">
                                            <i class="bi bi-stack text-primary me-1"></i> Fabric Rolls &amp; Inwards (Meters, Kg, Cost)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="programs" id="modPrograms" checked>
                                        <label class="form-check-label small fw-semibold" for="modPrograms">
                                            <i class="bi bi-kanban text-info me-1"></i> Production Programs (Planned Targets)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="cuttings" id="modCuttings" checked>
                                        <label class="form-check-label small fw-semibold" for="modCuttings">
                                            <i class="bi bi-scissors text-secondary me-1"></i> Cutting Batches &amp; Layers Output
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="stitching" id="modStitching" checked>
                                        <label class="form-check-label small fw-semibold" for="modStitching">
                                            <i class="bi bi-bezier text-warning me-1"></i> Stitching Logs &amp; Operator Counts
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="qc" id="modQc" checked>
                                        <label class="form-check-label small fw-semibold" for="modQc">
                                            <i class="bi bi-patch-check text-success me-1"></i> Quality Control (QC Passed, Rework, Rejection)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="billing" id="modBilling" checked>
                                        <label class="form-check-label small fw-semibold" for="modBilling">
                                            <i class="bi bi-receipt text-primary me-1"></i> Production Billing &amp; Direct Sales (HSN &amp; GST)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="returns" id="modReturns" checked>
                                        <label class="form-check-label small fw-semibold" for="modReturns">
                                            <i class="bi bi-arrow-return-left text-danger me-1"></i> Customer Returns &amp; Credit Notes
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="stock_requests" id="modStockReq" checked>
                                        <label class="form-check-label small fw-semibold" for="modStockReq">
                                            <i class="bi bi-box-arrow-in-down-right text-info me-1"></i> Warehouse Stock Requisitions
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="wastage" id="modWastage" checked>
                                        <label class="form-check-label small fw-semibold" for="modWastage">
                                            <i class="bi bi-recycle text-success me-1"></i> Cutting &amp; Stitching Wastage Selling
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-mod-cb" type="checkbox" name="modules[]" value="expenses" id="modExpenses" checked>
                                        <label class="form-check-label small fw-semibold" for="modExpenses">
                                            <i class="bi bi-wallet2 text-warning me-1"></i> Expenses &amp; Machinery Assets
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
                                <input class="form-check-input" type="radio" name="format" id="formatCsv" value="CSV" checked>
                                <label class="form-check-label fw-semibold" for="formatCsv">
                                    <i class="bi bi-file-earmark-excel text-success me-1"></i> CSV Spreadsheet (Excel Compatible)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatPrint" value="PRINT">
                                <label class="form-check-label fw-semibold" for="formatPrint">
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
function togglePeriodInputs() {
    const isMonth = document.getElementById('periodTypeMonth').checked;
    const mRow = document.getElementById('monthPickerRow');
    const cRow = document.getElementById('customPickerRow');
    if (isMonth) {
        mRow.classList.remove('d-none');
        cRow.classList.add('d-none');
    } else {
        mRow.classList.add('d-none');
        cRow.classList.remove('d-none');
    }
}

function toggleAllExportModules(check) {
    document.querySelectorAll('.export-mod-cb').forEach(cb => cb.checked = check);
}
</script>
HTML;

$content .= "\n" . $modalHtml;

$tmp = sys_get_temp_dir() . '/test_dash_syntax.php';
file_put_contents($tmp, $content);
exec("php -l " . escapeshellarg($tmp), $out, $ret);
if ($ret === 0) {
    file_put_contents($dashFile, $content);
    echo "SUCCESS: views/production/dashboard.php patched with Export button and modal!\n";
} else {
    echo "ERROR: " . implode("\n", $out) . "\n";
}
unlink($tmp);
