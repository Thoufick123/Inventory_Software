<!-- ========================================================================= -->
<!-- UNIVERSAL QR & BARCODE STATUS FINDER MODAL                                 -->
<!-- ========================================================================= -->
<div class="modal fade" id="qrStatusFinderModal" tabindex="-1" aria-labelledby="qrStatusFinderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <!-- Modal Header -->
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary rounded-3 text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-qr-code-scan fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="qrStatusFinderModalLabel">Universal QR Status Finder</h5>
                        <small class="text-white-50">Instant full lifecycle &amp; current location tracker across all inventory modules</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4">
                <!-- Search & Scanner Input Bar -->
                <div class="card border bg-light shadow-sm mb-4">
                    <div class="card-body p-3">
                        <label for="qrFinderInput" class="form-label fw-bold text-dark small text-uppercase mb-2">
                            <i class="bi bi-upc-scan me-1 text-primary"></i> Scan or Enter Any QR Code / Barcode / Number
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="qrFinderInput" class="form-control border-start-0 font-monospace fs-6" placeholder="e.g. FB112601, MSF112606, TTT112631, BAT-..., CUT-..., PP-..." autocomplete="off">
                            <button class="btn btn-primary px-4 fw-semibold d-flex align-items-center gap-1.5" type="button" id="btnTraceQr" onclick="runQrStatusFinder()">
                                <i class="bi bi-geo-alt-fill"></i>
                                <span>Trace Status</span>
                            </button>
                            <button class="btn btn-outline-secondary px-3" type="button" id="btnClearQrFinder" onclick="clearQrFinder()" title="Clear">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>Supports: Fabric Roll labels, QC garment tags, Warehouse QRs, Batches, Invoices &amp; Damages.
                            </small>
                            <button type="button" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1 py-0 px-2" id="btnToggleCameraScanner" onclick="toggleCameraScanner()">
                                <i class="bi bi-camera me-1"></i><span id="cameraToggleText">Scan with Camera</span>
                            </button>
                        </div>
                        
                        <!-- Camera Scanner Container (Hidden by default) -->
                        <div id="qrCameraScannerContainer" class="mt-3 text-center border rounded p-2 bg-white" style="display: none;">
                            <div id="qrInteractiveScanner" style="max-width: 400px; margin: 0 auto;"></div>
                            <small class="text-muted d-block mt-2">Position the barcode / QR code in front of your camera</small>
                        </div>
                    </div>
                </div>

                <!-- Status / Error Notification Box -->
                <div id="qrFinderFeedback" class="alert d-none py-2 px-3 mb-3 small" role="alert"></div>

                <!-- Spinner / Loading State -->
                <div id="qrFinderLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Scanning Inventory Ecosystem…</h6>
                    <p class="text-muted small mb-0">Searching Raw Materials, Cutting, QC, Batches, Outwards, Warehouse Stock, Damages and Billing records.</p>
                </div>

                <!-- Empty Initial State -->
                <div id="qrFinderEmpty" class="text-center py-5 text-muted">
                    <div class="p-3 bg-light rounded-circle d-inline-flex mb-3">
                        <i class="bi bi-compass fs-1 text-primary"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Ready to Find Item Status</h6>
                    <p class="small text-muted mb-0" style="max-width: 420px; margin: 0 auto;">
                        Scan any sticker with a barcode reader or type the identifier above to reveal where this item physically is right now and its complete historical journey.
                    </p>
                </div>

                <!-- Results Display Container (Hidden until scanned) -->
                <div id="qrFinderResults" class="d-none">
                    <!-- 1. Hero Summary Card (Current Location & Status) -->
                    <div class="card border-0 shadow-sm mb-4 text-white overflow-hidden" id="qrHeroCard" style="background: linear-gradient(135deg, #1e293b, #0f172a);">
                        <div class="card-body p-4 position-relative">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div>
                                    <span class="badge bg-light text-dark font-monospace px-2.5 py-1 mb-2" id="qrBadgeCode">CODE-12345</span>
                                    <span class="badge bg-primary bg-opacity-25 text-info border border-info border-opacity-25 ms-1" id="qrBadgeType">Item Type</span>
                                    <h4 class="fw-bold text-white mb-1" id="qrItemTitle">Item Title</h4>
                                </div>
                                <div class="text-end">
                                    <span class="badge px-3 py-2 fs-7 fw-bold" id="qrStatusBadge">STATUS</span>
                                </div>
                            </div>

                            <!-- Current Physical / System Location Callout -->
                            <div class="p-3 rounded-3 mt-3 d-flex align-items-center gap-3" style="background: rgba(255,255,255,0.08); border-left: 4px solid #38bdf8;">
                                <div class="fs-2 text-info">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <div>
                                    <span class="text-uppercase text-white-50 small fw-bold d-block">Current Physical &amp; System Location</span>
                                    <div class="fs-6 fw-bold text-white" id="qrCurrentLocation">Facility Location</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Item Specifications & Metrics Cards -->
                    <div class="card border shadow-sm mb-4">
                        <div class="card-header bg-white py-2.5 px-3">
                            <h6 class="mb-0 fw-bold small text-uppercase text-secondary">
                                <i class="bi bi-info-circle me-1 text-primary"></i> Scanned Item Attributes
                            </h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2" id="qrAttributesGrid">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- 3. Lifecycle History Timeline -->
                    <div class="card border shadow-sm">
                        <div class="card-header bg-white py-2.5 px-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold small text-uppercase text-secondary">
                                <i class="bi bi-clock-history me-1 text-primary"></i> Complete Lifecycle History &amp; Movement Timeline
                            </h6>
                            <span class="badge bg-secondary" id="qrTimelineCount">0 Steps</span>
                        </div>
                        <div class="card-body p-4">
                            <div class="qr-timeline position-relative" id="qrTimelineContainer">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between">
                <small class="text-muted"><i class="bi bi-shield-check me-1 text-success"></i>Live database synchronization enabled</small>
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* QR Finder Timeline Styling */
.qr-timeline {
    position: relative;
    padding-left: 32px;
}
.qr-timeline::before {
    content: '';
    position: absolute;
    top: 10px;
    bottom: 10px;
    left: 14px;
    width: 2px;
    background: #e2e8f0;
}
.qr-timeline-item {
    position: relative;
    margin-bottom: 24px;
}
.qr-timeline-item:last-child {
    margin-bottom: 0;
}
.qr-timeline-icon {
    position: absolute;
    left: -32px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    border: 2px solid #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.qr-timeline-content {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    transition: background 0.15s ease-in-out;
}
.qr-timeline-content:hover {
    background: #f1f5f9;
}
</style>

