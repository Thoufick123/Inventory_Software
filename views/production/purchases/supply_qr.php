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
                    · <strong>Total Supplies:</strong> <span class="badge bg-success-subtle text-success border border-success"><?= count($supplies) ?> Item<?= count($supplies) === 1 ? '' : 's' ?> (<?= count($stickers) ?> Labels<?= !empty($inwardedOnly) ? ' — Inwarded Only' : '' ?>)</span>
                    <?php if ($isAllInwarded): ?>
                        <span class="badge bg-success text-white ms-1"><i class="bi bi-check-circle-fill me-1"></i>Inward Complete</span>
                    <?php endif; ?>
                    <?php if (!empty($damagedCount)): ?>
                        <span class="badge bg-danger text-white ms-1"><i class="bi bi-exclamation-octagon-fill me-1"></i><?= (int)$damagedCount ?> Damaged</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= !empty($inwardedOnly) ? url('production/inventory') : url('production/purchases') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> <?= !empty($inwardedOnly) ? 'Back to Inventory' : 'Back to Purchases' ?>
                </a>

                <?php if ($isAllInwarded): ?>
                    <span class="badge bg-success-subtle text-success border border-success px-2.5 py-1.5 fw-bold d-inline-flex align-items-center gap-1 shadow-xs" title="All <?= (int)($allStickersCount ?? count($stickers)) ?> supplies processed (<?= (int)$inwardedCount ?> inwarded<?= !empty($damagedCount) ? ', ' . (int)$damagedCount . ' damaged' : '' ?>)">
                        <i class="bi bi-check-circle-fill text-success"></i> Inward Complete (<?= (int)($inwardedCount ?? count($stickers)) ?>/<?= (int)($allStickersCount ?? count($stickers)) ?>)
                    </span>
                    <?php if (!empty($damagedCount)): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger px-2.5 py-1.5 fw-bold d-inline-flex align-items-center gap-1 shadow-xs" title="<?= (int)$damagedCount ?> items recorded as production damage">
                            <i class="bi bi-exclamation-octagon-fill text-danger"></i> <?= (int)$damagedCount ?> Damaged
                        </span>
                        <a href="<?= url('production/damages') ?>" class="btn btn-outline-danger btn-sm fw-bold">
                            <i class="bi bi-shield-exclamation me-1"></i> Damages Log
                        </a>
                    <?php endif; ?>
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
                    <button type="button" class="btn btn-outline-danger btn-sm fw-bold shadow-sm d-inline-flex align-items-center gap-1" id="btnReportDamage" onclick="openBulkSupplyDamage()" <?= $canManageSupplies ? '' : 'disabled style="opacity: 0.5; cursor: not-allowed;" title="Action disabled in view-only access"' ?> title="Report defective or damaged pieces before adding to inventory">
                        <i class="bi bi-exclamation-octagon-fill"></i> <span>Report Damage</span>
                    </button>
                    <?php if (($damagedCount ?? 0) > 0): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 small fw-semibold" title="<?= (int)$damagedCount ?> items recorded as production damage">
                            <i class="bi bi-exclamation-octagon-fill me-1"></i><?= (int)$damagedCount ?> Damaged
                        </span>
                        <a href="<?= url('production/damages') ?>" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-shield-exclamation me-1"></i> Damages
                        </a>
                    <?php endif; ?>
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
                    <a href="<?= url('production/purchases/supply-qr/' . $purchase['id'] . '?mode=piece' . ($selectedItemId ? '&item_id=' . $selectedItemId : '') . (!empty($inwardedOnly) ? '&inwarded_only=1' : '')) ?>" class="btn <?= $mode === 'piece' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-grid-3x3-gap me-1"></i> Per Piece
                    </a>
                    <a href="<?= url('production/purchases/supply-qr/' . $purchase['id'] . '?mode=package' . ($selectedItemId ? '&item_id=' . $selectedItemId : '') . (!empty($inwardedOnly) ? '&inwarded_only=1' : '')) ?>" class="btn <?= $mode === 'package' ? 'btn-primary' : 'btn-outline-primary' ?>">
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

        <?php if (!empty($inwardedOnly)): ?>
            <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between small flex-wrap gap-2">
                <span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Showing <strong>Inwarded QR Labels only (<?= count($stickers) ?>)</strong> from Production Inventory.</span>
                <a href="<?= url('production/purchases/supply-qr/' . $purchase['id'] . ($selectedItemId ? '?item_id=' . $selectedItemId : '')) ?>" class="btn btn-outline-primary btn-sm py-0.5 px-2.5 fw-semibold">
                    <i class="bi bi-grid me-1"></i> Show All <?= (int)($allStickersCount ?? count($stickers)) ?> QR Labels
                </a>
            </div>
        <?php elseif ($selectedItemId > 0): ?>
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
            <i class="bi bi-qr-code-scan fs-1 text-secondary opacity-50 mb-3"></i>
            <h4 class="fw-bold text-dark"><?= !empty($inwardedOnly) ? 'No Inwarded QR Labels Found' : 'No Other Garment Supplies In This Purchase' ?></h4>
            <p class="text-muted"><?= !empty($inwardedOnly) ? 'No labels for this supply item have been inwarded to inventory yet.' : 'This purchase record contains fabric rolls only or no garment supply items were recorded.' ?></p>
            <div class="mt-3 d-flex justify-content-center gap-2">
                <?php if (!empty($inwardedOnly)): ?>
                    <a href="<?= url('production/purchases/supply-qr/' . $purchase['id'] . ($selectedItemId ? '?item_id=' . $selectedItemId : '')) ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-grid me-1"></i> View All QR Labels
                    </a>
                    <a href="<?= url('production/inventory') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-box-seam me-1"></i> Back to Inventory
                    </a>
                <?php else: ?>
                    <?php if ($rollCount > 0): ?>
                        <a href="<?= url('production/purchases/roll-qr/' . $purchase['id']) ?>" class="btn btn-dark btn-sm">
                            <i class="bi bi-qr-code text-warning me-1"></i> View Roll QR Labels (<?= $rollCount ?>)
                        </a>
                    <?php endif; ?>
                    <a href="<?= url('production/purchases/edit/' . $purchase['id']) ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Edit Purchase &amp; Add Supplies
                    </a>
                <?php endif; ?>
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
                <?php 
                    $stkIsDamaged = !empty($stk['is_damaged']);
                    $stkIsInwarded = !empty($stk['is_inwarded']); 
                    $cardClass = $stkIsDamaged ? 'border-danger bg-danger-subtle bg-opacity-25' : ($stkIsInwarded ? 'border-success' : '');
                ?>
                <div class="supply-label-card <?= $cardClass ?>" id="card-<?= $stk['index'] ?>" style="<?= $stkIsDamaged ? 'background-color: #fff5f5;' : ($stkIsInwarded ? 'background-color: #f8fafc;' : '') ?>">
                    <div class="label-header">
                        <div class="d-flex align-items-center gap-1">
                            <input type="checkbox" class="form-check-input supply-select-box no-print" id="check-supply-<?= $stk['index'] ?>" data-index="<?= $stk['index'] ?>" data-code="<?= htmlspecialchars($stk['code']) ?>" data-inwarded="<?= $stkIsInwarded ? '1' : '0' ?>" data-damaged="<?= $stkIsDamaged ? '1' : '0' ?>" <?= $stkIsDamaged ? 'disabled style="margin-top: 0; cursor: not-allowed; opacity: 0.4;" title="Damaged item — excluded from stock inwarding"' : ($canManageSupplies ? 'checked onchange="updateSelectedCount()" title="Check to print or inward this supply label" style="margin-top: 0; cursor: pointer;"' : 'disabled style="margin-top: 0; cursor: not-allowed; opacity: 0.5;" title="Selection disabled in view-only access"') ?>>
                            <div class="company-tag" title="<?= htmlspecialchars($stk['supplier_name']) ?>">
                                <?= htmlspecialchars($stk['supplier_name']) ?>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <?php if ($stkIsDamaged): ?>
                                <span class="badge bg-danger text-white px-1.5 py-0.5 no-print" style="font-size: 8px;" title="Defect: <?= htmlspecialchars($stk['damage_info']['damage_reason'] ?? 'Logged') ?> • <?= htmlspecialchars($stk['damage_info']['damage_number'] ?? 'DMG') ?>"><i class="bi bi-exclamation-octagon-fill"></i> Damaged</span>
                            <?php elseif ($stkIsInwarded): ?>
                                <span class="badge bg-success text-white px-1.5 py-0.5 no-print" style="font-size: 8px;" title="Inwarded to inventory on <?= !empty($stk['inwarded_at']) ? date('d M Y, h:i A', strtotime($stk['inwarded_at'])) : 'Done' ?>"><i class="bi bi-check-circle-fill"></i> Inwarded</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-1.5 py-0.5 no-print" style="font-size: 8px;"><i class="bi bi-hourglass-split"></i> Pending</span>
                                <?php if ($canManageSupplies): ?>
                                    <button type="button" class="btn btn-outline-danger btn-xs py-0 px-1 border-0 no-print fw-bold" style="font-size: 8.5px;" onclick='openSingleSupplyDamage(<?= json_encode([
                                        'code' => $stk['code'],
                                        'product_name' => $stk['display_title'],
                                        'supply_item_id' => $stk['supply_item_id'] ?? null,
                                        'price' => (float)$stk['unit_price'],
                                        'supplier_name' => $stk['supplier_name'],
                                        'po_number' => $stk['po_number'],
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)' title="Report defect/damage for this piece before inwarding"><i class="bi bi-exclamation-triangle text-danger"></i> Defect</button>
                                <?php endif; ?>
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
                            <?php if ($stkIsDamaged && !empty($stk['damage_info'])): ?>
                                <div class="meta-line text-danger mt-1 pt-1 border-top border-danger-subtle" style="font-size: 8.5px;">
                                    <span>Defect:</span> <strong title="<?= htmlspecialchars($stk['damage_info']['damage_reason'] ?? '') ?>"><?= htmlspecialchars($stk['damage_info']['damage_reason'] ?? 'Logged Damage') ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Report Damage Modal for Garment Supplies -->
