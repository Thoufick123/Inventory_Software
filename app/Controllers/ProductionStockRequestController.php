<?php

class ProductionStockRequestController
{
    public function index(): void
    {
        $status = trim((string)($_GET['status'] ?? ''));
        $priority = trim((string)($_GET['priority'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));

        $where = ['1=1'];
        $params = [];

        if ($status !== '') {
            $where[] = 'psr.status = ?';
            $params[] = $status;
        }

        if ($priority !== '') {
            $where[] = 'psr.priority = ?';
            $params[] = $priority;
        }

        if ($search !== '') {
            $where[] = '(psr.request_number LIKE ? OR psr.warehouse_name LIKE ? OR psr.notes LIKE ?)';
            $wild = '%' . $search . '%';
            $params = array_merge($params, [$wild, $wild, $wild]);
        }

        $requests = Database::fetchAll(
            "SELECT psr.*,
                    (SELECT COUNT(*) FROM production_stock_request_items WHERE request_id = psr.id) AS item_count
             FROM production_stock_requests psr
             WHERE " . implode(' AND ', $where) . "
             ORDER BY CASE psr.status 
                        WHEN 'PENDING' THEN 1 
                        WHEN 'APPROVED' THEN 2 
                        WHEN 'IN_PRODUCTION' THEN 3 
                        WHEN 'READY_FOR_DISPATCH' THEN 4 
                        WHEN 'FULFILLED' THEN 5 
                        ELSE 6 END,
                      psr.id DESC",
            $params
        );

        $stats = [
            'total_requests'  => count($requests),
            'pending_count'   => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM production_stock_requests WHERE status = 'PENDING'")['c'] ?? 0),
            'approved_count'  => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM production_stock_requests WHERE status = 'APPROVED'")['c'] ?? 0),
            'in_prod_count'   => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM production_stock_requests WHERE status = 'IN_PRODUCTION'")['c'] ?? 0),
            'fulfilled_count' => (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM production_stock_requests WHERE status = 'FULFILLED'")['c'] ?? 0),
            'total_requested' => (int)(Database::fetchOne("SELECT COALESCE(SUM(total_requested_pieces), 0) AS s FROM production_stock_requests")['s'] ?? 0),
            'total_fulfilled' => (int)(Database::fetchOne("SELECT COALESCE(SUM(fulfilled_pieces), 0) AS s FROM production_stock_requests")['s'] ?? 0),
        ];

        Response::view('production.stock_requests.index', [
            'pageTitle'    => 'Warehouse Stock Requisitions | Production Unit',
            'activeModule' => 'production',
            'activeMenu'   => 'prod-stock-requests',
            'requests'     => $requests,
            'stats'        => $stats,
            'status'       => $status,
            'priority'     => $priority,
            'search'       => $search,
        ]);
    }

    public function create(): void
    {
        $products = Database::fetchAll(
            "SELECT id, name, code, material, hsn_code FROM products WHERE is_active = 1 ORDER BY name ASC"
        );
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.product_id, pv.sku, s.name AS size_name, c.name AS color_name 
             FROM product_variants pv
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY pv.sku ASC"
        );

        Response::view('production.stock_requests.create', [
            'pageTitle'    => 'New Warehouse Stock Requisition | Production Unit',
            'activeModule' => 'production',
            'activeMenu'   => 'prod-stock-requests',
            'products'     => $products,
            'variants'     => $variants,
        ]);
    }

    public function store(): void
    {
        $warehouseName = trim((string)($_POST['warehouse_name'] ?? 'Central Warehouse'));
        $requestedByName = trim((string)($_POST['requested_by_name'] ?? 'Warehouse Incharge'));
        $priority = strtoupper(trim((string)($_POST['priority'] ?? 'NORMAL')));
        $requiredByDate = !empty($_POST['required_by_date']) ? $_POST['required_by_date'] : null;
        $notes = trim((string)($_POST['notes'] ?? ''));

        $items = $_POST['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            Session::setFlash('error', 'Please specify at least one product style and quantity.');
            Response::redirect(url('production/stock-requests/create'));
            return;
        }

        Database::beginTransaction();
        try {
            $totalPieces = 0;
            $itemsToInsert = [];

            foreach ($items as $it) {
                $qty = (int)($it['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $productId = (int)($it['product_id'] ?? 0);
                $variantId = !empty($it['variant_id']) ? (int)$it['variant_id'] : null;

                $prod = $productId > 0 ? Database::fetchOne("SELECT * FROM products WHERE id = ?", [$productId]) : null;
                $productName = $prod ? $prod['name'] : trim((string)($it['product_name'] ?? 'Custom Garment'));
                $sku = $prod ? $prod['code'] : 'SKU';
                $sizeName = trim((string)($it['size_name'] ?? ''));
                $colorName = trim((string)($it['color_name'] ?? ''));

                if ($variantId) {
                    $v = Database::fetchOne("SELECT pv.*, s.name AS size_name, c.name AS color_name FROM product_variants pv LEFT JOIN sizes s ON pv.size_id = s.id LEFT JOIN colors c ON pv.color_id = c.id WHERE pv.id = ?", [$variantId]);
                    if ($v) {
                        $sku = $v['sku'] ?: $sku;
                        $sizeName = $v['size_name'] ?: $sizeName;
                        $colorName = $v['color_name'] ?: $colorName;
                    }
                }

                $totalPieces += $qty;
                $itemsToInsert[] = [
                    'product_id'   => $productId ?: null,
                    'variant_id'   => $variantId,
                    'product_name' => $productName,
                    'sku'          => $sku,
                    'size_name'    => $sizeName,
                    'color_name'   => $colorName,
                    'requested_qty'=> $qty,
                ];
            }

            if ($totalPieces <= 0) {
                Database::rollBack();
                Session::setFlash('error', 'Requested quantities must be greater than zero.');
                Response::redirect(url('production/stock-requests/create'));
                return;
            }

            $count = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM production_stock_requests")['c'] ?? 0);
            $reqNumber = 'WH-REQ-' . date('Y') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

            Database::execute(
                "INSERT INTO production_stock_requests
                 (request_number, warehouse_name, requested_by_name, priority, required_by_date,
                  total_requested_pieces, fulfilled_pieces, status, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 'PENDING', ?, ?)",
                [
                    $reqNumber,
                    $warehouseName,
                    $requestedByName,
                    $priority,
                    $requiredByDate,
                    $totalPieces,
                    $notes,
                    (int)(Auth::id() ?? 0) ?: null,
                ]
            );
            $reqId = (int)Database::lastInsertId();

            foreach ($itemsToInsert as $ins) {
                Database::execute(
                    "INSERT INTO production_stock_request_items
                     (request_id, product_id, variant_id, product_name, sku, size_name, color_name, requested_qty, fulfilled_qty, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'PENDING')",
                    [
                        $reqId, $ins['product_id'], $ins['variant_id'], $ins['product_name'],
                        $ins['sku'], $ins['size_name'], $ins['color_name'], $ins['requested_qty']
                    ]
                );
            }

            Database::commit();
            Session::setFlash('success', "Stock Requisition #{$reqNumber} created for {$totalPieces} pieces.");
            Response::redirect(url('production/stock-requests/view/' . $reqId));
        } catch (Throwable $e) {
            Database::rollBack();
            error_log('ProductionStockRequestController::store error: ' . $e->getMessage());
            Session::setFlash('error', 'Error creating stock request: ' . $e->getMessage());
            Response::redirect(url('production/stock-requests/create'));
        }
    }

    public function view(string $id): void
    {
        $reqId = (int)$id;
        $req = Database::fetchOne("SELECT * FROM production_stock_requests WHERE id = ?", [$reqId]);
        if (!$req) {
            Session::setFlash('error', 'Stock requisition not found.');
            Response::redirect(url('production/stock-requests'));
            return;
        }

        $items = Database::fetchAll("SELECT * FROM production_stock_request_items WHERE request_id = ? ORDER BY id", [$reqId]);

        // Check available finished inventory in QC Passed items for each requested item
        foreach ($items as &$it) {
            $stockQuery = "SELECT COALESCE(SUM(qi.passed_qty), 0) AS total_passed
                           FROM qc_items qi
                           JOIN qc_batches qb ON qb.id = qi.qc_batch_id
                           JOIN production_programs pp ON pp.id = qb.program_id
                           WHERE 1=1";
            $stockParams = [];
            if ($it['variant_id']) {
                $stockQuery .= " AND qi.variant_id = ?";
                $stockParams[] = (int)$it['variant_id'];
            } elseif ($it['product_id']) {
                $stockQuery .= " AND pp.product_id = ?";
                $stockParams[] = (int)$it['product_id'];
            } else {
                $stockQuery .= " AND qi.passed_qty > 0 LIMIT 1";
            }

            $it['available_stock'] = (int)(Database::fetchOne($stockQuery, $stockParams)['total_passed'] ?? 0);
        }
        unset($it);

        Response::view('production.stock_requests.view', [
            'pageTitle'    => 'Requisition ' . $req['request_number'],
            'activeModule' => 'production',
            'activeMenu'   => 'prod-stock-requests',
            'req'          => $req,
            'items'        => $items,
        ]);
    }

    public function updateStatus(string $id): void
    {
        $reqId = (int)$id;
        $newStatus = strtoupper(trim((string)($_POST['status'] ?? '')));
        $fulfilledPieces = max(0, (int)($_POST['fulfilled_pieces'] ?? 0));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $validStatuses = ['PENDING', 'APPROVED', 'IN_PRODUCTION', 'READY_FOR_DISPATCH', 'FULFILLED', 'REJECTED'];
        if (!in_array($newStatus, $validStatuses, true)) {
            Session::setFlash('error', 'Invalid requisition status.');
            Response::redirect(url('production/stock-requests/view/' . $reqId));
            return;
        }

        Database::execute(
            "UPDATE production_stock_requests 
             SET status = ?, 
                 fulfilled_pieces = CASE WHEN ? > 0 THEN ? ELSE fulfilled_pieces END,
                 notes = CASE WHEN ? != '' THEN CONCAT(COALESCE(notes, ''), '\n', ?) ELSE notes END,
                 updated_at = NOW()
             WHERE id = ?",
            [$newStatus, $fulfilledPieces, $fulfilledPieces, $notes, $notes, $reqId]
        );

        Session::setFlash('success', "Stock requisition status updated to {$newStatus}.");
        Response::redirect(url('production/stock-requests/view/' . $reqId));
    }
}
