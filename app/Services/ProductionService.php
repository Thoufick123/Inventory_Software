<?php
/**
 * Production Service
 * Central business logic engine for the Production Module.
 * Enforces production lifecycle workflow, status transitions, and data integrity.
 */

require_once BASE_PATH . '/app/Models/ProductionProgram.php';
require_once BASE_PATH . '/app/Models/ProductionProgramItem.php';
require_once BASE_PATH . '/app/Models/CuttingBatch.php';
require_once BASE_PATH . '/app/Models/CuttingItem.php';
require_once BASE_PATH . '/app/Models/StitchingBatch.php';
require_once BASE_PATH . '/app/Models/StitchingItem.php';
require_once BASE_PATH . '/app/Models/LabelBatch.php';
require_once BASE_PATH . '/app/Models/LabelItem.php';
require_once BASE_PATH . '/app/Models/QCBatch.php';
require_once BASE_PATH . '/app/Models/QCItem.php';
require_once BASE_PATH . '/app/Models/ProductionBatch.php';
require_once BASE_PATH . '/app/Models/ProductionBatchItem.php';
require_once BASE_PATH . '/app/Models/ProductionOutward.php';
require_once BASE_PATH . '/app/Services/AuditService.php';

class ProductionService {

    /**
     * Generate unique sequential number with prefix.
     */
    public static function generateSequenceNumber(string $table, string $column, string $prefix): string {
        $today = date('Ymd');
        $pattern = $prefix . '-' . $today . '-%';
        
        $lastRecord = Database::fetchOne(
            "SELECT $column FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1",
            [$pattern]
        );

        if ($lastRecord && !empty($lastRecord[$column])) {
            $parts = explode('-', $lastRecord[$column]);
            $sequence = (int)end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $today, $sequence);
    }

    /**
     * Get the next continuous global sequence for fabric rolls in a given month/year.
     * Format: FB{MM}{YY}{count} (e.g., FB102601)
     */
    public static function getNextFabricRollSequence(int $qty = 1, ?string $date = null): array {
        $timestamp = !empty($date) ? strtotime($date) : time();
        $month = date('m', $timestamp);
        $year = date('y', $timestamp);
        $prefix = 'FB' . $month . $year;

        $row = Database::fetchOne(
            "SELECT MAX(end_seq) as max_seq FROM seller_rolls WHERE start_code LIKE ? OR qr_code LIKE ?",
            [$prefix . '%', $prefix . '%']
        );
        $startSeq = !empty($row['max_seq']) ? ((int)$row['max_seq'] + 1) : 1;
        $qty = max(1, $qty);
        $endSeq = $startSeq + $qty - 1;

        $startCode = sprintf('%s%02d', $prefix, $startSeq);
        $endCode = sprintf('%s%02d', $prefix, $endSeq);
        $qrCode = ($qty > 1) ? ($startCode . '-' . $endCode) : $startCode;

        $codes = [];
        for ($i = 0; $i < $qty; $i++) {
            $s = $startSeq + $i;
            $codes[] = [
                'seq'  => $s,
                'code' => sprintf('%s%02d', $prefix, $s),
            ];
        }

        return [
            'prefix'     => $prefix,
            'start_seq'  => $startSeq,
            'end_seq'    => $endSeq,
            'start_code' => $startCode,
            'end_code'   => $endCode,
            'qr_code'    => $qrCode,
            'codes'      => $codes,
        ];
    }