<div class="modal fade no-print" id="supplyDamageModal" tabindex="-1" aria-hidden="true" style="overflow-y: auto;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-danger">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Report Damage / Defect (Garment Supplies)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('production/damages/store') ?>" enctype="multipart/form-data" id="supplyDamageForm">
                <?= CSRF::field() ?>
                <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? url('production/purchases/supply-qr/' . $purchase['id'])) ?>">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                    <label for="supplyDamageQrScan" class="form-label fw-bold mb-0">
                                        <i class="bi bi-upc-scan text-success me-1"></i>Scan Garment Supply Barcode / QR Tag
                                    </label>
                                    <small class="text-muted">Hardware scanner or manual entry</small>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-success border-end-0">
                                        <i class="bi bi-qr-code"></i>
                                    </span>
                                    <input type="text" id="supplyDamageQrScan" class="form-control border-start-0" placeholder="Scan an SP label (e.g. SP112601) and press Enter" autocomplete="off">
                                    <button type="button" class="btn btn-success fw-semibold px-3" onclick="scanSupplyDamageQr()">
                                        <i class="bi bi-plus-circle me-1"></i>Scan / Add
                                    </button>
                                </div>
                            </div>
                            <div id="supplyDamageScanStatus" class="small mt-1" role="status" aria-live="polite"></div>
                            <div class="border rounded mt-2 p-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Defective / Damaged Supply Items</strong>
                                    <span id="supplyDamageScannedCount" class="badge bg-secondary">0 items</span>
                                </div>
                                <div id="supplyDamageScannedLabels" class="list-group small" style="max-height: none; overflow: visible;">
                                    <div class="list-group-item text-muted" id="supplyDamageScannedEmpty">No items selected or scanned yet.</div>
                                </div>
                            </div>
                            <div id="supplyDamageQrInputs"></div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-danger-subtle border border-danger-subtle text-danger py-2 mb-0 small">
                                <i class="bi bi-info-circle me-1"></i>Reported supply defects are quarantined under Production Damages and deducted so they will never be inwarded to stock.
                            </div>
                        </div>

                        <!-- Estimated Loss Valuation -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-danger">
                                <i class="bi bi-currency-rupee me-1"></i>Total Estimated Loss Valuation <span class="badge bg-danger-subtle text-danger border ms-1">Auto</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger-subtle text-danger fw-bold">₹</span>
                                <input type="number" step="0.01" name="loss_amount" id="supplyInputEstimatedLoss" class="form-control fw-bold text-danger bg-light fs-6" placeholder="0.00" readonly tabindex="-1" style="background-color: #f1f3f5 !important; cursor: not-allowed;">
                            </div>
                            <small class="text-muted d-block mt-1" id="supplyLossCalcHelp">Calculated automatically from supply purchase unit price.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Additional Notes &amp; Observations <small class="text-muted fw-normal">(Optional, applies to all reported items)</small>
                            </label>
                            <textarea name="defect_notes" id="supplyDefectNotes" class="form-control" rows="2" placeholder="e.g. Broken threads received from vendor, damaged spool boxes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold shadow-sm" id="btnSaveSupplyDamage">
                        <i class="bi bi-check-circle me-1"></i> Save Damage &amp; Quarantine
                    </button>
                </div>
            </form>
        </div>
    </div>
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

    const scanInput = document.getElementById('supplyDamageQrScan');
    if (scanInput) {
        scanInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                scanSupplyDamageQr();
            }
        });
    }

    const damageForm = document.getElementById('supplyDamageForm');
    if (damageForm) {
        damageForm.addEventListener('submit', function(e) {
            if (supplyDamageList.length === 0) {
                alert('Please scan or select at least 1 supply piece to report damage.');
                e.preventDefault();
                return false;
            }
            // Check that each item has a damage reason
            for (let i = 0; i < supplyDamageList.length; i++) {
                const item = supplyDamageList[i];
                if (!item.damageReason) {
                    alert('Please select a Damage / Defect Reason for item ' + item.code);
                    e.preventDefault();
                    return false;
                }
            }
            const saveBtn = document.getElementById('btnSaveSupplyDamage');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving Damage...';
            }
            return true;
        });
    }

    updateSelectedCount();
});

