<!-- ========================================================================= -->
<!-- GLOBAL MODERN CONFIRMATION MODAL                                          -->
<!-- Replaces default browser confirm() dialog with an elegant, modern UI      -->
<!-- ========================================================================= -->
<div class="modal fade" id="globalModernConfirmModal" tabindex="-1" aria-labelledby="globalModernConfirmModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-body p-4 text-center">
                <!-- Glowing Icon Indicator -->
                <div class="confirm-icon-pulse mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle" id="modernConfirmIconWrapper" style="width: 74px; height: 74px; background: #fee2e2; color: #dc2626; border: 4px solid #fecaca; transition: all 0.3s ease;">
                    <i class="bi bi-trash3-fill" id="modernConfirmIcon" style="font-size: 2rem;"></i>
                </div>
                
                <h4 class="fw-bold text-dark mb-2" id="modernConfirmTitle">Confirm Action</h4>
                <p class="text-secondary small mb-0 px-2" id="modernConfirmMessage" style="font-size: 0.925rem; line-height: 1.55;">
                    Are you sure you want to proceed? This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3 d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-semibold" data-bs-dismiss="modal" id="modernConfirmCancelBtn">
                    Cancel
                </button>
                <button type="button" class="btn btn-danger px-4 py-2 rounded-pill fw-semibold shadow-sm" id="modernConfirmOkBtn">
                    <i class="bi bi-trash3 me-1.5" id="modernConfirmBtnIcon"></i>
                    <span id="modernConfirmBtnText">Yes, Delete</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
#globalModernConfirmModal .modal-content {
    animation: modernModalZoom 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modernModalZoom {
    0% { transform: scale(0.92); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
.confirm-icon-pulse {
    box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4);
    animation: confirmPulse 2s infinite;
}
@keyframes confirmPulse {
    0% { transform: scale(0.98); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.35); }
    70% { transform: scale(1); box-shadow: 0 0 0 14px rgba(220, 38, 38, 0); }
    100% { transform: scale(0.98); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
}
.confirm-icon-warning {
    box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4) !important;
    animation: confirmPulseWarn 2s infinite !important;
}
@keyframes confirmPulseWarn {
    0% { transform: scale(0.98); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.35); }
    70% { transform: scale(1); box-shadow: 0 0 0 14px rgba(217, 119, 6, 0); }
    100% { transform: scale(0.98); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
}
</style>

