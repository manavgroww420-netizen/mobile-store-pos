<?php
/**
 * Customer Repair Payment Receipt Entry View
 * File: views/repairs/record_payment.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="/repairs/<?= (int)$repair['id'] ?>" class="btn btn-outline btn-sm">&larr; Back to Repair</a>
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                Record Customer Payment Receipt
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin: 4px 0 0 0;">
                Settle outstanding balance for repair job <?= Security::e($repair['repair_number']) ?>.
            </p>
        </div>
    </div>
</div>

<div class="grid grid-cols-3" style="gap: 24px; align-items: start;">
    <!-- Left Column: Repair Account Status -->
    <div>
        <div class="card">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                <div class="card-title">
                    <span>&#128100;</span> Repair Account Balance
                </div>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 12px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Repair Number</span>
                    <div style="font-weight: 700; color: #1e293b;"><?= Security::e($repair['repair_number']) ?></div>
                </div>

                <div style="margin-bottom: 12px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Customer</span>
                    <div style="font-weight: 600; color: #1e293b;"><?= Security::e($repair['customer_name'] ?? '—') ?></div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= Security::e($repair['customer_phone'] ?? '—') ?></div>
                </div>

                <div style="margin-bottom: 16px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Device Model</span>
                    <div style="font-weight: 600; color: #1e293b;"><?= Security::e($repair['device_model'] ?? '—') ?></div>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 12px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted); font-size: 0.85rem;">Total Charged:</span>
                    <span style="font-weight: 600;">₹ <?= number_format((float)$repair['amount_charged'], 2) ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #059669; font-size: 0.85rem;">Already Paid:</span>
                    <span style="font-weight: 600; color: #059669;">₹ <?= number_format((float)$repair['paid_amount'], 2) ?></span>
                </div>

                <div style="border-top: 2px solid #dc2626; padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; color: #991b1b;">Current Due Balance:</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: #dc2626;">₹ <?= number_format((float)$repair['due_amount'], 2) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right 2 Columns: Payment Entry Form -->
    <div style="grid-column: span 2;">
        <div class="card">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                <div class="card-title">
                    <span>&#128176;</span> Payment Collection Details
                </div>
            </div>
            <div class="card-body">
                <form id="payment-form" action="/repairs/<?= (int)$repair['id'] ?>/payments/create" method="POST">
                    <?= Security::csrfField() ?>

                    <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label for="payment_date" class="form-label" style="font-weight: 600;">Receipt Date <span style="color: #dc2626;">*</span></label>
                            <input 
                                type="date" 
                                id="payment_date" 
                                name="payment_date" 
                                class="form-control" 
                                required 
                                value="<?= Security::e($formData['payment_date'] ?? date('Y-m-d')) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="amount" class="form-label" style="font-weight: 700; color: #1e293b;">
                                Payment Amount Received (₹) <span style="color: #dc2626;">*</span>
                            </label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 10px; font-size: 1.1rem; font-weight: 700; color: #1e293b;">₹</span>
                                <input 
                                    type="number" 
                                    id="amount" 
                                    name="amount" 
                                    class="form-control" 
                                    style="padding-left: 32px; font-size: 1.15rem; font-weight: 700; color: #1e293b;" 
                                    step="0.01" 
                                    min="0.01" 
                                    max="<?= (float)$repair['due_amount'] ?>" 
                                    value="<?= Security::e($formData['amount'] ?? $repair['due_amount']) ?>" 
                                    required
                                >
                            </div>
                            <div class="help-text" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                Overpayment defense: Maximum acceptable amount is ₹ <?= number_format((float)$repair['due_amount'], 2) ?>.
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label for="payment_mode" class="form-label" style="font-weight: 600;">Payment Mode <span style="color: #dc2626;">*</span></label>
                            <select id="payment_mode" name="payment_mode" class="form-control" required>
                                <option value="CASH">CASH (Counter Cash Drawer)</option>
                                <option value="UPI">UPI / QR Code</option>
                                <option value="CARD">Debit / Credit Card</option>
                                <option value="BANK_TRANSFER">Bank Transfer / IMPS</option>
                                <option value="CHEQUE">Cheque</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="transaction_ref" class="form-label" style="font-weight: 600;">Transaction Ref / UTR / Cheque #</label>
                            <input type="text" id="transaction_ref" name="transaction_ref" class="form-control" placeholder="e.g. UPI-9876543210 or CHQ-10492">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="notes" class="form-label" style="font-weight: 600;">Payment Remark / Memo (Optional)</label>
                        <input type="text" id="notes" name="notes" class="form-control" placeholder="e.g. Settled balance via PhonePe at counter">
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: 700;">
                            <span>&#10003;</span> Confirm &amp; Record Receipt
                        </button>
                        <a href="/repairs/<?= (int)$repair['id'] ?>" class="btn btn-outline" style="padding: 10px 16px;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('payment-form').addEventListener('submit', function(e) {
        const maxDue = <?= (float)$repair['due_amount'] ?>;
        const amtInput = document.getElementById('amount');
        const amt = parseFloat(amtInput.value) || 0.00;

        if (amt <= 0.00) {
            e.preventDefault();
            alert('Payment amount must be greater than ₹0.00.');
            return false;
        }

        if (amt > maxDue) {
            e.preventDefault();
            alert('Overpayment rejected: Entered amount (₹' + amt.toFixed(2) + ') exceeds current outstanding due (₹' + maxDue.toFixed(2) + ').');
            return false;
        }
    });
</script>
