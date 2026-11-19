<?php
/**
 * Customer Return Detail View
 * File: views/returns/show.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/returns" class="btn btn-outline btn-sm">&larr; Back to Returns</a>
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                        <?= Security::e($return['return_number']) ?>
                    </h1>
                    <span class="badge" style="background: #eff6ff; color: #1e40af; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                        CUSTOMER RETURN
                    </span>
                </div>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 4px 0 0 0;">
                    Processed on <?= Security::e($return['return_date']) ?> at <?= Security::e(substr($return['return_time'], 0, 5)) ?> &bull; Logged by <?= Security::e($return['created_by_name']) ?>
                </p>
            </div>
        </div>

        <div style="display: flex; gap: 8px;">
            <a href="/sales/<?= (int)$return['sale_id'] ?>" class="btn btn-outline">
                <span>&#128722;</span> View Original Sale (<?= Security::e($return['sale_number']) ?>)
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-3" style="gap: 24px; align-items: start;">
    <!-- Left 2 Columns: Return Summary & Returned Items -->
    <div style="grid-column: span 2;">
        
        <!-- Transaction Details Card -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                <div class="card-title">
                    <span>&#128100;</span> Customer &amp; Transaction Details
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-3" style="gap: 16px; margin-bottom: 16px;">
                    <div>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Customer</span>
                        <div style="font-weight: 700; color: #1e293b; font-size: 1rem;">
                            <?= !empty($return['customer_name']) ? Security::e($return['customer_name']) : 'Walk-in Customer' ?>
                        </div>
                        <?php if (!empty($return['customer_phone'])): ?>
                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?= Security::e($return['customer_phone']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Original Sale Reference</span>
                        <div style="font-weight: 700; color: var(--primary);">
                            <a href="/sales/<?= (int)$return['sale_id'] ?>" style="text-decoration: none;">
                                <?= Security::e($return['sale_number']) ?>
                            </a>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Sale Date: <?= Security::e($return['sale_date']) ?></div>
                    </div>
                    <div>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Refund Settlement Mode</span>
                        <div style="font-weight: 700; color: #1e293b;">
                            <?= Security::e($return['refund_payment_mode']) ?>
                        </div>
                    </div>
                </div>

                <div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Return Reason</span>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-top: 4px; font-size: 0.95rem; color: #334155;">
                        <?= Security::e($return['reason']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Returned Items Table Card -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                <div class="card-title">
                    <span>&#128230;</span> Returned Items Ledger
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); text-align: left;">
                                <th style="padding: 12px 16px;">Product / Handset</th>
                                <th style="padding: 12px 16px; text-align: center;">Returned Qty</th>
                                <th style="padding: 12px 16px; text-align: right;">Unit Refund Price</th>
                                <th style="padding: 12px 16px; text-align: right;">Subtotal Refund</th>
                                <th style="padding: 12px 16px;">Disposition</th>
                                <?php if ($canViewCosts): ?>
                                    <th style="padding: 12px 16px; text-align: right;">COGS Reversal</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 12px 16px;">
                                        <div style="font-weight: 600; color: var(--text-main);">
                                            <?= Security::e($it['product_name'] ?? ($it['brand'] . ' ' . $it['model_name'])) ?>
                                        </div>
                                        <?php if (!empty($it['phone_id'])): ?>
                                            <div style="font-size: 0.75rem; color: #4338ca; font-weight: 600;">
                                                [PHONE] IMEI: <?= Security::e($it['imei_1']) ?> &bull; Status: <?= Security::e($it['phone_status']) ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                [ACCESSORY] <?= !empty($it['sku']) ? 'SKU: ' . Security::e($it['sku']) : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: center; font-weight: 700;">
                                        <?= (int)$it['quantity'] ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right;">
                                        ₹ <?= number_format((float)$it['refund_unit_price'], 2) ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: #b45309;">
                                        ₹ <?= number_format((float)$it['subtotal_refund'], 2) ?>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <?php if ($it['restock_condition'] === 'RESTOCKED_GOOD'): ?>
                                            <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.75rem; padding: 2px 6px;">
                                                RESTOCKED (Good)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.75rem; padding: 2px 6px;">
                                                SCRAPPED (Defective)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($canViewCosts): ?>
                                        <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: <?= (float)$it['cogs_reversed'] > 0 ? '#059669' : '#94a3b8' ?>;">
                                            ₹ <?= number_format((float)$it['cogs_reversed'], 2) ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Cash / Payment Refunds Ledger Card -->
        <?php if (!empty($refundPayments)): ?>
            <div class="card">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128179;</span> Cash / Payment Outflow Records
                    </div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); text-align: left;">
                                    <th style="padding: 10px 16px;">Refund Voucher #</th>
                                    <th style="padding: 10px 16px;">Date</th>
                                    <th style="padding: 10px 16px;">Payment Mode</th>
                                    <th style="padding: 10px 16px;">Notes</th>
                                    <th style="padding: 10px 16px;">Processed By</th>
                                    <th style="padding: 10px 16px; text-align: right;">Amount Paid Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($refundPayments as $rp): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 10px 16px; font-weight: 700; font-family: var(--font-mono);">
                                            <?= Security::e($rp['payment_number']) ?>
                                        </td>
                                        <td style="padding: 10px 16px; color: var(--text-muted);">
                                            <?= Security::e($rp['payment_date']) ?>
                                        </td>
                                        <td style="padding: 10px 16px;">
                                            <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 2px 6px;">
                                                <?= Security::e($rp['payment_mode']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px 16px; font-size: 0.8rem; color: var(--text-muted);">
                                            <?= Security::e($rp['notes'] ?? '—') ?>
                                        </td>
                                        <td style="padding: 10px 16px; color: var(--text-muted);">
                                            <?= Security::e($rp['processed_by_name'] ?? 'Staff') ?>
                                        </td>
                                        <td style="padding: 10px 16px; text-align: right; font-weight: 700; color: #dc2626;">
                                            - ₹ <?= number_format((float)$rp['amount'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Right Column: Financial Breakdown -->
    <div>
        <div class="card" style="position: sticky; top: 20px;">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                <div class="card-title">
                    <span>&#128176;</span> Reversal Accounting Summary
                </div>
            </div>
            <div class="card-body">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="font-size: 0.9rem; font-weight: 600; color: #475569;">Total Refund:</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: #b45309;">
                        ₹ <?= number_format((float)$return['total_refund_amount'], 2) ?>
                    </span>
                </div>

                <?php if ($canViewCosts): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.85rem;">
                            <span style="color: #059669;">Restocked COGS:</span>
                            <span style="font-weight: 700; color: #059669;">+ ₹ <?= number_format((float)$return['total_restocked_cogs'], 2) ?></span>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 8px;">
                            (Removed from realized cost of goods sold)
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.85rem;">
                            <span style="color: #dc2626;">Scrapped COGS:</span>
                            <span style="font-weight: 700; color: #dc2626;">₹ <?= number_format((float)$return['total_scrapped_cogs'], 2) ?></span>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            (Retained as absorbed business loss)
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($canViewProfit): ?>
                    <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.85rem; font-weight: 700; color: #9f1239; text-transform: uppercase;">Profit Reversed</span>
                            <span style="font-size: 1.15rem; font-weight: 800; color: #be123c;">
                                - ₹ <?= number_format((float)$return['net_profit_reversed'], 2) ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
