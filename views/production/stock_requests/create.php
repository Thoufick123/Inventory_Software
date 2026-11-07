<?php
// Production Unit Create Warehouse Stock Requisition Form
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-box-arrow-in-down-right text-primary me-2"></i>Create Warehouse Stock Requisition
            </h4>
            <span class="text-muted small">Submit a finished goods replenishment request from Central Warehouse to the Production Factory</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('production/stock-requests') ?>" class="btn btn-outline-secondary btn-sm fw-semibold shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Requisitions
            </a>
        </div>
    </div>

    <form method="POST" action="<?= url('production/stock-requests/store') ?>">
        <?= CSRF::field() ?>

        <div class="row g-4 justify-content-center">
            <div class="col-lg-10">
                <!-- Requisition Details -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-info-circle text-primary me-2"></i>1. Requisition Header &amp; Priority</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark mb-1">Destination Warehouse / Hub <span class="text-danger">*</span></label>
                                <input type="text" name="warehouse_name" class="form-control fw-semibold" value="Central Warehouse (Main Hub)" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark mb-1">Requested By (Staff / Manager)</label>
                                <input type="text" name="requested_by_name" class="form-control" value="Warehouse Manager" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold text-dark mb-1">Priority</label>
                                <select name="priority" class="form-select fw-semibold">
                                    <option value="NORMAL" selected>Normal</option>
                                    <option value="HIGH">High</option>
                                    <option value="URGENT">Urgent (Express)</option>
                                    <option value="LOW">Low</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold text-dark mb-1">Required By Date</label>
                                <input type="date" name="required_by_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark mb-1">Requisition Remarks / Context</label>
                                <input type="text" name="notes" class="form-control" placeholder="e.g. Replenishment for upcoming weekend retail promotion.">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requisition Line Items -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-stars text-primary me-2"></i>2. Garment Styles &amp; Target Quantities</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRequestRow()">
                            <i class="bi bi-plus-circle me-1"></i> Add Style Line
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0" id="reqItemsTable">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th style="width: 40%;">Product Style</th>
                                        <th style="width: 25%;">Specific Variant / Size / Color</th>
                                        <th style="width: 20%;" class="text-center">Requested Quantity</th>
                                        <th style="width: 15%;" class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="reqItemsTbody">
                                    <tr>
                                        <td>
                                            <select name="items[0][product_id]" class="form-select product-select" onchange="onProductChange(this, 0)" required>
                                                <option value="">-- Choose Product Style --</option>
                                                <?php foreach ($products as $p): ?>
                                                    <option value="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-code="<?= e($p['code']) ?>">
                                                        <?= e($p['name']) ?> (<?= e($p['code']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="items[0][variant_id]" class="form-select variant-select">
                                                <option value="">-- All Variants / Any --</option>
                                                <?php foreach ($variants as $v): ?>
                                                    <option value="<?= $v['id'] ?>" data-product-id="<?= $v['product_id'] ?>">
                                                        <?= e($v['sku']) ?> <?= $v['size_name'] ? '· ' . e($v['size_name']) : '' ?> <?= $v['color_name'] ? '· ' . e($v['color_name']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <div class="input-group input-group-sm justify-content-center" style="max-width: 150px; margin: 0 auto;">
                                                <input type="number" min="1" step="1" name="items[0][qty]" class="form-control text-center fw-bold" value="100" required>
                                                <span class="input-group-text">pcs</span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRequestRow(this)" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                        <a href="<?= url('production/stock-requests') ?>" class="btn btn-outline-secondary px-3">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm">
                            <i class="bi bi-send-check me-1"></i> Submit Stock Requisition to Factory
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let rowIdx = 1;

function addRequestRow() {
    const tbody = document.getElementById('reqItemsTbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="items[${rowIdx}][product_id]" class="form-select product-select" onchange="onProductChange(this, ${rowIdx})" required>
                <option value="">-- Choose Product Style --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-code="<?= e($p['code']) ?>">
                        <?= e($p['name']) ?> (<?= e($p['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select name="items[${rowIdx}][variant_id]" class="form-select variant-select">
                <option value="">-- All Variants / Any --</option>
                <?php foreach ($variants as $v): ?>
                    <option value="<?= $v['id'] ?>" data-product-id="<?= $v['product_id'] ?>">
                        <?= e($v['sku']) ?> <?= $v['size_name'] ? '· ' . e($v['size_name']) : '' ?> <?= $v['color_name'] ? '· ' . e($v['color_name']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="text-center">
            <div class="input-group input-group-sm justify-content-center" style="max-width: 150px; margin: 0 auto;">
                <input type="number" min="1" step="1" name="items[${rowIdx}][qty]" class="form-control text-center fw-bold" value="50" required>
                <span class="input-group-text">pcs</span>
            </div>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRequestRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    rowIdx++;
}

function removeRequestRow(btn) {
    const row = btn.closest('tr');
    row.remove();
}

function onProductChange(selectEl, idx) {
    const productId = selectEl.value;
    const row = selectEl.closest('tr');
    const varSelect = row.querySelector('.variant-select');
    if (!varSelect) return;

    for (let opt of varSelect.options) {
        if (!opt.value) continue;
        const pId = opt.getAttribute('data-product-id');
        if (!productId || pId === productId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    }
}
</script>
