<?php
$filePath = 'C:/xampp/htdocs/test/Inventory_Software/app/Controllers/WarehouseController.php';
$content = file_get_contents($filePath);

// Check if exportReports is already there
if (strpos($content, 'function exportReports') !== false) {
    echo "exportReports already present\n";
} else {
    $search = "            'breadcrumb'        => [\n                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],\n                ['label' => 'Dashboard'],\n            ],\n        ]));\n    }";
    $replace = "            'breadcrumb'        => [\n                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],\n                ['label' => 'Dashboard'],\n            ],\n        ]));\n    }\n\n    public function exportReports(): void {\n        if (!hasPermission('warehouse', 'view') && !hasPermission('stock', 'view') && !hasPermission('inward', 'view') && !hasPermission('allocation', 'view') && !hasPermission('purchases', 'view')) {\n            Session::flash('error', 'Access denied: You do not have permission to export warehouse reports.');\n            Response::redirect(url('warehouse/dashboard'));\n            return;\n        }\n\n        require_once BASE_PATH . '/app/Services/WarehouseReportService.php';\n\n        \$periodType = trim((string)(\$_REQUEST['period_type'] ?? 'MONTH'));\n        \$format = strtoupper(trim((string)(\$_REQUEST['format'] ?? 'CSV')));\n\n        if (\$periodType === 'MONTH') {\n            \$month = (int)(\$_REQUEST['selected_month'] ?? date('n'));\n            \$year = (int)(\$_REQUEST['selected_year'] ?? date('Y'));\n            \$month = max(1, min(12, \$month));\n            \$year = max(2020, min(2035, \$year));\n            \$startDate = sprintf('%04d-%02d-01', \$year, \$month);\n            \$endDate = date('Y-m-t', strtotime(\$startDate));\n            \$periodLabel = date('F Y', strtotime(\$startDate));\n        } else {\n            \$startDate = trim((string)(\$_REQUEST['date_from'] ?? date('Y-m-01')));\n            \$endDate = trim((string)(\$_REQUEST['date_to'] ?? date('Y-m-d')));\n            \$periodLabel = date('d M Y', strtotime(\$startDate)) . ' to ' . date('d M Y', strtotime(\$endDate));\n        }\n\n        \$allModules = ['inward', 'stock', 'catalog', 'suppliers', 'purchases', 'allocations', 'outward', 'damages', 'returns', 'expenses', 'complementary'];\n        \$selectedModules = !empty(\$_REQUEST['modules']) && is_array(\$_REQUEST['modules'])\n            ? array_values(array_intersect(\$_REQUEST['modules'], \$allModules))\n            : \$allModules;\n\n        if (empty(\$selectedModules)) {\n            \$selectedModules = \$allModules;\n        }\n\n        \$scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;\n        if (!empty(\$_REQUEST['warehouse_id']) && is_numeric(\$_REQUEST['warehouse_id'])) {\n            \$warehouseId = (int)\$_REQUEST['warehouse_id'];\n        } else {\n            \$warehouseId = \$scopedWarehouseId;\n        }\n\n        \$activeWarehouse = null;\n        if (\$warehouseId) {\n            \$activeWarehouse = Database::fetchOne(\"SELECT * FROM warehouses WHERE id = ?\", [\$warehouseId]);\n        }\n        if (!\$activeWarehouse) {\n            \$activeWarehouse = [\n                'name'    => 'Central Warehouses Network',\n                'code'    => 'WH-ALL',\n                'phone'   => '+91 98765 43210',\n                'address' => 'Central Hub & Outlets Distribution Center',\n            ];\n        }\n\n        \$data = \\App\\Services\\WarehouseReportService::getReportData(\$startDate, \$endDate, \$selectedModules, \$warehouseId);\n\n        if (\$format === 'PRINT') {\n            Response::viewOnly('warehouse.reports.print', [\n                'periodLabel'     => \$periodLabel,\n                'startDate'       => \$startDate,\n                'endDate'         => \$endDate,\n                'selectedModules' => \$selectedModules,\n                'data'            => \$data,\n                'warehouse'       => \$activeWarehouse,\n                'company'         => class_exists('SettingsService') ? \\SettingsService::getGroup('company') : [],\n            ]);\n            return;\n        }\n\n        \\App\\Services\\WarehouseReportService::streamCsv(\$periodLabel, \$startDate, \$endDate, \$data, \$activeWarehouse);\n    }";

    // Try both CRLF and LF
    if (strpos($content, "\r\n") !== false) {
        $search = str_replace("\n", "\r\n", $search);
        $replace = str_replace("\n", "\r\n", $replace);
    }
    
    if (strpos($content, $search) !== false) {
        $content = str_replace($search, $replace, $content);
        file_put_contents($filePath, $content);
        echo "Inserted exportReports successfully\n";
    } else {
        echo "Search string not found for exportReports\n";
    }
}
