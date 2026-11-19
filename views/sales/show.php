<?php
/**
 * Internal Counter Sale Detail View
 * File: views/sales/show.php
 *
 * NOTE: Strictly an internal business record. NOT a tax invoice.
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/sales" class="btn btn-outline btn-sm">&larr; Back to Sales</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    <?= Security::e($sale['sale_number']) ?>
                    <span style="font-size: 0.95rem; font-weight: 600; color: var(--text-muted); font-family: var(--font-mono);">
                        (Record #<?= (int)$sale['id'] ?>)
                    </span>
                </h1>
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px; flex-wrap: wrap;">
                    <?php if ((int)$sale['is_voided'] === 1): ?>
                        <span class="badge" style="background: #475569; color: #f8fafc; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">ADMINISTRATIVELY VOIDED</span>
                    <?php elseif ($sale['payment_status'] === 'PAID'): ?>
                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">FULLY SETTLED</span>
                    <?php elseif ($sale['payment_status'] === 'PARTIAL'): ?>
                        <span class="badge" style="background: #fffbeb; color: #b45309; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">PARTIAL CREDIT (UDHAR)</span>
                    <?php else: ?>
                        <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">UNPAID CREDIT (UDHAR)</span>
                    <?php endif; ?>

                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        &bull; Date: <?= Security::e($sale['sale_date']) ?> <?= Security::e(substr($sale['sale_time'], 0, 5)) ?>
                    </span>

                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        &bull; Cashier: <strong><?= Security::e($sale['cashier_username'] ?? 'Staff') ?></strong>
                    </span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-primary" onclick="window.print();" title="Print Customer Slip / Thermal Receipt">
                <span>&#128424;</span> Print Receipt
            </button>
            <?php if ((int)$sale['is_voided'] === 0): ?>
                <?php if ($canPerformSales && (float)$sale['due_amount'] > 0): ?>
                    <a href="/sales/<?= (int)$sale['id'] ?>/payments/create" class="btn btn-secondary">
                        <span>&#128179;</span> Record Payment Receipt
                    </a>
                <?php endif; ?>

                <?php if ($canPerformSales): ?>
                    <a href="/sales/<?= (int)$sale['id'] ?>/return" class="btn btn-outline" style="border-color: #f59e0b; color: #b45309;">
                        <span>&#8617;</span> Process Return
                    </a>
                <?php endif; ?>

                <?php if (AMH\Authorization::hasPermission('void_transactions') || AMH\Auth::isOwner()): ?>
                    <button type="button" class="btn btn-outline" style="border-color: #ef4444; color: #dc2626;" onclick="document.getElementById('void-modal').style.display='flex';">
                        <span>&#10006;</span> Void Sale
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-4" style="gap: 16px; margin-bottom: 24px;">
    <div class="metric-card">
        <div class="metric-icon blue">&#128722;</div>
        <div class="metric-data">
            <div class="metric-label">Sale Total Amount</div>
            <div class="metric-value">₹ <?= number_format((float)$sale['final_total_amount'], 2) ?></div>
            <div class="metric-sub">
                <?php if ((float)$sale['discount_amount'] > 0): ?>
                    Discount: -₹ <?= number_format((float)$sale['discount_amount'], 2) ?> (Subtotal: ₹<?= number_format((float)$sale['subtotal_amount'], 2) ?>)
                <?php else: ?>
                    <?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?> in transaction
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon green">&#9989;</div>
        <div class="metric-data">
            <div class="metric-label">Amount Paid / Received</div>
            <div class="metric-value">₹ <?= number_format((float)$sale['paid_amount'], 2) ?></div>
            <div class="metric-sub"><?= count($payments) ?> payment settlements</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon amber">&#9888;</div>
        <div class="metric-data">
            <div class="metric-label">Outstanding Balance (Udhar)</div>
            <div class="metric-value" style="color: <?= (float)$sale['due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">
                ₹ <?= number_format((float)$sale['due_amount'], 2) ?>
            </div>
            <div class="metric-sub">Receivable customer credit</div>
        </div>
    </div>

    <?php if ($canViewProfits): ?>
        <div class="metric-card">
            <div class="metric-icon <?= (float)$sale['gross_profit'] >= 0 ? 'green' : 'amber' ?>">&#128200;</div>
            <div class="metric-data">
                <div class="metric-label">Realized Gross Profit</div>
                <div class="metric-value" style="color: <?= (float)$sale['gross_profit'] >= 0 ? '#15803d' : '#dc2626' ?>;">
                    ₹ <?= number_format((float)$sale['gross_profit'], 2) ?>
                </div>
                <div class="metric-sub">
                    Margin: <?= (float)$sale['final_total_amount'] > 0 ? number_format(((float)$sale['gross_profit'] / (float)$sale['final_total_amount']) * 100, 1) . '%' : '0.0%' ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="metric-card">
            <div class="metric-icon blue">&#128203;</div>
            <div class="metric-data">
                <div class="metric-label">Settlement Status</div>
                <div class="metric-value" style="font-size: 1.25rem;">
                    <?= Security::e($sale['payment_status']) ?>
                </div>
                <div class="metric-sub">Internal counter sale</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Customer Information Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
        <div class="card-title">
            <span>&#128100;</span> Customer &amp; Transaction Details
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-3" style="gap: 20px;">
            <div>
                <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600;">Customer Name</div>
                <div style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-top: 4px;">
                    <?= !empty($sale['customer_name']) ? Security::e($sale['customer_name']) : '<span style="color: var(--text-muted); font-style: italic;">Walk-in Customer</span>' ?>
                </div>
            </div>

            <div>
                <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600;">Customer Phone Number</div>
                <div style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-top: 4px; font-family: var(--font-mono);">
                    <?= !empty($sale['customer_phone']) ? '&#128222; ' . Security::e($sale['customer_phone']) : '<span style="color: var(--text-muted); font-style: italic;">None recorded</span>' ?>
                </div>
            </div>

            <div>
                <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600;">Counter Remarks</div>
                <div style="font-size: 0.875rem; color: var(--text-main); margin-top: 4px;">
                    <?= !empty($sale['notes']) ? Security::e($sale['notes']) : '<span style="color: var(--text-muted); font-style: italic;">None</span>' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Itemized Sale Lines Table -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
        <div class="card-title">
            <span>&#128221;</span> Itemized Sold Inventory Lines (<?= count($items) ?> items)
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); text-align: left;">
                        <th style="padding: 10px 16px; width: 40px;">#</th>
                        <th style="padding: 10px 16px; width: 110px;">Type</th>
                        <th style="padding: 10px 16px;">Product &amp; Serial / Episode Details</th>
                        <th style="padding: 10px 16px; text-align: center; width: 70px;">Qty</th>
                        <th style="padding: 10px 16px; text-align: right; width: 120px;">Unit Price</th>
                        <th style="padding: 10px 16px; text-align: right; width: 120px;">Line Total</th>
                        <?php if ((float)$sale['discount_amount'] > 0): ?>
                            <th style="padding: 10px 16px; text-align: right; width: 120px;">Discount</th>
                            <th style="padding: 10px 16px; text-align: right; width: 120px;">Net Sale</th>
                        <?php endif; ?>
                        <?php if ($canViewCosts): ?>
                            <th style="padding: 10px 16px; text-align: right; width: 120px;">Unit Cost</th>
                            <th style="padding: 10px 16px; text-align: right; width: 120px;">Total Cost</th>
                        <?php endif; ?>
                        <?php if ($canViewProfits): ?>
                            <th style="padding: 10px 16px; text-align: right; width: 120px;">Profit</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $item): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px 16px; color: var(--text-muted); font-weight: 600;"><?= $idx + 1 ?></td>

                            <td style="padding: 12px 16px;">
                                <?php if ($item['item_type'] === 'SERIALIZED_PHONE'): ?>
                                    <span class="badge" style="background: #eff6ff; color: #1e40af; font-size: 0.75rem; padding: 2px 6px; font-weight: 700;">HANDSET</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 2px 6px; font-weight: 700;">ACCESSORY</span>
                                <?php endif; ?>
                            </td>

                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 700; color: var(--text-main);">
                                    <?= Security::e($item['brand']) ?> <?= Security::e($item['model_name']) ?>
                                </div>
                                <?php if ($item['item_type'] === 'SERIALIZED_PHONE'): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono); margin-top: 2px;">
                                        IMEI 1: <strong><?= Security::e($item['imei_1'] ?? 'N/A') ?></strong>
                                        <?php if (!empty($item['imei_2'])): ?>
                                            &bull; IMEI 2: <?= Security::e($item['imei_2']) ?>
                                        <?php endif; ?>
                                        (Episode #<?= (int)$item['phone_id'] ?>, <?= Security::e($item['phone_type']) ?>)
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td style="padding: 12px 16px; text-align: center; font-weight: 600;">
                                <?= (int)$item['quantity'] ?>
                            </td>

                            <td style="padding: 12px 16px; text-align: right; font-weight: 600;">
                                ₹ <?= number_format((float)$item['unit_sale_price'], 2) ?>
                            </td>

                            <td style="padding: 12px 16px; text-align: right; font-weight: 700;">
                                ₹ <?= number_format((float)$item['line_subtotal_price'], 2) ?>
                            </td>

                            <?php if ((float)$sale['discount_amount'] > 0): ?>
                                <td style="padding: 12px 16px; text-align: right; color: #b45309; font-size: 0.85rem;">
                                    -₹ <?= number_format((float)$item['discount_allocated'], 2) ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: var(--primary);">
                                    ₹ <?= number_format((float)$item['net_sale_price'], 2) ?>
                                </td>
                            <?php endif; ?>

                            <?php if ($canViewCosts): ?>
                                <td style="padding: 12px 16px; text-align: right; color: var(--text-muted);">
                                    ₹ <?= number_format((float)$item['unit_cost'], 2) ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; color: var(--text-muted); font-weight: 600;">
                                    ₹ <?= number_format((float)$item['subtotal_cost'], 2) ?>
                                </td>
                            <?php endif; ?>

                            <?php if ($canViewProfits): ?>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: <?= (float)$item['item_profit'] >= 0 ? '#15803d' : '#dc2626' ?>;">
                                    ₹ <?= number_format((float)$item['item_profit'], 2) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Receipts Table -->
<div class="card">
    <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <div class="card-title">
            <span>&#128179;</span> Payment Receipts &amp; Settlement History (<?= count($payments) ?> payments)
        </div>
        <?php if ($canPerformSales && (float)$sale['due_amount'] > 0): ?>
            <a href="/sales/<?= (int)$sale['id'] ?>/payments/create" class="btn btn-secondary btn-sm">
                <span>&#43;</span> Record Subsequent Payment
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($payments)): ?>
            <div style="padding: 30px; text-align: center; color: var(--text-muted);">
                <div>&#128179;</div>
                <div style="font-weight: 600; margin-top: 6px;">No payments recorded yet</div>
                <p style="font-size: 0.8rem; margin: 4px 0 0 0;">This sale is completely on credit (Udhar).</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); text-align: left;">
                            <th style="padding: 10px 16px;">Receipt Number</th>
                            <th style="padding: 10px 16px;">Date</th>
                            <th style="padding: 10px 16px; text-align: right;">Amount (₹)</th>
                            <th style="padding: 10px 16px;">Payment Mode</th>
                            <th style="padding: 10px 16px;">Transaction / UTR Ref</th>
                            <th style="padding: 10px 16px;">Cashier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px 16px; font-weight: 700; font-family: var(--font-mono);">
                                    <?= Security::e($p['payment_number']) ?>
                                </td>
                                <td style="padding: 10px 16px; color: var(--text-muted);">
                                    <?= Security::e($p['payment_date']) ?>
                                </td>
                                <td style="padding: 10px 16px; text-align: right; font-weight: 700; color: #059669;">
                                    ₹ <?= number_format((float)$p['amount'], 2) ?>
                                </td>
                                <td style="padding: 10px 16px;">
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 2px 6px;">
                                        <?= Security::e($p['payment_mode']) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 16px; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                    <?= !empty($p['transaction_ref']) ? Security::e($p['transaction_ref']) : '—' ?>
                                </td>
                                <td style="padding: 10px 16px; color: var(--text-muted);">
                                    <?= Security::e($p['received_by_username'] ?? 'Staff') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for Void Confirmation -->
<?php if (AMH\Authorization::hasPermission('void_transactions') || AMH\Auth::isOwner()): ?>
<div id="void-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: white; border-radius: 8px; max-width: 500px; width: 100%; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; color: #dc2626; font-size: 1.25rem; font-weight: 700;">
            Void Sale Transaction: <?= Security::e($sale['sale_number']) ?>
        </h3>
        <p style="font-size: 0.875rem; color: #475569; line-height: 1.5;">
            <strong>Warning:</strong> Voiding will invalidate this entire transaction, return all sold accessories and physical phones back to <code>IN_STOCK</code>, and clear any outstanding customer credit. This action is permanently audited.
        </p>
        <form method="POST" action="/sales/<?= (int)$sale['id'] ?>/void">
            <?= Security::csrfField() ?>
            <div class="form-group" style="margin-bottom: 16px;">
                <label for="void_reason" class="form-label" style="font-weight: 600;">Reason for Voiding <span style="color: #dc2626;">*</span></label>
                <input type="text" name="void_reason" id="void_reason" class="form-control" placeholder="e.g. Accidental double checkout entry" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('void-modal').style.display='none';">Cancel</button>
                <button type="submit" class="btn btn-danger" style="background: #dc2626; color: white;">Confirm Void</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Thermal Receipt Section for 80mm / 58mm POS Printers -->
<div id="thermal-receipt" class="thermal-receipt-container">
    <div class="thermal-header">
        <h2 class="thermal-shop-name"><?= Security::e($appName ?? 'Mobile Hub POS') ?></h2>
        <?php if (!empty($appTagline)): ?>
            <p class="thermal-tagline"><?= Security::e($appTagline) ?></p>
        <?php endif; ?>
        <?php if (!empty($appAddress)): ?>
            <p class="thermal-contact"><?= Security::e($appAddress) ?></p>
        <?php endif; ?>
        <?php if (!empty($appPhone)): ?>
            <p class="thermal-contact">Ph: <?= Security::e($appPhone) ?></p>
        <?php endif; ?>
        <div class="thermal-divider">================================</div>
        <div class="thermal-title">RETAIL SALE ESTIMATE / CASH SLIP</div>
        <div class="thermal-subtext">(Not a Tax Invoice - Composition / Unregistered Dealer)</div>
        <div class="thermal-divider">--------------------------------</div>
    </div>
    <div class="thermal-meta">
        <div><strong>Slip No:</strong> <?= Security::e($sale['sale_number']) ?></div>
        <div><strong>Date:</strong> <?= Security::e($sale['sale_date']) ?> <?= Security::e(substr($sale['sale_time'], 0, 5)) ?></div>
        <div><strong>Cashier:</strong> <?= Security::e($sale['cashier_username'] ?? 'Staff') ?></div>
        <div><strong>Customer:</strong> <?= Security::e($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
        <?php if (!empty($sale['customer_phone'])): ?>
            <div><strong>Contact:</strong> <?= Security::e($sale['customer_phone']) ?></div>
        <?php endif; ?>
    </div>
    <div class="thermal-divider">--------------------------------</div>
    <table class="thermal-table">
        <thead>
            <tr>
                <th style="text-align: left;">Item</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Rate</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td colspan="4" style="text-align: left; font-weight: bold; padding-top: 4px;">
                        <?= Security::e($it['product_name'] ?? 'Item') ?>
                        <?php if (!empty($it['imei_1'])): ?>
                            <br><small style="font-weight: normal; font-family: monospace;">IMEI: <?= Security::e($it['imei_1']) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left;">&nbsp;</td>
                    <td style="text-align: center;"><?= (int)$it['quantity'] ?></td>
                    <td style="text-align: right;">₹<?= number_format((float)$it['unit_price'], 2) ?></td>
                    <td style="text-align: right;">₹<?= number_format((float)$it['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="thermal-divider">--------------------------------</div>
    <div class="thermal-summary">
        <div class="thermal-sum-row"><span>Gross Subtotal:</span> <span>₹<?= number_format((float)$sale['subtotal_amount'], 2) ?></span></div>
        <?php if ((float)$sale['discount_amount'] > 0): ?>
            <div class="thermal-sum-row"><span>Discount:</span> <span>- ₹<?= number_format((float)$sale['discount_amount'], 2) ?></span></div>
        <?php endif; ?>
        <div class="thermal-sum-row thermal-sum-grand"><span>Net Total:</span> <span>₹<?= number_format((float)$sale['final_total_amount'], 2) ?></span></div>
        <div class="thermal-sum-row"><span>Amount Paid:</span> <span>₹<?= number_format((float)$sale['paid_amount'], 2) ?></span></div>
        <div class="thermal-sum-row"><span>Balance Due:</span> <span>₹<?= number_format((float)$sale['due_amount'], 2) ?></span></div>
        <div class="thermal-sum-row"><span>Payment Status:</span> <span><?= Security::e($sale['payment_status']) ?></span></div>
    </div>
    <div class="thermal-divider">================================</div>
    <div class="thermal-footer">
        <p>*** Terms &amp; Conditions ***</p>
        <p>1. Goods once sold cannot be returned without original receipt.</p>
        <p>2. Hardware warranty as per manufacturer / seller terms.</p>
        <p>3. Physical / water damage void any repair guarantee.</p>
        <p style="margin-top: 8px; font-weight: bold;">THANK YOU FOR YOUR VISIT!</p>
    </div>
</div>

<style>
.thermal-receipt-container {
    display: none;
}
@media print {
    body * {
        visibility: hidden;
    }
    #thermal-receipt, #thermal-receipt * {
        visibility: visible;
    }
    #thermal-receipt {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
        margin: 0 auto;
        padding: 4mm;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        line-height: 1.3;
        color: #000;
        background: #fff;
    }
    .thermal-header { text-align: center; }
    .thermal-shop-name { font-size: 16px; font-weight: bold; margin: 0 0 3px 0; text-transform: uppercase; }
    .thermal-tagline { font-size: 10px; margin: 0 0 2px 0; }
    .thermal-contact { font-size: 9px; margin: 0 0 2px 0; }
    .thermal-title { font-size: 10px; font-weight: bold; margin: 3px 0; }
    .thermal-subtext { font-size: 8px; margin-bottom: 3px; }
    .thermal-divider { text-align: center; font-size: 10px; margin: 3px 0; overflow: hidden; white-space: nowrap; }
    .thermal-meta { font-size: 10px; margin: 4px 0; }
    .thermal-table { width: 100%; border-collapse: collapse; font-size: 10px; margin: 4px 0; }
    .thermal-table th, .thermal-table td { padding: 2px 0; }
    .thermal-summary { font-size: 10px; margin: 4px 0; }
    .thermal-sum-row { display: flex; justify-content: space-between; padding: 1px 0; }
    .thermal-sum-grand { font-size: 12px; font-weight: bold; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 3px 0; margin: 3px 0; }
    .thermal-footer { text-align: center; font-size: 9px; margin-top: 6px; }
    .thermal-footer p { margin: 2px 0; }
}
</style>
