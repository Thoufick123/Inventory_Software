<div class="page-header mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><i class="bi bi-bag-check me-2 text-primary"></i>Production Purchases</h2>
    </div>
    <?php if (hasPermission('sellers', 'create')): ?>
        <a href="<?= url('production/purchases/create') ?>" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Record Purchase
        </a>
    <?php endif; ?>
</div>

<?php
$totalRollCount = count($fabricRollInventory);
$totalInwardQty = (int)array_sum(array_map(fn($r) => max(1, (int)($r['quantity'] ?? 1)), $fabricRollInventory));
?>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-semibold">Purchase Records</div>
                <div class="fs-3 fw-bold text-dark"><?= number_format(count($purchases)) ?></div>
                <div class="small text-muted">Inward purchase orders</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-semibold">Purchase Value</div>
                <div class="fs-3 fw-bold text-success">₹<?= number_format((float)$totalPurchaseValue, 2) ?></div>
                <div class="small text-muted">Total material spend</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-semibold">Inward Fabric Rolls</div>
                <div class="fs-3 fw-bold text-primary"><?= number_format($totalRollCount) ?></div>
                <div class="small text-muted"><?= number_format($totalInwardQty) ?> pieces / total qty</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-semibold">General Supplies</div>
                <div class="fs-3 fw-bold text-secondary"><?= number_format(count($supplyStock)) ?></div>
                <div class="small text-muted">Non-fabric supply items</div>
            </div>
        </div>
    </div>
</div>

