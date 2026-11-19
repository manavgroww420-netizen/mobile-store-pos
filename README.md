# Mobile Store POS & Inventory Management System
> **Zero-GST Mobile Phone Retail, IMEI Tracking, Service & Financial Control Platform**  
> *Fully Customizable Branding • Lightweight Pure PHP 8.2+ Architecture • Zero Dependencies*

---

## 1. Overview & Architecture

**Mobile Store POS** is a business control and shop management system purpose-built for mobile phone stores, smartphone resellers, electronics repair shops, and mobile accessories outlets. It delivers end-to-end operational control over IMEI-serialized handsets, high-velocity accessory inventory, workshop repair ticketing, refurbishment cost capitalization, customer credit (*Udhar*), and multi-mode point-of-sale settlements.

### Key Architectural Highlights
- **Fully Customizable Store Identity:** Shop name, tagline, address, phone, email, and currency are configurable through `.env` or `config/config.php` without altering application code.
- **Strict Zero GST Sitewide:** Designed specifically for composition dealers, unregistered merchants, and internal business control. No complex tax splits, GST filings, or tax invoices.
- **IMEI Lifecycle Tracking:** Dual-IMEI tracking with hardware-level Luhn checksum validation. Individual commercial episodes for new, refurbished, and pre-owned devices.
- **Refurbishment Cost Capitalization:** Used phone acquisition costs and repair parts/labor are automatically capitalized into `phones.total_cost_basis`.
- **Weighted-Average Inventory Valuation:** Accessories and spare parts inventory value is computed via weighted-average procurement cost.
- **Fixed-Point Financial Engine (`AMH\Financial`):** String-based BCMath arithmetic prevents IEEE-754 floating-point rounding errors across sales, COGS, gross margins, and customer ledgers.
- **Thermal Receipt Printing:** Built-in 80mm / 58mm POS thermal receipt formatting for sales slips, counter estimates, and payment vouchers.
- **Pure Native Stack:** Built purely in PHP 8.2+ (PDO MySQL / SQLite), HTML5, and Vanilla CSS/JS. Zero Composer, Node.js, or external framework dependencies.

---

## 2. Store Branding & Customization

The system makes it effortless to brand the application for any retail store. Simply copy `.env.example` to `.env` and set your store details:

```env
# =====================================================================
# STORE BRANDING & METADATA CONFIGURATION
# =====================================================================
SHOP_NAME="Apex Mobile World"
SHOP_TAGLINE="Smartphones, Premium Accessories & Express Service"
SHOP_ADDRESS="Plot 104, MG Road, Electronic City, Bengaluru - 560100"
SHOP_PHONE="+91 98765 43210"
SHOP_EMAIL="contact@apexmobile.example.com"
SHOP_CURRENCY="INR"
SHOP_CURRENCY_SYMBOL="₹"

# =====================================================================
# DATABASE CONNECTION
# =====================================================================
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="mobile_pos_db"
DB_USER="root"
DB_PASS=""
```

All views, navigation bars, footers, browser titles, P&L statements, and printed thermal receipts automatically reflect these configurations in real-time.

---

## 3. All 12 Completed System Modules

1. **Executive Command Center (`/` or `/dashboard`)**:
   - Real-time operational dials: Today's counter turnover, physical cash in drawer, digital receipts (UPI/Card/Bank), and Month-to-Date turnover & profit.
   - Handsets inventory health dials (New vs. Used) and aged handsets warning (>30 days in stock).
   - Critical low stock procurement alerts and open market udhar collection stream.
   - Quick workflow action launcher (`+ New Sale`, `Check IMEI`, `+ New Repair`, `Refurbish Phone`, `Receive Stock`, `Daily Close`).

2. **Authentication, Hardened Sessions & RBAC (`/login`, `/users`)**:
   - Hardened sessions with `HttpOnly`, `SameSite=Strict`, `session.use_strict_mode`, and periodic session ID regeneration.
   - Password hashing via Bcrypt (Cost Factor: 12).
   - Fine-grained permission system (`view_financial_reports`, `view_purchase_costs`, `view_profit_margins`, `manage_users`, etc.).
   - Shop Owner automatically inherits full unmasked authority.

3. **Product Catalog & Inventory Engine (`/products`, `/inventory`, `/categories`)**:
   - Complete catalog for serialized phones, non-serialized accessories, and spare parts.
   - Automated double-entry stock movement auditing for every physical addition or deduction.
   - Low stock warnings and reorder level alerts.

