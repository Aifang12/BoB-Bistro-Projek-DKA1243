<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip data ujian hanya boleh dijalankan melalui CLI.');
}

require_once __DIR__ . '/../config.php';

$conn->query(
    "CREATE TABLE IF NOT EXISTS report_test_orders (
        sample_key VARCHAR(40) NOT NULL PRIMARY KEY,
        order_id BIGINT UNSIGNED NOT NULL UNIQUE,
        CONSTRAINT fk_report_test_orders_order
            FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$conn->query(
    "CREATE TABLE IF NOT EXISTS report_test_expenses (
        sample_key VARCHAR(40) NOT NULL PRIMARY KEY,
        expense_id BIGINT UNSIGNED NOT NULL UNIQUE,
        CONSTRAINT fk_report_test_expenses_expense
            FOREIGN KEY (expense_id) REFERENCES expenses (expense_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$conn->begin_transaction();

try {
    $legacyOrderKeys = [
        'BAB-TEST-20260914A' => 'sale-a',
        'BAB-TEST-20260916B' => 'sale-b',
        'BAB-TEST-20260921C' => 'sale-c',
        'BAB-TEST-20260926D' => 'sale-d',
    ];
    $registerOrder = $conn->prepare(
        'INSERT IGNORE INTO report_test_orders (sample_key, order_id) VALUES (?, ?)'
    );
    $legacyOrderStatement = $conn->prepare(
        'SELECT order_id FROM orders WHERE order_number = ?'
    );
    foreach ($legacyOrderKeys as $legacyNumber => $sampleKey) {
        $legacyOrderStatement->bind_param('s', $legacyNumber);
        $legacyOrderStatement->execute();
        foreach ($legacyOrderStatement->get_result() as $legacyOrder) {
            $legacyOrderId = (int) $legacyOrder['order_id'];
            $registerOrder->bind_param('si', $sampleKey, $legacyOrderId);
            $registerOrder->execute();
        }
    }

    $legacyExpenseKeys = [
        '[DATA UJIAN LAPORAN] Contoh pembelian bahan mentah' => 'expense-a',
        '[DATA UJIAN LAPORAN] Contoh bil utiliti' => 'expense-b',
        '[DATA UJIAN LAPORAN] Contoh penyelenggaraan peralatan' => 'expense-c',
    ];
    $registerExpense = $conn->prepare(
        'INSERT IGNORE INTO report_test_expenses (sample_key, expense_id) VALUES (?, ?)'
    );
    $legacyExpenseStatement = $conn->prepare(
        'SELECT expense_id FROM expenses WHERE description = ?'
    );
    foreach ($legacyExpenseKeys as $legacyDescription => $sampleKey) {
        $legacyExpenseStatement->bind_param('s', $legacyDescription);
        $legacyExpenseStatement->execute();
        foreach ($legacyExpenseStatement->get_result() as $legacyExpense) {
            $legacyExpenseId = (int) $legacyExpense['expense_id'];
            $registerExpense->bind_param('si', $sampleKey, $legacyExpenseId);
            $registerExpense->execute();
        }
    }

    $staffResult = $conn->query(
        "SELECT users.user_id
         FROM users
         INNER JOIN roles ON roles.role_id = users.role_id
         WHERE users.is_active = 1 AND roles.role_name = 'Cashier'
         ORDER BY users.user_id LIMIT 1"
    )->fetch_assoc();
    if (!$staffResult) {
        $staffResult = $conn->query(
            "SELECT users.user_id
             FROM users
             INNER JOIN roles ON roles.role_id = users.role_id
             WHERE users.username = 'Admin' AND roles.role_name = 'Admin' AND users.is_active = 1
             LIMIT 1"
        )->fetch_assoc();
    }
    $tableResult = $conn->query(
        'SELECT table_id FROM restaurant_tables WHERE table_number = 5 LIMIT 1'
    )->fetch_assoc();
    $menuItems = $conn->query(
        'SELECT item_id, item_name, price
         FROM menu_items WHERE is_available = 1 ORDER BY item_id LIMIT 3'
    )->fetch_all(MYSQLI_ASSOC);

    if (!$staffResult || !$tableResult || count($menuItems) < 3) {
        throw new RuntimeException('Kakitangan aktif, meja 5 atau sekurang-kurangnya tiga menu tersedia tidak ditemui.');
    }

    $expenseCategoryIds = [];
    foreach ($conn->query('SELECT expense_category_id, category_name FROM expense_categories') as $category) {
        $expenseCategoryIds[$category['category_name']] = (int) $category['expense_category_id'];
    }
    foreach (['Bahan Mentah', 'Utiliti', 'Penyelenggaraan'] as $categoryName) {
        if (!isset($expenseCategoryIds[$categoryName])) {
            throw new RuntimeException('Kategori perbelanjaan tidak ditemui: ' . $categoryName);
        }
    }

    $staffId = (int) $staffResult['user_id'];
    $tableId = (int) $tableResult['table_id'];
    $samples = [
        [
            'key' => 'sale-a',
            'number' => 'BAB-6F8A1C203D5E7B91',
            'date' => '2026-09-03',
            'time' => '12:18:00',
            'menu' => 0,
            'quantity' => 2,
            'method' => 'Tunai',
            'status' => 'Berjaya',
            'reference' => null,
            'receipt' => 'BAB-R-3F8A1C20B5D7',
        ],
        [
            'key' => 'sale-b',
            'number' => 'BAB-2D7C9A416E03B852',
            'date' => '2026-09-09',
            'time' => '18:42:00',
            'menu' => 1,
            'quantity' => 2,
            'method' => 'Tunai',
            'status' => 'Dipulangkan',
            'reference' => null,
            'receipt' => 'BAB-R-2D7C9A416E03',
        ],
        [
            'key' => 'sale-c',
            'number' => 'BAB-A1B2C3D4E5F60718',
            'date' => '2026-09-17',
            'time' => '13:07:00',
            'menu' => 2,
            'quantity' => 1,
            'method' => 'FPX',
            'status' => 'Menunggu Pengesahan',
            'reference' => 'FPX20260917130748261',
            'receipt' => null,
        ],
        [
            'key' => 'sale-d',
            'number' => 'BAB-5E9F0A3C7B2D6148',
            'date' => '2026-09-28',
            'time' => '20:11:00',
            'menu' => 0,
            'quantity' => 3,
            'method' => 'Online Banking',
            'status' => 'Berjaya',
            'reference' => 'OB20260928201139674',
            'receipt' => 'BAB-R-5E9F0A3C7B2D',
        ],
        [
            'key' => 'oct-sale-20261005',
            'number' => 'BAB-8A10C405D7E29F31',
            'date' => '2026-10-05',
            'time' => '12:14:00',
            'menu' => 0,
            'quantity' => 2,
            'method' => 'Tunai',
            'status' => 'Berjaya',
            'reference' => null,
            'receipt' => 'BAB-R-8A10C405D7E2',
        ],
        [
            'key' => 'oct-sale-20261006',
            'number' => 'BAB-3D7F20A6C94B185E',
            'date' => '2026-10-06',
            'time' => '19:08:00',
            'menu' => 1,
            'quantity' => 2,
            'method' => 'Online Banking',
            'status' => 'Berjaya',
            'reference' => 'OB20261006190842715',
            'receipt' => 'BAB-R-3D7F20A6C94B',
        ],
        [
            'key' => 'oct-sale-20261007',
            'number' => 'BAB-61E84A0D3B7295C6',
            'date' => '2026-10-07',
            'time' => '13:22:00',
            'menu' => 2,
            'quantity' => 1,
            'method' => 'FPX',
            'status' => 'Berjaya',
            'reference' => 'FPX20261007132258104',
            'receipt' => 'BAB-R-61E84A0D3B72',
        ],
        [
            'key' => 'oct-sale-20261008',
            'number' => 'BAB-A56C20D14E738B92',
            'date' => '2026-10-08',
            'time' => '20:02:00',
            'menu' => 0,
            'quantity' => 3,
            'method' => 'Tunai',
            'status' => 'Berjaya',
            'reference' => null,
            'receipt' => 'BAB-R-A56C20D14E73',
        ],
        [
            'key' => 'oct-sale-20261009',
            'number' => 'BAB-0C74B9A63E1528DF',
            'date' => '2026-10-09',
            'time' => '12:45:00',
            'menu' => 1,
            'quantity' => 1,
            'method' => 'Online Banking',
            'status' => 'Berjaya',
            'reference' => 'OB20261009124530762',
            'receipt' => 'BAB-R-0C74B9A63E15',
        ],
        [
            'key' => 'oct-sale-20261010',
            'number' => 'BAB-F19D360A72C48E51',
            'date' => '2026-10-10',
            'time' => '18:34:00',
            'menu' => 2,
            'quantity' => 2,
            'method' => 'FPX',
            'status' => 'Berjaya',
            'reference' => 'FPX20261010183462409',
            'receipt' => 'BAB-R-F19D360A72C4',
        ],
        [
            'key' => 'oct-sale-20261011',
            'number' => 'BAB-4E8A17C2D6093F5B',
            'date' => '2026-10-11',
            'time' => '13:05:00',
            'menu' => 0,
            'quantity' => 1,
            'method' => 'Tunai',
            'status' => 'Berjaya',
            'reference' => null,
            'receipt' => 'BAB-R-4E8A17C2D609',
        ],
        [
            'key' => 'oct-sale-20261012',
            'number' => 'BAB-92B6D0A45E731C8F',
            'date' => '2026-10-12',
            'time' => '19:16:00',
            'menu' => 1,
            'quantity' => 3,
            'method' => 'Online Banking',
            'status' => 'Berjaya',
            'reference' => 'OB20261012191684530',
            'receipt' => 'BAB-R-92B6D0A45E73',
        ],
    ];

    $findSampleOrder = $conn->prepare(
        'SELECT order_id FROM report_test_orders WHERE sample_key = ? LIMIT 1'
    );
    $insertSession = $conn->prepare(
        'INSERT INTO table_sessions (table_id, started_at, ended_at, session_status)
         VALUES (?, ?, ?, "Selesai")'
    );
    $updateSession = $conn->prepare(
        'UPDATE table_sessions SET started_at = ?, ended_at = ?, session_status = "Selesai"
         WHERE session_id = ?'
    );
    $insertOrder = $conn->prepare(
        'INSERT INTO orders
            (order_number, session_id, created_by, order_status, notes, created_at, completed_at)
         VALUES (?, ?, NULL, "Selesai", NULL, ?, ?)'
    );
    $updateOrder = $conn->prepare(
        'UPDATE orders SET order_number = ?, created_by = NULL, order_status = "Selesai",
            notes = NULL, created_at = ?, completed_at = ? WHERE order_id = ?'
    );
    $registerSampleOrder = $conn->prepare(
        'INSERT INTO report_test_orders (sample_key, order_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)'
    );
    $findItem = $conn->prepare(
        'SELECT order_item_id FROM order_items WHERE order_id = ? ORDER BY order_item_id LIMIT 1'
    );
    $insertItem = $conn->prepare(
        'INSERT INTO order_items
            (order_id, item_id, item_name_snapshot, unit_price, quantity, item_status, notes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, "Diserahkan", NULL, ?, ?)'
    );
    $updateItem = $conn->prepare(
        'UPDATE order_items SET item_id = ?, item_name_snapshot = ?, unit_price = ?,
            quantity = ?, item_status = "Diserahkan", notes = NULL, created_at = ?, updated_at = ?
         WHERE order_item_id = ?'
    );
    $findPayment = $conn->prepare(
        'SELECT payment_id FROM payments WHERE order_id = ? ORDER BY payment_id LIMIT 1'
    );
    $insertPayment = $conn->prepare(
        'INSERT INTO payments
            (order_id, recorded_by, amount, payment_method, payment_status,
             transaction_reference, paid_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $updatePayment = $conn->prepare(
        'UPDATE payments SET recorded_by = ?, amount = ?, payment_method = ?,
            payment_status = ?, transaction_reference = ?, paid_at = ?, created_at = ?
         WHERE payment_id = ?'
    );
    $findReceipt = $conn->prepare(
        'SELECT receipt_id FROM receipts WHERE payment_id = ? LIMIT 1'
    );
    $insertReceipt = $conn->prepare(
        'INSERT INTO receipts (payment_id, receipt_number, issued_at) VALUES (?, ?, ?)'
    );
    $updateReceipt = $conn->prepare(
        'UPDATE receipts SET receipt_number = ?, issued_at = ? WHERE receipt_id = ?'
    );
    $deleteReceipt = $conn->prepare('DELETE FROM receipts WHERE payment_id = ?');

    foreach ($samples as $sample) {
        $sampleKey = $sample['key'];
        $findSampleOrder->bind_param('s', $sampleKey);
        $findSampleOrder->execute();
        $existingSample = $findSampleOrder->get_result()->fetch_assoc();

        $orderCreatedAt = $sample['date'] . ' ' . $sample['time'];
        $sessionStartedAt = date('Y-m-d H:i:s', strtotime($orderCreatedAt . ' -12 minutes'));
        $completedAt = date('Y-m-d H:i:s', strtotime($orderCreatedAt . ' +28 minutes'));
        $sessionEndedAt = date('Y-m-d H:i:s', strtotime($orderCreatedAt . ' +42 minutes'));
        $paymentCreatedAt = date('Y-m-d H:i:s', strtotime($orderCreatedAt . ' +31 minutes'));
        $item = $menuItems[$sample['menu']];
        $itemId = (int) $item['item_id'];
        $itemName = (string) $item['item_name'];
        $unitPrice = (float) $item['price'];
        $quantity = (int) $sample['quantity'];
        $amount = $unitPrice * $quantity;
        $orderNumber = $sample['number'];

        if ($existingSample) {
            $orderId = (int) $existingSample['order_id'];
            $updateOrder->bind_param('sssi', $orderNumber, $orderCreatedAt, $completedAt, $orderId);
            $updateOrder->execute();
            $sessionQuery = $conn->prepare('SELECT session_id FROM orders WHERE order_id = ?');
            $sessionQuery->bind_param('i', $orderId);
            $sessionQuery->execute();
            $sessionId = (int) $sessionQuery->get_result()->fetch_assoc()['session_id'];
            $updateSession->bind_param('ssi', $sessionStartedAt, $sessionEndedAt, $sessionId);
            $updateSession->execute();
        } else {
            $insertSession->bind_param('iss', $tableId, $sessionStartedAt, $sessionEndedAt);
            $insertSession->execute();
            $sessionId = $conn->insert_id;
            $insertOrder->bind_param('siss', $orderNumber, $sessionId, $orderCreatedAt, $completedAt);
            $insertOrder->execute();
            $orderId = $conn->insert_id;
            $registerSampleOrder->bind_param('si', $sampleKey, $orderId);
            $registerSampleOrder->execute();
        }

        $findItem->bind_param('i', $orderId);
        $findItem->execute();
        $existingItem = $findItem->get_result()->fetch_assoc();
        if ($existingItem) {
            $orderItemId = (int) $existingItem['order_item_id'];
            $updateItem->bind_param(
                'isdissi',
                $itemId,
                $itemName,
                $unitPrice,
                $quantity,
                $orderCreatedAt,
                $completedAt,
                $orderItemId
            );
            $updateItem->execute();
        } else {
            $insertItem->bind_param(
                'iisdiss',
                $orderId,
                $itemId,
                $itemName,
                $unitPrice,
                $quantity,
                $orderCreatedAt,
                $completedAt
            );
            $insertItem->execute();
        }

        $findPayment->bind_param('i', $orderId);
        $findPayment->execute();
        $existingPayment = $findPayment->get_result()->fetch_assoc();
        $paidAt = $sample['status'] === 'Berjaya' || $sample['status'] === 'Dipulangkan'
            ? $paymentCreatedAt
            : null;
        if ($existingPayment) {
            $paymentId = (int) $existingPayment['payment_id'];
            $updatePayment->bind_param(
                'idsssssi',
                $staffId,
                $amount,
                $sample['method'],
                $sample['status'],
                $sample['reference'],
                $paidAt,
                $paymentCreatedAt,
                $paymentId
            );
            $updatePayment->execute();
        } else {
            $insertPayment->bind_param(
                'iidsssss',
                $orderId,
                $staffId,
                $amount,
                $sample['method'],
                $sample['status'],
                $sample['reference'],
                $paidAt,
                $paymentCreatedAt
            );
            $insertPayment->execute();
            $paymentId = $conn->insert_id;
        }

        if ($sample['receipt'] === null) {
            $deleteReceipt->bind_param('i', $paymentId);
            $deleteReceipt->execute();
        } else {
            $findReceipt->bind_param('i', $paymentId);
            $findReceipt->execute();
            $existingReceipt = $findReceipt->get_result()->fetch_assoc();
            $receiptNumber = $sample['receipt'];
            if ($existingReceipt) {
                $receiptId = (int) $existingReceipt['receipt_id'];
                $updateReceipt->bind_param('ssi', $receiptNumber, $paidAt, $receiptId);
                $updateReceipt->execute();
            } else {
                $insertReceipt->bind_param('iss', $paymentId, $receiptNumber, $paidAt);
                $insertReceipt->execute();
            }
        }
    }

    $expenseSamples = [
        [
            'key' => 'expense-a',
            'category' => 'Bahan Mentah',
            'amount' => 485.60,
            'description' => 'Pembelian stok ayam, beras dan sayur · INV-260903-1842',
            'date' => '2026-09-03',
        ],
        [
            'key' => 'expense-b',
            'category' => 'Utiliti',
            'amount' => 318.75,
            'description' => 'Bil elektrik premis · INV-TNB-260915',
            'date' => '2026-09-15',
        ],
        [
            'key' => 'expense-c',
            'category' => 'Penyelenggaraan',
            'amount' => 165.00,
            'description' => 'Servis berkala peti sejuk dapur · WO-260927',
            'date' => '2026-09-27',
        ],
    ];
    $findSampleExpense = $conn->prepare(
        'SELECT expense_id FROM report_test_expenses WHERE sample_key = ? LIMIT 1'
    );
    $insertExpense = $conn->prepare(
        'INSERT INTO expenses
            (expense_category_id, recorded_by, amount, description, expense_date, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $updateExpense = $conn->prepare(
        'UPDATE expenses SET expense_category_id = ?, recorded_by = ?, amount = ?,
            description = ?, expense_date = ?, created_at = ? WHERE expense_id = ?'
    );
    $registerSampleExpense = $conn->prepare(
        'INSERT INTO report_test_expenses (sample_key, expense_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE expense_id = VALUES(expense_id)'
    );

    foreach ($expenseSamples as $sample) {
        $sampleKey = $sample['key'];
        $findSampleExpense->bind_param('s', $sampleKey);
        $findSampleExpense->execute();
        $existingExpense = $findSampleExpense->get_result()->fetch_assoc();
        $categoryId = $expenseCategoryIds[$sample['category']];
        $amount = (float) $sample['amount'];
        $description = $sample['description'];
        $expenseDate = $sample['date'];
        $createdAt = $expenseDate . ' 09:15:00';
        if ($existingExpense) {
            $expenseId = (int) $existingExpense['expense_id'];
            $updateExpense->bind_param(
                'iidsssi',
                $categoryId,
                $staffId,
                $amount,
                $description,
                $expenseDate,
                $createdAt,
                $expenseId
            );
            $updateExpense->execute();
        } else {
            $insertExpense->bind_param(
                'iidsss',
                $categoryId,
                $staffId,
                $amount,
                $description,
                $expenseDate,
                $createdAt
            );
            $insertExpense->execute();
            $expenseId = $conn->insert_id;
            $registerSampleExpense->bind_param('si', $sampleKey, $expenseId);
            $registerSampleExpense->execute();
        }
    }

    $conn->commit();
    echo "Realistic-looking September and October 2026 simulation data is ready.\n";
    echo "12 orders and payments, and 3 expenses, were updated or inserted; simulation tracking remains internal.\n";
    echo "Restaurant table statuses were not changed.\n";
} catch (Throwable $exception) {
    $conn->rollback();
    fwrite(STDERR, 'Sample report data failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
