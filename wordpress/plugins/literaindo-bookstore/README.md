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

## Pengembangan

```bash
php tests/test-functions.php   # uji ISBN dan format rupiah
```

Kontrak dengan child theme: filter `literaindo_book_author` (string) dan
`literaindo_book_details` (daftar `label`/`value`).
