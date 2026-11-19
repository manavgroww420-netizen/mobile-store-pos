<?php
/**
 * Second-Hand Mobile Intake Form View
 * File: views/phones/create_used.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="/phones" class="btn btn-outline btn-sm">&larr; Back to Mobiles</a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
            <?= Security::e($pageTitle) ?>
        </h1>
    </div>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 6px;">
        Buy back used and second-hand mobile devices from walk-in customers with mandatory seller identification and AES-256-GCM Aadhaar protection.
    </p>
</div>

<div class="card" style="max-width: 880px;">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128260;</span> Customer Buyback &amp; Second-Hand Mobile Registration
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="/phones/create-used" id="usedPhoneForm">
            <?= Security::csrfField() ?>

            <!-- Catalog Model Selection -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Mobile Brand &amp; Model (Catalog) <span style="color: var(--danger);">*</span>
                </label>
                <select name="product_id" id="product_id" class="form-control" required style="width: 100%;">
                    <option value="">-- Select Phone Model from Catalog --</option>
                    <?php foreach ($products as $prod): ?>
                        <option value="<?= (int)$prod['id'] ?>" 
                                <?= (isset($formData['product_id']) && (int)$formData['product_id'] === (int)$prod['id']) ? 'selected' : '' ?>>
                            <?= Security::e($prod['brand']) ?> <?= Security::e($prod['model_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
                    Model not cataloged? <a href="/products/create" target="_blank" style="color: var(--primary); font-weight: 600;">Add phone model to catalog &rarr;</a>
                </div>
            </div>

            <!-- Walk-in Seller Identification Section -->
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-md); padding: 18px; margin-bottom: 20px;">
                <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 12px; color: #92400e; display: flex; align-items: center; gap: 8px;">
                    <span>&#128100;</span> Customer / Seller Identification Records
                </h3>
                <p style="font-size: 0.78rem; color: #78350f; margin-bottom: 14px;">
                    Mandatory for regulatory compliance in Howrah. Full Aadhaar numbers are never stored in plaintext — they are encrypted using AES-256-GCM authenticated encryption and masked (XXXXXXXX1234).
                </p>

                <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #78350f;">
                            Seller Full Name <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="text" name="seller_name" class="form-control" 
                               value="<?= Security::e($formData['seller_name'] ?? '') ?>" 
                               maxlength="100" required placeholder="Full legal name of customer" style="width: 100%;">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #78350f;">
                            Seller Phone Number <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="text" name="seller_phone" class="form-control" 
                               value="<?= Security::e($formData['seller_phone'] ?? '') ?>" 
                               maxlength="15" required placeholder="10-digit mobile number" style="width: 100%;">
                    </div>
                </div>

                <div class="grid grid-cols-2" style="gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #78350f;">
                            ID Document Type <span style="color: var(--danger);">*</span>
                        </label>
                        <select name="seller_id_type" id="seller_id_type" class="form-control" style="width: 100%;" onchange="updateIdHelpText(this)">
                            <option value="AADHAAR" <?= (($formData['seller_id_type'] ?? 'AADHAAR') === 'AADHAAR') ? 'selected' : '' ?>>Aadhaar Card (12 Digits)</option>
                            <option value="VOTER_ID" <?= (($formData['seller_id_type'] ?? '') === 'VOTER_ID') ? 'selected' : '' ?>>Voter ID Card</option>
                            <option value="PAN" <?= (($formData['seller_id_type'] ?? '') === 'PAN') ? 'selected' : '' ?>>PAN Card</option>
                            <option value="DRIVING_LICENSE" <?= (($formData['seller_id_type'] ?? '') === 'DRIVING_LICENSE') ? 'selected' : '' ?>>Driving License</option>
                            <option value="OTHER" <?= (($formData['seller_id_type'] ?? '') === 'OTHER') ? 'selected' : '' ?>>Other Official ID</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #78350f;" id="idNumberLabel">
                            Aadhaar Number (12 Digits) <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="text" name="seller_id_number" id="seller_id_number" class="form-control" 
                               required placeholder="e.g. 1234 5678 9012" style="width: 100%; font-family: var(--font-mono);">
                        <div id="idHelpText" style="font-size: 0.75rem; color: #92400e; margin-top: 4px;">
                            Encrypted with AES-256-GCM. Masked in UI views.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hardware IMEI Registry -->
            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; margin-bottom: 20px;">
                <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 14px; color: var(--text-main);">
                    <span>&#128269;</span> Physical Hardware IMEI Registry
                </h3>

                <div class="grid grid-cols-2" style="gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                            IMEI 1 <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="text" name="imei_1" id="imei_1" class="form-control" 
                               value="<?= Security::e($formData['imei_1'] ?? '') ?>" 
                               maxlength="15" required placeholder="15-digit numeric IMEI" 
                               style="width: 100%; font-family: var(--font-mono); font-weight: 600;"
                               oninput="validateImeiInput(this, 'imei1_status')">
                        <div id="imei1_status" style="font-size: 0.76rem; margin-top: 4px; font-weight: 600;"></div>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                            IMEI 2 <span style="color: var(--text-muted); font-size: 0.78rem;">(Optional for dual-SIM)</span>
                        </label>
                        <input type="text" name="imei_2" id="imei_2" class="form-control" 
                               value="<?= Security::e($formData['imei_2'] ?? '') ?>" 
                               maxlength="15" placeholder="15-digit numeric IMEI" 
                               style="width: 100%; font-family: var(--font-mono); font-weight: 600;"
                               oninput="validateImeiInput(this, 'imei2_status')">
                        <div id="imei2_status" style="font-size: 0.76rem; margin-top: 4px; font-weight: 600;"></div>
                    </div>
                </div>

                <div style="margin-top: 14px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                        Serial Number (S/N) <span style="color: var(--text-muted); font-size: 0.78rem;">(Optional)</span>
                    </label>
                    <input type="text" name="serial_number" class="form-control" 
                           value="<?= Security::e($formData['serial_number'] ?? '') ?>" 
                           maxlength="50" placeholder="e.g. Serial on device or box" 
                           style="width: 100%; font-family: var(--font-mono);">
                </div>
            </div>

            <!-- Device Physical Condition Assessment -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Device Physical Assessment &amp; Cosmetic Condition <span style="color: var(--danger);">*</span>
                </label>
                <textarea name="device_condition" class="form-control" rows="3" required 
                          placeholder="e.g. Minor scratches on back glass, original display intact with no touch issues, battery health 86%, includes original box and bill." 
                          style="width: 100%; resize: vertical;"><?= Security::e($formData['device_condition'] ?? '') ?></textarea>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                    Include observations on screen condition, body scratches, battery health, accessories included, and camera working state.
                </div>
            </div>

            <!-- Specifications & Hardware Variants -->
            <div class="grid grid-cols-3" style="gap: 16px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                        Color Variant
                    </label>
                    <input type="text" name="color" class="form-control" 
                           value="<?= Security::e($formData['color'] ?? '') ?>" 
                           placeholder="e.g. Midnight Black, Silver" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                        RAM
                    </label>
                    <input type="text" name="ram" class="form-control" 
                           value="<?= Security::e($formData['ram'] ?? '') ?>" 
                           placeholder="e.g. 6 GB, 8 GB" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                        Storage
                    </label>
                    <input type="text" name="storage" class="form-control" 
                           value="<?= Security::e($formData['storage'] ?? '') ?>" 
                           placeholder="e.g. 128 GB, 256 GB" style="width: 100%;">
                </div>
            </div>

            <!-- Purchase Financials -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; margin-bottom: 24px;">
                <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 14px; color: var(--text-main);">
                    <span>&#128176;</span> Buyback Price &amp; Resale Target
                </h3>

                <div class="grid grid-cols-3" style="gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                            Purchase Date <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="date" name="purchase_date" class="form-control" 
                               value="<?= Security::e($formData['purchase_date'] ?? date('Y-m-d')) ?>" 
                               required style="width: 100%;">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                            Buyback Price Paid to Seller (₹) <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" name="purchase_cost" class="form-control" 
                               value="<?= Security::e($formData['purchase_cost'] ?? '') ?>" 
                               required placeholder="0.00" style="width: 100%; font-weight: 700; font-family: var(--font-mono);">
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                            Cash/UPI paid to walk-in seller.
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                            Target Resale Price (₹)
                        </label>
                        <input type="number" step="0.01" min="0" name="expected_sale_price" class="form-control" 
                               value="<?= Security::e($formData['expected_sale_price'] ?? '') ?>" 
                               placeholder="0.00" style="width: 100%; font-family: var(--font-mono);">
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                            Estimated resale value after refurbishment.
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn btn-secondary" style="padding: 10px 24px; font-size: 0.95rem;">
                    <span>&#10003;</span> Purchase &amp; Register Second-Hand Mobile
                </button>
                <a href="/phones" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function checkLuhn(str) {
    if (!/^[0-9]{15}$/.test(str)) return false;
    let sum = 0;
    let parity = str.length % 2;
    for (let i = 0; i < str.length; i++) {
        let digit = parseInt(str.charAt(i), 10);
        if (i % 2 === parity) {
            digit *= 2;
            if (digit > 9) digit -= 9;
        }
        sum += digit;
    }
    return (sum % 10 === 0);
}

function validateImeiInput(input, statusId) {
    const val = input.value.trim();
    const statusEl = document.getElementById(statusId);
    if (!val) {
        statusEl.innerHTML = '';
        input.style.borderColor = 'var(--border-color)';
        return;
    }

    if (val.length < 15) {
        statusEl.innerHTML = `<span style="color: #64748b;">${val.length}/15 digits entered</span>`;
        input.style.borderColor = 'var(--border-color)';
    } else if (val.length === 15) {
        if (checkLuhn(val)) {
            statusEl.innerHTML = '<span style="color: #059669;">&#10003; Valid 15-digit Luhn check</span>';
            input.style.borderColor = '#059669';
        } else {
            statusEl.innerHTML = '<span style="color: #dc2626;">&#10007; Invalid 15th Luhn check digit</span>';
            input.style.borderColor = '#dc2626';
        }
    } else {
        statusEl.innerHTML = '<span style="color: #dc2626;">&#10007; Exceeds 15 digits</span>';
        input.style.borderColor = '#dc2626';
    }
}

function updateIdHelpText(selectEl) {
    const val = selectEl.value;
    const label = document.getElementById('idNumberLabel');
    const help = document.getElementById('idHelpText');
    const input = document.getElementById('seller_id_number');

    if (val === 'AADHAAR') {
        label.innerHTML = 'Aadhaar Number (12 Digits) <span style="color: var(--danger);">*</span>';
        help.textContent = 'Encrypted with AES-256-GCM. Masked in UI views.';
        input.placeholder = 'e.g. 1234 5678 9012';
    } else {
        label.innerHTML = val.replace('_', ' ') + ' Number <span style="color: var(--danger);">*</span>';
        help.textContent = 'Stored securely for KYC records.';
        input.placeholder = 'Enter identification document number';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const im1 = document.getElementById('imei_1');
    const im2 = document.getElementById('imei_2');
    if (im1 && im1.value) validateImeiInput(im1, 'imei1_status');
    if (im2 && im2.value) validateImeiInput(im2, 'imei2_status');
});
</script>
