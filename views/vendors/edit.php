<?php
/**
 * Edit Vendor View
 * File: views/vendors/edit.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="/vendors/<?= (int)$vendor['id'] ?>" class="btn btn-outline btn-sm">&larr; Back to Profile</a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
            <?= Security::e($pageTitle) ?>
        </h1>
    </div>
</div>

<div class="card" style="max-width: 760px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#127970;</span> Edit Vendor Details
        </div>
        <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
            ID #<?= (int)$vendor['id'] ?>
        </span>
    </div>
    <div class="card-body">
        <form method="POST" action="/vendors/<?= (int)$vendor['id'] ?>/edit">
            <?= Security::csrfField() ?>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Business / Distributor Name <span style="color: var(--danger);">*</span>
                </label>
                <input type="text" name="business_name" class="form-control" 
                       value="<?= Security::e($vendor['business_name'] ?? '') ?>" 
                       maxlength="150" required style="width: 100%;">
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Contact Person / Representative
                    </label>
                    <input type="text" name="contact_person" class="form-control" 
                           value="<?= Security::e($vendor['contact_person'] ?? '') ?>" 
                           maxlength="100" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Primary Phone Number <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?= Security::e($vendor['phone'] ?? '') ?>" 
                           maxlength="20" required style="width: 100%;">
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Alternate Phone Number
                    </label>
                    <input type="text" name="alt_phone" class="form-control" 
                           value="<?= Security::e($vendor['alt_phone'] ?? '') ?>" 
                           maxlength="20" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Email Address
                    </label>
                    <input type="email" name="email" class="form-control" 
                           value="<?= Security::e($vendor['email'] ?? '') ?>" 
                           maxlength="100" style="width: 100%;">
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        GSTIN Reference
                    </label>
                    <input type="text" name="gstin" class="form-control" 
                           value="<?= Security::e($vendor['gstin'] ?? '') ?>" 
                           maxlength="20" style="width: 100%; font-family: var(--font-mono);">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Account Status
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?= ((int)$vendor['is_active'] === 1) ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="is_active" style="cursor: pointer; font-size: 0.9rem; font-weight: 600;">Active Vendor (Available for new purchase consignments)</label>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Office / Warehouse Address
                </label>
                <textarea name="address" class="form-control" rows="2" style="width: 100%; resize: vertical;"><?= Security::e($vendor['address'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Internal Notes / Credit Terms
                </label>
                <textarea name="notes" class="form-control" rows="2" style="width: 100%; resize: vertical;"><?= Security::e($vendor['notes'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                    <span>&#10003;</span> Update Vendor
                </button>
                <a href="/vendors/<?= (int)$vendor['id'] ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
