<?php
$companySettings = is_array($companySettings ?? null) ? $companySettings : [];
$storeName = trim((string)($po['warehouse_name'] ?? ''));
if ($storeName === '') {
    $storeName = trim((string)($companySettings['company_name'] ?? ''));
}
$storeAddress = trim((string)($po['warehouse_address'] ?? ''));
if ($storeAddress === '') {
    $storeAddress = trim((string)($companySettings['company_address'] ?? ''));
}
$storePhone = trim((string)($po['warehouse_phone'] ?? ''));
if ($storePhone === '') {
    $storePhone = trim((string)($companySettings['company_phone'] ?? ''));
}
$storeGst = trim((string)($po['warehouse_gst_number'] ?? ''));
if ($storeGst === '') {
    $storeGst = trim((string)($companySettings['company_gstin'] ?? ''));
}
$storeEmail = trim((string)($companySettings['company_email'] ?? ''));
$warehouseLogo = trim((string)($po['warehouse_logo_url'] ?? ''));
$billNumber = (string)($po['po_number'] ?? $po['id'] ?? '');
$customerName = trim((string)($po['display_customer_name'] ?? ''));
if ($customerName === '') {
    $customerName = 'Walk-in Customer';
}
$customerGst = trim((string)($po['customer_gst'] ?? ''));
$paymentMethod = trim((string)($po['payment_method'] ?? 'CASH'));
$paymentStatus = trim((string)($po['payment_status'] ?? ''));
$paymentNotes = (string)($po['notes'] ?? '');
$cashAmount = null;
$upiAmount = null;
$upiReference = '';
if (preg_match('/\[Split Payment: Cash ₹([\d,.]+) \+ UPI ₹([\d,.]+)(?: \(Ref: ([^)]+)\))?\]/u', $paymentNotes, $splitMatch)) {
    $cashAmount = (float)str_replace(',', '', $splitMatch[1]);
    $upiAmount = (float)str_replace(',', '', $splitMatch[2]);
    $upiReference = trim((string)($splitMatch[3] ?? ''));
    $paymentMethod = 'CASH + UPI';
}

$totalQuantity = 0;
$totalGst = (float)($po['cgst_amount'] ?? 0) + (float)($po['sgst_amount'] ?? 0) + (float)($po['igst_amount'] ?? 0);
$taxableAmount = (float)($po['subtotal'] ?? 0);
$netAmount = (float)($po['total_amount'] ?? 0);
$discountAmount = (float)($po['discount_amount'] ?? 0);
$roundOff = round($netAmount - ($taxableAmount + $totalGst), 2);
$gstType = (float)($po['igst_amount'] ?? 0) > 0
    ? 'IGST'
    : ($totalGst > 0 ? 'CGST + SGST' : 'EXEMPT');
