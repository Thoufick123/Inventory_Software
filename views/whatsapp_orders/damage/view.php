<?php
// views/whatsapp_orders/damage/view.php — Damage Record Details
$pieceTags = $damage['piece_tags'] ?? [];
if (!is_array($pieceTags)) {
    $pieceTags = array_filter(array_map('trim', explode(',', (string)$pieceTags)));
}
$pieceTags = array_values(array_filter(array_map(
    static fn($tag): string => trim((string)$tag),
    $pieceTags
)));
?>

<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('warehouse/whatsapp-orders') ?>">WhatsApp Orders</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('warehouse/whatsapp-orders/damage') ?>">Damage Management</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($damage['damage_number']) ?></li>
                </ol>
            </nav>
            <h4 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-octagon-fill text-danger"></i>
                <?= htmlspecialchars($damage['damage_number']) ?>
                <?php
                $statusBadges = [
                    'REPORTED' => 'bg-warning text-dark',
                    'INSPECTED' => 'bg-info text-dark',
                    'OUTWARD_PENDING' => 'bg-primary text-white',
                    'DISPATCHED' => 'bg-secondary text-white',
                    'WRITTEN_OFF' => 'bg-dark text-white',
                    'RESOLVED' => 'bg-success text-white'
                ];
                $sClass = $statusBadges[$damage['status']] ?? 'bg-secondary text-white';
                ?>
                <span class="badge <?= $sClass ?> fs-6"><?= htmlspecialchars($damage['status']) ?></span>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('warehouse/whatsapp-orders/damage') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Damage List
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Product & Defect Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Product Name</label>
                            <div class="fw-bold fs-6 text-dark"><?= htmlspecialchars($damage['product_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Product Type</label>
                            <?php
                            $productType = !empty($damage['variant_is_complementary']) || !empty($damage['product_is_complementary'])
                                ? 'COMPLEMENTARY'
                                : (strtoupper($damage['qr_type'] ?? '') === 'SERIES_BATCH' ? 'SERIES' : 'NORMAL');
                            $typeClass = $productType === 'COMPLEMENTARY' ? 'bg-info-subtle text-info border-info-subtle' : ($productType === 'SERIES' ? 'bg-primary-subtle text-primary border-primary-subtle' : 'bg-light text-dark border-secondary-subtle');
                            ?>
                            <div><span class="badge <?= $typeClass ?> border px-2 py-1"><?= htmlspecialchars($productType) ?></span></div>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Damaged Piece Tag<?= count($pieceTags) === 1 ? '' : 's' ?></label>
                            <?php if ($pieceTags): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($pieceTags as $tag): ?>
                                        <div class="d-inline-flex align-items-center gap-2 border rounded bg-light p-2">
                                            <div class="damage-piece-tag-qr bg-white border rounded p-1" data-piece-tag="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"></div>
                                            <code class="fw-bold text-dark"><?= htmlspecialchars($tag) ?></code>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted small">No piece tag was recorded for this damage entry.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="text-muted small">Damaged Quantity</label>
                            <div class="fw-bold fs-5 text-danger"><?= number_format($damage['quantity']) ?> pcs</div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Defect Category</label>
                            <div>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                    <i class="bi bi-tag-fill me-1"></i><?= htmlspecialchars($damage['defect_type'] ?? 'UNSPECIFIED') ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Severity Level</label>
                            <div>
                                <?php
                                $sevBadge = match($damage['severity'] ?? 'MODERATE') {
                                    'MINOR' => 'bg-info-subtle text-info border-info-subtle',
                                    'MODERATE' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                    'CRITICAL' => 'bg-danger-subtle text-danger border-danger-subtle',
                                    default => 'bg-secondary-subtle text-secondary'
                                };
                                ?>
                                <span class="badge <?= $sevBadge ?> border px-2 py-1">
                                    <?= htmlspecialchars($damage['severity'] ?? 'MODERATE') ?>
                                </span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="text-muted small">Defect Description / Notes</label>
                            <div class="p-3 bg-light rounded text-dark">
                                <?= nl2br(htmlspecialchars($damage['notes'] ?? 'No special notes recorded.')) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sidebar / Meta Details -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Audit & Source Info</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Channel:</span>
                            <span class="fw-bold"><?= htmlspecialchars($damage['channel_name'] ?? 'WhatsApp Channel') ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Discovered At:</span>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($damage['source_type'] ?? $damage['discovered_stage'] ?? 'INWARD_TRANSIT') ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Logged By:</span>
                            <span><?= htmlspecialchars($damage['reported_by_name'] ?? 'System') ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Recorded On:</span>
                            <span><?= date('M d, Y h:i A', strtotime($damage['created_at'])) ?></span>
                        </li>
                        <?php if (!empty($damage['allocation_id'])): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="text-muted">Inward Allocation Ref:</span>
                                <a href="<?= url('warehouse/whatsapp-orders/inward/view/' . $damage['allocation_id']) ?>" class="fw-bold text-decoration-none">
                                    Allocation #<?= $damage['allocation_id'] ?> <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-shield-check text-success me-1"></i> Stock Ledger Status</h6>
                    <p class="small text-muted mb-0">
                        This item has been deducted from Available WhatsApp stock and isolated in the <code>DAMAGED</code> stock partition for review.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('assets/js/qrcode.min.js') ?>"></script>
<script>
function renderDamagePieceTagQrs() {
    if (typeof QRCode !== 'undefined') {
        document.querySelectorAll('.damage-piece-tag-qr').forEach(function (pieceQr) {
            const tag = pieceQr.dataset.pieceTag || '';
            if (tag) {
                new QRCode(pieceQr, { text: tag, width: 48, height: 48, correctLevel: QRCode.CorrectLevel.M });
            }
        });
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderDamagePieceTagQrs, {once: true});
} else {
    renderDamagePieceTagQrs();
}
</script>
