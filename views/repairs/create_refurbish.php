<?php
/**
 * New Used Phone Refurbishment View
 * File: views/repairs/create_refurbish.php
 */
use AMH\Security;
?>

<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/repairs" class="btn btn-outline btn-sm">&larr; Back to Repairs</a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    Used-Phone Refurbishment Record
                </h1>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 4px 0 0 0;">
                    Log parts and labour invested into shop-owned second-hand handsets to capitalize into inventory cost basis.
                </p>
            </div>
        </div>
        <div style="font-size: 0.8rem; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 6px 14px; border-radius: 6px; font-weight: 600;">
            <span>&#9881;</span> Inventory Cost Basis Capitalization &bull; Not Customer Revenue
        </div>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 6px; font-size: 0.875rem;">
    <strong>Financial Accounting Note:</strong> All parts consumed and labour incurred during refurbishment are directly capitalized into the second-hand handset's cost basis (recorded in <code>phone_direct_costs</code> and added to <code>phones.total_cost_basis</code>). No revenue is recognized now; profit is realized upon final sale.
</div>

<form id="refurbish-form" action="/repairs/refurbish" method="POST">
    <?= Security::csrfField() ?>

    <div class="grid grid-cols-3" style="gap: 24px; align-items: start;">
        <!-- Left 2 Columns: Handset Selection, Work Done, Parts Used -->
        <div style="grid-column: span 2;">
            
            <!-- Handset Selection Card -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128241;</span> Target In-Stock Second-Hand Handset
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="phone_id" class="form-label" style="font-weight: 600;">Select Second-Hand Phone <span style="color: #dc2626;">*</span></label>
                        <select id="phone_id" name="phone_id" class="form-control" required style="font-size: 0.95rem;">
                            <option value="">-- Choose from available second-hand phones in stock --</option>
                            <?php foreach ($phones as $ph): ?>
                                <option 
                                    value="<?= (int)$ph['id'] ?>" 
                                    data-brand="<?= Security::e($ph['brand']) ?>"
                                    data-model="<?= Security::e($ph['model']) ?>"
                                    data-imei="<?= Security::e($ph['imei_1']) ?>"
                                    data-cost="<?= (float)$ph['total_cost_basis'] ?>"
                                    data-condition="<?= Security::e($ph['device_condition'] ?? 'Used') ?>"
                                    <?= ($formData['phone_id'] === (int)$ph['id']) ? 'selected' : '' ?>
                                >
                                    <?= Security::e($ph['brand'] . ' ' . $ph['model']) ?> 
                                    &bull; IMEI: <?= Security::e($ph['imei_1']) ?> 
                                    <?= $ph['color'] ? ' (' . Security::e($ph['color']) . ')' : '' ?>
                                    &bull; Cost Basis: ₹ <?= number_format((float)$ph['total_cost_basis'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="selected-phone-box" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                        <div class="grid grid-cols-3" style="gap: 12px; font-size: 0.85rem;">
                            <div>
                                <span style="color: var(--text-muted);">Model:</span>
                                <div id="ph-card-model" style="font-weight: 700; color: #1e293b;">—</div>
                            </div>
                            <div>
                                <span style="color: var(--text-muted);">IMEI 1:</span>
                                <div id="ph-card-imei" style="font-weight: 600; font-family: monospace;">—</div>
                            </div>
                            <div>
                                <span style="color: var(--text-muted);">Current Cost Basis:</span>
                                <div id="ph-card-cost" style="font-weight: 700; color: #2563eb;">₹ 0.00</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="repair_date" class="form-label" style="font-weight: 600;">Refurbishment Date <span style="color: #dc2626;">*</span></label>
                        <input 
                            type="date" 
                            id="repair_date" 
                            name="repair_date" 
                            class="form-control" 
                            required 
                            value="<?= Security::e($formData['repair_date'] ?? date('Y-m-d')) ?>"
                            style="width: 200px;"
                        >
                    </div>
                </div>
            </div>

            <!-- Refurbishment Work Description -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128295;</span> Refurbishment Work Performed
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="repair_description" class="form-label" style="font-weight: 600;">Work Summary <span style="color: #dc2626;">*</span></label>
                        <textarea 
                            id="repair_description" 
                            name="repair_description" 
                            class="form-control" 
                            rows="3" 
                            placeholder="e.g. Battery replacement, new back glass panel, camera glass buffing, ultrasonic cleaning." 
                            required
                        ><?= Security::e($formData['repair_description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="notes" class="form-label" style="font-weight: 600;">Technician / Quality Notes (Optional)</label>
                        <input 
                            type="text" 
                            id="notes" 
                            name="notes" 
                            class="form-control" 
                            placeholder="e.g. Battery health restored to 100%; device fully tested and ready for resale display."
                            value="<?= Security::e($formData['notes'] ?? '') ?>"
                        >
                    </div>
                </div>
            </div>

            <!-- Dual-Source Parts Ledger -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div class="card-title">
                        <span>&#9881;</span> Refurbishment Parts (In-House Inventory vs Local Market)
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
                                    <th style="padding: 10px 14px; width: 110px; text-align: right;">Unit Cost</th>
                                    <th style="padding: 10px 14px; width: 110px; text-align: right;">Subtotal</th>
                                    <th style="padding: 10px 14px; width: 50px; text-align: center;"></th>
                                </tr>
                            </thead>
                            <tbody id="parts-tbody">
                                <!-- Dynamic rows inserted by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div id="no-parts-msg" style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                        No parts added. (Labour-only servicing is supported). Click "Add Part Line" above if replacement parts were installed.
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Capitalization Breakdown -->
        <div>
            <div class="card" style="position: sticky; top: 20px;">
                <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color);">
                    <div class="card-title">
                        <span>&#128200;</span> Cost Basis Capitalization
                    </div>
                </div>
                <div class="card-body">
                    
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 20px;">
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.85rem;">
                            <span style="color: var(--text-muted);">Current Handset Basis:</span>
                            <span id="disp-current-basis" style="font-weight: 700; color: #1e293b;">₹ 0.00</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.85rem;">
                            <span style="color: var(--text-muted);">Parts Added:</span>
                            <span id="disp-parts-added" style="font-weight: 600; color: #0284c7;">+ ₹ 0.00</span>
                        </div>

                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="labour_amount" class="form-label" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 2px;">
                                Servicing / Labour Cost Added
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

                        <div style="display: flex; justify-content: space-between; border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-bottom: 8px; font-size: 0.9rem; font-weight: 700;">
                            <span>Total Refurbishment Added:</span>
                            <span id="disp-refurb-total" style="color: #0284c7;">₹ 0.00</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; border-top: 2px solid #059669; padding-top: 8px; font-size: 1.05rem; font-weight: 800;">
                            <span style="color: #065f46;">New Total Cost Basis:</span>
                            <span id="disp-new-basis" style="color: #059669;">₹ 0.00</span>
                        </div>
                    </div>

                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 20px; line-height: 1.4;">
                        Upon saving, <code>phone_direct_costs</code> will record this expenditure and the handset episode's <code>total_cost_basis</code> will be automatically recalculated.
                    </div>

                    <button type="submit" id="btn-submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; font-weight: 700;">
                        <span>&#10003;</span> Capitalize &amp; Save Refurbishment
                    </button>

                </div>
            </div>
        </div>
    </div>
</form>

<!-- Catalog Data JSON for In-House Parts -->
<script>
    const CATALOG_STOCK_PARTS = <?= json_encode($stockParts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let partRowIndex = 0;

    function formatINR(val) {
        const num = parseFloat(val) || 0.00;
        return '₹ ' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateSelectedPhoneInfo() {
        const select = document.getElementById('phone_id');
        const box = document.getElementById('selected-phone-box');
        const opt = select.options[select.selectedIndex];

        if (opt && opt.value) {
            box.style.display = 'block';
            document.getElementById('ph-card-model').textContent = opt.dataset.brand + ' ' + opt.dataset.model;
            document.getElementById('ph-card-imei').textContent = opt.dataset.imei;
            document.getElementById('ph-card-cost').textContent = formatINR(opt.dataset.cost);
        } else {
            box.style.display = 'none';
        }
        recalcTotals();
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
                    <input type="text" name="part_name[]" class="form-control" placeholder="Enter market part name (e.g. Battery BL-5C)" style="font-size: 0.85rem;">
                </div>
            </td>
            <td style="padding: 8px 14px; text-align: center;">
                <input type="number" name="part_qty[]" class="form-control part-qty-input" value="1" min="1" style="font-size: 0.85rem; width: 60px; text-align: center; margin: 0 auto;" oninput="recalcTotals()">
            </td>
            <td style="padding: 8px 14px; text-align: right;">
                <input type="number" name="part_unit_cost[]" class="form-control part-cost-input" value="0.00" min="0" step="0.01" style="font-size: 0.85rem; width: 90px; text-align: right; margin-left: auto;" oninput="recalcTotals()" readonly>
            </td>
            <td style="padding: 8px 14px; text-align: right; font-weight: 600; font-size: 0.85rem;" class="part-subtotal-display">
                ₹ 0.00
            </td>
            <td style="padding: 8px 14px; text-align: center;">
                <button type="button" class="btn btn-sm btn-outline" style="color: #dc2626; border-color: #fecaca; padding: 2px 6px;" onclick="removePartRow(${partRowIndex})">&times;</button>
            </td>
        `;

        tbody.appendChild(tr);
        recalcTotals();
    }

    function removePartRow(rowId) {
        const tr = document.getElementById('part-row-' + rowId);
        if (tr) tr.remove();
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
            if (costInput) costInput.readOnly = true;
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
        const totalRefurb = totalPartsCost + labour;

        // Current phone cost basis
        const select = document.getElementById('phone_id');
        const opt = select.options[select.selectedIndex];
        const currentBasis = (opt && opt.dataset.cost) ? parseFloat(opt.dataset.cost) : 0.00;

        const newBasis = currentBasis + totalRefurb;

        // Updates
        document.getElementById('disp-current-basis').textContent = formatINR(currentBasis);
        document.getElementById('disp-parts-added').textContent = '+ ' + formatINR(totalPartsCost);
        document.getElementById('disp-refurb-total').textContent = formatINR(totalRefurb);
        document.getElementById('disp-new-basis').textContent = formatINR(newBasis);
    }

    document.getElementById('phone_id').addEventListener('change', updateSelectedPhoneInfo);
    document.getElementById('btn-add-part').addEventListener('click', addPartRow);
    document.getElementById('labour_amount').addEventListener('input', recalcTotals);

    // Initial run
    updateSelectedPhoneInfo();
</script>
