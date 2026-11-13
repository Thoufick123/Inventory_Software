<?php
$batchId = (int)($batch['id'] ?? 0);
?>

<div class="container-fluid px-0 px-md-3 py-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('warehouse/dashboard') ?>" class="text-decoration-none">Warehouse</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('warehouse/inward') ?>" class="text-decoration-none">Inward / GRN</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('warehouse/inward/view/' . $batchId) ?>" class="text-decoration-none">Batch #<?= e($batch['batch_number']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit QR Codes</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0 text-dark">
                <i class="bi bi-upc-scan text-primary me-2"></i>Edit Inward QR Codes
                <span class="fs-6 font-monospace text-muted fw-normal ms-2">#<?= e($batch['batch_number']) ?></span>
            </h3>
        </div>
        <a href="<?= url('warehouse/inward/view/' . $batchId) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Batch View
        </a>
    </div>

    <div class="alert alert-info border-0 shadow-sm d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div class="small">
            <strong>QR-only editor:</strong> Series and Complementary products can be edited. Normal/Individual products are read-only.
            Enter one QR code per line. Expected QR codes can be corrected before GRN; received and damaged codes must remain within their recorded quantities and match the expected list.
            This form does not change item quantities, inward status, or warehouse stock.
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="alert alert-warning">No batch items are available to edit.</div>
    <?php elseif (!$hasEditableQrItems): ?>
        <div class="alert alert-secondary">This batch contains no Series or Complementary products. Its QR fields are disabled.</div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
        <form method="POST" action="<?= url('warehouse/inward/update-qr/' . $batchId) ?>">
            <?= CSRF::field() ?>

            <div class="row g-3">
                <?php foreach ($items as $item): ?>
                    <?php
                    $isEditable = !empty($item['is_series']) || !empty($item['is_complementary']);
                    $isSeries = !empty($item['is_series']);
                    $isComplementary = !empty($item['is_complementary']);
                    $productType = $isSeries && $isComplementary
                        ? 'SERIES + COMPLEMENTARY'
                        : ($isSeries ? 'SERIES' : ($isComplementary ? 'COMPLEMENTARY' : 'NORMAL / INDIVIDUAL'));
                    $expectedTags = is_array($item['outward_piece_tags'] ?? null) ? $item['outward_piece_tags'] : [];
                    $receivedTags = is_array($item['received_tags'] ?? null) ? $item['received_tags'] : [];
                    $damagedTags = is_array($item['damaged_tags'] ?? null) ? $item['damaged_tags'] : [];
                    $expectedQty = (int)($item['quantity'] ?? 0);
                    $receivedQty = (int)($item['received_qty'] ?? 0);
                    $damagedQty = (int)($item['damaged_qty'] ?? 0);
                    $missingQty = isset($item['missing_qty'])
                        ? (int)$item['missing_qty']
                        : max(0, $expectedQty - ($receivedQty + $damagedQty));
                    if (($batch['status'] ?? '') === 'RECEIVED' && $receivedQty === 0 && $damagedQty === 0 && $missingQty === 0) {
                        $receivedQty = $expectedQty;
                    }
                    $itemId = (int)($item['id'] ?? 0);
                    ?>
                    <div class="col-12">
                        <section class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-bold text-dark"><?= e($item['product_name'] ?? 'Product') ?></div>
                                    <div class="small text-muted font-monospace"><?= e($item['sku'] ?? '') ?></div>
                                    <div class="small text-muted">
                                        <?= e(trim(($item['color_name'] ?? '') . ' ' . ($item['size_name'] ?? ''))) ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?= $isEditable ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-secondary-subtle text-secondary border' ?>">
                                        <?= e($productType) ?>
                                    </span>
                                    <div class="small text-muted mt-1">
                                        Dispatched: <?= number_format($expectedQty) ?>
                                        · Good: <?= number_format($receivedQty) ?>
                                        · Damaged: <?= number_format($damagedQty) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!$isEditable): ?>
                                    <div class="small text-muted mb-3">
                                        <i class="bi bi-lock-fill me-1"></i>Normal/Individual product QR codes cannot be edited here.
                                    </div>
                                <?php endif; ?>
                                <div class="row g-3">
                                    <div class="col-12 col-lg-4">
                                        <label class="form-label fw-semibold" for="expectedQr<?= $itemId ?>">Expected / Outward QR codes</label>
                                        <textarea
                                            id="expectedQr<?= $itemId ?>"
                                            name="qr_items[<?= $itemId ?>][expected_tags]"
                                            class="form-control font-monospace"
                                            rows="5"
                                            aria-label="Expected QR codes for <?= e($item['sku'] ?? '') ?>"
                                            <?= $isEditable ? '' : 'disabled' ?>
                                        ><?= e(implode("\n", $expectedTags)) ?></textarea>
                                        <div class="form-text">Maximum <?= number_format($expectedQty) ?> tags.</div>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <label class="form-label fw-semibold" for="receivedQr<?= $itemId ?>">Received / Good QR codes</label>
                                        <textarea
                                            id="receivedQr<?= $itemId ?>"
                                            name="qr_items[<?= $itemId ?>][received_tags]"
                                            class="form-control font-monospace"
                                            rows="5"
                                            aria-label="Received QR codes for <?= e($item['sku'] ?? '') ?>"
                                            <?= $isEditable ? '' : 'disabled' ?>
                                        ><?= e(implode("\n", $receivedTags)) ?></textarea>
                                        <div class="form-text">Maximum <?= number_format($receivedQty) ?> tags.</div>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <label class="form-label fw-semibold" for="damagedQr<?= $itemId ?>">Damaged QR codes</label>
                                        <textarea
                                            id="damagedQr<?= $itemId ?>"
                                            name="qr_items[<?= $itemId ?>][damaged_tags]"
                                            class="form-control font-monospace"
                                            rows="5"
                                            aria-label="Damaged QR codes for <?= e($item['sku'] ?? '') ?>"
                                            <?= $isEditable ? '' : 'disabled' ?>
                                        ><?= e(implode("\n", $damagedTags)) ?></textarea>
                                        <div class="form-text">Maximum <?= number_format($damagedQty) ?> tags.</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($hasEditableQrItems): ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <label for="editReason" class="form-label fw-semibold">Reason for QR code changes <span class="text-danger">*</span></label>
                        <textarea id="editReason" name="edit_reason" class="form-control" rows="2" maxlength="500" required></textarea>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="<?= url('warehouse/inward/view/' . $batchId) ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary fw-semibold">
                                <i class="bi bi-check2-circle me-1"></i> Save QR Codes
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
