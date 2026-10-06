<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function order_api_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function find_order(mysqli $conn, string $orderNumber): ?array
{
    $statement = $conn->prepare(
        'SELECT orders.order_id, orders.order_number, orders.order_status,
                orders.notes, orders.created_at, restaurant_tables.table_number,
                payments.payment_method, payments.payment_status, payments.amount,
                receipts.receipt_number
         FROM orders
         INNER JOIN table_sessions ON table_sessions.session_id = orders.session_id
         INNER JOIN restaurant_tables ON restaurant_tables.table_id = table_sessions.table_id
         INNER JOIN payments ON payments.order_id = orders.order_id
         LEFT JOIN receipts ON receipts.payment_id = payments.payment_id
         WHERE orders.order_number = ?
         LIMIT 1'
    );
    $statement->bind_param('s', $orderNumber);
    $statement->execute();
    $order = $statement->get_result()->fetch_assoc();

    if (!$order) {
        return null;
    }

    $itemStatement = $conn->prepare(
        'SELECT order_items.item_name_snapshot, order_items.unit_price,
                order_items.quantity, menu_items.image_path
         FROM order_items
         INNER JOIN menu_items ON menu_items.item_id = order_items.item_id
         WHERE order_items.order_id = ?
         ORDER BY order_items.order_item_id'
    );
    $itemStatement->bind_param('i', $order['order_id']);
    $itemStatement->execute();

    $items = [];
    foreach ($itemStatement->get_result() as $item) {
        $items[] = [
            'name' => $item['item_name_snapshot'],
            'price' => (float) $item['unit_price'],
            'quantity' => (int) $item['quantity'],
            'image' => $item['image_path'] ?: 'images/foods/category/makanan.png',
        ];
    }

    return [
        'id' => (int) $order['order_id'],
        'order_number' => $order['order_number'],
        'status' => $order['order_status'],
        'table_number' => (int) $order['table_number'],
        'notes' => $order['notes'] ?? '',
        'created_at' => $order['created_at'],
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'],
        'receipt_url' => $order['payment_status'] === 'Berjaya' && $order['receipt_number'] !== null
            ? 'admin/receipt.php?order=' . rawurlencode($order['order_number'])
            : null,
        'total' => (float) $order['amount'],
        'items' => $items,
    ];
}

