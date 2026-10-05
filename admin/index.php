<?php

require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/report_helpers.php';

$error = '';
$userRole = '';
$isAdmin = false;
$canManageOrders = false;
$canManagePayments = false;
$canManageTables = false;
$categories = [];
$menuItems = [];
$orders = [];
$tables = [];
$users = [];
$sessions = [];
$kitchenMenu = [];
$cashierMenu = [];
$panelMenuItems = [];
$expenseCategories = [];
$recentExpenses = [];
$monthlySales = [];
$chartMax = 1;
$monthlyReport = [
    'revenue' => 0.0,
    'expenses' => 0.0,
    'profit' => 0.0,
    'paid_orders' => 0,
    'payment_count' => 0,
];
$reportPeriod = admin_report_period([
    'period' => 'month',
    'month' => date('Y-m'),
]);
try {
    $reportPeriod = admin_report_period($_GET);
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
}
$reportData = null;
$reportUrl = 'report.php';
$reportDownloadUrl = 'report_download.php';
$transactionOpen = false;
$orderTransitions = [
    'Menunggu' => ['Menunggu', 'Sedang Disediakan', 'Dibatalkan'],
    'Sedang Disediakan' => ['Sedang Disediakan', 'Sedia Diambil', 'Dibatalkan'],
    'Sedia Diambil' => ['Sedia Diambil', 'Diserahkan', 'Dibatalkan'],
    'Diserahkan' => ['Diserahkan', 'Selesai'],
    'Selesai' => ['Selesai'],
    'Dibatalkan' => ['Dibatalkan'],
];
$paymentStatuses = [
    'Belum Dibayar',
    'Menunggu Pengesahan',
    'Berjaya',
    'Gagal',
    'Dipulangkan',
];
$paymentTransitions = [
    'Belum Dibayar' => ['Belum Dibayar', 'Berjaya', 'Gagal'],
    'Menunggu Pengesahan' => ['Menunggu Pengesahan', 'Berjaya', 'Gagal'],
    'Berjaya' => ['Berjaya', 'Dipulangkan'],
    'Gagal' => ['Gagal', 'Menunggu Pengesahan'],
    'Dipulangkan' => ['Dipulangkan'],
];
$kitchenTransitions = [
    'Menunggu' => ['Menunggu', 'Sedang Disediakan'],
    'Sedang Disediakan' => ['Sedang Disediakan', 'Sedia Diambil'],
    'Sedia Diambil' => ['Sedia Diambil', 'Diserahkan'],
    'Diserahkan' => ['Diserahkan'],
    'Selesai' => ['Selesai'],
    'Dibatalkan' => ['Dibatalkan'],
];

