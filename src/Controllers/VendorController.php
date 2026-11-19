<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Security;
use AMH\AuditLogger;
use AMH\View;

/**
 * Vendor Management Controller
 *
 * Handles supplier and wholesale distributor directory,
 * contact details, active status toggling, and vendor profile inspection.
 */
class VendorController
{
    /**
     * List vendors with search, status filtering, and pagination.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $search = trim($_GET['q'] ?? '');
        $statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));
        if (!in_array($statusFilter, ['ALL', 'ACTIVE', 'INACTIVE'], true)) {
            $statusFilter = 'ALL';
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $whereClauses = [];
        $params = [];

        if ($statusFilter === 'ACTIVE') {
            $whereClauses[] = "v.is_active = 1";
        } elseif ($statusFilter === 'INACTIVE') {
            $whereClauses[] = "v.is_active = 0";
        }

        if ($search !== '') {
            $whereClauses[] = "(
                v.business_name LIKE :q1 OR 
                v.contact_person LIKE :q2 OR 
                v.phone LIKE :q3 OR 
                v.gstin LIKE :q4 OR
                v.email LIKE :q5
            )";
            $qWild = '%' . $search . '%';
            $params[':q1'] = $qWild;
            $params[':q2'] = $qWild;
            $params[':q3'] = $qWild;
            $params[':q4'] = $qWild;
            $params[':q5'] = $qWild;
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // Total count
        $countSql = "SELECT COUNT(*) FROM vendors v {$whereSql}";
        $totalItems = (int)Database::fetchColumn($countSql, $params);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        // Retrieve paginated records with aggregate purchase metrics
        $sql = "
            SELECT v.*,
                   COUNT(p.id) AS total_purchases_count,
                   COALESCE(SUM(p.total_amount), 0.00) AS total_purchased_amount,
                   COALESCE(SUM(p.due_amount), 0.00) AS total_due_amount
            FROM vendors v
            LEFT JOIN purchases p ON p.vendor_id = v.id
            {$whereSql}
            GROUP BY v.id
            ORDER BY v.business_name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $vendors = Database::fetchAll($sql, $params);

        // Permissions
        $canManage = Authorization::hasPermission('manage_purchases');
        $canViewCost = Authorization::hasPermission('view_purchase_costs') || Authorization::hasPermission('view_vendor_costs');

        // Suppress financial aggregates if unprivileged
        if (!$canViewCost) {
            foreach ($vendors as &$v) {
                $v['total_purchased_amount'] = null;
                $v['total_due_amount'] = null;
            }
            unset($v);
        }

        View::render('vendors/index', [
            'pageTitle'    => 'Vendor Directory',
            'vendors'      => $vendors,
            'search'       => $search,
            'statusFilter' => $statusFilter,
            'currentPage'  => $page,
            'totalPages'   => $totalPages,
            'totalItems'   => $totalItems,
            'canManage'    => $canManage,
            'canViewCost'  => $canViewCost,
        ], 'main');
    }

    /**
     * Render the create vendor form.
     */
    public function create(): void
    {
        Authorization::requirePermission('manage_purchases');

        View::render('vendors/create', [
            'pageTitle' => 'Register New Vendor',
            'formData'  => [
                'business_name'  => '',
                'contact_person' => '',
                'phone'          => '',
                'alt_phone'      => '',
                'email'          => '',
                'address'        => '',
                'gstin'          => '',
                'notes'          => '',
                'is_active'      => 1,
            ],
        ], 'main');
    }

    /**
     * Store a newly created vendor in the directory.
     */
    public function store(): void
    {
        Authorization::requirePermission('manage_purchases');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect('/vendors/create');
            return;
        }

