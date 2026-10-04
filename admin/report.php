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
    $reportPeriod = admin_report_period($_GET);
    $report = admin_load_financial_report($conn, $reportPeriod);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    $reportError = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Financial report error: ' . $exception->getMessage());
    http_response_code(500);
    $reportError = 'Laporan tidak dapat dijana. Sila semak log sistem.';
}

$periodName = $reportPeriod['period'] ?? 'month';
$periodField = $periodName === 'week'
    ? 'date'
    : ($periodName === 'year' ? 'year' : 'month');
$query = [
    'period' => $periodName,
    $periodField => $reportPeriod['selection'] ?? date('Y-m'),
];
$downloadUrl = 'report_download.php?' . http_build_query($query);

function report_escape(string|null $value): string
{
    return htmlspecialchars($value ?? '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kewangan | B@Bistro</title>
    <link rel="stylesheet" href="report.css?v=2">
</head>
<body>
    <?php if (isset($reportError)): ?>
        <main class="report-error">
            <h1>Laporan tidak tersedia</h1>
            <p><?= report_escape($reportError) ?></p>
            <a href="index.php">Kembali ke panel Admin</a>
        </main>
    <?php else: ?>
        <nav class="report-actions" aria-label="Tindakan laporan">
            <a href="index.php">Kembali ke panel Admin</a>
            <a href="<?= report_escape($downloadUrl) ?>" download>Muat turun CSV</a>
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </nav>

        <main class="report-document">
            <header class="report-header">
                <img src="../images/logo/logo coloured.svg" alt="B@Bistro">
                <h1>Laporan Kewangan Terperinci</h1>
                <p class="report-period"><?= report_escape($report['period']['label']) ?></p>
                <p class="report-range">
                    Tempoh: <?= report_escape($report['period']['start']->format('d/m/Y')) ?>
                    hingga <?= report_escape($report['period']['end']->modify('-1 day')->format('d/m/Y')) ?>
                </p>
                <p class="report-generated">Dijana pada <?= date('d/m/Y H:i:s') ?></p>
            </header>

            <?php if (($report['summary']['test_order_count'] + $report['summary']['test_expense_count']) > 0): ?>
                <p class="report-warning" role="note">
                    Laporan ini mengandungi <?= (int) $report['summary']['test_order_count'] ?> pesanan dan
                    <?= (int) $report['summary']['test_expense_count'] ?> perbelanjaan data ujian.
                    Rekod bertanda <strong>[DATA UJIAN LAPORAN]</strong> dikekalkan dan termasuk dalam jumlah.
                </p>
            <?php endif; ?>

            <section>
                <h2>1. Ringkasan kewangan</h2>
                <table>
                    <tbody>
                        <tr><th>Hasil kasar daripada bayaran berjaya</th><td><?= admin_report_money($report['summary']['gross_revenue']) ?></td></tr>
                        <tr><th>Bayaran dipulangkan</th><td><?= admin_report_money($report['summary']['refunds']) ?></td></tr>
                        <tr><th>Hasil bersih selepas pulangan</th><td><?= admin_report_money($report['summary']['net_revenue']) ?></td></tr>
                        <tr><th>Jumlah perbelanjaan</th><td><?= admin_report_money($report['summary']['expenses']) ?></td></tr>
                        <tr class="report-total"><th>Untung / (rugi) bersih</th><td><?= admin_report_money($report['summary']['profit']) ?></td></tr>
                        <tr><th>Bilangan pesanan dengan rekod bayaran</th><td><?= (int) $report['summary']['order_count'] ?></td></tr>
                        <tr><th>Bilangan pesanan dengan bayaran berjaya</th><td><?= (int) $report['summary']['successful_order_count'] ?></td></tr>
                        <tr><th>Bilangan transaksi bayaran</th><td><?= (int) $report['summary']['payment_count'] ?></td></tr>
                        <tr><th>Bilangan rekod perbelanjaan</th><td><?= (int) $report['summary']['expense_count'] ?></td></tr>
                    </tbody>
                </table>
                <p class="report-method">
                    Kaedah: hasil bersih = jumlah bayaran Berjaya − jumlah Dipulangkan.
                    Untung / (rugi) bersih = hasil bersih − perbelanjaan yang direkodkan.
                    Transaksi Belum Dibayar, Menunggu Pengesahan dan Gagal tidak dikira sebagai hasil.
                    Tempoh bayaran ditentukan menggunakan tarikh rekod bayaran; perbelanjaan menggunakan tarikh perbelanjaan.
                </p>
            </section>

            <section>
                <h2>2. Pecahan transaksi mengikut status dan kaedah</h2>
                <?php if ($report['payment_breakdown'] === []): ?>
                    <p>Tiada transaksi bayaran dalam tempoh ini.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Kaedah</th><th>Status</th><th>Bilangan</th><th>Jumlah direkod</th><th>Kesan pada hasil bersih</th></tr></thead>
                        <tbody>
                            <?php foreach ($report['payment_breakdown'] as $payment): ?>
                                <?php
                                $netEffect = in_array($payment['payment_status'], ['Berjaya', 'Dipulangkan'], true)
                                    ? (float) $payment['amount'] * ($payment['payment_status'] === 'Dipulangkan' ? -1 : 1)
                                    : 0.0;
                                ?>
                                <tr>
                                    <td><?= report_escape($payment['payment_method']) ?></td>
                                    <td><?= report_escape($payment['payment_status']) ?></td>
                                    <td><?= (int) $payment['transaction_count'] ?></td>
                                    <td><?= admin_report_money($payment['amount']) ?></td>
                                    <td><?= admin_report_money($netEffect) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section>
                <h2>3. Status pesanan</h2>
                <?php if ($report['summary']['order_status_counts'] === []): ?>
                    <p>Tiada pesanan untuk diringkaskan.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Status pesanan</th><th>Bilangan</th></tr></thead>
                        <tbody>
                            <?php foreach ($report['summary']['order_status_counts'] as $status => $count): ?>
                                <tr><td><?= report_escape($status) ?></td><td><?= (int) $count ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section>
                <h2>4. Butiran pesanan dan bayaran</h2>
                <?php if ($report['orders'] === []): ?>
                    <p>Tiada pesanan dalam tempoh ini.</p>
                <?php else: ?>
                    <?php foreach ($report['orders'] as $order): ?>
                        <article class="report-order">
                            <h3>Pesanan <?= report_escape($order['order_number']) ?></h3>
                            <table>
                                <tbody>
                                    <tr><th>ID pesanan / nombor</th><td><?= (int) $order['order_id'] ?> / <?= report_escape($order['order_number']) ?></td><th>ID sesi meja / meja</th><td><?= (int) $order['session_id'] ?> / <?= (int) $order['table_number'] ?></td></tr>
                                    <tr><th>Status pesanan</th><td><?= report_escape($order['order_status']) ?></td><th>Dicipta oleh</th><td><?= $order['order_created_by'] === null ? '—' : (int) $order['order_created_by'] ?> · <?= report_escape($order['order_created_by_name']) ?></td></tr>
                                    <tr><th>Status sesi meja</th><td><?= report_escape($order['session_status']) ?></td><th>Masa mula / tamat sesi</th><td><?= report_escape($order['session_started_at']) ?> / <?= report_escape($order['session_ended_at']) ?></td></tr>
                                    <tr><th>Tarikh pesanan</th><td><?= report_escape($order['order_created_at']) ?></td><th>Tarikh selesai</th><td><?= report_escape($order['completed_at']) ?></td></tr>
                                    <tr><th>ID bayaran / status</th><td><?= (int) $order['payment_id'] ?> / <?= report_escape($order['payment_status']) ?></td><th>Kaedah bayaran</th><td><?= report_escape($order['payment_method']) ?></td></tr>
                                    <tr><th>Jumlah bayaran</th><td><?= admin_report_money($order['amount']) ?></td><th>Tarikh rekod bayaran</th><td><?= report_escape($order['payment_created_at']) ?></td></tr>
                                    <tr><th>Tarikh bayaran berjaya</th><td><?= report_escape($order['paid_at']) ?></td><th>Direkod oleh</th><td><?= (int) ($order['payment_recorded_by'] ?? 0) ?> · <?= report_escape($order['recorded_by']) ?></td></tr>
                                    <tr><th>Rujukan transaksi</th><td><?= report_escape($order['transaction_reference']) ?></td><th>Resit (ID / nombor)</th><td><?= $order['receipt_id'] === null ? '—' : (int) $order['receipt_id'] ?> / <?= report_escape($order['receipt_number']) ?><br><?= report_escape($order['receipt_issued_at']) ?></td></tr>
                                    <tr><th>Nota pesanan</th><td colspan="3"><?= report_escape($order['order_notes']) ?></td></tr>
                                </tbody>
                            </table>
                            <h4>Item pesanan</h4>
                            <?php if ($order['items'] === []): ?>
                                <p>Tiada butiran item.</p>
                            <?php else: ?>
                                <table>
                                    <thead>                                                                        <tr><th>ID item pesanan / menu</th><th>Nama item (snapshot)</th><th>Harga seunit</th><th>Kuantiti</th><th>Status item</th><th>Nota item</th><th>Dicipta / dikemas kini</th><th>Jumlah baris</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <tr>
                                                <td><?= (int) $item['order_item_id'] ?> / <?= (int) $item['item_id'] ?></td>
                                                <td><?= report_escape($item['item_name_snapshot']) ?></td>
                                                <td><?= admin_report_money($item['unit_price']) ?></td>
                                                <td><?= (int) $item['quantity'] ?></td>
                                                <td><?= report_escape($item['item_status']) ?></td>
                                                <td><?= report_escape($item['notes']) ?></td>
                                                <td><?= report_escape($item['created_at']) ?><br><?= report_escape($item['updated_at']) ?></td>
                                                <td><?= admin_report_money($item['line_total']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section>
                <h2>5. Perbelanjaan mengikut kategori</h2>
                <?php if ($report['expenses_by_category'] === []): ?>
                    <p>Tiada perbelanjaan dalam tempoh ini.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Kategori</th><th>Bilangan rekod</th><th>Jumlah</th></tr></thead>
                        <tbody>
                            <?php foreach ($report['expenses_by_category'] as $category => $totals): ?>
                                <tr><td><?= report_escape($category) ?></td><td><?= (int) $totals['count'] ?></td><td><?= admin_report_money($totals['amount']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section>
                <h2>6. Butiran perbelanjaan</h2>
                <?php if ($report['expenses'] === []): ?>
                    <p>Tiada perbelanjaan dalam tempoh ini.</p>
                <?php else: ?>
                    <table>
                        <thead>                                                <tr><th>ID</th><th>Tarikh perbelanjaan</th><th>ID kategori</th><th>Kategori</th><th>Keterangan</th><th>ID direkod oleh</th><th>Direkod oleh</th><th>Tarikh rekod</th><th>Jumlah</th></tr></thead>
                        <tbody>
                            <?php foreach ($report['expenses'] as $expense): ?>
                                <tr>
                                    <td><?= (int) $expense['expense_id'] ?></td>
                                    <td><?= report_escape($expense['expense_date']) ?></td>
                                    <td><?= (int) $expense['expense_category_id'] ?></td>
                                    <td><?= report_escape($expense['category_name']) ?></td>
                                    <td><?= report_escape($expense['description']) ?></td>
                                    <td><?= (int) ($expense['expense_recorded_by'] ?? 0) ?></td>
                                    <td><?= report_escape($expense['recorded_by']) ?></td>
                                    <td><?= report_escape($expense['created_at']) ?></td>
                                    <td><?= admin_report_money($expense['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section>
                <h2>7. Trend hasil bersih dan perbelanjaan</h2>
                <table>
                    <thead><tr><th>Tarikh / bulan</th><th>Hasil bersih</th><th>Perbelanjaan</th><th>Untung / (rugi)</th></tr></thead>
                    <tbody>
                        <?php foreach ($report['timeline'] as $bucket): ?>
                            <tr>
                                <td><?= report_escape($bucket['label']) ?></td>
                                <td><?= admin_report_money($bucket['revenue']) ?></td>
                                <td><?= admin_report_money($bucket['expenses']) ?></td>
                                <td><?= admin_report_money($bucket['revenue'] - $bucket['expenses']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <footer class="report-footer">  B@Bistro</footer>
        </main>
    <?php endif; ?>
</body>
</html>
