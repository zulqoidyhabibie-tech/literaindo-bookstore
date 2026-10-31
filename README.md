# Literaindo Press

Website resmi Literaindo Press (https://bookstore.literaindo.com):

- **Katalog publik**: daftar buku dengan filter, halaman detail tiap buku (link tetap untuk pengajuan ISBN), pemesanan via WhatsApp dan QRIS BTN.
- **Akun penulis (pengaju)**: daftar, ajukan naskah, unggah berkas, bayar biaya awal dan pelunasan via QRIS, pantau status, terima email setiap perubahan status.
- **Panel admin**: verifikasi pembayaran, minta revisi, tetapkan jumlah cetak dan tagihan pelunasan (sekaligus membuat halaman buku), simpan ISBN, input resi, kelola katalog, pengguna, biaya awal, dan gambar QRIS.

Dibuat dengan PHP biasa (tanpa framework, tanpa WordPress) dan MySQL.
Butuh PHP 8.1 atau lebih baru.

## Alur pengajuan

```
Draf → Menunggu biaya awal (Rp250.000) → Verifikasi → Ditinjau redaksi ⇄ Perlu revisi
     → [admin tetapkan tagihan + halaman buku dibuat] Proses ISBN
     → [admin isi ISBN, buku jadi "Sudah Terbit"] Menunggu pelunasan → Verifikasi
     → Lunas, antre cetak → [admin isi resi] Selesai
```

Admin bisa menolak pengajuan di tahap mana pun sebelum selesai.

## Struktur

```
app/                     isi website (disalin apa adanya ke cabang `deploy`)
  index.php              pintu masuk + daftar rute
  .htaccess, .user.ini   aturan server dan batas unggah (32 MB)
  lib/                   inti (database, sesi, CSRF), tampilan, unggahan, skema tabel
  halaman/               publik, akun, pengaju, admin, pasang (pemasangan pertama)
  konten/*.html          halaman Tentang Kami, Layanan, Syarat & Ketentuan, dll.
  data/situs.json        identitas penerbit, kontak, menu, warna utama
  data/buku-awal.json    buku yang dimasukkan saat pemasangan pertama
  assets/                CSS, JavaScript, logo, QRIS, sampul
.github/workflows/deploy.yml   periksa PHP lalu kirim app/ ke cabang `deploy`
```

Data rahasia dan berkas unggahan **tidak** disimpan di folder website, tetapi di
folder `literaindo-data` satu tingkat di atas `public_html`
(`/home/uXXX/domains/literaindo.com/literaindo-data`): `config.php` dan `berkas/`.
Folder ini tidak tersentuh saat deploy. Bisa diganti dengan variabel lingkungan
`LITERAINDO_DATA`.

## Menambah buku

Masuk sebagai admin → **Katalog → Tambah Buku**. Buku dari pengajuan penulis
dibuat otomatis saat admin menetapkan tagihan pelunasan.

## Mencoba di komputer

```
export LITERAINDO_DATA=/tmp/literaindo-data LITERAINDO_SQLITE=/tmp/literaindo-data/db.sqlite
php -S 127.0.0.1:8000 -t app router.php
```

Buka http://127.0.0.1:8000/pasang, buat akun admin, lalu coba alurnya.

## Pemasangan di Hostinger

1. hPanel → **Database → Manajemen**: buat database MySQL, catat nama database, pengguna, dan kata sandinya.
2. hPanel → **Lanjutan → Konfigurasi PHP**: pilih PHP 8.1 atau lebih baru.
3. Deploy cabang `deploy` (lihat di bawah), lalu segera buka `https://bookstore.literaindo.com/pasang` dan isi formulirnya. Setelah itu halaman `/pasang` tertutup otomatis.

Pengaturan Git:

Hostinger hPanel → Websites → Kelola → Lanjutan → **GIT**:

- Repositori: `git@github.com:zulqoidyhabibie-tech/literaindo-bookstore.git`
- Cabang: `deploy`
- Direktori: `bookstore` (folder subdomain bookstore.literaindo.com di dalam `public_html`, harus kosong saat pertama dipasang)

Karena repo privat, salin **SSH key** dari halaman GIT hPanel ke GitHub →
Settings → Deploy keys (cukup akses baca). Untuk pembaruan otomatis, aktifkan
**Auto Deployment** di hPanel dan simpan URL webhook-nya sebagai secret
`HOSTINGER_WEBHOOK` di GitHub → Settings → Secrets and variables → Actions.
