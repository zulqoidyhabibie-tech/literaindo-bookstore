# Panduan Instalasi

Panduan memasang Literaindo Press Bookstore di hosting yang sudah punya domain.
Lakukan dulu di **staging** (subdomain uji, misalnya `staging.domainanda.id`)
bila hosting menyediakannya, lalu ulangi di produksi.

Perkiraan waktu: 1–2 jam.

## 0. Yang perlu disiapkan

- Akses cPanel/panel hosting dan akun admin WordPress.
- File `literaindo-child.zip` dan `literaindo-bookstore.zip` (buat dengan
  `scripts/build-zips.sh`, atau unduh dari folder proyek).
- Gambar QRIS BTN (PNG/JPG), nama merchant QRIS (nama CV).
- Logo Literaindo Press (PNG transparan, lebar ±400 px) bila ada.
- SSL aktif (alamat diawali `https://`). Biasanya bisa diaktifkan gratis lewat
  menu SSL/TLS atau "Let's Encrypt" di panel hosting.

## 1. WordPress

1. Pasang WordPress lewat installer hosting (Softaculous/WordPress Toolkit) bila belum ada.
2. **Pengaturan → Umum**
   - Judul situs: `Literaindo Press Bookstore`
   - Slogan: `Buku Berkualitas. Pengetahuan Tanpa Batas.`
   - Bahasa situs: **Bahasa Indonesia**
   - Zona waktu: **Jakarta**
3. **Pengaturan → Permalink**: pilih **Nama tulisan**.

## 2. WooCommerce

1. **Plugin → Tambah Baru**, cari **WooCommerce**, pasang dan aktifkan.
2. Ikuti wizard awal: negara **Indonesia**, mata uang **Rupiah**. Lewati
   tawaran plugin tambahan yang tidak diperlukan.
3. Pastikan halaman Toko, Keranjang, Checkout, dan Akun Saya sudah dibuat
   (WooCommerce → Status → Alat → *Buat halaman*).

## 3. Tema

1. **Tampilan → Tema → Tambah Baru**, cari **Storefront**, pasang (tidak perlu diaktifkan).
2. **Tambah Baru → Unggah Tema**, pilih `literaindo-child.zip`, pasang, lalu **Aktifkan**.
3. **Tampilan → Sesuaikan → Identitas Situs**: unggah logo bila ada.

## 4. Plugin Literaindo Bookstore

1. **Plugin → Tambah Baru → Unggah Plugin**, pilih `literaindo-bookstore.zip`, pasang, **Aktifkan**.
   Saat aktif, plugin mengatur URL buku ke `/produk/`, kategori ke `/kategori/`,
   membuat format *Buku Cetak* dan *E-book*, dan menyiapkan folder privat bukti bayar.
2. **WooCommerce → Pengaturan → Produk → Literaindo**: isi nama penerbit dan kota terbit.
3. Buka **Pengaturan → Permalink** dan tekan **Simpan** sekali (menyegarkan aturan URL).

## 5. WooCommerce → Persiapan Toko

Buka menu ini dan selesaikan semua butir sampai bertanda ✓:

| Butir | Cara menyelesaikan |
|---|---|
| Bahasa situs | Langkah 1 |
| Format Rupiah | Tekan **Terapkan format Rupiah** |
| URL `/produk/` | Otomatis dari plugin; bila belum, Pengaturan → Permalink → dasar produk kustom `/produk/` |
| QRIS BTN | Lihat langkah 6 |
| Folder bukti bayar | Otomatis. Server **Nginx**: minta hosting menambah aturan di README plugin |
| Unduhan e-book aman | Tekan **Terapkan pengaturan e-book** |
| Ongkos kirim | Lihat langkah 7 |
| Halaman kebijakan | Tekan **Buat draf yang belum ada**, lalu lengkapi dan terbitkan (langkah 8) |

## 6. Pembayaran QRIS BTN

1. **Media → Tambah Baru**: unggah gambar QRIS BTN. Buka file tersebut dan salin **URL File**.
2. **WooCommerce → Pengaturan → Pembayaran → QRIS BTN (konfirmasi manual)**:
   - Aktifkan.
   - Tempel URL gambar QRIS.
   - Nama merchant: nama yang muncul saat QRIS dipindai (nama CV).
   - Batas waktu bayar: misalnya 24 jam.
3. Nonaktifkan metode pembayaran lain yang tidak dipakai (Transfer Bank, COD, dsb.).

## 7. Ongkos kirim buku cetak

1. **WooCommerce → Pengaturan → Pengiriman → Tambah zona**.
2. Nama zona: `Indonesia`, wilayah: `Indonesia`.
3. Tambah metode **Tarif Tetap** (misalnya Rp15.000 per pesanan), atau gunakan
   **kelas pengiriman** untuk membedakan buku tipis dan tebal.
4. E-book tidak terkena ongkir karena produk Virtual.

## 8. Halaman, menu, dan beranda

1. **Halaman**: lengkapi draf Tentang Kami, Bantuan & FAQ, Syarat Transaksi,
   Pengiriman/Retur/Refund, Ketentuan E-book, Kebijakan Privasi. Ganti semua
   isian `[dalam kurung siku]`, tinjau, lalu **Terbitkan**.
2. **WooCommerce → Pengaturan → Produk**: halaman toko = halaman "Toko";
   ubah slug halaman Toko menjadi `buku` agar URL-nya `/buku/`.
   Ubah slug Keranjang menjadi `keranjang` dan Akun Saya menjadi `akun`.
3. **Tampilan → Menu**, buat menu utama (lokasi *Primary Menu*):
   Beranda, Semua Buku (`/buku/`), Buku Cetak (`/format/cetak/`),
   E-book (`/format/ebook/`), Tentang Kami, Bantuan.
   Menu footer *Bantuan & Kebijakan* sudah dibuat otomatis oleh plugin.
4. **Pengaturan → Membaca**: "Beranda menampilkan" = **Halaman statis**, pilih
   halaman apa saja (misalnya "Beranda"). Tampilan beranda tetap memakai desain tema.
5. **Tampilan → Sesuaikan → Beranda Literaindo**: ubah teks banner bila perlu dan
   pilih halaman Tentang Kami.
6. **Produk → Kategori**: buat kategori Pendidikan; Matematika dan Statistika;
   Metodologi Penelitian; Pendidikan Digital. Atur urutannya; empat teratas tampil
   di beranda.

## 9. Keamanan dasar

- Pasang plugin keamanan tepercaya untuk **2FA admin** dan **batas percobaan login**
  (misalnya Wordfence atau Solid Security).
- Pakai kata sandi kuat; jangan gunakan nama pengguna `admin`.
- Hapus tema dan plugin yang tidak dipakai.
- Atur backup otomatis (lihat `backup-recovery.md`).
- Atur email transaksional lewat SMTP (misalnya plugin WP Mail SMTP dengan
  email domain Anda) agar email pesanan tidak masuk spam.

## 10. Uji dan luncurkan

Jalankan `testing/functional-tests.md`, lalu centang `testing/launch-checklist.md`.
Setelah semua lulus, tambahkan buku asli (lihat `admin-guide.md`).
