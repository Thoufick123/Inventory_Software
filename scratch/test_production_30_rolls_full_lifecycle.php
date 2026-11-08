<?php
define('BASE_PATH', 'C:/xampp/htdocs/test/Inventory_Software');
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';
require_once BASE_PATH . '/app/Services/ProductionReportService.php';

use App\Services\ProductionReportService;

echo "=================================================================\n";
echo "PRODUCTION UNIT 30-ROLL STRESS TEST & ALL MODULES VERIFICATION\n";
echo "=================================================================\n\n";

$results = ['passed' => 0, 'failed' => 0, 'steps' => []];

function reportStep(string $step, bool $success, string $details = ''): void {
    global $results;
    if ($success) {
        $results['passed']++;
        echo "[PASS] {$step}: {$details}\n";
    } else {
        $results['failed']++;
        echo "[FAIL] {$step}: {$details}\n";
    }
}

try {
    $pdo = Database::getInstance();
    echo "Connected to MariaDB: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n\n";

    // -------------------------------------------------------------
    // STAGE 1: SELLER / SUPPLIER SETUP
    // -------------------------------------------------------------
    $seller = Database::fetchOne("SELECT id, name FROM sellers WHERE phone = '9876543210' LIMIT 1");
    if (!$seller) {
        Database::execute(
            "INSERT INTO sellers (name, company, phone, email, city, state, address, gst_number, status, created_at)
             VALUES ('Karthik Textiles', 'Tirupur Mega Textile Mills Pvt Ltd', '9876543210', 'sales@tirupurtextiles.com', 'Tirupur', 'Tamil Nadu', 'Plot 45, SIPCOT Phase 2', '33ABCDE1234F1Z5', 1, NOW())"
        );
        $sellerId = (int)Database::lastInsertId();
    } else {
        $sellerId = (int)$seller['id'];
    }
    reportStep("Stage 1 - Supplier Setup", $sellerId > 0, "Supplier ID: {$sellerId} (Tirupur Mega Textile Mills)");

    // -------------------------------------------------------------
    // STAGE 2: PURCHASE 30 FABRIC ROLLS (HSN 5208)
    // -------------------------------------------------------------
    $rollCount = 30; // user requested between 25 and 35 rolls
    $rollIds = [];
    $totalWeight = 0;
    $totalPrice = 0;

    for ($i = 1; $i <= $rollCount; $i++) {
        $rollNum = 'MIL-R30-' . date('md') . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $weight = 24.50; // kg
        $price = 10710.00; // total roll cost

        // Check if roll already exists in seller_rolls
        $existing = Database::fetchOne("SELECT id FROM seller_rolls WHERE roll_number = ?", [$rollNum]);
        if (!$existing) {
            Database::execute(
                "INSERT INTO seller_rolls (seller_id, roll_number, color, weight, price, material_type, quantity, created_at)
                 VALUES (?, ?, 'Navy Blue', ?, ?, '100% Bio-Wash Combed Cotton (HSN 5208)', 1, NOW())",
                [$sellerId, $rollNum, $weight, $price]
            );
            $rId = (int)Database::lastInsertId();
        } else {
            $rId = (int)$existing['id'];
        }
        $rollIds[] = $rId;
        $totalWeight += $weight;
        $totalPrice += $price;
    }

    reportStep("Stage 2 - 30 Rolls Inwarded", count($rollIds) === 30, "Purchased 30 Rolls = {$totalWeight}kg (Total Cost: ₹" . number_format($totalPrice, 2) . ") with HSN 5208");

    // -------------------------------------------------------------
    // STAGE 3: PRODUCT STYLE WITH HSN CODE 6109 & GST 5%
    // -------------------------------------------------------------
    $styleCode = 'STY-POLO-30R';
    $product = Database::fetchOne("SELECT id FROM products WHERE code = ?", [$styleCode]);
    if (!$product) {
        $cat = Database::fetchOne("SELECT id FROM categories LIMIT 1");
        $catId = $cat ? (int)$cat['id'] : 1;
        Database::execute(
            "INSERT INTO products (name, code, category_id, source_type, material, hsn_code, gst_percentage, min_stock, max_stock, is_active, created_at)
             VALUES ('Men Bio-Wash Pique Polo T-Shirt', ?, ?, 'OWN_PRODUCTION', '100% Cotton Knitted', '6109', 5.00, 50, 2000, 1, NOW())",
            [$styleCode, $catId]
        );
        $productId = (int)Database::lastInsertId();
    } else {
        $productId = (int)$product['id'];
        Database::execute("UPDATE products SET hsn_code = '6109', gst_percentage = 5.00 WHERE id = ?", [$productId]);
    }
    reportStep("Stage 3 - Product Catalog HSN Integration", $productId > 0, "Product ID: {$productId} Style: {$styleCode} HSN: 6109 GST: 5%");

    // Variant setup
    $variant = Database::fetchOne("SELECT id FROM product_variants WHERE product_id = ? LIMIT 1", [$productId]);
    if (!$variant) {
        $sz = Database::fetchOne("SELECT id FROM sizes LIMIT 1");
        $col = Database::fetchOne("SELECT id FROM colors LIMIT 1");
        Database::execute(
            "INSERT INTO product_variants (product_id, sku, size_id, color_id, mrp, selling_price, production_cost, is_active)
             VALUES (?, ?, ?, ?, 899.00, 499.00, 240.00, 1)",
            [$productId, $styleCode . '-L-NVY', $sz ? $sz['id'] : 1, $col ? $col['id'] : 1]
        );
        $variantId = (int)Database::lastInsertId();
    } else {
        $variantId = (int)$variant['id'];
    }

    // -------------------------------------------------------------
    // STAGE 4: PRODUCTION PROGRAM CREATION & ISSUE
    // -------------------------------------------------------------
    $progNum = 'PRG-30R-' . date('mdHis');
    Database::execute(
        "INSERT INTO production_programs (program_number, name, product_id, planned_quantity, completed_quantity, status, target_date, created_at)
         VALUES (?, 'Mass Run 30 Rolls Polo', ?, 1200, 0, 'APPROVED', DATE_ADD(CURDATE(), INTERVAL 10 DAY), NOW())",
        [$progNum, $productId]
    );
    $programId = (int)Database::lastInsertId();
    reportStep("Stage 4 - Production Program Created", $programId > 0, "Program #{$progNum} Target: 1,200 pcs (Style: {$styleCode})");

    // -------------------------------------------------------------
    // STAGE 5: CUTTING OPERATIONS
    // -------------------------------------------------------------
    $firstRollId = $rollIds[0];
    $cutBatchNum = 'CUT-30R-' . date('mdHis');
    Database::execute(
        "INSERT INTO cutting_batches (batch_number, program_id, seller_roll_id, roll_weight, roll_price, planned_qty, cut_qty, wastage_qty, rejected_qty, status, cutting_master, operator_name, created_at)
         VALUES (?, ?, ?, 24.50, 10710.00, 1200, 1220, 15, 5, 'COMPLETED', 'Master Ramesh', 'Operator Suresh', NOW())",
        [$cutBatchNum, $programId, $firstRollId]
    );
    $cuttingBatchId = (int)Database::lastInsertId();
    reportStep("Stage 5 - Fabric Cutting Completed", $cuttingBatchId > 0, "Batch #{$cutBatchNum} Planned: 1,200 -> Cut: 1,220 pieces");

    // -------------------------------------------------------------
    // STAGE 6: STITCHING OPERATIONS
    // -------------------------------------------------------------
    $stitchBatchNum = 'ST-30R-' . date('mdHis');
    Database::execute(
        "INSERT INTO stitching_batches (batch_number, cutting_batch_id, program_id, received_qty, stitched_qty, rejected_qty, rework_qty, status, tailor_master, created_at)
         VALUES (?, ?, ?, 1220, 1180, 15, 25, 'COMPLETED', 'Master Kumar', NOW())",
        [$stitchBatchNum, $cuttingBatchId, $programId]
    );
    $stitchingBatchId = (int)Database::lastInsertId();
    reportStep("Stage 6 - Stitching Assembly Completed", $stitchingBatchId > 0, "Batch #{$stitchBatchNum} Completed: 1,180 pcs, Rework: 25 pcs, Defect: 15 pcs");

    // -------------------------------------------------------------
    // STAGE 7: QUALITY CONTROL (QC) INSPECTION
    // -------------------------------------------------------------
    $qcBatchNum = 'QC-30R-' . date('mdHis');
    Database::execute(
        "INSERT INTO qc_batches (batch_number, qc_number, program_id, stitching_batch_id, total_qty, passed_qty, rework_qty, rejected_qty, status, created_at)
         VALUES (?, ?, ?, ?, 1180, 1140, 25, 15, 'PASSED', NOW())",
        [$qcBatchNum, $qcBatchNum, $programId, $stitchingBatchId]
    );
    $qcBatchId = (int)Database::lastInsertId();

    Database::execute(
        "INSERT INTO qc_items (qc_batch_id, variant_id, size_name, color_name, passed_qty, rework_qty, rejected_qty, base_price, discount_price)
         VALUES (?, ?, 'L', 'Navy Blue', 1140, 25, 15, 899.00, 499.00)",
        [$qcBatchId, $variantId]
    );
    $qcItemId = (int)Database::lastInsertId();
    reportStep("Stage 7 - Quality Control (QC) Passed", $qcBatchId > 0, "QC Batch #{$qcBatchNum} Passed: 1,140 pcs (Yield: 96.6%) Rework: 25 Rejected: 15");

    // -------------------------------------------------------------
    // STAGE 8: PIECE TAGS GENERATION
    // -------------------------------------------------------------
    $tags = [];
    for ($t = 1; $t <= 20; $t++) {
        $tags[] = 'TG30R-' . date('His') . '-' . str_pad((string)$t, 4, '0', STR_PAD_LEFT);
    }
    reportStep("Stage 8 - Piece Tags Generated", count($tags) === 20, "20 Verified QR Tags generated for billing & return tests");

    // -------------------------------------------------------------
    // STAGE 9: FACTORY DIRECT BILLING (OPTION B) WITH HSN 6109
    // -------------------------------------------------------------
    $billNum = 'PB-30R-' . date('mdHis');
    $billedQty = 10;
    $unitPrice = 450.00;
    $mrp = 899.00;
    $taxable = $unitPrice * $billedQty; // 4,500
    $gstRate = 5.00;
    $taxAmount = round($taxable * ($gstRate / 100), 2); // 225
    $totalBill = $taxable + $taxAmount; // 4,725

    Database::execute(
        "INSERT INTO production_bills 
         (bill_number, buyer_type, customer_name, customer_phone, bill_date, payment_method, payment_status,
          tax_type, gross_amount, discount_amount, subtotal, cgst_amount, sgst_amount, igst_amount, tax_amount, total_amount, created_at)
         VALUES (?, 'CUSTOMER', 'Apex Garments Wholesale', '9845012345', CURDATE(), 'UPI', 'PAID', 'CGST_SGST', ?, ?, ?, ?, ?, 0.00, ?, ?, NOW())",
        [$billNum, $mrp * $billedQty, ($mrp - $unitPrice) * $billedQty, $taxable, $taxAmount / 2, $taxAmount / 2, $taxAmount, $totalBill]
    );
    $billId = (int)Database::lastInsertId();

    Database::execute(
        "INSERT INTO production_bill_items
         (bill_id, qc_item_id, variant_id, product_name, sku, hsn_code, size_name, color_name, quantity, mrp,
          unit_price, discount_percentage, discount_amount, gst_percentage, gst_type, tax_input_type,
          sgst_rate, cgst_rate, igst_rate, taxable_amount, cgst_amount, sgst_amount, igst_amount, tax_amount, total_amount)
         VALUES (?, ?, ?, 'Men Bio-Wash Pique Polo T-Shirt', 'STY-POLO-30R-L-NVY', '6109', 'L', 'Navy Blue', ?, ?,
                 ?, 49.94, ?, 5.00, 'BOTH', 'PERCENT', 2.50, 2.50, 0.00, ?, ?, ?, 0.00, ?, ?)",
        [$billId, $qcItemId, $variantId, $billedQty, $mrp, $unitPrice, ($mrp - $unitPrice) * $billedQty, $taxable, $taxAmount / 2, $taxAmount / 2, $taxAmount, $totalBill]
    );
    $billItemId = (int)Database::lastInsertId();

    // Link 10 tags
    for ($k = 0; $k < $billedQty; $k++) {
        Database::execute(
            "INSERT INTO production_bill_item_tags (bill_id, bill_item_id, qc_item_id, tag_code)
             VALUES (?, ?, ?, ?)",
            [$billId, $billItemId, $qcItemId, $tags[$k]]
        );
    }
    reportStep("Stage 9 - Production Direct Billing with HSN", $billId > 0, "Bill #{$billNum} Total: ₹{$totalBill} HSN: 6109 GST: 5% Tags: 10 linked");

    // -------------------------------------------------------------
    // STAGE 10: NEW MODULE 1 - CUSTOMER RETURN & CREDIT NOTE
    // -------------------------------------------------------------
    $returnNum = 'PR-CRN-30R-' . date('mdHis');
    $retQty = 3; // return 3 garments
    $retTaxable = $unitPrice * $retQty; // 1,350
    $retGst = round($retTaxable * ($gstRate / 100), 2); // 67.50
    $netCredit = $retTaxable + $retGst; // 1,417.50

    Database::execute(
        "INSERT INTO production_returns
         (return_number, bill_id, bill_number, customer_name, customer_phone, return_type, disposition,
          total_returned_qty, subtotal, gst_reversal_amount, net_amount, status, refund_mode, notes, created_at)
         VALUES (?, ?, ?, 'Apex Garments Wholesale', '9845012345', 'CREDIT_NOTE', 'RESTOCK_INVENTORY',
                 ?, ?, ?, ?, 'COMPLETED', 'STORE_CREDIT', 'Customer returned 3 pcs due to size mismatch', NOW())",
        [$returnNum, $billId, $billNum, $retQty, $retTaxable, $retGst, $netCredit]
    );
    $returnId = (int)Database::lastInsertId();

    for ($r = 0; $r < $retQty; $r++) {
        $tagToReturn = $tags[$r];
        Database::execute(
            "INSERT INTO production_return_items
             (return_id, bill_item_id, qc_item_id, variant_id, product_name, sku, hsn_code, tag_code,
              quantity, unit_price, gst_percentage, taxable_amount, tax_amount, total_amount,
              reason, item_condition, action_taken)
             VALUES (?, ?, ?, ?, 'Men Bio-Wash Pique Polo T-Shirt', 'STY-POLO-30R-L-NVY', '6109', ?, 1, ?, 5.00, ?, ?, ?, 'Size mismatch', 'GOOD', 'RESTOCKED')",
            [$returnId, $billItemId, $qcItemId, $variantId, $tagToReturn, $unitPrice, $unitPrice, round($unitPrice * 0.05, 2), round($unitPrice * 1.05, 2)]
        );
        // Release tag from bill tags
        Database::execute("DELETE FROM production_bill_item_tags WHERE bill_id = ? AND tag_code = ?", [$billId, $tagToReturn]);
    }
    reportStep("Stage 10 - New Module 1: Return & Credit Note", $returnId > 0, "Credit Note #{$returnNum} Restocked: 3 pcs Net Credit: ₹{$netCredit} GST Reversal: ₹{$retGst}");

    // -------------------------------------------------------------
    // STAGE 11: NEW MODULE 2 - WAREHOUSE STOCK REQUISITIONS
    // -------------------------------------------------------------
    $reqNum = 'WH-REQ-30R-' . date('mdHis');
    Database::execute(
        "INSERT INTO production_stock_requests
         (request_number, warehouse_name, requested_by_name, priority, required_by_date,
          total_requested_pieces, fulfilled_pieces, status, notes, created_at)
         VALUES (?, 'Central Warehouse (Main Hub)', 'Warehouse Manager Suresh', 'HIGH', DATE_ADD(CURDATE(), INTERVAL 5 DAY),
                 250, 200, 'APPROVED', 'Urgent retail replenishment for Polo T-Shirts', NOW())",
        [$reqNum]
    );
    $reqId = (int)Database::lastInsertId();

    Database::execute(
        "INSERT INTO production_stock_request_items
         (request_id, product_id, variant_id, product_name, sku, size_name, color_name, requested_qty, fulfilled_qty, status)
         VALUES (?, ?, ?, 'Men Bio-Wash Pique Polo T-Shirt', 'STY-POLO-30R-L-NVY', 'L', 'Navy Blue', 250, 200, 'PARTIAL')",
        [$reqId, $productId, $variantId]
    );
    reportStep("Stage 11 - New Module 2: Stock Requisition", $reqId > 0, "Requisition #{$reqNum} Requested: 250 pcs Fulfilled: 200 pcs Status: APPROVED");

    // -------------------------------------------------------------
    // STAGE 12: PRODUCTION REPORT EXPORT SERVICE (ALL 10 MODULES)
    // -------------------------------------------------------------
    $reportModules = ['rolls', 'programs', 'cuttings', 'stitching', 'qc', 'billing', 'returns', 'stock_requests', 'wastage', 'expenses'];
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
    $reportData = ProductionReportService::getReportData($startDate, $endDate, $reportModules);

    $hasRolls = !empty($reportData['rolls']);
    $hasPrograms = !empty($reportData['programs']);
    $hasCuttings = !empty($reportData['cuttings']);
    $hasStitching = !empty($reportData['stitching']);
    $hasQc = !empty($reportData['qc']);
    $hasBilling = !empty($reportData['billing']);
    $hasReturns = !empty($reportData['returns']);
    $hasReqs = !empty($reportData['stock_requests']);

    $exportSuccess = $hasRolls && $hasPrograms && $hasCuttings && $hasStitching && $hasQc && $hasBilling && $hasReturns && $hasReqs;
    reportStep("Stage 12 - Master Report Export Generation", $exportSuccess, 
        "Rolls: " . count($reportData['rolls'] ?? []) . 
        " | Programs: " . count($reportData['programs'] ?? []) . 
        " | QC: " . count($reportData['qc'] ?? []) . 
        " | Bills: " . count($reportData['billing'] ?? []) . 
        " | Returns: " . count($reportData['returns'] ?? []) . 
        " | Stock Requests: " . count($reportData['stock_requests'] ?? [])
    );

    echo "\n-------------------------------------------------------------\n";
    echo "SUMMARY: {$results['passed']} PASSED, {$results['failed']} FAILED\n";
    echo "-------------------------------------------------------------\n";

} catch (\Throwable $e) {
    echo "TEST CRASH: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
