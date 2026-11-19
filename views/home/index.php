<?php
/**
 * Foundation Status & System Health View
 * File: views/home/index.php
 */
?>

<!-- Shop Header Information Banner -->
<div class="card" style="border-left: 4px solid var(--primary); margin-bottom: 24px;">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--primary); margin-bottom: 4px;">
                <?= e($appConfig['name']) ?>
            </h2>
            <p style="color: var(--text-muted); margin: 0; font-size: 0.85rem;">
                <strong>Address:</strong> <?= e($appConfig['address']) ?> &bull; 
                <strong>Phone:</strong> <?= e($appConfig['phone']) ?>
            </p>
        </div>
        <div>
            <span class="badge badge-success" style="font-size: 0.85rem; padding: 6px 12px;">
                &#9679; Internal Management Foundation Active
            </span>
        </div>
    </div>
</div>

<!-- Metric Stat Highlights -->
<div class="grid grid-cols-4" style="margin-bottom: 24px;">
    <div class="metric-card">
        <div class="metric-icon blue">&#9881;</div>
        <div class="metric-data">
            <div class="metric-label">Backend Engine</div>
            <div class="metric-value">PHP <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?></div>
            <div class="metric-sub"><?= e(PHP_VERSION) ?></div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon <?= $dbConnected ? 'green' : 'amber' ?>">&#128451;</div>
        <div class="metric-data">
            <div class="metric-label">Database Connection</div>
            <div class="metric-value" style="font-size: 1.15rem; color: <?= $dbConnected ? 'var(--success)' : 'var(--warning)' ?>;">
                <?= $dbConnected ? 'Connected (PDO)' : 'Ready / Configured' ?>
            </div>
            <div class="metric-sub"><?= $dbConnected ? "{$tableCount} Tables Installed" : 'Awaiting MySQL Daemon' ?></div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon purple">&#128337;</div>
        <div class="metric-data">
            <div class="metric-label">Shop Timezone</div>
            <div class="metric-value" style="font-size: 1.25rem;">IST (Kolkata)</div>
            <div class="metric-sub"><?= date('h:i:s A, d M Y') ?></div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon amber">&#128274;</div>
        <div class="metric-data">
            <div class="metric-label">Security &amp; Session</div>
            <div class="metric-value" style="font-size: 1.25rem;">Hardened</div>
            <div class="metric-sub">Strict SameSite &bull; CSRF Active</div>
        </div>
    </div>
</div>