const canManageSupplies = <?= json_encode((bool)$canManageSupplies) ?>;
const availableDamageReasons = <?= json_encode(array_column($damageReasons ?? [], 'reason')) ?>;
const currentPurchaseStickers = <?= json_encode(array_map(fn($s) => [
    'code' => $s['code'],
    'product_name' => $s['display_title'],
    'supply_item_id' => $s['supply_item_id'] ?? null,
    'price' => (float)$s['unit_price'],
    'supplier_name' => $s['supplier_name'],
    'po_number' => $s['po_number'],
    'is_damaged' => !empty($s['is_damaged']),
    'is_inwarded' => !empty($s['is_inwarded']),
], $stickers)) ?>;

let supplyDamageList = [];
let supplyDamageModalInstance = null;

function getSupplyDamageModal() {
    if (!supplyDamageModalInstance) {
        const el = document.getElementById('supplyDamageModal');
        if (el && typeof bootstrap !== 'undefined') {
            supplyDamageModalInstance = new bootstrap.Modal(el);
        }
    }
    return supplyDamageModalInstance;
}

function openSingleSupplyDamage(item) {
    if (!canManageSupplies) {
        alert('Action restricted: You do not have permission to report damage.');
        return;
    }
    supplyDamageList = [{
        code: item.code || item.qr_code,
        productName: item.product_name,
        supplyItemId: item.supply_item_id || null,
        price: parseFloat(item.price) || 0,
        supplierName: item.supplier_name || '',
        poNumber: item.po_number || '',
        damageReason: '',
        photoInput: null,
        photoPreviewUrl: null
    }];
    renderSupplyDamageList();
    setSupplyDamageScanStatus('');
    const modal = getSupplyDamageModal();
    if (modal) modal.show();
}

