<?php
/**
 * Production Outward Model
 * Handles dispatch of finished goods from production to warehouses.
 */

class ProductionOutward {

    /**
     * Find outward dispatch by ID.
     */
    public static function find(int $id): ?array {
        return Database::fetchOne(
            "SELECT po.*, 
                    pb.batch_number, pb.total_qty, pb.box_count, pb.status as batch_status,
                    pp.program_number,
                    COALESCE(p.name, pp.name) as product_name, p.code as product_code,
                    w.id as warehouse_id, w.name as warehouse_name, w.code as warehouse_code,
                    u.full_name as created_by_name
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             JOIN warehouses w ON pb.destination_warehouse_id = w.id
             LEFT JOIN users u ON po.created_by = u.id
             WHERE po.id = ?",
            [$id]
        );
    }

    /**
     * Find outward dispatch by production batch ID.
     */
    public static function findByBatchId(int $batchId): ?array {
        return Database::fetchOne(
            "SELECT po.*, 
                    pb.batch_number, pb.total_qty, pb.box_count, pb.status as batch_status,
                    pp.program_number,
                    COALESCE(p.name, pp.name) as product_name, p.code as product_code,
                    COALESCE(w.id, pw.id) as warehouse_id, COALESCE(w.name, pw.name) as warehouse_name, COALESCE(w.code, pw.code) as warehouse_code,
                    u.full_name as created_by_name
             FROM production_outwards po
             LEFT JOIN production_batches pb ON po.production_batch_id = pb.id
             LEFT JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             LEFT JOIN warehouses w ON po.warehouse_id = w.id
             LEFT JOIN warehouses pw ON pb.destination_warehouse_id = pw.id
             LEFT JOIN users u ON po.created_by = u.id
             WHERE po.production_batch_id = ?
                OR pb.id = ?
                OR EXISTS (SELECT 1 FROM production_outward_batches pob WHERE pob.production_outward_id = po.id AND pob.production_batch_id = ?)
             ORDER BY po.id DESC LIMIT 1",
            [$batchId, $batchId, $batchId]
        );
    }

    /**
     * Get all batches included in an outward dispatch (supports both single and multi-batch dispatches).
     */
    public static function getBatchesForOutward(int $outwardId): array {
        $batches = Database::fetchAll(
            "SELECT pb.*, pp.program_number, COALESCE(p.name, pp.name, 'Finished Garment') as product_name, p.code as product_code,
                    w.name as warehouse_name, w.code as warehouse_code,
                    pob.total_qty as outward_batch_qty, pob.box_count as outward_box_count
             FROM production_outward_batches pob
             JOIN production_batches pb ON pob.production_batch_id = pb.id
             JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             LEFT JOIN warehouses w ON pb.destination_warehouse_id = w.id
             WHERE pob.production_outward_id = ?
             ORDER BY pb.id ASC",
            [$outwardId]
        );

        if (empty($batches)) {
            $outward = Database::fetchOne("SELECT production_batch_id FROM production_outwards WHERE id = ?", [$outwardId]);
            if (!empty($outward['production_batch_id'])) {
                $batches = Database::fetchAll(
                    "SELECT pb.*, pp.program_number, COALESCE(p.name, pp.name, 'Finished Garment') as product_name, p.code as product_code,
                            w.name as warehouse_name, w.code as warehouse_code,
                            pb.total_qty as outward_batch_qty, pb.box_count as outward_box_count
                     FROM production_batches pb
                     JOIN production_programs pp ON pb.program_id = pp.id
                     LEFT JOIN products p ON pp.product_id = p.id
                     LEFT JOIN warehouses w ON pb.destination_warehouse_id = w.id
                     WHERE pb.id = ?",
                    [(int)$outward['production_batch_id']]
                );
            }
        }

        foreach ($batches as &$b) {
            $b['items'] = ProductionBatch::getItems((int)$b['id']);
        }
        unset($b);

        return $batches;
    }

    /**
     * Find outward dispatch by outward number.
     */
    public static function findByOutwardNumber(string $outwardNumber): ?array {
        return Database::fetchOne(
            "SELECT po.*, 
                    pb.batch_number, pb.total_qty, pb.box_count, pb.status as batch_status,
                    pp.program_number,
                    COALESCE(p.name, pp.name) as product_name, p.code as product_code,
                    w.id as warehouse_id, w.name as warehouse_name, w.code as warehouse_code,
                    u.full_name as created_by_name
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             JOIN warehouses w ON pb.destination_warehouse_id = w.id
             LEFT JOIN users u ON po.created_by = u.id
             WHERE po.outward_number = ?",
            [$outwardNumber]
        );
    }

