# BoB-Bistro-Projek-DKA1243 | B@Bistro

B@Bistro ialah sebuah prototaip sistem tempahan makanan berasaskan web yang dibangunkan bagi kursus **DKA1243 – Human Computer Interaction (HCI)**.

Sistem ini direka untuk memudahkan pelanggan membuat tempahan makanan secara digital melalui peranti mudah alih dengan menggunakan konsep **QR Code meja**. Pelanggan boleh melihat menu, memilih makanan, menambah item ke dalam bakul, memilih kaedah pembayaran dan melihat status tempahan.

---

## 1. Objektif Projek

Projek ini dibangunkan untuk:

- Menghasilkan antaramuka tempahan makanan yang mudah dan mesra pengguna.
- Mengaplikasikan prinsip Human Computer Interaction (HCI) dalam pembangunan antaramuka.
- Memudahkan pelanggan membuat tempahan tanpa perlu menunggu pelayan.
- Mengenal pasti meja pelanggan melalui QR Code.
- Menyediakan proses tempahan yang ringkas daripada pemilihan makanan sehingga status pesanan.
- Menghasilkan prototaip yang hampir dengan mockup yang telah direka dan dibincangkan.

---

## 2. Fungsi Utama

### 2.1 Halaman Utama

- Paparan banner dan pengenalan B@Bistro.
- Paparan kategori makanan.
- Paparan menu popular.
- Navigasi ke halaman menu dan bakul.

### 2.2 Menu

- Paparan senarai makanan.
- Fungsi carian makanan.
- Penapisan makanan mengikut kategori.
- Kategori boleh discroll secara horizontal pada skrin kecil.
- Paparan mesej sekiranya tiada makanan dijumpai.
- Pengguna boleh memilih produk untuk melihat maklumat lanjut.

### 2.3 Butiran Produk

- Paparan gambar makanan.
- Nama dan kategori makanan.
- Rating makanan.
- Harga makanan.
- Penerangan makanan.
- Kawalan kuantiti.
- Pengiraan jumlah harga secara automatik.
- Fungsi menambah produk ke dalam bakul.

### 2.4 Bakul

- Paparan semua makanan yang dipilih.
- Paparan kuantiti setiap item.
- Pengiraan jumlah harga.
- Fungsi mengubah kuantiti.
- Fungsi membuang item daripada bakul.
- Jumlah pesanan dikemas kini secara automatik.

### 2.5 QR Code Meja

Sistem menggunakan konsep QR Code meja untuk mengenal pasti lokasi pelanggan.

Contoh aliran:

```text
Pelanggan duduk di Meja 2
        ↓
Imbas QR Code Meja 2
        ↓
Sistem mengenal pasti pelanggan sebagai Meja 2