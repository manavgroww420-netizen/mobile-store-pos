<?php
/**
 * Create Vendor View
 * File: views/vendors/create.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="/vendors" class="btn btn-outline btn-sm">&larr; Back to Vendors</a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
            <?= Security::e($pageTitle) ?>
        </h1>
    </div>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 6px;">
        Add a supplier or wholesale distributor to the shop's procurement network.
    </p>
</div>

<div class="card" style="max-width: 760px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#127970;</span> Vendor Information Form
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="/vendors/create">
            <?= Security::csrfField() ?>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Business / Distributor Name <span style="color: var(--danger);">*</span>
                </label>
                <input type="text" name="business_name" class="form-control" 
                       value="<?= Security::e($formData['business_name'] ?? '') ?>" 
                       maxlength="150" required placeholder="e.g. Mahavir Mobile Accessories" style="width: 100%;">
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Contact Person / Representative
                    </label>
                    <input type="text" name="contact_person" class="form-control" 
                           value="<?= Security::e($formData['contact_person'] ?? '') ?>" 
                           maxlength="100" placeholder="e.g. Rakesh Jain" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Primary Phone Number <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?= Security::e($formData['phone'] ?? '') ?>" 
                           maxlength="20" required placeholder="e.g. 9831000000" style="width: 100%;">
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Alternate Phone Number
                    </label>
                    <input type="text" name="alt_phone" class="form-control" 
                           value="<?= Security::e($formData['alt_phone'] ?? '') ?>" 
                           maxlength="20" placeholder="Optional secondary phone" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Email Address
                    </label>
                    <input type="email" name="email" class="form-control" 
                           value="<?= Security::e($formData['email'] ?? '') ?>" 
                           maxlength="100" placeholder="e.g. supplier@example.com" style="width: 100%;">
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        GSTIN Reference <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">(Internal reference only)</span>
                    </label>
                    <input type="text" name="gstin" class="form-control" 
                           value="<?= Security::e($formData['gstin'] ?? '') ?>" 
                           maxlength="20" placeholder="e.g. 19ABCDE1234F1Z5" style="width: 100%; font-family: var(--font-mono);">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Account Status
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?= (!empty($formData['is_active'])) ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="is_active" style="cursor: pointer; font-size: 0.9rem; font-weight: 600;">Active Vendor (Available for new purchase consignments)</label>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Office / Warehouse Address
                </label>
                <textarea name="address" class="form-control" rows="2" placeholder="e.g. Burrabazar / Chandni Chowk, Kolkata" style="width: 100%; resize: vertical;"><?= Security::e($formData['address'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Internal Notes / Credit Terms
                </label>
                <textarea name="notes" class="form-control" rows="2" placeholder="e.g. 15-day credit period, replaces defective accessories on next delivery" style="width: 100%; resize: vertical;"><?= Security::e($formData['notes'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                    <span>&#10003;</span> Save Vendor
                </button>
                <a href="/vendors" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
