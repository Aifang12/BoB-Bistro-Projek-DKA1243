# Persediaan backend menu

1. Import dump `bistro_db.sql` ke MySQL/MariaDB dengan nama pangkalan data `bistro_db`.
2. Jalankan `menu_seed.sql` sekali selepas import. Skrip ini menggantikan empat item contoh dengan 16 item yang digunakan pada laman dan selamat dijalankan semula.
3. Tetapan sambungan lalai dalam `config.php` sesuai untuk WAMP tempatan (`127.0.0.1`, pengguna `root`, kata laluan kosong, pangkalan data `bistro_db`). Untuk pelayan lain, tetapkan `BISTRO_DB_HOST`, `BISTRO_DB_USER`, `BISTRO_DB_PASSWORD` dan `BISTRO_DB_NAME`; jangan gunakan akaun `root` tanpa kata laluan pada pelayan produksi.
4. Buka `/B@B/admin/setup.php` untuk mendaftar Admin pertama. Borang itu ditutup selepas akaun Admin berjaya dibuat. Log masuk seterusnya melalui `/B@B/admin/login.php`.

Laman utama, menu dan butiran produk membaca data tersedia melalui `api/menu.php`. Panel kakitangan dilindungi log masuk dan menggunakan role dalam jadual `roles`:

- `Admin`: urus menu (tambah, kemas kini harga/kategori/ketersediaan), akaun kakitangan, pesanan, bayaran, meja, perbelanjaan, laporan bulanan dan graf jualan.
- `Kitchen`: lihat menu tersedia daripada pangkalan data, pesanan aktif beserta item/nota, dan kemas kini status penyediaan sehingga serahan.
- `Cashier`: semak sesi meja dan bil aktif, sahkan bayaran, cetak resit, tutup pesanan yang telah diserahkan dan dibayar, serta tandakan meja yang telah dibersihkan sebagai kosong.

Admin boleh mencipta/menyahaktifkan akaun kakitangan; kata laluan minimum 12 aksara disimpan dalam bentuk hash. Tindakan pengurusan direkodkan dalam `audit_logs`. Gambar menu pilihan disimpan dalam `images/foods/uploads/`. Selepas pesanan selesai, meja menunggu pembersihan; Admin atau Cashier boleh tandakan meja sebagai kosong untuk sesi seterusnya.

Bakul disimpan sementara dalam storan pelayar. Semasa checkout, pelayan mengesahkan semula item, harga semasa dan nombor meja sebelum menyimpan pesanan, item pesanan, sesi meja, sejarah status dan rekod bayaran. FPX dan Online Banking hanya direkod sebagai `Menunggu Pengesahan`; laman ini tidak memproses transaksi atau menghubungi payment gateway.

Bayaran tunai bermula sebagai `Belum Dibayar`; juruwang merekod `Berjaya` selepas menerima bayaran. Bayaran FPX/Online Banking perlu disahkan secara manual selepas semakan transaksi luar sistem. Setiap bayaran berjaya akan menerima satu nombor resit unik dan resit boleh dicetak daripada panel juruwang. Pesanan hanya boleh ditutup selepas statusnya `Diserahkan` dan bayaran `Berjaya`; sesi meja ditutup automatik apabila semua pesanan dalam sesi selesai atau dibatalkan.

Laporan Admin menyokong tempoh mingguan (Isnin hingga Ahad), bulanan dan tahunan. Hasil bersih ialah jumlah bayaran `Berjaya` ditolak bayaran `Dipulangkan`; `Belum Dibayar`, `Menunggu Pengesahan` dan `Gagal` tidak dikira sebagai hasil. Perbelanjaan ditapis mengikut tarikh perbelanjaan, dan untung/rugi bersih ialah hasil bersih ditolak perbelanjaan. Graf dan laporan terperinci menggunakan tempoh yang sama. Laporan terperinci memaparkan ringkasan, pecahan kaedah/status, pesanan dan item, perbelanjaan, serta trend harian atau bulanan. Laporan boleh dicetak atau disimpan sebagai PDF; CSV memuat turun data terperinci. Cetakan menggunakan Arial 11 pt dengan jarak baris 1.5.

Untuk menguji paparan kewangan, jalankan `php database/report_test_seed.php` sekali daripada direktori projek. Skrip ini memasukkan contoh transaksi dan perbelanjaan bulan September 2026, termasuk bayaran berjaya, dipulangkan dan menunggu pengesahan. Semua rekod ditandakan jelas dengan `[DATA UJIAN LAPORAN]` pada nota/rujukan/keterangan, skrip selamat dijalankan semula tanpa menggandakan rekod sampel, menggunakan meja 5 tanpa mengubah status meja, dan tidak memadam data. Rekod contoh kekal dalam pangkalan data dan akan termasuk dalam jumlah laporan; asingkan atau padamkan rekod bertanda secara manual sebelum menggunakan data kewangan untuk operasi sebenar.