<!-- Top Two Navigation Tabs: History & Details -->
<ul class="nav nav-tabs nav-fill bg-white rounded-top border-bottom-0 shadow-sm px-2 pt-2 mb-0" id="purchasesTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold py-3 text-start text-sm-center" id="tab-history-link" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab" aria-controls="tab-history" aria-selected="true">
            <i class="bi bi-clock-history me-2 text-primary"></i>Purchase History
            <span class="badge bg-primary-subtle text-primary border ms-2"><?= count($purchases) ?> Records</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold py-3 text-start text-sm-center" id="tab-details-link" data-bs-toggle="tab" data-bs-target="#tab-details" type="button" role="tab" aria-controls="tab-details" aria-selected="false">
            <i class="bi bi-layers-half me-2 text-success"></i>Roll &amp; Inward Details
            <span class="badge bg-success-subtle text-success border ms-2"><?= $totalRollCount ?> Rolls (<?= $totalInwardQty ?> Qty)</span>
        </button>
    </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content bg-white shadow-sm border border-top-0 rounded-bottom mb-4 p-3" id="purchasesTabsContent">

    <!-- 1. TAB: PURCHASE HISTORY (Clean List Style) -->
    <div class="tab-pane fade show active" id="tab-history" role="tabpanel" aria-labelledby="tab-history-link">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="bi bi-receipt me-1 text-primary"></i> Purchase Orders &amp; Supplier Invoices
            </h6>
            <div class="small text-muted">
                Showing all recorded procurement orders in compact list format
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th style="width: 45px;" class="text-center">#</th>
                        <th>Purchase / PO #</th>
                        <th>Date</th>
                        <th>Supplier / Mill</th>
                        <th>Inward Items &amp; Rolls</th>
                        <th>Invoice #</th>
                        <th class="text-end">Total Amount (₹)</th>
                        <th class="text-center" style="width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($purchases)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No purchases recorded yet.</td></tr>
                <?php else: foreach ($purchases as $idx => $purchase): 
                    $rollItems = array_filter($purchase['items'] ?? [], fn($it) => ($it['item_type'] ?? '') === 'FABRIC_ROLL');
                    $supplyItems = array_filter($purchase['items'] ?? [], fn($it) => ($it['item_type'] ?? '') === 'GENERAL_SUPPLY');
                    $rollItemCount = count($rollItems);
                    $rollQtySum = (int)array_sum(array_map(fn($it) => max(1, (int)($it['quantity'] ?? 1)), $rollItems));
                    $supplyCount = count($supplyItems);
                    $totalWeight = array_sum(array_column($rollItems, 'weight_kg'));
                ?>
                    <tr>
                        <td class="text-center text-muted small fw-semibold"><?= $idx + 1 ?></td>
                        <td>
                            <strong class="font-monospace text-primary"><?= e($purchase['purchase_number']) ?></strong>
                        </td>
                        <td class="text-nowrap small text-dark">
                            <i class="bi bi-calendar3 me-1 text-muted"></i><?= e(formatDate($purchase['purchase_date'])) ?>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($purchase['company'] ?: $purchase['seller_name']) ?></div>
                            <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($purchase['seller_name']) ?></small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1 flex-wrap mb-1">
                                <?php if ($rollItemCount > 0): ?>
                                    <span class="badge bg-primary-subtle text-primary border fw-semibold">
                                        <i class="bi bi-layers me-1"></i><?= $rollItemCount ?> <?= $rollItemCount === 1 ? 'Roll' : 'Rolls' ?><?php if ($rollQtySum > $rollItemCount): ?> (<?= $rollQtySum ?> Qty)<?php endif; ?>
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        <?= number_format($totalWeight, 2) ?> kg
                                    </span>
                                <?php endif; ?>
                                <?php if ($supplyCount > 0): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-box me-1"></i><?= $supplyCount ?> <?= $supplyCount === 1 ? 'Supply' : 'Supplies' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($rollItemCount > 0): 
                                $rollSummaryLabels = array_map(function($r) {
                                    $name = $r['roll_number'] ?: ('Roll #' . $r['id']);
                                    $qty = (int)($r['quantity'] ?? 1);
                                    return $qty > 1 ? "{$name} ({$qty})" : $name;
                                }, $rollItems);
                            ?>
                                <div class="small text-muted font-monospace text-truncate" style="max-width: 320px;" title="<?= e(implode(', ', $rollSummaryLabels)) ?>">
                                    <?= e(implode(', ', array_slice($rollSummaryLabels, 0, 3))) ?>
                                    <?php if (count($rollItems) > 3): ?>
                                        <span class="text-secondary fw-normal font-sans">+<?= count($rollItems) - 3 ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($purchase['invoice_number'])): ?>
                                <span class="badge bg-light text-dark border font-monospace"><?= e($purchase['invoice_number']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                            <?php if (!empty($purchase['document_url'])): ?>
                                <a class="btn btn-xs btn-link p-0 text-decoration-none d-block small mt-1" href="<?= uploadUrl($purchase['document_url']) ?>" target="_blank" rel="noopener">
                                    <i class="bi bi-file-earmark-pdf me-1 text-danger"></i>View Bill
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold text-success fs-6">
                            ₹<?= number_format((float)$purchase['total_amount'], 2) ?>
                        </td>
                        <td class="text-center">
                            <div class="d-inline-flex gap-1 align-items-center">
                                <?php if (hasPermission('sellers', 'view')): ?>
                                    <a class="btn btn-sm btn-outline-secondary py-1 px-2" href="<?= url('production/purchases/view/' . $purchase['id']) ?>" title="View PO Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a class="btn btn-sm btn-outline-primary py-1 px-2" href="<?= url('production/purchases/print/' . $purchase['id']) ?>" target="_blank" rel="noopener" title="Print PO Invoice">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a class="btn btn-sm btn-dark py-1 px-2 fw-semibold" href="<?= url('production/purchases/roll-qr/' . $purchase['id']) ?>" target="_blank" rel="noopener" title="Roll QR Labels">
                                        <i class="bi bi-qr-code text-warning"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('sellers', 'edit')): ?>
                                    <a class="btn btn-sm btn-outline-primary py-1 px-2" href="<?= url('production/purchases/edit/' . $purchase['id']) ?>" title="Edit Purchase">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('sellers', 'delete')): ?>
                                    <form method="POST" action="<?= url('production/purchases/delete/' . $purchase['id']) ?>" class="d-inline mb-0" onsubmit="return confirm('Delete purchase <?= e(addslashes($purchase['purchase_number'])) ?>?');">
                                        <?= CSRF::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Purchase">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. TAB: DETAILS (Rolls Inventory & General Supplies) -->
    <div class="tab-pane fade" id="tab-details" role="tabpanel" aria-labelledby="tab-details-link">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-layers-half me-1 text-success"></i> Individual Fabric Rolls Received &amp; Stock Status
                </h6>
                <div class="small text-muted">Complete breakdown of every fabric roll inwarded with weights, QR codes, and current availability</div>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="rollFilterInput" class="form-control form-control-sm" placeholder="🔍 Search roll #, supplier, color..." style="width: 240px;" oninput="filterRollsTable(this.value)">
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle mb-0" id="fabricRollsTable">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th style="width: 45px;" class="text-center">#</th>
                        <th>Roll Number</th>
                        <th>Supplier / Mill</th>
                        <th>Fabric Description</th>
                        <th>Color / Shade</th>
                        <th>Design</th>
                        <th class="text-center">RQ</th>
                        <th class="text-end">Weight (kg)</th>
                        <th class="text-end">Total Price (₹)</th>
                        <th>Sticker Code</th>
                        <th class="text-center">Stock Status</th>
                        <th class="text-center" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="fabricRollsTableBody">
                <?php if (empty($fabricRollInventory)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-4">No fabric rolls recorded yet.</td></tr>
                <?php else: foreach ($fabricRollInventory as $index => $roll): ?>
                    <tr class="roll-row" data-search="<?= strtolower(e(($roll['roll_number'] ?? '') . ' ' . ($roll['company'] ?? '') . ' ' . ($roll['seller_name'] ?? '') . ' ' . ($roll['fabric_description'] ?? '') . ' ' . ($roll['color'] ?? '') . ' ' . ($roll['start_code'] ?? ''))) ?>">
                        <td class="text-center text-muted small fw-semibold"><?= $index + 1 ?></td>
                        <td>
                            <strong class="font-monospace text-dark"><?= e($roll['roll_number'] ?: '—') ?></strong>
                            <?php if (!empty($roll['photo_url'])): ?>
                                <a href="<?= uploadUrl($roll['photo_url']) ?>" target="_blank" rel="noopener" class="ms-1 text-primary small" title="View Roll Photo"><i class="bi bi-image"></i></a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?= e($roll['company'] ?: $roll['seller_name']) ?></div>
                            <small class="text-muted"><?= e($roll['seller_name']) ?></small>
                        </td>
                        <td><?= e($roll['fabric_description']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= e($roll['color'] ?: '—') ?></span></td>
                        <td><?= e($roll['design_name'] ?: '—') ?></td>
                        <td class="text-center fw-semibold"><?= number_format((int)$roll['quantity']) ?></td>
                        <td class="text-end fw-bold text-primary"><?= number_format((float)$roll['weight'], 2) ?> kg</td>
                        <td class="text-end fw-bold text-success">₹<?= number_format((float)$roll['price'], 2) ?></td>
                        <td class="font-monospace small text-muted">
                            <?= e($roll['start_code'] ?: '—') ?><?= !empty($roll['end_code']) && $roll['end_code'] !== $roll['start_code'] ? '–' . e($roll['end_code']) : '' ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$roll['available_quantity'] > 0): ?>
                                <span class="badge bg-success-subtle text-success border border-success"><?= number_format((int)$roll['available_quantity']) ?> Available</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Used / Consumed</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a class="btn btn-xs btn-outline-dark fw-semibold" href="<?= url('production/sellers/roll-labels/' . $roll['id']) ?>" target="_blank" rel="noopener" title="Thermal Print Label">
                                <i class="bi bi-qr-code text-primary me-1"></i> Label
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- General Supplies Purchased Subsection -->
        <?php if (!empty($supplyStock)): ?>
            <div class="card border shadow-sm">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>General Supplies Received</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-uppercase text-secondary">
                                <th>Item</th>
                                <th>Unit</th>
                                <th class="text-end">Total Received</th>
                                <th class="text-end">Purchase Lines</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supplyStock as $stock): ?>
                                <tr>
                                    <td><strong><?= e($stock['item_name']) ?></strong></td>
                                    <td><?= e($stock['unit']) ?></td>
                                    <td class="text-end fw-semibold text-primary"><?= number_format((float)$stock['total_quantity'], 3) ?></td>
                                    <td class="text-end"><?= number_format((int)$stock['purchase_lines']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterRollsTable(query) {
    const q = query.toLowerCase().trim();
    const rows = document.querySelectorAll('#fabricRollsTableBody .roll-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        r.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}

function switchToDetailsTab() {
    const detailsTabBtn = document.getElementById('tab-details-link');
    if (detailsTabBtn) {
        const tab = new bootstrap.Tab(detailsTabBtn);
        tab.show();
    }
}

// Preserve active tab state across reload
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash === '#details') {
        switchToDetailsTab();
    }
});
</script>
