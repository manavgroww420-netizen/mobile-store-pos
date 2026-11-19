<?php
/**
 * Customer Sales Return Entry Form
 * File: views/returns/create.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/sales/<?= (int)$sale['id'] ?>" class="btn btn-outline btn-sm">&larr; Back to Sale</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    Process Customer Return
                </h1>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 4px 0 0 0;">
                    Initiate item return, stock restocking / scrapping, and financial reversals for Sale <?= Security::e($sale['sale_number']) ?>.
                </p>
            </div>
        </div>
        <div style="font-size: 0.8rem; background: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 6px 14px; border-radius: 6px; font-weight: 600;">
            <span>&#9888;</span> Formal Return Ledger &bull; Strict P&amp;L Reversal
        </div>
    </div>
</div>

<form id="return-form" action="/sales/<?= (int)$sale['id'] ?>/return" method="POST">
    <?= Security::csrfField() ?>

    <div class="grid grid-cols-3" style="gap: 24px; align-items: start;">
        <!-- Left 2 Columns: Items to Return & Return Details -->
        <div style="grid-column: span 2;">
            
            <!-- Original Sale Summary Card -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128100;</span> Original Transaction &amp; Customer Overview
                    </div>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-3" style="gap: 16px;">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Customer</span>
                            <div style="font-weight: 700; color: #1e293b;">
                                <?= !empty($sale['customer_name']) ? Security::e($sale['customer_name']) : 'Walk-in Customer' ?>
                            </div>
                            <?php if (!empty($sale['customer_phone'])): ?>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= Security::e($sale['customer_phone']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Original Billed Total</span>
                            <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem;">
                                ₹ <?= number_format((float)$sale['final_total_amount'], 2) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Paid: ₹ <?= number_format((float)$sale['paid_amount'], 2) ?></div>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Outstanding Credit (Udhar)</span>
                            <div style="font-weight: 700; color: <?= (float)$sale['due_amount'] > 0 ? '#dc2626' : '#059669' ?>; font-size: 1.1rem;">
                                ₹ <?= number_format((float)$sale['due_amount'], 2) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                <?= (float)$sale['due_amount'] > 0 ? 'Refund will offset this first' : 'Eligible for direct payout' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Return Table Card -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128230;</span> Select Items to Return
                    </div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;" id="return-items-table">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); text-align: left;">
                                    <th style="padding: 12px 16px;">Product / Item</th>
                                    <th style="padding: 12px 16px; text-align: center;">Sold Qty</th>
                                    <th style="padding: 12px 16px; text-align: right;">Net Unit Price</th>
                                    <th style="padding: 12px 16px; text-align: center; width: 110px;">Return Qty</th>
                                    <th style="padding: 12px 16px; width: 170px;">Disposition</th>
                                    <th style="padding: 12px 16px; text-align: right; width: 120px;">Refund Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $it): ?>
                                    <?php 
                                        $unitNet = round((float)$it['net_sale_price'] / (int)$it['quantity'], 2);
                                        $returnable = (int)$it['returnable_quantity'];
                                        $isPhone = ($it['item_type'] === 'SERIALIZED_PHONE');
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--border-color); <?= $returnable === 0 ? 'opacity: 0.5; background: #f8fafc;' : '' ?>">
                                        <td style="padding: 12px 16px;">
                                            <div style="font-weight: 600; color: var(--text-main);">
                                                <?= Security::e($it['product_name'] ?? ($it['brand'] . ' ' . $it['model_name'])) ?>
                                            </div>
                                            <?php if ($isPhone): ?>
                                                <div style="font-size: 0.75rem; color: #4338ca; font-weight: 600;">
                                                    [PHONE] IMEI: <?= Security::e($it['imei_1']) ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                    [ACCESSORY] <?= !empty($it['sku']) ? 'SKU: ' . Security::e($it['sku']) : '' ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <?= (int)$it['quantity'] ?>
                                            <?php if ((int)$it['returned_quantity'] > 0): ?>
                                                <div style="font-size: 0.75rem; color: #dc2626;">(<?= (int)$it['returned_quantity'] ?> returned)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: right; font-weight: 600;">
                                            ₹ <?= number_format($unitNet, 2) ?>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <?php if ($returnable > 0): ?>
                                                <input 
                                                    type="number" 
                                                    name="return_qty[<?= (int)$it['id'] ?>]" 
                                                    class="form-control return-qty-input" 
                                                    data-id="<?= (int)$it['id'] ?>"
                                                    data-unit-price="<?= $unitNet ?>"
                                                    data-unit-cost="<?= (float)$it['unit_cost'] ?>"
                                                    data-is-phone="<?= $isPhone ? '1' : '0' ?>"
                                                    min="0" 
                                                    max="<?= $returnable ?>" 
                                                    value="0"
                                                    style="width: 80px; margin: 0 auto; text-align: center;"
                                                    oninput="recalcReturnTotals()"
                                                >
                                            <?php else: ?>
                                                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Fully Returned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 16px;">
                                            <?php if ($returnable > 0): ?>
                                                <select 
                                                    name="restock_condition[<?= (int)$it['id'] ?>]" 
                                                    class="form-control return-condition-select" 
                                                    data-id="<?= (int)$it['id'] ?>"
                                                    style="font-size: 0.8rem;"
                                                    onchange="recalcReturnTotals()"
                                                >
                                                    <option value="RESTOCKED_GOOD">RESTOCKED (Good / Working)</option>
                                                    <option value="DEFECTIVE_SCRAP">SCRAP (Dead / Defective)</option>
                                                </select>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: #b45309;" id="line-refund-<?= (int)$it['id'] ?>">
                                            ₹ 0.00
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Return Metadata Card -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128221;</span> Return Details &amp; Reason
                    </div>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label for="return_date" class="form-label" style="font-weight: 600;">Return Date <span style="color: #dc2626;">*</span></label>
                            <input 
                                type="date" 
                                id="return_date" 
                                name="return_date" 
                                class="form-control" 
                                required 
                                value="<?= date('Y-m-d') ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label for="refund_payment_mode" class="form-label" style="font-weight: 600;">Payout / Refund Mode</label>
                            <select id="refund_payment_mode" name="refund_payment_mode" class="form-control">
                                <option value="CASH">CASH (Physical Cash Drawer)</option>
                                <option value="UPI">UPI / QR Refund</option>
                                <option value="BANK_TRANSFER">Bank Transfer / IMPS</option>
                                <option value="STORE_CREDIT">Store Credit (No Immediate Cash Outflow)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reason" class="form-label" style="font-weight: 600;">Reason for Return <span style="color: #dc2626;">*</span></label>
                        <input 
                            type="text" 
                            id="reason" 
                            name="reason" 
                            class="form-control" 
                            placeholder="e.g. Customer changed mind / wrong model selected / defective speaker" 
                            required
                        >
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Financial Impact & Reversals -->
        <div>
            <div class="card" style="position: sticky; top: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128176;</span> Financial &amp; P&amp;L Reversal
                    </div>
                </div>
                <div class="card-body">
                    
                    <!-- Reversal Summary Box -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 16px;">
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.85rem;">
                            <span style="color: var(--text-muted);">Total Refund Amount:</span>
                            <span id="disp-total-refund" style="font-weight: 700; color: #b45309; font-size: 1rem;">₹ 0.00</span>
                        </div>

                        <div style="border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 4px;">
                                <span style="color: #475569;">Offset Against Open Credit:</span>
                                <span id="disp-credit-offset" style="font-weight: 600; color: #dc2626;">₹ 0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                                <span style="color: #475569;">Cash / UPI Payout to Customer:</span>
                                <span id="disp-cash-refund" style="font-weight: 700; color: #059669;">₹ 0.00</span>
                            </div>
                        </div>

                        <?php if ($canViewCosts): ?>
                            <div style="border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-bottom: 8px; font-size: 0.85rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="color: #059669;">Restocked COGS (Returned to Stock):</span>
                                    <span id="disp-restocked-cogs" style="font-weight: 600; color: #059669;">₹ 0.00</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #dc2626;">Scrapped COGS (Loss Retained):</span>
                                    <span id="disp-scrapped-cogs" style="font-weight: 600; color: #dc2626;">₹ 0.00</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($canViewProfit): ?>
                            <div style="border-top: 2px solid #fecdd3; padding-top: 8px; font-size: 0.95rem; font-weight: 700; display: flex; justify-content: space-between;">
                                <span style="color: #be123c;">Net Profit Reversal:</span>
                                <span id="disp-profit-reversed" style="color: #be123c;">- ₹ 0.00</span>
                            </div>
                        <?php endif; ?>

                    </div>

                    <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.4; margin-bottom: 20px;">
                        <strong>Restocked Items:</strong> Restored to available inventory; COGS removed from realized sales cost.<br>
                        <strong>Scrapped Items:</strong> Removed from active inventory; cost remains recognized as damaged/scrap expense.
                    </div>

                    <button type="submit" id="btn-submit-return" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; font-weight: 700;" disabled>
                        <span>&#10003;</span> Confirm &amp; Process Return
                    </button>

                </div>
            </div>
        </div>
    </div>
</form>

<script>
    const DUE_AMOUNT = <?= (float)$sale['due_amount'] ?>;

    function formatINR(val) {
        const num = parseFloat(val) || 0.00;
        return '₹ ' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcReturnTotals() {
        let totalRefund = 0.00;
        let totalRestockedCogs = 0.00;
        let totalScrappedCogs = 0.00;
        let totalProfitReversed = 0.00;
        let anyItemSelected = false;

        const inputs = document.querySelectorAll('.return-qty-input');
        inputs.forEach(inp => {
            const id = inp.dataset.id;
            const unitPrice = parseFloat(inp.dataset.unitPrice) || 0.00;
            const unitCost = parseFloat(inp.dataset.unitCost) || 0.00;
            const isPhone = inp.dataset.isPhone === '1';
            let qty = parseInt(inp.value) || 0;
            const max = parseInt(inp.max) || 0;

            if (qty > max) {
                qty = max;
                inp.value = max;
            }
            if (qty < 0) {
                qty = 0;
                inp.value = 0;
            }

            // Phones can only be 0 or 1
            if (isPhone && qty > 1) {
                qty = 1;
                inp.value = 1;
            }

            const subtotalRefund = qty * unitPrice;
            const subtotalCost = qty * unitCost;
            const subtotalProfit = subtotalRefund - subtotalCost;

            const lineDisp = document.getElementById('line-refund-' + id);
            if (lineDisp) {
                lineDisp.textContent = formatINR(subtotalRefund);
            }

            if (qty > 0) {
                anyItemSelected = true;
                totalRefund += subtotalRefund;
                totalProfitReversed += subtotalProfit;

                const condSelect = document.querySelector(`.return-condition-select[data-id="${id}"]`);
                const cond = condSelect ? condSelect.value : 'RESTOCKED_GOOD';

                if (cond === 'RESTOCKED_GOOD') {
                    totalRestockedCogs += subtotalCost;
                } else {
                    totalScrappedCogs += subtotalCost;
                }
            }
        });

        // Credit offset vs cash payout
        let creditOffset = 0.00;
        let cashRefund = 0.00;

        if (DUE_AMOUNT > 0.00) {
            if (totalRefund <= DUE_AMOUNT) {
                creditOffset = totalRefund;
                cashRefund = 0.00;
            } else {
                creditOffset = DUE_AMOUNT;
                cashRefund = totalRefund - creditOffset;
            }
        } else {
            cashRefund = totalRefund;
        }

        document.getElementById('disp-total-refund').textContent = formatINR(totalRefund);
        document.getElementById('disp-credit-offset').textContent = formatINR(creditOffset);
        document.getElementById('disp-cash-refund').textContent = formatINR(cashRefund);

        const restockedCogsDisp = document.getElementById('disp-restocked-cogs');
        if (restockedCogsDisp) restockedCogsDisp.textContent = formatINR(totalRestockedCogs);

        const scrappedCogsDisp = document.getElementById('disp-scrapped-cogs');
        if (scrappedCogsDisp) scrappedCogsDisp.textContent = formatINR(totalScrappedCogs);

        const profitRevDisp = document.getElementById('disp-profit-reversed');
        if (profitRevDisp) profitRevDisp.textContent = '- ' + formatINR(totalProfitReversed);

        document.getElementById('btn-submit-return').disabled = !anyItemSelected;
    }

    document.addEventListener('DOMContentLoaded', recalcReturnTotals);
</script>