4. **Serialized Handsets & IMEI Engine (`/phones`, `/imei/lookup`)**:
   - Dual IMEI validation (15 digits with Luhn algorithm check).
   - Handset commercial states (`IN_STOCK`, `SOLD`, `DEFECTIVE_SCRAP`, `RETURNED`).
   - Direct handset cost ledger for parts, labor, and renewal costs.

5. **Wholesale Procurement & Vendor Payables (`/purchases`, `/vendors`)**:
   - Tracks wholesale purchase invoices from distributors.
   - Vendor credit tracking, payables balance (*Dena*), and partial/full payment disbursements.

6. **Counter Sales & Multi-Mode POS Payments (`/sales`, `/sales/create`)**:
   - Supports mixed counter sales (serialized handsets + multiple accessories).
   - Atomic inventory deduction and row locking to prevent double-selling.
   - Multi-mode payment collection: Cash, UPI, Card POS, and Customer Credit (*Udhar*).
   - Built-in 80mm thermal receipt printer slip view (`views/sales/show.php`).

7. **Mobile Repair Records & Refurbishment (`/repairs`)**:
   - **Type A (Refurbishment):** Used phone renewal costs capitalized directly into `phones.total_cost_basis`.
   - **Type B (Customer Service):** Customer device repair tracking with parts cost, technician labour, billing, and profit contribution.

8. **Customer Returns & Administrative Voids (`/sales/{id}/return`, `/sales/{id}/void`)**:
   - Restocking return flow (`RESTOCKED_GOOD` restores inventory and reverses COGS).
   - Defective return flow (`DEFECTIVE_SCRAP` absorbs cost as shop loss).
   - Same-day administrative sale voiding with complete financial and inventory reversals.

9. **Customer Credit (*Udhar*) & Vendor Payables Ledgers (`/ledgers/customers`, `/ledgers/vendors`, `/ledgers/payments`)**:
   - Customer debit account statement showing sales and repair credit.
   - Single-voucher settlements with partial and full settlement options.
   - Central sequential payment movements ledger.

10. **Authoritative Profit & Loss (P&L) Engine (`/profit-loss`)**:
    - Net Operating Revenue = Counter Sales + Customer Repairs - Returns Refunds.
    - Effective COGS & Direct Costs = Gross Sales COGS - Restocked COGS + Scrapped Defective Loss + Repair Direct Costs.
    - Gross Operating Profit & Margin % (with zero-division protection).
    - Business channel breakdown (Serialized Handsets, Accessories, Customer Repairs).

11. **Management Reports & Native Export Engine (`/reports/*`)**:
    - 7 specialized reports: Sales & Turnover, Inventory Asset Valuation, Mobile Handsets Stock & Aging, Customer Credit Aging Schedule, Vendor Payables, Repair Services, and Daily Cash Drawer Close.
    - Native CSV streaming with UTF-8 BOM (`\xEF\xBB\xBF`) for direct Microsoft Excel compatibility.
    - Browser print-ready formatting.

12. **Production Hardening, Health Diagnostics & Security**:
    - Comprehensive diagnostic endpoint at `/health`.
    - Hardened HTTP security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`).
    - AES-256-GCM encryption for customer identification data (Aadhaar).
    - Anti-CSRF token verification across all mutations.
    - Full HTML escaping via `Security::e()` against XSS.

---

## 4. Quickstart & Installation

### Requirements
- **PHP:** 8.2 or higher (Extensions: `pdo`, `pdo_mysql`, `pdo_sqlite`, `bcmath`, `mbstring`, `openssl`)
- **Database:** MySQL 8.0+ / MariaDB 10.5+ (or SQLite 3 for local testing)
- **Web Server:** Apache (with `mod_rewrite`), Nginx, or PHP Built-in Server

### Step 1: Clone or Download
```bash
git clone <repository-url>
cd mobile-store-pos
```

### Step 2: Environment Setup
```bash
cp .env.example .env
```
Edit `.env` to configure your store name, address, and database credentials.

### Step 3: Database Setup
Import the database schema and seed data into MySQL:
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS mobile_pos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p mobile_pos_db < sql/schema.sql
mysql -u root -p mobile_pos_db < sql/seed.sql
```

Default credentials created by `seed.sql`:
- **Username:** `admin`
- **Password:** `Admin@123`
- **Role:** `Owner` (Full System Authority)

