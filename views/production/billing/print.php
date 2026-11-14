<?php
$prodUnit = $prodUnit ?? (class_exists('ProductionUnit') ? ProductionUnit::getActiveFacility($bill['production_unit_id'] ?? null) : []);
$sellerCompany = $sellerCompany ?? [];
$seller = static fn(string $key): string => htmlspecialchars((string)($sellerCompany[$key] ?? ''), ENT_QUOTES, 'UTF-8');

// Staff name
$staffName = 'System Administrator';
if (!empty($bill['created_by'])) {
    $staffUser = Database::fetchOne("SELECT full_name, username FROM users WHERE id = ?", [(int)$bill['created_by']]);
    if ($staffUser) {
        $staffName = $staffUser['full_name'] ?: $staffUser['username'];
    }
} elseif (Session::user()) {
    $staffName = Session::user()['full_name'] ?: (Session::user()['username'] ?: 'System Administrator');
}

// Buyer details
$isCustomer = (($bill['buyer_type'] ?? 'COMPANY') === 'CUSTOMER');
$buyerName = $isCustomer ? ($bill['customer_name'] ?: 'Direct Customer') : ($bill['company_name'] ?: 'Warehouse');
$buyerPhone = $isCustomer ? ($bill['customer_phone'] ?: '—') : ($bill['company_phone'] ?: '—');
$buyerAddress = $isCustomer 
    ? trim(($bill['customer_address'] ?? '') . ' ' . ($bill['customer_city'] ?? '') . ' ' . ($bill['customer_state'] ?? '')) 
    : ($bill['company_address'] ?? '');
if (empty($buyerAddress)) {
    $buyerAddress = '—';
}
$buyerGstin = $isCustomer ? ($bill['customer_gstin'] ?? '') : ($bill['company_gstin'] ?? '');

// Date & payment
$purchasedAt = !empty($bill['created_at']) ? date('d-m-Y H:i:s', strtotime($bill['created_at'])) : date('d-m-Y H:i:s', strtotime($bill['bill_date']));
$payMode = strtoupper($bill['payment_method'] ?: 'CASH');

// Totals calculation
$totalQty = 0;
foreach ($billItems as $it) {
    $totalQty += (int)($it['quantity'] ?? 0);
}
$subtotal = (float)($bill['subtotal'] ?? 0);
$discount = (float)($bill['discount_amount'] ?? 0);
$taxAmount = (float)($bill['tax_amount'] ?? 0);
$cgst = (float)($bill['cgst_amount'] ?? 0);
$sgst = (float)($bill['sgst_amount'] ?? 0);
$igst = (float)($bill['igst_amount'] ?? 0);
$totalAmount = (float)($bill['total_amount'] ?? 0);

// GST Inclusive adjustment: ensure net amount equals gross minus discount, with tax included inside
$grossAmt = (float)($bill['gross_amount'] ?? 0);
$netAmount = max(0, $grossAmt - $discount);
if ($netAmount > 0 && abs($totalAmount - ($netAmount + $taxAmount)) < 1.0) {
    // If historical bill added tax exclusively on top, convert display to inclusive
    $totalAmount = $netAmount;
    $combinedGstRate = ($cgst > 0 || $sgst > 0 || $igst > 0)
        ? (float)rtrim($cgstRate, '%') + (float)rtrim($sgstRate, '%') + (float)rtrim($igstRate, '%')
        : 5.0;
    if ($combinedGstRate > 0) {
        $subtotal = round($netAmount / (1 + ($combinedGstRate / 100)), 2);
        $taxAmount = round($netAmount - $subtotal, 2);
        if ($igst > 0) {
            $igst = $taxAmount;
        } else {
            $cgst = round($taxAmount / 2, 2);
            $sgst = round($taxAmount - $cgst, 2);
        }
    }
}

$rawTotal = $subtotal + $taxAmount;
$roundOff = round($totalAmount - $rawTotal, 2);

