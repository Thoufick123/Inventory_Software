<div class="page-header mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><i class="bi bi-receipt me-2 text-primary"></i><?= e($purchase['purchase_number']) ?></h2>
    </div>
    <?php
    $hasRolls = false;
    $hasSupplies = false;
    foreach ($items as $it) {
        if (($it['item_type'] ?? '') === 'FABRIC_ROLL') $hasRolls = true;
        if (($it['item_type'] ?? '') === 'GENERAL_SUPPLY') $hasSupplies = true;
    }
    ?>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('production/purchases') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if ($hasRolls): ?>
            <a href="<?= url('production/purchases/roll-qr/' . $purchase['id']) ?>" class="btn btn-outline-dark" target="_blank" rel="noopener" title="Print Roll QR Labels">
                <i class="bi bi-qr-code text-warning me-1"></i> Roll QR Labels
            </a>
        <?php endif; ?>
        <?php if ($hasSupplies): ?>
            <a href="<?= url('production/purchases/supply-qr/' . $purchase['id']) ?>" class="btn btn-success" target="_blank" rel="noopener" title="Print Other Garment Supplies QR Labels">
                <i class="bi bi-tags-fill me-1"></i> Supply QR Labels
            </a>
        <?php endif; ?>
        <a href="<?= url('production/purchases/print/' . $purchase['id']) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener" title="Print PO Invoice">
            <i class="bi bi-printer me-1"></i> Print Invoice
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Purchase Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6"><small class="text-muted d-block">Purchase Number</small><strong><?= e($purchase['purchase_number']) ?></strong></div>
                    <div class="col-sm-6"><small class="text-muted d-block">Purchase Date</small><strong><?= e(formatDate($purchase['purchase_date'])) ?></strong></div>
                    <div class="col-sm-6"><small class="text-muted d-block">Invoice / Bill Number</small><strong><?= e($purchase['invoice_number'] ?: '—') ?></strong></div>
                    <div class="col-sm-6"><small class="text-muted d-block">Notes</small><strong><?= e($purchase['notes'] ?: '—') ?></strong></div>
                    <?php if (!empty($purchase['document_url'])): ?><div class="col-12"><a href="<?= uploadUrl($purchase['document_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-text me-1"></i>View Invoice Attachment</a></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Supplier Details</div>
            <div class="card-body">
                <strong><?= e($purchase['seller_company'] ?: $purchase['seller_name']) ?></strong>
                <?php if (!empty($purchase['seller_company'])): ?><div><?= e($purchase['seller_name']) ?></div><?php endif; ?>
                <div class="mt-2"><?= e($purchase['seller_phone'] ?: '—') ?><?= !empty($purchase['seller_email']) ? ' · ' . e($purchase['seller_email']) : '' ?></div>
                <?php if (!empty($purchase['seller_gst_number'])): ?><div>GSTIN: <?= e($purchase['seller_gst_number']) ?></div><?php endif; ?>
                <?php if (!empty($purchase['seller_address'])): ?><div class="text-muted mt-2"><?= e($purchase['seller_address']) ?></div><?php endif; ?>
                <?php if (!empty($purchase['seller_city']) || !empty($purchase['seller_state'])): ?><div class="text-muted"><?= e(trim(($purchase['seller_city'] ?? '') . ', ' . ($purchase['seller_state'] ?? ''), ', ')) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Purchased Items</h5>
            <span class="badge bg-secondary-subtle text-secondary border" id="itemCountBadge"><?= count($items) ?> Items</span>
        </div>
        <div class="d-flex align-items-center gap-2" style="max-width: 320px; width: 100%;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="purchasedItemsSearch" class="form-control border-start-0" placeholder="Search item, roll, HSN, specs..." autocomplete="off">
                <button type="button" class="btn btn-outline-secondary border-start-0 d-none" id="clearItemSearch" title="Clear search"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="purchasedItemsTable">
            <thead class="table-light"><tr><th>#</th><th>Item</th><th>HSN</th><th>Quantity</th><th class="text-end">Unit Price</th><th class="text-end">Subtotal</th><th>GST</th><th class="text-end">Tax</th><th class="text-end">Total</th><th class="text-center">Label</th></tr></thead>
            <tbody id="purchasedItemsBody">
            <?php
                $subtotal = 0.0;
                $taxTotal = 0.0;
                foreach ($items as $index => $item):
                    $base = (float)$item['unit_price'] * ($item['item_type'] === 'GENERAL_SUPPLY' ? (float)$item['quantity'] : 1);
                    $tax = (float)($item['sgst_amount'] ?? 0) + (float)($item['cgst_amount'] ?? 0) + (float)($item['igst_amount'] ?? 0);
                    $subtotal += $base;
                    $taxTotal += $tax;
                    $taxType = $item['gst_type'] ?? 'LEGACY';
                    if ($taxType === 'BOTH') $taxType = 'SGST + CGST';
                    if ($taxType === 'LEGACY') $taxType = $tax > 0 ? 'Legacy SGST + CGST' : 'Exempted';
                    $searchData = strtolower(implode(' ', [
                        $item['item_name'] ?? '',
                        $item['item_type'] ?? '',
                        $item['roll_number'] ?? '',
                        $item['color'] ?? '',
                        $item['design_name'] ?? '',
                        $item['start_code'] ?? '',
                        $item['end_code'] ?? '',
                        $item['hsn_code'] ?? '',
                        $item['unit'] ?? '',
                        $item['weight_kg'] ?? '',
                        $item['unit_price'] ?? '',
                        $item['total_price'] ?? '',
                        $taxType
                    ]));
            ?>
                <tr class="item-row" data-search="<?= htmlspecialchars($searchData, ENT_QUOTES) ?>">
                    <td><?= $index + 1 ?></td>
                    <td>
                        <span class="badge <?= $item['item_type'] === 'FABRIC_ROLL' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' ?>"><?= $item['item_type'] === 'FABRIC_ROLL' ? 'Fabric Roll' : 'Supply' ?></span>
                        <strong class="d-block"><?= e($item['item_name']) ?></strong>
                        <?php if ($item['item_type'] === 'FABRIC_ROLL'): ?>
                            <small class="text-muted"><?= e($item['roll_number'] ?: '—') ?> · <?= e($item['color'] ?: '—') ?> · <?= e($item['design_name'] ?: '—') ?></small>
                            <?php if (!empty($item['start_code'])): ?><small class="d-block font-monospace"><?= e($item['start_code']) ?><?= $item['end_code'] !== $item['start_code'] ? '–' . e($item['end_code']) : '' ?></small><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark border font-monospace"><?= e($item['hsn_code'] ?: '—') ?></span></td>
                    <td><?= number_format((float)$item['quantity'], 3) ?> <?= e($item['unit']) ?><?= !empty($item['weight_kg']) ? '<br><small class="text-muted">' . number_format((float)$item['weight_kg'], 2) . ' kg</small>' : '' ?></td>
                    <td class="text-end">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td class="text-end">₹<?= number_format($base, 2) ?></td>
                    <td><?= e($taxType) ?></td>
                    <td class="text-end">₹<?= number_format($tax, 2) ?></td>
                    <td class="text-end fw-bold">₹<?= number_format((float)$item['total_price'], 2) ?></td>
                    <td class="text-center">
                        <?php if ($item['item_type'] === 'FABRIC_ROLL' && !empty($item['seller_roll_id'])): ?>
                            <a class="btn btn-xs btn-outline-dark fw-semibold" href="<?= url('production/sellers/roll-labels/' . $item['seller_roll_id']) ?>" target="_blank" rel="noopener" title="Thermal Print Roll Label">
                                <i class="bi bi-qr-code text-primary me-1"></i> Label
                            </a>
                        <?php elseif ($item['item_type'] === 'GENERAL_SUPPLY'): ?>
                            <a class="btn btn-xs btn-outline-success fw-semibold" href="<?= url('production/purchases/supply-qr/' . $purchase['id'] . '?item_id=' . $item['id']) ?>" target="_blank" rel="noopener" title="Thermal Print Supply Label">
                                <i class="bi bi-tags-fill me-1"></i> Label
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
                <tr id="noItemsMatchRow" class="d-none">
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="bi bi-search me-1"></i> No matching purchased items found.
                    </td>
                </tr>
            </tbody>
            <tfoot class="table-light">
                <tr><th colspan="5" class="text-end">Subtotal</th><th class="text-end">₹<?= number_format($subtotal, 2) ?></th><th colspan="3"></th><th></th></tr>
                <tr><th colspan="8" class="text-end">Taxes</th><th class="text-end">₹<?= number_format($taxTotal, 2) ?></th><th></th></tr>
                <tr><th colspan="8" class="text-end">Overall Total</th><th class="text-end text-success">₹<?= number_format((float)$purchase['total_amount'], 2) ?></th><th></th></tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('purchasedItemsSearch');
    const clearBtn = document.getElementById('clearItemSearch');
    const rows = document.querySelectorAll('#purchasedItemsBody .item-row');
    const noMatchRow = document.getElementById('noItemsMatchRow');
    const countBadge = document.getElementById('itemCountBadge');
    const totalCount = rows.length;

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            if (clearBtn) {
                clearBtn.classList.toggle('d-none', query.length === 0);
            }

            let visibleCount = 0;
            rows.forEach(function (row) {
                const searchData = (row.getAttribute('data-search') || '') + ' ' + (row.textContent || '').toLowerCase();
                const matches = !query || searchData.includes(query);
                row.classList.toggle('d-none', !matches);
                if (matches) visibleCount++;
            });

            if (noMatchRow) {
                noMatchRow.classList.toggle('d-none', visibleCount > 0);
            }

            if (countBadge) {
                countBadge.textContent = query ? (visibleCount + ' of ' + totalCount + ' Items') : (totalCount + ' Items');
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }
    }
});
</script>
