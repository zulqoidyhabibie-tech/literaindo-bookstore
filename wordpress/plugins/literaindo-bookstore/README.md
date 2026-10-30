# Literaindo Bookstore (plugin)

Fungsi khusus toko: metadata buku, status "Segera Terbit", format cetak/e-book,
dan pembayaran QRIS BTN dengan unggah bukti bayar. Membutuhkan WooCommerce.
Kompatibel dengan HPOS dan checkout berbasis blok.

## Pemasangan

1. Kompres folder `literaindo-bookstore` menjadi zip, lalu **Plugin → Tambah Baru → Unggah Plugin**.
2. Aktifkan. Saat aktivasi plugin:
   - membuat format **Buku Cetak** (`/format/cetak/`) dan **E-book** (`/format/ebook/`);
   - mengatur permalink produk ke `/produk/` dan kategori ke `/kategori/` bila masih bawaan;
   - membuat folder privat `wp-content/uploads/literaindo-proofs/` untuk bukti bayar.
3. **WooCommerce → Pengaturan → Produk → Literaindo**: isi nama penerbit dan kota terbit.

## Menambah buku

Di editor produk, buka tab **Data Buku**:

| Kolom | Keterangan |
|---|---|
| Status terbit | **Segera terbit**: halaman tampil publik tetapi belum bisa dibeli. Pakai ini untuk buku yang tautannya dibutuhkan saat mengajukan ISBN. Ubah ke **Sudah terbit** saat buku siap dijual. |
| Penulis | Beberapa penulis dipisah koma. Tampil di kartu buku dan bisa dicari. |
| ISBN / e-ISBN | Boleh kosong selama proses pengajuan (tampil "Dalam proses pengajuan"). Angka kontrol diperiksa saat disimpan. Bisa dicari dengan atau tanpa tanda hubung. |
| Edisi, tahun, halaman, ukuran, bahasa | Tampil di tab "Informasi Buku". |

**Format** diisi otomatis: produk/variasi yang *Virtual* + *Dapat diunduh* menjadi
E-book, selain itu Buku Cetak. Untuk judul yang tersedia dalam dua format, buat
produk **Variabel** dengan atribut "Format" (Cetak, E-book) dan atur tiap variasi.

Tautan publikasi untuk ISBN adalah URL halaman produk, misalnya
`https://domainanda.id/produk/metodologi-penelitian-pendidikan/`. Jangan ubah
slug setelah tautan dikirim.

Halaman buku juga menyertakan data terstruktur schema.org `Book` (judul,
penulis, penerbit, ISBN, tahun, halaman) agar mudah dikenali mesin pencari.

## Pembayaran QRIS BTN

1. Unggah gambar QRIS BTN di **Media → Tambah Baru**, salin URL file-nya.
2. **WooCommerce → Pengaturan → Pembayaran → QRIS BTN (konfirmasi manual)**:
   aktifkan, tempel URL gambar, isi nama merchant (nama CV), batas waktu bayar.
   Metode ini baru muncul di checkout setelah URL gambar QRIS diisi.

Alur pesanan:

1. Pelanggan checkout dengan QRIS → pesanan berstatus **Ditahan**, stok dicadangkan.
2. Halaman terima kasih dan email pesanan menampilkan QRIS, total, nomor pesanan, dan tenggat.
3. Pelanggan membayar lalu mengunggah bukti (JPG/PNG/PDF, maks. 3 MB) dari halaman terima
   kasih atau **Akun → Pesanan**. Admin menerima email pemberitahuan.
4. Admin membuka pesanan; kotak **Pembayaran QRIS** di samping menampilkan bukti.
   Cocokkan dengan mutasi rekening BTN, lalu tekan **Konfirmasi pembayaran**.
5. Pesanan menjadi **Diproses** (atau **Selesai** jika isinya e-book saja) dan
   akses unduhan e-book terbuka. Pesanan yang tidak dibayar dibatalkan manual.

QRIS statis tidak memberi notifikasi otomatis, jadi verifikasi selalu dari
mutasi rekening, bukan dari tangkapan layar saja.

### Server Nginx

Folder bukti dilindungi `.htaccess` (Apache/LiteSpeed). Di Nginx tambahkan:

```nginx
location ^~ /wp-content/uploads/literaindo-proofs/ { deny all; }
```

Nama file juga acak, dan admin membuka bukti lewat `admin-post.php` dengan cek hak akses.

## Persiapan Toko

Menu **WooCommerce → Persiapan Toko** berisi daftar periksa sebelum peluncuran:
bahasa situs, format Rupiah, permalink `/produk/`, QRIS, folder bukti bayar,
keamanan unduhan e-book, zona pengiriman, dan halaman kebijakan. Beberapa butir
punya tombol untuk langsung menerapkannya.

### E-book

Tombol **Terapkan pengaturan e-book** mengatur WooCommerce agar:

- file diunduh lewat mode *Paksa unduh* (URL asli file tidak terlihat);
- unduhan hanya untuk pelanggan yang login, dan baru terbuka setelah pembayaran dikonfirmasi;
- tidak ada pengalihan ke URL file asli bila unduhan gagal;
- nama file e-book diberi kode acak saat diunggah.

File e-book diunggah lewat kolom *Berkas yang dapat diunduh* di produk/variasi
(tersimpan di folder `woocommerce_uploads` yang dilindungi WooCommerce). Atur
*Batas unduhan* dan *Kedaluwarsa unduhan* per produk sesuai Ketentuan E-book,
misalnya 5 kali dalam 30 hari.

Karena unduhan memerlukan login, checkout yang berisi e-book otomatis mewajibkan
pembuatan akun. Halaman produk e-book menampilkan keterangan cara menerima file.

### Ongkos kirim

Ongkir hanya dihitung untuk buku cetak: e-book dibuat sebagai produk *Virtual*,
dan WooCommerce tidak meminta alamat atau ongkir untuk produk virtual. Untuk MVP,
buat zona pengiriman "Indonesia" dengan *Tarif Tetap* (atau tarif per kelas
pengiriman). Integrasi kurir otomatis masuk tahap lanjutan.

### Halaman bantuan dan kebijakan

Tombol **Buat draf yang belum ada** membuat halaman berikut sebagai **draf**
berisi teks contoh berbahasa Indonesia:

| Halaman | URL |
|---|---|
| Tentang Kami | `/tentang-kami/` |
| Bantuan & FAQ | `/bantuan/` |
| Syarat Transaksi | `/syarat-transaksi/` (dihubungkan ke pengaturan syarat & ketentuan WooCommerce) |
| Pengiriman, Retur, dan Refund | `/pengiriman-retur-refund/` |
| Ketentuan Penggunaan E-book | `/ketentuan-ebook/` |
| Kebijakan Privasi | `/kebijakan-privasi/` (dijadikan halaman privasi WordPress) |

Halaman yang sudah ada tidak diubah. Jika menu footer *Bantuan & Kebijakan*
(dari tema literaindo-child) masih kosong, menu itu ikut dibuat. Lengkapi bagian
dalam `[kurung siku]`, tinjau isi kebijakan (sebaiknya bersama penasihat hukum),
lalu terbitkan.

## Pengembangan

```bash
php tests/test-functions.php   # uji ISBN dan format rupiah
```

Kontrak dengan child theme: filter `literaindo_book_author` (string) dan
`literaindo_book_details` (daftar `label`/`value`).