        $businessName  = trim($_POST['business_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $altPhone      = trim($_POST['alt_phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $gstin         = strtoupper(trim($_POST['gstin'] ?? ''));
        $notes         = trim($_POST['notes'] ?? '');
        $isActive      = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];

        if ($businessName === '' || strlen($businessName) > 150) {
            $errors[] = 'Business name is required (up to 150 characters).';
        }

        if ($phone === '') {
            $errors[] = 'Primary contact phone number is required.';
        } elseif (!Security::validatePhone($phone)) {
            $errors[] = 'Phone number must contain between 10 and 15 digits.';
        }

        if ($altPhone !== '' && !Security::validatePhone($altPhone)) {
            $errors[] = 'Alternate phone number must contain between 10 and 15 digits.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($gstin !== '' && strlen($gstin) > 20) {
            $errors[] = 'GSTIN reference must not exceed 20 characters.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            View::render('vendors/create', [
                'pageTitle' => 'Register New Vendor',
                'formData'  => [
                    'business_name'  => $businessName,
                    'contact_person' => $contactPerson,
                    'phone'          => $phone,
                    'alt_phone'      => $altPhone,
                    'email'          => $email,
                    'address'        => $address,
                    'gstin'          => $gstin,
                    'notes'          => $notes,
                    'is_active'      => $isActive,
                ],
            ], 'main');
            return;
        }

        Database::execute("
            INSERT INTO vendors (
                business_name, contact_person, phone, alt_phone,
                email, address, gstin, notes, is_active,
                created_at, updated_at
            ) VALUES (
                :bname, :cperson, :phone, :altphone,
                :email, :address, :gstin, :notes, :active,
                NOW(), NOW()
            )
        ", [
            ':bname'    => $businessName,
            ':cperson'  => $contactPerson !== '' ? $contactPerson : null,
            ':phone'    => $phone,
            ':altphone' => $altPhone !== '' ? $altPhone : null,
            ':email'    => $email !== '' ? $email : null,
            ':address'  => $address !== '' ? $address : null,
            ':gstin'    => $gstin !== '' ? $gstin : null,
            ':notes'    => $notes !== '' ? $notes : null,
            ':active'   => $isActive,
        ]);

        $vendorId = (int)Database::lastInsertId();
        if ($vendorId === 0) {
            $vendorId = (int)Database::fetchColumn("SELECT MAX(id) FROM vendors");
        }

        AuditLogger::log(
            'VENDOR_CREATED',
            'vendors',
            $vendorId,
            null,
            ['business_name' => $businessName, 'phone' => $phone, 'is_active' => $isActive],
            "Registered vendor {$businessName} (ID #{$vendorId})"
        );

        View::flash('success', "Vendor '{$businessName}' has been successfully registered.");
        Security::safeRedirect("/vendors/{$vendorId}");
    }

    /**
     * Show vendor profile, purchase history, and payment ledger.
     */
    public function show(array $params): void
    {
        Auth::requireLogin();

        $id = (int)($params['id'] ?? 0);
        $vendor = Database::fetchOne("SELECT * FROM vendors WHERE id = :id", [':id' => $id]);

        if (!$vendor) {
            View::flash('danger', 'Vendor record not found.');
            Security::safeRedirect('/vendors');
            return;
        }

        // Recent purchases from this vendor
        $purchases = Database::fetchAll("
            SELECT p.*, u.username AS operator_username
            FROM purchases p
            LEFT JOIN users u ON u.id = p.created_by
            WHERE p.vendor_id = :vid
            ORDER BY p.purchase_date DESC, p.id DESC
            LIMIT 30
        ", [':vid' => $id]);

        // Payment history for this vendor across all purchases
        $payments = Database::fetchAll("
            SELECT pay.*, p.purchase_number, u.username AS received_by_username
            FROM payments pay
            JOIN purchases p ON p.id = pay.reference_id AND pay.reference_table = 'purchases'
            LEFT JOIN users u ON u.id = pay.received_by
            WHERE pay.vendor_id = :vid AND pay.transaction_type = 'PURCHASE_PAYMENT'
            ORDER BY pay.payment_date DESC, pay.id DESC
            LIMIT 30
        ", [':vid' => $id]);

        // Aggregate statistics
        $stats = Database::fetchOne("
            SELECT 
                COUNT(id) AS total_orders,
                COALESCE(SUM(total_amount), 0.00) AS grand_total_purchased,
                COALESCE(SUM(paid_amount), 0.00) AS grand_total_paid,
                COALESCE(SUM(due_amount), 0.00) AS grand_total_outstanding
            FROM purchases
            WHERE vendor_id = :vid
        ", [':vid' => $id]);

        $canManage = Authorization::hasPermission('manage_purchases');
        $canViewCost = Authorization::hasPermission('view_purchase_costs') || Authorization::hasPermission('view_vendor_costs');

        // Mask financial values if unprivileged
        if (!$canViewCost) {
            $stats['grand_total_purchased'] = null;
            $stats['grand_total_paid'] = null;
            $stats['grand_total_outstanding'] = null;

            foreach ($purchases as &$p) {
                $p['total_amount'] = null;
                $p['paid_amount'] = null;
                $p['due_amount'] = null;
            }
            unset($p);

            foreach ($payments as &$pay) {
                $pay['amount'] = null;
            }
            unset($pay);
        }

        View::render('vendors/show', [
            'pageTitle'   => $vendor['business_name'],
            'vendor'      => $vendor,
            'purchases'   => $purchases,
            'payments'    => $payments,
            'stats'       => $stats,
            'canManage'   => $canManage,
            'canViewCost' => $canViewCost,
        ], 'main');
    }

    /**
     * Render the edit vendor form.
     */
    public function edit(array $params): void
    {
        Authorization::requirePermission('manage_purchases');

        $id = (int)($params['id'] ?? 0);
        $vendor = Database::fetchOne("SELECT * FROM vendors WHERE id = :id", [':id' => $id]);

        if (!$vendor) {
            View::flash('danger', 'Vendor not found.');
            Security::safeRedirect('/vendors');
            return;
        }

        View::render('vendors/edit', [
            'pageTitle' => "Edit Vendor: {$vendor['business_name']}",
            'vendor'    => $vendor,
        ], 'main');
    }

    /**
     * Process vendor update.
     */
    public function update(array $params): void
    {
        Authorization::requirePermission('manage_purchases');

        $id = (int)($params['id'] ?? 0);
        $vendor = Database::fetchOne("SELECT * FROM vendors WHERE id = :id", [':id' => $id]);

        if (!$vendor) {
            View::flash('danger', 'Vendor not found.');
            Security::safeRedirect('/vendors');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect("/vendors/{$id}/edit");
            return;
        }

        $businessName  = trim($_POST['business_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $altPhone      = trim($_POST['alt_phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $gstin         = strtoupper(trim($_POST['gstin'] ?? ''));
        $notes         = trim($_POST['notes'] ?? '');
        $isActive      = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];

        if ($businessName === '' || strlen($businessName) > 150) {
            $errors[] = 'Business name is required (up to 150 characters).';
        }

        if ($phone === '') {
            $errors[] = 'Primary contact phone number is required.';
        } elseif (!Security::validatePhone($phone)) {
            $errors[] = 'Phone number must contain between 10 and 15 digits.';
        }

        if ($altPhone !== '' && !Security::validatePhone($altPhone)) {
            $errors[] = 'Alternate phone number must contain between 10 and 15 digits.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($gstin !== '' && strlen($gstin) > 20) {
            $errors[] = 'GSTIN reference must not exceed 20 characters.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            View::render('vendors/edit', [
                'pageTitle' => "Edit Vendor: {$vendor['business_name']}",
                'vendor'    => array_merge($vendor, [
                    'business_name'  => $businessName,
                    'contact_person' => $contactPerson,
                    'phone'          => $phone,
                    'alt_phone'      => $altPhone,
                    'email'          => $email,
                    'address'        => $address,
                    'gstin'          => $gstin,
                    'notes'          => $notes,
                    'is_active'      => $isActive,
                ]),
            ], 'main');
            return;
        }

        $oldValues = [
            'business_name'  => $vendor['business_name'],
            'contact_person' => $vendor['contact_person'],
            'phone'          => $vendor['phone'],
            'is_active'      => $vendor['is_active'],
        ];

        Database::execute("
            UPDATE vendors SET
                business_name = :bname,
                contact_person = :cperson,
                phone = :phone,
                alt_phone = :altphone,
                email = :email,
                address = :address,
                gstin = :gstin,
                notes = :notes,
                is_active = :active,
                updated_at = NOW()
            WHERE id = :id
        ", [
            ':bname'    => $businessName,
            ':cperson'  => $contactPerson !== '' ? $contactPerson : null,
            ':phone'    => $phone,
            ':altphone' => $altPhone !== '' ? $altPhone : null,
            ':email'    => $email !== '' ? $email : null,
            ':address'  => $address !== '' ? $address : null,
            ':gstin'    => $gstin !== '' ? $gstin : null,
            ':notes'    => $notes !== '' ? $notes : null,
            ':active'   => $isActive,
            ':id'       => $id,
        ]);

        AuditLogger::log(
            'VENDOR_UPDATED',
            'vendors',
            $id,
            $oldValues,
            ['business_name' => $businessName, 'phone' => $phone, 'is_active' => $isActive],
            "Updated vendor {$businessName} (ID #{$id})"
        );

        View::flash('success', "Vendor details for '{$businessName}' updated successfully.");
        Security::safeRedirect("/vendors/{$id}");
    }

    /**
     * Toggle vendor active status.
     */
    public function toggleStatus(array $params): void
    {
        Authorization::requirePermission('manage_purchases');

        $id = (int)($params['id'] ?? 0);
        $vendor = Database::fetchOne("SELECT id, business_name, is_active FROM vendors WHERE id = :id", [':id' => $id]);

        if (!$vendor) {
            View::flash('danger', 'Vendor not found.');
            Security::safeRedirect('/vendors');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/vendors');
            return;
        }

        $newStatus = (int)$vendor['is_active'] === 1 ? 0 : 1;

        Database::execute("UPDATE vendors SET is_active = :st, updated_at = NOW() WHERE id = :id", [
            ':st' => $newStatus,
            ':id' => $id,
        ]);

        AuditLogger::log(
            'VENDOR_STATUS_TOGGLED',
            'vendors',
            $id,
            ['is_active' => $vendor['is_active']],
            ['is_active' => $newStatus],
            "Toggled vendor {$vendor['business_name']} status to " . ($newStatus ? 'ACTIVE' : 'INACTIVE')
        );

        $statusText = $newStatus ? 'activated' : 'deactivated';
        View::flash('success', "Vendor '{$vendor['business_name']}' has been {$statusText}.");
        Security::safeRedirect('/vendors');
    }

    /**
     * Safe deletion defense: blocks deletion if vendor has purchases.
     */
    public function delete(array $params): void
    {
        Authorization::requirePermission('manage_purchases');

        $id = (int)($params['id'] ?? 0);
        $vendor = Database::fetchOne("SELECT id, business_name FROM vendors WHERE id = :id", [':id' => $id]);

        if (!$vendor) {
            View::flash('danger', 'Vendor not found.');
            Security::safeRedirect('/vendors');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/vendors');
            return;
        }

        // Check for historical purchases
        $purchaseCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM purchases WHERE vendor_id = :id", [':id' => $id]);

        if ($purchaseCount > 0) {
            View::flash('danger', "Cannot delete vendor '{$vendor['business_name']}' because {$purchaseCount} historical purchase consignment(s) exist. Deactivate this vendor instead to preserve accounting history.");
            Security::safeRedirect("/vendors/{$id}");
            return;
        }

        Database::execute("DELETE FROM vendors WHERE id = :id", [':id' => $id]);

        AuditLogger::log(
            'VENDOR_DELETED',
            'vendors',
            $id,
            ['business_name' => $vendor['business_name']],
            null,
            "Deleted vendor {$vendor['business_name']} with 0 purchase history"
        );

        View::flash('success', "Vendor '{$vendor['business_name']}' has been removed.");
        Security::safeRedirect('/vendors');
    }
}
