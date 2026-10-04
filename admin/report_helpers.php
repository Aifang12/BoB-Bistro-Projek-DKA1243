<?php

function admin_report_period(array $input): array
{
    $period = $input['period'] ?? 'month';
    if (!is_string($period) || !in_array($period, ['week', 'month', 'year'], true)) {
        throw new InvalidArgumentException('Jenis tempoh laporan tidak sah.');
    }

    if ($period === 'week') {
        $dateValue = $input['date'] ?? date('Y-m-d');
        if (!is_string($dateValue)
            || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateValue)
        ) {
            throw new InvalidArgumentException('Tarikh laporan mingguan tidak sah.');
        }
        $selectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        if (!$selectedDate || $selectedDate->format('Y-m-d') !== $dateValue) {
            throw new InvalidArgumentException('Tarikh laporan mingguan tidak sah.');
        }
        $start = $selectedDate->modify('monday this week');
        $end = $start->modify('+1 week');
        $selection = $dateValue;
        $label = 'Mingguan: ' . $start->format('d/m/Y') . ' hingga ' . $end->modify('-1 day')->format('d/m/Y');
    } elseif ($period === 'year') {
        $yearValue = $input['year'] ?? date('Y');
        if (!is_string($yearValue) && !is_int($yearValue)) {
            throw new InvalidArgumentException('Tahun laporan tidak sah.');
        }
        $yearValue = (string) $yearValue;
        if (!preg_match('/\A\d{4}\z/', $yearValue)
            || (int) $yearValue < 1000
            || (int) $yearValue > 9998
        ) {
            throw new InvalidArgumentException('Tahun laporan mestilah antara 1000 hingga 9998.');
        }
        $start = new DateTimeImmutable($yearValue . '-01-01');
        $end = $start->modify('+1 year');
        $selection = $yearValue;
        $label = 'Tahunan: ' . $yearValue;
    } else {
        $monthValue = $input['month'] ?? date('Y-m');
        if (!is_string($monthValue)
            || !preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $monthValue)
        ) {
            throw new InvalidArgumentException('Bulan laporan tidak sah.');
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m', $monthValue);
        if (!$start || $start->format('Y-m') !== $monthValue) {
            throw new InvalidArgumentException('Bulan laporan tidak sah.');
        }
        $end = $start->modify('+1 month');
        $selection = $monthValue;
        $label = 'Bulanan: ' . $start->format('m/Y');
    }

    return [
        'period' => $period,
        'selection' => $selection,
        'start' => $start,
        'end' => $end,
        'start_sql' => $start->format('Y-m-d H:i:s'),
        'end_sql' => $end->format('Y-m-d H:i:s'),
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'label' => $label,
    ];
}

