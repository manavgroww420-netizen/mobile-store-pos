<?php
/**
 * Application Sidebar Navigation Partial
 * File: views/partials/sidebar.php
 *
 * Outlines the 12 locked modules of Mobile Hub POS.
 */
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<aside class="app-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <span><?= e($appName ?? 'Mobile Hub POS') ?></span>
        </div>
        <div class="sidebar-tagline"><?= e($appTagline ?? 'Mobile Store & Inventory') ?></div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Core Operations</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/" class="nav-link <?= ($currentUri === '/' || $currentUri === '' || $currentUri === '/dashboard') ? 'active' : '' ?>">
                    <span class="nav-icon">&#9638;</span>
                    <span>1. Executive Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/sales" class="nav-link <?= (str_starts_with($currentUri, '/sales') && !str_contains($currentUri, '/return')) ? 'active' : '' ?>">
                    <span class="nav-icon">&#128722;</span>
                    <span>8. Sales Records</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/returns" class="nav-link <?= (str_starts_with($currentUri, '/returns') || str_contains($currentUri, '/return')) ? 'active' : '' ?>">
                    <span class="nav-icon">&#8617;</span>
                    <span>Customer Returns</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/repairs" class="nav-link <?= str_starts_with($currentUri, '/repairs') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128295;</span>
                    <span>9. Repair Records</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/ledgers/customers" class="nav-link <?= str_starts_with($currentUri, '/ledgers/customers') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128214;</span>
                    <span>Customer Khata (Udhar)</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-title">Inventory & Devices</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/inventory" class="nav-link <?= str_starts_with($currentUri, '/inventory') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128230;</span>
                    <span>3. Stock Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/products" class="nav-link <?= str_starts_with($currentUri, '/products') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128196;</span>
                    <span>2. Product Catalog</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/categories" class="nav-link <?= str_starts_with($currentUri, '/categories') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128194;</span>
                    <span>Categories Master</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/phones/new" class="nav-link <?= str_starts_with($currentUri, '/phones/new') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128241;</span>
                    <span>4. New Mobiles</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/phones/used" class="nav-link <?= str_starts_with($currentUri, '/phones/used') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128260;</span>
                    <span>5. Second-Hand Mobiles</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/imei" class="nav-link <?= str_starts_with($currentUri, '/imei') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128269;</span>
                    <span>6. IMEI Registry</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-title">Procurement &amp; Accounting</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/purchases" class="nav-link <?= str_starts_with($currentUri, '/purchases') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128229;</span>
                    <span>7. Purchase Records</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/vendors" class="nav-link <?= (str_starts_with($currentUri, '/vendors') && !str_starts_with($currentUri, '/ledgers/vendors')) ? 'active' : '' ?>">
                    <span class="nav-icon">&#127970;</span>
                    <span>10. Vendors</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/ledgers/vendors" class="nav-link <?= str_starts_with($currentUri, '/ledgers/vendors') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128203;</span>
                    <span>Vendor Payables (Dena)</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/ledgers/payments" class="nav-link <?= str_starts_with($currentUri, '/ledgers/payments') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128176;</span>
                    <span>Payments Master</span>
                </a>
            </li>
            <?php if (has_permission('view_financial_reports')): ?>
            <li class="nav-item">
                <a href="/profit-loss" class="nav-link <?= str_starts_with($currentUri, '/profit-loss') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128200;</span>
                    <span>11. Profit &amp; Loss</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/reports" class="nav-link <?= str_starts_with($currentUri, '/reports') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128202;</span>
                    <span>12. Reports</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php if (has_permission('manage_users')): ?>
        <div class="nav-section-title">Administration</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/users" class="nav-link <?= str_starts_with($currentUri, '/users') ? 'active' : '' ?>">
                    <span class="nav-icon">&#128101;</span>
                    <span>Staff &amp; Permissions</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div><strong>Status:</strong> Active Internal Node</div>
        <div style="margin-top: 4px; opacity: 0.7;"><?= e($appAddress ?? 'Retail Counter') ?></div>
    </div>
</aside>
