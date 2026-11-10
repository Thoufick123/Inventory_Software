<?php $canAdjust = hasPermission('whatsapp_inventory', 'adjust'); ?>
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-sliders me-2 text-primary"></i>WhatsApp Stock Adjustments</h2>
        <span class="text-muted">Request stock corrections for WhatsApp channel inventory. Approved adjustments update live channel balances.</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('warehouse/whatsapp-orders/allocations?tab=stock') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Inventory
        </a>
        <?php if ($canAdjust): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newWhatsappAdjustmentModal">
            <i class="bi bi-plus-circle me-1"></i> Request Adjustment
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($adjustments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-check-circle fs-1 text-success d-block mb-3"></i>
                <h5 class="text-muted">No Adjustment Requests Found</h5>
                <p class="text-muted small">Submit an adjustment request when a physical WhatsApp stock audit finds a variance.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Adjustment #</th>
                            <th>Channel</th>
                            <th>Product / SKU</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reason &amp; Notes</th>
                            <th>Requested By</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adjustments as $adjustment): ?>
                            <?php $qrCodes = json_decode((string)($adjustment['qr_codes'] ?? '[]'), true); ?>
                            <tr>
                                <td>
                                    <span class="fw-bold font-monospace text-primary"><?= e($adjustment['adjustment_number']) ?></span>
                                    <br><small class="text-muted"><?= date('d M Y, h:i A', strtotime($adjustment['created_at'])) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= e($adjustment['channel_name']) ?></span></td>
                                <td>
                                    <span class="fw-bold font-monospace"><?= e($adjustment['sku']) ?></span>
                                    <br><small class="text-muted"><?= e($adjustment['product_name']) ?> (<?= e($adjustment['size_name'] ?? '-') ?>, <?= e($adjustment['color_name'] ?? '-') ?>)</small>
                                </td>
                                <td><span class="badge bg-<?= $adjustment['adjustment_type'] === 'INCREASE' ? 'success' : 'danger' ?>"><?= e($adjustment['adjustment_type']) ?></span></td>
                                <td class="fw-bold <?= $adjustment['adjustment_type'] === 'INCREASE' ? 'text-success' : 'text-danger' ?>">
                                    <?= ($adjustment['adjustment_type'] === 'INCREASE' ? '+' : '-') . number_format((float)$adjustment['quantity']) ?> pcs
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= e($adjustment['reason']) ?></span>
                                    <?php if (is_array($qrCodes) && $qrCodes): ?>
                                        <br><small class="text-danger-emphasis fw-semibold"><?= $adjustment['status'] === 'APPROVED' ? 'Removed QR' : 'QR tags' ?>: <?= e(implode(', ', $qrCodes)) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($adjustment['notes'])): ?>
                                        <br><small class="text-muted"><?= e($adjustment['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="fw-semibold text-dark d-block"><?= e($adjustment['requested_by_name'] ?? 'Staff') ?></small>
                                    <?php if (!empty($adjustment['approved_by_name'])): ?>
                                        <small class="text-muted">Processed by: <?= e($adjustment['approved_by_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= $adjustment['status'] === 'APPROVED' ? 'success' : ($adjustment['status'] === 'PENDING' ? 'warning text-dark' : 'danger') ?>"><?= e($adjustment['status']) ?></span></td>
                                <td class="text-end">
                                    <?php if ($adjustment['status'] === 'PENDING' && $canAdjust): ?>
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="<?= url('warehouse/whatsapp-orders/adjustments/approve/' . $adjustment['id']) ?>">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="APPROVE">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this adjustment and update WhatsApp channel stock?');"><i class="bi bi-check-lg"></i> Approve</button>
                                            </form>
                                            <form method="POST" action="<?= url('warehouse/whatsapp-orders/adjustments/approve/' . $adjustment['id']) ?>">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="REJECT">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this adjustment request?');"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        </div>
                                    <?php elseif ($adjustment['status'] === 'PENDING'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Pending Review</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canAdjust): ?>
<div class="modal fade" id="newWhatsappAdjustmentModal" tabindex="-1" aria-labelledby="newWhatsappAdjustmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('warehouse/whatsapp-orders/adjustments/store') ?>" id="whatsappAdjustmentForm">
            <?= CSRF::input() ?>
            <input type="hidden" name="qr_codes" id="whatsappAdjustmentQrCodes" value="[]">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newWhatsappAdjustmentModalLabel"><i class="bi bi-sliders me-2 text-primary"></i>Request Stock Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">WhatsApp Channel <span class="text-danger">*</span></label>
                        <select name="channel_id" id="whatsappAdjustmentChannel" class="form-select" required>
                            <?php foreach ($channels as $channel): ?>
                                <option value="<?= (int)$channel['id'] ?>" <?= (int)$channel['id'] === (int)$defaultChannelId ? 'selected' : '' ?>><?= e($channel['name']) ?><?= !empty($channel['code']) ? ' (' . e($channel['code']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Item / Variant (SKU) <span class="text-danger">*</span></label>
                        <select name="variant_id" id="whatsappAdjustmentVariant" class="form-select" required>
                            <option value="">Select WhatsApp channel first</option>
                        </select>
                        <div id="whatsappAdjustmentVariantStatus" class="form-text" role="status"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Adjustment Type <span class="text-danger">*</span></label>
                            <select name="adjustment_type" id="whatsappAdjustmentType" class="form-select" required>
                                <option value="INCREASE">INCREASE (+ Found Stock)</option>
                                <option value="DECREASE">DECREASE (- Discrepancy / Damage)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Quantity (Pcs) <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="whatsappAdjustmentQuantity" class="form-control" min="1" step="1" value="1" required>
                        </div>
                    </div>
                    <div class="mb-3 d-none" id="whatsappAdjustmentQrScanner">
                        <label class="form-label small fw-semibold">Scan QR codes to remove from stock <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" id="whatsappAdjustmentQrInput" class="form-control" placeholder="Scan or enter product QR code..." autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" id="whatsappAdjustmentQrButton"><i class="bi bi-upc-scan me-1"></i>Add QR</button>
                        </div>
                        <small class="form-text text-muted">Each scanned QR removes its available unit(s) after approval.</small>
                        <div id="whatsappAdjustmentQrMessage" class="small mt-2" role="status"></div>
                        <div id="whatsappAdjustmentQrList" class="d-flex flex-wrap gap-1 mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Primary Reason <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select" required>
                            <option value="Physical Count Discrepancy">Physical Count Discrepancy (Stock Audit)</option>
                            <option value="Damaged / Spoiled in WhatsApp Inventory">Damaged / Spoiled in WhatsApp Inventory</option>
                            <option value="Lost / Missing Inventory">Lost / Missing Inventory</option>
                            <option value="Inventory Found / Corrected">Inventory Found / Corrected</option>
                            <option value="Sample / Showroom Pull">Sample / Showroom Pull</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Detailed Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Auditor name, audit batch reference..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Submit for Approval</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
(() => {
    const type = document.getElementById('whatsappAdjustmentType');
    const quantity = document.getElementById('whatsappAdjustmentQuantity');
    const channel = document.getElementById('whatsappAdjustmentChannel');
    const variant = document.getElementById('whatsappAdjustmentVariant');
    const variantStatus = document.getElementById('whatsappAdjustmentVariantStatus');
    const scanner = document.getElementById('whatsappAdjustmentQrScanner');
    const scanInput = document.getElementById('whatsappAdjustmentQrInput');
    const scanButton = document.getElementById('whatsappAdjustmentQrButton');
    const qrCodesInput = document.getElementById('whatsappAdjustmentQrCodes');
    const message = document.getElementById('whatsappAdjustmentQrMessage');
    const qrList = document.getElementById('whatsappAdjustmentQrList');
    const scanned = [];
    let variantRequestId = 0;

    async function loadAvailableVariants() {
        const requestId = ++variantRequestId;
        variant.replaceChildren(new Option('Loading available variants...', ''));
        variant.disabled = true;
        variantStatus.textContent = '';
        variantStatus.className = 'form-text';
        clearScanned();
        try {
            const url = `<?= url('api/whatsapp/adjustments/variants') ?>?channel_id=${encodeURIComponent(channel.value)}`;
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Could not load available WhatsApp variants.');
            if (requestId !== variantRequestId) return;

            variant.replaceChildren(new Option('Select Variant', ''));
            result.variants.forEach(item => {
                const label = `${item.sku} — ${item.product_name} (${item.size_name || '-'}, ${item.color_name || '-'}) · ${Number(item.available_quantity)} available`;
                variant.add(new Option(label, item.id));
            });
            variantStatus.textContent = result.variants.length
                ? `${result.variants.length} variant(s) with available WhatsApp stock.`
                : 'No variants currently have available stock in this channel.';
        } catch (error) {
            if (requestId !== variantRequestId) return;
            variant.replaceChildren(new Option('Available variants could not be loaded', ''));
            variantStatus.textContent = error.message || 'Could not load available WhatsApp variants.';
            variantStatus.className = 'form-text text-danger';
        } finally {
            if (requestId === variantRequestId) variant.disabled = false;
        }
    }

    function render() {
        qrList.replaceChildren();
        scanned.forEach((entry, index) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-dark font-monospace d-inline-flex align-items-center gap-1';
            const text = document.createElement('span');
            text.textContent = `${entry.tag} (${entry.quantity} pcs)`;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-close btn-close-white';
            remove.setAttribute('aria-label', `Remove QR ${entry.tag}`);
            remove.addEventListener('click', () => {
                scanned.splice(index, 1);
                update();
            });
            badge.append(text, remove);
            qrList.appendChild(badge);
        });
        qrCodesInput.value = JSON.stringify(scanned.map(entry => entry.tag));
        quantity.value = scanned.reduce((total, entry) => total + entry.quantity, 0) || 1;
    }

    function update() {
        render();
        message.textContent = scanned.length ? `${scanned.length} QR code(s) selected.` : '';
        message.className = 'small mt-2 text-muted';
    }

    function clearScanned() {
        scanned.splice(0, scanned.length);
        update();
    }

    type.addEventListener('change', () => {
        const decrease = type.value === 'DECREASE';
        scanner.classList.toggle('d-none', !decrease);
        quantity.readOnly = decrease;
        if (!decrease) {
            clearScanned();
        } else {
            scanInput.focus();
        }
    });
    channel.addEventListener('change', loadAvailableVariants);
    variant.addEventListener('change', clearScanned);
    loadAvailableVariants();

    async function addQr() {
        const code = scanInput.value.trim();
        if (!code) return;
        if (!variant.value) {
            message.textContent = 'Select the product variant before scanning.';
            message.className = 'small mt-2 text-danger fw-semibold';
            return;
        }
        scanButton.disabled = true;
        message.textContent = 'Checking QR code and channel stock...';
        message.className = 'small mt-2 text-muted';
        try {
            const url = `<?= url('api/whatsapp/adjustments/lookup') ?>?code=${encodeURIComponent(code)}&channel_id=${encodeURIComponent(channel.value)}&variant_id=${encodeURIComponent(variant.value)}`;
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'This QR code is not eligible.');
            if (scanned.some(entry => entry.tag === result.tag)) throw new Error(`QR code ${result.tag} is already in this request.`);
            scanned.push({tag: result.tag, quantity: Number(result.quantity) || 1});
            scanInput.value = '';
            update();
            scanInput.focus();
        } catch (error) {
            message.textContent = error.message || 'QR scan failed.';
            message.className = 'small mt-2 text-danger fw-semibold';
        } finally {
            scanButton.disabled = false;
        }
    }
    scanButton.addEventListener('click', addQr);
    scanInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addQr();
        }
    });
    document.getElementById('whatsappAdjustmentForm').addEventListener('submit', event => {
        if (type.value === 'DECREASE' && scanned.length === 0) {
            event.preventDefault();
            message.textContent = 'Scan at least one available QR code before requesting a decrease.';
            message.className = 'small mt-2 text-danger fw-semibold';
            scanInput.focus();
        }
    });
})();
</script>
<?php endif; ?>