// CGST / SGST rates
$cgstRate = '2.5%';
$sgstRate = '2.5%';
$igstRate = '5.0%';
if (!empty($billItems[0])) {
    if (!empty($billItems[0]['cgst_rate'])) $cgstRate = rtrim(rtrim(number_format((float)$billItems[0]['cgst_rate'], 2), '0'), '.') . '%';
    if (!empty($billItems[0]['sgst_rate'])) $sgstRate = rtrim(rtrim(number_format((float)$billItems[0]['sgst_rate'], 2), '0'), '.') . '%';
    if (!empty($billItems[0]['igst_rate'])) $igstRate = rtrim(rtrim(number_format((float)$billItems[0]['igst_rate'], 2), '0'), '.') . '%';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bill <?= htmlspecialchars($bill['bill_number']) ?> — POS Receipt</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background-color: #e9ecef;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.35;
            color: #000;
            padding: 20px 10px;
        }

        /* Top Action Bar (Buttons) */
        .no-print {
            max-width: 360px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .btn-print {
            flex: 1;
            background: #0d6efd;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-print:hover {
            background: #0b5ed7;
        }
        .btn-close-window {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-close-window:hover {
            background: #5c636a;
        }

        /* Thermal POS Receipt Container (80mm width standard) */
        .pos-receipt {
            max-width: 340px;
            width: 100%;
            margin: 0 auto;
            background: #ffffff;
            padding: 14px 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            border-radius: 4px;
        }

        /* Header Split: Left side Logo & Unit Name, Right side Unit Details */
        .header-split {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 6px;
        }
        .header-left {
            flex: 0 0 46%;
            max-width: 46%;
            text-align: left;
        }
        .header-left .logo-box {
            border: 1.5px solid #000;
            padding: 3px 5px;
            text-align: center;
            background: #fff;
            margin-bottom: 4px;
            display: inline-block;
            max-width: 100%;
        }
        .header-left .logo-box img {
            max-width: 110px;
            max-height: 48px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .header-left .logo-box .brand-text {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .header-left .store-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.25;
            word-break: break-word;
            margin-top: 2px;
        }

        .header-right {
            flex: 0 0 52%;
            max-width: 52%;
            text-align: right;
            font-size: 10.5px;
            line-height: 1.35;
        }
        .header-right .store-address {
            word-break: break-word;
            margin-bottom: 2px;
        }
        .header-right .store-contact {
            word-break: break-word;
            margin-bottom: 1px;
        }
        .header-right .store-gstin {
            font-weight: bold;
        }

        /* Dashed Separators */
        .dashed-line {
            border-top: 1px dashed #000;
            margin: 7px 0;
        }

        /* Metadata & Customer Sections */
        .info-grid {
            width: 100%;
            margin-bottom: 2px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 3px;
            font-size: 11.5px;
        }
        .info-label {
            font-weight: bold;
            white-space: nowrap;
            margin-right: 6px;
        }
        .info-value {
            text-align: right;
            word-break: break-word;
        }

        /* Table of Items */
        .pos-table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 2px 0;
            font-size: 11.5px;
        }
        .pos-table th {
            text-align: left;
            font-weight: bold;
            padding: 4px 2px;
            border-bottom: 1px dashed #000;
            font-size: 11px;
        }
        .pos-table td {
            padding: 3px 2px;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }

        .item-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11.5px;
        }
        .item-variant {
            font-size: 10.5px;
            color: #333;
            margin-top: 1px;
            margin-bottom: 2px;
        }
        .tag-single-label {
            font-family: inherit;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Totals / Summary */
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
            margin-bottom: 4px;
        }
        .summary-label {
            font-weight: normal;
        }
        .summary-val {
            font-weight: bold;
            text-align: right;
        }
        .discount-highlight {
            color: #d32f2f !important;
            font-weight: bold;
        }
        .net-amount-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: 14px;
            font-weight: bold;
            margin: 4px 0;
        }

        /* Footer */
        .terms-box {
            font-size: 10px;
            line-height: 1.3;
            margin: 8px 0;
        }
        .terms-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .thank-you {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-top: 10px;
            padding-top: 6px;
        }

        /* Thermal Print Media Queries */
        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .pos-receipt {
                max-width: 100% !important;
                width: 100% !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 4px !important;
                margin: 0 !important;
            }
            @page {
                size: auto;
                margin: 2mm 3mm;
            }
        }
    </style>
</head>
<body>

<!-- Print Controls (hidden on print) -->
<div class="no-print">
    <button type="button" class="btn-print" onclick="window.print()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
            <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
        </svg>
        Print Bill
    </button>
    <button type="button" class="btn-close-window" onclick="window.close()">Close</button>
</div>

