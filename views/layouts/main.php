<?php
// Layout initialization & user/unit context
$authUser = currentUser() ?? Session::user() ?? [];
$isSuperAdminUser = isSuperAdmin();
$userRoleName = $authUser['role_name'] ?? ($isSuperAdminUser ? 'Super Admin' : 'Staff');
$userRoleSlug = strtolower($authUser['role_slug'] ?? ($isSuperAdminUser ? 'super-admin' : ''));
$userDept = $authUser['department'] ?? '';
$isAdminRole = $isSuperAdminUser || ($authUser['role_id'] ?? 0) === 1 || str_contains($userRoleSlug, 'admin');
$whatsappReportModule = null;
$shopReportModule = null;
$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$whatsappRoutePosition = strrpos($requestPath, '/warehouse/whatsapp-orders');
if ($whatsappRoutePosition !== false) {
    $whatsappRouteTail = trim(substr($requestPath, $whatsappRoutePosition + strlen('/warehouse/whatsapp-orders')), '/');
    if ($whatsappRouteTail === '') {
        $whatsappReportModule = 'dashboard';
    } elseif (str_starts_with($whatsappRouteTail, 'inward')) {
        $whatsappReportModule = 'inward';
    } elseif (str_starts_with($whatsappRouteTail, 'returned-products')) {
        $whatsappReportModule = 'returned-products';
    } elseif (str_starts_with($whatsappRouteTail, 'adjustments')) {
        $whatsappReportModule = 'adjustments';
    } elseif (str_starts_with($whatsappRouteTail, 'allocations')) {
        $whatsappReportModule = ($_GET['tab'] ?? '') === 'allocations' ? 'allocations' : 'inventory';
    } elseif (str_starts_with($whatsappRouteTail, 'inventory')) {
        $whatsappReportModule = 'inventory';
    } elseif (str_starts_with($whatsappRouteTail, 'history')) {
        $whatsappReportModule = 'orders';
    } elseif (str_starts_with($whatsappRouteTail, 'damage')) {
        $whatsappReportModule = 'damage';
    } elseif (str_starts_with($whatsappRouteTail, 'returns')) {
        $whatsappReportModule = 'returns';
    } elseif (str_starts_with($whatsappRouteTail, 'customers')) {
        $whatsappReportModule = 'customers';
    } elseif (str_starts_with($whatsappRouteTail, 'return-to-warehouse')) {
        $whatsappReportModule = 'return-to-warehouse';
    } elseif (str_starts_with($whatsappRouteTail, 'expenses')) {
        $whatsappReportModule = 'expenses';
    }
}
$whatsappReportPermissions = [
    'dashboard' => 'whatsapp_dashboard',
    'inward' => 'whatsapp_inward',
    'inventory' => 'whatsapp_inventory',
    'allocations' => 'whatsapp_inventory',
    'returned-products' => 'whatsapp_inventory',
    'adjustments' => 'whatsapp_inventory',
    'orders' => 'whatsapp_orders',
    'damage' => 'whatsapp_damage',
    'returns' => 'whatsapp_returns',
    'customers' => 'whatsapp_customers',
    'return-to-warehouse' => 'whatsapp_return_to_wh',
    'expenses' => 'whatsapp_expenses',
];
$canDownloadWhatsappReport = $whatsappReportModule !== null
    && hasPermission($whatsappReportPermissions[$whatsappReportModule], 'view');
$shopRoutePosition = strrpos($requestPath, '/shop/');
if ($shopRoutePosition !== false) {
    $shopRouteTail = trim(substr($requestPath, $shopRoutePosition + strlen('/shop/')), '/');
    if ($shopRouteTail === 'dashboard') {
        $shopReportModule = 'dashboard';
    } elseif (str_starts_with($shopRouteTail, 'inventory/adjustments')) {
        $shopReportModule = 'adjustments';
    } elseif (str_starts_with($shopRouteTail, 'inventory')) {
        $shopReportModule = 'inventory';
    } elseif (str_starts_with($shopRouteTail, 'inward')) {
        $shopReportModule = 'inward';
    } elseif (str_starts_with($shopRouteTail, 'sales')) {
        $shopReportModule = 'sales';
    } elseif (str_starts_with($shopRouteTail, 'returns/returned-products')) {
        $shopReportModule = 'returned-products';
    } elseif (str_starts_with($shopRouteTail, 'returns')) {
        $shopReportModule = 'returns';
    } elseif (str_starts_with($shopRouteTail, 'exchanges')) {
        $shopReportModule = 'exchanges';
    } elseif (str_starts_with($shopRouteTail, 'return-to-warehouse')) {
        $shopReportModule = 'return-to-warehouse';
    } elseif (str_starts_with($shopRouteTail, 'customers')) {
        $shopReportModule = 'customers';
    } elseif (str_starts_with($shopRouteTail, 'damage')) {
        $shopReportModule = 'damage';
    } elseif (str_starts_with($shopRouteTail, 'cash')) {
        $shopReportModule = 'cash';
    }
}
$shopReportPermissions = [
    'dashboard' => 'shop',
    'inventory' => 'shop_stock',
    'adjustments' => 'shop_stock',
    'inward' => 'shop_inward',
    'sales' => 'sales',
    'returns' => 'shop_returns',
    'exchanges' => 'shop_returns',
    'returned-products' => 'shop_returns',
    'return-to-warehouse' => 'return_to_warehouse',
    'customers' => 'customers',
    'damage' => 'shop_damage',
    'cash' => 'shop_cash',
];
$canDownloadShopReport = $shopReportModule !== null
    && hasPermission($shopReportPermissions[$shopReportModule], 'view');

