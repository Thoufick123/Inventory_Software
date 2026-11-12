<?php
/**
 * Role Model
 * Handles role and permission data access.
 */

class Role {
    /**
     * Get all roles (excluding root Super Admin).
     */
    public static function getAll(): array {
        return Database::fetchAll("SELECT * FROM roles WHERE id != 1 ORDER BY name ASC");
    }

    /**
     * Map of operational departments to their respective functional modules.
     */
    /**
     * Map of operational departments to their respective functional modules.
     */
    public const DEPT_MODULE_MAP = [
        'PRODUCTION' => [
            'production', 'sellers', 'production_purchases', 'damages', 'catalog', 'programs', 'cutting', 'wastage',
            'stitching', 'stitching_wastage', 'labels', 'qc', 'inventory', 'production_expenses',
            'production_billing', 'production_returns', 'batches', 'outward', 'production_stock_requests',
            'warehouse_rework', 'reports_production'
        ],
        'WAREHOUSE'  => [
            'warehouse', 'inward', 'stock', 'wh_sellers', 'wh_catalog', 'wh_labels', 'purchases', 'purchases_returns', 'allocation',
            'allocation_outward', 'damage', 'returns', 'shop_requests',
            'warehouse_expenses', 'sales_channels',
            'reports_warehouse', 'reports_purchase', 'reports_inventory'
        ],
        'WHATSAPP'   => [
            'whatsapp_dashboard', 'whatsapp_inward', 'whatsapp_inventory', 'whatsapp_returned_products',
            'whatsapp_orders', 'whatsapp_damage', 'whatsapp_returns', 'whatsapp_return_to_wh', 'whatsapp_expenses',
            'reports_whatsapp', 'reports_online'
        ],
        'WEBSITE'    => [
            'website_dashboard', 'website_orders', 'website_inward', 'website_inventory', 'website_damage', 'website_returns', 'website_return_to_wh', 'website_customers', 'website_expenses',
            'reports_website', 'reports_online'
        ],
        'SHOP'       => [
            'shop', 'shop_cash', 'shop_inward', 'shop_stock', 'shop_damage', 'pos', 'sales',
            'customers', 'shop_returns', 'return_to_warehouse', 'reports_shop'
        ],
    ];

    /**
     * Get all active roles with their detected department scopes based on assigned permissions.
     */
    public static function getAllWithDepartmentScope(bool $onlyActive = true): array {
        $sql = "SELECT * FROM roles WHERE id != 1";
        if ($onlyActive) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY name ASC";
        $roles = Database::fetchAll($sql);

        foreach ($roles as &$role) {
            $roleId = (int)$role['id'];
            $permModules = Database::fetchAll(
                "SELECT DISTINCT p.module FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = ?",
                [$roleId]
            );
            $modules = array_column($permModules, 'module');

            $matchedDepts = [];
            foreach (self::DEPT_MODULE_MAP as $deptName => $deptMods) {
                if (!empty(array_intersect($modules, $deptMods))) {
                    $matchedDepts[] = $deptName;
                }
            }

            if (empty($matchedDepts)) {
                $scope = 'ALL';
            } elseif (count($matchedDepts) === 1) {
                $scope = $matchedDepts[0];
            } else {
                $scope = 'ALL';
            }

            $role['departments'] = $matchedDepts;
            $role['primary_department'] = $scope;
            $role['modules_count'] = count($modules);
        }

        return $roles;
    }

    /**
     * Check if a role is compatible with a given department.
     */
    public static function isRoleCompatibleWithDepartment(int $roleId, string $department): bool {
        if ($roleId === 1) return true;
        $department = strtoupper($department);
        if ($department === 'GENERAL' || $department === 'ALL') return true;

        $deptMods = self::DEPT_MODULE_MAP[$department] ?? [];
        if (empty($deptMods)) return true;

        $count = (int)(Database::fetchOne(
            "SELECT COUNT(p.id) as cnt
             FROM role_permissions rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ? AND p.module IN (" . implode(',', array_fill(0, count($deptMods), '?')) . ")",
            array_merge([$roleId], $deptMods)
        )['cnt'] ?? 0);

        if ($count > 0) return true;

        $allDeptMods = array_merge(
            self::DEPT_MODULE_MAP['PRODUCTION'],
            self::DEPT_MODULE_MAP['WAREHOUSE'],
            self::DEPT_MODULE_MAP['WHATSAPP'],
            self::DEPT_MODULE_MAP['WEBSITE'],
            self::DEPT_MODULE_MAP['SHOP']
        );
        $otherDeptsCount = (int)(Database::fetchOne(
            "SELECT COUNT(p.id) as cnt
             FROM role_permissions rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ? AND p.module IN (" . implode(',', array_fill(0, count($allDeptMods), '?')) . ")",
            array_merge([$roleId], $allDeptMods)
        )['cnt'] ?? 0);

        return ($otherDeptsCount === 0);
    }

    /**
     * Filter permission IDs so that only permissions allowed for the given department are retained.
     */
    public static function filterPermissionsForDepartment(array $permissionIds, string $department): array {
        $department = strtoupper($department);
        if (empty($permissionIds) || $department === 'ALL' || $department === 'GENERAL') {
            return array_values(array_filter(array_map('intval', $permissionIds)));
        }

        $allDeptMap = self::DEPT_MODULE_MAP;
        if (!isset($allDeptMap[$department])) {
            return array_values(array_filter(array_map('intval', $permissionIds)));
        }

        $moduleDepartments = [];
        foreach ($allDeptMap as $dept => $mods) {
            foreach ($mods as $mod) {
                $moduleDepartments[$mod][] = $dept;
            }
        }

        // Fetch modules for the given permission IDs
        $cleanIds = array_values(array_filter(array_map('intval', $permissionIds)));
        if (empty($cleanIds)) return [];

        $inClause = implode(',', array_fill(0, count($cleanIds), '?'));
        $rows = Database::fetchAll(
            "SELECT id, module FROM permissions WHERE id IN ($inClause)",
            $cleanIds
        );

        $allowedIds = [];
        foreach ($rows as $row) {
            $moduleDepts = $moduleDepartments[$row['module']] ?? [];
            if ($moduleDepts && !in_array($department, $moduleDepts, true)) {
                continue;
            }
            $allowedIds[] = (int)$row['id'];
        }

        return $allowedIds;
    }

    /**
     * Get all active roles.
     */
    public static function getActive(): array {
        return self::getAllWithDepartmentScope(true);
    }

    /**
     * Find role by ID.
     */
    public static function findById(int $id): ?array {
        return Database::fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
    }

    /**
     * Create a new role.
     */
    public static function create(array $data): int {
        Database::execute(
            "INSERT INTO roles (name, slug, description, is_active, created_at) VALUES (?, ?, ?, 1, NOW())",
            [$data['name'], $data['slug'], $data['description'] ?? '']
        );
        return (int) Database::lastInsertId();
    }

    /**
     * Update a role.
     */
    public static function update(int $id, array $data): void {
        Database::execute(
            "UPDATE roles SET name = ?, slug = ?, description = ?, is_active = ? WHERE id = ?",
            [$data['name'], $data['slug'], $data['description'] ?? '', $data['is_active'] ?? 1, $id]
        );
    }

