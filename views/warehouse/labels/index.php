<?php
// views/warehouse/labels/index.php
// Unified Warehouse QR Labels & Printing Suite

$currentSourceType = $_GET['source_type'] ?? (($_GET['tab'] ?? '') === 'returns' ? 'returns' : 'tp_purchase');
if (!in_array($currentSourceType, ['returns', 'tp_purchase', 'po'])) {
    $currentSourceType = 'tp_purchase';
}
$returnSource = trim((string)($_GET['return_source'] ?? ''));
$isDirectPurchaseReturn = $currentSourceType === 'returns' && $returnSource === 'purchase';
$selectedReturnItemId = !empty($_GET['return_item_id']) ? (int)$_GET['return_item_id'] : null;
$returnTagPayload = static function (string $tag): string {
    $tag = trim($tag);
    return $tag !== '' && !str_starts_with(strtoupper($tag), 'R') ? 'R' . $tag : $tag;
};

$selectedVariantId = !empty($_GET['variant_id']) ? (int)$_GET['variant_id'] : (!empty($_GET['return_variant_id']) ? (int)$_GET['return_variant_id'] : null);
$selectedSourceId  = !empty($_GET['source_id']) ? (int)$_GET['source_id'] : (!empty($_GET['return_id']) ? (int)$_GET['return_id'] : null);
$selectedSellerId  = !empty($_GET['seller_id']) ? (int)$_GET['seller_id'] : null;
$selectedBrandName = trim($_GET['brand_name'] ?? '');
$selectedCatalogCode = trim($_GET['catalog_code'] ?? '');
$selectedCustomBrand = trim($_GET['custom_brand'] ?? ($_GET['brand_name'] ?? ''));
$selectedCategoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$selectedSubcategoryId = !empty($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;
$selectedSubSubcategoryId = !empty($_GET['sub_subcategory_id']) ? (int)$_GET['sub_subcategory_id'] : null;
$tagType           = $_GET['tag_type'] ?? 'GOOD';
$copiesCount       = !empty($_GET['copies']) ? max(1, (int)$_GET['copies']) : 1;
$notes             = trim($_GET['reason'] ?? '');
$targetWarehouseId = !empty($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : ($warehouses[0]['id'] ?? 1);
$qrMode            = trim($_GET['qr_mode'] ?? '');
$qrAdjustmentType  = trim($_GET['qr_adjustment_type'] ?? 'STANDARD');
$isZeroStockRestricted = false;
$qrBatches         = $qrBatches ?? [];
$inwardedVariantIds= $inwardedVariantIds ?? [];
$viewParam         = $_GET['view'] ?? '';
$isViewingExisting = $viewParam === 'existing';
$isGeneratingView  = in_array($viewParam, ['generate', 'existing'], true) || (!empty($selectedCatalogCode) && $viewParam !== 'batches') || (!empty($selectedVariantId) && $viewParam !== 'batches');
$canCreateWarehouseQr = hasPermission('wh_labels', 'create') || (function_exists('isAdmin') && isAdmin());
$canEditWarehouseQr = hasPermission('wh_labels', 'edit') || (function_exists('isAdmin') && isAdmin());
$canDeleteWarehouseQr = hasPermission('wh_labels', 'delete') || (function_exists('isAdmin') && isAdmin());
$canViewWarehouseQr = hasPermission('wh_labels', 'view') || (function_exists('isAdmin') && isAdmin());
$canInwardQrUnits = hasPermission('wh_labels', 'create')
    || hasPermission('inward', 'verify')
    || hasPermission('inward', 'create')
    || hasPermission('purchases', 'receive')
    || (function_exists('isAdmin') && isAdmin());

// Build items to generate labels for
$activeVar = null;
$generatedItems = [];
$selectedWarehouseReturnItem = null;
$selectedWarehouseReturnInfo = null;
$warehouseReturnItems = [];
if ($currentSourceType === 'returns' && !$isDirectPurchaseReturn && $selectedSourceId) {
    $warehouseReturnItems = WarehouseReturnItem::getByReturnId($selectedSourceId);
    if ($selectedReturnItemId) {
        foreach ($warehouseReturnItems as $returnItem) {
            if ((int)$returnItem['id'] === $selectedReturnItemId
                && (!$selectedVariantId || (int)$returnItem['variant_id'] === $selectedVariantId)) {
                $selectedWarehouseReturnItem = $returnItem;
                break;
            }
        }
    }
}
$isSpecificWarehouseReturnItem = $selectedWarehouseReturnItem !== null;
$monthStr = sprintf('%02d', date('n'));
$yearStr = date('y');
$deletedWhRows = Database::fetchAll("SELECT code, variant_id FROM warehouse_deleted_label_codes");
$deletedWhCodes = [];
foreach ($deletedWhRows as $drow) {
    $cKey = strtoupper(trim((string)($drow['code'] ?? '')));
    if ($cKey === '') continue;
    $vKey = (int)($drow['variant_id'] ?? 0);
    if ($vKey > 0) {
        $deletedWhCodes[$vKey . '_' . $cKey] = true;
    } else {
        $deletedWhCodes['0_' . $cKey] = true;
    }
}
if (!function_exists('isWarehouseCodeDeleted')) {
    function isWarehouseCodeDeleted(string $code, ?int $variantId, array $deletedWhCodes): bool {
        $cKey = strtoupper(trim($code));
        if ($cKey === '') return false;
        if ($variantId !== null && $variantId > 0 && !empty($deletedWhCodes[$variantId . '_' . $cKey])) {
            return true;
        }
        return !empty($deletedWhCodes['0_' . $cKey]);
    }
}

if (!function_exists('getWarehouseQrSequenceOffset')) {
    function getWarehouseQrSequenceOffset(string $prefix, ?int $excludeVariantId = null): int {
        $maxSequence = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '\d{4}(\d+)$/i';

        $registrySql = "SELECT qr_code, series_end_code FROM warehouse_qr_codes WHERE qr_code LIKE ?";
        $registryParams = [$prefix . '%'];
        if ($excludeVariantId !== null) {
            $registrySql .= " AND (variant_id IS NULL OR variant_id != ?)";
            $registryParams[] = $excludeVariantId;
        }
        foreach (Database::fetchAll($registrySql, $registryParams) as $row) {
            if (preg_match($pattern, (string)$row['qr_code'], $match)) {
                $maxSequence = max($maxSequence, (int)$match[1]);
            }
            if (!empty($row['series_end_code']) && preg_match($pattern, (string)$row['series_end_code'], $match)) {
                $maxSequence = max($maxSequence, (int)$match[1]);
            }
        }

        $notesSql = "SELECT notes FROM stock_transactions WHERE notes LIKE ? AND reference_type IN ('THIRD_PARTY_STICKERED', 'warehouse_returns')";
        $notesParams = ['%' . $prefix . '%'];
        if ($excludeVariantId !== null) {
            $notesSql .= " AND (variant_id IS NULL OR variant_id != ?)";
            $notesParams[] = $excludeVariantId;
        }
        foreach (Database::fetchAll($notesSql, $notesParams) as $row) {
            if (preg_match_all('/' . preg_quote($prefix, '/') . '\d{4}(\d+)/i', (string)($row['notes'] ?? ''), $matches)) {
                foreach ($matches[1] as $sequence) {
                    $maxSequence = max($maxSequence, (int)$sequence);
                }
            }
        }

        $variantSql = "SELECT qr_code, series_start_code, series_end_code FROM product_variants WHERE qr_code LIKE ? OR series_start_code LIKE ? OR series_end_code LIKE ?";
        $variantParams = [$prefix . '%', $prefix . '%', $prefix . '%'];
        if ($excludeVariantId !== null) {
            $variantSql .= " AND id != ?";
            $variantParams[] = $excludeVariantId;
        }
        foreach (Database::fetchAll($variantSql, $variantParams) as $row) {
            if (preg_match($pattern, (string)$row['qr_code'], $match)) {
                $maxSequence = max($maxSequence, (int)$match[1]);
            }
            if (!empty($row['series_start_code']) && preg_match($pattern, (string)$row['series_start_code'], $match)) {
                $maxSequence = max($maxSequence, (int)$match[1]);
            }
            if (!empty($row['series_end_code']) && preg_match($pattern, (string)$row['series_end_code'], $match)) {
                $maxSequence = max($maxSequence, (int)$match[1]);
            }
        }

        return $maxSequence;
    }
}

if (!function_exists('calculateCategorySequenceOffset')) {
    /**
     * Calculate the next QR offset across earlier products and previously issued tags.
     * Prevents QR duplication (e.g. 1st product: 1-10, 2nd product: starts at 11 series: 11-20, etc.)
     */
    function calculateCategorySequenceOffset($variantId, $prodPrefix, $catId, $subcatId, $subSubcatId, $prodCreatedAt, $productId) {
        // Query earlier third-party variants in the same category hierarchy.
        $sql = "
            SELECT pv.id as variant_id, pv.pack_quantity, p.id as product_id, p.name as product_name, p.created_at,
                   p.quantity as prod_qty,
                   COALESCE(pi_agg.po_qty, 0) as po_qty,
                   COALESCE(st_agg.inwarded_qty, 0) as inwarded_qty
            FROM product_variants pv
            JOIN products p ON pv.product_id = p.id
            LEFT JOIN (
                SELECT variant_id, SUM(quantity) as po_qty
                FROM purchase_items
                GROUP BY variant_id
            ) pi_agg ON pi_agg.variant_id = pv.id
            LEFT JOIN (
                SELECT variant_id, SUM(quantity) as inwarded_qty
                FROM stock_transactions
                WHERE reference_type = 'THIRD_PARTY_STICKERED'
                GROUP BY variant_id
            ) st_agg ON st_agg.variant_id = pv.id
            WHERE pv.is_active = 1
              AND pv.id != ?
              AND (
                  (p.created_at < ?)
                  OR (p.created_at = ? AND p.id < ?)
                  OR (p.id = ? AND pv.id < ?)
              )
                AND (
                  (? > 0 AND p.warehouse_category_id = ? AND p.warehouse_subcategory_id = ? AND p.warehouse_sub_subcategory_id = ?)
                    OR (? > 0 AND p.category_id = ? AND p.subcategory_id = ? AND p.sub_subcategory_id = ?)
                  OR (? != '' AND p.qr_code_prefix = ?)
              )
            ORDER BY p.created_at ASC, p.id ASC, pv.id ASC
        ";

        $params = [
            $variantId,
            $prodCreatedAt,
            $prodCreatedAt, $productId,
            $productId, $variantId,
            $catId ?: 0, $catId ?: 0, $subcatId ?: 0, $subSubcatId ?: 0,
            $catId ?: 0, $catId ?: 0, $subcatId ?: 0, $subSubcatId ?: 0,
            $prodPrefix ?: '', $prodPrefix ?: ''
        ];

        $earlierRows = Database::fetchAll($sql, $params);

        $categoryOffset = 0;
        foreach ($earlierRows as $er) {
            $qty = $er['po_qty'] > 0 ? (int)$er['po_qty'] : max((int)$er['prod_qty'], (int)$er['inwarded_qty'], (int)($er['pack_quantity'] ?? 0));
            if ($qty <= 0) $qty = 1;
            $categoryOffset += $qty;
        }

        return max($categoryOffset, getWarehouseQrSequenceOffset((string)$prodPrefix, (int)$variantId));
    }
}

// Inwarding and expected quantity defaults (prevent undefined variable notices in HTML/JS)
$alreadyInwardedQty = 0;
$totalExpectedQty = 0;
$remainingQty = 0;
$isFullyInwarded = false;
$isPartiallyInwarded = false;

// Extra Generated QRs defaults (User requested profile-wise tab)
$extraQrSupplierGroups = $extraQrSupplierGroups ?? [];
$totalExtraQrsCount = $totalExtraQrsCount ?? 0;
$totalExtraPcsCount = $totalExtraPcsCount ?? 0;
$extraQrRows = $extraQrRows ?? [];

if (empty($extraQrSupplierGroups)) {
    $fallbackExtraRows = Database::fetchAll(
        "SELECT wqc.*,
                pv.sku, pv.mrp, pv.selling_price, pv.purchase_price, pv.production_cost,
                pv.qr_type as variant_qr_type, pv.pack_quantity as var_pack_qty,
                pv.is_complementary as var_is_comp,
                p.id as product_id, p.name as product_name, p.code as product_code, p.catalog_code,
                p.is_complementary as prod_is_comp, p.source_type,
                COALESCE(b.name, s.company, s.name, 'Third-Party Brand') as brand_name,
                COALESCE(s.company, s.name, b.name, 'Warehouse Produced') as supplier_name,
                s.id as supplier_id,
                COALESCE(cat.name, c_old.name, '') as category_name,
                COALESCE(subcat.name, sc_old.name, '') as subcategory_name,
                COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name,
                sz.name as size_name, col.name as color_name,
                w.name as warehouse_name,
                COALESCE(u.full_name, u.username, 'System') as creator_name
         FROM warehouse_qr_codes wqc
         JOIN product_variants pv ON wqc.variant_id = pv.id
         JOIN products p ON pv.product_id = p.id
         LEFT JOIN brands b ON p.brand_id = b.id
         LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
         LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
         LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
         LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
         LEFT JOIN categories c_old ON p.category_id = c_old.id
         LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
         LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
         LEFT JOIN sizes sz ON pv.size_id = sz.id
         LEFT JOIN colors col ON pv.color_id = col.id
         LEFT JOIN warehouses w ON wqc.warehouse_id = w.id
         LEFT JOIN users u ON wqc.created_by = u.id
         WHERE wqc.is_extra = 1 
            OR wqc.id IN (
                SELECT id FROM warehouse_qr_codes WHERE pack_quantity > 1 AND id NOT IN (
                    SELECT MIN(id) FROM warehouse_qr_codes GROUP BY variant_id
                )
            )
         ORDER BY wqc.id DESC"
    );
    if (!empty($fallbackExtraRows)) {
        $extraQrRows = $fallbackExtraRows;
        $totalExtraQrsCount = count($extraQrRows);
        $totalExtraPcsCount = 0;
        foreach ($extraQrRows as $eqr) {
            $sName = trim($eqr['supplier_name'] ?? '') ?: 'Warehouse Produced';
            if (!isset($extraQrSupplierGroups[$sName])) {
                $extraQrSupplierGroups[$sName] = [
                    'supplier_name'   => $sName,
                    'supplier_id'     => (int)($eqr['supplier_id'] ?? 0),
                    'total_qrs'       => 0,
                    'total_pcs'       => 0,
                    'inwarded_pcs'    => 0,
                    'pending_pcs'     => 0,
                    'items'           => [],
                ];
            }
            $pQty = (int)($eqr['pack_quantity'] ?? 1);
            if ($pQty <= 0) $pQty = 1;
            $isInwarded = !empty($eqr['inwarded_at']);
            $extraQrSupplierGroups[$sName]['total_qrs']++;
            $extraQrSupplierGroups[$sName]['total_pcs'] += $pQty;
            if ($isInwarded) {
                $extraQrSupplierGroups[$sName]['inwarded_pcs'] += $pQty;
            } else {
                $extraQrSupplierGroups[$sName]['pending_pcs'] += $pQty;
            }
            $totalExtraPcsCount += $pQty;
            $extraQrSupplierGroups[$sName]['items'][] = $eqr;
        }
    }
}

// Preload PO Information and Items if PO source is given
$poInfo = null;
$poItems = [];
$poTotalQty = 0;
if (!empty($selectedSourceId)) {
    $poInfo = Database::fetchOne(
        "SELECT po.*, s.name as seller_name, s.company as seller_company, w.name as warehouse_name 
         FROM purchase_orders po 
         LEFT JOIN warehouse_suppliers s ON (po.warehouse_supplier_id = s.id OR po.seller_id = s.id) 
         LEFT JOIN warehouses w ON po.warehouse_id = w.id 
         WHERE po.id = ?",
        [$selectedSourceId]
    );
    if ($poInfo) {
        $poItems = Database::fetchAll(
            "SELECT pi.*, pv.sku, p.name as product_name, COALESCE(NULLIF(p.catalog_code, ''), p.code) as catalog_code,
                    COALESCE(b.name, s.company, s.name, 'Third-Party Brand') as brand_name,
                    sz.name as size_name, c.name as color_name
             FROM purchase_items pi
             JOIN product_variants pv ON pi.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN brands b ON p.brand_id = b.id
             LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pi.purchase_order_id = ?",
            [$selectedSourceId]
        );
        foreach ($poItems as $pi) {
            $poTotalQty += (int)($pi['quantity'] ?? 0);
        }
        if (empty($selectedSellerId) && !empty($poInfo['warehouse_supplier_id'])) {
            $selectedSellerId = (int)$poInfo['warehouse_supplier_id'];
        }
    }
}

if ($currentSourceType === 'po' || $currentSourceType === 'tp_purchase') {
    // If PO source is given but no catalog code or variant is selected, auto-pick from PO items
    if (!empty($selectedSourceId) && empty($selectedCatalogCode) && empty($selectedVariantId) && !empty($poItems)) {
        $firstPi = $poItems[0];
        if (!empty($firstPi['catalog_code'])) {
            $selectedCatalogCode = $firstPi['catalog_code'];
            $selectedVariantId = (int)$firstPi['variant_id'];
            if (empty($selectedBrandName) && !empty($firstPi['brand_name'])) {
                $selectedBrandName = $firstPi['brand_name'];
            }
        }
    }

    // Disconnect PO if user selected a brand that does not match the PO supplier/items
    if (!empty($poInfo) && !empty($selectedBrandName)) {
        $poBrandMatches = false;
        if (strcasecmp(trim($poInfo['seller_name'] ?? ''), $selectedBrandName) === 0 || strcasecmp(trim($poInfo['seller_company'] ?? ''), $selectedBrandName) === 0) {
            $poBrandMatches = true;
        } else {
            foreach ($poItems as $pi) {
                if (strcasecmp(trim($pi['brand_name'] ?? ''), $selectedBrandName) === 0) {
                    $poBrandMatches = true;
                    break;
                }
            }
        }
        if (!$poBrandMatches) {
            $selectedSourceId = null;
            $poInfo = null;
            $poItems = [];
            $poTotalQty = 0;
        }
    }

    // Brand based dynamic resolution: if a brand is selected, ensure catalog_code matches that brand
    if (!empty($selectedBrandName)) {
        $pool = !empty($thirdPartyVariants) ? $thirdPartyVariants : $stockVariants;
        $codeMatchesBrand = false;
        if (!empty($selectedCatalogCode)) {
            foreach ($pool as $sv) {
                $cCode = trim($sv['catalog_code'] ?? '') ?: trim($sv['product_code'] ?? '');
                if (strcasecmp($cCode, $selectedCatalogCode) === 0) {
                    $bName = trim($sv['brand_name'] ?? '');
                    if (strcasecmp($bName, $selectedBrandName) === 0) {
                        $codeMatchesBrand = true;
                        if (empty($selectedVariantId)) {
                            $selectedVariantId = (int)$sv['id'];
                        }
                        $activeVar = $sv;
                        break;
                    }
                }
            }
        }
        if (!$codeMatchesBrand) {
            $selectedCatalogCode = '';
            $selectedVariantId = null;
            $activeVar = null;
            foreach ($pool as $sv) {
                $bName = trim($sv['brand_name'] ?? '');
                if (strcasecmp($bName, $selectedBrandName) === 0) {
                    $cCode = trim($sv['catalog_code'] ?? '') ?: trim($sv['product_code'] ?? '');
                    if (!empty($cCode)) {
                        $selectedCatalogCode = $cCode;
                        $selectedVariantId = (int)$sv['id'];
                        $activeVar = $sv;
                        break;
                    }
                }
            }
        }
    }

    // Auto-resolve variant_id and activeVar from selected Generated Code (catalog_code)
    if (!empty($selectedCatalogCode) && empty($selectedVariantId)) {
        $variantsPool = !empty($thirdPartyVariants) ? $thirdPartyVariants : $stockVariants;
        foreach ($variantsPool as $sv) {
            $cCode = trim($sv['catalog_code'] ?? '') ?: trim($sv['product_code'] ?? '');
            if (strcasecmp($cCode, $selectedCatalogCode) === 0) {
                $selectedVariantId = (int)$sv['id'];
                $activeVar = $sv;
                break;
            }
        }
        if (!$activeVar) {
            $activeVar = Database::fetchOne(
                "SELECT pv.*, p.name as product_name, p.code as product_code, COALESCE(NULLIF(p.catalog_code, ''), p.code) as catalog_code, p.qr_code_prefix,
                        p.is_complementary as product_is_comp, p.quantity, cat.category_type as warehouse_category_type,
                        p.brand_id, COALESCE(b.name, s.company, s.name, 'Third-Party Brand') as brand_name,
                        p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.warehouse_category_id as category_id, p.warehouse_subcategory_id as subcategory_id, p.warehouse_sub_subcategory_id as sub_subcategory_id,
                        p.source_type, p.warehouse_supplier_id as seller_id, s.name as seller_name, s.company as seller_company,
                        COALESCE(cat.name, c_old.name, '') as category_name, 
                        COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                        COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name,
                        sz.name as size_name, c.name as color_name,
                        COALESCE((
                            SELECT SUM(sb.quantity)
                            FROM stock_balances sb
                            WHERE sb.variant_id = pv.id
                              AND sb.stock_state = 'AVAILABLE'
                              AND sb.location_type = 'WAREHOUSE'
                        ), 0) as available_qty
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN brands b ON p.brand_id = b.id
                 LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
                 LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
                 LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
                 LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
                 LEFT JOIN categories c_old ON p.category_id = c_old.id
                 LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
                 LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 WHERE (p.catalog_code = ? OR p.code = ?) AND pv.is_active = 1
                 LIMIT 1",
                [$selectedCatalogCode, $selectedCatalogCode]
            );
            if ($activeVar) {
                $selectedVariantId = (int)$activeVar['id'];
            }
        }
        if ($activeVar && empty($selectedBrandName) && !empty($activeVar['brand_name'])) {
            $selectedBrandName = $activeVar['brand_name'];
        }
    }

    if ($selectedVariantId) {
        // Priority 1: User explicitly selected a Variant / SKU or resolved from Generated Code
        $variantsPool = !empty($thirdPartyVariants) ? $thirdPartyVariants : $stockVariants;
        if (!isset($activeVar) || !$activeVar) {
            foreach ($variantsPool as $sv) {
                if ($sv['id'] == $selectedVariantId) {
                    $activeVar = $sv;
                    break;
                }
            }
        }
        if (!$activeVar) {
            foreach ($stockVariants as $sv) {
                if ($sv['id'] == $selectedVariantId) {
                    $activeVar = $sv;
                    break;
                }
            }
        }
        if (!$activeVar) {
            $activeVar = Database::fetchOne(
                "SELECT pv.*, p.name as product_name, p.code as product_code, p.catalog_code, p.qr_code_prefix,
                        p.is_complementary as product_is_comp, p.quantity, cat.category_type as warehouse_category_type,
                        p.brand_id, COALESCE(b.name, s.company, s.name, 'Third-Party Brand') as brand_name,
                        p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.warehouse_category_id as category_id, p.warehouse_subcategory_id as subcategory_id, p.warehouse_sub_subcategory_id as sub_subcategory_id,
                        p.warehouse_category_id, p.warehouse_subcategory_id, p.warehouse_sub_subcategory_id,
                        p.created_at as product_created_at, p.id as product_id,
                        p.source_type, p.warehouse_supplier_id as seller_id, s.name as seller_name, s.company as seller_company,
                        COALESCE(cat.name, c_old.name, '') as category_name, 
                        COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                        COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name,
                        sz.name as size_name, c.name as color_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN brands b ON p.brand_id = b.id
                 LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
                 LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
                 LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
                 LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
                 LEFT JOIN categories c_old ON p.category_id = c_old.id
                 LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
                 LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 WHERE pv.id = ?",
                [$selectedVariantId]
            );
        }

        if ($activeVar) {
            // Preserve the returned product hierarchy when opening Return QR generation directly.
            if (empty($selectedCategoryId)) {
                $selectedCategoryId = (int)($activeVar['category_id'] ?? $activeVar['warehouse_category_id'] ?? 0) ?: null;
            }
            if (empty($selectedSubcategoryId)) {
                $selectedSubcategoryId = (int)($activeVar['subcategory_id'] ?? $activeVar['warehouse_subcategory_id'] ?? 0) ?: null;
            }
            if (empty($selectedSubSubcategoryId)) {
                $selectedSubSubcategoryId = (int)($activeVar['sub_subcategory_id'] ?? $activeVar['warehouse_sub_subcategory_id'] ?? 0) ?: null;
            }
            if (!empty($activeVar['catalog_code']) && empty($selectedCatalogCode)) {
                $selectedCatalogCode = $activeVar['catalog_code'];
            }

            // Find PO Line Quantity for this active variant if coming from a PO
            $poVarQty = 0;
            if (!empty($poItems)) {
                foreach ($poItems as $pi) {
                    if ((int)$pi['variant_id'] === (int)$selectedVariantId) {
                        $poVarQty = (int)$pi['quantity'];
                        break;
                    }
                }
            }
            if ($poVarQty <= 0) {
                $poItemRow = Database::fetchOne(
                    "SELECT pi.quantity, pi.purchase_order_id, po.po_number 
                     FROM purchase_items pi 
                     JOIN purchase_orders po ON pi.purchase_order_id = po.id 
                     WHERE pi.variant_id = ? 
                     ORDER BY pi.id DESC LIMIT 1",
                    [$selectedVariantId]
                );
                if ($poItemRow && !empty($poItemRow['quantity'])) {
                    $poVarQty = (int)$poItemRow['quantity'];
                    if (empty($selectedSourceId) && !empty($poItemRow['purchase_order_id'])) {
                        $selectedSourceId = (int)$poItemRow['purchase_order_id'];
                    }
                    if (empty($poNumber) && !empty($poItemRow['po_number'])) {
                        $poNumber = $poItemRow['po_number'];
                    }
                }
            }

            // Inward transactions already recorded for this variant
            $alreadyInwardedQty = (int)(Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as total_inwarded 
                 FROM stock_transactions 
                 WHERE variant_id = ? AND reference_type = 'THIRD_PARTY_STICKERED'",
                [$selectedVariantId]
            )['total_inwarded'] ?? 0);

            $activeCategoryType = strtoupper($activeVar['warehouse_category_type'] ?? '');
            $isComp = !empty($activeVar['is_complementary']) || !empty($activeVar['product_is_comp']) || $activeCategoryType === 'COMPLEMENTARY';
            $isSeries = ($activeVar['qr_type'] ?? '') === 'SERIES_BATCH' || $qrMode === 'SERIES_BATCH' || $activeCategoryType === 'SERIES';
            $isSeriesOrComp = $isSeries || $isComp;
            // Series/Complementary products use one aggregate QR for the returned pack.
            if ($isSeriesOrComp && empty($_GET['copies'])) {
                $copiesCount = 1;
            }

            // Pack QR products represent one fixed inward quantity; normal products follow PO quantities.
            if ($isSeriesOrComp && (int)($activeVar['pack_quantity'] ?? 0) > 1) {
                $totalExpectedQty = max((int)$activeVar['pack_quantity'], $poVarQty, $alreadyInwardedQty);
            } elseif ($poVarQty > 0) {
                $totalExpectedQty = max($poVarQty, $alreadyInwardedQty);
            } elseif (!empty($activeVar['quantity']) && (int)$activeVar['quantity'] > 0) {
                $totalExpectedQty = max((int)$activeVar['quantity'], (int)($activeVar['pack_quantity'] ?? 0), $alreadyInwardedQty);
            } elseif (!empty($activeVar['pack_quantity']) && (int)$activeVar['pack_quantity'] > 0) {
                $totalExpectedQty = max((int)$activeVar['pack_quantity'], $alreadyInwardedQty);
            } elseif (!empty($activeVar['available_qty']) && (int)$activeVar['available_qty'] > 0) {
                $totalExpectedQty = (int)$activeVar['available_qty'] + $alreadyInwardedQty;
            } else {
                $totalExpectedQty = $alreadyInwardedQty > 0 ? $alreadyInwardedQty : 1;
            }

            $remainingQty = max(0, $totalExpectedQty - $alreadyInwardedQty);
            $isFullyInwarded = ($alreadyInwardedQty >= $totalExpectedQty && $totalExpectedQty > 0);
            $isPartiallyInwarded = ($alreadyInwardedQty > 0 && !$isFullyInwarded);

            // Determine effective stock / pieces to generate
            if ($isFullyInwarded) {
                $effectiveStock = $totalExpectedQty;
            } else {
                $effectiveStock = $remainingQty;
            }

            // Validation & Restriction: 0 Product Stock Count cannot generate QR labels for regular stock
            if ($effectiveStock <= 0 && empty($selectedSourceId) && $currentSourceType !== 'po' && !$isFullyInwarded) {
                $isZeroStockRestricted = true;
                $copiesCount = 0;
            } else {
                $isZeroStockRestricted = false;
                if ($isSeriesOrComp) {
                    $copiesCount = 1;
                } elseif ($qrAdjustmentType === 'PRODUCE_NEW') {
                    $copiesCount = 1;
                } else {
                    if (isset($_GET['copies']) && (int)$_GET['copies'] > 0) {
                        $copiesCount = min($effectiveStock, (int)$_GET['copies']);
                    } else {
                        $copiesCount = $effectiveStock > 0 ? $effectiveStock : 1;
                    }
                }
            }
            if ($isDirectPurchaseReturn && !empty($selectedPurchaseReturnItem)) {
                $isDirectReturnSeriesOrComp = strtoupper((string)$selectedPurchaseReturnItem['qr_type']) === 'SERIES_BATCH'
                    || strtoupper((string)$selectedPurchaseReturnItem['qr_type']) === 'SERIES'
                    || !empty($selectedPurchaseReturnItem['is_complementary'])
                    || strtoupper((string)$selectedPurchaseReturnItem['warehouse_category_type']) === 'SERIES'
                    || strtoupper((string)$selectedPurchaseReturnItem['warehouse_category_type']) === 'COMPLEMENTARY';
                $totalExpectedQty = (int)$selectedPurchaseReturnItem['quantity'];
                $alreadyInwardedQty = 0;
                $remainingQty = $totalExpectedQty;
                $isFullyInwarded = false;
                $isPartiallyInwarded = false;
                $effectiveStock = $totalExpectedQty;
                $isZeroStockRestricted = false;
                $copiesCount = $isDirectReturnSeriesOrComp ? 1 : $totalExpectedQty;
            }
            $poNumber = $poInfo['po_number'] ?? '';

            $brandOrSeller = !empty($activeVar['brand_name']) && $activeVar['brand_name'] !== 'Third-Party Brand' ? $activeVar['brand_name'] : (!empty($activeVar['seller_company']) ? $activeVar['seller_company'] : (!empty($activeVar['seller_name']) ? $activeVar['seller_name'] : 'GRM CLASSIC'));

            $mrpVal = (float)($activeVar['mrp'] ?? 0);
            $sellingVal = (float)($activeVar['selling_price'] ?? 0);
            if ($sellingVal <= 0) {
                $sellingVal = (float)($activeVar['purchase_price'] ?? $activeVar['production_cost'] ?? $mrpVal);
            }
            if ($sellingVal <= 0) {
                $sellingVal = $mrpVal;
            }
            $costVal = (float)($activeVar['purchase_price'] ?? $activeVar['production_cost'] ?? 0);
            $fmtMrp = ($mrpVal == (int)$mrpVal) ? (int)$mrpVal : number_format($mrpVal, 2, '.', '');
            $fmtSelling = ($sellingVal == (int)$sellingVal) ? (int)$sellingVal : number_format($sellingVal, 2, '.', '');
            $catH = implode(' › ', array_filter([$activeVar['category_name'] ?? '', $activeVar['subcategory_name'] ?? '', $activeVar['sub_subcategory_name'] ?? '']));

            // Extract Category hierarchy 3-char prefix (e.g. WMF or NEW)
            $prodPrefix = !empty($activeVar['qr_code_prefix']) ? trim($activeVar['qr_code_prefix']) : '';
            if (empty($prodPrefix) && (!empty($activeVar['category_code']) || !empty($activeVar['subcategory_code']) || !empty($activeVar['sub_subcategory_code']))) {
                $prodPrefix = strtoupper(($activeVar['category_code'] ?? '') . ($activeVar['subcategory_code'] ?? '') . ($activeVar['sub_subcategory_code'] ?? ''));
            }
            if (empty($prodPrefix)) {
                $catParts = array_filter([$activeVar['category_name'] ?? '', $activeVar['subcategory_name'] ?? '', $activeVar['sub_subcategory_name'] ?? '']);
                if (!empty($catParts)) {
                    $abbr = '';
                    foreach ($catParts as $cp) {
                        $words = preg_split('/\s+/', trim($cp));
                        if (!empty($words[0])) { $abbr .= strtoupper($words[0][0]); }
                    }
                    $prodPrefix = substr($abbr, 0, 3);
                }
            }
            if (empty($prodPrefix)) {
                $prodPrefix = 'NEW';
            }
            $prodPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prodPrefix));
            if (strlen($prodPrefix) > 4) {
                $prodPrefix = substr($prodPrefix, 0, 4);
            }

            // 2-digit Month + 2-digit Year (e.g. 1026 for Oct 2026)
            $monthYear = date('my');
            $baseTagPrefix = $prodPrefix . $monthYear; // e.g. NEW1026 or WMF1026

            // Continue the category sequence across products and month changes.
            $catId = (int)($activeVar['warehouse_category_id'] ?? $activeVar['category_id'] ?? 0);
            $subcatId = (int)($activeVar['warehouse_subcategory_id'] ?? $activeVar['subcategory_id'] ?? 0);
            $subSubcatId = (int)($activeVar['warehouse_sub_subcategory_id'] ?? $activeVar['sub_subcategory_id'] ?? 0);
            $prodCreatedAt = $activeVar['product_created_at'] ?? $activeVar['created_at'] ?? date('Y-m-d H:i:s');
            $productId = (int)($activeVar['product_id'] ?? 0);

            $categoryOffset = calculateCategorySequenceOffset(
                (int)$activeVar['id'],
                $prodPrefix,
                $catId,
                $subcatId,
                $subSubcatId,
                $prodCreatedAt,
                $productId
            );

            if (!$isZeroStockRestricted && $effectiveStock > 0) {
                // Derive clean, scannable SKU matching Production Unit reference standard (e.g. S-RED instead of long internal database key)
                $sizeVal = trim($activeVar['size_name'] ?? '');
                $colorVal = trim($activeVar['color_name'] ?? '');
                $sizeColor = trim(($sizeVal !== '' ? $sizeVal : '') . ($colorVal !== '' ? ($sizeVal !== '' ? '-' : '') . $colorVal : ''));
                if (!empty($activeVar['sku']) && strlen($activeVar['sku']) <= 12 && strpos($activeVar['sku'], 'SKU-') !== 0 && strpos($activeVar['sku'], 'CAT-') !== 0 && strpos($activeVar['sku'], 'GRM-STY-') !== 0) {
                    $cleanSku = $activeVar['sku'];
                } elseif (!empty($sizeColor)) {
                    $cleanSku = $sizeColor;
                } elseif (!empty($activeVar['catalog_code'])) {
                    $cleanSku = $activeVar['catalog_code'];
                } else {
                    $cleanSku = !empty($activeVar['sku']) ? $activeVar['sku'] : 'STD';
                }

                if ($isSeriesOrComp) {
                    $packQty = (int)($activeVar['pack_quantity'] ?? 0) > 1
                        ? (int)$activeVar['pack_quantity']
                        : max(1, (int)($totalExpectedQty ?? $activeVar['available_qty'] ?? $copiesCount));

                    // Load all existing QRs for this variant from warehouse_qr_codes
                    $existingQrs = Database::fetchAll(
                        "SELECT id, qr_code, warehouse_id, pack_quantity, series_end_code, inwarded_at, created_at
                         FROM warehouse_qr_codes 
                         WHERE variant_id = ? 
                         ORDER BY id ASC",
                        [(int)$activeVar['id']]
                    );

                    if (!empty($existingQrs) && !empty($deletedWhCodes)) {
                        $activeVarId = (int)$activeVar['id'];
                        $existingQrs = array_values(array_filter($existingQrs, function($eqr) use ($deletedWhCodes, $activeVarId) {
                            return !isWarehouseCodeDeleted((string)$eqr['qr_code'], $activeVarId, $deletedWhCodes);
                        }));
                    }

                    if (!empty($existingQrs)) {
                        foreach ($existingQrs as $idx => $eqr) {
                            $packQ = (int)($eqr['pack_quantity'] ?? $packQty);
                            if ($packQ <= 0) $packQ = $packQty;
                            $startCode = $eqr['qr_code'];
                            $endCode = !empty($eqr['series_end_code']) ? $eqr['series_end_code'] : $startCode;

                            $seqRangeText = '';
                            if (preg_match('/^[A-Za-z]+\d{4}(\d+)$/', $startCode, $mStart) && preg_match('/^[A-Za-z]+\d{4}(\d+)$/', $endCode, $mEnd)) {
                                $seqRangeText = " (" . (int)$mStart[1] . " to " . (int)$mEnd[1] . ")";
                            } elseif (preg_match('/(\d+)$/', $startCode, $mStart) && preg_match('/(\d+)$/', $endCode, $mEnd)) {
                                $seqRangeText = " (" . (int)$mStart[1] . " to " . (int)$mEnd[1] . ")";
                            }

                            $generatedItems[] = [
                                'id'                   => (int)$eqr['id'],
                                'piece_no'             => $idx + 1,
                                'total_count'          => count($existingQrs),
                                'variant_id'           => (int)$activeVar['id'],
                                'warehouse_id'         => (int)($eqr['warehouse_id'] ?? $targetWarehouseId),
                                'qr_prefix'            => $prodPrefix,
                                'tag_code'             => $startCode,
                                'product_name'         => $activeVar['product_name'],
                                'sku'                  => $activeVar['sku'],
                                'clean_sku'            => $cleanSku,
                                'brand_name'           => $brandOrSeller,
                                'size_name'            => $activeVar['size_name'] ?? 'Free',
                                'color_name'           => $activeVar['color_name'] ?? 'Std',
                                'mrp'                  => $mrpVal,
                                'selling_price'        => $sellingVal,
                                'cost_price'           => $costVal,
                                'source_label'         => ($isComp ? 'Master Complementary Pack: ' : 'Master Series Pack: ') . $brandOrSeller . " ({$packQ} Pcs)" . $seqRangeText,
                                'badge_class'          => 'bg-dark text-white',
                                'qr_payload'           => $startCode,
                                'is_series'            => true,
                                'is_complementary'     => $isComp,
                                'series_start_code'    => $startCode,
                                'series_end_code'      => $endCode,
                                'pack_quantity'        => $packQ,
                                'is_inwarded'          => !empty($eqr['inwarded_at']),
                                'is_saved_qr'          => true,
                                'category_name'        => $activeVar['category_name'] ?? '',
                                'subcategory_name'     => $activeVar['subcategory_name'] ?? '',
                                'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
                                'category_hierarchy'   => $catH,
                            ];
                        }
                    } else {
                        // Generate the initial Master QR code
                        $startNum = $categoryOffset + 1;
                        $endNum = $startNum + $packQty - 1;
                        $seqPad = ($endNum > 99) ? 3 : 2;
                        $startCode = $baseTagPrefix . sprintf("%0{$seqPad}d", $startNum);
                        $endCode = $baseTagPrefix . sprintf("%0{$seqPad}d", $endNum);

                        $seqRangeText = " ({$startNum} to {$endNum})";

                        // Reserve in warehouse_qr_codes
                        try {
                            Database::execute(
                                "INSERT INTO warehouse_qr_codes (qr_code, variant_id, warehouse_id, pack_quantity, series_end_code) VALUES (?, ?, ?, ?, ?)",
                                [$startCode, (int)$activeVar['id'], $targetWarehouseId, $packQty, $endCode]
                            );
                            Database::execute(
                                "UPDATE product_variants SET series_start_code = ?, series_end_code = ? WHERE id = ?",
                                [$startCode, $endCode, (int)$activeVar['id']]
                            );
                        } catch (Throwable $ignore) {}

                        $generatedItems[] = [
                            'piece_no'             => 1,
                            'total_count'          => 1,
                            'variant_id'           => (int)$activeVar['id'],
                            'qr_prefix'            => $prodPrefix,
                            'tag_code'             => $startCode,
                            'product_name'         => $activeVar['product_name'],
                            'sku'                  => $activeVar['sku'],
                            'clean_sku'            => $cleanSku,
                            'brand_name'           => $brandOrSeller,
                            'size_name'            => $activeVar['size_name'] ?? 'Free',
                            'color_name'           => $activeVar['color_name'] ?? 'Std',
                            'mrp'                  => $mrpVal,
                            'selling_price'        => $sellingVal,
                            'cost_price'           => $costVal,
                            'source_label'         => ($isComp ? 'Master Complementary Pack: ' : 'Master Series Pack: ') . $brandOrSeller . " ({$packQty} Pcs)" . $seqRangeText,
                            'badge_class'          => 'bg-dark text-white',
                            'qr_payload'           => $startCode,
                            'is_series'            => true,
                            'is_complementary'     => $isComp,
                            'series_start_code'    => $startCode,
                            'series_end_code'      => $endCode,
                            'pack_quantity'        => $packQty,
                            'is_inwarded'          => false,
                            'is_saved_qr'          => true,
                            'category_name'        => $activeVar['category_name'] ?? '',
                            'subcategory_name'     => $activeVar['subcategory_name'] ?? '',
                            'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
                            'category_hierarchy'   => $catH,
                        ];
                    }
                } elseif ($qrAdjustmentType === 'PRODUCE_NEW') {
                    // Produce 1 New QR Sticker for Stock Adjustment (Next sequential code)
                    $nextSeq = $categoryOffset + $effectiveStock + 1;
                    $seqPad = ($nextSeq > 99) ? 3 : 2;
                    $newTagCode = $baseTagPrefix . sprintf("%0{$seqPad}d", $nextSeq);
                    $qrPayload = $newTagCode;
                    $copiesCount = 1;

                    $generatedItems[] = [
                        'piece_no'             => $nextSeq,
                        'total_count'          => $nextSeq,
                        'variant_id'           => (int)$activeVar['id'],
                        'qr_prefix'            => $prodPrefix,
                        'tag_code'             => $newTagCode,
                        'product_name'         => $activeVar['product_name'],
                        'sku'                  => $activeVar['sku'],
                        'clean_sku'            => $cleanSku,
                        'brand_name'           => $brandOrSeller,
                        'size_name'            => $activeVar['size_name'] ?? 'Free',
                        'color_name'           => $activeVar['color_name'] ?? 'Std',
                        'mrp'                  => $mrpVal,
                        'selling_price'        => $sellingVal,
                        'cost_price'           => $costVal,
                        'source_label'         => 'Stock Adjustment: New QR Piece (#' . $nextSeq . ')',
                        'badge_class'          => 'bg-warning text-dark',
                        'qr_payload'           => $qrPayload,
                        'is_series'            => false,
                        'is_adjustment'        => true,
                        'adjustment_type'      => 'PRODUCE_NEW',
                        'category_name'        => $activeVar['category_name'] ?? '',
                        'subcategory_name'     => $activeVar['subcategory_name'] ?? '',
                        'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
                        'category_hierarchy'   => $catH,
                    ];
                } else {
                    // Individual Piece QR Stickers (Category Prefix + Month/Year + Sequence Number)
                    if (isset($_GET['copies']) && (int)$_GET['copies'] > 0) {
                        $copiesCount = min($effectiveStock, (int)$_GET['copies']);
                    } else {
                        $copiesCount = $effectiveStock > 0 ? $effectiveStock : 1;
                    }

                    $maxPossibleSeq = $categoryOffset + $totalExpectedQty;
                    $seqPad = ($maxPossibleSeq > 99) ? 3 : 2;
                    if ($isFullyInwarded) {
                        $startSeq = $categoryOffset + 1;
                        $endSeq = $categoryOffset + $totalExpectedQty;
                    } else {
                        $startSeq = $categoryOffset + $alreadyInwardedQty + 1;
                        $endSeq = $categoryOffset + $alreadyInwardedQty + $copiesCount;
                    }

                    for ($i = $startSeq; $i <= $endSeq; $i++) {
                        $tagCode = $baseTagPrefix . sprintf("%0{$seqPad}d", $i);
                        if (isWarehouseCodeDeleted($tagCode, (int)$activeVar['id'], $deletedWhCodes)) {
                            continue;
                        }
                        $generatedItems[] = [
                            'piece_no'             => $i,
                            'total_count'          => $categoryOffset + $totalExpectedQty,
                            'variant_id'           => (int)$activeVar['id'],
                            'qr_prefix'            => $prodPrefix,
                            'tag_code'             => $tagCode,
                            'product_name'         => $activeVar['product_name'],
                            'sku'                  => $activeVar['sku'],
                            'clean_sku'            => $cleanSku,
                            'brand_name'           => $brandOrSeller,
                            'size_name'            => $activeVar['size_name'] ?? 'Free',
                            'color_name'           => $activeVar['color_name'] ?? 'Std',
                            'mrp'                  => $mrpVal,
                            'selling_price'        => $sellingVal,
                            'cost_price'           => $costVal,
                            'source_label'         => 'Third-Party: ' . $brandOrSeller . ($isPartiallyInwarded ? " (Remaining {$remainingQty} of {$totalExpectedQty} Pcs)" : (!empty($poNumber) ? " (PO #{$poNumber})" : '')),
                            'badge_class'          => 'bg-warning text-dark',
                            'qr_payload'           => $tagCode,
                            'is_series'            => false,
                            'is_remaining'         => $isPartiallyInwarded,
                            'category_name'        => $activeVar['category_name'] ?? '',
                            'subcategory_name'     => $activeVar['subcategory_name'] ?? '',
                            'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
                            'category_hierarchy'   => $catH,
                        ];
                    }
                }
            } else {
                $generatedItems = [];
                $copiesCount = 0;
            }
        }
    } elseif ($selectedSourceId) {
        // Priority 2: Only PO selected, generate for all items in that PO
        $poItems = PurchaseItem::getByPurchaseOrder($selectedSourceId);
        $poInfo = Database::fetchOne("SELECT po.*, s.name as seller_name, s.company as seller_company, w.name as warehouse_name FROM purchase_orders po LEFT JOIN warehouse_suppliers s ON (po.warehouse_supplier_id = s.id OR po.seller_id = s.id) LEFT JOIN warehouses w ON po.warehouse_id = w.id WHERE po.id = ?", [$selectedSourceId]);
        $sellerLabel = !empty($poInfo['seller_company']) ? $poInfo['seller_company'] : ($poInfo['seller_name'] ?? 'Third-Party Seller');
        $sequenceByPrefix = [];
        foreach ($poItems as $pi) {
            $count = max(1, (int)$pi['quantity']);
            $prodPrefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $pi['product_name']), 0, 3)) ?: 'TP';
            if (!isset($sequenceByPrefix[$prodPrefix])) {
                $sequenceByPrefix[$prodPrefix] = getWarehouseQrSequenceOffset($prodPrefix) + 1;
            }
            for ($i = 1; $i <= $count; $i++) {
                $tagCode = $prodPrefix . $monthStr . $yearStr . sprintf("%03d", $sequenceByPrefix[$prodPrefix]++);
                $fmtMrp = ($pi['mrp'] == (int)$pi['mrp']) ? (int)$pi['mrp'] : number_format($pi['mrp'], 2, '.', '');
                $fmtSell = !empty($pi['selling_price']) ? (($pi['selling_price'] == (int)$pi['selling_price']) ? (int)$pi['selling_price'] : number_format($pi['selling_price'], 2, '.', '')) : $fmtMrp;
                $catH = implode(' › ', array_filter([$pi['category_name'] ?? '', $pi['subcategory_name'] ?? '', $pi['sub_subcategory_name'] ?? '']));

                $sizeVal = trim($pi['size_name'] ?? '');
                $colorVal = trim($pi['color_name'] ?? '');
                $sizeColor = trim(($sizeVal !== '' ? $sizeVal : '') . ($colorVal !== '' ? ($sizeVal !== '' ? '-' : '') . $colorVal : ''));
                if (!empty($pi['sku']) && strlen($pi['sku']) <= 12 && strpos($pi['sku'], 'SKU-') !== 0 && strpos($pi['sku'], 'CAT-') !== 0) {
                    $cleanSku = $pi['sku'];
                } elseif (!empty($sizeColor)) {
                    $cleanSku = $sizeColor;
                } else {
                    $cleanSku = !empty($pi['sku']) ? $pi['sku'] : 'STD';
                }

                $generatedItems[] = [
                    'piece_no'             => $i,
                    'total_count'          => $count,
                    'variant_id'           => (int)($pi['variant_id'] ?? 0),
                    'qr_prefix'            => $prodPrefix,
                    'tag_code'             => $tagCode,
                    'product_name'         => $pi['product_name'],
                    'sku'                  => $pi['sku'],
                    'clean_sku'            => $cleanSku,
                    'brand_name'           => $sellerLabel,
                    'size_name'            => $pi['size_name'] ?? 'Free',
                    'color_name'           => $pi['color_name'] ?? 'Std',
                    'mrp'                  => (float)$pi['mrp'],
                    'selling_price'        => (float)($pi['selling_price'] ?? $pi['unit_price']),
                    'cost_price'           => (float)$pi['unit_price'],
                    'source_label'         => 'Third-Party: ' . $sellerLabel . (!empty($poInfo['po_number']) ? " (PO #{$poInfo['po_number']})" : ''),
                    'badge_class'          => 'bg-warning text-dark',
                    'qr_payload'           => $tagCode,
                    'category_name'        => $pi['category_name'] ?? '',
                    'subcategory_name'     => $pi['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $pi['sub_subcategory_name'] ?? '',
                    'category_hierarchy'   => $catH,
                ];
            }
        }
    }
} elseif ($currentSourceType === 'returns') {
    if ($selectedVariantId) {
        $activeVar = null;
        foreach ($stockVariants as $sv) {
            if ($sv['id'] == $selectedVariantId) {
                $activeVar = $sv;
                break;
            }
        }
        if (!$activeVar) {
            $activeVar = Database::fetchOne(
                "SELECT pv.*, p.name as product_name, p.code as product_code, p.qr_code_prefix,
                        p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.category_id, p.subcategory_id, p.sub_subcategory_id,
                        p.brand_id, COALESCE(b.name, 'GRM RETURN') as brand_name,
                        p.source_type, p.warehouse_supplier_id as seller_id, sz.name as size_name, c.name as color_name,
                        COALESCE(cat.name, c_old.name, '') as category_name, 
                        COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                        COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN brands b ON p.brand_id = b.id
                 LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
                 LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
                 LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
                 LEFT JOIN categories c_old ON p.category_id = c_old.id
                 LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
                 LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 WHERE pv.id = ?",
                [$selectedVariantId]
            );
        }

        if ($activeVar) {
            $sourceName = 'Customer Return';
            $returnRef = 'RET-' . date('ymd');
            $returnQrReason = null;
            $isReturnComp = !empty($activeVar['is_complementary'])
                || !empty($activeVar['product_is_comp'])
                || !empty($selectedPurchaseReturnItem['is_complementary'])
                || strtoupper((string)($activeVar['warehouse_category_type'] ?? '')) === 'COMPLEMENTARY';
            $isReturnSeriesOrComp = $isReturnComp
                || strtoupper((string)($activeVar['qr_type'] ?? '')) === 'SERIES_BATCH'
                || strtoupper((string)($activeVar['qr_type'] ?? '')) === 'SERIES'
                || strtoupper((string)($activeVar['warehouse_category_type'] ?? '')) === 'SERIES'
                || (int)($activeVar['pack_quantity'] ?? 1) > 1;
            if ($isDirectPurchaseReturn && !empty($selectedPurchaseReturnItem)) {
                $sourceName = 'Direct Purchase ' . ($selectedPurchaseReturnItem['return_number'] ?? 'Return');
                $returnRef = (string)($selectedPurchaseReturnItem['return_number'] ?? $returnRef);
                $returnQrReason = 'Direct purchase return item ' . (int)$selectedPurchaseReturnItem['id'];
                $tagType = 'GOOD';
                $isReturnSeriesOrComp = $isReturnSeriesOrComp
                    || strtoupper((string)($selectedPurchaseReturnItem['warehouse_category_type'] ?? '')) === 'COMPLEMENTARY'
                    || strtoupper((string)($selectedPurchaseReturnItem['warehouse_category_type'] ?? '')) === 'SERIES';
                $isReturnComp = $isReturnComp
                    || strtoupper((string)($selectedPurchaseReturnItem['warehouse_category_type'] ?? '')) === 'COMPLEMENTARY';
                $copiesCount = $isReturnSeriesOrComp
                    ? 1
                    : max(1, (int)$selectedPurchaseReturnItem['quantity']);
            } elseif ($selectedSourceId) {
                $selectedWarehouseReturnInfo = Database::fetchOne("SELECT wr.*, COALESCE(s.name, wr.source_type) as source_name FROM warehouse_returns wr LEFT JOIN shops s ON wr.source_type = 'SHOP' AND wr.source_id = s.id WHERE wr.id = ?", [$selectedSourceId]);
                if ($selectedWarehouseReturnInfo) {
                    if (!empty($selectedWarehouseReturnInfo['source_name'])) $sourceName = $selectedWarehouseReturnInfo['source_name'];
                    if (!empty($selectedWarehouseReturnInfo['return_number'])) $returnRef = $selectedWarehouseReturnInfo['return_number'];
                    $targetWarehouseId = (int)($selectedWarehouseReturnInfo['warehouse_id'] ?? $targetWarehouseId);
                }
            }

            $returnTagCodes = $selectedWarehouseReturnItem
                ? array_map($returnTagPayload, WarehouseReturnItem::parseScannedQrCodes($selectedWarehouseReturnItem['scanned_qr_codes'] ?? null))
                : [];
            if ($selectedWarehouseReturnItem) {
                $returnQrReason = 'Warehouse return item ' . (int)$selectedWarehouseReturnItem['id'];
                $isReturnSeriesOrComp = strtoupper((string)($activeVar['qr_type'] ?? '')) === 'SERIES_BATCH'
                    || strtoupper((string)($activeVar['qr_type'] ?? '')) === 'SERIES'
                    || !empty($activeVar['is_complementary'])
                    || !empty($activeVar['product_is_comp'])
                    || strtoupper((string)($activeVar['warehouse_category_type'] ?? '')) === 'SERIES'
                    || strtoupper((string)($activeVar['warehouse_category_type'] ?? '')) === 'COMPLEMENTARY'
                    || (int)($activeVar['pack_quantity'] ?? 1) > 1
                    || !empty($selectedWarehouseReturnItem['is_complementary']);
                $isReturnComp = !empty($activeVar['is_complementary'])
                    || !empty($activeVar['product_is_comp'])
                    || strtoupper((string)($activeVar['warehouse_category_type'] ?? '')) === 'COMPLEMENTARY'
                    || !empty($selectedWarehouseReturnItem['is_complementary']);
                if ($isReturnSeriesOrComp) {
                    $returnTagCodes = [];
                }
                $copiesCount = $isReturnSeriesOrComp
                    ? 1
                    : max(1, (int)$selectedWarehouseReturnItem['returned_qty'], count($returnTagCodes));
            }

            $c1 = !empty($activeVar['category_name']) ? trim($activeVar['category_name']) : (!empty($activeVar['category_code']) ? trim($activeVar['category_code']) : '');
            $c2 = !empty($activeVar['subcategory_name']) ? trim($activeVar['subcategory_name']) : (!empty($activeVar['subcategory_code']) ? trim($activeVar['subcategory_code']) : '');
            $c3 = !empty($activeVar['sub_subcategory_name']) ? trim($activeVar['sub_subcategory_name']) : (!empty($activeVar['sub_subcategory_code']) ? trim($activeVar['sub_subcategory_code']) : '');

            $prodPrefix = '';
            if ($c1 !== '') $prodPrefix .= strtoupper($c1[0]);
            if ($c2 !== '') $prodPrefix .= strtoupper($c2[0]);
            if ($c3 !== '') $prodPrefix .= strtoupper($c3[0]);
            if (strlen($prodPrefix) < 2) $prodPrefix = 'TTS';

            // Return QR keeps the complete category hierarchy prefix after R.
            // Example: T + A + M becomes RTAM102601, not RAM102601.
            $retTagPrefix = 'R' . $prodPrefix;
            $sequenceByPrefix = getWarehouseQrSequenceOffset($retTagPrefix) + 1;
            $badgeClass = $tagType === 'DAMAGED' ? 'bg-danger' : ($tagType === 'DEFECTIVE' ? 'bg-warning text-dark' : ($tagType === 'WRONG_SIZE' ? 'bg-info text-dark' : 'bg-success'));
            $fmtMrp = ($activeVar['mrp'] == (int)$activeVar['mrp']) ? (int)$activeVar['mrp'] : number_format($activeVar['mrp'], 2, '.', '');
            $returnBrand = !empty($selectedCustomBrand) ? $selectedCustomBrand : (!empty($activeVar['brand_name']) && $activeVar['brand_name'] !== 'Third-Party Brand' ? $activeVar['brand_name'] : 'GRM RETURN');
            $pendingAggregateReturnQr = null;
            if ($isReturnSeriesOrComp && $returnQrReason !== null) {
                $pendingAggregateReturnQr = Database::fetchOne(
                    "SELECT qr_code FROM warehouse_qr_codes
                     WHERE extra_reason = ? AND variant_id = ? AND warehouse_id = ?
                       AND inwarded_at IS NULL AND UPPER(qr_code) LIKE 'R%'
                     ORDER BY id DESC LIMIT 1",
                    [$returnQrReason, (int)$activeVar['id'], (int)$targetWarehouseId]
                );
            }

            $sizeVal = trim($activeVar['size_name'] ?? '');
            $colorVal = trim($activeVar['color_name'] ?? '');
            $sizeColor = trim(($sizeVal !== '' ? $sizeVal : '') . ($colorVal !== '' ? ($sizeVal !== '' ? '-' : '') . $colorVal : ''));
            if (!empty($activeVar['sku']) && strlen($activeVar['sku']) <= 12 && strpos($activeVar['sku'], 'SKU-') !== 0) {
                $cleanSku = $activeVar['sku'];
            } elseif (!empty($sizeColor)) {
                $cleanSku = $sizeColor;
            } else {
                $cleanSku = !empty($activeVar['sku']) ? $activeVar['sku'] : 'STD';
            }
            $fmtCost = ($activeVar['selling_price'] == (int)$activeVar['selling_price']) ? (int)$activeVar['selling_price'] : number_format($activeVar['selling_price'], 2, '.', '');

            for ($i = 1; $i <= $copiesCount; $i++) {
                $isAggregateReturnQr = $isReturnSeriesOrComp;
                $hasScannedReturnTag = !$isAggregateReturnQr && isset($returnTagCodes[$i - 1]);
                $tagCode = $isAggregateReturnQr && !empty($pendingAggregateReturnQr['qr_code'])
                    ? (string)$pendingAggregateReturnQr['qr_code']
                    : ($hasScannedReturnTag
                        ? $returnTagCodes[$i - 1]
                        : $retTagPrefix . $monthStr . $yearStr . sprintf("%02d", $sequenceByPrefix++));
                $catH = implode(' › ', array_filter([$activeVar['category_name'] ?? '', $activeVar['subcategory_name'] ?? '', $activeVar['sub_subcategory_name'] ?? '']));
                $returnPackQuantity = $isDirectPurchaseReturn && !empty($selectedPurchaseReturnItem)
                    ? (int)$selectedPurchaseReturnItem['quantity']
                    : (int)($selectedWarehouseReturnItem['returned_qty'] ?? 1);
                $generatedItems[] = [
                    'piece_no'           => $i,
                    'total_count'        => $copiesCount,
                    'variant_id'         => (int)$activeVar['id'],
                    'qr_prefix'          => $retTagPrefix,
                    'tag_code'           => $tagCode,
                    'is_exact_return_tag' => $hasScannedReturnTag,
                    'is_return_aggregate_qr' => $isAggregateReturnQr,
                    'return_qr_reason'  => $isAggregateReturnQr ? $returnQrReason : null,
                    'is_series'          => $isAggregateReturnQr,
                    'is_complementary'   => $isAggregateReturnQr && $isReturnComp,
                    'pack_quantity'      => $isAggregateReturnQr ? max(1, $returnPackQuantity) : 1,
                    'series_start_code'  => $isAggregateReturnQr ? $tagCode : null,
                    'series_end_code'    => $isAggregateReturnQr ? $tagCode : null,
                    'product_name'       => $activeVar['product_name'],
                    'sku'                => $activeVar['sku'],
                    'clean_sku'          => $cleanSku,
                    'brand_name'         => $returnBrand,
                    'size_name'          => $activeVar['size_name'] ?? 'Free',
                    'color_name'         => $activeVar['color_name'] ?? 'Std',
                    'mrp'                => (float)$activeVar['mrp'],
                    'selling_price'      => (float)$activeVar['selling_price'],
                    'cost_price'         => (float)$activeVar['selling_price'],
                    'source_label'       => 'Return (' . $sourceName . '): ' . $tagType . ' • ' . $returnBrand,
                    'badge_class'        => $badgeClass,
                    'qr_payload'         => $tagCode,
                    'category_name'      => $activeVar['category_name'] ?? '',
                    'subcategory_name'   => $activeVar['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                ];
            }
        }
    } elseif ($selectedSourceId) {
        $retItems = $warehouseReturnItems;
        $selectedWarehouseReturnInfo = Database::fetchOne("SELECT wr.*, COALESCE(s.name, wr.source_type) as source_name FROM warehouse_returns wr LEFT JOIN shops s ON wr.source_type = 'SHOP' AND wr.source_id = s.id WHERE wr.id = ?", [$selectedSourceId]);
        $sourceName = !empty($selectedWarehouseReturnInfo['source_name']) ? $selectedWarehouseReturnInfo['source_name'] : 'Customer Return';
        $returnRef = !empty($selectedWarehouseReturnInfo['return_number']) ? $selectedWarehouseReturnInfo['return_number'] : ('RET-' . date('ymd'));
        $targetWarehouseId = (int)($selectedWarehouseReturnInfo['warehouse_id'] ?? $targetWarehouseId);
        $sequenceByPrefix = [];

        foreach ($retItems as $ri) {
            $c1 = !empty($ri['category_name']) ? trim($ri['category_name']) : (!empty($ri['category_code']) ? trim($ri['category_code']) : '');
            $c2 = !empty($ri['subcategory_name']) ? trim($ri['subcategory_name']) : (!empty($ri['subcategory_code']) ? trim($ri['subcategory_code']) : '');
            $c3 = !empty($ri['sub_subcategory_name']) ? trim($ri['sub_subcategory_name']) : (!empty($ri['sub_subcategory_code']) ? trim($ri['sub_subcategory_code']) : '');

            $prodPrefix = '';
            if ($c1 !== '') $prodPrefix .= strtoupper($c1[0]);
            if ($c2 !== '') $prodPrefix .= strtoupper($c2[0]);
            if ($c3 !== '') $prodPrefix .= strtoupper($c3[0]);
            if (strlen($prodPrefix) < 2) $prodPrefix = 'TTS';

            // Return QR keeps the complete category hierarchy prefix after R.
            $retTagPrefix = 'R' . $prodPrefix;
            if (!isset($sequenceByPrefix[$retTagPrefix])) {
                $sequenceByPrefix[$retTagPrefix] = getWarehouseQrSequenceOffset($retTagPrefix) + 1;
            }
            $itemIsComp = !empty($ri['is_complementary']) || strtoupper((string)($ri['warehouse_category_type'] ?? '')) === 'COMPLEMENTARY';
            $itemIsSeries = !$itemIsComp && (strtoupper((string)($ri['warehouse_category_type'] ?? '')) === 'SERIES'
                || strtoupper((string)($ri['qr_type'] ?? '')) === 'SERIES_BATCH'
                || strtoupper((string)($ri['qr_type'] ?? '')) === 'SERIES'
                || (int)($ri['pack_quantity'] ?? 1) > 1);
            $isAggregateReturnQr = $itemIsComp || $itemIsSeries;
            $count = ($itemIsComp || $itemIsSeries) ? 1 : max(1, (int)$ri['returned_qty']);
            $class = !empty($ri['classification']) ? $ri['classification'] : $tagType;
            $returnBrand = !empty($selectedCustomBrand) ? $selectedCustomBrand : (!empty($ri['brand_name']) && $ri['brand_name'] !== 'Third-Party Brand' ? $ri['brand_name'] : 'GRM RETURN');
            $returnTagCodes = array_map($returnTagPayload, WarehouseReturnItem::parseScannedQrCodes($ri['scanned_qr_codes'] ?? null));
            if (!($itemIsComp || $itemIsSeries)) {
                $count = max($count, count($returnTagCodes));
            } else {
                $returnTagCodes = [];
            }
            $returnQrReason = $isAggregateReturnQr
                ? 'Warehouse return item ' . (int)$ri['id']
                : null;
            $pendingAggregateReturnQr = null;
            if ($returnQrReason !== null) {
                $pendingAggregateReturnQr = Database::fetchOne(
                    "SELECT qr_code FROM warehouse_qr_codes
                     WHERE extra_reason = ? AND variant_id = ? AND warehouse_id = ?
                       AND inwarded_at IS NULL AND UPPER(qr_code) LIKE 'R%'
                     ORDER BY id DESC LIMIT 1",
                    [$returnQrReason, (int)$ri['variant_id'], (int)$targetWarehouseId]
                );
            }

            $sizeVal = trim($ri['size_name'] ?? '');
            $colorVal = trim($ri['color_name'] ?? '');
            $sizeColor = trim(($sizeVal !== '' ? $sizeVal : '') . ($colorVal !== '' ? ($sizeVal !== '' ? '-' : '') . $colorVal : ''));
            if (!empty($ri['sku']) && strlen($ri['sku']) <= 12 && strpos($ri['sku'], 'SKU-') !== 0) {
                $cleanSku = $ri['sku'];
            } elseif (!empty($sizeColor)) {
                $cleanSku = $sizeColor;
            } else {
                $cleanSku = !empty($ri['sku']) ? $ri['sku'] : 'STD';
            }

            for ($i = 1; $i <= $count; $i++) {
                $isExactReturnTag = !$isAggregateReturnQr && isset($returnTagCodes[$i - 1]);
                $tagCode = $isAggregateReturnQr && !empty($pendingAggregateReturnQr['qr_code'])
                    ? (string)$pendingAggregateReturnQr['qr_code']
                    : ($isExactReturnTag
                        ? $returnTagCodes[$i - 1]
                        : $retTagPrefix . $monthStr . $yearStr . sprintf("%02d", $sequenceByPrefix[$retTagPrefix]++));
                $fmtMrp = ($ri['mrp'] == (int)$ri['mrp']) ? (int)$ri['mrp'] : number_format($ri['mrp'], 2, '.', '');
                $fmtCost = ($ri['selling_price'] == (int)$ri['selling_price']) ? (int)$ri['selling_price'] : number_format($ri['selling_price'], 2, '.', '');
                $badgeClass = $class === 'DAMAGED' ? 'bg-danger' : ($class === 'DEFECTIVE' ? 'bg-warning text-dark' : ($class === 'WRONG_SIZE' ? 'bg-info text-dark' : 'bg-success'));
                $catH = implode(' › ', array_filter([$ri['category_name'] ?? '', $ri['subcategory_name'] ?? '', $ri['sub_subcategory_name'] ?? '']));
                $generatedItems[] = [
                    'piece_no'           => $i,
                    'total_count'        => $count,
                    'variant_id'         => (int)($ri['variant_id'] ?? 0),
                    'qr_prefix'          => $retTagPrefix,
                    'tag_code'           => $tagCode,
                    'is_exact_return_tag' => $isExactReturnTag,
                    'is_return_aggregate_qr' => $isAggregateReturnQr,
                    'return_qr_reason'  => $returnQrReason,
                    'is_series'          => $isAggregateReturnQr,
                    'is_complementary'   => $itemIsComp,
                    'pack_quantity'      => $isAggregateReturnQr ? max(1, (int)$ri['returned_qty']) : 1,
                    'series_start_code'  => $isAggregateReturnQr ? $tagCode : null,
                    'series_end_code'    => $isAggregateReturnQr ? $tagCode : null,
                    'product_name'       => $ri['product_name'],
                    'sku'                => $ri['sku'],
                    'clean_sku'          => $cleanSku,
                    'brand_name'         => $returnBrand,
                    'size_name'          => $ri['size_name'] ?? 'Free',
                    'color_name'         => $ri['color_name'] ?? 'Std',
                    'mrp'                => (float)$ri['mrp'],
                    'selling_price'      => (float)$ri['selling_price'],
                    'cost_price'         => (float)$ri['selling_price'],
                    'source_label'       => 'Return (' . $sourceName . '): ' . $class . ' • ' . $returnBrand,
                    'badge_class'        => $badgeClass,
                    'qr_payload'         => $tagCode,
                    'category_name'      => $ri['category_name'] ?? '',
                    'subcategory_name'   => $ri['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $ri['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                ];
            }
        }
    }
}

if ($isViewingExisting && $selectedVariantId && $activeVar) {
    $activeCategoryType = strtoupper($activeVar['warehouse_category_type'] ?? '');
    $isComp = !empty($activeVar['is_complementary']) || !empty($activeVar['product_is_comp']) || $activeCategoryType === 'COMPLEMENTARY';
    $isSeries = strtoupper($activeVar['qr_type'] ?? '') === 'SERIES_BATCH'
        || strtoupper($activeVar['qr_type'] ?? '') === 'SERIES'
        || $activeCategoryType === 'SERIES';
    $existingQrCodes = Database::fetchAll(
        "SELECT id, qr_code, warehouse_id, series_end_code, pack_quantity, inwarded_at, created_at, is_extra, extra_reason
         FROM warehouse_qr_codes
         WHERE variant_id = ?
         ORDER BY id ASC",
        [(int)$selectedVariantId]
    );

    if (!empty($existingQrCodes) && !empty($deletedWhCodes)) {
        $existingQrCodes = array_values(array_filter($existingQrCodes, function($eqr) use ($deletedWhCodes, $selectedVariantId) {
            return !isWarehouseCodeDeleted((string)$eqr['qr_code'], (int)$selectedVariantId, $deletedWhCodes);
        }));
    }

    $generatedItems = [];

    $brandOrSeller = !empty($activeVar['brand_name']) && $activeVar['brand_name'] !== 'Third-Party Brand'
        ? $activeVar['brand_name']
        : (!empty($activeVar['seller_company']) ? $activeVar['seller_company'] : (!empty($activeVar['seller_name']) ? $activeVar['seller_name'] : 'GRM CLASSIC'));
    $sizeVal = trim($activeVar['size_name'] ?? '');
    $colorVal = trim($activeVar['color_name'] ?? '');
    $cleanSku = !empty($sizeVal) && !empty($colorVal)
        ? $sizeVal . '-' . $colorVal
        : (!empty($activeVar['sku']) ? $activeVar['sku'] : 'STD');
    $existingCount = count($existingQrCodes);

    foreach ($existingQrCodes as $index => $qrCode) {
        $tagCode = (string)$qrCode['qr_code'];
        $endCode = !empty($qrCode['series_end_code']) ? (string)$qrCode['series_end_code'] : $tagCode;
        $packQ = (int)($qrCode['pack_quantity'] ?? $activeVar['pack_quantity'] ?? 1);
        if ($packQ <= 0) $packQ = 1;

        $seqRangeText = '';
        if (preg_match('/^[A-Za-z]+\d{4}(\d+)$/', $tagCode, $mStart) && preg_match('/^[A-Za-z]+\d{4}(\d+)$/', $endCode, $mEnd)) {
            $seqRangeText = " (" . (int)$mStart[1] . " to " . (int)$mEnd[1] . ")";
        } elseif (preg_match('/(\d+)$/', $tagCode, $mStart) && preg_match('/(\d+)$/', $endCode, $mEnd)) {
            $seqRangeText = " (" . (int)$mStart[1] . " to " . (int)$mEnd[1] . ")";
        }

        $sourceLabel = ($isSeries || $isComp)
            ? (($isComp ? 'Master Complementary Pack: ' : 'Master Series Pack: ') . $brandOrSeller . " ({$packQ} Pcs)" . $seqRangeText)
            : 'Previously generated supplier QR label';

        $generatedItems[] = [
            'id'                   => (int)$qrCode['id'],
            'piece_no'             => $index + 1,
            'total_count'          => $existingCount,
            'variant_id'           => (int)$selectedVariantId,
            'warehouse_id'         => (int)($qrCode['warehouse_id'] ?? $targetWarehouseId),
            'qr_prefix'            => '',
            'tag_code'             => $tagCode,
            'product_name'         => $activeVar['product_name'] ?? '',
            'sku'                  => $activeVar['sku'] ?? '',
            'clean_sku'            => $cleanSku,
            'brand_name'           => $brandOrSeller,
            'size_name'            => $activeVar['size_name'] ?? 'Free',
            'color_name'           => $activeVar['color_name'] ?? 'Std',
            'mrp'                  => (float)($activeVar['mrp'] ?? 0),
            'selling_price'        => (float)($activeVar['selling_price'] ?? 0),
            'cost_price'           => (float)($activeVar['purchase_price'] ?? 0),
            'source_label'         => $sourceLabel,
            'badge_class'          => ($isSeries || $isComp) ? 'bg-dark text-white' : 'bg-success',
            'qr_payload'           => $tagCode,
            'is_series'            => $isSeries || $isComp,
            'is_complementary'     => $isComp,
            'series_start_code'    => $tagCode,
            'series_end_code'      => $endCode,
            'pack_quantity'        => $packQ,
            'is_inwarded'          => !empty($qrCode['inwarded_at']),
            'is_saved_qr'          => true,
            'category_name'        => $activeVar['category_name'] ?? '',
            'subcategory_name'     => $activeVar['subcategory_name'] ?? '',
            'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
            'category_hierarchy'   => implode(' › ', array_filter([
                $activeVar['category_name'] ?? '',
                $activeVar['subcategory_name'] ?? '',
                $activeVar['sub_subcategory_name'] ?? '',
            ])),
        ];
    }
}

if (!empty($generatedItems) && !$isViewingExisting) {
    Database::beginTransaction();
    try {
        $nextSequenceByPrefix = [];
        $availableQrCodesByVariant = [];
        $requestedQrCountByVariant = [];
        $exactQrCodesByVariant = [];
        $reservedExactQrCodes = [];
        foreach ($generatedItems as $item) {
            $itemVariantId = (int)($item['variant_id'] ?? 0);
            if (!empty($item['is_exact_return_tag']) || !empty($item['is_return_aggregate_qr'])) {
                $exactQrCodesByVariant[$itemVariantId][] = (string)$item['tag_code'];
            } elseif (empty($item['is_series'])) {
                $requestedQrCountByVariant[$itemVariantId] = ($requestedQrCountByVariant[$itemVariantId] ?? 0) + 1;
            }
        }

        foreach ($generatedItems as &$generatedItem) {
            $variantId = (int)($generatedItem['variant_id'] ?? 0);
            $qrPrefix = (string)($generatedItem['qr_prefix'] ?? '');
            $isReservedReturnQr = !empty($generatedItem['is_exact_return_tag']) || !empty($generatedItem['is_return_aggregate_qr']);
            $tagCode = $isReservedReturnQr
                ? trim((string)($generatedItem['tag_code'] ?? ''))
                : strtoupper((string)($generatedItem['tag_code'] ?? ''));
            if ($variantId <= 0 || $tagCode === '' || (!$isReservedReturnQr
                && ($qrPrefix === '' || !preg_match('/^' . preg_quote($qrPrefix, '/') . '\d{4}(\d+)$/i', $tagCode, $sequenceMatch)))) {
                throw new RuntimeException('Unable to reserve a generated warehouse QR code because its product or sequence is invalid.');
            }

            if ($isReservedReturnQr) {
                $exactTagKey = strtoupper($tagCode);
                if (isset($reservedExactQrCodes[$exactTagKey])) {
                    throw new RuntimeException("Return QR {$tagCode} is assigned to more than one returned product.");
                }
                $reservedExactQrCodes[$exactTagKey] = true;
                if (strlen($tagCode) > 100 || !preg_match('/^[\p{L}\p{N}_-]+$/u', $tagCode)) {
                    throw new RuntimeException("Return QR {$tagCode} contains characters that cannot be used for a warehouse label.");
                }
                if (isWarehouseCodeDeleted($tagCode, $variantId, $deletedWhCodes)) {
                    throw new RuntimeException("Return QR {$tagCode} has been deleted from the warehouse QR registry and cannot be reused.");
                }
                $existingQr = Database::fetchOne(
                    "SELECT id, variant_id, warehouse_id, inwarded_at, pack_quantity
                     FROM warehouse_qr_codes WHERE qr_code = ? FOR UPDATE",
                    [$tagCode]
                );
                if ($existingQr) {
                    if ((int)$existingQr['variant_id'] !== $variantId || (int)$existingQr['warehouse_id'] !== $targetWarehouseId) {
                        throw new RuntimeException("Return QR {$tagCode} is already assigned to a different product or warehouse.");
                    }
                    if (!empty($generatedItem['is_return_aggregate_qr'])) {
                        if (!empty($existingQr['inwarded_at'])) {
                            throw new RuntimeException("Return QR {$tagCode} has already been inwarded.");
                        }
                        Database::execute(
                            "UPDATE warehouse_qr_codes SET pack_quantity = ?, series_end_code = ? WHERE id = ?",
                            [
                                max(1, (int)$generatedItem['pack_quantity']),
                                $generatedItem['series_end_code'] ?? $tagCode,
                                (int)$existingQr['id'],
                            ]
                        );
                    }
                } else {
                    if (Database::fetchOne("SELECT id FROM product_variants WHERE qr_code = ? LIMIT 1", [$tagCode])) {
                        throw new RuntimeException("Return QR {$tagCode} is already assigned to a product variant.");
                    }
                    if (!empty($generatedItem['is_return_aggregate_qr'])) {
                        Database::execute(
                            "INSERT INTO warehouse_qr_codes
                                (qr_code, variant_id, warehouse_id, pack_quantity, series_end_code, extra_reason, inwarded_at)
                             VALUES (?, ?, ?, ?, ?, ?, NULL)",
                            [
                                $tagCode,
                                $variantId,
                                $targetWarehouseId,
                                max(1, (int)$generatedItem['pack_quantity']),
                                $generatedItem['series_end_code'] ?? $tagCode,
                                $generatedItem['return_qr_reason'] ?? 'Customer return aggregate QR',
                            ]
                        );
                    } else {
                        Database::execute(
                            "INSERT INTO warehouse_qr_codes (qr_code, variant_id, warehouse_id, inwarded_at) VALUES (?, ?, ?, ?)",
                            [$tagCode, $variantId, $targetWarehouseId, null]
                        );
                    }
                }
                $generatedItem['tag_code'] = $tagCode;
                $generatedItem['qr_payload'] = $tagCode;
                continue;
            }

            if (!empty($generatedItem['is_series'])) {
                // Series or Complementary items are already loaded from warehouse_qr_codes or reserved
                continue;
            }

            if (!array_key_exists($variantId, $availableQrCodesByVariant)) {
                $returnQrPrefixFilter = $isDirectPurchaseReturn ? "AND qr_code LIKE ?" : '';
                $exactCodes = array_values(array_unique($exactQrCodesByVariant[$variantId] ?? []));
                $exactCodesFilter = !empty($exactCodes)
                    ? 'AND qr_code NOT IN (' . implode(',', array_fill(0, count($exactCodes), '?')) . ')'
                    : '';
                $savedQrParams = [$variantId, $targetWarehouseId];
                if ($isDirectPurchaseReturn) {
                    $savedQrParams[] = $qrPrefix . '%';
                }
                $savedQrParams = array_merge($savedQrParams, $exactCodes);
                $savedQrParams[] = $variantId;
                $savedQrCodes = Database::fetchAll(
                    "SELECT qr_code
                     FROM warehouse_qr_codes
                     WHERE variant_id = ? AND warehouse_id = ? AND inwarded_at IS NULL
                       {$returnQrPrefixFilter}
                       {$exactCodesFilter}
                       AND qr_code NOT IN (SELECT code FROM warehouse_deleted_label_codes WHERE variant_id = ? OR variant_id IS NULL)
                     ORDER BY id DESC
                     LIMIT " . (int)($requestedQrCountByVariant[$variantId] ?? 0) . "
                     FOR UPDATE",
                    $savedQrParams
                );
                $availableQrCodesByVariant[$variantId] = array_reverse(array_column($savedQrCodes, 'qr_code'));
            }

            if (!empty($availableQrCodesByVariant[$variantId])) {
                $tagCode = array_shift($availableQrCodesByVariant[$variantId]);
                $generatedItem['tag_code'] = $tagCode;
                $generatedItem['qr_payload'] = $tagCode;
                continue;
            }

            if (!isset($nextSequenceByPrefix[$qrPrefix])) {
                $nextSequenceByPrefix[$qrPrefix] = getWarehouseQrSequenceOffset($qrPrefix) + 1;
            }
            $sequence = max((int)$sequenceMatch[1], $nextSequenceByPrefix[$qrPrefix]);
            $sequenceWidth = strlen($sequenceMatch[1]);
            $reserved = false;
            for ($attempt = 0; $attempt < 1000; $attempt++) {
                $tagCode = $qrPrefix . $monthStr . $yearStr . sprintf("%0{$sequenceWidth}d", $sequence);
                $variantQrExists = Database::fetchOne(
                    "SELECT id FROM product_variants WHERE qr_code = ? LIMIT 1",
                    [$tagCode]
                );
                if ($variantQrExists) {
                    $sequence++;
                    continue;
                }

                try {
                    Database::execute(
                        "INSERT INTO warehouse_qr_codes (qr_code, variant_id, warehouse_id) VALUES (?, ?, ?)",
                        [$tagCode, $variantId, $targetWarehouseId]
                    );
                    $reserved = true;
                    $nextSequenceByPrefix[$qrPrefix] = $sequence + 1;
                    break;
                } catch (PDOException $e) {
                    if ((string)$e->getCode() !== '23000' || !str_contains($e->getMessage(), 'uq_warehouse_qr_code')) {
                        throw $e;
                    }
                    $sequence++;
                }
            }

            if (!$reserved) {
                throw new RuntimeException("Unable to reserve a unique warehouse QR code for {$generatedItem['product_name']}.");
            }

            $generatedItem['tag_code'] = $tagCode;
            $generatedItem['qr_payload'] = $tagCode;
            if (!empty($generatedItem['is_series'])) {
                $generatedItem['series_start_code'] = $tagCode;
                $generatedItem['series_end_code'] = $tagCode;
            }
        }
        unset($generatedItem);
        Database::commit();
    } catch (Throwable $e) {
        if (Database::inTransaction()) {
            Database::rollback();
        }
        throw $e;
    }
}

if ($currentSourceType === 'returns') {
    $tagCodesList = array_values(array_filter(array_unique(array_column($generatedItems, 'tag_code'))));
    $inwardedTagsMap = [];
    if ($tagCodesList) {
        $placeholders = implode(',', array_fill(0, count($tagCodesList), '?'));
        $inwardedRows = Database::fetchAll(
            "SELECT qr_code FROM warehouse_qr_codes
             WHERE qr_code IN ($placeholders) AND inwarded_at IS NOT NULL",
            $tagCodesList
        );
        foreach ($inwardedRows as $inwardedRow) {
            $inwardedTagsMap[$inwardedRow['qr_code']] = true;
        }
    }

    $inwardedCount = 0;
    foreach ($generatedItems as &$generatedItem) {
        if (!empty($inwardedTagsMap[$generatedItem['tag_code'] ?? ''])) {
            $generatedItem['is_inwarded'] = true;
        }
        if (!empty($generatedItem['is_inwarded'])) {
            $inwardedCount++;
        }
    }
    unset($generatedItem);

    $alreadyInwardedQty = $inwardedCount;
    $totalExpectedQty = count($generatedItems);
    $remainingQty = max(0, $totalExpectedQty - $alreadyInwardedQty);
    $isFullyInwarded = $totalExpectedQty > 0 && $remainingQty === 0;
    $isPartiallyInwarded = $alreadyInwardedQty > 0 && !$isFullyInwarded;
}
$availableGeneratedCount = count(array_filter(
    $generatedItems,
    static fn(array $item): bool => empty($item['is_inwarded']) && empty($item['is_deleted'])
));

// Prepare distinct brands and catalog codes list — ONLY for products with stock > 0
$distinctBrands = [];
$distinctCatalogCodes = [];

if (!empty($thirdPartyVariants)) {
    // 1. Calculate total available stock per generated code
    $codeStockTotals = [];
    foreach ($thirdPartyVariants as $tpv) {
        $cCode = trim($tpv['catalog_code'] ?? '') ?: trim($tpv['product_code'] ?? '');
        if ($cCode === '') continue;
        $vStock = (int)($tpv['available_qty'] ?? 0);
        // If attached to a pending PO, include the PO incoming quantity
        if ($vStock <= 0 && !empty($selectedSourceId)) {
            $poLine = Database::fetchOne("SELECT quantity FROM purchase_items WHERE purchase_order_id = ? AND variant_id = ?", [$selectedSourceId, $tpv['id']]);
            if ($poLine && (int)$poLine['quantity'] > 0) {
                $vStock = (int)$poLine['quantity'];
            }
        }
        if ($vStock <= 0 && !empty($tpv['quantity']) && (int)$tpv['quantity'] > 0) {
            $vStock = (int)$tpv['quantity'];
        }
        $codeStockTotals[$cCode] = ($codeStockTotals[$cCode] ?? 0) + $vStock;
    }

    // 2. Build list: STRICT VALIDATION: If stock count is 0, DON'T SHOW IT!
    foreach ($thirdPartyVariants as $tpv) {
        $cCode = trim($tpv['catalog_code'] ?? '') ?: trim($tpv['product_code'] ?? '');
        if ($cCode === '') continue;

        $totalStock = $codeStockTotals[$cCode] ?? 0;
        if ($totalStock <= 0) {
            continue;
        }

        $bName = trim($tpv['brand_name'] ?? '');
        if ($bName !== '' && !in_array($bName, $distinctBrands)) {
            $distinctBrands[] = $bName;
        }

        if (!isset($distinctCatalogCodes[$cCode])) {
            $catId = (int)($tpv['warehouse_category_id'] ?? $tpv['category_id'] ?? 0);
            $subcatId = (int)($tpv['warehouse_subcategory_id'] ?? $tpv['subcategory_id'] ?? 0);
            $subSubcatId = (int)($tpv['warehouse_sub_subcategory_id'] ?? $tpv['sub_subcategory_id'] ?? 0);
            $pPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($tpv['qr_code_prefix'] ?? '')));
            if (strlen($pPrefix) > 4) {
                $pPrefix = substr($pPrefix, 0, 4);
            }
            $prodCreatedAt = $tpv['product_created_at'] ?? $tpv['created_at'] ?? date('Y-m-d H:i:s');
            $pId = (int)($tpv['product_id'] ?? 0);
            $vId = (int)$tpv['id'];
            $isTpvSeries = (strtoupper($tpv['qr_type'] ?? '') === 'SERIES_BATCH' || !empty($tpv['is_complementary']));

            $catOffset = 0;
            if (!$isTpvSeries) {
                $catOffset = calculateCategorySequenceOffset($vId, $pPrefix ?: 'NEW', $catId, $subcatId, $subSubcatId, $prodCreatedAt, $pId);
            }

            $distinctCatalogCodes[$cCode] = [
                'code'        => $cCode,
                'name'        => $tpv['product_name'] ?? '',
                'brand'       => $bName,
                'variant_id'  => (int)$tpv['id'],
                'stock'       => $totalStock,
                'mrp'         => (float)($tpv['mrp'] ?? 0),
                'selling'     => (float)($tpv['selling_price'] ?? 0),
                'cost'        => (float)($tpv['purchase_price'] ?? $tpv['production_cost'] ?? 0),
                'size'        => $tpv['size_name'] ?? 'Free',
                'color'       => $tpv['color_name'] ?? 'Std',
                'qr_type'     => $tpv['qr_type'] ?? 'INDIVIDUAL',
                'pack_qty'    => (int)($tpv['pack_quantity'] ?? 0),
                'is_comp'     => !empty($tpv['is_complementary']) ? 1 : 0,
                'cat'         => $tpv['category_name'] ?? '',
                'subcat'      => $tpv['subcategory_name'] ?? '',
                'subsubcat'   => $tpv['sub_subcategory_name'] ?? '',
                'start_code'  => $tpv['series_start_code'] ?? '',
                'end_code'    => $tpv['series_end_code'] ?? '',
                'qr_prefix'   => $tpv['qr_code_prefix'] ?? '',
                'seller_name' => !empty($tpv['seller_company']) ? $tpv['seller_company'] : ($tpv['seller_name'] ?? ''),
                'seller_id'   => (int)($tpv['seller_id'] ?? 0),
                'already_inwarded' => in_array((int)$tpv['id'], $inwardedVariantIds ?? [], true) ? 1 : 0,
                'offset'      => $catOffset,
                'start_seq'   => $catOffset + 1,
                'end_seq'     => $catOffset + $totalStock,
            ];
        }
    }
}
sort($distinctBrands);
ksort($distinctCatalogCodes);
?>

<!-- Custom Styles for Thermal Sticker Preview and Browser Printing -->
<style>
.piece-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, background-color 0.15s ease;
    border: 1.5px solid #e2e8f0 !important;
}
.piece-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
    border-color: #cbd5e1 !important;
}
.piece-card.selected-piece-card {
    border: 2px solid #0d6efd !important;
    background: linear-gradient(180deg, #edf5ff 0%, #ffffff 100%) !important;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25), 0 4px 14px rgba(13, 110, 253, 0.12) !important;
}
.piece-card.selected-piece-card .piece-label-badge {
    background-color: #0d6efd !important;
    color: #ffffff !important;
    border-color: #0d6efd !important;
    box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2) !important;
}
.piece-card.inwarded-piece-card {
    border: 2px solid #198754 !important;
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%) !important;
    opacity: 0.95;
}
.piece-card.inwarded-piece-card .piece-label-badge {
    background-color: #198754 !important;
    color: #ffffff !important;
    border-color: #198754 !important;
}
.inwarded-badge {
    z-index: 5;
    letter-spacing: 0.3px;
}
.piece-label-badge {
    font-size: 0.72rem !important;
    padding: 3px 8px !important;
    font-weight: 600 !important;
    transition: all 0.15s ease-in-out;
}
.piece-select-chk {
    cursor: pointer !important;
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    min-height: 20px !important;
    border: 2px solid #495057 !important;
    border-radius: 4px !important;
    background-color: #ffffff;
    transition: all 0.15s ease-in-out;
}
.piece-select-chk:hover {
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2) !important;
}
.piece-select-chk:checked {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.3) !important;
}
.piece-select-chk:focus {
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.3) !important;
    border-color: #0d6efd !important;
}
#selectAllPiecesChk {
    cursor: pointer !important;
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    min-height: 20px !important;
    border: 2px solid #495057 !important;
    border-radius: 4px !important;
    background-color: #ffffff;
    transition: all 0.15s ease-in-out;
}
#selectAllPiecesChk:hover {
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2) !important;
}
#selectAllPiecesChk:checked, #selectAllPiecesChk:indeterminate {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.3) !important;
}
.selection-toolbar-pill {
    background: #ffffff;
    border: 1.5px solid #ced4da;
    border-radius: 8px;
    padding: 6px 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.piece-qr-box {
    width: 74px;
    height: 74px;
    background: #ffffff;
    border-radius: 6px;
    padding: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
.piece-qr-box img,
.piece-qr-box canvas {
    width: 70px !important;
    height: 70px !important;
    display: block !important;
    margin: 0 auto;
    border-radius: 2px;
}
.lbl-title {
    font-size: 0.80rem;
    font-weight: 700;
    line-height: 1.15;
    margin-bottom: 2px;
    color: #1e293b;
    letter-spacing: 0.2px;
}
.lbl-sku {
    font-size: 0.84rem;
    font-weight: 800;
    line-height: 1.15;
    margin-bottom: 2px;
    color: #0f172a;
}
.lbl-mrp {
    font-size: 0.72rem;
    text-decoration: line-through;
    color: #64748b;
    line-height: 1.1;
    margin-bottom: 2px;
}
.lbl-disc {
    font-size: 0.82rem;
    font-weight: 800;
    color: #16a34a !important;
    line-height: 1.1;
}
.label-tag-code {
    display: block;
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.05;
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
}
.stock-stickers-sheet {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: center;
}
.stock-sticker-item {
    width: 280px;
    min-height: 122px;
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    padding: 12px;
    page-break-inside: avoid;
    break-inside: avoid;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stock-sticker-item:hover {
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

@media print {
    body * {
        visibility: hidden !important;
    }
    #modalPrintAreaWrapper, #modalPrintAreaWrapper *, #modalPrintArea, #modalPrintArea * {
        visibility: visible !important;
    }
    #modalPrintAreaWrapper {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    #modalPrintArea {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 5mm !important;
        padding: 4mm !important;
        justify-content: flex-start !important;
    }
    .stock-sticker-item {
        width: 48mm !important;
        min-height: 28mm !important;
        border: 1px dashed #666 !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 2mm !important;
        background: #fff !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    .modal-header, .modal-footer, .btn, .no-print {
        display: none !important;
    }
}
</style>

<?php if (!empty($damageReplacementLabel)): ?>
<style>
@media print {
    body * { visibility: hidden !important; }
    #damageReplacementPrintArea, #damageReplacementPrintArea * { visibility: visible !important; }
    #damageReplacementPrintArea { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print { display: none !important; }
}
</style>
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 no-print">
    <div>
        <h2 class="mb-1"><i class="bi bi-qr-code-scan me-2 text-success"></i>Damage Replacement QR Label</h2>
        <p class="text-muted small mb-0">Case <?= e($damageReplacementLabel['damage_number']) ?> · <?= e($damageReplacementLabel['warehouse_name']) ?></p>
    </div>
    <a href="<?= url('warehouse/damage/resolve/' . (int)$damageReplacementLabel['damage_id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Damage Case
    </a>
</div>
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <div id="damageReplacementPrintArea" class="row g-4 align-items-center">
            <div class="col-md-4 text-center">
                <div id="damageReplacementQr" class="d-inline-flex bg-white border rounded p-3"></div>
                <div class="font-monospace fw-bold text-dark mt-2"><?= e($damageReplacementLabel['replacement_qr_code']) ?></div>
            </div>
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h4 class="fw-bold mb-0"><?= e($damageReplacementLabel['product_name']) ?></h4>
                    <span class="badge bg-success-subtle text-success"><?= e($damageReplacementLabel['category_type']) ?></span>
                </div>
                <div class="font-monospace text-primary fw-semibold"><?= e($damageReplacementLabel['sku']) ?></div>
                <div class="text-muted small mt-1"><?= e($damageReplacementLabel['category_name']) ?><?= !empty($damageReplacementLabel['subcategory_name']) ? ' › ' . e($damageReplacementLabel['subcategory_name']) : '' ?><?= !empty($damageReplacementLabel['sub_subcategory_name']) ? ' › ' . e($damageReplacementLabel['sub_subcategory_name']) : '' ?></div>
                <div class="row g-2 mt-2 small">
                    <div class="col-sm-6"><strong>Variant:</strong> <?= e($damageReplacementLabel['size_name'] ?? 'Free') ?> / <?= e($damageReplacementLabel['color_name'] ?? 'Std') ?></div>
                    <div class="col-sm-6"><strong>HSN:</strong> <?= e($damageReplacementLabel['hsn_code'] ?: ($damageReplacementLabel['product_hsn_code'] ?? '—')) ?></div>
                    <div class="col-sm-6"><strong>GST:</strong> <?= number_format((float)($damageReplacementLabel['gst_rate'] ?? $damageReplacementLabel['gst_percentage'] ?? 5), 2) ?>%</div>
                    <div class="col-sm-6"><strong>Weight:</strong> <?= number_format((float)($damageReplacementLabel['variant_weight'] ?? $damageReplacementLabel['product_weight'] ?? 0), 3) ?> kg</div>
                    <div class="col-sm-6"><strong>Base MRP:</strong> ₹<?= number_format((float)$damageReplacementLabel['mrp'], 2) ?></div>
                    <div class="col-sm-6"><strong>Selling price:</strong> ₹<?= number_format((float)$damageReplacementLabel['selling_price'], 2) ?></div>
                    <div class="col-sm-6"><strong>Supplier cost:</strong> ₹<?= number_format((float)$damageReplacementLabel['purchase_price'], 2) ?></div>
                    <div class="col-sm-6"><strong>Supplier:</strong> <?= e($damageReplacementLabel['supplier_name']) ?></div>
                    <div class="col-sm-6"><strong>Quantity:</strong> <?= number_format((int)$damageReplacementLabel['replacement_quantity']) ?> pcs</div>
                    <div class="col-sm-6"><strong>Line retail value:</strong> ₹<?= number_format((float)$damageReplacementLabel['mrp'] * (int)$damageReplacementLabel['replacement_quantity'], 2) ?></div>
                </div>
                <div class="mt-3 no-print">
                    <?php if (!empty($damageReplacementLabel['replacement_inwarded_at']) || !empty($damageReplacementLabel['qr_inwarded_at'])): ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            Replacement stock was inwarded on <?= e($damageReplacementLabel['replacement_inwarded_at'] ?? $damageReplacementLabel['qr_inwarded_at']) ?>.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i> Print QR Label
                            </button>
                            <form method="POST" action="<?= url('warehouse/damage/inward-replacement/' . (int)$damageReplacementLabel['damage_id']) ?>" onsubmit="return confirm('Inward this replacement QR and update warehouse stock?');">
                                <?= CSRF::input() ?>
                                <button type="submit" class="btn btn-success fw-semibold">
                                    <i class="bi bi-box-arrow-in-down me-1"></i> Confirm QR &amp; Inward Stock
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const qrContainer = document.getElementById('damageReplacementQr');
    if (qrContainer && typeof QRCode !== 'undefined') {
        new QRCode(qrContainer, {
            text: <?= json_encode($damageReplacementLabel['replacement_qr_code']) ?>,
            width: 160,
            height: 160,
            correctLevel: QRCode.CorrectLevel.M
        });
    }
});
</script>
<?php else: ?>
<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-qr-code-scan me-2 text-primary"></i>Warehouse QR Labels &amp; Printing Suite</h2>
        <p class="text-muted small mb-0">Manage QR sticker batches, thermal printing &amp; official warehouse inwarding</p>
    </div>
