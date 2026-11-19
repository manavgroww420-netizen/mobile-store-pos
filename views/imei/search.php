<?php
/**
 * Dedicated Counter IMEI Registry & Search View
 * File: views/imei/search.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
        <?= Security::e($pageTitle) ?>
    </h1>
    <p style="color: var(--text-muted); font-size: 0.875rem;">
        Instantaneous counter lookup tool for physical hardware identification, active stock status, and entire historical ownership episodes.
    </p>
</div>

<!-- Prominent Search Bar -->
<div class="card" style="margin-bottom: 24px; box-shadow: var(--shadow-md);">
    <div class="card-body" style="padding: 20px;">
        <form method="GET" action="/imei" id="imeiSearchForm">
            <label style="display: block; font-weight: 700; font-size: 0.95rem; margin-bottom: 8px; color: var(--text-main);">
                <span>&#128269;</span> Enter 15-Digit IMEI 1, IMEI 2, or Serial Number
            </label>
            <div style="display: flex; gap: 10px;">
                <input type="text" name="q" id="imeiSearchInput" 
                       value="<?= Security::e($query) ?>" 
                       autofocus required
                       placeholder="Scan barcode or type IMEI (e.g. 861234567890123)..." 
                       class="form-control" 
                       style="flex: 1; font-size: 1.1rem; font-family: var(--font-mono); padding: 12px 16px; font-weight: 600;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 1rem; font-weight: 700;">
                    Search Device
                </button>
                <?php if ($searchPerformed): ?>
                    <a href="/imei" class="btn btn-outline" style="padding: 12px 18px;">Clear</a>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 6px;">
                Tip: Works with USB barcode / 2D QR scanners directly at the billing counter.
            </div>
        </form>
    </div>
</div>

<?php if ($searchPerformed): ?>
    <?php if (!$device): ?>
        <div class="card" style="border-left: 4px solid #dc2626;">
            <div class="card-body" style="padding: 30px; text-align: center;">
                <div style="font-size: 2.2rem; color: #dc2626; margin-bottom: 10px;">&#9888;</div>
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">
                    Device Not Found in Shop Registry
                </h3>
                <p style="color: var(--text-muted); font-size: 0.875rem; max-width: 520px; margin: 0 auto 18px;">
                    No registered physical device matches the query <code><?= Security::e($query) ?></code>. Verify the digits or intake the device as new or second-hand.
                </p>
                <div style="display: flex; justify-content: center; gap: 10px;">
                    <a href="/phones/create-new" class="btn btn-primary btn-sm">
                        <span>&#43;</span> Intake as New Mobile
                    </a>
                    <a href="/phones/create-used" class="btn btn-secondary btn-sm">
                        <span>&#128260;</span> Buy as Second-Hand Mobile
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Device Found Results -->
        <div class="card" style="border-left: 4px solid var(--primary); margin-bottom: 24px;">
            <div class="card-header" style="background: #fff;">
                <div class="card-title">
                    <span>&#128241;</span> <?= Security::e($device['brand']) ?> <?= Security::e($device['model_name']) ?>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                        Device ID: #<?= (int)$device['id'] ?>
                    </span>
                    <?php if ($activeEpisode): ?>
                        <?php if ($activeEpisode['status'] === 'IN_STOCK'): ?>
                            <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; font-weight: 800;">
                                CURRENTLY IN STOCK
                            </span>
                        <?php elseif ($activeEpisode['status'] === 'SOLD'): ?>
                            <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; font-weight: 800;">
                                SOLD
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; font-weight: 800;">
                                <?= Security::e($activeEpisode['status']) ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2" style="gap: 20px;">
                    <div>
                        <h4 style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px;">
                            Hardware Identification
                        </h4>
                        <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                            <tbody>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 6px 0; color: var(--text-muted); width: 130px;">IMEI 1:</td>
                                    <td style="padding: 6px 0; font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                                        <?= Security::e($device['imei_1']) ?>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 6px 0; color: var(--text-muted);">IMEI 2:</td>
                                    <td style="padding: 6px 0; font-family: var(--font-mono);">
                                        <?= !empty($device['imei_2']) ? Security::e($device['imei_2']) : '<span style="color: var(--text-muted);">N/A (Single SIM)</span>' ?>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 6px 0; color: var(--text-muted);">Serial Number:</td>
                                    <td style="padding: 6px 0; font-family: var(--font-mono);">
                                        <?= !empty($device['serial_number']) ? Security::e($device['serial_number']) : '<span style="color: var(--text-muted);">N/A</span>' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: var(--text-muted);">First Registered:</td>
                                    <td style="padding: 6px 0; color: var(--text-main);">
                                        <?= Security::e($device['created_at']) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h4 style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px;">
                            Active Commercial Episode
                        </h4>
                        <?php if ($activeEpisode): ?>
                            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                                <tbody>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 6px 0; color: var(--text-muted); width: 130px;">Episode ID:</td>
                                        <td style="padding: 6px 0; font-weight: 700;">
                                            <a href="/phones/<?= (int)$activeEpisode['id'] ?>" style="color: var(--primary); text-decoration: none;">
                                                #<?= (int)$activeEpisode['id'] ?> (<?= Security::e($activeEpisode['phone_type']) ?>)
                                            </a>
                                        </td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 6px 0; color: var(--text-muted);">Condition:</td>
                                        <td style="padding: 6px 0;">
                                            <?= Security::e($activeEpisode['device_condition'] ?? 'Good') ?>
                                        </td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 6px 0; color: var(--text-muted);">Target Price:</td>
                                        <td style="padding: 6px 0; font-weight: 700; font-family: var(--font-mono); color: var(--primary);">
                                            <?= $activeEpisode['expected_sale_price'] !== null ? '₹ ' . number_format((float)$activeEpisode['expected_sale_price'], 2) : 'Not specified' ?>
                                        </td>
                                    </tr>
                                    <?php if ($canViewCost): ?>
                                        <tr>
                                            <td style="padding: 6px 0; color: var(--text-muted);">Total Cost Basis:</td>
                                            <td style="padding: 6px 0; font-weight: 700; font-family: var(--font-mono);">
                                                ₹ <?= number_format((float)$activeEpisode['total_cost_basis'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <div style="margin-top: 10px;">
                                <a href="/phones/<?= (int)$activeEpisode['id'] ?>" class="btn btn-outline btn-sm">
                                    Open Commercial Episode #<?= (int)$activeEpisode['id'] ?> &rarr;
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="color: var(--text-muted); font-size: 0.85rem;">
                                No active commercial episode currently attached to this physical device.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- History of All Ownership Episodes for this Device -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header" style="background: #fff;">
                <div class="card-title">
                    <span>&#128337;</span> Complete Commercial Lifecycle History (<?= count($historyEpisodes) ?> episodes)
                </div>
                <span style="font-size: 0.75rem; color: var(--text-muted);">
                    Tracks every buy, refurbishment, sale, and buyback of this exact physical handset.
                </span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                                <th style="padding: 10px 16px;">Episode #</th>
                                <th style="padding: 10px 16px;">Type</th>
                                <th style="padding: 10px 16px;">Acquisition Date</th>
                                <th style="padding: 10px 16px;">Status</th>
                                <?php if ($canViewCost): ?>
                                    <th style="padding: 10px 16px; text-align: right;">Cost Basis</th>
                                <?php endif; ?>
                                <th style="padding: 10px 16px;">Seller / Source</th>
                                <th style="padding: 10px 16px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyEpisodes as $ep): ?>
                                <tr style="border-bottom: 1px solid var(--border-color); <?= ($activeEpisode && (int)$ep['id'] === (int)$activeEpisode['id']) ? 'background: #eff6ff;' : '' ?>">
                                    <td style="padding: 10px 16px; font-weight: 700; font-family: var(--font-mono);">
                                        #<?= (int)$ep['id'] ?>
                                        <?php if ($activeEpisode && (int)$ep['id'] === (int)$activeEpisode['id']): ?>
                                            <span style="font-size: 0.7rem; color: var(--primary); font-weight: 800; margin-left: 4px;">(Active)</span>
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
                                        <a href="/phones/<?= (int)$ep['id'] ?>" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 2px 8px;">
                                            Inspect &rarr;
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
<?php else: ?>
    <!-- Recent Device Entries Table for quick counter lookup -->
    <div class="card">
        <div class="card-header" style="background: #fff;">
            <div class="card-title">
                <span>&#128241;</span> Recently Registered Mobile Devices
            </div>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Quick-click to inspect or verify IMEI</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                            <th style="padding: 10px 16px;">Device ID</th>
                            <th style="padding: 10px 16px;">Model</th>
                            <th style="padding: 10px 16px;">IMEI 1</th>
                            <th style="padding: 10px 16px;">IMEI 2</th>
                            <th style="padding: 10px 16px;">Active Status</th>
                            <th style="padding: 10px 16px; text-align: center;">Quick Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentDevices)): ?>
                            <tr>
                                <td colspan="6" style="padding: 30px; text-align: center; color: var(--text-muted);">
                                    No physical devices registered yet in the database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentDevices as $rd): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 10px 16px; font-weight: 700; font-family: var(--font-mono); color: var(--text-muted);">
                                        #<?= (int)$rd['id'] ?>
                                    </td>
                                    <td style="padding: 10px 16px; font-weight: 600;">
                                        <?= Security::e($rd['brand']) ?> <?= Security::e($rd['model_name']) ?>
                                    </td>
                                    <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                                        <?= Security::e($rd['imei_1']) ?>
                                    </td>
                                    <td style="padding: 10px 16px; font-family: var(--font-mono); color: var(--text-muted);">
                                        <?= !empty($rd['imei_2']) ? Security::e($rd['imei_2']) : 'N/A' ?>
                                    </td>
                                    <td style="padding: 10px 16px;">
                                        <?php if ($rd['status'] === 'IN_STOCK'): ?>
                                            <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">IN STOCK</span>
                                        <?php elseif ($rd['status'] === 'SOLD'): ?>
                                            <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">SOLD</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;"><?= Security::e($rd['status'] ?? 'UNASSIGNED') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px 16px; text-align: center;">
                                        <a href="/imei?q=<?= urlencode($rd['imei_1']) ?>" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 2px 8px;">
                                            Lookup &rarr;
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
<?php endif; ?>
