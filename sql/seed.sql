-- ============================================================================
-- MOBILE STORE POS & INVENTORY MANAGEMENT SYSTEM — Seed Data
-- Initial Seed Data (Production-Ready)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Seed Permissions Catalog
-- ----------------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `description`, `category`) VALUES
('view_purchase_costs', 'View wholesale vendor purchase prices and inventory cost basis', 'FINANCIAL'),
('view_vendor_costs', 'View vendor balance dues, payment ledger, and wholesale invoices', 'FINANCIAL'),
('view_profit_margins', 'View item profit, transaction gross profit, and service repair margins', 'FINANCIAL'),
('view_financial_reports', 'Access overall Profit & Loss and business summary reporting', 'FINANCIAL'),
('view_sensitive_id', 'View unmasked customer Aadhaar and personal identification records', 'SECURITY'),
('manage_purchases', 'Create, edit, and receive vendor purchase consignments', 'INVENTORY'),
('manage_sales', 'Process counter sales, customer discounts, and formal returns', 'SALES'),
('perform_sales', 'Process retail counter sales and record customer payments', 'SALES'),
('apply_discounts', 'Apply ad-hoc item or bill-level discounts at checkout', 'SALES'),
('manage_repairs', 'Log refurbishment costs and customer repair financial records', 'REPAIRS'),
('manage_inventory', 'Perform stock adjustments, opening stock intake, and catalog management', 'INVENTORY'),
('void_transactions', 'Void accidental sales or purchase transactions (Owner only)', 'ADMIN'),
('manage_users', 'Create and manage user accounts and assign staff permissions', 'ADMIN')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`), `category` = VALUES(`category`);

-- ----------------------------------------------------------------------------
-- 2. Seed Default Product Categories
-- ----------------------------------------------------------------------------
INSERT INTO `categories` (`name`, `slug`, `description`, `is_active`) VALUES
('Smartphones', 'smartphones', 'New and second-hand touch-screen smartphones', 1),
('Feature Phones', 'feature-phones', 'Keypad and basic mobile phones', 1),
('Earphones & TWS', 'earphones-tws', 'Wired earphones, neckbands, and true wireless earbuds', 1),
('Chargers & Adapters', 'chargers-adapters', 'Wall charging bricks, fast chargers, and car chargers', 1),
('Cables', 'cables', 'Type-C, Micro-USB, Lightning, and AUX connection cables', 1),
('Cases & Covers', 'cases-covers', 'Protective back cases, flip covers, and silicone bumpers', 1),
('Screen Protectors', 'screen-protectors', 'Tempered glass, UV glass, and screen guard films', 1),
('Power Banks', 'power-banks', 'Portable battery charging packs and external power banks', 1),
('Speakers', 'speakers', 'Bluetooth portable speakers and mini soundbars', 1),
('Smartwatches & Bands', 'smartwatches-bands', 'Smart wearable watches and fitness activity trackers', 1),
('Memory Cards & Storage', 'memory-cards', 'MicroSD cards, USB OTG pen drives, and card readers', 1),
('Batteries', 'batteries', 'Replacement mobile phone batteries', 1),
('Spare Parts & Components', 'spare-parts', 'Combo displays, charging flexes, ICs, and repair parts', 1),
('Other Accessories', 'other-accessories', 'Mobile stands, ring lights, converters, and other accessories', 1)
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`), `is_active` = VALUES(`is_active`);

-- ----------------------------------------------------------------------------
-- 3. Seed Initial Administrator Account
-- IMPORTANT SECURITY NOTICE:
-- The password hash below is a PLACEHOLDER representing the temporary setup token:
-- "ChangeMeNow@AMH2026!"
-- Generated using: password_hash('ChangeMeNow@AMH2026!', PASSWORD_BCRYPT, ['cost' => 12])
--
-- BEFORE PRODUCTION DEPLOYMENT:
-- Generate a new secure password using the following command in terminal:
-- php -r "echo password_hash('YOUR_REAL_STRONG_PASSWORD', PASSWORD_DEFAULT);"
-- and update the password_hash column for username 'admin'.
-- ----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
(1, 'admin', '$2b$12$AkoFAXA0REjlXxZRycTsY.LQeU4Y3B9/H4O.n4RaOEcLJbPeB8oFi', 'Store Administrator (Owner)', 'OWNER', 1)
ON DUPLICATE KEY UPDATE `role` = 'OWNER', `is_active` = 1;

-- ----------------------------------------------------------------------------
-- 4. Grant Full System Privileges to Initial Administrator
-- ----------------------------------------------------------------------------
INSERT INTO `user_permissions` (`user_id`, `permission_name`, `granted_by`)
SELECT 1, `name`, 1 FROM `permissions`
ON DUPLICATE KEY UPDATE `granted_by` = 1;

SET FOREIGN_KEY_CHECKS = 1;
