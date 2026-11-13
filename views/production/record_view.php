<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-eye me-2 text-primary"></i><?= e($recordTitle) ?></h2>
        <span class="text-muted">Read-only record details</span>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<?php if (!empty($qrLabel['code'])): ?>
    <?php $isSupplyLabel = !empty($qrLabel['is_supply']) || preg_match('/^SP\d+/i', (string)$qrLabel['code']); ?>
    <div class="card border shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0 fw-bold text-danger">
                <i class="bi bi-qr-code-scan me-2"></i><?= $isSupplyLabel ? 'Damaged Garment Supply QR Label' : 'Damaged Fabric Piece QR Label' ?>
            </h5>
            <button type="button" class="btn btn-danger btn-sm fw-bold text-white shadow-sm px-3 d-inline-flex align-items-center gap-1.5" onclick="printDamageRecordQrLabel()" style="background-color: #dc3545 !important; border-color: #dc3545 !important; color: #ffffff !important;">
                <i class="bi bi-printer-fill me-1" style="color: #ffffff !important;"></i> <span style="color: #ffffff !important;">Print Label Sticker</span>
            </button>
        </div>
        <div class="card-body">
            <?php if ($isSupplyLabel): ?>
                <!-- Thermal Garment Supply Label Sticker Format (Matches 50x30mm supply sticker standard) -->
                <div class="supply-sticker-card border-danger" id="printableDamageSticker" style="width: 295px; border: 1.5px solid #dc3545; padding: 8px 10px; border-radius: 6px; background-color: #fff8f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1" style="border-bottom: 1px dashed #cbd5e1;">
                        <div class="fw-bold text-uppercase" style="font-size: 8.5px; letter-spacing: 0.3px; color: #0f172a;" title="<?= e($qrLabel['supplier_name'] ?? 'Supplier') ?>">
                            <?= e($qrLabel['supplier_name'] ?? 'SUPPLIER') ?>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-danger text-white px-1.5 py-0.5" style="font-size: 8px;" title="Quarantined Defect"><i class="bi bi-exclamation-octagon-fill"></i> Damaged</span>
                            <div class="font-monospace fw-bold text-muted" style="font-size: 8px;"><?= e($qrLabel['po_number'] ?? 'PO') ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div id="damageRecordQr" class="qr-box bg-white p-0.5 border rounded" style="width: 72px; height: 72px; flex-shrink: 0;"></div>
                        <div style="font-size: 9px; line-height: 1.35; color: #334155; flex-grow: 1;">
                            <strong class="d-block font-monospace text-dark" style="font-size: 11px; margin-bottom: 2px;"><?= e($qrLabel['display_title'] ?? $qrLabel['roll_number']) ?></strong>
                            <div><span>Type:</span> <strong>GARMENT SUPPLY</strong></div>
                            <?php if (!empty($qrLabel['hsn_code']) && $qrLabel['hsn_code'] !== '—'): ?>
                                <div><span>HSN:</span> <strong class="font-monospace"><?= e($qrLabel['hsn_code']) ?></strong></div>
                            <?php endif; ?>
                            <div><span>Price:</span> <strong>₹<?= number_format((float)($qrLabel['price'] ?? 0), 2) ?></strong></div>
                            <div><span>Code:</span> <strong class="font-monospace" style="font-size: 10.5px; font-weight: 800; color: #0f172a;"><?= e($qrLabel['code']) ?></strong></div>
                            <?php if (!empty($qrLabel['defect_reason'])): ?>
                                <div class="text-danger fw-semibold mt-0.5" style="font-size: 8.5px;"><span>Defect:</span> <?= e($qrLabel['defect_reason']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-inline-flex align-items-center gap-3 p-3 border rounded bg-white" id="printableDamageSticker">
                    <div id="damageRecordQr" class="p-1 border rounded bg-white"></div>
                    <div>
                        <div class="fw-bold fs-5 text-dark"><?= e($qrLabel['roll_number'] ?? '') ?></div>
                        <div class="font-monospace fw-semibold text-secondary"><?= e($qrLabel['code']) ?></div>
                        <?php if (!empty($qrLabel['color'])): ?>
                            <div class="small text-muted">Color: <?= e($qrLabel['color']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($qrLabel['design'])): ?>
                            <div class="small text-muted">Design: <?= e($qrLabel['design']) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted">Piece weight: <?= number_format((float)($qrLabel['weight'] ?? 0), 2) ?> kg</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        function renderDamageQr() {
            if (window.QRCode) {
                const qrEl = document.getElementById('damageRecordQr');
                if (!qrEl) return;
                qrEl.innerHTML = '';
                const qrSize = <?= $isSupplyLabel ? 72 : 112 ?>;
                new QRCode(qrEl, {
                    text: <?= json_encode((string)$qrLabel['code'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    width: qrSize,
                    height: qrSize,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
                setTimeout(function() {
                    qrEl.removeAttribute('title');
                    qrEl.querySelectorAll('img, canvas').forEach(function(el) { el.removeAttribute('title'); });
                }, 50);
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', renderDamageQr);
        } else {
            renderDamageQr();
        }

        function printDamageRecordQrLabel() {
            const card = document.getElementById('printableDamageSticker');
            if (!card) return;
            const printWin = window.open('', '_blank', 'width=420,height=340');
            if (!printWin) {
                alert('Please allow popups to print label.');
                return;
            }
            const clone = card.cloneNode(true);

            // If QR was rendered as a canvas, convert to img dataURL for printing
            const origCanvas = card.querySelector('#damageRecordQr canvas');
            const clonedQr = clone.querySelector('#damageRecordQr');
            if (origCanvas && clonedQr) {
                clonedQr.innerHTML = '';
                const img = document.createElement('img');
                img.src = origCanvas.toDataURL('image/png');
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'contain';
                clonedQr.appendChild(img);
            }

            const cardHtml = clone.outerHTML;
            printWin.document.open();
            printWin.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Damage QR Label Print</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { margin: 0; padding: 10px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #fff; }
                        #printableDamageSticker { width: 295px; border: 1.5px solid #dc3545 !important; padding: 8px 10px; border-radius: 6px; background-color: #fff8f8; }
                        .qr-box { width: 72px; height: 72px; }
                        .qr-box img, .qr-box canvas { width: 100% !important; height: 100% !important; display: block; }
                        @media print {
                            body { padding: 0; margin: 0; }
                            #printableDamageSticker { page-break-inside: avoid; }
                        }
                    </style>
                </head>
                <body onload="setTimeout(() => { window.print(); setTimeout(() => window.close(), 600); }, 250);">
                    ${cardHtml}
                </body>
                </html>
            `);
            printWin.document.close();
        }
    </script>
<?php endif; ?>

<div class="card border shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">Record Details</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($fields as $label => $value): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="text-muted small fw-semibold"><?= e($label) ?></div>
                    <div class="text-dark"><?= e((string)($value === '' || $value === null ? '—' : $value)) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($items)): ?>
    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fw-bold"><?= e($itemsTitle) ?></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($itemColumns as $label => $key): ?>
                            <th><?= e($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <?php foreach ($itemColumns as $key): ?>
                                <td><?= e((string)($item[$key] ?? '—')) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
