<?php
/**
 * Mobile Handsets Stock & Aging Report
 * File: views/reports/mobiles.php
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/reports">Reports Hub</a></li>
                <li class="breadcrumb-item active">Mobile Handsets</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Mobile Handsets Stock &amp; Aging Report</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="?status=<?= urlencode($statusFilter) ?>&type=<?= urlencode($typeFilter) ?>&q=<?= urlencode($search) ?>&export=csv" class="btn btn-outline-success btn-sm">
            <span>&#128190;</span> Export CSV
        </a>
        <a href="/reports" class="btn btn-outline-secondary btn-sm">Back to Hub</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/reports/mobiles" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="IMEI, brand, model..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="IN_STOCK" <?= $statusFilter === 'IN_STOCK' ? 'selected' : '' ?>>In Stock Only</option>
                    <option value="SOLD" <?= $statusFilter === 'SOLD' ? 'selected' : '' ?>>Sold Handsets</option>
                    <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Handsets (Any Status)</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="ALL" <?= $typeFilter === 'ALL' ? 'selected' : '' ?>>All Phone Types</option>
                    <option value="NEW" <?= $typeFilter === 'NEW' ? 'selected' : '' ?>>New Sealed Mobiles</option>
                    <option value="SECOND_HAND" <?= $typeFilter === 'SECOND_HAND' ? 'selected' : '' ?>>Second-Hand / Refurbished</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <a href="/reports/mobiles" class="btn btn-sm btn-outline-secondary" title="Reset">&#10006;</a>
            </div>
        </form>
    </div>
</div>

<!-- Summaries Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Handsets Volume</div>
                <div class="h4 mb-0 font-weight-bold"><?= number_format($totalHandsets) ?></div>
                <div class="small text-muted mt-1">Filtered inventory records</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-info shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">New vs Second-Hand</div>
                <div class="h4 mb-0 font-weight-bold"><?= $newCount ?> <span class="text-muted" style="font-size: 0.85rem;">New</span> / <?= $usedCount ?> <span class="text-muted" style="font-size: 0.85rem;">Used</span></div>
                <div class="small text-muted mt-1">Category split</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Aged Stock (&gt;30 Days)</div>
                <div class="h4 mb-0 font-weight-bold text-danger"><?= number_format($aged30Count) ?></div>
                <div class="small text-muted mt-1">Slow moving inventory handsets</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Handset Valuation</div>
                <div class="h4 mb-0 font-weight-bold text-success">
                    <?php if ($canViewCosts): ?>
                        ₹ <?= number_format((float)$totalCostBasis, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Capitalized acquisition &amp; repair basis</div>
            </div>
        </div>
    </div>
</div>

<!-- Handsets Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Mobile Handsets Inventory (<?= number_format($totalHandsets) ?> devices)</h6>
        <span class="text-xs text-muted">Luhn-Verified IMEI Tracking</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 20%;">IMEI 1 &amp; Details</th>
                        <th style="width: 20%;">Model &amp; Brand</th>
                        <th style="width: 12%;">Type &amp; Specs</th>
                        <th style="width: 12%;">Intake Date</th>
                        <th style="width: 10%;" class="text-center">Aging</th>
                        <?php if ($canViewCosts): ?>
                            <th style="width: 14%;" class="text-end">Cost Basis (₹)</th>
                        <?php endif; ?>
                        <th style="width: 12%;" class="text-end">Expected (₹)</th>
                        <th style="width: 10%;" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($phones)): ?>
                        <tr>
                            <td colspan="<?= $canViewCosts ? 8 : 7 ?>" class="text-center py-5 text-muted">No mobile handsets found matching criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($phones as $ph): ?>
                            <tr>
                                <td>
                                    <strong class="font-monospace text-dark"><?= htmlspecialchars($ph['imei_1']) ?></strong>
                                    <?php if (!empty($ph['imei_2'])): ?>
                                        <div class="text-xs text-muted font-monospace">IMEI 2: <?= htmlspecialchars($ph['imei_2']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($ph['brand'] . ' ' . $ph['model_name']) ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($ph['vendor_name'] ?? 'Direct Buyback') ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $ph['phone_type'] === 'NEW' ? 'bg-primary' : 'bg-warning text-dark' ?>">
                                        <?= htmlspecialchars($ph['phone_type']) ?>
                                    </span>
                                    <div class="text-xs text-muted mt-1">
                                        <?= htmlspecialchars(trim(($ph['ram'] ?? '') . '/' . ($ph['storage'] ?? '') . ' ' . ($ph['color'] ?? ''))) ?>
                                    </div>
                                </td>
                                <td class="small font-monospace"><?= htmlspecialchars($ph['purchase_date']) ?></td>
                                <td class="text-center">
                                    <?php if ($ph['status'] === 'IN_STOCK'): ?>
                                        <span class="badge <?= $ph['days_in_inventory'] > 30 ? 'bg-danger' : 'bg-light text-dark border' ?>">
                                            <?= $ph['days_in_inventory'] ?> days
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canViewCosts): ?>
                                    <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$ph['total_cost_basis'], 2) ?></td>
                                <?php endif; ?>
                                <td class="text-end font-monospace text-muted">
                                    <?= (float)($ph['expected_sale_price'] ?? 0) > 0 ? ('₹ ' . number_format((float)$ph['expected_sale_price'], 2)) : '—' ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $ph['status'] === 'IN_STOCK' ? 'bg-success' : ($ph['status'] === 'SOLD' ? 'bg-secondary' : 'bg-danger') ?>">
                                        <?= htmlspecialchars($ph['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