<script>
(function() {
    let pendingConfirmCallback = null;

    window.showModernConfirm = function(options) {
        options = options || {};
        const title = options.title || (options.isDelete ? 'Confirm Deletion' : 'Are you sure?');
        const message = options.message || 'Are you sure you want to proceed? This action cannot be undone.';
        const btnText = options.btnText || (options.isDelete ? 'Yes, Delete' : 'Confirm');
        const isDelete = options.isDelete !== false;

        const modalEl = document.getElementById('globalModernConfirmModal');
        if (!modalEl) {
            if (options.onConfirm) options.onConfirm();
            return;
        }

        const titleEl = document.getElementById('modernConfirmTitle');
        const msgEl = document.getElementById('modernConfirmMessage');
        const okBtn = document.getElementById('modernConfirmOkBtn');
        const btnTextEl = document.getElementById('modernConfirmBtnText');
        const btnIconEl = document.getElementById('modernConfirmBtnIcon');
        const iconWrapper = document.getElementById('modernConfirmIconWrapper');
        const iconEl = document.getElementById('modernConfirmIcon');

        if (titleEl) titleEl.textContent = title;
        if (msgEl) msgEl.textContent = message;
        if (btnTextEl) btnTextEl.textContent = btnText;

        if (isDelete) {
            iconWrapper.className = 'confirm-icon-pulse mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle';
            iconWrapper.style.background = '#fee2e2';
            iconWrapper.style.color = '#dc2626';
            iconWrapper.style.border = '4px solid #fecaca';
            iconEl.className = 'bi bi-trash3-fill';
            okBtn.className = 'btn btn-danger px-4 py-2 rounded-pill fw-semibold shadow-sm';
            btnIconEl.className = 'bi bi-trash3 me-1.5';
        } else {
            iconWrapper.className = 'confirm-icon-pulse confirm-icon-warning mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle';
            iconWrapper.style.background = '#fef3c7';
            iconWrapper.style.color = '#d97706';
            iconWrapper.style.border = '4px solid #fde68a';
            iconEl.className = 'bi bi-exclamation-triangle-fill';
            okBtn.className = 'btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm';
            btnIconEl.className = 'bi bi-check2-circle me-1.5';
        }

        pendingConfirmCallback = options.onConfirm || null;
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    };

    document.addEventListener('DOMContentLoaded', function() {
        const okBtn = document.getElementById('modernConfirmOkBtn');
        const modalEl = document.getElementById('globalModernConfirmModal');

        if (okBtn) {
            okBtn.addEventListener('click', function() {
                const cb = pendingConfirmCallback;
                pendingConfirmCallback = null;
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
                if (typeof cb === 'function') {
                    cb();
                }
            });
        }

        // Global Event Interceptor (Capture Phase) for forms with onsubmit="return confirm(...)" or data-confirm="..."
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form || form._modernConfirmed) return;

            let message = form.getAttribute('data-confirm');
            const onsubmitAttr = form.getAttribute('onsubmit') || '';

            if (!message && onsubmitAttr.includes('confirm(')) {
                const match = onsubmitAttr.match(/confirm\(\s*(['"`])([\s\S]*?)\1\s*\)/);
                if (match && match[2]) {
                    message = match[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
                }
            }

            if (message) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const isDelete = (form.action && form.action.includes('delete')) || 
                                 onsubmitAttr.toLowerCase().includes('delete') || 
                                 message.toLowerCase().includes('delete');

                window.showModernConfirm({
                    title: isDelete ? 'Confirm Deletion' : 'Please Confirm',
                    message: message,
                    isDelete: isDelete,
                    btnText: isDelete ? 'Yes, Delete' : 'Confirm',
                    onConfirm: function() {
                        form._modernConfirmed = true;
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                });
            }
        }, true);

        // Global Event Interceptor for links & buttons with onclick="return confirm(...)" or data-confirm="..."
        document.addEventListener('click', function(e) {
            const clickable = e.target.closest('a, button');
            if (!clickable || clickable._modernConfirmed) return;

            let message = clickable.getAttribute('data-confirm');
            const onclickAttr = clickable.getAttribute('onclick') || '';

            if (!message && onclickAttr.includes('confirm(')) {
                const match = onclickAttr.match(/confirm\(\s*(['"`])([\s\S]*?)\1\s*\)/);
                if (match && match[2]) {
                    message = match[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
                }
            }

            if (message) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const isLink = clickable.tagName.toLowerCase() === 'a';
                const form = clickable.form || clickable.closest('form');
                const actionUrl = isLink ? (clickable.href || '') : (form ? (form.action || '') : '');
                const isDelete = actionUrl.includes('delete') || 
                                 onclickAttr.toLowerCase().includes('delete') || 
                                 message.toLowerCase().includes('delete');

                window.showModernConfirm({
                    title: isDelete ? 'Confirm Deletion' : 'Please Confirm',
                    message: message,
                    isDelete: isDelete,
                    btnText: isDelete ? 'Yes, Delete' : 'Confirm',
                    onConfirm: function() {
                        clickable._modernConfirmed = true;
                        if (isLink) {
                            window.location.href = clickable.href;
                        } else if (onclickAttr.includes('.submit()')) {
                            // Target specific form submit calls like document.getElementById('deleteSupplierForm').submit();
                            const submitMatch = onclickAttr.match(/document\.getElementById\(['"]([^'"]+)['"]\)\.submit\(\)/);
                            if (submitMatch && submitMatch[1]) {
                                const targetForm = document.getElementById(submitMatch[1]);
                                if (targetForm) {
                                    targetForm.submit();
                                    return;
                                }
                            }
                            if (form) form.submit();
                        } else if (form) {
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit(clickable);
                            } else {
                                form.submit();
                            }
                        } else {
                            clickable.click();
                        }
                    }
                });
            }
        }, true);
            }
        }, true);
    });
})();
</script>
