<?php
/**
 * Customer Credit Repayment / Settlement Form
 * File: views/ledgers/customer_settle.php
 */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/ledgers/customers">Udhar Khata</a></li>
                <li class="breadcrumb-item"><a href="/ledgers/customers/<?= urlencode($customer['identifier']) ?>"><?= htmlspecialchars($customer['name']) ?></a></li>
                <li class="breadcrumb-item active">Settle Balance</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Record Customer Repayment</h1>
    </div>
    <div>
        <a href="/ledgers/customers/<?= urlencode($customer['identifier']) ?>" class="btn btn-outline-secondary">Back to Statement</a>
    </div>
</div>

<div class="row g-4 justify-content-center">
    <div class="col-lg-8">
        <!-- Balance Notice Card -->
        <div class="card shadow-sm border-left-danger mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 text-dark"><?= htmlspecialchars($customer['name']) ?></h5>
                        <div class="text-muted small">
                            <?php if ($customer['phone'] !== ''): ?>
                                &#128222; <?= htmlspecialchars($customer['phone']) ?>
                            <?php else: ?>
                                <em>Walk-in / No phone registered</em>
                            <?php endif; ?>
                            &bull; <?= count($openSales) + count($openRepairs) ?> open bill(s)
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-xs text-uppercase text-muted font-weight-bold">Total Outstanding Balance</div>
                        <div class="h3 font-weight-bold text-danger mb-0">
                            ₹ <?= number_format((float)$customer['total_due'], 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settlement Form -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Repayment Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/ledgers/customers/<?= urlencode($customer['identifier']) ?>/settle" id="settleForm">
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
                                        Distributes repayment across oldest unpaid sales and repair bills first.
                                    </div>
                                </label>
                            </div>
                            <hr class="my-2">
                            <div class="small font-weight-bold text-muted mb-2">Or Settle a Specific Bill Directly:</div>
                            <?php foreach ($openSales as $s): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input bill-radio" type="radio" name="target" id="sale_<?= $s['id'] ?>" value="SALE:<?= $s['id'] ?>" data-max="<?= $s['due_amount'] ?>" onchange="updateAllocationNotice()">
                                    <label class="form-check-label small" for="sale_<?= $s['id'] ?>">
                                        Sale <strong><?= htmlspecialchars($s['sale_number']) ?></strong> (<?= $s['sale_date'] ?>) — Due: <span class="text-danger font-weight-bold">₹ <?= number_format((float)$s['due_amount'], 2) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach ($openRepairs as $r): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input bill-radio" type="radio" name="target" id="rep_<?= $r['id'] ?>" value="REPAIR:<?= $r['id'] ?>" data-max="<?= $r['due_amount'] ?>" onchange="updateAllocationNotice()">
                                    <label class="form-check-label small" for="rep_<?= $r['id'] ?>">
                                        Repair <strong><?= htmlspecialchars($r['repair_number']) ?></strong> (<?= $r['repair_date'] ?>) — Due: <span class="text-danger font-weight-bold">₹ <?= number_format((float)$r['due_amount'], 2) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Payment Details Row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="payment_date" class="form-label font-weight-bold">Repayment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?= htmlspecialchars($formData['payment_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="payment_mode" class="form-label font-weight-bold">Payment Mode <span class="text-danger">*</span></label>
                            <select name="payment_mode" id="payment_mode" class="form-select" required>
                                <option value="CASH" <?= $formData['payment_mode'] === 'CASH' ? 'selected' : '' ?>>Cash Counter Receipt</option>
                                <option value="UPI" <?= $formData['payment_mode'] === 'UPI' ? 'selected' : '' ?>>UPI / QR Code (GPay, PhonePe, Paytm)</option>
                                <option value="CARD" <?= $formData['payment_mode'] === 'CARD' ? 'selected' : '' ?>>Debit / Credit Card (POS Terminal)</option>
                                <option value="BANK_TRANSFER" <?= $formData['payment_mode'] === 'BANK_TRANSFER' ? 'selected' : '' ?>>NEFT / IMPS / Bank Transfer</option>
                                <option value="CHEQUE" <?= $formData['payment_mode'] === 'CHEQUE' ? 'selected' : '' ?>>Cheque</option>
                            </select>
                        </div>
                    </div>

                    <!-- Amount with Quick Buttons -->
                    <div class="mb-3">
                        <label for="amount" class="form-label font-weight-bold d-flex justify-content-between">
                            <span>Repayment Amount (₹) <span class="text-danger">*</span></span>
                            <span class="text-muted small" id="maxDueLabel">Max allowed: ₹ <?= number_format((float)$customer['total_due'], 2) ?></span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text font-weight-bold">₹</span>
                            <input type="number" step="0.01" min="0.01" max="<?= $customer['total_due'] ?>" name="amount" id="amount" class="form-control font-weight-bold" value="<?= htmlspecialchars($formData['amount']) ?>" required>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="setAmount(<?= (float)$customer['total_due'] ?>)">Pay Full (₹ <?= number_format((float)$customer['total_due'], 2) ?>)</button>
                            <?php if ((float)$customer['total_due'] >= 500): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAmount(500)">₹ 500</button>
                            <?php endif; ?>
                            <?php if ((float)$customer['total_due'] >= 1000): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAmount(1000)">₹ 1,000</button>
                            <?php endif; ?>
                            <?php if ((float)$customer['total_due'] >= 2000): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAmount(2000)">₹ 2,000</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reference & Notes -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="transaction_ref" class="form-label font-weight-bold">Transaction Reference / UPI Ref</label>
                            <input type="text" name="transaction_ref" id="transaction_ref" class="form-control" placeholder="UPI Txn ID, Card Slip #, Cheque #">
                        </div>
                        <div class="col-md-6">
                            <label for="notes" class="form-label font-weight-bold">Notes / Remarks</label>
                            <input type="text" name="notes" id="notes" class="form-control" placeholder="Optional notes for passbook">
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="/ledgers/customers/<?= urlencode($customer['identifier']) ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 font-weight-bold">
                            <span>&#10004;</span> Confirm Repayment Receipt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const TOTAL_DUE = <?= (float)$customer['total_due'] ?>;

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
        const checkedBill = document.querySelector('.bill-radio:checked');
        if (checkedBill) {
            const billMax = parseFloat(checkedBill.getAttribute('data-max')) || TOTAL_DUE;
            amountInput.setAttribute('max', billMax.toFixed(2));
            maxDueLabel.innerText = "Max for selected bill: ₹ " + billMax.toFixed(2);
            if (parseFloat(amountInput.value) > billMax) {
                amountInput.value = billMax.toFixed(2);
            }
        }
    }
}
</script>
