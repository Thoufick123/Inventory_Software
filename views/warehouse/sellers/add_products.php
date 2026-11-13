<?php
// views/warehouse/sellers/add_products.php
// Supplier Registration Wizard — Phase 2: Category Tile Auto-Fill & Product Entry
// Enforces mandatory: HSN Code, Weight, MRP, Selling Price, Supplier Bought Price, Quantity.
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-boxes me-2 text-primary"></i>Phase 2: Add Products for Supplier</h2>
        <p class="text-muted small mb-0">Select pre-set categories to auto-fill hierarchy, then enter product pricing, HSN &amp; weights.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('warehouse/sellers/create?seller_id=' . (int)$seller['id'] . '&mode=edit') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Phase 1
        </a>
    </div>
</div>

<?php if (empty($warehouses)): ?>
    <div class="alert alert-warning shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        No active warehouse is available for this purchase. Create or activate one in
        <a href="<?= url('admin/dashboard?tab=warehouses') ?>" class="alert-link">Admin &gt; Warehouses</a>
        before saving these products.
    </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- 2-PHASE WIZARD STEPPER -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <!-- Phase 1 Step Indicator (Completed) -->
            <div class="col-md-6">
                <div class="d-flex align-items-center p-2.5 rounded-3 bg-success-subtle border border-success">
                    <div class="badge rounded-circle bg-success text-white me-3 fs-6 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-check-lg fs-5"></i>
                    </div>
                    <div>
                        <span class="badge bg-success text-white mb-0.5">COMPLETED</span>
                        <div class="fw-bold text-dark">1. Supplier Details</div>
                        <small class="text-muted"><?= e($seller['name']) ?><?= !empty($seller['company']) ? ' (' . e($seller['company']) . ')' : '' ?></small>
                    </div>
                </div>
            </div>

            <!-- Phase 2 Step Indicator (Active) -->
            <div class="col-md-6">
                <div class="d-flex align-items-center p-2.5 rounded-3 bg-primary-subtle border border-primary">
                    <div class="badge rounded-circle bg-primary text-white me-3 fs-6 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        2
                    </div>
                    <div>
                        <span class="badge bg-primary text-white mb-0.5">CURRENT STEP</span>
                        <div class="fw-bold text-dark">2. Product Entry</div>
                        <small class="text-muted">Auto-fill category hierarchy &amp; pricing</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Supplier & Settlement Summary Ribbon -->
