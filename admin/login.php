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
    <link rel="stylesheet" href="admin.css?v=19">
</head>
<body class="admin-login-page">
    <div class="admin-login-layout">
        <section class="admin-login-banner" aria-label="Selamat datang ke B@Bistro">
            <img class="admin-login-banner-image" src="../images/banners/banner.jpg" alt="">
            <img class="admin-login-banner-logo" src="../images/logo/logo.svg" alt="B@Bistro">
            <div class="admin-login-banner-copy">
                <p class="admin-login-eyebrow">Portal kakitangan</p>
                <h1>Selamat Datang</h1>
                <p>Log masuk untuk mengakses panel operasi B@Bistro.</p>
            </div>
        </section>
        <main class="admin-auth">
            <p class="admin-login-card-eyebrow">Akaun kakitangan</p>
            <h2>Log masuk</h2>
            <p class="admin-login-intro">Masukkan maklumat akaun anda untuk meneruskan.</p>
            <?php if ($error !== ''): ?>
                <p class="admin-message <?= isset($_GET['setup']) ? 'admin-success' : 'admin-error' ?>"
                    role="<?= isset($_GET['setup']) ? 'status' : 'alert' ?>">
                    <?= admin_escape($error) ?>
                </p>
            <?php endif; ?>
            <form method="post" class="admin-form admin-login-form">
                <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                <label for="loginUsername">Nama pengguna</label>
                <input id="loginUsername" name="username" maxlength="50" placeholder="Masukkan nama pengguna"
                    required autocomplete="username">
                <label for="loginPassword">Kata laluan</label>
                <input id="loginPassword" type="password" name="password" placeholder="Masukkan kata laluan"
                    required autocomplete="current-password">
                <button class="admin-button admin-login-submit" type="submit">
                    Log Masuk <span aria-hidden="true">&rarr;</span>
                </button>
            </form>
            <p class="admin-login-help">Akaun kakitangan disediakan oleh pentadbir sistem.</p>
            <a class="admin-login-home" href="../index.html">Kembali ke laman utama</a>
        </main>
    </div>
    <footer class="admin-login-footer">
        <p>&copy; <?= date('Y') ?> B@Bistro. Hak cipta terpelihara.</p>
    </footer>
</body>
</html>