function openBulkSupplyDamage() {
    if (!canManageSupplies) {
        alert('Action restricted: You do not have permission to report damage.');
        return;
    }

    const checkedBoxes = Array.from(document.querySelectorAll('.supply-select-box:checked'));
    const pendingSelectedBoxes = checkedBoxes.filter(cb => cb.dataset.damaged !== '1' && cb.dataset.inwarded !== '1');

    if (pendingSelectedBoxes.length > 0) {
        supplyDamageList = [];
        pendingSelectedBoxes.forEach(cb => {
            const code = cb.dataset.code;
            const sticker = currentPurchaseStickers.find(s => s.code === code);
            if (sticker) {
                supplyDamageList.push({
                    code: sticker.code,
                    productName: sticker.product_name,
                    supplyItemId: sticker.supply_item_id,
                    price: parseFloat(sticker.price) || 0,
                    supplierName: sticker.supplier_name || '',
                    poNumber: sticker.po_number || '',
                    damageReason: '',
                    photoInput: null,
                    photoPreviewUrl: null
                });
            }
        });
        renderSupplyDamageList();
        setSupplyDamageScanStatus('Loaded ' + supplyDamageList.length + ' selected item(s). Set damage reasons and attach evidence photos.');
    } else {
        // If none checked or only already inwarded/damaged, open with empty list
        supplyDamageList = [];
        renderSupplyDamageList();
        setSupplyDamageScanStatus('Scan an SP QR tag or hardware barcode above to add defective items.');
    }

    const modal = getSupplyDamageModal();
    if (modal) modal.show();
}

