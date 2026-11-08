<?php
$file = 'C:/xampp/htdocs/test/Inventory_Software/app/Services/ProductionReportService.php';
$content = file_get_contents($file);

$find = <<<'PHP'
        // 9. Wastage Selling
        if (in_array('wastage', $selectedModules, true)) {
            $cuttingWastage = Database::fetchAll(
                "SELECT cws.invoice_number, 'Cutting Scrap' AS wastage_type, cws.buyer_name,
                        cws.weight_kg, cws.rate_per_kg, cws.total_amount, cws.created_at
                 FROM cutting_wastage_sales cws
                 WHERE DATE(cws.created_at) BETWEEN ? AND ?
                 ORDER BY cws.id DESC",
                [$startDate, $endDate]
            );

            $stitchingWastage = Database::fetchAll(
                "SELECT sws.invoice_number, 'Stitching Scrap' AS wastage_type, sws.buyer_name,
                        sws.weight_kg, sws.rate_per_kg, sws.total_amount, sws.created_at
                 FROM stitching_wastage_sales sws
                 WHERE DATE(sws.created_at) BETWEEN ? AND ?
                 ORDER BY sws.id DESC",
                [$startDate, $endDate]
            );

            $data['wastage'] = array_merge($cuttingWastage, $stitchingWastage);
        }
PHP;

$replace = <<<'PHP'
        // 9. Wastage Selling
        if (in_array('wastage', $selectedModules, true)) {
            $cuttingWastage = Database::fetchAll(
                "SELECT ws.sale_number AS invoice_number, 'Cutting Scrap' AS wastage_type, ws.buyer_name,
                        ws.weight AS weight_kg, ws.rate_per_kg, ws.total_amount, ws.sale_date AS created_at
                 FROM wastage_sales ws
                 WHERE DATE(ws.sale_date) BETWEEN ? AND ?
                 ORDER BY ws.id DESC",
                [$startDate, $endDate]
            );

            $stitchingWastage = Database::fetchAll(
                "SELECT sws.sale_number AS invoice_number, 'Stitching Scrap' AS wastage_type, sws.buyer_name,
                        sws.quantity AS weight_kg, sws.rate_per_unit AS rate_per_kg, sws.total_amount, sws.sale_date AS created_at
                 FROM stitching_wastage_sales sws
                 WHERE DATE(sws.sale_date) BETWEEN ? AND ?
                 ORDER BY sws.id DESC",
                [$startDate, $endDate]
            );

            $data['wastage'] = array_merge($cuttingWastage, $stitchingWastage);
        }
PHP;

$content = str_replace($find, $replace, $content);
file_put_contents($file, $content);
echo "SUCCESS: ProductionReportService updated with wastage_sales table!\n";
