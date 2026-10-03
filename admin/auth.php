<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true,
    ]);
}

function admin_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function admin_verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token)
        && isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $token);
}

function admin_is_authenticated(): bool
{
    return isset(
        $_SESSION['admin_user_id'],
        $_SESSION['admin_name'],
        $_SESSION['admin_role']
    );
}

function admin_require_authentication(): void
{
    if (!admin_is_authenticated()) {
        header('Location: login.php');
        exit;
    }
}

function admin_refresh_identity(mysqli $conn): bool
{
    $userId = (int) $_SESSION['admin_user_id'];
    $statement = $conn->prepare(
        "SELECT users.full_name, roles.role_name
         FROM users
         INNER JOIN roles ON roles.role_id = users.role_id
         WHERE users.user_id = ? AND users.is_active = 1
           AND roles.role_name IN ('Admin', 'Cashier', 'Kitchen')
         LIMIT 1"
    );
    $statement->bind_param('i', $userId);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        return false;
    }

    if ($_SESSION['admin_name'] !== $user['full_name']
        || $_SESSION['admin_role'] !== $user['role_name']
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_role'] = $user['role_name'];
    }

    return true;
}

function admin_role_label(string $role): string
{
    $labels = [
        'Admin' => 'Pentadbir',
        'Cashier' => 'Juruwang',
        'Kitchen' => 'Dapur',
    ];

    return $labels[$role] ?? $role;
}

function admin_log_action(
    mysqli $conn,
    string $action,
    string $entityType,
    ?int $entityId,
    int $userId
): void {
    $statement = $conn->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id)
         VALUES (?, ?, ?, ?)'
    );
    $statement->bind_param('issi', $userId, $action, $entityType, $entityId);
    $statement->execute();
}
