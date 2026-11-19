<?php
/**
 * Vendor Payables Repayment / Settlement Form
 * File: views/ledgers/vendor_settle.php
 */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/ledgers/vendors">Vendor Payables</a></li>
                <li class="breadcrumb-item"><a href="/ledgers/vendors/<?= $vendor['id'] ?>"><?= htmlspecialchars($vendor['business_name']) ?></a></li>
                <li class="breadcrumb-item active">Record Payment</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Record Supplier Payment</h1>
    </div>
    <div>
        <a href="/ledgers/vendors/<?= $vendor['id'] ?>" class="btn btn-outline-secondary">Back to Statement</a>
    </div>
</div>

<div class="row g-4 justify-content-center">
    <div class="col-lg-8">
        <!-- Balance Notice Card -->
        <div class="card shadow-sm border-left-danger mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 text-dark"><?= htmlspecialchars($vendor['business_name']) ?></h5>
                        <div class="text-muted small">
                            <?= !empty($vendor['contact_person']) ? htmlspecialchars($vendor['contact_person']) . ' &bull; ' : '' ?>
                            <?= !empty($vendor['phone']) ? '&#128222; ' . htmlspecialchars($vendor['phone']) : '' ?>
                            &bull; <?= count($openPurchases) ?> open consignment(s)
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-xs text-uppercase text-muted font-weight-bold">Current Total Payable Due</div>
                        <div class="h3 font-weight-bold text-danger mb-0">
                            ₹ <?= number_format((float)$summary['total_due'], 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settlement Form -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-danger">Supplier Payment Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/ledgers/vendors/<?= $vendor['id'] ?>/settle" id="settleVendorForm">
                    <input type="hidden" name="csrf_token" value="<?= \AMH\Security::getCsrfToken() ?>">

                    <!-- Allocation Strategy -->
                    <div class="mb-4">
                        <label class="form-label font-weight-bold">Settlement Target / Allocation Mode <span class="text-danger">*</span></label>
                        <div class="border rounded p-3 bg-light">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="target" id="targetFifo" value="FIFO_AUTO" checked onchange="updateAllocationNotice()">
                                <label class="form-check-label font-weight-bold text-dark" for="targetFifo">
                                    Automatic FIFO Allocation (Recommended)
                                    <div class="text-muted small font-weight-normal">
                                        Distributes payment across oldest unpaid purchase consignments first.
                                    </div>
                                </label>
                            </div>
                            <hr class="my-2">
                            <div class="small font-weight-bold text-muted mb-2">Or Pay for a Specific Consignment:</div>
                            <?php foreach ($openPurchases as $p): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input purchase-radio" type="radio" name="target" id="pur_<?= $p['id'] ?>" value="<?= $p['id'] ?>" data-max="<?= $p['due_amount'] ?>" onchange="updateAllocationNotice()">
                                    <label class="form-check-label small" for="pur_<?= $p['id'] ?>">
                                        Consignment <strong><?= htmlspecialchars($p['purchase_number']) ?></strong> (<?= $p['purchase_date'] ?>) <?= !empty($p['vendor_invoice_no']) ? '[Inv: ' . htmlspecialchars($p['vendor_invoice_no']) . ']' : '' ?> — Due: <span class="text-danger font-weight-bold">₹ <?= number_format((float)$p['due_amount'], 2) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Payment Details Row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="payment_date" class="form-label font-weight-bold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?= htmlspecialchars($formData['payment_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="payment_mode" class="form-label font-weight-bold">Payment Mode <span class="text-danger">*</span></label>
                            <select name="payment_mode" id="payment_mode" class="form-select" required>
                                <option value="BANK_TRANSFER" <?= $formData['payment_mode'] === 'BANK_TRANSFER' ? 'selected' : '' ?>>NEFT / RTGS / IMPS Bank Transfer</option>
                                <option value="UPI" <?= $formData['payment_mode'] === 'UPI' ? 'selected' : '' ?>>UPI / QR Transfer</option>
                                <option value="CHEQUE" <?= $formData['payment_mode'] === 'CHEQUE' ? 'selected' : '' ?>>Bank Cheque</option>
                                <option value="CASH" <?= $formData['payment_mode'] === 'CASH' ? 'selected' : '' ?>>Cash Payout</option>
                                <option value="CARD" <?= $formData['payment_mode'] === 'CARD' ? 'selected' : '' ?>>Debit / Corporate Card</option>
                            </select>
                        </div>
                    </div>

                    <!-- Amount with Quick Buttons -->
                    <div class="mb-3">
                        <label for="amount" class="form-label font-weight-bold d-flex justify-content-between">
                            <span>Payment Amount (₹) <span class="text-danger">*</span></span>
                            <span class="text-muted small" id="maxDueLabel">Max allowed: ₹ <?= number_format((float)$summary['total_due'], 2) ?></span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text font-weight-bold">₹</span>
                            <input type="number" step="0.01" min="0.01" max="<?= $summary['total_due'] ?>" name="amount" id="amount" class="form-control font-weight-bold" value="<?= htmlspecialchars($formData['amount']) ?>" required>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="setAmount(<?= (float)$summary['total_due'] ?>)">Pay Full (₹ <?= number_format((float)$summary['total_due'], 2) ?>)</button>
                            <?php if ((float)$summary['total_due'] >= 5000): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAmount(5000)">₹ 5,000</button>
                            <?php endif; ?>
                            <?php if ((float)$summary['total_due'] >= 10000): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAmount(10000)">₹ 10,000</button>
                            <?php endif; ?>
                            <?php if ((float)$summary['total_due'] >= 25000): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAmount(25000)">₹ 25,000</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reference & Notes -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="transaction_ref" class="form-label font-weight-bold">Bank Reference / Cheque # / UTR</label>
                            <input type="text" name="transaction_ref" id="transaction_ref" class="form-control" placeholder="UTR Number, Cheque Number, UPI Txn ID">
                        </div>
                        <div class="col-md-6">
                            <label for="notes" class="form-label font-weight-bold">Notes / Description</label>
                            <input type="text" name="notes" id="notes" class="form-control" placeholder="Optional notes for supplier ledger">
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="/ledgers/vendors/<?= $vendor['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger px-4 font-weight-bold">
                            <span>&#10004;</span> Confirm Supplier Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const TOTAL_DUE = <?= (float)$summary['total_due'] ?>;

function setAmount(val) {
    const amountInput = document.getElementById('amount');
    const maxVal = parseFloat(amountInput.getAttribute('max')) || TOTAL_DUE;
    amountInput.value = Math.min(val, maxVal).toFixed(2);
}

function updateAllocationNotice() {
    const fifoRadio = document.getElementById('targetFifo');
    const amountInput = document.getElementById('amount');
    const maxDueLabel = document.getElementById('maxDueLabel');

    if (fifoRadio.checked) {
        amountInput.setAttribute('max', TOTAL_DUE.toFixed(2));
        maxDueLabel.innerText = "Max allowed: ₹ " + TOTAL_DUE.toFixed(2);
    } else {
        const checkedPurchase = document.querySelector('.purchase-radio:checked');
        if (checkedPurchase) {
            const purchaseMax = parseFloat(checkedPurchase.getAttribute('data-max')) || TOTAL_DUE;
            amountInput.setAttribute('max', purchaseMax.toFixed(2));
            maxDueLabel.innerText = "Max for selected consignment: ₹ " + purchaseMax.toFixed(2);
            if (parseFloat(amountInput.value) > purchaseMax) {
                amountInput.value = purchaseMax.toFixed(2);
            }
        }
    }
}
</script>
