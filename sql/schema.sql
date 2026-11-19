-- ============================================================================
-- MOBILE STORE POS & INVENTORY MANAGEMENT SYSTEM — Database Schema
-- Database Schema Definition (Production-Ready)
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ----------------------------------------------------------------------------
-- 1. Table: users
-- Purpose: System credentials, authentication state, and role assignments.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('OWNER', 'STAFF') NOT NULL DEFAULT 'STAFF',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_users_username` UNIQUE (`username`),
    INDEX `idx_users_role_active` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: permissions
-- Purpose: Master catalog of server-enforced system privileges.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `name` VARCHAR(50) NOT NULL PRIMARY KEY,
    `description` VARCHAR(255) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    INDEX `idx_permissions_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: user_permissions
-- Purpose: Explicit granular permission grants assigned to staff members.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `user_id` INT UNSIGNED NOT NULL,
    `permission_name` VARCHAR(50) NOT NULL,
    `granted_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `permission_name`),
    CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_name`) REFERENCES `permissions` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_up_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: categories
-- Purpose: Dynamic, shop-owner configurable product categories.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_categories_name` UNIQUE (`name`),
    CONSTRAINT `uq_categories_slug` UNIQUE (`slug`),
    INDEX `idx_categories_active_name` (`is_active`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table: products
-- Purpose: Master catalog defining serialized phones and non-serialized accessories.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `brand` VARCHAR(100) NOT NULL,
    `model_name` VARCHAR(150) NOT NULL,
    `item_type` ENUM('SERIALIZED_PHONE', 'ACCESSORY') NOT NULL,
    `sku` VARCHAR(50) DEFAULT NULL,
    `min_stock_alert` INT UNSIGNED NOT NULL DEFAULT 2,
    `default_sale_price` DECIMAL(12,2) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_products_sku` UNIQUE (`sku`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_products_category` (`category_id`),
    INDEX `idx_products_lookup` (`brand`, `model_name`, `item_type`),
    INDEX `idx_products_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Table: inventory_stock
-- Purpose: Current on-hand quantity and weighted average cost for accessories.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_stock` (
    `product_id` INT UNSIGNED NOT NULL PRIMARY KEY,
    `quantity` INT NOT NULL DEFAULT 0,
    `avg_unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `last_restocked_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_inv_qty_positive` CHECK (`quantity` >= 0),
    CONSTRAINT `chk_inv_cost_positive` CHECK (`avg_unit_cost` >= 0.00),
    INDEX `idx_inv_stock_qty` (`quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Table: stock_movements
-- Purpose: Immutable double-entry movement ledger for non-serialized stock.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `movement_type` ENUM('PURCHASE', 'SALE', 'REPAIR_USAGE', 'SALE_RETURN', 'ADJUSTMENT_ADD', 'ADJUSTMENT_SUB', 'OPENING_STOCK') NOT NULL,
    `quantity_change` INT NOT NULL,
    `quantity_before` INT NOT NULL,
    `quantity_after` INT NOT NULL,
    `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reference_table` VARCHAR(50) DEFAULT NULL,
    `reference_id` BIGINT UNSIGNED DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sm_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sm_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_sm_qty_change_not_zero` CHECK (`quantity_change` != 0),
    CONSTRAINT `chk_sm_math_reconciliation` CHECK (`quantity_after` = `quantity_before` + `quantity_change`),
    INDEX `idx_sm_product_date` (`product_id`, `created_at`),
    INDEX `idx_sm_reference` (`reference_table`, `reference_id`),
    INDEX `idx_sm_type` (`movement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Table: devices
-- Purpose: Physical hardware registry. Manages unique IMEI transceivers across time.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devices` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `imei_1` VARCHAR(15) NOT NULL,
    `imei_2` VARCHAR(15) DEFAULT NULL,
    `serial_number` VARCHAR(50) DEFAULT NULL,
    `brand` VARCHAR(100) NOT NULL,
    `model_name` VARCHAR(150) NOT NULL,
    `active_phone_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_devices_imei_1` UNIQUE (`imei_1`),
    CONSTRAINT `uq_devices_imei_2` UNIQUE (`imei_2`),
    CONSTRAINT `uq_devices_active_phone` UNIQUE (`active_phone_id`),
    CONSTRAINT `chk_devices_imei_1_format` CHECK (`imei_1` REGEXP '^[0-9]{15}$'),
    CONSTRAINT `chk_devices_imei_2_format` CHECK (`imei_2` IS NULL OR `imei_2` REGEXP '^[0-9]{15}$'),
    CONSTRAINT `chk_devices_imei_distinct` CHECK (`imei_2` IS NULL OR `imei_1` != `imei_2`),
    INDEX `idx_devices_imei_1` (`imei_1`),
    INDEX `idx_devices_imei_2` (`imei_2`),
    INDEX `idx_devices_brand_model` (`brand`, `model_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Table: vendors
-- Purpose: Supplier and wholesale distributor directory.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vendors` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `business_name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `alt_phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `gstin` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_vendors_phone` (`phone`),
    INDEX `idx_vendors_business_name` (`business_name`),
    INDEX `idx_vendors_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. Table: purchases
-- Purpose: Consignment procurement headers with payable tracking.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_number` VARCHAR(50) NOT NULL,
    `vendor_id` INT UNSIGNED NOT NULL,
    `purchase_date` DATE NOT NULL,
    `vendor_invoice_no` VARCHAR(100) DEFAULT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('PAID', 'PARTIAL', 'UNPAID') NOT NULL DEFAULT 'UNPAID',
    `notes` TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_purchases_number` UNIQUE (`purchase_number`),
    CONSTRAINT `fk_purchases_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_purchases_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_purchases_amounts` CHECK (`total_amount` >= 0.00 AND `paid_amount` >= 0.00 AND `due_amount` >= 0.00),
    INDEX `idx_purchases_vendor_date` (`vendor_id`, `purchase_date`),
    INDEX `idx_purchases_date` (`purchase_date`),
    INDEX `idx_purchases_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. Table: purchase_items
-- Purpose: Line items inside a vendor procurement consignment.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('SERIALIZED_PHONE', 'ACCESSORY') NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `unit_cost` DECIMAL(12,2) NOT NULL,
    `subtotal_cost` DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pi_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_pi_qty` CHECK (`quantity` > 0),
    CONSTRAINT `chk_pi_unit_cost` CHECK (`unit_cost` >= 0.00),
    CONSTRAINT `chk_pi_subtotal` CHECK (`subtotal_cost` = `quantity` * `unit_cost`),
    INDEX `idx_pi_purchase` (`purchase_id`),
    INDEX `idx_pi_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. Table: phones
-- Purpose: Commercial inventory episodes (acquisition -> refurbishment -> sale).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `phones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `phone_type` ENUM('NEW', 'SECOND_HAND') NOT NULL,
    `ram` VARCHAR(20) DEFAULT NULL,
    `storage` VARCHAR(20) DEFAULT NULL,
    `color` VARCHAR(50) DEFAULT NULL,
    `device_condition` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('IN_STOCK', 'SOLD', 'RETURNED_TO_VENDOR', 'SCRAPPED') NOT NULL DEFAULT 'IN_STOCK',
    `acquisition_source` ENUM('VENDOR_PURCHASE', 'CUSTOMER_BUYBACK') NOT NULL,
    `vendor_id` INT UNSIGNED DEFAULT NULL,
    `purchase_item_id` BIGINT UNSIGNED DEFAULT NULL,
    -- Seller Identification & Aadhaar Privacy Protection
    `seller_name` VARCHAR(100) DEFAULT NULL,
    `seller_phone` VARCHAR(20) DEFAULT NULL,
    `seller_id_type` VARCHAR(50) DEFAULT NULL,
    `seller_aadhaar_last4` CHAR(4) DEFAULT NULL,
    `seller_aadhaar_hash` CHAR(64) DEFAULT NULL,
    `seller_aadhaar_encrypted` TEXT DEFAULT NULL,
    `seller_id_number_masked` VARCHAR(50) DEFAULT NULL,
    -- Financial Cost Basis Formulation
    `purchase_date` DATE NOT NULL,
    `purchase_cost` DECIMAL(12,2) NOT NULL,
    `repair_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `other_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_cost_basis` DECIMAL(12,2) NOT NULL,
    `expected_sale_price` DECIMAL(12,2) DEFAULT NULL,
    -- Outward Sale Reference
    `sale_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `sold_at` DATETIME DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_phones_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_phones_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_phones_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_phones_purchase_item` FOREIGN KEY (`purchase_item_id`) REFERENCES `purchase_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_phones_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_phones_costs` CHECK (`purchase_cost` >= 0.00 AND `repair_cost` >= 0.00 AND `other_cost` >= 0.00),
    CONSTRAINT `chk_phones_total_cost` CHECK (`total_cost_basis` = `purchase_cost` + `repair_cost` + `other_cost`),
    INDEX `idx_phones_device` (`device_id`),
    INDEX `idx_phones_status` (`status`),
    INDEX `idx_phones_type_status` (`phone_type`, `status`),
    INDEX `idx_phones_product` (`product_id`),
    INDEX `idx_phones_seller_phone` (`seller_phone`),
    INDEX `idx_phones_aadhaar_hash` (`seller_aadhaar_hash`),
    INDEX `idx_phones_sale_item` (`sale_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Circular Foreign Key Resolution for devices.active_phone_id
-- ----------------------------------------------------------------------------
ALTER TABLE `devices`
    ADD CONSTRAINT `fk_devices_active_phone` 
    FOREIGN KEY (`active_phone_id`) REFERENCES `phones` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- 13. Table: phone_direct_costs
-- Purpose: Normalized direct-cost ledger attached to specific phone episodes.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `phone_direct_costs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phone_id` BIGINT UNSIGNED NOT NULL,
    `cost_type` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `cost_date` DATE NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pdc_phone` FOREIGN KEY (`phone_id`) REFERENCES `phones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pdc_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_pdc_amount` CHECK (`amount` > 0.00),
    INDEX `idx_pdc_phone` (`phone_id`),
    INDEX `idx_pdc_cost_date` (`cost_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. Table: sales
-- Purpose: Counter sales headers (strictly internal, NOT a tax invoice).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_number` VARCHAR(50) NOT NULL,
    `sale_date` DATE NOT NULL,
    `sale_time` TIME NOT NULL,
    `customer_name` VARCHAR(100) DEFAULT NULL,
    `customer_phone` VARCHAR(20) DEFAULT NULL,
    `subtotal_amount` DECIMAL(12,2) NOT NULL,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `final_total_amount` DECIMAL(12,2) NOT NULL,
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('PAID', 'PARTIAL', 'UNPAID') NOT NULL DEFAULT 'UNPAID',
    `total_cost_basis` DECIMAL(12,2) NOT NULL,
    `gross_profit` DECIMAL(12,2) NOT NULL,
    -- Administrative Void / Error Invalidation
    `is_voided` TINYINT(1) NOT NULL DEFAULT 0,
    `voided_by` INT UNSIGNED DEFAULT NULL,
    `voided_at` DATETIME DEFAULT NULL,
    `void_reason` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_sales_number` UNIQUE (`sale_number`),
    CONSTRAINT `fk_sales_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_sales_final_total` CHECK (`final_total_amount` = `subtotal_amount` - `discount_amount`),
    CONSTRAINT `chk_sales_gross_profit` CHECK (`gross_profit` = `final_total_amount` - `total_cost_basis`),
    INDEX `idx_sales_date` (`sale_date`),
    INDEX `idx_sales_customer_phone` (`customer_phone`),
    INDEX `idx_sales_payment_status` (`payment_status`),
    INDEX `idx_sales_voided` (`is_voided`),
    INDEX `idx_sales_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. Table: sale_items
-- Purpose: Sold line items with frozen cost basis and exact discount allocation.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('SERIALIZED_PHONE', 'ACCESSORY') NOT NULL,
    `phone_id` BIGINT UNSIGNED DEFAULT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `unit_cost` DECIMAL(12,2) NOT NULL,
    `unit_sale_price` DECIMAL(12,2) NOT NULL,
    `line_subtotal_price` DECIMAL(12,2) NOT NULL,
    `discount_allocated` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_sale_price` DECIMAL(12,2) NOT NULL,
    `subtotal_cost` DECIMAL(12,2) NOT NULL,
    `item_profit` DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_si_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_si_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_si_phone` FOREIGN KEY (`phone_id`) REFERENCES `phones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_si_qty` CHECK (`quantity` > 0),
    CONSTRAINT `chk_si_subtotal` CHECK (`line_subtotal_price` = `quantity` * `unit_sale_price`),
    CONSTRAINT `chk_si_net_price` CHECK (`net_sale_price` = `line_subtotal_price` - `discount_allocated`),
    CONSTRAINT `chk_si_cost` CHECK (`subtotal_cost` = `quantity` * `unit_cost`),
    CONSTRAINT `chk_si_profit` CHECK (`item_profit` = `net_sale_price` - `subtotal_cost`),
    INDEX `idx_si_sale` (`sale_id`),
    INDEX `idx_si_product` (`product_id`),
    INDEX `idx_si_phone` (`phone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 16. Table: sale_returns
-- Purpose: Formal customer return headers with net revenue and COGS reversals.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_returns` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_number` VARCHAR(50) NOT NULL,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `return_date` DATE NOT NULL,
    `return_time` TIME NOT NULL,
    `total_refund_amount` DECIMAL(12,2) NOT NULL,
    `total_restocked_cogs` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_scrapped_cogs` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_profit_reversed` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `refund_payment_mode` ENUM('CASH', 'UPI', 'BANK_TRANSFER', 'STORE_CREDIT') NOT NULL DEFAULT 'CASH',
    `reason` VARCHAR(255) NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_sale_returns_number` UNIQUE (`return_number`),
    CONSTRAINT `fk_sr_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sr_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_sr_amounts` CHECK (`total_refund_amount` >= 0.00 AND `total_restocked_cogs` >= 0.00 AND `total_scrapped_cogs` >= 0.00),
    INDEX `idx_sr_sale` (`sale_id`),
    INDEX `idx_sr_date` (`return_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 17. Table: sale_return_items
-- Purpose: Line items returned by customers, tracking inventory state & COGS.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_return_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_id` BIGINT UNSIGNED NOT NULL,
    `sale_item_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `phone_id` BIGINT UNSIGNED DEFAULT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `refund_unit_price` DECIMAL(12,2) NOT NULL,
    `unit_cost` DECIMAL(12,2) NOT NULL,
    `subtotal_refund` DECIMAL(12,2) NOT NULL,
    `cogs_reversed` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `profit_reversed` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `restock_condition` ENUM('RESTOCKED_GOOD', 'DEFECTIVE_SCRAP') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sri_return` FOREIGN KEY (`return_id`) REFERENCES `sale_returns` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sri_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sri_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sri_phone` FOREIGN KEY (`phone_id`) REFERENCES `phones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_sri_qty` CHECK (`quantity` > 0),
    CONSTRAINT `chk_sri_refund` CHECK (`subtotal_refund` = `quantity` * `refund_unit_price`),
    INDEX `idx_sri_return` (`return_id`),
    INDEX `idx_sri_sale_item` (`sale_item_id`),
    INDEX `idx_sri_product` (`product_id`),
    INDEX `idx_sri_phone` (`phone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 18. Table: repair_records
-- Purpose: Financial records for repair jobs (purely financial accounting).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `repair_records` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `repair_number` VARCHAR(50) NOT NULL,
    `repair_type` ENUM('REFURBISHMENT', 'CUSTOMER_SERVICE') NOT NULL,
    `phone_id` BIGINT UNSIGNED DEFAULT NULL,
    `customer_name` VARCHAR(100) DEFAULT NULL,
    `customer_phone` VARCHAR(20) DEFAULT NULL,
    `device_model` VARCHAR(100) DEFAULT NULL,
    `repair_description` TEXT NOT NULL,
    `parts_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `labour_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_repair_cost` DECIMAL(12,2) NOT NULL,
    `amount_charged` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_profit_contribution` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('PAID', 'PARTIAL', 'UNPAID') NOT NULL DEFAULT 'PAID',
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `repair_date` DATE NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_repairs_number` UNIQUE (`repair_number`),
    CONSTRAINT `fk_repairs_phone` FOREIGN KEY (`phone_id`) REFERENCES `phones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_repairs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_repairs_cost` CHECK (`parts_cost` >= 0.00 AND `labour_amount` >= 0.00),
    CONSTRAINT `chk_repairs_total_cost` CHECK (`total_repair_cost` = `parts_cost` + `labour_amount`),
    INDEX `idx_repairs_phone` (`phone_id`),
    INDEX `idx_repairs_type_date` (`repair_type`, `repair_date`),
    INDEX `idx_repairs_customer_phone` (`customer_phone`),
    INDEX `idx_repairs_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 19. Table: repair_parts_used
-- Purpose: Dual-source parts ledger (in-house stock deduction vs. market cash).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `repair_parts_used` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `repair_id` BIGINT UNSIGNED NOT NULL,
    `source_type` ENUM('IN_HOUSE_STOCK', 'EXTERNAL_PURCHASE') NOT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `part_name` VARCHAR(150) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_cost` DECIMAL(12,2) NOT NULL,
    `subtotal_cost` DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rpu_repair` FOREIGN KEY (`repair_id`) REFERENCES `repair_records` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_rpu_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_rpu_quantity` CHECK (`quantity` > 0),
    CONSTRAINT `chk_rpu_unit_cost` CHECK (`unit_cost` >= 0.00),
    CONSTRAINT `chk_rpu_subtotal` CHECK (`subtotal_cost` = `quantity` * `unit_cost`),
    INDEX `idx_rpu_repair` (`repair_id`),
    INDEX `idx_rpu_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 20. Table: payments
-- Purpose: Unified financial ledger for customer credit, vendor credit, and repairs.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_number` VARCHAR(50) NOT NULL,
    `transaction_type` ENUM('SALE_RECEIPT', 'PURCHASE_PAYMENT', 'SALE_REFUND', 'REPAIR_RECEIPT') NOT NULL,
    `reference_table` VARCHAR(50) NOT NULL,
    `reference_id` BIGINT UNSIGNED NOT NULL,
    `vendor_id` INT UNSIGNED DEFAULT NULL,
    `payment_date` DATE NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `payment_mode` ENUM('CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE') NOT NULL,
    `transaction_ref` VARCHAR(100) DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `received_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_payments_number` UNIQUE (`payment_number`),
    CONSTRAINT `fk_payments_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_payments_amount` CHECK (`amount` > 0.00),
    CONSTRAINT `chk_payments_ref_table` CHECK (`reference_table` IN ('sales', 'purchases', 'sale_returns', 'repair_records')),
    INDEX `idx_payments_ref` (`reference_table`, `reference_id`),
    INDEX `idx_payments_vendor` (`vendor_id`),
    INDEX `idx_payments_date` (`payment_date`),
    INDEX `idx_payments_type` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 21. Table: audit_logs
-- Purpose: Append-only audit trail capturing critical business mutations.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` BIGINT UNSIGNED NOT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_audit_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_user_date` (`user_id`, `created_at`),
    INDEX `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
