<?php
$expense = $expense ?? [];
?>

<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">Edit WhatsApp Expense</h1>
            <div class="text-muted small">Voucher <?= e($expense['expense_number'] ?? '') ?> · changes are recorded in the audit log.</div>
        </div>
        <a href="<?= url('warehouse/whatsapp-orders/expenses') ?>" class="btn btn-outline-secondary btn-sm">Back to Expenses</a>
    </div>

    <form method="POST" enctype="multipart/form-data" action="<?= url('warehouse/whatsapp-orders/expenses/update/' . (int)$expense['id']) ?>" class="card border-0 shadow-sm rounded-4">
        <?= csrf_field() ?>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" value="<?= e($expense['category'] ?? '') ?>" maxlength="100" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Expense date</label>
                    <input type="date" name="expense_date" class="form-control" value="<?= e($expense['expense_date'] ?? '') ?>" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Item / description</label>
                    <input type="text" name="item_name" class="form-control" value="<?= e($expense['item_name'] ?? '') ?>" maxlength="255" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Amount</label>
                    <input type="number" name="amount" class="form-control" min="0.01" max="10000000" step="0.01" value="<?= e($expense['total_cost'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Paid to</label>
                    <input type="text" name="paid_to" class="form-control" value="<?= e($expense['paid_to'] ?? '') ?>" maxlength="100" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment method</label>
                    <select name="payment_method" class="form-select" required>
                        <?php foreach (['UPI' => 'UPI', 'CASH' => 'Cash', 'BANK_TRANSFER' => 'Bank transfer', 'CARD' => 'Card', 'OTHER' => 'Other'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($expense['payment_method'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vendor phone</label>
                    <input type="tel" name="vendor_phone" class="form-control" value="<?= e($expense['vendor_phone'] ?? '') ?>" maxlength="10" inputmode="numeric">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vendor company</label>
                    <input type="text" name="vendor_company" class="form-control" value="<?= e($expense['vendor_company'] ?? '') ?>" maxlength="200">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Invoice number</label>
                    <input type="text" name="invoice_number" class="form-control" value="<?= e($expense['invoice_number'] ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment reference</label>
                    <input type="text" name="payment_reference" class="form-control" value="<?= e($expense['payment_reference'] ?? '') ?>" maxlength="100">
                </div>
                <div class="col-12">
                    <label class="form-label">Replace receipt (optional)</label>
                    <input type="file" name="receipt_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <?php if (!empty($expense['receipt_url'])): ?>
                        <a href="<?= uploadUrl($expense['receipt_url']) ?>" target="_blank" rel="noopener noreferrer" class="small">View current receipt</a>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" maxlength="500"><?= e($expense['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2 p-3">
            <a href="<?= url('warehouse/whatsapp-orders/expenses') ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
        </div>
    </form>
</div>
