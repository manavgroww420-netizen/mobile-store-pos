/**
 * MOBILE STORE POS — Client-Side Foundation Utilities (Vanilla JS)
 *
 * File: public/assets/js/app.js
 */

document.addEventListener('DOMContentLoaded', () => {
    initFlashAlerts();
    initModalHandlers();
    initFormDoubleSubmitProtection();
    initScannerInputHelpers();
});

/**
 * Auto-dismiss flash alerts after 5 seconds and handle manual close clicks.
 */
function initFlashAlerts() {
    document.querySelectorAll('.alert').forEach(alert => {
        // Handle close button click
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 200);
            });
        }

        // Auto fade-out after 5 seconds for success/info alerts
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.4s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 400);
            }, 5000);
        }
    });
}

/**
 * Modal dialog trigger and close listeners.
 */
function initModalHandlers() {
    // Open triggers: [data-modal-open="modalId"]
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = btn.getAttribute('data-modal-open');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
            }
        });
    });

    // Close triggers: [data-modal-close]
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const modal = btn.closest('.modal-backdrop');
            if (modal) {
                modal.classList.remove('show');
            }
        });
    });

    // Close when clicking directly on the backdrop outside the modal dialog
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });
    });
}

/**
 * Prevent accidental double form submissions during financial/inventory operations.
 */
function initFormDoubleSubmitProtection() {
    document.querySelectorAll('form[data-prevent-double-submit]').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = 'Processing...';
                // Re-enable after 6 seconds in case of client-side validation stall
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 6000);
            }
        });
    });
}

/**
 * Intercept barcode scanner Enter keystrokes to prevent accidental form submission.
 */
function initScannerInputHelpers() {
    document.querySelectorAll('input[data-barcode-input]').forEach(input => {
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Dispatch custom event for barcode/IMEI scanner handlers
                input.dispatchEvent(new CustomEvent('barcodeScanned', {
                    bubbles: true,
                    detail: { value: input.value.trim() }
                }));
            }
        });
    });
}
