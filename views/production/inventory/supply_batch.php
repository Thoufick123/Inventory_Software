<?php
// views/production/inventory/supply_batch.php
// Piece-by-piece and unit detail for a single Supply Inward batch with QR Code search, filtering & label printing
// Exactly matches the layout, style, and structure of QC Batch Inventory (views/production/inventory/batch.php)
$batchDate = new DateTime($supplyItem['inwarded_at'] ?: ($supplyItem['purchase_date'] ?? 'now'));
$unit = mb_strtoupper(trim($supplyItem['unit'] ?? 'PCS'));
$hsnCode = !empty($supplyItem['hsn_code']) ? trim((string)$supplyItem['hsn_code']) : '—';
$supplierName = trim($supplyItem['supplier_company'] ?: ($supplyItem['supplier_name'] ?? 'Supplier Inward'));
$itemName = trim($supplyItem['catalog_product_name'] ?: ($supplyItem['item_name'] ?? 'Supply Item'));
$unitPrice = (float)($supplyItem['unit_price'] ?? 0);
?>

<!-- QR Code Library CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
@media print {
    body * {
        visibility: hidden !important;
    }
    #qrPrintModal, #qrPrintModal * {
        visibility: visible !important;
    }
    #qrPrintModal {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
    }
    .modal-dialog {
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
    }
    .modal-content {
        border: none !important;
        box-shadow: none !important;
    }
    .modal-header, .modal-footer, .no-print, .btn-close {
        display: none !important;
    }
    .modal-body {
        padding: 0 !important;
    }
    .qr-sticker-card {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        border: 1px solid #000 !important;
        margin-bottom: 8px !important;
    }
}

.qr-sticker-card {
    border-radius: 6px;
    background: #fff;
    border: 1px solid #dee2e6;
    transition: all 0.2s ease;
}
.qr-sticker-card:hover {
    border-color: #0d6efd;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
}
.lbl-title {
    font-size: 8px;
    font-weight: 800;
    color: #111;
    letter-spacing: 0.5px;
    line-height: 1.1;
}
.lbl-sku {
    font-size: 8.5px;
    font-family: monospace;
    color: #222;
    line-height: 1.1;
}
.lbl-damage-code {
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.3px;
    color: #111;
}
.lbl-mrp {
    font-size: 7.5px;
    color: #777;
    text-decoration: line-through;
    line-height: 1;
}
.lbl-disc {
    font-size: 9px;
    color: #0d6efd;
    line-height: 1.1;
}
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-3 mb-1">
            <a href="<?= url('production/inventory') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Batches
            </a>
            <h2 class="mb-0">
                <i class="bi bi-box-seam me-2 text-success"></i>Supply Inward #<?= $supplyItemId ?> — Inventory
            </h2>
        </div>
        <span class="text-muted">
            <strong><?= e($itemName) ?></strong> &nbsp;·&nbsp;
            Purchase: <a href="<?= url('production/purchases/view/' . $supplyItem['purchase_id']) ?>" class="text-decoration-none fw-semibold"><strong><?= e($supplyItem['purchase_number']) ?></strong></a> &nbsp;·&nbsp;
            Supplier: <strong><?= e($supplierName) ?></strong> &nbsp;·&nbsp;
            <i class="bi bi-calendar3 me-1"></i><?= $batchDate->format('d M Y, g:i A') ?>
        </span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($mode === 'package'): ?>
            <a href="<?= url('production/inventory/supply/' . $supplyItemId) ?>" class="btn btn-outline-primary">
                <i class="bi bi-grid me-1"></i> Switch to Per-Piece View
            </a>
        <?php else: ?>
            <a href="<?= url('production/inventory/supply/' . $supplyItemId . '?mode=package') ?>" class="btn btn-outline-primary">
                <i class="bi bi-box-seam me-1"></i> Master Box Package QR
            </a>
        <?php endif; ?>
        <?php if ($remainingPieces > 0 && hasPermission('batches', 'create')): ?>
        <a href="<?= url('production/batches/create?supply_item_id=' . $supplyItemId) ?>" class="btn btn-success">
            <i class="bi bi-box-seam me-1"></i> Pack into Box Batch (<?= number_format($remainingPieces) ?> <?= e($unit) ?>)
        </a>
        <?php elseif ($totalPieces > 0 && $remainingPieces == 0): ?>
        <span class="btn btn-outline-secondary disabled" title="All items from this inward batch have been outwarded">
            <i class="bi bi-check2-all me-1"></i> Fully Outwarded
        </span>
        <?php endif; ?>
    </div>
