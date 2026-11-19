<?php
/**
 * User Directory & Management View
 * File: views/users/index.php
 */
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">&#128101; System Users &amp; Terminal Operators</span>
        <a href="/users/create" class="btn btn-primary btn-sm">
            <span>+</span> Add New User
        </a>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Assigned Permissions</th>
                        <th>Last Login</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                No system users found in the database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['username']) ?></strong>
                                    <?php if ($u['id'] == current_user()['id']): ?>
                                        <span class="badge badge-info" style="font-size: 0.65rem; margin-left: 4px;">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($u['full_name']) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'OWNER'): ?>
                                        <span class="badge" style="background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;">OWNER</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">STAFF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span class="badge badge-success">&#10003; Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">&#10007; Deactivated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'OWNER'): ?>
                                        <span style="color: var(--primary); font-weight: 600; font-size: 0.8rem;">Full Access (Automatic)</span>
                                    <?php else: ?>
                                        <span class="badge badge-info" style="font-weight: 600;">
                                            <?= (int)$u['permission_count'] ?> Granted
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= !empty($u['last_login_at']) ? date('d M Y, h:i A', strtotime($u['last_login_at'])) : 'Never' ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <!-- Edit -->
                                        <a href="/users/<?= (int)$u['id'] ?>/edit" class="btn btn-secondary btn-sm" title="Edit User">
                                            Edit
                                        </a>

                                        <!-- Permissions (Staff only) -->
                                        <?php if ($u['role'] === 'STAFF'): ?>
                                            <a href="/users/<?= (int)$u['id'] ?>/permissions" class="btn btn-secondary btn-sm" style="color: var(--primary);" title="Configure Permissions">
                                                Permissions
                                            </a>
                                        <?php endif; ?>

                                        <!-- Reset Password -->
                                        <a href="/users/<?= (int)$u['id'] ?>/reset-password" class="btn btn-secondary btn-sm" title="Reset Password">
                                            Reset Key
                                        </a>

                                        <!-- Toggle Status Form -->
                                        <?php if ($u['id'] != current_user()['id']): ?>
                                            <form method="POST" action="/users/<?= (int)$u['id'] ?>/toggle-status" style="display: inline;" onsubmit="return confirm('Are you sure you want to change this user\'s access status?');">
                                                <?= csrf_field() ?>
                                                <?php if ((int)$u['is_active'] === 1): ?>
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Deactivate Account">Disable</button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-success btn-sm" title="Activate Account">Enable</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
