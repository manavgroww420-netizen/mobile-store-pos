<?php
/**
 * Serialized Mobile Episode Detail View
 * File: views/phones/show.php
 */
use AMH\Security;
use AMH\Financial;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/phones" class="btn btn-outline btn-sm">&larr; Back to Mobiles</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    <?= Security::e($phone['brand']) ?> <?= Security::e($phone['model_name']) ?>
                    <span style="font-size: 0.95rem; font-weight: 600; color: var(--text-muted); font-family: var(--font-mono);">
                        (Episode #<?= (int)$phone['id'] ?> &bull; Device #<?= (int)$phone['device_id'] ?>)
                    </span>
                </h1>
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px;">
                    <?php if ($phone['phone_type'] === 'NEW'): ?>
                        <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">BRAND NEW</span>
                    <?php else: ?>
                        <span class="badge" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">SECOND-HAND</span>
                    <?php endif; ?>

                    <?php if ($phone['status'] === 'IN_STOCK'): ?>
                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">IN STOCK</span>
                    <?php elseif ($phone['status'] === 'SOLD'): ?>
                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">SOLD</span>
                    <?php elseif ($phone['status'] === 'RETURNED_TO_VENDOR'): ?>
                        <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">RETURNED TO VENDOR</span>
                    <?php else: ?>
                        <span class="badge" style="background: #fef2f2; color: #991b1b; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;">SCRAPPED</span>
                    <?php endif; ?>

                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        Intake: <?= Security::e($phone['purchase_date']) ?> by <?= Security::e($phone['operator_username'] ?? 'admin') ?>
                    </span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="/imei?q=<?= urlencode($phone['imei_1']) ?>" class="btn btn-outline" style="background: #fff;">
                <span>&#128269;</span> IMEI Counter Lookup
            </a>
            <?php if ($canAddCost && $phone['status'] === 'IN_STOCK'): ?>
                <a href="/phones/<?= (int)$phone['id'] ?>/costs/create" class="btn btn-primary">
                    <span>&#43;</span> Add Direct Cost
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-2" style="gap: 20px; margin-bottom: 20px;">
    <!-- Hardware & Device Registry -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <span>&#128241;</span> Physical Hardware Registry
            </div>
            <span style="font-size: 0.75rem; font-family: var(--font-mono); color: var(--text-muted);">Device #<?= (int)$phone['device_id'] ?></span>
        </div>
        <div class="card-body">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0; color: var(--text-muted); width: 140px; font-weight: 600;">Brand &amp; Model</td>
                        <td style="padding: 10px 0; font-weight: 700; color: var(--text-main);">
                            <?= Security::e($phone['brand']) ?> <?= Security::e($phone['model_name']) ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">IMEI 1</td>
                        <td style="padding: 10px 0; font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                            <?= Security::e($phone['imei_1']) ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">IMEI 2</td>
                        <td style="padding: 10px 0; font-family: var(--font-mono);">
                            <?= !empty($phone['imei_2']) ? Security::e($phone['imei_2']) : '<span style="color: var(--text-muted);">N/A (Single SIM)</span>' ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">Serial Number</td>
                        <td style="padding: 10px 0; font-family: var(--font-mono);">
                            <?= !empty($phone['serial_number']) ? Security::e($phone['serial_number']) : '<span style="color: var(--text-muted);">N/A</span>' ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">Variant Specs</td>
                        <td style="padding: 10px 0;">
                            Color: <strong><?= !empty($phone['color']) ? Security::e($phone['color']) : 'Standard' ?></strong>
                            <?php if (!empty($phone['ram']) || !empty($phone['storage'])): ?>
                                &bull; Memory: <strong><?= Security::e($phone['ram'] ?? '') ?><?= (!empty($phone['ram']) && !empty($phone['storage'])) ? ' / ' : '' ?><?= Security::e($phone['storage'] ?? '') ?></strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600; vertical-align: top;">Condition</td>
                        <td style="padding: 10px 0; color: var(--text-main);">
                            <?= Security::e($phone['device_condition'] ?? 'Good') ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Commercial Episode & Seller Identity -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <span>&#128100;</span> Commercial Episode &amp; Source
            </div>
            <span style="font-size: 0.75rem; font-family: var(--font-mono); color: var(--text-muted);">Episode #<?= (int)$phone['id'] ?></span>
        </div>
        <div class="card-body">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0; color: var(--text-muted); width: 140px; font-weight: 600;">Acquisition Source</td>
                        <td style="padding: 10px 0; font-weight: 700;">
                            <?= ($phone['acquisition_source'] === 'CUSTOMER_BUYBACK') ? 'Walk-in Customer Buyback' : 'Wholesale Vendor Consignment' ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">Intake Date</td>
                        <td style="padding: 10px 0;">
                            <?= Security::e($phone['purchase_date']) ?>
                        </td>
                    </tr>

                    <?php if ($phone['phone_type'] === 'SECOND_HAND'): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">Seller Full Name</td>
                            <td style="padding: 10px 0; font-weight: 700;">
                                <?= Security::e($phone['seller_name'] ?? 'N/A') ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">Seller Contact Phone</td>
                            <td style="padding: 10px 0; font-family: var(--font-mono);">
                                <?= Security::e($phone['seller_phone'] ?? 'N/A') ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">Seller Identification</td>
                            <td style="padding: 10px 0;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <div>
                                        <span style="font-weight: 600;"><?= Security::e($phone['seller_id_type'] ?? 'AADHAAR') ?>:</span>
                                        <span id="aadhaarDisplay" style="font-family: var(--font-mono); font-weight: 700; color: var(--text-main); margin-left: 4px;">
                                            <?= Security::e($phone['seller_id_number_masked'] ?? ('XXXXXXXX' . ($phone['seller_aadhaar_last4'] ?? ''))) ?>
                                        </span>
                                    </div>
                                    <?php if ($canViewSensitiveId && !empty($phone['seller_aadhaar_encrypted'])): ?>
                                        <button type="button" class="btn btn-outline btn-sm" id="revealBtn" onclick="revealAadhaar(<?= (int)$phone['id'] ?>)" style="font-size: 0.75rem; padding: 2px 8px;">
                                            Reveal Full ID
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div id="revealNotice" style="font-size: 0.72rem; color: #059669; margin-top: 4px; display: none;"></div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <td style="padding: 10px 0; color: var(--text-muted); font-weight: 600;">Expected Resale Price</td>
                        <td style="padding: 10px 0; font-weight: 700; font-family: var(--font-mono); color: var(--primary);">
                            <?= $phone['expected_sale_price'] !== null ? '₹ ' . number_format((float)$phone['expected_sale_price'], 2) : '<span style="color: var(--text-muted); font-weight: normal;">Not specified</span>' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Financial Cost Basis Breakdown -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <div class="card-title">
            <span>&#128176;</span> Financial Cost Basis Formulation
        </div>
        <?php if ($canViewCost): ?>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Formula: Total Cost Basis = Purchase Cost + Direct Repair Costs + Other Costs</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$canViewCost): ?>
            <div style="padding: 24px; text-align: center; color: var(--text-muted); background: var(--bg-main); border-radius: var(--radius-sm);">
                <div style="font-size: 1.5rem; margin-bottom: 6px;">&#128274;</div>
                <div style="font-weight: 600;">Purchase Cost &amp; Profit Margins Restricted</div>
                <div style="font-size: 0.82rem; margin-top: 2px;">Your user account does not hold the <code>view_purchase_costs</code> permission.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-4" style="gap: 16px;">
                <div style="background: var(--bg-main); padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">1. Acquisition Cost</div>
                    <div style="font-size: 1.35rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-main); margin-top: 4px;">
                        ₹ <?= number_format((float)$phone['purchase_cost'], 2) ?>
                    </div>
                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Paid to seller / vendor</div>
                </div>

                <div style="background: var(--bg-main); padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">2. Refurbishment / Parts</div>
                    <div style="font-size: 1.35rem; font-weight: 800; font-family: var(--font-mono); color: #d97706; margin-top: 4px;">
                        ₹ <?= number_format((float)$phone['repair_cost'], 2) ?>
                    </div>
                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Screens, battery, IC, labor</div>
                </div>

                <div style="background: var(--bg-main); padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">3. Other Direct Overhead</div>
                    <div style="font-size: 1.35rem; font-weight: 800; font-family: var(--font-mono); color: #64748b; margin-top: 4px;">
                        ₹ <?= number_format((float)$phone['other_cost'], 2) ?>
                    </div>
                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Buffing, packaging, courier</div>
                </div>

                <div style="background: #eff6ff; padding: 14px; border-radius: var(--radius-sm); border: 1px solid #bfdbfe;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #1d4ed8; text-transform: uppercase;">Total Cost Basis</div>
                    <div style="font-size: 1.35rem; font-weight: 800; font-family: var(--font-mono); color: #1e3a8a; margin-top: 4px;">
                        ₹ <?= number_format((float)$phone['total_cost_basis'], 2) ?>
                    </div>
                    <div style="font-size: 0.72rem; color: #3b82f6; margin-top: 2px;">Net COGS for P&amp;L</div>
                </div>
            </div>

            <?php if ($phone['expected_sale_price'] !== null): ?>
                <?php 
                    $estProfit = (float)$phone['expected_sale_price'] - (float)$phone['total_cost_basis'];
                ?>
                <div style="margin-top: 14px; padding: 12px 16px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                        Projected Gross Margin @ Target Price (₹<?= number_format((float)$phone['expected_sale_price'], 2) ?>):
                    </span>
                    <span style="font-size: 1.05rem; font-weight: 800; font-family: var(--font-mono); color: <?= $estProfit >= 0 ? '#059669' : '#dc2626' ?>;">
                        ₹ <?= number_format($estProfit, 2) ?>
                        <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-muted);">
                            (<?= ((float)$phone['expected_sale_price'] > 0) ? round(($estProfit / (float)$phone['expected_sale_price']) * 100, 1) : 0 ?>% margin)
                        </span>
                    </span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Direct Costs Ledger Table -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128295;</span> Direct Refurbishment &amp; Overhead Ledger (<?= count($directCosts) ?> entries)
        </div>
        <?php if ($canAddCost && $phone['status'] === 'IN_STOCK'): ?>
            <a href="/phones/<?= (int)$phone['id'] ?>/costs/create" class="btn btn-primary btn-sm">
                <span>&#43;</span> Add Direct Cost
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 10px 16px;">Date</th>
                        <th style="padding: 10px 16px;">Cost Type</th>
                        <th style="padding: 10px 16px;">Description / Memo</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 10px 16px; text-align: right;">Amount (₹)</th>
                        <?php endif; ?>
                        <th style="padding: 10px 16px;">Logged By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($directCosts)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? 5 : 4 ?>" style="padding: 24px; text-align: center; color: var(--text-muted);">
                                No refurbishment or direct costs logged yet for this phone episode.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($directCosts as $dc): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px 16px; font-family: var(--font-mono); color: var(--text-muted); font-size: 0.8rem;">
                                    <?= Security::e($dc['cost_date']) ?>
                                </td>
                                <td style="padding: 10px 16px;">
                                    <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">
                                        <?= Security::e($dc['cost_type']) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 16px;">
                                    <?= Security::e($dc['description']) ?>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="padding: 10px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono);">
                                        ₹ <?= number_format((float)$dc['amount'], 2) ?>
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 10px 16px; color: var(--text-muted); font-size: 0.8rem;">
                                    <?= Security::e($dc['added_by_username'] ?? 'admin') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Physical Device Ownership & Episode History -->
