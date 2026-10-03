<?php

require_once __DIR__ . '/auth.php';

$error = '';
$setupAvailable = false;
$transactionOpen = false;

try {
    require_once __DIR__ . '/../config.php';

    $result = $conn->query(
        "SELECT COUNT(*) AS admin_count
         FROM users
         INNER JOIN roles ON roles.role_id = users.role_id
         WHERE roles.role_name = 'Admin'"
    );
    $setupAvailable = (int) $result->fetch_assoc()['admin_count'] === 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$setupAvailable) {
            $error = 'Pendaftaran admin pertama telah pun selesai.';
        } elseif (!admin_verify_csrf()) {
            $error = 'Sesi borang tamat. Sila muat semula halaman dan cuba lagi.';
        } else {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($fullName === '' || strlen($fullName) > 100) {
                $error = 'Nama penuh wajib diisi dan maksimum 100 aksara.';
            } elseif (!preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/', $username)) {
                $error = 'Nama pengguna perlu 3 hingga 50 aksara (huruf, nombor, titik, garis atau sempang).';
            } elseif (strlen($password) < 12) {
                $error = 'Kata laluan admin mestilah sekurang-kurangnya 12 aksara.';
            } else {
                $conn->begin_transaction();
                $transactionOpen = true;
                $roleResult = $conn->query(
                    "SELECT role_id FROM roles WHERE role_name = 'Admin' FOR UPDATE"
                );
                $role = $roleResult->fetch_assoc();

                if (!$role) {
                    throw new RuntimeException('Role Admin tidak ditemui dalam jadual roles.');
                }

                $adminResult = $conn->query(
                    "SELECT COUNT(*) AS admin_count
                     FROM users
                     INNER JOIN roles ON roles.role_id = users.role_id
                     WHERE roles.role_name = 'Admin'"
                );

                if ((int) $adminResult->fetch_assoc()['admin_count'] > 0) {
                    $conn->rollback();
                    $transactionOpen = false;
                    $setupAvailable = false;
                    $error = 'Pendaftaran admin pertama telah pun selesai.';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $statement = $conn->prepare(
                        'INSERT INTO users (role_id, full_name, username, password_hash)
                         VALUES (?, ?, ?, ?)'
                    );
                    $statement->bind_param(
                        'isss',
                        $role['role_id'],
                        $fullName,
                        $username,
                        $passwordHash
                    );
                    $statement->execute();
                    $conn->commit();
                    $transactionOpen = false;

                    header('Location: login.php?setup=complete');
                    exit;
                }
            }
        }
    }
} catch (mysqli_sql_exception $exception) {
    if ($transactionOpen) {
        $conn->rollback();
    }

    if ($exception->getCode() === 1062) {
        $error = 'Nama pengguna itu telah digunakan.';
    } else {
        error_log('Admin setup error: ' . $exception->getMessage());
        $error = 'Admin tidak dapat didaftarkan. Sila semak sambungan pangkalan data.';
    }
} catch (Throwable $exception) {
    if ($transactionOpen) {
        $conn->rollback();
    }

    error_log('Admin setup error: ' . $exception->getMessage());
    $error = 'Admin tidak dapat didaftarkan. Sila semak konfigurasi pangkalan data.';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Admin | B@Bistro</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <main class="admin-auth">
        <h1>Daftar Admin Pertama</h1>
        <?php if ($error !== ''): ?><p class="admin-message admin-error"><?= admin_escape($error) ?></p><?php endif; ?>
        <?php if ($setupAvailable): ?>
            <p>Borang ini akan ditutup secara automatik selepas akaun Admin pertama diwujudkan.</p>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                <label>Nama penuh<input name="full_name" maxlength="100" required></label>
                <label>Nama pengguna<input name="username" minlength="3" maxlength="50" required autocomplete="username"></label>
                <label>Kata laluan (minimum 12 aksara)<input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
                <button class="admin-button" type="submit">Daftar Admin</button>
            </form>
        <?php else: ?>
            <p>Admin pertama telah didaftarkan. <a href="login.php">Log masuk ke panel admin</a>.</p>
        <?php endif; ?>
    </main>
</body>
</html>
