<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Roll QR Labels') ?> — GRM Inventory</title>
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

        .roll-qr-container {
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

        .roll-label-card {
            width: 275px;
            min-height: 155px;
            background: #ffffff;
            border: 1.5px solid #0f172a;
            border-radius: 8px;
            padding: 10px 12px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            break-inside: avoid;
            page-break-inside: avoid;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .roll-label-card:hover {
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
            color: #0284c7;
            font-family: monospace;
            background: #e0f2fe;
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

        .roll-badge-num {
            font-family: monospace;
            font-size: 11px;
            font-weight: 900;
            color: #0f172a;
            display: block;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
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
            justify-content: space-between;
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
            }
            .action-bar, .single-print-btn, .no-print {
                display: none !important;
            }
            .roll-qr-container {
                max-width: none !important;
                margin: 0 !important;
            }
            .stickers-wrapper {
                gap: 8px !important;
                justify-content: flex-start !important;
            }
            .roll-label-card {
                border: 1.5px solid #000 !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

<div class="roll-qr-container">
    <!-- Top Action & Info Bar -->
    <div class="action-bar no-print">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary fs-6 px-2 py-1"><i class="bi bi-qr-code-scan me-1"></i> Roll QR Module</span>
                    <h3 class="mb-0 fw-bold fs-4 text-dark"><?= htmlspecialchars($purchase['purchase_number']) ?></h3>
                </div>
                <div class="text-muted small">
                    <strong>Supplier:</strong> <?= htmlspecialchars($purchase['supplier_company'] ?: ($purchase['supplier_name'] ?? 'Direct Supplier')) ?>
                    · <strong>PO Date:</strong> <?= date('d M Y', strtotime($purchase['purchase_date'])) ?>
                    · <strong>Invoice:</strong> <?= htmlspecialchars($purchase['invoice_number'] ?: 'N/A') ?>
                    · <strong>Total Rolls:</strong> <span class="badge bg-success-subtle text-success border border-success"><?= count($stickers) ?> Rolls</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="<?= url('production/purchases') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Purchases
                </a>
                <a href="<?= url('production/purchases/view/' . $purchase['id']) ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye me-1"></i> View Order
                </a>
                <button type="button" class="btn btn-dark btn-sm fw-bold px-3 shadow-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1 text-warning"></i> Print All QR Labels (<?= count($stickers) ?>)
                </button>
            </div>
        </div>
    </div>

    <!-- Rolls Status Bar -->
    <?php if (empty($stickers)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-3">
            <i class="bi bi-inbox fs-1 text-secondary opacity-50 mb-3"></i>
            <h4 class="fw-bold text-dark">No Fabric Rolls In This Purchase</h4>
            <p class="text-muted">This purchase record contains general supplies only or no roll items were recorded.</p>
            <div class="mt-3">
                <a href="<?= url('production/purchases/edit/' . $purchase['id']) ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Edit Purchase &amp; Add Rolls
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-3 d-flex justify-content-between align-items-center no-print">
            <span class="text-muted small fw-semibold">
                <i class="bi bi-layers me-1 text-primary"></i> Displaying <strong><?= count($stickers) ?></strong> thermal fabric roll label stickers (50x30mm standard):
            </span>
            <span class="badge bg-secondary-subtle text-secondary border">Scan with Handheld or Mobile Barcode Scanner</span>
        </div>

        <div class="stickers-wrapper" id="stickersContainer">
            <?php foreach ($stickers as $stk): ?>
                <div class="roll-label-card" id="card-<?= $stk['index'] ?>">
                    <div class="label-header">
                        <div class="company-tag" title="<?= htmlspecialchars($stk['supplier_name']) ?>">
                            <?= htmlspecialchars($stk['supplier_name']) ?>
                        </div>
                        <div class="po-tag"><?= htmlspecialchars($stk['po_number']) ?></div>
                    </div>

                    <div class="label-body">
                        <!-- Dynamic QR Canvas/Image -->
                        <div class="qr-box" id="qr-box-<?= $stk['index'] ?>" data-qr='<?= htmlspecialchars($stk['qr_payload'], ENT_QUOTES, 'UTF-8') ?>'></div>

                        <div class="label-meta">
                            <span class="roll-badge-num"><?= htmlspecialchars($stk['roll_number']) ?></span>
                            <div class="meta-line">
                                <span>Fabric:</span> <strong><?= htmlspecialchars(mb_strimwidth($stk['fabric_type'], 0, 16, '...')) ?></strong>
                            </div>
                            <div class="meta-line">
                                <span>Color:</span> <strong><?= htmlspecialchars($stk['color'] ?: 'STANDARD') ?></strong>
                            </div>
                            <div class="meta-line">
                                <span>Weight:</span> <strong style="color:#0284c7"><?= number_format((float)$stk['weight'], 2) ?> kg</strong>
                            </div>
                            <div class="meta-line">
                                <span>Code:</span> <span class="font-monospace text-muted" style="font-size:8px"><?= htmlspecialchars($stk['code']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="label-footer">
                        <span>GRM MFG · <?= $stk['purchase_date'] ?></span>
                        <button type="button" class="btn btn-outline-dark btn-xs single-print-btn no-print" onclick="printSingleLabel(<?= $stk['index'] ?>)" title="Print only this label">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generate clean, high-readability low-density QR codes (large bold pixels like industrial 21x21 QR)
    document.querySelectorAll('.qr-box').forEach(function(box) {
        const payload = box.getAttribute('data-qr');
        if (payload && typeof QRCode !== 'undefined') {
            try {
                // Clear any existing children
                box.innerHTML = '';
                new QRCode(box, {
                    text: payload,
                    width: 72,
                    height: 72,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
                // Remove browser tooltip popups from img/canvas
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
});

function printSingleLabel(index) {
    const card = document.getElementById('card-' + index);
    if (!card) return;

    const printWin = window.open('', '_blank', 'width=380,height=300');
    if (!printWin) {
        alert('Please allow popups to print individual roll label.');
        return;
    }

    const cardHtml = card.outerHTML;
    printWin.document.open();
    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Roll Label Print</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { margin: 0; padding: 10px; font-family: sans-serif; }
                .roll-label-card { width: 270px; border: 1.5px solid #000; padding: 8px 10px; border-radius: 6px; }
                .single-print-btn, .no-print { display: none !important; }
                .company-tag { font-size: 8.5px; font-weight: 800; text-transform: uppercase; }
                .po-tag { font-size: 8px; font-family: monospace; font-weight: bold; }
                .qr-box { width: 72px; height: 72px; }
                .qr-box img, .qr-box canvas { width: 100% !important; height: 100% !important; }
                .roll-badge-num { font-size: 11px; font-weight: 900; font-family: monospace; }
                .label-meta { font-size: 9px; line-height: 1.3; }
                .label-footer { font-size: 8px; border-top: 1px dashed #999; margin-top: 4px; padding-top: 2px; }
            </style>
        </head>
        <body onload="window.print(); setTimeout(() => window.close(), 500);">
            ${cardHtml}
        </body>
        </html>
    `);
    printWin.document.close();
}
</script>

</body>
</html>