<div class="card">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128337;</span> Physical Hardware Commercial History (<?= count($episodes) ?> commercial lifecycles)
        </div>
        <span style="font-size: 0.75rem; color: var(--text-muted);">
            Preserves separate episodes if this device was sold and bought back over time.
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 10px 16px;">Episode #</th>
                        <th style="padding: 10px 16px;">Lifecycle Type</th>
                        <th style="padding: 10px 16px;">Intake Date</th>
                        <th style="padding: 10px 16px;">Status</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 10px 16px; text-align: right;">Total Cost Basis</th>
                        <?php endif; ?>
                        <th style="padding: 10px 16px;">Seller / Source</th>
                        <th style="padding: 10px 16px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($episodes as $ep): ?>
                        <tr style="border-bottom: 1px solid var(--border-color); <?= ((int)$ep['id'] === (int)$phone['id']) ? 'background: #eff6ff;' : '' ?>">
                            <td style="padding: 10px 16px; font-weight: 700; font-family: var(--font-mono);">
                                #<?= (int)$ep['id'] ?>
                                <?php if ((int)$ep['id'] === (int)$phone['id']): ?>
                                    <span style="font-size: 0.7rem; color: var(--primary); font-weight: 800; margin-left: 4px;">(Current)</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 16px;">
                                <span class="badge" style="font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; background: <?= ($ep['phone_type'] === 'NEW') ? '#eff6ff; color: #1d4ed8;' : '#fffbeb; color: #b45309;' ?>">
                                    <?= Security::e($ep['phone_type']) ?>
                                </span>
                            </td>
                            <td style="padding: 10px 16px; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                <?= Security::e($ep['purchase_date']) ?>
                            </td>
                            <td style="padding: 10px 16px;">
                                <span class="badge" style="font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; background: <?= ($ep['status'] === 'IN_STOCK') ? '#ecfdf5; color: #047857;' : '#f1f5f9; color: #475569;' ?>">
                                    <?= Security::e($ep['status']) ?>
                                </span>
                            </td>
                            <?php if ($canViewCost): ?>
                                <td style="padding: 10px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono);">
                                    ₹ <?= number_format((float)$ep['total_cost_basis'], 2) ?>
                                </td>
                            <?php endif; ?>
                            <td style="padding: 10px 16px; color: var(--text-muted); font-size: 0.82rem;">
                                <?= Security::e($ep['seller_name'] ?? $ep['acquisition_source']) ?>
                            </td>
                            <td style="padding: 10px 16px; text-align: center;">
                                <?php if ((int)$ep['id'] !== (int)$phone['id']): ?>
                                    <a href="/phones/<?= (int)$ep['id'] ?>" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 2px 8px;">
                                        View
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--primary); font-weight: 700;">Viewing</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canViewSensitiveId): ?>
<script>
function revealAadhaar(phoneId) {
    if (!confirm('Are you sure you want to reveal the customer identification document number? This action will be audited in system logs.')) {
        return;
    }

    const btn = document.getElementById('revealBtn');
    btn.disabled = true;
    btn.textContent = 'Decrypting...';

    const csrfToken = '<?= Security::getCsrfToken() ?>';

    fetch(`/phones/${phoneId}/reveal-id`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('aadhaarDisplay').textContent = data.aadhaar;
            document.getElementById('aadhaarDisplay').style.color = '#047857';
            btn.style.display = 'none';

            const notice = document.getElementById('revealNotice');
            notice.style.display = 'block';
            notice.textContent = `Aadhaar decrypted and verified. Access logged at ${data.timestamp}.`;
        } else {
            alert('Failed to decrypt: ' + (data.error || 'Access denied'));
            btn.disabled = false;
            btn.textContent = 'Reveal Full ID';
        }
    })
    .catch(err => {
        alert('Network error while requesting ID reveal: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Reveal Full ID';
    });
}
</script>
<?php endif; ?>