function setSupplyDamageScanStatus(msg, isError = false) {
    const el = document.getElementById('supplyDamageScanStatus');
    if (!el) return;
    el.textContent = msg;
    el.className = isError ? 'small mt-1 text-danger fw-semibold' : 'small mt-1 text-muted';
}

function scanSupplyDamageQr() {
    const scanInput = document.getElementById('supplyDamageQrScan');
    const scannedCode = scanInput ? scanInput.value.trim().toUpperCase().replace(/^#/, '') : '';
    if (!scannedCode) return;

    if (supplyDamageList.some(item => item.code === scannedCode)) {
        setSupplyDamageScanStatus('QR label ' + scannedCode + ' is already in this damage list.', true);
        if (scanInput) scanInput.select();
        return;
    }

    // 1. Check if it's already in the current purchase stickers list
    const foundLocal = currentPurchaseStickers.find(s => s.code === scannedCode);
    if (foundLocal) {
        if (foundLocal.is_damaged) {
            setSupplyDamageScanStatus('QR label ' + scannedCode + ' has already been reported as damaged.', true);
            return;
        }
        supplyDamageList.push({
            code: foundLocal.code,
            productName: foundLocal.product_name,
            supplyItemId: foundLocal.supply_item_id,
            price: parseFloat(foundLocal.price) || 0,
            supplierName: foundLocal.supplier_name || '',
            poNumber: foundLocal.po_number || '',
            damageReason: '',
            photoInput: null,
            photoPreviewUrl: null
        });
        renderSupplyDamageList();
        setSupplyDamageScanStatus('Added ' + foundLocal.code + ' (' + foundLocal.product_name + ')');
        if (scanInput) scanInput.value = '';
        return;
    }

    // 2. Fallback to API lookup for other supply tags or roll labels
    setSupplyDamageScanStatus('Looking up QR label: ' + scannedCode + '…');
    fetch('<?= url('api/production/damages/scan-roll') ?>?code=' + encodeURIComponent(scannedCode))
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'QR label not found.');
            }
            supplyDamageList.push({
                code: data.code,
                productName: data.roll_number || data.product_name || 'Garment Supply',
                supplyItemId: data.supply_item_id || null,
                price: parseFloat(data.price || data.loss || 0),
                supplierName: data.seller_name || '',
                poNumber: '',
                damageReason: '',
                photoInput: null,
                photoPreviewUrl: null
            });
            renderSupplyDamageList();
            setSupplyDamageScanStatus('Added ' + data.code + ' (' + (data.roll_number || data.code) + ')');
            if (scanInput) scanInput.value = '';
        })
        .catch(err => {
            setSupplyDamageScanStatus(err.message || 'Unable to scan this item.', true);
        });
}