<div class="card border-0 shadow-sm rounded-3 bg-white mb-4 border-start border-4 border-primary">
    <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2.5 bg-primary-subtle text-primary rounded-circle">
                <i class="bi bi-shop fs-4"></i>
            </div>
            <div>
                <h5 class="fw-bold text-dark mb-0"><?= e($seller['company'] ?: $seller['name']) ?></h5>
                <div class="text-muted small d-flex align-items-center gap-3 flex-wrap mt-0.5">
                    <?php if (!empty($seller['name']) && $seller['company']): ?>
                        <span><i class="bi bi-person me-1"></i>Contact: <?= e($seller['name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($seller['phone'])): ?>
                        <span><i class="bi bi-telephone me-1"></i><?= e($seller['phone']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($seller['gst_number'])): ?>
                        <span class="font-monospace"><i class="bi bi-card-text me-1"></i>GSTIN: <?= e($seller['gst_number']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($billSetup['brand_name'])): ?>
                        <span class="badge bg-primary text-white"><i class="bi bi-tag-fill me-1"></i>Brand: <?= e($billSetup['brand_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($seller['city'])): ?>
                        <span><i class="bi bi-geo-alt me-1"></i><?= e($seller['city']) ?>, <?= e($seller['state'] ?? 'Tamil Nadu') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="text-end me-2 d-none d-md-block">
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-building me-1 text-primary"></i>Inward to: 
                    <?php 
                        $destWhName = 'Unavailable';
                        $destWhId = (int)($billSetup['warehouse_id'] ?? 0);
                        if (!empty($warehouses)) {
                            foreach ($warehouses as $w) {
                                if ($w['id'] == $destWhId) { $destWhName = $w['name']; break; }
                            }
                        }
                        echo e($destWhName);
                    ?>
                </span>
                <?php if (!empty($billSetup['bill_number'])): ?>
                    <span class="badge bg-light text-dark border font-monospace ms-1">Bill: <?= e($billSetup['bill_number']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- EXISTING SUPPLIER PRODUCTS (READ ONLY) -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="bi bi-box-seam text-primary me-2"></i>Existing Supplier Products
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-bold">
                <?= count($existingSupplierProducts ?? []) ?> Product(s)
            </span>
        </div>
        <span class="text-muted small"><i class="bi bi-eye me-1"></i>View only — these items are not included in the new inward bill.</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle mb-0 text-nowrap" id="existingSupplierProductsTable">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Product Type</th>
                    <th>Variant</th>
                    <th>SKU</th>
                    <th>HSN</th>
                    <th class="text-end">Weight (kg)</th>
                    <th class="text-end">MRP (₹)</th>
                    <th class="text-end">Selling Price (₹)</th>
                    <th class="text-end">Supplier Cost (₹)</th>
                    <th class="text-end">Available Stock</th>
                    <th class="text-center" style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($existingSupplierProducts)): ?>
                    <tr>
                        <td colspan="13" class="text-center text-muted py-4">No existing products are linked to this supplier.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($existingSupplierProducts as $index => $product): ?>
                        <?php
                            $categoryPath = array_filter([
                                $product['category_name'] ?? '',
                                $product['subcategory_name'] ?? '',
                                $product['sub_subcategory_name'] ?? '',
                            ]);
                            $rowUid = 'spRow_' . (int)($product['variant_id'] ?? $product['product_id'] ?? ($index + 1));
                            $productPayload = [
                                'product_id'     => (int)($product['product_id'] ?? 0),
                                'variant_id'     => (int)($product['variant_id'] ?? 0),
                                'product_name'   => $product['product_name'] ?? '',
                                'sku'            => $product['sku'] ?? '',
                                'hsn_code'       => $product['hsn_code'] ?? '',
                                'variant_name'   => $product['variant_name'] ?? '',
                                'size_name'      => $product['size_name'] ?? '',
                                'color_name'     => $product['color_name'] ?? '',
                                'weight'         => (float)($product['weight'] ?? 0),
                                'mrp'            => (float)($product['mrp'] ?? 0),
                                'selling_price'  => (float)($product['selling_price'] ?? 0),
                                'purchase_price' => (float)($product['purchase_price'] ?? 0),
                                'available_stock'=> (float)($product['available_stock'] ?? 0),
                                'can_edit_stock' => !empty($product['can_edit_stock']),
                                'row_id'         => $rowUid,
                            ];
                        ?>
                        <tr id="<?= $rowUid ?>">
                            <td><?= $index + 1 ?></td>
                            <td class="fw-semibold col-sp-name"><?= e($product['product_name'] ?? '') ?></td>
                            <td><?= e(implode(' › ', $categoryPath) ?: '—') ?></td>
                            <?php $productType = strtoupper((string)($product['product_type'] ?? 'NORMAL')); ?>
                            <td>
                                <span class="badge rounded-pill <?= $productType === 'SERIES' ? 'bg-info-subtle text-info-emphasis' : ($productType === 'COMPLEMENTARY' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-primary-subtle text-primary') ?>">
                                    <?= $productType === 'SERIES' ? 'Series Pack' : ($productType === 'COMPLEMENTARY' ? 'Complementary' : 'Individual Piece') ?>
                                </span>
                            </td>
                            <td class="col-sp-variant"><?= e($product['variant_name'] ?? 'Standard') ?></td>
                            <td class="font-monospace col-sp-sku"><?= e($product['sku'] ?? '') ?: '—' ?></td>
                            <td class="font-monospace col-sp-hsn"><?= e($product['hsn_code'] ?? '') ?: '—' ?></td>
                            <td class="text-end col-sp-weight"><?= number_format((float)($product['weight'] ?? 0), 3) ?></td>
                            <td class="text-end col-sp-mrp">₹<?= number_format((float)($product['mrp'] ?? 0), 2) ?></td>
                            <td class="text-end col-sp-selling">₹<?= number_format((float)($product['selling_price'] ?? 0), 2) ?></td>
                            <td class="text-end col-sp-cost">₹<?= number_format((float)($product['purchase_price'] ?? 0), 2) ?></td>
                            <td class="text-end fw-semibold col-sp-stock"><?= number_format((float)($product['available_stock'] ?? 0), 0) ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2.5 fw-semibold shadow-xs" onclick='openEditSupplierProductModal(<?= htmlspecialchars(json_encode($productPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>)' title="Edit Product Details &amp; Pricing">
                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. CATEGORY & ATTRIBUTE VARIANT SELECTION (Full Width Streamlined) -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h6 class="card-title mb-0 fw-bold"><i class="bi bi-grid-fill text-primary me-2"></i>1. Select Category &amp; Click Attributes to Generate Inward Products</h6>
            <small class="text-muted">Pick a category and click attribute options (e.g. S, M, L) to directly load products into the Inward List table below</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Active Hierarchy Breadcrumb -->
            <div class="d-flex align-items-center gap-2 bg-light px-2.5 py-1 rounded border">
                <span class="badge bg-primary text-white">CATEGORY PATH:</span>
                <span id="activeHierarchyDisplay" class="fw-bold text-dark small">
                    <span class="text-muted">Please select a category below</span>
                </span>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5" onclick="resetCategorySelection()" title="Clear Selection">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <a href="<?= url('warehouse/catalog?tab=category') ?>" target="_blank" class="btn btn-xs btn-outline-primary" title="Configure Categories & Attributes">
                <i class="bi bi-gear"></i> Pre-sets
            </a>
        </div>
    </div>
    <div class="card-body p-3">
        <!-- Hidden Active Category Tracking -->
        <input type="hidden" id="entryCatId" value="">
        <input type="hidden" id="entryCatName" value="">
        <input type="hidden" id="entrySubId" value="">
        <input type="hidden" id="entrySubName" value="">
        <input type="hidden" id="entrySubSubId" value="">
        <input type="hidden" id="entrySubSubName" value="">

        <!-- Step 1: Main Category Tiles -->
        <div class="mb-3">
            <label class="form-label small fw-bold text-dark mb-1.5">Main Category:</label>
            <div class="d-flex flex-wrap gap-2" id="mainCategoryTiles">
                <?php if (empty($categories)): ?>
                    <div class="alert alert-warning small p-2 w-100 mb-0">No categories found. Please add categories in Catalog Manager.</div>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" 
                                class="btn btn-sm btn-outline-primary cat-tile rounded-3 px-3 py-1.5 text-start fw-semibold d-flex align-items-center gap-2"
                                data-cat-id="<?= $cat['id'] ?>"
                                data-cat-name="<?= e($cat['name']) ?>"
                                onclick="selectCategoryTile(this, <?= $cat['id'] ?>, '<?= e(addslashes($cat['name'])) ?>')">
                            <i class="bi bi-folder-fill text-warning"></i>
                            <span><?= e($cat['name']) ?></span>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 2: Subcategories Container (Dynamic) -->
        <div class="mb-3 d-none" id="subcategoriesSection">
            <label class="form-label small fw-bold text-dark mb-1.5">
                <i class="bi bi-arrow-return-right me-1 text-primary"></i>Subcategory for: <span id="labelSelectedCatName" class="text-primary fw-bold"></span>
            </label>
            <div class="d-flex flex-wrap gap-2" id="subcategoryTiles">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- Step 3: Sub-Subcategories Container (Dynamic) -->
        <div class="mb-3 d-none" id="subSubcategoriesSection">
            <label class="form-label small fw-bold text-dark mb-1.5">
                <i class="bi bi-arrow-return-right me-1 text-info"></i>Sub-Subcategory for: <span id="labelSelectedSubcatName" class="text-info fw-bold"></span>
            </label>
            <div class="d-flex flex-wrap gap-2" id="subSubcategoryTiles">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- Pre-set Product Templates Quick Pick (When Sub-Subcategory has templates) -->
        <div class="d-none mb-3 p-2.5 bg-success-subtle border border-success-subtle rounded-3" id="templateQuickPickSection">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-bold text-success"><i class="bi bi-magic me-1"></i>Pre-set Product Templates (Click to add directly to table)</span>
                <span class="badge bg-success text-white fs-8">1-Click Add</span>
            </div>
            <div class="d-flex flex-wrap gap-2" id="templateButtonsContainer">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- Pre-set Category Attributes & Variant Generator -->
        <div class="p-3 bg-light rounded-3 border d-none" id="attributesPreviewSection">
            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-white"><i class="bi bi-sliders me-1"></i>Pre-set Attributes &amp; Variants:</span>
                    <span class="text-muted small">Click attribute chips across categories to generate matrix variants (e.g. S-BROWN, S-RED).</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-xs btn-primary fw-bold px-2.5 py-1" onclick="selectAllAttributeVariants(true)">
                        <i class="bi bi-check-all me-1"></i>Select All Variants
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary fw-semibold px-2.5 py-1" onclick="selectAllAttributeVariants(false)">
                        <i class="bi bi-x-circle me-1"></i>Clear All
                    </button>
                </div>
            </div>
            <div class="d-flex flex-column gap-2" id="attributesBadgeContainer">
                <!-- Populated by JS -->
            </div>

            <!-- Generated Matrix Combinations Bar -->
            <div class="mt-3 pt-3 border-top d-none" id="matrixCombinationsSection">
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="small fw-bold text-dark">
                            <i class="bi bi-grid-3x3-gap-fill text-success me-1"></i>Active Matrix Combinations:
                        </span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-0.5 fw-bold" id="matrixCountBadge">0 Variants</span>
                    </div>
                    <span class="text-muted fs-8">Click a combination badge below to include / exclude it</span>
                </div>
                <div class="d-flex flex-wrap gap-1.5" id="matrixChipsContainer">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- Direct Add Category Button (If category has no attributes) -->
        <div class="d-none mt-2 pt-2 border-top text-end" id="singleCategoryAddContainer">
            <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="addSingleCategoryProduct()">
                <i class="bi bi-plus-circle me-1"></i> Add Category Product to Inward List
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. ADDED PRODUCTS INWARD LIST (DIRECT INLINE EDITABLE SPREADSHEET) -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="bi bi-cart-check-fill text-success me-2"></i>Added Products Inward List
            </h5>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-bold" id="badgeItemCount">
                0 Product(s)
            </span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="text-muted small">
                <i class="bi bi-info-circle text-primary me-1"></i>Only products added above are included in this inward bill; existing supplier products are not re-added.
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <!-- ⚡ Quick Fill All Variants Bar -->
        <div class="p-3 border-bottom d-none" id="quickFillBar" style="background-color: #fbfcfe !important;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-white px-2 py-1"><i class="bi bi-lightning-charge-fill me-1"></i>Quick Fill All Variants</span>
                    <span class="small text-muted">Fill details once below to apply across all variants.</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-xs btn-primary fw-bold shadow-xs px-2.5" onclick="applyQuickFillToAll()">
                        <i class="bi bi-check2-all me-1"></i>Apply to All Variants
                    </button>
                </div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-xl-2 col-lg-3 col-md-4 col-12">
                    <label class="form-label fs-8 text-muted mb-1 fw-bold">Product Name:</label>
                    <input type="text" class="form-control form-control-sm fw-semibold text-uppercase" id="qfProductName" placeholder="" oninput="this.value=this.value.toUpperCase()">
                </div>
                <div class="col-xl-2 col-lg-2 col-md-4 col-6">
                    <label class="form-label fs-8 text-muted mb-1 fw-bold">Category Type:</label>
                    <select class="form-select form-select-sm fw-semibold" id="qfCategoryType" onchange="handleQfCategoryTypeChange(this.value)">
                        <option value="NORMAL" selected>Normal</option>
                        <option value="COMPLEMENTARY">Complementary</option>
                        <option value="SERIES">Series</option>
                    </select>
                    <!-- Quick Fill Range (Shown if Series or Complementary) -->
                    <div id="qfSeriesRangeBox" class="d-none mt-1 p-1 bg-white rounded border shadow-xs">
                        <div class="d-flex align-items-center gap-1">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text py-0 px-1 font-monospace fs-8 text-muted bg-light">From</span>
                                <input type="number" min="1" step="1" id="qfSeriesFrom" class="form-control form-control-sm text-center px-1 font-monospace fw-bold" value="1" placeholder="" disabled>
                            </div>
                            <span class="text-muted small">&rarr;</span>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text py-0 px-1 font-monospace fs-8 text-muted bg-light">End</span>
                                <input type="number" min="1" step="1" id="qfSeriesEnd" class="form-control form-control-sm text-center px-1 font-monospace fw-bold" value="1" placeholder="" disabled>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-2 col-md-4 col-6">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label fs-8 text-muted mb-0 fw-bold">HSN Code:</label>
                        <button type="button" class="btn btn-link p-0 fs-8 text-primary fw-bold text-decoration-none" onclick="openSupplierAddHsnModal()" title="Add New HSN Code">
                            New HSN
                        </button>
                    </div>
                    <select class="form-select form-select-sm font-monospace fw-bold" id="qfHsnSelect" onchange="onQfHsnSelect(this.value)">
                        <option value="">-- Select HSN --</option>
                        <?php foreach (($hsnCodes ?? []) as $h): ?>
                            <option value="<?= htmlspecialchars($h['hsn_code']) ?>" data-gst="<?= (float)($h['gst_rate'] ?? 5) ?>">
                                <?= htmlspecialchars($h['hsn_code']) ?><?= !empty($h['description']) ? ' &bull; ' . htmlspecialchars($h['description']) : '' ?> (<?= (float)($h['gst_rate'] ?? 5) ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-xl-1 col-lg-1 col-md-2 col-6">
                    <label class="form-label fs-8 text-muted mb-1 fw-bold">GST (%):</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm text-center fw-bold" id="qfGst" value="5.00">
                        <span class="input-group-text bg-white px-1 fs-8">%</span>
                    </div>
                </div>
                <div class="col-xl-1 col-lg-1 col-md-2 col-6">
                    <label class="form-label fs-8 text-muted mb-1 fw-bold">Weight (kg):</label>
                    <input type="number" step="0.001" min="0" class="form-control form-control-sm text-end font-monospace" id="qfWeight" placeholder="" oninput="validateQuickFillPricing()">
                    <div id="qfWeightWarn" class="text-danger fs-8 fw-bold mt-1 d-none"></div>
                </div>
                <div class="col-xl-1 col-lg-1 col-md-3 col-6">
                    <label class="form-label fs-8 text-muted mb-1 fw-bold">Base MRP (₹):</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white px-1.5 fs-8">₹</span>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end text-success fw-bold" id="qfMrp" placeholder="" oninput="validateQuickFillPricing()">
                    </div>
                    <div id="qfMrpWarn" class="text-danger fs-8 fw-bold mt-1 d-none"></div>
                </div>
                <div class="col-xl-1 col-lg-1 col-md-3 col-6">
                    <label class="form-label fs-8 text-muted mb-1 fw-bold">Selling Price (₹):</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white px-1.5 fs-8">₹</span>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end text-primary fw-bold" id="qfSp" placeholder="" oninput="validateQuickFillPricing()">
                    </div>
                    <div id="qfPriceWarn" class="text-danger fs-8 fw-bold mt-1 d-none"></div>
                </div>
                <div class="col-xl-1 col-lg-1 col-md-3 col-6">
                    <label class="form-label fs-8 text-muted mb-1 fw-bold">Supplier Cost (₹):</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white px-1.5 fs-8">₹</span>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end text-dark fw-bold" id="qfCost" placeholder="" oninput="validateQuickFillPricing()">
                    </div>
                    <div id="qfCostWarn" class="text-danger fs-8 fw-bold mt-1 d-none"></div>
                </div>
                <div class="col-xl-1 col-lg-1 col-md-3 col-6">
                    <label class="form-label fs-8 text-muted mb-1 fw-bold">Qty / Var:</label>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-center fw-bold fs-7" id="qfQty" placeholder="" oninput="handleQfQtyInput(this.value); validateQuickFillPricing();">
                    <div id="qfQtyWarn" class="text-danger fs-8 fw-bold mt-1 d-none"></div>
                </div>
            </div>
            <div id="quickFillSuccessNotice" class="alert alert-success alert-dismissible fade show py-1.5 px-3 mb-0 mt-2 fs-8 fw-semibold d-none" role="alert">
                <i class="bi bi-check-circle-fill me-1"></i><span id="quickFillNoticeText">All variants updated successfully.</span>
            </div>
        </div>

        <!-- HSN CODES DATALIST FOR AUTOCOMPLETE -->
        <datalist id="supplierHsnCodesList">
            <?php foreach (($hsnCodes ?? []) as $h): ?>
                <option value="<?= htmlspecialchars($h['hsn_code']) ?>"><?= htmlspecialchars($h['hsn_code']) ?> - <?= htmlspecialchars($h['description'] ?? '') ?> (<?= (float)($h['gst_rate'] ?? 5) ?>% GST)</option>
            <?php endforeach; ?>
        </datalist>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0" id="addedProductsTable">
                <thead class="table-light text-nowrap">
                    <tr>
                        <th style="width: 35px;" class="text-center">#</th>
                        <th style="min-width: 140px;">Category Hierarchy</th>
                        <th style="min-width: 195px;">Category Type</th>
                        <th style="min-width: 220px;">Product Name <span class="text-danger">*</span></th>
                        <th style="width: 135px;" class="text-center">HSN <span class="text-danger">*</span></th>
                        <th style="width: 95px;" class="text-center">GST (%) <span class="text-danger">*</span></th>
                        <th style="width: 100px;" class="text-end">Weight (kg) <span class="text-danger">*</span></th>
                        <th style="min-width: 115px;" class="text-center">Variant / Size</th>
                        <th style="width: 125px;" class="text-end">Base MRP (₹) <span class="text-danger">*</span></th>
                        <th style="width: 125px;" class="text-end">Selling Price (₹) <span class="text-danger">*</span></th>
                        <th style="width: 120px;" class="text-end">Supplier Cost (₹) <span class="text-danger">*</span></th>
                        <th style="width: 80px;" class="text-center">Qty <span class="text-danger">*</span></th>
                        <th style="width: 130px;" class="text-end">Line Retail Value (₹)</th>
                        <th style="width: 50px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="addedProductsTableBody">
                    <tr id="emptyProductListNotice">
                        <td colspan="14" class="text-center py-5 text-muted">
                            <i class="bi bi-cart-x fs-1 opacity-50 d-block mb-2"></i>
                            <div class="fw-semibold">No Products in Inward List Yet</div>
                            <div class="small">Choose a category and click attribute options above to directly load products here.</div>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="6" class="text-end">Retail Totals:</td>
                        <td class="text-end font-monospace" id="footerTotalWeight">0.000 kg</td>
                        <td colspan="4"></td>
                        <td class="text-center fs-7" id="footerTotalQty">0 pcs</td>
                        <td class="text-end text-primary fs-6" id="footerGrandTotal">₹0.00</td>
                        <td></td>
                    </tr>
                    <tr class="table-warning-subtle">
                        <td colspan="10" class="text-end small">
                            <span class="text-muted">Supplier Net Cost:</span> <span class="text-dark fw-bold me-2" id="footerSupplierGrossCost">₹0.00</span>
                            <span class="text-muted">| Total GST:</span> <span class="text-danger fw-bold me-2" id="footerSupplierGstTotal">₹0.00</span>
                            <span class="text-muted">|</span>
                        </td>
                        <td colspan="2" class="text-end text-nowrap">Supplier Product Total (Cost + GST):</td>
                        <td class="text-end text-success fs-6 fw-bolder" id="footerSupplierProductTotal">₹0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- BOTTOM STICKY ACTION BAR -->
<!-- ========================================================================= -->
<form id="finalSubmitForm" method="POST" action="<?= url('warehouse/sellers/' . $seller['id'] . '/store-products') ?>">
    <?= CSRF::field() ?>
    <input type="hidden" name="items" id="jsonProductsInput" value="[]">
    <input type="hidden" name="warehouse_id" value="<?= e($billSetup['warehouse_id'] ?? ($warehouses[0]['id'] ?? '')) ?>">
    <input type="hidden" name="order_date" value="<?= e($billSetup['order_date'] ?? date('Y-m-d')) ?>">
    <input type="hidden" name="expected_date" value="<?= e($billSetup['expected_date'] ?? date('Y-m-d')) ?>">
    <input type="hidden" name="payment_method" value="<?= e($billSetup['payment_method'] ?? 'CASH') ?>">
    <input type="hidden" name="payment_status" value="<?= e($billSetup['payment_status'] ?? 'PAID') ?>">
    <input type="hidden" name="gst_type" value="<?= e($billSetup['gst_type'] ?? 'CGST_SGST') ?>">
    <input type="hidden" name="bill_number" value="<?= e($billSetup['bill_number'] ?? '') ?>">
    <input type="hidden" name="notes" value="<?= e($billSetup['notes'] ?? '') ?>">

    <div class="card border-0 shadow-lg rounded-3 bg-white border-top border-3 border-success">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= url('warehouse/sellers/create?seller_id=' . (int)$seller['id'] . '&mode=edit') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Phase 1
                </a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div id="saveProductsValidationNotice" class="text-danger small fw-bold d-none me-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Please fix highlighted field errors or duplicate lines before saving.
                </div>
                <button type="button" class="btn btn-primary px-5 py-2.5 fw-bold shadow" id="btnSaveAllProducts" onclick="submitFinalProducts()" disabled <?= empty($warehouses) ? 'aria-disabled="true" title="Create or activate a warehouse before saving"' : '' ?>>
                    <i class="bi bi-qr-code me-1"></i> Save Products &amp; Generate QR Labels &rarr;
                </button>
            </div>
        </div>
    </div>
</form>

<!-- MODAL: QUICK ADD HSN CODE (SUPPLIER PURCHASE / INWARD) -->
<div class="modal fade" id="modalSupplierAddHsn" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-primary text-white py-2.5 px-3">
                <h6 class="modal-title fw-bold mb-0 text-white">
                    <i class="bi bi-upc-scan me-1.5"></i>Create New HSN Code
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSupplierAddHsn" onsubmit="submitSupplierAddHsn(event)">
                <div class="modal-body p-3">
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold text-dark mb-1">HSN Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm font-monospace text-uppercase fw-bold" id="supplierHsnCodeInput" placeholder="e.g. 6109 or 6205" required maxlength="15">
                        <div class="form-text text-muted" style="font-size:.72rem;">Harmonized System Nomenclature code</div>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold text-dark mb-1">Description / Garment Commodity <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm fw-semibold" id="supplierHsnDescInput" placeholder="e.g. Cotton T-Shirts &amp; Polos" required maxlength="255">
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-bold text-dark mb-1">GST Tax Rate (%) <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm fw-semibold" id="supplierHsnGstInput">
                            <option value="5.00" selected>5.00% (Standard Apparel)</option>
                            <option value="12.00">12.00%</option>
                            <option value="18.00">18.00%</option>
                            <option value="0.00">0.00% (Nil / Exempt)</option>
                            <option value="28.00">28.00%</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold" id="btnSaveSupplierHsn">
                        <i class="bi bi-check-circle me-1"></i>Save HSN Code
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: PHASE 2 DIRECT TABLE GENERATION & INLINE EDITING -->
<!-- ========================================================================= -->
<script>
const APP_URL = <?= json_encode(rtrim(url(''), '/')) ?>;
const CATEGORIES_DATA = <?= json_encode($categories ?? [], JSON_UNESCAPED_UNICODE) ?>;
const ATTRIBUTES_DATA = <?= json_encode($allAttributes ?? [], JSON_UNESCAPED_UNICODE) ?>;
const DEFAULT_PRODUCTS_DATA = <?= json_encode($allDefaultProducts ?? [], JSON_UNESCAPED_UNICODE) ?>;
const DEFAULT_BRAND_ID = <?= json_encode(!empty($billSetup['brand_id']) ? (int)$billSetup['brand_id'] : (!empty($seller['brand_id']) ? (int)$seller['brand_id'] : null)) ?>;
const DEFAULT_BRAND_NAME = <?= json_encode(!empty($billSetup['brand_name']) ? $billSetup['brand_name'] : '') ?>;
let HSN_OPTIONS = <?= json_encode(array_values(array_map(function($h) {
    return [
        'hsn_code'    => (string)$h['hsn_code'],
        'description' => (string)($h['description'] ?? ''),
        'gst_rate'    => (float)($h['gst_rate'] ?? 5.00),
    ];
}, $hsnCodes ?? [])), JSON_UNESCAPED_UNICODE) ?>;
const EXISTING_SUPPLIER_PRODUCTS = <?= json_encode($existingSupplierProducts ?? [], JSON_UNESCAPED_UNICODE) ?>;
const HAS_ACTIVE_WAREHOUSE = <?= empty($warehouses) ? 'false' : 'true' ?>;
const SUPPLIER_INDEX_URL = <?= json_encode(url('warehouse/sellers')) ?>;

// Array of active inward products
let addedProductsList = [];
const pendingSupplierProductEdits = new Map();
const touchedProductFields = new WeakMap();
const submittedProductItems = new WeakSet();

function showSupplierToast(title, message, type = 'info') {
    let container = document.getElementById('supplierToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'supplierToastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '2000';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
    }

    const colorClass = {
        success: 'text-bg-success',
        danger: 'text-bg-danger',
        warning: 'text-bg-warning',
        info: 'text-bg-primary'
    }[type] || 'text-bg-primary';
    const toastElement = document.createElement('div');
    toastElement.className = `toast align-items-center ${colorClass} border-0 shadow`;
    toastElement.setAttribute('role', 'status');
    toastElement.setAttribute('aria-live', 'polite');
    toastElement.setAttribute('aria-atomic', 'true');

    const layout = document.createElement('div');
    layout.className = 'd-flex';
    const body = document.createElement('div');
    body.className = 'toast-body';
    const heading = document.createElement('strong');
    heading.textContent = title;
    const detail = document.createElement('div');
    detail.textContent = message;
    body.append(heading, detail);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white me-2 m-auto';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', 'Close');
    layout.append(body, close);
    toastElement.appendChild(layout);
    container.appendChild(toastElement);

    toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove(), { once: true });
    new bootstrap.Toast(toastElement, { delay: 4500 }).show();
}

function markProductFieldTouched(item, fieldName) {
    let fields = touchedProductFields.get(item);
    if (!fields) {
        fields = new Set();
        touchedProductFields.set(item, fields);
    }
    fields.add(fieldName);
}

function shouldShowProductFieldError(item, ...fieldNames) {
    const fields = touchedProductFields.get(item);
    return submittedProductItems.has(item)
        || (fields !== undefined && fieldNames.some(fieldName => fields.has(fieldName)));
}

// Track current available attributes for active category
let currentCategoryAttributes = [];
// Track selected options per attribute for matrix generation, e.g. { 'SIZE': ['S', 'L'], 'COLOR': ['BROWN', 'RED'] }
let selectedAttributeOptions = {};
// Track explicitly excluded matrix combo keys, e.g. 'COLOR:RED|SIZE:S'
let excludedComboKeys = new Set();

document.addEventListener('DOMContentLoaded', function() {
    renderAddedProductsTable();
});

// -----------------------------------------------------------------------------
// 1. TIERED CATEGORY SELECTION & ATTRIBUTE LOADING
// -----------------------------------------------------------------------------
function selectCategoryTile(btn, catId, catName) {
    const prevCatId = document.getElementById('entryCatId').value;
    if (String(prevCatId) !== String(catId)) {
        selectedAttributeOptions = {};
        excludedComboKeys.clear();
    }

    document.querySelectorAll('#mainCategoryTiles .cat-tile').forEach(t => {
        t.classList.remove('btn-primary', 'text-white');
        t.classList.add('btn-outline-primary');
    });
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-primary', 'text-white');

    document.getElementById('entryCatId').value = catId;
    document.getElementById('entryCatName').value = catName;
    document.getElementById('entrySubId').value = '';
    document.getElementById('entrySubName').value = '';
    document.getElementById('entrySubSubId').value = '';
    document.getElementById('entrySubSubName').value = '';

    updateHierarchyDisplay();
    displayCategoryAttributes(catId, null, null);

    const catObj = CATEGORIES_DATA.find(c => String(c.id) === String(catId));
    if (catObj && catObj.hsn_code) {
        const qfHsnEl = document.getElementById('qfHsnSelect');
        if (qfHsnEl && !qfHsnEl.value) {
            qfHsnEl.value = catObj.hsn_code;
            if (catObj.gst_rate) {
                const qfGst = document.getElementById('qfGst');
                if (qfGst) qfGst.value = parseFloat(catObj.gst_rate).toFixed(2);
            }
        }
    }
    const subSection = document.getElementById('subcategoriesSection');
    const subContainer = document.getElementById('subcategoryTiles');
    const subSubSection = document.getElementById('subSubcategoriesSection');
    const templateSection = document.getElementById('templateQuickPickSection');

    subSubSection.classList.add('d-none');
    templateSection.classList.add('d-none');

    if (catObj && catObj.subcategories && catObj.subcategories.length > 0) {
        document.getElementById('labelSelectedCatName').textContent = catName;
        let html = '';
        catObj.subcategories.forEach(sc => {
            html += `
                <button type="button" 
                        class="btn btn-sm btn-outline-info subcat-tile rounded-3 px-3 py-1.5 text-start fw-semibold"
                        data-sub-id="${sc.id}"
                        data-sub-name="${escapeHtml(sc.name)}"
                        onclick="selectSubcategoryTile(this, ${sc.id}, '${escapeHtml(sc.name)}')">
                    <i class="bi bi-folder2 text-primary me-1"></i>${escapeHtml(sc.name)}
                </button>
            `;
        });
        subContainer.innerHTML = html;
        subSection.classList.remove('d-none');
    } else {
        subSection.classList.add('d-none');
    }
}

function selectSubcategoryTile(btn, subId, subName) {
    document.querySelectorAll('#subcategoryTiles .subcat-tile').forEach(t => {
        t.classList.remove('btn-info', 'text-white');
        t.classList.add('btn-outline-info');
    });
    btn.classList.remove('btn-outline-info');
    btn.classList.add('btn-info', 'text-white');

    document.getElementById('entrySubId').value = subId;
    document.getElementById('entrySubName').value = subName;
    document.getElementById('entrySubSubId').value = '';
    document.getElementById('entrySubSubName').value = '';

    updateHierarchyDisplay();
    displayCategoryAttributes(document.getElementById('entryCatId').value, subId, null);

    const catId = document.getElementById('entryCatId').value;
    const catObj = CATEGORIES_DATA.find(c => String(c.id) === String(catId));
    const subObj = catObj && catObj.subcategories ? catObj.subcategories.find(s => String(s.id) === String(subId)) : null;

    const subSubSection = document.getElementById('subSubcategoriesSection');
    const subSubContainer = document.getElementById('subSubcategoryTiles');
    const templateSection = document.getElementById('templateQuickPickSection');
    templateSection.classList.add('d-none');

    if (subObj && subObj.sub_subcategories && subObj.sub_subcategories.length > 0) {
        document.getElementById('labelSelectedSubcatName').textContent = subName;
        let html = '';
        subObj.sub_subcategories.forEach(ssc => {
            html += `
                <button type="button" 
                        class="btn btn-sm btn-outline-dark subsub-tile rounded-3 px-3 py-1.5 text-start fw-semibold"
                        data-ssc-id="${ssc.id}"
                        data-ssc-name="${escapeHtml(ssc.name)}"
                        onclick="selectSubSubcategoryTile(this, ${ssc.id}, '${escapeHtml(ssc.name)}')">
                    <i class="bi bi-tag-fill text-info me-1"></i>${escapeHtml(ssc.name)}
                </button>
            `;
        });
        subSubContainer.innerHTML = html;
        subSubSection.classList.remove('d-none');
    } else {
        subSubSection.classList.add('d-none');
    }
}

function selectSubSubcategoryTile(btn, sscId, sscName) {
    document.querySelectorAll('#subSubcategoryTiles .subsub-tile').forEach(t => {
        t.classList.remove('btn-dark', 'text-white');
        t.classList.add('btn-outline-dark');
    });
    btn.classList.remove('btn-outline-dark');
    btn.classList.add('btn-dark', 'text-white');

    document.getElementById('entrySubSubId').value = sscId;
    document.getElementById('entrySubSubName').value = sscName;

    updateHierarchyDisplay();
    const catId = document.getElementById('entryCatId').value;
    const subId = document.getElementById('entrySubId').value;
    displayCategoryAttributes(catId, subId, sscId);

    // Show templates if any
    displayProductTemplates(sscId, sscName);
}

function updateHierarchyDisplay() {
    const cat = document.getElementById('entryCatName').value;
    const sub = document.getElementById('entrySubName').value;
    const ssc = document.getElementById('entrySubSubName').value;

    const display = document.getElementById('activeHierarchyDisplay');
    if (!cat) {
        display.innerHTML = `<span class="text-muted">Please select a category below</span>`;
        return;
    }

    let str = `<span class="badge bg-primary text-white">${escapeHtml(cat)}</span>`;
    if (sub) {
        str += ` <i class="bi bi-chevron-right text-muted fs-8"></i> <span class="badge bg-info text-dark">${escapeHtml(sub)}</span>`;
    }
    if (ssc) {
        str += ` <i class="bi bi-chevron-right text-muted fs-8"></i> <span class="badge bg-dark text-white">${escapeHtml(ssc)}</span>`;
    }
    display.innerHTML = str;
}

function resetCategorySelection() {
    selectedAttributeOptions = {};
    excludedComboKeys.clear();

    const qfNameReset = document.getElementById('qfProductName');
    if (qfNameReset) qfNameReset.value = '';

    document.querySelectorAll('#mainCategoryTiles .cat-tile').forEach(t => {
        t.classList.remove('btn-primary', 'text-white');
        t.classList.add('btn-outline-primary');
    });
    document.getElementById('subcategoriesSection').classList.add('d-none');
    document.getElementById('subSubcategoriesSection').classList.add('d-none');
    document.getElementById('templateQuickPickSection').classList.add('d-none');
    document.getElementById('attributesPreviewSection').classList.add('d-none');
    document.getElementById('singleCategoryAddContainer').classList.add('d-none');

    document.getElementById('entryCatId').value = '';
    document.getElementById('entryCatName').value = '';
    document.getElementById('entrySubId').value = '';
    document.getElementById('entrySubName').value = '';
    document.getElementById('entrySubSubId').value = '';
    document.getElementById('entrySubSubName').value = '';
    updateHierarchyDisplay();
}

function displayProductTemplates(sscId, sscName) {
    const section = document.getElementById('templateQuickPickSection');
    const container = document.getElementById('templateButtonsContainer');

    const templates = DEFAULT_PRODUCTS_DATA.filter(p => String(p.sub_subcategory_id) === String(sscId));
    if (templates && templates.length > 0) {
        let html = '';
        templates.forEach(t => {
            html += `
                <button type="button" class="btn btn-sm btn-light border text-dark fw-bold text-start p-2 shadow-xs" onclick='addTemplateProduct(${JSON.stringify(t)})'>
                    <div class="d-flex align-items-center gap-1.5">
                        <i class="bi bi-magic text-success"></i>
                        <span>${escapeHtml(t.product_template_name)}</span>
                    </div>
                    <div class="text-muted fs-8 mt-1">
                        ${t.hsn_code ? `<span class="badge bg-secondary-subtle text-secondary me-1">HSN: ${escapeHtml(t.hsn_code)}</span>` : ''}
                        ${t.default_weight_kg ? `<span class="badge bg-info-subtle text-info me-1">${t.default_weight_kg} kg</span>` : ''}
                    </div>
                </button>
            `;
        });
        container.innerHTML = html;
        section.classList.remove('d-none');
    } else {
        section.classList.add('d-none');
    }
}

// -----------------------------------------------------------------------------
// 2. MATRIX VARIANT GENERATION & ATTRIBUTE CHIP CONTROLS
// -----------------------------------------------------------------------------
function displayCategoryAttributes(catId, subId, sscId) {
    const previewSection = document.getElementById('attributesPreviewSection');
    const badgeContainer = document.getElementById('attributesBadgeContainer');
    const singleAddBtn = document.getElementById('singleCategoryAddContainer');

    const matchingAttrs = ATTRIBUTES_DATA.filter(a => {
        if (String(a.category_id) !== String(catId)) return false;
        if (a.subcategory_id && (!subId || String(a.subcategory_id) !== String(subId))) return false;
        if (a.sub_subcategory_id && (!sscId || String(a.sub_subcategory_id) !== String(sscId))) return false;
        return true;
    });

    currentCategoryAttributes = matchingAttrs;

    if (matchingAttrs.length > 0) {
        let leftHtml = '';

        matchingAttrs.forEach(a => {
            const attrName = (a.attr_name || '').trim();
            const attrKey = attrName.toUpperCase();
            const rawOpts = a.attr_options || '';
            const opts = rawOpts ? rawOpts.split(',').map(o => o.trim()).filter(Boolean) : [];

            if (!selectedAttributeOptions[attrKey]) {
                selectedAttributeOptions[attrKey] = [];
            }

            leftHtml += `
                <div class="p-2.5 bg-white rounded border shadow-xs">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold text-primary small text-uppercase">
                            <i class="bi bi-tag-fill me-1"></i>${escapeHtml(attrName)}
                        </span>
                        <span class="badge bg-light text-muted border">${opts.length > 0 ? opts.length + ' variants' : a.attr_type}</span>
                    </div>
                    ${opts.length > 0 ? `
                        <div class="d-flex flex-wrap gap-2">
                            ${opts.map(opt => {
                                const cleanOpt = opt.toUpperCase();
                                const isSelected = selectedAttributeOptions[attrKey] && selectedAttributeOptions[attrKey].includes(cleanOpt);
                                return `
                                    <button type="button" 
                                            class="btn btn-sm ${isSelected ? 'btn-primary text-white' : 'btn-outline-primary'} attr-opt-chip fw-bold px-3 py-1.5 shadow-xs" 
                                            data-cat-id="${catId}"
                                            data-attr="${escapeHtml(attrKey)}" 
                                            data-val="${escapeHtml(cleanOpt)}" 
                                            onclick="toggleAttributeOption('${escapeHtml(attrKey)}', '${escapeHtml(cleanOpt)}')">
                                        <i class="bi ${isSelected ? 'bi-check-circle-fill me-1' : 'bi-plus-circle me-1'}"></i>${escapeHtml(cleanOpt)}
                                    </button>
                                `;
                            }).join('')}
                        </div>
                    ` : `<span class="text-muted small">${a.attr_type}</span>`}
                </div>
            `;
        });

        badgeContainer.innerHTML = leftHtml;
        previewSection.classList.remove('d-none');
        if (singleAddBtn) singleAddBtn.classList.add('d-none');
        renderMatrixSection();
    } else {
        previewSection.classList.add('d-none');
        if (singleAddBtn) singleAddBtn.classList.remove('d-none');
    }
}

function toggleAttributeOption(attrKey, optVal) {
    const cleanKey = attrKey.trim().toUpperCase();
    const cleanVal = optVal.trim().toUpperCase();

    if (!selectedAttributeOptions[cleanKey]) {
        selectedAttributeOptions[cleanKey] = [];
    }

    const idx = selectedAttributeOptions[cleanKey].indexOf(cleanVal);
    if (idx >= 0) {
        selectedAttributeOptions[cleanKey].splice(idx, 1);
    } else {
        selectedAttributeOptions[cleanKey].push(cleanVal);
    }

    regenerateMatrixProducts();
}

// Cartesian product algorithm across multiple attribute dimensions
function cartesianProduct(arrays) {
    if (!arrays || arrays.length === 0) return [];
    return arrays.reduce((acc, curr) => {
        const res = [];
        acc.forEach(a => {
            curr.forEach(c => {
                res.push(a.concat([c]));
            });
        });
        return res;
    }, [[]]);
}

function regenerateMatrixProducts() {
    const catId = document.getElementById('entryCatId').value;
    const catName = document.getElementById('entryCatName').value;
    const subId = document.getElementById('entrySubId').value;
    const subName = document.getElementById('entrySubName').value;
    const sscId = document.getElementById('entrySubSubId').value;
    const sscName = document.getElementById('entrySubSubName').value;

    if (!catId) {
        renderAddedProductsTable();
        updateAttributeChipVisuals();
        renderMatrixSection();
        return;
    }

    // Build dimensions in the defined attribute order
    const activeDimensions = [];
    currentCategoryAttributes.forEach(attr => {
        const aKey = (attr.attr_name || '').trim().toUpperCase();
        const selectedVals = selectedAttributeOptions[aKey] || [];
        if (selectedVals.length > 0) {
            activeDimensions.push(selectedVals.map(val => ({
                attr: aKey,
                val: val
            })));
        }
    });

    // Retain products from other categories or non-matrix items
    const otherProducts = addedProductsList.filter(item => {
        return String(item.category_id) !== String(catId) || !item.is_matrix_item;
    });

    if (activeDimensions.length === 0) {
        addedProductsList = otherProducts;
        renderAddedProductsTable();
        updateAttributeChipVisuals();
        renderMatrixSection();
        return;
    }

    const combinations = cartesianProduct(activeDimensions);
    const updatedCategoryProducts = [];

    combinations.forEach(combo => {
        const sortedCombo = [...combo].sort((a, b) => a.attr.localeCompare(b.attr));
        const comboKey = sortedCombo.map(c => `${c.attr}:${c.val}`).join('|');
        const comboDisplay = combo.map(c => c.val).join('-');

        // If user explicitly excluded this combination, skip adding it
        if (excludedComboKeys.has(comboKey)) {
            return;
        }

        // Check if item already exists in addedProductsList to preserve user-edited prices/quantities
        const existingItem = addedProductsList.find(it => {
            return String(it.category_id) === String(catId) && it.combo_key === comboKey;
        });

        if (existingItem) {
            if (!existingItem.category_type) {
                const qfCatType = document.getElementById('qfCategoryType');
                existingItem.category_type = (qfCatType && qfCatType.value) ? qfCatType.value : 'NORMAL';
                existingItem.is_complementary = (existingItem.category_type === 'COMPLEMENTARY') ? 1 : 0;
                existingItem.qr_type = (existingItem.category_type === 'SERIES') ? 'SERIES_BATCH' : 'INDIVIDUAL';
            }
            if (!existingItem.series_from) existingItem.series_from = 1;
            if (!existingItem.series_end) existingItem.series_end = (existingItem.quantity > 0) ? existingItem.quantity : 1;
            updatedCategoryProducts.push(existingItem);
        } else {
            let sizeVal = '';
            let colorVal = '';
            combo.forEach(c => {
                if (c.attr === 'SIZE') sizeVal = c.val;
                else if (c.attr === 'COLOR' || c.attr === 'COLOUR') colorVal = c.val;
            });
            if (!sizeVal) sizeVal = comboDisplay;

            // Product name: empty by default unless user entered a name in Quick Fill bar
            const qfNameInput = document.getElementById('qfProductName');
            const vProductName = (qfNameInput && qfNameInput.value.trim()) ? qfNameInput.value.trim().toUpperCase() : '';

            const qfCatType = document.getElementById('qfCategoryType');
            const initialCatType = (qfCatType && qfCatType.value) ? qfCatType.value : 'NORMAL';
            const isInitialSeriesOrComp = (initialCatType === 'SERIES' || initialCatType === 'COMPLEMENTARY');
            const qfQty = parseInt(document.getElementById('qfQty')?.value) || 0;
            const qfFrom = parseInt(document.getElementById('qfSeriesFrom')?.value) || 1;
            const qfEnd = parseInt(document.getElementById('qfSeriesEnd')?.value) || (qfQty > 0 ? qfQty : 1);
            const qfHsnVal = (document.getElementById('qfHsnSelect')?.value || '').trim();
            const qfGstVal = document.getElementById('qfGst')?.value;
            const activeCat = CATEGORIES_DATA.find(c => String(c.id) === String(catId));
            const defaultHsn = qfHsnVal || (activeCat?.hsn_code || '');
            const defaultGst = (qfGstVal !== undefined && qfGstVal !== '') ? (parseFloat(qfGstVal) || 0) : (activeCat?.gst_rate ? parseFloat(activeCat.gst_rate) : 5.00);

            updatedCategoryProducts.push({
                id: Date.now() + Math.floor(Math.random() * 100000),
                is_matrix_item: true,
                combo_key: comboKey,
                variant_display: comboDisplay,
                category_id: catId,
                category_name: catName,
                subcategory_id: subId || null,
                subcategory_name: subName || null,
                sub_subcategory_id: sscId || null,
                sub_subcategory_name: sscName || null,
                category_type: initialCatType,
                product_name: vProductName,
                hsn_code: defaultHsn,
                weight: 0,
                mrp: 0,
                selling_price: 0,
                purchase_price: 0,
                quantity: 0,
                series_from: isInitialSeriesOrComp ? qfFrom : 1,
                series_end: isInitialSeriesOrComp ? qfEnd : 1,
                series_start_code: isInitialSeriesOrComp ? String(qfFrom) : '',
                series_end_code: isInitialSeriesOrComp ? String(qfEnd) : '',
                gst_percentage: defaultGst,
                line_total: 0,
                size_name: sizeVal,
                color_name: colorVal,
                brand_id: DEFAULT_BRAND_ID,
                qr_type: (initialCatType === 'SERIES') ? 'SERIES_BATCH' : 'INDIVIDUAL',
                is_complementary: (initialCatType === 'COMPLEMENTARY') ? 1 : 0
            });
        }
    });

    addedProductsList = [...otherProducts, ...updatedCategoryProducts];

    renderAddedProductsTable();
    updateAttributeChipVisuals();
    renderMatrixSection();
}

function renderMatrixSection() {
    const section = document.getElementById('matrixCombinationsSection');
    const container = document.getElementById('matrixChipsContainer');
    const countBadge = document.getElementById('matrixCountBadge');

    if (!section || !container) return;

    const catId = document.getElementById('entryCatId').value;
    if (!catId) {
        section.classList.add('d-none');
        return;
    }

    const activeDimensions = [];
    currentCategoryAttributes.forEach(attr => {
        const aKey = (attr.attr_name || '').trim().toUpperCase();
        const selectedVals = selectedAttributeOptions[aKey] || [];
        if (selectedVals.length > 0) {
            activeDimensions.push(selectedVals.map(val => ({
                attr: aKey,
                val: val
            })));
        }
    });

    if (activeDimensions.length === 0) {
        section.classList.add('d-none');
        container.innerHTML = '';
        return;
    }

    const combinations = cartesianProduct(activeDimensions);
    if (combinations.length === 0) {
        section.classList.add('d-none');
        container.innerHTML = '';
        return;
    }

    const activeCombosCount = combinations.filter(combo => {
        const sortedCombo = [...combo].sort((a, b) => a.attr.localeCompare(b.attr));
        const comboKey = sortedCombo.map(c => `${c.attr}:${c.val}`).join('|');
        return !excludedComboKeys.has(comboKey);
    }).length;

    countBadge.textContent = `${activeCombosCount} of ${combinations.length} Variants Active`;

    let html = '';
    combinations.forEach(combo => {
        const sortedCombo = [...combo].sort((a, b) => a.attr.localeCompare(b.attr));
        const comboKey = sortedCombo.map(c => `${c.attr}:${c.val}`).join('|');
        const comboDisplay = combo.map(c => c.val).join('-');
        const isExcluded = excludedComboKeys.has(comboKey);

        if (isExcluded) {
            html += `
                <button type="button" class="btn btn-xs btn-outline-secondary matrix-combo-chip fw-semibold px-2.5 py-1 text-decoration-line-through opacity-75"
                        title="Click to add ${escapeHtml(comboDisplay)} back to Inward List"
                        onclick="toggleMatrixCombo('${escapeHtml(comboKey)}')">
                    <i class="bi bi-plus-circle me-1 text-primary"></i>${escapeHtml(comboDisplay)}
                </button>
            `;
        } else {
            html += `
                <button type="button" class="btn btn-xs btn-success text-white matrix-combo-chip fw-bold px-2.5 py-1 shadow-xs"
                        title="Click to exclude ${escapeHtml(comboDisplay)}"
                        onclick="toggleMatrixCombo('${escapeHtml(comboKey)}')">
                    <i class="bi bi-check2-circle me-1"></i>${escapeHtml(comboDisplay)}
                </button>
            `;
        }
    });

    container.innerHTML = html;
    section.classList.remove('d-none');
}

function toggleMatrixCombo(comboKey) {
    if (excludedComboKeys.has(comboKey)) {
        excludedComboKeys.delete(comboKey);
    } else {
        excludedComboKeys.add(comboKey);
    }
    regenerateMatrixProducts();
}

function selectAllAttributeVariants(selectAll) {
    const catId = document.getElementById('entryCatId').value;
    if (!catId) {
        alert('Please select a category first.');
        return;
    }

    if (!selectAll) {
        selectedAttributeOptions = {};
        excludedComboKeys.clear();
    } else {
        excludedComboKeys.clear();
        currentCategoryAttributes.forEach(attr => {
            const aKey = (attr.attr_name || '').trim().toUpperCase();
            const rawOpts = attr.attr_options || '';
            const opts = rawOpts ? rawOpts.split(',').map(o => o.trim().toUpperCase()).filter(Boolean) : [];
            selectedAttributeOptions[aKey] = [...opts];
        });
    }

    regenerateMatrixProducts();
}

function updateAttributeChipVisuals() {
    document.querySelectorAll('.attr-opt-chip').forEach(btn => {
        const attr = btn.getAttribute('data-attr');
        const val = btn.getAttribute('data-val');
        const isSelected = selectedAttributeOptions[attr] && selectedAttributeOptions[attr].includes(val);
        btn.classList.toggle('btn-primary', isSelected);
        btn.classList.toggle('text-white', isSelected);
        btn.classList.toggle('btn-outline-primary', !isSelected);
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = isSelected ? 'bi bi-check-circle-fill me-1' : 'bi bi-plus-circle me-1';
        }
    });
}

function addSingleCategoryProduct() {
    const catId = document.getElementById('entryCatId').value;
    const catName = document.getElementById('entryCatName').value;
    const subId = document.getElementById('entrySubId').value;
    const subName = document.getElementById('entrySubName').value;
    const sscId = document.getElementById('entrySubSubId').value;
    const sscName = document.getElementById('entrySubSubName').value;

    if (!catId) {
        alert('Please select a category first.');
        return;
    }

    const qfNameInput = document.getElementById('qfProductName');
    const pName = (qfNameInput && qfNameInput.value.trim()) ? qfNameInput.value.trim() : '';

    const qfCatType = document.getElementById('qfCategoryType');
    const initialCatType = (qfCatType && qfCatType.value) ? qfCatType.value : 'NORMAL';
    const isInitialSeriesOrComp = (initialCatType === 'SERIES' || initialCatType === 'COMPLEMENTARY');
    const qfQty = parseInt(document.getElementById('qfQty')?.value) || 0;
    const qfFrom = parseInt(document.getElementById('qfSeriesFrom')?.value) || 1;
    const qfEnd = parseInt(document.getElementById('qfSeriesEnd')?.value) || (qfQty > 0 ? qfQty : 1);
    const qfHsnVal = (document.getElementById('qfHsnSelect')?.value || '').trim();
    const qfGstVal = document.getElementById('qfGst')?.value;
    const activeCat = CATEGORIES_DATA.find(c => String(c.id) === String(catId));
    const defaultHsn = qfHsnVal || (activeCat?.hsn_code || '');
    const defaultGst = (qfGstVal !== undefined && qfGstVal !== '') ? (parseFloat(qfGstVal) || 0) : (activeCat?.gst_rate ? parseFloat(activeCat.gst_rate) : 5.00);

    addedProductsList.push({
        id: Date.now() + Math.floor(Math.random() * 10000),
        category_id: catId,
        category_name: catName,
        subcategory_id: subId || null,
        subcategory_name: subName || null,
        sub_subcategory_id: sscId || null,
        sub_subcategory_name: sscName || null,
        category_type: initialCatType,
        product_name: pName,
        hsn_code: defaultHsn,
        weight: 0,
        mrp: 0,
        selling_price: 0,
        purchase_price: 0,
        quantity: 0,
        series_from: isInitialSeriesOrComp ? qfFrom : 1,
        series_end: isInitialSeriesOrComp ? qfEnd : 1,
        series_start_code: isInitialSeriesOrComp ? String(qfFrom) : '',
        series_end_code: isInitialSeriesOrComp ? String(qfEnd) : '',
        gst_percentage: defaultGst,
        line_total: 0,
        size_name: 'Standard',
        color_name: '',
        brand_id: DEFAULT_BRAND_ID,
        qr_type: (initialCatType === 'SERIES') ? 'SERIES_BATCH' : 'INDIVIDUAL',
        is_complementary: (initialCatType === 'COMPLEMENTARY') ? 1 : 0
    });

    renderAddedProductsTable();
}

function addTemplateProduct(t) {
    const catId = document.getElementById('entryCatId').value;
    const catName = document.getElementById('entryCatName').value;
    const subId = document.getElementById('entrySubId').value;
    const subName = document.getElementById('entrySubName').value;
    const sscId = document.getElementById('entrySubSubId').value;
    const sscName = document.getElementById('entrySubSubName').value;

    const qfCatType = document.getElementById('qfCategoryType');
    const initialCatType = (qfCatType && qfCatType.value) ? qfCatType.value : 'NORMAL';
    const isInitialSeriesOrComp = (initialCatType === 'SERIES' || initialCatType === 'COMPLEMENTARY');
    const qfQty = parseInt(document.getElementById('qfQty')?.value) || 0;
    const qfFrom = parseInt(document.getElementById('qfSeriesFrom')?.value) || 1;
    const qfEnd = parseInt(document.getElementById('qfSeriesEnd')?.value) || (qfQty > 0 ? qfQty : 1);
    const qfHsnVal = (document.getElementById('qfHsnSelect')?.value || '').trim();
    const qfGstVal = document.getElementById('qfGst')?.value;
    const activeCat = CATEGORIES_DATA.find(c => String(c.id) === String(catId));
    const defaultHsn = qfHsnVal || t.hsn_code || (activeCat?.hsn_code || '');
    const defaultGst = (qfGstVal !== undefined && qfGstVal !== '') ? (parseFloat(qfGstVal) || 0) : (parseFloat(t.default_gst_pct) || 5.00);

    addedProductsList.push({
        id: Date.now() + Math.floor(Math.random() * 10000),
        category_id: catId,
        category_name: catName,
        subcategory_id: subId || null,
        subcategory_name: subName || null,
        sub_subcategory_id: sscId || null,
        sub_subcategory_name: sscName || null,
        category_type: initialCatType,
        product_name: t.product_template_name || '',
        hsn_code: defaultHsn,
        weight: parseFloat(t.default_weight_kg) || 0,
        mrp: 0,
        selling_price: 0,
        purchase_price: 0,
        quantity: 0,
        series_from: isInitialSeriesOrComp ? qfFrom : 1,
        series_end: isInitialSeriesOrComp ? qfEnd : 1,
        series_start_code: isInitialSeriesOrComp ? String(qfFrom) : '',
        series_end_code: isInitialSeriesOrComp ? String(qfEnd) : '',
        gst_percentage: defaultGst,
        line_total: 0,
        size_name: 'Standard',
        color_name: '',
        brand_id: DEFAULT_BRAND_ID,
        qr_type: (initialCatType === 'SERIES') ? 'SERIES_BATCH' : 'INDIVIDUAL',
        is_complementary: (initialCatType === 'COMPLEMENTARY') ? 1 : 0
    });

    renderAddedProductsTable();
}

// -----------------------------------------------------------------------------
// 3. INLINE EDITABLE SPREADSHEET TABLE RENDERING
// -----------------------------------------------------------------------------
function updateFinalSaveButtonState() {
    const button = document.getElementById('btnSaveAllProducts');
    if (!button) return;

    const hasNewProducts = addedProductsList.length > 0;
    const hasPendingEdits = pendingSupplierProductEdits.size > 0;
    button.disabled = ((!hasNewProducts && !hasPendingEdits) || !HAS_ACTIVE_WAREHOUSE);

    if (hasPendingEdits && !hasNewProducts) {
        button.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save Product Changes';
    } else if (hasPendingEdits) {
        button.innerHTML = '<i class="bi bi-qr-code me-1"></i> Save Products &amp; Changes &amp; Generate QR Labels &rarr;';
    } else {
        button.innerHTML = '<i class="bi bi-qr-code me-1"></i> Save Products &amp; Generate QR Labels &rarr;';
    }
}

function renderAddedProductsTable() {
    const tbody = document.getElementById('addedProductsTableBody');
    const qfBar = document.getElementById('quickFillBar');

    if (addedProductsList.length === 0) {
        if (qfBar) qfBar.classList.add('d-none');
        tbody.innerHTML = `
            <tr id="emptyProductListNotice">
                <td colspan="13" class="text-center py-5 text-muted">
                    <i class="bi bi-cart-x fs-1 opacity-50 d-block mb-2"></i>
                    <div class="fw-semibold">No Products in Inward List Yet</div>
                    <div class="small">Choose a category and click attribute options above to directly load products here.</div>
                </td>
            </tr>`;
        updateFinalSaveButtonState();
        recalculateAllTotals();
        return;
    }

    if (qfBar) qfBar.classList.remove('d-none');

    let html = '';

    addedProductsList.forEach((item, idx) => {
        let pathBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle">${escapeHtml(item.category_name)}</span>`;
        if (item.subcategory_name) pathBadge += ` &gt; <span class="badge bg-info-subtle text-info border">${escapeHtml(item.subcategory_name)}</span>`;
        if (item.sub_subcategory_name) pathBadge += ` &gt; <span class="badge bg-light text-dark border">${escapeHtml(item.sub_subcategory_name)}</span>`;

        const mrp = parseFloat(item.mrp) || 0;
        const sp = parseFloat(item.selling_price) || 0;
        const cost = parseFloat(item.purchase_price) || 0;

        let priceWarnMsg = '';
        let isPriceInvalid = false;
        if (sp > 0 && cost > 0 && sp <= cost) {
            priceWarnMsg = `<i class="bi bi-exclamation-triangle-fill"></i> Selling must be &gt; Cost (₹${cost.toFixed(2)})!`;
            isPriceInvalid = true;
        } else if (sp > 0 && mrp > 0 && sp >= mrp) {
            priceWarnMsg = `<i class="bi bi-exclamation-triangle-fill"></i> Selling must be &lt; MRP (₹${mrp.toFixed(2)})!`;
            isPriceInvalid = true;
        }
        const isSeriesOrComp = (item.category_type === 'SERIES' || item.category_type === 'COMPLEMENTARY');
        const sFromVal = (item.series_from !== undefined && item.series_from !== null) ? item.series_from : 1;
        const sEndVal = (item.series_end !== undefined && item.series_end !== null) ? item.series_end : ((item.quantity && parseInt(item.quantity) > 0) ? parseInt(item.quantity) : 1);

        let hsnOptionsHtml = `<option value="">-- HSN --</option>`;
        let hasSelectedHsn = false;
        HSN_OPTIONS.forEach(h => {
            const isSel = (item.hsn_code && String(h.hsn_code).toUpperCase() === String(item.hsn_code).toUpperCase());
            if (isSel) hasSelectedHsn = true;
            const label = h.description ? `${h.hsn_code} - ${h.description} (${parseFloat(h.gst_rate)}%)` : `${h.hsn_code} (${parseFloat(h.gst_rate)}%)`;
            hsnOptionsHtml += `<option value="${escapeHtml(h.hsn_code)}" data-gst="${parseFloat(h.gst_rate)}" ${isSel ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        });
        if (item.hsn_code && !hasSelectedHsn) {
            hsnOptionsHtml += `<option value="${escapeHtml(item.hsn_code)}" data-gst="${parseFloat(item.gst_percentage || 5)}" selected>${escapeHtml(item.hsn_code)}</option>`;
        }

        html += `
            <tr id="row_${item.id}">
                <td class="text-center fw-bold text-muted">${idx + 1}</td>
                <td><div class="text-nowrap small">${pathBadge}</div></td>
                <td>
                    <select class="form-select form-select-sm fw-semibold" id="catType_${item.id}"
                            onchange="updateItemField(${item.id}, 'category_type', this.value)">
                        <option value="NORMAL" ${(item.category_type || 'NORMAL') === 'NORMAL' ? 'selected' : ''}>Normal</option>
                        <option value="COMPLEMENTARY" ${item.category_type === 'COMPLEMENTARY' ? 'selected' : ''}>Complementary</option>
                        <option value="SERIES" ${item.category_type === 'SERIES' ? 'selected' : ''}>Series</option>
                    </select>
                    <div id="seriesBox_${item.id}" class="${isSeriesOrComp ? '' : 'd-none'} mt-1.5 p-1 bg-light rounded border shadow-xs">
                        <div class="d-flex align-items-center gap-1">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text py-0 px-1 font-monospace fs-8 text-muted bg-white">From</span>
                                <input type="number" min="1" step="1" id="from_${item.id}"
                                       class="form-control form-control-sm text-center px-1 font-monospace fw-bold series-range-input"
                                       disabled
                                       value="${sFromVal}"
                                       placeholder=""
                                       oninput="updateItemField(${item.id}, 'series_from', this.value)">
                            </div>
                            <span class="text-muted small">&rarr;</span>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text py-0 px-1 font-monospace fs-8 text-muted bg-white">End</span>
                                <input type="number" min="1" step="1" id="end_${item.id}"
                                       class="form-control form-control-sm text-center px-1 font-monospace fw-bold series-range-input"
                                       disabled
                                       value="${sEndVal}"
                                       placeholder=""
                                       oninput="updateItemField(${item.id}, 'series_end', this.value)">
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm fw-semibold text-uppercase" id="name_${item.id}"
                            value="${escapeHtml(item.product_name || '')}" 
                            placeholder=""
                            oninput="this.value=this.value.toUpperCase();updateItemField(${item.id}, 'product_name', this.value)">
                    <div id="nameWarn_${item.id}" class="text-danger fs-8 fw-bold mt-0.5 d-none"></div>
                </td>
                <td>
                    <select class="form-select form-select-sm font-monospace fw-bold" id="hsn_${item.id}"
                            onchange="handleRowHsnChange(${item.id}, this)">
                        ${hsnOptionsHtml}
                    </select>
                    <div id="hsnWarn_${item.id}" class="text-danger fs-8 fw-bold mt-0.5 d-none"></div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0" max="100" id="gst_${item.id}"
                               class="form-control form-control-sm text-center fw-bold"
                               value="${(item.gst_percentage !== undefined && item.gst_percentage !== null) ? parseFloat(item.gst_percentage).toFixed(2) : '5.00'}"
                               placeholder=""
                               oninput="updateItemField(${item.id}, 'gst_percentage', this.value)">
                        <span class="input-group-text bg-light text-muted px-1 fs-8">%</span>
                    </div>
                </td>
                <td>
                    <input type="number" step="0.001" min="0" id="wt_${item.id}"
                            class="form-control form-control-sm text-end font-monospace" 
                            value="${(item.weight && parseFloat(item.weight) > 0) ? parseFloat(item.weight).toFixed(3) : ''}" 
                            placeholder=""
                            oninput="updateItemField(${item.id}, 'weight', this.value)">
                    <div id="wtWarn_${item.id}" class="text-danger fs-8 fw-bold mt-0.5 d-none"></div>
                </td>
                <td class="text-center">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1 fw-bold">
                        ${escapeHtml(item.variant_display || item.size_name || 'Standard')}
                    </span>
                    <div id="dupWarn_${item.id}"></div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted px-1.5 fs-8">₹</span>
                        <input type="number" step="0.01" min="0" id="mrp_${item.id}"
                               class="form-control form-control-sm text-end text-success fw-bold ${(sp > 0 && mrp > 0 && sp >= mrp) ? 'is-invalid border-danger' : ''}" 
                               value="${(item.mrp && parseFloat(item.mrp) > 0) ? parseFloat(item.mrp).toFixed(2) : ''}" 
                               placeholder=""
                               oninput="updateItemField(${item.id}, 'mrp', this.value)">
                    </div>
                    <div id="mrpWarn_${item.id}" class="text-danger fs-8 fw-bold mt-0.5 d-none"></div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted px-1.5 fs-8">₹</span>
                        <input type="number" step="0.01" min="0" id="sp_${item.id}"
                               class="form-control form-control-sm text-end text-primary fw-bold ${isPriceInvalid ? 'is-invalid border-danger' : ''}" 
                               value="${(item.selling_price && parseFloat(item.selling_price) > 0) ? parseFloat(item.selling_price).toFixed(2) : ''}" 
                               placeholder=""
                               oninput="updateItemField(${item.id}, 'selling_price', this.value)">
                    </div>
                    <div id="valWarn_${item.id}" class="${isPriceInvalid ? '' : 'd-none'} text-danger fs-8 fw-bold mt-1 text-end">
                        ${priceWarnMsg}
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted px-1.5 fs-8">₹</span>
                        <input type="number" step="0.01" min="0" id="cost_${item.id}"
                               class="form-control form-control-sm text-end text-dark fw-bold ${(sp > 0 && cost > 0 && sp <= cost) ? 'is-invalid border-danger' : ''}" 
                               value="${(item.purchase_price && parseFloat(item.purchase_price) > 0) ? parseFloat(item.purchase_price).toFixed(2) : ''}" 
                               placeholder=""
                               oninput="updateItemField(${item.id}, 'purchase_price', this.value)">
                    </div>
                    <div id="costWarn_${item.id}" class="text-danger fs-8 fw-bold mt-0.5 d-none"></div>
                </td>
                <td>
                    <input type="number" min="0" step="1" id="qty_${item.id}"
                           class="form-control form-control-sm text-center fw-bold fs-7" 
                           value="${(item.quantity && parseInt(item.quantity) > 0) ? item.quantity : ''}" 
                           placeholder=""
                           oninput="updateItemField(${item.id}, 'quantity', this.value)">
                    <div id="qtyWarn_${item.id}" class="text-danger fs-8 fw-bold mt-0.5 d-none"></div>
                </td>
                <td class="text-end fw-bold text-primary fs-7" id="lineTotal_${item.id}">
                    ${(parseFloat(item.selling_price) > 0 && parseInt(item.quantity) > 0) ? `
                        <div>₹${(item.selling_price * item.quantity).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                        <div class="text-muted fs-8">₹${parseFloat(item.selling_price).toFixed(2)} &times; ${item.quantity}</div>
                    ` : `
                        <div class="text-muted">₹0.00</div>
                    `}
                </td>
                <td class="text-center text-nowrap">
                    <button type="button" class="btn btn-xs btn-outline-danger py-1 px-1.5" onclick="removeProductItem(${item.id})" title="Remove Product">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    recalculateAllTotals();
    checkAllRowValidationsAndDuplicates();
}

function handleRowHsnChange(itemId, selectEl) {
    const val = (selectEl.value || '').trim();
    const item = addedProductsList.find(it => it.id === itemId);
    if (!item) return;

    markProductFieldTouched(item, 'hsn_code');
    item.hsn_code = val;

    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    if (selectedOpt && selectedOpt.dataset && selectedOpt.dataset.gst !== undefined) {
        const gstVal = parseFloat(selectedOpt.dataset.gst);
        if (!isNaN(gstVal)) {
            item.gst_percentage = gstVal;
            const gstInp = document.getElementById(`gst_${itemId}`);
            if (gstInp) gstInp.value = gstVal.toFixed(2);
        }
    }
    recalculateAllTotals();
    checkAllRowValidationsAndDuplicates();
}

function updateItemField(itemId, fieldName, value) {
    const item = addedProductsList.find(it => it.id === itemId);
    if (!item) return;

    markProductFieldTouched(item, fieldName);

    if (fieldName === 'category_type') {
        item.category_type = value;
        item.is_complementary = (value === 'COMPLEMENTARY') ? 1 : 0;
        item.qr_type = (value === 'SERIES') ? 'SERIES_BATCH' : 'INDIVIDUAL';

        const isSeriesOrComp = (value === 'SERIES' || value === 'COMPLEMENTARY');
        const box = document.getElementById(`seriesBox_${itemId}`);
        if (box) {
            box.classList.toggle('d-none', !isSeriesOrComp);
        }

        if (isSeriesOrComp) {
            const qty = (item.quantity && parseInt(item.quantity) > 0) ? parseInt(item.quantity) : 1;
            item.series_from = 1;
            item.series_end = qty;
            item.series_start_code = '1';
            item.series_end_code = String(qty);

            const fromEl = document.getElementById(`from_${itemId}`);
            const endEl = document.getElementById(`end_${itemId}`);
            if (fromEl) fromEl.value = 1;
            if (endEl) endEl.value = qty;
        } else {
            item.series_from = null;
            item.series_end = null;
            item.series_start_code = '';
            item.series_end_code = '';
        }
        return;
    } else if (fieldName === 'series_from') {
        item.series_from = parseInt(value) || 1;
        item.series_start_code = String(item.series_from);
        return;
    } else if (fieldName === 'series_end') {
        item.series_end = parseInt(value) || 1;
        item.series_end_code = String(item.series_end);
        return;
    } else if (fieldName === 'selling_price') {
        item.selling_price = parseFloat(value) || 0;
    } else if (fieldName === 'mrp') {
        item.mrp = parseFloat(value) || 0;
    } else if (fieldName === 'purchase_price') {
        item.purchase_price = parseFloat(value) || 0;
    } else if (fieldName === 'quantity') {
        const qty = parseInt(value) || 0;
        item.quantity = qty;

        // Auto-set From to 1 and End to Qty when Series or Complementary is selected
        if (item.category_type === 'SERIES' || item.category_type === 'COMPLEMENTARY') {
            item.series_from = 1;
            item.series_end = qty > 0 ? qty : 1;
            item.series_start_code = '1';
            item.series_end_code = String(item.series_end);

            const fromEl = document.getElementById(`from_${itemId}`);
            const endEl = document.getElementById(`end_${itemId}`);
            if (fromEl) fromEl.value = 1;
            if (endEl) endEl.value = item.series_end;
        }
    } else if (fieldName === 'weight') {
        item.weight = parseFloat(value) || 0;
    } else if (fieldName === 'gst_percentage') {
        item.gst_percentage = parseFloat(value) || 0;
    } else {
        item[fieldName] = value;
    }

    // Line total = Selling Price * Quantity (e.g. 800 * 20 = 16,000)
    item.line_total = (item.selling_price || 0) * (item.quantity || 0);

    // Update Line Total element on that row
    const lineTotalEl = document.getElementById(`lineTotal_${itemId}`);
    if (lineTotalEl) {
        if (item.selling_price > 0 && item.quantity > 0) {
            lineTotalEl.innerHTML = `
                <div>₹${item.line_total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                <div class="text-muted fs-8">₹${(item.selling_price || 0).toFixed(2)} &times; ${item.quantity || 0}</div>
            `;
        } else {
            lineTotalEl.innerHTML = `<div class="text-muted">₹0.00</div>`;
        }
    }

    // Price Validation: Supplier Cost < Selling Price < Base MRP
    const mrpInput = document.getElementById(`mrp_${itemId}`);
    const spInput = document.getElementById(`sp_${itemId}`);
    const costInput = document.getElementById(`cost_${itemId}`);
    const valWarn = document.getElementById(`valWarn_${itemId}`);

    const mrp = parseFloat(item.mrp) || 0;
    const sp = parseFloat(item.selling_price) || 0;
    const cost = parseFloat(item.purchase_price) || 0;

    let warnMsg = '';
    let isInvalid = false;
    if (sp > 0 && cost > 0 && sp <= cost) {
        warnMsg = `<i class="bi bi-exclamation-triangle-fill"></i> Selling must be &gt; Cost (₹${cost.toFixed(2)})!`;
        isInvalid = true;
    } else if (sp > 0 && mrp > 0 && sp >= mrp) {
        warnMsg = `<i class="bi bi-exclamation-triangle-fill"></i> Selling must be &lt; MRP (₹${mrp.toFixed(2)})!`;
        isInvalid = true;
    }

    if (spInput) {
        spInput.classList.toggle('is-invalid', isInvalid);
        spInput.classList.toggle('border-danger', isInvalid);
    }
    if (mrpInput) {
        const isMrpBad = (sp > 0 && mrp > 0 && sp >= mrp);
        mrpInput.classList.toggle('is-invalid', isMrpBad);
        mrpInput.classList.toggle('border-danger', isMrpBad);
    }
    if (costInput) {
        const isCostBad = (sp > 0 && cost > 0 && sp <= cost);
        costInput.classList.toggle('is-invalid', isCostBad);
        costInput.classList.toggle('border-danger', isCostBad);
    }

    if (valWarn) {
        if (isInvalid) {
            valWarn.innerHTML = warnMsg;
            valWarn.classList.remove('d-none');
        } else {
            valWarn.classList.add('d-none');
            valWarn.innerHTML = '';
        }
    }

    recalculateAllTotals();
    checkAllRowValidationsAndDuplicates();
}

// -----------------------------------------------------------------------------
// 3.1 QUICK FILL & COPY TO ALL VARIANTS UTILITIES
// -----------------------------------------------------------------------------
function validateQuickFillPricing() {
    const mrp = parseFloat(document.getElementById('qfMrp')?.value) || 0;
    const sp = parseFloat(document.getElementById('qfSp')?.value) || 0;
    const cost = parseFloat(document.getElementById('qfCost')?.value) || 0;
    const wtVal = document.getElementById('qfWeight')?.value;
    const qtyVal = document.getElementById('qfQty')?.value;

    const spInput = document.getElementById('qfSp');
    const mrpInput = document.getElementById('qfMrp');
    const costInput = document.getElementById('qfCost');
    const wtInput = document.getElementById('qfWeight');
    const qtyInput = document.getElementById('qfQty');

    const warn = document.getElementById('qfPriceWarn');
    const costWarn = document.getElementById('qfCostWarn');
    const mrpWarn = document.getElementById('qfMrpWarn');
    const wtWarn = document.getElementById('qfWeightWarn');
    const qtyWarn = document.getElementById('qfQtyWarn');

    let warnMsg = '';
    let isSpInvalid = false;
    let isCostInvalid = false;
    let isMrpInvalid = false;

    if (sp > 0 && cost > 0 && sp <= cost) {
        warnMsg = `<i class="bi bi-exclamation-triangle-fill"></i> Selling must be &gt; Cost (₹${cost.toFixed(2)})`;
        isSpInvalid = true;
        isCostInvalid = true;
        if (costWarn) {
            costWarn.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Must be &lt; Selling (₹${sp.toFixed(2)})`;
            costWarn.classList.remove('d-none');
        }
    } else {
        if (costWarn) { costWarn.classList.add('d-none'); costWarn.innerHTML = ''; }
    }

    if (sp > 0 && mrp > 0 && sp >= mrp) {
        warnMsg = `<i class="bi bi-exclamation-triangle-fill"></i> Selling must be &lt; MRP (₹${mrp.toFixed(2)})`;
        isSpInvalid = true;
        isMrpInvalid = true;
        if (mrpWarn) {
            mrpWarn.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Must be &gt; Selling (₹${sp.toFixed(2)})`;
            mrpWarn.classList.remove('d-none');
        }
    } else {
        if (mrpWarn) { mrpWarn.classList.add('d-none'); mrpWarn.innerHTML = ''; }
    }

    if (spInput) {
        spInput.classList.toggle('is-invalid', isSpInvalid);
        spInput.classList.toggle('border-danger', isSpInvalid);
    }
    if (mrpInput) {
        mrpInput.classList.toggle('is-invalid', isMrpInvalid);
        mrpInput.classList.toggle('border-danger', isMrpInvalid);
    }
    if (costInput) {
        costInput.classList.toggle('is-invalid', isCostInvalid);
        costInput.classList.toggle('border-danger', isCostInvalid);
    }

    if (warn) {
        if (isSpInvalid) {
            warn.innerHTML = warnMsg;
            warn.classList.remove('d-none');
        } else {
            warn.classList.add('d-none');
            warn.innerHTML = '';
        }
    }

    if (wtInput && wtVal !== undefined && wtVal !== '') {
        const wtNum = parseFloat(wtVal);
        if (isNaN(wtNum) || wtNum <= 0) {
            wtInput.classList.add('is-invalid', 'border-danger');
            if (wtWarn) { wtWarn.textContent = 'Weight must be > 0'; wtWarn.classList.remove('d-none'); }
        } else {
            wtInput.classList.remove('is-invalid', 'border-danger');
            if (wtWarn) wtWarn.classList.add('d-none');
        }
    }

    if (qtyInput && qtyVal !== undefined && qtyVal !== '') {
        const qtyNum = parseInt(qtyVal, 10);
        if (isNaN(qtyNum) || qtyNum < 1) {
            qtyInput.classList.add('is-invalid', 'border-danger');
            if (qtyWarn) { qtyWarn.textContent = 'Qty must be >= 1'; qtyWarn.classList.remove('d-none'); }
        } else {
            qtyInput.classList.remove('is-invalid', 'border-danger');
            if (qtyWarn) qtyWarn.classList.add('d-none');
        }
    }

    return !isSpInvalid && !isCostInvalid;
}

/**
 * Real-time Inline & Duplication Validation for all inward product rows.
 */
function checkAllRowValidationsAndDuplicates() {
    if (addedProductsList.length === 0) {
        const notice = document.getElementById('saveProductsValidationNotice');
        updateFinalSaveButtonState();
        if (notice) notice.classList.add('d-none');
        return true;
    }

    let hasErrors = false;
    let hasVisibleErrors = false;
    const variantMap = new Map();

    // 1. Identify duplicates inside the added inward list
    addedProductsList.forEach(item => {
        const pName = (item.product_name || '').trim().toUpperCase();
        const vDisplay = (item.variant_display || item.size_name || 'Standard').trim().toUpperCase();
        const key = pName + '|||' + vDisplay;
        if (!variantMap.has(key)) {
            variantMap.set(key, []);
        }
        variantMap.get(key).push(item.id);
    });

    const duplicateItemIds = new Set();
    variantMap.forEach((ids, key) => {
        if (ids.length > 1 && !key.startsWith('|||')) {
            ids.forEach(id => duplicateItemIds.add(id));
        }
    });

    // 2. Validate every row
    addedProductsList.forEach((item) => {
        const rowEl = document.getElementById(`row_${item.id}`);
        const nameInput = document.getElementById(`name_${item.id}`);
        const hsnInput = document.getElementById(`hsn_${item.id}`);
        const wtInput = document.getElementById(`wt_${item.id}`);
        const mrpInput = document.getElementById(`mrp_${item.id}`);
        const spInput = document.getElementById(`sp_${item.id}`);
        const costInput = document.getElementById(`cost_${item.id}`);
        const qtyInput = document.getElementById(`qty_${item.id}`);

        const nameWarn = document.getElementById(`nameWarn_${item.id}`);
        const hsnWarn = document.getElementById(`hsnWarn_${item.id}`);
        const wtWarn = document.getElementById(`wtWarn_${item.id}`);
        const mrpWarn = document.getElementById(`mrpWarn_${item.id}`);
        const valWarn = document.getElementById(`valWarn_${item.id}`);
        const costWarn = document.getElementById(`costWarn_${item.id}`);
        const qtyWarn = document.getElementById(`qtyWarn_${item.id}`);
        const dupWarn = document.getElementById(`dupWarn_${item.id}`);

        const pName = (item.product_name || '').trim();
        const hsn = (item.hsn_code || '').trim();
        const wt = parseFloat(item.weight) || 0;
        const mrp = parseFloat(item.mrp) || 0;
        const sp = parseFloat(item.selling_price) || 0;
        const cost = parseFloat(item.purchase_price) || 0;
        const qty = parseInt(item.quantity, 10) || 0;

        let rowHasError = false;

        // Name
        const isNameInvalid = !pName || pName.length < 2;
        const showNameError = shouldShowProductFieldError(item, 'product_name');
        if (isNameInvalid) {
            if (nameInput) nameInput.classList.toggle('is-invalid', showNameError);
            if (nameInput) nameInput.classList.toggle('border-danger', showNameError);
            if (nameWarn) {
                nameWarn.textContent = showNameError ? 'Name required (min 2 chars)' : '';
                nameWarn.classList.toggle('d-none', !showNameError);
            }
            if (showNameError) hasVisibleErrors = true;
            rowHasError = true;
        } else {
            if (nameInput) nameInput.classList.remove('is-invalid', 'border-danger');
            if (nameWarn) nameWarn.classList.add('d-none');
        }

        // HSN
        const isHsnInvalid = !hsn;
        const showHsnError = shouldShowProductFieldError(item, 'hsn_code');
        if (isHsnInvalid) {
            if (hsnInput) hsnInput.classList.toggle('is-invalid', showHsnError);
            if (hsnInput) hsnInput.classList.toggle('border-danger', showHsnError);
            if (hsnWarn) {
                hsnWarn.textContent = showHsnError ? 'HSN required' : '';
                hsnWarn.classList.toggle('d-none', !showHsnError);
            }
            if (showHsnError) hasVisibleErrors = true;
            rowHasError = true;
        } else {
            if (hsnInput) hsnInput.classList.remove('is-invalid', 'border-danger');
            if (hsnWarn) hsnWarn.classList.add('d-none');
        }

        // Weight
        const isWeightInvalid = wt <= 0;
        const showWeightError = shouldShowProductFieldError(item, 'weight');
        if (isWeightInvalid) {
            if (wtInput) wtInput.classList.toggle('is-invalid', showWeightError);
            if (wtInput) wtInput.classList.toggle('border-danger', showWeightError);
            if (wtWarn) {
                wtWarn.textContent = showWeightError ? 'Weight must be > 0' : '';
                wtWarn.classList.toggle('d-none', !showWeightError);
            }
            if (showWeightError) hasVisibleErrors = true;
            rowHasError = true;
        } else {
            if (wtInput) wtInput.classList.remove('is-invalid', 'border-danger');
            if (wtWarn) wtWarn.classList.add('d-none');
        }

        // Pricing: Cost < Selling Price < Base MRP
        let isSpErr = false;
        let isMrpErr = false;
        let isCostErr = false;
        let spErrMsg = '';
        let mrpErrMsg = '';
        let costErrMsg = '';

        if (mrp <= 0) {
            isMrpErr = true;
            mrpErrMsg = 'MRP > 0 required';
            rowHasError = true;
        }

        if (cost <= 0) {
            isCostErr = true;
            costErrMsg = 'Cost > 0 required';
            rowHasError = true;
        }

        if (sp <= 0) {
            isSpErr = true;
            spErrMsg = '<i class="bi bi-exclamation-triangle-fill"></i> Selling price > 0 required';
            rowHasError = true;
        } else if (cost > 0 && sp <= cost) {
            isSpErr = true;
            isCostErr = true;
            spErrMsg = `<i class="bi bi-exclamation-triangle-fill"></i> Selling must be &gt; Cost (₹${cost.toFixed(2)})!`;
            costErrMsg = `Cost must be < Selling Price (₹${sp.toFixed(2)})`;
            rowHasError = true;
        } else if (mrp > 0 && sp >= mrp) {
            isSpErr = true;
            isMrpErr = true;
            spErrMsg = `<i class="bi bi-exclamation-triangle-fill"></i> Selling must be &lt; MRP (₹${mrp.toFixed(2)})!`;
            mrpErrMsg = `MRP must be > Selling Price (₹${sp.toFixed(2)})`;
            rowHasError = true;
        }

        const anyPriceFieldTouched = shouldShowProductFieldError(item, 'mrp', 'selling_price', 'purchase_price');
        const showSpError = isSpErr && (sp <= 0
            ? shouldShowProductFieldError(item, 'selling_price')
            : anyPriceFieldTouched);
        const showMrpError = isMrpErr && (mrp <= 0
            ? shouldShowProductFieldError(item, 'mrp')
            : anyPriceFieldTouched);
        const showCostError = isCostErr && (cost <= 0
            ? shouldShowProductFieldError(item, 'purchase_price')
            : anyPriceFieldTouched);

        if (spInput) {
            spInput.classList.toggle('is-invalid', showSpError);
            spInput.classList.toggle('border-danger', showSpError);
        }
        if (mrpInput) {
            mrpInput.classList.toggle('is-invalid', showMrpError);
            mrpInput.classList.toggle('border-danger', showMrpError);
        }
        if (costInput) {
            costInput.classList.toggle('is-invalid', showCostError);
            costInput.classList.toggle('border-danger', showCostError);
        }
        if (valWarn) {
            if (showSpError) {
                valWarn.innerHTML = spErrMsg;
                valWarn.classList.remove('d-none');
            } else {
                valWarn.classList.add('d-none');
                valWarn.innerHTML = '';
            }
        }
        if (mrpWarn) {
            if (showMrpError) {
                mrpWarn.textContent = mrpErrMsg;
                mrpWarn.classList.remove('d-none');
            } else {
                mrpWarn.textContent = '';
                mrpWarn.classList.add('d-none');
            }
        }
        if (costWarn) {
            if (showCostError) {
                costWarn.textContent = costErrMsg;
                costWarn.classList.remove('d-none');
            } else {
                costWarn.textContent = '';
                costWarn.classList.add('d-none');
            }
        }
        if (showSpError || showMrpError || showCostError) hasVisibleErrors = true;

        // Qty
        const isQtyInvalid = qty < 1;
        const showQtyError = shouldShowProductFieldError(item, 'quantity');
        if (isQtyInvalid) {
            if (qtyInput) qtyInput.classList.toggle('is-invalid', showQtyError);
            if (qtyInput) qtyInput.classList.toggle('border-danger', showQtyError);
            if (qtyWarn) {
                qtyWarn.textContent = showQtyError ? 'Qty >= 1 required' : '';
                qtyWarn.classList.toggle('d-none', !showQtyError);
            }
            if (showQtyError) hasVisibleErrors = true;
            rowHasError = true;
        } else {
            if (qtyInput) qtyInput.classList.remove('is-invalid', 'border-danger');
            if (qtyWarn) qtyWarn.classList.add('d-none');
        }

        // Inward list duplicate
        let dupHtml = '';
        if (duplicateItemIds.has(item.id)) {
            dupHtml += `<span class="badge bg-danger text-white py-0.5 px-1 fs-8 d-block mt-1 shadow-xs"><i class="bi bi-copy me-1"></i>Duplicate Variant Line</span>`;
            rowHasError = true;
            hasVisibleErrors = true;
        }

        // Catalog duplicate
        if (typeof EXISTING_SUPPLIER_PRODUCTS !== 'undefined' && Array.isArray(EXISTING_SUPPLIER_PRODUCTS)) {
            const vDisplay = (item.variant_display || item.size_name || '').trim().toUpperCase();
            const existsInCat = EXISTING_SUPPLIER_PRODUCTS.some(ep => {
                const epName = (ep.product_name || ep.name || '').trim().toUpperCase();
                const epSize = (ep.size_name || ep.variant_name || '').trim().toUpperCase();
                return epName && pName && epName === pName.toUpperCase() && (!vDisplay || epSize === vDisplay);
            });
            if (existsInCat) {
                dupHtml += `<span class="badge bg-warning-subtle text-dark border border-warning py-0.5 px-1 fs-8 d-block mt-1" title="Variant already exists in supplier's catalog"><i class="bi bi-exclamation-triangle me-1"></i>In Supplier Catalog</span>`;
            }
        }

        if (dupWarn) dupWarn.innerHTML = dupHtml;

        if (rowEl) {
            rowEl.classList.toggle('table-danger', duplicateItemIds.has(item.id));
        }

        if (rowHasError) hasErrors = true;
    });

    const notice = document.getElementById('saveProductsValidationNotice');
    updateFinalSaveButtonState();
    if (notice) {
        notice.classList.toggle('d-none', !hasVisibleErrors || addedProductsList.length === 0);
    }

    return !hasErrors;
}

function handleQfCategoryTypeChange(typeVal) {
    const box = document.getElementById('qfSeriesRangeBox');
    const fromInput = document.getElementById('qfSeriesFrom');
    const endInput = document.getElementById('qfSeriesEnd');
    const qtyVal = parseInt(document.getElementById('qfQty')?.value) || 0;
    const isSeriesOrComp = (typeVal === 'SERIES' || typeVal === 'COMPLEMENTARY');

    if (box) {
        box.classList.toggle('d-none', !isSeriesOrComp);
    }
    if (isSeriesOrComp) {
        if (fromInput) fromInput.value = 1;
        if (endInput) endInput.value = (qtyVal > 0) ? qtyVal : 1;
    }
}

function handleQfQtyInput(qtyVal) {
    const catType = document.getElementById('qfCategoryType')?.value || 'NORMAL';
    if (catType === 'SERIES' || catType === 'COMPLEMENTARY') {
        const fromInput = document.getElementById('qfSeriesFrom');
        const endInput = document.getElementById('qfSeriesEnd');
        const qty = parseInt(qtyVal) || 0;
        if (fromInput) fromInput.value = 1;
        if (endInput) endInput.value = (qty > 0) ? qty : 1;
    }
}

function showQuickFillNotice(msg) {
    const notice = document.getElementById('quickFillSuccessNotice');
    const noticeText = document.getElementById('quickFillNoticeText');
    if (notice && noticeText) {
        noticeText.textContent = msg;
        notice.classList.remove('d-none');
        clearTimeout(window._qfNoticeTimer);
        window._qfNoticeTimer = setTimeout(() => {
            notice.classList.add('d-none');
        }, 4000);
    }
}

function applyQuickFillToAll() {
    if (addedProductsList.length === 0) {
        alert('Please select attributes to load variants first.');
        return;
    }

    const pName = (document.getElementById('qfProductName')?.value || '').trim().toUpperCase();
    const catType = (document.getElementById('qfCategoryType')?.value || 'NORMAL');
    const hsn = (document.getElementById('qfHsnSelect')?.value || '').trim();
    const qfGstVal = document.getElementById('qfGst')?.value;
    const weightVal = document.getElementById('qfWeight')?.value;
    const mrpVal = document.getElementById('qfMrp')?.value;
    const spVal = document.getElementById('qfSp')?.value;
    const costVal = document.getElementById('qfCost')?.value;
    const qtyVal = document.getElementById('qfQty')?.value;
    const qfFromVal = parseInt(document.getElementById('qfSeriesFrom')?.value) || 1;
    const qfEndVal = parseInt(document.getElementById('qfSeriesEnd')?.value) || ((qtyVal !== undefined && qtyVal !== '') ? (parseInt(qtyVal) > 0 ? parseInt(qtyVal) : 1) : 1);

    const mrpNum = parseFloat(mrpVal) || 0;
    const spNum = parseFloat(spVal) || 0;
    const costNum = parseFloat(costVal) || 0;

    if (spNum > 0 && costNum > 0 && spNum <= costNum) {
        alert(`Validation Error: Selling Price (₹${spNum.toFixed(2)}) must be greater than Supplier Cost (₹${costNum.toFixed(2)}).`);
        const spEl = document.getElementById('qfSp');
        if (spEl) spEl.focus();
        return;
    }
    if (spNum > 0 && mrpNum > 0 && spNum >= mrpNum) {
        alert(`Validation Error: Selling Price (₹${spNum.toFixed(2)}) must be less than Base MRP (₹${mrpNum.toFixed(2)}).`);
        const spEl = document.getElementById('qfSp');
        if (spEl) spEl.focus();
        return;
    }

    if (weightVal !== undefined && weightVal !== '' && parseFloat(weightVal) <= 0) {
        alert('Validation Error: Weight (kg) must be greater than 0.');
        const wtEl = document.getElementById('qfWeight');
        if (wtEl) wtEl.focus();
        return;
    }
    if (qtyVal !== undefined && qtyVal !== '' && parseInt(qtyVal, 10) < 1) {
        alert('Validation Error: Quantity must be at least 1.');
        const qtyEl = document.getElementById('qfQty');
        if (qtyEl) qtyEl.focus();
        return;
    }

    addedProductsList.forEach(item => {
        if (pName) {
            item.product_name = pName;
            markProductFieldTouched(item, 'product_name');
        }
        if (catType) {
            item.category_type = catType;
            item.is_complementary = (catType === 'COMPLEMENTARY') ? 1 : 0;
            item.qr_type = (catType === 'SERIES') ? 'SERIES_BATCH' : 'INDIVIDUAL';
        }
        if (hsn) {
            item.hsn_code = hsn;
            markProductFieldTouched(item, 'hsn_code');
        }
        if (qfGstVal !== undefined && qfGstVal !== '') item.gst_percentage = parseFloat(qfGstVal) || 0;
        if (weightVal !== undefined && weightVal !== '') {
            item.weight = parseFloat(weightVal) || 0;
            markProductFieldTouched(item, 'weight');
        }
        if (mrpVal !== undefined && mrpVal !== '') {
            item.mrp = parseFloat(mrpVal) || 0;
            markProductFieldTouched(item, 'mrp');
        }
        if (spVal !== undefined && spVal !== '') {
            item.selling_price = parseFloat(spVal) || 0;
            markProductFieldTouched(item, 'selling_price');
        }
        if (costVal !== undefined && costVal !== '') {
            item.purchase_price = parseFloat(costVal) || 0;
            markProductFieldTouched(item, 'purchase_price');
        }
        if (qtyVal !== undefined && qtyVal !== '') {
            item.quantity = parseInt(qtyVal) || 0;
            markProductFieldTouched(item, 'quantity');
        }

        if (item.category_type === 'SERIES' || item.category_type === 'COMPLEMENTARY') {
            item.series_from = qfFromVal;
            item.series_end = (qtyVal !== undefined && qtyVal !== '' && parseInt(qtyVal) > 0) ? parseInt(qtyVal) : qfEndVal;
            item.series_start_code = String(item.series_from);
            item.series_end_code = String(item.series_end);
        } else {
            item.series_from = null;
            item.series_end = null;
            item.series_start_code = '';
            item.series_end_code = '';
        }

        item.line_total = (item.selling_price || 0) * (item.quantity || 0);
    });

    renderAddedProductsTable();
    showQuickFillNotice(`Quick Fill details applied across all ${addedProductsList.length} variants!`);
}

function recalculateAllTotals() {
    let totalQty = 0;
    let totalWeight = 0.000;
    let retailTotal = 0.00;
    let supplierGrossCost = 0.00;
    let supplierGstTotal = 0.00;
    let supplierProductTotal = 0.00;

    addedProductsList.forEach(item => {
        const qty = parseInt(item.quantity) || 0;
        const wt = parseFloat(item.weight) || 0;
        const sp = parseFloat(item.selling_price) || 0;
        const cost = parseFloat(item.purchase_price) || 0;
        const gstPct = (item.gst_percentage !== undefined && item.gst_percentage !== null) ? parseFloat(item.gst_percentage) : 5.00;
        item.line_total = sp * qty;
        totalQty += qty;
        totalWeight += (wt * qty);
        retailTotal += item.line_total;
        const lineCost = Math.round((cost * qty + Number.EPSILON) * 100) / 100;
        const lineTax = Math.round((lineCost * (gstPct / 100) + Number.EPSILON) * 100) / 100;
        supplierGrossCost += lineCost;
        supplierGstTotal += lineTax;
        supplierProductTotal += (lineCost + lineTax);
    });

    document.getElementById('badgeItemCount').textContent = `${addedProductsList.length} Product(s)`;
    document.getElementById('footerTotalQty').textContent = `${totalQty} pcs`;
    document.getElementById('footerTotalWeight').textContent = `${totalWeight.toFixed(3)} kg`;
    document.getElementById('footerGrandTotal').textContent = '₹' + retailTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const grossCostEl = document.getElementById('footerSupplierGrossCost');
    if (grossCostEl) grossCostEl.textContent = '₹' + supplierGrossCost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const gstTotalEl = document.getElementById('footerSupplierGstTotal');
    if (gstTotalEl) gstTotalEl.textContent = '₹' + supplierGstTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const supplierTotalEl = document.getElementById('footerSupplierProductTotal');
    if (supplierTotalEl) supplierTotalEl.textContent = '₹' + supplierProductTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    updateFinalSaveButtonState();
}

function removeProductItem(itemId) {
    const item = addedProductsList.find(it => it.id === itemId);
    if (item && item.combo_key) {
        excludedComboKeys.add(item.combo_key);
    }
    addedProductsList = addedProductsList.filter(it => it.id !== itemId);
    renderAddedProductsTable();
    updateAttributeChipVisuals();
    renderMatrixSection();
}

// -----------------------------------------------------------------------------
// 4. FINAL FORM SUBMIT & MULTI-ROW VALIDATION
// -----------------------------------------------------------------------------
async function submitFinalProducts() {
    if (addedProductsList.length === 0 && pendingSupplierProductEdits.size === 0) {
        showSupplierToast('Nothing to save', 'Add a product or edit an existing product price first.', 'warning');
        return;
    }

    addedProductsList.forEach(item => submittedProductItems.add(item));
    if (!checkAllRowValidationsAndDuplicates()) {
        const firstInvalid = document.querySelector('#addedProductsTableBody .is-invalid');
        if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    // Strict Multi-Row Validation
    for (let i = 0; i < addedProductsList.length; i++) {
        const item = addedProductsList[i];
        const lineNum = i + 1;

        if (!item.product_name || !item.product_name.trim()) {
            alert(`Line #${lineNum}: Product Name is mandatory.`);
            return;
        }
        if (!item.hsn_code || !item.hsn_code.trim()) {
            alert(`Line #${lineNum} (${item.product_name}): HSN Code is mandatory.`);
            return;
        }
        if (parseFloat(item.weight) <= 0) {
            alert(`Line #${lineNum} (${item.product_name}): Weight in kg must be greater than 0.`);
            return;
        }
        if (parseFloat(item.mrp) <= 0) {
            alert(`Line #${lineNum} (${item.product_name}): Base MRP Price must be greater than 0.`);
            return;
        }
        if (parseFloat(item.selling_price) <= 0) {
            alert(`Line #${lineNum} (${item.product_name}): Selling Price must be greater than 0.`);
            return;
        }
        if (parseFloat(item.purchase_price) <= 0) {
            alert(`Line #${lineNum} (${item.product_name}): Supplier Cost must be greater than 0.`);
            return;
        }

        const mrp = parseFloat(item.mrp) || 0;
        const sp = parseFloat(item.selling_price) || 0;
        const cost = parseFloat(item.purchase_price) || 0;

        if (sp <= cost) {
            alert(`Validation Error on Line #${lineNum} (${item.product_name}):\n\nSelling Price (₹${sp.toFixed(2)}) must be greater than Supplier Cost (₹${cost.toFixed(2)}).`);
            const spInput = document.getElementById(`sp_${item.id}`);
            if (spInput) spInput.focus();
            return;
        }
        if (sp >= mrp) {
            alert(`Validation Error on Line #${lineNum} (${item.product_name}):\n\nSelling Price (₹${sp.toFixed(2)}) must be less than Base MRP (₹${mrp.toFixed(2)}).`);
            const spInput = document.getElementById(`sp_${item.id}`);
            if (spInput) spInput.focus();
            return;
        }
        if (parseInt(item.quantity) <= 0) {
            alert(`Line #${lineNum} (${item.product_name}): Quantity must be at least 1.`);
            return;
        }

        // Validate Series / Complementary range
        if (item.category_type === 'SERIES' || item.category_type === 'COMPLEMENTARY') {
            const sFrom = parseInt(item.series_from) || 1;
            const sEnd = parseInt(item.series_end) || (item.quantity > 0 ? item.quantity : 1);
            if (sEnd < sFrom) {
                alert(`Line #${lineNum} (${item.product_name}): Sequence End number (${sEnd}) cannot be smaller than From number (${sFrom}).`);
                const endInput = document.getElementById(`end_${item.id}`);
                if (endInput) endInput.focus();
                return;
            }
            item.series_from = sFrom;
            item.series_end = sEnd;
            item.series_start_code = String(sFrom);
            item.series_end_code = String(sEnd);
            item.pack_quantity = Math.max(1, (sEnd - sFrom + 1));
        }
    }

    const btn = document.getElementById('btnSaveAllProducts');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Saving Changes...`;

    try {
        await savePendingSupplierProductEdits();
    } catch (error) {
        updateFinalSaveButtonState();
        showSupplierToast('Save failed', error.message || 'Unable to save supplier product changes.', 'danger');
        return;
    }

    if (addedProductsList.length === 0) {
        updateFinalSaveButtonState();
        showSupplierToast('Saved', 'Supplier product changes saved successfully.', 'success');
        window.setTimeout(() => window.location.assign(SUPPLIER_INDEX_URL), 800);
        return;
    }

    document.getElementById('jsonProductsInput').value = JSON.stringify(addedProductsList);
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Crediting Stock &amp; Saving Bill...`;
    document.getElementById('finalSubmitForm').submit();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// =========================================================================
// QUICK ADD HSN CODE LOGIC (SUPPLIER PURCHASE)
// =========================================================================
function openSupplierAddHsnModal() {
    const hsnInp = document.getElementById('supplierHsnCodeInput');
    const descInp = document.getElementById('supplierHsnDescInput');
    const gstSel = document.getElementById('supplierHsnGstInput');
    if (hsnInp) hsnInp.value = '';
    if (descInp) descInp.value = '';
    if (gstSel) gstSel.value = '5.00';

    new bootstrap.Modal(document.getElementById('modalSupplierAddHsn')).show();
    setTimeout(() => {
        if (hsnInp) hsnInp.focus();
    }, 400);
}

async function submitSupplierAddHsn(e) {
    e.preventDefault();
    const hsn = (document.getElementById('supplierHsnCodeInput').value || '').trim().toUpperCase();
    const desc = (document.getElementById('supplierHsnDescInput').value || '').trim();
    const gst = document.getElementById('supplierHsnGstInput').value || '5.00';

    if (!hsn) {
        alert('Please enter an HSN code.');
        return;
    }

    const btn = document.getElementById('btnSaveSupplierHsn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    const p = new URLSearchParams();
    p.append('hsn_code', hsn);
    p.append('description', desc || `HSN ${hsn}`);
    p.append('gst_rate', gst);

    try {
        const response = await fetch(`${APP_URL}/api/hsn-codes/store`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: p.toString()
        });
        const data = await response.json();
        if (!response.ok || !data.success || !data.data) {
            throw new Error(data.message || 'The HSN code could not be saved.');
        }

        const modalElement = document.getElementById('modalSupplierAddHsn');
        const modalInstance = bootstrap.Modal.getInstance(modalElement);
        if (modalInstance) modalInstance.hide();

        const newHsn = data.data.hsn_code;
        const parsedGst = Number(data.data.gst_rate);
        const newGst = Number.isFinite(parsedGst) ? parsedGst : 5.00;
        const newDesc = data.data.description || '';
        const existingHsn = HSN_OPTIONS.find(h => String(h.hsn_code).toUpperCase() === String(newHsn).toUpperCase());
        if (existingHsn) {
            existingHsn.description = newDesc;
            existingHsn.gst_rate = newGst;
        } else {
            HSN_OPTIONS.push({ hsn_code: newHsn, description: newDesc, gst_rate: newGst });
        }

        const sel = document.getElementById('qfHsnSelect');
        if (sel) {
            let existingOpt = Array.from(sel.options).find(o => o.value === newHsn);
            if (!existingOpt) {
                existingOpt = document.createElement('option');
                existingOpt.value = newHsn;
                sel.appendChild(existingOpt);
            }
            existingOpt.dataset.gst = newGst;
            existingOpt.textContent = `${newHsn}${newDesc ? ' • ' + newDesc : ''} (${newGst}%)`;
            sel.value = newHsn;
        }

        const qfGst = document.getElementById('qfGst');
        if (qfGst) qfGst.value = newGst.toFixed(2);

        addedProductsList.forEach(item => {
            const rowSel = document.getElementById(`hsn_${item.id}`);
            if (!rowSel) return;
            let opt = Array.from(rowSel.options).find(o => o.value === newHsn);
            if (!opt) {
                opt = document.createElement('option');
                opt.value = newHsn;
                rowSel.appendChild(opt);
            }
            opt.dataset.gst = newGst;
            opt.textContent = `${newHsn}${newDesc ? ' • ' + newDesc : ''} (${newGst}%)`;
        });
        showSupplierToast('HSN Saved', `${newHsn} is saved and ready to use.`, 'success');
    } catch (err) {
        alert('Error saving HSN code: ' + (err instanceof Error ? err.message : String(err)));
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

function onQfHsnSelect(val) {
    if (!val) return;
    const sel = document.getElementById('qfHsnSelect');
    const selectedOpt = sel ? sel.options[sel.selectedIndex] : null;
    if (selectedOpt && selectedOpt.dataset && selectedOpt.dataset.gst !== undefined) {
        const gstVal = parseFloat(selectedOpt.dataset.gst);
        const qfGst = document.getElementById('qfGst');
        if (qfGst && !isNaN(gstVal)) {
            qfGst.value = gstVal.toFixed(2);
        }
    }
}

// =========================================================================
// EDIT EXISTING SUPPLIER PRODUCT
// =========================================================================
let editSpModalInstance = null;
function updateSupplierProductRow(rowId, product, hasPendingChanges = false) {
    const row = document.getElementById(rowId);
    if (!row) return;

    const setText = (selector, key, format = value => String(value)) => {
        const element = row.querySelector(selector);
        if (element && product[key] !== undefined && product[key] !== null) {
            element.textContent = format(product[key]);
        }
    };

    setText('.col-sp-name', 'product_name');
    setText('.col-sp-sku', 'sku');
    setText('.col-sp-hsn', 'hsn_code', value => String(value) || '—');
    setText('.col-sp-variant', 'variant_name', value => String(value) || 'Standard');
    setText('.col-sp-weight', 'weight', value => (parseFloat(value) || 0).toFixed(3));
    setText('.col-sp-mrp', 'mrp', value => '₹' + (parseFloat(value) || 0).toFixed(2));
    setText('.col-sp-selling', 'selling_price', value => '₹' + (parseFloat(value) || 0).toFixed(2));
    setText('.col-sp-cost', 'purchase_price', value => '₹' + (parseFloat(value) || 0).toFixed(2));
    setText('.col-sp-stock', 'available_stock', value => String(parseInt(value, 10) || 0));
    row.classList.toggle('table-warning', hasPendingChanges);
}

async function savePendingSupplierProductEdits() {
    const updates = Array.from(pendingSupplierProductEdits.entries());
    for (const [editKey, editData] of updates) {
        const formData = new FormData();
        Object.entries(editData).forEach(([name, value]) => formData.append(name, value));
        formData.append('_csrf_token', '<?= CSRF::token() ?>');
        formData.append('csrf_token', '<?= CSRF::token() ?>');

        const response = await fetch('<?= url("warehouse/sellers/update-product") ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= CSRF::token() ?>'
            },
            body: formData
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to save supplier product changes.');
        }

        pendingSupplierProductEdits.delete(editKey);
        updateSupplierProductRow(editData.row_id, data.product || editData);
    }
}

function openEditSupplierProductModal(prod) {
    if (!prod) return;
    const editKey = String(prod.variant_id || prod.product_id || '');
    const pendingEdit = pendingSupplierProductEdits.get(editKey);
    if (pendingEdit) prod = { ...prod, ...pendingEdit };

    document.getElementById('editSpProductId').value = prod.product_id || 0;
    document.getElementById('editSpVariantId').value = prod.variant_id || 0;
    document.getElementById('editSpRowId').value = prod.row_id || '';
    document.getElementById('editSpProductName').value = prod.product_name || '';
    document.getElementById('editSpSku').value = prod.sku || '';
    document.getElementById('editSpHsnCode').value = prod.hsn_code || '';
    document.getElementById('editSpVariantName').value = prod.variant_name || '';
    document.getElementById('editSpWeight').value = prod.weight !== undefined ? parseFloat(prod.weight) : 0;
    document.getElementById('editSpMrp').value = prod.mrp !== undefined ? parseFloat(prod.mrp) : 0;
    document.getElementById('editSpSellingPrice').value = prod.selling_price !== undefined ? parseFloat(prod.selling_price) : 0;
    document.getElementById('editSpPurchasePrice').value = prod.purchase_price !== undefined ? parseFloat(prod.purchase_price) : 0;

    const stockInput = document.getElementById('editSpStock');
    const stockLockBadge = document.getElementById('editSpStockLockBadge');
    const stockLockNotice = document.getElementById('editSpStockLockNotice');

    stockInput.value = prod.available_stock !== undefined ? parseInt(prod.available_stock) : 0;
    stockInput.disabled = true;
    stockInput.classList.add('bg-light', 'text-muted');
    stockInput.classList.remove('text-primary');
    stockInput.title = 'Stock is read-only in supplier product editing.';
    if (stockLockBadge) stockLockBadge.classList.remove('d-none');
    if (stockLockNotice) stockLockNotice.classList.remove('d-none');

    const modalEl = document.getElementById('modalEditSupplierProduct');
    if (modalEl) {
        if (!editSpModalInstance) editSpModalInstance = new bootstrap.Modal(modalEl);
        editSpModalInstance.show();
    }
}

function submitEditSupplierProduct(e) {
    e.preventDefault();
    const form = document.getElementById('formEditSupplierProduct');
    if (!form.reportValidity()) return;

    const btn = document.getElementById('btnSubmitEditSupplierProduct');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Staging...';

    const formData = new FormData(form);
    const editData = {};
    formData.forEach((value, name) => {
        if (name !== 'csrf_token' && name !== '_csrf_token') {
            editData[name] = String(value);
        }
    });
    const editKey = String(editData.variant_id || editData.product_id || '');
    if (!editKey) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        showSupplierToast('Unable to stage changes', 'The product identifier is missing.', 'danger');
        return;
    }

    pendingSupplierProductEdits.set(editKey, editData);
    updateSupplierProductRow(editData.row_id, editData, true);
    if (editSpModalInstance) editSpModalInstance.hide();
    btn.disabled = false;
    btn.innerHTML = origHtml;
    updateFinalSaveButtonState();
    const saveAction = addedProductsList.length > 0 ? 'Save Products & Changes' : 'Save Product Changes';
    showSupplierToast('Changes staged', `Click the bottom "${saveAction}" button to apply the supplier product changes.`, 'info');
}
</script>

<!-- MODAL: EDIT EXISTING SUPPLIER PRODUCT -->
<div class="modal fade" id="modalEditSupplierProduct" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div style="background:#4f46e5;color:#fff;width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white">Edit Supplier Product &amp; Pricing</h5>
                        <small class="text-white-50">Update product name, SKU, HSN, weights, prices and inventory</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditSupplierProduct" onsubmit="submitEditSupplierProduct(event)">
                <?= CSRF::field() ?>
                <input type="hidden" id="editSpProductId" name="product_id">
                <input type="hidden" id="editSpVariantId" name="variant_id">
                <input type="hidden" id="editSpRowId" name="row_id">
                <div class="modal-body p-4 bg-light">
                    <div class="card border-0 shadow-xs rounded-3 bg-white mb-3">
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                <i class="bi bi-tag-fill text-primary me-1.5"></i>General Product &amp; Identification Details
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-8 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Product / Style Name <span class="text-danger">*</span></label>
                                    <input type="text" id="editSpProductName" name="product_name" class="form-control form-control-sm fw-semibold text-uppercase" placeholder="" required>
                                </div>
                                <div class="col-md-4 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">Variant (Spec / Size-Color)</label>
                                    <input type="text" id="editSpVariantName" name="variant_name" class="form-control form-control-sm text-uppercase" placeholder="">
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold text-dark mb-1">SKU / Item Code <span class="text-danger">*</span></label>
                                    <input type="text" id="editSpSku" name="sku" class="form-control form-control-sm font-monospace text-uppercase fw-bold" placeholder="" required>
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="form-label small fw-bold text-dark mb-1">HSN Code</label>
                                    <input type="text" id="editSpHsnCode" name="hsn_code" class="form-control form-control-sm font-monospace text-uppercase" placeholder="">
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="form-label small fw-bold text-dark mb-1">Unit Weight (kg)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="editSpWeight" name="weight" class="form-control form-control-sm text-end" step="0.001" min="0" placeholder="">
                                        <span class="input-group-text">kg</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-xs rounded-3 bg-white">
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                <i class="bi bi-currency-rupee text-success me-1.5"></i>Pricing &amp; Live Stock Levels
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-3 col-6">
                                    <label class="form-label small fw-bold text-dark mb-1">Supplier Cost (₹)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="editSpPurchasePrice" name="purchase_price" class="form-control form-control-sm text-end fw-bold" step="0.01" min="0" placeholder="">
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="form-label small fw-bold text-dark mb-1">Selling Price (₹)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="editSpSellingPrice" name="selling_price" class="form-control form-control-sm text-end fw-bold text-success" step="0.01" min="0" placeholder="">
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="form-label small fw-bold text-dark mb-1">MRP Price (₹)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" id="editSpMrp" name="mrp" class="form-control form-control-sm text-end fw-semibold" step="0.01" min="0" placeholder="">
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="form-label small fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                                        <span>Available Stock (Pcs)</span>
                                        <span id="editSpStockLockBadge" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small" style="font-size: 0.65rem;">
                                            <i class="bi bi-lock-fill"></i> Read-only
                                        </span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="editSpStock" name="available_stock" class="form-control form-control-sm text-center fw-bold bg-light text-muted" step="1" min="0" disabled>
                                        <span class="input-group-text">pcs</span>
                                    </div>
                                    <small id="editSpStockLockNotice" class="text-muted small mt-1" style="font-size: 0.72rem; line-height: 1.25;">
                                        <i class="bi bi-info-circle-fill me-1"></i>Update inventory through warehouse stock workflows.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="bi bi-info-circle me-1 text-primary"></i>Changes are staged here and saved with the bottom button.</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-semibold px-4 shadow-sm" id="btnSubmitEditSupplierProduct">
                            <i class="bi bi-check2-circle me-1"></i> Stage Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.fs-7 { font-size: 0.82rem !important; }
.fs-8 { font-size: 0.74rem !important; }
.btn-xs { padding: 0.15rem 0.4rem; font-size: 0.72rem; border-radius: 0.25rem; }
.cat-tile { transition: all 0.15s ease-in-out; }
.cat-tile:hover { transform: translateY(-1px); }
.attr-variant-chip, .attr-opt-chip, .matrix-combo-chip { transition: all 0.15s ease-in-out; }
.attr-variant-chip:hover, .attr-opt-chip:hover, .matrix-combo-chip:hover { transform: translateY(-1px); }
#addedProductsTable input { min-width: 60px; }
#addedProductsTable input.series-range-input { min-width: 36px !important; width: 100% !important; }
</style>
