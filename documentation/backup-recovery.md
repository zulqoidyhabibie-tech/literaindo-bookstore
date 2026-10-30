# Backup, Pemulihan, dan Pembaruan

## Backup

| Apa | Seberapa sering | Disimpan |
|---|---|---|
| Database (produk, pesanan, pelanggan) | Harian | Minimal 14 hari terakhir |
| File `wp-content` (unggahan, e-book, bukti bayar) | Mingguan, dan setelah unggah e-book baru | Minimal 4 minggu terakhir |

- Gunakan fitur backup hosting (JetBackup/cPanel Backup) **dan** salinan di luar
  hosting, misalnya plugin UpdraftPlus ke Google Drive.
- Folder penting: `wp-content/uploads/woocommerce_uploads/` (file e-book) dan
  `wp-content/uploads/literaindo-proofs/` (bukti bayar).
- Kode tema dan plugin tersimpan di repositori GitHub ini, jadi bisa dipasang ulang dari sana.
- Jangan simpan `wp-config.php` atau kata sandi di repositori.

## Uji pemulihan (setiap 3 bulan)

1. Ambil backup terbaru.
2. Pulihkan ke staging (bukan produksi).
3. Periksa: beranda tampil, produk dan pesanan lengkap, e-book bisa diunduh oleh
   akun uji, bukti bayar bisa dibuka admin.
4. Catat tanggal dan hasilnya.

## Pemulihan darurat produksi

1. Aktifkan mode pemeliharaan (plugin maintenance atau fitur hosting).
2. Pulihkan database dan file dari backup terakhir yang sehat.
3. Bandingkan pesanan setelah waktu backup dengan email notifikasi pesanan dan
   mutasi BTN; masukkan ulang pesanan yang hilang secara manual.
4. Matikan mode pemeliharaan, lakukan satu transaksi uji.

## Pembaruan

1. Backup dulu.
2. Perbarui WordPress, WooCommerce, Storefront, dan plugin lain di **staging**.
3. Jalankan uji singkat: beranda, halaman buku, checkout QRIS, unggah bukti,
   konfirmasi, unduh e-book.
4. Bila lancar, ulangi di produksi.
5. Tema `literaindo-child` dan plugin `literaindo-bookstore` diperbarui dengan
   mengunggah zip versi baru (Plugin/Tema → Unggah → *Ganti yang terpasang*).
