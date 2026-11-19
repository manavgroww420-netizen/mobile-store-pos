<?php
/**
 * Vendor Profile & Account Ledger View
 * File: views/vendors/show.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/vendors" class="btn btn-outline btn-sm">&larr; Back to Directory</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    <?= Security::e($vendor['business_name']) ?>
                    <span style="font-size: 0.95rem; font-weight: 600; color: var(--text-muted); font-family: var(--font-mono);">
                        (Vendor #<?= (int)$vendor['id'] ?>)
                    </span>
                </h1>
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px;">
                    <?php if ((int)$vendor['is_active'] === 1): ?>
                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">ACTIVE VENDOR</span>
                    <?php else: ?>
                        <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">INACTIVE VENDOR</span>
                    <?php endif; ?>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        Phone: <strong><?= Security::e($vendor['phone']) ?></strong>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($canManage): ?>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php if ((int)$vendor['is_active'] === 1): ?>
                    <a href="/purchases/create?vendor_id=<?= (int)$vendor['id'] ?>" class="btn btn-primary">
                        <span>&#43;</span> New Purchase Consignment
                    </a>
                <?php endif; ?>
                <a href="/vendors/<?= (int)$vendor['id'] ?>/edit" class="btn btn-outline">
                    Edit Vendor
                </a>
                <form method="POST" action="/vendors/<?= (int)$vendor['id'] ?>/toggle-status" style="display: inline;">
                    <?= Security::csrfField() ?>
                    <button type="submit" class="btn btn-outline" onclick="return confirm('Toggle active status for this vendor?');">
                        <?= ((int)$vendor['is_active'] === 1) ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Summary Metric Cards -->
<?php if ($canViewCost): ?>
    <div class="grid grid-cols-4" style="gap: 16px; margin-bottom: 24px;">
        <div class="metric-card">
            <div class="metric-icon blue">&#128229;</div>
            <div class="metric-data">
                <div class="metric-label">Total Consignments</div>
                <div class="metric-value"><?= number_format((int)$stats['total_orders']) ?></div>
                <div class="metric-sub">Lifetime purchase orders</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon purple">&#128176;</div>
            <div class="metric-data">
                <div class="metric-label">Total Billed</div>
                <div class="metric-value">₹ <?= number_format((float)$stats['grand_total_purchased'], 2) ?></div>
                <div class="metric-sub">Cumulative purchase value</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon green">&#9989;</div>
            <div class="metric-data">
                <div class="metric-label">Total Paid</div>
                <div class="metric-value">₹ <?= number_format((float)$stats['grand_total_paid'], 2) ?></div>
                <div class="metric-sub">Settled payments</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon amber">&#9888;</div>
            <div class="metric-data">
                <div class="metric-label">Outstanding Balance</div>
                <div class="metric-value" style="color: <?= (float)$stats['grand_total_outstanding'] > 0 ? '#dc2626' : '#059669' ?>;">
                    ₹ <?= number_format((float)$stats['grand_total_outstanding'], 2) ?>
                </div>
                <div class="metric-sub">Payable vendor credit</div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Vendor Details Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#127970;</span> Vendor Profile Details
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2" style="gap: 20px;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px 0; color: var(--text-muted); width: 140px; font-weight: 600;">Contact Person:</td>
                        <td style="padding: 8px 0; font-weight: 700;">
                            <?= !empty($vendor['contact_person']) ? Security::e($vendor['contact_person']) : 'N/A' ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px 0; color: var(--text-muted); font-weight: 600;">Primary Phone:</td>
                        <td style="padding: 8px 0; font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                            <?= Security::e($vendor['phone']) ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px 0; color: var(--text-muted); font-weight: 600;">Alternate Phone:</td>
                        <td style="padding: 8px 0; font-family: var(--font-mono);">
                            <?= !empty($vendor['alt_phone']) ? Security::e($vendor['alt_phone']) : 'N/A' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: var(--text-muted); font-weight: 600;">Email:</td>
                        <td style="padding: 8px 0;">
                            <?= !empty($vendor['email']) ? Security::e($vendor['email']) : 'N/A' ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px 0; color: var(--text-muted); width: 140px; font-weight: 600;">GSTIN Reference:</td>
                        <td style="padding: 8px 0; font-family: var(--font-mono);">
                            <?= !empty($vendor['gstin']) ? Security::e($vendor['gstin']) : 'Unregistered / None' ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px 0; color: var(--text-muted); font-weight: 600;">Address:</td>
                        <td style="padding: 8px 0;">
                            <?= !empty($vendor['address']) ? Security::e($vendor['address']) : 'N/A' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: var(--text-muted); font-weight: 600; vertical-align: top;">Terms / Notes:</td>
                        <td style="padding: 8px 0;">
                            <?= !empty($vendor['notes']) ? Security::e($vendor['notes']) : 'No special terms noted.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Purchase History Table -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128229;</span> Consignment Purchases (<?= count($purchases) ?> records)
        </div>
        <?php if ($canManage && (int)$vendor['is_active'] === 1): ?>
            <a href="/purchases/create?vendor_id=<?= (int)$vendor['id'] ?>" class="btn btn-primary btn-sm">
                <span>&#43;</span> Record Purchase
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 10px 16px;">Purchase Number</th>
                        <th style="padding: 10px 16px;">Date</th>
                        <th style="padding: 10px 16px;">Vendor Invoice Ref</th>
                        <th style="padding: 10px 16px;">Status</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 10px 16px; text-align: right;">Total Amount</th>
                            <th style="padding: 10px 16px; text-align: right;">Paid</th>
                            <th style="padding: 10px 16px; text-align: right;">Outstanding</th>
                        <?php endif; ?>
                        <th style="padding: 10px 16px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? 8 : 5 ?>" style="padding: 24px; text-align: center; color: var(--text-muted);">
                                No purchases recorded from this vendor yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px 16px; font-weight: 700; font-family: var(--font-mono);">
                                    <a href="/purchases/<?= (int)$p['id'] ?>" style="color: var(--primary); text-decoration: none;">
                                        <?= Security::e($p['purchase_number']) ?>
                                    </a>
                                </td>
                                <td style="padding: 10px 16px; font-family: var(--font-mono); color: var(--text-muted);">
                                    <?= Security::e($p['purchase_date']) ?>
                                </td>
                                <td style="padding: 10px 16px; font-family: var(--font-mono);">
                                    <?= !empty($p['vendor_invoice_no']) ? Security::e($p['vendor_invoice_no']) : '<span style="color: var(--text-muted);">--</span>' ?>
                                </td>
                                <td style="padding: 10px 16px;">
                                    <?php if ($p['payment_status'] === 'PAID'): ?>
                                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">PAID</span>
                                    <?php elseif ($p['payment_status'] === 'PARTIAL'): ?>
                                        <span class="badge" style="background: #fffbeb; color: #b45309; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">PARTIAL</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">UNPAID</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="padding: 10px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono);">
                                        ₹ <?= number_format((float)$p['total_amount'], 2) ?>
                                    </td>
                                    <td style="padding: 10px 16px; text-align: right; font-family: var(--font-mono); color: #059669;">
                                        ₹ <?= number_format((float)$p['paid_amount'], 2) ?>
                                    </td>
                                    <td style="padding: 10px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono); color: <?= (float)$p['due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">
                                        ₹ <?= number_format((float)$p['due_amount'], 2) ?>
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 10px 16px; text-align: center;">
                                    <a href="/purchases/<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 2px 8px;">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Vendor Payment History Table -->
<div class="card">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128179;</span> Payment History to Vendor (<?= count($payments) ?> settlements)
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 10px 16px;">Payment #</th>
                        <th style="padding: 10px 16px;">Date</th>
                        <th style="padding: 10px 16px;">Purchase Order</th>
                        <th style="padding: 10px 16px;">Payment Mode</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 10px 16px; text-align: right;">Amount Paid</th>
                        <?php endif; ?>
                        <th style="padding: 10px 16px;">Reference / Notes</th>
                        <th style="padding: 10px 16px;">Paid By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? 7 : 6 ?>" style="padding: 24px; text-align: center; color: var(--text-muted);">
                                No payments recorded yet for this vendor.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $pay): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700;">
                                    <?= Security::e($pay['payment_number']) ?>
                                </td>
                                <td style="padding: 10px 16px; font-family: var(--font-mono); color: var(--text-muted);">
                                    <?= Security::e($pay['payment_date']) ?>
                                </td>
                                <td style="padding: 10px 16px; font-family: var(--font-mono);">
                                    <a href="/purchases/<?= (int)$pay['reference_id'] ?>" style="color: var(--primary); text-decoration: none;">
                                        <?= Security::e($pay['purchase_number']) ?>
                                    </a>
                                </td>
                                <td style="padding: 10px 16px;">
                                    <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">
                                        <?= Security::e($pay['payment_mode']) ?>
                                    </span>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="padding: 10px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono); color: #059669;">
                                        ₹ <?= number_format((float)$pay['amount'], 2) ?>
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 10px 16px; color: var(--text-muted); font-size: 0.8rem;">
                                    <?= !empty($pay['transaction_ref']) ? 'Ref: ' . Security::e($pay['transaction_ref']) . ' ' : '' ?>
                                    <?= !empty($pay['notes']) ? Security::e($pay['notes']) : '' ?>
                                </td>
                                <td style="padding: 10px 16px; color: var(--text-muted); font-size: 0.8rem;">
                                    <?= Security::e($pay['received_by_username'] ?? 'admin') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