foreach ($items as $item) {
    $totalQuantity += (int)($item['quantity'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Bill #<?= e($billNumber) ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f1f3f5;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }

        .receipt-actions {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 16px auto;
        }

        .receipt-actions a,
        .receipt-actions button {
            border: 0;
            border-radius: 4px;
            padding: 9px 14px;
            color: #fff;
            background: #343a40;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }

        .receipt-actions button {
            background: #0d6efd;
        }

        .receipt {
            width: 80mm;
            max-width: calc(100vw - 16px);
            margin: 0 auto 20px;
            padding: 5mm 4mm;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .12);
        }

        .store-header,
        .customer-header {
            text-align: center;
        }

        .store-brand {
            display: flex;
            align-items: center;
            gap: 3mm;
            text-align: left;
        }

        .store-logo {
            display: block;
            flex: 0 0 22mm;
            width: 22mm;
            max-width: 22mm;
            max-height: 20mm;
            margin: 0;
            object-fit: contain;
        }

        .store-brand-info {
            flex: 1;
            min-width: 0;
            text-align: right;
        }

        .store-name {
            margin: 0 0 3px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }

        .store-details {
            margin: 2px 0;
            overflow-wrap: anywhere;
        }

        .receipt-title {
            margin: 7px 0 5px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .rule {
            margin: 6px 0;
            border: 0;
            border-top: 1px dashed #222;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 2px 0;
        }

        .meta-row strong {
            font-weight: 700;
        }

        .customer-name {
            margin: 3px 0;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            padding: 4px 1px;
            border-bottom: 1px dotted #777;
            text-align: right;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        th {
            border-top: 1px dashed #222;
            border-bottom: 1px dashed #222;
            font-size: 9px;
            font-weight: 800;
        }

        th:first-child,
        td:first-child {
            text-align: left;
        }

        .item-name {
            font-weight: 700;
        }

        .item-tag {
            margin-top: 2px;
            font-size: 9px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .totals {
            margin-top: 5px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 2px 0;
        }

        .total-row.discount {
            color: #b42318;
        }

        .total-row.grand-total {
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed #222;
            font-size: 14px;
            font-weight: 800;
        }

        .payment-details,
        .terms {
            margin: 5px 0 0;
        }

        .terms p {
            margin: 2px 0;
        }

        .thank-you {
            margin-top: 8px;
            text-align: center;
            font-weight: 800;
        }

        @media print {
            body {
                background: #fff;
                font-size: 10px;
            }

            .receipt-actions {
                display: none !important;
            }

            .receipt {
                width: auto;
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-actions">
        <a href="<?= e(url('warehouse/purchases')) ?>">Back to Purchases</a>
        <a href="<?= e(url('warehouse/purchases/receive/' . ($po['id'] ?? 0))) ?>?document=invoice" target="_blank" rel="noopener">Office Invoice</a>
        <button type="button" onclick="window.print()">Print Bill</button>
    </div>

    <main class="receipt">
        <header class="store-header">
            <div class="store-brand">
            <?php if ($warehouseLogo !== ''): ?>
                <img class="store-logo" src="<?= e($warehouseLogo) ?>" alt="<?= e($storeName) ?>">
            <?php endif; ?>
                <div class="store-brand-info">
                    <h1 class="store-name"><?= e($storeName !== '' ? $storeName : 'Store') ?></h1>
            <?php if ($storeAddress !== ''): ?>
                    <div class="store-details"><?= nl2br(e($storeAddress)) ?></div>
            <?php endif; ?>
            <?php if ($storePhone !== ''): ?>
                    <div class="store-details">Ph: <?= e($storePhone) ?></div>
            <?php endif; ?>
            <?php if ($storeGst !== ''): ?>
                    <div class="store-details">GST No: <?= e($storeGst) ?></div>
            <?php endif; ?>
            <?php if ($storeEmail !== ''): ?>
                    <div class="store-details">Email: <?= e($storeEmail) ?></div>
            <?php endif; ?>
                </div>
            </div>
            <div class="receipt-title">CUSTOMER BILL</div>
        </header>

        <hr class="rule">
        <div class="meta-row">
            <span>Bill No: <strong><?= e($billNumber) ?></strong></span>
            <span>BID: <strong><?= e((string)($po['id'] ?? '')) ?></strong></span>
        </div>
        <div class="meta-row">
            <span>Date: <?= e(formatUtcDateTime($po['created_at'] ?? null)) ?></span>
            <span>Staff: <?= e((string)($po['created_by_name'] ?? '')) ?></span>
        </div>

        <hr class="rule">
        <section class="customer-header">
            <div class="customer-name"><?= e($customerName) ?></div>
            <?php if ($customerGst !== ''): ?>
                <div>GST No: <?= e($customerGst) ?></div>
            <?php endif; ?>
        </section>

        <hr class="rule">
        <table aria-label="Purchased items">
            <colgroup>
                <col style="width: 30%">
                <col style="width: 14%">
                <col style="width: 8%">
                <col style="width: 16%">
                <col style="width: 14%">
                <col style="width: 18%">
            </colgroup>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>HSN</th>
                    <th>Qty</th>
                    <th>Rate</th>
                    <th>Disc</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $quantity = (int)($item['quantity'] ?? 0);
                    $rate = (float)($item['mrp'] ?? 0);
                    if ($rate <= 0) {
                        $rate = (float)($item['unit_price'] ?? 0);
                    }
                    $lineAmount = (float)($item['total_amount'] ?? ($rate * $quantity));
                    $lineDiscount = max(0, round(($rate * $quantity) - $lineAmount, 2));
                    $itemName = trim((string)($item['product_name'] ?? 'Item'));
                    $variant = trim(implode(' / ', array_filter([
                        trim((string)($item['size_name'] ?? '')),
                        trim((string)($item['color_name'] ?? '')),
                    ])));
                    $tagCodes = trim((string)($item['scanned_qr_codes'] ?? $item['batch_lot_no'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <div class="item-name"><?= e($itemName) ?><?= $variant !== '' ? ' - ' . e($variant) : '' ?></div>
                            <?php if ($tagCodes !== ''): ?>
                                <div class="item-tag">Tag: <?= e($tagCodes) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string)($item['resolved_hsn_code'] ?? $item['hsn_code'] ?? '-')) ?></td>
                        <td><?= e((string)$quantity) ?></td>
                        <td><?= e(number_format($rate, 2)) ?></td>
                        <td><?= e(number_format($lineDiscount, 2)) ?></td>
                        <td><?= e(number_format($lineAmount, 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <section class="totals">
            <div class="total-row">
                <span>Items / Total Qty</span>
                <strong><?= e((string)count($items)) ?> / <?= e((string)$totalQuantity) ?></strong>
            </div>
            <div class="total-row">
                <span>Taxable Amount</span>
                <span><?= e(number_format($taxableAmount, 2)) ?></span>
            </div>
            <?php if ($discountAmount > 0): ?>
                <div class="total-row discount">
                    <span>Total Discount</span>
                    <span>-<?= e(number_format($discountAmount, 2)) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((float)($po['cgst_amount'] ?? 0) > 0): ?>
                <div class="total-row">
                    <span>CGST</span>
                    <span><?= e(number_format((float)$po['cgst_amount'], 2)) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((float)($po['sgst_amount'] ?? 0) > 0): ?>
                <div class="total-row">
                    <span>SGST</span>
                    <span><?= e(number_format((float)$po['sgst_amount'], 2)) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((float)($po['igst_amount'] ?? 0) > 0): ?>
                <div class="total-row">
                    <span>IGST</span>
                    <span><?= e(number_format((float)$po['igst_amount'], 2)) ?></span>
                </div>
            <?php endif; ?>
            <div class="total-row">
                <span>GST Type</span>
                <span><?= e($gstType) ?></span>
            </div>
            <?php if ($roundOff != 0.0): ?>
                <div class="total-row">
                    <span>Round Off</span>
                    <span><?= e(number_format($roundOff, 2)) ?></span>
                </div>
            <?php endif; ?>
            <div class="total-row grand-total">
                <span>NET AMOUNT</span>
                <span>₹ <?= e(number_format($netAmount, 2)) ?></span>
            </div>
        </section>

        <hr class="rule">
        <section class="payment-details">
            <div class="meta-row">
                <span>Pay Mode: <strong><?= e($paymentMethod) ?></strong></span>
                <?php if ($paymentStatus !== ''): ?>
                    <span><?= e($paymentStatus) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($cashAmount !== null && $upiAmount !== null): ?>
                <div class="meta-row">
                    <span>Cash: ₹ <?= e(number_format($cashAmount, 2)) ?></span>
                    <span>UPI: ₹ <?= e(number_format($upiAmount, 2)) ?></span>
                </div>
                <?php if ($upiReference !== ''): ?>
                    <div>UPI Ref: <?= e($upiReference) ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <hr class="rule">
        <section class="terms">
            <p>Please check the items and bill before leaving.</p>
            <p>No bargain; fixed price only.</p>
            <p>No cash back.</p>
            <p>Returns &amp; exchanges available.</p>
        </section>
        <div class="thank-you">Thank you. Visit again!</div>
    </main>
</body>
</html>
