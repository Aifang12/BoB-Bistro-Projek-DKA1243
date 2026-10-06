<?php

require_once __DIR__ . '/auth.php';
$isCustomerReceipt = array_key_exists('order', $_GET);
if (!$isCustomerReceipt) {
    admin_require_authentication();
}
require_once __DIR__ . '/../config.php';

if (!$isCustomerReceipt) {
    if (!admin_refresh_identity($conn)) {
        header('Location: login.php?expired=1');
        exit;
    }

    if (!in_array($_SESSION['admin_role'], ['Admin', 'Cashier'], true)) {
        http_response_code(403);
        exit('Anda tidak mempunyai kebenaran untuk melihat resit.');
    }
}

$rawReceiptNumber = $_GET['receipt'] ?? '';
$rawOrderNumber = $_GET['order'] ?? '';
if (!is_string($rawReceiptNumber) || !is_string($rawOrderNumber)) {
    http_response_code(400);
    exit('Nombor resit atau tempahan tidak sah.');
}
$receiptNumber = trim($rawReceiptNumber);
$orderNumber = trim($rawOrderNumber);
if ($isCustomerReceipt && !preg_match('/\ABAB-[A-F0-9]{16}\z/', $orderNumber)) {
    http_response_code(400);
    exit('Nombor tempahan tidak sah.');
}
if (!$isCustomerReceipt && !preg_match('/\ABAB-R-[A-F0-9]{12}\z/', $receiptNumber)) {
    http_response_code(400);
    exit('Nombor resit tidak sah.');
}

$lookupColumn = $isCustomerReceipt ? 'orders.order_number' : 'receipts.receipt_number';
$lookupValue = $isCustomerReceipt ? $orderNumber : $receiptNumber;
$statement = $conn->prepare(
    "SELECT receipts.receipt_number, receipts.issued_at,
            payments.amount, payments.payment_method, payments.payment_status,
            orders.order_id, orders.order_number, orders.notes, restaurant_tables.table_number
     FROM receipts
     INNER JOIN payments ON payments.payment_id = receipts.payment_id
     INNER JOIN orders ON orders.order_id = payments.order_id
     INNER JOIN table_sessions ON table_sessions.session_id = orders.session_id
     INNER JOIN restaurant_tables ON restaurant_tables.table_id = table_sessions.table_id
     WHERE $lookupColumn = ?
       AND payments.payment_status = 'Berjaya'
     LIMIT 1"
);
$statement->bind_param('s', $lookupValue);
$statement->execute();
$receipt = $statement->get_result()->fetch_assoc();

if (!$receipt) {
    http_response_code(404);
    exit('Resit tidak ditemui atau pembayaran belum berjaya.');
}

$itemStatement = $conn->prepare(
    'SELECT item_name_snapshot, unit_price, quantity
     FROM order_items WHERE order_id = ? ORDER BY order_item_id'
);
$itemStatement->bind_param('i', $receipt['order_id']);
$itemStatement->execute();
$items = $itemStatement->get_result()->fetch_all(MYSQLI_ASSOC);

function receipt_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resit <?= receipt_escape($receipt['receipt_number']) ?> | B@Bistro</title>
    <link rel="stylesheet" href="receipt.css?v=3">
</head>
<body>
    <main class="receipt">
        <header class="receipt-header">
            <img class="receipt-brand" src="../images/logo/logo coloured.svg" alt="B@Bistro">
            <h1>Resit Pembayaran</h1>
            <p><?= receipt_escape($receipt['receipt_number']) ?></p>
        </header>

        <dl class="receipt-details">
            <div><dt>No. Pesanan</dt><dd><?= receipt_escape($receipt['order_number']) ?></dd></div>
            <div><dt>Meja</dt><dd><?= (int) $receipt['table_number'] ?></dd></div>
            <div><dt>Tarikh</dt><dd><?= receipt_escape($receipt['issued_at']) ?></dd></div>
            <div><dt>Kaedah</dt><dd><?= receipt_escape($receipt['payment_method']) ?></dd></div>
            <div><dt>Status</dt><dd><?= receipt_escape($receipt['payment_status']) ?></dd></div>
        </dl>

        <table>
            <thead><tr><th>Menu</th><th>Kuantiti</th><th>Jumlah</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= receipt_escape($item['item_name_snapshot']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td>RM <?= number_format((float) $item['unit_price'] * (int) $item['quantity'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="2">Jumlah dibayar</th><th>RM <?= number_format((float) $receipt['amount'], 2) ?></th></tr></tfoot>
        </table>

        <?php if ($receipt['notes'] !== null && $receipt['notes'] !== ''): ?>
            <p class="receipt-notes"><strong>Nota:</strong> <?= receipt_escape($receipt['notes']) ?></p>
        <?php endif; ?>

        <p class="receipt-thanks">Terima kasih kerana memilih B@Bistro.</p>
        <div class="receipt-actions">
            <button type="button" onclick="window.print()">Muat turun / Cetak PDF</button>
            <?php if ($isCustomerReceipt): ?>
                <a href="../order-status.html?order=<?= rawurlencode($receipt['order_number']) ?>">Kembali ke pesanan</a>
            <?php else: ?>
                <a href="index.php">Kembali ke panel</a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
