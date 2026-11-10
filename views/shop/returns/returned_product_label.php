<?php
$product = $product ?? [];
$labelTitle = 'SHOP RETURNED PRODUCT';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($product['returned_tag'] ?? 'Returned Product') ?> · <?= e($labelTitle) ?></title>
    <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { background: #f1f5f9; color: #0f172a; }
        .return-label { width: 430px; min-height: 260px; margin: 8vh auto 20px; padding: 18px; border: 2px solid #166534; border-radius: 12px; background: #fff; }
        .qr-box { width: 112px; height: 112px; }
        .returned-stamp { border: 1px solid #d97706; color: #92400e; background: #fffbeb; }
        @media print {
            @page { size: 100mm 70mm; margin: 3mm; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .return-label { width: 94mm; min-height: 62mm; margin: 0; padding: 4mm; border: 1px solid #166534; border-radius: 2mm; box-shadow: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print d-flex justify-content-center gap-2 pt-4">
        <a href="<?= url('shop/returns/returned-products') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Returned Products</a>
        <button type="button" class="btn btn-success" id="printReturnedLabel"><i class="bi bi-printer me-1"></i><?= !empty($product['printed_at']) ? 'Print again' : 'Print Label' ?></button>
        <span class="badge <?= !empty($product['printed_at']) ? 'bg-success' : 'bg-secondary' ?> align-content-center" id="printedStatus">
            <?= !empty($product['printed_at']) ? 'Printed' : 'Not printed' ?>
        </span>
    </div>
    <main class="return-label shadow-sm">
        <header class="d-flex justify-content-between align-items-start border-bottom border-success pb-2 mb-3">
            <div>
                <div class="fw-bold text-success" style="letter-spacing:.7px;">GRM INVENTORY</div>
                <div class="small fw-bold"><?= e($labelTitle) ?></div>
            </div>
            <span class="badge returned-stamp">QUARANTINE</span>
        </header>
        <div class="d-flex gap-3 align-items-center">
            <div class="qr-box border rounded p-1 d-flex align-items-center justify-content-center" id="returnedQr"></div>
            <div class="flex-grow-1">
                <div class="fw-bold fs-5 font-monospace text-success text-break"><?= e($product['returned_tag']) ?></div>
                <div class="fw-semibold mt-1"><?= e($product['product_name']) ?></div>
                <div class="small text-muted">SKU: <span class="font-monospace"><?= e($product['sku']) ?></span></div>
                <div class="small text-muted"><?= e(($product['size_name'] ?? 'Free Size') . ' / ' . ($product['color_name'] ?? 'Standard')) ?></div>
                <div class="small mt-1">Condition: <strong><?= e($product['item_condition']) ?></strong></div>
            </div>
        </div>
        <footer class="d-flex justify-content-between border-top mt-3 pt-2 small text-muted">
            <span>Return: <?= e($product['return_number'] ?? '-') ?></span>
            <span>Original tag: <?= e($product['original_piece_tag'] ?: 'SKU-generated') ?></span>
        </footer>
    </main>
    <form id="printStatusForm" class="d-none">
        <?= CSRF::field() ?>
    </form>
    <script>
        new QRCode(document.getElementById('returnedQr'), {
            text: <?= json_encode((string)($product['returned_tag'] ?? '')) ?>,
            width: 100,
            height: 100,
            correctLevel: QRCode.CorrectLevel.M
        });

        let awaitingPrintResult = false;
        document.getElementById('printReturnedLabel').addEventListener('click', function() {
            awaitingPrintResult = true;
            window.print();
        });

        window.addEventListener('afterprint', async function() {
            if (!awaitingPrintResult) return;
            awaitingPrintResult = false;
            if (!window.confirm('Did the returned product label print successfully? Confirming adds this piece to Shop inventory.')) return;

            const formData = new FormData(document.getElementById('printStatusForm'));
            try {
                const response = await fetch(<?= json_encode(url('shop/returns/returned-products/printed/' . (int)$product['id'])) ?>, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Could not update print status.');
                const status = document.getElementById('printedStatus');
                status.textContent = 'Printed';
                status.className = 'badge bg-success align-content-center';
            } catch (error) {
                alert(error.message);
            }
        });
    </script>
</body>
</html>
