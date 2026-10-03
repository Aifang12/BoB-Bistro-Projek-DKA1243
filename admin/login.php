<?php

require_once __DIR__ . '/auth.php';

if (admin_is_authenticated()) {
    header('Location: index.php');
    exit;
}

$error = isset($_GET['setup'])
    ? 'Admin pertama berjaya didaftarkan. Sila log masuk.'
    : (isset($_GET['expired']) ? 'Sesi tamat atau akaun tidak aktif. Sila log masuk semula.' : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf()) {
        $error = 'Sesi borang tamat. Sila muat semula halaman dan cuba lagi.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        try {
            require_once __DIR__ . '/../config.php';
            $statement = $conn->prepare(
                "SELECT users.user_id, users.full_name, users.password_hash,
                        roles.role_name
                 FROM users
                 INNER JOIN roles ON roles.role_id = users.role_id
                 WHERE users.username = ? AND users.is_active = 1
                   AND roles.role_name IN ('Admin', 'Cashier', 'Kitchen')
                 LIMIT 1"
            );
            $statement->bind_param('s', $username);
            $statement->execute();
            $user = $statement->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_user_id'] = (int) $user['user_id'];
                $_SESSION['admin_name'] = $user['full_name'];
                $_SESSION['admin_role'] = $user['role_name'];
                unset($_SESSION['admin_csrf_token']);
                header('Location: index.php');
                exit;
            }

            $error = 'Nama pengguna atau kata laluan tidak tepat.';
        } catch (Throwable $exception) {
            error_log('Admin login error: ' . $exception->getMessage());
            $error = 'Log masuk gagal. Sila semak sambungan pangkalan data.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk Kakitangan | B@Bistro</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <main class="admin-auth">
        <h1>Log Masuk Kakitangan B@Bistro</h1>
        <?php if ($error !== ''): ?><p class="admin-message"><?= admin_escape($error) ?></p><?php endif; ?>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
            <label>Nama pengguna<input name="username" maxlength="50" required autocomplete="username"></label>
            <label>Kata laluan<input type="password" name="password" required autocomplete="current-password"></label>
            <button class="admin-button" type="submit">Log Masuk</button>
        </form>
        <p class="admin-help">Akaun kakitangan disediakan oleh pentadbir sistem.</p>
    </main>
</body>
</html>