### Step 4: Run Locally
You can run the application immediately using the PHP built-in web server:
```bash
php -S localhost:8000 -t public
```
Open [http://localhost:8000](http://localhost:8000) in your web browser and sign in.

---

## 5. Directory Structure

```text
mobile-store-pos/
├── config/
│   ├── config.php              # Central configuration & .env loader
│   ├── database.php            # PDO connection manager
│   └── database.example.php
├── public/
│   ├── index.php               # Front controller & router dispatch
│   ├── .htaccess               # Apache clean URL rewrites
│   └── assets/
│       ├── css/                # Vanilla CSS styling
│       └── js/                 # Client utilities
├── src/
│   ├── Auth.php                # Authentication & session security
│   ├── Authorization.php       # RBAC & permission checking
│   ├── AuditLogger.php         # Audit trail logging
│   ├── Database.php            # PDO wrapper & transaction handling
│   ├── ErrorHandler.php        # Error catching & logging
│   ├── Financial.php           # BCMath fixed-point financial math
│   ├── Helpers.php             # Global helper functions
│   ├── RateLimiter.php         # Brute-force login defense
│   ├── Router.php              # Clean URL router
│   ├── Security.php            # CSRF, XSS, AES-256-GCM, IMEI Luhn
│   ├── View.php                # View renderer & global data injector
│   └── Controllers/
│       ├── AuthController.php
│       ├── CategoryController.php
│       ├── DashboardController.php
│       ├── ImeiController.php
│       ├── InventoryController.php
│       ├── LedgerController.php
│       ├── PhoneController.php
│       ├── ProductController.php
│       ├── ProfitLossController.php
│       ├── PurchaseController.php
│       ├── RepairController.php
│       ├── ReportController.php
│       ├── ReturnController.php
│       ├── SaleController.php
│       ├── UserController.php
│       └── VendorController.php
├── sql/
│   ├── schema.sql              # 21 InnoDB relational tables
│   └── seed.sql                # Initial roles, users, and seed data
├── storage/
│   └── logs/                   # System error logs & audit records
├── views/
│   ├── layouts/                # Base layouts (main.php, auth.php, error.php)
│   ├── partials/               # Header, sidebar, footer, alerts
│   ├── dashboard/              # Executive KPI dashboard
│   ├── sales/                  # POS sales & thermal receipt
│   ├── purchases/              # Procurement & stock receiving
│   ├── repairs/                # Repair ticketing & refurbishment
│   ├── ledgers/                # Customer credit & vendor payables
│   ├── profit_loss/            # P&L financial reports
│   └── reports/                # Management analytics & CSV exports
└── tests/                      # 12 automated test suites (748 passing tests)
```

---

## 6. Running Automated Tests

The repository includes a comprehensive 12-suite automated test suite covering all modules:

```powershell
php tests/FoundationTest.php
php tests/AuthAndUserManagementTest.php
php tests/ProductAndInventoryTest.php
php tests/MobileAndImeiTest.php
php tests/PurchaseAndVendorTest.php
php tests/SaleAndPaymentTest.php
php tests/RepairAndRefurbishmentTest.php
php tests/ReturnAndVoidTest.php
php tests/LedgerAndCreditTest.php
php tests/ProfitLossTest.php
php tests/ManagementReportsTest.php
php tests/ExecutiveDashboardAndHardeningTest.php
```

### Test Coverage Results
| Suite | Module / Scope | Tests | Result |
|---|---|---|---|
| **Foundation** | Routing, Security, CSRF, AES-256, BCMath, Luhn | 53 | 100% Passed |
| **Auth & Users** | Login, Session Hardening, RBAC, Permissions | 77 | 100% Passed |
| **Products & Stock** | Product Catalog, Categories, Stock Auditing | 62 | 100% Passed |
| **Mobiles & IMEI** | Handset Episodes, Dual IMEI, Commercial States | 71 | 100% Passed |
| **Purchases & Vendors** | Wholesale Procurement, Vendor Ledgers | 98 | 100% Passed |
| **Sales & Payments** | Mixed Sales, Atomic Inventory, Multi-Mode Pay | 80 | 100% Passed |
| **Repairs & Refurbishment** | Service Tickets, Refurbishment Capitalization | 91 | 100% Passed |
| **Returns & Voids** | Restocking, Scrapping, Administrative Voids | 51 | 100% Passed |
| **Ledgers & Credit** | Customer Udhar, Single-Voucher Settlement | 44 | 100% Passed |
| **Profit & Loss** | Operating P&L, Channel Breakdown, Realized COGS | 40 | 100% Passed |
| **Management Reports** | 7 Reports, Excel CSV Streaming with UTF-8 BOM | 49 | 100% Passed |
| **Executive Dashboard** | Real-time dials, Alerting, Security Diagnostics | 32 | 100% Passed |
| **TOTAL** | **Full System Verification** | **748** | **0 Failures (100% OK)** |

---

## 7. License

Open-source and available under the [MIT License](LICENSE).
