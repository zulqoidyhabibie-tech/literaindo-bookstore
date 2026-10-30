# Panduan Admin

Untuk staf yang mengelola buku, pesanan, dan e-book sehari-hari.

## Peran pengguna

| Peran WordPress | Untuk | Bisa |
|---|---|---|
| Administrator | Pemilik/IT | Semua, termasuk pengaturan dan plugin |
| Manajer Toko (*Shop manager*) | Manajer, staf pesanan, staf katalog | Produk, stok, pesanan, laporan, konfirmasi QRIS |
| Pelanggan | Pembeli | Profil, pesanannya sendiri, unduhan e-book miliknya |

Buat akun terpisah untuk setiap staf (**Pengguna → Tambah Baru**). Jangan berbagi akun.

## Menambah buku

**Produk → Tambah Baru**

1. **Judul**: judul resmi buku.
2. **Deskripsi** (kotak besar): sinopsis lengkap, daftar isi bila diizinkan.
3. **Deskripsi singkat** (bawah): 1–3 kalimat; tampil di samping sampul.
4. **Gambar produk**: sampul depan, rasio 2:3 (misalnya 800 × 1200 px), JPG < 300 KB.
5. **Kategori produk**: pilih satu atau lebih.
6. **Tab Data Buku**: status terbit, penulis, editor, ISBN, e-ISBN, edisi, tahun,
   halaman, ukuran, bahasa.

### Buku hanya cetak (produk sederhana)

Jenis **Produk sederhana**. Isi harga, lalu di tab **Persediaan** centang
*Kelola stok* dan isi jumlah. Di tab **Pengiriman** isi berat (gram) dan kelas
pengiriman bila dipakai.

### Buku hanya e-book (produk sederhana)

Jenis **Produk sederhana**, centang **Virtual** dan **Dapat diunduh**. Unggah
file PDF di *Berkas yang dapat diunduh*, isi **Batas unduhan** (misalnya 5) dan
**Kedaluwarsa unduhan** (misalnya 30 hari).

### Buku cetak dan e-book (produk variabel)

1. Jenis **Produk variabel**.
2. Tab **Atribut**: tambah atribut `Format` dengan nilai `Cetak | E-book`,
   centang *Digunakan untuk variasi*, simpan.
3. Tab **Variasi**: *Buat variasi dari semua atribut*.
   - Variasi **Cetak**: harga, kelola stok, berat.
   - Variasi **E-book**: centang Virtual + Dapat diunduh, harga, file PDF, batas unduhan.

Format (Buku Cetak / E-book) di katalog terisi otomatis dari pengaturan ini.

## Halaman buku untuk pengajuan ISBN

1. Tambah buku seperti di atas, isi **Status terbit = Segera terbit**. ISBN boleh kosong.
2. Isi sampul (boleh desain sementara), deskripsi, penulis, tahun, dan perkiraan halaman.
3. **Terbitkan** produk. Harga boleh diisi; buku tetap tidak bisa dibeli selama
   statusnya "Segera terbit".
4. Salin URL produk (contoh `https://domainanda.id/produk/judul-buku/`) dan
   gunakan sebagai tautan publikasi di formulir pengajuan ISBN.
5. Setelah ISBN terbit: isi kolom ISBN, ubah status ke **Sudah terbit**, isi
   stok/file e-book, lalu **Perbarui**. URL tetap sama.

Jangan mengubah slug (URL) buku setelah tautannya dikirim.

## Memproses pesanan QRIS

Email "Bukti bayar QRIS untuk pesanan #…" masuk setiap kali pelanggan mengunggah bukti.

1. **WooCommerce → Pesanan**, buka pesanan berstatus **Ditahan**.
2. Kotak **Pembayaran QRIS** di kanan menampilkan total dan bukti bayar.
3. Cek mutasi rekening BTN (aplikasi/internet banking): pastikan ada dana masuk
   dengan **nominal sama** pada waktu yang sesuai. Jangan hanya mengandalkan
   tangkapan layar.
4. Bila cocok, tekan **Konfirmasi pembayaran**. Pesanan menjadi **Diproses**
   (atau **Selesai** bila isinya e-book saja) dan e-book langsung bisa diunduh pelanggan.
5. Bila tidak cocok, tambahkan catatan untuk pelanggan (kotak *Catatan pesanan*,
   pilih "Catatan untuk pelanggan") atau hubungi pelanggan.
6. Pesanan yang tidak dibayar melewati batas waktu: ubah status ke **Dibatalkan**
   (stok kembali otomatis).

## Mengirim buku cetak

1. Kemas buku, kirim lewat kurir.
2. Di pesanan, tambahkan **Catatan untuk pelanggan**:
   `Pesanan dikirim via [kurir], nomor resi [nomor].` Pelanggan menerima email.
3. Ubah status ke **Selesai** setelah buku diterima (atau setelah resi dikirim, sesuai kebijakan).

## E-book

- File e-book disimpan WooCommerce di folder terlindungi; pelanggan mengunduh
  lewat **Akun → Unduhan**.
- Untuk memberi ulang akses (file rusak, batas unduhan habis): buka pesanan,
  bagian **Izin unduhan produk**, tambah ulang atau reset hitungan unduhan.
- Untuk mencabut akses (misalnya refund): hapus izin unduhan di pesanan tersebut.

## Refund

1. Buka pesanan → **Refund**, isi jumlah dan alasan.
2. Pilih *Refund manual* (QRIS tidak mendukung refund otomatis), lalu transfer
   dana ke pelanggan dari rekening BTN.
3. Untuk e-book, hapus izin unduhan.

## Laporan

**WooCommerce → Analitik**: penjualan per periode, produk terlaris, dan stok.
**Produk → filter "Stok menipis"** untuk melihat buku yang perlu dicetak ulang.

## Rutinitas

| Kapan | Tugas |
|---|---|
| Setiap hari | Cek pesanan *Ditahan* dan konfirmasi pembayaran; kirim pesanan *Diproses* |
| Setiap minggu | Cek stok menipis; batalkan pesanan tak dibayar |
| Setiap bulan | Perbarui plugin/tema di staging lalu produksi; cek backup bisa dipulihkan |
