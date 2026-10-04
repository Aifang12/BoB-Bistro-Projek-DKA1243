<?php

require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../config.php';

if (!admin_refresh_identity($conn)) {
    header('Location: login.php?expired=1');
    exit;
}
if ($_SESSION['admin_role'] !== 'Admin') {
    http_response_code(403);
    exit('Laporan kewangan hanya boleh diakses oleh Admin.');
}

require_once __DIR__ . '/report_helpers.php';

try {
    $period = admin_report_period($_GET);
    $report = admin_load_financial_report($conn, $period);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    exit($exception->getMessage());
} catch (Throwable $exception) {
    error_log('Financial report CSV error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Laporan tidak dapat dimuat turun. Sila semak log sistem.');
}

function report_csv_text(string|null $value): string
{
    $value = $value ?? '';
    if ($value !== '' && preg_match('/\A[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function report_csv_row($stream, array $fields): void
{
    if (fputcsv($stream, $fields, ',', '"', '') === false) {
        throw new RuntimeException('CSV row could not be written.');
    }
}

$filename = 'laporan-kewangan-' . $period['period'] . '-' . $period['selection'] . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$output = fopen('php://output', 'wb');
if ($output === false) {
    throw new RuntimeException('CSV output stream cannot be opened.');
}

fwrite($output, "\xEF\xBB\xBF");
report_csv_row($output, ['LAPORAN KEWANGAN TERPERINCI — B@Bistro']);
report_csv_row($output, ['Tempoh', $period['label']]);
report_csv_row($output, ['Dari', $period['start']->format('Y-m-d')]);
report_csv_row($output, ['Hingga', $period['end']->modify('-1 day')->format('Y-m-d')]);
report_csv_row($output, ['Dijana pada', date('Y-m-d H:i:s')]);
report_csv_row($output, [
    'Data ujian dalam tempoh',
    (int) $report['summary']['test_order_count'] . ' pesanan; '
        . (int) $report['summary']['test_expense_count'] . ' perbelanjaan',
]);
report_csv_row($output, []);

report_csv_row($output, ['RINGKASAN KEWANGAN']);
report_csv_row($output, ['Metrik', 'Nilai']);
report_csv_row($output, ['Hasil kasar bayaran berjaya (RM)', number_format($report['summary']['gross_revenue'], 2, '.', '')]);
report_csv_row($output, ['Bayaran dipulangkan (RM)', number_format($report['summary']['refunds'], 2, '.', '')]);
report_csv_row($output, ['Hasil bersih (RM)', number_format($report['summary']['net_revenue'], 2, '.', '')]);
report_csv_row($output, ['Perbelanjaan (RM)', number_format($report['summary']['expenses'], 2, '.', '')]);
report_csv_row($output, ['Untung / (rugi) bersih (RM)', number_format($report['summary']['profit'], 2, '.', '')]);
report_csv_row($output, ['Pesanan', $report['summary']['order_count']]);
report_csv_row($output, ['Pesanan dengan bayaran berjaya', $report['summary']['successful_order_count']]);
report_csv_row($output, ['Transaksi bayaran', $report['summary']['payment_count']]);
report_csv_row($output, ['Bayaran berjaya', $report['summary']['successful_count']]);
report_csv_row($output, ['Bayaran dipulangkan', $report['summary']['refunded_count']]);
report_csv_row($output, ['Belum dibayar', $report['summary']['unpaid_count']]);
report_csv_row($output, ['Menunggu pengesahan', $report['summary']['pending_count']]);
report_csv_row($output, ['Bayaran gagal', $report['summary']['failed_count']]);
report_csv_row($output, ['Rekod perbelanjaan', $report['summary']['expense_count']]);
report_csv_row($output, []);

report_csv_row($output, ['PECAHAN BAYARAN']);
report_csv_row($output, ['Kaedah', 'Status', 'Bilangan transaksi', 'Jumlah direkod (RM)', 'Kesan hasil bersih (RM)']);
foreach ($report['payment_breakdown'] as $payment) {
    $netEffect = in_array($payment['payment_status'], ['Berjaya', 'Dipulangkan'], true)
        ? (float) $payment['amount'] * ($payment['payment_status'] === 'Dipulangkan' ? -1 : 1)
        : 0.0;
    report_csv_row($output, [
        report_csv_text($payment['payment_method']),
        report_csv_text($payment['payment_status']),
        (int) $payment['transaction_count'],
        number_format((float) $payment['amount'], 2, '.', ''),
        number_format($netEffect, 2, '.', ''),
    ]);
}
report_csv_row($output, []);

report_csv_row($output, ['STATUS PESANAN']);
report_csv_row($output, ['Status pesanan', 'Bilangan']);
foreach ($report['summary']['order_status_counts'] as $status => $count) {
    report_csv_row($output, [report_csv_text((string) $status), $count]);
}
report_csv_row($output, []);

report_csv_row($output, ['BUTIRAN PESANAN DAN ITEM']);
report_csv_row($output, [
    'ID pesanan', 'No. pesanan', 'ID sesi meja', 'Status sesi meja',
    'Sesi bermula', 'Sesi tamat', 'Meja',
    'ID pencipta pesanan', 'Nama pencipta pesanan', 'Tarikh pesanan', 'Tarikh selesai', 'Status pesanan',
    'Nota pesanan', 'Kaedah bayaran', 'Status bayaran', 'Jumlah bayaran (RM)',
    'Tarikh rekod bayaran', 'Tarikh bayaran berjaya', 'Rujukan transaksi',
    'ID bayaran', 'ID juruwang', 'Direkod oleh', 'ID resit', 'No. resit',
    'Tarikh resit', 'ID item pesanan', 'ID menu asal', 'Nama item snapshot',
    'Harga seunit (RM)', 'Kuantiti', 'Status item', 'Nota item',
    'Item dicipta', 'Item dikemas kini', 'Jumlah item (RM)',
]);
foreach ($report['orders'] as $order) {
    $items = $order['items'] === [] ? [null] : $order['items'];
    foreach ($items as $item) {
        report_csv_row($output, [
            (int) $order['order_id'],
            report_csv_text($order['order_number']),
            (int) $order['session_id'],
            report_csv_text($order['session_status']),
            report_csv_text($order['session_started_at']),
            report_csv_text($order['session_ended_at']),
            (int) $order['table_number'],
            $order['order_created_by'] === null ? '' : (int) $order['order_created_by'],
            report_csv_text($order['order_created_by_name']),
            report_csv_text($order['order_created_at']),
            report_csv_text($order['completed_at']),
            report_csv_text($order['order_status']),
            report_csv_text($order['order_notes']),
            report_csv_text($order['payment_method']),
            report_csv_text($order['payment_status']),
            number_format((float) $order['amount'], 2, '.', ''),
            report_csv_text($order['payment_created_at']),
            report_csv_text($order['paid_at']),
            report_csv_text($order['transaction_reference']),
            (int) $order['payment_id'],
            $order['payment_recorded_by'] === null ? '' : (int) $order['payment_recorded_by'],
            report_csv_text($order['recorded_by']),
            $order['receipt_id'] === null ? '' : (int) $order['receipt_id'],
            report_csv_text($order['receipt_number']),
            report_csv_text($order['receipt_issued_at']),
            $item === null ? '' : (int) $item['order_item_id'],
            $item === null ? '' : (int) $item['item_id'],
            report_csv_text($item['item_name_snapshot'] ?? null),
            $item === null ? '' : number_format((float) $item['unit_price'], 2, '.', ''),
            $item === null ? '' : (int) $item['quantity'],
            report_csv_text($item['item_status'] ?? null),
            report_csv_text($item['notes'] ?? null),
            report_csv_text($item['created_at'] ?? null),
            report_csv_text($item['updated_at'] ?? null),
            $item === null ? '' : number_format((float) $item['line_total'], 2, '.', ''),
        ]);
    }
}
report_csv_row($output, []);

report_csv_row($output, ['PERBELANJAAN MENGIKUT KATEGORI']);
report_csv_row($output, ['Kategori', 'Bilangan rekod', 'Jumlah (RM)']);
foreach ($report['expenses_by_category'] as $category => $totals) {
    report_csv_row($output, [
        report_csv_text((string) $category),
        (int) $totals['count'],
        number_format((float) $totals['amount'], 2, '.', ''),
    ]);
}
report_csv_row($output, []);

report_csv_row($output, ['BUTIRAN PERBELANJAAN']);
report_csv_row($output, ['ID', 'Tarikh perbelanjaan', 'ID kategori', 'Kategori', 'Keterangan', 'ID direkod oleh', 'Direkod oleh', 'Tarikh rekod', 'Jumlah (RM)']);
foreach ($report['expenses'] as $expense) {
    report_csv_row($output, [
        (int) $expense['expense_id'],
        report_csv_text($expense['expense_date']),
        (int) $expense['expense_category_id'],
        report_csv_text($expense['category_name']),
        report_csv_text($expense['description']),
        $expense['expense_recorded_by'] === null ? '' : (int) $expense['expense_recorded_by'],
        report_csv_text($expense['recorded_by']),
        report_csv_text($expense['created_at']),
        number_format((float) $expense['amount'], 2, '.', ''),
    ]);
}
report_csv_row($output, []);

report_csv_row($output, ['TREND HASIL DAN PERBELANJAAN']);
report_csv_row($output, ['Tarikh / bulan', 'Hasil bersih (RM)', 'Perbelanjaan (RM)', 'Untung / (rugi) (RM)']);
foreach ($report['timeline'] as $bucket) {
    report_csv_row($output, [
        report_csv_text($bucket['label']),
        number_format((float) $bucket['revenue'], 2, '.', ''),
        number_format((float) $bucket['expenses'], 2, '.', ''),
        number_format((float) $bucket['revenue'] - (float) $bucket['expenses'], 2, '.', ''),
    ]);
}

fclose($output);
