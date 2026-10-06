<?php

require_once dirname(__DIR__, 2) . '/admin/auth.php';
require_once dirname(__DIR__, 2) . '/config.php';

$error = '';
$success = '';
$transactionOpen = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!admin_verify_csrf()) {
            throw new InvalidArgumentException('Sesi halaman tamat. Sila muat semula halaman dan cuba lagi.');
        }
        if (($_POST['action'] ?? '') !== 'mark_delivered') {
            throw new InvalidArgumentException('Tindakan tidak sah.');
        }

        $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        if ($orderId === false || $orderId === null || $orderId < 1) {
            throw new InvalidArgumentException('Nombor pesanan tidak sah.');
        }

        $conn->begin_transaction();
        $transactionOpen = true;

        $orderStatement = $conn->prepare(
            'SELECT order_number, order_status FROM orders WHERE order_id = ? FOR UPDATE'
        );
        $orderStatement->bind_param('i', $orderId);
        $orderStatement->execute();
        $order = $orderStatement->get_result()->fetch_assoc();
        if (!$order || $order['order_status'] !== 'Sedia Diambil') {
            throw new InvalidArgumentException('Pesanan ini tidak lagi menunggu untuk diserahkan.');
        }

        $updateOrder = $conn->prepare(
            "UPDATE orders SET order_status = 'Diserahkan' WHERE order_id = ?"
        );
        $updateOrder->bind_param('i', $orderId);
        $updateOrder->execute();

        $updateItems = $conn->prepare(
            "UPDATE order_items SET item_status = 'Diserahkan' WHERE order_id = ?"
        );
        $updateItems->bind_param('i', $orderId);
        $updateItems->execute();

        $history = $conn->prepare(
            "INSERT INTO order_status_history (order_id, user_id, old_status, new_status)
             VALUES (?, NULL, 'Sedia Diambil', 'Diserahkan')"
        );
        $history->bind_param('i', $orderId);
        $history->execute();

        $audit = $conn->prepare(
            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id)
             VALUES (NULL, 'Pelayan menandakan pesanan sebagai Diserahkan', 'orders', ?)"
        );
        $audit->bind_param('i', $orderId);
        $audit->execute();

        $conn->commit();
        $transactionOpen = false;
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        header('Location: ' . (is_string($requestPath) ? $requestPath : 'order') . '?delivered=1');
        exit;
    } catch (InvalidArgumentException $exception) {
        if ($transactionOpen) {
            $conn->rollback();
        }
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($transactionOpen) {
            $conn->rollback();
        }
        error_log('Waiter order handoff error: ' . $exception->getMessage());
        $error = 'Pesanan tidak dapat dikemas kini. Sila cuba lagi.';
    }
}

if (isset($_GET['delivered'])) {
    $success = 'Pesanan telah ditandakan sebagai diserahkan.';
}

try {
    $ordersResult = $conn->query(
        "SELECT orders.order_id, orders.order_number, orders.notes, orders.created_at,
                restaurant_tables.table_number,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(order_items.item_name_snapshot, ' ×', order_items.quantity)
                        ORDER BY order_items.order_item_id SEPARATOR ', '
                    )
                    FROM order_items
                    WHERE order_items.order_id = orders.order_id
                ) AS item_summary
         FROM orders
         INNER JOIN table_sessions ON table_sessions.session_id = orders.session_id
         INNER JOIN restaurant_tables ON restaurant_tables.table_id = table_sessions.table_id
         WHERE orders.order_status = 'Sedia Diambil'
         ORDER BY orders.created_at ASC, orders.order_id ASC"
    );
    $orders = $ordersResult->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $exception) {
    error_log('Waiter ready order listing error: ' . $exception->getMessage());
    http_response_code(500);
    $error = 'Senarai pesanan tidak dapat dimuatkan. Sila cuba lagi.';
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="20">
    <title>Pesanan sedia diambil | B@Bistro</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="order.css?v=1">
</head>
<body>
    <main class="waiter-page">
        <header class="waiter-header">
            <div>
                <img src="../../images/logo/logo coloured.svg" alt="B@Bistro" class="waiter-logo">
                <h1>Pesanan sedia diambil</h1>
                <p>Serahkan pesanan kepada pelanggan dan kemas kini status di sini.</p>
            </div>
            <span class="waiter-order-count"><?= count($orders) ?> pesanan</span>
        </header>

        <?php if ($success !== ''): ?><p class="waiter-message waiter-success" role="status"><?= admin_escape($success) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="waiter-message waiter-error" role="alert"><?= admin_escape($error) ?></p><?php endif; ?>

        <?php if ($orders === []): ?>
            <section class="waiter-empty">
                <span class="waiter-empty-icon" aria-hidden="true"><i class="bi bi-bag-check"></i></span>
                <h2>Tiada pesanan sedia diambil</h2>
                <p>Pesanan baharu akan muncul di sini selepas disediakan oleh dapur.</p>
            </section>
        <?php else: ?>
            <section class="waiter-orders" aria-label="Senarai pesanan sedia diambil">
                <?php foreach ($orders as $order): ?>
                    <article class="waiter-order">
                        <div class="waiter-order-heading">
                            <div>
                                <span class="waiter-table">Meja <?= (int) $order['table_number'] ?></span>
                                <h2><?= admin_escape($order['order_number']) ?></h2>
                            </div>
                            <time datetime="<?= admin_escape(date(DATE_ATOM, strtotime($order['created_at']))) ?>">
                                <?= admin_escape(date('d/m/Y H:i', strtotime($order['created_at']))) ?>
                            </time>
                        </div>
                        <p class="waiter-items"><?= admin_escape($order['item_summary'] ?? '') ?></p>
                        <?php if (!empty($order['notes'])): ?>
                            <p class="waiter-notes"><strong>Nota:</strong> <?= admin_escape($order['notes']) ?></p>
                        <?php endif; ?>
                        <form method="post" class="waiter-action">
                            <input type="hidden" name="csrf_token" value="<?= admin_escape(admin_csrf_token()) ?>">
                            <input type="hidden" name="action" value="mark_delivered">
                            <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                            <button class="waiter-deliver-button" type="submit">
                                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                Tandakan sebagai diserahkan
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
