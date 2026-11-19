<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Security;
use AMH\View;
use AMH\AuditLogger;

/**
 * Category Management Controller
 *
 * Manages dynamic product accessory and device categories.
 * Enforces server-side uniqueness, safe deactivation, and dependency checks.
 */
class CategoryController
{
    /**
     * Display listing of categories with active product counts.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $categories = Database::fetchAll("
            SELECT c.id, c.name, c.slug, c.description, c.is_active, c.created_at,
                   COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            GROUP BY c.id, c.name, c.slug, c.description, c.is_active, c.created_at
            ORDER BY c.name ASC
        ");

        View::render('categories/index', [
            'pageTitle'    => 'Category Management',
            'categories'   => $categories,
            'canManage'    => Authorization::hasPermission('manage_inventory'),
        ], 'main');
    }

    /**
     * Render the create category form.
     */
    public function create(): void
    {
        Authorization::requirePermission('manage_inventory');

        View::render('categories/create', [
            'pageTitle' => 'Create New Category',
            'formData'  => [],
        ], 'main');
    }

    /**
     * Store a newly created category.
     */
    public function store(): void
    {
        Authorization::requirePermission('manage_inventory');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect('/categories/create');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Auto-generate slug from name if empty
        if ($slug === '' && $name !== '') {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        } else {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $slug), '-'));
        }

        // Validation
        $errors = [];
        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Category name must be between 2 and 100 characters.';
        }

        if (strlen($slug) < 2 || strlen($slug) > 100) {
            $errors[] = 'Category slug must be between 2 and 100 alphanumeric characters/dashes.';
        }

        if (strlen($description) > 255) {
            $errors[] = 'Description cannot exceed 255 characters.';
        }

        // Check uniqueness of name
        $existingName = Database::fetchOne("SELECT id FROM categories WHERE name = :name LIMIT 1", [':name' => $name]);
        if ($existingName) {
            $errors[] = "A category with the name '{$name}' already exists.";
        }

        // Check uniqueness of slug
        $existingSlug = Database::fetchOne("SELECT id FROM categories WHERE slug = :slug LIMIT 1", [':slug' => $slug]);
        if ($existingSlug) {
            $errors[] = "A category with the URL slug '{$slug}' already exists.";
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            View::render('categories/create', [
                'pageTitle' => 'Create New Category',
                'formData'  => [
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description,
                    'is_active'   => $isActive,
                ],
            ], 'main');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        Database::transaction(function() use ($name, $slug, $description, $isActive, $operatorId) {
            Database::execute("
                INSERT INTO categories (name, slug, description, is_active, created_at, updated_at)
                VALUES (:name, :slug, :desc, :act, NOW(), NOW())
            ", [
                ':name' => $name,
                ':slug' => $slug,
                ':desc' => $description !== '' ? $description : null,
                ':act'  => $isActive,
            ]);

            $newId = (int)Database::fetchColumn("SELECT id FROM categories WHERE slug = :slug", [':slug' => $slug]);

            AuditLogger::log(
                'CATEGORY_CREATED',
                'categories',
                $newId,
                null,
                ['name' => $name, 'slug' => $slug, 'is_active' => $isActive],
                "Created product category '{$name}'",
                $operatorId
            );
        });

        View::flash('success', "Category '{$name}' created successfully.");
        Security::safeRedirect('/categories');
    }

    /**
     * Render the edit category form.
     */
    public function edit(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $id = (int)($params['id'] ?? 0);
        $category = Database::fetchOne("SELECT id, name, slug, description, is_active FROM categories WHERE id = :id", [':id' => $id]);

        if (!$category) {
            View::flash('danger', 'Category not found.');
            Security::safeRedirect('/categories');
            return;
        }

        View::render('categories/edit', [
            'pageTitle' => "Edit Category: {$category['name']}",
            'category'  => $category,
        ], 'main');
    }

    /**
     * Process category update.
     */
    public function update(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $id = (int)($params['id'] ?? 0);
        $category = Database::fetchOne("SELECT id, name, slug, description, is_active FROM categories WHERE id = :id", [':id' => $id]);

        if (!$category) {
            View::flash('danger', 'Category not found.');
            Security::safeRedirect('/categories');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect("/categories/{$id}/edit");
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($slug === '' && $name !== '') {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        } else {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $slug), '-'));
        }

        $errors = [];
        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Category name must be between 2 and 100 characters.';
        }

        if (strlen($slug) < 2 || strlen($slug) > 100) {
            $errors[] = 'Category slug must be between 2 and 100 alphanumeric characters/dashes.';
        }

        if (strlen($description) > 255) {
            $errors[] = 'Description cannot exceed 255 characters.';
        }

        // Check uniqueness of name excluding current id
        $existingName = Database::fetchOne("SELECT id FROM categories WHERE name = :name AND id != :id LIMIT 1", [':name' => $name, ':id' => $id]);
        if ($existingName) {
            $errors[] = "Another category with the name '{$name}' already exists.";
        }

        // Check uniqueness of slug excluding current id
        $existingSlug = Database::fetchOne("SELECT id FROM categories WHERE slug = :slug AND id != :id LIMIT 1", [':slug' => $slug, ':id' => $id]);
        if ($existingSlug) {
            $errors[] = "Another category with the URL slug '{$slug}' already exists.";
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            View::render('categories/edit', [
                'pageTitle' => "Edit Category: {$category['name']}",
                'category'  => [
                    'id'          => $id,
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description,
                    'is_active'   => $isActive,
                ],
            ], 'main');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        Database::transaction(function() use ($id, $name, $slug, $description, $isActive, $category, $operatorId) {
            Database::execute("
                UPDATE categories
                SET name = :name, slug = :slug, description = :desc, is_active = :act, updated_at = NOW()
                WHERE id = :id
            ", [
                ':name' => $name,
                ':slug' => $slug,
                ':desc' => $description !== '' ? $description : null,
                ':act'  => $isActive,
                ':id'   => $id,
            ]);

            AuditLogger::log(
                'CATEGORY_UPDATED',
                'categories',
                $id,
                ['name' => $category['name'], 'slug' => $category['slug'], 'is_active' => $category['is_active']],
                ['name' => $name, 'slug' => $slug, 'is_active' => $isActive],
                "Updated product category '{$name}'",
                $operatorId
            );
        });

        View::flash('success', "Category '{$name}' updated successfully.");
        Security::safeRedirect('/categories');
    }

    /**
     * Toggle active/inactive status for a category.
     */
    public function toggleStatus(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/categories');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $category = Database::fetchOne("SELECT id, name, is_active FROM categories WHERE id = :id", [':id' => $id]);

        if (!$category) {
            View::flash('danger', 'Category not found.');
            Security::safeRedirect('/categories');
            return;
        }

        $newStatus = ((int)$category['is_active'] === 1) ? 0 : 1;
        $operatorId = Auth::id() ?? 1;

        Database::execute("UPDATE categories SET is_active = :act, updated_at = NOW() WHERE id = :id", [
            ':act' => $newStatus,
            ':id'  => $id,
        ]);

        AuditLogger::log(
            'CATEGORY_STATUS_TOGGLED',
            'categories',
            $id,
            ['is_active' => $category['is_active']],
            ['is_active' => $newStatus],
            "Toggled category '{$category['name']}' to " . ($newStatus ? 'Active' : 'Inactive'),
            $operatorId
        );

        $actionText = $newStatus ? 'activated' : 'deactivated';
        View::flash('success', "Category '{$category['name']}' has been {$actionText}.");
        Security::safeRedirect('/categories');
    }

    /**
     * Safe category deletion with strict dependency checking.
     */
    public function delete(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/categories');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $category = Database::fetchOne("SELECT id, name FROM categories WHERE id = :id", [':id' => $id]);

        if (!$category) {
            View::flash('danger', 'Category not found.');
            Security::safeRedirect('/categories');
            return;
        }

        // Check if any products depend on this category
        $productCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM products WHERE category_id = :id",
            [':id' => $id]
        );

        if ($productCount > 0) {
            View::flash(
                'danger',
                "Cannot delete category '{$category['name']}' because {$productCount} product(s) are assigned to it. Please deactivate the category instead to maintain historical integrity."
            );
            Security::safeRedirect('/categories');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        Database::execute("DELETE FROM categories WHERE id = :id", [':id' => $id]);

        AuditLogger::log(
            'CATEGORY_DELETED',
            'categories',
            $id,
            ['name' => $category['name']],
            null,
            "Deleted empty category '{$category['name']}'",
            $operatorId
        );

        View::flash('success', "Category '{$category['name']}' was removed successfully.");
        Security::safeRedirect('/categories');
    }
}