<!-- Core Application Foundation Status Cards -->
<div class="grid grid-cols-2" style="margin-bottom: 24px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">&#10004; Architectural Core Subsystems</span>
            <span class="badge badge-info">Production Ready</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <tbody>
                    <tr>
                        <td><strong>Central PDO Connection</strong></td>
                        <td>Strict prepared statements, `ERRMODE_EXCEPTION`, UTF-8 mb4 unicode</td>
                        <td><span class="badge badge-success">&#10003; Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>Atomic Transactions</strong></td>
                        <td>`Database::transaction()` with automatic rollback and error logging</td>
                        <td><span class="badge badge-success">&#10003; Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>Central Error Handling</strong></td>
                        <td>Global exception catcher, server-side log, credential suppression in prod</td>
                        <td><span class="badge badge-success">&#10003; Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>Session Security</strong></td>
                        <td>`HttpOnly`, `SameSite=Strict`, `session.use_strict_mode`, regeneration on login</td>
                        <td><span class="badge badge-success">&#10003; Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>CSRF Defense Engine</strong></td>
                        <td>Timing-safe `hash_equals()` validation on state-changing requests</td>
                        <td><span class="badge badge-success">&#10003; Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>Financial Calculation</strong></td>
                        <td>Fixed 2-decimal precision (no floating-point rounding errors)</td>
                        <td><span class="badge badge-success">&#10003; Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>Aadhaar Data Protection</strong></td>
                        <td>AES-256-GCM authenticated encryption + Salted SHA-256 + Masked display</td>
                        <td><span class="badge badge-success">&#10003; Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>Audit Trail Foundation</strong></td>
                        <td>Append-only event recorder targeting the `audit_logs` table</td>
                        <td><span class="badge badge-success">&#10003; Active</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">&#128220; 12 Locked Operational Modules</span>
            <span class="badge badge-secondary">Next Phases</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="table table-striped">
                <tbody>
                    <tr><td><strong>1. Dashboard</strong></td><td>Real-time daily sales, stock valuation &amp; alerts</td></tr>
                    <tr><td><strong>2. Products &amp; Categories</strong></td><td>Dynamic catalog (Phones vs. Accessories)</td></tr>
                    <tr><td><strong>3. Inventory Management</strong></td><td>Non-serialized stock with immutable ledger</td></tr>
                    <tr><td><strong>4. New Mobile Management</strong></td><td>Intake, IMEI assignment &amp; stock tracking</td></tr>
                    <tr><td><strong>5. Second-Hand Mobiles</strong></td><td>Dynamic cost basis &amp; condition grading</td></tr>
                    <tr><td><strong>6. IMEI Management</strong></td><td>Hardware lifecycle &amp; instant 15-digit lookup</td></tr>
                    <tr><td><strong>7. Purchase Records</strong></td><td>Vendor consignments with payable credit ledger</td></tr>
                    <tr><td><strong>8. Sales Records</strong></td><td>Counter checkout with proportional discount COGS</td></tr>
                    <tr><td><strong>9. Repair Records</strong></td><td>Stock refurbishment costs &amp; customer services</td></tr>
                    <tr><td><strong>10. Vendor Management</strong></td><td>Suppliers directory &amp; wholesale records</td></tr>
                    <tr><td><strong>11. Profit &amp; Loss</strong></td><td>Database-driven gross operating profit</td></tr>
                    <tr><td><strong>12. Reports</strong></td><td>Audits, inventory turnover &amp; business summaries</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Component Foundation Interactive Test Area -->
<div class="card">
    <div class="card-header">
        <span class="card-title">&#9874; Interactive UI &amp; Component Foundation Testing</span>
    </div>
    <div class="card-body">
        <p style="color: var(--text-muted); margin-bottom: 16px;">
            Test the client-side Vanilla CSS and Vanilla JavaScript components (modals, buttons, forms, and scanner helpers).
        </p>

        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
            <button type="button" class="btn btn-primary" data-modal-open="demoModal">
                Open Test Modal
            </button>

            <button type="button" class="btn btn-secondary" onclick="alert('Secondary button clicked');">
                Secondary Button
            </button>

            <button type="button" class="btn btn-success" onclick="alert('Success action triggered');">
                Success Action
            </button>

            <button type="button" class="btn btn-danger" onclick="alert('Danger action triggered');">
                Danger Action
            </button>
        </div>

        <div class="form-group" style="max-width: 480px;">
            <label class="form-label" for="testImei">Test Barcode / IMEI Scanner Input (15 digits):</label>
            <input type="text" id="testImei" class="form-control" placeholder="Scan or type 15-digit IMEI..." data-barcode-input maxlength="15">
            <span class="form-help">Pressing 'Enter' on a handheld scanner will trigger a scan event rather than submitting the form.</span>
        </div>
    </div>
</div>

<!-- Test Modal Dialog Component -->
<div class="modal-backdrop" id="demoModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title"><?= e($appName ?? "Mobile Hub POS") ?> — System Dialog</h3>
            <button type="button" class="alert-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p>This is a lightweight Vanilla CSS/JS modal component created for dialogs, item selectors, and prompt workflows.</p>
            <div class="alert alert-info" style="margin-top: 12px;">
                Zero framework dependencies. Lightweight, fast, and responsive.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
            <button type="button" class="btn btn-primary" data-modal-close>Acknowledge</button>
        </div>
    </div>
</div>
