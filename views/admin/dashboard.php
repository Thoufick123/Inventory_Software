<style>
    /* Custom Organization Tabs Styling */
    #orgTabs .nav-link { 
        color: #6c757d; 
        transition: all 0.2s;
    }
    #orgTabs .nav-link:hover { 
        background-color: rgba(0,0,0,0.05); 
    }
    
    #tab-prod-units.active { background-color: #0d6efd !important; color: white !important; border: none; }
    #tab-prod-units:not(.active) { color: #0d6efd; }
    
    #tab-warehouses.active { background-color: #cc8a26ff !important; color: white !important; border: none; }
    #tab-warehouses:not(.active) { color: #cc8a26ff; }
    
    #tab-shops.active { background-color: #0dcaf0 !important; color: white !important; border: none; }
    #tab-shops:not(.active) { color: #0dcaf0; }
    
    #tab-whatsapp.active { background-color: #198754 !important; color: white !important; border: none; }
    #tab-whatsapp:not(.active) { color: #198754; }
    
    #tab-website.active { background-color: #6f42c1 !important; color: white !important; border: none; }
    #tab-website:not(.active) { color: #6f42c1; }

    /* Custom color classes */
    .bg-warehouse { background-color: #cc8a26 !important; color: white !important; }
    .bg-warehouse-subtle { background-color: rgba(204, 138, 38, 0.15) !important; }
    .text-warehouse { color: #cc8a26 !important; }
    .border-warehouse { border-color: #cc8a26 !important; }
    .btn-warehouse { background-color: #cc8a26 !important; color: white !important; border-color: #cc8a26 !important; }
    .btn-warehouse:hover { background-color: #b57a22 !important; color: white !important; }

    .bg-website { background-color: #6f42c1 !important; color: white !important; }
    .bg-website-subtle { background-color: rgba(111, 66, 193, 0.15) !important; }
    .text-website { color: #6f42c1 !important; }
    .border-website { border-color: #6f42c1 !important; }
    .btn-website { background-color: #6f42c1 !important; color: white !important; border-color: #6f42c1 !important; }
    .btn-website:hover { background-color: #59339d !important; color: white !important; }
</style>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-speedometer2 me-2 text-primary"></i>Admin Dashboard</h2>
        </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-dark shadow-sm fw-semibold d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#qrStatusFinderModal"><i class="bi bi-qr-code-scan text-primary"></i><span>QR Status Finder</span></button>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. TOP HIGHLIGHT STATS -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <!-- Production Units -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card border-start border-primary border-4 p-3 shadow-sm h-100" style="cursor: pointer;" onclick="switchOrgTab('tab-prod-units')">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label text-uppercase fw-600 text-muted small">Production Facilities</div>
                    <div class="stat-value fw-700 text-primary mt-1"><?= number_format($totalProductionUnits ?? 0) ?> <span class="fs-6 fw-normal text-secondary">Units</span></div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-gear-wide-connected text-primary me-1"></i>Apparel Manufacturing
                    </div>
                </div>
                <div class="stat-icon icon-primary bg-primary-subtle text-primary p-3 rounded-circle">
                    <i class="bi bi-gear-wide-connected fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Warehouses -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card border-start border-warehouse border-4 p-3 shadow-sm h-100" style="cursor: pointer;" onclick="switchOrgTab('tab-warehouses')">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label text-uppercase fw-600 text-muted small">Central Warehouses</div>
                    <div class="stat-value fw-700 text-warehouse mt-1"><?= number_format($totalWarehouses ?? 0) ?> <span class="fs-6 fw-normal text-secondary">Hubs</span></div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-boxes text-warehouse me-1"></i><?= number_format(array_sum(array_column($warehouses ?? [], 'available_units'))) ?> Pcs
                    </div>
                </div>
                <div class="stat-icon bg-warehouse-subtle text-warehouse p-3 rounded-circle">
                    <i class="bi bi-buildings fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Retail Shops -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card border-start border-info border-4 p-3 shadow-sm h-100" style="cursor: pointer;" onclick="switchOrgTab('tab-shops')">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label text-uppercase fw-600 text-muted small">Retail Stores & POS</div>
                    <div class="stat-value fw-700 text-info mt-1"><?= number_format($totalShops ?? 0) ?> <span class="fs-6 fw-normal text-secondary">Stores</span></div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-cart-check text-info me-1"></i><?= formatCurrency(array_sum(array_column($shops ?? [], 'today_revenue'))) ?> Today
                    </div>
                </div>
                <div class="stat-icon icon-info bg-info-subtle text-info p-3 rounded-circle">
                    <i class="bi bi-shop fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- WhatsApp Channels -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card border-start border-success border-4 p-3 shadow-sm h-100" style="cursor: pointer;" onclick="switchOrgTab('tab-whatsapp')">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label text-uppercase fw-600 text-muted small">WhatsApp Channels</div>
                    <div class="stat-value fw-700 text-success mt-1"><?= number_format($totalWhatsapp ?? 0) ?> <span class="fs-6 fw-normal text-secondary">Channels</span></div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-whatsapp text-success me-1"></i>Order Channels
                    </div>
                </div>
                <div class="stat-icon icon-success bg-success-subtle text-success p-3 rounded-circle">
                    <i class="bi bi-whatsapp fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Website Channels -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card border-start border-website border-4 p-3 shadow-sm h-100" style="cursor: pointer;" onclick="switchOrgTab('tab-website')">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label text-uppercase fw-600 text-muted small">Website Channels</div>
                    <div class="stat-value fw-700 text-website mt-1"><?= number_format($totalWebsite ?? 0) ?> <span class="fs-6 fw-normal text-secondary">Channels</span></div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-globe text-website me-1"></i>E-Commerce
                    </div>
                </div>
                <div class="stat-icon bg-website-subtle text-website p-3 rounded-circle">
                    <i class="bi bi-globe fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Operational Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card p-3 border-0 shadow-sm bg-white rounded-3">
            <div class="text-muted small">Total Catalog SKUs</div>
            <div class="fs-4 fw-700 text-dark"><?= number_format($totalProducts ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 border-0 shadow-sm bg-white rounded-3">
            <div class="text-muted small">Total Stock Valuation</div>
            <div class="fs-4 fw-700 text-success"><?= formatCurrency($totalStockValue ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 border-0 shadow-sm bg-white rounded-3">
            <div class="text-muted small">Today's Invoices Count</div>
            <div class="fs-4 fw-700 text-primary"><?= number_format($todaySales ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 border-0 shadow-sm bg-white rounded-3">
            <div class="text-muted small">Low Stock Warnings</div>
            <div class="fs-4 fw-700 <?= ($lowStockCount ?? 0) > 0 ? 'text-danger' : 'text-secondary' ?>"><?= number_format($lowStockCount ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. ORGANIZATION UNITS COMMAND HUB -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
    <div class="card-header bg-white border-bottom p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-0 fw-700 text-dark"><i class="bi bi-diagram-3 me-2 text-primary"></i>Organization &amp; Infrastructure Management</h5>
            </div>
            <?php
            $currentTab = $_GET['tab'] ?? '';
            $activeTab = 'prod-units';
            if (in_array($currentTab, ['warehouses', 'warehouse'])) {
                $activeTab = 'warehouses';
            } elseif (in_array($currentTab, ['shops', 'shop', 'stores'])) {
                $activeTab = 'shops';
            } elseif (in_array($currentTab, ['whatsapp', 'wa'])) {
                $activeTab = 'whatsapp';
            } elseif (in_array($currentTab, ['website', 'web'])) {
                $activeTab = 'website';
            }
            ?>
            <ul class="nav nav-pills card-header-pills" id="orgTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'prod-units' ? 'active' : '' ?> fw-600 px-3 py-2" id="tab-prod-units" data-bs-toggle="pill" data-bs-target="#panel-prod-units" type="button" role="tab">
                        <i class="bi bi-gear-wide-connected me-1"></i> Production Units <span class="badge bg-primary ms-1"><?= count($productionUnits ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'warehouses' ? 'active' : '' ?> fw-600 px-3 py-2 text-warehouse" id="tab-warehouses" data-bs-toggle="pill" data-bs-target="#panel-warehouses" type="button" role="tab">
                        <i class="bi bi-buildings me-1"></i> Warehouses <span class="badge bg-warehouse ms-1"><?= count($warehouses ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'shops' ? 'active' : '' ?> fw-600 px-3 py-2" id="tab-shops" data-bs-toggle="pill" data-bs-target="#panel-shops" type="button" role="tab">
                        <i class="bi bi-shop me-1"></i> Retail Shops <span class="badge bg-info ms-1"><?= count($shops ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'whatsapp' ? 'active' : '' ?> fw-600 px-3 py-2 text-success" id="tab-whatsapp" data-bs-toggle="pill" data-bs-target="#panel-whatsapp" type="button" role="tab">
                        <i class="bi bi-whatsapp me-1"></i> WhatsApp <span class="badge bg-success ms-1"><?= $totalWhatsapp ?? 0 ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'website' ? 'active' : '' ?> fw-600 px-3 py-2 text-website" id="tab-website" data-bs-toggle="pill" data-bs-target="#panel-website" type="button" role="tab">
                        <i class="bi bi-globe me-1"></i> Website <span class="badge bg-website ms-1"><?= $totalWebsite ?? 0 ?></span>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="card-body p-0">
        <?php
        // Role-based permission controls for Organization & Infrastructure Management
        $canCreateFacilities = hasPermission('dashboard', 'create');
        $canEditFacilities   = hasPermission('dashboard', 'edit');
        $canDeleteFacilities = hasPermission('dashboard', 'delete');

        $canCreateUnit = $canCreateFacilities || hasPermission('production', 'create');
        $canEditUnit   = $canEditFacilities   || hasPermission('production', 'edit');
        $canDeleteUnit = $canDeleteFacilities || hasPermission('production', 'delete');

        $canCreateWarehouse = $canCreateFacilities || hasPermission('warehouse', 'create');
        $canEditWarehouse   = $canEditFacilities   || hasPermission('warehouse', 'edit');
        $canDeleteWarehouse = $canDeleteFacilities || hasPermission('warehouse', 'delete');

        $canCreateShop = $canCreateFacilities || hasPermission('shop', 'create');
        $canEditShop   = $canEditFacilities   || hasPermission('shop', 'edit');
        $canDeleteShop = $canDeleteFacilities || hasPermission('shop', 'delete');

        $canCreateChannel = $canCreateFacilities || hasPermission('sales_channels', 'create') || hasPermission('whatsapp_dashboard', 'create');
        $canEditChannel   = $canEditFacilities   || hasPermission('sales_channels', 'edit')   || hasPermission('whatsapp_dashboard', 'edit');
        $canDeleteChannel = $canDeleteFacilities || hasPermission('sales_channels', 'delete') || hasPermission('whatsapp_dashboard', 'delete');
        ?>
        <div class="tab-content" id="orgTabsContent">
            
            <!-- ========================================================================= -->
            <!-- TAB 1: PRODUCTION UNITS -->
            <!-- ========================================================================= -->
            <div class="tab-pane fade <?= $activeTab === 'prod-units' ? 'show active' : '' ?>" id="panel-prod-units" role="tabpanel">
                <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="text-secondary small">
                        <strong><?= count($productionUnits ?? []) ?></strong> production facilities configured for cutting, stitching, and assembly.
                    </div>
                    <?php if ($canCreateUnit): ?>
                    <button class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProductionUnit" onclick="resetUnitModal()">
                        <i class="bi bi-plus-lg me-1"></i> Add Production Unit
                    </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase small text-muted">
                            <tr>
                                <th class="ps-3">Unit Details</th>
                                <th>Manager & Contact</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($productionUnits)): ?>
                                <?php foreach ($productionUnits as $unit): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary-subtle text-primary font-monospace fw-bold px-2 py-1"><?= e($unit['code']) ?></span>
                                            <div>
                                                <div class="fw-bold text-dark"><?= e($unit['name']) ?></div>
                                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($unit['address'] ?: 'No address specified') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-600 text-dark"><?= e($unit['manager_name'] ?: 'Unassigned') ?></div>
                                        <small class="text-muted"><?= e($unit['phone'] ?: '-') ?></small>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" title="View Unit Details"
                                                    onclick="viewFacilityDetails('unit', <?= htmlspecialchars(json_encode($unit), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" title="Edit Unit" 
                                                    onclick="editUnit(<?= htmlspecialchars(json_encode($unit), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Delete Production Unit"
                                                    onclick="confirmDeleteFacility('unit', <?= (int)$unit['id'] ?>, '<?= addslashes(e($unit['name'])) ?>', '<?= url('admin/production-units/delete/' . $unit['id']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        <i class="bi bi-gear-wide-connected fs-2 d-block mb-2 text-secondary"></i>
                                        No production units registered yet. Click "Add Production Unit" to create one.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 2: WAREHOUSES -->
            <!-- ========================================================================= -->
            <div class="tab-pane fade <?= $activeTab === 'warehouses' ? 'show active' : '' ?>" id="panel-warehouses" role="tabpanel">
                <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="text-secondary small">
                        <strong><?= count($warehouses ?? []) ?></strong> storage &amp; distribution warehouses holding central stock.
                    </div>
                    <button class="btn btn-sm btn-warehouse shadow-sm" data-bs-toggle="modal" data-bs-target="#modalWarehouse" onclick="resetWarehouseModal()">
                        <i class="bi bi-plus-lg me-1"></i> Add Warehouse
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase small text-muted">
                            <tr>
                                <th class="ps-3">Warehouse Details</th>
                                <th>Manager & Contact</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($warehouses)): ?>
                                <?php foreach ($warehouses as $wh): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-success-subtle text-success font-monospace fw-bold px-2 py-1"><?= e($wh['code']) ?></span>
                                            <div>
                                                <div class="fw-bold text-dark"><?= e($wh['name']) ?></div>
                                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($wh['address'] ?: 'No address specified') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-600 text-dark"><?= e($wh['manager_name'] ?: 'Unassigned') ?></div>
                                        <small class="text-muted"><?= e($wh['phone'] ?: '-') ?></small>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" title="View Warehouse Details"
                                                    onclick="viewFacilityDetails('warehouse', <?= htmlspecialchars(json_encode($wh), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" title="Edit Warehouse" 
                                                    onclick="editWarehouse(<?= htmlspecialchars(json_encode($wh), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Delete Warehouse"
                                                    onclick="confirmDeleteFacility('warehouse', <?= (int)$wh['id'] ?>, '<?= addslashes(e($wh['name'])) ?>', '<?= url('admin/warehouses/delete/' . $wh['id']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        <i class="bi bi-buildings fs-2 d-block mb-2 text-secondary"></i>
                                        No warehouses registered. Click "Add Warehouse" to create one.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 3: RETAIL SHOPS -->
            <!-- ========================================================================= -->
            <div class="tab-pane fade <?= $activeTab === 'shops' ? 'show active' : '' ?>" id="panel-shops" role="tabpanel">
                <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="text-secondary small">
                        <strong><?= count($shops ?? []) ?></strong> retail store counters connected for real-time POS billing.
                    </div>
                    <button class="btn btn-sm btn-info shadow-sm text-white" data-bs-toggle="modal" data-bs-target="#modalShop" onclick="resetShopModal()">
                        <i class="bi bi-plus-lg me-1"></i> Add Retail Shop
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase small text-muted">
                            <tr>
                                <th class="ps-3">Shop Details</th>
                                <th>Manager & Contact</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($shops)): ?>
                                <?php foreach ($shops as $shop): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-info-subtle text-info font-monospace fw-bold px-2 py-1"><?= e($shop['code']) ?></span>
                                            <div>
                                                <div class="fw-bold text-dark"><?= e($shop['name']) ?></div>
                                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($shop['address'] ?: 'No address specified') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-600 text-dark"><?= e($shop['manager_name'] ?: 'Unassigned') ?></div>
                                        <small class="text-muted"><?= e($shop['phone'] ?: '-') ?></small>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" title="View Shop Details"
                                                    onclick="viewFacilityDetails('shop', <?= htmlspecialchars(json_encode($shop), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" title="Edit Shop" 
                                                    onclick="editShop(<?= htmlspecialchars(json_encode($shop), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Delete Shop"
                                                    onclick="confirmDeleteFacility('shop', <?= (int)$shop['id'] ?>, '<?= addslashes(e($shop['name'])) ?>', '<?= url('admin/shops/delete/' . $shop['id']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        <i class="bi bi-shop fs-2 d-block mb-2 text-secondary"></i>
                                        No retail shops registered yet. Click "Add Retail Shop" to create one.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 4: WHATSAPP CHANNELS -->
            <!-- ========================================================================= -->
            <div class="tab-pane fade <?= $activeTab === 'whatsapp' ? 'show active' : '' ?>" id="panel-whatsapp" role="tabpanel">
                <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="text-secondary small">
                        <strong><?= count($whatsappChannels ?? []) ?></strong> WhatsApp sales channels configured.
                    </div>
                    <button class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalChannel" onclick="resetChannelModal('WHATSAPP')">
                        <i class="bi bi-plus-lg me-1"></i> Add WhatsApp Channel
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase small text-muted">
                            <tr>
                                <th class="ps-3">Channel Details</th>
                                <th>Manager & Contact</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($whatsappChannels)): ?>
                                <?php foreach ($whatsappChannels as $chan): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-success-subtle text-success font-monospace fw-bold px-2 py-1"><?= e($chan['code']) ?></span>
                                            <div class="fw-bold text-dark"><?= e($chan['name']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-600 text-dark"><?= e($chan['manager_name'] ?: 'Unassigned') ?></div>
                                        <small class="text-muted"><?= e($chan['phone'] ?: '-') ?></small>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" title="View Channel Details"
                                                    onclick="viewFacilityDetails('whatsapp', <?= htmlspecialchars(json_encode($chan), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" title="Edit Channel" onclick="editChannel(<?= htmlspecialchars(json_encode($chan), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Delete Channel"
                                                    onclick="confirmDeleteFacility('whatsapp', <?= (int)$chan['id'] ?>, '<?= addslashes(e($chan['name'])) ?>', '<?= url('admin/channels/delete/' . $chan['id']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No WhatsApp channels added yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 5: WEBSITE CHANNELS -->
            <!-- ========================================================================= -->
            <div class="tab-pane fade <?= $activeTab === 'website' ? 'show active' : '' ?>" id="panel-website" role="tabpanel">
                <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="text-secondary small">
                        <strong><?= count($websiteChannels ?? []) ?></strong> Website sales channels configured.
                    </div>
                    <button class="btn btn-sm btn-website shadow-sm" data-bs-toggle="modal" data-bs-target="#modalChannel" onclick="resetChannelModal('WEBSITE')">
                        <i class="bi bi-plus-lg me-1"></i> Add Website Channel
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase small text-muted">
                            <tr>
                                <th class="ps-3">Channel Details</th>
                                <th>Manager & Contact</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($websiteChannels)): ?>
                                <?php foreach ($websiteChannels as $chan): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary-subtle text-primary font-monospace fw-bold px-2 py-1"><?= e($chan['code']) ?></span>
                                            <div class="fw-bold text-dark"><?= e($chan['name']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-600 text-dark"><?= e($chan['manager_name'] ?: 'Unassigned') ?></div>
                                        <small class="text-muted"><?= e($chan['phone'] ?: '-') ?></small>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" title="View Channel Details"
                                                    onclick="viewFacilityDetails('website', <?= htmlspecialchars(json_encode($chan), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" title="Edit Channel" onclick="editChannel(<?= htmlspecialchars(json_encode($chan), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Delete Channel"
                                                    onclick="confirmDeleteFacility('website', <?= (int)$chan['id'] ?>, '<?= addslashes(e($chan['name'])) ?>', '<?= url('admin/channels/delete/' . $chan['id']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No Website channels added yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. INVENTORY ALERTS -->
<!-- ========================================================================= -->
<div class="row g-3">
    <!-- Low Stock Alerts -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-700 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Warnings</h6>
                <span class="badge bg-danger"><?= count($lowStockItems ?? []) ?></span>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($lowStockItems)): ?>
                    <div class="row g-2">
                        <?php foreach (array_slice($lowStockItems, 0, 8) as $item): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="p-2 border rounded d-flex justify-content-between align-items-center bg-light">
                                <div class="overflow-hidden me-2">
                                    <div class="fw-bold text-dark small text-truncate"><?= e($item['product_name']) ?></div>
                                    <span class="font-monospace text-muted small"><?= e($item['sku']) ?></span>
                                </div>
                                <span class="badge bg-danger-subtle text-danger px-2 py-1 font-monospace fw-bold flex-shrink-0">
                                    <?= number_format($item['quantity']) ?> left
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-check-circle text-success fs-3 d-block mb-1"></i>
                        <small>All inventory items are currently above threshold.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3.5. LIVE STAFF ACTIVITY & ENTERPRISE AUDIT TRAIL -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 mt-4 mb-4">
    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h6 class="mb-0 fw-700 text-dark">
                <i class="bi bi-shield-check text-primary me-2"></i>Live Staff Activity &amp; System Change Logs
            </h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('system/audit') ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                <i class="bi bi-journal-text me-1"></i> View Full Audit Trail (<?= number_format($auditStats['total'] ?? 0) ?>)
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th class="ps-3">Timestamp</th>
                    <th>Staff / User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Activity Detail</th>
                    <th class="text-end pe-3">Audit Diff</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentAuditLogs)): ?>
                    <?php foreach ($recentAuditLogs as $log): ?>
                        <?php
                        $actionUpper = strtoupper($log['action']);
                        $badgeClass = 'bg-secondary-subtle text-secondary';
                        if (in_array($actionUpper, ['CREATE', 'STORE', 'ADD'])) $badgeClass = 'bg-success-subtle text-success';
                        elseif (in_array($actionUpper, ['UPDATE', 'EDIT', 'MODIFY', 'STATUS_CHANGE', 'TOGGLE'])) $badgeClass = 'bg-warning-subtle text-warning-emphasis';
                        elseif (in_array($actionUpper, ['DELETE', 'REMOVE', 'PURGE'])) $badgeClass = 'bg-danger-subtle text-danger';
                        elseif (in_array($actionUpper, ['LOGIN', 'RESTORE', 'IMPORT'])) $badgeClass = 'bg-info-subtle text-info-emphasis';
                        
                        $staffName = e($log['user_name'] ?? 'System');
                        $moduleTitle = ucfirst(e($log['module']));
                        ?>
                        <tr>
                            <td class="ps-3 text-nowrap">
                                <div class="fw-semibold text-dark small"><?= formatDateTime($log['created_at']) ?></div>
                                <small class="text-muted"><?= date('h:i A', strtotime($log['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm rounded-circle bg-primary-subtle text-primary fw-bold me-2 d-flex align-items-center justify-content-center" style="width:30px; height:30px; font-size:11px; flex-shrink:0;">
                                        <?= strtoupper(substr($staffName, 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark small"><?= $staffName ?></div>
                                        <div class="text-muted" style="font-size: 11px;">@<?= e($log['username'] ?? 'system') ?> &bull; <?= e($log['role_name'] ?? 'Staff') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border rounded-pill px-2 py-0.5 text-uppercase fw-semibold" style="font-size: 0.7rem;"><?= $moduleTitle ?></span></td>
                            <td><span class="badge <?= $badgeClass ?> rounded-pill px-2 py-0.5 text-uppercase fw-bold" style="font-size: 0.68rem;"><?= e($log['action']) ?></span></td>
                            <td>
                                <span class="small text-dark"><?= $staffName ?> performed <?= strtolower($log['action']) ?> in <?= $moduleTitle ?></span>
                                <?php if ($log['reference_type']): ?>
                                    <small class="text-muted d-block font-monospace">(<?= e($log['reference_type']) ?> #<?= e($log['reference_id']) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if ($log['previous_value'] || $log['new_value']): ?>
                                    <a href="<?= url('system/audit?search=' . urlencode($log['user_name'] ?? $log['module'])) ?>" class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 small">
                                        <i class="bi bi-eye text-primary me-1"></i> View Diff
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-shield-check fs-2 d-block mb-1 text-secondary opacity-50"></i>
                            No activity logs recorded yet. All changes performed by staff will appear here.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 4. MODALS FOR PRODUCTION UNIT, WAREHOUSE, AND SHOP -->
<!-- ========================================================================= -->

<!-- Modal: Production Unit (Add & Edit) -->
<div class="modal fade" id="modalProductionUnit" tabindex="-1" aria-labelledby="modalProductionUnitLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" id="formProductionUnit" action="<?= url('admin/production-units/store') ?>" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-700" id="modalProductionUnitLabel"><i class="bi bi-gear-wide-connected me-2"></i>Add Production Unit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-600">Unit Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="unit_name" class="form-control" placeholder="e.g. Tirupur Main Unit 1" required>
                        <div class="invalid-feedback">Unit Name is required.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-600">Unit Code</label>
                            <input type="text" name="code" id="unit_code" class="form-control font-monospace" placeholder="Auto-generated e.g. PU-01">
                            <small class="text-muted">Leave blank for auto-generation</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600">Unit Manager</label>
                            <input type="text" name="manager_name" id="unit_manager_name" class="form-control" placeholder="e.g. Ramesh Kumar" pattern="[A-Za-z\s]+" title="Only alphabetic characters and spaces are allowed" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            <div class="invalid-feedback">Please enter a valid manager name.</div>
                            <small class="text-muted">Alphabets only</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Contact Phone</label>
                        <input type="tel" name="phone" id="unit_phone" class="form-control" placeholder="10-digit number (e.g. 9876543210)" maxlength="10" pattern="[0-9]{10}" title="Must be exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
                        <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                        <small class="text-muted">10-digit mobile number</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">GST Number</label>
                        <input type="text" name="gst_number" id="unit_gst_number" class="form-control font-monospace text-uppercase" placeholder="33AAAAA0000A1Z5" maxlength="15" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]" title="Enter a valid 15-character GSTIN" oninput="this.value = this.value.toUpperCase()">
                        <div class="invalid-feedback">Enter a valid 15-character GSTIN.</div>
                        <small class="text-muted">Optional 15-character GSTIN</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Facility Address</label>
                        <textarea name="address" id="unit_address" class="form-control" rows="2" placeholder="Full address of the production unit..."></textarea>
                    </div>
                    <input type="hidden" name="is_active" value="1">
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-600" id="btnSubmitUnit">Save Production Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Warehouse (Add & Edit) -->
<div class="modal fade" id="modalWarehouse" tabindex="-1" aria-labelledby="modalWarehouseLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" id="formWarehouse" action="<?= url('admin/warehouses/store') ?>" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-700" id="modalWarehouseLabel"><i class="bi bi-buildings me-2"></i>Add Warehouse</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-600">Warehouse Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="wh_name" class="form-control" placeholder="e.g. Central Finished Goods Warehouse" required>
                        <div class="invalid-feedback">Warehouse Name is required.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-600">Warehouse Code</label>
                            <input type="text" name="code" id="wh_code" class="form-control font-monospace" placeholder="Auto-generated e.g. WH-01">
                            <small class="text-muted">Leave blank for auto-generation</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600">Warehouse Manager</label>
                            <input type="text" name="manager_name" id="wh_manager_name" class="form-control" placeholder="e.g. Suresh Patel" pattern="[A-Za-z\s]+" title="Only alphabetic characters and spaces are allowed" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            <div class="invalid-feedback">Please enter a valid manager name.</div>
                            <small class="text-muted">Alphabets only</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Contact Phone</label>
                        <input type="tel" name="phone" id="wh_phone" class="form-control" placeholder="10-digit number (e.g. 9876543210)" maxlength="10" pattern="[0-9]{10}" title="Must be exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
                        <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                        <small class="text-muted">10-digit mobile number</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">GST Number</label>
                        <input type="text" name="gst_number" id="wh_gst_number" class="form-control font-monospace text-uppercase" placeholder="33AAAAA0000A1Z5" maxlength="15" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]" title="Enter a valid 15-character GSTIN" oninput="this.value = this.value.toUpperCase()">
                        <div class="invalid-feedback">Enter a valid 15-character GSTIN.</div>
                        <small class="text-muted">Optional 15-character GSTIN</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Warehouse Address</label>
                        <textarea name="address" id="wh_address" class="form-control" rows="2" placeholder="Full address of the warehouse facility..."></textarea>
                    </div>
                    <input type="hidden" name="is_active" value="1">
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-600" id="btnSubmitWarehouse">Save Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Shop (Add & Edit) -->
<div class="modal fade" id="modalShop" tabindex="-1" aria-labelledby="modalShopLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" id="formShop" action="<?= url('admin/shops/store') ?>" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-700" id="modalShopLabel"><i class="bi bi-shop me-2"></i>Add Retail Shop</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-600">Shop / Store Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="shop_name" class="form-control" placeholder="e.g. Flagship City Store" required>
                        <div class="invalid-feedback">Shop Name is required.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-600">Shop Code</label>
                            <input type="text" name="code" id="shop_code" class="form-control font-monospace" placeholder="Auto-generated e.g. SH-01">
                            <small class="text-muted">Leave blank for auto-generation</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600">Store Manager</label>
                            <input type="text" name="manager_name" id="shop_manager_name" class="form-control" placeholder="e.g. Anitha Devi" pattern="[A-Za-z\s]+" title="Only alphabetic characters and spaces are allowed" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            <div class="invalid-feedback">Please enter a valid manager name.</div>
                            <small class="text-muted">Alphabets only</small>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-600">GST Number</label>
                            <input type="text" name="gst_number" id="shop_gst_number" class="form-control font-monospace text-uppercase" placeholder="33AAAAA0000A1Z5" maxlength="15" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]" title="Enter a valid 15-character GSTIN" oninput="this.value = this.value.toUpperCase()">
                            <div class="invalid-feedback">Enter a valid 15-character GSTIN.</div>
                            <small class="text-muted">Optional 15-character GSTIN</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600">Contact Phone</label>
                            <input type="tel" name="phone" id="shop_phone" class="form-control" placeholder="10-digit number (e.g. 9876543210)" maxlength="10" pattern="[0-9]{10}" title="Must be exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
                            <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                            <small class="text-muted">10-digit mobile number</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Store Address</label>
                        <textarea name="address" id="shop_address" class="form-control" rows="2" placeholder="Full physical address of the retail outlet..."></textarea>
                    </div>
                    <input type="hidden" name="is_active" value="1">
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white fw-600" id="btnSubmitShop">Save Retail Shop</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Channel (Add & Edit) -->
<div class="modal fade" id="modalChannel" tabindex="-1" aria-labelledby="modalChannelLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form id="formChannel" method="POST" action="<?= url('admin/channels/store') ?>" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="channel_type" id="channel_type" value="">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-700" id="modalChannelLabel"><i class="bi bi-broadcast me-2"></i>Add Channel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-600">Channel Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="chan_name" class="form-control" placeholder="e.g. WhatsApp Main" required>
                        <div class="invalid-feedback">Channel Name is required.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-600">Channel Code</label>
                            <input type="text" name="code" id="chan_code" class="form-control font-monospace" placeholder="Auto-generated e.g. WA-01">
                            <small class="text-muted">Leave blank for auto-generation</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600">Manager Name</label>
                            <input type="text" name="manager_name" id="chan_manager_name" class="form-control" placeholder="e.g. Rahul" pattern="[A-Za-z\s]+" title="Only alphabetic characters and spaces are allowed" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            <div class="invalid-feedback">Please enter a valid manager name.</div>
                            <small class="text-muted">Alphabets only</small>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-600">Contact Phone</label>
                            <input type="tel" name="phone" id="chan_phone" class="form-control" placeholder="10-digit number" maxlength="10" pattern="[0-9]{10}" title="Must be exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
                            <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600">GST Number</label>
                            <input type="text" name="gst_number" id="chan_gst_number" class="form-control font-monospace text-uppercase" placeholder="33AAAAA0000A1Z5" maxlength="15" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]" title="Enter a valid 15-character GSTIN" oninput="this.value = this.value.toUpperCase()">
                            <div class="invalid-feedback">Enter a valid 15-character GSTIN.</div>
                            <small class="text-muted">Optional 15-character GSTIN</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Description</label>
                        <textarea name="description" id="chan_description" class="form-control" rows="2" placeholder="Optional details..."></textarea>
                    </div>
                    <input type="hidden" name="is_active" value="1">
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark text-white fw-600" id="btnSubmitChannel">Save Channel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: VIEW FACILITY / SAVED INFRASTRUCTURE DETAILS -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalFacilityDetails" tabindex="-1" aria-labelledby="modalFacilityDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" id="modalFacilityDetailsHeader" style="background: #0d6efd;">
                <div class="d-flex align-items-center gap-2">
                    <span id="facilityHeaderIcon" class="fs-4"><i class="bi bi-info-circle"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="facilityDetailTitle">Facility Details</h5>
                        <div class="small opacity-75 text-white" id="facilityDetailSubtitle">Saved Configuration &amp; Profile</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="text-muted small fw-semibold text-uppercase mb-2"><i class="bi bi-card-text me-1"></i>Basic Information</div>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Facility / Unit Name:</span>
                                <span class="fw-bold text-dark fs-6" id="facilityDetailName">-</span>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Facility Code:</span>
                                <span class="badge bg-secondary-subtle text-secondary font-monospace" id="facilityDetailCode">-</span>
                            </div>
                            <div>
                                <span class="text-muted small d-block">GST / Reg Number:</span>
                                <span class="fw-semibold text-dark font-monospace" id="facilityDetailGst">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="text-muted small fw-semibold text-uppercase mb-2"><i class="bi bi-person-lines-fill me-1"></i>Contact &amp; Location</div>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Manager / In-charge:</span>
                                <span class="fw-bold text-dark" id="facilityDetailManager">-</span>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Phone / Contact:</span>
                                <span class="fw-semibold text-dark font-monospace" id="facilityDetailPhone">-</span>
                            </div>
                            <div>
                                <span class="text-muted small d-block">Address / Location:</span>
                                <span class="text-dark small" id="facilityDetailAddress">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Operational Metrics Card -->
                <div class="card border border-primary-subtle bg-light mb-1">
                    <div class="card-body p-3">
                        <div class="text-muted small fw-bold text-uppercase mb-2" id="facilityMetricsTitle"><i class="bi bi-graph-up me-1"></i>Operational Metrics</div>
                        <div class="row g-2 text-center" id="facilityMetricsContainer">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-end">
                <button type="button" class="btn btn-secondary px-4 fw-600" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 5. JAVASCRIPT FOR DYNAMIC MODALS & TAB NAVIGATION -->
<!-- ========================================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap Inline Validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Handle Active Tab State & Persistence across Refreshes
    const tabMap = {
        'production-units': 'tab-prod-units',
        'units': 'tab-prod-units',
        'prod-units': 'tab-prod-units',
        'warehouses': 'tab-warehouses',
        'warehouse': 'tab-warehouses',
        'shops': 'tab-shops',
        'shop': 'tab-shops',
        'whatsapp': 'tab-whatsapp',
        'wa': 'tab-whatsapp',
        'website': 'tab-website',
        'web': 'tab-website'
    };

    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    const savedLocalTab = localStorage.getItem('admin_org_active_tab');

    if (tabParam && tabMap[tabParam]) {
        switchOrgTab(tabMap[tabParam], false);
    } else if (savedLocalTab && document.getElementById(savedLocalTab)) {
        switchOrgTab(savedLocalTab, false);
    }

    // Persist active tab when user clicks any tab button
    const tabButtons = document.querySelectorAll('#orgTabs button[data-bs-toggle="pill"]');
    tabButtons.forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function(e) {
            const btnId = e.target.id;
            localStorage.setItem('admin_org_active_tab', btnId);
            const key = btnId.replace('tab-', '');
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', key);
            window.history.replaceState({}, '', newUrl);
        });
    });
});

function switchOrgTab(tabId, scroll = true) {
    const triggerEl = document.getElementById(tabId);
    if (triggerEl) {
        const tab = bootstrap.Tab.getOrCreateInstance(triggerEl);
        tab.show();
        localStorage.setItem('admin_org_active_tab', tabId);
        const key = tabId.replace('tab-', '');
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('tab', key);
        window.history.replaceState({}, '', newUrl);
        if (scroll) {
            triggerEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

// Facility Code Auto-generation Lists
const existingUnitCodes = <?= json_encode(array_values(array_filter(array_column($productionUnits ?? [], 'code')))) ?>;
const existingWhCodes = <?= json_encode(array_values(array_filter(array_column($warehouses ?? [], 'code')))) ?>;
const existingShopCodes = <?= json_encode(array_values(array_filter(array_column($shops ?? [], 'code')))) ?>;
const existingChannels = <?= json_encode(array_values(array_filter(array_column($channels ?? [], 'code')))) ?>;

function getNextSequentialCode(prefix, existingList) {
    let num = 1;
    while (existingList.some(c => c && c.toUpperCase() === (prefix + '-' + String(num).padStart(2, '0')).toUpperCase())) {
        num++;
    }
    return prefix + '-' + String(num).padStart(2, '0');
}

// Production Unit Modal Helpers
function resetUnitModal() {
    document.getElementById('formProductionUnit').classList.remove('was-validated');
    document.getElementById('modalProductionUnitLabel').innerHTML = '<i class="bi bi-gear-wide-connected me-2"></i>Add Production Unit';
    document.getElementById('formProductionUnit').action = '<?= url("admin/production-units/store") ?>';
    document.getElementById('unit_name').value = '';
    const autoCode = getNextSequentialCode('PU', existingUnitCodes);
    document.getElementById('unit_code').value = autoCode;
    document.getElementById('unit_code').placeholder = autoCode;
    document.getElementById('unit_manager_name').value = '';
    document.getElementById('unit_phone').value = '';
    document.getElementById('unit_gst_number').value = '';
    document.getElementById('unit_address').value = '';
    document.getElementById('btnSubmitUnit').textContent = 'Save Production Unit';
}

function editUnit(unit) {
    document.getElementById('formProductionUnit').classList.remove('was-validated');
    document.getElementById('modalProductionUnitLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Production Unit';
    document.getElementById('formProductionUnit').action = '<?= url("admin/production-units/update/") ?>' + unit.id;
    document.getElementById('unit_name').value = unit.name || '';
    document.getElementById('unit_code').value = unit.code || getNextSequentialCode('PU', existingUnitCodes);
    document.getElementById('unit_manager_name').value = unit.manager_name || '';
    document.getElementById('unit_phone').value = unit.phone || '';
    document.getElementById('unit_gst_number').value = unit.gst_number || '';
    document.getElementById('unit_address').value = unit.address || '';
    document.getElementById('btnSubmitUnit').textContent = 'Update Production Unit';
    const modal = new bootstrap.Modal(document.getElementById('modalProductionUnit'));
    modal.show();
}

// Warehouse Modal Helpers
function resetWarehouseModal() {
    document.getElementById('formWarehouse').classList.remove('was-validated');
    document.getElementById('modalWarehouseLabel').innerHTML = '<i class="bi bi-buildings me-2"></i>Add Warehouse';
    document.getElementById('formWarehouse').action = '<?= url("admin/warehouses/store") ?>';
    document.getElementById('wh_name').value = '';
    const autoCode = getNextSequentialCode('WH', existingWhCodes);
    document.getElementById('wh_code').value = autoCode;
    document.getElementById('wh_code').placeholder = autoCode;
    document.getElementById('wh_manager_name').value = '';
    document.getElementById('wh_phone').value = '';
    document.getElementById('wh_gst_number').value = '';
    document.getElementById('wh_address').value = '';
    document.getElementById('btnSubmitWarehouse').textContent = 'Save Warehouse';
}

function editWarehouse(wh) {
    document.getElementById('formWarehouse').classList.remove('was-validated');
    document.getElementById('modalWarehouseLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Warehouse';
    document.getElementById('formWarehouse').action = '<?= url("admin/warehouses/update/") ?>' + wh.id;
    document.getElementById('wh_name').value = wh.name || '';
    document.getElementById('wh_code').value = wh.code || getNextSequentialCode('WH', existingWhCodes);
    document.getElementById('wh_manager_name').value = wh.manager_name || '';
    document.getElementById('wh_phone').value = wh.phone || '';
    document.getElementById('wh_gst_number').value = wh.gst_number || '';
    document.getElementById('wh_address').value = wh.address || '';
    document.getElementById('btnSubmitWarehouse').textContent = 'Update Warehouse';
    const modal = new bootstrap.Modal(document.getElementById('modalWarehouse'));
    modal.show();
}

// Shop Modal Helpers
function resetShopModal() {
    document.getElementById('formShop').classList.remove('was-validated');
    document.getElementById('modalShopLabel').innerHTML = '<i class="bi bi-shop me-2"></i>Add Retail Shop';
    document.getElementById('formShop').action = '<?= url("admin/shops/store") ?>';
    document.getElementById('shop_name').value = '';
    const autoCode = getNextSequentialCode('SH', existingShopCodes);
    document.getElementById('shop_code').value = autoCode;
    document.getElementById('shop_code').placeholder = autoCode;
    document.getElementById('shop_manager_name').value = '';
    document.getElementById('shop_gst_number').value = '';
    document.getElementById('shop_phone').value = '';
    document.getElementById('shop_address').value = '';
    document.getElementById('btnSubmitShop').textContent = 'Save Retail Shop';
}

function editShop(shop) {
    document.getElementById('formShop').classList.remove('was-validated');
    document.getElementById('modalShopLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Retail Shop';
    document.getElementById('formShop').action = '<?= url("admin/shops/update/") ?>' + shop.id;
    document.getElementById('shop_name').value = shop.name || '';
    document.getElementById('shop_code').value = shop.code || getNextSequentialCode('SH', existingShopCodes);
    document.getElementById('shop_manager_name').value = shop.manager_name || '';
    document.getElementById('shop_gst_number').value = shop.gst_number || '';
    document.getElementById('shop_phone').value = shop.phone || '';
    document.getElementById('shop_address').value = shop.address || '';
    document.getElementById('btnSubmitShop').textContent = 'Update Retail Shop';
    const modal = new bootstrap.Modal(document.getElementById('modalShop'));
    modal.show();
}

// Channel Modal Helpers
function resetChannelModal(type) {
    document.getElementById('formChannel').classList.remove('was-validated');
    const isWhatsapp = type === 'WHATSAPP';
    const title = isWhatsapp ? 'WhatsApp Channel' : 'Website Channel';
    const icon = isWhatsapp ? 'bi-whatsapp' : 'bi-globe';
    const bgClass = isWhatsapp ? 'bg-success' : 'bg-website';
    const prefix = isWhatsapp ? 'WA' : 'WE';
    const autoCode = getNextSequentialCode(prefix, existingChannels);
    
    document.getElementById('modalChannelLabel').innerHTML = `<i class="bi ${icon} me-2"></i>Add ${title}`;
    const header = document.querySelector('#modalChannel .modal-header');
    header.className = `modal-header ${bgClass} text-white`;
    
    document.getElementById('formChannel').action = '<?= url("admin/channels/store") ?>';
    document.getElementById('channel_type').value = type;
    document.getElementById('chan_name').value = '';
    document.getElementById('chan_code').value = autoCode;
    document.getElementById('chan_code').placeholder = autoCode;
    document.getElementById('chan_manager_name').value = '';
    document.getElementById('chan_phone').value = '';
    document.getElementById('chan_gst_number').value = '';
    document.getElementById('chan_description').value = '';
    document.getElementById('btnSubmitChannel').className = `btn ${bgClass} text-white fw-600`;
    document.getElementById('btnSubmitChannel').textContent = `Save ${title}`;
}

function editChannel(chan) {
    document.getElementById('formChannel').classList.remove('was-validated');
    const isWhatsapp = chan.channel_type === 'WHATSAPP';
    const title = isWhatsapp ? 'WhatsApp Channel' : 'Website Channel';
    const icon = isWhatsapp ? 'bi-whatsapp' : 'bi-globe';
    const bgClass = isWhatsapp ? 'bg-success' : 'bg-website';
    
    document.getElementById('modalChannelLabel').innerHTML = `<i class="bi ${icon} me-2"></i>Edit ${title}`;
    const header = document.querySelector('#modalChannel .modal-header');
    header.className = `modal-header ${bgClass} text-white`;
    
    document.getElementById('formChannel').action = '<?= url("admin/channels/update/") ?>' + chan.id;
    document.getElementById('channel_type').value = chan.channel_type || '';
    document.getElementById('chan_name').value = chan.name || '';
    document.getElementById('chan_code').value = chan.code || '';
    document.getElementById('chan_manager_name').value = chan.manager_name || '';
    document.getElementById('chan_phone').value = chan.phone || '';
    document.getElementById('chan_gst_number').value = chan.gst_number || '';
    document.getElementById('chan_description').value = chan.description || '';
    
    document.getElementById('btnSubmitChannel').className = `btn ${bgClass} text-white fw-600`;
    document.getElementById('btnSubmitChannel').textContent = `Update ${title}`;
    
    const modal = new bootstrap.Modal(document.getElementById('modalChannel'));
    modal.show();
}

// Facility Saved Details Modal
function viewFacilityDetails(type, data) {
    if (!data) return;

    const header = document.getElementById('modalFacilityDetailsHeader');
    const headerIcon = document.getElementById('facilityHeaderIcon');
    const title = document.getElementById('facilityDetailTitle');
    const subtitle = document.getElementById('facilityDetailSubtitle');
    const name = document.getElementById('facilityDetailName');
    const code = document.getElementById('facilityDetailCode');
    const gst = document.getElementById('facilityDetailGst');
    const manager = document.getElementById('facilityDetailManager');
    const phone = document.getElementById('facilityDetailPhone');
    const address = document.getElementById('facilityDetailAddress');
    const metricsTitle = document.getElementById('facilityMetricsTitle');
    const metricsCont = document.getElementById('facilityMetricsContainer');

    name.textContent = data.name || '-';
    code.textContent = data.code || '-';
    gst.textContent = data.gst_number || 'N/A';
    manager.textContent = data.manager_name || 'Unassigned';
    phone.textContent = data.phone || 'N/A';
    address.textContent = data.address || 'No address specified';

    metricsCont.innerHTML = '';

    if (type === 'unit') {
        header.style.background = '#0d6efd';
        headerIcon.innerHTML = '<i class="bi bi-gear-wide-connected"></i>';
        title.textContent = data.name || 'Production Unit Details';
        subtitle.textContent = 'Apparel Cutting, Stitching & Manufacturing Unit Profile';
        metricsTitle.innerHTML = '<i class="bi bi-activity me-1"></i>Active Production Capacity';

        metricsCont.innerHTML = `
            <div class="col-sm-3 col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Active Programs</div>
                    <div class="fw-bold fs-6 text-primary">${data.active_programs || 0}</div>
                </div>
            </div>
            <div class="col-sm-3 col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Planned Qty</div>
                    <div class="fw-bold fs-6 text-dark">${(parseInt(data.total_planned_qty, 10) || 0).toLocaleString()} pcs</div>
                </div>
            </div>
            <div class="col-sm-3 col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Total Output</div>
                    <div class="fw-bold fs-6 text-success">${(parseInt(data.total_completed_qty, 10) || 0).toLocaleString()} pcs</div>
                </div>
            </div>
            <div class="col-sm-3 col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Cut Batches</div>
                    <div class="fw-bold fs-6 text-secondary">${data.total_cutting || 0}</div>
                </div>
            </div>
        `;
    } else if (type === 'warehouse') {
        header.style.background = '#cc8a26';
        headerIcon.innerHTML = '<i class="bi bi-buildings"></i>';
        title.textContent = data.name || 'Warehouse Details';
        subtitle.textContent = 'Storage & Distribution Facility Profile';
        metricsTitle.innerHTML = '<i class="bi bi-boxes me-1"></i>Warehouse Stock & Valuation';

        const stockPcs = parseInt(data.total_stock_pcs, 10) || 0;
        const valuation = parseFloat(data.total_valuation) || 0;

        metricsCont.innerHTML = `
            <div class="col-sm-4 col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">In-Stock Pieces</div>
                    <div class="fw-bold fs-6 text-warning-emphasis">${stockPcs.toLocaleString()} pcs</div>
                </div>
            </div>
            <div class="col-sm-4 col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Stock Valuation</div>
                    <div class="fw-bold fs-6 text-success">₹${valuation.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                </div>
            </div>
            <div class="col-sm-4 col-12">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Facility Type</div>
                    <div class="fw-bold fs-6 text-dark">Central Distribution Hub</div>
                </div>
            </div>
        `;
    } else if (type === 'shop') {
        header.style.background = '#0dcaf0';
        headerIcon.innerHTML = '<i class="bi bi-shop"></i>';
        title.textContent = data.name || 'Retail Shop Details';
        subtitle.textContent = 'Direct Retail Storefront Profile';
        metricsTitle.innerHTML = '<i class="bi bi-receipt me-1"></i>Storefront Sales Activity';

        const todaySales = parseFloat(data.today_sales) || 0;
        const monthSales = parseFloat(data.month_sales) || 0;

        metricsCont.innerHTML = `
            <div class="col-sm-4 col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Today's Sales</div>
                    <div class="fw-bold fs-6 text-success">₹${todaySales.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                </div>
            </div>
            <div class="col-sm-4 col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Month Sales</div>
                    <div class="fw-bold fs-6 text-primary">₹${monthSales.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                </div>
            </div>
            <div class="col-sm-4 col-12">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Terminal Status</div>
                    <div class="fw-bold fs-6 text-dark">${data.pos_enabled ? 'POS Active' : 'Standard'}</div>
                </div>
            </div>
        `;
    } else {
        const isWhatsapp = type === 'whatsapp';
        header.style.background = isWhatsapp ? '#198754' : '#6f42c1';
        headerIcon.innerHTML = `<i class="bi ${isWhatsapp ? 'bi-whatsapp' : 'bi-globe'}"></i>`;
        title.textContent = data.name || (isWhatsapp ? 'WhatsApp Channel Details' : 'Website Channel Details');
        subtitle.textContent = isWhatsapp ? 'WhatsApp Business Channel Profile' : 'Online Website E-Commerce Channel Profile';
        metricsTitle.innerHTML = '<i class="bi bi-broadcast me-1"></i>Channel Status';

        metricsCont.innerHTML = `
            <div class="col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Channel Type</div>
                    <div class="fw-bold fs-6 text-dark">${data.channel_type || (isWhatsapp ? 'WHATSAPP' : 'WEBSITE')}</div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2 bg-white rounded border">
                    <div class="text-muted small" style="font-size: 11px;">Channel Code</div>
                    <div class="fw-bold fs-6 text-secondary">${data.code || '-'}</div>
                </div>
            </div>
        `;
    }

    const modal = new bootstrap.Modal(document.getElementById('modalFacilityDetails'));
    modal.show();
}

function confirmDeleteFacility(type, id, name, actionUrl) {
    const modalEl = document.getElementById('modalConfirmDeleteFacility');
    if (!modalEl) return;

    const form = document.getElementById('deleteFacilityForm');
    const title = document.getElementById('deleteFacilityModalTitle');
    const desc = document.getElementById('deleteFacilityModalDesc');
    const notice = document.getElementById('deleteFacilityAuditNotice');

    form.action = actionUrl;

    if (type === 'unit') {
        title.textContent = 'Delete Production Unit';
        desc.innerHTML = `Are you sure you want to permanently delete <strong class="text-danger">${name}</strong>?`;
        notice.innerHTML = 'All programs, cutting/stitching batches, and operational assignments will be unlinked from this facility. Deletion will be permanently recorded in the <strong>Live Staff Activity & System Change Logs</strong>.';
    } else if (type === 'warehouse') {
        title.textContent = 'Delete Central Warehouse';
        desc.innerHTML = `Are you sure you want to permanently delete warehouse <strong class="text-danger">${name}</strong>?`;
        notice.innerHTML = 'Associated warehouse locations and inventory records will be cleared. Deletion will be permanently logged in System Audit Trail.';
    } else if (type === 'shop') {
        title.textContent = 'Delete Retail Store';
        desc.innerHTML = `Are you sure you want to permanently delete retail store <strong class="text-danger">${name}</strong>?`;
        notice.innerHTML = 'Associated store stock and POS assignments will be cleared. Action will be permanently logged in System Audit Trail.';
    } else {
        title.textContent = 'Delete Sales Channel';
        desc.innerHTML = `Are you sure you want to permanently delete channel <strong class="text-danger">${name}</strong>?`;
        notice.innerHTML = 'Action will be permanently logged in Live Staff Activity & System Change Logs.';
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
</script>

<!-- Modal: Modern Confirmation for Deleting Production Units & Facilities -->
<div class="modal fade" id="modalConfirmDeleteFacility" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 68px; height: 68px;">
                    <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2" id="deleteFacilityModalTitle">Delete Production Unit</h5>
                <p class="text-secondary small mb-3" id="deleteFacilityModalDesc">
                    Are you sure you want to delete this facility? This action cannot be undone.
                </p>

                <div class="alert alert-warning border-0 bg-warning-subtle text-start p-3 rounded-3 small mb-4">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-shield-exclamation text-warning fs-5 flex-shrink-0 mt-n1"></i>
                        <div>
                            <strong class="text-dark">System Audit Trail Notice:</strong>
                            <div class="text-secondary mt-1" id="deleteFacilityAuditNotice">
                                Deleting this facility will be permanently recorded in the Live Staff Activity &amp; System Change Logs.
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" id="deleteFacilityForm" action="">
                    <?= csrf_field() ?>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light px-4 fw-semibold border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4 fw-semibold shadow-sm d-flex align-items-center gap-1">
                            <i class="bi bi-trash me-1"></i> Confirm &amp; Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
