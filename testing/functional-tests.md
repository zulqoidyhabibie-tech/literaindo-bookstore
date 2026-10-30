# Uji Fungsional

Jalankan di staging setelah instalasi, dan setelah setiap pembaruan besar.
Isi kolom Hasil dengan ✓ / ✗ dan catatan.

Siapkan data uji: 1 buku cetak (stok 3), 1 e-book (PDF kecil), 1 buku variabel
cetak + e-book, 1 buku berstatus *Segera terbit* tanpa ISBN, dan gambar QRIS.

## A. Tampilan dan katalog

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A1 | Buka beranda di desktop | Banner, pencarian, kategori unggulan, buku terbaru, terlaris, Tentang tampil; warna navy/ivory/maroon | |
| A2 | Buka beranda di ponsel | Tidak ada geser ke samping; menu ringkas, tombol mudah ditekan | |
| A3 | Cari judul sebagian | Buku muncul | |
| A4 | Cari nama penulis | Buku penulis itu muncul | |
| A5 | Cari ISBN dengan dan tanpa tanda hubung | Buku yang sama muncul | |
| A6 | Klik kategori di beranda | Hanya buku kategori itu | |
| A7 | Buka `/format/ebook/` dan `/format/cetak/` | Daftar buku sesuai format | |
| A8 | Urutkan katalog berdasarkan harga | Urutan benar | |
| A9 | Cari kata yang tidak ada | Pesan "tidak ditemukan" yang jelas | |

## B. Halaman buku

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| B1 | Buka buku cetak | Penulis di bawah judul; tab *Informasi Buku* pertama berisi penerbit, ISBN, tahun, halaman, ukuran, format | |
| B2 | Buka buku *Segera terbit* | Badge/teks "segera terbit", ISBN "Dalam proses pengajuan", tidak ada tombol beli | |
| B3 | Buka buku variabel, klik beli tanpa memilih format | Diminta memilih format | |
| B4 | Pilih format e-book | Harga e-book tampil; ada keterangan cara menerima e-book | |
| B5 | Cek data terstruktur di Google Rich Results Test | Terdeteksi Product/Book dengan ISBN dan penulis | |

## C. Keranjang dan checkout

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| C1 | Tambah buku cetak, ubah jumlah, hapus | Total berubah benar | |
| C2 | Checkout hanya e-book | Tidak ada alamat/ongkir; wajib membuat akun | |
| C3 | Checkout hanya buku cetak | Alamat dan ongkir muncul; total = subtotal + ongkir | |
| C4 | Checkout cetak + e-book | Ongkir hanya untuk buku cetak; wajib akun | |
| C5 | Pilih QRIS, buat pesanan | Halaman terima kasih: QRIS, total, nomor pesanan, tenggat, formulir bukti | |
| C6 | Cek email pelanggan | Ada QRIS, total, dan tautan unggah bukti | |
| C7 | Cek stok buku cetak | Berkurang sesuai jumlah | |

## D. Bukti bayar dan konfirmasi

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| D1 | Unggah file .txt atau gambar > 3 MB | Ditolak dengan pesan jelas | |
| D2 | Unggah JPG bukti | Pesan "bukti diterima"; admin menerima email; catatan di pesanan | |
| D3 | Buka URL langsung `/wp-content/uploads/literaindo-proofs/` | Akses ditolak (403) | |
| D4 | Login sebagai Pelanggan lain, coba unggah ke pesanan orang lain | Ditolak | |
| D5 | Admin buka pesanan | Kotak Pembayaran QRIS menampilkan bukti | |
| D6 | Sebelum konfirmasi, pelanggan buka Akun → Unduhan | E-book **belum** tersedia | |
| D7 | Admin tekan Konfirmasi pembayaran | Status Diproses/Selesai; catatan nama admin | |
| D8 | Pelanggan buka Akun → Unduhan, unduh | File terunduh; URL bukan alamat file asli | |
| D9 | Keluar akun, buka tautan unduhan yang sama | Diminta login / ditolak | |
| D10 | Unduh melewati batas unduhan | Ditolak | |

## E. Admin

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| E1 | Tambah, edit, arsipkan (draf) produk | Berhasil; produk draf tidak tampil di katalog | |
| E2 | Isi ISBN salah | Muncul peringatan angka kontrol | |
| E3 | Login sebagai Manajer Toko | Bisa kelola produk dan pesanan, tidak bisa kelola plugin | |
| E4 | Refund manual pesanan e-book, hapus izin unduhan | Pelanggan tidak bisa mengunduh lagi | |
| E5 | WooCommerce → Persiapan Toko | Semua butir ✓ | |
| E6 | WooCommerce → Status | Tidak ada peringatan merah; HPOS kompatibel | |

## F. Kinerja dan aksesibilitas

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| F1 | PageSpeed Insights beranda dan halaman buku (ponsel) | LCP ≤ 2,5 dtk, CLS ≤ 0,1 (target) | |
| F2 | Navigasi dengan tombol Tab saja | Fokus terlihat (garis maroon), semua tombol bisa dicapai | |
| F3 | Semua sampul punya teks alternatif | Ya | |
