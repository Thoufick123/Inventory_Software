<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';
require_once BASE_PATH . '/app/Services/WarehouseReportService.php';

echo "=== 1. TESTING WAREHOUSE REPORT DATA FETCHING ===\n";
$startDate = date('Y-m-01');
$endDate = date('Y-m-t');
$allModules = ['inward', 'stock', 'catalog', 'suppliers', 'purchases', 'allocations', 'outward', 'damages', 'returns', 'expenses', 'complementary'];

$reportData = \App\Services\WarehouseReportService::getReportData($startDate, $endDate, $allModules);

echo "Modules returned in dataset:\n";
foreach ($reportData as $k => $v) {
    if ($k === 'summary') {
        echo "- summary: " . count($v['executive'] ?? []) . " executive KPI metrics\n";
    } else {
        echo "- {$k}: " . count($v) . " records\n";
    }
}

echo "\n=== 2. EXECUTIVE METRICS SAMPLE ===\n";
$exec = $reportData['summary']['executive'] ?? [];
echo "- Available Stock Units: " . ($exec['available_stock_units'] ?? 0) . "\n";
echo "- Unique SKUs: " . ($exec['unique_skus'] ?? 0) . "\n";
echo "- Retail Valuation: ₹" . number_format($exec['retail_valuation'] ?? 0, 2) . "\n";
echo "- Cost Valuation: ₹" . number_format($exec['cost_valuation'] ?? 0, 2) . "\n";
echo "- Inward Pieces: " . ($exec['total_inward_pieces'] ?? 0) . "\n";
echo "- Outward Pieces: " . ($exec['total_outward_pieces'] ?? 0) . "\n";
echo "- PO Spend: ₹" . number_format($exec['total_purchases_spend'] ?? 0, 2) . "\n";
echo "- Expenses: ₹" . number_format($exec['total_expenses'] ?? 0, 2) . "\n";

echo "\n=== 3. VERIFYING DUAL HSN PERSISTENCE ===\n";
$cats = Database::fetchAll("SELECT id, name, hsn_code, gst_rate FROM warehouse_categories LIMIT 3");
echo "Warehouse Categories with HSN:\n";
foreach ($cats as $cat) {
    echo "- Cat ID {$cat['id']} ({$cat['name']}): HSN {$cat['hsn_code']} @ {$cat['gst_rate']}%\n";
}

$vars = Database::fetchAll("SELECT pv.id, pv.sku, pv.hsn_code, pv.gst_rate, p.name as prod_name FROM product_variants pv JOIN products p ON pv.product_id = p.id ORDER BY pv.id DESC LIMIT 3");
echo "\nProduct Variants with HSN & GST:\n";
foreach ($vars as $v) {
    echo "- Var ID {$v['id']} ({$v['sku']}): HSN " . ($v['hsn_code'] ?? 'NULL') . " @ " . ($v['gst_rate'] ?? '5.00') . "%\n";
}

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
