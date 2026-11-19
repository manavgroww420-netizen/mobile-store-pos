<?php
/**
 * Purchase Consignment Detail & Ledger View
 * File: views/purchases/show.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/purchases" class="btn btn-outline btn-sm">&larr; Back to Purchases</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    <?= Security::e($purchase['purchase_number']) ?>
                    <span style="font-size: 0.95rem; font-weight: 600; color: var(--text-muted); font-family: var(--font-mono);">
                        (Consignment #<?= (int)$purchase['id'] ?>)
                    </span>
                </h1>
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px;">
                    <?php if ($purchase['payment_status'] === 'PAID'): ?>
                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">FULLY PAID</span>
                    <?php elseif ($purchase['payment_status'] === 'PARTIAL'): ?>
                        <span class="badge" style="background: #fffbeb; color: #b45309; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">PARTIALLY PAID</span>
                    <?php else: ?>
                        <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">UNPAID CREDIT</span>
                    <?php endif; ?>

                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        Vendor: <a href="/vendors/<?= (int)$purchase['vendor_id'] ?>" style="color: var(--primary); font-weight: 600; text-decoration: none;"><?= Security::e($purchase['vendor_name']) ?></a>
                    </span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        &bull; Date: <?= Security::e($purchase['purchase_date']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($canManage && (float)$purchase['due_amount'] > 0): ?>
                <a href="/purchases/<?= (int)$purchase['id'] ?>/payments/create" class="btn btn-secondary">
                    <span>&#128179;</span> Record Payment
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Financial Summary Cards -->
<?php if ($canViewCost): ?>
    <div class="grid grid-cols-3" style="gap: 16px; margin-bottom: 24px;">
        <div class="metric-card">
            <div class="metric-icon blue">&#128229;</div>
            <div class="metric-data">
                <div class="metric-label">Total Consignment Billed</div>
                <div class="metric-value">₹ <?= number_format((float)$purchase['total_amount'], 2) ?></div>
                <div class="metric-sub"><?= count($items) ?> line items total</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon green">&#9989;</div>
            <div class="metric-data">
                <div class="metric-label">Total Amount Paid</div>
                <div class="metric-value">₹ <?= number_format((float)$purchase['paid_amount'], 2) ?></div>
                <div class="metric-sub"><?= count($payments) ?> payment settlements</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon amber">&#9888;</div>
            <div class="metric-data">
                <div class="metric-label">Outstanding Balance Due</div>
                <div class="metric-value" style="color: <?= (float)$purchase['due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">
                    ₹ <?= number_format((float)$purchase['due_amount'], 2) ?>
                </div>
                <div class="metric-sub">Payable vendor credit</div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Consignment Metadata Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128203;</span> Consignment &amp; Supplier Information
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2" style="gap: 20px;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 6px 0; color: var(--text-muted); width: 140px; font-weight: 600;">Vendor Business:</td>
                        <td style="padding: 6px 0; font-weight: 700;">
                            <a href="/vendors/<?= (int)$purchase['vendor_id'] ?>" style="color: var(--primary); text-decoration: none;">
                                <?= Security::e($purchase['vendor_name']) ?>
                            </a>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 6px 0; color: var(--text-muted); font-weight: 600;">Contact Phone:</td>
                        <td style="padding: 6px 0; font-family: var(--font-mono);">
                            <?= Security::e($purchase['vendor_phone']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: var(--text-muted); font-weight: 600;">Vendor GSTIN:</td>
                        <td style="padding: 6px 0; font-family: var(--font-mono);">
                            <?= !empty($purchase['vendor_gstin']) ? Security::e($purchase['vendor_gstin']) : 'N/A' ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 6px 0; color: var(--text-muted); width: 140px; font-weight: 600;">Vendor Challan / Ref:</td>
                        <td style="padding: 6px 0; font-family: var(--font-mono); font-weight: 600;">
                            <?= !empty($purchase['vendor_invoice_no']) ? Security::e($purchase['vendor_invoice_no']) : 'None' ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 6px 0; color: var(--text-muted); font-weight: 600;">Received By Operator:</td>
                        <td style="padding: 6px 0;">
                            <?= Security::e($purchase['operator_username'] ?? 'admin') ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: var(--text-muted); font-weight: 600;">Internal Notes:</td>
                        <td style="padding: 6px 0;">
                            <?= !empty($purchase['notes']) ? Security::e($purchase['notes']) : 'No notes.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Purchase Line Items Table -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128230;</span> Purchased Items Ledger (<?= count($items) ?> lines)
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 10px 16px;">#</th>
                        <th style="padding: 10px 16px;">Product Brand &amp; Model</th>
                        <th style="padding: 10px 16px;">Classification</th>
                        <th style="padding: 10px 16px; text-align: center;">Quantity</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 10px 16px; text-align: right;">Unit Cost</th>
                            <th style="padding: 10px 16px; text-align: right;">Line Subtotal</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px 16px; color: var(--text-muted); font-family: var(--font-mono);">
                                <?= $idx + 1 ?>
                            </td>
                            <td style="padding: 10px 16px;">
                                <div style="font-weight: 700; color: var(--text-main);">
                                    <?= Security::e($it['brand']) ?> <?= Security::e($it['model_name']) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    Category: <?= Security::e($it['category_name']) ?>
                                    <?php if (!empty($it['sku'])): ?>
                                        &bull; SKU: <?= Security::e($it['sku']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 10px 16px;">
                                <?php if ($it['item_type'] === 'SERIALIZED_PHONE'): ?>
                                    <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">SERIALIZED PHONE</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">ACCESSORY</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 16px; text-align: center; font-weight: 700; font-family: var(--font-mono);">
                                <?= (int)$it['quantity'] ?>
                            </td>
                            <?php if ($canViewCost): ?>
                                <td style="padding: 10px 16px; text-align: right; font-family: var(--font-mono);">
                                    ₹ <?= number_format((float)$it['unit_cost'], 2) ?>
                                </td>
                                <td style="padding: 10px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono);">
                                    ₹ <?= number_format((float)$it['subtotal_cost'], 2) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($canViewCost): ?>
                    <tfoot>
                        <tr style="background: var(--bg-main); font-weight: 800;">
                            <td colspan="5" style="padding: 12px 16px; text-align: right; text-transform: uppercase; font-size: 0.8rem; color: var(--text-muted);">
                                Authoritative Consignment Total:
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-size: 1.05rem; font-family: var(--font-mono); color: var(--primary);">
                                ₹ <?= number_format((float)$purchase['total_amount'], 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Serialized Phones Generated under this Purchase -->
<?php if (!empty($phones)): ?>
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header" style="background: #fff;">
            <div class="card-title">
                <span>&#128241;</span> Serialized Phones Received in Consignment (<?= count($phones) ?> devices)
            </div>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Individual hardware transceiver records with active inventory status</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                            <th style="padding: 10px 16px;">Episode #</th>
                            <th style="padding: 10px 16px;">Brand &amp; Model</th>
                            <th style="padding: 10px 16px;">IMEI 1</th>
                            <th style="padding: 10px 16px;">IMEI 2</th>
                            <th style="padding: 10px 16px;">Specs / Color</th>
                            <th style="padding: 10px 16px;">Current Status</th>
                            <th style="padding: 10px 16px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($phones as $ph): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px 16px; font-weight: 700; font-family: var(--font-mono);">
                                    #<?= (int)$ph['phone_id'] ?>
                                </td>
                                <td style="padding: 10px 16px; font-weight: 600;">
                                    <?= Security::e($ph['brand']) ?> <?= Security::e($ph['model_name']) ?>
                                </td>
                                <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                                    <?= Security::e($ph['imei_1']) ?>
                                </td>
                                <td style="padding: 10px 16px; font-family: var(--font-mono); color: var(--text-muted);">
                                    <?= !empty($ph['imei_2']) ? Security::e($ph['imei_2']) : 'N/A' ?>
                                </td>
                                <td style="padding: 10px 16px; font-size: 0.8rem; color: var(--text-muted);">
                                    <?= !empty($ph['color']) ? Security::e($ph['color']) : 'Standard' ?>
                                    <?php if (!empty($ph['ram']) || !empty($ph['storage'])): ?>
                                        (<?= Security::e($ph['ram'] ?? '') ?>/<?= Security::e($ph['storage'] ?? '') ?>)
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px 16px;">
                                    <?php if ($ph['status'] === 'IN_STOCK'): ?>
                                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">IN STOCK</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;"><?= Security::e($ph['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px 16px; text-align: center;">
                                    <a href="/phones/<?= (int)$ph['phone_id'] ?>" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 2px 8px;">
                                        Inspect
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Payment Settlement History Table -->
<div class="card">
    <div class="card-header" style="background: #fff; display: flex; justify-content: space-between; align-items: center;">
        <div class="card-title">
            <span>&#128179;</span> Payment Settlement History (<?= count($payments) ?> payments)
        </div>
        <?php if ($canManage && (float)$purchase['due_amount'] > 0): ?>
            <a href="/purchases/<?= (int)$purchase['id'] ?>/payments/create" class="btn btn-secondary btn-sm">
                <span>&#43;</span> Record Payment
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 10px 16px;">Payment #</th>
                        <th style="padding: 10px 16px;">Date</th>
                        <th style="padding: 10px 16px;">Mode</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 10px 16px; text-align: right;">Amount Paid</th>
                        <?php endif; ?>
                        <th style="padding: 10px 16px;">Transaction Ref / Notes</th>
                        <th style="padding: 10px 16px;">Received / Logged By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? 6 : 5 ?>" style="padding: 24px; text-align: center; color: var(--text-muted);">
                                No payment settlements recorded yet for this purchase. Full amount is outstanding on credit.
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