try {
    require_once __DIR__ . '/../config.php';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($requestMethod === 'GET') {
        $rawOrderNumber = $_GET['order'] ?? '';
        if (!is_string($rawOrderNumber)) {
            order_api_response(400, ['error' => 'Nombor tempahan tidak sah.']);
        }
        $orderNumber = strtoupper(trim($rawOrderNumber));
        if (!preg_match('/\ABAB-[A-F0-9]{16}\z/', $orderNumber)) {
            order_api_response(400, ['error' => 'Nombor tempahan tidak sah.']);
        }

        $order = find_order($conn, $orderNumber);
        if ($order === null) {
            order_api_response(404, ['error' => 'Tempahan tidak ditemui.']);
        }

        order_api_response(200, ['order' => $order]);
    }

    if ($requestMethod !== 'POST') {
        header('Allow: GET, POST');
        order_api_response(405, ['error' => 'Kaedah permintaan tidak disokong.']);
    }

    try {
        $payload = json_decode(
            file_get_contents('php://input'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        order_api_response(400, ['error' => 'Data tempahan tidak sah.']);
    }

    if (!is_array($payload)) {
        order_api_response(400, ['error' => 'Data tempahan tidak sah.']);
    }

    $tableNumber = $payload['table_number'] ?? null;
    $rawItems = $payload['items'] ?? null;
    $paymentMethod = $payload['payment_method'] ?? null;
    $notes = $payload['notes'] ?? '';

    if (!is_int($tableNumber) || $tableNumber < 1) {
        order_api_response(422, ['error' => 'Nombor meja tidak sah. Imbas semula QR meja.']);
    }
    if (!is_array($rawItems) || count($rawItems) < 1 || count($rawItems) > 50) {
        order_api_response(422, ['error' => 'Bakul kosong atau mempunyai terlalu banyak jenis item.']);
    }
    $paymentMethods = ['Tunai', 'Online Banking', 'TNG eWallet', 'Boost', 'ShopeePay'];
    if (!is_string($paymentMethod) || !in_array($paymentMethod, $paymentMethods, true)) {
        order_api_response(422, ['error' => 'Sila pilih kaedah pembayaran yang disokong.']);
    }
    if (!is_string($notes)) {
        order_api_response(422, ['error' => 'Nota pesanan tidak sah.']);
    }
    $notes = trim($notes);
    if (strlen($notes) > 2000) {
        order_api_response(422, ['error' => 'Nota pesanan terlalu panjang (maksimum 2,000 aksara).']);
    }

    $requestedItems = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)
            || !is_int($item['id'] ?? null)
            || !is_int($item['quantity'] ?? null)
            || $item['id'] < 1
            || $item['quantity'] < 1
        ) {
            order_api_response(422, ['error' => 'Kuantiti atau item dalam bakul tidak sah.']);
        }

        $itemId = $item['id'];
        $requestedItems[$itemId] = ($requestedItems[$itemId] ?? 0) + $item['quantity'];
        if ($requestedItems[$itemId] > 99) {
            order_api_response(422, ['error' => 'Kuantiti maksimum bagi setiap menu ialah 99.']);
        }
    }

    $conn->begin_transaction();
    try {
        $tableStatement = $conn->prepare(
            'SELECT table_id, table_status
             FROM restaurant_tables
             WHERE table_number = ?
             FOR UPDATE'
        );
        $tableStatement->bind_param('i', $tableNumber);
        $tableStatement->execute();
        $table = $tableStatement->get_result()->fetch_assoc();

        if (!$table) {
            throw new InvalidArgumentException('Nombor meja tidak ditemui. Imbas semula QR meja.');
        }
        if ($table['table_status'] === 'Menunggu Pembersihan') {
            throw new InvalidArgumentException('Meja ini sedang menunggu pembersihan. Sila pilih meja lain atau hubungi kakitangan.');
        }

        $sessionStatement = $conn->prepare(
            "SELECT session_id
             FROM table_sessions
             WHERE table_id = ? AND session_status = 'Aktif' AND ended_at IS NULL
             ORDER BY session_id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $sessionStatement->bind_param('i', $table['table_id']);
        $sessionStatement->execute();
        $session = $sessionStatement->get_result()->fetch_assoc();

        if ($session) {
            $tableSessionId = (int) $session['session_id'];
        } else {
            $createSession = $conn->prepare(
                'INSERT INTO table_sessions (table_id) VALUES (?)'
            );
            $createSession->bind_param('i', $table['table_id']);
            $createSession->execute();
            $tableSessionId = (int) $conn->insert_id;
        }

        $occupyTable = $conn->prepare(
            "UPDATE restaurant_tables
             SET table_status = 'Diduduki'
             WHERE table_id = ?"
        );
        $occupyTable->bind_param('i', $table['table_id']);
        $occupyTable->execute();

        $orderItems = [];
        $totalCents = 0;
        $menuStatement = $conn->prepare(
            'SELECT item_id, item_name, price, image_path
             FROM menu_items
             WHERE item_id = ? AND is_available = 1
             FOR UPDATE'
        );

        foreach ($requestedItems as $itemId => $quantity) {
            $menuStatement->bind_param('i', $itemId);
            $menuStatement->execute();
            $menuItem = $menuStatement->get_result()->fetch_assoc();
            if (!$menuItem) {
                throw new InvalidArgumentException('Salah satu menu dalam bakul sudah tidak tersedia. Sila semak menu.');
            }

            $unitPrice = number_format((float) $menuItem['price'], 2, '.', '');
            $unitPriceCents = (int) round((float) $menuItem['price'] * 100);
            $totalCents += $unitPriceCents * $quantity;
            $orderItems[] = [
                'id' => (int) $menuItem['item_id'],
                'name' => $menuItem['item_name'],
                'price' => $unitPrice,
                'quantity' => $quantity,
                'image' => $menuItem['image_path'] ?: null,
            ];
        }

        if ($totalCents < 1 || $totalCents > 9999999999) {
            throw new InvalidArgumentException('Jumlah tempahan di luar had yang dibenarkan.');
        }

        $orderNumber = 'BAB-' . strtoupper(bin2hex(random_bytes(8)));
        $orderStatement = $conn->prepare(
            'INSERT INTO orders (order_number, session_id, order_status, notes)
             VALUES (?, ?, \'Menunggu\', ?)'
        );
        $orderStatement->bind_param('sis', $orderNumber, $tableSessionId, $notes);
        $orderStatement->execute();
        $orderId = (int) $conn->insert_id;

        $itemInsert = $conn->prepare(
            'INSERT INTO order_items
                (order_id, item_id, item_name_snapshot, unit_price, quantity)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($orderItems as $item) {
            $itemInsert->bind_param(
                'iissi',
                $orderId,
                $item['id'],
                $item['name'],
                $item['price'],
                $item['quantity']
            );
            $itemInsert->execute();
        }

        $initialStatus = 'Menunggu';
        $historyStatement = $conn->prepare(
            'INSERT INTO order_status_history (order_id, old_status, new_status)
             VALUES (?, NULL, ?)'
        );
        $historyStatement->bind_param('is', $orderId, $initialStatus);
        $historyStatement->execute();

        $paymentStatus = $paymentMethod === 'Tunai' ? 'Belum Dibayar' : 'Berjaya';
        $amount = number_format($totalCents / 100, 2, '.', '');
        $paymentStatement = $conn->prepare(
            'INSERT INTO payments (order_id, amount, payment_method, payment_status, paid_at)
             VALUES (?, ?, ?, ?, IF(? = \'Berjaya\', CURRENT_TIMESTAMP, NULL))'
        );
        $paymentStatement->bind_param('issss', $orderId, $amount, $paymentMethod, $paymentStatus, $paymentStatus);
        $paymentStatement->execute();
        $paymentId = (int) $conn->insert_id;

        if ($paymentStatus === 'Berjaya') {
            $receiptNumber = 'BAB-R-' . strtoupper(bin2hex(random_bytes(6)));
            $receiptStatement = $conn->prepare(
                'INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)'
            );
            $receiptStatement->bind_param('is', $paymentId, $receiptNumber);
            $receiptStatement->execute();
        }

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    order_api_response(201, ['order' => find_order($conn, $orderNumber)]);
} catch (InvalidArgumentException $exception) {
    order_api_response(422, ['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Order API error: ' . $exception->getMessage());
    order_api_response(500, ['error' => 'Tempahan tidak dapat disimpan. Sila cuba lagi.']);
}