</div>

<!-- ========================================================================= -->
<!-- VIEW 1: ALREADY GENERATED QR BATCHES & INWARD HISTORY LIST -->
<!-- ========================================================================= -->
<?php
// Group batches by Supplier / Brand (Task 4: Profile-wise view)
$supplierGroups = [];
$historyTab = $_GET['history_tab'] ?? 'warehouse';
if (!in_array($historyTab, ['warehouse', 'returns', 'extra_qrs'], true)) $historyTab = 'warehouse';
foreach ($qrBatches as $batch) {
    $sKey = trim($batch['brand_name'] ?? '') ?: 'Direct Supplier';
    if (!isset($supplierGroups[$sKey])) {
        $supplierGroups[$sKey] = [
            'supplier_name'   => $sKey,
            'supplier_id'     => $batch['seller_id'] ?? null,
            'total_products'  => 0,
            'total_qty'       => 0,
            'inwarded_qty'    => 0,
            'pending_qty'     => 0,
            'inwarded_count'  => 0,
            'partial_count'   => 0,
            'pending_count'   => 0,
            'latest_date'     => $batch['batch_date'] ?? '',
            'products'        => [],
        ];
    }
    $supplierGroups[$sKey]['total_products']++;
    $bQty = (int)($batch['qty'] ?? 1);
    $inwQty = (int)($batch['inwarded_qty'] ?? 0);
    $remQty = (int)($batch['remaining_qty'] ?? max(0, $bQty - $inwQty));
    
    $supplierGroups[$sKey]['total_qty'] += $bQty;
    $supplierGroups[$sKey]['inwarded_qty'] += $inwQty;
    $supplierGroups[$sKey]['pending_qty'] += $remQty;

    $st = strtoupper($batch['inward_status'] ?? 'PENDING');
    if ($st === 'INWARDED') {
        $supplierGroups[$sKey]['inwarded_count']++;
    } elseif ($st === 'PARTIAL') {
        $supplierGroups[$sKey]['partial_count']++;
    } else {
        $supplierGroups[$sKey]['pending_count']++;
    }
    $supplierGroups[$sKey]['products'][] = $batch;
}
?>
<div id="qrBatchesListSection" style="display: <?= !$isGeneratingView ? 'block' : 'none' ?>;" class="no-print">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link fw-semibold <?= $historyTab === 'warehouse' ? 'active' : '' ?>" href="<?= url('warehouse/labels?history_tab=warehouse') ?>">
                <i class="bi bi-box-arrow-in-down me-1"></i>Warehouse Inward History
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-semibold <?= $historyTab === 'returns' ? 'active' : '' ?>" href="<?= url('warehouse/labels?history_tab=returns') ?>">
                <i class="bi bi-arrow-return-left me-1"></i>Return QR History
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-semibold <?= $historyTab === 'extra_qrs' ? 'active' : '' ?>" href="<?= url('warehouse/labels?history_tab=extra_qrs') ?>" style="<?= $historyTab === 'extra_qrs' ? 'color: #6f42c1; border-color: #dee2e6 #dee2e6 #fff;' : '' ?>">
                <i class="bi bi-patch-plus me-1" style="color: #6f42c1;"></i>Extra Generated QR History
                <?php if (!empty($totalExtraQrsCount)): ?>
                    <span class="badge rounded-pill text-white ms-1" style="background-color: #6f42c1; font-size: 0.72rem;"><?= $totalExtraQrsCount ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>
    <?php if ($historyTab === 'returns'): ?>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left text-danger me-2"></i>Warehouse Return QR History</h5>
                <small class="text-muted">Returned products available for QR label generation and warehouse inward.</small>
            </div>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= count($classifiedReturns ?? []) ?> Return Items</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Return No.</th><th>Product</th><th>SKU</th><th>Classification</th><th>Quantity</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php if (empty($classifiedReturns)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No warehouse return QR history found.</td></tr>
                <?php else: foreach ($classifiedReturns as $returnItem): ?>
                    <?php $returnQty = (int)($returnItem['good_qty'] ?? 0) + (int)($returnItem['damaged_qty'] ?? 0) + (int)($returnItem['defective_qty'] ?? 0); ?>
                    <tr>
                        <td class="font-monospace fw-semibold text-danger"><?= e($returnItem['return_number'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= e($returnItem['product_name'] ?? '-') ?></td>
                        <td class="font-monospace small"><?= e($returnItem['sku'] ?? '-') ?></td>
                        <td><span class="badge <?= strtoupper($returnItem['classification'] ?? '') === 'DAMAGED' ? 'bg-danger' : 'bg-success' ?>"><?= e($returnItem['classification'] ?? 'GOOD') ?></span></td>
                        <td><?= $returnQty ?> pcs</td>
                        <td class="text-end">
                            <?php if (!empty($returnItem['is_inwarded'])): ?>
                                <span class="text-success small fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Stock updated</span>
                            <?php elseif (hasPermission('wh_labels', 'create')): ?>
                                <a class="btn btn-sm btn-outline-danger" href="<?= url('warehouse/labels?source_type=returns&view=generate&history_tab=returns&return_id=' . (int)$returnItem['warehouse_return_id'] . '&return_item_id=' . (int)$returnItem['id'] . '&return_variant_id=' . (int)$returnItem['variant_id']) ?>">
                                    <i class="bi bi-qr-code me-1"></i>Return QR
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">View only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card border-0 shadow-sm rounded-3 mt-4">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Direct Purchase Customer Returns</h5>
                <small class="text-muted">Track GOOD returned items from R-prefixed QR generation through admin inward.</small>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= count($directPurchaseReturns ?? []) ?> Return Items</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Return No.</th><th>Purchase / Customer</th><th>Product</th><th>SKU</th><th>Quantity</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php if (empty($directPurchaseReturns)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No direct purchase customer returns are waiting for QR inward or have completed it.</td></tr>
                <?php else: foreach ($directPurchaseReturns as $purchaseReturnItem): ?>
                    <?php $purchaseReturnInwarded = !empty($purchaseReturnItem['is_inwarded']); ?>
                    <tr>
                        <td class="font-monospace fw-semibold text-primary"><?= e($purchaseReturnItem['return_number'] ?? '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($purchaseReturnItem['po_number'] ?? 'Direct Purchase') ?></div>
                            <small class="text-muted"><?= e($purchaseReturnItem['customer_name'] ?? 'Direct Customer') ?></small>
                        </td>
                        <td class="fw-semibold"><?= e($purchaseReturnItem['product_name'] ?? '-') ?></td>
                        <td class="font-monospace small"><?= e($purchaseReturnItem['sku'] ?? '-') ?></td>
                        <td><?= (int)$purchaseReturnItem['quantity'] ?> pcs</td>
                        <td>
                            <span class="badge <?= $purchaseReturnInwarded ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= $purchaseReturnInwarded ? 'Inwarded' : 'Waiting for QR Inward' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($purchaseReturnInwarded): ?>
                                <span class="text-success small fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Stock updated</span>
                            <?php elseif (hasPermission('wh_labels', 'create') || (function_exists('isAdmin') && isAdmin())): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('warehouse/labels?source_type=returns&return_source=purchase&view=generate&history_tab=returns&return_id=' . (int)$purchaseReturnItem['return_id'] . '&return_item_id=' . (int)$purchaseReturnItem['return_item_id'] . '&return_variant_id=' . (int)$purchaseReturnItem['variant_id'] . '&warehouse_id=' . (int)$purchaseReturnItem['warehouse_id']) ?>">
                                    <i class="bi bi-qr-code me-1"></i>Generate R QR
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">View only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($historyTab === 'extra_qrs'): ?>
    <!-- EXTRA GENERATED QR HISTORY (Profile-Wise View - User Requested) -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-patch-plus-fill" style="color: #6f42c1;"></i>
                    <span>Extra Generated QR History (Sequential Packs &amp; Single Pieces)</span>
                    <span class="badge rounded-pill border fs-7" style="color: #6f42c1; background-color: #f3e8ff; border-color: #d8b4fe !important;">
                        <?= count($extraQrSupplierGroups ?? []) ?> Profile<?= count($extraQrSupplierGroups ?? []) === 1 ? '' : 's' ?>
                    </span>
                    <span class="badge bg-secondary-subtle text-dark border fs-7" id="badgeExtraTotalCount">
                        <?= (int)($totalExtraQrsCount ?? 0) ?> Extra QR<?= ($totalExtraQrsCount ?? 0) === 1 ? '' : 's' ?> (<?= (int)($totalExtraPcsCount ?? 0) ?> Total Pcs)
                    </span>
                </h5>
                <small class="text-muted">Review on-the-fly produced sequential QR packs, stock adjustments and extra product labels organized profile-wise</small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" id="btnToggleAllExtraProfiles" onclick="toggleAllExtraProfiles()" title="Expand or Collapse All Profiles">
                    <i class="bi bi-arrows-expand me-1" id="iconToggleAllExtraProfiles"></i> <span id="textToggleAllExtraProfiles">Collapse All</span>
                </button>
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" id="filterExtraQrsTableInput" class="form-control" placeholder="Search Supplier, Code, SKU..." onkeyup="filterExtraQrsTable()">
                </div>
            </div>
        </div>

        <div class="card-body p-3" id="extraProfilesContainer">
            <?php if (empty($extraQrSupplierGroups)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-patch-plus fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    <div class="fw-semibold">No extra generated QR stickers recorded yet</div>
                    <div class="small">Extra sequential packs or single pieces created via <strong>Add QR</strong> will automatically appear here.</div>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php 
                    $eSuppIdx = 0;
                    foreach ($extraQrSupplierGroups as $sName => $sData): 
                        $eSuppIdx++;
                        $safeExtraId = 'extra-supp-' . preg_replace('/[^A-Za-z0-9_-]/', '', $sName) . '-' . $eSuppIdx;
                        $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sName), 0, 3)) ?: 'SUP';
                        $extraSearch = strtolower($sName);
                        foreach ($sData['items'] as $it) {
                            $extraSearch .= ' ' . strtolower(($it['qr_code'] ?? '') . ' ' . ($it['series_end_code'] ?? '') . ' ' . ($it['product_name'] ?? '') . ' ' . ($it['sku'] ?? ''));
                        }
                    ?>
                        <div class="card border rounded-3 shadow-xs extra-profile-card overflow-hidden" data-extra-search="<?= e($extraSearch) ?>" id="card-<?= $safeExtraId ?>">
                            <!-- Profile Header -->
                            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-3 border-bottom" style="cursor: pointer;" onclick="toggleSupplierCollapse('<?= $safeExtraId ?>')">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center shadow-xs flex-shrink-0" style="width: 44px; height: 44px; font-size: 0.95rem; background: linear-gradient(135deg, #6f42c1, #4f46e5);">
                                        <?= e($initials) ?>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h5 class="fw-bold mb-0 text-dark"><?= e($sName) ?></h5>
                                            <span class="badge bg-light text-dark border font-monospace px-2 py-0.5" style="font-size: 0.72rem;">
                                                <i class="bi bi-qr-code text-purple me-1" style="color: #6f42c1;"></i><?= $sData['total_qrs'] ?> Extra QR<?= $sData['total_qrs'] === 1 ? '' : 's' ?>
                                            </span>
                                            <span class="badge bg-light text-dark border font-monospace px-2 py-0.5" style="font-size: 0.72rem;">
                                                <i class="bi bi-layers text-info me-1"></i><?= $sData['total_pcs'] ?> Total Pcs
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap" style="font-size: 0.75rem;">
                                            <?php if ($sData['inwarded_pcs'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-0.5">
                                                    <i class="bi bi-check-circle-fill me-1"></i><?= $sData['inwarded_pcs'] ?> Pcs Inwarded
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($sData['pending_pcs'] > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace px-2 py-0.5">
                                                    <i class="bi bi-hourglass-split me-1"></i><?= $sData['pending_pcs'] ?> Pcs Pending Inward
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btn-toggle-<?= $safeExtraId ?>">
                                        <span id="label-<?= $safeExtraId ?>">Extra QRs (<?= count($sData['items']) ?>)</span>
                                        <i class="bi bi-chevron-up ms-1" id="chevron-<?= $safeExtraId ?>"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Collapsible Table -->
                            <div class="collapse show" id="collapse-<?= $safeExtraId ?>">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                        <thead class="table-light text-secondary border-bottom">
                                            <tr>
                                                <th class="ps-3" style="width: 70px;">ID</th>
                                                <th>Product / Variant</th>
                                                <th>Category Type</th>
                                                <th>QR Code &amp; Piece Range</th>
                                                <th class="text-center">Pack Qty</th>
                                                <th>Warehouse &amp; User</th>
                                                <th>Status</th>
                                                <th class="text-end pe-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sData['items'] as $item): 
                                                $isComp = !empty($item['var_is_comp']) || !empty($item['prod_is_comp']);
                                                $isSeries = strtoupper($item['variant_qr_type'] ?? '') === 'SERIES_BATCH' || strtoupper($item['variant_qr_type'] ?? '') === 'SERIES';
                                                $pQty = (int)($item['pack_quantity'] ?? 1);
                                                if ($pQty <= 0) $pQty = 1;
                                                $isInwarded = !empty($item['inwarded_at']);

                                                // Calculate sequence range badge
                                                $seqBadge = '';
                                                $sCode = $item['qr_code'];
                                                $eCode = !empty($item['series_end_code']) ? $item['series_end_code'] : $sCode;
                                                if (preg_match('/^[A-Za-z]+\d{4}(\d+)$/', $sCode, $mS) && preg_match('/^[A-Za-z]+\d{4}(\d+)$/', $eCode, $mE)) {
                                                    $seqBadge = (int)$mS[1] . ' &rarr; ' . (int)$mE[1];
                                                } elseif (preg_match('/(\d+)$/', $sCode, $mS) && preg_match('/(\d+)$/', $eCode, $mE)) {
                                                    $seqBadge = (int)$mS[1] . ' &rarr; ' . (int)$mE[1];
                                                }
                                            ?>
                                                <tr class="extra-qr-row">
                                                    <td class="ps-3 font-monospace text-muted small">#<?= (int)$item['id'] ?></td>
                                                    <td>
                                                        <div class="fw-bold text-dark"><?= e($item['product_name']) ?></div>
                                                        <div class="font-monospace small text-secondary">
                                                            SKU: <strong><?= e($item['sku']) ?></strong> 
                                                            (<?= e($item['size_name'] ?: 'Free') ?> / <?= e($item['color_name'] ?: 'Std') ?>)
                                                        </div>
                                                        <?php if (!empty($item['category_name'])): ?>
                                                            <div class="text-muted" style="font-size: 0.72rem;">
                                                                <?= e(implode(' › ', array_filter([$item['category_name'], $item['subcategory_name'] ?? '', $item['sub_subcategory_name'] ?? '']))) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($isComp): ?>
                                                            <span class="badge text-white px-2 py-1" style="background-color: #6f42c1; font-size: 0.72rem;">
                                                                <i class="bi bi-gift me-1"></i>Complementary Pack
                                                            </span>
                                                        <?php elseif ($isSeries): ?>
                                                            <span class="badge bg-dark text-white px-2 py-1" style="font-size: 0.72rem;">
                                                                <i class="bi bi-boxes me-1"></i>Series Pack
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary-subtle text-dark border px-2 py-1" style="font-size: 0.72rem;">
                                                                <i class="bi bi-tag me-1"></i>Single Piece
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-1">
                                                            <strong class="font-monospace text-primary fs-6"><?= e($item['qr_code']) ?></strong>
                                                        </div>
                                                        <?php if ($seqBadge && $pQty > 1): ?>
                                                            <span class="badge bg-light text-primary border font-monospace mt-1 px-1.5 py-0.5" style="font-size: 0.70rem;">
                                                                <i class="bi bi-upc me-1"></i>Range: <?= $seqBadge ?>
                                                            </span>
                                                        <?php elseif ($pQty === 1): ?>
                                                            <span class="badge bg-light text-secondary border font-monospace mt-1 px-1.5 py-0.5" style="font-size: 0.70rem;">
                                                                Single Piece Sticker
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['extra_reason'])): ?>
                                                            <div class="text-muted small italic mt-0.5" style="font-size: 0.70rem;">
                                                                <?= e($item['extra_reason']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center font-monospace">
                                                        <span class="badge bg-dark-subtle text-dark px-2 py-1 fs-7">
                                                            <strong><?= $pQty ?></strong> pcs
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="small text-dark"><i class="bi bi-building text-primary me-1"></i><?= e($item['warehouse_name'] ?: 'Warehouse') ?></div>
                                                        <div class="text-muted" style="font-size: 0.72rem;">
                                                            <i class="bi bi-person me-1"></i><?= e($item['creator_name'] ?: 'System') ?> 
                                                            &bull; <?= date('d M Y, h:i A', strtotime($item['created_at'])) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($isInwarded): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                                <i class="bi bi-check-circle-fill me-1"></i>Inwarded to Stock
                                                            </span>
                                                            <div class="text-muted small mt-0.5" style="font-size: 0.68rem;">
                                                                <?= date('d M, h:i A', strtotime($item['inwarded_at'])) ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                                                <i class="bi bi-clock-history me-1"></i>Pending Inward
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end pe-3 text-nowrap">
                                                        <?php
                                                        $extraStickerPayload = [
                                                            'id'                 => (int)$item['id'],
                                                            'tag_code'           => $item['qr_code'],
                                                            'series_end_code'    => $item['series_end_code'] ?: $item['qr_code'],
                                                            'product_name'       => $item['product_name'] ?? '',
                                                            'sku'                => $item['sku'] ?? '',
                                                            'clean_sku'          => !empty($item['size_name']) && !empty($item['color_name']) ? ($item['size_name'] . '-' . $item['color_name']) : ($item['sku'] ?? 'STD'),
                                                            'brand_name'         => $item['brand_name'] ?: 'GRM WAREHOUSE',
                                                            'size_name'          => $item['size_name'] ?: 'Free',
                                                            'color_name'         => $item['color_name'] ?: 'Std',
                                                            'mrp'                => (float)($item['mrp'] ?? 0),
                                                            'selling_price'      => (float)($item['selling_price'] ?? 0),
                                                            'cost_price'         => (float)($item['purchase_price'] ?? 0),
                                                            'pack_quantity'      => $pQty,
                                                            'is_series'          => $isSeries || $isComp,
                                                            'is_complementary'   => $isComp,
                                                            'qr_payload'         => $item['qr_code'],
                                                            'category_hierarchy' => implode(' › ', array_filter([$item['category_name'] ?? '', $item['subcategory_name'] ?? '', $item['sub_subcategory_name'] ?? ''])),
                                                        ];
                                                        ?>

                                                        <a href="<?= url('warehouse/labels?source_type=po&brand_name=' . urlencode($item['brand_name'] ?: '') . '&catalog_code=' . urlencode($item['catalog_code'] ?: $item['product_code'] ?: '') . '&variant_id=' . (int)$item['variant_id'] . '&view=existing&history_tab=extra_qrs') ?>" 
                                                           class="btn btn-sm btn-outline-secondary fw-semibold shadow-2xs" 
                                                           title="Open all QR batches for this product in QR Suite">
                                                            <i class="bi bi-boxes me-1"></i>All Labels
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-building-fill-check text-primary"></i>
                    <span>QR Batches &amp; Inward History</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-7"><?= count($supplierGroups) ?> Supplier Profile<?= count($supplierGroups) === 1 ? '' : 's' ?></span>
                    <span class="badge bg-secondary-subtle text-dark border fs-7" id="badgeBatchesTotalCount"><?= count($qrBatches) ?> Total Products</span>
                </h5>
                <small class="text-muted">Review QR sticker batches organized supplier profile-wise with piece tracking &amp; inward confirmation</small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" id="btnToggleAllProfiles" onclick="toggleAllSupplierProfiles()" title="Expand or Collapse All Supplier Profiles">
                    <i class="bi bi-arrows-expand me-1" id="iconToggleAllProfiles"></i> <span id="textToggleAllProfiles">Expand All</span>
                </button>
                <div class="input-group input-group-sm" style="width: 240px;">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" id="filterBatchesTableInput" class="form-control" placeholder="Search Supplier, Code, SKU..." onkeyup="filterBatchesTable()">
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- MODE A: SUPPLIER PROFILE-WISE GROUPED VIEW (User Requested) -->
        <!-- ================================================================= -->
        <div class="card-body p-3" id="supplierProfilesContainer">
            <?php if (empty($supplierGroups)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-qr-code fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    <div class="fw-semibold">No QR batches or third-party catalog products found</div>
                    <div class="small">Click <strong>+ Generate QR Labels</strong> to create your first QR sticker batch.</div>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php 
                    $suppIdx = 0;
                    foreach ($supplierGroups as $sName => $sData): 
                        $suppIdx++;
                        $safeId = 'supp-' . preg_replace('/[^A-Za-z0-9_-]/', '', $sName) . '-' . $suppIdx;
                        $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sName), 0, 3)) ?: 'SUP';
                        $allProdsSearch = strtolower($sName);
                        foreach ($sData['products'] as $p) {
                            $allProdsSearch .= ' ' . strtolower(($p['catalog_code'] ?? '') . ' ' . ($p['product_name'] ?? '') . ' ' . ($p['sku'] ?? ''));
                        }
                    ?>
                        <div class="card border rounded-3 shadow-xs supplier-profile-card overflow-hidden" data-supplier-search="<?= e($allProdsSearch) ?>" id="card-<?= $safeId ?>">
                            <!-- Supplier Profile Header -->
                            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-3 border-bottom" style="cursor: pointer;" onclick="toggleSupplierCollapse('<?= $safeId ?>')">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center shadow-xs flex-shrink-0" style="width: 44px; height: 44px; font-size: 0.95rem; letter-spacing: 0.5px;">
                                        <?= e($initials) ?>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h5 class="fw-bold mb-0 text-dark"><?= e($sName) ?></h5>
                                            <span class="badge bg-light text-dark border font-monospace px-2 py-0.5" style="font-size: 0.72rem;">
                                                <i class="bi bi-box-seam text-primary me-1"></i><?= $sData['total_products'] ?> Product<?= $sData['total_products'] === 1 ? '' : 's' ?>
                                            </span>
                                            <span class="badge bg-light text-dark border font-monospace px-2 py-0.5" style="font-size: 0.72rem;">
                                                <i class="bi bi-layers text-info me-1"></i><?= $sData['total_qty'] ?> Total Pcs
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap" style="font-size: 0.75rem;">
                                            <?php if ($sData['inwarded_qty'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5">
                                                    <i class="bi bi-check2-circle me-1"></i><?= $sData['inwarded_qty'] ?> Pcs Inwarded
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($sData['pending_qty'] > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-0.5">
                                                    <i class="bi bi-hourglass-split me-1"></i><?= $sData['pending_qty'] ?> Pcs Pending Inward
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary fw-semibold px-3 py-1.5 shadow-2xs"
                                            id="btn-toggle-<?= $safeId ?>" 
                                            aria-expanded="false"
                                            aria-controls="collapse-<?= $safeId ?>"
                                            onclick="event.stopPropagation(); toggleSupplierCollapse('<?= $safeId ?>')"
                                            title="Click to collapse or expand this supplier profile">
                                        <i class="bi bi-chevron-down me-1" id="chevron-<?= $safeId ?>"></i> Products &amp; Inward (<?= $sData['total_products'] ?>)
                                    </button>
                                </div>
                            </div>

                            <!-- Products under this Supplier -->
                            <div class="collapse" id="collapse-<?= $safeId ?>">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr class="small text-muted text-uppercase" style="font-size: 0.72rem;">
                                                <th style="min-width: 140px;" class="ps-3">Generated Code</th>
                                                <th style="min-width: 200px;">Product &amp; Details</th>
                                                <th style="width: 110px;" class="text-center">Batch Qty</th>
                                                <th style="min-width: 190px;">QR Tag Range / Notes</th>
                                                <th style="width: 140px;" class="text-center">Inward Status</th>
                                                <th style="min-width: 180px;" class="text-end pe-3">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sData['products'] as $batch): 
                                                $isInwarded = ($batch['inward_status'] === 'INWARDED');
                                                $isPartial = ($batch['inward_status'] === 'PARTIAL');
                                                $bDate = !empty($batch['batch_date']) ? date('d M Y, h:i A', strtotime($batch['batch_date'])) : '—';
                                                $cCode = $batch['catalog_code'] ?: $batch['product_code'];
                                                $isComp = !empty($batch['is_complementary']) || strtoupper($batch['category_type'] ?? '') === 'COMPLEMENTARY';
                                                $isSeries = (strtoupper($batch['category_type'] ?? '') === 'SERIES' || strtoupper($batch['qr_type'] ?? '') === 'SERIES_BATCH' || strtoupper($batch['qr_type'] ?? '') === 'SERIES');
                                                $typeLabel = $isComp ? 'Complementary Product' : ($isSeries ? 'Series Product' : 'Individual Product');
                                                $prodSearch = strtolower($sName . ' ' . $cCode . ' ' . ($batch['product_name'] ?? '') . ' ' . ($batch['sku'] ?? '') . ' ' . $typeLabel);
                                                $loadUrl = url('warehouse/labels?source_type=po&brand_name=' . urlencode($sName) . '&catalog_code=' . urlencode($cCode) . '&variant_id=' . (int)$batch['variant_id'] . '&view=generate' . ($historyTab !== 'warehouse' ? '&history_tab=' . urlencode($historyTab) : ''));
                                                $existingLabelsUrl = str_replace('view=generate', 'view=existing', $loadUrl);
                                                $editQrPackUrl = $existingLabelsUrl . '&edit_qr_pack=1';
                                                $canEditBatchQrPack = $canEditWarehouseQr && (!$isInwarded || $canInwardQrUnits);
                                            ?>
                                                <tr class="supplier-prod-row" data-search="<?= e($prodSearch) ?>">
                                                    <td class="ps-3">
                                                        <span class="badge bg-light text-dark font-monospace border px-2 py-1 shadow-2xs" style="font-size: 0.78rem;">
                                                            <i class="bi bi-upc-scan text-info me-1"></i><?= e($cCode) ?>
                                                        </span>
                                                        <div class="small text-muted font-monospace mt-0.5" style="font-size: 0.70rem;">
                                                            <?= e($bDate) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold text-dark text-truncate mb-1" style="max-width: 240px; font-size: 0.84rem;" title="<?= e($batch['product_name']) ?>">
                                                            <?= e($batch['product_name']) ?>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                            <?php if ($isComp): ?>
                                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-0.5" style="font-size: 0.69rem;">
                                                                    <i class="bi bi-gift-fill me-1"></i>Complementary
                                                                </span>
                                                            <?php elseif ($isSeries): ?>
                                                                <span class="badge border px-2 py-0.5" style="background-color: #f3e8ff; color: #6f42c1; border-color: #d8b4fe; font-size: 0.69rem;">
                                                                    <i class="bi bi-boxes me-1"></i>Series Pack<?= !empty($batch['pack_quantity']) && $batch['pack_quantity'] > 1 ? ' (' . (int)$batch['pack_quantity'] . ' Pcs)' : '' ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5" style="font-size: 0.69rem;">
                                                                    <i class="bi bi-upc me-1"></i>Individual Piece
                                                                </span>
                                                            <?php endif; ?>

                                                            <?php if (!empty($batch['size_name']) || !empty($batch['color_name'])): ?>
                                                                <span class="badge bg-light text-secondary border font-monospace px-1.5 py-0.5" style="font-size: 0.69rem;">
                                                                    <?= e($batch['size_name'] ?? 'Free') ?> / <?= e($batch['color_name'] ?? 'Std') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center font-monospace">
                                                        <?php if ($isPartial): ?>
                                                            <div class="fw-bold text-dark fs-7"><?= (int)$batch['total_qty'] ?> pcs</div>
                                                            <div class="small text-muted" style="font-size: 0.70rem;">
                                                                <span class="text-success fw-bold"><?= (int)$batch['inwarded_qty'] ?> in</span> &bull; <span class="text-warning-emphasis fw-bold"><?= (int)$batch['remaining_qty'] ?> left</span>
                                                            </div>
                                                        <?php elseif ($isInwarded): ?>
                                                            <div class="fw-bold text-success fs-7"><?= (int)$batch['total_qty'] ?> pcs</div>
                                                            <div class="small text-muted" style="font-size: 0.70rem;">100% Inwarded</div>
                                                        <?php else: ?>
                                                            <div class="fw-bold text-dark fs-7"><?= (int)$batch['total_qty'] ?> pcs</div>
                                                            <div class="small text-muted" style="font-size: 0.70rem;">Awaiting Inward</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($batch['notes'])): ?>
                                                            <span class="small font-monospace text-muted text-truncate d-inline-block" style="max-width: 250px;" title="<?= e($batch['notes']) ?>">
                                                                <?= e($batch['notes']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;">
                                                                Standard Sequential Batch
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($isInwarded): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1">
                                                                <i class="bi bi-check-circle-fill me-1"></i>Inwarded
                                                            </span>
                                                        <?php elseif ($isPartial): ?>
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1">
                                                                <i class="bi bi-pie-chart-fill me-1"></i>Partially Inwarded (<?= (int)$batch['inwarded_qty'] ?>/<?= (int)$batch['total_qty'] ?>)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary-subtle text-dark border px-2.5 py-1">
                                                                <i class="bi bi-clock me-1"></i>Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end pe-3">
                                                        <div class="d-flex align-items-center justify-content-end gap-1.5 flex-wrap">
                                                            <?php if ($isPartial && $canCreateWarehouseQr && $canInwardQrUnits): ?>
                                                                <a href="<?= $loadUrl ?>" class="btn btn-sm btn-success fw-bold px-2.5 py-1 shadow-2xs" title="Generate remaining QR labels and inward remaining pieces">
                                                                    <i class="bi bi-box-arrow-in-down me-1"></i>Inward Remaining (+<?= (int)$batch['remaining_qty'] ?>)
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if ($isPartial || $isInwarded): ?>
                                                                <a href="<?= e($existingLabelsUrl) ?>" class="btn btn-sm btn-outline-primary fw-semibold px-2 py-1" title="View previously generated QR stickers from this batch">
                                                                    <i class="bi bi-eye me-1"></i>View
                                                                </a>
                                                                <?php if (($isSeries || $isComp) && $canEditBatchQrPack): ?>
                                                                    <a href="<?= e($editQrPackUrl) ?>" class="btn btn-sm btn-outline-success fw-semibold px-2 py-1" title="Edit the existing master QR pack quantity">
                                                                        <i class="bi bi-pencil-square me-1"></i>Edit Pack
                                                                    </a>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <?php if ($canCreateWarehouseQr && $canInwardQrUnits): ?>
                                                                <a href="<?= $loadUrl ?>" class="btn btn-sm btn-success fw-bold px-2.5 py-1 shadow-2xs" title="Generate QR stickers and inward this product to inventory">
                                                                    <i class="bi bi-qr-code me-1"></i>Generate &amp; Inward (+<?= (int)$batch['total_qty'] ?>)
                                                                </a>
                                                                <?php else: ?>
                                                                <span class="badge bg-light text-muted border px-2 py-1"><i class="bi bi-lock me-1"></i>View Only</span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>
</div><!-- End #qrBatchesListSection -->

<!-- ========================================================================= -->
<!-- VIEW 2: QR GENERATION AREA -->
<!-- ========================================================================= -->
<div id="qrGenerationArea" style="display: <?= $isGeneratingView ? 'block' : 'none' ?>;">
    <div class="d-flex align-items-center justify-content-between mb-3 no-print">
        <a href="<?= url('warehouse/labels' . ($historyTab !== 'warehouse' ? '?history_tab=' . $historyTab : '')) ?>" 
           class="btn btn-sm btn-outline-secondary fw-semibold px-3 py-1.5" 
           id="btnBackToBatches" 
           onclick="return handleBackToBatches(event)">
            <i class="bi bi-arrow-left me-1"></i> Back to QR Batches History
        </a>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 font-monospace">
            <i class="bi bi-qr-code-scan me-1 text-primary"></i><?= $isViewingExisting ? 'Saved QR Labels' : 'QR Generation Mode Active' ?>
        </span>
    </div>

<?php if ($isViewingExisting): ?>
    <div class="alert alert-info py-2 mb-3 no-print">
        <i class="bi bi-info-circle me-1"></i>
        <?php if ($canEditWarehouseQr): ?>
            Saved QR labels are shown below. Use <strong>Edit Pack</strong> on a master QR to add units. Delete non-inwarded labels with a required audit reason; inwarded labels are protected.
        <?php else: ?>
            Viewing previously generated QR labels. This page is read-only for your account.
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- SOURCE SELECTION TABS (Tab 1: Customer Returns | Tab 2: Third-Party Buy Products) -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 mb-4 no-print bg-white">
    <div class="card-header bg-white border-bottom p-3">
        <ul class="nav nav-pills card-header-pills gap-2 flex-wrap" id="sourceTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $currentSourceType === 'returns' ? 'active' : '' ?> fw-semibold" href="<?= url('warehouse/labels?source_type=returns') ?>">
                    <i class="bi bi-arrow-return-left me-1"></i> 1. Customer Return QR Labels &amp; Stock Inward
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentSourceType, ['po', 'tp_purchase']) ? 'active' : '' ?> fw-semibold" href="<?= url('warehouse/labels?source_type=tp_purchase') ?>">
                    <i class="bi bi-bag-check me-1"></i> 2. Third-Party Buy Products QR &amp; Stock Inward
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-4">
        <form method="GET" action="<?= url('warehouse/labels') ?>" id="qrGenForm" class="row g-3" <?= $isViewingExisting ? 'style="display:none;"' : '' ?>>
            <input type="hidden" name="source_type" value="<?= e($currentSourceType) ?>">
            <?php if ($isDirectPurchaseReturn): ?>
                <input type="hidden" name="return_source" value="purchase">
                <input type="hidden" name="return_item_id" value="<?= (int)$selectedReturnItemId ?>">
                <input type="hidden" name="return_id" value="<?= (int)$selectedSourceId ?>">
                <input type="hidden" name="return_variant_id" value="<?= (int)$selectedVariantId ?>">
                <input type="hidden" name="warehouse_id" value="<?= (int)$targetWarehouseId ?>">
            <?php elseif ($isSpecificWarehouseReturnItem): ?>
                <input type="hidden" name="return_item_id" value="<?= (int)$selectedReturnItemId ?>">
                <input type="hidden" name="return_id" value="<?= (int)$selectedSourceId ?>">
                <input type="hidden" name="return_variant_id" value="<?= (int)$selectedVariantId ?>">
                <input type="hidden" name="warehouse_id" value="<?= (int)$targetWarehouseId ?>">
            <?php endif; ?>

            <?php if ($currentSourceType === 'returns'): ?>
                <!-- SESSION 1: CUSTOMER RETURNS QR GENERATOR -->
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark"><i class="bi bi-folder-fill text-primary me-1"></i>Category</label>
                    <select name="category_id" id="returnsCatFilter" class="form-select form-select-sm" onchange="onCategoryFilterChange('returns')">
                        <option value="">-- All Categories --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($selectedCategoryId == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark"><i class="bi bi-folder2 text-primary me-1"></i>Subcategory</label>
                    <select name="subcategory_id" id="returnsSubcatFilter" class="form-select form-select-sm" onchange="onSubcategoryFilterChange('returns')">
                        <option value="">-- All Subcategories --</option>
                        <?php foreach ($subcategories as $sc): ?>
                            <option value="<?= $sc['id'] ?>" data-cat="<?= $sc['category_id'] ?>" <?= ($selectedSubcategoryId == $sc['id']) ? 'selected' : '' ?>><?= e($sc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark"><i class="bi bi-folder2-open text-primary me-1"></i>Sub-Subcategory</label>
                    <select name="sub_subcategory_id" id="returnsSubSubcatFilter" class="form-select form-select-sm" onchange="filterVariantsByHierarchy('returns')">
                        <option value="">-- All Sub-Subcategories --</option>
                        <?php foreach ($subSubcategories as $ssc): ?>
                            <option value="<?= $ssc['id'] ?>" data-subcat="<?= $ssc['subcategory_id'] ?>" <?= ($selectedSubSubcategoryId == $ssc['id']) ? 'selected' : '' ?>><?= e($ssc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark"><i class="bi bi-tag text-primary me-1"></i>Returned Product Variant / SKU <span class="text-danger">*</span></label>
                    <select name="variant_id" id="returnsVariantSelect" class="form-select form-select-sm" required onchange="onReturnVariantChange(this)" <?= ($isDirectPurchaseReturn || $isSpecificWarehouseReturnItem) ? 'disabled' : '' ?>>
                        <option value="">-- Select Product Variant --</option>
                        <?php foreach ($stockVariants as $sv): 
                            $hParts = array_filter([$sv['category_name'] ?? '', $sv['subcategory_name'] ?? '', $sv['sub_subcategory_name'] ?? '']);
                            $hBadge = !empty($hParts) ? ('[' . implode(' › ', $hParts) . '] ') : '';
                        ?>
                            <option value="<?= $sv['id'] ?>"
                                    data-cat="<?= $sv['category_id'] ?? '' ?>"
                                    data-subcat="<?= $sv['subcategory_id'] ?? '' ?>"
                                    data-subsubcat="<?= $sv['sub_subcategory_id'] ?? '' ?>"
                                    data-brand="<?= e($sv['brand_name'] ?? '') ?>"
                                    <?= ($selectedVariantId == $sv['id']) ? 'selected' : '' ?>>
                                <?= e($hBadge . $sv['product_name']) ?> | SKU: <?= e($sv['sku']) ?> (<?= e($sv['size_name'] ?? 'Free') ?> / <?= e($sv['color_name'] ?? 'Std') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isDirectPurchaseReturn || $isSpecificWarehouseReturnItem): ?>
                        <input type="hidden" name="variant_id" value="<?= (int)$selectedVariantId ?>">
                    <?php endif; ?>
                </div>

                <!-- Custom Brand for Return -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-tag-fill text-primary me-1"></i>Custom Brand for Return
                    </label>
                    <input type="text" name="custom_brand" id="returnsCustomBrand" class="form-control form-control-sm text-uppercase fw-semibold" list="returnsBrandList" placeholder="e.g. GRM RETURN or Brand" value="<?= e($selectedCustomBrand) ?>">
                    <datalist id="returnsBrandList">
                        <option value="GRM RETURN">
                        <option value="GRM CLASSIC">
                        <?php foreach ($distinctBrands as $bName): ?>
                            <option value="<?= e($bName) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold small text-dark">Return QC Classification</label>
                    <select name="tag_type" class="form-select form-select-sm" <?= $isDirectPurchaseReturn ? 'disabled' : '' ?>>
                        <option value="GOOD" <?= $tagType === 'GOOD' ? 'selected' : '' ?>>GOOD — Re-Stock (Available Inventory)</option>
                        <option value="DAMAGED" <?= $tagType === 'DAMAGED' ? 'selected' : '' ?>>DAMAGED — Quarantine / Scrap</option>
                        <option value="DEFECTIVE" <?= $tagType === 'DEFECTIVE' ? 'selected' : '' ?>>DEFECTIVE — Factory Rework</option>
                        <option value="WRONG_SIZE" <?= $tagType === 'WRONG_SIZE' ? 'selected' : '' ?>>WRONG SIZE — Store Exchange</option>
                    </select>
                    <?php if ($isDirectPurchaseReturn): ?>
                        <input type="hidden" name="tag_type" value="GOOD">
                    <?php endif; ?>
                </div>

                <div class="col-md-1">
                    <label class="form-label fw-bold small text-dark">Copies</label>
                    <input type="number" name="copies" class="form-control form-control-sm text-center" value="<?= $copiesCount ?>" min="1" max="500" required <?= ($isDirectPurchaseReturn || $isSpecificWarehouseReturnItem) ? 'readonly' : '' ?>>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <?php if ($canCreateWarehouseQr): ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold py-2 shadow-sm">
                            <i class="bi bi-qr-code me-1"></i> Generate QR Labels
                        </button>
                    <?php endif; ?>
                </div>

            <?php elseif (in_array($currentSourceType, ['po', 'tp_purchase'])): ?>
                <!-- SESSION 2: THIRD-PARTY BUY PRODUCTS & PO QR GENERATOR -->

                <?php if (empty($thirdPartyVariants)): ?>
                <!-- ⚠️ CATALOG GATE: Block QR generation until catalog products exist -->
                <div class="col-12">
                    <div class="alert alert-warning border-warning border-2 rounded-3 d-flex align-items-start gap-3 py-4 mb-0" style="background: linear-gradient(135deg, #fff8e1 0%, #fffde7 100%);">
                        <i class="bi bi-lock-fill fs-2 text-warning mt-1 flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-exclamation-triangle me-1 text-warning"></i>QR Generation Blocked — No Catalog Products Found</h6>
                            <p class="mb-3 text-secondary small">No third-party supplier products are catalogued yet. QR labels can only be generated for products that are properly added to the supplier catalog first.</p>
                            <p class="mb-2 text-secondary small fw-semibold">Correct workflow: <span class="badge bg-primary">1. Register Supplier</span> → <span class="badge bg-success">2. Add to Catalog</span> → <span class="badge bg-warning text-dark">3. Generate QR</span></p>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if (hasPermission('wh_catalog', 'create')): ?>
                                    <a href="<?= url('warehouse/catalog/create') ?>" class="btn btn-warning btn-sm fw-bold shadow-sm">
                                        <i class="bi bi-plus-circle me-1"></i> Add Products to Catalog
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('wh_sellers', 'view')): ?>
                                    <a href="<?= url('warehouse/sellers') ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-shop-window me-1"></i> Go to Suppliers
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>

                <!-- 1. Brand Dropdown (only shows brands with cataloged products) -->
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-tag-fill text-primary me-1"></i>1. Brand Name <span class="text-danger">*</span>
                    </label>
                    <select name="brand_name" id="brandFilterSelect" class="form-select form-select-sm fw-semibold" onchange="onBrandFilterChange(this.value, true)" <?= !empty($activeVar) ? 'disabled' : '' ?>>
                        <option value="">-- All Brands --</option>
                        <?php foreach ($distinctBrands as $bName): ?>
                            <option value="<?= e($bName) ?>" <?= ($selectedBrandName === $bName) ? 'selected' : '' ?>>
                                <?= e($bName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-funnel me-1 text-primary"></i>Filters Generated Codes by Brand.
                    </div>
                </div>

                <!-- 2. Generated Code Dropdown -->
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-upc-scan text-info me-1"></i>2. Generated Code <span class="text-danger">*</span>
                    </label>
                    <select name="catalog_code" id="catalogCodeFilterSelect" class="form-select form-select-sm fw-bold font-monospace" required onchange="onCatalogCodeFilterChange(this.value)" <?= !empty($activeVar) ? 'disabled' : '' ?>>
                        <option value="">-- Choose Generated Code --</option>
                        <?php 
                        foreach ($distinctCatalogCodes as $cCode => $cInfo): 
                            // Brand name based: if brand is selected, only render codes for this brand
                            if (!empty($selectedBrandName) && strcasecmp(trim($cInfo['brand']), trim($selectedBrandName)) !== 0) {
                                continue;
                            }
                            // Strict validation: if 0 don't show it
                            if ($cInfo['stock'] <= 0) {
                                continue;
                            }
                            $isAlreadyInwarded = !empty($cInfo['already_inwarded']);
                        ?>
                            <option value="<?= e($cCode) ?>" 
                                    data-brand="<?= e($cInfo['brand']) ?>"
                                    data-variant-id="<?= (int)$cInfo['variant_id'] ?>"
                                    data-name="<?= e($cInfo['name']) ?>"
                                    data-stock="<?= (int)$cInfo['stock'] ?>"
                                    data-mrp="<?= (float)$cInfo['mrp'] ?>"
                                    data-selling="<?= (float)$cInfo['selling'] ?>"
                                    data-cost="<?= (float)$cInfo['cost'] ?>"
                                    data-size="<?= e($cInfo['size']) ?>"
                                    data-color="<?= e($cInfo['color']) ?>"
                                    data-qr-type="<?= e($cInfo['qr_type']) ?>"
                                    data-pack-qty="<?= (int)$cInfo['pack_qty'] ?>"
                                    data-cat="<?= e($cInfo['cat']) ?>"
                                    data-subcat="<?= e($cInfo['subcat']) ?>"
                                    data-subsubcat="<?= e($cInfo['subsubcat']) ?>"
                                    data-start-code="<?= e($cInfo['start_code']) ?>"
                                    data-end-code="<?= e($cInfo['end_code']) ?>"
                                    data-qr-prefix="<?= e($cInfo['qr_prefix']) ?>"
                                    data-seller-name="<?= e($cInfo['seller_name']) ?>"
                                    data-seller-id="<?= (int)$cInfo['seller_id'] ?>"
                                    data-is-comp="<?= (int)($cInfo['is_comp'] ?? 0) ?>"
                                    data-already-inwarded="<?= $isAlreadyInwarded ? '1' : '0' ?>"
                                    data-offset="<?= (int)($cInfo['offset'] ?? 0) ?>"
                                    data-start-seq="<?= (int)($cInfo['start_seq'] ?? 1) ?>"
                                    data-end-seq="<?= (int)($cInfo['end_seq'] ?? (int)$cInfo['stock']) ?>"
                                    <?= ($selectedCatalogCode === $cCode) ? 'selected' : '' ?>>
                                <?= e($cCode) ?><?= !empty($cInfo['name']) ? ' &bull; ' . e($cInfo['name']) : '' ?><?= !empty($cInfo['brand']) ? ' [' . e($cInfo['brand']) . ']' : '' ?> &bull; <?= (int)$cInfo['stock'] ?> pcs<?= $isAlreadyInwarded ? ' 🔒 (Already Inwarded)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-qr-code me-1 text-info"></i>Select Generated Code to automatically generate QR labels.
                    </div>
                </div>

                <!-- Hidden Selected Variant ID for form submission -->
                <input type="hidden" name="variant_id" id="tpVariantSelect" value="<?= (int)($selectedVariantId ?? 0) ?>">
                <?php if (!empty($activeVar)): ?>
                    <input type="hidden" name="brand_name" value="<?= e($selectedBrandName) ?>">
                    <input type="hidden" name="catalog_code" value="<?= e($selectedCatalogCode) ?>">
                <?php endif; ?>

                <!-- STOCK INWARD STATUS BANNER -->
                <div class="col-12" id="alreadyInwardedValidationBanner" style="display: <?= (!empty($activeVar) && in_array((int)$activeVar['id'], $inwardedVariantIds ?? [], true)) ? 'block' : 'none' ?>;">
                    <div class="alert alert-info border-info border-2 rounded-3 d-flex align-items-center gap-3 p-3 mb-0 shadow-sm" style="background-color: #f0f9ff;">
                        <i class="bi bi-box-seam-fill text-info fs-3 flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark"><i class="bi bi-info-circle-fill text-info me-1"></i>Stock Inwarded - Sequential QR Pack Extension Enabled</h6>
                            <p class="mb-0 small text-secondary">
                                Product <strong id="lblAlreadyInwardedCode" class="text-dark font-monospace"><?= e($selectedCatalogCode ?: 'Selected Code') ?></strong> has inwarded packs in stock. You can add additional sequential QR labels anytime using <strong>Add QR</strong> and inward them to warehouse inventory.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ZERO STOCK RESTRICTION ALERT BANNER -->
                <div class="col-12" id="zeroStockValidationBanner" style="display: <?= !empty($isZeroStockRestricted) ? 'block' : 'none' ?>;">
                    <div class="alert alert-danger border-danger border-2 rounded-3 d-flex align-items-center gap-3 p-3 mb-0 shadow-sm" style="background-color: #fff5f5;">
                        <i class="bi bi-slash-circle-fill text-danger fs-3 flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold mb-1 text-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>QR Generation Restricted — 0 Product Stock Count</h6>
                            <p class="mb-0 small text-danger-emphasis">
                                Selected SKU <strong><?= e($activeVar['sku'] ?? 'Item') ?></strong> has <strong>0 pcs</strong> in warehouse stock count. QR labels cannot be generated for out-of-stock items. Please choose a catalog product with positive stock count or inward stock first.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Target Warehouse (hidden, auto-pick first available) -->
                <input type="hidden" name="warehouse_id" value="<?= $targetWarehouseId ?>">

                <!-- DYNAMIC INFO PANEL: Dynamic 3-Tier Category Hierarchy, SKU Details & Series Batch Specs -->
                <div class="col-12" id="skuDynamicInfoPanel" style="display: none;">
                    <div class="card border rounded-3 p-3 bg-light shadow-xs">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary text-white px-2.5 py-1" style="font-size: 0.75rem;">
                                    <i class="bi bi-diagram-3 me-1"></i>3-Tier Category Hierarchy:
                                </span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="badgeDynamicCat">
                                    <i class="bi bi-folder-fill me-1"></i>Category: <strong id="lblDynamicCat">—</strong>
                                </span>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" id="badgeDynamicSubcat">
                                    <i class="bi bi-folder2 me-1"></i>Subcategory: <strong id="lblDynamicSubcat">—</strong>
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" id="badgeDynamicSubSubcat">
                                    <i class="bi bi-folder2-open me-1"></i>Sub-Subcategory: <strong id="lblDynamicSubSubcat">—</strong>
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle shadow-xs" id="badgeDynamicQrTypeBadge">
                                    <i class="bi bi-upc me-1"></i>Individual Product
                                </span>
                                <span class="badge bg-purple text-white shadow-xs" id="badgeDynamicSeriesMode" style="display: none; background-color: #6f42c1 !important;">
                                    <i class="bi bi-boxes me-1"></i>1 Master QR per Series Mode
                                </span>
                                <span class="badge bg-secondary-subtle text-dark border">
                                    <i class="bi bi-boxes me-1 text-success"></i>Current Stock: <strong id="lblDynamicStock">0 pcs</strong>
                                </span>
                            </div>
                        </div>

                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <div class="small text-muted">Product Style &amp; Code:</div>
                                <div class="fw-bold text-dark text-truncate" id="lblDynamicProductName">—</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">Size / Color:</div>
                                <div class="fw-bold text-secondary font-monospace" id="lblDynamicVariantAttr">—</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">MRP (Max Retail):</div>
                                <div class="fw-bold text-success font-monospace" id="lblDynamicMrp">₹0.00</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">Selling Price:</div>
                                <div class="fw-bold text-primary font-monospace" id="lblDynamicCost">₹0.00</div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted" id="lblDynamicSeriesTitle">Brand / Supplier:</div>
                                <div class="fw-bold text-dark text-truncate" id="lblDynamicBrand">—</div>
                                <div id="lblDynamicSeriesRangeBox" class="mt-1 small fw-bold font-monospace" style="display: none; color: #6f42c1;">
                                    <i class="bi bi-upc me-1"></i>Range: <span id="lblDynamicSeriesRangeText">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PO / Bill source (hidden) -->
                <input type="hidden" name="source_id" value="<?= (int)$selectedSourceId ?>">

                <!-- 4. QR Generation Mode Toggle -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-qr-code me-1 text-primary"></i>4. Label Mode
                    </label>
                    <div class="d-flex align-items-center gap-2 pt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qr_mode" id="qrModeIndividual" value="INDIVIDUAL" <?= ($qrMode !== 'SERIES_BATCH' && (empty($activeVar) || (($activeVar['qr_type'] ?? '') !== 'SERIES_BATCH' && empty($activeVar['is_complementary'])))) ? 'checked' : '' ?> onchange="onQrModeChange('INDIVIDUAL')" <?= !empty($activeVar) ? 'disabled' : '' ?>>
                            <label class="form-check-label small fw-semibold text-dark" for="qrModeIndividual">
                                Pieces
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qr_mode" id="qrModeSeries" value="SERIES_BATCH" <?= ($qrMode === 'SERIES_BATCH' || (!empty($activeVar) && (($activeVar['qr_type'] ?? '') === 'SERIES_BATCH' || !empty($activeVar['is_complementary'])))) ? 'checked' : '' ?> onchange="onQrModeChange('SERIES_BATCH')" <?= !empty($activeVar) ? 'disabled' : '' ?>>
                            <label class="form-check-label small fw-semibold" for="qrModeSeries" style="color: #6f42c1;">
                                Master QR
                            </label>
                        </div>
                    </div>
                    <?php if (!empty($activeVar)): ?>
                        <input type="hidden" name="qr_mode" value="<?= e(($qrMode === 'SERIES_BATCH' || ($activeVar['qr_type'] ?? '') === 'SERIES_BATCH' || !empty($activeVar['is_complementary'])) ? 'SERIES_BATCH' : 'INDIVIDUAL') ?>">
                    <?php endif; ?>
                </div>

                <!-- Dynamic QR Count / Number of Stickers -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-calculator text-primary me-1"></i>Dynamic QR Count <span class="badge bg-secondary-subtle text-secondary border ms-1" id="copiesLockBadge"><i class="bi bi-lock-fill me-1"></i>Locked</span>
                    </label>
                    <input type="number" name="copies" id="copiesInput" class="form-control form-control-sm fw-bold font-monospace text-center fs-6 border-secondary bg-light text-dark" value="<?= $copiesCount ?>" min="1" max="10000" readonly style="cursor: not-allowed; background-color: #f8f9fa !important;" title="Auto-set to current available warehouse stock count">
                    <div class="form-text text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-shield-lock-fill me-1 text-secondary"></i>Auto-locked (<span id="copiesStockHint"><?= $copiesCount ?></span> pcs).
                    </div>
                </div>

                <!-- Generate Action Button -->
                <div class="col-md-3 d-flex align-items-end">
                    <?php if (hasPermission('wh_labels', 'create')): ?>
                        <button type="submit" id="btnSubmitGenerateLabels" class="btn <?= !empty($isZeroStockRestricted) ? 'btn-secondary opacity-75' : 'btn-primary' ?> btn-sm w-100 fw-semibold py-2 shadow-sm" <?= !empty($isZeroStockRestricted) ? 'disabled title="Cannot generate QR labels: product stock is 0 pcs."' : '' ?>>
                            <i class="bi <?= !empty($isZeroStockRestricted) ? 'bi-slash-circle' : 'bi-qr-code' ?> me-1" id="btnSubmitIcon"></i>
                            <span id="btnSubmitLabelText"><?= !empty($isZeroStockRestricted) ? 'QR Restricted (0 Stock)' : 'Generate QR Labels' ?></span>
                        </button>
                    <?php endif; ?>
                <?php endif; // end catalog gate else ?>

            <?php endif; // end source_type if ?>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- GENERATED PIECE-BY-PIECE QR STICKERS GRID (Matching Image 2 UI) -->
<!-- ========================================================================= -->
<?php 
$hasSeriesGenerated = !empty($generatedItems) && !empty($generatedItems[0]['is_series']);
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="card-title mb-0 fw-bold">
                <?php if ($hasSeriesGenerated): ?>
                    <i class="bi bi-boxes me-2 text-primary"></i>
                    Master Series Bundle Pack QR Label
                    <span class="badge bg-purple text-white rounded-pill ms-2" id="masterPackTotalBadge" style="background-color: #6f42c1 !important;">
                        <?php
                        $mPackCount = count($generatedItems);
                        $mTotalPcs = array_sum(array_column($generatedItems, 'pack_quantity'));
                        echo $mPackCount . ' Master QR' . ($mPackCount > 1 ? 's' : '') . ' (' . $mTotalPcs . ' Total Pcs)';
                        ?>
                    </span>
                <?php else: ?>
                    <i class="bi bi-qr-code me-2 text-success"></i>
                    Individual Piece QR Codes &amp; Labels
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-2" id="totalPiecesBadge">
                        <?= $availableGeneratedCount ?> Available Pieces
                    </span>
                    <span class="badge bg-primary text-white rounded-pill ms-1" id="selectedPiecesBadge" style="display: none;">
                        <i class="bi bi-check2-square me-1"></i><span id="selectedCountText">0</span> Selected
                    </span>
                <?php endif; ?>
            </h5>
           
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm no-print" style="width: 200px;">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" id="filterTagInput" class="form-control" placeholder="Filter by Code or SKU..." onkeyup="filterRenderedStickers()">
            </div>
            <!-- Print Selected Stickers Button -->
            <?php if (!$isViewingExisting && (hasPermission('wh_labels', 'view') || hasPermission('wh_labels', 'print'))): ?>
                <button type="button" class="btn btn-sm btn-primary fw-semibold shadow-sm" id="btnPrintSelectedStickers" onclick="printSelectedStickers()" disabled title="Print only the selected checked stickers">
                    <i class="bi bi-check2-circle me-1"></i> Print Selected (<span id="btnSelectedCount">0</span>)
                </button>
            <?php endif; ?>
            <!-- Print All Stickers Button -->
            <?php if (hasPermission('wh_labels', 'view') || hasPermission('wh_labels', 'print')): ?>
            <button type="button" class="btn btn-sm btn-success fw-semibold shadow-sm" onclick="printAllGeneratedStickers()">
                <i class="bi bi-printer me-1"></i> Print All Stickers
            </button>
            <?php endif; ?>
          
            <?php if (hasPermission('wh_labels', 'view') || hasPermission('wh_labels', 'print')): ?>
            <button type="button" class="btn btn-sm btn-outline-dark fw-semibold shadow-sm" onclick="openPrinterSettingsModal()" title="Calibrate TSC TE244 printer alignment and dimensions">
                <i class="bi bi-sliders me-1 text-warning"></i> Printer Settings
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-4">
        <?php if (empty($generatedItems)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-qr-code fs-1 d-block mb-3 text-secondary"></i>
                <h5><?= $isViewingExisting ? 'No saved QR labels found for this product' : 'Select Brand &amp; SKU above to generate QR stickers' ?></h5>
                <p class="mb-0"><?= $isViewingExisting ? 'No previously generated QR stickers are available to view.' : 'Choose the Brand/Supplier and Variant/SKU to generate scannable QR price stickers and inward stock to warehouse inventory.' ?></p>
            </div>
        <?php else: ?>
            <!-- DYNAMIC SELECTION & QR ADJUSTMENT ACTION BAR -->
            <?php if (!$isViewingExisting): ?>
            <div class="p-3 mb-3 rounded-3 border bg-light shadow-xs no-print" id="qrSelectionAdjustmentBar">
                <div class="row g-2 align-items-center">
                    <!-- Selection Controls: Select All & Range Selection -->
                    <div class="col-lg-7 col-md-12 d-flex align-items-center gap-2 flex-wrap">
                        <div class="form-check d-flex align-items-center gap-2 mb-0 selection-toolbar-pill">
                            <input class="form-check-input mt-0" type="checkbox" id="selectAllPiecesChk" onchange="toggleSelectAllPieces(this.checked)" style="cursor: pointer;" <?= $availableGeneratedCount === 0 ? 'disabled' : '' ?>>
                            <label class="form-check-label fw-bold text-dark user-select-none mb-0" for="selectAllPiecesChk" style="cursor: pointer; font-size: 0.85rem;">
                                Select All (<span id="totalSelectableCount"><?= $availableGeneratedCount ?></span>)
                            </label>
                        </div>

                        <div class="input-group input-group-sm shadow-2xs" style="width: 310px; max-width: 100%;">
                            <span class="input-group-text bg-white fw-bold text-dark border-secondary-subtle">Range</span>
                            <input type="number" id="rangeSelectFrom" class="form-control text-center fw-bold border-secondary-subtle" placeholder="<?= !empty($generatedItems) ? $generatedItems[0]['piece_no'] : 1 ?>" min="1" max="<?= !empty($generatedItems) ? end($generatedItems)['piece_no'] : 1 ?>" style="flex: 0 0 72px; width: 72px; max-width: none;" <?= $availableGeneratedCount === 0 ? 'disabled' : '' ?>>
                            <span class="input-group-text bg-white fw-semibold text-muted border-secondary-subtle">to</span>
                            <input type="number" id="rangeSelectTo" class="form-control text-center fw-bold border-secondary-subtle" placeholder="<?= !empty($generatedItems) ? end($generatedItems)['piece_no'] : 1 ?>" min="1" max="<?= !empty($generatedItems) ? end($generatedItems)['piece_no'] : 1 ?>" style="flex: 0 0 72px; width: 72px; max-width: none;" <?= $availableGeneratedCount === 0 ? 'disabled' : '' ?>>
                            <button type="button" class="btn btn-outline-primary fw-bold px-2.5" onclick="selectPieceRange()" <?= $availableGeneratedCount === 0 ? 'disabled' : '' ?>>Select</button>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-secondary px-2.5 fw-semibold shadow-2xs" onclick="clearPieceSelection()" title="Deselect all">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </button>

                    </div>

                    <!-- New QR adjustment control -->
                    <div class="col-lg-5 col-md-12 d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                        <!-- Add consecutive QR labels or extend an aggregate pack -->
                        <?php if ($canEditWarehouseQr || $canCreateWarehouseQr): ?>
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold bg-white shadow-2xs" onclick="produceNewAdjustmentQR()" title="Add QR labels or extend the selected master pack">
                            <i class="bi bi-plus-circle-fill text-success me-1"></i> Add QR / Units
                        </button>
                        <?php endif; ?>
                        <?php if ($canDeleteWarehouseQr): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger fw-bold bg-white shadow-2xs" id="btnDeleteSelectedQrs" onclick="deleteSelectedQrs()" disabled title="Delete selected QR labels and record the reason">
                                <i class="bi bi-trash3 me-1"></i>Delete Selected QR(s)
                            </button>
                        <?php endif; ?>
                        <?php if ($canInwardQrUnits): ?>
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold bg-white shadow-2xs" id="btnQuickInwardSelected" onclick="confirmStickeredInwardAction(event)" title="Inward stickered product(s) to warehouse inventory stock" <?= $availableGeneratedCount === 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-box-arrow-in-down-right me-1"></i>Inward to Stock
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3" id="stickersCardGrid">
                <?php foreach ($generatedItems as $index => $item): ?>
                    <?php 
                        $displaySku = $item['clean_sku'] ?? (!empty($item['size_name']) && !empty($item['color_name']) ? ($item['size_name'] . '-' . $item['color_name']) : $item['sku']);
                        $mrpNum = (float)($item['mrp'] ?? 0);
                        $sellNum = (!empty($item['selling_price']) && (float)$item['selling_price'] > 0) ? (float)$item['selling_price'] : ((!empty($item['cost_price']) && (float)$item['cost_price'] > 0) ? (float)$item['cost_price'] : $mrpNum);
                        $fmtMrp = ($mrpNum == (int)$mrpNum) ? (int)$mrpNum : number_format($mrpNum, 2, '.', '');
                        $fmtSell = ($sellNum == (int)$sellNum) ? (int)$sellNum : number_format($sellNum, 2, '.', '');
                        $displayMrp = ($mrpNum == (int)$mrpNum) ? number_format($mrpNum) : number_format($mrpNum, 2);
                        $displaySell = ($sellNum == (int)$sellNum) ? number_format($sellNum) : number_format($sellNum, 2);
                        $qrPayload = $item['tag_code'];
                    ?>
                    <div class="col sticker-grid-col" id="col-piece-<?= $index ?>" data-code="<?= e(strtoupper($item['tag_code'])) ?>" data-sku="<?= e(strtoupper($displaySku)) ?>" data-piece-no="<?= $item['piece_no'] ?>">
                        <div class="card rounded-3 p-2.5 h-100 shadow-sm bg-white piece-card position-relative <?= !empty($item['is_series']) ? 'border-primary' : '' ?>" id="card-piece-<?= $index ?>">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <?php if (!empty($item['is_series'])): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!$isViewingExisting): ?>
                                        <input type="checkbox" class="form-check-input piece-select-chk mt-0" id="chk-piece-<?= $index ?>" data-index="<?= $index ?>" onchange="onPieceCheckChanged(<?= $index ?>)" style="cursor: pointer;" <?= !empty($item['is_inwarded']) ? 'disabled' : '' ?>>
                                        <?php endif; ?>
                                        <span class="badge bg-dark text-white font-monospace piece-label-badge" style="font-size: 0.68rem;"><i class="bi bi-boxes me-1"></i>Master Pack (<?= $item['pack_quantity'] ?> Pcs)</span>
                                    </div>
                                    <span class="badge bg-purple text-white" style="font-size: 0.68rem; background-color: #6f42c1 !important;"><i class="bi bi-qr-code me-1"></i><?= !empty($item['is_complementary']) ? 'Complementary Pack QR' : 'Series QR' ?></span>
                                    <?php if (!empty($item['is_inwarded'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">Inwarded QR</span>
                                    <?php elseif (!empty($item['is_saved_qr'])): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">Saved QR — Not Inwarded</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!$isViewingExisting): ?>
                                        <input type="checkbox" class="form-check-input piece-select-chk mt-0" id="chk-piece-<?= $index ?>" data-index="<?= $index ?>" onchange="onPieceCheckChanged(<?= $index ?>)" title="Select this QR sticker" <?= !empty($item['is_inwarded']) ? 'disabled' : '' ?>>
                                        <?php endif; ?>
                                        <label for="chk-piece-<?= $index ?>" class="badge bg-light text-dark border border-secondary-subtle font-monospace mb-0 user-select-none piece-label-badge" style="cursor: pointer;">
                                            Piece #<?= $item['piece_no'] ?> of <?= $item['total_count'] ?>
                                        </label>
                                    </div>
                                    <?php if (!empty($item['is_adjustment'])): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;"><i class="bi bi-tools me-1"></i>Adjustment</span>
                                    <?php elseif (!empty($item['is_inwarded'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">Inwarded QR</span>
                                    <?php elseif ($isViewingExisting || !empty($item['is_saved_qr'])): ?>
                                        <span class="badge <?= !empty($item['is_inwarded']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' ?>" style="font-size: 0.68rem;">
                                            <?= !empty($item['is_inwarded']) ? 'Inwarded QR' : 'Saved QR — Not Inwarded' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;"><i class="bi bi-check-circle me-1"></i>Available</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Modern Garment Tag Sticker Layout (Matching Production Unit Standard Reference) -->
                            <div class="p-2 border rounded-3 bg-white shadow-xs d-flex align-items-start gap-2 mb-2" style="background: #fafafa;">
                                <!-- Left: QR Code + Tag Code Badge -->
                                <div class="text-center flex-shrink-0" style="width: 80px;">
                                    <div class="piece-qr-box bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" id="qr-box-<?= $index ?>" data-json="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>" style="width: 72px; height: 72px;"></div>
                                    <div class="label-tag-code badge bg-white text-dark border font-monospace mt-1 px-1 py-0.5 w-100" style="font-size: 0.56rem; letter-spacing: 0.1px;" title="<?= e($item['tag_code']) ?>">
                                        <?= e($item['tag_code']) ?>
                                    </div>
                                    <?php 
                                    $rangeBadge = '';
                                    if (!empty($item['series_start_code']) && !empty($item['series_end_code'])) {
                                        $sCode = $item['series_start_code'];
                                        $eCode = $item['series_end_code'];
                                        if (preg_match('/^[A-Za-z]+\d{4}(\d+)$/', $sCode, $mS) && preg_match('/^[A-Za-z]+\d{4}(\d+)$/', $eCode, $mE)) {
                                            $rangeBadge = (int)$mS[1] . ' &rarr; ' . (int)$mE[1];
                                        } elseif (preg_match('/(\d+)$/', $sCode, $mS) && preg_match('/(\d+)$/', $eCode, $mE)) {
                                            $rangeBadge = (int)$mS[1] . ' &rarr; ' . (int)$mE[1];
                                        }
                                    }
                                    ?>
                                    <?php if ($rangeBadge): ?>
                                    <div class="badge bg-light text-primary border font-monospace mt-1 px-1 py-0.5 w-100 series-range-badge" style="font-size: 0.54rem;" title="Piece Sequence Range">Range: <?= $rangeBadge ?></div>
                                    <?php endif; ?>
                                </div>
                                <!-- Right: Brand, Clean Variant SKU, MRP, Disc (Matching Production Unit Standard) -->
                                <div class="overflow-hidden flex-grow-1" style="font-size: 0.76rem; margin-top: 1px;">
                                    <div class="lbl-title fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.80rem; letter-spacing: 0.2px;" title="<?= e($item['brand_name'] ?? 'GRM CLASSIC') ?>">
                                        <?= e($item['brand_name'] ?? 'GRM CLASSIC') ?>
                                    </div>
                                    <div class="lbl-sku fw-bold text-dark text-truncate mt-0.5" style="font-size: 0.84rem; color: #0f172a;" title="<?= e($displaySku) ?>">
                                        <?= e($displaySku) ?><?= !empty($item['is_series']) ? ' (' . $item['pack_quantity'] . ' Pcs)' : '' ?>
                                    </div>
                                    <div class="lbl-mrp text-muted small mt-0.5 text-decoration-line-through" style="font-size: 0.72rem;">
                                        MRP: ₹<?= $displayMrp ?>
                                    </div>
                                    <div class="lbl-disc fw-bold text-success mt-0.5" style="font-size: 0.82rem; color: #16a34a !important;">
                                        Disc: ₹<?= $displaySell ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons for This Piece -->
                            <div class="d-flex gap-1.5 mt-auto pt-2 border-top no-print">
                                <button type="button" 
                                        class="btn btn-xs btn-outline-primary flex-fill fw-semibold py-1.5" 
                                        onclick="previewSingleTag(<?= $index ?>)" title="Preview 2D QR Code Tag">
                                    <i class="bi bi-eye"></i> Preview
                                </button>
                                <button type="button" 
                                        class="btn btn-xs btn-outline-dark flex-fill fw-semibold py-1.5" 
                                        onclick="printSinglePieceSticker(<?= $index ?>)" title="Print Thermal Sticker">
                                    <i class="bi bi-printer"></i> Print
                                </button>
                                <?php if (!empty($item['is_series']) && $canEditWarehouseQr && (empty($item['is_inwarded']) || $canInwardQrUnits)): ?>
                                    <button type="button"
                                            class="btn btn-xs btn-outline-success flex-fill fw-semibold py-1.5"
                                            onclick="produceNewAdjustmentQR(<?= $index ?>)"
                                            title="Edit pack quantity and add units">
                                        <i class="bi bi-pencil-square"></i> Edit Pack
                                    </button>
                                <?php endif; ?>
                                <?php if ($canDeleteWarehouseQr && empty($item['is_inwarded'])): ?>
                                    <button type="button"
                                            class="btn btn-xs btn-outline-danger flex-fill fw-semibold py-1.5"
                                            onclick="deleteSingleQr(<?= $index ?>)"
                                            title="Delete this QR label">
                                        <i class="bi bi-trash3"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php if ($canDeleteWarehouseQr): ?>
<div class="modal fade" id="deleteQrConfirmModal" tabindex="-1" aria-labelledby="deleteQrConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="deleteQrConfirmTitle">
                    <i class="bi bi-trash3 me-2"></i>Delete QR Labels
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning py-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Confirm deletion of <strong id="deleteQrSelectedCount">0</strong> QR label(s).
                    Inwarded labels are protected and cannot be deleted.
                </div>
                <label for="deleteQrReason" class="form-label fw-semibold">Reason for deletion <span class="text-danger">*</span></label>
                <textarea id="deleteQrReason" class="form-control" rows="3" maxlength="500" required
                          placeholder="Explain why these QR labels should be deleted."></textarea>
                <div class="form-text">The reason and affected QR codes will be stored in the audit log.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger fw-bold" id="confirmDeleteQrButton" onclick="confirmDeleteSelectedQrs()">
                    <i class="bi bi-trash3 me-1"></i>Confirm Delete
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($currentSourceType, ['po', 'tp_purchase']) && !empty($selectedSourceId) && !empty($poInfo) && empty($selectedVariantId)): 
    $isPoDraft = ($poInfo['status'] === 'DRAFT' || $poInfo['status'] === 'PENDING');
    $firstItem = !empty($generatedItems) ? $generatedItems[0] : null;
    $lastItem = !empty($generatedItems) ? end($generatedItems) : null;
    $tagRangeDisplay = ($firstItem && $lastItem) ? ($firstItem['tag_code'] . ' &rarr; ' . $lastItem['tag_code']) : 'None';
?>
<!-- ========================================================================= -->
<!-- STEP 3 ACTION BANNER: PO QR GENERATION & CONFIRM OFFICIAL STOCK INWARD -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4 rounded-3 border-start border-4 <?= $isPoDraft ? 'border-warning' : 'border-success' ?> no-print mt-3" style="background: <?= $isPoDraft ? 'linear-gradient(135deg, #fffbeb 0%, #ffffff 100%)' : 'linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%)' ?>;" id="poInwardActionBanner">
    <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="badge <?= $isPoDraft ? 'bg-warning text-dark' : 'bg-success' ?> px-3 py-1 fs-7 shadow-xs fw-bold">
                    <i class="bi <?= $isPoDraft ? 'bi-hourglass-split' : 'bi-check-circle-fill' ?> me-1"></i>
                    <?= $isPoDraft ? 'Step 3: Awaiting Official Stock Inward' : 'Stock Officially Inwarded (GRN Generated)' ?>
                </span>
                <span class="badge bg-primary font-monospace fs-7"><?= e($poInfo['po_number']) ?></span>
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-qr-code me-1 text-primary"></i> <?= count($generatedItems) ?> QR Stickers Generated
                </span>
                <?php if ($isPoDraft): ?>
                    <span class="badge bg-danger text-white">
                        <i class="bi bi-shield-exclamation me-1"></i>Stock NOT added yet
                    </span>
                <?php else: ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-check2-all me-1"></i>Live Inventory Updated
                    </span>
                <?php endif; ?>
            </div>
            <h4 class="fw-bold mb-1 text-dark">
                <?= e($poInfo['seller_company'] ?: $poInfo['seller_name'] ?: 'Supplier Bill') ?>
                <span class="text-muted fw-normal fs-6 ms-2">(PO: <strong class="text-dark font-monospace"><?= e($poInfo['po_number']) ?></strong> &bull; <?= count($poItems) ?> Product(s), <?= $poTotalQty ?> Total Pcs)</span>
            </h4>
            
            <?php if (!empty($activeVar)): ?>
            <div class="mt-2 p-2 px-3 rounded-2 bg-white border d-inline-flex align-items-center gap-3 flex-wrap shadow-xs">
                <span class="fw-bold text-dark"><i class="bi bi-box-seam text-primary me-1"></i>Product: <strong><?= e($activeVar['product_name']) ?></strong></span>
                <span class="text-secondary small font-monospace">SKU: <strong><?= e($activeVar['sku'] ?? 'N/A') ?></strong> (<?= e($activeVar['size_name'] ?? 'Free') ?> / <?= e($activeVar['color_name'] ?? 'Std') ?>)</span>
                <span class="badge bg-light text-dark border"><i class="bi bi-tags-fill me-1 text-warning-emphasis"></i> QR Range: <?= $tagRangeDisplay ?></span>
                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-layers-fill me-1"></i> Stickered Qty: +<?= count($generatedItems) ?> Pcs</span>
            </div>
            <?php endif; ?>

            <div class="text-muted small d-flex align-items-center gap-3 flex-wrap mt-2">
                <span><i class="bi bi-building text-primary me-1"></i> Destination: <strong><?= e($poInfo['warehouse_name'] ?? 'Warehouse #' . $poInfo['warehouse_id']) ?></strong></span>
                <span><i class="bi bi-calendar-event text-secondary me-1"></i> Bill Date: <strong><?= e($poInfo['order_date']) ?></strong></span>
                <span><i class="bi bi-currency-rupee text-success me-1"></i> Bill Total: <strong>₹<?= number_format((float)$poInfo['total_amount'], 2) ?></strong></span>
                <?php if ($isPoDraft): ?>
                    <span class="text-warning-emphasis fw-bold"><i class="bi bi-arrow-down-circle me-1"></i>Generate &amp; print QR labels below &rarr; Click button to inward stock</span>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <?php if ($isPoDraft): ?>
                <?php if (hasPermission('purchases', 'receive')): ?>
                    <form method="POST" action="<?= url('warehouse/sellers/inward-bill/' . $selectedSourceId) ?>" onsubmit="return confirm('Confirm Stock Inward: This will officially add all products (+<?= $poTotalQty ?> pcs) from PO #<?= e($poInfo['po_number']) ?> into <?= e($poInfo['warehouse_name'] ?? 'Warehouse') ?> stock and generate GRN. Proceed?');">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="warehouse_id" value="<?= (int)$poInfo['warehouse_id'] ?>">
                        <button type="submit" class="btn btn-success btn-lg fw-bold shadow px-4 py-2.5">
                            <i class="bi bi-box-arrow-in-down me-2 fs-5"></i> Inward to Warehouse Stock Now (+<?= $poTotalQty ?> Pcs)
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary py-1 px-3 mb-0 small">Admin permission required to confirm inward.</div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (hasPermission('wh_sellers', 'view')): ?>
                    <a href="<?= url('warehouse/sellers/view/' . ($poInfo['warehouse_supplier_id'] ?: $poInfo['seller_id'])) ?>" class="btn btn-outline-success fw-bold">
                        <i class="bi bi-check-circle me-1"></i> View Supplier Inward Record
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php elseif (!$isViewingExisting && in_array($currentSourceType, ['po', 'tp_purchase', 'returns']) && !empty($generatedItems) && !empty($selectedVariantId)):
    if (empty($activeVar)) {
        $lookupPool = array_merge($stockVariants ?? [], $thirdPartyVariants ?? []);
        foreach ($lookupPool as $sv) {
            if ($sv['id'] == $selectedVariantId) {
                $activeVar = $sv;
                break;
            }
        }
    }
    if (empty($activeVar)) {
        $activeVar = Database::fetchOne(
            "SELECT pv.*, p.name as product_name, p.code as product_code, p.qr_code_prefix,
                    p.category_code, p.subcategory_code, p.sub_subcategory_code,
                    p.category_id, p.subcategory_id, p.sub_subcategory_id,
                    p.brand_id, b.name as brand_name,
                    p.source_type, p.warehouse_supplier_id as seller_id, sz.name as size_name, c.name as color_name,
                    COALESCE(cat.name, c_old.name, '') as category_name, 
                    COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                    COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN brands b ON p.brand_id = b.id
             LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
             LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
             LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
             LEFT JOIN categories c_old ON p.category_id = c_old.id
             LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
             LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.id = ?",
            [$selectedVariantId]
        );
    }
    $activeWh = null;
    foreach ($warehouses as $wh) {
        if ($wh['id'] == $targetWarehouseId) {
            $activeWh = $wh;
            break;
        }
    }
    if (!$activeWh && !empty($warehouses)) $activeWh = $warehouses[0];

?>
<!-- ========================================================================= -->
<!-- STEP 3: CONFIRM STICKERED PRODUCTS & UPDATE INVENTORY STOCK (STANDALONE ONLY) -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4 rounded-3 border-start border-4 <?= $isFullyInwarded ? 'border-primary' : ($isPartiallyInwarded ? 'border-warning' : 'border-success') ?> no-print mt-3" style="background: linear-gradient(135deg, <?= $isFullyInwarded ? '#f8fafc' : ($isPartiallyInwarded ? '#fffdf5' : '#f0fdf4') ?> 0%, #ffffff 100%);" id="confirmInwardSection">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <?php 
                $activeCategoryType = strtoupper($activeVar['warehouse_category_type'] ?? '');
                $actIsComp = !empty($activeVar['is_complementary']) || !empty($activeVar['product_is_comp']) || $activeCategoryType === 'COMPLEMENTARY';
                $actIsSeries = (!empty($generatedItems[0]['is_series']) || strtoupper($activeVar['qr_type'] ?? '') === 'SERIES_BATCH' || strtoupper($activeVar['qr_type'] ?? '') === 'SERIES' || $activeCategoryType === 'SERIES');
                    $isSeriesOrComp = ($actIsComp || $actIsSeries);

                $aggregateTotalQty = 0;
                $aggregateInwardedQty = 0;
                $aggregatePendingQty = 0;
                if ($isSeriesOrComp && $currentSourceType !== 'returns') {
                    foreach ($generatedItems as $generatedItem) {
                        if (empty($generatedItem['is_series']) && empty($generatedItem['is_complementary'])) {
                            continue;
                        }
                        $itemPackQty = max(1, (int)($generatedItem['pack_quantity'] ?? 1));
                        $aggregateTotalQty += $itemPackQty;
                        if (!empty($generatedItem['is_inwarded'])) {
                            $aggregateInwardedQty += $itemPackQty;
                        } else {
                            $aggregatePendingQty += $itemPackQty;
                        }
                    }
                }
                if ($aggregateTotalQty > 0) {
                    $alreadyInwardedQty = $aggregateInwardedQty;
                    $totalExpectedQty = $aggregateTotalQty;
                    $remainingQty = $aggregatePendingQty;
                    $isFullyInwarded = ($aggregatePendingQty === 0);
                    $isPartiallyInwarded = ($aggregatePendingQty > 0 && $aggregateInwardedQty > 0);
                }

                    // For Series & Complementary products: inward the full pack/remaining count in a single action!
                    // For Normal products: inward 1 piece per sticker (count($generatedItems))
                $inwardPieceQty = $isSeriesOrComp
                    ? ($aggregateTotalQty > 0 ? $aggregatePendingQty : ($remainingQty > 0 ? $remainingQty : (!empty($activeVar['pack_quantity']) && (int)$activeVar['pack_quantity'] > 1 ? (int)$activeVar['pack_quantity'] : $totalExpectedQty)))
                        : ($remainingQty > 0 ? $remainingQty : count($generatedItems));
                if ($isDirectPurchaseReturn && !empty($selectedPurchaseReturnItem)) {
                    $totalExpectedQty = (int)$selectedPurchaseReturnItem['quantity'];
                    $alreadyInwardedQty = !empty($selectedPurchaseReturnItem['is_inwarded']) ? $totalExpectedQty : 0;
                    $remainingQty = max(0, $totalExpectedQty - $alreadyInwardedQty);
                    $isFullyInwarded = $totalExpectedQty > 0 && $remainingQty === 0;
                    $isPartiallyInwarded = $alreadyInwardedQty > 0 && !$isFullyInwarded;
                    $inwardPieceQty = $remainingQty;
                } elseif (
                    $currentSourceType === 'returns'
                    && $isSeriesOrComp
                    && !empty($selectedWarehouseReturnItem)
                ) {
                    $totalExpectedQty = (int)$selectedWarehouseReturnItem['returned_qty'];
                    $alreadyInwardedQty = 0;
                    $remainingQty = $totalExpectedQty;
                    $isFullyInwarded = false;
                    $isPartiallyInwarded = false;
                    $inwardPieceQty = $totalExpectedQty;
                }
                ?>
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-success px-3 py-1 fs-7 shadow-xs">
                        <i class="bi <?= $currentSourceType === 'returns' ? 'bi-arrow-return-left' : 'bi-box-seam-fill' ?> me-1"></i>
                        <?= $currentSourceType === 'returns' ? 'Official Return Stock Inward' : 'Official Inventory Stock Inward' ?>
                    </span>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-qr-code-scan me-1 text-primary"></i> <span id="lblStickersGeneratedCount"><?= count($generatedItems) ?></span> QR Stickers Generated
                    </span>
                    <?php if ($isFullyInwarded): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle" id="inwardStatusBadge">
                            <i class="bi bi-check-circle-fill me-1"></i> <?= $currentSourceType === 'returns' ? 'Returned Stock Officially Inwarded' : 'Stock Officially Inwarded' ?> (<?= $alreadyInwardedQty ?> pcs)
                        </span>
                    <?php elseif ($isPartiallyInwarded): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" id="inwardStatusBadge">
                            <i class="bi bi-hourglass-split me-1"></i> Partially Inwarded (<?= $alreadyInwardedQty ?> of <?= $totalExpectedQty ?> Pcs in Stock) — Ready to Inward Remaining <?= $inwardPieceQty ?> Pcs
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" id="inwardStatusBadge">
                            <i class="bi bi-hourglass-split me-1"></i> Stock NOT credited yet — Click button to inward <?= $inwardPieceQty ?> Pcs to inventory
                        </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <h4 class="fw-bold mb-0 text-dark">
                        <?= e($activeVar['product_name'] ?? 'Selected Product') ?>
                        <span class="text-muted fw-normal fs-6 ms-1">(<?= e($activeVar['size_name'] ?? 'Free') ?> / <?= e($activeVar['color_name'] ?? 'Std') ?>)</span>
                    </h4>
                    <?php if ($currentSourceType === 'returns'): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1" style="font-size: 0.74rem;">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Customer Return Restock
                        </span>
                    <?php elseif ($actIsComp): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1" style="font-size: 0.74rem;">
                            <i class="bi bi-gift-fill me-1"></i>Complementary Product
                        </span>
                    <?php elseif ($actIsSeries): ?>
                        <span class="badge border px-2.5 py-1" style="background-color: #f3e8ff; color: #6f42c1; border-color: #d8b4fe; font-size: 0.74rem;">
                            <i class="bi bi-boxes me-1"></i>Series Product<?= !empty($activeVar['pack_quantity']) && $activeVar['pack_quantity'] > 1 ? ' (' . (int)$activeVar['pack_quantity'] . ' Pcs Pack)' : '' ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1" style="font-size: 0.74rem;">
                            <i class="bi bi-upc me-1"></i>Individual Product
                        </span>
                    <?php endif; ?>
                </div>
                <?php 
                $activeHierarchy = array_filter([$activeVar['category_name'] ?? '', $activeVar['subcategory_name'] ?? '', $activeVar['sub_subcategory_name'] ?? '']);
                if (!empty($activeHierarchy)):
                ?>
                    <div class="mt-1 mb-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2.5 py-1">
                            <i class="bi bi-diagram-3-fill me-1 text-warning-emphasis"></i><?= e(implode(' › ', $activeHierarchy)) ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="text-muted small d-flex align-items-center gap-3 flex-wrap mt-2">
                    <span><i class="bi bi-building text-primary me-1"></i> Inward Warehouse: <strong><?= e($activeWh['name'] ?? 'Main Warehouse') ?></strong></span>
                    <span><i class="bi bi-boxes text-success me-1"></i> <?= $currentSourceType === 'returns' ? 'Return Inward Progress' : 'Inwarded Stock' ?>: <strong id="liveCurrentWhStock" class="badge bg-secondary-subtle text-dark fs-7"><?= number_format($alreadyInwardedQty) ?> / <?= $totalExpectedQty ?> pcs</strong></span>
                    <span><i class="bi bi-tags-fill text-warning-emphasis me-1"></i> QR Tag Range: <strong class="font-monospace text-dark" id="inwardTagRangeText"><?= e($generatedItems[0]['tag_code'] ?? '') ?> &rarr; <?= e(end($generatedItems)['tag_code'] ?? '') ?></strong></span>
                    <span><i class="bi bi-currency-rupee text-success me-1"></i> MRP: <strong>₹<?= number_format($activeVar['mrp'] ?? 0, 2) ?></strong></span>
                </div>
            </div>

            <?php 
            $canConfirmInward = ($currentSourceType === 'returns') 
                ? (hasPermission('returns', 'classify') || hasPermission('returns', 'receive') || hasPermission('wh_labels', 'create') || hasPermission('inward', 'create') || hasPermission('inward', 'verify') || hasPermission('purchases', 'receive') || (function_exists('isAdmin') && isAdmin()))
                : (hasPermission('wh_labels', 'create') || hasPermission('inward', 'create') || hasPermission('inward', 'verify') || hasPermission('purchases', 'receive') || (function_exists('isAdmin') && isAdmin()));
            ?>
            <?php if ($canConfirmInward): ?>
            <div class="d-flex align-items-center gap-2">
                <form id="formConfirmInward" onsubmit="confirmStickeredInwardAction(event)">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="variant_id" id="inwardVariantId" value="<?= (int)$selectedVariantId ?>">
                    <input type="hidden" name="warehouse_id" id="inwardWarehouseId" value="<?= (int)($activeWh['id'] ?? 1) ?>">
                    <input type="hidden" name="quantity" id="inwardQtyInput" value="<?= $inwardPieceQty ?>">
                    <input type="hidden" name="seller_id" id="inwardSellerId" value="<?= (int)$selectedSellerId ?>">
                    <input type="hidden" name="source_id" id="inwardSourceId" value="<?= (int)$selectedSourceId ?>">
                    <input type="hidden" name="return_inward" value="<?= $currentSourceType === 'returns' ? '1' : '0' ?>">
                    <input type="hidden" name="return_source" value="<?= $isDirectPurchaseReturn ? 'purchase' : '' ?>">
                    <input type="hidden" name="return_item_id" value="<?= $isDirectPurchaseReturn ? (int)$selectedReturnItemId : 0 ?>">
                    <input type="hidden" name="tag_codes" id="inwardTagCodesInput" value="<?= htmlspecialchars(json_encode(array_column($generatedItems, 'tag_code')), ENT_QUOTES, 'UTF-8') ?>">
                    
                    <?php 
                    $hasUninwarded = false;
                    foreach ($generatedItems as $gi) {
                        if (empty($gi['is_inwarded'])) {
                            $hasUninwarded = true;
                            break;
                        }
                    }
                    ?>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="submit" id="btnConfirmInwardSubmit" class="btn <?= (!$hasUninwarded) ? 'btn-secondary opacity-75' : 'btn-success' ?> btn-lg shadow fw-bold px-4 py-3 d-flex align-items-center gap-2" <?= (!$hasUninwarded) ? 'disabled' : '' ?>>
                            <i class="bi <?= (!$hasUninwarded) ? 'bi-check-circle-fill' : 'bi-box-arrow-in-down-right' ?> fs-4" id="btnInwardSubmitIcon"></i>
                            <span id="btnInwardSubmitText">
                                <?php if (!$hasUninwarded): ?>
                                    All <?= $alreadyInwardedQty ?> Pieces Officially Inwarded (1-Time Completed)
                                <?php elseif ($currentSourceType === 'returns'): ?>
                                    Confirm Return QR &amp; Inward to Inventory (+<?= $inwardPieceQty ?> Pcs)
                                <?php elseif ($isPartiallyInwarded): ?>
                                    Confirm Stickered &amp; Inward Remaining (+<?= $inwardPieceQty ?> Pcs)
                                <?php else: ?>
                                    Confirm Stickered &amp; Inward to Inventory (+<?= $inwardPieceQty ?> Pcs)
                                <?php endif; ?>
                            </span>
                        </button>
                        <a href="<?= url('warehouse/labels' . ($historyTab !== 'warehouse' ? '?history_tab=' . $historyTab : '')) ?>" 
                           class="btn btn-outline-secondary btn-lg fw-semibold px-4 py-3 d-flex align-items-center gap-2" 
                           onclick="return handleBackToBatches(event)">
                            <i class="bi bi-arrow-left fs-4"></i>
                            <span>Back to Batches</span>
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
</div><!-- end #qrGenerationArea -->

<!-- ========================================================================= -->
<!-- MODAL: 2D QR CODE TAG PREVIEW (Exact Image 3 Layout) -->
<!-- ========================================================================= -->
<div class="modal fade" id="singleQRModal" tabindex="-1" aria-labelledby="singleQRModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-white py-3 border-bottom">
                <h5 class="modal-title fw-bold text-dark" id="singleQRModalLabel">
                    <i class="bi bi-qr-code me-2 text-primary"></i>2D QR Code Tag Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center bg-light">
                <div class="p-3 border rounded-3 bg-white d-inline-block shadow-sm mb-3 text-start position-relative" style="width: 290px; min-height: 120px;">
                    <div class="d-flex align-items-start gap-2.5">
                        <div class="text-center flex-shrink-0">
                            <div id="singleQRCanvas" class="bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" style="width: 78px; height: 78px;"></div>
                            <div class="badge bg-white text-dark border font-monospace mt-1 px-1.5 py-1 w-100 text-truncate" id="previewDamageCode" style="font-size: 0.72rem; letter-spacing: 0.3px;">TTS092601</div>
                        </div>
                        <div class="overflow-hidden ms-1 flex-grow-1" style="margin-top: 2px;">
                            <div class="fw-bold text-uppercase text-dark text-truncate" id="previewBrandName" style="font-size: 0.82rem; letter-spacing: 0.3px;">GRM WAREHOUSE</div>
                            <div class="fw-bold text-secondary text-truncate" id="previewAttrText" style="font-size: 0.80rem;">4XL-BLUE</div>
                            <div class="text-muted small text-decoration-line-through" id="previewBasePrice" style="font-size: 0.76rem;">MRP: ₹1000</div>
                            <div class="fw-bold text-success" id="previewDiscPrice" style="font-size: 0.84rem;">Disc: ₹800</div>
                        </div>
                    </div>
                </div>
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1 text-primary"></i>Scannable 2D QR Code containing SKU, Style, Size, Color &amp; Price payload.
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
                <?php if (hasPermission('wh_labels', 'view') || hasPermission('wh_labels', 'print') || (function_exists('isAdmin') && isAdmin())): ?>
                    <button type="button" class="btn btn-success px-4 fw-semibold shadow-sm" id="btnSinglePrintModal" onclick="printSinglePieceFromModal()">
                        <i class="bi bi-printer-fill me-1"></i> Print This Tag
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ADD QR LABELS OR EXTEND AN EXISTING MASTER PACK -->
<!-- ========================================================================= -->
<div class="modal fade" id="adjustmentQrModal" tabindex="-1" aria-labelledby="adjustmentQrModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #0f766e, #2563eb);">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="adjustmentQrModalTitle">
                        <i class="bi bi-qr-code me-2"></i>Adjust QR Pack
                    </h5>
                    <div class="small text-white-50" id="adjustmentQrModalSubtitle">Add labels or units to this product.</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="adjustmentQrForm" onsubmit="submitAdjustmentQrChange(event)">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light border mb-4">
                        <span class="rounded-circle bg-white border text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px;">
                            <i class="bi bi-box-seam fs-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate" id="adjustmentQrProductName">Product</div>
                            <div class="small text-muted">QR: <span class="font-monospace" id="adjustmentQrCode">—</span></div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border ms-auto flex-shrink-0">
                            Current: <span id="adjustmentQrCurrentQty">1</span>
                        </span>
                    </div>

                    <label class="form-label fw-semibold" for="adjustmentQrCount" id="adjustmentQrCountLabel">Quantity to add</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="bi bi-plus-lg text-success"></i></span>
                        <input type="number" class="form-control fw-bold text-center" id="adjustmentQrCount" min="1" max="500" step="1" value="1" required>
                        <span class="input-group-text bg-white">units</span>
                    </div>
                    <div class="form-text mt-2">Enter a whole number from 1 to 500.</div>

                    <div class="alert alert-info d-flex gap-2 align-items-start mt-3 mb-0 py-2" role="status">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div class="small" id="adjustmentQrStockNote">A new QR label will be generated for each requested unit.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 fw-semibold shadow-sm" id="adjustmentQrSubmit">
                        <i class="bi bi-check2-circle me-1"></i>Apply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TSC TE244 PRINTER SETTINGS & LIVE DUAL-STICKER PREVIEW MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="printerSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 1280px; width: 95%;">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2 text-warning"></i>TSC TE244 Printer &amp; Dynamic Label Layout Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- Left Side: Interactive Controls -->
                    <div class="col-lg-5 col-md-6">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-bold py-2"><i class="bi bi-bounding-box me-1 text-primary"></i>1. Sticker Dimensions</div>
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Sticker Width (mm)</label>
                                        <input type="number" id="cfgWidth" class="form-control form-control-sm" value="40" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Sticker Height (mm)</label>
                                        <input type="number" id="cfgHeight" class="form-control form-control-sm" value="25" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Roll Columns</label>
                                        <select id="cfgColumns" class="form-select form-select-sm" onchange="updateLivePreview()">
                                            <option value="2">2 Columns (Dual Roll)</option>
                                            <option value="1">1 Column (Single Roll)</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Row Gap (mm)</label>
                                        <input type="number" id="cfgGap" class="form-control form-control-sm" value="2" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label small fw-semibold mb-1 text-primary"><i class="bi bi-arrow-left-right me-1"></i>2nd Sticker (Right Column) X Shift (dots)</label>
                                        <input type="number" id="cfgCol2ShiftDots" class="form-control form-control-sm border-primary" value="0" placeholder="e.g. -10 to move left, +10 to move right" oninput="updateLivePreview()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-bold py-2"><i class="bi bi-qr-code me-1 text-success"></i>2. 2D QR Code &amp; Tag Code Position</div>
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold mb-1">QR X (dots)</label>
                                        <input type="number" id="cfgQrX" class="form-control form-control-sm" value="12" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold mb-1">QR Y (dots)</label>
                                        <input type="number" id="cfgQrY" class="form-control form-control-sm" value="35" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold mb-1">QR Cell Size</label>
                                        <input type="number" id="cfgQrCellSize" class="form-control form-control-sm" value="3" min="1" max="6" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Tag Code X (dots)</label>
                                        <input type="number" id="cfgCodeX" class="form-control form-control-sm" value="12" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Tag Code Y (dots)</label>
                                        <input type="number" id="cfgCodeY" class="form-control form-control-sm" value="125" oninput="updateLivePreview()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-bold py-2"><i class="bi bi-fonts me-1 text-info"></i>3. Text &amp; Attributes Position</div>
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Text Left X (dots)</label>
                                        <input type="number" id="cfgTextX" class="form-control form-control-sm" value="115" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Font Code</label>
                                        <select id="cfgTextFont" class="form-select form-select-sm" onchange="updateLivePreview()">
                                            <option value="2">Font 2 (Clean/Standard)</option>
                                            <option value="1">Font 1 (Compact/Small)</option>
                                            <option value="3">Font 3 (Bold/Medium)</option>
                                        </select>
                                    </div>
                                    <div class="col-3 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Brand Y</label>
                                        <input type="number" id="cfgTitleY" class="form-control form-control-sm" value="35" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-3 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Size/Color Y</label>
                                        <input type="number" id="cfgSkuY" class="form-control form-control-sm" value="62" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-3 mt-2">
                                        <label class="form-label small fw-semibold mb-1">MRP Y</label>
                                        <input type="number" id="cfgMrpY" class="form-control form-control-sm" value="90" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-3 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Disc Y</label>
                                        <input type="number" id="cfgDiscY" class="form-control form-control-sm" value="118" oninput="updateLivePreview()">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Real-Time Dual Sticker Preview Box -->
                    <div class="col-lg-7 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white fw-bold py-2 d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-display me-1 text-primary"></i>Live Thermal Sticker Output Preview</span>
                                <span class="badge bg-dark" id="previewDimensionBadge">Dual Roll 40mm × 25mm</span>
                            </div>
                            <div class="card-body p-4 d-flex align-items-center justify-content-center bg-secondary-subtle">
                                <div class="d-flex align-items-center gap-3" id="liveStickersPreviewContainer">
                                    <!-- Sticker 1 Preview Box -->
                                    <div class="border border-2 border-dark rounded-2 bg-white shadow-sm position-relative overflow-hidden" id="liveStickerCard1" style="width: 240px; height: 150px;">
                                        <div class="badge bg-light text-dark position-absolute top-0 start-0 m-1 border" style="font-size: 0.60rem;">Left Sticker #1</div>
                                        <div id="prevQRBox1" class="position-absolute bg-white border d-flex align-items-center justify-content-center text-dark font-monospace" style="left: 12px; top: 35px; width: 45px; height: 45px; font-size: 0.65rem;">QR</div>
                                        <div id="prevCodeTxt1" class="position-absolute badge bg-white text-dark border font-monospace" style="left: 12px; top: 85px; font-size: 0.60rem;">TTS092601</div>
                                        <div id="prevTitleTxt1" class="position-absolute fw-bold text-uppercase text-dark font-monospace" style="left: 68px; top: 35px; font-size: 0.70rem;">GRM WAREHOUSE</div>
                                        <div id="prevSkuTxt1" class="position-absolute fw-bold text-secondary font-monospace" style="left: 68px; top: 52px; font-size: 0.68rem;">4XL-BLUE</div>
                                        <div id="prevMrpTxt1" class="position-absolute text-muted font-monospace" style="left: 68px; top: 70px; font-size: 0.62rem;">MRP: 1000</div>
                                        <div id="prevDiscTxt1" class="position-absolute fw-bold text-success font-monospace" style="left: 68px; top: 88px; font-size: 0.70rem;">Disc: 800</div>
                                    </div>

                                    <!-- Sticker 2 Preview Box -->
                                    <div class="border border-2 border-dark rounded-2 bg-white shadow-sm position-relative overflow-hidden" id="liveStickerCard2" style="width: 240px; height: 150px;">
                                        <div class="badge bg-light text-dark position-absolute top-0 start-0 m-1 border" id="badgeSticker2" style="font-size: 0.60rem;">Right Sticker #2</div>
                                        <div id="prevQRBox2" class="position-absolute bg-white border d-flex align-items-center justify-content-center text-dark font-monospace" style="left: 12px; top: 35px; width: 45px; height: 45px; font-size: 0.65rem;">QR</div>
                                        <div id="prevCodeTxt2" class="position-absolute badge bg-white text-dark border font-monospace" style="left: 12px; top: 85px; font-size: 0.60rem;">TTS092602</div>
                                        <div id="prevTitleTxt2" class="position-absolute fw-bold text-uppercase text-dark font-monospace" style="left: 68px; top: 35px; font-size: 0.70rem;">GRM WAREHOUSE</div>
                                        <div id="prevSkuTxt2" class="position-absolute fw-bold text-secondary font-monospace" style="left: 68px; top: 52px; font-size: 0.68rem;">4XL-BLUE</div>
                                        <div id="prevMrpTxt2" class="position-absolute text-muted font-monospace" style="left: 68px; top: 70px; font-size: 0.62rem;">MRP: 1000</div>
                                        <div id="prevDiscTxt2" class="position-absolute fw-bold text-success font-monospace" style="left: 68px; top: 88px; font-size: 0.70rem;">Disc: 800</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary" onclick="testPrintCurrentSettings()">
                    <i class="bi bi-printer me-1"></i> Test Print 1 Sticker
                </button>
                <button type="button" class="btn btn-success fw-semibold shadow-sm px-4" onclick="savePrinterSettings()">
                    <i class="bi bi-check2-circle me-1"></i> Save Calibration Settings
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- BROWSER PRINT SHEET MODAL (Fallback when local port 9191 is unavailable) -->
<!-- ========================================================================= -->
<div class="modal fade" id="warehousePrintSheetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-printer me-1 text-warning fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0">Thermal Sticker Print Sheet Preview</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="modalPrintAreaWrapper">
                <div class="stock-stickers-sheet" id="modalPrintArea"></div>
            </div>
            <div class="modal-footer bg-white border-top py-2 d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i>Compact dual-column thermal sticker cards (TSC TE244 / 40mm × 25mm layout)
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary px-4 fw-semibold shadow-sm" onclick="window.print()">
                        <i class="bi bi-printer-fill me-1"></i> Open Browser Print Dialog
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Library for rendering scannable QR codes client-side -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
const GENERATED_ITEMS = <?= json_encode($generatedItems ?? []) ?>;
const CAN_CREATE_WAREHOUSE_QR = <?= $canCreateWarehouseQr ? 'true' : 'false' ?>;
const CAN_EDIT_WAREHOUSE_QR = <?= $canEditWarehouseQr ? 'true' : 'false' ?>;
const CAN_EDIT_INWARDED_WAREHOUSE_QR = <?= $canInwardQrUnits ? 'true' : 'false' ?>;
const CAN_DELETE_WAREHOUSE_QR = <?= $canDeleteWarehouseQr ? 'true' : 'false' ?>;
let currentPreviewIndex = 0;

/**
 * Render scannable QR Code matching Production Unit reference standard (68-75px, Level M, img freeze)
 */
function generateQRCodeContainer(container, textPayload, size = 68) {
    if (!container) return;
    container.innerHTML = '';

    if (window.QRCode) {
        new QRCode(container, {
            text: textPayload,
            width: size,
            height: size,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.M
        });
        setTimeout(() => {
            const canvas = container.querySelector('canvas');
            if (canvas) {
                try {
                    const imgUrl = canvas.toDataURL("image/png");
                    container.innerHTML = `<img src="${imgUrl}" style="width: ${size}px; height: ${size}px; display: block; border-radius: 2px;" alt="QR">`;
                } catch(e) {}
            }
        }, 40);
    } else {
        container.innerHTML = `<div class="p-1 border text-center fs-8 text-muted">QR<br><code>${textPayload}</code></div>`;
    }
}

// Initialize QR codes for rendered cards on page load
document.addEventListener('DOMContentLoaded', function() {
    GENERATED_ITEMS.forEach((item, i) => {
        const qrBox = document.getElementById(`qr-box-${i}`);
        if (qrBox) {
            const cleanSku = item.clean_sku || ((item.size_name && item.color_name) ? `${item.size_name}-${item.color_name}` : item.sku);
            const mrpVal = parseFloat(item.mrp || 0);
            const sellVal = parseFloat(item.selling_price || item.cost_price || item.mrp || 0);
            const fmtMrp = (mrpVal % 1 === 0) ? String(Math.round(mrpVal)) : mrpVal.toFixed(2);
            const fmtSell = (sellVal % 1 === 0) ? String(Math.round(sellVal)) : sellVal.toFixed(2);
            const qrText = qrBox.getAttribute('data-json') || item.qr_payload || item.tag_code;
            generateQRCodeContainer(qrBox, qrText, 68);
        }
    });

    // Auto-select and trigger dynamic display if variant is preselected
    const vSel = document.getElementById('tpVariantSelect');
    if (vSel && vSel.value) {
        onSkuSelectChange(vSel);
    }
});

/**
 * Supplier Filter Change Handler: Dynamically filters the SKU dropdown & Brands
 */
function onSupplierFilterChange(sellerId) {
    const skuSelect = document.getElementById('tpVariantSelect');
    const brandSelect = document.getElementById('brandFilterSelect');
    if (!skuSelect) return;

    const sId = (sellerId || '').trim();
    const currentBrand = (brandSelect ? brandSelect.value : '').trim().toUpperCase();
    let firstVisibleIndex = -1;
    let matchCount = 0;

    for (let i = 0; i < skuSelect.options.length; i++) {
        const opt = skuSelect.options[i];
        if (!opt.value) {
            opt.style.display = '';
            continue;
        }

        const optSellerId = (opt.getAttribute('data-seller-id') || '').trim();
        const optBrand = (opt.getAttribute('data-brand') || opt.getAttribute('data-seller-name') || '').trim().toUpperCase();

        let show = true;
        if (sId && optSellerId !== sId) {
            show = false;
        }
        if (currentBrand && optBrand !== currentBrand) {
            show = false;
        }

        if (show) {
            opt.style.display = '';
            matchCount++;
            if (firstVisibleIndex === -1) {
                firstVisibleIndex = i;
            }
        } else {
            opt.style.display = 'none';
        }
    }

    if (skuSelect.selectedIndex >= 0 && skuSelect.options[skuSelect.selectedIndex].style.display === 'none') {
        if (firstVisibleIndex !== -1) {
            skuSelect.selectedIndex = firstVisibleIndex;
        } else {
            skuSelect.value = '';
        }
    }

    onSkuSelectChange(skuSelect);
}

// Master array of generated codes with positive stock count
const allGeneratedCodesMaster = <?= json_encode(array_values($distinctCatalogCodes)) ?>;

/**
 * Brand Filter Change Handler: Dynamically loads Generated Code dropdown based on Brand Name
 * Strictly excludes any codes with 0 stock count!
 */
function onBrandFilterChange(brandName, autoSubmit = true) {
    const catalogSelect = document.getElementById('catalogCodeFilterSelect');
    if (!catalogSelect) return;

    const bName = (brandName || '').trim().toUpperCase();
    const currentVal = catalogSelect.value;

    // Clear and rebuild options
    catalogSelect.innerHTML = '';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = bName ? '-- Choose Generated Code for ' + brandName + ' --' : '-- Choose Generated Code --';
    catalogSelect.appendChild(defaultOpt);

    let currentStillValid = false;
    let firstMatchingOpt = null;
    let matchCount = 0;

    if (Array.isArray(allGeneratedCodesMaster)) {
        allGeneratedCodesMaster.forEach(cInfo => {
            const itemBrand = (cInfo.brand || '').trim().toUpperCase();
            const itemStock = parseInt(cInfo.stock || 0, 10);

            // Strict Validation: If stock count is 0, DON'T SHOW IT!
            if (itemStock <= 0) return;

            // Brand based: only load codes for the chosen brand
            if (bName && itemBrand !== bName) return;

            const opt = document.createElement('option');
            opt.value = cInfo.code;
            opt.setAttribute('data-brand', cInfo.brand || '');
            opt.setAttribute('data-variant-id', cInfo.variant_id || '');
            opt.setAttribute('data-name', cInfo.name || '');
            opt.setAttribute('data-stock', cInfo.stock || 0);
            opt.setAttribute('data-mrp', cInfo.mrp || 0);
            opt.setAttribute('data-selling', cInfo.selling || 0);
            opt.setAttribute('data-cost', cInfo.cost || 0);
            opt.setAttribute('data-size', cInfo.size || 'Free');
            opt.setAttribute('data-color', cInfo.color || 'Std');
            opt.setAttribute('data-qr-type', cInfo.qr_type || 'INDIVIDUAL');
            opt.setAttribute('data-pack-qty', cInfo.pack_qty || 0);
            opt.setAttribute('data-cat', cInfo.cat || '');
            opt.setAttribute('data-subcat', cInfo.subcat || '');
            opt.setAttribute('data-subsubcat', cInfo.subsubcat || '');
            opt.setAttribute('data-start-code', cInfo.start_code || '');
            opt.setAttribute('data-end-code', cInfo.end_code || '');
            opt.setAttribute('data-qr-prefix', cInfo.qr_prefix || '');
            opt.setAttribute('data-seller-name', cInfo.seller_name || '');
            opt.setAttribute('data-seller-id', cInfo.seller_id || 0);
            opt.setAttribute('data-is-comp', cInfo.is_comp ? '1' : '0');
            opt.setAttribute('data-already-inwarded', cInfo.already_inwarded ? '1' : '0');
            opt.setAttribute('data-offset', cInfo.offset || 0);
            opt.setAttribute('data-start-seq', cInfo.start_seq || 1);
            opt.setAttribute('data-end-seq', cInfo.end_seq || (cInfo.stock || 1));

            const inwardSuffix = cInfo.already_inwarded ? ' 🔒 (Already Inwarded)' : '';
            opt.textContent = cInfo.code + (cInfo.name ? ' • ' + cInfo.name : '') + (cInfo.brand ? ' [' + cInfo.brand + ']' : '') + ' • ' + cInfo.stock + ' pcs' + inwardSuffix;

            if (currentVal && cInfo.code === currentVal) {
                opt.selected = true;
                currentStillValid = true;
            }

            if (!firstMatchingOpt) {
                firstMatchingOpt = opt;
            }

            catalogSelect.appendChild(opt);
            matchCount++;
        });
    }

    if (bName && matchCount === 0) {
        defaultOpt.textContent = '-- No in-stock products for ' + brandName + ' --';
    }

    // Dynamic auto-select first matching code if previous selection is not valid for this brand
    let activeOpt = null;
    if (!currentStillValid && firstMatchingOpt) {
        firstMatchingOpt.selected = true;
        catalogSelect.value = firstMatchingOpt.value;
        activeOpt = firstMatchingOpt;
    } else if (currentStillValid && catalogSelect.selectedIndex >= 0) {
        activeOpt = catalogSelect.options[catalogSelect.selectedIndex];
    } else if (firstMatchingOpt) {
        firstMatchingOpt.selected = true;
        catalogSelect.value = firstMatchingOpt.value;
        activeOpt = firstMatchingOpt;
    }

    if (activeOpt && activeOpt.value) {
        const varId = activeOpt.getAttribute('data-variant-id') || '';
        const hiddenVar = document.getElementById('tpVariantSelect');
        if (hiddenVar) hiddenVar.value = varId;
        
        // If brand switched away from active PO, clear source_id
        const hiddenSource = document.querySelector('input[name="source_id"]');
        const activePoBrand = '<?= !empty($poInfo) ? addslashes(strtoupper($poInfo['seller_company'] ?: ($poInfo['seller_name'] ?: ($firstPi['brand_name'] ?? '')))) : '' ?>';
        if (hiddenSource && activePoBrand && bName && bName !== activePoBrand) {
            hiddenSource.value = '';
        }

        renderCodeDetails(activeOpt);
        if (autoSubmit) {
            const form = document.getElementById('qrGenForm');
            if (form) {
                form.submit();
            }
        }
    } else {
        catalogSelect.value = '';
        const infoPanel = document.getElementById('skuDynamicInfoPanel');
        if (infoPanel) infoPanel.style.display = 'none';
        const hiddenVar = document.getElementById('tpVariantSelect');
        if (hiddenVar) hiddenVar.value = '';
    }
}

/**
 * Generated Code Filter Change Handler:
 * Dynamically updates product specs, syncs Brand Name, and immediately triggers QR generation!
 */
function onCatalogCodeFilterChange(catalogCode) {
    const catalogSelect = document.getElementById('catalogCodeFilterSelect');
    const brandSelect = document.getElementById('brandFilterSelect');
    const hiddenVar = document.getElementById('tpVariantSelect');
    if (!catalogSelect) return;

    const opt = catalogSelect.options[catalogSelect.selectedIndex];
    const infoPanel = document.getElementById('skuDynamicInfoPanel');

    if (!opt || !opt.value) {
        if (infoPanel) infoPanel.style.display = 'none';
        if (hiddenVar) hiddenVar.value = '';
        return;
    }

    // Sync variant ID
    const varId = opt.getAttribute('data-variant-id') || '';
    if (hiddenVar) hiddenVar.value = varId;

    // Dynamically sync Brand Name to match the selected code's brand
    const optBrand = (opt.getAttribute('data-brand') || '').trim();
    if (optBrand && brandSelect) {
        brandSelect.value = optBrand;
    }

    // Render specifications and count
    renderCodeDetails(opt);

    // Auto-generate QR labels immediately when a Generated Code is chosen
    const form = document.getElementById('qrGenForm');
    if (form) {
        form.submit();
    }
}

/**
 * Render details panel and update dynamic QR count from selected code option
 */
function renderCodeDetails(opt) {
    if (!opt || !opt.value) return;
    const infoPanel = document.getElementById('skuDynamicInfoPanel');

    // Extract attributes from selected option
    const cat = opt.getAttribute('data-cat') || 'General';
    const subcat = opt.getAttribute('data-subcat') || '—';
    const subsubcat = opt.getAttribute('data-subsubcat') || '—';
    const prodName = opt.getAttribute('data-name') || 'Selected Product';
    const size = opt.getAttribute('data-size') || 'Free';
    const color = opt.getAttribute('data-color') || 'Std';
    const mrp = parseFloat(opt.getAttribute('data-mrp') || 0);
    const selling = parseFloat(opt.getAttribute('data-selling') || 0);
    const cost = parseFloat(opt.getAttribute('data-cost') || 0);
    const brand = opt.getAttribute('data-brand') || opt.getAttribute('data-seller-name') || 'GRM CLASSIC';
    const stock = parseInt(opt.getAttribute('data-stock') || 0);
    const qrType = (opt.getAttribute('data-qr-type') || 'INDIVIDUAL').toUpperCase();
    const startCode = opt.getAttribute('data-start-code') || '';
    const endCode = opt.getAttribute('data-end-code') || '';
    const packQty = parseInt(opt.getAttribute('data-pack-qty') || 0);
    const poId = parseInt(document.querySelector('input[name="source_id"]')?.value || '0', 10);
    const totalEffectiveStock = (stock > 0 || poId > 0) ? (stock || 1) : 0;

    // Update dynamic 3-tier hierarchy labels
    const lblCat = document.getElementById('lblDynamicCat');
    const lblSubcat = document.getElementById('lblDynamicSubcat');
    const lblSubSubcat = document.getElementById('lblDynamicSubSubcat');

    if (lblCat) lblCat.textContent = cat;
    if (lblSubcat) lblSubcat.textContent = subcat;
    if (lblSubSubcat) lblSubSubcat.textContent = subsubcat;

    // Update dynamic spec labels (MRP & Selling Price)
    const lblProd = document.getElementById('lblDynamicProductName');
    const lblAttr = document.getElementById('lblDynamicVariantAttr');
    const lblMrpEl = document.getElementById('lblDynamicMrp');
    const lblCostEl = document.getElementById('lblDynamicCost');
    const lblBrandEl = document.getElementById('lblDynamicBrand');
    const lblStockEl = document.getElementById('lblDynamicStock');

    const finalSellingPrice = (selling > 0 ? selling : (mrp > 0 ? mrp : cost));

    if (lblProd) lblProd.textContent = prodName;
    if (lblAttr) lblAttr.textContent = `${size} / ${color}`;
    if (lblMrpEl) lblMrpEl.textContent = `₹${mrp.toFixed(2)}`;
    if (lblCostEl) lblCostEl.textContent = `₹${finalSellingPrice.toFixed(2)}`;
    if (lblBrandEl) lblBrandEl.textContent = brand;
    if (lblStockEl) {
        lblStockEl.textContent = `${stock} pcs`;
    }

    // Handle Series QR detection & 8-digit range formatting
    const isComp = opt.getAttribute('data-is-comp') === '1';
    const isSeries = qrType === 'SERIES_BATCH' || isComp;
    const badgeSeries = document.getElementById('badgeDynamicSeriesMode');
    const rangeBox = document.getElementById('lblDynamicSeriesRangeBox');
    const rangeText = document.getElementById('lblDynamicSeriesRangeText');
    const radSeries = document.getElementById('qrModeSeries');
    const radIndividual = document.getElementById('qrModeIndividual');

    // Build Category Prefix + Month/Year (MMYY) + Sequence
    const my = '<?= date('my') ?>';
    let prodPrefix = (opt.getAttribute('data-qr-prefix') || '').trim();
    if (!prodPrefix) {
        const c1 = (opt.getAttribute('data-cat') || '').trim();
        const c2 = (opt.getAttribute('data-subcat') || '').trim();
        const c3 = (opt.getAttribute('data-subsubcat') || '').trim();
        const initials = [c1, c2, c3].filter(Boolean).map(s => s[0]).join('').toUpperCase();
        prodPrefix = initials || 'NEW';
    }
    prodPrefix = prodPrefix.replace(/[^A-Za-z0-9]/g, '').slice(0, 4).toUpperCase();
    const baseTagPrefix = prodPrefix + my;
    const count = (packQty || stock || 1);
    const startSeq = parseInt(opt.getAttribute('data-start-seq'), 10) || 1;
    const endSeq = parseInt(opt.getAttribute('data-end-seq'), 10) || (startSeq + count - 1);
    const seqPad = Math.max(endSeq, count) > 99 ? 3 : 2;
    const dispStart = baseTagPrefix + String(startSeq).padStart(seqPad, '0');
    const dispEnd = baseTagPrefix + String(endSeq).padStart(seqPad, '0');

    if (isSeries) {
        if (badgeSeries) badgeSeries.style.display = 'inline-block';
        if (rangeBox) {
            rangeBox.style.display = 'block';
            rangeText.textContent = `${dispStart} → ${dispEnd} (${count} pcs/pack)`;
        }
        if (radSeries) radSeries.checked = true;
        if (radIndividual) radIndividual.checked = false;
    } else {
        if (badgeSeries) badgeSeries.style.display = 'none';
        if (rangeBox) {
            rangeBox.style.display = 'block';
            rangeText.textContent = `${dispStart} → ${dispEnd} (${count} pcs)`;
        }
        if (radIndividual) radIndividual.checked = true;
        if (radSeries) radSeries.checked = false;
    }

    // Dynamic QR Type Badge Update
    const badgeQrType = document.getElementById('badgeDynamicQrTypeBadge');
    if (badgeQrType) {
        if (isComp) {
            badgeQrType.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle shadow-xs';
            badgeQrType.style.backgroundColor = '';
            badgeQrType.style.color = '';
            badgeQrType.style.borderColor = '';
            badgeQrType.innerHTML = '<i class="bi bi-gift-fill me-1"></i>Complementary Product';
        } else if (isSeries) {
            badgeQrType.className = 'badge border shadow-xs';
            badgeQrType.style.backgroundColor = '#f3e8ff';
            badgeQrType.style.color = '#6f42c1';
            badgeQrType.style.borderColor = '#d8b4fe';
            badgeQrType.innerHTML = `<i class="bi bi-boxes me-1"></i>Series Product${packQty > 1 ? ` (${packQty} Pcs Pack)` : ''}`;
        } else {
            badgeQrType.className = 'badge bg-primary-subtle text-primary border border-primary-subtle shadow-xs';
            badgeQrType.style.backgroundColor = '';
            badgeQrType.style.color = '';
            badgeQrType.style.borderColor = '';
            badgeQrType.innerHTML = '<i class="bi bi-upc me-1"></i>Individual Product';
        }
    }

    // Stock count & one-time inward validation
    const isAlreadyInwarded = (opt.getAttribute('data-already-inwarded') === '1');
    const alreadyInwardedBanner = document.getElementById('alreadyInwardedValidationBanner');
    const lblAlreadyInwardedCode = document.getElementById('lblAlreadyInwardedCode');
    const isSeriesSelected = (radSeries && radSeries.checked);
    const copiesInput = document.getElementById('copiesInput');
    const copiesStockHint = document.getElementById('copiesStockHint');
    const submitBtn = document.getElementById('btnSubmitGenerateLabels');
    const zeroStockBanner = document.getElementById('zeroStockValidationBanner');

    if (alreadyInwardedBanner) {
        alreadyInwardedBanner.style.display = isAlreadyInwarded ? 'block' : 'none';
        if (lblAlreadyInwardedCode && isAlreadyInwarded) lblAlreadyInwardedCode.textContent = opt.value;
    }

    if (totalEffectiveStock <= 0) {
        // STOCK COUNT IS 0: BLOCK AND RESTRICT QR GENERATION
        if (copiesInput) {
            copiesInput.value = 0;
            copiesInput.readOnly = true;
            if (copiesStockHint) copiesStockHint.textContent = '0 (Out of Stock)';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.remove('btn-primary');
            submitBtn.classList.add('btn-secondary', 'opacity-75');
            submitBtn.title = 'Cannot generate QR labels: product stock count is 0 pcs.';
            const btnText = document.getElementById('btnSubmitLabelText');
            if (btnText) btnText.textContent = 'QR Restricted (0 Stock)';
            const btnIcon = document.getElementById('btnSubmitIcon');
            if (btnIcon) btnIcon.className = 'bi bi-slash-circle me-1';
        }
        if (zeroStockBanner) {
            zeroStockBanner.style.display = 'block';
        }
    } else {
        // POSITIVE STOCK COUNT: ALLOW QR GENERATION
        if (zeroStockBanner) {
            zeroStockBanner.style.display = 'none';
        }
        if (copiesInput) {
            if (isSeriesSelected) {
                copiesInput.value = 1;
                copiesInput.readOnly = true;
                if (copiesStockHint) copiesStockHint.textContent = `1 Master Pack (${packQty || totalEffectiveStock} pcs)`;
            } else {
                const effectiveQty = totalEffectiveStock > 0 ? totalEffectiveStock : 1;
                copiesInput.value = effectiveQty;
                copiesInput.readOnly = true;
                if (copiesStockHint) copiesStockHint.textContent = effectiveQty;
            }
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-secondary', 'opacity-75');
            submitBtn.classList.add('btn-primary');
            submitBtn.title = '';
            const btnText = document.getElementById('btnSubmitLabelText');
            if (btnText) btnText.textContent = 'Generate QR Labels';
            const btnIcon = document.getElementById('btnSubmitIcon');
            if (btnIcon) btnIcon.className = 'bi bi-qr-code me-1';
        }
        if (zeroStockBanner) {
            zeroStockBanner.style.display = 'none';
        }
    }

    if (infoPanel) infoPanel.style.display = 'block';
}

/**
 * Backward compatibility alias for any legacy callers
 */
function onSkuSelectChange(selectEl) {
    if (!selectEl) return;
    const opt = selectEl.options ? selectEl.options[selectEl.selectedIndex] : selectEl;
    renderCodeDetails(opt);
}

/**
 * QR Mode Change Handler: Updates dynamic count and locking
 */
function onQrModeChange(mode) {
    const catalogSelect = document.getElementById('catalogCodeFilterSelect');
    const copiesInput = document.getElementById('copiesInput');
    const copiesStockHint = document.getElementById('copiesStockHint');
    const opt = catalogSelect ? catalogSelect.options[catalogSelect.selectedIndex] : null;

    const stock = opt ? parseInt(opt.getAttribute('data-stock') || 0) : 0;
    const packQty = opt ? parseInt(opt.getAttribute('data-pack-qty') || 0) : 0;
    const poId = parseInt(document.querySelector('input[name="source_id"]')?.value || '0', 10);
    const totalEffectiveStock = (stock > 0 || poId > 0) ? (stock || 1) : 0;

    if (totalEffectiveStock <= 0) {
        if (copiesInput) copiesInput.value = 0;
        if (copiesStockHint) copiesStockHint.textContent = '0 (Out of Stock)';
        return;
    }

    if (mode === 'SERIES_BATCH') {
        if (copiesInput) copiesInput.value = 1;
        if (copiesStockHint) copiesStockHint.textContent = `1 Master Pack (${packQty || totalEffectiveStock} pcs)`;
    } else {
        const effectiveQty = totalEffectiveStock > 0 ? totalEffectiveStock : 1;
        if (copiesInput) copiesInput.value = effectiveQty;
        if (copiesStockHint) copiesStockHint.textContent = effectiveQty;
    }
}

let currentPreviewExtraItem = null;

/**
 * Preview Single Piece in 2D QR Code Tag Modal
 */
function previewSingleTag(idx) {
    if (!GENERATED_ITEMS[idx]) return;
    currentPreviewExtraItem = null;
    currentPreviewIndex = idx;
    const item = GENERATED_ITEMS[idx];

    const mrpNum = parseFloat(item.mrp || 0);
    const sellVal = (item.selling_price && item.selling_price > 0) ? parseFloat(item.selling_price) : mrpNum;
    const fmtMrp = (mrpNum % 1 === 0) ? String(Math.round(mrpNum)) : mrpNum.toFixed(2);
    const fmtSelling = (sellVal % 1 === 0) ? String(Math.round(sellVal)) : sellVal.toFixed(2);
    const displaySku = item.clean_sku || ((item.size_name && item.color_name) ? `${item.size_name}-${item.color_name}` : item.sku);

    document.getElementById('previewDamageCode').textContent = item.tag_code;
    document.getElementById('previewBrandName').textContent = (item.brand_name || 'GRM WAREHOUSE').toUpperCase();
    document.getElementById('previewAttrText').textContent = `${displaySku}${item.is_series ? ` (Pack of ${item.pack_quantity || 1})` : ''}`.toUpperCase();
    document.getElementById('previewBasePrice').textContent = `MRP: ₹${(mrpNum % 1 === 0) ? mrpNum.toLocaleString() : mrpNum.toFixed(2)}`;
    document.getElementById('previewDiscPrice').textContent = `Disc: ₹${(sellVal % 1 === 0) ? sellVal.toLocaleString() : sellVal.toFixed(2)}`;

    const canvas = document.getElementById('singleQRCanvas');
    const qrPayload = item.tag_code;

    generateQRCodeContainer(canvas, qrPayload, 74);

    const modal = new bootstrap.Modal(document.getElementById('singleQRModal'));
    modal.show();
}

/**
 * Preview and Print an Extra Generated QR Sticker directly in 2D QR Code Modal
 */
function previewExtraQrSticker(item) {
    if (!item) return;
    currentPreviewExtraItem = item;
    currentPreviewIndex = null;

    const mrpNum = parseFloat(item.mrp || 0);
    const sellVal = (item.selling_price && item.selling_price > 0) ? parseFloat(item.selling_price) : mrpNum;
    const displaySku = item.clean_sku || ((item.size_name && item.color_name) ? `${item.size_name}-${item.color_name}` : item.sku);

    const elCode = document.getElementById('previewDamageCode');
    const elBrand = document.getElementById('previewBrandName');
    const elAttr = document.getElementById('previewAttrText');
    const elBase = document.getElementById('previewBasePrice');
    const elDisc = document.getElementById('previewDiscPrice');

    if (elCode) elCode.textContent = item.tag_code || item.qr_code || '';
    if (elBrand) elBrand.textContent = (item.brand_name || 'GRM WAREHOUSE').toUpperCase();
    if (elAttr) elAttr.textContent = `${displaySku}${item.is_series ? ` (Pack of ${item.pack_quantity || 1})` : ''}`.toUpperCase();
    if (elBase) elBase.textContent = `MRP: ₹${(mrpNum % 1 === 0) ? mrpNum.toLocaleString() : mrpNum.toFixed(2)}`;
    if (elDisc) elDisc.textContent = `Disc: ₹${(sellVal % 1 === 0) ? sellVal.toLocaleString() : sellVal.toFixed(2)}`;

    const canvas = document.getElementById('singleQRCanvas');
    const qrPayload = item.tag_code || item.qr_code || '';
    generateQRCodeContainer(canvas, qrPayload, 74);

    const modalEl = document.getElementById('singleQRModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
}

/**
 * Print Single Piece directly from the 2D QR Code Tag Preview Modal
 */
function printSinglePieceFromModal() {
    if (currentPreviewExtraItem) {
        const item = currentPreviewExtraItem;
        const mrpNum = parseFloat(item.mrp || 0);
        const sellNum = parseFloat(item.selling_price || item.cost_price || item.mrp || 0);
        const fmtMrp = (mrpNum % 1 === 0) ? String(Math.round(mrpNum)) : mrpNum.toFixed(2);
        const fmtDisc = (sellNum % 1 === 0) ? String(Math.round(sellNum)) : sellNum.toFixed(2);
        const displaySku = item.clean_sku || ((item.size_name && item.color_name) ? `${item.size_name}-${item.color_name}` : item.sku);

        const labelPayload = [{
            sku: displaySku,
            productName: item.product_name,
            categoryHierarchy: item.category_hierarchy || '',
            categoryName: item.category_name || '',
            subcategoryName: item.subcategory_name || '',
            subSubcategoryName: item.sub_subcategory_name || '',
            sizeName: item.size_name || 'Free',
            colorName: item.color_name || 'Std',
            attrText: `${item.size_name || 'Free'}-${item.color_name || 'Std'}`,
            basePrice: fmtMrp,
            discountPrice: fmtDisc,
            damageCode: item.tag_code || item.qr_code,
            title: (item.brand_name || "GRM CLASSIC").toUpperCase(),
            qrPayload: item.qr_payload || item.tag_code || item.qr_code,
            quantity: 1
        }];

        printLabel(labelPayload);
        return;
    }
    if (currentPreviewIndex !== null && typeof printSinglePieceSticker === 'function') {
        printSinglePieceSticker(currentPreviewIndex);
    }
}

/**
 * Filter Rendered Stickers via live search
 */
function filterRenderedStickers() {
    const input = document.getElementById('filterTagInput');
    const query = input ? input.value.trim().toUpperCase() : '';
    const cols = document.querySelectorAll('.sticker-grid-col');

    cols.forEach(col => {
        const code = col.getAttribute('data-code') || '';
        const sku = col.getAttribute('data-sku') || '';
        if (!query || code.includes(query) || sku.includes(query)) {
            col.style.display = '';
        } else {
            col.style.display = 'none';
        }
    });
}

/**
 * Copy piece tag code to clipboard
 */
function copyTagCode(code, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(() => {
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i> Copied!';
            btn.classList.replace('btn-outline-secondary', 'btn-success');
            btn.classList.add('text-white');
            setTimeout(() => {
                btn.innerHTML = origHtml;
                btn.classList.replace('btn-success', 'btn-outline-secondary');
                btn.classList.remove('text-white');
            }, 1500);
        });
    }
}

/**
 * Piece Selection & QR Adjustment State Management
 */
const selectedStickerIndices = new Set();

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function updateCardVisualHighlight(idx, isChecked) {
    const card = document.getElementById(`card-piece-${idx}`);
    if (card) {
        if (isChecked) {
            card.classList.add('selected-piece-card');
        } else {
            card.classList.remove('selected-piece-card');
        }
    }
}

function onPieceCheckChanged(idx) {
    const chk = document.getElementById(`chk-piece-${idx}`);
    if (!chk) return;

    if (chk.checked) {
        selectedStickerIndices.add(idx);
    } else {
        selectedStickerIndices.delete(idx);
    }
    updateCardVisualHighlight(idx, chk.checked);
    updateSelectionCounter();
}

function toggleSelectAllPieces(isChecked) {
    const cols = document.querySelectorAll('.sticker-grid-col');
    cols.forEach(col => {
        if (col.style.display !== 'none') {
            const chk = col.querySelector('.piece-select-chk');
            if (chk && !chk.disabled) {
                const idx = parseInt(chk.getAttribute('data-index'), 10);
                chk.checked = isChecked;
                if (isChecked) {
                    selectedStickerIndices.add(idx);
                } else {
                    selectedStickerIndices.delete(idx);
                }
                updateCardVisualHighlight(idx, isChecked);
            }
        }
    });
    updateSelectionCounter();
}

function selectPieceRange() {
    const fromVal = parseInt(document.getElementById('rangeSelectFrom')?.value, 10);
    const toVal = parseInt(document.getElementById('rangeSelectTo')?.value, 10);

    if (isNaN(fromVal) || isNaN(toVal) || fromVal < 1 || toVal < fromVal) {
        showPrintToast('Please enter a valid Range (From must be <= To).', 'warning');
        return;
    }

    const cols = document.querySelectorAll('.sticker-grid-col');
    let selectedCountInRange = 0;
    cols.forEach((col, idx) => {
        const pieceNo = parseInt(col.getAttribute('data-piece-no') || '0', 10);
        const relIdx = idx + 1;
        const chk = col.querySelector('.piece-select-chk');
        // Match either absolute piece_no (e.g. 6 to 10) OR 1-based relative index (1 to 5)
        const inRange = (pieceNo >= fromVal && pieceNo <= toVal) || (relIdx >= fromVal && relIdx <= toVal);
        if (chk && !chk.disabled && inRange) {
            const itemIdx = parseInt(chk.getAttribute('data-index'), 10);
            chk.checked = true;
            selectedStickerIndices.add(itemIdx);
            updateCardVisualHighlight(itemIdx, true);
            selectedCountInRange++;
        }
    });

    updateSelectionCounter();
    showPrintToast(`Selected ${selectedCountInRange} stickers in range #${fromVal} to #${toVal}.`, 'info');
}

function clearPieceSelection() {
    selectedStickerIndices.clear();
    const chks = document.querySelectorAll('.piece-select-chk');
    chks.forEach(chk => {
        chk.checked = false;
        const idx = parseInt(chk.getAttribute('data-index'), 10);
        updateCardVisualHighlight(idx, false);
    });
    const masterChk = document.getElementById('selectAllPiecesChk');
    if (masterChk) {
        masterChk.checked = false;
        masterChk.indeterminate = false;
    }
    updateSelectionCounter();
}

function updateSelectionCounter() {
    const count = selectedStickerIndices.size;
    const btnCount = document.getElementById('btnSelectedCount');
    const badgeCount = document.getElementById('selectedCountText');
    const badgeEl = document.getElementById('selectedPiecesBadge');
    const btnPrint = document.getElementById('btnPrintSelectedStickers');
    const btnDelete = document.getElementById('btnDeleteSelectedQrs');
    const masterChk = document.getElementById('selectAllPiecesChk');

    if (btnCount) btnCount.textContent = count;
    if (badgeCount) badgeCount.textContent = count;
    if (badgeEl) badgeEl.style.display = count > 0 ? 'inline-block' : 'none';
    if (btnPrint) {
        btnPrint.disabled = (count === 0);
        if (count > 0) {
            btnPrint.classList.replace('btn-secondary', 'btn-primary');
        }
    }
    if (btnDelete) btnDelete.disabled = count === 0;
    const selectableCount = document.querySelectorAll('.sticker-grid-col .piece-select-chk:not(:disabled)').length;
    if (masterChk && selectableCount > 0) {
        if (count === selectableCount) {
            masterChk.checked = true;
            masterChk.indeterminate = false;
        } else if (count > 0) {
            masterChk.checked = false;
            masterChk.indeterminate = true;
        } else {
            masterChk.checked = false;
            masterChk.indeterminate = false;
        }
    }

    // Dynamically synchronize the Confirm Inward button with the current selection
    syncInwardButtonWithSelection();
}

/**
 * Selective Inward Synchronization:
 * Updates the Confirm Inward button and form inputs depending on whether individual products are selected.
 * - If products are selected (picked): inward ONLY the selected products (+X Selected Pcs).
 * - If NO products are selected: inward ALL available non-inwarded products (+X Pcs).
 */
function syncInwardButtonWithSelection() {
    const btnText = document.getElementById('btnInwardSubmitText');
    const qtyInput = document.getElementById('inwardQtyInput');
    const tagsInput = document.getElementById('inwardTagCodesInput');
    const btn = document.getElementById('btnConfirmInwardSubmit');
    const quickBtn = document.getElementById('btnQuickInwardSelected');
    const icon = document.getElementById('btnInwardSubmitIcon');
    const form = document.getElementById('formConfirmInward');

    // Use the rendered checkbox state as the source of truth so the buttons cannot
    // get out of sync with selection changes or a stale in-memory selection set.
    selectedStickerIndices.clear();
    document.querySelectorAll('.piece-select-chk:checked:not(:disabled)').forEach(chk => {
        const index = Number.parseInt(chk.dataset.index || '', 10);
        if (Number.isInteger(index)) selectedStickerIndices.add(index);
    });

    const isFlagSet = value => value === true || value === 1 || ['1', 'true', 'yes'].includes(String(value).toLowerCase());
    const availableItems = [];
    (Array.isArray(GENERATED_ITEMS) ? GENERATED_ITEMS : []).forEach((it, idx) => {
        if (it && !isFlagSet(it.is_inwarded) && !isFlagSet(it.is_deleted)) {
            availableItems.push({ index: idx, item: it });
        }
    });
    const pickedAvailable = availableItems.filter(entry => selectedStickerIndices.has(entry.index));
    const hasSelection = pickedAvailable.length > 0;
    const canSubmit = hasSelection && Boolean(form);
    if (quickBtn) {
        quickBtn.disabled = !canSubmit;
        quickBtn.classList.toggle('btn-outline-success', !canSubmit);
        quickBtn.classList.toggle('btn-success', canSubmit);
        quickBtn.classList.toggle('text-white', canSubmit);
    }

    if (availableItems.length === 0) {
        if (btnText) btnText.textContent = 'All Generated QR Stickers Inwarded to Inventory';
        if (qtyInput) qtyInput.value = 0;
        if (tagsInput) tagsInput.value = '[]';
        if (btn) {
            btn.disabled = true;
            btn.classList.add('btn-secondary', 'opacity-75');
            btn.classList.remove('btn-success');
        }
        if (icon) icon.className = 'bi bi-check-circle-fill fs-4';
        if (quickBtn) quickBtn.disabled = true;
        return;
    }

    if (!btnText || !qtyInput || !tagsInput) return;

    if (hasSelection) {
        // PICKED / SELECTED PRODUCTS ONLY INWARD
        let totalPieces = 0;
        pickedAvailable.forEach(entry => {
            if (entry.item.is_series && entry.item.pack_quantity) {
                totalPieces += parseInt(entry.item.pack_quantity, 10) || 1;
            } else {
                totalPieces += 1;
            }
        });
        const tagCodes = pickedAvailable.map(entry => entry.item.tag_code);
        btnText.textContent = `Confirm Stickered & Inward to Inventory (+${totalPieces} Selected Pcs)`;
        qtyInput.value = totalPieces;
        tagsInput.value = JSON.stringify(tagCodes);
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('btn-secondary', 'opacity-75');
            btn.classList.add('btn-success');
        }
        if (icon) icon.className = 'bi bi-box-arrow-in-down-right fs-4';
    } else {
        // Default to ALL available non-inwarded products
        let totalPieces = 0;
        availableItems.forEach(entry => {
            if (entry.item.is_series && entry.item.pack_quantity) {
                totalPieces += parseInt(entry.item.pack_quantity, 10) || 1;
            } else {
                totalPieces += 1;
            }
        });
        const tagCodes = availableItems.map(entry => entry.item.tag_code);
        btnText.textContent = `Confirm Stickered & Inward to Inventory (+${totalPieces} Pcs)`;
        qtyInput.value = totalPieces;
        tagsInput.value = JSON.stringify(tagCodes);
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('btn-secondary', 'opacity-75');
            btn.classList.add('btn-success');
        }
        if (icon) icon.className = 'bi bi-box-arrow-in-down-right fs-4';
    }
}

/**
 * Confirm Inward Action:
 * Posts inward quantity and tag codes to the backend.
 * Respects selection: inwarding ONLY picked products when selected, or ALL when unselected.
 */
function confirmStickeredInwardAction(event) {
    if (event) event.preventDefault();

    const form = document.getElementById('formConfirmInward');
    const btn = document.getElementById('btnConfirmInwardSubmit');
    const btnText = document.getElementById('btnInwardSubmitText');
    const btnIcon = document.getElementById('btnInwardSubmitIcon');
    const statusBadge = document.getElementById('inwardStatusBadge');
    if (!form) return;

    const qty = parseInt(document.getElementById('inwardQtyInput')?.value || '0', 10);
    const tagCodesJson = document.getElementById('inwardTagCodesInput')?.value || '[]';
    let tagCodes = [];
    try { tagCodes = JSON.parse(tagCodesJson); } catch (e) { tagCodes = []; }

    if (qty <= 0 || tagCodes.length === 0) {
        showPrintToast('No active sticker products available to inward.', 'warning');
        return;
    }

    const isPickedOnly = (selectedStickerIndices.size > 0);
    const confirmMessage = isPickedOnly
        ? `Confirm Selected Inward: Inward ONLY the ${qty} picked/selected product sticker(s) into warehouse inventory?`
        : `Confirm Full Inward: No specific products picked. Inward ALL ${qty} stickered product(s) into warehouse inventory?`;

    if (!confirm(confirmMessage)) {
        return;
    }

    if (btn) {
        btn.disabled = true;
        if (btnText) btnText.textContent = `Inwarding +${qty} Pcs to Stock...`;
        if (btnIcon) btnIcon.className = 'spinner-border spinner-border-sm me-2';
    }

    const formData = new FormData(form);
    formData.append('is_ajax', '1');

    fetch('<?= url("warehouse/labels/confirm-stickered-inward") ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.hasStockOrQrChanged = true;
            showPrintToast(data.message || `Successfully inwarded +${qty} pcs to warehouse inventory!`, 'success');

            // Mark inwarded items in GENERATED_ITEMS and update their card UI
            tagCodes.forEach(code => {
                GENERATED_ITEMS.forEach((it, idx) => {
                    if (it && it.tag_code === code) {
                        it.is_inwarded = true;
                        selectedStickerIndices.delete(idx);

                        const card = document.getElementById(`card-piece-${idx}`);
                        const chk = document.getElementById(`chk-piece-${idx}`);
                        if (chk) {
                            chk.checked = false;
                            chk.disabled = true;
                            chk.title = 'Officially Inwarded to Inventory';
                        }
                        if (card) {
                            card.classList.remove('selected-piece-card');
                            card.classList.add('inwarded-piece-card');

                            let inwBadge = card.querySelector('.inwarded-badge');
                            if (!inwBadge) {
                                inwBadge = document.createElement('span');
                                inwBadge.className = 'badge bg-success text-white position-absolute top-0 end-0 m-1 shadow-xs inwarded-badge';
                                inwBadge.style.fontSize = '0.62rem';
                                inwBadge.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Inwarded';
                                card.appendChild(inwBadge);
                            }
                        }
                    }
                });
            });

            // Update live warehouse stock display if returned
            const liveWhStock = document.getElementById('liveCurrentWhStock');
            const newStockVal = (data.warehouse_stock !== undefined) ? data.warehouse_stock : (data.data && data.data.new_stock !== undefined ? data.data.new_stock : null);
            if (liveWhStock && newStockVal !== null) {
                liveWhStock.textContent = `${newStockVal} pcs`;
                liveWhStock.className = 'badge bg-success text-white px-2 py-1 fs-7';
            }

            // Update master select checkbox & counters
            updateSelectionCounter();

            // Check if any items remain to be inwarded
            const remainingActive = GENERATED_ITEMS.filter(it => it && !it.is_inwarded && !it.is_deleted);
            if (remainingActive.length === 0) {
                // Batch inward fully finished
                if (statusBadge) {
                    statusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                    statusBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Stock Officially Inwarded (1-Time Completed)';
                }
                if (btn) {
                    btn.disabled = true;
                    btn.className = 'btn btn-secondary opacity-75 btn-lg shadow fw-bold px-4 py-3 d-flex align-items-center gap-2';
                    if (btnText) btnText.textContent = `All Items Inwarded (+${data.inward_qty || qty} Pcs Credited)`;
                    if (btnIcon) btnIcon.className = 'bi bi-check-circle-fill fs-4';
                }

                // Update dropdown option to reflect inwarded status
                const catSelect = document.getElementById('catalogCodeFilterSelect');
                if (catSelect && catSelect.selectedIndex >= 0) {
                    const curOpt = catSelect.options[catSelect.selectedIndex];
                    curOpt.setAttribute('data-already-inwarded', '1');
                    if (!curOpt.textContent.includes('(Already Inwarded)')) {
                        curOpt.textContent += ' 🔒 (Already Inwarded)';
                    }
                }
            } else {
                // Partial inward finished
                if (statusBadge) {
                    statusBadge.className = 'badge bg-info-subtle text-info-emphasis border border-info-subtle';
                    statusBadge.innerHTML = `<i class="bi bi-info-circle-fill me-1"></i> Partial Inward: ${qty} Pcs Credited &bull; ${remainingActive.length} Remaining`;
                }
                if (btn) {
                    btn.disabled = false;
                    btn.className = 'btn btn-success btn-lg shadow fw-bold px-4 py-3 d-flex align-items-center gap-2';
                    if (btnIcon) btnIcon.className = 'bi bi-box-arrow-in-down-right fs-4';
                }
                syncInwardButtonWithSelection();
            }
        } else {
            alert(data.message || 'Failed to confirm stock inward.');
            if (btn) {
                btn.disabled = false;
                if (btnIcon) btnIcon.className = 'bi bi-box-arrow-in-down-right fs-4';
            }
            syncInwardButtonWithSelection();
        }
    })
    .catch(err => {
        console.error('Inward error:', err);
        alert('Network or server error while confirming stock inward.');
        if (btn) {
            btn.disabled = false;
            if (btnIcon) btnIcon.className = 'bi bi-box-arrow-in-down-right fs-4';
        }
        syncInwardButtonWithSelection();
    });
}

window.hasStockOrQrChanged = false;

/**
 * Handle Back to Batches button click
 */
function handleBackToBatches(event) {
    if (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey) return true;
        event.preventDefault();
    }
    toggleViewMode('batches');
    return false;
}

/**
 * Toggle between QR Batches History (list style) and QR Generation Area
 */
function toggleViewMode(mode) {
    const batchesSection = document.getElementById('qrBatchesListSection');
    const genSection = document.getElementById('qrGenerationArea');
    const baseUrl = '<?= url("warehouse/labels") ?>';
    const activeTab = '<?= e($historyTab) ?>';
    const cleanUrl = baseUrl + (activeTab && activeTab !== 'warehouse' ? '?history_tab=' + encodeURIComponent(activeTab) : '');

    if (mode === 'generate') {
        if (batchesSection) batchesSection.style.display = 'none';
        if (genSection) genSection.style.display = 'block';
        if (genSection) {
            genSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } else {
        // If stock was inwarded or extra QRs generated in this session, reload to fetch fresh database counts & status
        if (window.hasStockOrQrChanged) {
            window.location.href = cleanUrl;
            return;
        }

        if (batchesSection) batchesSection.style.display = 'block';
        if (genSection) genSection.style.display = 'none';

        // Update browser URL cleanly: remove ?source_type=po&brand_name=...&catalog_code=...&variant_id=...&view=generate
        if (window.history && window.history.pushState) {
            window.history.pushState({ mode: 'batches' }, document.title, cleanUrl);
        } else {
            window.location.href = cleanUrl;
        }

        if (batchesSection) {
            batchesSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

// Seamlessly handle browser Back and Forward buttons
window.addEventListener('popstate', function(event) {
    const urlParams = new URLSearchParams(window.location.search);
    const hasGenerate = urlParams.has('view') && (urlParams.get('view') === 'generate' || urlParams.get('view') === 'existing');
    const hasVariant = (urlParams.has('variant_id') || urlParams.has('catalog_code')) && urlParams.get('view') !== 'batches';
    const batchesSection = document.getElementById('qrBatchesListSection');
    const genSection = document.getElementById('qrGenerationArea');

    if (hasGenerate || hasVariant) {
        if (batchesSection) batchesSection.style.display = 'none';
        if (genSection) genSection.style.display = 'block';
    } else {
        if (batchesSection) batchesSection.style.display = 'block';
        if (genSection) genSection.style.display = 'none';
    }
});

/**
 * Toggle supplier profile card collapse
 */
function toggleSupplierCollapse(safeId) {
    const el = document.getElementById(`collapse-${safeId}`);
    const chevron = document.getElementById(`chevron-${safeId}`);
    const btn = document.getElementById(`btn-toggle-${safeId}`);
    if (!el) return;

    const isShown = el.classList.contains('show');

    if (window.bootstrap && bootstrap.Collapse) {
        const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        if (isShown) {
            bsCollapse.hide();
        } else {
            bsCollapse.show();
        }
    } else {
        if (isShown) {
            el.classList.remove('show');
            el.style.display = 'none';
        } else {
            el.classList.add('show');
            el.style.display = 'block';
        }
    }

    // Immediately sync icons & button visual styles
    if (isShown) {
        if (chevron) {
            chevron.classList.remove('bi-chevron-up');
            chevron.classList.add('bi-chevron-down');
        }
        if (btn) {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-primary');
            btn.setAttribute('aria-expanded', 'false');
        }
    } else {
        if (chevron) {
            chevron.classList.remove('bi-chevron-down');
            chevron.classList.add('bi-chevron-up');
        }
        if (btn) {
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-primary');
            btn.setAttribute('aria-expanded', 'true');
        }
    }
}

/**
 * Expand or Collapse All Supplier Profiles
 */
let allProfilesCollapsed = true;
function toggleAllSupplierProfiles() {
    allProfilesCollapsed = !allProfilesCollapsed;
    const cards = document.querySelectorAll('.supplier-profile-card');
    cards.forEach(card => {
        const collapseEl = card.querySelector('.collapse');
        if (!collapseEl) return;
        const safeId = collapseEl.id.replace('collapse-', '');
        const chevron = document.getElementById(`chevron-${safeId}`);
        const btn = document.getElementById(`btn-toggle-${safeId}`);

        if (window.bootstrap && bootstrap.Collapse) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
            if (allProfilesCollapsed) {
                bsCollapse.hide();
            } else {
                bsCollapse.show();
            }
        } else {
            if (allProfilesCollapsed) {
                collapseEl.classList.remove('show');
                collapseEl.style.display = 'none';
            } else {
                collapseEl.classList.add('show');
                collapseEl.style.display = 'block';
            }
        }

        if (allProfilesCollapsed) {
            if (chevron) { chevron.classList.remove('bi-chevron-up'); chevron.classList.add('bi-chevron-down'); }
            if (btn) { btn.classList.remove('btn-primary'); btn.classList.add('btn-outline-primary'); btn.setAttribute('aria-expanded', 'false'); }
        } else {
            if (chevron) { chevron.classList.remove('bi-chevron-down'); chevron.classList.add('bi-chevron-up'); }
            if (btn) { btn.classList.remove('btn-outline-primary'); btn.classList.add('btn-primary'); btn.setAttribute('aria-expanded', 'true'); }
        }
    });

    const textEl = document.getElementById('textToggleAllProfiles');
    const iconEl = document.getElementById('iconToggleAllProfiles');
    if (textEl) textEl.textContent = allProfilesCollapsed ? 'Expand All' : 'Collapse All';
    if (iconEl) {
        iconEl.className = allProfilesCollapsed ? 'bi bi-arrows-expand me-1' : 'bi bi-arrows-collapse me-1';
    }
}

/**
 * Filter supplier profiles and product rows in real-time
 */
function filterBatchesTable() {
    const input = document.getElementById('filterBatchesTableInput');
    const query = input ? input.value.trim().toLowerCase() : '';

    const cards = document.querySelectorAll('.supplier-profile-card');
    let visibleSupplierCount = 0;
    let visibleProductCount = 0;

    cards.forEach(card => {
        const suppSearch = card.getAttribute('data-supplier-search') || '';
        const prodRows = card.querySelectorAll('.supplier-prod-row');
        let cardProdMatches = 0;

        prodRows.forEach(pRow => {
            const pSearch = pRow.getAttribute('data-search') || '';
            if (!query || pSearch.includes(query)) {
                pRow.style.display = '';
                cardProdMatches++;
                visibleProductCount++;
            } else {
                pRow.style.display = 'none';
            }
        });

        if (!query) {
            card.style.display = '';
            visibleSupplierCount++;
        } else if (suppSearch.includes(query) || cardProdMatches > 0) {
            card.style.display = '';
            visibleSupplierCount++;
            // If user searches and supplier matches, ensure card collapse is open
            const collapseEl = card.querySelector('.collapse');
            if (collapseEl && !collapseEl.classList.contains('show')) {
                collapseEl.classList.add('show');
                collapseEl.style.display = 'block';
            }
        } else {
            card.style.display = 'none';
        }
    });

    const badge = document.getElementById('badgeBatchesTotalCount');
    if (badge) {
        badge.textContent = query ? `${visibleProductCount} Matching Products (${visibleSupplierCount} Suppliers)` : `<?= count($qrBatches) ?> Total Products`;
    }
}

/**
 * Expand or Collapse All Extra Generated QR Profiles
 */
let allExtraProfilesCollapsed = false;
function toggleAllExtraProfiles() {
    allExtraProfilesCollapsed = !allExtraProfilesCollapsed;
    const cards = document.querySelectorAll('.extra-profile-card');
    cards.forEach(card => {
        const collapseEl = card.querySelector('.collapse');
        if (!collapseEl) return;
        const safeId = collapseEl.id.replace('collapse-', '');
        const chevron = document.getElementById(`chevron-${safeId}`);
        const btn = document.getElementById(`btn-toggle-${safeId}`);

        if (window.bootstrap && bootstrap.Collapse) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
            if (allExtraProfilesCollapsed) {
                bsCollapse.hide();
            } else {
                bsCollapse.show();
            }
        } else {
            if (allExtraProfilesCollapsed) {
                collapseEl.classList.remove('show');
                collapseEl.style.display = 'none';
            } else {
                collapseEl.classList.add('show');
                collapseEl.style.display = 'block';
            }
        }

        if (allExtraProfilesCollapsed) {
            if (chevron) { chevron.classList.remove('bi-chevron-up'); chevron.classList.add('bi-chevron-down'); }
            if (btn) { btn.classList.remove('btn-primary'); btn.classList.add('btn-outline-primary'); btn.setAttribute('aria-expanded', 'false'); }
        } else {
            if (chevron) { chevron.classList.remove('bi-chevron-down'); chevron.classList.add('bi-chevron-up'); }
            if (btn) { btn.classList.remove('btn-outline-primary'); btn.classList.add('btn-primary'); btn.setAttribute('aria-expanded', 'true'); }
        }
    });

    const textEl = document.getElementById('textToggleAllExtraProfiles');
    const iconEl = document.getElementById('iconToggleAllExtraProfiles');
    if (textEl) textEl.textContent = allExtraProfilesCollapsed ? 'Expand All' : 'Collapse All';
    if (iconEl) {
        iconEl.className = allExtraProfilesCollapsed ? 'bi bi-arrows-expand me-1' : 'bi bi-arrows-collapse me-1';
    }
}

/**
 * Filter Extra QRs profiles and rows in real-time
 */
function filterExtraQrsTable() {
    const input = document.getElementById('filterExtraQrsTableInput');
    const query = input ? input.value.trim().toLowerCase() : '';

    const cards = document.querySelectorAll('.extra-profile-card');
    let visibleProfileCount = 0;
    let visibleRowCount = 0;

    cards.forEach(card => {
        const suppSearch = card.getAttribute('data-extra-search') || '';
        const rows = card.querySelectorAll('.extra-qr-row');
        let cardMatches = 0;

        rows.forEach(r => {
            const text = r.textContent.toLowerCase();
            if (!query || text.includes(query) || suppSearch.includes(query)) {
                r.style.display = '';
                cardMatches++;
                visibleRowCount++;
            } else {
                r.style.display = 'none';
            }
        });

        if (!query || cardMatches > 0 || suppSearch.includes(query)) {
            card.style.display = '';
            visibleProfileCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const badge = document.getElementById('badgeExtraTotalCount');
    if (badge) {
        badge.textContent = query 
            ? `${visibleRowCount} Matching Extra QRs` 
            : `<?= (int)($totalExtraQrsCount ?? 0) ?> Extra QRs (<?= (int)($totalExtraPcsCount ?? 0) ?> Total Pcs)`;
    }
}

/**
 * Print only the selected checked stickers
 */
function printSelectedStickers() {
    if (selectedStickerIndices.size === 0) {
        showPrintToast('Please select at least one QR sticker to print.', 'warning');
        return;
    }

    const selectedItems = Array.from(selectedStickerIndices)
        .map(idx => GENERATED_ITEMS[idx])
        .filter(item => item && !item.is_deleted);

    if (selectedItems.length === 0) {
        showPrintToast('No valid stickers selected.', 'warning');
        return;
    }

    const batchLabels = selectedItems.map(item => {
        const mrpNum = parseFloat(item.mrp || 0);
        const sellNum = parseFloat(item.selling_price || item.cost_price || item.mrp || 0);
        const fmtMrp = (mrpNum % 1 === 0) ? String(Math.round(mrpNum)) : mrpNum.toFixed(2);
        const fmtDisc = (sellNum % 1 === 0) ? String(Math.round(sellNum)) : sellNum.toFixed(2);
        const displaySku = item.clean_sku || ((item.size_name && item.color_name) ? `${item.size_name}-${item.color_name}` : item.sku);
        return {
            sku: displaySku,
            productName: item.product_name,
            categoryHierarchy: item.category_hierarchy || '',
            categoryName: item.category_name || '',
            subcategoryName: item.subcategory_name || '',
            subSubcategoryName: item.sub_subcategory_name || '',
            sizeName: item.size_name || 'Free',
            colorName: item.color_name || 'Std',
            attrText: `${item.size_name || 'Free'}-${item.color_name || 'Std'}`,
            basePrice: fmtMrp,
            discountPrice: fmtDisc,
            damageCode: item.tag_code,
            title: (item.brand_name || "GRM CLASSIC").toUpperCase(),
            qrPayload: item.qr_payload || item.tag_code,
            quantity: 1
        };
    });

    showPrintToast(`Printing ${batchLabels.length} selected stickers to printer...`, 'info');
    printLabel(batchLabels);
}

let pendingQrDeleteIndices = [];

function deleteSelectedQrs() {
    const indices = Array.from(selectedStickerIndices)
        .filter(index => GENERATED_ITEMS[index] && !GENERATED_ITEMS[index].is_deleted);
    openQrDeleteConfirmation(indices);
}

function deleteSingleQr(index) {
    const item = GENERATED_ITEMS[index];
    if (!item || item.is_deleted || item.is_inwarded) {
        showPrintToast('Only QR labels that have not been inwarded can be deleted.', 'warning');
        return;
    }
    openQrDeleteConfirmation([index]);
}

function openQrDeleteConfirmation(indices) {
    if (!CAN_DELETE_WAREHOUSE_QR) {
        showPrintToast('You do not have permission to delete QR labels.', 'danger');
        return;
    }

    pendingQrDeleteIndices = indices.filter(index =>
        GENERATED_ITEMS[index] &&
        !GENERATED_ITEMS[index].is_deleted &&
        !GENERATED_ITEMS[index].is_inwarded &&
        GENERATED_ITEMS[index].tag_code
    );
    if (pendingQrDeleteIndices.length === 0) {
        showPrintToast('Select at least one QR label that has not been inwarded.', 'warning');
        return;
    }

    const modalElement = document.getElementById('deleteQrConfirmModal');
    const reasonInput = document.getElementById('deleteQrReason');
    const countElement = document.getElementById('deleteQrSelectedCount');
    if (!modalElement || !window.bootstrap || !bootstrap.Modal) {
        showPrintToast('The QR deletion confirmation dialog is unavailable. Refresh the page and try again.', 'danger');
        return;
    }

    if (countElement) countElement.textContent = String(pendingQrDeleteIndices.length);
    if (reasonInput) {
        reasonInput.value = '';
        reasonInput.setCustomValidity('');
    }
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
    setTimeout(() => reasonInput?.focus(), 150);
}

function confirmDeleteSelectedQrs() {
    const reasonInput = document.getElementById('deleteQrReason');
    const reason = (reasonInput?.value || '').trim();
    if (!reason) {
        reasonInput?.setCustomValidity('Enter a reason for deleting these QR labels.');
        reasonInput?.reportValidity();
        return;
    }
    reasonInput?.setCustomValidity('');

    const selectedIndices = pendingQrDeleteIndices.filter(index =>
        GENERATED_ITEMS[index] && !GENERATED_ITEMS[index].is_deleted
    );
    const codesToDelete = selectedIndices.map(index => GENERATED_ITEMS[index].tag_code).filter(Boolean);
    if (codesToDelete.length === 0) {
        showPrintToast('No active QR labels are selected for deletion.', 'warning');
        return;
    }

    const confirmButton = document.getElementById('confirmDeleteQrButton');
    const originalButtonHtml = confirmButton ? confirmButton.innerHTML : '';
    if (confirmButton) {
        confirmButton.disabled = true;
        confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
    }

    const formData = new FormData();
    codesToDelete.forEach(code => formData.append('codes[]', code));
    formData.append('reason', reason);
    formData.append('_csrf_token', '<?= CSRF::token() ?>');
    const variantId = GENERATED_ITEMS[selectedIndices[0]]?.variant_id || '<?= $activeVar['id'] ?? '' ?>';
    if (variantId) formData.append('variant_id', variantId);

    fetch('<?= url("warehouse/labels/delete-qr") ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(res => {
        if (!res.success) {
            showPrintToast(res.message || 'Error deleting QR labels.', 'danger');
            return;
        }

        const deletedCodes = new Set((res.deleted_codes || []).map(code => String(code).toUpperCase()));
        selectedIndices.forEach(index => {
            const item = GENERATED_ITEMS[index];
            if (!item || !deletedCodes.has(String(item.tag_code).toUpperCase())) return;
            item.is_deleted = true;
            document.getElementById(`col-piece-${index}`)?.remove();
            selectedStickerIndices.delete(index);
        });

        const activeItems = GENERATED_ITEMS.filter(item => item && !item.is_deleted);
        const totalBadge = document.getElementById('totalPiecesBadge');
        const totalSelectable = document.getElementById('totalSelectableCount');
        const inwardCount = document.getElementById('lblStickersGeneratedCount');
        if (totalBadge) totalBadge.textContent = `${activeItems.length} Available Pieces`;
        if (totalSelectable) totalSelectable.textContent = activeItems.length;
        if (inwardCount) inwardCount.textContent = activeItems.length;
        const summaryBadge = document.getElementById('masterPackTotalBadge');
        if (summaryBadge) {
            const aggregateItems = activeItems.filter(item => item.is_series || item.is_complementary);
            const totalPackQty = aggregateItems.reduce((total, item) => total + Number(item.pack_quantity || 1), 0);
            summaryBadge.textContent = `${aggregateItems.length} Master QR${aggregateItems.length === 1 ? '' : 's'} (${totalPackQty} Total Pcs)`;
        }

        const emptyMessage = document.getElementById('emptyGeneratedQrMessage');
        if (activeItems.length === 0 && !emptyMessage) {
            const message = document.createElement('div');
            message.id = 'emptyGeneratedQrMessage';
            message.className = 'col-12 text-center py-4 text-muted';
            message.textContent = 'No QR stickers remain on this page.';
            document.getElementById('stickersCardGrid')?.appendChild(message);
        }

        const maxPieceNo = activeItems.reduce((max, item) => Math.max(max, parseInt(item.piece_no, 10) || 0), 1);
        ['rangeSelectFrom', 'rangeSelectTo'].forEach(id => {
            const input = document.getElementById(id);
            if (input) input.max = maxPieceNo;
        });
        const tagRange = document.getElementById('inwardTagRangeText');
        if (tagRange) {
            tagRange.textContent = activeItems.length
                ? `${activeItems[0].tag_code} -> ${activeItems[activeItems.length - 1].tag_code}`
                : 'None';
        }

        updateSelectionCounter();
        pendingQrDeleteIndices = [];
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteQrConfirmModal')).hide();
        const skippedCount = (res.skipped_inwarded || []).length;
        const toastMessage = skippedCount > 0
            ? `${res.message} ${skippedCount} inwarded QR label(s) were protected.`
            : res.message;
        showPrintToast(toastMessage || `Deleted ${deletedCodes.size} QR label(s) and recorded the reason.`, 'success');
    })
    .catch(() => {
        showPrintToast('Network error while deleting QR labels. Please try again.', 'danger');
    })
    .finally(() => {
        if (confirmButton) {
            confirmButton.disabled = false;
            confirmButton.innerHTML = originalButtonHtml;
        }
    });
}

/**
 * Add QR labels or extend a master QR pack from the labels page
 */
let adjustmentQrTargetIndex = null;

function produceNewAdjustmentQR(itemIndex = null) {
    if (!GENERATED_ITEMS || GENERATED_ITEMS.length === 0) {
        showPrintToast('Please select a Brand and Generated Code first to produce adjustment QR labels.', 'warning');
        return;
    }

    if (!CAN_EDIT_WAREHOUSE_QR && !CAN_CREATE_WAREHOUSE_QR) {
        showPrintToast('You do not have permission to edit packs or add QR labels.', 'danger');
        return;
    }
    const activeIndices = GENERATED_ITEMS
        .map((item, index) => item && !item.is_deleted ? index : -1)
        .filter(index => index >= 0);
    adjustmentQrTargetIndex = Number.isInteger(itemIndex) ? itemIndex : activeIndices[activeIndices.length - 1];
    const item = GENERATED_ITEMS[adjustmentQrTargetIndex];
    if (!item || item.is_deleted) {
        showPrintToast('Cannot create a new QR because this page has no QR details to copy.', 'warning');
        return;
    }

    const isAggregateQr = !!(item.is_series || item.is_complementary);
    const modalEl = document.getElementById('adjustmentQrModal');
    const countInput = document.getElementById('adjustmentQrCount');
    const displaySku = item.clean_sku || item.sku || '';
    document.getElementById('adjustmentQrProductName').textContent = item.product_name || displaySku || 'Selected product';
    document.getElementById('adjustmentQrCode').textContent = item.tag_code || '—';
    document.getElementById('adjustmentQrCurrentQty').textContent = String(item.pack_quantity || 1);
    document.getElementById('adjustmentQrModalTitle').innerHTML = isAggregateQr
        ? '<i class="bi bi-box-seam me-2"></i>Edit Master QR Pack'
        : '<i class="bi bi-qr-code me-2"></i>Add QR Labels';
    document.getElementById('adjustmentQrModalSubtitle').textContent = isAggregateQr
        ? 'Keep the same QR code and add units to its pack.'
        : 'Generate consecutive QR labels for this product.';
    document.getElementById('adjustmentQrCountLabel').textContent = isAggregateQr ? 'Additional units to add' : 'Number of QR labels';
    document.getElementById('adjustmentQrStockNote').textContent = isAggregateQr
        ? (item.is_inwarded
            ? 'This pack is already inwarded. Applying the change will add only these new units to warehouse stock.'
            : 'This pack is not inwarded yet. Added units will be included when you confirm the stock inward.')
        : 'A new QR label will be generated for each requested unit. This does not inward stock.';
    if (countInput) countInput.value = '1';

    if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
        showPrintToast('The QR adjustment dialog is unavailable. Refresh the page and try again.', 'danger');
        return;
    }
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
    setTimeout(() => countInput?.focus(), 150);
}

function submitAdjustmentQrChange(event) {
    event.preventDefault();
    const countInput = document.getElementById('adjustmentQrCount');
    const countValue = String(countInput?.value || '').trim();
    if (!/^[1-9]\d*$/.test(countValue) || Number(countValue) > 500) {
        showPrintToast('Enter a whole number between 1 and 500.', 'warning');
        return;
    }
    const count = Number(countValue);
    const targetIndex = adjustmentQrTargetIndex;
    const item = Number.isInteger(targetIndex) ? GENERATED_ITEMS[targetIndex] : null;
    if (!item || item.is_deleted) {
        showPrintToast('The selected QR label is no longer available. Refresh and try again.', 'warning');
        return;
    }

    const isAggregateQr = !!(item.is_series || item.is_complementary);
    const variantId = item.variant_id;
    const warehouseId = item.warehouse_id || document.getElementById('inwardWarehouseId')?.value || document.querySelector('input[name="warehouse_id"]')?.value || '1';
    const sourceType = document.querySelector('input[name="source_type"]')?.value || 'tp_purchase';
    const sourceId = document.querySelector('input[name="source_id"]')?.value || '0';

    const submitButton = document.getElementById('adjustmentQrSubmit');
    const originalButtonHtml = submitButton ? submitButton.innerHTML : '';
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Applying...';
    }

    const formData = new FormData();
    formData.append('variant_id', variantId);
    formData.append('count', String(count));
    if (isAggregateQr) {
        formData.append('aggregate_extension', '1');
        formData.append('qr_code', item.tag_code);
    }
    formData.append('warehouse_id', warehouseId);
    formData.append('source_type', sourceType);
    formData.append('source_id', sourceId);
    const csrfToken = document.querySelector('#adjustmentQrForm input[name="_csrf_token"]')?.value;
    if (csrfToken) formData.append('_csrf_token', csrfToken);

    fetch('<?= url("warehouse/labels/produce-new-qr") ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json().then(data => ({ response: res, data })))
    .then(({ response, data }) => {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHtml;
        }

        if (data.success && data.aggregate_extension && data.item) {
            if (GENERATED_ITEMS[targetIndex] !== item) {
                showPrintToast('The selected QR label changed while the request was running. Refresh and try again.', 'warning');
                return;
            }
            GENERATED_ITEMS[targetIndex] = data.item;
            window.hasStockOrQrChanged = true;
            updateAggregateStickerCard(data.item, targetIndex);
            const inwardStatusBadge = document.getElementById('inwardStatusBadge');
            if (data.stock_updated) {
                const liveWhStock = document.getElementById('liveCurrentWhStock');
                if (liveWhStock && data.warehouse_stock !== undefined) {
                    liveWhStock.textContent = `${data.warehouse_stock} pcs`;
                    liveWhStock.className = 'badge bg-success text-white px-2 py-1 fs-7';
                }
                if (inwardStatusBadge) {
                    inwardStatusBadge.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Pack updated; +${count} pcs credited to stock`;
                }
            } else if (isAggregateQr && inwardStatusBadge) {
                inwardStatusBadge.textContent = 'Added units are pending stock inward.';
            }
            updateSelectionCounter();
            const modalEl = document.getElementById('adjustmentQrModal');
            if (modalEl) (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
            showPrintToast(data.message || `Updated QR pack quantity to ${data.item.pack_quantity}.`, 'success');
            return;
        }

        const items = Array.isArray(data.items) ? data.items : (data.item ? [data.item] : []);
        if (data.success && items.length > 0) {
            window.hasStockOrQrChanged = true;
            document.getElementById('emptyGeneratedQrMessage')?.remove();

            items.forEach(newItem => {
                const newIndex = GENERATED_ITEMS.length;
                GENERATED_ITEMS.push(newItem);
                renderNewStickerCard(newItem, newIndex);
                selectedStickerIndices.add(newIndex);
            });
            updateSelectionCounter();

            const modalEl = document.getElementById('adjustmentQrModal');
            if (modalEl) (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
            showPrintToast(data.message || `Produced ${items.length} New QR label(s).`, 'success');
        } else {
            showPrintToast(data.message || `Failed to produce QR labels (HTTP ${response.status}).`, 'danger');
        }
    })
    .catch(err => {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHtml;
        }
        showPrintToast('Network error updating QR labels: ' + err.message, 'danger');
    });
}

function updateAggregateStickerCard(item, index) {
    const col = document.getElementById(`col-piece-${index}`);
    if (!col) return;
    const card = document.getElementById(`card-piece-${index}`);

    const packQty = Number(item.pack_quantity || 1);
    const packBadge = col.querySelector('.piece-label-badge');
    if (packBadge) {
        packBadge.innerHTML = `<i class="bi bi-boxes me-1"></i>Master Pack (${packQty} Pcs)`;
    }

    const displaySku = item.clean_sku || ((item.size_name && item.color_name)
        ? `${item.size_name}-${item.color_name}`
        : item.sku);
    const skuLabel = col.querySelector('.lbl-sku');
    if (skuLabel) skuLabel.textContent = `${displaySku} (${packQty} Pcs)`;

    const startMatch = String(item.series_start_code || item.tag_code || '').match(/(\d+)$/);
    const endMatch = String(item.series_end_code || '').match(/(\d+)$/);
    if (startMatch && endMatch) {
        const rangeText = `Range: ${parseInt(startMatch[1], 10)} → ${parseInt(endMatch[1], 10)}`;
        let rangeBadge = col.querySelector('.series-range-badge');
        if (!rangeBadge) {
            rangeBadge = document.createElement('div');
            rangeBadge.className = 'badge bg-light text-primary border font-monospace mt-1 px-1 py-0.5 w-100 series-range-badge';
            rangeBadge.style.fontSize = '0.54rem';
            rangeBadge.title = 'Piece Sequence Range';
            col.querySelector('.label-tag-code')?.after(rangeBadge);
        }
        if (rangeBadge) rangeBadge.textContent = rangeText;
    }

    if (item.is_inwarded && card) {
        card.classList.remove('selected-piece-card');
        card.classList.add('inwarded-piece-card');
        const checkbox = document.getElementById(`chk-piece-${index}`);
        if (checkbox) {
            checkbox.checked = false;
            checkbox.disabled = true;
            checkbox.title = 'Officially Inwarded to Inventory';
            selectedStickerIndices.delete(index);
        }
        const savedBadge = Array.from(card.querySelectorAll('.badge'))
            .find(badge => badge.textContent.includes('Saved QR'));
        if (savedBadge) {
            savedBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
            savedBadge.textContent = 'Inwarded QR';
        }
    }

    const summaryBadge = document.getElementById('masterPackTotalBadge');
    if (summaryBadge) {
        const aggregateItems = GENERATED_ITEMS.filter(generatedItem => generatedItem && (generatedItem.is_series || generatedItem.is_complementary) && !generatedItem.is_deleted);
        const totalPackQty = aggregateItems.reduce((total, generatedItem) => total + Number(generatedItem.pack_quantity || 1), 0);
        summaryBadge.textContent = `${aggregateItems.length} Master QR${aggregateItems.length === 1 ? '' : 's'} (${totalPackQty} Total Pcs)`;
    }
}

function renderNewStickerCard(newItem, newIndex) {
    const grid = document.getElementById('stickersCardGrid');
    if (!grid) return;

    const mrpNum = parseFloat(newItem.mrp || 0);
    const sellNum = parseFloat(newItem.selling_price || newItem.cost_price || newItem.mrp || 0);
    const displayMrp = (mrpNum % 1 === 0) ? mrpNum.toLocaleString() : mrpNum.toFixed(2);
    const displaySelling = (sellNum % 1 === 0) ? sellNum.toLocaleString() : sellNum.toFixed(2);
    const displaySku = newItem.clean_sku || ((newItem.size_name && newItem.color_name) ? `${newItem.size_name}-${newItem.color_name}` : newItem.sku);
    const qrPayload = newItem.tag_code;

    let rangeBadge = '';
    if (newItem.start_num && newItem.end_num) {
        rangeBadge = `${newItem.start_num} &rarr; ${newItem.end_num}`;
    } else if (newItem.series_start_code && newItem.series_end_code) {
        const mS = String(newItem.series_start_code).match(/^[A-Za-z]+\d{4}(\d+)$/) || String(newItem.series_start_code).match(/(\d+)$/);
        const mE = String(newItem.series_end_code).match(/^[A-Za-z]+\d{4}(\d+)$/) || String(newItem.series_end_code).match(/(\d+)$/);
        if (mS && mE) {
            rangeBadge = `${parseInt(mS[1], 10)} &rarr; ${parseInt(mE[1], 10)}`;
        }
    }

    const col = document.createElement('div');
    col.className = 'col sticker-grid-col';
    col.id = `col-piece-${newIndex}`;
    col.setAttribute('data-code', (newItem.tag_code || '').toUpperCase());
    col.setAttribute('data-sku', (displaySku || '').toUpperCase());
    col.setAttribute('data-piece-no', newItem.piece_no);

    if (newItem.is_series) {
        col.innerHTML = `
            <div class="card rounded-3 p-2.5 h-100 shadow-sm bg-white piece-card position-relative border-primary selected-piece-card" id="card-piece-${newIndex}">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input piece-select-chk mt-0" id="chk-piece-${newIndex}" data-index="${newIndex}" onchange="onPieceCheckChanged(${newIndex})" checked style="cursor: pointer;" title="Select this QR sticker">
                        <span class="badge bg-dark text-white font-monospace piece-label-badge" style="font-size: 0.68rem;">
                            <i class="bi bi-boxes me-1"></i>Master Pack (${newItem.pack_quantity || 20} Pcs)
                        </span>
                    </div>
                    <span class="badge bg-purple text-white" style="font-size: 0.68rem; background-color: #6f42c1 !important;">
                        <i class="bi bi-qr-code me-1"></i>${newItem.is_complementary ? 'Complementary Pack QR' : 'Series QR'}
                    </span>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                        Saved QR — Not Inwarded
                    </span>
                </div>
                
                <div class="p-2 border rounded-3 bg-white shadow-xs d-flex align-items-start gap-2 mb-2" style="background: #fafafa;">
                    <div class="text-center flex-shrink-0" style="width: 80px;">
                        <div class="piece-qr-box bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" id="qr-box-${newIndex}" style="width: 72px; height: 72px;"></div>
                        <div class="label-tag-code badge bg-white text-dark border font-monospace mt-1 px-1 py-0.5 w-100" style="font-size: 0.56rem; letter-spacing: 0.1px;" title="${newItem.tag_code}">
                            ${newItem.tag_code}
                        </div>
                        ${rangeBadge ? `<div class="badge bg-light text-primary border font-monospace mt-1 px-1 py-0.5 w-100 series-range-badge" style="font-size: 0.54rem;" title="Piece Sequence Range">Range: ${rangeBadge}</div>` : ''}
                    </div>
                    <div class="overflow-hidden flex-grow-1" style="font-size: 0.76rem; margin-top: 1px;">
                        <div class="lbl-title fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.80rem; letter-spacing: 0.2px;" title="${escapeHtml(newItem.brand_name || 'GRM CLASSIC')}">
                            ${escapeHtml(newItem.brand_name || 'GRM CLASSIC')}
                        </div>
                        <div class="lbl-sku fw-bold text-dark text-truncate mt-0.5" style="font-size: 0.84rem; color: #0f172a;" title="${escapeHtml(displaySku)}">
                            ${escapeHtml(displaySku)} (${newItem.pack_quantity || 20} Pcs)
                        </div>
                        <div class="lbl-mrp text-muted small mt-0.5 text-decoration-line-through" style="font-size: 0.72rem;">
                            MRP: ₹${displayMrp}
                        </div>
                        <div class="lbl-disc fw-bold text-success mt-0.5" style="font-size: 0.82rem; color: #16a34a !important;">
                            Disc: ₹${displaySelling}
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-1.5 mt-auto pt-2 border-top no-print">
                    <button type="button" class="btn btn-xs btn-outline-primary flex-fill fw-semibold py-1.5" onclick="previewSingleTag(${newIndex})" title="Preview 2D QR Code Tag">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-dark flex-fill fw-semibold py-1.5" onclick="printSinglePieceSticker(${newIndex})" title="Print Thermal Sticker">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    ${CAN_DELETE_WAREHOUSE_QR && !newItem.is_inwarded ? `<button type="button" class="btn btn-xs btn-outline-danger flex-fill fw-semibold py-1.5" onclick="deleteSingleQr(${newIndex})" title="Delete this QR label"><i class="bi bi-trash3"></i> Delete</button>` : ''}
                </div>
            </div>
        `;
    } else {
        col.innerHTML = `
            <div class="card rounded-3 p-2.5 h-100 shadow-sm bg-white piece-card position-relative border-warning transition-all selected-piece-card" id="card-piece-${newIndex}">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input piece-select-chk mt-0" id="chk-piece-${newIndex}" data-index="${newIndex}" onchange="onPieceCheckChanged(${newIndex})" checked style="cursor: pointer;" title="Select this QR sticker">
                        <label for="chk-piece-${newIndex}" class="badge bg-primary text-white border border-primary font-monospace mb-0 user-select-none piece-label-badge" style="cursor: pointer;">
                            Piece #${newItem.piece_no} (Adjustment)
                        </label>
                    </div>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                        <i class="bi bi-tools me-1"></i>New QR
                    </span>
                </div>
                
                <div class="p-2 border rounded-3 bg-white shadow-xs d-flex align-items-start gap-2 mb-2" style="background: #fafafa;">
                    <div class="text-center flex-shrink-0" style="width: 74px;">
                        <div class="piece-qr-box bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" id="qr-box-${newIndex}" style="width: 72px; height: 72px;"></div>
                        <div class="label-tag-code badge bg-white text-dark border font-monospace mt-1 px-1 py-0.5 w-100" style="font-size: 0.56rem; letter-spacing: 0.1px;" title="${newItem.tag_code}">
                            ${newItem.tag_code}
                        </div>
                    </div>
                    <div class="overflow-hidden flex-grow-1" style="font-size: 0.76rem; margin-top: 1px;">
                        <div class="lbl-title fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.80rem; letter-spacing: 0.2px;" title="${escapeHtml(newItem.brand_name || 'GRM CLASSIC')}">
                            ${escapeHtml(newItem.brand_name || 'GRM CLASSIC')}
                        </div>
                        <div class="lbl-sku fw-bold text-dark text-truncate mt-0.5" style="font-size: 0.84rem; color: #0f172a;" title="${escapeHtml(displaySku)}">
                            ${escapeHtml(displaySku)}
                        </div>
                        <div class="lbl-mrp text-muted small mt-0.5 text-decoration-line-through" style="font-size: 0.72rem;">
                            MRP: ₹${displayMrp}
                        </div>
                        <div class="lbl-disc fw-bold text-success mt-0.5" style="font-size: 0.82rem; color: #16a34a !important;">
                            Disc: ₹${displaySelling}
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-1.5 mt-auto pt-2 border-top no-print">
                    <button type="button" class="btn btn-xs btn-outline-primary flex-fill fw-semibold py-1.5" onclick="previewSingleTag(${newIndex})" title="Preview 2D QR Code Tag">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-dark flex-fill fw-semibold py-1.5" onclick="printSinglePieceSticker(${newIndex})" title="Print Thermal Sticker">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    ${CAN_DELETE_WAREHOUSE_QR && !newItem.is_inwarded ? `<button type="button" class="btn btn-xs btn-outline-danger flex-fill fw-semibold py-1.5" onclick="deleteSingleQr(${newIndex})" title="Delete this QR label"><i class="bi bi-trash3"></i> Delete</button>` : ''}
                </div>
            </div>
        `;
    }

    grid.appendChild(col);

    const qrBox = document.getElementById(`qr-box-${newIndex}`);
    if (qrBox) {
        generateQRCodeContainer(qrBox, qrPayload, 68);
    }

    const totalCols = GENERATED_ITEMS.filter(item => item && !item.is_deleted).length;
    const totalBadge = document.getElementById('totalPiecesBadge');
    if (totalBadge) totalBadge.textContent = `${totalCols} Available Pieces`;
    const totalSelectable = document.getElementById('totalSelectableCount');
    if (totalSelectable) totalSelectable.textContent = totalCols;
    const stickersGenCount = document.getElementById('lblStickersGeneratedCount');
    if (stickersGenCount) stickersGenCount.textContent = totalCols;

    const tagRange = document.getElementById('inwardTagRangeText');
    const activeItems = GENERATED_ITEMS.filter(item => item && !item.is_deleted);
    if (tagRange && activeItems.length) {
        tagRange.textContent = `${activeItems[0].tag_code} -> ${activeItems[activeItems.length - 1].tag_code}`;
    }

    col.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Print a single piece sticker (Direct Thermal TSPL with browser fallback)
 */
function printSinglePieceSticker(idx) {
    if (!GENERATED_ITEMS[idx]) return;
    const item = GENERATED_ITEMS[idx];

    const mrpNum = parseFloat(item.mrp || 0);
    const sellNum = parseFloat(item.selling_price || item.cost_price || item.mrp || 0);
    const fmtMrp = (mrpNum % 1 === 0) ? String(Math.round(mrpNum)) : mrpNum.toFixed(2);
    const fmtDisc = (sellNum % 1 === 0) ? String(Math.round(sellNum)) : sellNum.toFixed(2);
    const displaySku = item.clean_sku || ((item.size_name && item.color_name) ? `${item.size_name}-${item.color_name}` : item.sku);

    const labelPayload = [{
        sku: displaySku,
        productName: item.product_name,
        categoryHierarchy: item.category_hierarchy || '',
        categoryName: item.category_name || '',
        subcategoryName: item.subcategory_name || '',
        subSubcategoryName: item.sub_subcategory_name || '',
        sizeName: item.size_name || 'Free',
        colorName: item.color_name || 'Std',
        attrText: `${item.size_name || 'Free'}-${item.color_name || 'Std'}`,
        basePrice: fmtMrp,
        discountPrice: fmtDisc,
        damageCode: item.tag_code,
        title: (item.brand_name || "GRM CLASSIC").toUpperCase(),
        qrPayload: item.qr_payload || item.tag_code,
        quantity: 1
    }];

    printLabel(labelPayload);
}

/**
 * Print all generated stickers to TSC TE244 thermal printer
 */
function printAllGeneratedStickers() {
    if (!GENERATED_ITEMS || GENERATED_ITEMS.length === 0) {
        alert('No stickers generated to print.');
        return;
    }

    const activeItems = GENERATED_ITEMS.filter(item => item && !item.is_deleted);
    if (activeItems.length === 0) {
        alert('No active stickers generated to print.');
        return;
    }

    const batchLabels = activeItems.map(item => {
        const mrpNum = parseFloat(item.mrp || 0);
        const sellNum = parseFloat(item.selling_price || item.cost_price || item.mrp || 0);
        const fmtMrp = (mrpNum % 1 === 0) ? String(Math.round(mrpNum)) : mrpNum.toFixed(2);
        const fmtDisc = (sellNum % 1 === 0) ? String(Math.round(sellNum)) : sellNum.toFixed(2);
        const displaySku = item.clean_sku || ((item.size_name && item.color_name) ? `${item.size_name}-${item.color_name}` : item.sku);
        return {
            sku: displaySku,
            productName: item.product_name,
            categoryHierarchy: item.category_hierarchy || '',
            categoryName: item.category_name || '',
            subcategoryName: item.subcategory_name || '',
            subSubcategoryName: item.sub_subcategory_name || '',
            sizeName: item.size_name || 'Free',
            colorName: item.color_name || 'Std',
            attrText: `${item.size_name || 'Free'}-${item.color_name || 'Std'}`,
            basePrice: fmtMrp,
            discountPrice: fmtDisc,
            damageCode: item.tag_code,
            title: (item.brand_name || "GRM CLASSIC").toUpperCase(),
            qrPayload: item.qr_payload || item.tag_code,
            quantity: 1
        };
    });

    printLabel(batchLabels);
}

/**
 * Direct TSPL Thermal Print Function (Port 9191)
 */
async function printLabel(labels) {
    try {
        const response = await fetch('http://127.0.0.1:9191/print', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ labels: labels })
        });

        if (response.ok) {
            const result = await response.json();
            showPrintToast(result.message || 'Printed successfully to TSC TE244!', 'success');
        } else {
            const err = await response.json().catch(() => ({}));
            showPrintToast(`Printer helper error: ${err.error || response.statusText}. Opening browser print sheet...`, 'warning');
            openBrowserPrintSheet(labels);
        }
    } catch (e) {
        console.warn('Local print service not responding on port 9191. Falling back to browser print dialog.', e);
        showPrintToast('Local printer service (port 9191) offline. Using browser print sheet.', 'info');
        openBrowserPrintSheet(labels);
    }
}

/**
 * Fallback browser print sheet modal (Exact Image 1 UI Card Layout)
 */
function openBrowserPrintSheet(labels) {
    const container = document.getElementById('modalPrintArea');
    if (!container) return;
    container.innerHTML = '';

    labels.forEach((lbl, i) => {
        const div = document.createElement('div');
        div.className = 'stock-sticker-item';
        
        const attrText = (lbl.attrText || (lbl.sizeName && lbl.colorName ? `${lbl.sizeName}-${lbl.colorName}` : (lbl.sku || ''))).toUpperCase();
        const mrpNum = parseFloat(lbl.basePrice || 0);
        const sellNum = parseFloat(lbl.discountPrice || lbl.basePrice || 0);
        const fmtMrp = (mrpNum % 1 === 0) ? String(Math.round(mrpNum)) : mrpNum.toFixed(2);
        const fmtSelling = (sellNum % 1 === 0) ? String(Math.round(sellNum)) : sellNum.toFixed(2);
        const displayMrp = (mrpNum % 1 === 0) ? mrpNum.toLocaleString() : mrpNum.toFixed(2);
        const displaySelling = (sellNum % 1 === 0) ? sellNum.toLocaleString() : sellNum.toFixed(2);
        const qrPayload = lbl.qrPayload || lbl.damageCode;
        const titleText = (lbl.title || 'GRM CLASSIC').toUpperCase();

        div.innerHTML = `
            <div class="d-flex align-items-start gap-2.5">
                <div class="text-center flex-shrink-0" style="width: 76px;">
                    <div id="modal-qr-${i}" class="bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" style="width: 74px; height: 74px;"></div>
                    <div class="label-tag-code badge bg-white text-dark border font-monospace mt-1 px-1.5 py-0.5 w-100" style="font-size: 0.56rem; letter-spacing: 0.1px;">
                        ${lbl.damageCode}
                    </div>
                </div>
                <div class="overflow-hidden ms-1 flex-grow-1" style="margin-top: 2px;">
                    <div class="lbl-title fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.82rem; letter-spacing: 0.3px;">
                        ${titleText}
                    </div>
                    <div class="lbl-sku fw-bold text-dark text-truncate mt-0.5" style="font-size: 0.84rem; color: #0f172a;">
                        ${attrText}
                    </div>
                    <div class="lbl-mrp text-muted small text-decoration-line-through mt-0.5" style="font-size: 0.74rem;">
                        MRP: ₹${displayMrp}
                    </div>
                    <div class="lbl-disc fw-bold text-success mt-0.5" style="font-size: 0.84rem; color: #16a34a !important;">
                        Disc: ₹${displaySelling}
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);

        setTimeout(() => {
            const qrEl = document.getElementById(`modal-qr-${i}`);
            if (qrEl) {
                generateQRCodeContainer(qrEl, qrPayload, 70);
            }
        }, 50);
    });

    const modal = new bootstrap.Modal(document.getElementById('warehousePrintSheetModal'));
    modal.show();
}

/**
 * Toast notification for print actions
 */
function showPrintToast(message, type = 'success') {
    let container = document.getElementById('printToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'printToastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastId = 'toast_' + Date.now();
    const bgClass = type === 'success' ? 'bg-success text-white' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-info text-white');
    const icon = type === 'success' ? 'bi-check-circle-fill' : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2 fs-6">
                    <i class="bi ${icon} fs-5"></i>
                    <span>${message}</span>
                </div>
                <button type="button" class="btn-close ${type === 'warning' ? '' : 'btn-close-white'} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(toastId);
    if (window.bootstrap && bootstrap.Toast) {
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
    } else {
        setTimeout(() => toastEl.remove(), 4000);
    }
}

/**
 * Open Printer & Alignment Settings Modal & Load Current Config
 */
async function openPrinterSettingsModal() {
    try {
        const response = await fetch('http://127.0.0.1:9191/config');
        if (response.ok) {
            const data = await response.json();
            if (data && data.config) {
                const cfg = data.config;
                if (document.getElementById('cfgWidth')) document.getElementById('cfgWidth').value = cfg.labelWidthMm || 40;
                if (document.getElementById('cfgHeight')) document.getElementById('cfgHeight').value = cfg.labelHeightMm || 25;
                if (document.getElementById('cfgColumns')) document.getElementById('cfgColumns').value = cfg.columns || 2;
                if (document.getElementById('cfgGap')) document.getElementById('cfgGap').value = cfg.gapMm || 2;
                if (document.getElementById('cfgCol2ShiftDots')) document.getElementById('cfgCol2ShiftDots').value = cfg.col2ShiftDots !== undefined ? cfg.col2ShiftDots : 0;
                
                if (document.getElementById('cfgQrX')) document.getElementById('cfgQrX').value = cfg.qrX !== undefined ? cfg.qrX : 12;
                if (document.getElementById('cfgQrY')) document.getElementById('cfgQrY').value = cfg.qrY !== undefined ? cfg.qrY : 35;
                if (document.getElementById('cfgQrCellSize')) document.getElementById('cfgQrCellSize').value = cfg.qrCellSize || 3;
                if (document.getElementById('cfgCodeX')) document.getElementById('cfgCodeX').value = cfg.codeX !== undefined ? cfg.codeX : 12;
                if (document.getElementById('cfgCodeY')) document.getElementById('cfgCodeY').value = cfg.codeY !== undefined ? cfg.codeY : 125;
                
                if (document.getElementById('cfgTextX')) document.getElementById('cfgTextX').value = cfg.textX !== undefined ? cfg.textX : 115;
                if (document.getElementById('cfgTextFont')) document.getElementById('cfgTextFont').value = cfg.textFont || "2";
                if (document.getElementById('cfgTitleY')) document.getElementById('cfgTitleY').value = cfg.titleY !== undefined ? cfg.titleY : 35;
                if (document.getElementById('cfgSkuY')) document.getElementById('cfgSkuY').value = cfg.skuY !== undefined ? cfg.skuY : 62;
                if (document.getElementById('cfgMrpY')) document.getElementById('cfgMrpY').value = cfg.mrpY !== undefined ? cfg.mrpY : 90;
                if (document.getElementById('cfgDiscY')) document.getElementById('cfgDiscY').value = cfg.discY !== undefined ? cfg.discY : 118;
            }
        }
    } catch (e) {
        console.warn('Could not fetch current printer config:', e);
    }

    updateLivePreview();
    const modal = new bootstrap.Modal(document.getElementById('printerSettingsModal'));
    modal.show();
}

/**
 * Update Live Visual Sticker Layout Preview Box (Dual Sticker Roll)
 */
function updateLivePreview() {
    const widthMm = parseFloat(document.getElementById('cfgWidth')?.value || 40);
    const heightMm = parseFloat(document.getElementById('cfgHeight')?.value || 25);
    const columns = parseInt(document.getElementById('cfgColumns')?.value || 2);
    const col2ShiftDots = parseFloat(document.getElementById('cfgCol2ShiftDots')?.value || 0);

    const badge = document.getElementById('previewDimensionBadge');
    if (badge) badge.textContent = `${columns === 2 ? 'Dual Roll ' : ''}${widthMm}mm × ${heightMm}mm`;

    const card1 = document.getElementById('liveStickerCard1');
    const card2 = document.getElementById('liveStickerCard2');
    if (!card1) return;

    const scale = 240 / widthMm;
    const cardPxWidth = 240;
    const cardPxHeight = Math.max(100, Math.round(heightMm * scale));

    card1.style.width = `${cardPxWidth}px`;
    card1.style.height = `${cardPxHeight}px`;

    if (card2) {
        if (columns === 1) {
            card2.style.display = 'none';
        } else {
            card2.style.display = 'block';
            card2.style.width = `${cardPxWidth}px`;
            card2.style.height = `${cardPxHeight}px`;
        }
    }

    const badge2 = document.getElementById('badgeSticker2');
    if (badge2) {
        if (col2ShiftDots !== 0) {
            badge2.textContent = `Right Sticker #2 (${col2ShiftDots > 0 ? '+' : ''}${col2ShiftDots} dots)`;
            badge2.className = 'badge bg-primary text-white position-absolute top-0 start-0 m-1 border';
        } else {
            badge2.textContent = 'Right Sticker #2';
            badge2.className = 'badge bg-light text-dark position-absolute top-0 start-0 m-1 border';
        }
    }

    const dotScale = scale / 8.12;

    const qrX = parseFloat(document.getElementById('cfgQrX')?.value || 12) * dotScale;
    const qrY = parseFloat(document.getElementById('cfgQrY')?.value || 35) * dotScale;
    const qrSize = parseInt(document.getElementById('cfgQrCellSize')?.value || 3);
    const qrPxDim = Math.max(20, Math.round(qrSize * 8.5 * (scale / 4)));

    const codeX = parseFloat(document.getElementById('cfgCodeX')?.value || 12) * dotScale;
    const codeY = parseFloat(document.getElementById('cfgCodeY')?.value || 125) * dotScale;

    const textX = parseFloat(document.getElementById('cfgTextX')?.value || 115) * dotScale;
    const titleY = parseFloat(document.getElementById('cfgTitleY')?.value || 35) * dotScale;
    const skuY = parseFloat(document.getElementById('cfgSkuY')?.value || 62) * dotScale;
    const mrpY = parseFloat(document.getElementById('cfgMrpY')?.value || 90) * dotScale;
    const discY = parseFloat(document.getElementById('cfgDiscY')?.value || 118) * dotScale;

    // Apply to Left Card
    const qb1 = document.getElementById('prevQRBox1');
    if (qb1) { qb1.style.left = `${qrX}px`; qb1.style.top = `${qrY}px`; qb1.style.width = `${qrPxDim}px`; qb1.style.height = `${qrPxDim}px`; }

    const ct1 = document.getElementById('prevCodeTxt1');
    if (ct1) { ct1.style.left = `${codeX}px`; ct1.style.top = `${codeY}px`; }

    const tt1 = document.getElementById('prevTitleTxt1');
    if (tt1) { tt1.style.left = `${textX}px`; tt1.style.top = `${titleY}px`; }

    const st1 = document.getElementById('prevSkuTxt1');
    if (st1) { st1.style.left = `${textX}px`; st1.style.top = `${skuY}px`; }

    const mt1 = document.getElementById('prevMrpTxt1');
    if (mt1) { mt1.style.left = `${textX}px`; mt1.style.top = `${mrpY}px`; }

    const dt1 = document.getElementById('prevDiscTxt1');
    if (dt1) { dt1.style.left = `${textX}px`; dt1.style.top = `${discY}px`; }

    // Apply to Right Card
    const col2ShiftPx = col2ShiftDots * dotScale;
    const qb2 = document.getElementById('prevQRBox2');
    if (qb2) { qb2.style.left = `${qrX + col2ShiftPx}px`; qb2.style.top = `${qrY}px`; qb2.style.width = `${qrPxDim}px`; qb2.style.height = `${qrPxDim}px`; }

    const ct2 = document.getElementById('prevCodeTxt2');
    if (ct2) { ct2.style.left = `${codeX + col2ShiftPx}px`; ct2.style.top = `${codeY}px`; }

    const tt2 = document.getElementById('prevTitleTxt2');
    if (tt2) { tt2.style.left = `${textX + col2ShiftPx}px`; tt2.style.top = `${titleY}px`; }

    const st2 = document.getElementById('prevSkuTxt2');
    if (st2) { st2.style.left = `${textX + col2ShiftPx}px`; st2.style.top = `${skuY}px`; }

    const mt2 = document.getElementById('prevMrpTxt2');
    if (mt2) { mt2.style.left = `${textX + col2ShiftPx}px`; mt2.style.top = `${mrpY}px`; }

    const dt2 = document.getElementById('prevDiscTxt2');
    if (dt2) { dt2.style.left = `${textX + col2ShiftPx}px`; dt2.style.top = `${discY}px`; }
}

/**
 * Save Printer Settings to local printer service config
 */
async function savePrinterSettings() {
    const configData = {
        labelWidthMm: parseFloat(document.getElementById('cfgWidth')?.value || 40),
        labelHeightMm: parseFloat(document.getElementById('cfgHeight')?.value || 25),
        columns: parseInt(document.getElementById('cfgColumns')?.value || 2),
        gapMm: parseFloat(document.getElementById('cfgGap')?.value || 2),
        col2ShiftDots: parseFloat(document.getElementById('cfgCol2ShiftDots')?.value || 0),
        qrX: parseInt(document.getElementById('cfgQrX')?.value || 12),
        qrY: parseInt(document.getElementById('cfgQrY')?.value || 35),
        qrCellSize: parseInt(document.getElementById('cfgQrCellSize')?.value || 3),
        codeX: parseInt(document.getElementById('cfgCodeX')?.value || 12),
        codeY: parseInt(document.getElementById('cfgCodeY')?.value || 125),
        textX: parseInt(document.getElementById('cfgTextX')?.value || 115),
        textFont: document.getElementById('cfgTextFont')?.value || "2",
        titleY: parseInt(document.getElementById('cfgTitleY')?.value || 35),
        skuY: parseInt(document.getElementById('cfgSkuY')?.value || 62),
        mrpY: parseInt(document.getElementById('cfgMrpY')?.value || 90),
        discY: parseInt(document.getElementById('cfgDiscY')?.value || 118),
    };

    try {
        const response = await fetch('http://127.0.0.1:9191/config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(configData)
        });

        if (response.ok) {
            showPrintToast('Printer settings saved successfully!', 'success');
            const modalEl = document.getElementById('printerSettingsModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        } else {
            showPrintToast('Could not save configuration to printer service.', 'warning');
        }
    } catch (e) {
        showPrintToast('Printer service not responding on port 9191.', 'warning');
    }
}

/**
 * Test Print 1 Sticker
 */
function testPrintCurrentSettings() {
    printLabel([
        {
            sku: "TEST-SKU",
            basePrice: "999.00",
            discountPrice: "799.00",
            damageCode: "TST01",
            title: "GRM WAREHOUSE",
            quantity: 1
        }
    ]);
}



// -----------------------------------------------------------------------------
// Category, Subcategory & Sub-Subcategory Dynamic Cascading Filters (For Returns)
// -----------------------------------------------------------------------------
const allSubcategoriesData = <?= json_encode($subcategories ?? []) ?>;
const allSubSubcategoriesData = <?= json_encode($subSubcategories ?? []) ?>;

function onCategoryFilterChange(tabType, preselectSubId = null, preselectSubSubId = null) {
    const catSelect = document.getElementById(tabType + 'CatFilter');
    const subcatSelect = document.getElementById(tabType + 'SubcatFilter');
    const subSubcatSelect = document.getElementById(tabType + 'SubSubcatFilter');
    
    if (!catSelect || !subcatSelect) return;
    const catId = catSelect.value ? String(catSelect.value) : '';
    const currentSubVal = preselectSubId !== null ? String(preselectSubId) : subcatSelect.value;
    const currentSubSubVal = preselectSubSubId !== null ? String(preselectSubSubId) : (subSubcatSelect ? subSubcatSelect.value : '');

    subcatSelect.innerHTML = '<option value="">-- All Subcategories --</option>';
    if (subSubcatSelect) {
        subSubcatSelect.innerHTML = '<option value="">-- All Sub-Subcategories --</option>';
    }

    const subsToRender = catId ? allSubcategoriesData.filter(sc => String(sc.category_id) === catId) : allSubcategoriesData;
    subsToRender.forEach(sc => {
        const opt = document.createElement('option');
        opt.value = sc.id;
        opt.textContent = sc.name;
        opt.setAttribute('data-cat', sc.category_id);
        if (currentSubVal && String(sc.id) === String(currentSubVal)) {
            opt.selected = true;
        }
        subcatSelect.appendChild(opt);
    });

    if (subcatSelect.value) {
        onSubcategoryFilterChange(tabType, preselectSubSubId);
    } else {
        if (subSubcatSelect) {
            const validSubIds = catId ? subsToRender.map(sc => String(sc.id)) : null;
            const subSubsToRender = validSubIds ? allSubSubcategoriesData.filter(ssc => validSubIds.includes(String(ssc.subcategory_id))) : allSubSubcategoriesData;
            subSubsToRender.forEach(ssc => {
                const opt = document.createElement('option');
                opt.value = ssc.id;
                opt.textContent = ssc.name;
                opt.setAttribute('data-subcat', ssc.subcategory_id);
                if (currentSubSubVal && String(ssc.id) === String(currentSubSubVal)) {
                    opt.selected = true;
                }
                subSubcatSelect.appendChild(opt);
            });
        }
        filterVariantsByHierarchy(tabType);
    }
}

function onSubcategoryFilterChange(tabType, preselectSubSubId = null) {
    const subcatSelect = document.getElementById(tabType + 'SubcatFilter');
    const subSubcatSelect = document.getElementById(tabType + 'SubSubcatFilter');

    if (!subcatSelect) return;
    const subcatId = subcatSelect.value ? String(subcatSelect.value) : '';
    const currentSubSubVal = preselectSubSubId !== null ? String(preselectSubSubId) : (subSubcatSelect ? subSubcatSelect.value : '');

    if (subSubcatSelect) {
        subSubcatSelect.innerHTML = '<option value="">-- All Sub-Subcategories --</option>';
        const subSubsToRender = subcatId ? allSubSubcategoriesData.filter(ssc => String(ssc.subcategory_id) === subcatId) : allSubSubcategoriesData;
        subSubsToRender.forEach(ssc => {
            const opt = document.createElement('option');
            opt.value = ssc.id;
            opt.textContent = ssc.name;
            opt.setAttribute('data-subcat', ssc.subcategory_id);
            if (currentSubSubVal && String(ssc.id) === String(currentSubSubVal)) {
                opt.selected = true;
            }
            subSubcatSelect.appendChild(opt);
        });
    }

    filterVariantsByHierarchy(tabType);
}

function filterVariantsByHierarchy(tabType) {
    const variantSelect = document.getElementById('returnsVariantSelect');
    if (!variantSelect) return;

    const catVal = (document.getElementById(tabType + 'CatFilter')?.value || '').trim();
    const subcatVal = (document.getElementById(tabType + 'SubcatFilter')?.value || '').trim();
    const subSubcatVal = (document.getElementById(tabType + 'SubSubcatFilter')?.value || '').trim();

    for (let i = 0; i < variantSelect.options.length; i++) {
        const opt = variantSelect.options[i];
        if (!opt.value) {
            opt.style.display = '';
            continue;
        }

        const optCat = opt.getAttribute('data-cat') || '';
        const optSubcat = opt.getAttribute('data-subcat') || '';
        const optSubSubcat = opt.getAttribute('data-subsubcat') || '';

        let match = true;
        if (catVal && optCat !== catVal) match = false;
        if (subcatVal && optSubcat !== subcatVal) match = false;
        if (subSubcatVal && optSubSubcat !== subSubcatVal) match = false;

        opt.style.display = match ? '' : 'none';
    }

    if (variantSelect.selectedOptions.length > 0 && variantSelect.selectedOptions[0].style.display === 'none') {
        variantSelect.value = '';
    }
}

function onReturnVariantChange(selectEl) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const customBrandInp = document.getElementById('returnsCustomBrand');
    if (selectedOpt && customBrandInp && (!customBrandInp.value || customBrandInp.value === 'GRM RETURN')) {
        const b = selectedOpt.getAttribute('data-brand');
        if (b && b !== 'Third-Party Brand') {
            customBrandInp.value = b;
        }
    }
}

// Auto-initialize details and dynamic count on page load
document.addEventListener('DOMContentLoaded', function() {
    const brandSelect = document.getElementById('brandFilterSelect');
    const catCodeSelect = document.getElementById('catalogCodeFilterSelect');

    // Filter Generated Codes by selected brand on initial load without auto-submitting
    if (brandSelect && brandSelect.value) {
        onBrandFilterChange(brandSelect.value, false);
    }

    if (catCodeSelect && catCodeSelect.value) {
        const opt = catCodeSelect.options[catCodeSelect.selectedIndex];
        if (opt && opt.value) {
            renderCodeDetails(opt);
        }
    }

    // Initialize selective inward button state
    syncInwardButtonWithSelection();

    // Form submit validation: strictly prevent generating QR labels if count is 0 or already inwarded
    const qrForm = document.getElementById('qrGenForm');
    if (qrForm) {
        qrForm.addEventListener('submit', function(e) {
            const currentCode = document.getElementById('catalogCodeFilterSelect');
            if (currentCode && currentCode.value) {
                const opt = currentCode.options[currentCode.selectedIndex];
                const stock = opt ? parseInt(opt.getAttribute('data-stock') || '0', 10) : 0;
                const poId = parseInt(document.querySelector('input[name="source_id"]')?.value || '0', 10);
                if (stock <= 0 && poId <= 0) {
                    e.preventDefault();
                    alert('QR Label Generation Restricted: This product has 0 stock count. Cannot generate QR labels for out-of-stock items.');
                    return false;
                }
            }
        });
    }

    const pageUrl = new URL(window.location.href);
    if (pageUrl.searchParams.get('edit_qr_pack') === '1') {
        pageUrl.searchParams.delete('edit_qr_pack');
        window.history.replaceState(null, '', pageUrl.toString());

        const editIndices = GENERATED_ITEMS
            .map((item, index) => item && !item.is_deleted && (item.is_series || item.is_complementary) ? index : -1)
            .filter(index => index >= 0);
        const editItem = editIndices.length === 1 ? GENERATED_ITEMS[editIndices[0]] : null;
        if (!CAN_EDIT_WAREHOUSE_QR || editIndices.length === 0 || (editItem && editItem.is_inwarded && !CAN_EDIT_INWARDED_WAREHOUSE_QR)) {
            showPrintToast('This master QR pack is not available for editing with your current permissions.', 'warning');
        } else if (editIndices.length > 1) {
            document.getElementById('stickersCardGrid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            showPrintToast('Choose the master QR pack you want to edit below.', 'info');
        } else {
            setTimeout(() => produceNewAdjustmentQR(editIndices[0]), 150);
        }
    }
});
</script>
<?php endif; ?>
