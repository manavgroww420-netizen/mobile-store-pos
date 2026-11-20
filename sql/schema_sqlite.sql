PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'STAFF',
    is_active INTEGER NOT NULL DEFAULT 1,
    last_login_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
    name TEXT NOT NULL PRIMARY KEY,
    description TEXT NOT NULL,
    category TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INTEGER NOT NULL,
    permission_name TEXT NOT NULL,
    granted_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_name),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (permission_name) REFERENCES permissions (name) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    brand TEXT NOT NULL,
    model_name TEXT NOT NULL,
    item_type TEXT NOT NULL,
    sku TEXT UNIQUE DEFAULT NULL,
    min_stock_alert INTEGER NOT NULL DEFAULT 2,
    default_sale_price REAL DEFAULT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories (id)
);

CREATE TABLE IF NOT EXISTS inventory_stock (
    product_id INTEGER NOT NULL PRIMARY KEY,
    quantity INTEGER NOT NULL DEFAULT 0,
    avg_unit_cost REAL NOT NULL DEFAULT 0.00,
    last_restocked_at TEXT DEFAULT NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products (id)
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    movement_type TEXT NOT NULL,
    quantity_change INTEGER NOT NULL,
    quantity_before INTEGER NOT NULL,
    quantity_after INTEGER NOT NULL,
    unit_cost REAL NOT NULL DEFAULT 0.00,
    reference_table TEXT DEFAULT NULL,
    reference_id INTEGER DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products (id),
    FOREIGN KEY (created_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    imei_1 TEXT NOT NULL UNIQUE,
    imei_2 TEXT UNIQUE DEFAULT NULL,
    serial_number TEXT DEFAULT NULL,
    brand TEXT NOT NULL,
    model_name TEXT NOT NULL,
    active_phone_id INTEGER UNIQUE DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_name TEXT NOT NULL,
    contact_person TEXT DEFAULT NULL,
    phone TEXT NOT NULL,
    alt_phone TEXT DEFAULT NULL,
    email TEXT DEFAULT NULL,
    address TEXT DEFAULT NULL,
    gstin TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_number TEXT NOT NULL UNIQUE,
    vendor_id INTEGER NOT NULL,
    purchase_date TEXT NOT NULL,
    vendor_invoice_no TEXT DEFAULT NULL,
    total_amount REAL NOT NULL DEFAULT 0.00,
    paid_amount REAL NOT NULL DEFAULT 0.00,
    due_amount REAL NOT NULL DEFAULT 0.00,
    payment_status TEXT NOT NULL DEFAULT 'UNPAID',
    notes TEXT DEFAULT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors (id),
    FOREIGN KEY (created_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS purchase_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    item_type TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    unit_cost REAL NOT NULL,
    subtotal_cost REAL NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases (id),
    FOREIGN KEY (product_id) REFERENCES products (id)
);

CREATE TABLE IF NOT EXISTS phones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    phone_type TEXT NOT NULL,
    ram TEXT DEFAULT NULL,
    storage TEXT DEFAULT NULL,
    color TEXT DEFAULT NULL,
    device_condition TEXT DEFAULT NULL,
    status TEXT NOT NULL DEFAULT 'IN_STOCK',
    acquisition_source TEXT NOT NULL,
    vendor_id INTEGER DEFAULT NULL,
    purchase_item_id INTEGER DEFAULT NULL,
    seller_name TEXT DEFAULT NULL,
    seller_phone TEXT DEFAULT NULL,
    seller_id_type TEXT DEFAULT NULL,
    seller_aadhaar_last4 TEXT DEFAULT NULL,
    seller_aadhaar_hash TEXT DEFAULT NULL,
    seller_aadhaar_encrypted TEXT DEFAULT NULL,
    seller_id_number_masked TEXT DEFAULT NULL,
    purchase_date TEXT NOT NULL,
    purchase_cost REAL NOT NULL,
    repair_cost REAL NOT NULL DEFAULT 0.00,
    other_cost REAL NOT NULL DEFAULT 0.00,
    total_cost_basis REAL NOT NULL,
    expected_sale_price REAL DEFAULT NULL,
    sale_item_id INTEGER DEFAULT NULL,
    sold_at TEXT DEFAULT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices (id),
    FOREIGN KEY (product_id) REFERENCES products (id),
    FOREIGN KEY (vendor_id) REFERENCES vendors (id),
    FOREIGN KEY (purchase_item_id) REFERENCES purchase_items (id),
    FOREIGN KEY (created_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS phone_direct_costs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phone_id INTEGER NOT NULL,
    cost_type TEXT NOT NULL,
    description TEXT NOT NULL,
    amount REAL NOT NULL,
    cost_date TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (phone_id) REFERENCES phones (id),
    FOREIGN KEY (created_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_number TEXT NOT NULL UNIQUE,
    sale_date TEXT NOT NULL,
    sale_time TEXT NOT NULL,
    customer_name TEXT DEFAULT NULL,
    customer_phone TEXT DEFAULT NULL,
    subtotal_amount REAL NOT NULL,
    discount_amount REAL NOT NULL DEFAULT 0.00,
    final_total_amount REAL NOT NULL,
    paid_amount REAL NOT NULL DEFAULT 0.00,
    due_amount REAL NOT NULL DEFAULT 0.00,
    payment_status TEXT NOT NULL DEFAULT 'UNPAID',
    total_cost_basis REAL NOT NULL,
    gross_profit REAL NOT NULL,
    is_voided INTEGER NOT NULL DEFAULT 0,
    voided_by INTEGER DEFAULT NULL,
    voided_at TEXT DEFAULT NULL,
    void_reason TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (voided_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS sale_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    item_type TEXT NOT NULL,
    phone_id INTEGER DEFAULT NULL,
    quantity INTEGER NOT NULL,
    unit_cost REAL NOT NULL,
    unit_sale_price REAL NOT NULL,
    line_subtotal_price REAL NOT NULL,
    discount_allocated REAL NOT NULL DEFAULT 0.00,
    net_sale_price REAL NOT NULL,
    subtotal_cost REAL NOT NULL,
    item_profit REAL NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales (id),
    FOREIGN KEY (product_id) REFERENCES products (id),
    FOREIGN KEY (phone_id) REFERENCES phones (id)
);

CREATE TABLE IF NOT EXISTS sale_returns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_number TEXT NOT NULL UNIQUE,
    sale_id INTEGER NOT NULL,
    return_date TEXT NOT NULL,
    return_time TEXT NOT NULL,
    total_refund_amount REAL NOT NULL,
    total_restocked_cogs REAL NOT NULL DEFAULT 0.00,
    total_scrapped_cogs REAL NOT NULL DEFAULT 0.00,
    net_profit_reversed REAL NOT NULL DEFAULT 0.00,
    refund_payment_mode TEXT NOT NULL DEFAULT 'CASH',
    reason TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales (id),
    FOREIGN KEY (created_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS sale_return_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_id INTEGER NOT NULL,
    sale_item_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    phone_id INTEGER DEFAULT NULL,
    quantity INTEGER NOT NULL,
    refund_unit_price REAL NOT NULL,
    unit_cost REAL NOT NULL,
    subtotal_refund REAL NOT NULL,
    cogs_reversed REAL NOT NULL DEFAULT 0.00,
    profit_reversed REAL NOT NULL DEFAULT 0.00,
    restock_condition TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (return_id) REFERENCES sale_returns (id),
    FOREIGN KEY (sale_item_id) REFERENCES sale_items (id),
    FOREIGN KEY (product_id) REFERENCES products (id),
    FOREIGN KEY (phone_id) REFERENCES phones (id)
);

CREATE TABLE IF NOT EXISTS repair_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_number TEXT NOT NULL UNIQUE,
    repair_type TEXT NOT NULL,
    phone_id INTEGER DEFAULT NULL,
    customer_name TEXT DEFAULT NULL,
    customer_phone TEXT DEFAULT NULL,
    device_model TEXT DEFAULT NULL,
    repair_description TEXT NOT NULL,
    parts_cost REAL NOT NULL DEFAULT 0.00,
    labour_amount REAL NOT NULL DEFAULT 0.00,
    total_repair_cost REAL NOT NULL,
    amount_charged REAL NOT NULL DEFAULT 0.00,
    net_profit_contribution REAL NOT NULL DEFAULT 0.00,
    payment_status TEXT NOT NULL DEFAULT 'PAID',
    paid_amount REAL NOT NULL DEFAULT 0.00,
    due_amount REAL NOT NULL DEFAULT 0.00,
    repair_date TEXT NOT NULL,
    notes TEXT DEFAULT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (phone_id) REFERENCES phones (id),
    FOREIGN KEY (created_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS repair_parts_used (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_id INTEGER NOT NULL,
    source_type TEXT NOT NULL,
    product_id INTEGER DEFAULT NULL,
    part_name TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_cost REAL NOT NULL,
    subtotal_cost REAL NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repair_records (id),
    FOREIGN KEY (product_id) REFERENCES products (id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_number TEXT NOT NULL UNIQUE,
    transaction_type TEXT NOT NULL,
    reference_table TEXT NOT NULL,
    reference_id INTEGER NOT NULL,
    vendor_id INTEGER DEFAULT NULL,
    payment_date TEXT NOT NULL,
    amount REAL NOT NULL,
    payment_mode TEXT NOT NULL,
    transaction_ref TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    received_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors (id),
    FOREIGN KEY (received_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    table_name TEXT NOT NULL,
    record_id INTEGER NOT NULL,
    old_values TEXT DEFAULT NULL,
    new_values TEXT DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    ip_address TEXT DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- Seed Data
INSERT OR IGNORE INTO permissions (name, description, category) VALUES
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
('manage_users', 'Create and manage user accounts and assign staff permissions', 'ADMIN');

INSERT OR IGNORE INTO categories (name, slug, description, is_active) VALUES
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
('Other Accessories', 'other-accessories', 'Mobile stands, ring lights, converters, and other accessories', 1);

INSERT OR IGNORE INTO users (id, username, password_hash, full_name, role, is_active) VALUES
(1, 'admin', '$2y$12$K1f.c1ZtM6rY2g2r6V2Wv.eL8qC3nCq0O1q4uT9zK5pZ8x7sY0eXa', 'Store Administrator (Owner)', 'OWNER', 1);

INSERT OR IGNORE INTO user_permissions (user_id, permission_name, granted_by)
SELECT 1, name, 1 FROM permissions;