<script>
let html5QrScannerInstance = null;
let cameraScannerActive = false;

document.addEventListener('DOMContentLoaded', () => {
    const qrModal = document.getElementById('qrStatusFinderModal');
    const qrInput = document.getElementById('qrFinderInput');

    if (qrModal && qrInput) {
        qrModal.addEventListener('shown.bs.modal', () => {
            qrInput.focus();
            qrInput.select();
        });

        qrModal.addEventListener('hidden.bs.modal', () => {
            stopCameraScanner();
        });

        qrInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                runQrStatusFinder();
            }
        });
    }
});

function clearQrFinder() {
    const qrInput = document.getElementById('qrFinderInput');
    if (qrInput) {
        qrInput.value = '';
        qrInput.focus();
    }
    document.getElementById('qrFinderResults').classList.add('d-none');
    document.getElementById('qrFinderLoading').classList.add('d-none');
    document.getElementById('qrFinderEmpty').classList.remove('d-none');
    hideQrFeedback();
}

function showQrFeedback(msg, isError = false) {
    const fb = document.getElementById('qrFinderFeedback');
    if (!fb) return;
    fb.className = `alert ${isError ? 'alert-danger' : 'alert-success'} py-2 px-3 mb-3 small d-flex align-items-center gap-2`;
    fb.innerHTML = `<i class="bi bi-${isError ? 'exclamation-circle-fill' : 'check-circle-fill'}"></i><span>${msg}</span>`;
    fb.classList.remove('d-none');
}

function hideQrFeedback() {
    const fb = document.getElementById('qrFinderFeedback');
    if (fb) fb.classList.add('d-none');
}

function runQrStatusFinder() {
    const qrInput = document.getElementById('qrFinderInput');
    const code = qrInput ? qrInput.value.trim() : '';

    if (!code) {
        showQrFeedback('Please enter or scan a QR code / barcode first.', true);
        if (qrInput) qrInput.focus();
        return;
    }

    hideQrFeedback();
    document.getElementById('qrFinderEmpty').classList.add('d-none');
    document.getElementById('qrFinderResults').classList.add('d-none');
    document.getElementById('qrFinderLoading').classList.remove('d-none');

    fetch('<?= url('api/qr/status-finder') ?>?code=' + encodeURIComponent(code))
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'No inventory record matches this QR code.');
            }
            return data;
        })
        .then(data => {
            renderQrFinderResults(data);
        })
        .catch(err => {
            document.getElementById('qrFinderLoading').classList.add('d-none');
            document.getElementById('qrFinderEmpty').classList.remove('d-none');
            showQrFeedback(err.message || 'Item not found in any inventory stage.', true);
        });
}