    /**
     * Get all outward records with filters.
     */
    public static function getAll(array $filters = []): array {
        $sql = "SELECT po.*, 
                       pb.batch_number, pb.total_qty, pb.box_count, pb.status as batch_status,
                       pp.program_number,
                       COALESCE(p.name, pp.name) as product_name,
                       COALESCE(w.name, pw.name) as warehouse_name,
                       u.full_name as created_by_name,
                       COALESCE((
                           SELECT GROUP_CONCAT(DISTINCT pb_sub.batch_number ORDER BY pb_sub.id SEPARATOR ', ')
                           FROM production_outward_batches pob
                           JOIN production_batches pb_sub ON pob.production_batch_id = pb_sub.id
                           WHERE pob.production_outward_id = po.id
                       ), pb.batch_number) as all_batch_numbers,
                       COALESCE((
                           SELECT COUNT(DISTINCT pob.production_batch_id)
                           FROM production_outward_batches pob
                           WHERE pob.production_outward_id = po.id
                       ), 1) as linked_batches_count
                FROM production_outwards po
                LEFT JOIN production_batches pb ON po.production_batch_id = pb.id
                LEFT JOIN production_programs pp ON pb.program_id = pp.id
                LEFT JOIN products p ON pp.product_id = p.id
                LEFT JOIN warehouses w ON po.warehouse_id = w.id
                LEFT JOIN warehouses pw ON pb.destination_warehouse_id = pw.id
                LEFT JOIN users u ON po.created_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['production_unit_id'])) {
            $sql .= " AND (pp.production_unit_id = ? OR po.production_unit_id = ?)";
            $params[] = (int)$filters['production_unit_id'];
            $params[] = (int)$filters['production_unit_id'];
        }

        if (!empty($filters['warehouse_id'])) {
            $sql .= " AND (pb.destination_warehouse_id = ? OR po.warehouse_id = ?)";
            $params[] = (int)$filters['warehouse_id'];
            $params[] = (int)$filters['warehouse_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (po.outward_number LIKE ? OR pb.batch_number LIKE ? OR po.vehicle_number LIKE ? OR w.name LIKE ? OR pw.name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search, $search]);
        }

        $sql .= " ORDER BY po.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        return Database::fetchAll($sql, $params);
    }

    /**
     * Create outward record.
     */
    public static function create(array $data): int {
        if (empty($data['outward_number'])) {
            if (class_exists('ProductionService')) {
                $data['outward_number'] = ProductionService::generateSequenceNumber('production_outwards', 'outward_number', 'OUT');
            } else {
                $data['outward_number'] = 'OUT-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));
            }
        }

        // Auto-resolve unit, warehouse, quantity from production_batches if missing
        if (!empty($data['production_batch_id']) && (empty($data['production_unit_id']) || empty($data['warehouse_id']) || empty($data['quantity']))) {
            $pb = Database::fetchOne(
                "SELECT pb.*, pp.production_unit_id 
                 FROM production_batches pb 
                 LEFT JOIN production_programs pp ON pb.program_id = pp.id 
                 WHERE pb.id = ?",
                [$data['production_batch_id']]
            );
            if ($pb) {
                if (empty($data['production_unit_id'])) $data['production_unit_id'] = $pb['production_unit_id'] ?? null;
                if (empty($data['warehouse_id'])) $data['warehouse_id'] = $pb['destination_warehouse_id'] ?? null;
                if (empty($data['quantity'])) $data['quantity'] = $pb['total_qty'] ?? ($pb['quantity'] ?? 0);
                if (empty($data['total_boxes'])) $data['total_boxes'] = $pb['box_count'] ?? 0;
            }
        }

        Database::execute(
            "INSERT INTO production_outwards 
             (production_batch_id, batch_ids, is_multi_batch, production_unit_id, warehouse_id, outward_number, quantity, total_boxes, total_quantity, dispatch_date, vehicle_number, driver_name, transporter_name, lr_number, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['production_batch_id'],
                $data['batch_ids'] ?? null,
                !empty($data['is_multi_batch']) ? 1 : 0,
                !empty($data['production_unit_id']) ? (int)$data['production_unit_id'] : null,
                !empty($data['warehouse_id']) ? (int)$data['warehouse_id'] : null,
                $data['outward_number'],
                $data['quantity'] ?? 0,
                $data['total_boxes'] ?? 0,
                $data['total_quantity'] ?? ($data['quantity'] ?? 0),
                $data['dispatch_date'] ?? date('Y-m-d H:i:s'),
                $data['vehicle_number'] ?? null,
                $data['driver_name'] ?? null,
                $data['transporter_name'] ?? null,
                $data['lr_number'] ?? null,
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
            ]
        );
        return (int)Database::lastInsertId();
    }

    /**
     * Update outward record fields.
     */
    public static function update(int $id, array $data): bool {
        $allowed = ['warehouse_id', 'vehicle_number', 'driver_name', 'driver_contact', 'transporter_name', 'lr_number', 'total_boxes', 'quantity', 'total_quantity', 'dispatch_date', 'notes', 'status'];
        $fields = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "`$f` = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $params[] = $id;
        return Database::execute("UPDATE production_outwards SET " . implode(', ', $fields) . " WHERE id = ?", $params) >= 0;
    }
}