try {
    require_once __DIR__ . '/../config.php';
    if (!admin_refresh_identity($conn)) {
        header('Location: login.php?expired=1');
        exit;
    }

    $userRole = (string) $_SESSION['admin_role'];
    $isAdmin = $userRole === 'Admin';
    $canManageOrders = in_array($userRole, ['Admin', 'Kitchen'], true);
    $canManagePayments = in_array($userRole, ['Admin', 'Cashier'], true);
    $canManageTables = in_array($userRole, ['Admin', 'Cashier'], true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!admin_verify_csrf()) {
            throw new InvalidArgumentException('Sesi borang tamat. Sila muat semula halaman dan cuba lagi.');
        }

        $action = (string) ($_POST['action'] ?? 'create_menu');

        if (in_array($action, ['update_order', 'close_order'], true)) {
            $isClosingOrder = $action === 'close_order';
            if ($isClosingOrder && !in_array($userRole, ['Admin', 'Cashier'], true)) {
                http_response_code(403);
                throw new InvalidArgumentException('Hanya Admin atau Cashier boleh menutup pesanan.');
            }
            if (!$canManageOrders && !$canManagePayments) {
                http_response_code(403);
                throw new InvalidArgumentException('Role anda tidak dibenarkan mengemas kini pesanan.');
            }

            $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);

            if ($orderId === false || $orderId === null) {
                throw new InvalidArgumentException('Nombor pesanan tidak sah.');
            }

            $conn->begin_transaction();
            $transactionOpen = true;

            $orderStatement = $conn->prepare(
                'SELECT order_status FROM orders WHERE order_id = ? FOR UPDATE'
            );
            $orderStatement->bind_param('i', $orderId);
            $orderStatement->execute();
            $currentOrder = $orderStatement->get_result()->fetch_assoc();
            if (!$currentOrder) {
                throw new InvalidArgumentException('Pesanan tidak ditemui.');
            }

            $paymentStatement = $conn->prepare(
                'SELECT payment_id, payment_status FROM payments WHERE order_id = ? FOR UPDATE'
            );
            $paymentStatement->bind_param('i', $orderId);
            $paymentStatement->execute();
            $currentPayment = $paymentStatement->get_result()->fetch_assoc();
            if (!$currentPayment) {
                throw new InvalidArgumentException('Rekod pembayaran bagi pesanan ini tidak ditemui.');
            }

            if ($isClosingOrder) {
                if ($currentOrder['order_status'] !== 'Diserahkan'
                    || $currentPayment['payment_status'] !== 'Berjaya'
                ) {
                    throw new InvalidArgumentException('Pesanan hanya boleh ditutup selepas diserahkan dan pembayaran berjaya.');
                }
                $newOrderStatus = 'Selesai';
            } elseif (in_array($userRole, ['Admin', 'Kitchen'], true)) {
                $newOrderStatus = (string) ($_POST['order_status'] ?? $currentOrder['order_status']);
            } else {
                $newOrderStatus = $currentOrder['order_status'];
            }
            $newPaymentStatus = $canManagePayments
                ? (string) ($_POST['payment_status'] ?? $currentPayment['payment_status'])
                : $currentPayment['payment_status'];

            $allowedOrderStatuses = $userRole === 'Kitchen'
                ? ($kitchenTransitions[$currentOrder['order_status']] ?? [])
                : ($orderTransitions[$currentOrder['order_status']] ?? []);
            if (!in_array($newOrderStatus, $allowedOrderStatuses, true)) {
                throw new InvalidArgumentException('Perubahan status pesanan tidak dibenarkan.');
            }
            if ($newOrderStatus === 'Selesai'
                && $currentPayment['payment_status'] !== 'Berjaya'
            ) {
                throw new InvalidArgumentException('Pesanan hanya boleh ditutup selepas pembayaran berjaya.');
            }
            if (!in_array($newPaymentStatus, $paymentStatuses, true)) {
                throw new InvalidArgumentException('Status pembayaran tidak sah.');
            }

            $allowedPaymentStatuses = $paymentTransitions[$currentPayment['payment_status']] ?? [];
            if (!in_array($newPaymentStatus, $allowedPaymentStatuses, true)) {
                throw new InvalidArgumentException('Perubahan status pembayaran tidak dibenarkan.');
            }

            if ($newOrderStatus !== $currentOrder['order_status']) {
                $updateOrder = $conn->prepare(
                    "UPDATE orders
                     SET order_status = ?,
                         completed_at = IF(
                             ? = 'Selesai',
                             COALESCE(completed_at, CURRENT_TIMESTAMP),
                             NULL
                         )
                     WHERE order_id = ?"
                );
                $updateOrder->bind_param('ssi', $newOrderStatus, $newOrderStatus, $orderId);
                $updateOrder->execute();

                if ($newOrderStatus !== 'Selesai') {
                    $updateItems = $conn->prepare(
                        'UPDATE order_items SET item_status = ? WHERE order_id = ?'
                    );
                    $updateItems->bind_param('si', $newOrderStatus, $orderId);
                    $updateItems->execute();
                }

                $history = $conn->prepare(
                    'INSERT INTO order_status_history
                        (order_id, user_id, old_status, new_status)
                     VALUES (?, ?, ?, ?)'
                );
                $adminUserId = (int) $_SESSION['admin_user_id'];
                $history->bind_param(
                    'iiss',
                    $orderId,
                    $adminUserId,
                    $currentOrder['order_status'],
                    $newOrderStatus
                );
                $history->execute();
                admin_log_action(
                    $conn,
                    'Kemaskini status pesanan: ' . $currentOrder['order_status'] . ' -> ' . $newOrderStatus,
                    'orders',
                    $orderId,
                    $adminUserId
                );
            }

            if ($newPaymentStatus !== $currentPayment['payment_status']) {
                $adminUserId = (int) $_SESSION['admin_user_id'];
                $updatePayment = $conn->prepare(
                    "UPDATE payments
                     SET payment_status = ?,
                         recorded_by = ?,
                         paid_at = IF(
                             ? = 'Berjaya',
                             COALESCE(paid_at, CURRENT_TIMESTAMP),
                             paid_at
                         )
                     WHERE order_id = ?"
                );
                $updatePayment->bind_param(
                    'sisi',
                    $newPaymentStatus,
                    $adminUserId,
                    $newPaymentStatus,
                    $orderId
                );
                $updatePayment->execute();
                admin_log_action(
                    $conn,
                    'Kemaskini status bayaran: ' . $currentPayment['payment_status'] . ' -> ' . $newPaymentStatus,
                    'payments',
                    $orderId,
                    $adminUserId
                );

            }

            if ($newPaymentStatus === 'Berjaya') {
                $receiptLookup = $conn->prepare(
                    'SELECT receipt_id FROM receipts WHERE payment_id = ?'
                );
                $receiptLookup->bind_param('i', $currentPayment['payment_id']);
                $receiptLookup->execute();
                if (!$receiptLookup->get_result()->fetch_assoc()) {
                    $receiptNumber = 'BAB-R-' . strtoupper(bin2hex(random_bytes(6)));
                    $receiptInsert = $conn->prepare(
                        'INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)'
                    );
                    $receiptInsert->bind_param(
                        'is',
                        $currentPayment['payment_id'],
                        $receiptNumber
                    );
                    $receiptInsert->execute();
                    admin_log_action(
                        $conn,
                        'Mengeluarkan resit ' . $receiptNumber,
                        'receipts',
                        (int) $conn->insert_id,
                        (int) $_SESSION['admin_user_id']
                    );
                }
            }

            if ($newOrderStatus !== $currentOrder['order_status']
                && in_array($newOrderStatus, ['Selesai', 'Dibatalkan'], true)
            ) {
                $sessionLookup = $conn->prepare(
                    'SELECT session_id FROM orders WHERE order_id = ?'
                );
                $sessionLookup->bind_param('i', $orderId);
                $sessionLookup->execute();
                $tableSessionId = (int) $sessionLookup->get_result()->fetch_assoc()['session_id'];

                $openOrders = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM orders
                     WHERE session_id = ?
                       AND order_status NOT IN ('Selesai', 'Dibatalkan')"
                );
                $openOrders->bind_param('i', $tableSessionId);
                $openOrders->execute();

                if ((int) $openOrders->get_result()->fetch_assoc()['total'] === 0) {
                    $sessionTable = $conn->prepare(
                        'SELECT table_id FROM table_sessions WHERE session_id = ? FOR UPDATE'
                    );
                    $sessionTable->bind_param('i', $tableSessionId);
                    $sessionTable->execute();
                    $tableId = (int) $sessionTable->get_result()->fetch_assoc()['table_id'];

                    $nonCancelledOrders = $conn->prepare(
                        "SELECT COUNT(*) AS total
                         FROM orders
                         WHERE session_id = ? AND order_status <> 'Dibatalkan'"
                    );
                    $nonCancelledOrders->bind_param('i', $tableSessionId);
                    $nonCancelledOrders->execute();
                    $hadCompletedOrder = (int) $nonCancelledOrders->get_result()->fetch_assoc()['total'] > 0;
                    $sessionStatus = $hadCompletedOrder ? 'Selesai' : 'Dibatalkan';
                    $tableStatus = $hadCompletedOrder ? 'Menunggu Pembersihan' : 'Kosong';

                    $closeSession = $conn->prepare(
                        'UPDATE table_sessions
                         SET session_status = ?, ended_at = CURRENT_TIMESTAMP
                         WHERE session_id = ?'
                    );
                    $closeSession->bind_param('si', $sessionStatus, $tableSessionId);
                    $closeSession->execute();

                    $updateTable = $conn->prepare(
                        'UPDATE restaurant_tables SET table_status = ? WHERE table_id = ?'
                    );
                    $updateTable->bind_param('si', $tableStatus, $tableId);
                    $updateTable->execute();
                }
            }

            $conn->commit();
            $transactionOpen = false;

            header('Location: index.php?order_updated=1');
            exit;
        }

        if ($action === 'clear_table') {
            if (!$canManageTables) {
                http_response_code(403);
                throw new InvalidArgumentException('Role anda tidak dibenarkan mengurus status meja.');
            }

            $tableId = filter_var($_POST['table_id'] ?? null, FILTER_VALIDATE_INT);
            if ($tableId === false || $tableId === null) {
                throw new InvalidArgumentException('Nombor meja tidak sah.');
            }

            $conn->begin_transaction();
            $transactionOpen = true;
            $tableStatement = $conn->prepare(
                'SELECT table_status FROM restaurant_tables WHERE table_id = ? FOR UPDATE'
            );
            $tableStatement->bind_param('i', $tableId);
            $tableStatement->execute();
            $table = $tableStatement->get_result()->fetch_assoc();
            if (!$table || $table['table_status'] !== 'Menunggu Pembersihan') {
                throw new InvalidArgumentException('Meja ini belum menunggu pembersihan.');
            }

            $clearTable = $conn->prepare(
                "UPDATE restaurant_tables SET table_status = 'Kosong' WHERE table_id = ?"
            );
            $clearTable->bind_param('i', $tableId);
            $clearTable->execute();
            admin_log_action(
                $conn,
                'Tandakan meja sebagai kosong',
                'restaurant_tables',
                $tableId,
                (int) $_SESSION['admin_user_id']
            );
            $conn->commit();
            $transactionOpen = false;
            header('Location: index.php?table_updated=1');
            exit;
        }

        if ($action === 'create_user') {
            if (!$isAdmin) {
                http_response_code(403);
                throw new InvalidArgumentException('Hanya Admin boleh mengurus akaun kakitangan.');
            }

            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $roleName = (string) ($_POST['role_name'] ?? '');

            if ($fullName === '' || strlen($fullName) > 100) {
                throw new InvalidArgumentException('Nama penuh wajib diisi dan maksimum 100 aksara.');
            }
            if (!preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/', $username)) {
                throw new InvalidArgumentException('Nama pengguna perlu 3 hingga 50 aksara (huruf, nombor, titik, garis atau sempang).');
            }
            if (strlen($password) < 12) {
                throw new InvalidArgumentException('Kata laluan kakitangan mestilah sekurang-kurangnya 12 aksara.');
            }
            if (!in_array($roleName, ['Admin', 'Cashier', 'Kitchen'], true)) {
                throw new InvalidArgumentException('Sila pilih role kakitangan yang sah.');
            }

            $roleStatement = $conn->prepare('SELECT role_id FROM roles WHERE role_name = ?');
            $roleStatement->bind_param('s', $roleName);
            $roleStatement->execute();
            $role = $roleStatement->get_result()->fetch_assoc();
            if (!$role) {
                throw new InvalidArgumentException('Role tersebut tidak wujud dalam pangkalan data.');
            }

            $conn->begin_transaction();
            $transactionOpen = true;
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $createUser = $conn->prepare(
                'INSERT INTO users (role_id, full_name, username, password_hash)
                 VALUES (?, ?, ?, ?)'
            );
            $createUser->bind_param('isss', $role['role_id'], $fullName, $username, $passwordHash);
            $createUser->execute();
            $newUserId = (int) $conn->insert_id;
            admin_log_action(
                $conn,
                'Mencipta akaun kakitangan dengan role ' . $roleName,
                'users',
                $newUserId,
                (int) $_SESSION['admin_user_id']
            );

            $conn->commit();
            $transactionOpen = false;
            header('Location: index.php?user_created=1');
            exit;
        }

        if ($action === 'set_user_active') {
            if (!$isAdmin) {
                http_response_code(403);
                throw new InvalidArgumentException('Hanya Admin boleh mengurus akaun kakitangan.');
            }

            $targetUserId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $isActive = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT);
            if ($targetUserId === false || $targetUserId === null
                || !in_array($isActive, [0, 1], true)
            ) {
                throw new InvalidArgumentException('Permintaan kemas kini akaun tidak sah.');
            }
            if ($targetUserId === (int) $_SESSION['admin_user_id'] && $isActive === 0) {
                throw new InvalidArgumentException('Anda tidak boleh menyahaktifkan akaun sendiri.');
            }

            $conn->begin_transaction();
            $transactionOpen = true;
            $targetStatement = $conn->prepare(
                'SELECT users.is_active, roles.role_name
                 FROM users INNER JOIN roles ON roles.role_id = users.role_id
                 WHERE users.user_id = ?
                 FOR UPDATE'
            );
            $targetStatement->bind_param('i', $targetUserId);
            $targetStatement->execute();
            $targetUser = $targetStatement->get_result()->fetch_assoc();
            if (!$targetUser) {
                throw new InvalidArgumentException('Akaun kakitangan tidak ditemui.');
            }
            if ($targetUser['role_name'] === 'Admin' && (int) $targetUser['is_active'] === 1 && $isActive === 0) {
                $conn->query("SELECT role_id FROM roles WHERE role_name = 'Admin' FOR UPDATE");
                $activeAdminCount = $conn->query(
                    "SELECT COUNT(*) AS total FROM users
                     INNER JOIN roles ON roles.role_id = users.role_id
                     WHERE roles.role_name = 'Admin' AND users.is_active = 1"
                );
                if ((int) $activeAdminCount->fetch_assoc()['total'] <= 1) {
                    throw new InvalidArgumentException('Sekurang-kurangnya seorang Admin aktif mesti dikekalkan.');
                }
            }

            $updateUser = $conn->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
            $updateUser->bind_param('ii', $isActive, $targetUserId);
            $updateUser->execute();
            admin_log_action(
                $conn,
                $isActive === 1 ? 'Mengaktifkan akaun kakitangan' : 'Menyahaktifkan akaun kakitangan',
                'users',
                $targetUserId,
                (int) $_SESSION['admin_user_id']
            );

            $conn->commit();
            $transactionOpen = false;
            header('Location: index.php?user_updated=1');
            exit;
        }

        if ($action === 'update_menu') {
            if (!$isAdmin) {
                http_response_code(403);
                throw new InvalidArgumentException('Hanya Admin boleh mengurus menu.');
            }

            $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
            $itemName = trim((string) ($_POST['item_name'] ?? ''));
            $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
            $description = trim((string) ($_POST['description'] ?? ''));
            $price = trim((string) ($_POST['price'] ?? ''));
            $isAvailable = filter_var($_POST['is_available'] ?? null, FILTER_VALIDATE_INT);

            if ($itemId === false || $itemId === null || $itemName === '' || strlen($itemName) > 150) {
                throw new InvalidArgumentException('Item menu atau nama tidak sah.');
            }
            if ($categoryId === false || $categoryId === null) {
                throw new InvalidArgumentException('Sila pilih kategori yang sah.');
            }
            if (!preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $price) || (float) $price <= 0) {
                throw new InvalidArgumentException('Harga mesti lebih daripada RM0.00 dan maksimum dua tempat perpuluhan.');
            }
            if (!in_array($isAvailable, [0, 1], true)) {
                throw new InvalidArgumentException('Status ketersediaan menu tidak sah.');
            }

            $conn->begin_transaction();
            $transactionOpen = true;
            $categoryStatement = $conn->prepare(
                'SELECT category_id FROM categories WHERE category_id = ? AND is_active = 1'
            );
            $categoryStatement->bind_param('i', $categoryId);
            $categoryStatement->execute();
            if (!$categoryStatement->get_result()->fetch_assoc()) {
                throw new InvalidArgumentException('Kategori tidak aktif atau tidak ditemui.');
            }

            $updateMenu = $conn->prepare(
                'UPDATE menu_items
                 SET category_id = ?, item_name = ?, description = ?, price = ?, is_available = ?
                 WHERE item_id = ?'
            );
            $updateMenu->bind_param(
                'isssii',
                $categoryId,
                $itemName,
                $description,
                $price,
                $isAvailable,
                $itemId
            );
            $updateMenu->execute();
            if ($updateMenu->affected_rows === 0) {
                $exists = $conn->prepare('SELECT item_id FROM menu_items WHERE item_id = ?');
                $exists->bind_param('i', $itemId);
                $exists->execute();
                if (!$exists->get_result()->fetch_assoc()) {
                    throw new InvalidArgumentException('Menu tidak ditemui.');
                }
            }
            admin_log_action(
                $conn,
                'Mengemas kini menu',
                'menu_items',
                $itemId,
                (int) $_SESSION['admin_user_id']
            );
            $conn->commit();
            $transactionOpen = false;
            header('Location: index.php?menu_updated=1');
            exit;
        }

        if ($action === 'delete_menu') {
            if (!$isAdmin) {
                http_response_code(403);
                throw new InvalidArgumentException('Hanya Admin boleh memadam menu.');
            }

            $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
            if ($itemId === false || $itemId === null) {
                throw new InvalidArgumentException('Item menu tidak sah.');
            }

            $conn->begin_transaction();
            $transactionOpen = true;
            $menuStatement = $conn->prepare(
                'SELECT item_name FROM menu_items WHERE item_id = ? FOR UPDATE'
            );
            $menuStatement->bind_param('i', $itemId);
            $menuStatement->execute();
            $menuItem = $menuStatement->get_result()->fetch_assoc();
            if (!$menuItem) {
                throw new InvalidArgumentException('Menu tidak ditemui.');
            }

            $usageStatement = $conn->prepare(
                'SELECT COUNT(*) AS usage_count FROM order_items WHERE item_id = ?'
            );
            $usageStatement->bind_param('i', $itemId);
            $usageStatement->execute();
            $hasOrderHistory = (int) $usageStatement->get_result()->fetch_assoc()['usage_count'] > 0;

            if ($hasOrderHistory) {
                $archiveMenu = $conn->prepare(
                    'UPDATE menu_items SET is_available = 0 WHERE item_id = ?'
                );
                $archiveMenu->bind_param('i', $itemId);
                $archiveMenu->execute();
                $resultFlag = 'menu_archived';
                $auditAction = 'Menyahaktifkan menu yang mempunyai rekod pesanan: ' . $menuItem['item_name'];
            } else {
                $deleteMenu = $conn->prepare('DELETE FROM menu_items WHERE item_id = ?');
                $deleteMenu->bind_param('i', $itemId);
                $deleteMenu->execute();
                $resultFlag = 'menu_deleted';
                $auditAction = 'Memadam menu: ' . $menuItem['item_name'];
            }

            admin_log_action(
                $conn,
                $auditAction,
                'menu_items',
                $itemId,
                (int) $_SESSION['admin_user_id']
            );
            $conn->commit();
            $transactionOpen = false;
            header('Location: index.php?' . $resultFlag . '=1');
            exit;
        }

        if ($action === 'save_expense') {
            if (!$isAdmin) {
                http_response_code(403);
                throw new InvalidArgumentException('Hanya Admin boleh merekod perbelanjaan.');
            }

            $categoryId = filter_var($_POST['expense_category_id'] ?? null, FILTER_VALIDATE_INT);
            $amount = trim((string) ($_POST['amount'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $expenseDate = (string) ($_POST['expense_date'] ?? '');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expenseDate);

            if ($categoryId === false || $categoryId === null) {
                throw new InvalidArgumentException('Sila pilih kategori perbelanjaan.');
            }
            if (!preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $amount) || (float) $amount <= 0) {
                throw new InvalidArgumentException('Jumlah perbelanjaan mestilah lebih daripada RM0.00.');
            }
            if (strlen($description) > 255) {
                throw new InvalidArgumentException('Keterangan perbelanjaan maksimum 255 aksara.');
            }
            if (!$date || $date->format('Y-m-d') !== $expenseDate) {
                throw new InvalidArgumentException('Tarikh perbelanjaan tidak sah.');
            }

            $categoryStatement = $conn->prepare(
                'SELECT expense_category_id FROM expense_categories WHERE expense_category_id = ?'
            );
            $categoryStatement->bind_param('i', $categoryId);
            $categoryStatement->execute();
            if (!$categoryStatement->get_result()->fetch_assoc()) {
                throw new InvalidArgumentException('Kategori perbelanjaan tidak ditemui.');
            }

            $conn->begin_transaction();
            $transactionOpen = true;
            $userId = (int) $_SESSION['admin_user_id'];
            $insertExpense = $conn->prepare(
                'INSERT INTO expenses
                    (expense_category_id, recorded_by, amount, description, expense_date)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insertExpense->bind_param('iidss', $categoryId, $userId, $amount, $description, $expenseDate);
            $insertExpense->execute();
            $expenseId = (int) $conn->insert_id;
            admin_log_action(
                $conn,
                'Merekod perbelanjaan',
                'expenses',
                $expenseId,
                $userId
            );
            $conn->commit();
            $transactionOpen = false;
            header('Location: index.php?expense_saved=1');
            exit;
        }

        if ($action !== 'create_menu') {
            throw new InvalidArgumentException('Tindakan tidak dikenali.');
        }
        if (!$isAdmin) {
            http_response_code(403);
            throw new InvalidArgumentException('Hanya Admin boleh menambah menu.');
        }

        $itemName = trim((string) ($_POST['item_name'] ?? ''));
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
        $description = trim((string) ($_POST['description'] ?? ''));
        $price = trim((string) ($_POST['price'] ?? ''));

        if ($itemName === '' || strlen($itemName) > 150) {
            throw new InvalidArgumentException('Nama menu wajib diisi dan maksimum 150 aksara.');
        }
        if ($categoryId === false || $categoryId === null) {
            throw new InvalidArgumentException('Sila pilih kategori yang sah.');
        }
        if (!preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $price) || (float) $price <= 0) {
            throw new InvalidArgumentException('Harga mesti lebih daripada RM0.00 dan maksimum dua tempat perpuluhan.');
        }

        $categoryStatement = $conn->prepare(
            'SELECT category_id FROM categories WHERE category_id = ? AND is_active = 1'
        );
        $categoryStatement->bind_param('i', $categoryId);
        $categoryStatement->execute();
        if (!$categoryStatement->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('Kategori tidak aktif atau tidak ditemui.');
        }

        $imagePath = null;
        $uploadedPath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Muat naik gambar gagal. Sila pilih semula gambar.');
            }
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('Saiz gambar maksimum ialah 5 MB.');
            }

            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($_FILES['image']['tmp_name']);
            $allowedImages = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($allowedImages[$mimeType]) || getimagesize($_FILES['image']['tmp_name']) === false) {
                throw new InvalidArgumentException('Gambar mestilah fail JPG, PNG atau WebP yang sah.');
            }

            $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images'
                . DIRECTORY_SEPARATOR . 'foods' . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new InvalidArgumentException('Folder muat naik gambar tidak dapat disediakan.');
            }

            $fileName = bin2hex(random_bytes(16)) . '.' . $allowedImages[$mimeType];
            $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                throw new InvalidArgumentException('Gambar tidak dapat disimpan ke folder menu.');
            }

            $imagePath = 'images/foods/uploads/' . $fileName;
            $uploadedPath = $destination;
        }

        $conn->begin_transaction();
        $transactionOpen = true;
        try {
            $statement = $conn->prepare(
                'INSERT INTO menu_items (category_id, item_name, description, price, image_path, is_available)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            $statement->bind_param('issss', $categoryId, $itemName, $description, $price, $imagePath);
            $statement->execute();
            $newItemId = (int) $conn->insert_id;
            admin_log_action(
                $conn,
                'Mencipta menu',
                'menu_items',
                $newItemId,
                (int) $_SESSION['admin_user_id']
            );
            $conn->commit();
            $transactionOpen = false;
        } catch (Throwable $exception) {
            if ($transactionOpen) {
                $conn->rollback();
                $transactionOpen = false;
            }
            if ($uploadedPath !== null && is_file($uploadedPath)) {
                unlink($uploadedPath);
            }
            throw $exception;
        }

        header('Location: index.php?saved=1');
        exit;
    }

} catch (InvalidArgumentException $exception) {
    if ($transactionOpen) {
        $conn->rollback();
    }
    $error = $exception->getMessage();
} catch (mysqli_sql_exception $exception) {
    if ($transactionOpen) {
        $conn->rollback();
    }
    if ($exception->getCode() === 1062) {
        $error = 'Nama pengguna tersebut telah digunakan.';
    } else {
        error_log('Admin action database error: ' . $exception->getMessage());
        $error = 'Tindakan tidak dapat disimpan. Sila semak sambungan pangkalan data.';
    }
} catch (Throwable $exception) {
    if ($transactionOpen) {
        $conn->rollback();
    }
    error_log('Admin action error: ' . $exception->getMessage());
    $error = 'Tindakan tidak dapat diselesaikan. Sila semak log sistem.';
}

