<?php
/**
 * New Counter Sale Entry View
 * File: views/sales/create.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/sales" class="btn btn-outline btn-sm">&larr; Back to Sales</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    New Counter Sale
                </h1>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 4px 0 0 0;">
                    Record counter transactions, mobile handset sales, and customer credit (udhar).
                </p>
            </div>
        </div>
        <div style="font-size: 0.8rem; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 6px 14px; border-radius: 6px; font-weight: 600;">
            <span>&#9432;</span> Internal Business Record &bull; Not a Tax Invoice
        </div>
    </div>
</div>

<form id="sale-form" action="/sales/create" method="POST">
    <?= Security::csrfField() ?>

    <div class="grid grid-cols-3" style="gap: 24px; align-items: start;">
        <!-- Left 2 Columns: Item Selection & Cart -->
        <div style="grid-column: span 2;">
            
            <!-- Customer Information Card -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128100;</span> Customer Identification (Required for Credit / Udhar)
                    </div>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2" style="gap: 16px;">
                        <div class="form-group">
                            <label for="customer_name" class="form-label" style="font-weight: 600;">Customer Full Name</label>
                            <input 
                                type="text" 
                                id="customer_name" 
                                name="customer_name" 
                                class="form-control" 
                                placeholder="e.g. Ramesh Ghosh (Walk-in if blank)"
                            >
                        </div>

                        <div class="form-group">
                            <label for="customer_phone" class="form-label" style="font-weight: 600;">Customer Phone Number</label>
                            <input 
                                type="tel" 
                                id="customer_phone" 
                                name="customer_phone" 
                                class="form-control" 
                                placeholder="10-digit mobile number (e.g. 9830012345)" 
                                maxlength="10" 
                                pattern="[6-9][0-9]{9}"
                            >
                            <div class="help-text" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                Required if any outstanding balance / credit is extended.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item Entry Selector Card -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <span><span>&#128722;</span> Add Items to Counter Cart</span>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-sm btn-primary" id="tab-btn-phone" style="font-size: 0.75rem; padding: 4px 10px;">
                                + Serialized Phone
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" id="tab-btn-acc" style="font-size: 0.75rem; padding: 4px 10px;">
                                + Accessory Product
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="background: #f8fafc;">
                    <!-- Add Serialized Handset Box -->
                    <div id="box-add-phone">
                        <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 10px;">
                            Select Available Serialized Phone (In-Stock Physical Devices):
                        </div>
                        <div class="grid grid-cols-3" style="gap: 12px; align-items: end;">
                            <div style="grid-column: span 2;">
                                <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Available Handset Episode</label>
                                <select id="sel-phone-id" class="form-control" style="font-size: 0.85rem;">
                                    <option value="">-- Choose from available handsets in stock --</option>
                                    <?php foreach ($phones as $ph): ?>
                                        <option 
                                            value="<?= (int)$ph['id'] ?>"
                                            data-product-id="<?= (int)$ph['product_id'] ?>"
                                            data-brand="<?= Security::e($ph['brand']) ?>"
                                            data-model="<?= Security::e($ph['model_name']) ?>"
                                            data-imei="<?= Security::e($ph['imei_1']) ?>"
                                            data-specs="<?= Security::e(trim(($ph['ram'] ? $ph['ram'] . ' ' : '') . ($ph['storage'] ?: '') . ' ' . ($ph['color'] ?: ''))) ?>"
                                            data-type="<?= Security::e($ph['phone_type']) ?>"
                                            data-price="<?= Security::e((string)($ph['expected_sale_price'] ?: $ph['default_sale_price'] ?: '0.00')) ?>"
                                        >
                                            <?= Security::e($ph['brand']) ?> <?= Security::e($ph['model_name']) ?> &bull; 
                                            IMEI: <?= Security::e($ph['imei_1']) ?> 
                                            (<?= Security::e($ph['phone_type']) ?><?= $ph['storage'] ? ', ' . Security::e($ph['storage']) : '' ?>) &bull; 
                                            ₹ <?= number_format((float)($ph['expected_sale_price'] ?: $ph['default_sale_price'] ?: 0), 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Selling Price (₹)</label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="number" id="inp-phone-price" step="0.01" min="0" placeholder="0.00" class="form-control" style="font-weight: 700;">
                                    <button type="button" id="btn-add-phone-row" class="btn btn-primary" style="white-space: nowrap;">
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Accessory Box -->
                    <div id="box-add-acc" style="display: none;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 10px;">
                            Select Non-Serialized Accessory Product:
                        </div>
                        <div class="grid grid-cols-4" style="gap: 12px; align-items: end;">
                            <div style="grid-column: span 2;">
                                <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Product Catalog Item</label>
                                <select id="sel-acc-id" class="form-control" style="font-size: 0.85rem;">
                                    <option value="">-- Choose accessory product --</option>
                                    <?php foreach ($accessories as $acc): ?>
                                        <option 
                                            value="<?= (int)$acc['id'] ?>"
                                            data-brand="<?= Security::e($acc['brand']) ?>"
                                            data-model="<?= Security::e($acc['model_name']) ?>"
                                            data-stock="<?= (int)$acc['stock_qty'] ?>"
                                            data-price="<?= Security::e((string)($acc['default_sale_price'] ?: '0.00')) ?>"
                                        >
                                            <?= Security::e($acc['brand']) ?> <?= Security::e($acc['model_name']) ?> &bull; 
                                            In Stock: <?= (int)$acc['stock_qty'] ?> units &bull; 
                                            ₹ <?= number_format((float)($acc['default_sale_price'] ?: 0), 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Quantity</label>
                                <input type="number" id="inp-acc-qty" min="1" value="1" class="form-control">
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Unit Price (₹)</label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="number" id="inp-acc-price" step="0.01" min="0" placeholder="0.00" class="form-control" style="font-weight: 700;">
                                    <button type="button" id="btn-add-acc-row" class="btn btn-primary" style="white-space: nowrap;">
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cart Ledger Table -->
            <div class="card">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div class="card-title">
                        <span>&#128221;</span> Cart Items
                    </div>
                    <span id="cart-item-counter" style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">0 items in cart</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); text-align: left;">
                                    <th style="padding: 10px 14px;">#</th>
                                    <th style="padding: 10px 14px;">Type</th>
                                    <th style="padding: 10px 14px;">Item Description</th>
                                    <th style="padding: 10px 14px; text-align: center; width: 100px;">Qty</th>
                                    <th style="padding: 10px 14px; text-align: right; width: 130px;">Unit Price (₹)</th>
                                    <th style="padding: 10px 14px; text-align: right; width: 130px;">Subtotal (₹)</th>
                                    <th style="padding: 10px 14px; text-align: center; width: 60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cart-tbody">
                                <tr id="cart-empty-row">
                                    <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                        <div>&#128722;</div>
                                        <div style="font-size: 0.85rem; margin-top: 4px;">No items added yet. Select a phone or accessory above to start checkout.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Settlement, Discounts & Payment -->
        <div style="grid-column: span 1;">
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128176;</span> Payment &amp; Checkout Settlement
                    </div>
                </div>
                <div class="card-body" style="padding: 16px;">
                    <!-- Date & Time Row -->
                    <div class="grid grid-cols-2" style="gap: 10px; margin-bottom: 14px;">
                        <div>
                            <label for="sale_date" class="form-label" style="font-size: 0.8rem; font-weight: 600;">Sale Date <span style="color: #dc2626;">*</span></label>
                            <input type="date" id="sale_date" name="sale_date" value="<?= Security::e($todayDate) ?>" class="form-control" style="font-size: 0.85rem;" required>
                        </div>
                        <div>
                            <label for="sale_time" class="form-label" style="font-size: 0.8rem; font-weight: 600;">Time</label>
                            <input type="time" id="sale_time" name="sale_time" value="<?= Security::e(substr($nowTime, 0, 5)) ?>" class="form-control" style="font-size: 0.85rem;">
                        </div>
                    </div>

                    <!-- Gross Subtotal -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dashed var(--border-color);">
                        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Cart Subtotal:</span>
                        <span id="txt-subtotal" style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);">₹ 0.00</span>
                    </div>

                    <!-- Discount Section -->
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label for="discount_amount" class="form-label" style="font-size: 0.8rem; font-weight: 600; margin: 0;">
                                Sale Discount (₹)
                            </label>
                            <?php if (!$canApplyDiscounts): ?>
                                <span style="font-size: 0.7rem; color: #dc2626; font-weight: 600;">Permission required</span>
                            <?php endif; ?>
                        </div>
                        <input 
                            type="number" 
                            id="discount_amount" 
                            name="discount_amount" 
                            step="0.01" 
                            min="0" 
                            value="0.00" 
                            class="form-control" 
                            style="font-size: 0.95rem; font-weight: 700; color: #b45309;"
                            <?= !$canApplyDiscounts ? 'readonly title="You do not have permission to apply discounts"' : '' ?>
                        >
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
                            Allocated proportionally across line items.
                        </div>
                    </div>

                    <!-- Final Payable Net Total -->
                    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 700;">Final Payable Total</div>
                        <div id="txt-final-total" style="font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-top: 2px;">
                            ₹ 0.00
                        </div>
                    </div>

                    <!-- Initial Payment -->
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label for="initial_payment" class="form-label" style="font-size: 0.8rem; font-weight: 600; margin: 0;">
                                Payment Received Now (₹)
                            </label>
                            <button type="button" id="btn-pay-full" class="btn btn-outline btn-sm" style="font-size: 0.7rem; padding: 1px 6px;">
                                Paid in Full
                            </button>
                        </div>
                        <input 
                            type="number" 
                            id="initial_payment" 
                            name="initial_payment" 
                            step="0.01" 
                            min="0" 
                            value="0.00" 
                            class="form-control" 
                            style="font-size: 1.1rem; font-weight: 700; color: #047857;"
                        >
                    </div>

                    <!-- Payment Mode & Reference -->
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label for="payment_mode" class="form-label" style="font-size: 0.8rem; font-weight: 600;">Payment Mode</label>
                        <select id="payment_mode" name="payment_mode" class="form-control" style="font-size: 0.85rem;">
                            <option value="CASH">Cash</option>
                            <option value="UPI">UPI (GPay / PhonePe / Paytm)</option>
                            <option value="CARD">Debit / Credit Card</option>
                            <option value="BANK_TRANSFER">Bank Transfer (NEFT/IMPS)</option>
                            <option value="CHEQUE">Cheque</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="transaction_ref" class="form-label" style="font-size: 0.8rem; font-weight: 600;">Transaction / UTR Reference</label>
                        <input type="text" id="transaction_ref" name="transaction_ref" placeholder="Optional transaction reference" class="form-control" style="font-size: 0.85rem;">
                    </div>

                    <!-- Calculated Remaining Balance Due (Customer Credit / Udhar) -->
                    <div style="padding: 10px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 6px; margin-bottom: 16px;">
                        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #be123c; font-weight: 700;">Remaining Balance Due (Udhar)</div>
                        <div id="txt-due-balance" style="font-size: 1.3rem; font-weight: 800; color: #dc2626; margin-top: 2px;">
                            ₹ 0.00
                        </div>
                        <div style="font-size: 0.7rem; color: #be123c; margin-top: 2px;">
                            Status: <span id="txt-pstatus" class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.7rem; padding: 1px 6px;">UNPAID</span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="notes" class="form-label" style="font-size: 0.8rem; font-weight: 600;">Internal Counter Remarks</label>
                        <textarea id="notes" name="notes" rows="2" class="form-control" style="font-size: 0.85rem;" placeholder="Optional counter remarks..."></textarea>
                    </div>

                    <!-- Submit Actions -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="submit" id="btn-submit-sale" class="btn btn-primary" style="width: 100%; font-weight: 700; padding: 12px; font-size: 1rem;">
                            <span>&#128722;</span> Complete Counter Sale
                        </button>
                        <a href="/sales" class="btn btn-outline" style="width: 100%; text-align: center;">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabPhone = document.getElementById('tab-btn-phone');
    const tabAcc = document.getElementById('tab-btn-acc');
    const boxPhone = document.getElementById('box-add-phone');
    const boxAcc = document.getElementById('box-add-acc');

    const selPhone = document.getElementById('sel-phone-id');
    const inpPhonePrice = document.getElementById('inp-phone-price');
    const btnAddPhone = document.getElementById('btn-add-phone-row');

    const selAcc = document.getElementById('sel-acc-id');
    const inpAccQty = document.getElementById('inp-acc-qty');
    const inpAccPrice = document.getElementById('inp-acc-price');
    const btnAddAcc = document.getElementById('btn-add-acc-row');

    const cartTbody = document.getElementById('cart-tbody');
    const cartEmptyRow = document.getElementById('cart-empty-row');
    const cartCounter = document.getElementById('cart-item-counter');

    const txtSubtotal = document.getElementById('txt-subtotal');
    const inpDiscount = document.getElementById('discount_amount');
    const txtFinalTotal = document.getElementById('txt-final-total');
    const inpInitialPay = document.getElementById('initial_payment');
    const txtDueBalance = document.getElementById('txt-due-balance');
    const txtPstatus = document.getElementById('txt-pstatus');
    const btnPayFull = document.getElementById('btn-pay-full');

    let rowCounter = 0;
    const addedPhoneIds = new Set();

    // Toggle between Phone and Accessory selector
    tabPhone.addEventListener('click', function() {
        tabPhone.classList.remove('btn-outline');
        tabPhone.classList.add('btn-primary');
        tabAcc.classList.remove('btn-primary');
        tabAcc.classList.add('btn-outline');
        boxPhone.style.display = 'block';
        boxAcc.style.display = 'none';
    });

    tabAcc.addEventListener('click', function() {
        tabAcc.classList.remove('btn-outline');
        tabAcc.classList.add('btn-primary');
        tabPhone.classList.remove('btn-primary');
        tabPhone.classList.add('btn-outline');
        boxPhone.style.display = 'none';
        boxAcc.style.display = 'block';
    });

    // Populate default price when phone selected
    selPhone.addEventListener('change', function() {
        const opt = selPhone.options[selPhone.selectedIndex];
        if (opt && opt.dataset.price) {
            inpPhonePrice.value = parseFloat(opt.dataset.price).toFixed(2);
        } else {
            inpPhonePrice.value = '';
        }
    });

    // Populate default price and stock when accessory selected
    selAcc.addEventListener('change', function() {
        const opt = selAcc.options[selAcc.selectedIndex];
        if (opt && opt.dataset.price) {
            inpAccPrice.value = parseFloat(opt.dataset.price).toFixed(2);
            const stock = parseInt(opt.dataset.stock, 10);
            inpAccQty.max = stock > 0 ? stock : 1;
            inpAccQty.value = 1;
        } else {
            inpAccPrice.value = '';
        }
    });

    // Add Phone row to cart
    btnAddPhone.addEventListener('click', function() {
        const opt = selPhone.options[selPhone.selectedIndex];
        const phoneId = parseInt(selPhone.value, 10);
        const price = parseFloat(inpPhonePrice.value);

        if (!phoneId) {
            alert('Please select a valid serialized phone from stock.');
            return;
        }
        if (addedPhoneIds.has(phoneId)) {
            alert('This handset episode is already added to the cart.');
            return;
        }
        if (isNaN(price) || price < 0) {
            alert('Please enter a valid non-negative selling price.');
            return;
        }

        const brand = opt.dataset.brand;
        const model = opt.dataset.model;
        const imei = opt.dataset.imei;
        const specs = opt.dataset.specs;
        const pType = opt.dataset.type;

        rowCounter++;
        addedPhoneIds.add(phoneId);
        if (cartEmptyRow) cartEmptyRow.style.display = 'none';

        const tr = document.createElement('tr');
        tr.id = `cart-row-${rowCounter}`;
        tr.dataset.rowId = rowCounter;
        tr.dataset.itemType = 'SERIALIZED_PHONE';
        tr.dataset.phoneId = phoneId;
        tr.style.borderBottom = '1px solid var(--border-color)';

        tr.innerHTML = `
            <td style="padding: 10px 14px; font-weight: 600; color: var(--text-muted);">${rowCounter}</td>
            <td style="padding: 10px 14px;">
                <span class="badge" style="background: #eff6ff; color: #1e40af; font-size: 0.75rem; padding: 2px 6px; font-weight: 700;">HANDSET</span>
                <input type="hidden" name="items[${rowCounter}][item_type]" value="SERIALIZED_PHONE">
                <input type="hidden" name="items[${rowCounter}][phone_id]" value="${phoneId}">
            </td>
            <td style="padding: 10px 14px;">
                <div style="font-weight: 700; color: var(--text-main);">${escapeHtml(brand)} ${escapeHtml(model)}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                    IMEI: ${escapeHtml(imei)} &bull; ${escapeHtml(pType)}${specs ? ' &bull; ' + escapeHtml(specs) : ''}
                </div>
            </td>
            <td style="padding: 10px 14px; text-align: center; font-weight: 700;">
                1
                <input type="hidden" name="items[${rowCounter}][quantity]" value="1">
            </td>
            <td style="padding: 10px 14px; text-align: right;">
                <input 
                    type="number" 
                    name="items[${rowCounter}][unit_sale_price]" 
                    step="0.01" 
                    min="0" 
                    value="${price.toFixed(2)}" 
                    class="form-control inp-row-price" 
                    style="width: 110px; font-weight: 700; text-align: right; margin-left: auto;"
                    required
                >
            </td>
            <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: var(--text-main);" class="td-row-subtotal">
                ₹ ${price.toFixed(2)}
            </td>
            <td style="padding: 10px 14px; text-align: center;">
                <button type="button" class="btn btn-outline btn-sm btn-del-row" data-row-id="${rowCounter}" data-phone-id="${phoneId}" style="color: #dc2626; border-color: #fca5a5; padding: 2px 6px;">
                    &times;
                </button>
            </td>
        `;

        cartTbody.appendChild(tr);

        // Reset selector
        selPhone.value = '';
        inpPhonePrice.value = '';
        recalcTotals();
    });

    // Add Accessory row to cart
    btnAddAcc.addEventListener('click', function() {
        const opt = selAcc.options[selAcc.selectedIndex];
        const prodId = parseInt(selAcc.value, 10);
        const qty = parseInt(inpAccQty.value, 10);
        const price = parseFloat(inpAccPrice.value);

        if (!prodId) {
            alert('Please select an accessory product.');
            return;
        }
        if (isNaN(qty) || qty <= 0) {
            alert('Quantity must be at least 1.');
            return;
        }
        const stock = parseInt(opt.dataset.stock, 10);
        if (qty > stock) {
            alert(`Requested quantity (${qty}) exceeds on-hand stock (${stock}).`);
            return;
        }
        if (isNaN(price) || price < 0) {
            alert('Please enter a valid non-negative selling price.');
            return;
        }

        const brand = opt.dataset.brand;
        const model = opt.dataset.model;
        const lineSubtotal = (qty * price).toFixed(2);

        rowCounter++;
        if (cartEmptyRow) cartEmptyRow.style.display = 'none';

        const tr = document.createElement('tr');
        tr.id = `cart-row-${rowCounter}`;
        tr.dataset.rowId = rowCounter;
        tr.dataset.itemType = 'ACCESSORY';
        tr.dataset.productId = prodId;
        tr.style.borderBottom = '1px solid var(--border-color)';

        tr.innerHTML = `
            <td style="padding: 10px 14px; font-weight: 600; color: var(--text-muted);">${rowCounter}</td>
            <td style="padding: 10px 14px;">
                <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 2px 6px; font-weight: 700;">ACCESSORY</span>
                <input type="hidden" name="items[${rowCounter}][item_type]" value="ACCESSORY">
                <input type="hidden" name="items[${rowCounter}][product_id]" value="${prodId}">
            </td>
            <td style="padding: 10px 14px;">
                <div style="font-weight: 700; color: var(--text-main);">${escapeHtml(brand)} ${escapeHtml(model)}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Stock Available: ${stock} units</div>
            </td>
            <td style="padding: 10px 14px; text-align: center;">
                <input 
                    type="number" 
                    name="items[${rowCounter}][quantity]" 
                    min="1" 
                    max="${stock}" 
                    value="${qty}" 
                    class="form-control inp-row-qty" 
                    style="width: 70px; text-align: center; margin: 0 auto; font-weight: 700;"
                    required
                >
            </td>
            <td style="padding: 10px 14px; text-align: right;">
                <input 
                    type="number" 
                    name="items[${rowCounter}][unit_sale_price]" 
                    step="0.01" 
                    min="0" 
                    value="${price.toFixed(2)}" 
                    class="form-control inp-row-price" 
                    style="width: 110px; font-weight: 700; text-align: right; margin-left: auto;"
                    required
                >
            </td>
            <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: var(--text-main);" class="td-row-subtotal">
                ₹ ${lineSubtotal}
            </td>
            <td style="padding: 10px 14px; text-align: center;">
                <button type="button" class="btn btn-outline btn-sm btn-del-row" data-row-id="${rowCounter}" style="color: #dc2626; border-color: #fca5a5; padding: 2px 6px;">
                    &times;
                </button>
            </td>
        `;

        cartTbody.appendChild(tr);

        // Reset inputs
        selAcc.value = '';
        inpAccPrice.value = '';
        inpAccQty.value = 1;
        recalcTotals();
    });

    // Delete row event delegation
    cartTbody.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-del-row');
        if (!btn) return;

        const rowId = btn.dataset.rowId;
        const phoneId = parseInt(btn.dataset.phoneId, 10);
        if (phoneId) {
            addedPhoneIds.delete(phoneId);
        }

        const row = document.getElementById(`cart-row-${rowId}`);
        if (row) {
            row.remove();
        }

        recalcTotals();
    });

    // Handle price or qty modifications in cart
    cartTbody.addEventListener('input', function(e) {
        if (e.target.classList.contains('inp-row-qty') || e.target.classList.contains('inp-row-price')) {
            const tr = e.target.closest('tr');
            if (tr) {
                const qtyInput = tr.querySelector('.inp-row-qty');
                const priceInput = tr.querySelector('.inp-row-price');
                const subtotalTd = tr.querySelector('.td-row-subtotal');

                const qty = qtyInput ? parseInt(qtyInput.value, 10) || 0 : 1;
                const price = parseFloat(priceInput.value) || 0.0;
                const lineTotal = (qty * price).toFixed(2);

                if (subtotalTd) {
                    subtotalTd.textContent = `₹ ${lineTotal}`;
                }
                recalcTotals();
            }
        }
    });

    // Discount change listener
    inpDiscount.addEventListener('input', recalcTotals);

    // Initial pay change listener
    inpInitialPay.addEventListener('input', recalcDue);

    // Pay full button
    btnPayFull.addEventListener('click', function() {
        const finalTot = parseFloat(txtFinalTotal.textContent.replace('₹ ', '')) || 0;
        inpInitialPay.value = finalTot.toFixed(2);
        recalcDue();
    });

    function recalcTotals() {
        const rows = cartTbody.querySelectorAll('tr[data-row-id]');
        let subtotal = 0.0;

        rows.forEach(tr => {
            const qtyInput = tr.querySelector('.inp-row-qty');
            const priceInput = tr.querySelector('.inp-row-price');
            const qty = qtyInput ? parseInt(qtyInput.value, 10) || 0 : 1;
            const price = parseFloat(priceInput.value) || 0.0;
            subtotal += (qty * price);
        });

        cartCounter.textContent = `${rows.length} ${rows.length === 1 ? 'item' : 'items'} in cart`;
        if (rows.length === 0) {
            if (cartEmptyRow) cartEmptyRow.style.display = '';
        }

        txtSubtotal.textContent = `₹ ${subtotal.toFixed(2)}`;

        let discount = parseFloat(inpDiscount.value) || 0.0;
        if (discount > subtotal) {
            discount = subtotal;
            inpDiscount.value = discount.toFixed(2);
        }

        const finalTotal = Math.max(0, subtotal - discount);
        txtFinalTotal.textContent = `₹ ${finalTotal.toFixed(2)}`;

        recalcDue();
    }

    function recalcDue() {
        const finalTot = parseFloat(txtFinalTotal.textContent.replace('₹ ', '')) || 0.0;
        let pay = parseFloat(inpInitialPay.value) || 0.0;

        if (pay > finalTot) {
            pay = finalTot;
            inpInitialPay.value = pay.toFixed(2);
        }

        const due = Math.max(0, finalTot - pay);
        txtDueBalance.textContent = `₹ ${due.toFixed(2)}`;

        if (due <= 0.0001 && finalTot > 0) {
            txtPstatus.textContent = 'PAID';
            txtPstatus.style.background = '#ecfdf5';
            txtPstatus.style.color = '#047857';
        } else if (pay > 0.0001) {
            txtPstatus.textContent = 'PARTIAL';
            txtPstatus.style.background = '#fffbeb';
            txtPstatus.style.color = '#b45309';
        } else {
            txtPstatus.textContent = 'UNPAID';
            txtPstatus.style.background = '#fef2f2';
            txtPstatus.style.color = '#b91c1c';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>