<!-- Receipt Paper Body -->
<main class="pos-receipt">

    <!-- 1 & 2. Header: Logo & Production Unit Name on LEFT, Details on RIGHT -->
    <div class="header-split">
        <div class="header-left">
            <div class="logo-box">
                <?php if (!empty($prodUnit['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($prodUnit['logo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Logo">
                <?php elseif (!empty($sellerCompany['company_logo'])): ?>
                    <img src="<?= htmlspecialchars($sellerCompany['company_logo'], ENT_QUOTES, 'UTF-8') ?>" alt="Logo">
                <?php else: ?>
                    <div class="brand-text"><?= htmlspecialchars($prodUnit['name'] ?? ($seller('company_name') ?: 'GRM APPARELS')) ?></div>
                <?php endif; ?>
            </div>
            <div class="store-title"><?= htmlspecialchars($prodUnit['name'] ?? ($seller('company_name') ?: 'Production Unit')) ?></div>
        </div>
        <div class="header-right">
            <?php if (!empty($prodUnit['address'])): ?>
                <div class="store-address"><?= htmlspecialchars($prodUnit['address']) ?></div>
            <?php elseif ($seller('address')): ?>
                <div class="store-address"><?= $seller('address') ?></div>
            <?php endif; ?>

            <div class="store-contact">
                <?php if (!empty($prodUnit['phone'])): ?>
                    Phone: <?= htmlspecialchars($prodUnit['phone']) ?>
                <?php elseif ($seller('phone')): ?>
                    Phone: <?= $seller('phone') ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($prodUnit['code'])): ?>
                <div class="store-contact">Unit: <?= htmlspecialchars($prodUnit['code']) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($prodUnit['gst_number'])): ?>
                <div class="store-contact store-gstin">GSTIN: <?= htmlspecialchars($prodUnit['gst_number']) ?></div>
            <?php elseif ($seller('gstin')): ?>
                <div class="store-contact store-gstin">GSTIN: <?= $seller('gstin') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashed-line"></div>

    <!-- 3. Invoice Metadata -->
    <div class="info-grid">
        <div class="info-row">
            <span class="info-label">Bill No :</span>
            <span class="info-value font-monospace" style="font-weight: bold;"><?= htmlspecialchars($bill['bill_number']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">BID :</span>
            <span class="info-value"><?= (int)$bill['id'] ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Purchased At :</span>
            <span class="info-value"><?= htmlspecialchars($purchasedAt) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Staff :</span>
            <span class="info-value"><?= htmlspecialchars($staffName) ?></span>
        </div>
    </div>

    <div class="dashed-line"></div>

    <!-- 4. Customer / Buyer Details -->
    <div class="info-grid">
        <div class="info-row">
            <span class="info-label">To :</span>
            <span class="info-value" style="font-weight: bold;"><?= htmlspecialchars($buyerName) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Ph :</span>
            <span class="info-value"><?= htmlspecialchars($buyerPhone) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Add :</span>
            <span class="info-value"><?= htmlspecialchars($buyerAddress) ?></span>
        </div>
        <?php if (!empty($buyerGstin)): ?>
        <div class="info-row">
            <span class="info-label">GSTIN :</span>
            <span class="info-value"><?= htmlspecialchars($buyerGstin) ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="info-label">Pay Mode :</span>
            <span class="info-value" style="font-weight: bold;"><?= htmlspecialchars($payMode) ?></span>
        </div>
    </div>

    <div class="dashed-line"></div>

    <!-- 5. Items Table (Each piece QR tag rendered as its own single detail row) -->
    <table class="pos-table">
        <thead>
            <tr>
                <th style="width: 34%;">Item</th>
                <th style="width: 12%;">HSN</th>
                <th style="width: 8%; text-align: right;">Qty</th>
                <th style="width: 15%; text-align: right;">Price</th>
                <th style="width: 13%; text-align: right;">Disc</th>
                <th style="width: 18%; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($billItems as $item): ?>
                <?php
                    $iQty = max(1, (int)($item['quantity'] ?? 1));
                    $varText = trim(($item['sku'] ?? '') . ' ' . ($item['size_name'] ?? '') . ' ' . ($item['color_name'] ?? ''));
                    $tags = !empty($item['tags']) && is_array($item['tags']) ? array_values(array_filter(array_map('trim', $item['tags']))) : [];
                    $hsnCode = htmlspecialchars($item['hsn_code'] ?? '6109');
                    
                    // Single piece unit metrics (GST is INCLUSIVE in product amount)
                    $unitPrice = (float)($item['mrp'] ?? 0);
                    $unitDisc = round((float)($item['discount_amount'] ?? 0) / $iQty, 2);
                    $itemNetLine = max(0, ((float)($item['mrp'] ?? 0) * $iQty) - (float)($item['discount_amount'] ?? 0));
                    if ($itemNetLine <= 0 && !empty($item['unit_price'])) {
                        $itemNetLine = round((float)$item['unit_price'] * $iQty, 2);
                    }
                    $unitTotal = $iQty > 0 ? round($itemNetLine / $iQty, 2) : 0.0;
                    if ($unitPrice <= 0) {
                        $unitPrice = $unitTotal + $unitDisc;
                    }
                ?>

                <!-- Product Name Header Line -->
                <tr>
                    <td colspan="6" style="padding-top: 5px; padding-bottom: 2px;">
                        <div class="item-title"><?= htmlspecialchars($item['product_name']) ?></div>
                    </td>
                </tr>

                <?php if (!empty($tags)): ?>
                    <!-- Single detail row for each QR code tag -->
                    <?php foreach ($tags as $tg): ?>
                        <tr>
                            <td>
                                <span class="tag-single-label"><?= htmlspecialchars($tg) ?></span>
                            </td>
                            <td><?= $hsnCode ?></td>
                            <td class="text-right">1</td>
                            <td class="text-right"><?= number_format($unitPrice, 2) ?></td>
                            <td class="text-right <?= $unitDisc > 0 ? 'discount-highlight' : '' ?>"><?= number_format($unitDisc, 2) ?></td>
                            <td class="text-right" style="font-weight: bold;"><?= number_format($unitTotal, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Single detail row for non-tagged/bulk line -->
                    <tr>
                        <td><span class="tag-single-label">—</span></td>
                        <td><?= $hsnCode ?></td>
                        <td class="text-right"><?= (int)($item['quantity'] ?? 1) ?></td>
                        <td class="text-right"><?= number_format($unitPrice, 2) ?></td>
                        <td class="text-right <?= (float)($item['discount_amount'] ?? 0) > 0 ? 'discount-highlight' : '' ?>"><?= number_format((float)($item['discount_amount'] ?? 0), 2) ?></td>
                        <td class="text-right" style="font-weight: bold;"><?= number_format($itemNetLine, 2) ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="dashed-line"></div>

    <!-- 6. Summary & Totals -->
    <div class="info-grid">
        <div class="summary-row">
            <span class="summary-label">Total Qty :</span>
            <span class="summary-val"><?= $totalQty ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Taxable Base :</span>
            <span class="summary-val">₹ <?= number_format($subtotal, 2) ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Disc :</span>
            <span class="summary-val discount-highlight">-₹ <?= number_format($discount, 2) ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">GST Amount (Incl.) :</span>
            <span class="summary-val">₹ <?= number_format($taxAmount, 2) ?></span>
        </div>
        <?php if ($cgst > 0): ?>
        <div class="summary-row" style="padding-left: 10px; font-size: 11px;">
            <span class="summary-label">CGST (<?= $cgstRate ?>) :</span>
            <span class="summary-val">₹ <?= number_format($cgst, 2) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($sgst > 0): ?>
        <div class="summary-row" style="padding-left: 10px; font-size: 11px;">
            <span class="summary-label">SGST (<?= $sgstRate ?>) :</span>
            <span class="summary-val">₹ <?= number_format($sgst, 2) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($igst > 0): ?>
        <div class="summary-row" style="padding-left: 10px; font-size: 11px;">
            <span class="summary-label">IGST (<?= $igstRate ?>) :</span>
            <span class="summary-val">₹ <?= number_format($igst, 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="summary-row">
            <span class="summary-label">Round Off :</span>
            <span class="summary-val">₹ <?= number_format($roundOff, 2) ?></span>
        </div>
    </div>

    <div class="dashed-line"></div>

    <!-- 7. Net Amount -->
    <div class="net-amount-row">
        <span>NET AMOUNT :</span>
        <span style="font-size: 16px;">₹ <?= number_format($totalAmount, 2) ?></span>
    </div>

    <div class="dashed-line"></div>

    <!-- 8. Terms & Conditions -->
    <div class="terms-box">
        <div class="terms-title">Terms &amp; Conditions:</div>
        <div>1. Goods once sold cannot be taken back or exchanged.</div>
        <div>2. Claims subject to local jurisdiction only.</div>
        <?php if (!empty($bill['notes'])): ?>
            <div style="margin-top: 3px;"><strong>Note:</strong> <?= nl2br(htmlspecialchars($bill['notes'])) ?></div>
        <?php endif; ?>
    </div>

    <!-- 9. Thank You Footer -->
    <div class="thank-you">
        ***** Thank You !!! Visit Again *****
    </div>

</main>

<script>
window.addEventListener('DOMContentLoaded', () => {
    if (window.location.search.includes('autoprint=1')) {
        window.print();
    }
});
</script>
</body>
</html>