$adminSectionAfterAction = $isAdmin ? 'finance' : 'orders';
if ($isAdmin && isset($_GET['expense_saved'])) {
    $adminSectionAfterAction = 'expenses';
} elseif ($isAdmin && (isset($_GET['user_created']) || isset($_GET['user_updated']))) {
    $adminSectionAfterAction = 'staff';
} elseif ($isAdmin && (isset($_GET['menu_updated']) || isset($_GET['menu_deleted']) || isset($_GET['menu_archived']))) {
    $adminSectionAfterAction = 'menu-list';
} elseif ($isAdmin && isset($_GET['saved'])) {
    $adminSectionAfterAction = 'menu-create';
} elseif (isset($_GET['order_updated'])) {
    $adminSectionAfterAction = 'orders';
} elseif (($isAdmin || $userRole === 'Cashier') && isset($_GET['table_updated'])) {
    $adminSectionAfterAction = 'tables';
}

if (isset($conn) && $conn instanceof mysqli) {
    try {
        if ($isAdmin) {
            $categoryResult = $conn->query(
                'SELECT category_id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name'
            );
            $categories = $categoryResult->fetch_all(MYSQLI_ASSOC);

            $menuResult = $conn->query(
                'SELECT menu_items.item_id, menu_items.item_name, menu_items.description, menu_items.price,
                        menu_items.image_path, menu_items.is_available,
                        categories.category_id, categories.category_name
                 FROM menu_items
                 INNER JOIN categories ON categories.category_id = menu_items.category_id
                 ORDER BY menu_items.item_id DESC'
            );
            $menuItems = $menuResult->fetch_all(MYSQLI_ASSOC);
            $panelMenuItems = $menuItems;

            $expenseCategories = $conn->query(
                'SELECT expense_category_id, category_name
                 FROM expense_categories
                 ORDER BY category_name'
            )->fetch_all(MYSQLI_ASSOC);

            $reportData = admin_load_financial_report($conn, $reportPeriod);
            $monthlyReport = [
                'revenue' => $reportData['summary']['net_revenue'],
                'expenses' => $reportData['summary']['expenses'],
                'profit' => $reportData['summary']['profit'],
                'paid_orders' => $reportData['summary']['successful_order_count'],
                'payment_count' => $reportData['summary']['successful_count'],
            ];
            $monthlySales = $reportData['timeline'];
            $chartYear = (int) $reportPeriod['start']->format('Y');
            $chartSalesMax = max(
                1,
                ...array_map(static fn (array $row): float => abs($row['revenue']), $monthlySales)
            );
            $chartExpenseMax = max(
                1,
                ...array_map(static fn (array $row): float => $row['expenses'], $monthlySales)
            );
            $reportFilterKey = $reportPeriod['period'] === 'week'
                ? 'date'
                : ($reportPeriod['period'] === 'year' ? 'year' : 'month');
            $reportQuery = [
                'period' => $reportPeriod['period'],
                $reportFilterKey => $reportPeriod['selection'],
            ];
            $reportUrl = 'report.php?' . http_build_query($reportQuery);
            $reportDownloadUrl = 'report_download.php?' . http_build_query($reportQuery);

            $recentExpenseResult = $conn->query(
                'SELECT expenses.amount, expenses.description, expenses.expense_date,
                        expense_categories.category_name, users.full_name
                 FROM expenses
                 INNER JOIN expense_categories
                    ON expense_categories.expense_category_id = expenses.expense_category_id
                 LEFT JOIN users ON users.user_id = expenses.recorded_by
                 ORDER BY expenses.expense_date DESC, expenses.expense_id DESC
                 LIMIT 20'
            );
            $recentExpenses = $recentExpenseResult->fetch_all(MYSQLI_ASSOC);
        }

        $orderFilter = $userRole === 'Kitchen'
            ? "WHERE orders.order_status IN ('Menunggu', 'Sedang Disediakan', 'Sedia Diambil', 'Diserahkan')"
            : '';
        $ordersResult = $conn->query(
            "SELECT orders.order_id, orders.order_number, orders.session_id,
                    orders.order_status, orders.notes, orders.created_at,
                    restaurant_tables.table_number, payments.payment_id,
                    payments.payment_method, payments.payment_status, payments.amount,
                    receipts.receipt_number,
                    (
                        SELECT GROUP_CONCAT(
                            CONCAT(order_items.item_name_snapshot, ' ×', order_items.quantity,
                                ' (RM ', FORMAT(order_items.unit_price, 2), ')')
                            ORDER BY order_items.order_item_id SEPARATOR ', '
                        )
                        FROM order_items
                        WHERE order_items.order_id = orders.order_id
                    ) AS item_summary
             FROM orders
             INNER JOIN table_sessions ON table_sessions.session_id = orders.session_id
             INNER JOIN restaurant_tables ON restaurant_tables.table_id = table_sessions.table_id
             INNER JOIN payments ON payments.order_id = orders.order_id
             LEFT JOIN receipts ON receipts.payment_id = payments.payment_id
             $orderFilter
             ORDER BY orders.created_at DESC
             LIMIT 100"
        );
        $orders = $ordersResult->fetch_all(MYSQLI_ASSOC);

        if ($canManageTables) {
            $tablesResult = $conn->query(
                'SELECT table_id, table_number, table_status
                 FROM restaurant_tables
                 ORDER BY table_number'
            );
            $tables = $tablesResult->fetch_all(MYSQLI_ASSOC);

            if ($userRole === 'Cashier') {
                $sessionsResult = $conn->query(
                    "SELECT table_sessions.session_id, table_sessions.started_at,
                            restaurant_tables.table_number,
                            COUNT(DISTINCT orders.order_id) AS order_count,
                            COALESCE(SUM(payments.amount), 0) AS bill_total,
                            COALESCE(SUM(payments.payment_status = 'Berjaya'), 0) AS paid_orders,
                            COALESCE(SUM(payments.payment_status IN
                                ('Belum Dibayar', 'Menunggu Pengesahan')), 0) AS unpaid_orders,
                            GROUP_CONCAT(orders.order_number ORDER BY orders.created_at SEPARATOR ', ') AS order_numbers
                     FROM table_sessions
                     INNER JOIN restaurant_tables
                        ON restaurant_tables.table_id = table_sessions.table_id
                     LEFT JOIN orders ON orders.session_id = table_sessions.session_id
                     LEFT JOIN payments ON payments.order_id = orders.order_id
                     WHERE table_sessions.session_status = 'Aktif'
                     GROUP BY table_sessions.session_id, table_sessions.started_at,
                              restaurant_tables.table_number
                     ORDER BY table_sessions.started_at"
                );
                $sessions = $sessionsResult->fetch_all(MYSQLI_ASSOC);
            }
        }

        if ($isAdmin) {
            $usersResult = $conn->query(
                'SELECT users.user_id, users.full_name, users.username,
                        users.is_active, roles.role_name
                 FROM users
                 INNER JOIN roles ON roles.role_id = users.role_id
                 ORDER BY roles.role_name, users.full_name'
            );
            $users = $usersResult->fetch_all(MYSQLI_ASSOC);
        }

        if ($userRole === 'Kitchen') {
            $kitchenMenuResult = $conn->query(
                'SELECT menu_items.item_name, menu_items.description, menu_items.image_path,
                        categories.category_name
                 FROM menu_items
                 INNER JOIN categories ON categories.category_id = menu_items.category_id
                 WHERE menu_items.is_available = 1 AND categories.is_active = 1
                 ORDER BY categories.category_name, menu_items.item_name'
            );
            $kitchenMenu = $kitchenMenuResult->fetch_all(MYSQLI_ASSOC);
            $panelMenuItems = $kitchenMenu;
        } elseif ($userRole === 'Cashier') {
            $cashierMenuResult = $conn->query(
                'SELECT menu_items.item_name, menu_items.image_path, categories.category_name
                 FROM menu_items
                 INNER JOIN categories ON categories.category_id = menu_items.category_id
                 WHERE menu_items.is_available = 1 AND categories.is_active = 1
                 ORDER BY categories.category_name, menu_items.item_name'
            );
            $cashierMenu = $cashierMenuResult->fetch_all(MYSQLI_ASSOC);
            $panelMenuItems = $cashierMenu;
        }
    } catch (Throwable $exception) {
        error_log('Admin menu listing error: ' . $exception->getMessage());
        $error = 'Menu tidak dapat dipaparkan. Sila semak sambungan pangkalan data.';
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= admin_escape($isAdmin ? 'Panel Pentadbir' : ($userRole === 'Kitchen' ? 'Panel Dapur' : 'Panel Juruwang')) ?> | B@Bistro</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css?v=10">
</head>
<body data-admin-section-default="<?= admin_escape($adminSectionAfterAction) ?>">
    <main class="admin-page">
        <header class="admin-header">
            <div>
                <img class="admin-brand-logo" src="../images/logo/logo coloured.svg" alt="B@Bistro">
                <h1><?= $isAdmin ? 'Panel Pentadbir' : ($userRole === 'Kitchen' ? 'Panel Dapur' : 'Panel Juruwang') ?></h1>
                <p><?= admin_escape((string) $_SESSION['admin_name']) ?> · <?= admin_escape(admin_role_label($userRole)) ?></p>
            </div>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                <button class="admin-button admin-button-secondary" type="submit">Log Keluar</button>
            </form>
        </header>

        <?php if (isset($_GET['saved'])): ?><p class="admin-message admin-success">Menu baharu berjaya disimpan.</p><?php endif; ?>
        <?php if (isset($_GET['order_updated'])): ?><p class="admin-message admin-success">Pesanan dan pembayaran berjaya dikemas kini.</p><?php endif; ?>
        <?php if (isset($_GET['table_updated'])): ?><p class="admin-message admin-success">Status meja berjaya dikemas kini.</p><?php endif; ?>
        <?php if (isset($_GET['user_created'])): ?><p class="admin-message admin-success">Akaun kakitangan berjaya dicipta.</p><?php endif; ?>
        <?php if (isset($_GET['user_updated'])): ?><p class="admin-message admin-success">Akaun kakitangan berjaya dikemas kini.</p><?php endif; ?>
        <?php if (isset($_GET['expense_saved'])): ?><p class="admin-message admin-success">Perbelanjaan berjaya direkodkan.</p><?php endif; ?>
        <?php if (isset($_GET['menu_deleted'])): ?><p class="admin-message admin-success">Menu berjaya dipadamkan.</p><?php endif; ?>
        <?php if (isset($_GET['menu_archived'])): ?><p class="admin-message admin-success">Menu telah dinyahaktifkan kerana mempunyai rekod pesanan lama.</p><?php endif; ?>
        <?php if (isset($_GET['menu_updated'])): ?><p class="admin-message admin-success">Menu berjaya dikemas kini.</p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="admin-message admin-error"><?= admin_escape($error) ?></p><?php endif; ?>

        <?php if ($isAdmin): ?>
        <nav class="admin-panel-nav" aria-label="Navigasi panel Admin">
            <a class="admin-panel-nav-link" href="#admin-section-finance" data-admin-tab="finance">Kewangan &amp; laporan</a>
            <a class="admin-panel-nav-link" href="#admin-section-expenses" data-admin-tab="expenses">Perbelanjaan</a>
            <a class="admin-panel-nav-link" href="#admin-section-orders" data-admin-tab="orders">Pesanan</a>
            <a class="admin-panel-nav-link" href="#admin-section-tables" data-admin-tab="tables">Meja</a>
            <a class="admin-panel-nav-link" href="#admin-section-menu-create" data-admin-tab="menu-create">Tambah menu</a>
            <a class="admin-panel-nav-link" href="#admin-section-menu-list" data-admin-tab="menu-list">Senarai menu</a>
            <a class="admin-panel-nav-link" href="#admin-section-staff" data-admin-tab="staff">Kakitangan</a>
        </nav>
        <?php elseif ($userRole === 'Kitchen'): ?>
        <nav class="admin-panel-nav" aria-label="Navigasi panel Dapur">
            <a class="admin-panel-nav-link" href="#admin-section-orders" data-admin-tab="orders">Pesanan aktif</a>
            <a class="admin-panel-nav-link" href="#admin-section-kitchen-menu" data-admin-tab="kitchen-menu">Menu sedia ada</a>
        </nav>
        <?php elseif ($userRole === 'Cashier'): ?>
        <nav class="admin-panel-nav" aria-label="Navigasi panel Cashier">
            <a class="admin-panel-nav-link" href="#admin-section-orders" data-admin-tab="orders">Semakan bil, pembayaran dan resit</a>
            <a class="admin-panel-nav-link" href="#admin-section-cashier-sessions" data-admin-tab="cashier-sessions">Sesi meja dan semakan bil</a>
            <a class="admin-panel-nav-link" href="#admin-section-tables" data-admin-tab="tables">Status meja</a>
            <a class="admin-panel-nav-link" href="#admin-section-kitchen-menu" data-admin-tab="kitchen-menu">Menu sedia ada</a>
        </nav>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
        <section class="admin-panel" id="admin-section-finance" data-admin-section="finance">
            <div class="admin-panel-heading admin-finance-heading">
                <div>
                    <h2>Kewangan dan laporan</h2>
                    <p class="admin-help">Pilih tempoh untuk menyemak ringkasan dan jana laporan.</p>
                </div>
                <form method="get" class="admin-month-filter" id="report-filter">
                    <div class="admin-report-filter-field">
                        <label for="report-period">Jenis laporan</label>
                        <select id="report-period" name="period">
                            <option value="week"<?= $reportPeriod['period'] === 'week' ? ' selected' : '' ?>>Mingguan</option>
                            <option value="month"<?= $reportPeriod['period'] === 'month' ? ' selected' : '' ?>>Bulanan</option>
                            <option value="year"<?= $reportPeriod['period'] === 'year' ? ' selected' : '' ?>>Tahunan</option>
                        </select>
                    </div>
                    <div class="admin-report-filter-field report-period-field" data-period="week"<?= $reportPeriod['period'] === 'week' ? '' : ' hidden' ?>>
                        <label for="report-week">Pilih tarikh dalam minggu</label>
                        <input id="report-week" type="date" name="date" value="<?= admin_escape($reportPeriod['period'] === 'week' ? $reportPeriod['selection'] : date('Y-m-d')) ?>">
                    </div>
                    <div class="admin-report-filter-field report-period-field" data-period="month"<?= $reportPeriod['period'] === 'month' ? '' : ' hidden' ?>>
                        <label for="report-month">Pilih bulan</label>
                        <input id="report-month" type="month" name="month" value="<?= admin_escape($reportPeriod['period'] === 'month' ? $reportPeriod['selection'] : date('Y-m')) ?>">
                    </div>
                    <div class="admin-report-filter-field report-period-field" data-period="year"<?= $reportPeriod['period'] === 'year' ? '' : ' hidden' ?>>
                        <label for="report-year">Pilih tahun</label>
                        <input id="report-year" type="number" name="year" min="1000" max="9998" step="1" value="<?= admin_escape($reportPeriod['period'] === 'year' ? $reportPeriod['selection'] : date('Y')) ?>">
                    </div>
                    <button class="admin-button" type="submit">Papar</button>
                </form>
            </div>
            <p class="admin-report-period"><?= admin_escape($reportPeriod['label']) ?> · <?= admin_escape($reportPeriod['start']->format('d/m/Y')) ?> – <?= admin_escape($reportPeriod['end']->modify('-1 day')->format('d/m/Y')) ?></p>
            <?php if ($reportData !== null): ?>
                <div class="admin-report-actions">
                    <a class="admin-button" href="<?= admin_escape($reportUrl) ?>" target="_blank" rel="noopener">Jana laporan</a>
                    <a class="admin-button admin-button-secondary" href="<?= admin_escape($reportDownloadUrl) ?>">Muat turun CSV</a>
                </div>
            <?php endif; ?>
            <div class="admin-metrics">
                <article><span>Hasil bersih</span><strong>RM <?= number_format($monthlyReport['revenue'], 2) ?></strong></article>
                <article><span>Perbelanjaan</span><strong>RM <?= number_format($monthlyReport['expenses'], 2) ?></strong></article>
                <article><span>Untung bersih</span><strong>RM <?= number_format($monthlyReport['profit'], 2) ?></strong></article>
                <article><span>Pesanan dibayar</span><strong><?= (int) $monthlyReport['paid_orders'] ?></strong></article>
                <article><span>Transaksi berjaya</span><strong><?= (int) $monthlyReport['payment_count'] ?></strong></article>
                <article><span>Bayaran dipulangkan</span><strong><?= (int) ($reportData['summary']['refunded_count'] ?? 0) ?></strong></article>
                <article><span>Menunggu pengesahan</span><strong><?= (int) ($reportData['summary']['pending_count'] ?? 0) ?></strong></article>
            </div>
            <h3>Trend hasil bersih dan perbelanjaan · <?= admin_escape($reportPeriod['label']) ?></h3>
            <div class="admin-chart-legend"><span class="admin-legend-sales">Jualan bersih</span><span class="admin-legend-expenses">Perbelanjaan</span></div>
            <div class="admin-chart-scroll" tabindex="0" role="region" aria-label="Graf boleh ditatal mendatar">
                <div class="admin-chart" style="--chart-count: <?= count($monthlySales) ?>" role="img" aria-label="Graf jualan bersih dan perbelanjaan bagi <?= admin_escape($reportPeriod['label']) ?>">
                    <?php foreach ($monthlySales as $monthData): ?>
                        <div class="admin-chart-month">
                            <div class="admin-chart-bars">
                                <span class="admin-chart-bar admin-chart-sales" title="Jualan bersih <?= admin_escape($monthData['label']) ?>: RM <?= number_format($monthData['revenue'], 2) ?>"<?= $monthData['revenue'] == 0.0 ? ' hidden' : '' ?> style="height: <?= max(5, (int) round(abs($monthData['revenue']) / $chartSalesMax * 100)) ?>%"></span>
                                <span class="admin-chart-bar admin-chart-expense" title="Perbelanjaan <?= admin_escape($monthData['label']) ?>: RM <?= number_format($monthData['expenses'], 2) ?>"<?= $monthData['expenses'] == 0.0 ? ' hidden' : '' ?> style="height: <?= max(5, (int) round($monthData['expenses'] / $chartExpenseMax * 100)) ?>%"></span>
                            </div>
                            <span><?= admin_escape($monthData['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <label class="admin-chart-slider" for="admin-chart-slider">
                <input id="admin-chart-slider" type="range" min="0" max="1000" value="0" step="1" aria-label="Skrol graf jualan dan perbelanjaan">
            </label>
            <p class="admin-help">Skala bar jualan dan perbelanjaan adalah berasingan supaya kedua-duanya mudah dilihat. Arahkan kursor pada bar untuk melihat amaun sebenar.</p>
            <p class="admin-help">Hasil bersih mengira bayaran berjaya selepas ditolak bayaran dipulangkan. Bayaran belum selesai tidak dianggap hasil. Perbelanjaan dikelaskan mengikut tarikh perbelanjaan.</p>
        </section>

        <section class="admin-panel" id="admin-section-expenses" data-admin-section="expenses">
            <h2>Rekod perbelanjaan</h2>
            <form method="post" class="admin-form admin-expense-form">
                <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="save_expense">
                <label>Kategori
                    <select name="expense_category_id" required>
                        <option value="">Pilih kategori</option>
                        <?php foreach ($expenseCategories as $expenseCategory): ?>
                            <option value="<?= (int) $expenseCategory['expense_category_id'] ?>"><?= admin_escape($expenseCategory['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Jumlah (RM)<input name="amount" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" required></label>
                <label>Tarikh<input type="date" name="expense_date" value="<?= admin_escape(date('Y-m-d')) ?>" required></label>
                <label>Keterangan<input name="description" maxlength="255" required></label>
                <button class="admin-button" type="submit">Rekod Perbelanjaan</button>
            </form>
            <h3>Perbelanjaan terkini</h3>
            <?php if ($recentExpenses === []): ?>
                <p>Belum ada rekod perbelanjaan.</p>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Tarikh</th><th>Kategori</th><th>Keterangan</th><th>Direkod oleh</th><th>Jumlah</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentExpenses as $expense): ?>
                                <tr>
                                    <td><?= admin_escape($expense['expense_date']) ?></td>
                                    <td><?= admin_escape($expense['category_name']) ?></td>
                                    <td><?= admin_escape($expense['description']) ?></td>
                                    <td><?= admin_escape($expense['full_name'] ?? '—') ?></td>
                                    <td>RM <?= number_format((float) $expense['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($userRole === 'Cashier'): ?>
        <section class="admin-panel" id="admin-section-cashier-sessions" data-admin-section="cashier-sessions">
            <h2>Sesi meja dan semakan bil</h2>
            <?php if ($sessions === []): ?>
                <p>Tiada sesi meja aktif.</p>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Meja / sesi</th><th>Pesanan</th><th>Jumlah bil</th><th>Telah dibayar</th><th>Belum dibayar</th></tr></thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td>Meja <?= (int) $session['table_number'] ?><small class="admin-order-date"><?= admin_escape($session['started_at']) ?></small></td>
                                    <td>
                                        <?= (int) $session['order_count'] ?>
                                        <?php if (!empty($session['order_numbers'])): ?><small class="admin-order-date"><?= admin_escape($session['order_numbers']) ?></small><?php endif; ?>
                                    </td>
                                    <td>RM <?= number_format((float) $session['bill_total'], 2) ?></td>
                                    <td><?= (int) $session['paid_orders'] ?> pesanan</td>
                                    <td><?= (int) $session['unpaid_orders'] ?> pesanan</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="admin-help">Sahkan bayaran pada pesanan di bawah. Sesi ditutup secara automatik apabila semua pesanan selesai atau dibatalkan.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (in_array($userRole, ['Kitchen', 'Cashier'], true)): ?>
        <section class="admin-panel" id="admin-section-kitchen-menu" data-admin-section="kitchen-menu">
            <div class="admin-menu-heading">
                <h2>Menu sedia ada</h2>
                <label class="admin-menu-category-filter">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10m-7 5h4"/><path d="m8 4-2 3 2 3m8 4 2 3-2 3"/></svg>
                    <select data-menu-category-filter aria-label="Asingkan menu mengikut kategori">
                        <option value="">Semua kategori</option>
                        <?php foreach (array_unique(array_column($panelMenuItems, 'category_name')) as $menuCategory): ?>
                            <option value="<?= admin_escape($menuCategory) ?>"><?= admin_escape($menuCategory) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <?php if ($panelMenuItems === []): ?>
                <p>Tiada menu tersedia buat masa ini.</p>
            <?php else: ?>
                <div class="admin-kitchen-menu" data-menu-category-list>
                    <?php foreach ($panelMenuItems as $menuItem): ?>
                        <article data-menu-category="<?= admin_escape($menuItem['category_name']) ?>">
                            <?php if (!empty($menuItem['image_path'])): ?><img src="../<?= admin_escape($menuItem['image_path']) ?>" alt=""><?php endif; ?>
                            <div><strong><?= admin_escape($menuItem['item_name']) ?></strong><span><?= admin_escape($menuItem['category_name']) ?></span></div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="admin-help" data-menu-filter-empty hidden>Tiada menu dalam kategori ini.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="admin-panel"<?= $isAdmin || $userRole === 'Kitchen' || $userRole === 'Cashier' ? ' id="admin-section-orders" data-admin-section="orders"' : '' ?>>
            <h2><?= $userRole === 'Kitchen' ? 'Pesanan aktif' : ($userRole === 'Cashier' ? 'Semakan bil, pembayaran dan resit' : 'Pesanan terkini') ?></h2>
            <p class="admin-help">
                <?= $canManagePayments
                    ? 'Semak item dan jumlah bil. Sahkan pembayaran setelah diterima; pesanan yang diserahkan dan dibayar boleh ditutup serta resitnya dicetak.'
                    : 'Semak item pesanan sebenar dan kemas kini status penyediaan sehingga pesanan diserahkan kepada juruwang.' ?>
            </p>
                    <?php if ($orders === []): ?>
                        <p>Belum ada pesanan.</p>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-order-table">
                                <thead>
                                    <tr><th>Pesanan</th><th>Meja</th><th>Item</th><th>Jumlah</th><th>Status pesanan / bayaran</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <?php
                                        $allowedOrderStatuses = $orderTransitions[$order['order_status']] ?? [$order['order_status']];
                                        $allowedPaymentStatuses = $paymentTransitions[$order['payment_status']] ?? [$order['payment_status']];
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= admin_escape($order['order_number']) ?></strong>
                                                <small class="admin-order-date"><?= admin_escape($order['created_at']) ?></small>
                                                <small><?= admin_escape($order['payment_method']) ?></small>
                                            </td>
                                            <td>Meja <?= (int) $order['table_number'] ?></td>
                                            <td>
                                                <?= admin_escape($order['item_summary'] ?? '') ?>
                                                <?php if ($userRole === 'Kitchen' && !empty($order['notes'])): ?>
                                                    <small class="admin-order-date">Nota pelanggan: <?= admin_escape($order['notes']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>RM <?= number_format((float) $order['amount'], 2) ?></td>
                                            <td>
                                                <form method="post" class="admin-order-update">
                                                    <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="update_order">
                                                    <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                                                    <?php if ($canManageOrders): ?>
                                                        <label>Status pesanan
                                                            <select name="order_status">
                                                                <?php foreach ($allowedOrderStatuses as $status): ?>
                                                                    <option value="<?= admin_escape($status) ?>"<?= $status === $order['order_status'] ? ' selected' : '' ?>><?= admin_escape($status) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                    <?php else: ?>
                                                        <span>Status pesanan: <?= admin_escape($order['order_status']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($canManagePayments): ?>
                                                        <label>Status pembayaran
                                                            <select name="payment_status">
                                                                <?php foreach ($allowedPaymentStatuses as $status): ?>
                                                                    <option value="<?= admin_escape($status) ?>"<?= $status === $order['payment_status'] ? ' selected' : '' ?>><?= admin_escape($status) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                    <?php else: ?>
                                                        <span>Status bayaran: <?= admin_escape($order['payment_status']) ?></span>
                                                    <?php endif; ?>
                                                    <button class="admin-button" type="submit">Simpan</button>
                                                </form>
                                                <?php if ($canManagePayments && $order['payment_status'] === 'Berjaya' && !empty($order['receipt_number'])): ?>
                                                    <a class="admin-receipt-link" href="receipt.php?receipt=<?= rawurlencode($order['receipt_number']) ?>" target="_blank" rel="noopener">Cetak Resit</a>
                                                <?php endif; ?>
                                                <?php if ($canManagePayments && $order['order_status'] === 'Diserahkan' && $order['payment_status'] === 'Berjaya'): ?>
                                                    <form method="post" class="admin-close-order">
                                                        <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="close_order">
                                                        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                                                        <button class="admin-button" type="submit">Tutup Pesanan</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
        </section>

        <?php if ($canManageTables): ?>
        <section class="admin-panel"<?= $isAdmin || $userRole === 'Cashier' ? ' id="admin-section-tables" data-admin-section="tables"' : '' ?>>
                    <h2>Status meja</h2>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Meja</th><th>Status</th><th>Tindakan</th></tr></thead>
                            <tbody>
                                <?php foreach ($tables as $table): ?>
                                    <tr>
                                        <td>Meja <?= (int) $table['table_number'] ?></td>
                                        <td><?= admin_escape($table['table_status']) ?></td>
                                        <td>
                                            <?php if ($table['table_status'] === 'Menunggu Pembersihan'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="clear_table">
                                                    <input type="hidden" name="table_id" value="<?= (int) $table['table_id'] ?>">
                                                    <button class="admin-button" type="submit">Tandakan Meja Kosong</button>
                                                </form>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
        </section>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <section class="admin-panel" id="admin-section-menu-create" data-admin-section="menu-create">
                    <h2>Tambah menu baharu</h2>
            <?php if ($categories === []): ?>
                <p class="admin-message admin-error">Tiada kategori aktif. Aktifkan kategori terlebih dahulu dalam pangkalan data.</p>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="admin-form admin-menu-form">
                    <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                    <input type="hidden" name="action" value="create_menu">
                    <label>Nama menu<input name="item_name" maxlength="150" required></label>
                    <label>Kategori
                        <select name="category_id" required>
                            <option value="">Pilih kategori</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['category_id'] ?>"><?= admin_escape($category['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Harga (RM)<input name="price" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" placeholder="12.00" required></label>
                    <label>Gambar menu (JPG, PNG atau WebP; maksimum 5 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                    <label class="admin-full-width">Penerangan<textarea name="description" rows="4"></textarea></label>
                    <button class="admin-button admin-full-width" type="submit">Simpan Menu</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="admin-panel" id="admin-section-staff" data-admin-section="staff">
            <h2>Akaun kakitangan</h2>
            <form method="post" class="admin-form admin-menu-form">
        <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
        <input type="hidden" name="action" value="create_user">
        <label>Nama penuh<input name="full_name" maxlength="100" required></label>
        <label>Nama pengguna<input name="username" minlength="3" maxlength="50" required autocomplete="off"></label>
        <label>Kata laluan (minimum 12 aksara)<input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
        <label>Role
            <select name="role_name" required>
                <option value="Cashier">Cashier / Juruwang</option>
                <option value="Kitchen">Kitchen / Dapur</option>
                <option value="Admin">Admin / Pentadbir</option>
            </select>
        </label>
        <button class="admin-button admin-full-width" type="submit">Cipta Akaun</button>
            </form>
            <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Tindakan</th></tr></thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= admin_escape($user['full_name']) ?></td>
                        <td><?= admin_escape($user['username']) ?></td>
                        <td><?= admin_escape(admin_role_label($user['role_name'])) ?></td>
                        <td><?= (int) $user['is_active'] === 1 ? 'Aktif' : 'Tidak aktif' ?></td>
                        <td>
                            <?php if ((int) $user['user_id'] !== (int) $_SESSION['admin_user_id']
                                && !($user['role_name'] === 'Admin' && (int) $user['is_active'] === 1
                                    && count(array_filter($users, static fn (array $entry): bool =>
                                        $entry['role_name'] === 'Admin' && (int) $entry['is_active'] === 1
                                    )) === 1)
                            ): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="set_user_active">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= (int) $user['is_active'] === 1 ? '0' : '1' ?>">
                                    <button class="admin-button admin-button-secondary" type="submit"><?= (int) $user['is_active'] === 1 ? 'Nyahaktifkan' : 'Aktifkan' ?></button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
            </div>
        </section>

        <section class="admin-panel" id="admin-section-menu-list" data-admin-section="menu-list">
            <div class="admin-menu-heading">
                <h2>Menu sedia ada</h2>
                <label class="admin-menu-category-filter">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10m-7 5h4"/><path d="m8 4-2 3 2 3m8 4 2 3-2 3"/></svg>
                    <select data-menu-category-filter aria-label="Asingkan menu mengikut kategori">
                        <option value="">Semua kategori</option>
                        <?php foreach (array_unique(array_column($panelMenuItems, 'category_name')) as $menuCategory): ?>
                            <option value="<?= admin_escape($menuCategory) ?>"><?= admin_escape($menuCategory) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <?php if (($menuItems ?? []) === []): ?>
                <p>Belum ada menu untuk dipaparkan.</p>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table admin-menu-table">
                        <thead><tr><th>Menu</th><th>Kategori</th><th>Harga</th><th>Status</th><th>Tindakan</th></tr></thead>
                        <tbody>
                            <?php foreach ($menuItems as $item): ?>
                                <tr data-menu-category="<?= admin_escape($item['category_name']) ?>">
                                    <td><strong><?= admin_escape($item['item_name']) ?></strong></td>
                                    <td><?= admin_escape($item['category_name']) ?></td>
                                    <td>RM <?= number_format((float) $item['price'], 2) ?></td>
                                    <td><span class="admin-menu-status<?= (int) $item['is_available'] === 1 ? ' is-available' : '' ?>"><?= (int) $item['is_available'] === 1 ? 'Tersedia' : 'Tidak tersedia' ?></span></td>
                                    <td>
                                        <div class="admin-menu-actions">
                                            <button
                                                class="admin-icon-button admin-edit-menu"
                                                type="button"
                                                aria-label="Edit <?= admin_escape($item['item_name']) ?>"
                                                title="Edit menu"
                                                data-item-id="<?= (int) $item['item_id'] ?>"
                                                data-item-name="<?= admin_escape($item['item_name']) ?>"
                                                data-description="<?= admin_escape($item['description'] ?? '') ?>"
                                                data-category-id="<?= (int) $item['category_id'] ?>"
                                                data-price="<?= admin_escape(number_format((float) $item['price'], 2, '.', '')) ?>"
                                                data-available="<?= (int) $item['is_available'] ?>"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>
                                            </button>
                                            <form method="post" class="admin-delete-menu-form">
                                                <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_menu">
                                                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                                <button
                                                    class="admin-icon-button admin-delete-menu"
                                                    type="submit"
                                                    aria-label="Padam <?= admin_escape($item['item_name']) ?>"
                                                    title="Padam menu"
                                                    data-item-name="<?= admin_escape($item['item_name']) ?>"
                                                >
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="admin-help" data-menu-filter-empty hidden>Tiada menu dalam kategori ini.</p>
            <script>
                const reportPeriodSelect = document.getElementById('report-period');
                reportPeriodSelect?.addEventListener('change', () => {
                    document.querySelectorAll('.report-period-field').forEach((field) => {
                        const isActive = field.dataset.period === reportPeriodSelect.value;
                        field.hidden = !isActive;
                        const input = field.querySelector('input');
                        input.disabled = !isActive;
                        input.required = isActive;
                    });
                });

                const reportChart = document.querySelector('.admin-chart-scroll');
                const reportChartSlider = document.getElementById('admin-chart-slider');
                if (reportChart && reportChartSlider) {
                    const updateReportChartSlider = () => {
                        const maxScroll = reportChart.scrollWidth - reportChart.clientWidth;
                        reportChartSlider.closest('.admin-chart-slider').hidden = maxScroll <= 0;
                        reportChartSlider.value = maxScroll > 0
                            ? String(Math.round(reportChart.scrollLeft / maxScroll * Number(reportChartSlider.max)))
                            : '0';
                    };

                    reportChart.addEventListener('scroll', updateReportChartSlider, { passive: true });
                    reportChartSlider.addEventListener('input', () => {
                        const maxScroll = reportChart.scrollWidth - reportChart.clientWidth;
                        reportChart.scrollLeft = maxScroll * Number(reportChartSlider.value) / Number(reportChartSlider.max);
                    });
                    window.addEventListener('resize', updateReportChartSlider);
                    updateReportChartSlider();
                }
            </script>
            <?php endif; ?>
        </section>

        <dialog class="admin-menu-dialog" id="edit-menu-dialog" aria-labelledby="edit-menu-title">
            <form method="post" class="admin-form admin-menu-modal-form">
                <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="update_menu">
                <input type="hidden" name="item_id" id="edit-item-id">
                <div class="admin-panel-heading">
                    <h2 id="edit-menu-title">Edit menu</h2>
                    <button class="admin-icon-button admin-dialog-close" type="button" aria-label="Tutup dialog">×</button>
                </div>
                <label>Nama menu<input name="item_name" id="edit-item-name" maxlength="150" required></label>
                <label>Kategori
                    <select name="category_id" id="edit-category-id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>"><?= admin_escape($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Harga (RM)<input name="price" id="edit-item-price" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" required></label>
                <label>Ketersediaan
                    <select name="is_available" id="edit-item-available">
                        <option value="1">Tersedia</option>
                        <option value="0">Tidak tersedia</option>
                    </select>
                </label>
                <label>Penerangan<textarea name="description" id="edit-item-description" rows="4"></textarea></label>
                <div class="admin-menu-dialog-actions">
                    <button class="admin-button admin-button-secondary admin-dialog-cancel" type="button">Batal</button>
                    <button class="admin-button" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </dialog>
        <script>
            const menuDialog = document.getElementById('edit-menu-dialog');
            document.querySelectorAll('.admin-edit-menu').forEach((button) => {
                button.addEventListener('click', () => {
                    document.getElementById('edit-item-id').value = button.dataset.itemId;
                    document.getElementById('edit-item-name').value = button.dataset.itemName;
                    document.getElementById('edit-item-description').value = button.dataset.description;
                    document.getElementById('edit-category-id').value = button.dataset.categoryId;
                    document.getElementById('edit-item-price').value = button.dataset.price;
                    document.getElementById('edit-item-available').value = button.dataset.available;
                    menuDialog.showModal();
                });
            });
            document.querySelectorAll('.admin-dialog-close, .admin-dialog-cancel').forEach((button) => {
                button.addEventListener('click', () => menuDialog.close());
            });
            document.querySelectorAll('.admin-delete-menu-form').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    const itemName = form.querySelector('.admin-delete-menu').dataset.itemName;
                    if (!window.confirm(`Padam menu "${itemName}"? Jika menu pernah dipesan, ia akan dinyahaktifkan supaya rekod pesanan kekal.`)) {
                        event.preventDefault();
                    }
                });
            });
        </script>
        <?php endif; ?>
        <script>
            const panelTabs = [...document.querySelectorAll('[data-admin-tab]')];
            const panelSections = [...document.querySelectorAll('[data-admin-section]')];
            document.querySelectorAll('[data-menu-category-filter]').forEach((filter) => {
                filter.addEventListener('change', () => {
                    const menuSection = filter.closest('[data-admin-section]');
                    const menuItems = [...menuSection.querySelectorAll('[data-menu-category]')];
                    let visibleMenuCount = 0;

                    menuItems.forEach((menuItem) => {
                        const isVisible = filter.value === '' || menuItem.dataset.menuCategory === filter.value;
                        menuItem.hidden = !isVisible;
                        visibleMenuCount += Number(isVisible);
                    });

                    const emptyMessage = menuSection.querySelector('[data-menu-filter-empty]');
                    if (emptyMessage) {
                        emptyMessage.hidden = visibleMenuCount !== 0;
                    }
                });
            });

            if (panelTabs.length && panelSections.length) {
                const validPanelSections = new Set(panelSections.map((section) => section.dataset.adminSection));
                const showPanelSection = (sectionName) => {
                    if (!validPanelSections.has(sectionName)) {
                        return;
                    }

                    panelSections.forEach((section) => {
                        section.hidden = section.dataset.adminSection !== sectionName;
                    });
                    panelTabs.forEach((tab) => {
                        if (tab.dataset.adminTab === sectionName) {
                            tab.setAttribute('aria-current', 'page');
                        } else {
                            tab.removeAttribute('aria-current');
                        }
                    });
                };
                const panelSectionFromHash = () => {
                    const sectionName = window.location.hash.replace('#admin-section-', '');
                    return validPanelSections.has(sectionName)
                        ? sectionName
                        : document.body.dataset.adminSectionDefault;
                };

                panelTabs.forEach((tab) => {
                    tab.addEventListener('click', (event) => {
                        event.preventDefault();
                        const sectionName = tab.dataset.adminTab;
                        history.pushState(null, '', `#admin-section-${sectionName}`);
                        showPanelSection(sectionName);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                });
                window.addEventListener('popstate', () => showPanelSection(panelSectionFromHash()));
                showPanelSection(panelSectionFromHash());
            }
        </script>
    </main>
</body>
</html>
