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
        .return-label { box-sizing: border-box; width: 40mm; height: 22mm; margin: 20px auto; padding: 1mm; border: 1px solid #166534; background: #fff; overflow: hidden; }
        .label-heading { box-sizing: border-box; display: flex; flex: 0 0 2.5mm; justify-content: space-between; align-items: center; height: 2.5mm; padding-bottom: .4mm; border-bottom: .3mm solid #166534; font-size: 5.5pt; font-weight: 700; line-height: 1; }
        .returned-stamp { border: .2mm solid #d97706; color: #92400e; background: #fffbeb; padding: .3mm .7mm; font-size: 4.5pt; line-height: 1; }
        .label-content { box-sizing: border-box; display: flex; flex: 1; align-items: center; gap: 1mm; min-height: 17mm; padding: .5mm 0; }
        .qr-box { flex: 0 0 13mm; width: 13mm; height: 13mm; display: flex; align-items: center; justify-content: center; }
        .qr-box img, .qr-box canvas { display: block; width: 13mm !important; height: 13mm !important; }
        .label-info { min-width: 0; flex: 1; overflow: hidden; font-size: 5pt; line-height: 1.1; }
        .returned-tag { color: #166534; font-size: 7pt; font-weight: 800; line-height: 1.1; overflow-wrap: anywhere; }
        .product-name { max-height: 2.2em; margin-top: .3mm; overflow: hidden; font-size: 5.5pt; font-weight: 700; line-height: 1.1; }
        .product-meta { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; color: #475569; }
        @media print {
            @page { size: 40mm 22mm; margin: 0; }
            html, body { width: 40mm !important; height: 22mm !important; margin: 0 !important; padding: 0 !important; background: #fff !important; overflow: hidden !important; }
            body > *:not(.return-label):not(#printStatusForm):not(script) { display: none !important; }
            .no-print, #printStatusForm { display: none !important; }
            .return-label { width: 40mm; height: 22mm; margin: 0; padding: 1mm; border: 0; border-radius: 0; box-shadow: none !important; page-break-inside: avoid; break-inside: avoid; }
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
        <header class="label-heading">
            <span>GRM · SHOP RETURN</span>
            <span class="returned-stamp">RETURNED</span>
        </header>
        <div class="label-content">
            <div class="qr-box" id="returnedQr"></div>
            <div class="label-info">
                <div class="returned-tag font-monospace"><?= e($product['returned_tag']) ?></div>
                <div class="product-name"><?= e($product['product_name']) ?></div>
                <div class="product-meta"><?= e(($product['size_name'] ?? 'Free Size') . ' / ' . ($product['color_name'] ?? 'Standard')) ?> · <?= e($product['item_condition']) ?></div>
            </div>
        </div>
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