    /**
     * Get map of role_id => [permission_id_1, permission_id_2, ...] for all roles.
     */
    public static function getAllRolePermissionsMap(): array {
        $rows = Database::fetchAll("SELECT role_id, permission_id FROM role_permissions");
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['role_id']][] = (int)$row['permission_id'];
        }
        return $map;
    }

    /**
     * Ensure all required module & submodule permissions exist in the database.
     * Automatically self-heals schemas across all environments (Local, Staging, Live/Hostinger).
     */
    public static function ensureRequiredPermissions(): void {
        static $ensured = false;
        if ($ensured) return;
        $ensured = true;

        $required = [
            ['production_purchases', 'view', 'View Production Purchases & Supplier Bills', 'production_purchases.view', 'sellers'],
            ['production_purchases', 'create', 'Create Production Purchase Record', 'production_purchases.create', 'sellers'],
            ['production_purchases', 'edit', 'Edit Production Purchase & Invoices', 'production_purchases.edit', 'sellers'],
            ['production_purchases', 'delete', 'Delete Production Purchase Record', 'production_purchases.delete', 'sellers'],

            ['production_billing', 'view', 'View Production Sales Invoices & Bills', 'production_billing.view', 'production'],
            ['production_billing', 'create', 'Create Production Billing Invoices', 'production_billing.create', 'production'],
            ['production_billing', 'edit', 'Edit Production Invoices & Pricing', 'production_billing.edit', 'production'],
            ['production_billing', 'delete', 'Delete Production Invoices & Cancel Bills', 'production_billing.delete', 'production'],

            ['production_returns', 'view', 'View Production Customer Returns & Exchanges', 'production_returns.view', 'production'],
            ['production_returns', 'create', 'Process Return Items & Credit Notes', 'production_returns.create', 'production'],
            ['production_returns', 'edit', 'Edit Return Status & Restock Orders', 'production_returns.edit', 'production'],
            ['production_returns', 'delete', 'Cancel & Delete Production Returns', 'production_returns.delete', 'production'],

            ['production_stock_requests', 'view', 'View Warehouse Requisitions & Indents', 'production_stock_requests.view', 'production'],
            ['production_stock_requests', 'create', 'Create Stock Request Indents', 'production_stock_requests.create', 'production'],
            ['production_stock_requests', 'edit', 'Edit & Approve Requisition Quantities', 'production_stock_requests.edit', 'production'],
            ['production_stock_requests', 'delete', 'Cancel & Reject Requisitions', 'production_stock_requests.delete', 'production'],

            ['asset_register', 'view', 'View Asset Register & Machinery Details', 'asset_register.view', 'assets'],
            ['asset_register', 'create', 'Add New Machinery & Hardware Assets', 'asset_register.create', 'assets'],
            ['asset_register', 'edit', 'Edit Asset Specifications & Valuation', 'asset_register.edit', 'assets'],
            ['asset_register', 'delete', 'Archive & Delete Equipment Assets', 'asset_register.delete', 'assets'],

            ['asset_dashboard', 'view', 'View Asset Overview KPIs & Valuation', 'asset_dashboard.view', 'assets'],
            ['asset_dashboard', 'create', 'Create Asset Widgets & Custom KPIs', 'asset_dashboard.create', 'assets'],
            ['asset_dashboard', 'edit', 'Edit Asset Valuation Metrics', 'asset_dashboard.edit', 'assets'],
            ['asset_dashboard', 'delete', 'Delete Custom Valuation Metrics', 'asset_dashboard.delete', 'assets'],

            ['whatsapp_returned_products', 'view', 'View WhatsApp Returned Parcels & Products', 'whatsapp_returned_products.view', 'whatsapp_returns'],
            ['whatsapp_returned_products', 'create', 'Inward & Log WhatsApp Returns', 'whatsapp_returned_products.create', 'whatsapp_returns'],
            ['whatsapp_returned_products', 'edit', 'Inspect & Re-shelve WhatsApp Returns', 'whatsapp_returned_products.edit', 'whatsapp_returns'],
            ['whatsapp_returned_products', 'delete', 'Delete WhatsApp Return Audit Logs', 'whatsapp_returned_products.delete', 'whatsapp_returns'],

            ['dashboard', 'view', 'View Executive Dashboard KPIs & Analytics', 'dashboard.view', null],
            ['dashboard', 'create', 'Create infrastructure facilities (Production Units, Warehouses, Shops, Channels)', 'dashboard.create', null],
            ['dashboard', 'edit', 'Edit infrastructure facilities and dashboard configurations', 'dashboard.edit', null],
            ['dashboard', 'delete', 'Delete infrastructure facilities and records', 'dashboard.delete', null],

            ['batches', 'view', 'View Packaging Batches', 'batches.view', null],
            ['batches', 'create', 'Create Packaging Batch & Box Consolidations', 'batches.create', null],
            ['batches', 'edit', 'Edit Packaging Batches & Box Allocations', 'batches.edit', null],
            ['batches', 'delete', 'Delete or Cancel Packaging Batches', 'batches.delete', null],

            ['allocation_outward', 'view', 'View Warehouse Outward Manifests', 'allocation_outward.view', 'allocation'],
            ['allocation_outward', 'create', 'Create Outward Transport Manifest', 'allocation_outward.create', 'allocation'],
            ['allocation_outward', 'edit', 'Edit Outward Delivery Manifest & Vehicle Notes', 'allocation_outward.edit', 'allocation'],
            ['allocation_outward', 'delete', 'Delete & Cancel Outward Dispatch Record', 'allocation_outward.delete', 'allocation'],

            ['shop_inward', 'create', 'Scan, Receive & Verify Shop Inward Shipments', 'shop_inward.create', 'inward'],
            ['shop_inward', 'edit', 'Edit Shop Inward Receipts & Notes', 'shop_inward.edit', 'inward'],
            ['shop_inward', 'delete', 'Cancel & Delete Shop Inward Records', 'shop_inward.delete', 'inward'],

            // System: Audit Logs (Full CRUD)
            ['audit', 'view', 'View Audit Trail & System Activity Logs', 'audit.view', null],
            ['audit', 'create', 'Export Audit Records & Log System Events', 'audit.create', 'audit'],
            ['audit', 'edit', 'Archive, Filter & Annotate Audit Trail', 'audit.edit', 'audit'],
            ['audit', 'delete', 'Purge & Clear System Activity Logs', 'audit.delete', 'audit'],

            // System: Settings Hub (Full CRUD)
            ['settings', 'view', 'View System & Company Configurations', 'settings.view', null],
            ['settings', 'create', 'Create System Configurations & Profile Presets', 'settings.create', 'settings'],
            ['settings', 'edit', 'Update System Settings, Tax & Branding', 'settings.edit', null],
            ['settings', 'delete', 'Reset & Delete System Configurations', 'settings.delete', 'settings'],

            // System: Data Backup (Full CRUD)
            ['backup', 'view', 'View Database Snapshots & Recovery Hub', 'backup.view', null],
            ['backup', 'create', 'Generate Manual Database Backup Snapshot', 'backup.create', null],
            ['backup', 'edit', 'Restore Database Snapshots & Edit Configurations', 'backup.edit', 'backup'],
            ['backup', 'delete', 'Delete Database Backup Files & Wipe Test Data', 'backup.delete', null],

            // ── Production submodules ──────────────────────────────────────────
            ['production', 'view', 'View Production Dashboard & KPIs', 'production.view', null],
            ['production', 'create', 'Create Production Records & Work Orders', 'production.create', null],
            ['production', 'edit', 'Edit Production Records & Configurations', 'production.edit', null],
            ['production', 'delete', 'Delete Production Records', 'production.delete', null],

            ['sellers', 'view', 'View Job Workers & Vendor Ledgers', 'sellers.view', null],
            ['sellers', 'create', 'Add Job Workers & Vendor Contracts', 'sellers.create', null],
            ['sellers', 'edit', 'Edit Vendor Details & Contract Rates', 'sellers.edit', null],
            ['sellers', 'delete', 'Delete Vendor & Job Worker Records', 'sellers.delete', null],

            ['damages', 'view', 'View Production Damage Logs', 'damages.view', null],
            ['damages', 'create', 'Log Production Damages & Scrap', 'damages.create', null],
            ['damages', 'edit', 'Edit Damage Records & Scrap Recovery', 'damages.edit', null],
            ['damages', 'delete', 'Delete Production Damage Logs', 'damages.delete', null],

            ['catalog', 'view', 'View Design Catalog & BOMs', 'catalog.view', null],
            ['catalog', 'create', 'Create Style Codes & BOM Entries', 'catalog.create', null],
            ['catalog', 'edit', 'Edit Style Codes & BOM Configurations', 'catalog.edit', null],
            ['catalog', 'delete', 'Delete Catalog Entries & BOMs', 'catalog.delete', null],

            ['programs', 'view', 'View Cutting Programs & Work Orders', 'programs.view', null],
            ['programs', 'create', 'Create Cutting Programs & Size Breakdowns', 'programs.create', null],
            ['programs', 'edit', 'Edit Programs & Work Order Releases', 'programs.edit', null],
            ['programs', 'delete', 'Delete Programs & Work Orders', 'programs.delete', null],

            ['cutting', 'view', 'View Cutting & Roll Lay Records', 'cutting.view', null],
            ['cutting', 'create', 'Create Table Cuts & Bundle Records', 'cutting.create', null],
            ['cutting', 'edit', 'Edit Cutting Records & Cut Counts', 'cutting.edit', null],
            ['cutting', 'delete', 'Delete Cutting Records & Bundles', 'cutting.delete', null],

            ['wastage', 'view', 'View Wastage & Scrap Selling Records', 'wastage.view', null],
            ['wastage', 'create', 'Create Scrap Selling Tickets & Invoices', 'wastage.create', null],
            ['wastage', 'edit', 'Edit Scrap Records & Weights', 'wastage.edit', null],
            ['wastage', 'delete', 'Delete Scrap & Wastage Records', 'wastage.delete', null],

            ['stitching', 'view', 'View Stitching Lines & Bundle Allocations', 'stitching.view', null],
            ['stitching', 'create', 'Create Stitching Allocations & Piece Records', 'stitching.create', null],
            ['stitching', 'edit', 'Edit Stitching Allocations & Piece Rates', 'stitching.edit', null],
            ['stitching', 'delete', 'Delete Stitching Records & Allocations', 'stitching.delete', null],

            ['stitching_wastage', 'view', 'View Stitching Wastage & Scrap Logs', 'stitching_wastage.view', null],
            ['stitching_wastage', 'create', 'Log Thread/Trimming Scrap & Defects', 'stitching_wastage.create', null],
            ['stitching_wastage', 'edit', 'Edit Stitching Wastage Records', 'stitching_wastage.edit', null],
            ['stitching_wastage', 'delete', 'Delete Stitching Wastage Logs', 'stitching_wastage.delete', null],

            ['labels', 'view', 'View Label Batches & Print History', 'labels.view', null],
            ['labels', 'create', 'Create Label Batches & Barcode Stickers', 'labels.create', null],
            ['labels', 'edit', 'Edit Label Configurations & Batches', 'labels.edit', null],
            ['labels', 'delete', 'Delete Label Batches & Print Records', 'labels.delete', null],

            ['qc', 'view', 'View QC Inspections & Audit Results', 'qc.view', null],
            ['qc', 'create', 'Create QC Inspections & Rework Queues', 'qc.create', null],
            ['qc', 'edit', 'Edit QC Results & Rework Status', 'qc.edit', null],
            ['qc', 'delete', 'Delete QC Records & Inspection Logs', 'qc.delete', null],

            ['inventory', 'view', 'View Production Inventory & Lot Staging', 'inventory.view', null],
            ['inventory', 'create', 'Create Inventory Entries & Lot Records', 'inventory.create', null],
            ['inventory', 'edit', 'Edit Inventory Records & Bay Assignments', 'inventory.edit', null],
            ['inventory', 'delete', 'Delete Inventory Records & Staging Lots', 'inventory.delete', null],

            ['production_expenses', 'view', 'View Production Expenses & Vouchers', 'production_expenses.view', null],
            ['production_expenses', 'create', 'Create Expense Vouchers & Cost Records', 'production_expenses.create', null],
            ['production_expenses', 'edit', 'Edit Expense Records & Cost Allocations', 'production_expenses.edit', null],
            ['production_expenses', 'delete', 'Delete Expense Records & Vouchers', 'production_expenses.delete', null],

            ['outward', 'view', 'View Production Outward Challans', 'outward.view', null],
            ['outward', 'create', 'Create Delivery Challans & Dispatch Records', 'outward.create', null],
            ['outward', 'edit', 'Edit Outward Challans & Delivery Notes', 'outward.edit', null],
            ['outward', 'delete', 'Delete Outward Challans & Dispatch Records', 'outward.delete', null],

            ['warehouse_rework', 'view', 'View Warehouse Rework Queue', 'warehouse_rework.view', null],
            ['warehouse_rework', 'create', 'Create Rework Dispatch & Repair Jobs', 'warehouse_rework.create', null],
            ['warehouse_rework', 'edit', 'Edit Rework Records & Repair Status', 'warehouse_rework.edit', null],
            ['warehouse_rework', 'delete', 'Delete Rework Records', 'warehouse_rework.delete', null],

            // ── Warehouse submodules ───────────────────────────────────────────
            ['warehouse', 'view', 'View Warehouse Dashboard & Capacity', 'warehouse.view', null],
            ['warehouse', 'create', 'Create Warehouse Records & Bay Configurations', 'warehouse.create', null],
            ['warehouse', 'edit', 'Edit Warehouse Bay & Storage Configurations', 'warehouse.edit', null],
            ['warehouse', 'delete', 'Delete Warehouse Records & Bay Entries', 'warehouse.delete', null],

            ['inward', 'view', 'View Goods Receipt Notes & Inward Records', 'inward.view', null],
            ['inward', 'create', 'Create GRN & Inward Production Receipts', 'inward.create', null],
            ['inward', 'edit', 'Edit GRN & Inward Receipts', 'inward.edit', null],
            ['inward', 'delete', 'Delete GRN & Inward Records', 'inward.delete', null],

            ['stock', 'view', 'View Warehouse Stock & SKU Quantities', 'stock.view', null],
            ['stock', 'create', 'Create Stock Entries & Bin Records', 'stock.create', null],
            ['stock', 'edit', 'Edit Stock Records & Bin Assignments', 'stock.edit', null],
            ['stock', 'delete', 'Delete Stock Records & Bin Entries', 'stock.delete', null],

            ['wh_sellers', 'view', 'View Warehouse Suppliers & Vendor Ledgers', 'wh_sellers.view', null],
            ['wh_sellers', 'create', 'Add Warehouse Suppliers & Procurement Logs', 'wh_sellers.create', null],
            ['wh_sellers', 'edit', 'Edit Supplier Details & Vendor Contracts', 'wh_sellers.edit', null],
            ['wh_sellers', 'delete', 'Delete Warehouse Supplier Records', 'wh_sellers.delete', null],

            ['wh_catalog', 'view', 'View Warehouse Master SKU & Catalog', 'wh_catalog.view', null],
            ['wh_catalog', 'create', 'Create Master SKU Codes & Barcode Mappings', 'wh_catalog.create', null],
            ['wh_catalog', 'edit', 'Edit Warehouse Catalog & Pricing', 'wh_catalog.edit', null],
            ['wh_catalog', 'delete', 'Delete Warehouse Catalog Entries', 'wh_catalog.delete', null],

            ['wh_labels', 'view', 'View Warehouse QR Label Batches', 'wh_labels.view', null],
            ['wh_labels', 'create', 'Create Shelf Labels & Carton QR Stickers', 'wh_labels.create', null],
            ['wh_labels', 'edit', 'Edit Label Configurations & Print Batches', 'wh_labels.edit', null],
            ['wh_labels', 'delete', 'Delete Label Batches & QR Sticker Records', 'wh_labels.delete', null],

            ['purchases', 'view', 'View Purchase Orders & Procurement Bills', 'purchases.view', null],
            ['purchases', 'create', 'Create Purchase Orders & Supplier Invoices', 'purchases.create', null],
            ['purchases', 'edit', 'Edit Purchase Orders & Invoice Details', 'purchases.edit', null],
            ['purchases', 'delete', 'Delete Purchase Orders & Procurement Records', 'purchases.delete', null],

            ['purchases_returns', 'view', 'View Purchase Returns & Credit Notes', 'purchases_returns.view', null],
            ['purchases_returns', 'create', 'Process Purchase Returns & Exchanges', 'purchases_returns.create', null],
            ['purchases_returns', 'edit', 'Edit Return Records & Credit Notes', 'purchases_returns.edit', null],
            ['purchases_returns', 'delete', 'Delete Purchase Return Records', 'purchases_returns.delete', null],

            ['allocation', 'view', 'View Stock Allocation Plans & Demand', 'allocation.view', null],
            ['allocation', 'create', 'Create Allocation Plans & Box Picking', 'allocation.create', null],
            ['allocation', 'edit', 'Edit Allocation Plans & Distribution Schedules', 'allocation.edit', null],
            ['allocation', 'delete', 'Delete Allocation Plans & Picking Records', 'allocation.delete', null],

            ['damage', 'view', 'View Warehouse Damage Logs & Write-offs', 'damage.view', null],
            ['damage', 'create', 'Log Warehouse Damages & Salvage Records', 'damage.create', null],
            ['damage', 'edit', 'Edit Damage Records & Salvage Status', 'damage.edit', null],
            ['damage', 'delete', 'Delete Warehouse Damage Records', 'damage.delete', null],

            ['returns', 'view', 'View Warehouse Return Packages & RTO', 'returns.view', null],
            ['returns', 'create', 'Process Shop Return Packages & Restock', 'returns.create', null],
            ['returns', 'edit', 'Edit Return Records & Restock Evaluations', 'returns.edit', null],
            ['returns', 'delete', 'Delete Return Package Records', 'returns.delete', null],

            ['shop_requests', 'view', 'View Shop Replenishment Requests', 'shop_requests.view', null],
            ['shop_requests', 'create', 'Create Replenishment Indents & Authorizations', 'shop_requests.create', null],
            ['shop_requests', 'edit', 'Edit Shop Request Quantities & Priorities', 'shop_requests.edit', null],
            ['shop_requests', 'delete', 'Delete Shop Replenishment Requests', 'shop_requests.delete', null],

            ['warehouse_expenses', 'view', 'View Warehouse Expenses & Freight Charges', 'warehouse_expenses.view', null],
            ['warehouse_expenses', 'create', 'Create Warehouse Expense Vouchers', 'warehouse_expenses.create', null],
            ['warehouse_expenses', 'edit', 'Edit Warehouse Expense Records', 'warehouse_expenses.edit', null],
            ['warehouse_expenses', 'delete', 'Delete Warehouse Expense Records', 'warehouse_expenses.delete', null],

            ['sales_channels', 'view', 'View Sales Channel Connections', 'sales_channels.view', null],
            ['sales_channels', 'create', 'Create & Connect Sales Channels', 'sales_channels.create', null],
            ['sales_channels', 'edit', 'Edit Sales Channel Configurations', 'sales_channels.edit', null],
            ['sales_channels', 'delete', 'Delete & Disconnect Sales Channels', 'sales_channels.delete', null],

            // ── WhatsApp submodules ────────────────────────────────────────────
            ['whatsapp_dashboard', 'view', 'View WhatsApp Orders Dashboard & KPIs', 'whatsapp_dashboard.view', null],
            ['whatsapp_dashboard', 'create', 'Create WhatsApp Dashboard Widgets', 'whatsapp_dashboard.create', null],
            ['whatsapp_dashboard', 'edit', 'Edit WhatsApp Dashboard Configurations', 'whatsapp_dashboard.edit', null],
            ['whatsapp_dashboard', 'delete', 'Delete WhatsApp Dashboard Records', 'whatsapp_dashboard.delete', null],

            ['whatsapp_inward', 'view', 'View WhatsApp Stock Inward Records', 'whatsapp_inward.view', null],
            ['whatsapp_inward', 'create', 'Create WhatsApp Inward Receipts & GRNs', 'whatsapp_inward.create', null],
            ['whatsapp_inward', 'edit', 'Edit WhatsApp Inward Records', 'whatsapp_inward.edit', null],
            ['whatsapp_inward', 'delete', 'Delete WhatsApp Inward Records', 'whatsapp_inward.delete', null],

            ['whatsapp_inventory', 'view', 'View WhatsApp Channel Inventory', 'whatsapp_inventory.view', null],
            ['whatsapp_inventory', 'create', 'Create WhatsApp Inventory Entries', 'whatsapp_inventory.create', null],
            ['whatsapp_inventory', 'edit', 'Edit WhatsApp Inventory Allocations', 'whatsapp_inventory.edit', null],
            ['whatsapp_inventory', 'delete', 'Delete WhatsApp Inventory Records', 'whatsapp_inventory.delete', null],

            ['whatsapp_orders', 'view', 'View WhatsApp B2B Orders History', 'whatsapp_orders.view', null],
            ['whatsapp_orders', 'create', 'Create WhatsApp Orders & Packing Queues', 'whatsapp_orders.create', null],
            ['whatsapp_orders', 'edit', 'Edit WhatsApp Orders & Processing Status', 'whatsapp_orders.edit', null],
            ['whatsapp_orders', 'delete', 'Delete WhatsApp Order Records', 'whatsapp_orders.delete', null],

            ['whatsapp_damage', 'view', 'View WhatsApp Damage & Transit Logs', 'whatsapp_damage.view', null],
            ['whatsapp_damage', 'create', 'Log WhatsApp Parcel Damages & Write-offs', 'whatsapp_damage.create', null],
            ['whatsapp_damage', 'edit', 'Edit WhatsApp Damage Records', 'whatsapp_damage.edit', null],
            ['whatsapp_damage', 'delete', 'Delete WhatsApp Damage Records', 'whatsapp_damage.delete', null],

            ['whatsapp_returns', 'view', 'View WhatsApp Returns & Exchanges', 'whatsapp_returns.view', null],
            ['whatsapp_returns', 'create', 'Process WhatsApp Customer Returns', 'whatsapp_returns.create', null],
            ['whatsapp_returns', 'edit', 'Edit Return Records & Credit Notes', 'whatsapp_returns.edit', null],
            ['whatsapp_returns', 'delete', 'Delete WhatsApp Return Records', 'whatsapp_returns.delete', null],

            ['whatsapp_return_to_wh', 'view', 'View WhatsApp-to-Warehouse Returns', 'whatsapp_return_to_wh.view', null],
            ['whatsapp_return_to_wh', 'create', 'Create WhatsApp-to-Warehouse Return Records', 'whatsapp_return_to_wh.create', null],
            ['whatsapp_return_to_wh', 'edit', 'Edit WhatsApp-to-Warehouse Return Details', 'whatsapp_return_to_wh.edit', null],
            ['whatsapp_return_to_wh', 'delete', 'Delete WhatsApp-to-Warehouse Return Records', 'whatsapp_return_to_wh.delete', null],

            ['whatsapp_expenses', 'view', 'View WhatsApp Channel Expenses', 'whatsapp_expenses.view', null],
            ['whatsapp_expenses', 'create', 'Create WhatsApp Expense Vouchers', 'whatsapp_expenses.create', null],
            ['whatsapp_expenses', 'edit', 'Edit WhatsApp Expense Records', 'whatsapp_expenses.edit', null],
            ['whatsapp_expenses', 'delete', 'Delete WhatsApp Expense Records', 'whatsapp_expenses.delete', null],

            // ── Website submodules ─────────────────────────────────────────────
            ['website_dashboard', 'view', 'View Website Orders Dashboard & KPIs', 'website_dashboard.view', null],
            ['website_dashboard', 'create', 'Create Website Dashboard Configurations', 'website_dashboard.create', null],
            ['website_dashboard', 'edit', 'Edit Website Dashboard Settings', 'website_dashboard.edit', null],
            ['website_dashboard', 'delete', 'Delete Website Dashboard Records', 'website_dashboard.delete', null],

            ['website_inward', 'view', 'View Website Stock Inward Records', 'website_inward.view', null],
            ['website_inward', 'edit', 'Edit Website Inward Records & GRNs', 'website_inward.edit', null],
            ['website_inward', 'delete', 'Delete Website Inward Records', 'website_inward.delete', null],

            ['website_inventory', 'view', 'View Website Store Inventory & SKU Stock', 'website_inventory.view', null],
            ['website_inventory', 'create', 'Create Website Inventory Entries', 'website_inventory.create', null],
            ['website_inventory', 'delete', 'Delete Website Inventory Records', 'website_inventory.delete', null],

            ['website_orders', 'view', 'View Website Store Orders History', 'website_orders.view', null],
            ['website_orders', 'create', 'Create Website Order Records & Shipping', 'website_orders.create', null],

            ['website_damage', 'view', 'View Website Damage & Write-off Logs', 'website_damage.view', null],
            ['website_damage', 'create', 'Log Website Customer Defects & Damages', 'website_damage.create', null],
            ['website_damage', 'edit', 'Edit Website Damage Records', 'website_damage.edit', null],

            ['website_returns', 'view', 'View Website RTO & Return Requests', 'website_returns.view', null],
            ['website_returns', 'create', 'Process Website Customer Return Requests', 'website_returns.create', null],
            ['website_returns', 'edit', 'Edit Website Return & Refund Records', 'website_returns.edit', null],
            ['website_returns', 'delete', 'Delete Website Return Records', 'website_returns.delete', null],

            ['website_return_to_wh', 'view', 'View Website-to-Warehouse Returns', 'website_return_to_wh.view', null],
            ['website_return_to_wh', 'create', 'Create Website-to-Warehouse Return Records', 'website_return_to_wh.create', null],
            ['website_return_to_wh', 'edit', 'Edit Website-to-Warehouse Return Details', 'website_return_to_wh.edit', null],
            ['website_return_to_wh', 'delete', 'Delete Website-to-Warehouse Return Records', 'website_return_to_wh.delete', null],

            ['website_customers', 'view', 'View Website Customer Directory', 'website_customers.view', null],
            ['website_customers', 'create', 'Create Website Customer Profiles', 'website_customers.create', null],
            ['website_customers', 'edit', 'Edit Website Customer Details', 'website_customers.edit', null],
            ['website_customers', 'delete', 'Delete Website Customer Records', 'website_customers.delete', null],

            ['website_expenses', 'view', 'View Website Channel Expenses', 'website_expenses.view', null],
            ['website_expenses', 'create', 'Create Website Expense Vouchers', 'website_expenses.create', null],
            ['website_expenses', 'edit', 'Edit Website Expense Records', 'website_expenses.edit', null],
            ['website_expenses', 'delete', 'Delete Website Expense Records', 'website_expenses.delete', null],

            // ── Shop submodules ────────────────────────────────────────────────
            ['shop', 'view', 'View Shop Dashboard & Revenue KPIs', 'shop.view', null],
            ['shop', 'create', 'Create Shop Configurations & Counter Records', 'shop.create', null],
            ['shop', 'edit', 'Edit Shop Dashboard & Counter Configurations', 'shop.edit', null],
            ['shop', 'delete', 'Delete Shop Records & Counter Entries', 'shop.delete', null],

            ['shop_cash', 'view', 'View Shop Cash Register & Flow Records', 'shop_cash.view', null],
            ['shop_cash', 'create', 'Create Cash Register Openings & Drops', 'shop_cash.create', null],
            ['shop_cash', 'edit', 'Edit Cash Entries & Shift Handover Records', 'shop_cash.edit', null],
            ['shop_cash', 'delete', 'Delete Cash Register Records', 'shop_cash.delete', null],

            ['shop_inward', 'view', 'View Shop Inward / GRN Records', 'shop_inward.view', 'inward'],

            ['shop_stock', 'view', 'View Shop Floor Inventory & Rack Counts', 'shop_stock.view', null],
            ['shop_stock', 'create', 'Create Shop Stock Entries & Rack Records', 'shop_stock.create', null],
            ['shop_stock', 'edit', 'Edit Shop Stock Records & Price Adjustments', 'shop_stock.edit', null],
            ['shop_stock', 'delete', 'Delete Shop Stock Records', 'shop_stock.delete', null],

            ['shop_damage', 'view', 'View Shop Damage Logs & Write-offs', 'shop_damage.view', null],
            ['shop_damage', 'create', 'Log Shop Shelf Defects & Damaged Garments', 'shop_damage.create', null],
            ['shop_damage', 'edit', 'Edit Shop Damage Records & Salvage Status', 'shop_damage.edit', null],
            ['shop_damage', 'delete', 'Delete Shop Damage Records', 'shop_damage.delete', null],

            ['pos', 'view', 'View POS Billing History & Receipts', 'pos.view', null],
            ['pos', 'create', 'Create POS Sales Transactions & Invoices', 'pos.create', null],
            ['pos', 'edit', 'Edit POS Invoices & Payment Records', 'pos.edit', null],
            ['pos', 'delete', 'Delete POS Transactions & Void Invoices', 'pos.delete', null],

            ['sales', 'view', 'View Sold Products & Customer Receipts', 'sales.view', null],
            ['sales', 'create', 'Create Sales Records & Invoice Reprints', 'sales.create', null],
            ['sales', 'edit', 'Edit Sales Records & Invoice Details', 'sales.edit', null],
            ['sales', 'delete', 'Delete Sales Records & Receipts', 'sales.delete', null],

            ['customers', 'view', 'View Shop Customer Directory & Histories', 'customers.view', null],
            ['customers', 'create', 'Create Customer Profiles & Shopper Records', 'customers.create', null],
            ['customers', 'edit', 'Edit Customer Details & Purchase Histories', 'customers.edit', null],
            ['customers', 'delete', 'Delete Customer Profiles & Records', 'customers.delete', null],

            ['shop_returns', 'view', 'View Shop Customer Returns & Exchanges', 'shop_returns.view', null],
            ['shop_returns', 'create', 'Process Customer Return Slips & Exchanges', 'shop_returns.create', null],
            ['shop_returns', 'edit', 'Edit Return Records & Credit Notes', 'shop_returns.edit', null],
            ['shop_returns', 'delete', 'Delete Customer Return Records', 'shop_returns.delete', null],

            ['return_to_warehouse', 'view', 'View Shop-to-Warehouse Return Records', 'return_to_warehouse.view', null],
            ['return_to_warehouse', 'create', 'Create Shop-to-Warehouse Return Dispatches', 'return_to_warehouse.create', null],
            ['return_to_warehouse', 'edit', 'Edit Return-to-Warehouse Records', 'return_to_warehouse.edit', null],
            ['return_to_warehouse', 'delete', 'Delete Return-to-Warehouse Records', 'return_to_warehouse.delete', null],

            // ── Assets submodules ──────────────────────────────────────────────
            ['asset_scanner', 'view', 'View & Scan Asset QR Tags', 'asset_scanner.view', 'assets'],
            ['asset_scanner', 'create', 'Create Asset QR Scan Records', 'asset_scanner.create', 'assets'],
            ['asset_scanner', 'edit', 'Edit Asset Scan & Inspection Records', 'asset_scanner.edit', 'assets'],
            ['asset_scanner', 'delete', 'Delete Asset Scan Records', 'asset_scanner.delete', 'assets'],

            ['asset_transfers', 'view', 'View Asset Transfer & Movement Audit', 'asset_transfers.view', 'assets'],
            ['asset_transfers', 'create', 'Create Asset Transfer Records', 'asset_transfers.create', 'assets'],
            ['asset_transfers', 'edit', 'Edit Asset Transfer Details', 'asset_transfers.edit', 'assets'],
            ['asset_transfers', 'delete', 'Delete Asset Transfer Records', 'asset_transfers.delete', 'assets'],

            ['asset_maintenance', 'view', 'View Asset Maintenance & Service Tickets', 'asset_maintenance.view', 'assets'],
            ['asset_maintenance', 'create', 'Create Maintenance Tickets & Service Logs', 'asset_maintenance.create', 'assets'],
            ['asset_maintenance', 'edit', 'Edit Maintenance Records & Repair Status', 'asset_maintenance.edit', 'assets'],
            ['asset_maintenance', 'delete', 'Delete Maintenance Records & Service Logs', 'asset_maintenance.delete', 'assets'],

            ['asset_bulk_labels', 'view', 'View Asset QR Label Print Batches', 'asset_bulk_labels.view', 'assets'],
            ['asset_bulk_labels', 'create', 'Create & Print Asset QR Label Batches', 'asset_bulk_labels.create', 'assets'],
            ['asset_bulk_labels', 'edit', 'Edit Asset Label Configurations', 'asset_bulk_labels.edit', 'assets'],
            ['asset_bulk_labels', 'delete', 'Delete Asset Label Batches', 'asset_bulk_labels.delete', 'assets'],

            // ── Staff submodules ───────────────────────────────────────────────
            ['staff', 'view', 'View Staff Accounts & Employee Directory', 'staff.view', null],
            ['staff', 'create', 'Create Staff Accounts & Employee Records', 'staff.create', null],
            ['staff', 'edit', 'Edit Staff Details & Unit Assignments', 'staff.edit', null],
            ['staff', 'delete', 'Delete Staff Accounts & Employee Records', 'staff.delete', null],

            ['roles', 'view', 'View Roles & Permission Configurations', 'roles.view', null],
            ['roles', 'create', 'Create New Roles & Access Policies', 'roles.create', null],
            ['roles', 'edit', 'Edit Role Permissions & Access Rights', 'roles.edit', null],
            ['roles', 'delete', 'Delete Roles & Access Configurations', 'roles.delete', null],

            // ── System: Notifications ──────────────────────────────────────────
            ['notifications', 'view', 'View System Notifications & Alerts', 'notifications.view', null],
            ['notifications', 'create', 'Create System Notification Records', 'notifications.create', null],
            ['notifications', 'edit', 'Mark & Archive Notifications', 'notifications.edit', null],
            ['notifications', 'delete', 'Clear & Delete Notifications', 'notifications.delete', null],
        ];

        try {
            // Self-heal slugs for allocation_outward permissions
            Database::execute("UPDATE permissions SET slug = CONCAT(module, '.', action) WHERE module = 'allocation_outward' AND (slug IS NULL OR slug = '')");

            foreach ($required as $r) {
                [$mod, $act, $desc, $slug, $syncFromMod] = $r;
                $row = Database::fetchOne("SELECT id FROM permissions WHERE module = ? AND action = ?", [$mod, $act]);
                if (!$row) {
                    Database::execute(
                        "INSERT INTO permissions (module, action, description, slug, created_at) VALUES (?, ?, ?, ?, NOW())",
                        [$mod, $act, $desc, $slug]
                    );
                    $newId = (int)Database::lastInsertId();
                    if ($syncFromMod && $newId > 0) {
                        $sourcePerm = Database::fetchOne("SELECT id FROM permissions WHERE module = ? AND action = ?", [$syncFromMod, $act]);
                        if ($sourcePerm) {
                            $sourceId = (int)$sourcePerm['id'];
                            Database::execute(
                                "INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                                 SELECT role_id, ? FROM role_permissions WHERE permission_id = ?",
                                [$newId, $sourceId]
                            );
                            Database::execute(
                                "INSERT IGNORE INTO user_permissions (user_id, permission_id) 
                                 SELECT user_id, ? FROM user_permissions WHERE permission_id = ?",
                                [$newId, $sourceId]
                            );
                        }
                    }
                } else {
                    // Ensure existing permission has correct slug and role sync
                    if ($syncFromMod) {
                        $sourcePerm = Database::fetchOne("SELECT id FROM permissions WHERE module = ? AND action = ?", [$syncFromMod, $act]);
                        if ($sourcePerm) {
                            $sourceId = (int)$sourcePerm['id'];
                            Database::execute(
                                "INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                                 SELECT role_id, ? FROM role_permissions WHERE permission_id = ?",
                                [$row['id'], $sourceId]
                            );
                            Database::execute(
                                "INSERT IGNORE INTO user_permissions (user_id, permission_id) 
                                 SELECT user_id, ? FROM user_permissions WHERE permission_id = ?",
                                [$row['id'], $sourceId]
                            );
                        }
                    }
                }
            }

            // Always ensure Super Admin (1) and Admin (2) have all system module permissions
            Database::execute("
                INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id
                FROM roles r
                CROSS JOIN permissions p
                WHERE r.id IN (1, 2) AND p.module IN (
                    'allocation_outward', 'shop_inward', 'audit', 'settings', 'backup',
                    'production', 'sellers', 'damages', 'catalog', 'programs', 'cutting',
                    'wastage', 'stitching', 'stitching_wastage', 'labels', 'qc', 'inventory',
                    'production_expenses', 'outward', 'warehouse_rework',
                    'warehouse', 'inward', 'stock', 'wh_sellers', 'wh_catalog', 'wh_labels',
                    'purchases', 'purchases_returns', 'allocation', 'damage', 'returns',
                    'shop_requests', 'warehouse_expenses', 'sales_channels',
                    'whatsapp_dashboard', 'whatsapp_inward', 'whatsapp_inventory', 'whatsapp_orders',
                    'whatsapp_damage', 'whatsapp_returns', 'whatsapp_return_to_wh', 'whatsapp_expenses',
                    'website_dashboard', 'website_inward', 'website_inventory', 'website_orders',
                    'website_damage', 'website_returns', 'website_return_to_wh', 'website_customers', 'website_expenses',
                    'shop', 'shop_cash', 'shop_stock', 'shop_damage', 'pos', 'sales',
                    'customers', 'shop_returns', 'return_to_warehouse',
                    'asset_scanner', 'asset_transfers', 'asset_maintenance', 'asset_bulk_labels',
                    'staff', 'roles', 'notifications',
                    'dashboard', 'batches', 'asset_register', 'asset_dashboard', 'whatsapp_returned_products',
                    'production_purchases', 'production_billing', 'production_returns', 'production_stock_requests'
                )
            ");

            // Sync roles that have shop_inward.receive or shop_inward.verify or inward.create to have shop_inward.create
            Database::execute("
                INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT rp.role_id, p_new.id
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                CROSS JOIN permissions p_new
                WHERE p_new.module = 'shop_inward' AND p_new.action = 'create'
                  AND ((p.module = 'shop_inward' AND p.action IN ('receive', 'verify'))
                       OR (p.module = 'inward' AND p.action IN ('create', 'verify')))
            ");
        } catch (\Throwable $e) {
            // Silently ignore if database is temporarily unavailable during boot
        }
    }

    /**
     * Get all permissions.
     */
    public static function getAllPermissions(): array {
        self::ensureRequiredPermissions();
        return Database::fetchAll("SELECT * FROM permissions ORDER BY module ASC, action ASC");
    }

    /**
     * Get permissions grouped by module.
     */
    public static function getPermissionsGrouped(): array {
        $permissions = self::getAllPermissions();
        $grouped = [];
        foreach ($permissions as $p) {
            $grouped[$p['module']][] = $p;
        }
        return $grouped;
    }

    /**
     * Get structured module & submodule hierarchy with mapped permissions.
     * Ordering and naming strictly matches Admin Panel Sidebar navigation.
     */
    public static function getHierarchicalPermissions(): array {
        $allPerms = self::getAllPermissions();
        $permsMap = [];
        foreach ($allPerms as $p) {
            if (
                ($p['module'] === 'website_orders' && in_array($p['action'], ['edit', 'delete'], true))
                || ($p['module'] === 'website_inward' && $p['action'] === 'create')
                || ($p['module'] === 'website_inventory' && $p['action'] === 'edit')
                || ($p['module'] === 'website_damage' && $p['action'] === 'delete')
            ) {
                continue;
            }
            $permsMap[$p['module']][$p['action']] = $p;
        }

        $modules = [
            'dashboard' => [
                'title'      => 'Dashboard',
                'icon'       => 'bi-speedometer2',
                'badge'      => 'bg-primary-subtle text-primary border-primary',
                'department' => 'ALL',
                'submodules' => [
                    'dashboard' => [
                        'name'       => 'Dashboard',
                        'icon'       => 'bi-speedometer2',
                        'desc'       => 'High-level KPI metrics, operational graphs, and summary statistics',
                        'department' => 'ALL',
                    ],
                ]
            ],
            'production' => [
                'title'      => 'Production',
                'icon'       => 'bi-gear-wide-connected',
                'badge'      => 'bg-indigo-subtle text-indigo border-indigo',
                'department' => 'PRODUCTION',
                'submodules' => [
                    'production'                => ['name' => 'Dashboard', 'icon' => 'bi-speedometer', 'desc' => 'Plant overview, line throughput, and live tracking', 'department' => 'PRODUCTION'],
                    'sellers'                   => ['name' => 'Sellers', 'icon' => 'bi-person-badge', 'desc' => 'Job worker directory, vendor ledgers, and contract rates', 'department' => 'PRODUCTION'],
                    'production_purchases'      => ['name' => 'Purchases', 'icon' => 'bi-cart-check', 'desc' => 'Production fabric, yarn, raw material purchases and supplier invoices', 'department' => 'PRODUCTION'],
                    'damages'                   => ['name' => 'Damages', 'icon' => 'bi-exclamation-octagon', 'desc' => 'Defective fabric, floor damages, and scrap recovery logs', 'department' => 'PRODUCTION'],
                    'catalog'                   => ['name' => 'Catalog', 'icon' => 'bi-journal-bookmark', 'desc' => 'Design catalogs, style codes, and Bill of Materials (BOM)', 'department' => 'PRODUCTION'],
                    'programs'                  => ['name' => 'Programs', 'icon' => 'bi-calendar-check', 'desc' => 'Cutting programs, size breakdowns, and work order releases', 'department' => 'PRODUCTION'],
                    'cutting'                   => ['name' => 'Cutting', 'icon' => 'bi-scissors', 'desc' => 'Roll laying, table cuts, cut piece counts, and bundle creation', 'department' => 'PRODUCTION'],
                    'wastage'                   => ['name' => 'Wastage Selling', 'icon' => 'bi-recycle', 'desc' => 'Fabric scrap selling tickets, scrap weights, and scrap invoices', 'department' => 'PRODUCTION'],
                    'stitching'                 => ['name' => 'Stitching', 'icon' => 'bi-bounding-box', 'desc' => 'Line allocation, tailor piece-rate tracking, and bundle stitching', 'department' => 'PRODUCTION'],
                    'stitching_wastage'         => ['name' => 'Stitching Wastage', 'icon' => 'bi-trash3', 'desc' => 'Thread/trimming scrap, defect logs, and floor disposal', 'department' => 'PRODUCTION'],
                    'labels'                    => ['name' => 'Labels', 'icon' => 'bi-tag', 'desc' => 'Brand tags, care labels, barcode sticker print batches', 'department' => 'PRODUCTION'],
                    'qc'                        => ['name' => 'QC', 'icon' => 'bi-shield-check', 'desc' => 'AQL inspections, passed/failed piece audits, and rework queues', 'department' => 'PRODUCTION'],
                    'inventory'                 => ['name' => 'Inventory', 'icon' => 'bi-boxes', 'desc' => 'Unassigned lot staging bays and finished goods floor storage', 'department' => 'PRODUCTION'],
                    'production_expenses'       => ['name' => 'Expenses', 'icon' => 'bi-currency-rupee', 'desc' => 'Machine maintenance, repair costs, threads, needles, and daily floor expenses', 'department' => 'PRODUCTION'],
                    'production_billing'        => ['name' => 'Billing', 'icon' => 'bi-receipt-cutoff', 'desc' => 'Production client orders, sales bills, GST invoicing, and payment receipts', 'department' => 'PRODUCTION'],
                    'production_returns'        => ['name' => 'Returns & Exchanges', 'icon' => 'bi-arrow-left-right', 'desc' => 'Production client customer returns, piece exchanges, and credit notes', 'department' => 'PRODUCTION'],
                    'batches'                   => ['name' => 'Batches', 'icon' => 'bi-box-seam', 'desc' => 'Carton packing, SKU bundles, and master box packaging', 'department' => 'PRODUCTION'],
                    'outward'                   => ['name' => 'Outward', 'icon' => 'bi-truck', 'desc' => 'Delivery challans and outward dispatch to Central Warehouses', 'department' => 'PRODUCTION'],
                    'production_stock_requests' => ['name' => 'Stock Requests', 'icon' => 'bi-inbox', 'desc' => 'Warehouse stock requisitions and production fulfillment requests', 'department' => 'PRODUCTION'],
                    'warehouse_rework'          => ['name' => 'Warehouse Rework', 'icon' => 'bi-tools', 'desc' => 'Repair and dispatch damaged warehouse goods', 'department' => 'PRODUCTION'],
                ]
            ],
            'warehouse' => [
                'title'      => 'Warehouse',
                'icon'       => 'bi-buildings',
                'badge'      => 'bg-info-subtle text-info border-info',
                'department' => 'WAREHOUSE',
                'submodules' => [
                    'warehouse'          => ['name' => 'Dashboard', 'icon' => 'bi-speedometer2', 'desc' => 'Storage capacity, bay occupancy, and dispatch workflows', 'department' => 'WAREHOUSE'],
                    'inward'             => ['name' => 'Inward / GRN', 'icon' => 'bi-box-arrow-in-down', 'desc' => 'Goods receipt notes, production receipts, and seller inwarding', 'department' => 'WAREHOUSE'],
                    'stock'              => ['name' => 'Stock', 'icon' => 'bi-grid-3x3-gap', 'desc' => 'Bin/rack storage, live SKU quantities, count audits, and price editing', 'department' => 'WAREHOUSE'],
                    'wh_sellers'         => ['name' => 'Suppliers', 'icon' => 'bi-truck-front', 'desc' => 'Fabric/apparel suppliers, procurement logs, and vendor ledgers', 'department' => 'WAREHOUSE'],
                    'wh_catalog'         => ['name' => 'Catalog', 'icon' => 'bi-card-list', 'desc' => 'Master SKU codes, wholesale pricing, and barcode mappings', 'department' => 'WAREHOUSE'],
                    'wh_labels'          => ['name' => 'QR Labels', 'icon' => 'bi-qr-code', 'desc' => 'Shelf labels, bulk carton QR stickers, and barcode printing', 'department' => 'WAREHOUSE'],
                    'purchases'          => ['name' => 'Direct Purchases', 'icon' => 'bi-cart-check', 'desc' => 'Purchase orders, seller invoices, and procurement bills', 'department' => 'WAREHOUSE'],
                    'purchases_returns'  => ['name' => 'Purchases Returns & Exchanges', 'icon' => 'bi-arrow-left-right', 'desc' => 'Direct customer purchase returns, exchanges, invoice credit notes, and restock', 'department' => 'WAREHOUSE'],
                    'allocation'         => ['name' => 'Allocation', 'icon' => 'bi-diagram-3', 'desc' => 'Shop demand planning, box picking, and stock distribution', 'department' => 'WAREHOUSE'],
                    'allocation_outward' => ['name' => 'Outward', 'icon' => 'bi-truck', 'desc' => 'Vehicle loading manifests, transport LR notes, shop deliveries', 'department' => 'WAREHOUSE'],
                    'damage'             => ['name' => 'Damage', 'icon' => 'bi-shield-slash', 'desc' => 'Transit damage, water defect write-offs, and salvage storage', 'department' => 'WAREHOUSE'],
                    'returns'            => ['name' => 'Returns', 'icon' => 'bi-arrow-return-left', 'desc' => 'Shop return packages, restocking evaluation, and RTO parcels', 'department' => 'WAREHOUSE'],
                    'shop_requests'      => ['name' => 'Shop Requests', 'icon' => 'bi-inbox', 'desc' => 'Store urgent replenishment indents and picking authorizations', 'department' => 'WAREHOUSE'],
                    'warehouse_expenses' => ['name' => 'Warehouse Expenses', 'icon' => 'bi-cash-stack', 'desc' => 'Freight charges, utility bills, warehouse maintenance, and vouchers', 'department' => 'WAREHOUSE'],
                    'sales_channels'     => ['name' => 'Sales Channels', 'icon' => 'bi-broadcast', 'desc' => 'Manage warehouse sales-channel connections', 'department' => 'WAREHOUSE'],
                ]
            ],
            'whatsapp' => [
                'title'      => 'WhatsApp Orders',
                'icon'       => 'bi-whatsapp',
                'badge'      => 'bg-success-subtle text-success border-success',
                'department' => 'WHATSAPP',
                'submodules' => [
                    'whatsapp_dashboard'         => ['name' => 'Dashboard', 'icon' => 'bi-speedometer2', 'desc' => 'WhatsApp B2B sales throughput, orders summary, and channel KPIs', 'department' => 'WHATSAPP'],
                    'whatsapp_inward'            => ['name' => 'Inward / GRN', 'icon' => 'bi-box-arrow-in-down', 'desc' => 'Direct stock receipts, batch manifests, and inbound goods confirmation', 'department' => 'WHATSAPP'],
                    'whatsapp_inventory'         => ['name' => 'Inventory', 'icon' => 'bi-boxes', 'desc' => 'Dedicated channel inventory allocations, SKU balances, and bin stock', 'department' => 'WHATSAPP'],
                    'whatsapp_returned_products' => ['name' => 'Returned Products', 'icon' => 'bi-arrow-repeat', 'desc' => 'WhatsApp returned pieces, QC inspection, and re-shelving evaluation', 'department' => 'WHATSAPP'],
                    'whatsapp_orders'            => ['name' => 'Orders History', 'icon' => 'bi-clock-history', 'desc' => 'WhatsApp B2B customer orders, order processing, and packing queues', 'department' => 'WHATSAPP'],
                    'whatsapp_damage'            => ['name' => 'Damage Management', 'icon' => 'bi-exclamation-octagon', 'desc' => 'Defective parcels, transit damages, salvage logs, and gate passes', 'department' => 'WHATSAPP'],
                    'whatsapp_returns'           => ['name' => 'Returns & Exchanges', 'icon' => 'bi-arrow-left-right', 'desc' => 'Customer returns, exchanges, restocking inspections, and credit notes', 'department' => 'WHATSAPP'],
                    'whatsapp_return_to_wh'      => ['name' => 'Return to Warehouse', 'icon' => 'bi-box-arrow-up-right', 'desc' => 'Return WhatsApp channel products to the warehouse', 'department' => 'WHATSAPP'],
                    'whatsapp_expenses'          => ['name' => 'Channel Expenses', 'icon' => 'bi-cash-stack', 'desc' => 'Channel packaging costs, courier freight, petty cash, and payment vouchers', 'department' => 'WHATSAPP'],
                ]
            ],
            'website' => [
                'title'      => 'Website Orders',
                'icon'       => 'bi-globe',
                'badge'      => 'bg-info-subtle text-info border-info',
                'department' => 'WEBSITE',
                'submodules' => [
                    'website_dashboard' => ['name' => 'Dashboard', 'icon' => 'bi-speedometer2', 'desc' => 'Online store sales volume, conversion stats, and eCommerce KPIs', 'department' => 'WEBSITE'],
                    'website_inward'    => ['name' => 'Inward / GRN', 'icon' => 'bi-box-arrow-in-down', 'desc' => 'Goods inward receipts allocated specifically for website stock inventory', 'department' => 'WEBSITE'],
                    'website_inventory' => ['name' => 'Inventory', 'icon' => 'bi-boxes', 'desc' => 'Online live store stock counts, available SKU variants, and low stock alerts', 'department' => 'WEBSITE'],
                    'website_orders'    => ['name' => 'Orders History', 'icon' => 'bi-clock-history', 'desc' => 'Direct web store orders, payment verifications, and shipping manifests', 'department' => 'WEBSITE'],
                    'website_damage'    => ['name' => 'Damage Management', 'icon' => 'bi-exclamation-octagon', 'desc' => 'Customer trial defects, warehouse damages, write-offs, and outward salvage', 'department' => 'WEBSITE'],
                    'website_returns'   => ['name' => 'Returns & Exchanges', 'icon' => 'bi-arrow-left-right', 'desc' => 'Courier RTO packages, customer return requests, and refund verifications', 'department' => 'WEBSITE'],
                    'website_return_to_wh' => ['name' => 'Return to Warehouse', 'icon' => 'bi-box-arrow-up-right', 'desc' => 'Return Website channel products to the warehouse', 'department' => 'WEBSITE'],
                    'website_customers' => ['name' => 'Customers', 'icon' => 'bi-people', 'desc' => 'Website customer directory and order history', 'department' => 'WEBSITE'],
                    'website_expenses'  => ['name' => 'Channel Expenses', 'icon' => 'bi-cash-stack', 'desc' => 'Hosting, domain fees, courier charges, gateway commissions, and vouchers', 'department' => 'WEBSITE'],
                ]
            ],
            'shop' => [
                'title'      => 'Shop',
                'icon'       => 'bi-shop',
                'badge'      => 'bg-success-subtle text-success border-success',
                'department' => 'SHOP',
                'submodules' => [
                    'shop'                => ['name' => 'Dashboard', 'icon' => 'bi-speedometer2', 'desc' => 'Store daily revenue, top seller items, and counter performance', 'department' => 'SHOP'],
                    'shop_cash'           => ['name' => 'Cash & Cash Flow', 'icon' => 'bi-cash-stack', 'desc' => 'Daily register opening, cash drops, shift handover, and expenses', 'department' => 'SHOP'],
                    'shop_inward'         => ['name' => 'Inward / GRN', 'icon' => 'bi-box-arrow-in-down', 'desc' => 'Scan carton barcodes and receive warehouse allocation shipments', 'department' => 'SHOP'],
                    'shop_stock'          => ['name' => 'Inventory', 'icon' => 'bi-columns-gap', 'desc' => 'On-floor rack counts, size/color breakdown, and price adjustments', 'department' => 'SHOP'],
                    'shop_damage'         => ['name' => 'Damage Management', 'icon' => 'bi-exclamation-octagon', 'desc' => 'Store shelf defects, damaged garments, salvage logs, and write-offs', 'department' => 'SHOP'],
                    'pos'                 => ['name' => 'POS / Billing', 'icon' => 'bi-receipt-cutoff', 'desc' => 'High-speed barcode checkout, discounts, Cash/UPI/Card billing', 'department' => 'SHOP'],
                    'sales'               => ['name' => 'Selled Products', 'icon' => 'bi-file-earmark-text', 'desc' => 'Customer receipts, invoice reprinting, and billing search', 'department' => 'SHOP'],
                    'customers'           => ['name' => 'Customer Details', 'icon' => 'bi-people', 'desc' => 'Shopper directory and purchase histories; create adds customer profiles, edit updates profiles', 'department' => 'SHOP'],
                    'shop_returns'        => ['name' => 'Customer Returns & Exchanges', 'icon' => 'bi-arrow-left-right', 'desc' => 'Customer return slips, item exchanges, and credit note refunds', 'department' => 'SHOP'],
                    'return_to_warehouse' => ['name' => 'Return to Warehouse', 'icon' => 'bi-box-arrow-up-right', 'desc' => 'Defective goods, slow-moving items, and seasonal returns to warehouse', 'department' => 'SHOP'],
                ]
            ],
            'assets' => [
                'title'      => 'Assets',
                'icon'       => 'bi-cpu',
                'badge'      => 'bg-warning-subtle text-warning border-warning',
                'department' => 'ALL',
                'submodules' => [
                    'asset_dashboard'   => ['name' => 'Overview', 'icon' => 'bi-bar-chart-fill', 'desc' => 'High-level machinery & equipment KPIs, live valuation, and asset statistics', 'department' => 'ALL'],
                    'asset_register'    => ['name' => 'Asset Register', 'icon' => 'bi-table', 'desc' => 'Machinery registry, serial codes, depreciation schedules, and maintenance history', 'department' => 'ALL'],
                    'asset_scanner'     => ['name' => 'Scan Assets', 'icon' => 'bi-qr-code-scan', 'desc' => 'Live hardware gun inspection, QR tag decoder, history lookup', 'department' => 'ALL'],
                    'asset_transfers'   => ['name' => 'Transfers', 'icon' => 'bi-arrow-left-right', 'desc' => 'Movement audit trails between Production, Warehouses, and Shops', 'department' => 'ALL'],
                    'asset_maintenance' => ['name' => 'Maintenance', 'icon' => 'bi-wrench-adjustable', 'desc' => 'Service tickets, breakdown repairs, AMC logs, technician costs', 'department' => 'ALL'],
                    'asset_bulk_labels' => ['name' => 'Print QR Labels', 'icon' => 'bi-qr-code', 'desc' => 'Multi-tag asset sticker printing studio with barcodes', 'department' => 'ALL'],
                ]
            ],
            'staff' => [
                'title'      => 'Staff',
                'icon'       => 'bi-people',
                'badge'      => 'bg-primary-subtle text-primary border-primary',
                'department' => 'ALL',
                'submodules' => [
                    'staff' => ['name' => 'Total Staffs', 'icon' => 'bi-person-lines-fill', 'desc' => 'Manage employee accounts, phone numbers, and unit assignments', 'department' => 'ALL'],
                    'roles' => ['name' => 'Role Assign / Permissions', 'icon' => 'bi-shield-lock', 'desc' => 'Configure role permissions, access rights, and module security', 'department' => 'ALL'],
                ]
            ],
            'system' => [
                'title'      => 'System',
                'icon'       => 'bi-sliders',
                'badge'      => 'bg-dark-subtle text-dark border-dark',
                'department' => 'ALL',
                'submodules' => [
                    'audit'         => ['name' => 'Audit Logs', 'icon' => 'bi-journal-text', 'desc' => 'System activity log, user IP address, and change timestamps', 'department' => 'ALL'],
                    'settings'      => ['name' => 'Settings', 'icon' => 'bi-sliders', 'desc' => 'Company profile, invoice prefixes, tax rates, and configurations', 'department' => 'ALL'],
                    'backup'        => ['name' => 'Backup', 'icon' => 'bi-database-down', 'desc' => 'Create SQL snapshots, download backups, and restore data', 'department' => 'ALL'],
                    'notifications' => ['name' => 'Notifications', 'icon' => 'bi-bell', 'desc' => 'View, read, and clear system notifications', 'department' => 'ALL'],
                ]
            ]
        ];

        // Attach resolved permissions to each submodule
        foreach ($modules as $modKey => &$modData) {
            foreach ($modData['submodules'] as $subKey => &$subData) {
                $subData['permissions'] = $permsMap[$subKey] ?? [];
                if (!isset($subData['department'])) {
                    $subData['department'] = $modData['department'] ?? 'ALL';
                }
            }
        }

        return $modules;
    }

    public static function getCrudPermissionsByAction(array $permissions): array {
        $columns = [
            'view' => [],
            'create' => [],
            'edit' => [],
            'delete' => [],
        ];

        foreach ($permissions as $action => $permission) {
            if (isset($columns[$action])) {
                $columns[$action][$action] = $permission;
            }
        }

        return $columns;
    }

    /**
     * Keep valid CRUD permission IDs and the dedicated shop stock adjustment permission.
     */
    public static function filterCrudPermissionIds(array $permissionIds): array {
        $permissionIds = array_values(array_unique(array_filter(
            array_map('intval', $permissionIds),
            static fn(int $id): bool => $id > 0
        )));
        if (empty($permissionIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
        $rows = Database::fetchAll(
            "SELECT id FROM permissions
             WHERE id IN ($placeholders)
               AND (
                    action IN ('view', 'create', 'edit', 'delete')
                    OR (module = 'shop_stock' AND action = 'adjust')
               )",
            $permissionIds
        );
        $allowedIds = array_fill_keys(array_map('intval', array_column($rows, 'id')), true);

        return array_values(array_filter(
            $permissionIds,
            static fn(int $id): bool => isset($allowedIds[$id])
        ));
    }

    /**
     * Get permission IDs for a role.
     */
    public static function getRolePermissionIds(int $roleId): array {
        $rows = Database::fetchAll(
            "SELECT permission_id FROM role_permissions WHERE role_id = ?",
            [$roleId]
        );
        return array_column($rows, 'permission_id');
    }

    /**
     * Save permissions for a role (replace all).
     */
    public static function savePermissions(int $roleId, array $permissionIds): void {
        $permissionIds = self::filterCrudPermissionIds($permissionIds);
        Database::beginTransaction();
        try {
            Database::execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
            foreach ($permissionIds as $permId) {
                Database::execute(
                    "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                    [$roleId, (int)$permId]
                );
            }
            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Count users in a role.
     */
    public static function getUserCount(int $roleId): int {
        $result = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM users WHERE role_id = ?",
            [$roleId]
        );
        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Delete a role.
     */
    public static function delete(int $id): bool {
        Database::beginTransaction();
        try {
            Database::execute("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
            Database::execute("UPDATE users SET role_id = NULL WHERE role_id = ?", [$id]);
            Database::execute("DELETE FROM roles WHERE id = ?", [$id]);
            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }
}
