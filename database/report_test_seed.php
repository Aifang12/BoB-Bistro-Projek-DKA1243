<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip data ujian hanya boleh dijalankan melalui CLI.');
}

require_once __DIR__ . '/../config.php';

$testMarker = '[DATA UJIAN LAPORAN]';
$conn->begin_transaction();

try {
    $adminResult = $conn->query(
        "SELECT users.user_id
         FROM users
         INNER JOIN roles ON roles.role_id = users.role_id
         WHERE users.username = 'Admin' AND roles.role_name = 'Admin' AND users.is_active = 1
         LIMIT 1"
    )->fetch_assoc();
    $tableResult = $conn->query(
        "SELECT table_id FROM restaurant_tables WHERE table_number = 5 LIMIT 1"
    )->fetch_assoc();
    $menuResult = $conn->query(
        'SELECT item_id, item_name, price
         FROM menu_items WHERE is_available = 1 ORDER BY item_id LIMIT 3'
    )->fetch_all(MYSQLI_ASSOC);

    if (!$adminResult || !$tableResult || count($menuResult) < 3) {
        throw new RuntimeException('Admin aktif, meja 5 atau sekurang-kurangnya tiga menu tersedia tidak ditemui.');
    }

    $expenseCategoryIds = [];
    $categoryResult = $conn->query(
        'SELECT expense_category_id, category_name FROM expense_categories'
    );
    foreach ($categoryResult as $category) {
        $expenseCategoryIds[$category['category_name']] = (int) $category['expense_category_id'];
    }
    foreach (['Bahan Mentah', 'Utiliti', 'Penyelenggaraan'] as $categoryName) {
        if (!isset($expenseCategoryIds[$categoryName])) {
            throw new RuntimeException('Kategori perbelanjaan tidak ditemui: ' . $categoryName);
        }
    }

    $adminId = (int) $adminResult['user_id'];
    $tableId = (int) $tableResult['table_id'];
    $orderExists = $conn->prepare('SELECT order_id FROM orders WHERE order_number = ? LIMIT 1');
    $insertSession = $conn->prepare(
        'INSERT INTO table_sessions (table_id, started_at, ended_at, session_status)
         VALUES (?, ?, ?, "Selesai")'
    );
    $insertOrder = $conn->prepare(
        'INSERT INTO orders
            (order_number, session_id, created_by, order_status, notes, created_at, completed_at)
         VALUES (?, ?, ?, "Selesai", ?, ?, ?)'
    );
    $insertItem = $conn->prepare(
        'INSERT INTO order_items
            (order_id, item_id, item_name_snapshot, unit_price, quantity, item_status, notes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, "Diserahkan", ?, ?, ?)'
    );
    $insertPayment = $conn->prepare(
        'INSERT INTO payments
            (order_id, recorded_by, amount, payment_method, payment_status,
             transaction_reference, paid_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertReceipt = $conn->prepare(
        'INSERT INTO receipts (payment_id, receipt_number, issued_at) VALUES (?, ?, ?)'
    );

    $samples = [
        [
            'number' => 'BAB-TEST-20260914A',
            'date' => '2026-09-14',
            'time' => '12:30:00',
            'menu' => 0,
            'quantity' => 2,
            'method' => 'Tunai',
            'status' => 'Berjaya',
            'receipt' => 'BBR-TEST-20260914A',
        ],
        [
            'number' => 'BAB-TEST-20260916B',
            'date' => '2026-09-16',
            'time' => '13:15:00',
            'menu' => 1,
            'quantity' => 1,
            'method' => 'Tunai',
            'status' => 'Dipulangkan',
            'receipt' => 'BBR-TEST-20260916B',
        ],
        [
            'number' => 'BAB-TEST-20260921C',
            'date' => '2026-09-21',
            'time' => '18:20:00',
            'menu' => 2,
            'quantity' => 1,
            'method' => 'FPX',
            'status' => 'Menunggu Pengesahan',
            'receipt' => null,
        ],
        [
            'number' => 'BAB-TEST-20260926D',
            'date' => '2026-09-26',
            'time' => '19:10:00',
            'menu' => 0,
            'quantity' => 1,
            'method' => 'Online Banking',
            'status' => 'Berjaya',
            'receipt' => 'BBR-TEST-20260926D',
        ],
    ];

    $insertedOrders = 0;
    foreach ($samples as $sample) {
        $orderExists->bind_param('s', $sample['number']);
        $orderExists->execute();
        if ($orderExists->get_result()->num_rows > 0) {
            continue;
        }

        $createdAt = $sample['date'] . ' ' . $sample['time'];
        $completedAt = $createdAt;
        $startedAt = date('Y-m-d H:i:s', strtotime($createdAt . ' -25 minutes'));
        $item = $menuResult[$sample['menu']];
        $unitPrice = (float) $item['price'];
        $quantity = (int) $sample['quantity'];
        $amount = $unitPrice * $quantity;
        $orderNotes = $testMarker . ' Rekod contoh; bukan transaksi sebenar.';
        $itemNotes = $testMarker . ' Item contoh.';

        $insertSession->bind_param('iss', $tableId, $startedAt, $completedAt);
        $insertSession->execute();
        $sessionId = $conn->insert_id;

        $insertOrder->bind_param(
            'siisss',
            $sample['number'],
            $sessionId,
            $adminId,
            $orderNotes,
            $createdAt,
            $completedAt
        );
        $insertOrder->execute();
        $orderId = $conn->insert_id;

        $insertItem->bind_param(
            'iisdisss',
            $orderId,
            $item['item_id'],
            $item['item_name'],
            $unitPrice,
            $quantity,
            $itemNotes,
            $createdAt,
            $completedAt
        );
        $insertItem->execute();

        $recordedBy = in_array($sample['status'], ['Berjaya', 'Dipulangkan'], true)
            ? $adminId
            : null;
        $paidAt = in_array($sample['status'], ['Berjaya', 'Dipulangkan'], true)
            ? $createdAt
            : null;
        $reference = 'TEST-REPORT-202609-' . substr($sample['number'], -1);
        $insertPayment->bind_param(
            'iidsssss',
            $orderId,
            $recordedBy,
            $amount,
            $sample['method'],
            $sample['status'],
            $reference,
            $paidAt,
            $createdAt
        );
        $insertPayment->execute();
        $paymentId = $conn->insert_id;

        if ($sample['receipt'] !== null) {
            $insertReceipt->bind_param('iss', $paymentId, $sample['receipt'], $createdAt);
            $insertReceipt->execute();
        }
        $insertedOrders++;
    }

    $insertExpense = $conn->prepare(
        'INSERT INTO expenses
            (expense_category_id, recorded_by, amount, description, expense_date, created_at)
         SELECT ?, ?, ?, ?, ?, ?
         WHERE NOT EXISTS (
             SELECT 1 FROM expenses WHERE description = ? AND expense_date = ?
         )'
    );
    $expenses = [
        ['Bahan Mentah', 8.50, 'Contoh pembelian bahan mentah', '2026-09-14'],
        ['Utiliti', 25.00, 'Contoh bil utiliti', '2026-09-16'],
        ['Penyelenggaraan', 12.00, 'Contoh penyelenggaraan peralatan', '2026-09-26'],
    ];
    $insertedExpenses = 0;
    foreach ($expenses as [$categoryName, $amount, $description, $expenseDate]) {
        $description = $testMarker . ' ' . $description;
        $createdAt = $expenseDate . ' 09:00:00';
        $categoryId = $expenseCategoryIds[$categoryName];
        $insertExpense->bind_param(
            'iidsssss',
            $categoryId,
            $adminId,
            $amount,
            $description,
            $expenseDate,
            $createdAt,
            $description,
            $expenseDate
        );
        $insertExpense->execute();
        $insertedExpenses += $insertExpense->affected_rows;
    }

    $conn->commit();
    echo "Sample report data committed for September 2026.\n";
    echo "Orders/payments inserted: {$insertedOrders}; expenses inserted: {$insertedExpenses}.\n";
    echo "All sample rows are marked {$testMarker}; restaurant table statuses were not changed.\n";
} catch (Throwable $exception) {
    $conn->rollback();
    fwrite(STDERR, 'Sample report data failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