function admin_load_financial_report(mysqli $conn, array $period): array
{
    $summaryStatement = $conn->prepare(
        "SELECT COUNT(*) AS payment_count,
                COUNT(DISTINCT order_id) AS order_count,
                COUNT(DISTINCT CASE
                    WHEN payment_status = 'Berjaya' THEN order_id
                END) AS successful_order_count,
                COALESCE(SUM(payment_status = 'Berjaya'), 0) AS successful_count,
                COALESCE(SUM(payment_status = 'Dipulangkan'), 0) AS refunded_count,
                COALESCE(SUM(payment_status = 'Belum Dibayar'), 0) AS unpaid_count,
                COALESCE(SUM(payment_status = 'Menunggu Pengesahan'), 0) AS pending_count,
                COALESCE(SUM(payment_status = 'Gagal'), 0) AS failed_count,
                COALESCE(SUM(CASE WHEN payment_status = 'Berjaya' THEN amount ELSE 0 END), 0) AS gross_revenue,
                COALESCE(SUM(CASE WHEN payment_status = 'Dipulangkan' THEN amount ELSE 0 END), 0) AS refunds
         FROM payments
         WHERE created_at >= ? AND created_at < ?"
    );
    $summaryStatement->bind_param('ss', $period['start_sql'], $period['end_sql']);
    $summaryStatement->execute();
    $summary = $summaryStatement->get_result()->fetch_assoc();

    $paymentBreakdownStatement = $conn->prepare(
        'SELECT payment_method, payment_status, COUNT(*) AS transaction_count,
                COALESCE(SUM(amount), 0) AS amount
         FROM payments
         WHERE created_at >= ? AND created_at < ?
         GROUP BY payment_method, payment_status
         ORDER BY payment_method, payment_status'
    );
    $paymentBreakdownStatement->bind_param('ss', $period['start_sql'], $period['end_sql']);
    $paymentBreakdownStatement->execute();
    $paymentBreakdown = $paymentBreakdownStatement->get_result()->fetch_all(MYSQLI_ASSOC);

    $orderStatement = $conn->prepare(
        'SELECT orders.order_id, orders.order_number, orders.session_id,
                orders.created_by AS order_created_by,
                order_creator.full_name AS order_created_by_name, orders.order_status,
                orders.notes AS order_notes, orders.created_at AS order_created_at,
                orders.completed_at, table_sessions.started_at AS session_started_at,
                table_sessions.ended_at AS session_ended_at,
                table_sessions.session_status, restaurant_tables.table_number,
                payments.payment_id, payments.payment_method, payments.payment_status,
                payments.amount, payments.transaction_reference, payments.created_at AS payment_created_at,
                payments.paid_at, payments.recorded_by AS payment_recorded_by,
                users.full_name AS recorded_by,
                receipts.receipt_id, receipts.receipt_number, receipts.issued_at AS receipt_issued_at
         FROM payments
         INNER JOIN orders ON orders.order_id = payments.order_id
         INNER JOIN table_sessions ON table_sessions.session_id = orders.session_id
         INNER JOIN restaurant_tables ON restaurant_tables.table_id = table_sessions.table_id
         LEFT JOIN users AS order_creator ON order_creator.user_id = orders.created_by
         LEFT JOIN users ON users.user_id = payments.recorded_by
         LEFT JOIN receipts ON receipts.payment_id = payments.payment_id
         WHERE payments.created_at >= ? AND payments.created_at < ?
         ORDER BY payments.created_at, orders.order_number'
    );
    $orderStatement->bind_param('ss', $period['start_sql'], $period['end_sql']);
    $orderStatement->execute();
    $orders = $orderStatement->get_result()->fetch_all(MYSQLI_ASSOC);

    $orderItemsStatement = $conn->prepare(
        'SELECT order_items.order_id, order_items.order_item_id, order_items.item_id,
                order_items.item_name_snapshot, order_items.unit_price,
                order_items.quantity, order_items.item_status, order_items.notes,
                order_items.created_at, order_items.updated_at
         FROM order_items
         WHERE EXISTS (
             SELECT 1 FROM payments
             WHERE payments.order_id = order_items.order_id
               AND payments.created_at >= ? AND payments.created_at < ?
         )
         ORDER BY order_items.order_id, order_items.order_item_id'
    );
    $orderItemsStatement->bind_param('ss', $period['start_sql'], $period['end_sql']);
    $orderItemsStatement->execute();
    $itemsByOrder = [];
    foreach ($orderItemsStatement->get_result() as $item) {
        $item['line_total'] = (float) $item['unit_price'] * (int) $item['quantity'];
        $itemsByOrder[(int) $item['order_id']][] = $item;
    }

    $orderStatusCounts = [];
    $seenOrderStatuses = [];
    foreach ($orders as &$order) {
        $orderId = (int) $order['order_id'];
        $order['items'] = $itemsByOrder[$orderId] ?? [];
        if (!isset($seenOrderStatuses[$orderId])) {
            $orderStatusCounts[$order['order_status']] = ($orderStatusCounts[$order['order_status']] ?? 0) + 1;
            $seenOrderStatuses[$orderId] = true;
        }
    }
    unset($order);
    $testOrderIds = [];
    foreach ($orders as $order) {
        if (str_contains((string) $order['order_notes'], '[DATA UJIAN LAPORAN]')
            || str_starts_with((string) $order['transaction_reference'], 'TEST-REPORT-')
        ) {
            $testOrderIds[(int) $order['order_id']] = true;
        }
    }

    $expenseStatement = $conn->prepare(
        'SELECT expenses.expense_id, expenses.expense_category_id,
                expenses.amount, expenses.description,
                expenses.recorded_by AS expense_recorded_by,
                expenses.expense_date, expenses.created_at,
                expense_categories.category_name, users.full_name AS recorded_by
         FROM expenses
         INNER JOIN expense_categories
            ON expense_categories.expense_category_id = expenses.expense_category_id
         LEFT JOIN users ON users.user_id = expenses.recorded_by
         WHERE expenses.expense_date >= ? AND expenses.expense_date < ?
         ORDER BY expenses.expense_date, expenses.expense_id'
    );
    $expenseStatement->bind_param('ss', $period['start_date'], $period['end_date']);
    $expenseStatement->execute();
    $expenses = $expenseStatement->get_result()->fetch_all(MYSQLI_ASSOC);

    $expenseTotal = 0.0;
    $expensesByCategory = [];
    $testExpenseCount = 0;
    foreach ($expenses as $expense) {
        $amount = (float) $expense['amount'];
        $expenseTotal += $amount;
        if (str_contains((string) $expense['description'], '[DATA UJIAN LAPORAN]')) {
            $testExpenseCount++;
        }
        $category = $expense['category_name'];
        if (!isset($expensesByCategory[$category])) {
            $expensesByCategory[$category] = ['count' => 0, 'amount' => 0.0];
        }
        $expensesByCategory[$category]['count']++;
        $expensesByCategory[$category]['amount'] += $amount;
    }
    ksort($expensesByCategory);

    $summary = [
        'payment_count' => (int) $summary['payment_count'],
        'order_count' => (int) $summary['order_count'],
        'successful_order_count' => (int) $summary['successful_order_count'],
        'successful_count' => (int) $summary['successful_count'],
        'refunded_count' => (int) $summary['refunded_count'],
        'unpaid_count' => (int) $summary['unpaid_count'],
        'pending_count' => (int) $summary['pending_count'],
        'failed_count' => (int) $summary['failed_count'],
        'gross_revenue' => (float) $summary['gross_revenue'],
        'refunds' => (float) $summary['refunds'],
        'net_revenue' => (float) $summary['gross_revenue'] - (float) $summary['refunds'],
        'expenses' => $expenseTotal,
        'profit' => (float) $summary['gross_revenue'] - (float) $summary['refunds'] - $expenseTotal,
        'expense_count' => count($expenses),
        'test_order_count' => count($testOrderIds),
        'test_expense_count' => $testExpenseCount,
        'order_status_counts' => $orderStatusCounts,
    ];

    $salesByBucket = [];
    $paymentDatesStatement = $conn->prepare(
        "SELECT DATE(created_at) AS report_date,
                SUM(CASE
                    WHEN payment_status = 'Berjaya' THEN amount
                    WHEN payment_status = 'Dipulangkan' THEN -amount
                    ELSE 0
                END) AS net_revenue
         FROM payments
         WHERE created_at >= ? AND created_at < ?
         GROUP BY DATE(created_at)"
    );
    $paymentDatesStatement->bind_param('ss', $period['start_sql'], $period['end_sql']);
    $paymentDatesStatement->execute();
    foreach ($paymentDatesStatement->get_result() as $row) {
        $salesByBucket[$row['report_date']] = (float) $row['net_revenue'];
    }

    $expensesByBucket = [];
    $expenseDatesStatement = $conn->prepare(
        'SELECT expense_date AS report_date, SUM(amount) AS expenses
         FROM expenses
         WHERE expense_date >= ? AND expense_date < ?
         GROUP BY expense_date'
    );
    $expenseDatesStatement->bind_param('ss', $period['start_date'], $period['end_date']);
    $expenseDatesStatement->execute();
    foreach ($expenseDatesStatement->get_result() as $row) {
        $expensesByBucket[$row['report_date']] = (float) $row['expenses'];
    }

    $timeline = [];
    if ($period['period'] === 'year') {
        $monthNames = [
            'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun',
            'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember',
        ];
        for ($month = 1; $month <= 12; $month++) {
            $key = $period['start']->setDate((int) $period['start']->format('Y'), $month, 1)->format('Y-m-d');
            $timeline[] = [
                'key' => $key,
                'label' => $monthNames[$month - 1],
                'revenue' => array_sum(array_filter(
                    $salesByBucket,
                    static fn (float $amount, string $date): bool => str_starts_with($date, substr($key, 0, 7)),
                    ARRAY_FILTER_USE_BOTH
                )),
                'expenses' => array_sum(array_filter(
                    $expensesByBucket,
                    static fn (float $amount, string $date): bool => str_starts_with($date, substr($key, 0, 7)),
                    ARRAY_FILTER_USE_BOTH
                )),
            ];
        }
    } else {
        for ($date = $period['start']; $date < $period['end']; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $timeline[] = [
                'key' => $key,
                'label' => $date->format('d/m'),
                'revenue' => $salesByBucket[$key] ?? 0.0,
                'expenses' => $expensesByBucket[$key] ?? 0.0,
            ];
        }
    }

    return [
        'period' => $period,
        'summary' => $summary,
        'payment_breakdown' => $paymentBreakdown,
        'orders' => $orders,
        'expenses' => $expenses,
        'expenses_by_category' => $expensesByCategory,
        'timeline' => $timeline,
    ];
}

function admin_report_money(float|int|string $amount): string
{
    return 'RM ' . number_format((float) $amount, 2, '.', ',');
}
