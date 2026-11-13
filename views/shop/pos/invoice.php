<?php
// views/shop/pos/invoice.php — Exact Thermal POS Receipt Layout matching GRM Shop sample bill
$totalGst = (float)$sale['cgst_amount'] + (float)$sale['sgst_amount'] + (float)$sale['igst_amount'];
$subtotal = (float)$sale['subtotal'];
$discountAmount = (float)($sale['discount_amount'] ?? 0);
$netAmount = (float)$sale['total_amount'];
$gstType = $sale['gst_type'] ?? 'CGST_SGST';
$unroundedNet = array_sum(array_column($items, 'total_amount'));
$roundOff = round($netAmount - $unroundedNet, 2);
$totalQuantity = $totalQty ?? array_sum(array_column($items, 'quantity'));

// Extract Bill No
$billNo = $sale['invoice_number'];
if (preg_match('/(\d+)$/', $sale['invoice_number'], $m)) {
    $billNo = $m[1];
}
$billId = $sale['id'] ?? '1026';
$purchasedAt = formatUtcDateTime($sale['created_at'] ?? null);
$cashTender = null;
if (($sale['payment_mode'] ?? '') === 'CASH'
    && preg_match('/\[Cash Tender: (.*?) \| Received ₹([\d,]+(?:\.\d{2})?) \| Payable ₹([\d,]+(?:\.\d{2})?) \| Change ₹([\d,]+(?:\.\d{2})?)(?: \| Change Breakdown: (.*?))?\]/u', (string)($sale['notes'] ?? ''), $cashTenderMatch)) {
    $cashTender = [
        'denominations' => $cashTenderMatch[1],
        'received' => (float)str_replace(',', '', $cashTenderMatch[2]),
        'payable' => (float)str_replace(',', '', $cashTenderMatch[3]),
        'change' => (float)str_replace(',', '', $cashTenderMatch[4]),
        'change_denominations' => $cashTenderMatch[5] ?? '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= e($sale['invoice_number']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Courier New', Courier, Consolas, 'Lucida Console', Monaco, monospace;
            color: #000;
            font-size: 11.5px;
            line-height: 1.35;
            font-weight: 600;
            padding: 20px 10px;
        }
        .action-bar {
            max-width: 320px;
            margin: 0 auto 12px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            font-size: 12px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-weight: 600;
            border-radius: 20px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #e2e8f0;
        }
        .btn-primary {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background: #0b5ed7;
        }

        /* Thermal Bill Receipt Card */
        .thermal-receipt {
            width: 320px;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 18px 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            color: #000;
        }

        /* Header Logo & Store */
        .store-header {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 6px;
        }
        .logo-container {
            flex: 0 0 74px;
            text-align: center;
        }
        .logo-box {
            display: inline-block;
            border: 1.5px solid #000;
            padding: 3px;
            background: #fff;
            position: relative;
            max-width: 100%;
        }
        .logo-text {
            font-family: 'Times New Roman', Times, Georgia, serif;
            font-size: 42px;
            font-weight: 900;
            letter-spacing: -2px;
            line-height: 1;
            color: #000;
            display: inline-block;
            text-transform: uppercase;
        }
        .store-details {
            flex: 1;
            min-width: 0;
        }
        .store-title {
            text-align: left;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        .store-address {
            text-align: left;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            line-height: 1.3;
            margin-top: 2px;
            overflow-wrap: anywhere;
        }
        .store-contact {
            text-align: left;
            font-size: 9px;
            font-weight: 600;
            margin-top: 2px;
            overflow-wrap: anywhere;
        }
        .store-gst {
            text-align: left;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.2px;
            margin-top: 2px;
            overflow-wrap: anywhere;
        }

        /* Dividers */
        .divider-dashed {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .divider-solid {
            border-top: 1px solid #000;
            margin: 5px 0;
        }
        .divider-double {
            border-top: 2px solid #000;
            margin: 6px 0;
        }

        /* Bill Meta Two-Column Grid */
        .meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.3;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            font-weight: 600;
        }
        .items-table th {
            font-weight: 800;
            padding: 3px 0;
            border-bottom: 1px dashed #000;
        }
        .items-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .items-table tfoot td {
            text-align: right;
            padding-top: 3px;
            border-top: 1px dashed #000;
        }
        .item-name-cell {
            font-weight: 700;
            word-break: break-word;
        }
        .item-tag-badge {
            font-size: 9.5px;
            font-weight: 600;
            display: block;
            color: #111;
        }

        /* Totals Grid */
        .totals-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 600;
            margin: 2px 0;
        }
        .totals-row-left {
            justify-content: flex-start;
            gap: 4px;
        }
        .net-amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13.5px;
            font-weight: 900;
            margin: 4px 0;
        }

        /* Terms & Footer */
        .terms-section {
            font-size: 9.5px;
            font-weight: 600;
            line-height: 1.35;
            margin-top: 4px;
        }
        .terms-title {
            font-weight: 800;
            margin-bottom: 2px;
        }
        .visit-again {
            text-align: center;
            font-size: 10.5px;
            font-weight: 800;
            margin-top: 8px;
            letter-spacing: 0.5px;
        }

        @media print {
            body {
                background: transparent !important;
                padding: 0 !important;
            }
            .action-bar {
                display: none !important;
            }
            .thermal-receipt {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 4px 6px !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Top Navigation & Print Controls (Hidden on Print) -->
<div class="action-bar no-print">
    <a href="<?= url('shop/pos') ?>" class="btn">
        <i class="bi bi-arrow-left" style="margin-right: 4px;"></i> Back to POS
    </a>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer-fill" style="margin-right: 4px;"></i> Print Bill
    </button>
</div>

<!-- Thermal Bill Sheet -->
<div class="thermal-receipt">
    <div class="store-header">
        <div class="logo-container">
            <div class="logo-box">
                <?php if (!empty($sale['shop_logo_url'])): ?>
                    <img src="<?= e($sale['shop_logo_url']) ?>" alt="<?= e($sale['shop_name'] ?? 'Shop') ?> logo" style="width:66px;height:42px;object-fit:contain;">
                <?php else: ?>
                    <svg viewBox="0 0 150 75" width="66" height="42" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="stipple" width="3" height="3" patternUnits="userSpaceOnUse">
                                <circle cx="1.5" cy="1.5" r="0.6" fill="#000" />
                            </pattern>
                        </defs>
                        <rect width="150" height="75" fill="url(#stipple)" opacity="0.35" />
                        <rect width="150" height="75" fill="none" stroke="#000" stroke-width="1.5" />
                        <text x="75" y="54" font-family="'Times New Roman', Georgia, serif" font-size="52" font-weight="900" text-anchor="middle" fill="#000" letter-spacing="-2">GRM</text>
                    </svg>
                <?php endif; ?>
            </div>
        </div>

        <div class="store-details">
            <div class="store-title"><?= e(!empty($sale['shop_name']) && $sale['shop_name'] !== 'GRM Retail Store' ? $sale['shop_name'] : 'GRM SHOP') ?></div>
            <?php if (!empty($sale['shop_address'])): ?>
                <div class="store-address"><?= nl2br(e($sale['shop_address'])) ?></div>
            <?php endif; ?>

            <div class="store-contact">
                <?php if (!empty($sale['shop_phone'])): ?>
                    Phone: <?= e($sale['shop_phone']) ?><br>
                <?php endif; ?>
                <?php if (!empty($sale['shop_email'])): ?>
                    Email: <?= e($sale['shop_email']) ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($sale['shop_gstin'])): ?>
                <div class="store-gst">GST NO: <?= e($sale['shop_gstin']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="divider-dashed"></div>

    <!-- 3. BILL METADATA -->
    <div class="meta-row">
        <span>Bill No: <?= e($billNo) ?></span>
        <span>Bill ID: <?= e($billId) ?></span>
    </div>
    <div class="meta-row">
        <span>Purchased At: <?= e($purchasedAt) ?></span>
    </div>
    <div class="meta-row">
        <?php if (!empty($sale['staff_name'])): ?>
            <span>Staff: <?= e($sale['staff_name']) ?></span>
        <?php endif; ?>
    </div>

    <div class="divider-dashed"></div>

    <!-- 4. CUSTOMER & PAYMENT MODE -->
    <div class="meta-row">
        <span>To: <?= e(!empty($sale['customer_name']) ? strtoupper($sale['customer_name']) : 'WALK-IN CUSTOMER') ?></span>
        <span>Pay Mode : <?= ($sale['payment_mode'] ?? '') === 'CASH_UPI' ? 'CASH + UPI' : e(strtoupper($sale['payment_mode'] ?? 'CASH')) ?></span>
    </div>
    <?php if ($cashTender): ?>
    <div class="meta-row" style="font-size: 9.5px;">
        <span>Cash Received: ₹<?= number_format($cashTender['received'], 2) ?></span>
    </div>
    <div class="meta-row" style="font-size: 9.5px;">
        <span>Amount Due: ₹<?= number_format($cashTender['payable'], 2) ?></span>
    </div>
    <div class="meta-row" style="font-size: 9.5px;">
        <span>Notes / Coins: <?= e($cashTender['denominations']) ?></span>
    </div>
    <div class="meta-row" style="font-size: 9.5px; font-weight: 800;">
        <span>Change Returned: ₹<?= number_format($cashTender['change'], 2) ?></span>
    </div>
    <?php if ($cashTender['change_denominations'] !== ''): ?>
    <div class="meta-row" style="font-size: 9.5px;">
        <span>Change Notes / Coins: <?= e($cashTender['change_denominations']) ?></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php if (($sale['payment_mode'] ?? '') === 'CASH_UPI' && preg_match('/\[Split Payment:\s*Cash\s*₹([\d\.]+)\s*\+\s*UPI\s*₹([\d\.]+)(?:\s*\(Ref:\s*([^)]+)\))?\]/i', $sale['notes'] ?? '', $spm)): ?>
    <div class="meta-row" style="font-size: 9.5px; font-weight: 700; color: #000;">
        <span>Split: Cash ₹<?= number_format((float)$spm[1], 2) ?> | UPI ₹<?= number_format((float)$spm[2], 2) ?><?= !empty($spm[3]) ? ' (' . e($spm[3]) . ')' : '' ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($sale['customer_phone']) || !empty($sale['customer_gst'])): ?>
    <div class="meta-row">
        <?php if (!empty($sale['customer_phone'])): ?>
            <span>Ph: <?= e($sale['customer_phone']) ?></span>
        <?php endif; ?>
        <?php if (!empty($sale['customer_gst'])): ?>
            <span>Cust GST: <?= e($sale['customer_gst']) ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($sale['customer_address'])): ?>
    <div class="meta-row" style="font-size: 10px; color: #111;">
        <span>Add: <?= e($sale['customer_address']) ?></span>
    </div>
    <?php endif; ?>

    <div class="divider-dashed"></div>

    <!-- 5. ITEM TABLE -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 30%;">Item</th>
                <th style="text-align: center; width: 14%;">HSN</th>
                <th style="text-align: center; width: 8%;">Qty</th>
                <th style="text-align: right; width: 17%;">Price</th>
                <th style="text-align: right; width: 14%;">Disc</th>
                <th style="text-align: right; width: 17%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): 
                $itemGstPct = (float)($item['gst_percentage'] ?? 5.0);
                $itemRate = (float)$item['unit_price'];
                $itemDisc = (float)($item['discount_amount'] ?? 0);
                $itemTotal = (float)$item['total_amount'];
                $itemQty = (int)$item['quantity'];
            ?>
                <tr>
                    <td class="item-name-cell">
                        <?= e($item['product_name']) ?><?= !empty($item['size_name']) ? ' - ' . e($item['size_name']) : '' ?><?= !empty($item['color_name']) ? ' (' . e($item['color_name']) . ')' : '' ?>
                        <?php if (!empty($item['scanned_qr_codes'])): ?>
                            <span class="item-tag-badge">Tag: <?= e($item['scanned_qr_codes']) ?></span>
                        <?php else: ?>
                            <span class="item-tag-badge">QR: <?= e($item['sku'] ?? 'QR-CODE') ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?= e($item['resolved_hsn_code'] ?? '') ?: '-' ?></td>
                    <td style="text-align: center;"><?= $itemQty ?></td>
                    <td style="text-align: right;"><?= number_format($itemRate, 2) ?></td>
                    <td style="text-align: right; color: <?= $itemDisc > 0 ? '#b91c1c' : '#000' ?>;"><?= $itemDisc > 0 ? '-' . number_format($itemDisc, 2) : '0.00' ?></td>
                    <td style="text-align: right; font-weight: 700;"><?= number_format($itemTotal, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Total Qty: <?= $totalQuantity ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="divider-dashed"></div>

    <!-- 6. SUMMARY TOTALS -->
    <div class="totals-row" style="color: #b91c1c;">
        <span>Total Discount:</span>
        <span><?= $discountAmount > 0 ? '-' : '' ?><?= number_format($discountAmount, 2) ?></span>
    </div>
    <div class="totals-row">
        <span>Taxable Base:</span>
        <span><?= number_format($subtotal, 2) ?></span>
    </div>
    <div class="totals-row">
        <span>GST Amount (Incl.):</span>
        <span><?= number_format($totalGst, 2) ?></span>
    </div>
    <?php if ($gstType === 'IGST'): ?>
    <div class="totals-row totals-row-left">
        <span>IGST:</span>
        <span><?= number_format((float)$sale['igst_amount'], 2) ?></span>
    </div>
    <?php elseif ($gstType === 'EXEMPT'): ?>
    <div class="totals-row">
        <span>GST Type:</span>
        <span>Exemption</span>
    </div>
    <?php else: ?>
    <div class="totals-row">
        <span>CGST: <?= number_format((float)$sale['cgst_amount'], 2) ?></span>
    </div>
    <div class="totals-row">
        <span>SGST: <?= number_format((float)$sale['sgst_amount'], 2) ?></span>
    </div>
    <?php endif; ?>
    <div class="totals-row">
        <span>Round Off:</span>
        <span><?= number_format($roundOff, 2) ?></span>
    </div>

    <div class="divider-double"></div>

    <!-- 7. NET AMOUNT -->
    <div class="net-amount-row">
        <span>Net Amount</span>
        <span><?= number_format($netAmount, 2) ?></span>
    </div>

    <div class="divider-dashed"></div>

    <!-- 8. TERMS AND CONDITIONS -->
    <div class="terms-section">
        <div class="terms-title">*Terms and Conditions</div>
        <div>1.Please check your bill before leaving the shop</div>
        <div>2.No bargain, fixed price only</div>
        <div>3.No cash back</div>
    </div>

    <!-- 9. FOOTER GREETING -->
    <div class="visit-again">
        ***** Thank You !!! Visit Again *****
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    // Auto-trigger print if opened in popup from POS checkout
    if (window.opener) {
        window.print();
    }
});
</script>

</body>
</html>
