<?php
/**
 * Printable Profit & Loss Statement
 * File: views/profit_loss/print.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P&amp;L Statement — <?= htmlspecialchars($periodLabel) ?> — <?= htmlspecialchars($appName ?? 'Mobile Hub POS') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <style>
        body {
            background-color: #fff !important;
            color: #111 !important;
            font-size: 13px;
        }
        .print-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        .print-header {
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #ddd !important;
            padding: 6px 10px !important;
        }
        @media print {
            .no-print { display: none !important; }
            .print-container { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Action Bar -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light border rounded">
            <div>
                <strong>Print Preview Mode</strong> — Financial Statement
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-primary btn-sm">&#128438; Print Now</button>
                <button onclick="window.close()" class="btn btn-outline-secondary btn-sm">Close</button>
            </div>
        </div>

        <!-- Shop Header -->
        <div class="print-header d-flex justify-content-between align-items-end">
            <div>
                <h2 class="mb-0 font-weight-bold" style="letter-spacing: 0.5px;"><?= htmlspecialchars($appName ?? 'Mobile Hub POS') ?></h2>
                <div style="font-size: 13px; color: #555;"><?= htmlspecialchars($appTagline ?? 'Internal Financial Accounting') ?></div>
                <div style="font-size: 12px; color: #666; margin-top: 3px;">
                    <?= htmlspecialchars($appAddress ?? 'Retail Counter') ?>
                </div>
            </div>
            <div class="text-end">
                <h4 class="mb-0 text-dark font-weight-bold">PROFIT &amp; LOSS STATEMENT</h4>
                <div style="font-size: 12px; color: #555; margin-top: 2px;">
                    <strong>Period:</strong> <?= htmlspecialchars($periodLabel) ?>
                </div>
                <div style="font-size: 11px; color: #777;">
                    Generated on <?= date('d M Y, h:i A') ?>
                </div>
            </div>
        </div>

        <!-- High-level Summary Box -->
        <div class="row g-2 mb-4 text-center">
            <div class="col-3">
                <div class="p-2 border rounded">
                    <div class="text-xs text-uppercase text-muted">Net Operating Revenue</div>
                    <div class="h5 mb-0 font-weight-bold">₹ <?= number_format((float)$netOperatingRevenue, 2) ?></div>
                </div>
            </div>
            <div class="col-3">
                <div class="p-2 border rounded">
                    <div class="text-xs text-uppercase text-muted">Total COGS / Direct Costs</div>
                    <div class="h5 mb-0 font-weight-bold">₹ <?= number_format((float)$totalDirectCosts, 2) ?></div>
                </div>
            </div>
            <div class="col-3">
                <div class="p-2 border rounded bg-light">
                    <div class="text-xs text-uppercase text-muted font-weight-bold">Net Gross Profit</div>
                    <div class="h5 mb-0 font-weight-bold text-success">₹ <?= number_format((float)$totalGrossProfit, 2) ?></div>
                </div>
            </div>
            <div class="col-3">
                <div class="p-2 border rounded">
                    <div class="text-xs text-uppercase text-muted">Gross Margin</div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format((float)$overallMarginPct, 2) ?>%</div>
                </div>
            </div>
        </div>

        <!-- Formal P&L Statement Table -->
        <table class="table table-bordered align-middle mb-4">
            <thead class="table-light text-xs text-uppercase">
                <tr>
                    <th style="width: 60%;">Financial Item / Account Line</th>
                    <th style="width: 20%;" class="text-end">Subtotal (₹)</th>
                    <th style="width: 20%;" class="text-end font-weight-bold">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light font-weight-bold">
                    <td colspan="3">1. OPERATING REVENUE &amp; TURNOVER</td>
                </tr>
                <tr>
                    <td class="ps-3">Gross Counter Sales (<?= $salesCount ?> transaction records)</td>
                    <td class="text-end font-monospace">₹ <?= number_format((float)$grossSalesRev, 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-3">Customer Service Repairs Charges (<?= $repairsCount ?> repair jobs)</td>
                    <td class="text-end font-monospace">₹ <?= number_format((float)$grossRepairsRev, 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold">
                    <td class="ps-3">Total Gross Inward Turnover</td>
                    <td></td>
                    <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$totalGrossTurnover, 2) ?></td>
                </tr>
                <tr>
                    <td class="ps-3 text-danger">Less: Customer Returns &amp; Cash Refunds</td>
                    <td class="text-end font-monospace text-danger">- ₹ <?= number_format((float)$totalRefundAmount, 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold bg-light">
                    <td>NET OPERATING REVENUE</td>
                    <td></td>
                    <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$netOperatingRevenue, 2) ?></td>
                </tr>

                <tr class="table-light font-weight-bold">
                    <td colspan="3" class="pt-3">2. COST OF GOODS SOLD (COGS) &amp; DIRECT EXPENSES</td>
                </tr>
                <tr>
                    <td class="ps-3">Gross Realized Sales Inventory Cost Basis</td>
                    <td class="text-end font-monospace">₹ <?= number_format((float)$grossSalesCogs, 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-3">Less: Restocked Inventory Cost Reversals</td>
                    <td class="text-end font-monospace">- ₹ <?= number_format((float)$totalRestockedCogs, 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-3">Plus: Scrapped / Defective Return Losses</td>
                    <td class="text-end font-monospace">+ ₹ <?= number_format((float)$totalScrappedCogs, 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold">
                    <td class="ps-3">Net Realized Counter Sales COGS</td>
                    <td></td>
                    <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$effectiveSalesCogs, 2) ?></td>
                </tr>
                <tr>
                    <td class="ps-3">Customer Repair Parts Incurred</td>
                    <td class="text-end font-monospace">₹ <?= number_format((float)$repairsPartsCost, 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-3">Customer Repair Labour &amp; Technician Costs</td>
                    <td class="text-end font-monospace">₹ <?= number_format((float)$repairsLabourCost, 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold">
                    <td class="ps-3">Total Customer Repair Direct Costs</td>
                    <td></td>
                    <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$repairsTotalCost, 2) ?></td>
                </tr>
                <tr class="fw-bold bg-light">
                    <td>TOTAL DIRECT OPERATING COSTS</td>
                    <td></td>
                    <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$totalDirectCosts, 2) ?></td>
                </tr>

                <tr class="table-light font-weight-bold">
                    <td colspan="3" class="pt-3">3. PROFIT &amp; MARGIN SUMMARY</td>
                </tr>
                <tr>
                    <td class="ps-3">Realized Counter Sales Gross Profit</td>
                    <td class="text-end font-monospace">₹ <?= number_format((float)$realizedSalesProfit, 2) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-3">Customer Repair Service Margin</td>
                    <td class="text-end font-monospace">₹ <?= number_format((float)$repairsNetProfit, 2) ?></td>
                    <td></td>
                </tr>
                <tr class="fw-bold bg-light" style="font-size: 14px;">
                    <td class="font-weight-bold">CONSOLIDATED SHOP GROSS PROFIT</td>
                    <td></td>
                    <td class="text-end font-monospace font-weight-bold text-success">
                        ₹ <?= number_format((float)$totalGrossProfit, 2) ?>
                    </td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Overall Operating Gross Margin Percentage</td>
                    <td></td>
                    <td class="text-end font-monospace font-weight-bold">
                        <?= number_format((float)$overallMarginPct, 2) ?>%
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Signatures & Verification -->
        <div class="row pt-5 mt-5">
            <div class="col-6">
                <div style="border-top: 1px solid #333; width: 200px; padding-top: 5px; font-size: 12px;">
                    <strong>Prepared / Checked By:</strong><br>
                    <?= htmlspecialchars(\AMH\Auth::user()['full_name'] ?? 'Authorized Operator') ?>
                </div>
            </div>
            <div class="col-6 text-end">
                <div style="border-top: 1px solid #333; width: 200px; margin-left: auto; padding-top: 5px; font-size: 12px;">
                    <strong>Proprietor / Authorized Signatory</strong><br>
                    <?= htmlspecialchars($appName ?? 'Mobile Hub POS') ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