function removeSupplyDamageItem(index) {
    if (supplyDamageList[index] && supplyDamageList[index].photoPreviewUrl) {
        URL.revokeObjectURL(supplyDamageList[index].photoPreviewUrl);
    }
    supplyDamageList.splice(index, 1);
    renderSupplyDamageList();
}

function renderSupplyDamageList() {
    const container = document.getElementById('supplyDamageScannedLabels');
    const countBadge = document.getElementById('supplyDamageScannedCount');
    const inputsContainer = document.getElementById('supplyDamageQrInputs');
    if (!container || !inputsContainer) return;

    container.innerHTML = '';
    inputsContainer.innerHTML = '';

    if (countBadge) {
        countBadge.textContent = supplyDamageList.length + ' item' + (supplyDamageList.length === 1 ? '' : 's');
    }

    if (supplyDamageList.length === 0) {
        container.innerHTML = '<div class="list-group-item text-muted" id="supplyDamageScannedEmpty">No items selected or scanned yet.</div>';
        updateSupplyDamageLossCalculations(0);
        return;
    }

    let totalLoss = 0;

    supplyDamageList.forEach((item, index) => {
        totalLoss += (parseFloat(item.price) || 0);

        const card = document.createElement('div');
        card.className = 'list-group-item p-3 border mb-2 rounded bg-white shadow-xs';

        // Header line
        const header = document.createElement('div');
        header.className = 'd-flex justify-content-between align-items-center mb-2 flex-wrap gap-1';

        const titleDiv = document.createElement('div');
        titleDiv.className = 'd-flex align-items-center gap-1.5 flex-wrap';

        const codeBadge = document.createElement('span');
        codeBadge.className = 'badge bg-dark font-monospace';
        codeBadge.textContent = item.code;

        const supplyBadge = document.createElement('span');
        supplyBadge.className = 'badge bg-info-subtle text-info border';
        supplyBadge.textContent = 'Supply';

        const descSpan = document.createElement('span');
        descSpan.className = 'fw-bold text-dark';
        const metaParts = [item.supplierName, item.productName, '1 PCS', '₹' + item.price.toFixed(2)].filter(Boolean);
        descSpan.textContent = metaParts.join(' • ');

        titleDiv.append(codeBadge, supplyBadge, descSpan);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-outline-danger btn-sm py-0 px-2 fw-semibold';
        removeBtn.textContent = 'Remove';
        removeBtn.onclick = () => removeSupplyDamageItem(index);

        header.append(titleDiv, removeBtn);

        // Fields row (Reason & Photo)
        const fieldsRow = document.createElement('div');
        fieldsRow.className = 'row g-2 mt-1';

        // Reason column
        const reasonCol = document.createElement('div');
        reasonCol.className = 'col-md-6';
        const reasonLabel = document.createElement('label');
        reasonLabel.className = 'form-label small fw-semibold mb-1';
        reasonLabel.innerHTML = 'Damage / Defect Reason <span class="text-danger">*</span>';

        const reasonSelect = document.createElement('select');
        reasonSelect.className = 'form-select form-select-sm';
        reasonSelect.name = `damage_reasons[${index}]`;
        reasonSelect.required = true;

        const defOpt = document.createElement('option');
        defOpt.value = '';
        defOpt.textContent = '-- Select Reason --';
        reasonSelect.appendChild(defOpt);

        availableDamageReasons.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r;
            opt.textContent = r;
            if (item.damageReason === r) opt.selected = true;
            reasonSelect.appendChild(opt);
        });

        reasonSelect.onchange = () => {
            item.damageReason = reasonSelect.value;
        };

        reasonCol.append(reasonLabel, reasonSelect);

        // Photo column
        const photoCol = document.createElement('div');
        photoCol.className = 'col-md-6';
        const photoLabel = document.createElement('label');
        photoLabel.className = 'form-label small fw-semibold mb-1';
        photoLabel.innerHTML = 'Defect Photo / Evidence <span class="text-danger">*</span>';

        if (!item.photoInput) {
            item.photoInput = document.createElement('input');
            item.photoInput.type = 'file';
            item.photoInput.accept = 'image/*,application/pdf';
            item.photoInput.className = 'form-control form-control-sm';
            item.photoInput.required = true;
            item.photoInput.addEventListener('change', () => {
                if (item.photoPreviewUrl) {
                    URL.revokeObjectURL(item.photoPreviewUrl);
                    item.photoPreviewUrl = null;
                }
                const file = item.photoInput.files && item.photoInput.files[0];
                if (!file || !file.type.startsWith('image/')) {
                    if (item.photoPreviewImage) item.photoPreviewImage.removeAttribute('src');
                    if (item.photoPreviewContainer) item.photoPreviewContainer.classList.add('d-none');
                    return;
                }
                item.photoPreviewUrl = URL.createObjectURL(file);
                if (item.photoPreviewImage) item.photoPreviewImage.src = item.photoPreviewUrl;
                if (item.photoPreviewContainer) item.photoPreviewContainer.classList.remove('d-none');
            });
        }
        item.photoInput.name = `damage_photos[${index}]`;

        const photoPreview = document.createElement('div');
        photoPreview.className = `mt-1 ${item.photoPreviewUrl ? '' : 'd-none'}`;
        const previewImage = document.createElement('img');
        previewImage.className = 'rounded border bg-white p-1';
        previewImage.style.cssText = 'display:block;max-width:100%;max-height:80px;object-fit:contain;';
        if (item.photoPreviewUrl) previewImage.src = item.photoPreviewUrl;
        photoPreview.appendChild(previewImage);
        item.photoPreviewContainer = photoPreview;
        item.photoPreviewImage = previewImage;

        photoCol.append(photoLabel, item.photoInput, photoPreview);

        fieldsRow.append(reasonCol, photoCol);
        card.append(header, fieldsRow);
        container.appendChild(card);

        // Hidden input for QR code
        const hiddenQr = document.createElement('input');
        hiddenQr.type = 'hidden';
        hiddenQr.name = `qr_codes[${index}]`;
        hiddenQr.value = item.code;
        inputsContainer.appendChild(hiddenQr);

        // Hidden input for supply item id
        const hiddenSupplyId = document.createElement('input');
        hiddenSupplyId.type = 'hidden';
        hiddenSupplyId.name = `damage_supply_item_ids[${index}]`;
        hiddenSupplyId.value = item.supplyItemId || '';
        inputsContainer.appendChild(hiddenSupplyId);
    });

    updateSupplyDamageLossCalculations(totalLoss);
}

function updateSupplyDamageLossCalculations(totalLoss) {
    const lossInput = document.getElementById('supplyInputEstimatedLoss');
    const help = document.getElementById('supplyLossCalcHelp');
    if (lossInput) lossInput.value = totalLoss.toFixed(2);
    if (help) {
        help.textContent = `Estimated loss for ${supplyDamageList.length} item(s): ₹${totalLoss.toFixed(2)}`;
    }
}

function toggleSelectAllSupplies(checked) {
    if (!canManageSupplies) return;
    document.querySelectorAll('.supply-select-box').forEach(cb => {
        if (cb.dataset.damaged === '1') {
            cb.checked = false;
        } else {
            cb.checked = checked;
        }
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
        const isDamaged = cb.getAttribute('data-damaged') === '1';

        if (cb.checked) {
            totalCount++;
            if (card) card.classList.remove('d-print-none', 'unselected-supply');
            if (!isInwarded && !isDamaged && code) {
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
