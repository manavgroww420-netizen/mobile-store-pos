<?php
/**
 * Repair Record Detail View
 * File: views/repairs/show.php
 */
use AMH\Security;
use AMH\Financial;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/repairs" class="btn btn-outline btn-sm">&larr; Back to Repairs</a>
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                        <?= Security::e($repair['repair_number']) ?>
                    </h1>
                    <?php if ($repair['repair_type'] === 'CUSTOMER_SERVICE'): ?>
                        <span class="badge" style="background: #e0e7ff; color: #3730a3; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                            CUSTOMER SERVICE
                        </span>
                    <?php else: ?>
                        <span class="badge" style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                            REFURBISHMENT
                        </span>
                    <?php endif; ?>

                    <?php if ($repair['payment_status'] === 'PAID'): ?>
                        <span class="badge" style="background: #dcfce7; color: #15803d; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">PAID</span>
                    <?php elseif ($repair['payment_status'] === 'PARTIAL'): ?>
                        <span class="badge" style="background: #fef3c7; color: #b45309; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">PARTIAL</span>
                    <?php else: ?>
                        <span class="badge" style="background: #fee2e2; color: #b91c1c; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">UNPAID</span>
                    <?php endif; ?>
                </div>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 4px 0 0 0;">
                    Job Date: <?= Security::e($repair['repair_date']) ?> &bull; Logged by <?= Security::e($repair['created_by_name']) ?>
                </p>
            </div>
        </div>

        <div style="display: flex; gap: 8px;">
            <?php if ($repair['repair_type'] === 'CUSTOMER_SERVICE' && (float)$repair['due_amount'] > 0.00 && $canManage): ?>
                <a href="/repairs/<?= (int)$repair['id'] ?>/payments/create" class="btn btn-primary">
                    <span>&#128176;</span> Record Payment Receipt (Due: ₹ <?= number_format((float)$repair['due_amount'], 2) ?>)
                </a>
            <?php endif; ?>

            <?php if ($repair['repair_type'] === 'REFURBISHMENT' && !empty($repair['phone_id'])): ?>
                <a href="/phones/<?= (int)$repair['phone_id'] ?>" class="btn btn-outline">
                    <span>&#128241;</span> View Handset Episode
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-3" style="gap: 24px; align-items: start;">
    <!-- Left 2 Columns: Job Overview, Parts Used, Payments Ledger -->
    <div style="grid-column: span 2;">
        
        <!-- Job Overview Card -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                <div class="card-title">
                    <span>&#128295;</span> Job Overview &amp; Target Details
                </div>
            </div>
            <div class="card-body">
                <?php if ($repair['repair_type'] === 'CUSTOMER_SERVICE'): ?>
                    <div class="grid grid-cols-3" style="gap: 16px; margin-bottom: 16px;">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Customer Name</span>
                            <div style="font-weight: 700; color: #1e293b; font-size: 1rem;"><?= Security::e($repair['customer_name'] ?? '—') ?></div>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Phone Number</span>
                            <div style="font-weight: 600; color: #1e293b;"><?= Security::e($repair['customer_phone'] ?? '—') ?></div>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Device Model</span>
                            <div style="font-weight: 600; color: #1e293b;"><?= Security::e($repair['device_model'] ?? '—') ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-3" style="gap: 16px; margin-bottom: 16px;">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Target Handset</span>
                            <div style="font-weight: 700; color: #1e293b; font-size: 1rem;">
                                <?= Security::e(($repair['phone_brand'] ?? '') . ' ' . ($repair['phone_model'] ?? '')) ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Phone #<?= (int)$repair['phone_id'] ?></div>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">IMEI 1</span>
                            <div style="font-weight: 600; font-family: monospace; color: #1e293b;"><?= Security::e($repair['phone_imei'] ?? '—') ?></div>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Handset Cost Basis</span>
                            <div style="font-weight: 700; color: #2563eb;">₹ <?= number_format((float)($repair['phone_total_cost_basis'] ?? 0), 2) ?></div>
                            <div style="font-size: 0.75rem; color: #059669;">[Refurbishment Capitalized]</div>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom: 12px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Work Performed / Diagnostic Summary</span>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-top: 4px; font-size: 0.95rem; line-height: 1.5; color: #334155;">
                        <?= nl2br(Security::e($repair['repair_description'])) ?>
                    </div>
                </div>

                <?php if (!empty($repair['notes'])): ?>
                    <div>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Internal Notes</span>
                        <div style="font-size: 0.875rem; color: #64748b; margin-top: 2px;">
                            <?= Security::e($repair['notes']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Parts Consumed Ledger -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                <div class="card-title">
                    <span>&#9881;</span> Parts Consumed (Dual-Source Ledger)
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table" style="width: 100%; margin-bottom: 0;">
                        <thead>
                            <tr style="background: #f8fafc; font-size: 0.8rem; border-bottom: 1px solid var(--border-color);">
                                <th style="padding: 10px 16px;">Source</th>
                                <th style="padding: 10px 16px;">Part Name / Product Description</th>
                                <th style="padding: 10px 16px; text-align: center;">Quantity</th>
                                <?php if ($canViewCosts): ?>
                                    <th style="padding: 10px 16px; text-align: right;">Unit Cost</th>
                                    <th style="padding: 10px 16px; text-align: right;">Subtotal</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($parts)): ?>
                                <tr>
                                    <td colspan="<?= $canViewCosts ? 5 : 3 ?>" style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                        No replacement parts used for this repair. (Labour / service only).
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($parts as $p): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 10px 16px;">
                                            <?php if ($p['source_type'] === 'IN_HOUSE_STOCK'): ?>
                                                <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px;">
                                                    IN-HOUSE STOCK
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px;">
                                                    EXTERNAL MARKET
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px 16px; font-weight: 500;">
                                            <?= Security::e($p['part_name']) ?>
                                            <?php if (!empty($p['product_sku'])): ?>
                                                <span style="font-size: 0.75rem; color: var(--text-muted);">(SKU: <?= Security::e($p['product_sku']) ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px 16px; text-align: center; font-weight: 600;">
                                            <?= (int)$p['quantity'] ?>
                                        </td>
                                        <?php if ($canViewCosts): ?>
                                            <td style="padding: 10px 16px; text-align: right; color: var(--text-muted); font-size: 0.9rem;">
                                                ₹ <?= number_format((float)$p['unit_cost'], 2) ?>
                                            </td>
                                            <td style="padding: 10px 16px; text-align: right; font-weight: 600;">
                                                ₹ <?= number_format((float)$p['subtotal_cost'], 2) ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payments Ledger (for Customer Service Repairs) -->
        <?php if ($repair['repair_type'] === 'CUSTOMER_SERVICE'): ?>
            <div class="card">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div class="card-title">
                        <span>&#128179;</span> Payment Receipts History
                    </div>
                    <?php if ((float)$repair['due_amount'] > 0.00 && $canManage): ?>
                        <a href="/repairs/<?= (int)$repair['id'] ?>/payments/create" class="btn btn-sm btn-primary">
                            <span>&#43;</span> Record Payment
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table" style="width: 100%; margin-bottom: 0;">
                            <thead>
                                <tr style="background: #f8fafc; font-size: 0.8rem; border-bottom: 1px solid var(--border-color);">
                                    <th style="padding: 10px 16px;">Receipt #</th>
                                    <th style="padding: 10px 16px;">Date</th>
                                    <th style="padding: 10px 16px;">Mode</th>
                                    <th style="padding: 10px 16px;">Ref / Notes</th>
                                    <th style="padding: 10px 16px;">Received By</th>
                                    <th style="padding: 10px 16px; text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                            No payment receipts recorded yet. Outstanding balance is ₹ <?= number_format((float)$repair['due_amount'], 2) ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $pm): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 10px 16px; font-weight: 600; font-family: monospace;">
                                                <?= Security::e($pm['payment_number']) ?>
                                            </td>
                                            <td style="padding: 10px 16px; font-size: 0.85rem; color: var(--text-muted);">
                                                <?= Security::e($pm['payment_date']) ?>
                                            </td>
                                            <td style="padding: 10px 16px;">
                                                <span class="badge" style="background: #e2e8f0; color: #334155; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px;">
                                                    <?= Security::e($pm['payment_mode']) ?>
                                                </span>
                                            </td>
                                            <td style="padding: 10px 16px; font-size: 0.85rem; color: var(--text-muted);">
                                                <?= Security::e($pm['transaction_ref'] ?? ($pm['notes'] ?? '—')) ?>
                                            </td>
                                            <td style="padding: 10px 16px; font-size: 0.85rem;">
                                                <?= Security::e($pm['received_by_name']) ?>
                                            </td>
                                            <td style="padding: 10px 16px; text-align: right; font-weight: 700; color: #059669;">
                                                ₹ <?= number_format((float)$pm['amount'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Right Column: Financial Card -->
    <div>
        <div class="card" style="position: sticky; top: 20px;">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                <div class="card-title">
                    <span>&#128176;</span> Financial Accounting Summary
                </div>
            </div>
            <div class="card-body">
                
                <?php if ($canViewCosts): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.85rem;">
                            <span style="color: var(--text-muted);">Parts Cost:</span>
                            <span style="font-weight: 600;">₹ <?= number_format((float)$repair['parts_cost'], 2) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.85rem;">
                            <span style="color: var(--text-muted);">Labour Cost:</span>
                            <span style="font-weight: 600;">₹ <?= number_format((float)$repair['labour_amount'], 2) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 1px dashed #cbd5e1; padding-top: 6px; font-size: 0.9rem; font-weight: 700;">
                            <span>Total Repair Cost:</span>
                            <span style="color: #334155;">₹ <?= number_format((float)$repair['total_repair_cost'], 2) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($repair['repair_type'] === 'CUSTOMER_SERVICE'): ?>
                    <!-- Customer Service Accounting -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 0.9rem; font-weight: 600; color: #475569;">Amount Charged:</span>
                        <span style="font-size: 1.25rem; font-weight: 800; color: #1e293b;">
                            ₹ <?= number_format((float)$repair['amount_charged'], 2) ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 0.85rem; color: #059669;">Receipts Settled:</span>
                        <span style="font-size: 1rem; font-weight: 700; color: #059669;">
                            ₹ <?= number_format((float)$repair['paid_amount'], 2) ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 10px; margin-bottom: 14px;">
                        <span style="font-size: 0.95rem; font-weight: 700; color: <?= (float)$repair['due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">Balance Due:</span>
                        <span style="font-size: 1.2rem; font-weight: 800; color: <?= (float)$repair['due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">
                            ₹ <?= number_format((float)$repair['due_amount'], 2) ?>
                        </span>
                    </div>

                    <?php if ($canViewProfit): ?>
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 12px; margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.8rem; font-weight: 600; color: #166534; text-transform: uppercase;">Net Profit Contribution</span>
                                <span style="font-size: 1.1rem; font-weight: 800; color: <?= (float)$repair['net_profit_contribution'] >= 0 ? '#15803d' : '#b91c1c' ?>;">
                                    ₹ <?= number_format((float)$repair['net_profit_contribution'], 2) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ((float)$repair['due_amount'] > 0.00 && $canManage): ?>
                        <a href="/repairs/<?= (int)$repair['id'] ?>/payments/create" class="btn btn-primary" style="width: 100%; padding: 10px; font-weight: 700; text-align: center;">
                            <span>&#128176;</span> Record Customer Payment
                        </a>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Refurbishment Capitalization Accounting -->
                    <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; padding: 12px; margin-bottom: 16px; color: #92400e; font-size: 0.85rem; line-height: 1.4;">
                        <strong>Used Phone Capitalization:</strong> Refurbishment expenditures do not record customer billing. All ₹ <?= number_format((float)$repair['total_repair_cost'], 2) ?> in parts and labour are capitalized into the phone's total cost basis.
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">Acquisition / Purchase Cost:</span>
                        <span style="font-weight: 600;">₹ <?= number_format((float)($repair['phone_purchase_cost'] ?? 0), 2) ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.85rem; color: #0284c7;">Total Refurbishment / Repair Cost:</span>
                        <span style="font-weight: 600; color: #0284c7;">₹ <?= number_format((float)($repair['phone_repair_cost'] ?? 0), 2) ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 2px solid #059669; padding-top: 10px;">
                        <span style="font-size: 0.95rem; font-weight: 700; color: #065f46;">Handset Total Cost Basis:</span>
                        <span style="font-size: 1.25rem; font-weight: 800; color: #059669;">
                            ₹ <?= number_format((float)($repair['phone_total_cost_basis'] ?? 0), 2) ?>
                        </span>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
