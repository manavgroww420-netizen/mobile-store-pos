<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Security;
use AMH\Financial;
use AMH\AuditLogger;
use AMH\View;

/**
 * Customer Credit & Vendor Payables Ledger Controller (Udhar & Dena Khata)
 *
 * Implements centralized customer credit tracking (sales & repair receivables),
 * vendor payables tracking (wholesale consignment debts), FIFO auto-allocation settlements,
 * chronological customer/vendor statements with running balances, and universal payment registers.
 *
 * NOTE: Strictly internal shop management. Zero GST sitewide.
 */
class LedgerController
{
    // =========================================================================
    // 1. CUSTOMER CREDIT LEDGER (UDHAR KHATA)
    // =========================================================================

    /**
     * List customers with outstanding credit (Udhar Khata Directory).
     * Aggregates unpaid/partial balances across sales and repair records.
     */
    public function customers(): void
    {
        Auth::requireLogin();
        $this->requireCustomerCreditPermission();

        $search = trim($_GET['q'] ?? '');
        $agingFilter = strtoupper(trim($_GET['aging'] ?? 'ALL')); // ALL, 0-30, 31-60, 60+
        $minDue = (float)($_GET['min_due'] ?? 0.00);

        // Fetch all customer debt episodes from sales and repairs
        // We aggregate by customer_phone (or customer_name if phone is missing)
        $salesRows = Database::fetchAll("
            SELECT 
                s.customer_phone,
                s.customer_name,
                s.due_amount,
                s.final_total_amount AS total_amount,
                s.paid_amount,
                s.sale_date AS record_date,
                'SALE' AS record_type,
                s.id AS record_id
            FROM sales s
            WHERE s.due_amount > 0.00 AND s.is_voided = 0
            ORDER BY s.sale_date ASC
        ");

        $repairRows = Database::fetchAll("
            SELECT 
                r.customer_phone,
                r.customer_name,
                r.due_amount,
                r.amount_charged AS total_amount,
                r.paid_amount,
                r.repair_date AS record_date,
                'REPAIR' AS record_type,
                r.id AS record_id
            FROM repair_records r
            WHERE r.due_amount > 0.00 AND r.repair_type = 'CUSTOMER_SERVICE'
            ORDER BY r.repair_date ASC
        ");

        // Merge and aggregate per customer key (phone preferred, fallback to sanitized name)
        $customers = [];
        $today = new \DateTimeImmutable(date('Y-m-d'));

        $totalMarketUdhar = '0.00';
        $totalOverdue30 = '0.00';

        $allDues = array_merge($salesRows, $repairRows);

        foreach ($allDues as $row) {
            $phone = trim($row['customer_phone'] ?? '');
            $name = trim($row['customer_name'] ?? '');
            if ($name === '' && $phone === '') {
                $name = 'Walk-in Customer';
            }

            // Key on phone if available; else on normalized name
            $key = $phone !== '' ? $phone : 'name:' . strtolower($name);

            if (!isset($customers[$key])) {
                $customers[$key] = [
                    'key'            => $key,
                    'phone'          => $phone,
                    'name'           => $name !== '' ? $name : 'Unnamed Customer',
                    'total_credit'   => '0.00',
                    'total_paid'     => '0.00',
                    'total_due'      => '0.00',
                    'unpaid_bills'   => 0,
                    'oldest_date'    => $row['record_date'],
                    'days_old'       => 0,
                    'has_sales'      => false,
                    'has_repairs'    => false,
                ];
            }

            $c = &$customers[$key];
            if ($name !== '' && $c['name'] === 'Unnamed Customer') {
                $c['name'] = $name;
            }

            $c['total_credit'] = Financial::add($c['total_credit'], (string)$row['total_amount']);
            $c['total_paid']   = Financial::add($c['total_paid'], (string)$row['paid_amount']);
            $c['total_due']    = Financial::add($c['total_due'], (string)$row['due_amount']);
            $c['unpaid_bills']++;

            if ($row['record_type'] === 'SALE') {
                $c['has_sales'] = true;
            } else {
                $c['has_repairs'] = true;
            }

            if (strtotime($row['record_date']) < strtotime($c['oldest_date'])) {
                $c['oldest_date'] = $row['record_date'];
            }
            unset($c);
        }

        // Calculate aging days and totals
        foreach ($customers as &$c) {
            $recordDate = new \DateTimeImmutable($c['oldest_date']);
            $diff = $today->diff($recordDate);
            $c['days_old'] = $diff->days;

            $totalMarketUdhar = Financial::add($totalMarketUdhar, $c['total_due']);
            if ($c['days_old'] > 30) {
                $totalOverdue30 = Financial::add($totalOverdue30, $c['total_due']);
            }
        }
        unset($c);

        // Apply Search Filter
        if ($search !== '') {
            $sLower = strtolower($search);
            $customers = array_filter($customers, function ($c) use ($sLower) {
                return str_contains(strtolower($c['name']), $sLower) || str_contains($c['phone'], $sLower);
            });
        }

        // Apply Aging Filter
        if ($agingFilter === '0-30') {
            $customers = array_filter($customers, fn($c) => $c['days_old'] <= 30);
        } elseif ($agingFilter === '31-60') {
            $customers = array_filter($customers, fn($c) => $c['days_old'] > 30 && $c['days_old'] <= 60);
        } elseif ($agingFilter === '60+') {
            $customers = array_filter($customers, fn($c) => $c['days_old'] > 60);
        }

        // Apply Min Due Filter
        if ($minDue > 0) {
            $customers = array_filter($customers, fn($c) => (float)$c['total_due'] >= $minDue);
        }

        // Sort by highest due balance descending
        usort($customers, function ($a, $b) {
            return (float)$b['total_due'] <=> (float)$a['total_due'];
        });

        $totalDebtorsCount = count($customers);

        View::render('ledgers/customers', [
            'pageTitle'         => 'Customer Credit Ledger (Udhar Khata)',
            'customers'         => $customers,
            'totalMarketUdhar'  => $totalMarketUdhar,
            'totalDebtorsCount' => $totalDebtorsCount,
            'totalOverdue30'    => $totalOverdue30,
            'search'            => $search,
            'agingFilter'       => $agingFilter,
            'minDue'            => $minDue,
        ], 'main');
    }

    /**
     * View detailed chronological Customer Statement / Passbook.
     * Shows timeline of sales, repairs, returns, and payment installments with running balance.
     */
    public function customerStatement(array $params): void
    {
        Auth::requireLogin();
        $this->requireCustomerCreditPermission();

        $identifier = trim(urldecode($params['identifier'] ?? ''));
        if ($identifier === '') {
            View::flash('danger', 'Customer identifier is required.');
            Security::safeRedirect('/ledgers/customers');
            return;
        }

        $customerData = $this->getCustomerTimeline($identifier);

        if (!$customerData['exists']) {
            View::flash('danger', 'No commercial or credit history found for this customer.');
            Security::safeRedirect('/ledgers/customers');
            return;
        }

        View::render('ledgers/customer_statement', [
            'pageTitle'    => "Khata Statement: {$customerData['name']}",
            'customer'     => $customerData,
            'timeline'     => $customerData['timeline'],
            'openSales'    => $customerData['open_sales'],
            'openRepairs'  => $customerData['open_repairs'],
        ], 'main');
    }

    /**
     * Show Debt Settlement / Repayment form for a customer.
     */
    public function showSettleCustomer(array $params): void
    {
        Auth::requireLogin();
        $this->requireCustomerCreditPermission();

        $identifier = trim(urldecode($params['identifier'] ?? ''));
        $customerData = $this->getCustomerTimeline($identifier);

        if (!$customerData['exists'] || (float)$customerData['total_due'] <= 0.00) {
            View::flash('info', "Customer has no open balance due to settle.");
            Security::safeRedirect('/ledgers/customers');
            return;
        }

        View::render('ledgers/customer_settle', [
            'pageTitle'   => "Settle Customer Dues: {$customerData['name']}",
            'customer'    => $customerData,
            'openSales'   => $customerData['open_sales'],
            'openRepairs' => $customerData['open_repairs'],
            'formData'    => [
                'payment_date' => date('Y-m-d'),
                'amount'       => $customerData['total_due'],
                'payment_mode' => 'CASH',
                'target'       => 'FIFO_AUTO',
            ],
        ], 'main');
    }

    /**
     * Process Customer Debt Settlement (FIFO Auto-Allocation or Specific Bill Payment).
     */
    public function settleCustomer(array $params): void
    {
        Auth::requireLogin();
        $this->requireCustomerCreditPermission();

        $identifier = trim(urldecode($params['identifier'] ?? ''));

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/ledgers/customers/" . urlencode($identifier) . "/settle");
            return;
        }

        $paymentDate    = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $amountRaw      = trim($_POST['amount'] ?? '');
        $paymentMode    = strtoupper(trim($_POST['payment_mode'] ?? 'CASH'));
        $target         = trim($_POST['target'] ?? 'FIFO_AUTO');
        $transactionRef = trim($_POST['transaction_ref'] ?? '');
        $notes          = trim($_POST['notes'] ?? '');

        $errors = [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $errors[] = 'Payment date must be in YYYY-MM-DD format.';
        }

        $allowedModes = ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE'];
        if (!in_array($paymentMode, $allowedModes, true)) {
            $errors[] = 'Please select a valid payment mode.';
        }

        if (!is_numeric($amountRaw) || (float)$amountRaw <= 0.00) {
            $errors[] = 'Payment amount must be a positive number greater than ₹0.00.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect("/ledgers/customers/" . urlencode($identifier) . "/settle");
            return;
        }

        $paymentAmount = Financial::toDecimal((float)$amountRaw);
        $operatorId = Auth::id() ?? 1;

        try {
            $settledReceipts = Database::transaction(function() use (
                $identifier, $paymentDate, $paymentAmount, $paymentMode,
                $target, $transactionRef, $notes, $operatorId
            ) {
                // Fetch customer identity and lock eligible records FOR UPDATE
                $customerData = $this->getCustomerTimeline($identifier, true);

                $totalDue = $customerData['total_due'];

                if ((float)$paymentAmount > (float)$totalDue) {
                    throw new \RuntimeException("Repayment amount (₹{$paymentAmount}) exceeds total customer outstanding balance (₹{$totalDue}). Overpayment is prohibited.");
                }

                $receiptsCreated = [];
                $remainingToAllocate = $paymentAmount;

                // Case A: Specific Bill Settlement
                if ($target !== 'FIFO_AUTO') {
                    $parts = explode(':', $target);
                    if (count($parts) !== 2) {
                        throw new \RuntimeException("Invalid target bill selection.");
                    }
                    $billType = $parts[0];
                    $billId = (int)$parts[1];

                    if ($billType === 'SALE') {
                        $sale = Database::fetchOne("
                            SELECT * FROM sales WHERE id = :id AND due_amount > 0.00 AND is_voided = 0 FOR UPDATE
                        ", [':id' => $billId]);

                        if (!$sale) {
                            throw new \RuntimeException("Selected sale record not found or already settled.");
                        }

                        $due = (string)$sale['due_amount'];
                        if ((float)$paymentAmount > (float)$due) {
                            throw new \RuntimeException("Payment (₹{$paymentAmount}) exceeds specific sale due balance (₹{$due}).");
                        }

                        $rec = $this->applyPaymentToSale($sale, $paymentAmount, $paymentDate, $paymentMode, $transactionRef, $notes, $operatorId);
                        $receiptsCreated[] = $rec;
                    } elseif ($billType === 'REPAIR') {
                        $repair = Database::fetchOne("
                            SELECT * FROM repair_records WHERE id = :id AND due_amount > 0.00 AND repair_type = 'CUSTOMER_SERVICE' FOR UPDATE
                        ", [':id' => $billId]);

                        if (!$repair) {
                            throw new \RuntimeException("Selected repair record not found or already settled.");
                        }

                        $due = (string)$repair['due_amount'];
                        if ((float)$paymentAmount > (float)$due) {
                            throw new \RuntimeException("Payment (₹{$paymentAmount}) exceeds specific repair due balance (₹{$due}).");
                        }

                        $rec = $this->applyPaymentToRepair($repair, $paymentAmount, $paymentDate, $paymentMode, $transactionRef, $notes, $operatorId);
                        $receiptsCreated[] = $rec;
                    } else {
                        throw new \RuntimeException("Unsupported bill target type.");
                    }
                }
                // Case B: FIFO Auto-Allocation across oldest open sales & repairs
                else {
                    // Combine open sales and repairs sorted chronologically ascending
                    $queue = [];
                    foreach ($customerData['open_sales'] as $s) {
                        $queue[] = [
                            'type' => 'SALE',
                            'date' => $s['sale_date'],
                            'id'   => $s['id'],
                            'ref'  => $s['sale_number'],
                            'due'  => (string)$s['due_amount'],
                        ];
                    }
                    foreach ($customerData['open_repairs'] as $r) {
                        $queue[] = [
                            'type' => 'REPAIR',
                            'date' => $r['repair_date'],
                            'id'   => $r['id'],
                            'ref'  => $r['repair_number'],
                            'due'  => (string)$r['due_amount'],
                        ];
                    }

                    usort($queue, function ($a, $b) {
                        $c = strcmp($a['date'], $b['date']);
                        return $c !== 0 ? $c : ($a['id'] <=> $b['id']);
                    });

                    foreach ($queue as $item) {
                        if ((float)$remainingToAllocate <= 0.00) {
                            break;
                        }

                        $currentDue = $item['due'];
                        $portion = ((float)$remainingToAllocate >= (float)$currentDue)
                            ? $currentDue
                            : $remainingToAllocate;

                        if ($item['type'] === 'SALE') {
                            $sale = Database::fetchOne("SELECT * FROM sales WHERE id = :id FOR UPDATE", [':id' => $item['id']]);
                            $rec = $this->applyPaymentToSale($sale, $portion, $paymentDate, $paymentMode, $transactionRef, $notes, $operatorId);
                            $receiptsCreated[] = $rec;
                        } else {
                            $repair = Database::fetchOne("SELECT * FROM repair_records WHERE id = :id FOR UPDATE", [':id' => $item['id']]);
                            $rec = $this->applyPaymentToRepair($repair, $portion, $paymentDate, $paymentMode, $transactionRef, $notes, $operatorId);
                            $receiptsCreated[] = $rec;
                        }

                        $remainingToAllocate = Financial::sub($remainingToAllocate, $portion);
                    }
                }

                // Log audit trail
                AuditLogger::log(
                    'CUSTOMER_CREDIT_SETTLED',
                    'payments',
                    $receiptsCreated[0]['id'] ?? 0,
                    null,
                    [
                        'customer'         => $customerData['name'],
                        'phone'            => $customerData['phone'],
                        'amount_settled'   => $paymentAmount,
                        'payment_mode'     => $paymentMode,
                        'allocation_mode'  => $target === 'FIFO_AUTO' ? 'FIFO' : 'SPECIFIC_BILL',
                        'receipts_count'   => count($receiptsCreated),
                    ],
                    "Settled ₹{$paymentAmount} customer credit for {$customerData['name']} ({$paymentMode})",
                    $operatorId
                );

                return $receiptsCreated;
            });

            View::flash('success', "Repayment of ₹" . number_format((float)$paymentAmount, 2) . " processed successfully across " . count($settledReceipts) . " bill(s).");
            Security::safeRedirect("/ledgers/customers/" . urlencode($identifier));
        } catch (\Throwable $e) {
            View::flash('danger', 'Settlement failed: ' . $e->getMessage());
            Security::safeRedirect("/ledgers/customers/" . urlencode($identifier) . "/settle");
        }
    }

    // =========================================================================
    // 2. VENDOR PAYABLES LEDGER (MAHAJAN DENA KHATA)
    // =========================================================================

    /**
     * List vendors with outstanding payables (Vendor Dena Khata Directory).
     */
    public function vendors(): void
    {
        Auth::requireLogin();
        $this->requireVendorPayablesPermission();

        $search = trim($_GET['q'] ?? '');
        $agingFilter = strtoupper(trim($_GET['aging'] ?? 'ALL'));

        $today = new \DateTimeImmutable(date('Y-m-d'));

        // Query vendors having unpaid/partial purchase consignments
        $vendorsWithDues = Database::fetchAll("
            SELECT 
                v.id,
                v.business_name,
                v.contact_person,
                v.phone,
                v.email,
                v.is_active,
                COUNT(p.id) AS open_purchases_count,
                COALESCE(SUM(p.total_amount), 0.00) AS total_purchased,
                COALESCE(SUM(p.paid_amount), 0.00) AS total_paid,
                COALESCE(SUM(p.due_amount), 0.00) AS total_due,
                MIN(p.purchase_date) AS oldest_purchase_date
            FROM vendors v
            JOIN purchases p ON p.vendor_id = v.id
            WHERE p.due_amount > 0.00
            GROUP BY v.id, v.business_name, v.contact_person, v.phone, v.email, v.is_active
            ORDER BY total_due DESC
        ");

        $totalMarketDena = '0.00';
        $totalOverdue30 = '0.00';

        foreach ($vendorsWithDues as &$v) {
            $totalMarketDena = Financial::add($totalMarketDena, (string)$v['total_due']);

            $oldestDate = new \DateTimeImmutable($v['oldest_purchase_date']);
            $diff = $today->diff($oldestDate);
            $v['days_old'] = $diff->days;

            if ($v['days_old'] > 30) {
                $totalOverdue30 = Financial::add($totalOverdue30, (string)$v['total_due']);
            }
        }
        unset($v);

        // Filter by Search
        if ($search !== '') {
            $sLower = strtolower($search);
            $vendorsWithDues = array_filter($vendorsWithDues, function ($v) use ($sLower) {
                return str_contains(strtolower($v['business_name']), $sLower) ||
                       str_contains(strtolower($v['contact_person'] ?? ''), $sLower) ||
                       str_contains($v['phone'] ?? '', $sLower);
            });
        }

        // Filter by Aging
        if ($agingFilter === '0-30') {
            $vendorsWithDues = array_filter($vendorsWithDues, fn($v) => $v['days_old'] <= 30);
        } elseif ($agingFilter === '31-60') {
            $vendorsWithDues = array_filter($vendorsWithDues, fn($v) => $v['days_old'] > 30 && $v['days_old'] <= 60);
        } elseif ($agingFilter === '60+') {
            $vendorsWithDues = array_filter($vendorsWithDues, fn($v) => $v['days_old'] > 60);
        }

        $totalCreditorsCount = count($vendorsWithDues);

        View::render('ledgers/vendors', [
            'pageTitle'           => 'Vendor Payables Ledger (Mahajan Dena Khata)',
            'vendors'             => $vendorsWithDues,
            'totalMarketDena'     => $totalMarketDena,
            'totalCreditorsCount' => $totalCreditorsCount,
            'totalOverdue30'      => $totalOverdue30,
            'search'              => $search,
            'agingFilter'         => $agingFilter,
        ], 'main');
    }

    /**
     * View detailed chronological Vendor Statement.
     */
    public function vendorStatement(array $params): void
    {
        Auth::requireLogin();
        $this->requireVendorPayablesPermission();

        $vendorId = (int)($params['id'] ?? 0);
        $vendor = Database::fetchOne("SELECT * FROM vendors WHERE id = :id", [':id' => $vendorId]);

        if (!$vendor) {
            View::flash('danger', 'Vendor not found.');
            Security::safeRedirect('/ledgers/vendors');
            return;
        }

        $vendorData = $this->getVendorTimeline($vendorId);

        View::render('ledgers/vendor_statement', [
            'pageTitle'      => "Vendor Statement: {$vendor['business_name']}",
            'vendor'         => $vendor,
            'summary'        => $vendorData['summary'],
            'timeline'       => $vendorData['timeline'],
            'openPurchases'  => $vendorData['open_purchases'],
        ], 'main');
    }

    /**
     * Show form to settle vendor dues (FIFO or specific purchase).
     */
    public function showSettleVendor(array $params): void
    {
        Auth::requireLogin();
        $this->requireVendorPayablesPermission();

        $vendorId = (int)($params['id'] ?? 0);
        $vendor = Database::fetchOne("SELECT * FROM vendors WHERE id = :id", [':id' => $vendorId]);

        if (!$vendor) {
            View::flash('danger', 'Vendor not found.');
            Security::safeRedirect('/ledgers/vendors');
            return;
        }

        $vendorData = $this->getVendorTimeline($vendorId);

        if ((float)$vendorData['summary']['total_due'] <= 0.00) {
            View::flash('info', "Vendor {$vendor['business_name']} has zero outstanding dues.");
            Security::safeRedirect("/ledgers/vendors/{$vendorId}");
            return;
        }

        View::render('ledgers/vendor_settle', [
            'pageTitle'      => "Settle Vendor Dues: {$vendor['business_name']}",
            'vendor'         => $vendor,
            'summary'        => $vendorData['summary'],
            'openPurchases'  => $vendorData['open_purchases'],
            'formData'       => [
                'payment_date' => date('Y-m-d'),
                'amount'       => $vendorData['summary']['total_due'],
                'payment_mode' => 'BANK_TRANSFER',
                'target'       => 'FIFO_AUTO',
            ],
        ], 'main');
    }

    /**
     * Process Vendor Settlement (FIFO Auto-Allocation or Specific Purchase).
     */
    public function settleVendor(array $params): void
    {
        Auth::requireLogin();
        $this->requireVendorPayablesPermission();

        $vendorId = (int)($params['id'] ?? 0);
        $vendor = Database::fetchOne("SELECT * FROM vendors WHERE id = :id", [':id' => $vendorId]);

        if (!$vendor) {
            View::flash('danger', 'Vendor not found.');
            Security::safeRedirect('/ledgers/vendors');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/ledgers/vendors/{$vendorId}/settle");
            return;
        }

        $paymentDate    = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $amountRaw      = trim($_POST['amount'] ?? '');
        $paymentMode    = strtoupper(trim($_POST['payment_mode'] ?? 'BANK_TRANSFER'));
        $target         = trim($_POST['target'] ?? 'FIFO_AUTO');
        $transactionRef = trim($_POST['transaction_ref'] ?? '');
        $notes          = trim($_POST['notes'] ?? '');

        $errors = [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $errors[] = 'Payment date must be in YYYY-MM-DD format.';
        }

        $allowedModes = ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE'];
        if (!in_array($paymentMode, $allowedModes, true)) {
            $errors[] = 'Please select a valid payment mode.';
        }

        if (!is_numeric($amountRaw) || (float)$amountRaw <= 0.00) {
            $errors[] = 'Payment amount must be greater than ₹0.00.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect("/ledgers/vendors/{$vendorId}/settle");
            return;
        }

        $paymentAmount = Financial::toDecimal((float)$amountRaw);
        $operatorId = Auth::id() ?? 1;

        try {
            $settledVouchers = Database::transaction(function() use (
                $vendorId, $vendor, $paymentDate, $paymentAmount, $paymentMode,
                $target, $transactionRef, $notes, $operatorId
            ) {
                // Fetch open purchases locked FOR UPDATE
                $openPurchases = Database::fetchAll("
                    SELECT * FROM purchases 
                    WHERE vendor_id = :vid AND due_amount > 0.00 
                    ORDER BY purchase_date ASC, id ASC
                    FOR UPDATE
                ", [':vid' => $vendorId]);

                $totalDue = '0.00';
                foreach ($openPurchases as $p) {
                    $totalDue = Financial::add($totalDue, (string)$p['due_amount']);
                }

                if ((float)$paymentAmount > (float)$totalDue) {
                    throw new \RuntimeException("Payment amount (₹{$paymentAmount}) exceeds total outstanding payable to vendor (₹{$totalDue}). Overpayment is prohibited.");
                }

                $vouchersCreated = [];
                $remainingToAllocate = $paymentAmount;

                // Case A: Specific Purchase Consignment
                if ($target !== 'FIFO_AUTO') {
                    $purchaseId = (int)$target;
                    $targetPurchase = null;
                    foreach ($openPurchases as $p) {
                        if ((int)$p['id'] === $purchaseId) {
                            $targetPurchase = $p;
                            break;
                        }
                    }

                    if (!$targetPurchase) {
                        throw new \RuntimeException("Selected purchase consignment not found or already settled.");
                    }

                    $due = (string)$targetPurchase['due_amount'];
                    if ((float)$paymentAmount > (float)$due) {
                        throw new \RuntimeException("Payment (₹{$paymentAmount}) exceeds consignment due balance (₹{$due}).");
                    }

                    $vouchersCreated[] = $this->applyPaymentToPurchase($targetPurchase, $paymentAmount, $paymentDate, $paymentMode, $transactionRef, $notes, $operatorId);
                }
                // Case B: FIFO Auto-Allocation across oldest open purchases
                else {
                    foreach ($openPurchases as $p) {
                        if ((float)$remainingToAllocate <= 0.00) {
                            break;
                        }

                        $due = (string)$p['due_amount'];
                        $portion = ((float)$remainingToAllocate >= (float)$due)
                            ? $due
                            : $remainingToAllocate;

                        $vouchersCreated[] = $this->applyPaymentToPurchase($p, $portion, $paymentDate, $paymentMode, $transactionRef, $notes, $operatorId);
                        $remainingToAllocate = Financial::sub($remainingToAllocate, $portion);
                    }
                }

                // Log audit trail
                AuditLogger::log(
                    'VENDOR_PAYABLE_SETTLED',
                    'payments',
                    $vouchersCreated[0]['id'] ?? 0,
                    null,
                    [
                        'vendor_id'       => $vendorId,
                        'vendor_name'     => $vendor['business_name'],
                        'amount_settled'  => $paymentAmount,
                        'payment_mode'    => $paymentMode,
                        'allocation_mode' => $target === 'FIFO_AUTO' ? 'FIFO' : 'SPECIFIC_PURCHASE',
                        'vouchers_count'  => count($vouchersCreated),
                    ],
                    "Paid ₹{$paymentAmount} to vendor {$vendor['business_name']} ({$paymentMode})",
                    $operatorId
                );

                return $vouchersCreated;
            });

            View::flash('success', "Vendor payment of ₹" . number_format((float)$paymentAmount, 2) . " recorded successfully across " . count($settledVouchers) . " consignment(s).");
            Security::safeRedirect("/ledgers/vendors/{$vendorId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Vendor payment failed: ' . $e->getMessage());
            Security::safeRedirect("/ledgers/vendors/{$vendorId}/settle");
        }
    }

    // =========================================================================
    // 3. UNIVERSAL PAYMENTS REGISTER
    // =========================================================================

    /**
     * Unified payment register showing all financial receipts, disbursements, and refunds.
     */
    public function payments(): void
    {
        Auth::requireLogin();

        $typeFilter = strtoupper(trim($_GET['type'] ?? 'ALL')); // ALL, SALE_RECEIPT, REPAIR_RECEIPT, PURCHASE_PAYMENT, SALE_REFUND
        $modeFilter = strtoupper(trim($_GET['mode'] ?? 'ALL'));
        $dateFrom   = trim($_GET['date_from'] ?? '');
        $dateTo     = trim($_GET['date_to'] ?? '');
        $search     = trim($_GET['q'] ?? '');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if ($typeFilter !== 'ALL') {
            $where[] = "p.transaction_type = :ttype";
            $params[':ttype'] = $typeFilter;
        }

        if ($modeFilter !== 'ALL') {
            $where[] = "p.payment_mode = :pmode";
            $params[':pmode'] = $modeFilter;
        }

        if ($dateFrom !== '') {
            $where[] = "p.payment_date >= :dfrom";
            $params[':dfrom'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = "p.payment_date <= :dto";
            $params[':dto'] = $dateTo;
        }

        if ($search !== '') {
            $where[] = "(
                p.payment_number LIKE :q1 OR 
                p.transaction_ref LIKE :q2 OR 
                p.notes LIKE :q3 OR
                v.business_name LIKE :q4
            )";
            $qWild = '%' . $search . '%';
            $params[':q1'] = $qWild;
            $params[':q2'] = $qWild;
            $params[':q3'] = $qWild;
            $params[':q4'] = $qWild;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $totalRows = (int)Database::fetchColumn("
            SELECT COUNT(*) 
            FROM payments p
            LEFT JOIN vendors v ON v.id = p.vendor_id
            {$whereSql}
        ", $params);

        $payments = Database::fetchAll("
            SELECT 
                p.*,
                u.full_name AS received_by_name,
                v.business_name AS vendor_name,
                s.sale_number,
                s.customer_name AS sale_customer_name,
                s.customer_phone AS sale_customer_phone,
                r.repair_number,
                r.customer_name AS repair_customer_name,
                r.customer_phone AS repair_customer_phone,
                sr.return_number
            FROM payments p
            JOIN users u ON u.id = p.received_by
            LEFT JOIN vendors v ON v.id = p.vendor_id
            LEFT JOIN sales s ON (p.reference_table = 'sales' AND s.id = p.reference_id)
            LEFT JOIN repair_records r ON (p.reference_table = 'repair_records' AND r.id = p.reference_id)
            LEFT JOIN sale_returns sr ON (p.reference_table = 'sale_returns' AND sr.id = p.reference_id)
            {$whereSql}
            ORDER BY p.payment_date DESC, p.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", $params);

        // Calculate period inflow vs outflow
        $inflowSql = "
            SELECT COALESCE(SUM(amount), 0.00) 
            FROM payments p 
            LEFT JOIN vendors v ON v.id = p.vendor_id
            " . (!empty($where) ? $whereSql . " AND " : "WHERE ") . " p.transaction_type IN ('SALE_RECEIPT', 'REPAIR_RECEIPT')
        ";
        $totalInflow = (string)Database::fetchColumn($inflowSql, $params);

        $outflowSql = "
            SELECT COALESCE(SUM(amount), 0.00) 
            FROM payments p 
            LEFT JOIN vendors v ON v.id = p.vendor_id
            " . (!empty($where) ? $whereSql . " AND " : "WHERE ") . " p.transaction_type IN ('PURCHASE_PAYMENT', 'SALE_REFUND')
        ";
        $totalOutflow = (string)Database::fetchColumn($outflowSql, $params);

        $netCashFlow = Financial::sub($totalInflow, $totalOutflow);

        View::render('ledgers/payments', [
            'pageTitle'    => 'Universal Payments Register',
            'payments'     => $payments,
            'totalRows'    => $totalRows,
            'currentPage'  => $page,
            'totalPages'   => max(1, (int)ceil($totalRows / $perPage)),
            'typeFilter'   => $typeFilter,
            'modeFilter'   => $modeFilter,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'search'       => $search,
            'totalInflow'  => $totalInflow,
            'totalOutflow' => $totalOutflow,
            'netCashFlow'  => $netCashFlow,
        ], 'main');
    }

    // =========================================================================
    // 4. TIMELINE HELPERS & PAYMENT DISPATCHERS
    // =========================================================================

    /**
     * Retrieve complete customer timeline across sales, repairs, returns, and payments.
     */
    private function getCustomerTimeline(string $identifier, bool $forUpdate = false): array
    {
        $isPhone = !str_starts_with($identifier, 'name:');
        $phoneValue = $isPhone ? $identifier : '';
        $nameValue = !$isPhone ? substr($identifier, 5) : '';

        $lockClause = $forUpdate ? 'FOR UPDATE' : '';

        // Fetch sales
        if ($isPhone) {
            $sales = Database::fetchAll("
                SELECT * FROM sales 
                WHERE customer_phone = :phone AND is_voided = 0
                ORDER BY sale_date ASC, id ASC {$lockClause}
            ", [':phone' => $phoneValue]);

            $repairs = Database::fetchAll("
                SELECT * FROM repair_records 
                WHERE customer_phone = :phone AND repair_type = 'CUSTOMER_SERVICE'
                ORDER BY repair_date ASC, id ASC {$lockClause}
            ", [':phone' => $phoneValue]);
        } else {
            $sales = Database::fetchAll("
                SELECT * FROM sales 
                WHERE LOWER(customer_name) = :name AND is_voided = 0
                ORDER BY sale_date ASC, id ASC {$lockClause}
            ", [':name' => $nameValue]);

            $repairs = Database::fetchAll("
                SELECT * FROM repair_records 
                WHERE LOWER(customer_name) = :name AND repair_type = 'CUSTOMER_SERVICE'
                ORDER BY repair_date ASC, id ASC {$lockClause}
            ", [':name' => $nameValue]);
        }

        if (empty($sales) && empty($repairs)) {
            return ['exists' => false];
        }

        // Determine customer display name & phone
        $customerName = 'Walk-in Customer';
        $customerPhone = $phoneValue;

        foreach ($sales as $s) {
            if (!empty($s['customer_name'])) {
                $customerName = $s['customer_name'];
            }
            if ($customerPhone === '' && !empty($s['customer_phone'])) {
                $customerPhone = $s['customer_phone'];
            }
        }
        foreach ($repairs as $r) {
            if ($customerName === 'Walk-in Customer' && !empty($r['customer_name'])) {
                $customerName = $r['customer_name'];
            }
            if ($customerPhone === '' && !empty($r['customer_phone'])) {
                $customerPhone = $r['customer_phone'];
            }
        }

        // Fetch all payments for these sales & repairs
        $saleIds = array_column($sales, 'id');
        $repairIds = array_column($repairs, 'id');

        $salePayments = [];
        if (!empty($saleIds)) {
            $inSales = implode(',', array_map('intval', $saleIds));
            $salePayments = Database::fetchAll("
                SELECT p.*, u.full_name AS received_by_name, s.sale_number
                FROM payments p
                JOIN users u ON u.id = p.received_by
                JOIN sales s ON s.id = p.reference_id
                WHERE p.reference_table = 'sales' AND p.reference_id IN ({$inSales})
                ORDER BY p.payment_date ASC, p.id ASC
            ");
        }

        $repairPayments = [];
        if (!empty($repairIds)) {
            $inRepairs = implode(',', array_map('intval', $repairIds));
            $repairPayments = Database::fetchAll("
                SELECT p.*, u.full_name AS received_by_name, r.repair_number
                FROM payments p
                JOIN users u ON u.id = p.received_by
                JOIN repair_records r ON r.id = p.reference_id
                WHERE p.reference_table = 'repair_records' AND p.reference_id IN ({$inRepairs})
                ORDER BY p.payment_date ASC, p.id ASC
            ");
        }

        // Fetch returns on these sales
        $returns = [];
        if (!empty($saleIds)) {
            $inSales = implode(',', array_map('intval', $saleIds));
            $returns = Database::fetchAll("
                SELECT sr.*, s.sale_number, u.full_name AS processed_by_name
                FROM sale_returns sr
                JOIN users u ON u.id = sr.created_by
                JOIN sales s ON s.id = sr.sale_id
                WHERE sr.sale_id IN ({$inSales})
                ORDER BY sr.return_date ASC, sr.id ASC
            ");
        }

        // Build unified chronological timeline
        $timeline = [];

        foreach ($sales as $s) {
            $timeline[] = [
                'type'        => 'SALE',
                'date'        => $s['sale_date'],
                'time'        => $s['sale_time'],
                'ref'         => $s['sale_number'],
                'url'         => "/sales/{$s['id']}",
                'description' => "Counter Sale Purchase",
                'debit'       => (string)$s['final_total_amount'],
                'credit'      => '0.00',
                'timestamp'   => strtotime($s['sale_date'] . ' ' . $s['sale_time']),
            ];
        }

        foreach ($repairs as $r) {
            $timeline[] = [
                'type'        => 'REPAIR',
                'date'        => $r['repair_date'],
                'time'        => '12:00:00',
                'ref'         => $r['repair_number'],
                'url'         => "/repairs/{$r['id']}",
                'description' => "Customer Repair: " . ($r['device_model'] ?? 'Device'),
                'debit'       => (string)$r['amount_charged'],
                'credit'      => '0.00',
                'timestamp'   => strtotime($r['repair_date'] . ' 12:00:00'),
            ];
        }

        foreach ($returns as $ret) {
            $timeline[] = [
                'type'        => 'RETURN_CREDIT',
                'date'        => $ret['return_date'],
                'time'        => $ret['return_time'],
                'ref'         => $ret['return_number'],
                'url'         => "/returns/{$ret['id']}",
                'description' => "Return Credit on {$ret['sale_number']}",
                'debit'       => '0.00',
                'credit'      => (string)$ret['total_refund_amount'],
                'timestamp'   => strtotime($ret['return_date'] . ' ' . $ret['return_time']),
            ];
        }

        foreach ($salePayments as $p) {
            $timeline[] = [
                'type'        => 'PAYMENT',
                'date'        => $p['payment_date'],
                'time'        => '12:00:00',
                'ref'         => $p['payment_number'],
                'url'         => "/sales/{$p['reference_id']}",
                'description' => "Receipt for Sale {$p['sale_number']} ({$p['payment_mode']})",
                'debit'       => '0.00',
                'credit'      => (string)$p['amount'],
                'timestamp'   => strtotime($p['payment_date'] . ' 12:00:00'),
            ];
        }

        foreach ($repairPayments as $p) {
            $timeline[] = [
                'type'        => 'PAYMENT',
                'date'        => $p['payment_date'],
                'time'        => '12:00:00',
                'ref'         => $p['payment_number'],
                'url'         => "/repairs/{$p['reference_id']}",
                'description' => "Receipt for Repair {$p['repair_number']} ({$p['payment_mode']})",
                'debit'       => '0.00',
                'credit'      => (string)$p['amount'],
                'timestamp'   => strtotime($p['payment_date'] . ' 12:00:00'),
            ];
        }

        // Sort timeline chronologically
        usort($timeline, function ($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        // Compute running balance
        $runningBalance = '0.00';
        foreach ($timeline as &$row) {
            $runningBalance = Financial::add($runningBalance, $row['debit']);
            $runningBalance = Financial::sub($runningBalance, $row['credit']);
            $row['balance'] = $runningBalance;
        }
        unset($row);

        // Identify open (unsettled) sales & repairs
        $openSales = array_filter($sales, fn($s) => (float)$s['due_amount'] > 0.00);
        $openRepairs = array_filter($repairs, fn($r) => (float)$r['due_amount'] > 0.00);

        $totalDue = '0.00';
        foreach ($openSales as $s) {
            $totalDue = Financial::add($totalDue, (string)$s['due_amount']);
        }
        foreach ($openRepairs as $r) {
            $totalDue = Financial::add($totalDue, (string)$r['due_amount']);
        }

        return [
            'exists'       => true,
            'identifier'   => $identifier,
            'name'         => $customerName,
            'phone'        => $customerPhone,
            'total_due'    => $totalDue,
            'timeline'     => $timeline,
            'open_sales'   => array_values($openSales),
            'open_repairs' => array_values($openRepairs),
        ];
    }

    /**
     * Retrieve vendor timeline of purchases and payments with running balance.
     */
    private function getVendorTimeline(int $vendorId): array
    {
        $purchases = Database::fetchAll("
            SELECT * FROM purchases 
            WHERE vendor_id = :vid 
            ORDER BY purchase_date ASC, id ASC
        ", [':vid' => $vendorId]);

        $payments = Database::fetchAll("
            SELECT p.*, u.full_name AS received_by_name, pur.purchase_number
            FROM payments p
            JOIN users u ON u.id = p.received_by
            JOIN purchases pur ON pur.id = p.reference_id
            WHERE p.reference_table = 'purchases' AND p.vendor_id = :vid
            ORDER BY p.payment_date ASC, p.id ASC
        ", [':vid' => $vendorId]);

        $timeline = [];

        foreach ($purchases as $p) {
            $timeline[] = [
                'type'        => 'PURCHASE',
                'date'        => $p['purchase_date'],
                'ref'         => $p['purchase_number'],
                'url'         => "/purchases/{$p['id']}",
                'description' => "Consignment Purchase" . (!empty($p['vendor_invoice_no']) ? " (Inv: {$p['vendor_invoice_no']})" : ""),
                'credit'      => (string)$p['total_amount'], // Liability increases
                'debit'       => '0.00',
                'timestamp'   => strtotime($p['purchase_date'] . ' 09:00:00'),
            ];
        }

        foreach ($payments as $pay) {
            $timeline[] = [
                'type'        => 'PAYMENT',
                'date'        => $pay['payment_date'],
                'ref'         => $pay['payment_number'],
                'url'         => "/purchases/{$pay['reference_id']}",
                'description' => "Payment Voucher for {$pay['purchase_number']} ({$pay['payment_mode']})",
                'credit'      => '0.00',
                'debit'       => (string)$pay['amount'], // Liability decreases
                'timestamp'   => strtotime($pay['payment_date'] . ' 15:00:00'),
            ];
        }

        usort($timeline, function ($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        $runningBalance = '0.00';
        foreach ($timeline as &$row) {
            $runningBalance = Financial::add($runningBalance, $row['credit']);
            $runningBalance = Financial::sub($runningBalance, $row['debit']);
            $row['balance'] = $runningBalance;
        }
        unset($row);

        $openPurchases = array_filter($purchases, fn($p) => (float)$p['due_amount'] > 0.00);

        $totalPurchased = '0.00';
        $totalPaid = '0.00';
        $totalDue = '0.00';

        foreach ($purchases as $p) {
            $totalPurchased = Financial::add($totalPurchased, (string)$p['total_amount']);
            $totalPaid = Financial::add($totalPaid, (string)$p['paid_amount']);
            $totalDue = Financial::add($totalDue, (string)$p['due_amount']);
        }

        return [
            'summary' => [
                'total_purchased' => $totalPurchased,
                'total_paid'      => $totalPaid,
                'total_due'       => $totalDue,
                'open_count'      => count($openPurchases),
            ],
            'timeline'       => $timeline,
            'open_purchases' => array_values($openPurchases),
        ];
    }

    /**
     * Apply payment portion to a specific sale record and insert receipt.
     */
    private function applyPaymentToSale(
        array $sale,
        string $amount,
        string $paymentDate,
        string $paymentMode,
        string $transactionRef,
        string $notes,
        int $operatorId
    ): array {
        $saleId = (int)$sale['id'];
        $saleNumber = $sale['sale_number'];

        // Recalculate true paid amount from payments ledger
        $existingPaid = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) 
            FROM payments 
            WHERE reference_table = 'sales' AND reference_id = :id AND transaction_type = 'SALE_RECEIPT'
        ", [':id' => $saleId]);

        $newPaid = Financial::add($existingPaid, $amount);
        $finalTotal = (string)$sale['final_total_amount'];
        $newDue = Financial::sub($finalTotal, $newPaid);
        $paymentStatus = ((float)$newDue <= 0.00) ? 'PAID' : 'PARTIAL';

        // Insert payment voucher
        $paymentNumber = 'REC-' . date('Ymd', strtotime($paymentDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

        Database::execute("
            INSERT INTO payments (
                payment_number, transaction_type, reference_table,
                reference_id, vendor_id, payment_date, amount,
                payment_mode, transaction_ref, notes, received_by, created_at
            ) VALUES (
                :pnum, 'SALE_RECEIPT', 'sales',
                :ref_id, NULL, :pdate, :amt,
                :pmode, :txref, :notes, :rcv_by, NOW()
            )
        ", [
            ':pnum'   => $paymentNumber,
            ':ref_id' => $saleId,
            ':pdate'  => $paymentDate,
            ':amt'    => $amount,
            ':pmode'  => $paymentMode,
            ':txref'  => $transactionRef !== '' ? $transactionRef : null,
            ':notes'  => $notes !== '' ? $notes : "Khata credit settlement for sale {$saleNumber}",
            ':rcv_by' => $operatorId,
        ]);

        $paymentId = (int)Database::lastInsertId();

        // Update sales record
        Database::execute("
            UPDATE sales 
            SET paid_amount = :paid, due_amount = :due, payment_status = :status, updated_at = NOW() 
            WHERE id = :id
        ", [
            ':paid'   => $newPaid,
            ':due'    => $newDue,
            ':status' => $paymentStatus,
            ':id'     => $saleId,
        ]);

        return [
            'id'             => $paymentId,
            'payment_number' => $paymentNumber,
            'amount'         => $amount,
            'ref'            => $saleNumber,
            'type'           => 'SALE',
        ];
    }

    /**
     * Apply payment portion to a specific customer repair record and insert receipt.
     */
    private function applyPaymentToRepair(
        array $repair,
        string $amount,
        string $paymentDate,
        string $paymentMode,
        string $transactionRef,
        string $notes,
        int $operatorId
    ): array {
        $repairId = (int)$repair['id'];
        $repairNumber = $repair['repair_number'];

        $existingPaid = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) 
            FROM payments 
            WHERE reference_table = 'repair_records' AND reference_id = :id AND transaction_type = 'REPAIR_RECEIPT'
        ", [':id' => $repairId]);

        $newPaid = Financial::add($existingPaid, $amount);
        $amountCharged = (string)$repair['amount_charged'];
        $newDue = Financial::sub($amountCharged, $newPaid);
        $paymentStatus = ((float)$newDue <= 0.00) ? 'PAID' : 'PARTIAL';

        $paymentNumber = 'REP-REC-' . date('Ymd', strtotime($paymentDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

        Database::execute("
            INSERT INTO payments (
                payment_number, transaction_type, reference_table,
                reference_id, vendor_id, payment_date, amount,
                payment_mode, transaction_ref, notes, received_by, created_at
            ) VALUES (
                :pnum, 'REPAIR_RECEIPT', 'repair_records',
                :ref_id, NULL, :pdate, :amt,
                :pmode, :txref, :notes, :rcv_by, NOW()
            )
        ", [
            ':pnum'   => $paymentNumber,
            ':ref_id' => $repairId,
            ':pdate'  => $paymentDate,
            ':amt'    => $amount,
            ':pmode'  => $paymentMode,
            ':txref'  => $transactionRef !== '' ? $transactionRef : null,
            ':notes'  => $notes !== '' ? $notes : "Khata credit settlement for repair {$repairNumber}",
            ':rcv_by' => $operatorId,
        ]);

        $paymentId = (int)Database::lastInsertId();

        Database::execute("
            UPDATE repair_records 
            SET paid_amount = :paid, due_amount = :due, payment_status = :status, updated_at = NOW() 
            WHERE id = :id
        ", [
            ':paid'   => $newPaid,
            ':due'    => $newDue,
            ':status' => $paymentStatus,
            ':id'     => $repairId,
        ]);

        return [
            'id'             => $paymentId,
            'payment_number' => $paymentNumber,
            'amount'         => $amount,
            'ref'            => $repairNumber,
            'type'           => 'REPAIR',
        ];
    }

    /**
     * Apply payment portion to a specific vendor purchase consignment and insert payment voucher.
     */
    private function applyPaymentToPurchase(
        array $purchase,
        string $amount,
        string $paymentDate,
        string $paymentMode,
        string $transactionRef,
        string $notes,
        int $operatorId
    ): array {
        $purchaseId = (int)$purchase['id'];
        $purchaseNumber = $purchase['purchase_number'];
        $vendorId = (int)$purchase['vendor_id'];

        $existingPaid = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) 
            FROM payments 
            WHERE reference_table = 'purchases' AND reference_id = :id AND transaction_type = 'PURCHASE_PAYMENT'
        ", [':id' => $purchaseId]);

        $newPaid = Financial::add($existingPaid, $amount);
        $totalAmount = (string)$purchase['total_amount'];
        $newDue = Financial::sub($totalAmount, $newPaid);
        $paymentStatus = ((float)$newDue <= 0.00) ? 'PAID' : 'PARTIAL';

        $paymentNumber = 'PAY-' . date('Ymd', strtotime($paymentDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

        Database::execute("
            INSERT INTO payments (
                payment_number, transaction_type, reference_table,
                reference_id, vendor_id, payment_date, amount,
                payment_mode, transaction_ref, notes, received_by, created_at
            ) VALUES (
                :pnum, 'PURCHASE_PAYMENT', 'purchases',
                :ref_id, :vid, :pdate, :amt,
                :pmode, :txref, :notes, :rcv_by, NOW()
            )
        ", [
            ':pnum'   => $paymentNumber,
            ':ref_id' => $purchaseId,
            ':vid'    => $vendorId,
            ':pdate'  => $paymentDate,
            ':amt'    => $amount,
            ':pmode'  => $paymentMode,
            ':txref'  => $transactionRef !== '' ? $transactionRef : null,
            ':notes'  => $notes !== '' ? $notes : "Supplier payable settlement for consignment {$purchaseNumber}",
            ':rcv_by' => $operatorId,
        ]);

        $paymentId = (int)Database::lastInsertId();

        Database::execute("
            UPDATE purchases 
            SET paid_amount = :paid, due_amount = :due, payment_status = :status, updated_at = NOW() 
            WHERE id = :id
        ", [
            ':paid'   => $newPaid,
            ':due'    => $newDue,
            ':status' => $paymentStatus,
            ':id'     => $purchaseId,
        ]);

        return [
            'id'             => $paymentId,
            'payment_number' => $paymentNumber,
            'amount'         => $amount,
            'ref'            => $purchaseNumber,
            'type'           => 'PURCHASE',
        ];
    }

    private function requireCustomerCreditPermission(): void
    {
        if (!Auth::isOwner() && !Authorization::hasPermission('manage_sales') && !Authorization::hasPermission('perform_sales')) {
            View::flash('danger', 'You do not have permission to access the customer credit ledger.');
            Security::safeRedirect('/');
            exit;
        }
    }

    private function requireVendorPayablesPermission(): void
    {
        if (!Auth::isOwner() && !Authorization::hasPermission('manage_purchases') && !Authorization::hasPermission('view_vendor_costs')) {
            View::flash('danger', 'You do not have permission to access the vendor payables ledger.');
            Security::safeRedirect('/');
            exit;
        }
    }
}
