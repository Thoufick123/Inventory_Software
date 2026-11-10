<?php
$products = $products ?? [];
$printedCount = count(array_filter($products, static fn(array $product): bool => !empty($product['printed_at'])));
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-1 rounded-pill fw-semibold">
                <i class="bi bi-whatsapp me-1"></i>WhatsApp Inventory
            </span>
            <span class="badge bg-warning-subtle text-warning-emphasis border rounded-pill px-2 py-1">Quarantined stock</span>
        </div>
        <h2 class="fw-bold mb-1 text-dark"><i class="bi bi-arrow-return-left me-2 text-success"></i>Returned Products</h2>
        <span class="text-muted">Customer returns are stored here separately and are not added to available WhatsApp stock.</span>
    </div>
    <a href="<?= url('warehouse/whatsapp-orders/allocations') ?>" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-boxes me-1"></i> Regular Inventory
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted text-uppercase fw-semibold">Returned pieces</div>
            <div class="fs-3 fw-bold text-dark"><?= number_format(count($products)) ?></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted text-uppercase fw-semibold">Labels printed</div>
            <div class="fs-3 fw-bold text-success"><?= number_format($printedCount) ?></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted text-uppercase fw-semibold">Awaiting print</div>
            <div class="fs-3 fw-bold text-warning-emphasis"><?= number_format(count($products) - $printedCount) ?></div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-success"></i>Returned Product Register</h5>
        <span class="badge bg-light text-dark border"><?= count($products) ?> pieces</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Returned QR</th>
                    <th>Product / Variant</th>
                    <th>Original piece tag</th>
                    <th>Invoice / Return</th>
                    <th>Condition</th>
                    <th>Print status</th>
                    <th class="text-end pe-3">Label</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$products): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>No WhatsApp customer returns have been recorded.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td class="ps-3"><span class="badge bg-success-subtle text-success border border-success-subtle font-monospace fs-6"><?= htmlspecialchars($product['returned_tag']) ?></span></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($product['product_name']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($product['sku']) ?> · <?= htmlspecialchars(($product['size_name'] ?? 'Free Size') . ' / ' . ($product['color_name'] ?? 'Standard')) ?></small>
                            </td>
                            <td class="font-monospace"><?= htmlspecialchars($product['original_piece_tag'] ?: 'Generated from SKU') ?></td>
                            <td>
                                <div><?= htmlspecialchars($product['invoice_number'] ?? '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($product['return_number'] ?? '-') ?></small>
                            </td>
                            <td>
                                <?php $isGood = strtoupper((string)$product['item_condition']) === 'GOOD'; ?>
                                <span class="badge <?= $isGood ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>"><?= htmlspecialchars($product['item_condition']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($product['printed_at'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check2 me-1"></i>Printed</span>
                                    <small class="d-block text-muted mt-1"><?= htmlspecialchars($product['printed_at']) ?></small>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-hourglass-split me-1"></i>Not printed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <a class="btn btn-sm <?= empty($product['printed_at']) ? 'btn-success' : 'btn-outline-secondary' ?>" target="_blank" rel="noopener" href="<?= url('warehouse/whatsapp-orders/returned-products/label/' . (int)$product['id']) ?>">
                                    <i class="bi bi-printer me-1"></i><?= empty($product['printed_at']) ? 'Print label' : 'Print again' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
