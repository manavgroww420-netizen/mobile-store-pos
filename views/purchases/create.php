<?php
/**
 * Create Purchase Consignment View
 * File: views/purchases/create.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="/purchases" class="btn btn-outline btn-sm">&larr; Back to Purchases</a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
            <?= Security::e($pageTitle) ?>
        </h1>
    </div>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 6px;">
        Record a supplier purchase consignment with dual inventory integration for bulk accessories and serialized mobile transceivers.
    </p>
</div>

<form method="POST" action="/purchases/create" id="purchaseForm">
    <?= Security::csrfField() ?>

    <!-- Consignment Header Card -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header" style="background: #fff;">
            <div class="card-title">
                <span>&#128229;</span> Vendor Consignment Header
            </div>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-3" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Supplier / Vendor <span style="color: var(--danger);">*</span>
                    </label>
                    <select name="vendor_id" class="form-control" required style="width: 100%;">
                        <option value="">-- Select Active Vendor --</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>" <?= ((int)($formData['vendor_id'] ?? 0) === (int)$v['id']) ? 'selected' : '' ?>>
                                <?= Security::e($v['business_name']) ?> (<?= Security::e($v['phone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Consignment / Invoice Date <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="date" name="purchase_date" class="form-control" 
                           value="<?= Security::e($formData['purchase_date'] ?? date('Y-m-d')) ?>" 
                           required style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Vendor Challan / Invoice Ref <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">(Internal memo)</span>
                    </label>
                    <input type="text" name="vendor_invoice_no" class="form-control" 
                           value="<?= Security::e($formData['vendor_invoice_no'] ?? '') ?>" 
                           placeholder="e.g. INV-9842 or Challan #12" style="width: 100%; font-family: var(--font-mono);">
                </div>
            </div>

            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                    Delivery Notes / Consignment Memo
                </label>
                <input type="text" name="notes" class="form-control" 
                       value="<?= Security::e($formData['notes'] ?? '') ?>" 
                       placeholder="e.g. Received via courier in good condition with bill" style="width: 100%;">
            </div>
        </div>
    </div>

    <!-- Purchase Line Items -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header" style="background: #fff; display: flex; justify-content: space-between; align-items: center;">
            <div class="card-title">
                <span>&#128230;</span> Purchase Consignment Line Items
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="addAccessoryRow()">
                    <span>&#43;</span> Add Accessory Line
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="addPhoneRow()">
                    <span>&#43;</span> Add Serialized Phone Line
                </button>
            </div>
        </div>
        <div class="card-body" id="itemsContainer" style="padding: 16px;">
            <div id="noItemsNotice" style="text-align: center; padding: 30px; color: var(--text-muted); border: 2px dashed var(--border-color); border-radius: var(--radius-sm);">
                <div style="font-size: 2rem; margin-bottom: 6px;">&#128229;</div>
                <div style="font-weight: 600;">No line items added yet</div>
                <div style="font-size: 0.82rem; margin-top: 4px;">Click "+ Add Accessory Line" or "+ Add Serialized Phone Line" above to enter purchased stock.</div>
            </div>
        </div>

        <div class="card-footer" style="background: var(--bg-main); display: flex; justify-content: space-between; align-items: center; padding: 14px 20px;">
            <div style="font-size: 0.82rem; color: var(--text-muted);">
                Authoritative total will be calculated server-side based on exact items and verified costs.
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted); margin-right: 8px;">Estimated Total:</span>
                <span id="grandTotalDisplay" style="font-size: 1.4rem; font-weight: 800; font-family: var(--font-mono); color: var(--primary);">
                    ₹ 0.00
                </span>
            </div>
        </div>
    </div>

    <!-- Initial Payment & Settlement Card -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header" style="background: #fff;">
            <div class="card-title">
                <span>&#128179;</span> Initial Payment &amp; Credit Terms
            </div>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-3" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Initial Payment Amount (₹) <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">(Leave 0 for full credit)</span>
                    </label>
                    <input type="number" step="0.01" min="0" name="initial_payment" id="initial_payment" class="form-control" 
                           value="<?= Security::e($formData['initial_payment'] ?? '0.00') ?>" 
                           placeholder="0.00" style="width: 100%; font-weight: 700; font-family: var(--font-mono);"
                           oninput="updatePaymentMath()">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Payment Mode
                    </label>
                    <select name="payment_mode" class="form-control" style="width: 100%;">
                        <option value="BANK_TRANSFER" <?= (($formData['payment_mode'] ?? '') === 'BANK_TRANSFER') ? 'selected' : '' ?>>Bank Transfer (NEFT / RTGS / IMPS)</option>
                        <option value="UPI" <?= (($formData['payment_mode'] ?? '') === 'UPI') ? 'selected' : '' ?>>UPI (GPay / PhonePe / Paytm)</option>
                        <option value="CASH" <?= (($formData['payment_mode'] ?? '') === 'CASH') ? 'selected' : '' ?>>Cash</option>
                        <option value="CHEQUE" <?= (($formData['payment_mode'] ?? '') === 'CHEQUE') ? 'selected' : '' ?>>Cheque</option>
                        <option value="CARD" <?= (($formData['payment_mode'] ?? '') === 'CARD') ? 'selected' : '' ?>>Debit / Credit Card</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem;">
                        Transaction Ref / Cheque No
                    </label>
                    <input type="text" name="transaction_ref" class="form-control" 
                           value="<?= Security::e($formData['transaction_ref'] ?? '') ?>" 
                           placeholder="e.g. UTR # or Cheque #123456" style="width: 100%; font-family: var(--font-mono);">
                </div>
            </div>

            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.875rem; font-weight: 600; color: var(--text-muted);">
                    Projected Outstanding Vendor Balance:
                </span>
                <span id="remainingDueDisplay" style="font-size: 1.2rem; font-weight: 800; font-family: var(--font-mono); color: #dc2626;">
                    ₹ 0.00
                </span>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 12px; align-items: center;">
        <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 1rem; font-weight: 700;">
            <span>&#10003;</span> Commit Consignment &amp; Update Inventory
        </button>
        <a href="/purchases" class="btn btn-outline">Cancel</a>
    </div>
</form>

<!-- Raw JSON Product Catalog for Dynamic JS Rows -->
<script>
const CATALOG_PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_SLASHES) ?>;
let itemCounter = 0;

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

function updateGrandTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.line-subtotal').forEach(el => {
        const val = parseFloat(el.getAttribute('data-subtotal') || 0);
        if (!isNaN(val)) grandTotal += val;
    });

    document.getElementById('grandTotalDisplay').textContent = '₹ ' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    updatePaymentMath();
}

function updatePaymentMath() {
    let grandTotal = 0;
    document.querySelectorAll('.line-subtotal').forEach(el => {
        const val = parseFloat(el.getAttribute('data-subtotal') || 0);
        if (!isNaN(val)) grandTotal += val;
    });

    const payInput = document.getElementById('initial_payment');
    const payVal = parseFloat(payInput.value || 0);
    const due = Math.max(0, grandTotal - payVal);

    document.getElementById('remainingDueDisplay').textContent = '₹ ' + due.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function removeLine(btn) {
    const row = btn.closest('.item-row');
    if (row) {
        row.remove();
        const container = document.getElementById('itemsContainer');
        if (container.querySelectorAll('.item-row').length === 0) {
            document.getElementById('noItemsNotice').style.display = 'block';
        }
        updateGrandTotal();
    }
}

// 1. Add Accessory Line
function addAccessoryRow() {
    document.getElementById('noItemsNotice').style.display = 'none';
    itemCounter++;
    const idx = itemCounter;

    const accessories = CATALOG_PRODUCTS.filter(p => p.item_type === 'ACCESSORY');
    let optionsHtml = '<option value="">-- Select Accessory --</option>';
    accessories.forEach(p => {
        optionsHtml += `<option value="${p.id}">${escapeHtml(p.brand)} ${escapeHtml(p.model_name)} [${escapeHtml(p.category_name)}]</option>`;
    });

    const row = document.createElement('div');
    row.className = 'item-row card';
    row.style.background = '#f8fafc';
    row.style.marginBottom = '14px';
    row.style.border = '1px solid var(--border-color)';
    row.innerHTML = `
        <div class="card-body" style="padding: 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span style="font-weight: 700; font-size: 0.85rem; color: var(--primary);">
                    &#128230; Accessory Line #${idx}
                </span>
                <button type="button" class="btn btn-outline btn-sm" onclick="removeLine(this)" style="color: #dc2626; border-color: #fca5a5; padding: 2px 8px; font-size: 0.75rem;">
                    Remove Line
                </button>
            </div>
            <div class="grid grid-cols-4" style="gap: 12px; align-items: flex-end;">
                <div style="grid-column: span 2;">
                    <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Product</label>
                    <select name="items[${idx}][product_id]" class="form-control" required style="width: 100%;">
                        ${optionsHtml}
                    </select>
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Quantity</label>
                    <input type="number" min="1" name="items[${idx}][quantity]" class="form-control acc-qty" value="1" required style="width: 100%;" oninput="calcAccSubtotal(this, ${idx})">
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Wholesale Unit Cost (₹)</label>
                    <input type="number" step="0.01" min="0" name="items[${idx}][unit_cost]" class="form-control acc-cost" required placeholder="0.00" style="width: 100%; font-family: var(--font-mono); font-weight: 700;" oninput="calcAccSubtotal(this, ${idx})">
                </div>
            </div>
            <div style="text-align: right; margin-top: 8px; font-size: 0.85rem;">
                <span style="color: var(--text-muted); font-weight: 600;">Line Subtotal:</span>
                <span class="line-subtotal" id="subtotal_${idx}" data-subtotal="0" style="font-weight: 800; font-family: var(--font-mono); color: var(--text-main); margin-left: 6px;">₹ 0.00</span>
            </div>
        </div>
    `;

    document.getElementById('itemsContainer').appendChild(row);
}

function calcAccSubtotal(input, idx) {
    const parent = input.closest('.item-row');
    const qty = parseInt(parent.querySelector('.acc-qty').value || 0, 10);
    const cost = parseFloat(parent.querySelector('.acc-cost').value || 0);
    const subtotal = Math.max(0, qty * cost);

    const el = document.getElementById(`subtotal_${idx}`);
    el.setAttribute('data-subtotal', subtotal.toFixed(2));
    el.textContent = '₹ ' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    updateGrandTotal();
}

// 2. Add Serialized Phone Line
function addPhoneRow() {
    document.getElementById('noItemsNotice').style.display = 'none';
    itemCounter++;
    const idx = itemCounter;

    const phones = CATALOG_PRODUCTS.filter(p => p.item_type === 'SERIALIZED_PHONE');
    let optionsHtml = '<option value="">-- Select Phone Model --</option>';
    phones.forEach(p => {
        optionsHtml += `<option value="${p.id}">${escapeHtml(p.brand)} ${escapeHtml(p.model_name)}</option>`;
    });

    const row = document.createElement('div');
    row.className = 'item-row card';
    row.style.background = '#eff6ff';
    row.style.border = '1px solid #bfdbfe';
    row.style.marginBottom = '14px';
    row.innerHTML = `
        <div class="card-body" style="padding: 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span style="font-weight: 700; font-size: 0.85rem; color: #1e3a8a;">
                    &#128241; Serialized Phone Line #${idx}
                </span>
                <button type="button" class="btn btn-outline btn-sm" onclick="removeLine(this)" style="color: #dc2626; border-color: #fca5a5; padding: 2px 8px; font-size: 0.75rem;">
                    Remove Line
                </button>
            </div>

            <div class="grid grid-cols-2" style="gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Phone Model (Catalog)</label>
                    <select name="items[${idx}][product_id]" class="form-control" required style="width: 100%;">
                        ${optionsHtml}
                    </select>
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Per-Unit Purchase Cost (₹)</label>
                    <input type="number" step="0.01" min="0" name="items[${idx}][unit_cost]" class="form-control phone-cost" required placeholder="0.00" style="width: 100%; font-family: var(--font-mono); font-weight: 700;" oninput="calcPhoneSubtotal(${idx})">
                </div>
            </div>

            <!-- Serialized Unit Enclosure -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">
                        Transceivers &amp; Specs for Unit #1
                    </span>
                </div>
                <div class="grid grid-cols-2" style="gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="font-size: 0.78rem; font-weight: 600; display: block; margin-bottom: 2px;">IMEI 1 (15 Digits, Luhn) <span style="color: var(--danger);">*</span></label>
                        <input type="text" maxlength="15" name="items[${idx}][phones][0][imei_1]" class="form-control" required placeholder="15-digit IMEI 1" style="font-family: var(--font-mono); font-weight: 600;" oninput="validateLineImei(this)">
                        <div class="imei-status" style="font-size: 0.72rem; margin-top: 2px; font-weight: 600;"></div>
                    </div>
                    <div>
                        <label style="font-size: 0.78rem; font-weight: 600; display: block; margin-bottom: 2px;">IMEI 2 (Optional dual-SIM)</label>
                        <input type="text" maxlength="15" name="items[${idx}][phones][0][imei_2]" class="form-control" placeholder="15-digit IMEI 2" style="font-family: var(--font-mono);" oninput="validateLineImei(this)">
                        <div class="imei-status" style="font-size: 0.72rem; margin-top: 2px; font-weight: 600;"></div>
                    </div>
                </div>
                <div class="grid grid-cols-4" style="gap: 10px;">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block;">Color</label>
                        <input type="text" name="items[${idx}][phones][0][color]" class="form-control" placeholder="e.g. Black" style="font-size: 0.8rem; padding: 4px 8px;">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block;">RAM</label>
                        <input type="text" name="items[${idx}][phones][0][ram]" class="form-control" placeholder="e.g. 8GB" style="font-size: 0.8rem; padding: 4px 8px;">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block;">Storage</label>
                        <input type="text" name="items[${idx}][phones][0][storage]" class="form-control" placeholder="e.g. 128GB" style="font-size: 0.8rem; padding: 4px 8px;">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block;">Serial Number</label>
                        <input type="text" name="items[${idx}][phones][0][serial_number]" class="form-control" placeholder="Optional S/N" style="font-size: 0.8rem; padding: 4px 8px;">
                    </div>
                </div>
            </div>

            <div style="text-align: right; margin-top: 10px; font-size: 0.85rem;">
                <span style="color: var(--text-muted); font-weight: 600;">Line Subtotal (1 unit):</span>
                <span class="line-subtotal" id="subtotal_${idx}" data-subtotal="0" style="font-weight: 800; font-family: var(--font-mono); color: #1e3a8a; margin-left: 6px;">₹ 0.00</span>
            </div>
        </div>
    `;

    document.getElementById('itemsContainer').appendChild(row);
}

function calcPhoneSubtotal(idx) {
    const parent = document.getElementById(`subtotal_${idx}`).closest('.item-row');
    const cost = parseFloat(parent.querySelector('.phone-cost').value || 0);
    const subtotal = Math.max(0, 1 * cost); // 1 unit per card

    const el = document.getElementById(`subtotal_${idx}`);
    el.setAttribute('data-subtotal', subtotal.toFixed(2));
    el.textContent = '₹ ' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    updateGrandTotal();
}

function validateLineImei(input) {
    const val = input.value.trim();
    const statusEl = input.nextElementSibling;
    if (!statusEl || !statusEl.classList.contains('imei-status')) return;

    if (!val) {
        statusEl.innerHTML = '';
        input.style.borderColor = 'var(--border-color)';
        return;
    }

    if (val.length < 15) {
        statusEl.innerHTML = `<span style="color: #64748b;">${val.length}/15 digits</span>`;
        input.style.borderColor = 'var(--border-color)';
    } else if (val.length === 15) {
        if (checkLuhn(val)) {
            statusEl.innerHTML = '<span style="color: #059669;">&#10003; Valid Luhn</span>';
            input.style.borderColor = '#059669';
        } else {
            statusEl.innerHTML = '<span style="color: #dc2626;">&#10007; Invalid Luhn</span>';
            input.style.borderColor = '#dc2626';
        }
    } else {
        statusEl.innerHTML = '<span style="color: #dc2626;">&#10007; &gt;15 digits</span>';
        input.style.borderColor = '#dc2626';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
    });
}
</script>
