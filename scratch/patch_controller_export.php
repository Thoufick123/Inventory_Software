<?php
$file = 'C:/xampp/htdocs/test/Inventory_Software/app/Controllers/ProductionController.php';
$content = file_get_contents($file);

$exportMethod = <<<'PHP'


    /**
     * Export Comprehensive Production Reports (CSV / Print).
     */
    public function exportReports(): void {
        require_once BASE_PATH . '/app/Services/ProductionReportService.php';

        $periodType = trim((string)($_REQUEST['period_type'] ?? 'MONTH'));
        $format = strtoupper(trim((string)($_REQUEST['format'] ?? 'CSV')));

        if ($periodType === 'MONTH') {
            $month = (int)($_REQUEST['selected_month'] ?? date('n'));
            $year = (int)($_REQUEST['selected_year'] ?? date('Y'));
            $month = max(1, min(12, $month));
            $year = max(2020, min(2035, $year));
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate));
            $periodLabel = date('F Y', strtotime($startDate));
        } else {
            $startDate = trim((string)($_REQUEST['date_from'] ?? date('Y-m-01')));
            $endDate = trim((string)($_REQUEST['date_to'] ?? date('Y-m-d')));
            $periodLabel = date('d M Y', strtotime($startDate)) . ' to ' . date('d M Y', strtotime($endDate));
        }

        $allModules = ['rolls', 'programs', 'cuttings', 'stitching', 'qc', 'billing', 'returns', 'stock_requests', 'wastage', 'expenses'];
        $selectedModules = !empty($_REQUEST['modules']) && is_array($_REQUEST['modules'])
            ? array_values(array_intersect($_REQUEST['modules'], $allModules))
            : $allModules;

        if (empty($selectedModules)) {
            $selectedModules = $allModules;
        }

        $data = \App\Services\ProductionReportService::getReportData($startDate, $endDate, $selectedModules);

        if ($format === 'PRINT') {
            Response::viewOnly('production.reports.print', [
                'periodLabel'     => $periodLabel,
                'startDate'       => $startDate,
                'endDate'         => $endDate,
                'selectedModules' => $selectedModules,
                'data'            => $data,
                'sellerCompany'   => SettingsService::getGroup('company'),
            ]);
            return;
        }

        \App\Services\ProductionReportService::streamCsv($periodLabel, $startDate, $endDate, $data);
    }
PHP;

$pattern = '/(public function dashboard\(\): void \{.*?\n    \})/s';
if (preg_match($pattern, $content, $matches)) {
    $content = preg_replace($pattern, '$1' . "\n" . $exportMethod, $content, 1);
    $tmp = sys_get_temp_dir() . '/test_ctrl_syntax.php';
    file_put_contents($tmp, $content);
    exec("php -l " . escapeshellarg($tmp), $out, $ret);
    if ($ret === 0) {
        file_put_contents($file, $content);
        echo "SUCCESS: ProductionController patched with exportReports!\n";
    } else {
        echo "ERROR: " . implode("\n", $out) . "\n";
    }
    unlink($tmp);
} else {
    echo "ERROR: Regex match failed\n";
}