function renderQrFinderResults(data) {
    document.getElementById('qrFinderLoading').classList.add('d-none');
    document.getElementById('qrFinderEmpty').classList.add('d-none');
    const resultsBox = document.getElementById('qrFinderResults');
    resultsBox.classList.remove('d-none');

    // 1. Hero Card
    document.getElementById('qrBadgeCode').textContent = data.code;
    document.getElementById('qrBadgeType').textContent = data.item_type || 'Inventory Item';
    document.getElementById('qrItemTitle').textContent = data.item_title;
    document.getElementById('qrCurrentLocation').textContent = data.current_location;

    const statusBadge = document.getElementById('qrStatusBadge');
    statusBadge.textContent = (data.current_status || 'ACTIVE').replace(/_/g, ' ');
    statusBadge.className = `badge px-3 py-2 fs-7 fw-bold bg-${data.status_color || 'primary'}`;

    // 2. Attributes Grid
    const attrGrid = document.getElementById('qrAttributesGrid');
    attrGrid.replaceChildren();

    if (data.details && typeof data.details === 'object') {
        for (const [key, val] of Object.entries(data.details)) {
            const col = document.createElement('div');
            col.className = 'col-sm-6 col-md-4';
            col.innerHTML = `
                <div class="p-2 bg-light rounded border h-100">
                    <span class="text-uppercase text-muted d-block" style="font-size: 11px;">${key}</span>
                    <strong class="text-dark small text-break">${val || '—'}</strong>
                </div>
            `;
            attrGrid.appendChild(col);
        }
    }

    // 3. Timeline
    const tlContainer = document.getElementById('qrTimelineContainer');
    tlContainer.replaceChildren();

    const timeline = data.timeline || [];
    document.getElementById('qrTimelineCount').textContent = `${timeline.length} ${timeline.length === 1 ? 'Step' : 'Steps'}`;

    if (timeline.length === 0) {
        tlContainer.innerHTML = '<div class="text-muted small">No lifecycle movement steps logged yet.</div>';
    } else {
        timeline.forEach((step, idx) => {
            const isLast = (idx === timeline.length - 1);
            const stepEl = document.createElement('div');
            stepEl.className = 'qr-timeline-item';

            const badgeBg = step.badge_color || 'primary';
            const iconName = step.icon || 'bi-circle-fill';

            const dt = step.timestamp ? new Date(step.timestamp.replace(/-/g, '/')) : null;
            const dateStr = dt && !isNaN(dt.getTime()) 
                ? dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                : (step.timestamp || 'N/A');

            stepEl.innerHTML = `
                <div class="qr-timeline-icon bg-${badgeBg} text-white">
                    <i class="bi ${iconName}"></i>
                </div>
                <div class="qr-timeline-content ${isLast ? 'border-primary border-opacity-50 shadow-sm' : ''}">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                        <strong class="text-dark fs-7">${step.title}</strong>
                        <div class="d-flex align-items-center gap-1.5">
                            <span class="badge bg-${badgeBg}-subtle text-${badgeBg} border border-${badgeBg}-subtle font-monospace" style="font-size: 11px;">${step.badge || 'LOG'}</span>
                            <span class="text-muted small" style="font-size: 11px;"><i class="bi bi-clock me-1"></i>${dateStr}</span>
                        </div>
                    </div>
                    <div class="small text-secondary mb-1">
                        <i class="bi bi-geo-alt me-1 text-primary"></i><strong>${step.location || 'Facility'}</strong>
                    </div>
                    <p class="small text-muted mb-0" style="line-height: 1.4;">${step.description}</p>
                </div>
            `;
            tlContainer.appendChild(stepEl);
        });
    }
}

function toggleCameraScanner() {
    if (cameraScannerActive) {
        stopCameraScanner();
    } else {
        startCameraScanner();
    }
}

function startCameraScanner() {
    const container = document.getElementById('qrCameraScannerContainer');
    const toggleBtnText = document.getElementById('cameraToggleText');
    if (!container) return;

    if (typeof Html5Qrcode === 'undefined') {
        alert('Camera scanner module is loading or unavailable in this environment.');
        return;
    }

    container.style.display = 'block';
    if (toggleBtnText) toggleBtnText.textContent = 'Stop Camera';
    cameraScannerActive = true;

    html5QrScannerInstance = new Html5Qrcode('qrInteractiveScanner');
    html5QrScannerInstance.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            const qrInput = document.getElementById('qrFinderInput');
            if (qrInput) {
                qrInput.value = decodedText;
                stopCameraScanner();
                runQrStatusFinder();
            }
        },
        () => {} // Ignore frame-by-frame read errors
    ).catch(err => {
        showQrFeedback('Could not access camera: ' + err, true);
        stopCameraScanner();
    });
}

function stopCameraScanner() {
    const container = document.getElementById('qrCameraScannerContainer');
    const toggleBtnText = document.getElementById('cameraToggleText');
    if (container) container.style.display = 'none';
    if (toggleBtnText) toggleBtnText.textContent = 'Scan with Camera';
    cameraScannerActive = false;

    if (html5QrScannerInstance) {
        html5QrScannerInstance.stop().then(() => {
            html5QrScannerInstance.clear();
            html5QrScannerInstance = null;
        }).catch(() => {
            html5QrScannerInstance = null;
        });
    }
}
</script>
