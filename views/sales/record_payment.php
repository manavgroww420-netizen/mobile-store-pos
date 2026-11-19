<?php
/**
 * Record Sale Customer Payment Receipt View
 * File: views/sales/record_payment.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/sales/<?= (int)$sale['id'] ?>" class="btn btn-outline btn-sm">&larr; Back to Sale Record</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    Record Customer Payment Receipt
                </h1>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 4px 0 0 0;">
                    Sale <strong><?= Security::e($sale['sale_number']) ?></strong> &bull; <?= !empty($sale['customer_name']) ? Security::e($sale['customer_name']) : 'Walk-in Customer' ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-3" style="gap: 24px; align-items: start;">
    <!-- Left Column: Outstanding Balance Summary -->
    <div style="grid-column: span 1;">
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <div class="card-title">
                    <span>&#128176;</span> Receivable Balance (Udhar)
                </div>
            </div>
            <div class="card-body" style="padding: 16px;">
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600;">Sale Total Billed</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-top: 4px;">
                        ₹ <?= number_format((float)$sale['final_total_amount'], 2) ?>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600;">Collected to Date</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: #059669; margin-top: 4px;">
                        ₹ <?= number_format((float)$sale['paid_amount'], 2) ?>
                    </div>
                </div>

                <div style="padding-top: 16px; border-top: 2px dashed var(--border-color);">
                    <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #b91c1c; font-weight: 700;">Remaining Balance Due</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #dc2626; margin-top: 4px;">
                        ₹ <?= number_format((float)$sale['due_amount'], 2) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                        Status: <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.75rem; padding: 2px 6px;"><?= Security::e($sale['payment_status']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="background: #f8fafc; border-color: #cbd5e1;">
            <div class="card-body" style="padding: 16px; font-size: 0.85rem; color: #475569;">
                <div style="font-weight: 700; margin-bottom: 6px; color: #1e293b;">
                    <span>&#9888;</span> Overpayment Policy
                </div>
                Overpayments are strictly prohibited. The maximum receivable amount for this sale is exactly <strong>₹ <?= number_format((float)$sale['due_amount'], 2) ?></strong>.
            </div>
        </div>
    </div>

    <!-- Right Column: Payment Receipt Entry Form -->
    <div style="grid-column: span 2;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <span>&#128197;</span> Payment Receipt Entry Form
                </div>
            </div>
            <div class="card-body">
                <form action="/sales/<?= (int)$sale['id'] ?>/payments/create" method="POST">
                    <?= Security::csrfField() ?>

                    <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label for="payment_date" class="form-label" style="font-weight: 600;">Payment Date <span style="color: #dc2626;">*</span></label>
                            <input 
                                type="date" 
                                id="payment_date" 
                                name="payment_date" 
                                class="form-control" 
                                value="<?= Security::e($formData['payment_date'] ?? date('Y-m-d')) ?>" 
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="payment_mode" class="form-label" style="font-weight: 600;">Payment Mode <span style="color: #dc2626;">*</span></label>
                            <select id="payment_mode" name="payment_mode" class="form-control" required>
                                <option value="CASH" <?= ($formData['payment_mode'] ?? '') === 'CASH' ? 'selected' : '' ?>>Cash Payment</option>
                                <option value="UPI" <?= ($formData['payment_mode'] ?? '') === 'UPI' ? 'selected' : '' ?>>UPI (GPay / PhonePe / Paytm)</option>
                                <option value="CARD" <?= ($formData['payment_mode'] ?? '') === 'CARD' ? 'selected' : '' ?>>Debit / Credit Card</option>
                                <option value="BANK_TRANSFER" <?= ($formData['payment_mode'] ?? '') === 'BANK_TRANSFER' ? 'selected' : '' ?>>Bank Transfer (NEFT/RTGS/IMPS)</option>
                                <option value="CHEQUE" <?= ($formData['payment_mode'] ?? '') === 'CHEQUE' ? 'selected' : '' ?>>Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label for="amount" class="form-label" style="font-weight: 600; margin: 0;">Payment Amount (₹) <span style="color: #dc2626;">*</span></label>
                            <button type="button" class="btn btn-outline btn-sm" id="btn-fill-full" style="font-size: 0.75rem; padding: 2px 8px;">
                                Settle Full Balance (₹ <?= number_format((float)$sale['due_amount'], 2) ?>)
                            </button>
                        </div>
                        <input 
                            type="number" 
                            id="amount" 
                            name="amount" 
                            class="form-control" 
                            step="0.01" 
                            min="0.01" 
                            max="<?= Security::e((string)$sale['due_amount']) ?>" 
                            value="<?= Security::e((string)$formData['amount']) ?>" 
                            required
                            style="font-size: 1.15rem; font-weight: 700; color: #1e293b;"
                        >
                        <div class="help-text" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                            Must be greater than 0.00 and cannot exceed the current outstanding balance of ₹<?= number_format((float)$sale['due_amount'], 2) ?>.
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="transaction_ref" class="form-label" style="font-weight: 600;">Transaction / Cheque / UTR Reference</label>
                        <input 
                            type="text" 
                            id="transaction_ref" 
                            name="transaction_ref" 
                            class="form-control" 
                            placeholder="e.g. UTR99882211, Cheque #124001, UPI Ref 38291..." 
                            value="<?= Security::e($formData['transaction_ref'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="notes" class="form-label" style="font-weight: 600;">Payment Remarks / Notes</label>
                        <textarea 
                            id="notes" 
                            name="notes" 
                            class="form-control" 
                            rows="3" 
                            placeholder="Optional settlement notes..."
                        ><?= Security::e($formData['notes'] ?? '') ?></textarea>
                    </div>

                    <div style="display: flex; gap: 12px; align-items: center; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--border-color);">
                        <a href="/sales/<?= (int)$sale['id'] ?>" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary" style="font-weight: 700;">
                            <span>&#128179;</span> Confirm &amp; Save Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fullAmount = "<?= (string)$sale['due_amount'] ?>";
    const amountInput = document.getElementById('amount');
    const fillBtn = document.getElementById('btn-fill-full');

    if (fillBtn && amountInput) {
        fillBtn.addEventListener('click', function() {
            amountInput.value = fullAmount;
            amountInput.focus();
        });
    }
});
</script>
