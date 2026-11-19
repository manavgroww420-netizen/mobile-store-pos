<?php
/**
 * Add Direct Cost to Phone Episode View
 * File: views/phones/add_cost.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="/phones/<?= (int)$phone['id'] ?>" class="btn btn-outline btn-sm">&larr; Back to Phone #<?= (int)$phone['id'] ?></a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
            <?= Security::e($pageTitle) ?>
        </h1>
    </div>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 6px;">
        Log direct repair, spare part, replacement, or overhead expenses attached to this specific mobile phone episode.
    </p>
</div>

<div class="card" style="max-width: 680px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128295;</span> Direct Refurbishment Cost Voucher
        </div>
        <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
            IMEI: <?= Security::e($phone['imei_1']) ?>
        </span>
    </div>
    <div class="card-body">
        <!-- Target Device Brief Banner -->
        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-weight: 700; color: var(--text-main);">
                    <?= Security::e($phone['brand']) ?> <?= Security::e($phone['model_name']) ?>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                    Episode #<?= (int)$phone['id'] ?> &bull; Status: <strong style="color: #059669;"><?= Security::e($phone['status']) ?></strong>
                </div>
            </div>
            <?php if ($canViewCost): ?>
                <div style="text-align: right;">
                    <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Current Cost Basis</div>
                    <div style="font-size: 1.15rem; font-weight: 800; font-family: var(--font-mono); color: var(--primary);">
                        ₹ <?= number_format((float)$phone['total_cost_basis'], 2) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" action="/phones/<?= (int)$phone['id'] ?>/costs/create">
            <?= Security::csrfField() ?>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Cost Category / Type <span style="color: var(--danger);">*</span>
                </label>
                <select name="cost_type" class="form-control" required style="width: 100%;">
                    <optgroup label="Direct Repair &amp; Refurbishment (Adds to repair_cost)">
                        <option value="DISPLAY" <?= (($formData['cost_type'] ?? '') === 'DISPLAY') ? 'selected' : '' ?>>Screen / Display / Touch Folder Replacement</option>
                        <option value="BATTERY" <?= (($formData['cost_type'] ?? '') === 'BATTERY') ? 'selected' : '' ?>>Battery Replacement</option>
                        <option value="PARTS" <?= (($formData['cost_type'] ?? '') === 'PARTS') ? 'selected' : '' ?>>Internal Spare Parts / Camera / Charging Flex</option>
                        <option value="LABOR" <?= (($formData['cost_type'] ?? '') === 'LABOR') ? 'selected' : '' ?>>Technician Servicing / Labor Charge</option>
                        <option value="REPAIR_OTHER" <?= (($formData['cost_type'] ?? '') === 'REPAIR_OTHER') ? 'selected' : '' ?>>Other Hardware Repair Fix</option>
                    </optgroup>
                    <optgroup label="Overhead &amp; Resale Preparation (Adds to other_cost)">
                        <option value="CLEANING" <?= (($formData['cost_type'] ?? '') === 'CLEANING') ? 'selected' : '' ?>>Buffing, Polishing &amp; Sanitization</option>
                        <option value="PACKAGING" <?= (($formData['cost_type'] ?? '') === 'PACKAGING') ? 'selected' : '' ?>>Box, Cable &amp; Charger Packing</option>
                        <option value="LOGISTICS" <?= (($formData['cost_type'] ?? '') === 'LOGISTICS') ? 'selected' : '' ?>>Transport &amp; Courier Overhead</option>
                        <option value="OTHER" <?= (($formData['cost_type'] ?? '') === 'OTHER') ? 'selected' : '' ?>>Other Direct Expense</option>
                    </optgroup>
                </select>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Amount (₹) <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" 
                           value="<?= Security::e($formData['amount'] ?? '') ?>" 
                           required placeholder="e.g. 1500.00" 
                           style="width: 100%; font-weight: 700; font-family: var(--font-mono);">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Expense Date <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="date" name="cost_date" class="form-control" 
                           value="<?= Security::e($formData['cost_date'] ?? date('Y-m-d')) ?>" 
                           required style="width: 100%;">
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Description / Voucher Memo <span style="color: var(--danger);">*</span>
                </label>
                <input type="text" name="description" class="form-control" 
                       value="<?= Security::e($formData['description'] ?? '') ?>" 
                       maxlength="255" required 
                       placeholder="e.g. Replaced cracked AMOLED display folder from vendor ABC" 
                       style="width: 100%;">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                    Explain what parts were replaced, vendor voucher number, or technician task details.
                </div>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                    <span>&#10003;</span> Commit Cost &amp; Recalculate Basis
                </button>
                <a href="/phones/<?= (int)$phone['id'] ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
