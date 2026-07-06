<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('admins');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/admins.php');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

function redirect_admins(string $msg = '', bool $error = false): never {
    if ($msg) {
        $_SESSION[$error ? 'admins_error' : 'admins_success'] = $msg;
    }
    header('Location: /admin/admins.php');
    exit;
}

function other_active_supers(PDO $pdo, int $excludeId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE admin_role = 'super' AND is_active = 1 AND id != ?");
    $stmt->execute([$excludeId]);
    return (int) $stmt->fetchColumn();
}

match ($action) {
    'save'          => do_save($id),
    'toggle_active' => do_toggle_active($id),
    'delete'        => do_delete($id),
    default         => redirect_admins(),
};

function sync_permissions(PDO $pdo, int $adminId, string $role): void {
    $pdo->prepare('DELETE FROM admin_permissions WHERE admin_id = ?')->execute([$adminId]);
    if ($role !== 'custom') {
        return;
    }
    $allowed  = array_keys(admin_all_sections());
    $sections = array_intersect((array) ($_POST['sections'] ?? []), $allowed);
    if (empty($sections)) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO admin_permissions (admin_id, section) VALUES (?, ?)');
    foreach ($sections as $section) {
        $stmt->execute([$adminId, $section]);
    }
}

function do_save(int $id): void {
    global $pdo;

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['admin_role'] ?? '';
    $password = $_POST['password'] ?? '';
    $validRoles = ['super', 'custom'];

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_admins('A valid email is required.', true);
    }
    if (!in_array($role, $validRoles, true)) {
        redirect_admins('Invalid role.', true);
    }

    $dupStmt = $pdo->prepare('SELECT id FROM admins WHERE email = ? AND id != ?');
    $dupStmt->execute([$email, $id]);
    if ($dupStmt->fetch()) {
        redirect_admins('That email is already in use by another admin.', true);
    }

    if ($id) {
        $stmt = $pdo->prepare('SELECT admin_role FROM admins WHERE id = ?');
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        if (!$current) {
            redirect_admins('Admin not found.', true);
        }

        if ($current['admin_role'] === 'super' && $role !== 'super' && other_active_supers($pdo, $id) === 0) {
            redirect_admins('Cannot change the role of the last super admin.', true);
        }

        if ($password !== '' && strlen($password) < 8) {
            redirect_admins('New password must be at least 8 characters.', true);
        }

        if ($password !== '') {
            $pdo->prepare('UPDATE admins SET name = ?, email = ?, admin_role = ?, password = ? WHERE id = ?')
                ->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $pdo->prepare('UPDATE admins SET name = ?, email = ?, admin_role = ? WHERE id = ?')
                ->execute([$name, $email, $role, $id]);
        }
        sync_permissions($pdo, $id, $role);
        redirect_admins('Admin updated.');
    }

    if (strlen($password) < 8) {
        redirect_admins('Password must be at least 8 characters.', true);
    }

    $stmt = $pdo->prepare('INSERT INTO admins (name, email, password, admin_role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    sync_permissions($pdo, (int) $pdo->lastInsertId(), $role);
    redirect_admins('Admin created.');
}

function do_toggle_active(int $id): void {
    global $pdo;
    if (!$id) redirect_admins();

    if ($id === (int) $_SESSION['user_id']) {
        redirect_admins('You cannot deactivate your own account.', true);
    }

    $stmt = $pdo->prepare('SELECT admin_role, is_active, is_owner FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    $admin = $stmt->fetch();
    if (!$admin) redirect_admins('Admin not found.', true);

    if ($admin['is_active'] && $admin['is_owner']) {
        redirect_admins('This admin account is protected and cannot be deactivated.', true);
    }

    if ($admin['is_active'] && $admin['admin_role'] === 'super' && other_active_supers($pdo, $id) === 0) {
        redirect_admins('Cannot deactivate the last super admin.', true);
    }

    $pdo->prepare('UPDATE admins SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    redirect_admins($admin['is_active'] ? 'Admin deactivated.' : 'Admin reactivated.');
}

function do_delete(int $id): void {
    global $pdo;
    if (!$id) redirect_admins('Invalid admin.', true);

    if ($id === (int) $_SESSION['user_id']) {
        redirect_admins('You cannot delete your own account.', true);
    }

    $stmt = $pdo->prepare('SELECT admin_role, is_owner FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    $admin = $stmt->fetch();
    if (!$admin) redirect_admins('Admin not found.', true);

    if ($admin['is_owner']) {
        redirect_admins('This admin account is protected and cannot be deleted.', true);
    }

    if ($admin['admin_role'] === 'super' && other_active_supers($pdo, $id) === 0) {
        redirect_admins('Cannot delete the last super admin.', true);
    }

    $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
    redirect_admins('Admin deleted.');
}