    /**
     * Create a complete production program with variant items.
     */
    public static function createProgram(array $programData, array $items, int $userId): int {
        Database::beginTransaction();
        try {
            if (empty($programData['program_number'])) {
                $programData['program_number'] = self::generateSequenceNumber('production_programs', 'program_number', 'PRG');
            }
            $programData['created_by'] = $userId;

            $totalPlanned = (int)($programData['planned_quantity'] ?? 0);
            if (!empty($items)) {
                $calcPlanned = 0;
                foreach ($items as $item) {
                    $calcPlanned += (int)($item['planned_quantity'] ?? 0);
                }
                if ($calcPlanned > 0) {
                    $totalPlanned = $calcPlanned;
                }
            }
            $programData['planned_quantity'] = $totalPlanned;

            $programId = ProductionProgram::create($programData);

            foreach ($items as $item) {
                if ((int)($item['planned_quantity'] ?? 0) > 0) {
                    ProductionProgramItem::create([
                        'program_id'       => $programId,
                        'variant_id'       => (int)$item['variant_id'],
                        'planned_quantity' => (int)$item['planned_quantity'],
                        'completed_quantity' => 0,
                    ]);
                }
            }

            AuditService::log('production', 'create', 'production_program', $programId, null, $programData);
            Database::commit();
            return $programId;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Update a production program and its items.
     */
    public static function updateProgram(int $id, array $programData, ?array $items = null): bool {
        Database::beginTransaction();
        try {
            $existing = ProductionProgram::find($id);
            if (!$existing) {
                throw new Exception("Program not found: $id");
            }

            if ($items !== null) {
                $totalPlanned = 0;
                foreach ($items as $item) {
                    $totalPlanned += (int)($item['planned_quantity'] ?? 0);
                }
                $programData['planned_quantity'] = $totalPlanned;

                // Replace items
                ProductionProgramItem::deleteByProgram($id);
                foreach ($items as $item) {
                    if ((int)($item['planned_quantity'] ?? 0) > 0) {
                        ProductionProgramItem::create([
                            'program_id'       => $id,
                            'variant_id'       => (int)$item['variant_id'],
                            'planned_quantity' => (int)$item['planned_quantity'],
                            'completed_quantity' => (int)($item['completed_quantity'] ?? 0),
                        ]);
                    }
                }
            }

            ProductionProgram::update($id, $programData);
            AuditService::log('production', 'edit', 'production_program', $id, $existing, $programData);

            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Approve production program.
     */
    public static function approveProgram(int $id, int $userId): bool {
        $program = ProductionProgram::find($id);
        if (!$program) {
            throw new Exception("Program not found: $id");
        }
        if ($program['status'] !== 'DRAFT') {
            throw new Exception("Only DRAFT programs can be approved.");
        }

        $result = ProductionProgram::approve($id, $userId);
        if ($result) {
            AuditService::log('production', 'approve', 'production_program', $id, ['status' => 'DRAFT'], ['status' => 'APPROVED']);
        }
        return $result;
    }

    /**
     * Create Cutting Batch from Program.
     */
    public static function createCuttingBatch(array $batchData, array $items, int $userId): int {
        Database::beginTransaction();
        try {
            $programId = (int)($batchData['program_id'] ?? 0);
            $program = ProductionProgram::find($programId);
            if (!$program) {
                throw new Exception("Production program not found.");
            }

            // Prevent duplicate cutting of the same program
            $existingCutting = Database::fetchOne(
                "SELECT id, batch_number FROM cutting_batches WHERE program_id = ?",
                [$programId]
            );
            if ($existingCutting) {
                $progNum = $program['program_number'] ?? ('#' . $programId);
                throw new Exception("Validation Error: Production Program ({$progNum}) has already been assigned to Cutting Batch {$existingCutting['batch_number']}. Duplicate cutting of the same program is not allowed.");
            }

            if (empty($batchData['batch_number'])) {
                $batchData['batch_number'] = self::generateSequenceNumber('cutting_batches', 'batch_number', 'CUT');
            }

            $batchId = CuttingBatch::create($batchData);

            foreach ($items as $item) {
                CuttingItem::create([
                    'cutting_batch_id'   => $batchId,
                    'variant_id'         => !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'category_id'        => !empty($item['category_id']) ? (int)$item['category_id'] : null,
                    'subcategory_id'     => !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null,
                    'sub_subcategory_id' => !empty($item['sub_subcategory_id']) ? (int)$item['sub_subcategory_id'] : null,
                    'catalog_hierarchy'  => $item['catalog_hierarchy'] ?? null,
                    'size_name'          => $item['size_name'] ?? null,
                    'color_name'         => $item['color_name'] ?? null,
                    'planned_qty'        => (int)($item['planned_qty'] ?? 0),
                    'cut_qty'            => (int)($item['cut_qty'] ?? 0),
                    'rejected_qty'       => (int)($item['rejected_qty'] ?? 0),
                    'wastage_qty'        => (int)($item['wastage_qty'] ?? 0),
                ]);
            }

            CuttingBatch::recalculateTotals($batchId);

            if ($program['status'] === 'DRAFT' || $program['status'] === 'APPROVED' || $program['status'] === 'SCHEDULED') {
                ProductionProgram::updateStatus($program['id'], 'IN_PROGRESS');
            }

            AuditService::log('production', 'create', 'cutting_batches', $batchId, null, $batchData);
            Database::commit();
            return $batchId;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Update Cutting Batch and Items.
     */
    public static function updateCuttingBatch(int $id, array $batchData, array $items): bool {
        Database::beginTransaction();
        try {
            $existing = CuttingBatch::find($id);
            if (!$existing) {
                throw new Exception("Cutting batch not found: $id");
            }

            CuttingBatch::update($id, $batchData);

            CuttingItem::deleteByBatch($id);
            foreach ($items as $item) {
                CuttingItem::create([
                    'cutting_batch_id'   => $id,
                    'variant_id'         => !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'category_id'        => !empty($item['category_id']) ? (int)$item['category_id'] : null,
                    'subcategory_id'     => !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null,
                    'sub_subcategory_id' => !empty($item['sub_subcategory_id']) ? (int)$item['sub_subcategory_id'] : null,
                    'catalog_hierarchy'  => $item['catalog_hierarchy'] ?? null,
                    'size_name'          => $item['size_name'] ?? null,
                    'color_name'         => $item['color_name'] ?? null,
                    'planned_qty'        => (int)($item['planned_qty'] ?? 0),
                    'cut_qty'            => (int)($item['cut_qty'] ?? 0),
                    'rejected_qty'       => (int)($item['rejected_qty'] ?? 0),
                    'wastage_qty'        => (int)($item['wastage_qty'] ?? 0),
                ]);
            }

            CuttingBatch::recalculateTotals($id);
            AuditService::log('production', 'edit', 'cutting_batches', $id, $existing, $batchData);

            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Create Stitching Batch from Cutting Output.
     */
    public static function createStitchingBatch(array $batchData, array $items, int $userId): int {
        Database::beginTransaction();
        try {
            if (empty($batchData['batch_number'])) {
                $batchData['batch_number'] = self::generateSequenceNumber('stitching_batches', 'batch_number', 'STCH');
            }

            if (!empty($batchData['cutting_batch_id'])) {
                $cuttingBatch = CuttingBatch::find((int)$batchData['cutting_batch_id']);
                if ($cuttingBatch && empty($batchData['program_id'])) {
                    $batchData['program_id'] = $cuttingBatch['program_id'];
                }
            }

            if (empty($batchData['program_id'])) {
                throw new Exception("Production Program is required.");
            }

            // Prevent duplicate stitching
            if (!empty($batchData['cutting_batch_id'])) {
                $existingStitching = Database::fetchOne(
                    "SELECT id, batch_number FROM stitching_batches WHERE cutting_batch_id = ? AND (is_active = 1 OR is_active IS NULL) AND status != 'CANCELLED'",
                    [(int)$batchData['cutting_batch_id']]
                );
                if ($existingStitching) {
                    throw new Exception("Validation Error: This Cutting Batch has already been assigned to Stitching Batch {$existingStitching['batch_number']}. Duplicate stitching is not allowed.");
                }
            }
            if (empty($batchData['cutting_batch_id']) && !empty($batchData['program_id'])) {
                $existingStitchingProg = Database::fetchOne(
                    "SELECT id, batch_number FROM stitching_batches WHERE program_id = ? AND cutting_batch_id IS NULL AND (is_active = 1 OR is_active IS NULL) AND status != 'CANCELLED'",
                    [(int)$batchData['program_id']]
                );
                if ($existingStitchingProg) {
                    throw new Exception("Validation Error: This Production Program has already been assigned to Stitching Batch {$existingStitchingProg['batch_number']}. Duplicate stitching is not allowed.");
                }
            }

            $batchId = StitchingBatch::create($batchData);

            foreach ($items as $item) {
                $itemStitchedQty = (int)($item['stitched_qty'] ?? 0);
                $itemWeight = (float)($item['weight'] ?? 0.00);
                $itemUnitWeight = isset($item['weight_per_piece']) ? (float)$item['weight_per_piece'] : ($itemStitchedQty > 0 ? round($itemWeight / $itemStitchedQty, 3) : 0.000);

                StitchingItem::create([
                    'stitching_batch_id' => $batchId,
                    'variant_id'         => !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'category_id'        => !empty($item['category_id']) ? (int)$item['category_id'] : null,
                    'subcategory_id'     => !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null,
                    'sub_subcategory_id' => !empty($item['sub_subcategory_id']) ? (int)$item['sub_subcategory_id'] : null,
                    'catalog_hierarchy'  => $item['catalog_hierarchy'] ?? null,
                    'size_name'          => $item['size_name'] ?? null,
                    'color_name'         => $item['color_name'] ?? null,
                    'received_qty'       => (int)($item['received_qty'] ?? 0),
                    'stitched_qty'       => $itemStitchedQty,
                    'weight_per_piece'   => $itemUnitWeight,
                    'weight'             => $itemWeight,
                    'base_price'         => (float)($item['base_price'] ?? 0.00),
                    'discount_price'     => (float)($item['discount_price'] ?? 0.00),
                    'rejected_qty'       => (int)($item['rejected_qty'] ?? 0),
                    'rework_qty'         => (int)($item['rework_qty'] ?? 0),
                ]);
            }

            StitchingBatch::recalculateTotals($batchId);
            AuditService::log('production', 'create', 'stitching_batch', $batchId, null, $batchData);
            Database::commit();
            return $batchId;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Update Stitching Batch.
     */
    public static function updateStitchingBatch(int $id, array $batchData, array $items): bool {
        Database::beginTransaction();
        try {
            $existing = StitchingBatch::find($id);
            if (!$existing) {
                throw new Exception("Stitching batch not found: $id");
            }

            StitchingBatch::update($id, $batchData);

            StitchingItem::deleteByBatch($id);
            foreach ($items as $item) {
                $itemStitchedQty = (int)($item['stitched_qty'] ?? 0);
                $itemWeight = (float)($item['weight'] ?? 0.00);
                $itemUnitWeight = isset($item['weight_per_piece']) ? (float)$item['weight_per_piece'] : ($itemStitchedQty > 0 ? round($itemWeight / $itemStitchedQty, 3) : 0.000);

                StitchingItem::create([
                    'stitching_batch_id' => $id,
                    'variant_id'         => !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'category_id'        => !empty($item['category_id']) ? (int)$item['category_id'] : null,
                    'subcategory_id'     => !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null,
                    'sub_subcategory_id' => !empty($item['sub_subcategory_id']) ? (int)$item['sub_subcategory_id'] : null,
                    'catalog_hierarchy'  => $item['catalog_hierarchy'] ?? null,
                    'size_name'          => $item['size_name'] ?? null,
                    'color_name'         => $item['color_name'] ?? null,
                    'received_qty'       => (int)($item['received_qty'] ?? 0),
                    'stitched_qty'       => $itemStitchedQty,
                    'weight_per_piece'   => $itemUnitWeight,
                    'weight'             => $itemWeight,
                    'base_price'         => (float)($item['base_price'] ?? 0.00),
                    'discount_price'     => (float)($item['discount_price'] ?? 0.00),
                    'rejected_qty'       => (int)($item['rejected_qty'] ?? 0),
                    'rework_qty'         => (int)($item['rework_qty'] ?? 0),
                ]);
            }

            StitchingBatch::recalculateTotals($id);
            AuditService::log('production', 'edit', 'stitching_batch', $id, $existing, $batchData);

            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Create Label Batch.
     */
    public static function createLabelBatch(array $batchData, array $items, int $userId): int {
        Database::beginTransaction();
        try {
            if (!empty($batchData['stitching_batch_id'])) {
                $existingLabel = Database::fetchOne(
                    "SELECT id, batch_number FROM label_batches WHERE stitching_batch_id = ?",
                    [(int)$batchData['stitching_batch_id']]
                );
                if ($existingLabel) {
                    throw new Exception("Validation Error: This Stitching Batch has already been assigned to Label Batch {$existingLabel['batch_number']}. Duplicate label generation is not allowed.");
                }
            }
            if (!empty($batchData['program_id'])) {
                $existingLabelProg = Database::fetchOne(
                    "SELECT id, batch_number FROM label_batches WHERE program_id = ?",
                    [(int)$batchData['program_id']]
                );
                if ($existingLabelProg) {
                    throw new Exception("Validation Error: This Production Program has already been assigned to Label Batch {$existingLabelProg['batch_number']}. Duplicate label generation is not allowed.");
                }
            }

            if (empty($batchData['batch_number'])) {
                $batchData['batch_number'] = self::generateSequenceNumber('label_batches', 'batch_number', 'LBL');
            }

            $batchId = LabelBatch::create($batchData);

            $catalogRunningSeq = [];
            foreach ($items as $item) {
                $catId = !empty($item['category_id']) ? (int)$item['category_id'] : null;
                $subcatId = !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null;
                $subSubcatId = !empty($item['sub_subcategory_id']) ? (int)$item['sub_subcategory_id'] : null;
                $catHierarchy = $item['catalog_hierarchy'] ?? null;
                $qty = (int)($item['required_qty'] ?? ($item['printed_qty'] ?? 1));

                $catKey = ($catId ?? 0) . '_' . ($subcatId ?? 0) . '_' . ($subSubcatId ?? 0);
                if (!isset($catalogRunningSeq[$catKey])) {
                    $lastDbSeq = LabelItem::getLastSequenceForCatalog($catId, $subcatId, $subSubcatId, $catHierarchy);
                    if (!empty($item['start_seq']) && (int)$item['start_seq'] > $lastDbSeq) {
                        $catalogRunningSeq[$catKey] = (int)$item['start_seq'];
                    } else {
                        $catalogRunningSeq[$catKey] = $lastDbSeq + 1;
                    }
                }

                $startSeq = (!empty($item['start_seq']) && (int)$item['start_seq'] >= $catalogRunningSeq[$catKey]) 
                    ? (int)$item['start_seq'] 
                    : $catalogRunningSeq[$catKey];
                $endSeq = (!empty($item['end_seq']) && (int)$item['end_seq'] >= $startSeq) 
                    ? (int)$item['end_seq'] 
                    : ($startSeq + max($qty, 1) - 1);
                $catalogRunningSeq[$catKey] = $endSeq + 1;

                $startCode = LabelItem::generateLabelCode($startSeq, $catHierarchy, $item);
                $endCode = LabelItem::generateLabelCode($endSeq, $catHierarchy, $item);

                LabelItem::create([
                    'label_batch_id'     => $batchId,
                    'variant_id'         => !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'category_id'        => $catId,
                    'subcategory_id'     => $subcatId,
                    'sub_subcategory_id' => $subSubcatId,
                    'catalog_hierarchy'  => $catHierarchy,
                    'size_name'          => $item['size_name'] ?? null,
                    'color_name'         => $item['color_name'] ?? null,
                    'base_price'         => (float)($item['base_price'] ?? 0.00),
                    'discount_price'     => (float)($item['discount_price'] ?? 0.00),
                    'required_qty'       => (int)($item['required_qty'] ?? 0),
                    'printed_qty'        => (int)($item['printed_qty'] ?? 0),
                    'used_qty'           => (int)($item['used_qty'] ?? 0),
                    'damaged_qty'        => (int)($item['damaged_qty'] ?? 0),
                    'start_seq'          => $startSeq,
                    'end_seq'            => $endSeq,
                    'start_code'         => $startCode,
                    'end_code'           => $endCode,
                ]);
            }

            LabelBatch::recalculateTotals($batchId);
            AuditService::log('production', 'create', 'label_batch', $batchId, null, $batchData);
            Database::commit();
            return $batchId;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Update Label Batch.
     */
    public static function updateLabelBatch(int $id, array $batchData, array $items): bool {
        Database::beginTransaction();
        try {
            $existing = LabelBatch::find($id);
            if (!$existing) {
                throw new Exception("Label batch not found: $id");
            }

            LabelBatch::update($id, $batchData);

            LabelItem::deleteByBatch($id);
            $catalogRunningSeq = [];
            foreach ($items as $item) {
                $catId = !empty($item['category_id']) ? (int)$item['category_id'] : null;
                $subcatId = !empty($item['subcategory_id']) ? (int)$item['subcategory_id'] : null;
                $subSubcatId = !empty($item['sub_subcategory_id']) ? (int)$item['sub_subcategory_id'] : null;
                $catHierarchy = $item['catalog_hierarchy'] ?? null;
                $qty = (int)($item['required_qty'] ?? ($item['printed_qty'] ?? 1));

                $catKey = ($catId ?? 0) . '_' . ($subcatId ?? 0) . '_' . ($subSubcatId ?? 0);
                if (!isset($catalogRunningSeq[$catKey])) {
                    $lastDbSeq = LabelItem::getLastSequenceForCatalog($catId, $subcatId, $subSubcatId, $catHierarchy, $id);
                    if (!empty($item['start_seq']) && (int)$item['start_seq'] > $lastDbSeq) {
                        $catalogRunningSeq[$catKey] = (int)$item['start_seq'];
                    } else {
                        $catalogRunningSeq[$catKey] = $lastDbSeq + 1;
                    }
                }

                $startSeq = (!empty($item['start_seq']) && (int)$item['start_seq'] >= $catalogRunningSeq[$catKey]) 
                    ? (int)$item['start_seq'] 
                    : $catalogRunningSeq[$catKey];
                $endSeq = (!empty($item['end_seq']) && (int)$item['end_seq'] >= $startSeq) 
                    ? (int)$item['end_seq'] 
                    : ($startSeq + max($qty, 1) - 1);
                $catalogRunningSeq[$catKey] = $endSeq + 1;

                $startCode = LabelItem::generateLabelCode($startSeq, $catHierarchy, $item);
                $endCode = LabelItem::generateLabelCode($endSeq, $catHierarchy, $item);

                LabelItem::create([
                    'label_batch_id'     => $id,
                    'variant_id'         => !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'category_id'        => $catId,
                    'subcategory_id'     => $subcatId,
                    'sub_subcategory_id' => $subSubcatId,
                    'catalog_hierarchy'  => $catHierarchy,
                    'size_name'          => $item['size_name'] ?? null,
                    'color_name'         => $item['color_name'] ?? null,
                    'base_price'         => (float)($item['base_price'] ?? 0.00),
                    'discount_price'     => (float)($item['discount_price'] ?? 0.00),
                    'required_qty'       => (int)($item['required_qty'] ?? 0),
                    'printed_qty'        => (int)($item['printed_qty'] ?? 0),
                    'used_qty'           => (int)($item['used_qty'] ?? 0),
                    'damaged_qty'        => (int)($item['damaged_qty'] ?? 0),
                    'start_seq'          => $startSeq,
                    'end_seq'            => $endSeq,
                    'start_code'         => $startCode,
                    'end_code'           => $endCode,
                ]);
            }

            LabelBatch::recalculateTotals($id);
            AuditService::log('production', 'edit', 'label_batch', $id, $existing, $batchData);

            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Delete Label Batch.
     */
    public static function deleteLabelBatch(int $id): bool {
        Database::beginTransaction();
        try {
            $existing = LabelBatch::find($id);
            if (!$existing) {
                throw new Exception("Label batch not found: $id");
            }

            LabelBatch::delete($id);
            AuditService::log('production', 'delete', 'label_batch', $id, $existing, null);

            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Create QC Batch.
     */
    public static function createQCBatch(array $batchData, array $items, int $userId): int {
        Database::beginTransaction();
        try {
            if (!empty($batchData['stitching_batch_id'])) {
                $existingQC = Database::fetchOne(
                    "SELECT id FROM qc_batches WHERE stitching_batch_id = ?",
                    [(int)$batchData['stitching_batch_id']]
                );
                if ($existingQC) {
                    throw new Exception("Validation Error: This Stitching Batch has already undergone QC Inspection (QC #{$existingQC['id']}). Duplicate QC inspection is not allowed.");
                }
            }
            if (!empty($batchData['label_batch_id'])) {
                $existingQC = Database::fetchOne(
                    "SELECT id FROM qc_batches WHERE label_batch_id = ?",
                    [(int)$batchData['label_batch_id']]
                );
                if ($existingQC) {
                    throw new Exception("Validation Error: This Label Batch has already undergone QC Inspection (QC #{$existingQC['id']}). Duplicate QC inspection is not allowed.");
                }
            }
            if (!empty($batchData['program_id'])) {
                $existingQCProg = Database::fetchOne(
                    "SELECT id FROM qc_batches WHERE program_id = ?",
                    [(int)$batchData['program_id']]
                );
                if ($existingQCProg) {
                    throw new Exception("Validation Error: This Production Program has already undergone QC Inspection (QC #{$existingQCProg['id']}). Duplicate QC inspection is not allowed.");
                }
            }

            $batchData['inspector_id'] = $batchData['inspector_id'] ?? $userId;
            $batchId = QCBatch::create($batchData);

            foreach ($items as $item) {
                QCItem::create([
                    'qc_batch_id'   => $batchId,
                    'variant_id'    => (int)$item['variant_id'],
                    'inspected_qty' => (int)($item['inspected_qty'] ?? 0),
                    'passed_qty'    => (int)($item['passed_qty'] ?? 0),
                    'rejected_qty'  => (int)($item['rejected_qty'] ?? 0),
                    'rework_qty'    => (int)($item['rework_qty'] ?? 0),
                    'defect_notes'  => $item['defect_notes'] ?? null,
                ]);
            }

            QCBatch::recalculateTotals($batchId);
            AuditService::log('production', 'create', 'qc_batch', $batchId, null, $batchData);
            Database::commit();
            return $batchId;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Inspect & Update QC Batch.
     */
    public static function updateQC(int $id, array $batchData, array $items): bool {
        Database::beginTransaction();
        try {
            $existing = QCBatch::find($id);
            if (!$existing) {
                throw new Exception("QC batch not found: $id");
            }

            QCBatch::update($id, $batchData);

            QCItem::deleteByBatch($id);
            foreach ($items as $item) {
                QCItem::create([
                    'qc_batch_id'   => $id,
                    'variant_id'    => (int)$item['variant_id'],
                    'inspected_qty' => (int)($item['inspected_qty'] ?? 0),
                    'passed_qty'    => (int)($item['passed_qty'] ?? 0),
                    'rejected_qty'  => (int)($item['rejected_qty'] ?? 0),
                    'rework_qty'    => (int)($item['rework_qty'] ?? 0),
                    'defect_notes'  => $item['defect_notes'] ?? null,
                ]);
            }

            QCBatch::recalculateTotals($id);
            AuditService::log('production', 'edit', 'qc_batch', $id, $existing, $batchData);

            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Resolve approved (and optionally rework) QR tags by QC item, including legacy label sequence tags.
     */
    private static function getApprovedQCPieceTags(int $qcBatchId, bool $includeRework = false): array {
        if ($qcBatchId <= 0) {
            return [];
        }

        $qcItems = QCBatch::getItems($qcBatchId);
        $batch = Database::fetchOne("SELECT pieces_data FROM qc_batches WHERE id = ?", [$qcBatchId]);
        $rawPieces = (string)($batch['pieces_data'] ?? '');
        $piecesData = json_decode($rawPieces, true);
        if (!is_array($piecesData)) {
            $piecesData = json_decode(html_entity_decode($rawPieces, ENT_QUOTES, 'UTF-8'), true);
        }
        if (!is_array($piecesData)) {
            $piecesData = json_decode(stripslashes($rawPieces), true);
        }

        $allowedStatuses = $includeRework ? ['APPROVED', 'REWORK'] : ['APPROVED'];
        $savedApprovedByItem = [];
        if (is_array($piecesData)) {
            foreach ($piecesData as $piece) {
                if (!is_array($piece) || !in_array(strtoupper((string)($piece['status'] ?? '')), $allowedStatuses, true)) {
                    continue;
                }
                $itemId = (int)($piece['item_id'] ?? 0);
                $tagCode = trim((string)($piece['tag_code'] ?? ''));
                if ($itemId > 0 && $tagCode !== '') {
                    $savedApprovedByItem[$itemId][] = $tagCode;
                }
            }
        }

        $approvedByItem = [];
        foreach ($qcItems as $item) {
            $itemId = (int)$item['id'];
            $tags = array_values(array_unique($savedApprovedByItem[$itemId] ?? []));
            if (empty($tags)) {
                $startCode = trim((string)($item['label_start_code'] ?? $item['tag_code'] ?? ''));
                $startSeq = (int)($item['label_start_seq'] ?? 0);
                $effectiveQty = max(0, (int)($item['passed_qty'] ?? 0)) + ($includeRework ? max(0, (int)($item['rework_qty'] ?? 0)) : 0);
                if ($startSeq > 0 && $effectiveQty > 0 && preg_match('/^(.+?)(\d+)$/', $startCode, $match)) {
                    $endSeq = (int)($item['label_end_seq'] ?? ($startSeq + $effectiveQty - 1));
                    $endSeq = min($endSeq, $startSeq + $effectiveQty - 1);
                    for ($seq = $startSeq; $seq <= $endSeq; $seq++) {
                        $tags[] = $match[1] . str_pad((string)$seq, strlen($match[2]), '0', STR_PAD_LEFT);
                    }
                }
            }
            $approvedByItem[$itemId] = [
                'variant_id' => (int)($item['variant_id'] ?? 0),
                'tags' => $tags,
            ];
        }

        return $approvedByItem;
    }

    public static function getQCPieceTagPositions(int $qcBatchId): array {
        $approvedByItem = self::getApprovedQCPieceTags($qcBatchId);
        $items = QCBatch::getItems($qcBatchId);
        $positions = [];
        $pieceNo = 0;
        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            $passedQty = max(0, (int)($item['passed_qty'] ?? 0));
            $tags = $approvedByItem[$itemId]['tags'] ?? [];
            for ($pieceIndex = 0; $pieceIndex < $passedQty; $pieceIndex++) {
                $pieceNo++;
                if (!empty($tags[$pieceIndex])) {
                    $positions[strtoupper(trim((string)$tags[$pieceIndex]))] = $pieceNo;
                }
            }
        }

        return $positions;
    }

    public static function getAvailableQCPieceTags(int $qcBatchId, bool $includeRework = false): array {
        if ($qcBatchId <= 0) {
            return [];
        }

        $approvedByItem = self::getApprovedQCPieceTags($qcBatchId, $includeRework);
        $legacyPackedByVariant = [];
        $assignedTagsByVariant = [];
        $previousRows = Database::fetchAll(
            "SELECT pbi.variant_id, pbi.quantity, pbi.piece_tags
             FROM production_batch_items pbi
             JOIN production_batches pb ON pb.id = pbi.production_batch_id
             WHERE pb.qc_batch_id = ? AND pb.status != 'CANCELLED'
             ORDER BY pb.id ASC, pbi.id ASC",
            [$qcBatchId]
        );
        foreach ($previousRows as $row) {
            $variantId = (int)$row['variant_id'];
            $tags = json_decode((string)($row['piece_tags'] ?? ''), true);
            if (is_array($tags) && !empty($tags)) {
                foreach ($tags as $tag) {
                    $tag = trim((string)$tag);
                    if ($tag !== '') {
                        $assignedTagsByVariant[$variantId][strtoupper($tag)] = true;
                    }
                }
            } else {
                $legacyPackedByVariant[$variantId] = ($legacyPackedByVariant[$variantId] ?? 0) + (int)$row['quantity'];
            }
        }

        $deletedRows = Database::fetchAll("SELECT code FROM production_deleted_label_codes");
        $deletedTags = array_fill_keys(array_map(
            static fn(array $row): string => strtoupper(trim((string)$row['code'])),
            $deletedRows
        ), true);

        $availableByItem = [];
        foreach ($approvedByItem as $itemId => $itemData) {
            $variantId = $itemData['variant_id'];
            $legacySkip = $legacyPackedByVariant[$variantId] ?? 0;
            $available = [];
            foreach ($itemData['tags'] as $tag) {
                if ($legacySkip > 0) {
                    $legacySkip--;
                    continue;
                }
                if (empty($assignedTagsByVariant[$variantId][strtoupper($tag)]) && empty($deletedTags[strtoupper($tag)])) {
                    $available[] = $tag;
                }
            }
            $legacyPackedByVariant[$variantId] = $legacySkip;
            $availableByItem[$itemId] = $available;
        }

        return $availableByItem;
    }

    public static function getDeletedQCPieceCounts(int $qcBatchId, array $deletedCodes = []): array {
        if ($qcBatchId <= 0) {
            return [];
        }

        $approvedByItem = self::getApprovedQCPieceTags($qcBatchId);
        $deletedTags = array_fill_keys(array_map(
            static fn($code): string => strtoupper(trim((string)$code)),
            $deletedCodes
        ), true);
        $deletedCounts = [];

        foreach (QCBatch::getItems($qcBatchId) as $item) {
            $itemId = (int)$item['id'];
            $tags = $approvedByItem[$itemId]['tags'] ?? [];

            $deletedCounts[$itemId] = count(array_filter(
                $tags,
                static fn(string $tag): bool => isset($deletedTags[strtoupper($tag)])
            ));
        }

        return $deletedCounts;
    }

    /**
     * Create Production Batch (Finished Goods Packing).
     */
    public static function createProductionBatch(array $batchData, array $items, int $userId): int {
        Database::beginTransaction();
        try {
            $includeRework = !empty($batchData['include_rework']);
            $primaryQcId = !empty($batchData['qc_batch_id']) ? (int)$batchData['qc_batch_id'] : 0;
            
            $additionalQcIds = [];
            if (!empty($batchData['additional_qc_batch_ids'])) {
                if (is_array($batchData['additional_qc_batch_ids'])) {
                    $additionalQcIds = array_map('intval', $batchData['additional_qc_batch_ids']);
                } else {
                    $decoded = json_decode((string)$batchData['additional_qc_batch_ids'], true);
                    if (is_array($decoded)) {
                        $additionalQcIds = array_map('intval', $decoded);
                    } else {
                        $additionalQcIds = array_map('intval', explode(',', (string)$batchData['additional_qc_batch_ids']));
                    }
                }
            }
            $allQcIds = array_values(array_filter(array_unique(array_merge($primaryQcId > 0 ? [$primaryQcId] : [], $additionalQcIds))));

            if (!empty($allQcIds)) {
                $availableTagsByItem = [];
                $qcItemsById = [];
                $totalCombinedPool = 0;
                $totalCombinedPacked = 0;

                foreach ($allQcIds as $qId) {
                    $tagsForBatch = self::getAvailableQCPieceTags($qId, $includeRework);
                    foreach ($tagsForBatch as $itemId => $tagList) {
                        $availableTagsByItem[$itemId] = array_values(array_unique(array_merge($availableTagsByItem[$itemId] ?? [], $tagList)));
                    }
                    foreach (QCBatch::getItems($qId) as $qcItem) {
                        $qcItemsById[(int)$qcItem['id']] = $qcItem;
                    }

                    $qPassed = (int)(Database::fetchOne("SELECT COALESCE(SUM(passed_qty), 0) as total FROM qc_items WHERE qc_batch_id = ?", [$qId])['total'] ?? 0);
                    if ($qPassed <= 0) {
                        $qcRow = QCBatch::find($qId);
                        $qPassed = (int)($qcRow['passed_qty'] ?? 0);
                    }

                    $qRework = 0;
                    if ($includeRework) {
                        $qRework = (int)(Database::fetchOne("SELECT COALESCE(SUM(rework_qty), 0) as total FROM qc_items WHERE qc_batch_id = ?", [$qId])['total'] ?? 0);
                        if ($qRework <= 0) {
                            $qcRow = $qcRow ?? QCBatch::find($qId);
                            $qRework = (int)($qcRow['rework_qty'] ?? 0);
                        }
                    }

                    $qPacked = (int)(Database::fetchOne(
                        "SELECT COALESCE(SUM(pbi.quantity), 0) as total
                         FROM production_batch_items pbi
                         JOIN production_batches pb ON pbi.production_batch_id = pb.id
                         WHERE (pb.qc_batch_id = ? OR pb.additional_qc_batch_ids LIKE ?) AND pb.status != 'CANCELLED'",
                        [$qId, '%' . $qId . '%']
                    )['total'] ?? 0);

                    $totalCombinedPool += ($qPassed + $qRework);
                    $totalCombinedPacked += $qPacked;
                }

                $remainingQty = max(0, $totalCombinedPool - $totalCombinedPacked);

                $requestedTags = [];
                foreach ($items as &$item) {
                    $qty = (int)($item['quantity'] ?? 0);
                    $itemId = (int)($item['qc_item_id'] ?? 0);
                    $tags = array_values(array_unique(array_filter(array_map(
                        static fn($tag) => trim((string)$tag),
                        is_array($item['piece_tags'] ?? null) ? $item['piece_tags'] : []
                    ), static fn($tag) => $tag !== '')));
                    $item['piece_tags'] = $tags;

                    if ($qty <= 0 || empty($availableTagsByItem[$itemId])) {
                        if (!empty($tags)) {
                            throw new Exception('Selected QR codes are not available for this QC item.');
                        }
                        continue;
                    }
                    if (count($tags) !== $qty) {
                        throw new Exception("Select exactly {$qty} approved QR code(s) for each packed variant.");
                    }

                    $qcItem = $qcItemsById[$itemId] ?? null;
                    if (!$qcItem || (int)($qcItem['variant_id'] ?? 0) !== (int)$item['variant_id']) {
                        throw new Exception('The selected QC item does not match the packed variant.');
                    }
                    $availableSet = array_fill_keys($availableTagsByItem[$itemId], true);
                    foreach ($tags as $tag) {
                        if (empty($availableSet[$tag]) || isset($requestedTags[$tag])) {
                            throw new Exception("QR code {$tag} is no longer available for packing. Refresh the page and select available codes.");
                        }
                        $requestedTags[$tag] = true;
                    }
                }
                unset($item);

                $requestPackingQty = 0;
                foreach ($items as $item) {
                    $requestPackingQty += (int)($item['quantity'] ?? 0);
                }

                if ($remainingQty <= 0) {
                    $batchLabels = implode(', ', array_map(fn($id) => '#' . $id, $allQcIds));
                    throw new Exception("Validation Error: All garments in selected QC Batch(es) {$batchLabels} have already been packed into finished batches.");
                }

                if ($requestPackingQty > $remainingQty) {
                    $batchLabels = implode(', ', array_map(fn($id) => '#' . $id, $allQcIds));
                    throw new Exception("Validation Error: Cannot pack {$requestPackingQty} pcs. Only {$remainingQty} pcs remain available across selected QC Batch(es) {$batchLabels}.");
                }
            }

            if (empty($batchData['batch_number'])) {
                $batchData['batch_number'] = self::generateSequenceNumber('production_batches', 'batch_number', 'BAT');
            }
            $batchData['prepared_by'] = $userId;

            $totalQty = 0;
            foreach ($items as $item) {
                $totalQty += (int)($item['quantity'] ?? 0);
            }
            $batchData['total_qty'] = $totalQty;
            if (!empty($additionalQcIds)) {
                $batchData['additional_qc_batch_ids'] = $additionalQcIds;
                $batchData['is_multi_qc'] = 1;
            }

            $batchId = ProductionBatch::create($batchData);

            foreach ($items as $item) {
                if ((int)($item['quantity'] ?? 0) > 0) {
                    ProductionBatchItem::create([
                        'production_batch_id' => $batchId,
                        'variant_id'          => (int)$item['variant_id'],
                        'quantity'            => (int)$item['quantity'],
                        'piece_tags'          => $item['piece_tags'] ?? [],
                    ]);
                }
            }

            AuditService::log('production', 'create', 'production_batch', $batchId, null, $batchData);
            Database::commit();
            return $batchId;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Approve Production Batch.
     */
    public static function approveProductionBatch(int $id, int $userId): bool {
        $batch = ProductionBatch::find($id);
        if (!$batch) {
            throw new Exception("Batch not found: $id");
        }
        if ($batch['status'] === 'DISPATCHED' || $batch['status'] === 'RECEIVED') {
            throw new Exception("Batch {$batch['batch_number']} has already been dispatched and cannot be approved again.");
        }
        if ($batch['status'] === 'APPROVED') {
            throw new Exception("Batch {$batch['batch_number']} is already approved.");
        }

        $result = ProductionBatch::approve($id, $userId);
        if ($result) {
            // Update program completed quantity
            ProductionProgram::recalculateCompletedQty((int)$batch['program_id']);
            AuditService::log('production', 'approve', 'production_batch', $id, ['status' => $batch['status']], ['status' => 'APPROVED']);
        }
        return $result;
    }

    /**
     * Create Outward Dispatch for Finished Batch(es) — supports multiple batch selection.
     */
    public static function createOutward(array $outwardData, int $userId): int {
        Database::beginTransaction();
        try {
            $batchIds = [];
            if (!empty($outwardData['production_batch_ids']) && is_array($outwardData['production_batch_ids'])) {
                foreach ($outwardData['production_batch_ids'] as $bid) {
                    $bid = (int)$bid;
                    if ($bid > 0 && !in_array($bid, $batchIds, true)) {
                        $batchIds[] = $bid;
                    }
                }
            }
            if (empty($batchIds) && !empty($outwardData['production_batch_id'])) {
                $batchIds[] = (int)$outwardData['production_batch_id'];
            }

            if (empty($batchIds)) {
                throw new Exception("Please select at least one production batch to dispatch.");
            }

            $primaryBatchId = $batchIds[0];
            $batches = [];
            $totalQuantity = 0;
            $totalBoxes = 0;
            $destinationWhId = null;
            $productionUnitId = null;

            foreach ($batchIds as $bId) {
                $batch = ProductionBatch::find($bId);
                if (!$batch) {
                    throw new Exception("Production batch #{$bId} not found.");
                }
                if ($batch['status'] === 'DISPATCHED' || $batch['status'] === 'RECEIVED') {
                    throw new Exception("Batch {$batch['batch_number']} has already been dispatched and cannot be dispatched again.");
                }
                if ($batch['status'] !== 'APPROVED') {
                    throw new Exception("Only APPROVED batches can be dispatched. Batch {$batch['batch_number']} status is: {$batch['status']}.");
                }

                // Check if outward record already exists for this batch
                $existingOutward = Database::fetchOne("
                    SELECT po.id, po.outward_number 
                    FROM production_outwards po 
                    WHERE po.production_batch_id = ? 
                       OR EXISTS (SELECT 1 FROM production_outward_batches pob WHERE pob.production_outward_id = po.id AND pob.production_batch_id = ?)
                ", [$bId, $bId]);
                if ($existingOutward) {
                    throw new Exception("Batch {$batch['batch_number']} has already been dispatched under Outward #{$existingOutward['outward_number']}.");
                }

                if ($destinationWhId === null) {
                    $destinationWhId = (int)$batch['destination_warehouse_id'];
                } elseif ($destinationWhId !== (int)$batch['destination_warehouse_id']) {
                    throw new Exception("All selected batches must be assigned to the same destination warehouse to dispatch together in one vehicle.");
                }

                if ($productionUnitId === null && !empty($batch['production_unit_id'])) {
                    $productionUnitId = (int)$batch['production_unit_id'];
                }

                $bQty = (int)($batch['total_qty'] ?: ($batch['quantity'] ?: 0));
                $bBoxes = max(1, (int)($batch['box_count'] ?: 1));
                $totalQuantity += $bQty;
                $totalBoxes += $bBoxes;
                $batches[] = $batch;
            }

            if (empty($outwardData['outward_number'])) {
                $outwardData['outward_number'] = self::generateSequenceNumber('production_outwards', 'outward_number', 'OUT');
            }

            $isMulti = count($batchIds) > 1 ? 1 : 0;
            $batchNumbers = array_column($batches, 'batch_number');
            $multiNote = $isMulti ? ('[Multi-Batch Mixed Outward: ' . implode(', ', $batchNumbers) . ' (' . $totalQuantity . ' pcs)]') : '';
            $finalNotes = trim(($outwardData['notes'] ?? '') . ($multiNote ? "\n" . $multiNote : ''));

            $insertData = [
                'production_batch_id' => $primaryBatchId,
                'batch_ids'           => json_encode($batchIds),
                'is_multi_batch'      => $isMulti,
                'warehouse_id'        => $destinationWhId,
                'production_unit_id'  => $productionUnitId,
                'quantity'            => $totalQuantity,
                'total_quantity'      => $totalQuantity,
                'total_boxes'         => $totalBoxes,
                'outward_number'      => $outwardData['outward_number'],
                'dispatch_date'       => !empty($outwardData['dispatch_date']) ? $outwardData['dispatch_date'] : date('Y-m-d H:i:s'),
                'vehicle_number'      => trim($outwardData['vehicle_number'] ?? ''),
                'driver_name'         => trim($outwardData['driver_name'] ?? ''),
                'driver_contact'      => trim($outwardData['driver_contact'] ?? ''),
                'transporter_name'    => trim($outwardData['transporter_name'] ?? ''),
                'lr_number'           => trim($outwardData['lr_number'] ?? ''),
                'notes'               => $finalNotes,
                'dispatched_by'       => $userId,
                'created_by'          => $userId,
                'status'              => 'DISPATCHED',
            ];

            $outwardId = ProductionOutward::create($insertData);

            // Record each batch in production_outward_batches and update batch status to DISPATCHED
            foreach ($batches as $b) {
                $bQty = (int)($b['total_qty'] ?: ($b['quantity'] ?: 0));
                $bBoxes = max(1, (int)($b['box_count'] ?: 1));
                Database::execute(
                    "INSERT INTO production_outward_batches (production_outward_id, production_batch_id, total_qty, box_count) VALUES (?, ?, ?, ?)",
                    [$outwardId, (int)$b['id'], $bQty, $bBoxes]
                );
                Database::execute(
                    "UPDATE production_batches SET status = 'DISPATCHED', production_outward_id = ?, updated_at = NOW() WHERE id = ?",
                    [$outwardId, (int)$b['id']]
                );
            }

            AuditService::log('production', 'create', 'production_outward', $outwardId, null, [
                'outward_number' => $outwardData['outward_number'],
                'batch_ids'      => $batchIds,
                'total_quantity' => $totalQuantity,
                'total_boxes'    => $totalBoxes,
                'is_multi_batch' => $isMulti,
            ]);

            Database::commit();
            return $outwardId;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Dispatch an Outward.
     */
    public static function dispatchOutward(int $id, int $userId): bool {
        $outward = ProductionOutward::find($id);
        if (!$outward) {
            throw new Exception("Outward record not found: $id");
        }

        ProductionBatch::updateStatus((int)$outward['production_batch_id'], 'DISPATCHED');
        AuditService::log('production', 'dispatch', 'production_outward', $id, null, ['status' => 'DISPATCHED']);
        return true;
    }

    /**
     * Get Production Dashboard KPIs.
     */
    public static function getDashboardStats(?int $unitId = null): array {
        $unitClauseProg = $unitId ? " AND production_unit_id = $unitId" : "";
        $unitClausePP = $unitId ? " AND pp.production_unit_id = $unitId" : "";

        // 1. Programs: Total & Finished
        $activePrograms = Database::fetchOne(
            "SELECT
                COUNT(*) as count,
                SUM(CASE WHEN status = 'COMPLETED' OR (planned_quantity > 0 AND completed_quantity >= planned_quantity) THEN 1 ELSE 0 END) as finished_count,
                COALESCE(SUM(planned_quantity), 0) as total_planned,
                COALESCE(SUM(completed_quantity), 0) as total_completed
             FROM production_programs
             WHERE 1=1$unitClauseProg"
        );

        // 2. Cutting Batches: Total & Finished
        $pendingCutting = Database::fetchOne(
            "SELECT
                COUNT(*) as count,
                SUM(CASE 
                    WHEN cb.status IN ('COMPLETED', 'FINISHED', 'DONE') THEN 1
                    WHEN cb.cut_qty > 0 AND (cb.planned_qty <= 0 OR cb.cut_qty >= cb.planned_qty) THEN 1
                    WHEN EXISTS (SELECT 1 FROM stitching_batches sb WHERE sb.cutting_batch_id = cb.id) THEN 1
                    ELSE 0 
                END) as finished_count,
                COALESCE(SUM(cb.planned_qty), 0) as total_planned,
                COALESCE(SUM(CASE WHEN cb.cut_qty > 0 THEN cb.cut_qty ELSE COALESCE(cb.planned_qty, 0) END), 0) as total_completed
             FROM cutting_batches cb
             LEFT JOIN production_programs pp ON cb.program_id = pp.id
             WHERE 1=1$unitClausePP"
        );

        // 3. Stitching Batches: Total & Finished
        $pendingStitching = Database::fetchOne(
            "SELECT
                COUNT(*) as count,
                SUM(CASE 
                    WHEN sb.status IN ('COMPLETED', 'FINISHED', 'DONE') THEN 1
                    WHEN COALESCE(sb.total_stitched_qty, sb.stitched_qty, 0) > 0 AND (COALESCE(sb.total_cut_qty, sb.received_qty, 0) <= 0 OR COALESCE(sb.total_stitched_qty, sb.stitched_qty, 0) >= COALESCE(sb.total_cut_qty, sb.received_qty, 0)) THEN 1
                    WHEN EXISTS (SELECT 1 FROM qc_batches qb WHERE qb.stitching_batch_id = sb.id) THEN 1
                    ELSE 0 
                END) as finished_count,
                COALESCE(SUM(COALESCE(sb.total_cut_qty, sb.received_qty, 0)), 0) as total_planned,
                COALESCE(SUM(COALESCE(sb.total_stitched_qty, sb.stitched_qty, 0)), 0) as total_completed
             FROM stitching_batches sb
             LEFT JOIN production_programs pp ON sb.program_id = pp.id
             WHERE 1=1$unitClausePP"
        );

        // 4. QC Batches: Total & Finished (Passed / Audited)
        $pendingQC = Database::fetchOne(
            "SELECT
                COUNT(*) as count,
                SUM(CASE 
                    WHEN qb.status IN ('COMPLETED', 'PASSED') THEN 1
                    WHEN (COALESCE(qb.passed_qty, 0) + COALESCE(qb.rejected_qty, 0) + COALESCE(qb.rework_qty, 0)) > 0 AND (COALESCE(qb.total_qty, 0) <= 0 OR (COALESCE(qb.passed_qty, 0) + COALESCE(qb.rejected_qty, 0) + COALESCE(qb.rework_qty, 0)) >= qb.total_qty) THEN 1
                    WHEN qb.status IN ('REWORK', 'PARTIAL') AND (COALESCE(qb.passed_qty, 0) + COALESCE(qb.rejected_qty, 0) + COALESCE(qb.rework_qty, 0)) > 0 THEN 1
                    WHEN EXISTS (SELECT 1 FROM production_batches pb WHERE pb.qc_batch_id = qb.id) THEN 1
                    ELSE 0 
                END) as finished_count,
                COALESCE(SUM(qb.total_qty), 0) as total_planned,
                COALESCE(SUM(CASE 
                    WHEN (COALESCE(qb.passed_qty, 0) + COALESCE(qb.rejected_qty, 0) + COALESCE(qb.rework_qty, 0)) > 0 
                    THEN (COALESCE(qb.passed_qty, 0) + COALESCE(qb.rejected_qty, 0) + COALESCE(qb.rework_qty, 0)) 
                    ELSE COALESCE(qb.passed_qty, 0) 
                END), 0) as total_completed
             FROM qc_batches qb
             LEFT JOIN production_programs pp ON qb.program_id = pp.id
             WHERE 1=1$unitClausePP"
        );

        // 5. Packed / Ready Dispatch Batches: Total & Finished
        $readyDispatch = Database::fetchOne(
            "SELECT
                COUNT(*) as count,
                SUM(CASE WHEN pb.status IN ('DISPATCHED', 'RECEIVED', 'COMPLETED', 'APPROVED') OR pb.box_count > 0 THEN 1 ELSE 0 END) as finished_count,
                COALESCE(SUM(pb.total_qty), 0) as total_planned,
                COALESCE(SUM(pb.total_qty), 0) as total_completed
             FROM production_batches pb
             LEFT JOIN production_programs pp ON pb.program_id = pp.id
             WHERE 1=1$unitClausePP"
        );

        // 6. Outward Dispatches: Total & Finished (Dispatched or Delivered to WH)
        $monthOutward = Database::fetchOne(
            "SELECT
                COUNT(po.id) as count,
                SUM(CASE WHEN po.status IN ('DISPATCHED', 'RECEIVED', 'DELIVERED', 'COMPLETED') THEN 1 ELSE 0 END) as finished_count,
                COALESCE(SUM(COALESCE(po.total_quantity, po.quantity, pb.total_qty, 0)), 0) as total_planned,
                COALESCE(SUM(CASE WHEN po.status IN ('DISPATCHED', 'RECEIVED', 'DELIVERED', 'COMPLETED') THEN COALESCE(po.total_quantity, po.quantity, pb.total_qty, 0) ELSE 0 END), 0) as total_completed
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             LEFT JOIN production_programs pp ON pb.program_id = pp.id
             WHERE 1=1$unitClausePP"
        );

        // Active programs not yet completed against their planned quantity
        $recentPrograms = Database::fetchAll(
            "SELECT pp.*, COALESCE(p.name, pp.name, 'General Apparel') as product_name, p.code as product_code, pu.name as unit_name
             FROM production_programs pp
             LEFT JOIN products p ON pp.product_id = p.id
             JOIN production_units pu ON pp.production_unit_id = pu.id
             WHERE pp.status IN ('APPROVED', 'SCHEDULED', 'IN_PROGRESS')
               AND (pp.is_active IS NULL OR pp.is_active = 1)
               AND (COALESCE(pp.planned_quantity, 0) <= 0 OR COALESCE(pp.completed_quantity, 0) < COALESCE(pp.planned_quantity, 0))$unitClausePP
             ORDER BY pp.created_at DESC LIMIT 6"
        );

        // Recent QC inspections
        $recentQC = Database::fetchAll(
            "SELECT qb.*, pp.program_number, COALESCE(p.name, pp.name, 'General Apparel') as product_name, u.full_name as inspector_name
             FROM qc_batches qb
             JOIN production_programs pp ON qb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             LEFT JOIN users u ON qb.inspector_id = u.id
             WHERE 1=1$unitClausePP
             ORDER BY qb.created_at DESC LIMIT 5"
        );

        // Recent Outward dispatches
        $recentOutwards = Database::fetchAll(
            "SELECT po.*, pb.batch_number, pb.total_qty, w.name as warehouse_name
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             LEFT JOIN production_programs pp ON pb.program_id = pp.id
             JOIN warehouses w ON pb.destination_warehouse_id = w.id
             WHERE 1=1$unitClausePP
             ORDER BY po.created_at DESC LIMIT 5"
        );

        // QC Quality Breakdown
        $qcBreakdown = Database::fetchOne(
            "SELECT 
                COALESCE(SUM(qb.passed_qty), 0) as total_passed,
                COALESCE(SUM(qb.rework_qty), 0) as total_rework,
                COALESCE(SUM(qb.rejected_qty), 0) as total_rejected,
                COALESCE(SUM(qb.total_qty), 0) as total_inspected
             FROM qc_batches qb
             LEFT JOIN production_programs pp ON qb.program_id = pp.id
             WHERE 1=1$unitClausePP"
        );

        // Daily Throughput Trends (Last 7 Days)
        $dailyTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dailyTrends[$date] = [
                'date'      => $date,
                'label'     => date('d M', strtotime("-$i days")),
                'cut'       => 0,
                'stitched'  => 0,
                'qc_passed' => 0,
                'outward'   => 0,
            ];
        }

        // Daily Cutting Quantities
        $cutRows = Database::fetchAll(
            "SELECT DATE(cb.created_at) as log_date, COALESCE(SUM(cb.cut_qty), SUM(cb.planned_qty), 0) as qty
             FROM cutting_batches cb
             LEFT JOIN production_programs pp ON cb.program_id = pp.id
             WHERE cb.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)$unitClausePP
             GROUP BY DATE(cb.created_at)"
        );
        foreach ($cutRows as $r) {
            if (isset($dailyTrends[$r['log_date']])) {
                $dailyTrends[$r['log_date']]['cut'] = (int)$r['qty'];
            }
        }

        // Daily Stitching Quantities
        $stitchRows = Database::fetchAll(
            "SELECT DATE(sb.created_at) as log_date, COALESCE(SUM(sb.stitched_qty), SUM(sb.received_qty), 0) as qty
             FROM stitching_batches sb
             LEFT JOIN production_programs pp ON sb.program_id = pp.id
             WHERE sb.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)$unitClausePP
             GROUP BY DATE(sb.created_at)"
        );
        foreach ($stitchRows as $r) {
            if (isset($dailyTrends[$r['log_date']])) {
                $dailyTrends[$r['log_date']]['stitched'] = (int)$r['qty'];
            }
        }

        // Daily QC Passed
        $qcRows = Database::fetchAll(
            "SELECT DATE(qb.created_at) as log_date, COALESCE(SUM(qb.passed_qty), 0) as qty
             FROM qc_batches qb
             LEFT JOIN production_programs pp ON qb.program_id = pp.id
             WHERE qb.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)$unitClausePP
             GROUP BY DATE(qb.created_at)"
        );
        foreach ($qcRows as $r) {
            if (isset($dailyTrends[$r['log_date']])) {
                $dailyTrends[$r['log_date']]['qc_passed'] = (int)$r['qty'];
            }
        }

        // Daily Outward Dispatched
        $outRows = Database::fetchAll(
            "SELECT DATE(po.created_at) as log_date, COALESCE(SUM(pb.total_qty), SUM(po.quantity), 0) as qty
             FROM production_outwards po
             JOIN production_batches pb ON po.production_batch_id = pb.id
             LEFT JOIN production_programs pp ON pb.program_id = pp.id
             WHERE po.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)$unitClausePP
             GROUP BY DATE(po.created_at)"
        );
        foreach ($outRows as $r) {
            if (isset($dailyTrends[$r['log_date']])) {
                $dailyTrends[$r['log_date']]['outward'] = (int)$r['qty'];
            }
        }

        // WIP volume per stage
        $wipDistribution = [
            'Planned'         => (int)($activePrograms['total_planned'] ?? 0),
            'Cutting'         => (int)($pendingCutting['total_qty'] ?? 0),
            'Stitching'       => (int)($pendingStitching['total_qty'] ?? 0),
            'QC Inspection'   => (int)($pendingQC['total_qty'] ?? 0),
            'Ready Dispatch'  => (int)($readyDispatch['total_qty'] ?? 0),
            'Dispatched'      => (int)($monthOutward['total_qty'] ?? 0),
        ];

        return [
            'activePrograms'   => $activePrograms,
            'pendingCutting'   => $pendingCutting,
            'pendingStitching' => $pendingStitching,
            'pendingQC'        => $pendingQC,
            'readyDispatch'    => $readyDispatch,
            'monthOutward'     => $monthOutward,
            'recentPrograms'   => $recentPrograms,
            'recentQC'         => $recentQC,
            'recentOutwards'   => $recentOutwards,
            'qcBreakdown'      => $qcBreakdown ?: ['total_passed' => 0, 'total_rework' => 0, 'total_rejected' => 0, 'total_inspected' => 0],
            'dailyTrends'      => array_values($dailyTrends),
            'wipDistribution'  => $wipDistribution,
        ];
    }

    /**
     * Get Complete Timeline & Stage Information for a Program.
     */
    public static function getProgramTimeline(int $programId): array {
        $program = ProductionProgram::find($programId);
        $items = ProductionProgram::getItems($programId);
        $cutting = Database::fetchAll("SELECT * FROM cutting_batches WHERE program_id = ? ORDER BY id DESC", [$programId]);
        $stitching = Database::fetchAll("SELECT * FROM stitching_batches WHERE program_id = ? ORDER BY id DESC", [$programId]);
        $labels = Database::fetchAll("SELECT * FROM label_batches WHERE program_id = ? ORDER BY id DESC", [$programId]);
        $qc = Database::fetchAll("SELECT * FROM qc_batches WHERE program_id = ? ORDER BY id DESC", [$programId]);
        $batches = Database::fetchAll("SELECT * FROM production_batches WHERE program_id = ? ORDER BY id DESC", [$programId]);

        return [
            'program'   => $program,
            'items'     => $items,
            'cutting'   => $cutting,
            'stitching' => $stitching,
            'labels'    => $labels,
            'qc'        => $qc,
            'batches'   => $batches,
        ];
    }

    /**
     * Find or create a product for a production program.
     */
    public static function findOrCreateProductForProgram(int $programId): int {
        if ($programId <= 0) {
            return 0;
        }

        $prog = Database::fetchOne("SELECT * FROM production_programs WHERE id = ?", [$programId]);
        if (!$prog) {
            return 0;
        }

        if (!empty($prog['product_id'])) {
            $existing = Database::fetchOne("SELECT id FROM products WHERE id = ?", [(int)$prog['product_id']]);
            if ($existing) {
                return (int)$existing['id'];
            }
        }

        // Try matching an existing product by category hierarchy or name
        $prod = null;
        if (!empty($prog['sub_subcategory_id'])) {
            $prod = Database::fetchOne(
                "SELECT id FROM products WHERE sub_subcategory_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1",
                [(int)$prog['sub_subcategory_id']]
            );
        }
        if (!$prod && !empty($prog['subcategory_id'])) {
            $prod = Database::fetchOne(
                "SELECT id FROM products WHERE subcategory_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1",
                [(int)$prog['subcategory_id']]
            );
        }
        if (!$prod && !empty($prog['name'])) {
            $prod = Database::fetchOne(
                "SELECT id FROM products WHERE name = ? AND is_active = 1 ORDER BY id DESC LIMIT 1",
                [$prog['name']]
            );
        }

        if ($prod) {
            $productId = (int)$prod['id'];
            Database::execute("UPDATE production_programs SET product_id = ? WHERE id = ?", [$productId, $programId]);
            return $productId;
        }

        // Auto-create product for this program
        $catNames = [];
        $catCode = null;
        $subCode = null;
        $sscCode = null;

        if (!empty($prog['sub_subcategory_id'])) {
            $ssc = Database::fetchOne("SELECT name, code FROM sub_subcategories WHERE id = ?", [(int)$prog['sub_subcategory_id']]);
            if ($ssc) {
                $catNames[] = $ssc['name'];
                $sscCode = $ssc['code'];
            }
        }
        if (!empty($prog['subcategory_id'])) {
            $sc = Database::fetchOne("SELECT name, code FROM subcategories WHERE id = ?", [(int)$prog['subcategory_id']]);
            if ($sc) {
                $catNames[] = $sc['name'];
                $subCode = $sc['code'];
            }
        }
        if (!empty($prog['category_id'])) {
            $c = Database::fetchOne("SELECT name, code FROM categories WHERE id = ?", [(int)$prog['category_id']]);
            if ($c) {
                $catNames[] = $c['name'];
                $catCode = $c['code'];
            }
        }

        $name = !empty($catNames) ? implode(' - ', array_reverse($catNames)) : (!empty($prog['name']) ? $prog['name'] : 'PRODUCT STYLE');

        $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE source_type = 'OWN_PRODUCTION'")['c'] ?? 0;
        $code = 'GRM-STY-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
        $existingCode = Database::fetchOne("SELECT id FROM products WHERE code = ?", [$code]);
        if ($existingCode) {
            $code .= '-' . rand(10, 99);
        }

        $qrPrefix = '';
        if ($catCode) $qrPrefix .= substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $catCode)), 0, 1);
        if ($subCode) $qrPrefix .= substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $subCode)), 0, 1);
        if ($sscCode) $qrPrefix .= substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sscCode)), 0, 1);

        Database::execute(
            "INSERT INTO products (name, code, category_id, subcategory_id, sub_subcategory_id, category_code, subcategory_code, sub_subcategory_code, qr_code_prefix, source_type, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'OWN_PRODUCTION', 1, NOW(), NOW())",
            [
                $name,
                $code,
                $prog['category_id'] ?? null,
                $prog['subcategory_id'] ?? null,
                $prog['sub_subcategory_id'] ?? null,
                $catCode,
                $subCode,
                $sscCode,
                $qrPrefix ?: null,
            ]
        );

        $productId = (int)Database::lastInsertId();
        Database::execute("UPDATE production_programs SET product_id = ? WHERE id = ?", [$productId, $programId]);

        return $productId;
    }

    /**
     * Find or create a product variant for a product with given size and color names.
     */
    public static function findOrCreateVariant(int $productId, ?string $sizeName = null, ?string $colorName = null, ?string $sku = null): int {
        $sizeName = trim($sizeName ?? '');
        $colorName = trim($colorName ?? '');

        $sizeId = null;
        if ($sizeName !== '' && $sizeName !== 'Standard' && $sizeName !== 'All') {
            $size = Database::fetchOne("SELECT id FROM sizes WHERE name = ? OR code = ?", [$sizeName, $sizeName]);
            if ($size) {
                $sizeId = (int)$size['id'];
            } else {
                Database::execute("INSERT INTO sizes (name, code, is_active) VALUES (?, ?, 1)", [$sizeName, $sizeName]);
                $sizeId = (int)Database::lastInsertId();
            }
        }

        $colorId = null;
        if ($colorName !== '' && $colorName !== 'Assorted' && $colorName !== 'Standard' && $colorName !== 'All') {
            $color = Database::fetchOne("SELECT id FROM colors WHERE name = ?", [$colorName]);
            if ($color) {
                $colorId = (int)$color['id'];
            } else {
                Database::execute("INSERT INTO colors (name, is_active) VALUES (?, 1)", [$colorName]);
                $colorId = (int)Database::lastInsertId();
            }
        }

        // Check if variant already exists with size_id & color_id
        if ($sizeId && $colorId) {
            $v = Database::fetchOne(
                "SELECT id FROM product_variants WHERE product_id = ? AND size_id = ? AND color_id = ? AND is_active = 1",
                [$productId, $sizeId, $colorId]
            );
            if ($v) return (int)$v['id'];
        } elseif ($sizeId) {
            $v = Database::fetchOne(
                "SELECT id FROM product_variants WHERE product_id = ? AND size_id = ? AND (color_id IS NULL OR color_id = 0) AND is_active = 1",
                [$productId, $sizeId]
            );
            if ($v) return (int)$v['id'];
        } elseif ($colorId) {
            $v = Database::fetchOne(
                "SELECT id FROM product_variants WHERE product_id = ? AND (size_id IS NULL OR size_id = 0) AND color_id = ? AND is_active = 1",
                [$productId, $colorId]
            );
            if ($v) return (int)$v['id'];
        }

        // Check if matching by SKU if provided
        if (!empty($sku)) {
            $v = Database::fetchOne("SELECT id FROM product_variants WHERE product_id = ? AND sku = ? AND is_active = 1", [$productId, $sku]);
            if ($v) return (int)$v['id'];
        }

        // Generate SKU if needed
        $product = Database::fetchOne("SELECT code, name FROM products WHERE id = ?", [$productId]);
        $prodCode = $product['code'] ?? ('PRD-' . $productId);

        $skuParts = [$prodCode];
        if ($sizeName !== '' && $sizeName !== 'Standard') $skuParts[] = $sizeName;
        if ($colorName !== '' && $colorName !== 'Assorted') $skuParts[] = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $colorName)), 0, 4);
        $generatedSku = implode('-', $skuParts);

        // Check if this generated SKU exists
        $v = Database::fetchOne("SELECT id FROM product_variants WHERE sku = ?", [$generatedSku]);
        if ($v) return (int)$v['id'];

        // Insert new variant
        Database::execute(
            "INSERT INTO product_variants (product_id, size_id, color_id, sku, mrp, purchase_price, production_cost, selling_price, is_active)
             VALUES (?, ?, ?, ?, 0.00, 0.00, 0.00, 0.00, 1)",
            [$productId, $sizeId, $colorId, $generatedSku]
        );

        return (int)Database::lastInsertId();
    }
}