</div>

<!-- Batch KPI Cards (Exact look of QC Batch) -->
<div class="row g-3 mb-4">
    <!-- 1. In-Stock Remaining -->
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase text-muted fw-semibold small d-block">In-Stock Remaining</span>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($remainingPieces) ?> <span class="fs-6 fw-normal text-muted"><?= e(strtolower($unit)) ?></span></h3>
                    <small class="text-muted d-block mt-1">Available in production unit</small>
                </div>
                <div class="bg-success text-white rounded-3 p-3">
                    <i class="bi bi-box-seam-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Outward Dispatched -->
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm bg-danger bg-opacity-10 h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase text-muted fw-semibold small d-block">Outward Dispatched</span>
                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($totalOutwardPieces) ?> <span class="fs-6 fw-normal text-muted"><?= e(strtolower($unit)) ?></span></h3>
                    <small class="text-muted d-block mt-1">Dispatched to warehouses</small>
                </div>
                <div class="bg-danger text-white rounded-3 p-3">
                    <i class="bi bi-truck fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Total Inwarded -->
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm bg-info bg-opacity-10 h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase text-muted fw-semibold small d-block">Total Inwarded</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($totalPieces) ?> <span class="fs-6 fw-normal text-muted"><?= e(strtolower($unit)) ?></span></h3>
                    <small class="text-muted d-block mt-1">1 supply item variant</small>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">HSN: <?= e($hsnCode) ?></span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Inward Complete</span>
                    </div>
                </div>
                <div class="bg-info text-dark rounded-3 p-3">
                    <i class="bi bi-patch-check-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. In-Stock Valuation -->
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase text-muted fw-semibold small d-block">In-Stock Valuation</span>
                    <h3 class="fw-bold text-primary mb-0 mt-1">₹<?= number_format($remainingValue, 2) ?></h3>
                    <small class="text-muted d-block mt-1">Total: ₹<?= number_format($totalValue, 2) ?></small>
                </div>
                <div class="bg-primary text-white rounded-3 p-3">
                    <i class="bi bi-currency-rupee fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Supply Specifications & Inward Details Card (Exact styling of QC Rework & Rejected Card) -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="card-title mb-0 fw-bold text-dark"><i class="bi bi-clipboard2-pulse me-2 text-warning"></i>Supply Specifications &amp; Inward Details</h5>
            <small class="text-muted">Verified supply specifications, HSN classification, and supplier purchase record.</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-secondary-subtle text-secondary border px-3 py-2">HSN: <?= e($hsnCode) ?></span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">Unit Price: ₹<?= number_format($unitPrice, 2) ?> / <?= e($unit) ?></span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Supply Item &amp; Code</th>
                        <th>Category / Type</th>
                        <th>Supplier / PO #</th>
                        <th class="text-center">Purchase Qty</th>
                        <th class="text-center">Inwarded Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total Valuation</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong class="text-dark d-block"><?= e($itemName) ?></strong>
                            <small class="text-muted font-monospace">SKU: <?= e($supplyItem['product_sku'] ?: ('SUP-' . $supplyItemId)) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-success-subtle text-success border">General Supply</span>
                            <small class="text-muted d-block mt-0.5"><?= e($supplyItem['category_name'] ?: 'Garment Supplies') ?></small>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?= e($supplierName) ?></div>
                            <small class="text-muted">PO: <?= e($supplyItem['purchase_number']) ?><?= !empty($supplyItem['invoice_number']) ? ' · Inv: ' . e($supplyItem['invoice_number']) : '' ?></small>
                        </td>
                        <td class="text-center fw-semibold"><?= number_format((float)$supplyItem['quantity']) ?> <?= e($unit) ?></td>
                        <td class="text-center fw-bold text-success"><?= number_format($totalPieces) ?> <?= e($unit) ?></td>
                        <td class="text-end fw-semibold">₹<?= number_format($unitPrice, 2) ?></td>
                        <td class="text-end fw-bold text-primary">₹<?= number_format($totalValue, 2) ?></td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success border"><i class="bi bi-check-circle me-1"></i>Inward Complete</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Outward Dispatch History & Inventory Movement (Exact styling of QC Outwards Card) -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-danger bg-opacity-10 text-danger rounded p-2 text-center" style="width: 36px; height: 36px;">
                <i class="bi bi-truck fs-5"></i>
            </div>
            <div>
                <h5 class="card-title mb-0 fw-bold text-dark">Outward Dispatch History &amp; Inventory Movement</h5>
                <small class="text-muted">Track all product batches dispatched outward from production unit to central warehouses</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fw-bold">
                <?= count($outwards) ?> Outward Dispatch(es) &nbsp;·&nbsp; <?= number_format($totalOutwardPieces) ?> <?= e(strtolower($unit)) ?> dispatched
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($outwards)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Outward #</th>
                        <th>Destination Warehouse</th>
                        <th class="text-end">Dispatched Qty</th>
                        <th>Transport / Vehicle</th>
                        <th>Dispatch Date</th>
                        <th>Status</th>
                        <th class="text-end">Delivery Challan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outwards as $ow): ?>
                    <tr>
                        <td>
                            <span class="fw-bold text-primary font-monospace">
                                <i class="bi bi-file-earmark-text me-1"></i><?= e($ow['outward_number']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?= e($ow['warehouse_name'] ?? 'Main Warehouse') ?></div>
                            <?php if (!empty($ow['warehouse_code'])): ?>
                                <small class="text-muted"><code><?= e($ow['warehouse_code']) ?></code></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold text-danger fs-6">
                                <?= number_format((float)($ow['total_quantity'] ?? $ow['quantity'] ?? 0)) ?> <?= e($unit) ?>
                            </span>
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark">
                                <i class="bi bi-truck me-1 text-muted"></i><?= e($ow['vehicle_number'] ?: 'N/A') ?>
                            </div>
                        </td>
                        <td>
                            <div class="small text-dark">
                                <?= !empty($ow['dispatch_date']) ? (new DateTime($ow['dispatch_date']))->format('d M Y, g:i A') : 'N/A' ?>
                            </div>
                        </td>
                        <td>
                            <?php if (($ow['status'] ?? '') === 'RECEIVED'): ?>
                                <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i>RECEIVED</span>
                            <?php else: ?>
                                <span class="badge bg-primary"><i class="bi bi-truck me-1"></i>DISPATCHED</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url('production/outward') ?>" class="btn btn-sm btn-outline-primary" title="View in Outward Management">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Outward Pass
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="p-4 text-center text-muted">
            <i class="bi bi-info-circle fs-3 d-block mb-2 text-info"></i>
            <h6 class="fw-semibold text-dark mb-1">No Outward Dispatches Yet</h6>
            <p class="small text-muted mb-0">100% of inwarded supplies in this batch (<strong><?= number_format($totalPieces) ?> <?= e($unit) ?></strong>) are currently available in production unit inventory.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Fast 2D QR Code Search & Filter Bar (Exact styling of QC Batch) -->
<div class="card shadow-sm mb-4 border-primary" id="qrSection">
    <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-bold fs-7"><i class="bi bi-qr-code-scan me-2"></i>Supply QR Code Search &amp; Quick Label Print</span>
        <span class="badge bg-white text-primary fw-bold fs-8" id="searchMatchBadge">Showing all <?= count($pieceItems) ?> pieces</span>
    </div>
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-lg-7 col-md-6">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light border-primary"><i class="bi bi-upc-scan text-primary fs-5"></i></span>
                    <input type="text" id="qrInventorySearch" class="form-control border-primary fw-bold" 
                           placeholder="Search or Scan QR sticker tag (e.g. <?= $pieceItems[0]['tag_code'] ?? 'SP112601' ?>)..." 
                           autofocus oninput="filterInventoryPieces()" onkeyup="if(event.key==='Enter') filterInventoryPieces()">
                    <button type="button" class="btn btn-outline-secondary" onclick="clearInventorySearch()" title="Clear Search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-5 col-md-6 d-flex gap-2 justify-content-md-end flex-wrap">
                <button type="button" class="btn btn-primary btn-lg fw-semibold shadow-sm flex-grow-1 flex-md-grow-0" id="btnPrintFiltered" onclick="printFilteredLabels()">
                    <i class="bi bi-printer-fill me-1"></i> Print Filtered Labels (<span id="filteredCountLabel"><?= count($pieceItems) ?></span>)
                </button>
                <button type="button" class="btn btn-outline-primary btn-lg fw-semibold shadow-sm" onclick="printAllSupplyLabels()">
                    <i class="bi bi-printer me-1"></i> Print All Labels (<?= count($pieceItems) ?>)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Items Container (Exact styling of QC Items Table / Cards) -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-table text-primary"></i>
                Inwarded Garment Supplies
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-7 px-2" id="headerCountBadge">
                    <?= $remainingPieces ?> In Stock / <?= count($pieceItems) ?> Total Pieces
                </span>
            </h5>
            <span class="text-muted small">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i>
                Piece-by-piece listing for Supply Inward #<?= $supplyItemId ?> (<?= $remainingPieces ?> <?= e($unit) ?> remaining in stock)
            </span>
        </div>
        <!-- View Mode Switch -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary fw-semibold" id="btnCardView" onclick="switchView('card')">
                    <i class="bi bi-grid-3x3-gap me-1"></i> Cards
                </button>
                <button type="button" class="btn btn-primary active fw-semibold" id="btnPieceView" onclick="switchView('list')">
                    <i class="bi bi-list-ul me-1"></i> List (<?= count($pieceItems) ?>)
                </button>
                <button type="button" class="btn btn-outline-secondary fw-semibold" id="btnTableView" onclick="switchView('table')">
                    <i class="bi bi-table me-1"></i> Table
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-4">

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="pieceCardsContainer" style="display: none;"></div>
        <div class="list-group list-group-flush gap-2" id="pieceListContainer" style="display: block;"></div>
        <div class="table-responsive" id="pieceViewContainer" style="display: none;">
            <table class="table table-hover align-middle mb-0" id="pieceInventoryTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Supply QR Tag / Code</th>
                        <th>Item &amp; Category</th>
                        <th>Supplier &amp; PO</th>
                        <th>Unit Price</th>
                        <th>Inventory Status</th>
                        <th class="text-end" style="min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="pieceTableBody"></tbody>
            </table>
        </div>

        <div class="text-center text-muted py-5" id="emptyPieceSearchState" style="display: none;">
            <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
            <h6 class="fw-semibold mb-1">No Matching Supply QR Found</h6>
            <p class="small mb-0">No supply piece in this batch matches the scanned or searched QR code.</p>
        </div>

    </div>
</div>

<!-- Thermal Garment Supply QR Print Modal -->
<div class="modal fade" id="qrPrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold" id="qrPrintModalTitle">
                    <i class="bi bi-printer me-2"></i>Print Thermal Garment Supply QR Labels
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 bg-light">
                <div class="alert alert-info py-2 small d-flex justify-content-between align-items-center mb-3 no-print">
                    <div>
                        <i class="bi bi-info-circle me-1"></i> Showing <strong id="printCountLabel">0</strong> stickers. Optimized for 50x30mm thermal sticker roll printers.
                    </div>
                    <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="window.print()">
                        <i class="bi bi-printer-fill me-1"></i> Print Now
                    </button>
                </div>
                <div class="row g-2 justify-content-center" id="qrStickersGrid"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm fw-bold" onclick="window.print()">
                    <i class="bi bi-printer-fill me-1"></i> Print Labels
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.inventoryPieceItems = <?= json_encode($pieceItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentFilteredPieces = [...window.inventoryPieceItems];
let currentViewMode = 'list';

function switchView(mode) {
    currentViewMode = mode;
    document.getElementById('btnCardView').classList.toggle('btn-primary', mode === 'card');
    document.getElementById('btnCardView').classList.toggle('btn-outline-secondary', mode !== 'card');
    document.getElementById('btnCardView').classList.toggle('active', mode === 'card');

    document.getElementById('btnPieceView').classList.toggle('btn-primary', mode === 'list');
    document.getElementById('btnPieceView').classList.toggle('btn-outline-secondary', mode !== 'list');
    document.getElementById('btnPieceView').classList.toggle('active', mode === 'list');

    document.getElementById('btnTableView').classList.toggle('btn-primary', mode === 'table');
    document.getElementById('btnTableView').classList.toggle('btn-outline-secondary', mode !== 'table');
    document.getElementById('btnTableView').classList.toggle('active', mode === 'table');

    renderPiecesView();
}

function filterInventoryPieces() {
    const q = (document.getElementById('qrInventorySearch').value || '').trim().toLowerCase();
    if (!q) {
        currentFilteredPieces = [...window.inventoryPieceItems];
    } else {
        currentFilteredPieces = window.inventoryPieceItems.filter(p => {
            return (p.tag_code && p.tag_code.toLowerCase().includes(q))
                || (p.product_name && p.product_name.toLowerCase().includes(q))
                || (p.program_number && p.program_number.toLowerCase().includes(q))
                || (p.category_name && p.category_name.toLowerCase().includes(q))
                || (p.hsn_code && p.hsn_code.toLowerCase().includes(q));
        });
    }

    document.getElementById('searchMatchBadge').textContent = `Showing ${currentFilteredPieces.length} of ${window.inventoryPieceItems.length} pieces`;
    document.getElementById('filteredCountLabel').textContent = currentFilteredPieces.length;

    renderPiecesView();
}

function clearInventorySearch() {
    document.getElementById('qrInventorySearch').value = '';
    filterInventoryPieces();
    document.getElementById('qrInventorySearch').focus();
}

function renderPiecesView() {
    const cardsEl = document.getElementById('pieceCardsContainer');
    const listEl = document.getElementById('pieceListContainer');
    const tableEl = document.getElementById('pieceViewContainer');
    const tableBody = document.getElementById('pieceTableBody');
    const emptyEl = document.getElementById('emptyPieceSearchState');

    if (currentFilteredPieces.length === 0) {
        cardsEl.style.display = 'none';
        listEl.style.display = 'none';
        tableEl.style.display = 'none';
        emptyEl.style.display = 'block';
        return;
    }

    emptyEl.style.display = 'none';
    cardsEl.style.display = currentViewMode === 'card' ? 'flex' : 'none';
    listEl.style.display = currentViewMode === 'list' ? 'flex' : 'none';
    tableEl.style.display = currentViewMode === 'table' ? 'block' : 'none';

    if (currentViewMode === 'card') {
        cardsEl.innerHTML = currentFilteredPieces.map((p, idx) => `
            <div class="col">
                <div class="card h-100 shadow-xs border ${p.is_dispatched ? 'border-danger-subtle bg-light' : 'border-success-subtle bg-white'}">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge ${p.is_dispatched ? 'bg-danger text-white' : 'bg-dark text-white'} font-monospace fs-7 px-2 py-1">
                                #${p.piece_no} · ${p.tag_code}
                            </span>
                            <span class="badge ${p.is_dispatched ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle'}">
                                ${p.is_dispatched ? 'Dispatched' : 'In Stock'}
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">${escapeHtml(p.product_name)}</h6>
                        <div class="small text-muted mb-2">${escapeHtml(p.category_name)} · PO: <strong>${escapeHtml(p.program_number)}</strong></div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold text-primary">₹${parseFloat(p.unit_price).toFixed(2)}</span>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printSinglePiece(${idx})" title="Print QR Sticker">
                                    <i class="bi bi-printer"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyTagCode('${p.tag_code}', this)" title="Copy Code">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    } else if (currentViewMode === 'list') {
        listEl.innerHTML = currentFilteredPieces.map((p, idx) => `
            <div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 rounded border mb-1 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded p-2 text-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-qr-code fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge ${p.is_dispatched ? 'bg-danger' : 'bg-dark'} font-monospace fs-7 px-2 py-1">
                                #${p.piece_no} · ${p.tag_code}
                            </span>
                            <strong class="text-dark">${escapeHtml(p.product_name)}</strong>
                            <span class="badge ${p.is_dispatched ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle'}">
                                ${p.is_dispatched ? 'Dispatched' : 'In Stock'}
                            </span>
                        </div>
                        <div class="small text-muted mt-1">
                            PO: <span class="fw-semibold text-dark">${escapeHtml(p.program_number)}</span> &nbsp;·&nbsp;
                            Unit: <span class="fw-semibold">${escapeHtml(p.size_name)}</span> &nbsp;·&nbsp;
                            HSN: <code>${escapeHtml(p.hsn_code || '—')}</code>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <div class="fw-bold text-primary fs-6">₹${parseFloat(p.unit_price).toFixed(2)}</div>
                        <small class="text-muted">Unit Valuation</small>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="printSinglePiece(${idx})" title="Print QR Sticker">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyTagCode('${p.tag_code}', this)" title="Copy Code">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    } else {
        tableBody.innerHTML = currentFilteredPieces.map((p, idx) => `
            <tr>
                <td class="text-muted font-monospace">${p.piece_no}</td>
                <td>
                    <span class="badge bg-dark font-monospace fs-7 px-2 py-1">${p.tag_code}</span>
                </td>
                <td>
                    <strong class="text-dark">${escapeHtml(p.product_name)}</strong>
                    <small class="text-muted d-block">${escapeHtml(p.category_name)}</small>
                </td>
                <td>
                    <span class="fw-semibold text-dark">${escapeHtml(p.program_number)}</span>
                    <small class="text-muted d-block">HSN: ${escapeHtml(p.hsn_code || '—')}</small>
                </td>
                <td class="fw-bold text-primary">₹${parseFloat(p.unit_price).toFixed(2)}</td>
                <td>
                    <span class="badge ${p.is_dispatched ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle'}">
                        ${p.is_dispatched ? 'Dispatched' : 'In Stock'}
                    </span>
                </td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" onclick="printSinglePiece(${idx})" title="Print QR Sticker">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="copyTagCode('${p.tag_code}', this)" title="Copy Code">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function copyTagCode(code, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(() => {
            showCopyFeedback(btn);
        }).catch(() => {
            fallbackCopy(code, btn);
        });
    } else {
        fallbackCopy(code, btn);
    }
}

function fallbackCopy(code, btn) {
    const temp = document.createElement('textarea');
    temp.value = code;
    document.body.appendChild(temp);
    temp.select();
    try {
        document.execCommand('copy');
        showCopyFeedback(btn);
    } catch (e) {
        console.error('Copy failed', e);
    }
    document.body.removeChild(temp);
}

function showCopyFeedback(btn) {
    if (!btn) return;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i> Copied!';
    setTimeout(() => { btn.innerHTML = orig; }, 1500);
}

// Thermal Garment Supply QR Printing
function printSinglePiece(index) {
    if (currentFilteredPieces && currentFilteredPieces[index]) {
        renderStickersToModal([currentFilteredPieces[index]]);
    }
}

function printFilteredLabels() {
    if (currentFilteredPieces.length === 0) {
        alert('No matching pieces to print.');
        return;
    }
    renderStickersToModal(currentFilteredPieces);
}

function printAllSupplyLabels() {
    if (window.inventoryPieceItems.length === 0) {
        alert('No supply pieces in this batch.');
        return;
    }
    renderStickersToModal(window.inventoryPieceItems);
}

function renderStickersToModal(itemsToPrint) {
    const grid = document.getElementById('qrStickersGrid');
    const printCount = document.getElementById('printCountLabel');
    printCount.textContent = itemsToPrint.length;
    grid.innerHTML = '';

    itemsToPrint.forEach((item, i) => {
        const qrId = 'modal_qr_' + i + '_' + Math.random().toString(36).substring(2, 6);
        const cardHtml = `
            <div class="col-md-4 col-sm-6 mb-2">
                <div class="qr-sticker-card p-2 shadow-xs bg-white border rounded">
                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                        <span class="lbl-title text-truncate fw-bold text-dark" style="max-width: 150px;">
                            <?= e($supplierName) ?>
                        </span>
                        <span class="badge bg-light text-dark font-monospace border" style="font-size: 7.5px;">
                            ${escapeHtml(item.program_number)}
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div id="${qrId}" style="width: 70px; height: 70px; flex-shrink: 0;" class="p-1 bg-white border rounded"></div>
                        <div class="flex-grow-1" style="font-size: 8px; line-height: 1.2;">
                            <strong class="d-block text-dark text-truncate" style="max-width: 140px;">${escapeHtml(item.product_name)}</strong>
                            <div class="text-muted">Type: GARMENT SUPPLY</div>
                            <div>HSN: <strong>${escapeHtml(item.hsn_code || '—')}</strong></div>
                            <div class="text-primary fw-bold">Price: ₹${parseFloat(item.unit_price).toFixed(2)}</div>
                            <div class="font-monospace fw-bold text-dark mt-1">Code: ${escapeHtml(item.tag_code)}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        grid.insertAdjacentHTML('beforeend', cardHtml);

        setTimeout(() => {
            const qrContainer = document.getElementById(qrId);
            if (qrContainer && typeof QRCode !== 'undefined') {
                qrContainer.innerHTML = '';
                new QRCode(qrContainer, {
                    text: item.tag_code,
                    width: 65,
                    height: 65,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        }, 50);
    });

    const modal = new bootstrap.Modal(document.getElementById('qrPrintModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    renderPiecesView();
});
</script>
