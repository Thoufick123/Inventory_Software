<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Other Garment Supplies QR Labels') ?> — GRM Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --bs-body-bg: #f8fafc;
            --bs-body-color: #0f172a;
        }
        body {
            background-color: var(--bs-body-bg);
            color: var(--bs-body-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .supply-qr-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Top Action Bar */
        .action-bar {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 16px 24px;
            margin-bottom: 24px;
        }

        /* Sticker Card: Standard 50x30mm thermal label layout */
        .stickers-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: flex-start;
        }

        .supply-label-card {
            width: 275px;
            min-height: 155px;
            background: #ffffff;
            border: 1.5px solid #0f172a;
            border-radius: 8px;
            padding: 10px 12px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 6px;
            position: relative;
            break-inside: avoid;
            page-break-inside: avoid;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .supply-label-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
        }

        .label-header {
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .company-tag {
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #1e293b;
            text-transform: uppercase;
            line-height: 1.2;
            max-width: 190px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .po-tag {
            font-size: 8px;
            font-weight: 700;
            color: #0d6efd;
            font-family: monospace;
            background: #e7f1ff;
            padding: 1px 4px;
            border-radius: 3px;
        }

        .label-body {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qr-box {
            width: 76px;
            height: 76px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 2px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }

        .qr-box img, .qr-box canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .label-meta {
            flex-grow: 1;
            font-size: 9.5px;
            line-height: 1.35;
        }

        .supply-badge-num {
            font-family: monospace;
            font-size: 11px;
            font-weight: 900;
            color: #0f172a;
            display: block;
            margin-bottom: 2px;
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 160px;
        }

        .meta-line {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .meta-line strong {
            color: #0f172a;
        }

        .label-footer {
            border-top: 1px dashed #cbd5e1;
            padding-top: 4px;
            margin-top: 6px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            font-size: 8px;
            color: #64748b;
        }

        .single-print-btn {
            padding: 1px 6px;
            font-size: 9px;
            border-radius: 3px;
            line-height: 1.3;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .action-bar, .single-print-btn, .supply-select-box, .no-print, .label-footer, .unselected-supply, .d-print-none {
                display: none !important;
            }
            .supply-qr-container {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .stickers-wrapper {
                gap: 8px !important;
                justify-content: flex-start !important;
            }
            .supply-label-card {
                border: 1.5px solid #000 !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                min-height: auto !important;
                height: auto !important;
                justify-content: flex-start !important;
                gap: 4px !important;
                padding: 8px 10px !important;
                width: 260px !important;
            }
        }
    </style>
</head>
<body>

<div class="supply-qr-container">
    <?php if (class_exists('Session') && Session::hasFlash('success')): ?>
        <?php foreach (Session::getFlash('success') as $msg): ?>
            <div class="alert alert-success alert-dismissible fade show no-print mb-3 shadow-sm border-0 border-start border-success border-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                    <div>
                        <strong>Inward Completed:</strong> <?= htmlspecialchars($msg) ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (class_exists('Session') && Session::hasFlash('error')): ?>
        <?php foreach (Session::getFlash('error') as $msg): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print mb-3 shadow-sm border-0 border-start border-danger border-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5 me-2"></i>
                    <div>
                        <strong>Action Failed:</strong> <?= htmlspecialchars($msg) ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    $canManageSupplies = hasPermission('production_purchases', 'create') 
        || hasPermission('production_purchases', 'edit') 
        || hasPermission('sellers', 'create') 
        || hasPermission('sellers', 'edit')
        || (class_exists('Auth') && method_exists('Auth', 'isAdmin') && Auth::isAdmin())
        || (class_exists('Session') && Session::get('role') === 'admin');

    $isAllInwarded = !empty($purchase['is_inwarded']);
    if (!$isAllInwarded && !empty($supplies)) {
        $isAllInwarded = true;
        foreach ($supplies as $s) {
            if (empty($s['is_inwarded'])) {
                $isAllInwarded = false;
                break;
            }
        }
    }
    ?>

    <!-- Top Action & Info Bar -->
    <div class="action-bar no-print">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-success fs-6 px-2 py-1"><i class="bi bi-tags-fill me-1"></i> Supply QR Module</span>
                    <h3 class="mb-0 fw-bold fs-4 text-dark"><?= htmlspecialchars($purchase['purchase_number']) ?></h3>
                </div>
                <div class="text-muted small">
                    <strong>Supplier:</strong> <?= htmlspecialchars($purchase['supplier_company'] ?: ($purchase['supplier_name'] ?? 'Direct Supplier')) ?>
                    · <strong>PO Date:</strong> <?= date('d M Y', strtotime($purchase['purchase_date'])) ?>
                    · <strong>Invoice:</strong> <?= htmlspecialchars($purchase['invoice_number'] ?: 'N/A') ?>
                    · <strong>Total Supplies:</strong> <span class="badge bg-success-subtle text-success border border-success"><?= count($supplies) ?> Item<?= count($supplies) === 1 ? '' : 's' ?> (<?= count($stickers) ?> Labels)</span>
                    <?php if ($isAllInwarded): ?>
                        <span class="badge bg-success text-white ms-1"><i class="bi bi-check-circle-fill me-1"></i>Inward Complete</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= url('production/purchases') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Purchases
                </a>

                <?php if ($isAllInwarded): ?>
                    <span class="badge bg-success-subtle text-success border border-success px-2.5 py-1.5 fw-bold d-inline-flex align-items-center gap-1 shadow-xs" title="All <?= count($stickers) ?> supplies inwarded to inventory">
                        <i class="bi bi-check-circle-fill text-success"></i> Inwarded to Inventory (<?= (int)($inwardedCount ?? count($stickers)) ?>/<?= count($stickers) ?>)
                    </span>
                    <a href="<?= url('production/inventory') ?>" class="btn btn-outline-success btn-sm fw-bold">
                        <i class="bi bi-box-seam me-1"></i> View in Inventory
                    </a>
                <?php else: ?>
                    <form method="POST" action="<?= url('production/purchases/supply-inward/' . $purchase['id']) ?>" class="d-inline" id="supplyInwardForm" onsubmit="return confirmInwardSupplies(event);">
                        <?= CSRF::field() ?>
                        <?php if ($selectedItemId > 0): ?>
                            <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">
                        <?php endif; ?>
                        <input type="hidden" name="selected_qr_codes" id="inwardSelectedQrCodes" value="">
                        <button type="submit" class="btn btn-success btn-sm fw-bold shadow-sm d-inline-flex align-items-center gap-1" id="btnMoveToInward" <?= $canManageSupplies ? '' : 'disabled style="opacity: 0.5; cursor: not-allowed;" title="Action disabled in view-only access"' ?>>
                            <i class="bi bi-box-arrow-in-down"></i> <span id="btnMoveToInwardText">Move to Inward (<?= (int)($pendingInwardCount ?? count($stickers)) ?>)</span>
                        </button>
                    </form>
                    <?php if (($inwardedCount ?? 0) > 0): ?>
                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1 small fw-semibold" title="<?= (int)$inwardedCount ?> labels already inwarded">
                            <i class="bi bi-check-circle me-1"></i><?= (int)$inwardedCount ?> Inwarded
                        </span>
                        <a href="<?= url('production/inventory') ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-seam me-1"></i> Inventory
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Mode Switcher: Per Piece vs Master Package -->
                <div class="btn-group btn-group-sm" role="group" title="Choose label generation mode">
                    <a href="<?= url('production/purchases/supply-qr/' . $purchase['id'] . '?mode=piece' . ($selectedItemId ? '&item_id=' . $selectedItemId : '')) ?>" class="btn <?= $mode === 'piece' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-grid-3x3-gap me-1"></i> Per Piece
                    </a>
                    <a href="<?= url('production/purchases/supply-qr/' . $purchase['id'] . '?mode=package' . ($selectedItemId ? '&item_id=' . $selectedItemId : '')) ?>" class="btn <?= $mode === 'package' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-box-seam me-1"></i> Per Item Line
                    </a>
                </div>

                <div class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0 bg-light border px-2 py-1 rounded">
                    <input class="form-check-input ms-0" type="checkbox" id="selectAllSupplies" <?= $canManageSupplies ? 'checked onchange="toggleSelectAllSupplies(this.checked)" style="cursor: pointer;"' : 'disabled style="cursor: not-allowed; opacity: 0.5;"' ?>>
                    <label class="form-check-label small fw-bold text-dark <?= $canManageSupplies ? 'cursor-pointer' : '' ?> mb-0" for="selectAllSupplies">Select All (<span id="selectedCount"><?= count($stickers) ?></span>/<?= count($stickers) ?>)<?= !$canManageSupplies ? ' <span class="badge bg-secondary-subtle text-secondary border px-1.5 py-0.5 ms-1" style="font-size:10px;">View Only</span>' : '' ?></label>
                </div>

                <button type="button" class="btn btn-dark btn-sm fw-bold px-3 shadow-sm" id="printSelectedBtn" <?= $canManageSupplies ? 'onclick="printSelectedLabels()"' : 'disabled style="opacity: 0.5; cursor: not-allowed;" title="Printing disabled in view-only access"' ?>>
                    <i class="bi bi-printer me-1 text-warning"></i> Print Selected QR Labels (<span id="btnSelectedCount"><?= count($stickers) ?></span>)
                </button>
            </div>
        </div>

        <?php if ($selectedItemId > 0): ?>
            <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between small">
                <span class="text-muted"><i class="bi bi-filter me-1 text-primary"></i> Filtered to selected supply item only.</span>
                <a href="<?= url('production/purchases/supply-qr/' . $purchase['id'] . ($mode ? '?mode=' . $mode : '')) ?>" class="text-decoration-none fw-semibold">
                    <i class="bi bi-x-circle me-1"></i> Show All Supplies on this Purchase
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Supplies Status -->
    <?php if (empty($stickers)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-3">
            <i class="bi bi-inbox fs-1 text-secondary opacity-50 mb-3"></i>
            <h4 class="fw-bold text-dark">No Other Garment Supplies In This Purchase</h4>
            <p class="text-muted">This purchase record contains fabric rolls only or no garment supply items were recorded.</p>
            <div class="mt-3 d-flex justify-content-center gap-2">
                <?php if ($rollCount > 0): ?>
                    <a href="<?= url('production/purchases/roll-qr/' . $purchase['id']) ?>" class="btn btn-dark btn-sm">
                        <i class="bi bi-qr-code text-warning me-1"></i> View Roll QR Labels (<?= $rollCount ?>)
                    </a>
                <?php endif; ?>
                <a href="<?= url('production/purchases/edit/' . $purchase['id']) ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Edit Purchase &amp; Add Supplies
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-2 d-flex justify-content-between align-items-center no-print">
            <span class="text-muted small fw-semibold">
                <i class="bi bi-tags me-1 text-success"></i> Displaying <strong><?= count($stickers) ?></strong> thermal garment supply label stickers (50x30mm standard):
            </span>
            <span class="badge bg-secondary-subtle text-secondary border">Check supplies to print only selected labels</span>
        </div>

        <div class="stickers-wrapper" id="stickersContainer">
            <?php foreach ($stickers as $stk): ?>
                <?php $stkIsInwarded = !empty($stk['is_inwarded']); ?>
                <div class="supply-label-card <?= $stkIsInwarded ? 'border-success' : '' ?>" id="card-<?= $stk['index'] ?>" style="<?= $stkIsInwarded ? 'background-color: #f8fafc;' : '' ?>">
                    <div class="label-header">
                        <div class="d-flex align-items-center gap-1">
                            <input type="checkbox" class="form-check-input supply-select-box no-print" id="check-supply-<?= $stk['index'] ?>" data-index="<?= $stk['index'] ?>" data-code="<?= htmlspecialchars($stk['code']) ?>" data-inwarded="<?= $stkIsInwarded ? '1' : '0' ?>" checked <?= $canManageSupplies ? 'onchange="updateSelectedCount()" title="Check to print or inward this supply label" style="margin-top: 0; cursor: pointer;"' : 'disabled style="margin-top: 0; cursor: not-allowed; opacity: 0.5;" title="Selection disabled in view-only access"' ?>>
                            <div class="company-tag" title="<?= htmlspecialchars($stk['supplier_name']) ?>">
                                <?= htmlspecialchars($stk['supplier_name']) ?>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <?php if ($stkIsInwarded): ?>
                                <span class="badge bg-success text-white px-1.5 py-0.5 no-print" style="font-size: 8px;" title="Inwarded to inventory on <?= !empty($stk['inwarded_at']) ? date('d M Y, h:i A', strtotime($stk['inwarded_at'])) : 'Done' ?>"><i class="bi bi-check-circle-fill"></i> Inwarded</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-1.5 py-0.5 no-print" style="font-size: 8px;"><i class="bi bi-hourglass-split"></i> Pending Inward</span>
                            <?php endif; ?>
                            <div class="po-tag"><?= htmlspecialchars($stk['po_number']) ?></div>
                        </div>
                    </div>

                    <div class="label-body">
                        <!-- Dynamic QR Canvas/Image -->
                        <div class="qr-box" id="qr-box-<?= $stk['index'] ?>" data-qr='<?= htmlspecialchars($stk['qr_payload'], ENT_QUOTES, 'UTF-8') ?>'></div>

                        <div class="label-meta">
                            <span class="supply-badge-num" title="<?= htmlspecialchars($stk['display_title']) ?>"><?= htmlspecialchars($stk['display_title']) ?></span>
                            
                            <div class="meta-line">
                                <span>Type:</span> <strong>GARMENT SUPPLY</strong>
                            </div>
                            <?php if (!empty($stk['hsn_code']) && $stk['hsn_code'] !== '—'): ?>
                                <div class="meta-line">
                                    <span>HSN:</span> <strong class="font-monospace"><?= htmlspecialchars($stk['hsn_code']) ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="meta-line">
                                <span>Price:</span> <strong>₹<?= number_format((float)$stk['unit_price'], 2) ?></strong>
                            </div>
                            <div class="meta-line">
                                <span>Code:</span> <strong class="font-monospace" style="font-size: 10.5px; font-weight: 800; color: #0f172a; letter-spacing: 0.3px;"><?= htmlspecialchars($stk['code']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Render high-readability QR codes (same standard 72x72 as roll labels)
    document.querySelectorAll('.qr-box').forEach(function(box) {
        const payload = box.getAttribute('data-qr');
        if (payload && typeof QRCode !== 'undefined') {
            try {
                box.innerHTML = '';
                new QRCode(box, {
                    text: payload,
                    width: 72,
                    height: 72,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
                setTimeout(function() {
                    box.removeAttribute('title');
                    box.querySelectorAll('img, canvas').forEach(function(el) {
                        el.removeAttribute('title');
                        el.setAttribute('alt', payload);
                    });
                }, 40);
            } catch (err) {
                console.error("QR Code Error:", err);
            }
        }
    });

    updateSelectedCount();
});

const canManageSupplies = <?= json_encode((bool)$canManageSupplies) ?>;

function toggleSelectAllSupplies(checked) {
    if (!canManageSupplies) return;
    document.querySelectorAll('.supply-select-box').forEach(cb => {
        cb.checked = checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const boxes = document.querySelectorAll('.supply-select-box');
    let totalCount = 0;
    let pendingSelectedCount = 0;
    const selectedPendingCodes = [];

    boxes.forEach(cb => {
        const card = document.getElementById('card-' + cb.dataset.index);
        const code = cb.getAttribute('data-code');
        const isInwarded = cb.getAttribute('data-inwarded') === '1';

        if (cb.checked) {
            totalCount++;
            if (card) card.classList.remove('d-print-none', 'unselected-supply');
            if (!isInwarded && code) {
                pendingSelectedCount++;
                selectedPendingCodes.push(code);
            }
        } else {
            if (card) card.classList.add('d-print-none', 'unselected-supply');
        }
    });

    const countEl = document.getElementById('selectedCount');
    const btnCountEl = document.getElementById('btnSelectedCount');
    const selectAll = document.getElementById('selectAllSupplies');
    if (countEl) countEl.textContent = totalCount;
    if (btnCountEl) btnCountEl.textContent = totalCount;
    if (selectAll) selectAll.checked = (totalCount === boxes.length && boxes.length > 0);

    const printBtn = document.getElementById('printSelectedBtn');
    if (printBtn) {
        printBtn.disabled = (totalCount === 0);
    }

    // Update inward form selection
    const inwardInput = document.getElementById('inwardSelectedQrCodes');
    if (inwardInput) {
        inwardInput.value = selectedPendingCodes.join(',');
    }

    const inwardBtnText = document.getElementById('btnMoveToInwardText');
    const inwardBtn = document.getElementById('btnMoveToInward');
    if (inwardBtnText) {
        const totalPending = <?= (int)($pendingInwardCount ?? count($stickers)) ?>;
        if (pendingSelectedCount === 0) {
            inwardBtnText.textContent = 'Move to Inward (0 Selected)';
            if (inwardBtn) inwardBtn.disabled = true;
        } else if (pendingSelectedCount === totalPending) {
            inwardBtnText.textContent = 'Move to Inward (All ' + pendingSelectedCount + ')';
            if (inwardBtn && canManageSupplies) inwardBtn.disabled = false;
        } else {
            inwardBtnText.textContent = 'Move to Inward (' + pendingSelectedCount + ' Selected)';
            if (inwardBtn && canManageSupplies) inwardBtn.disabled = false;
        }
    }
}

function printSelectedLabels() {
    if (!canManageSupplies) {
        alert('Action restricted: You only have view permission.');
        return;
    }
    const checked = document.querySelectorAll('.supply-select-box:checked');
    if (checked.length === 0) {
        alert('Please select at least 1 supply label to print.');
        return;
    }
    updateSelectedCount();
    window.print();
}

function printSingleLabel(index) {
    if (!canManageSupplies) {
        alert('Action restricted: You only have view permission.');
        return;
    }
    const card = document.getElementById('card-' + index);
    if (!card) return;

    const printWin = window.open('', '_blank', 'width=380,height=300');
    if (!printWin) {
        alert('Please allow popups to print individual supply label.');
        return;
    }

    // Clone card and strip non-print elements
    const clone = card.cloneNode(true);
    clone.querySelectorAll('.no-print, .single-print-btn, .supply-select-box, .label-footer').forEach(el => el.remove());
    const cardHtml = clone.outerHTML;

    printWin.document.open();
    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Supply Label Print</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { margin: 0; padding: 10px; font-family: sans-serif; background: #fff; }
                .supply-label-card { width: 270px; border: 1.5px solid #000; padding: 8px 10px; border-radius: 6px; display: flex; flex-direction: column; justify-content: flex-start; gap: 4px; }
                .single-print-btn, .supply-select-box, .no-print, .label-footer { display: none !important; }
                .label-header { border-bottom: 1px dashed #94a3b8; padding-bottom: 3px; margin-bottom: 4px; display: flex; justify-content: space-between; }
                .company-tag { font-size: 8.5px; font-weight: 800; text-transform: uppercase; }
                .po-tag { font-size: 8px; font-family: monospace; font-weight: bold; }
                .label-body { display: flex; align-items: center; gap: 8px; }
                .qr-box { width: 72px; height: 72px; flex-shrink: 0; }
                .qr-box img, .qr-box canvas { width: 100% !important; height: 100% !important; }
                .supply-badge-num { font-size: 11px; font-weight: 900; font-family: monospace; display: block; margin-bottom: 2px; }
                .label-meta { font-size: 9px; line-height: 1.3; }
                .meta-line { display: flex; gap: 4px; }
            </style>
        </head>
        <body onload="window.print(); setTimeout(() => window.close(), 500);">
            ${cardHtml}
        </body>
        </html>
    `);
    printWin.document.close();
}

function confirmInwardSupplies(event) {
    if (!canManageSupplies) {
        alert('Action restricted: You do not have permission to inward supplies.');
        event.preventDefault();
        return false;
    }

    const inwardInput = document.getElementById('inwardSelectedQrCodes');
    const selectedCodes = inwardInput && inwardInput.value ? inwardInput.value.split(',').filter(Boolean) : [];

    let msg = '';
    if (selectedCodes.length > 0) {
        msg = "Are you sure you want to Move to Inward " + selectedCodes.length + " selected QR label(s)?\n\nThis will automatically register these garment supply items into inventory and credit their stock balances.";
    } else {
        msg = "Are you sure you want to Move to Inward?\n\nThis will automatically register pending garment supply product(s) into inventory and credit their stock balances.";
    }

    if (!confirm(msg)) {
        event.preventDefault();
        return false;
    }
    const btn = document.getElementById('btnMoveToInward');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Adding to Inventory...';
    }
    return true;
}
</script>

</body>
</html>