// Extract all assigned units defined by admin
$userAssignedUnits = [];
if (!empty($authUser['production_unit_name'])) {
    $userAssignedUnits[] = [
        'type'        => 'Production Unit',
        'short_type'  => 'Unit',
        'name'        => $authUser['production_unit_name'],
        'icon'        => 'bi bi-gear-wide-connected',
        'badge_class' => 'bg-primary text-white'
    ];
}
if (!empty($authUser['warehouse_name'])) {
    $userAssignedUnits[] = [
        'type'        => 'Warehouse',
        'short_type'  => 'Warehouse',
        'name'        => $authUser['warehouse_name'],
        'icon'        => 'bi bi-buildings',
        'badge_class' => 'bg-warning text-dark'
    ];
}
if (!empty($authUser['shop_name'])) {
    $userAssignedUnits[] = [
        'type'        => 'Shop',
        'short_type'  => 'Shop',
        'name'        => $authUser['shop_name'],
        'icon'        => 'bi bi-shop',
        'badge_class' => 'bg-success text-white'
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — GRM Inventory</title>
    <meta name="description" content="GRM Inventory Management System">
    <?= CSRF::metaTag() ?>
    <meta name="auth-check-url" content="<?= url('api/auth/check-status') ?>">
    <meta name="login-url" content="<?= url('login') ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="<?= asset('css/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= asset('css/style.css') ?>?v=<?= file_exists(BASE_PATH . '/public/assets/css/style.css') ? filemtime(BASE_PATH . '/public/assets/css/style.css') : '1.1' ?>" rel="stylesheet">
    <style>
        .submenu.show {
            max-height: none !important;
        }
        .sidebar {
            overflow-y: auto !important;
            scrollbar-width: thin;
        }

        /* Modern Rounded Square Action Buttons (Global Standard) */
        .action-btns-group {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            flex-wrap: nowrap !important;
        }
        .action-btns-group > .btn,
        .action-btns-group > form,
        .action-btns-group > form > .btn,
        .btn-action-icon {
            width: 34px !important;
            height: 34px !important;
            min-width: 34px !important;
            min-height: 34px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            line-height: 1 !important;
            background-color: #ffffff;
            border-width: 1.5px !important;
            border-style: solid !important;
            transition: all 0.15s ease-in-out !important;
            text-decoration: none !important;
            flex-shrink: 0 !important;
            box-shadow: none !important;
        }

        .action-btns-group > .btn:hover,
        .btn-action-icon:hover {
            transform: translateY(-1px);
        }

        /* 0. SECONDARY / VIEW BUTTON (Slate / Grey) */
        .btn-action-icon.btn-outline-secondary,
        .btn-action-icon.btn-secondary,
        .action-btns-group > .btn-outline-secondary,
        .action-btns-group > .btn-secondary {
            border-color: #64748b !important;
            color: #475569 !important;
            background-color: #ffffff !important;
        }
        .btn-action-icon.btn-outline-secondary:visited,
        .action-btns-group > .btn-outline-secondary:visited,
        .btn-action-icon.btn-outline-secondary:active,
        .btn-action-icon.btn-outline-secondary:focus {
            color: #475569 !important;
            border-color: #64748b !important;
            background-color: #ffffff !important;
        }
        .btn-action-icon.btn-outline-secondary:hover,
        .btn-action-icon.btn-secondary:hover,
        .action-btns-group > .btn-outline-secondary:hover,
        .action-btns-group > .btn-secondary:hover {
            background-color: #64748b !important;
            border-color: #64748b !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(100, 116, 139, 0.35) !important;
        }

        /* Enforce visited & active states across all action buttons so icons never turn white/invisible upon click */
        .btn-action-icon.btn-outline-info:visited { color: #00c0ef !important; border-color: #00c0ef !important; }
        .btn-action-icon.btn-outline-primary:visited { color: #2563eb !important; border-color: #2563eb !important; }
        .btn-action-icon.btn-outline-success:visited { color: #10b981 !important; border-color: #10b981 !important; }
        .btn-action-icon.btn-outline-danger:visited { color: #ef4444 !important; border-color: #ef4444 !important; }
        .btn-action-icon.btn-outline-warning:visited { color: #f59e0b !important; border-color: #f59e0b !important; }
        .btn-action-icon.btn-outline-dark:visited { color: #1e293b !important; border-color: #1e293b !important; }

        /* Ensure icon inside action button is always visible, centered, properly sized and inherits color */
        .btn-action-icon i,
        .action-btns-group > .btn i,
        .action-btns-group > form > .btn i {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 15px !important;
            line-height: 1 !important;
            color: inherit !important;
            vertical-align: middle !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* 1. VIEW / INFO BUTTON (Cyan / Light Blue) */
        .btn-action-icon.btn-outline-info,
        .btn-action-icon.btn-action-view,
        .action-btns-group > .btn-outline-info {
            border-color: #00c0ef !important;
            color: #00c0ef !important;
            background-color: #ffffff !important;
        }
        .btn-action-icon.btn-outline-info:hover,
        .btn-action-icon.btn-action-view:hover,
        .action-btns-group > .btn-outline-info:hover {
            background-color: #00c0ef !important;
            border-color: #00c0ef !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(0, 192, 239, 0.35) !important;
        }

        /* 2. EDIT / PRIMARY BUTTON (Blue) */
        .btn-action-icon.btn-outline-primary,
        .btn-action-icon.btn-action-edit,
        .action-btns-group > .btn-outline-primary {
            border-color: #2563eb !important;
            color: #2563eb !important;
            background-color: #ffffff !important;
        }
        .btn-action-icon.btn-outline-primary:hover,
        .btn-action-icon.btn-action-edit:hover,
        .action-btns-group > .btn-outline-primary:hover {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.35) !important;
        }

        /* 3. VERIFY / SUCCESS BUTTON (Green) */
        .btn-action-icon.btn-outline-success,
        .btn-action-icon.btn-action-check,
        .action-btns-group > .btn-outline-success {
            border-color: #10b981 !important;
            color: #10b981 !important;
            background-color: #ffffff !important;
        }
        .btn-action-icon.btn-outline-success:hover,
        .btn-action-icon.btn-action-check:hover,
        .action-btns-group > .btn-outline-success:hover {
            background-color: #10b981 !important;
            border-color: #10b981 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35) !important;
        }

        /* 4. DELETE / DANGER BUTTON (Red) */
        .btn-action-icon.btn-outline-danger,
        .btn-action-icon.btn-action-delete,
        .action-btns-group > .btn-outline-danger,
        .action-btns-group > form > .btn-outline-danger {
            border-color: #ef4444 !important;
            color: #ef4444 !important;
            background-color: #ffffff !important;
        }
        .btn-action-icon.btn-outline-danger:hover,
        .btn-action-icon.btn-action-delete:hover,
        .action-btns-group > .btn-outline-danger:hover,
        .action-btns-group > form > .btn-outline-danger:hover {
            background-color: #ef4444 !important;
            border-color: #ef4444 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.35) !important;
        }

        /* 5. WARNING / SPECIAL ACTIONS (Amber) */
        .btn-action-icon.btn-outline-warning,
        .btn-action-icon.btn-action-warning,
        .action-btns-group > .btn-outline-warning {
            border-color: #f59e0b !important;
            color: #f59e0b !important;
            background-color: #ffffff !important;
        }
        .btn-action-icon.btn-outline-warning:hover,
        .btn-action-icon.btn-action-warning:hover,
        .action-btns-group > .btn-outline-warning:hover {
            background-color: #f59e0b !important;
            border-color: #f59e0b !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.35) !important;
        }

        /* 6. DARK BUTTONS (e.g. Roll QR print) */
        .btn-action-icon.btn-dark,
        .action-btns-group > .btn-dark {
            border-color: #1e293b !important;
            color: #f8fafc !important;
            background-color: #1e293b !important;
        }
        .btn-action-icon.btn-dark:hover,
        .action-btns-group > .btn-dark:hover {
            background-color: #0f172a !important;
            border-color: #0f172a !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.35) !important;
        }
    </style>
    <?php if (($activeModule ?? '') === 'reports'): ?>
    <link href="<?= asset('css/reports.css') ?>?v=<?= file_exists(BASE_PATH . '/public/assets/css/reports.css') ? filemtime(BASE_PATH . '/public/assets/css/reports.css') : '1' ?>" rel="stylesheet">
    <?php endif; ?>
    <!-- Modern Pagination Engine (Preloaded in head so all page scripts have instant access) -->
    <script src="<?= asset('js/pagination.js') ?>?v=<?= file_exists(BASE_PATH . '/public/assets/js/pagination.js') ? filemtime(BASE_PATH . '/public/assets/js/pagination.js') : '1.1' ?>"></script>
</head>
<body>
    <div class="app-wrapper">
        <!-- ============================================ -->
        <!-- SIDEBAR -->
        <!-- ============================================ -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <i class="bi bi-box-seam-fill"></i>
                    <span class="brand-text">GRM Inventory</span>
                </div>
                <button class="sidebar-toggle-btn d-lg-none" id="sidebarClose">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <!-- Admin Dashboard -->
                    <!-- Admin Dashboard -->
                    <?php if (hasPermission('dashboard', 'view')): ?>
                    <li class="nav-item">
                        <a href="<?= url('admin/dashboard') ?>" class="nav-link <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Production -->
                    <?php 
                    $canViewProduction = hasPermission('production', 'view') 
                        || hasPermission('production_billing', 'view')
                        || hasPermission('sellers', 'view')
                        || hasPermission('production_purchases', 'view')
                        || hasPermission('damages', 'view')
                        || hasPermission('catalog', 'view')
                        || hasPermission('programs', 'view')
                        || hasPermission('cutting', 'view')
                        || hasPermission('wastage', 'view')
                        || hasPermission('stitching', 'view')
                        || hasPermission('stitching_wastage', 'view')
                        || hasPermission('production_expenses', 'view')
                        || hasPermission('labels', 'view')
                        || hasPermission('qc', 'view')
                        || hasPermission('inventory', 'view')
                        || hasPermission('batches', 'view')
                        || hasPermission('outward', 'view')
                        || hasPermission('production_returns', 'view')
                        || hasPermission('production_stock_requests', 'view')
                        || hasPermission('warehouse_rework', 'view');
                    ?>
                    <?php if ($canViewProduction): ?>
                    <li class="nav-item has-submenu">
                        <a href="#" class="nav-link <?= ($activeModule ?? '') === 'production' ? 'active' : '' ?>">
                            <i class="bi bi-gear-wide-connected"></i>
                            <span>Production</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu <?= ($activeModule ?? '') === 'production' ? 'show' : '' ?>">
                            <?php if (hasPermission('production', 'view')): ?>
                            <li><a href="<?= url('production/dashboard') ?>" class="<?= ($activeMenu ?? '') === 'prod-dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('sellers', 'view')): ?>
                            <li><a href="<?= url('production/sellers') ?>" class="<?= ($activeMenu ?? '') === 'prod-sellers' ? 'active' : '' ?>">Sellers</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('production_purchases', 'view')): ?>
                            <li><a href="<?= url('production/purchases') ?>" class="<?= ($activeMenu ?? '') === 'prod-purchases' ? 'active' : '' ?>">Purchases</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('damages', 'view')): ?>
                            <li><a href="<?= url('production/damages') ?>" class="<?= ($activeMenu ?? '') === 'prod-damages' ? 'active' : '' ?>">Damages</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('catalog', 'view')): ?>
                            <li><a href="<?= url('production/catalog') ?>" class="<?= ($activeMenu ?? '') === 'prod-catalog' ? 'active' : '' ?>">Catalog</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('programs', 'view')): ?>
                            <li><a href="<?= url('production/programs') ?>" class="<?= ($activeMenu ?? '') === 'programs' ? 'active' : '' ?>">Programs</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('cutting', 'view')): ?>
                            <li><a href="<?= url('production/cutting') ?>" class="<?= ($activeMenu ?? '') === 'cutting' ? 'active' : '' ?>">Cutting</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('wastage', 'view')): ?>
                            <li><a href="<?= url('production/wastage') ?>" class="<?= ($activeMenu ?? '') === 'wastage-selling' ? 'active' : '' ?>">Wastage Selling</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('stitching', 'view')): ?>
                            <li><a href="<?= url('production/stitching') ?>" class="<?= ($activeMenu ?? '') === 'stitching' ? 'active' : '' ?>">Stitching</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('stitching_wastage', 'view')): ?>
                            <li><a href="<?= url('production/stitching-wastage') ?>" class="<?= ($activeMenu ?? '') === 'stitching-wastage' ? 'active' : '' ?>">Stitching Wastage</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('labels', 'view')): ?>
                            <li><a href="<?= url('production/labels') ?>" class="<?= ($activeMenu ?? '') === 'labels' ? 'active' : '' ?>">Labels</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('qc', 'view')): ?>
                            <li><a href="<?= url('production/qc') ?>" class="<?= ($activeMenu ?? '') === 'qc' ? 'active' : '' ?>">QC</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('inventory', 'view')): ?>
                            <li><a href="<?= url('production/inventory') ?>" class="<?= ($activeMenu ?? '') === 'prod-inventory' ? 'active' : '' ?>">Inventory</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('production_expenses', 'view')): ?>
                            <li><a href="<?= url('production/expenses') ?>" class="<?= ($activeMenu ?? '') === 'production-expenses' ? 'active' : '' ?>">Expenses</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('production_billing', 'view')): ?>
                            <li><a href="<?= url('production/billing') ?>" class="<?= ($activeMenu ?? '') === 'prod-billing' ? 'active' : '' ?>">Billing</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('production_returns', 'view')): ?>
                            <li><a href="<?= url('production/returns') ?>" class="<?= ($activeMenu ?? '') === 'prod-returns' ? 'active' : '' ?>">Returns &amp; Exchanges</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('batches', 'view')): ?>
                            <li><a href="<?= url('production/batches') ?>" class="<?= in_array($activeMenu ?? '', ['batches', 'prod-batches']) ? 'active' : '' ?>">Batches</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('outward', 'view')): ?>
                            <li><a href="<?= url('production/outward') ?>" class="<?= in_array($activeMenu ?? '', ['outward', 'prod-outward', 'batch-outward']) ? 'active' : '' ?>">Outward</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('production_stock_requests', 'view')): ?>
                            <li><a href="<?= url('production/stock-requests') ?>" class="<?= ($activeMenu ?? '') === 'prod-stock-requests' ? 'active' : '' ?>">Stock Requests</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('warehouse_rework', 'view')): ?>
                            <li><a href="<?= url('production/warehouse-rework') ?>" class="<?= ($activeMenu ?? '') === 'warehouse-rework' ? 'active' : '' ?>">Rework from Warehouse</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Warehouse -->
                    <?php 
                    $canViewWarehouse = hasPermission('warehouse', 'view')
                        || hasPermission('inward', 'view')
                        || hasPermission('stock', 'view')
                        || hasPermission('stock_adjustments', 'view')
                        || hasPermission('wh_sellers', 'view')
                        || hasPermission('wh_catalog', 'view')
                        || hasPermission('wh_labels', 'view')
                        || hasPermission('purchases', 'view')
                        || hasPermission('purchases_returns', 'view')
                        || hasPermission('allocation', 'view')
                        || hasPermission('allocation_outward', 'view')
                        || hasPermission('damage', 'view')
                        || hasPermission('returns', 'view')
                        || hasPermission('shop_requests', 'view')
                        || hasPermission('warehouse_expenses', 'view');
                    ?>
                    <?php if ($canViewWarehouse): ?>
                    <li class="nav-item has-submenu">
                        <a href="#" class="nav-link <?= ($activeModule ?? '') === 'warehouse' ? 'active' : '' ?>">
                            <i class="bi bi-buildings"></i>
                            <span>Warehouse</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu <?= ($activeModule ?? '') === 'warehouse' ? 'show' : '' ?>">
                            <?php if (hasPermission('warehouse', 'view')): ?>
                            <li><a href="<?= url('warehouse/dashboard') ?>" class="<?= ($activeMenu ?? '') === 'wh-dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('inward', 'view')): ?>
                            <li><a href="<?= url('warehouse/inward') ?>" class="<?= in_array(($activeMenu ?? ''), ['inward', 'wh-inward']) ? 'active' : '' ?>">Inward / GRN</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('stock', 'view')): ?>
                            <li><a href="<?= url('warehouse/stock') ?>" class="<?= ($activeMenu ?? '') === 'wh-stock' ? 'active' : '' ?>">Stock</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('stock_adjustments', 'view')): ?>
                            <li><a href="<?= url('warehouse/adjustments') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-adjustments', 'wh-adjustment-increase', 'wh-adjustment-decrease'], true) ? 'active' : '' ?>"><i class="bi bi-sliders"></i><span>Stock Adjustments</span></a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('wh_catalog', 'view')): ?>
                            <li><a href="<?= url('warehouse/catalog') ?>" class="<?= ($activeMenu ?? '') === 'wh-catalog' ? 'active' : '' ?>">Catalog</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('wh_sellers', 'view')): ?>
                            <li><a href="<?= url('warehouse/sellers') ?>" class="<?= ($activeMenu ?? '') === 'wh-sellers' ? 'active' : '' ?>">Suppliers</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('wh_labels', 'view')): ?>
                            <li><a href="<?= url('warehouse/labels') ?>" class="<?= ($activeMenu ?? '') === 'wh-labels' ? 'active' : '' ?>">QR Labels</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('purchases', 'view')): ?>
                            <li><a href="<?= url('warehouse/purchases') ?>" class="<?= in_array(($activeMenu ?? ''), ['purchases', 'wh-purchases']) ? 'active' : '' ?>">Direct Purchases</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('purchases_returns', 'view')): ?>
                            <li><a href="<?= url('warehouse/purchases/returns') ?>" class="<?= ($activeMenu ?? '') === 'purchases-returns' ? 'active' : '' ?>">Purchases Returns &amp; Exchanges</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('allocation', 'view')): ?>
                            <li><a href="<?= url('warehouse/allocation') ?>" class="<?= in_array(($activeMenu ?? ''), ['allocation', 'wh-allocations']) ? 'active' : '' ?>">Allocation</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('allocation_outward', 'view')): ?>
                            <li><a href="<?= url('warehouse/allocation/outward') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-outward', 'allocation-outward']) ? 'active' : '' ?>">Outward</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('damage', 'view')): ?>
                            <li><a href="<?= url('warehouse/damage') ?>" class="<?= ($activeMenu ?? '') === 'wh-damage' ? 'active' : '' ?>">Damage</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('returns', 'view')): ?>
                            <li><a href="<?= url('warehouse/returns') ?>" class="<?= ($activeMenu ?? '') === 'wh-returns' ? 'active' : '' ?>">Returns</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('shop_requests', 'view')): ?>
                            <li><a href="<?= url('warehouse/shop-requests') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-shop-requests', 'shop-requests']) ? 'active' : '' ?>">Requests</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('warehouse_expenses', 'view')): ?>
                            <li><a href="<?= url('warehouse/expenses') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-expenses', 'warehouse-expenses']) ? 'active' : '' ?>"><i class="bi bi-cash-stack me-1"></i> Warehouse Expenses</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- WhatsApp Orders -->
                    <?php 
                    $canViewWhatsapp = hasPermission('whatsapp_dashboard', 'view')
                        || hasPermission('whatsapp_orders', 'view')
                        || hasPermission('whatsapp_inward', 'view')
                        || hasPermission('whatsapp_inventory', 'view')
                        || hasPermission('whatsapp_returned_products', 'view')
                        || hasPermission('whatsapp_damage', 'view')
                        || hasPermission('whatsapp_returns', 'view')
                        || hasPermission('whatsapp_return_to_wh', 'view')
                        || hasPermission('whatsapp_expenses', 'view');
                    ?>
                    <?php if ($canViewWhatsapp): ?>
                    <li class="nav-item has-submenu">
                        <a href="#" class="nav-link <?= in_array(($activeModule ?? ''), ['whatsapp', 'whatsapp-orders', 'wh-whatsapp']) ? 'active' : '' ?>">
                            <i class="bi bi-whatsapp"></i>
                            <span>WhatsApp Orders</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu <?= in_array(($activeModule ?? ''), ['whatsapp', 'whatsapp-orders', 'wh-whatsapp']) ? 'show' : '' ?>">
                            <?php if (hasPermission('whatsapp_dashboard', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-whatsapp', 'wh-whatsapp-dashboard', 'whatsapp-dashboard']) ? 'active' : '' ?>">Dashboard</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('whatsapp_inward', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders/inward') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-whatsapp-inward', 'whatsapp-inward']) ? 'active' : '' ?>">Inward / GRN</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('whatsapp_inventory', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders/allocations') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-whatsapp-allocations', 'whatsapp-allocations']) ? 'active' : '' ?>">Inventory</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('whatsapp_returned_products', 'view') || hasPermission('whatsapp_inventory', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders/returned-products') ?>" class="<?= ($activeMenu ?? '') === 'wh-whatsapp-returned-products' ? 'active' : '' ?>">Returned Products</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('whatsapp_orders', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders/history') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-whatsapp-history', 'whatsapp-history', 'wh-whatsapp-orders']) ? 'active' : '' ?>">Orders History</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('whatsapp_damage', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders/damage') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-whatsapp-damage', 'whatsapp-damage']) ? 'active' : '' ?>"><i class="bi bi-exclamation-octagon me-1"></i> Damage Management</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('whatsapp_returns', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders/returns') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-whatsapp-returns', 'whatsapp-returns']) ? 'active' : '' ?>">Returns &amp; Exchanges</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('whatsapp_return_to_wh', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders/return-to-warehouse') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-whatsapp-return-to-wh', 'whatsapp-return-to-wh']) ? 'active' : '' ?>"><i class="bi bi-arrow-return-left me-1"></i> Return to Warehouse</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('whatsapp_expenses', 'view')): ?>
                            <li><a href="<?= url('warehouse/whatsapp-orders/expenses') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-whatsapp-expenses', 'whatsapp-expenses']) ? 'active' : '' ?>"><i class="bi bi-cash-stack me-1"></i> Channel Expenses</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Website Orders -->
                    <?php 
                    $canViewWebsite = hasPermission('website_dashboard', 'view')
                        || hasPermission('website_orders', 'view')
                        || hasPermission('website_inward', 'view')
                        || hasPermission('website_inventory', 'view')
                        || hasPermission('website_damage', 'view')
                        || hasPermission('website_returns', 'view')
                        || hasPermission('website_return_to_wh', 'view')
                        || hasPermission('website_customers', 'view')
                        || hasPermission('website_expenses', 'view');
                    ?>
                    <?php if ($canViewWebsite): ?>
                    <li class="nav-item has-submenu">
                        <a href="#" class="nav-link <?= in_array(($activeModule ?? ''), ['website', 'website-orders', 'wh-website']) ? 'active' : '' ?>">
                            <i class="bi bi-globe"></i>
                            <span>Website Orders</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu <?= in_array(($activeModule ?? ''), ['website', 'website-orders', 'wh-website']) ? 'show' : '' ?>">
                            <?php if (hasPermission('website_dashboard', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website', 'wh-website-dashboard', 'website-dashboard']) ? 'active' : '' ?>">Dashboard</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('website_inward', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders/inward') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website-inward', 'website-inward']) ? 'active' : '' ?>">Inward / GRN</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('website_inventory', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders/allocations') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website-allocations', 'website-allocations']) ? 'active' : '' ?>">Inventory</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('website_orders', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders/history') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website-history', 'website-history', 'wh-website-orders']) ? 'active' : '' ?>">Orders History</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('website_damage', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders/damage') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website-damage', 'website-damage']) ? 'active' : '' ?>"><i class="bi bi-exclamation-octagon me-1"></i> Damage Management</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('website_returns', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders/returns') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website-returns', 'website-returns']) ? 'active' : '' ?>">Returns &amp; Exchanges</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('website_return_to_wh', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders/return-to-warehouse') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website-return-to-wh', 'website-return-to-wh']) ? 'active' : '' ?>"><i class="bi bi-arrow-return-left me-1"></i> Return to Warehouse</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('website_customers', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders/customers') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website-customers', 'website-customers']) ? 'active' : '' ?>">Customers</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('website_expenses', 'view')): ?>
                            <li><a href="<?= url('warehouse/website-orders/expenses') ?>" class="<?= in_array(($activeMenu ?? ''), ['wh-website-expenses', 'website-expenses']) ? 'active' : '' ?>"><i class="bi bi-cash-stack me-1"></i> Channel Expenses</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Shop -->
                    <?php 
                    $canViewShop = hasPermission('shop', 'view')
                        || hasPermission('pos', 'view')
                        || hasPermission('shop_cash', 'view')
                        || hasPermission('shop_inward', 'view')
                        || hasPermission('shop_stock', 'view')
                        || hasPermission('shop_damage', 'view')
                        || hasPermission('sales', 'view')
                        || hasPermission('customers', 'view')
                        || hasPermission('shop_returns', 'view')
                        || hasPermission('return_to_warehouse', 'view');
                    ?>
                    <?php if ($canViewShop): ?>
                    <li class="nav-item has-submenu">
                        <a href="#" class="nav-link <?= ($activeModule ?? '') === 'shop' ? 'active' : '' ?>">
                            <i class="bi bi-shop"></i>
                            <span>Shop</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu <?= ($activeModule ?? '') === 'shop' ? 'show' : '' ?>">
                            <?php if (hasPermission('shop', 'view')): ?>
                            <li><a href="<?= url('shop/dashboard') ?>" class="<?= ($activeMenu ?? '') === 'shop-dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('shop_cash', 'view')): ?>
                            <li><a href="<?= url('shop/cash') ?>" class="<?= in_array(($activeMenu ?? ''), ['shop-cash', 'cash', 'cash-flow']) ? 'active' : '' ?>"><i class="bi bi-cash-stack me-1"></i> Cash &amp; Cash Flow</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('shop_inward', 'view')): ?>
                            <li><a href="<?= url('shop/inward') ?>" class="<?= in_array(($activeMenu ?? ''), ['shop-inward', 'inward']) ? 'active' : '' ?>">Inward / GRN</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('shop_stock', 'view')): ?>
                            <li><a href="<?= url('shop/inventory') ?>" class="<?= ($activeMenu ?? '') === 'shop-inventory' ? 'active' : '' ?>">Inventory</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('shop_damage', 'view') || hasPermission('damage', 'view')): ?>
                            <li><a href="<?= url('shop/damage') ?>" class="<?= in_array(($activeMenu ?? ''), ['shop-damage', 'damage']) ? 'active' : '' ?>"><i class="bi bi-exclamation-octagon me-1"></i> Damage Management</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('pos', 'view')): ?>
                            <li><a href="<?= url('shop/pos') ?>" class="<?= in_array(($activeMenu ?? ''), ['shop-pos', 'pos']) ? 'active' : '' ?>">POS / Billing</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('sales', 'view')): ?>
                            <li><a href="<?= url('shop/sales') ?>" class="<?= in_array(($activeMenu ?? ''), ['shop-sales', 'sales']) ? 'active' : '' ?>">Selled Products</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('customers', 'view')): ?>
                            <li><a href="<?= url('shop/customers') ?>" class="<?= in_array(($activeMenu ?? ''), ['shop-customers', 'customers']) ? 'active' : '' ?>"><i class="bi bi-people me-1"></i> Customer Details</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('shop_returns', 'view')): ?>
                            <li><a href="<?= url('shop/returns') ?>" class="<?= in_array(($activeMenu ?? ''), ['shop-returns', 'returns', 'shop-exchanges', 'exchanges']) ? 'active' : '' ?>">Customer Returns &amp; Exchanges</a></li>
                            <li><a href="<?= url('shop/returns/returned-products') ?>" class="<?= ($activeMenu ?? '') === 'shop-returned-products' ? 'active' : '' ?>">Returned Products</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('return_to_warehouse', 'view')): ?>
                            <li><a href="<?= url('shop/return-to-warehouse') ?>" class="<?= in_array(($activeMenu ?? ''), ['shop-return-to-wh', 'return-to-warehouse']) ? 'active' : '' ?>">Return to Warehouse</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Assets -->
                    <?php 
                    $canViewAssets = hasPermission('asset_dashboard', 'view')
                        || hasPermission('asset_register', 'view')
                        || hasPermission('assets', 'view')
                        || hasPermission('asset_scanner', 'view')
                        || hasPermission('asset_transfers', 'view')
                        || hasPermission('asset_maintenance', 'view');
                    ?>
                    <?php if ($canViewAssets): ?>
                    <li class="nav-item has-submenu">
                        <a href="#" class="nav-link <?= ($activeModule ?? '') === 'assets' ? 'active' : '' ?>">
                            <i class="bi bi-cpu"></i>
                            <span>Assets</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu <?= ($activeModule ?? '') === 'assets' ? 'show' : '' ?>">
                            <?php if (hasPermission('asset_dashboard', 'view') || hasPermission('assets', 'view')): ?>
                            <li><a href="<?= url('asset/dashboard') ?>" class="<?= ($activeMenu ?? '') === 'asset-dashboard' ? 'active' : '' ?>"><i class="bi bi-bar-chart-fill me-1 text-primary"></i> Overview</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('asset_register', 'view') || hasPermission('assets', 'view')): ?>
                            <li><a href="<?= url('asset/list') ?>" class="<?= in_array(($activeMenu ?? ''), ['asset-list', 'asset-create']) ? 'active' : '' ?>"><i class="bi bi-table me-1 text-success"></i> Asset Register</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('asset_scanner', 'view')): ?>
                            <li><a href="<?= url('asset/scanner') ?>" class="<?= ($activeMenu ?? '') === 'asset-scanner' ? 'active' : '' ?>"><i class="bi bi-qr-code-scan me-1 text-info"></i> Scan Assets</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('asset_maintenance', 'view')): ?>
                            <li><a href="<?= url('asset/maintenance') ?>" class="<?= ($activeMenu ?? '') === 'asset-maintenance' ? 'active' : '' ?>"><i class="bi bi-wrench-adjustable me-1 text-danger"></i> Maintenance</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('asset_transfers', 'view')): ?>
                            <li><a href="<?= url('asset/transfers') ?>" class="<?= ($activeMenu ?? '') === 'asset-transfers' ? 'active' : '' ?>" title="Equipment Allocation Logs"><i class="bi bi-arrow-left-right me-1 text-warning"></i> Transfers</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <li class="nav-divider"></li>

                    <!-- Staff -->
                    <?php 
                    $canViewStaff = hasPermission('staff', 'view') || hasPermission('roles', 'view') || hasPermission('staff', 'create');
                    ?>
                    <?php if ($canViewStaff): ?>
                    <li class="nav-item has-submenu">
                        <a href="#" class="nav-link <?= ($activeModule ?? '') === 'staff' ? 'active' : '' ?>">
                            <i class="bi bi-people"></i>
                            <span>Staff</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu <?= ($activeModule ?? '') === 'staff' ? 'show' : '' ?>">
                            <?php if (hasPermission('staff', 'create')): ?>
                            <li><a href="<?= url('admin/staff/create') ?>" class="<?= ($activeMenu ?? '') === 'staff-create' ? 'active' : '' ?>">Add Staff</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('staff', 'view')): ?>
                            <li><a href="<?= url('admin/staff?department=all') ?>" class="<?= in_array(($activeMenu ?? ''), ['staff', 'staff-all', 'staff-production', 'staff-warehouse', 'staff-whatsapp', 'staff-website', 'staff-shop', 'staff-reports']) ? 'active' : '' ?>">Total Staffs</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('roles', 'view')): ?>
                            <li><a href="<?= url('admin/roles') ?>" class="<?= ($activeMenu ?? '') === 'roles' ? 'active' : '' ?>">Role Assign / Permissions</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- System -->
                    <?php 
                    $canViewSystem = hasPermission('settings', 'view') 
                        || hasPermission('audit', 'view')
                        || hasPermission('backup', 'view');
                    ?>
                    <?php if ($canViewSystem): ?>
                    <li class="nav-item has-submenu">
                        <a href="#" class="nav-link <?= ($activeModule ?? '') === 'system' ? 'active' : '' ?>">
                            <i class="bi bi-sliders"></i>
                            <span>System</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu <?= ($activeModule ?? '') === 'system' ? 'show' : '' ?>">
                            <?php if (hasPermission('audit', 'view')): ?>
                            <li><a href="<?= url('system/audit') ?>">Audit Logs</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('settings', 'view')): ?>
                            <li><a href="<?= url('system/settings') ?>">Settings</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('backup', 'view')): ?>
                            <li><a href="<?= url('system/backup') ?>">Backup</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <!-- ============================================ -->
        <!-- MAIN CONTENT -->
        <!-- ============================================ -->
        <main class="main-content <?= ($activeModule ?? '') === 'reports' ? 'reports-content' : '' ?>">
            <!-- Top Navbar -->
            <header class="top-navbar">
                <div class="navbar-left">
                    <button class="sidebar-toggle-btn" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <!-- Breadcrumb -->
                    <?php 
                    $bc = $breadcrumb ?? $breadcrumbs ?? [];
                    if (!empty($bc)): 
                    ?>
                    <nav aria-label="breadcrumb" class="d-none d-md-block">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= url('admin/dashboard') ?>">Home</a></li>
                            <?php foreach ($bc as $i => $crumb): ?>
                                <?php if (isset($crumb['url']) && $i < count($bc) - 1): ?>
                                    <li class="breadcrumb-item"><a href="<?= $crumb['url'] ?>"><?= e($crumb['label']) ?></a></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active"><?= e($crumb['label']) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                    <?php endif; ?>
                </div>
                <div class="navbar-right">
                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <button class="user-dropdown" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <div class="user-info d-none d-md-block">
                                <div class="d-flex align-items-center gap-1">
                                    <span class="user-name fw-semibold"><?= e($authUser['full_name'] ?? $authUser['username'] ?? 'User') ?></span>
                                    <span class="badge <?= $isAdminRole ? 'bg-primary' : 'bg-info text-dark' ?> rounded-pill" style="font-size: 0.6rem; padding: 1px 5px;">
                                        <?= $isAdminRole ? 'ADMIN' : 'STAFF' ?>
                                    </span>
                                </div>
                                <span class="user-role text-muted" style="font-size: 0.72rem;"><?= e($userRoleName) ?></span>
                            </div>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li class="px-3 py-2 border-bottom bg-light">
                                <div class="fw-semibold text-dark text-truncate"><?= e($authUser['full_name'] ?? $authUser['username'] ?? 'User') ?></div>
                                <small class="text-muted d-block"><i class="bi bi-shield me-1"></i>Role: <?= e($userRoleName) ?> (<?= $isAdminRole ? 'Admin' : 'Staff' ?>)</small>
                                <?php if (!empty($userAssignedUnits)): ?>
                                    <?php foreach ($userAssignedUnits as $uItem): ?>
                                    <small class="text-primary d-block text-truncate mt-0.5"><i class="<?= $uItem['icon'] ?> me-1"></i><?= e($uItem['name']) ?></small>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </li>
                            <li><a class="dropdown-item mt-1" href="<?= url('admin/change-password') ?>"><i class="bi bi-key me-2"></i>Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Flash Messages (Modern Alert Box UI) -->
                <?php foreach (['success', 'error', 'warning', 'info'] as $type): ?>
                    <?php $messages = Session::getFlash($type); ?>
                    <?php foreach ($messages as $message): ?>
                        <?php
                            $alertConfig = match($type) {
                                'success' => ['icon' => 'check-circle-fill', 'class' => 'flash-alert-success', 'title' => 'Success Notification'],
                                'error'   => ['icon' => 'exclamation-triangle-fill', 'class' => 'flash-alert-danger', 'title' => 'System Warning / Action Blocked'],
                                'warning' => ['icon' => 'exclamation-circle-fill', 'class' => 'flash-alert-warning', 'title' => 'Attention Required'],
                                default   => ['icon' => 'info-circle-fill', 'class' => 'flash-alert-info', 'title' => 'System Information'],
                            };
                        ?>
                        <div class="alert <?= $alertConfig['class'] ?> alert-dismissible fade show flash-alert modern-alert-box shadow-sm" role="alert">
                            <div class="alert-icon-wrap me-3">
                                <i class="bi bi-<?= $alertConfig['icon'] ?>"></i>
                            </div>
                            <div class="alert-content-wrap flex-grow-1">
                                <div class="alert-title fw-bold text-uppercase"><?= $alertConfig['title'] ?></div>
                                <div class="alert-message"><?= e($message) ?></div>
                            </div>
                            <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <?php if ($canDownloadWhatsappReport || $canDownloadShopReport): ?>
                    <?php $reportModule = $canDownloadShopReport ? $shopReportModule : $whatsappReportModule; ?>
                    <?php $reportAction = $canDownloadShopReport ? 'shop/reports/download/' : 'warehouse/whatsapp-orders/reports/download/'; ?>
                    <form method="get" action="<?= url($reportAction . rawurlencode($reportModule)) ?>" class="whatsapp-report-toolbar card border shadow-sm mb-3" <?= $canDownloadShopReport ? 'onsubmit="syncShopReportFilters(this)" data-shop-report-module="' . e($shopReportModule) . '"' : '' ?>>
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if (is_scalar($value) && !in_array($key, ['url', 'format', 'scope'], true)): ?>
                                <input type="hidden" name="<?= e((string)$key) ?>" value="<?= e((string)$value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 p-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="whatsapp-report-icon"><i class="bi bi-file-earmark-arrow-down"></i></span>
                                <div>
                                    <div class="fw-semibold text-dark">Export report</div>
                                    <small class="text-muted">Choose a format and data scope</small>
                                </div>
                            </div>
                            <div class="whatsapp-report-controls d-flex flex-wrap align-items-end gap-2">
                                <div>
                                    <label for="whatsappReportFormat" class="form-label small fw-semibold text-muted mb-1">Format</label>
                                    <select id="whatsappReportFormat" name="format" class="form-select form-select-sm">
                                        <option value="csv">CSV</option>
                                        <option value="pdf">PDF</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="whatsappReportScope" class="form-label small fw-semibold text-muted mb-1">Data</label>
                                    <select id="whatsappReportScope" name="scope" class="form-select form-select-sm">
                                        <option value="filtered">Current filters</option>
                                        <option value="all">All records</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary whatsapp-report-download">
                                    <i class="bi bi-download me-1"></i>Download
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php if ($canDownloadShopReport): ?>
                        <script>
                            function syncShopReportFilters(form) {
                                const setFilter = (name, value) => {
                                    let field = form.querySelector(`[name="${name}"]`);
                                    if (!field) {
                                        field = document.createElement('input');
                                        field.type = 'hidden';
                                        field.name = name;
                                        form.appendChild(field);
                                    }
                                    field.value = value || '';
                                };
                                if (form.dataset.shopReportModule === 'dashboard') {
                                    const activePeriod = document.querySelector('#mainFilterBtnGroup button.active')?.dataset.period;
                                    if (activePeriod) setFilter('period', activePeriod);
                                    if (activePeriod === 'custom') {
                                        setFilter('start_date', document.getElementById('filterStartDate')?.value);
                                        setFilter('end_date', document.getElementById('filterEndDate')?.value);
                                    }
                                }
                                if (form.dataset.shopReportModule === 'cash') {
                                    setFilter('q', document.getElementById('cashTabSearchInput')?.value);
                                }
                                if (form.dataset.shopReportModule === 'inventory') {
                                    const activeType = document.querySelector('.product-type-tab.active')?.dataset.productType;
                                    if (activeType) setFilter('product_type', activeType);
                                }
                                return true;
                            }
                        </script>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Main Content from child view -->
                <?= $content ?? '' ?>
            </div>

            <!-- Footer -->
            <footer class="app-footer">
                <span>© <?= date('Y') ?> GRM Inventory Management. All rights reserved.</span>
                <span>v1.0</span>
            </footer>
        </main>
    </div>

    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
    <script>if (typeof bootstrap === 'undefined') document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"><\/script>');</script>
    <!-- QRCode Generator JS -->
    <script src="<?= asset('js/qrcode.min.js') ?>"></script>
    <script>if (typeof QRCode === 'undefined') document.write('<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"><\/script>');</script>
    <!-- HTML5 QR / Barcode Scanner JS -->
    <script src="<?= asset('js/html5-qrcode.min.js') ?>"></script>
    <script>if (typeof Html5Qrcode === 'undefined') document.write('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"><\/script>');</script>
    <!-- Custom JS -->
    <script src="<?= asset('js/app.js') ?>?v=<?= file_exists(BASE_PATH . '/public/assets/js/app.js') ? filemtime(BASE_PATH . '/public/assets/js/app.js') : '1.1' ?>"></script>
    <!-- Page-specific scripts -->
    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= asset('js/' . $script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <!-- Global Universal QR Status Finder Modal -->
    <?php require BASE_PATH . '/views/partials/qr_status_finder_modal.php'; ?>
    <!-- Global Modern Confirmation Modal -->
    <?php require BASE_PATH . '/views/partials/confirm_modal.php'; ?>
</body>
</html>
