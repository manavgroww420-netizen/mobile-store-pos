<?php
/**
 * New Customer Service Repair View
 * File: views/repairs/create_customer.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/repairs" class="btn btn-outline btn-sm">&larr; Back to Repairs</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    New Customer Service Repair
                </h1>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 4px 0 0 0;">
                    Record customer hardware/software repair job, parts consumed, and payment receipt.
                </p>
            </div>
        </div>
        <div style="font-size: 0.8rem; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 6px 14px; border-radius: 6px; font-weight: 600;">
            <span>&#9432;</span> Internal Repair Ledger &bull; Not a Customer Portal
        </div>
    </div>
</div>

<form id="repair-customer-form" action="/repairs/customer" method="POST">
    <?= Security::csrfField() ?>

    <div class="grid grid-cols-3" style="gap: 24px; align-items: start;">
        <!-- Left 2 Columns: Customer Info, Fault Description, Parts Used -->
        <div style="grid-column: span 2;">
            
            <!-- Customer & Device Identification -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128100;</span> Customer &amp; Handset Details
                    </div>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label for="customer_name" class="form-label" style="font-weight: 600;">Customer Full Name <span style="color: #dc2626;">*</span></label>
                            <input 
                                type="text" 
                                id="customer_name" 
                                name="customer_name" 
                                class="form-control" 
                                placeholder="e.g. Subir Karmakar" 
                                required
                                value="<?= Security::e($formData['customer_name'] ?? '') ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="customer_phone" class="form-label" style="font-weight: 600;">Customer Phone Number <span style="color: #dc2626;">*</span></label>
                            <input 
                                type="tel" 
                                id="customer_phone" 
                                name="customer_phone" 
                                class="form-control" 
                                placeholder="e.g. 9830123456" 
                                required
                                value="<?= Security::e($formData['customer_phone'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2" style="gap: 16px;">
                        <div class="form-group">
                            <label for="device_model" class="form-label" style="font-weight: 600;">Device Model / Make <span style="color: #dc2626;">*</span></label>
                            <input 
                                type="text" 
                                id="device_model" 
                                name="device_model" 
                                class="form-control" 
                                placeholder="e.g. Samsung Galaxy M31 / iPhone 11" 
                                required
                                value="<?= Security::e($formData['device_model'] ?? '') ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="repair_date" class="form-label" style="font-weight: 600;">Job Date <span style="color: #dc2626;">*</span></label>
                            <input 
                                type="date" 
                                id="repair_date" 
                                name="repair_date" 
                                class="form-control" 
                                required
                                value="<?= Security::e($formData['repair_date'] ?? date('Y-m-d')) ?>"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Repair Description & Work Performed -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128295;</span> Work Performed &amp; Fault Description
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="repair_description" class="form-label" style="font-weight: 600;">Work Performed / Issue Solved <span style="color: #dc2626;">*</span></label>
                        <textarea 
                            id="repair_description" 
                            name="repair_description" 
                            class="form-control" 
                            rows="3" 
                            placeholder="e.g. Display combo replacement, charging flex replacement, and speaker mesh cleaning." 
                            required
                        ><?= Security::e($formData['repair_description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="notes" class="form-label" style="font-weight: 600;">Internal Technician / Shop Notes (Optional)</label>
                        <input 
                            type="text" 
                            id="notes" 
                            name="notes" 
                            class="form-control" 
                            placeholder="e.g. Customer provided back cover; old broken screen returned to customer."
                            value="<?= Security::e($formData['notes'] ?? '') ?>"
                        >
                    </div>
                </div>
            </div>

            <!-- Dual-Source Parts Ledger -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div class="card-title">
                        <span>&#9881;</span> Repair Parts Consumed (In-House Stock vs External Purchase)
                    </div>
                    <button type="button" id="btn-add-part" class="btn btn-sm btn-outline">
                        <span>&#43;</span> Add Part Line
                    </button>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table" style="width: 100%; margin-bottom: 0;" id="parts-table">
                            <thead>
                                <tr style="background: #f8fafc; font-size: 0.8rem; border-bottom: 1px solid var(--border-color);">
                                    <th style="padding: 10px 14px; width: 180px;">Source Type</th>
                                    <th style="padding: 10px 14px;">Part Description / Catalog Product</th>
                                    <th style="padding: 10px 14px; width: 80px; text-align: center;">Qty</th>
                                    <?php if ($canViewCosts): ?>
                                        <th style="padding: 10px 14px; width: 110px; text-align: right;">Unit Cost</th>
                                        <th style="padding: 10px 14px; width: 110px; text-align: right;">Subtotal</th>
                                    <?php endif; ?>
                                    <th style="padding: 10px 14px; width: 50px; text-align: center;"></th>
                                </tr>
                            </thead>
                            <tbody id="parts-tbody">
                                <!-- Dynamic rows inserted by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div id="no-parts-msg" style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                        No parts added. (Labour-only repairs are supported). Click "Add Part Line" above if spare parts were used.
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Financial Calculations & Settlement -->
        <div>
            <div class="card" style="position: sticky; top: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128176;</span> Financial Settlement
                    </div>
                </div>
                <div class="card-body">
                    
                    <!-- Cost Breakdown -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.85rem;">
                            <span style="color: var(--text-muted);">Parts Cost:</span>
                            <span id="disp-parts-cost" style="font-weight: 600;">₹ 0.00</span>
                        </div>

                        <div class="form-group" style="margin-bottom: 8px;">
                            <label for="labour_amount" class="form-label" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 2px;">
                                Labour / Service Cost (Incurred)
                            </label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 10px; top: 7px; color: var(--text-muted); font-size: 0.85rem;">₹</span>
                                <input 
                                    type="number" 
                                    id="labour_amount" 
                                    name="labour_amount" 
                                    class="form-control" 
                                    style="padding-left: 26px; font-size: 0.9rem;" 
                                    step="0.01" 
                                    min="0" 
                                    value="<?= Security::e($formData['labour_amount'] ?? '0.00') ?>"
                                >
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; border-top: 1px dashed #cbd5e1; padding-top: 6px; font-size: 0.9rem; font-weight: 700;">
                            <span>Total Repair Cost:</span>
                            <span id="disp-total-cost" style="color: #334155;">₹ 0.00</span>
                        </div>
                    </div>

                    <!-- Customer Billing -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="amount_charged" class="form-label" style="font-weight: 700; color: #1e293b;">
                            Amount Charged to Customer <span style="color: #dc2626;">*</span>
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 10px; font-size: 1.1rem; font-weight: 700; color: #1e293b;">₹</span>
                            <input 
                                type="number" 
                                id="amount_charged" 
                                name="amount_charged" 
                                class="form-control" 
                                style="padding-left: 32px; font-size: 1.15rem; font-weight: 700; color: #1e293b;" 
                                step="0.01" 
                                min="0" 
                                placeholder="0.00" 
                                required
                                value="<?= Security::e($formData['amount_charged'] ?? '') ?>"
                            >
                        </div>
                        <div class="help-text" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                            Total price quoted and billed to the walk-in customer.
                        </div>
                    </div>

                    <!-- Initial Payment Received -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="initial_payment" class="form-label" style="font-weight: 600;">
                            Initial Payment Received Now
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 10px; top: 8px; color: var(--text-muted); font-size: 0.9rem;">₹</span>
                            <input 
                                type="number" 
                                id="initial_payment" 
                                name="initial_payment" 
                                class="form-control" 
                                style="padding-left: 28px;" 
                                step="0.01" 
                                min="0" 
                                value="<?= Security::e($formData['initial_payment'] ?? '0.00') ?>"
                            >
                        </div>
                    </div>

                    <div id="payment-details-box" style="margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="payment_mode" class="form-label" style="font-size: 0.8rem; font-weight: 600;">Payment Mode</label>
                            <select id="payment_mode" name="payment_mode" class="form-control" style="font-size: 0.85rem;">
                                <option value="CASH">CASH (Counter Cash Drawer)</option>
                                <option value="UPI">UPI / QR Code</option>
                                <option value="CARD">Debit / Credit Card</option>
                                <option value="BANK_TRANSFER">Bank Transfer / IMPS</option>
                                <option value="CHEQUE">Cheque</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="transaction_ref" class="form-label" style="font-size: 0.8rem; font-weight: 600;">Transaction Ref / UTR (Optional)</label>
                            <input type="text" id="transaction_ref" name="transaction_ref" class="form-control" placeholder="e.g. UPI Ref # or Cheque #">
                        </div>
                    </div>

                    <!-- Balance Due & Profit Indicators -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 14px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-weight: 600; color: #475569;">Customer Due (Balance):</span>
                            <span id="disp-due-amount" style="font-size: 1.1rem; font-weight: 800; color: #059669;">₹ 0.00</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                            <span style="color: #64748b;">Est. Net Profit:</span>
                            <span id="disp-net-profit" style="font-weight: 700; color: #16a34a;">₹ 0.00</span>
                        </div>
                    </div>

                    <button type="submit" id="btn-submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; font-weight: 700;">
                        <span>&#10003;</span> Finalize &amp; Save Repair Record
                    </button>

                </div>
            </div>
        </div>
    </div>
</form>

<!-- Catalog Data JSON for In-House Parts -->
<script>
    const CATALOG_STOCK_PARTS = <?= json_encode($stockParts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const CAN_VIEW_COSTS = <?= $canViewCosts ? 'true' : 'false' ?>;

    let partRowIndex = 0;

    function formatINR(val) {
        const num = parseFloat(val) || 0.00;
        return '₹ ' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function addPartRow() {
        partRowIndex++;
        const tbody = document.getElementById('parts-tbody');
        document.getElementById('no-parts-msg').style.display = 'none';

        const tr = document.createElement('tr');
        tr.id = 'part-row-' + partRowIndex;
        tr.dataset.rowId = partRowIndex;
        tr.style.borderBottom = '1px solid var(--border-color)';

        let optionsHtml = '<option value="">-- Select Catalog Part --</option>';
        CATALOG_STOCK_PARTS.forEach(p => {
            optionsHtml += `<option value="${p.id}" data-cost="${p.avg_unit_cost}" data-stock="${p.current_quantity}">
                ${p.name} [Stock: ${p.current_quantity}]
            </option>`;
        });

        tr.innerHTML = `
            <td style="padding: 8px 14px;">
                <select name="part_source[]" class="form-control part-source-select" style="font-size: 0.85rem;" onchange="handleSourceChange(${partRowIndex})">
                    <option value="IN_HOUSE_STOCK">In-House Stock</option>
                    <option value="EXTERNAL_PURCHASE">External Purchase</option>
                </select>
            </td>
            <td style="padding: 8px 14px;">
                <div id="container-in-house-${partRowIndex}">
                    <select name="part_product_id[]" class="form-control part-product-select" style="font-size: 0.85rem;" onchange="handleProductSelect(${partRowIndex})">
                        ${optionsHtml}
                    </select>
                </div>
                <div id="container-external-${partRowIndex}" style="display: none;">
                    <input type="text" name="part_name[]" class="form-control" placeholder="Enter market part name (e.g. Original Display)" style="font-size: 0.85rem;">
                </div>
            </td>
            <td style="padding: 8px 14px; text-align: center;">
                <input type="number" name="part_qty[]" class="form-control part-qty-input" value="1" min="1" style="font-size: 0.85rem; width: 60px; text-align: center; margin: 0 auto;" oninput="recalcTotals()">
            </td>
            ${CAN_VIEW_COSTS ? `
                <td style="padding: 8px 14px; text-align: right;">
                    <input type="number" name="part_unit_cost[]" class="form-control part-cost-input" value="0.00" min="0" step="0.01" style="font-size: 0.85rem; width: 90px; text-align: right; margin-left: auto;" oninput="recalcTotals()" readonly>
                </td>
                <td style="padding: 8px 14px; text-align: right; font-weight: 600; font-size: 0.85rem;" class="part-subtotal-display">
                    ₹ 0.00
                </td>
            ` : `
                <input type="hidden" name="part_unit_cost[]" class="part-cost-input" value="0.00">
            `}
            <td style="padding: 8px 14px; text-align: center;">
                <button type="button" class="btn btn-sm btn-outline" style="color: #dc2626; border-color: #fecaca; padding: 2px 6px;" onclick="removePartRow(${partRowIndex})">&times;</button>
            </td>
        `;

        tbody.appendChild(tr);
        recalcTotals();
    }

    function removePartRow(rowId) {
        const tr = document.getElementById('part-row-' + rowId);
        if (tr) {
            tr.remove();
        }
        const tbody = document.getElementById('parts-tbody');
        if (tbody.children.length === 0) {
            document.getElementById('no-parts-msg').style.display = 'block';
        }
        recalcTotals();
    }

    function handleSourceChange(rowId) {
        const tr = document.getElementById('part-row-' + rowId);
        if (!tr) return;

        const srcSelect = tr.querySelector('.part-source-select');
        const inHouseDiv = document.getElementById('container-in-house-' + rowId);
        const extDiv = document.getElementById('container-external-' + rowId);
        const costInput = tr.querySelector('.part-cost-input');

        if (srcSelect.value === 'IN_HOUSE_STOCK') {
            inHouseDiv.style.display = 'block';
            extDiv.style.display = 'none';
            if (costInput) {
                costInput.readOnly = true;
            }
            handleProductSelect(rowId);
        } else {
            inHouseDiv.style.display = 'none';
            extDiv.style.display = 'block';
            if (costInput) {
                costInput.readOnly = false;
                costInput.value = '0.00';
            }
        }
        recalcTotals();
    }

    function handleProductSelect(rowId) {
        const tr = document.getElementById('part-row-' + rowId);
        if (!tr) return;

        const prodSelect = tr.querySelector('.part-product-select');
        const costInput = tr.querySelector('.part-cost-input');

        const opt = prodSelect.options[prodSelect.selectedIndex];
        if (opt && opt.dataset.cost) {
            costInput.value = parseFloat(opt.dataset.cost).toFixed(2);
        } else {
            costInput.value = '0.00';
        }
        recalcTotals();
    }

    function recalcTotals() {
        let totalPartsCost = 0.00;
        const rows = document.querySelectorAll('#parts-tbody tr');

        rows.forEach(tr => {
            const qtyInput = tr.querySelector('.part-qty-input');
            const costInput = tr.querySelector('.part-cost-input');
            const subtotalDisp = tr.querySelector('.part-subtotal-display');

            const qty = parseInt(qtyInput ? qtyInput.value : 0) || 0;
            const unitCost = parseFloat(costInput ? costInput.value : 0) || 0.00;
            const subtotal = qty * unitCost;

            totalPartsCost += subtotal;

            if (subtotalDisp) {
                subtotalDisp.textContent = formatINR(subtotal);
            }
        });

        const labourInput = document.getElementById('labour_amount');
        const labour = parseFloat(labourInput ? labourInput.value : 0) || 0.00;
        const totalRepairCost = totalPartsCost + labour;

        const chargedInput = document.getElementById('amount_charged');
        const charged = parseFloat(chargedInput ? chargedInput.value : 0) || 0.00;

        const initialPayInput = document.getElementById('initial_payment');
        let initialPay = parseFloat(initialPayInput ? initialPayInput.value : 0) || 0.00;

        // Display updates
        const dispParts = document.getElementById('disp-parts-cost');
        if (dispParts) dispParts.textContent = formatINR(totalPartsCost);

        const dispTotalCost = document.getElementById('disp-total-cost');
        if (dispTotalCost) dispTotalCost.textContent = formatINR(totalRepairCost);

        // Due amount calculation
        const due = Math.max(0, charged - initialPay);
        const dispDue = document.getElementById('disp-due-amount');
        if (dispDue) {
            dispDue.textContent = formatINR(due);
            dispDue.style.color = (due > 0) ? '#dc2626' : '#059669';
        }

        // Net profit calculation
        const netProfit = charged - totalRepairCost;
        const dispProfit = document.getElementById('disp-net-profit');
        if (dispProfit) {
            dispProfit.textContent = formatINR(netProfit);
            dispProfit.style.color = (netProfit >= 0) ? '#16a34a' : '#dc2626';
        }
    }

    document.getElementById('btn-add-part').addEventListener('click', addPartRow);
    document.getElementById('labour_amount').addEventListener('input', recalcTotals);
    document.getElementById('amount_charged').addEventListener('input', recalcTotals);
    document.getElementById('initial_payment').addEventListener('input', recalcTotals);

    document.getElementById('repair-customer-form').addEventListener('submit', function(e) {
        const charged = parseFloat(document.getElementById('amount_charged').value) || 0.00;
        const initialPay = parseFloat(document.getElementById('initial_payment').value) || 0.00;

        if (initialPay > charged) {
            e.preventDefault();
            alert('Initial payment (₹' + initialPay.toFixed(2) + ') cannot exceed amount charged (₹' + charged.toFixed(2) + ').');
            return false;
        }
    });

    // Run initial calculation
    recalcTotals();
</script>
