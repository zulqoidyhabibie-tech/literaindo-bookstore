# Literaindo Press

Website katalog resmi Literaindo Press: daftar buku dengan filter, halaman detail
untuk setiap buku (link tetap untuk pengajuan ISBN), serta pemesanan melalui
WhatsApp dan pembayaran QRIS BTN.

Website ini statis (HTML, CSS, sedikit JavaScript) tanpa database, sehingga
ringan, aman, dan murah di hosting.

## Struktur

```
site/
  data/situs.json   identitas penerbit, kontak, menu, warna utama
  data/buku.json    daftar buku (satu objek per buku)
  konten/*.html     halaman Tentang Kami, Layanan, Cara Pemesanan, dll.
  assets/           CSS, JavaScript, logo, gambar QRIS, sampul buku
  statis/           file yang disalin apa adanya (.htaccess)
  build.mjs         pembuat website → folder dist/
.github/workflows/deploy.yml   bangun otomatis ke cabang `deploy`
```

## Menambah atau mengubah buku

1. Simpan sampul (JPG/PNG/WebP, rasio 2:3, lebar ±600 px) di `site/assets/sampul/`.
2. Tambahkan satu entri di `site/data/buku.json`:

| Kolom | Isi |
| --- | --- |
| `slug` | bagian link, huruf kecil dan tanda minus. Jangan diubah setelah dikirim ke ISBN. |
| `judul`, `subjudul` | judul buku |
| `penulis` | daftar nama, misalnya `["Dr. A", "B, M.Pd."]` |
| `editor`, `kategori`, `tahun`, `edisi`, `halaman`, `ukuran`, `bahasa` | data bibliografis |
| `status` | `segera_terbit` (tampil, belum bisa dipesan) atau `terbit` |
| `isbn`, `eisbn` | kosongkan dulu bila belum terbit |
| `harga_cetak`, `harga_ebook` | angka tanpa titik; 0 = tidak dijual dalam format itu |
| `sampul` | misalnya `/assets/sampul/judul-buku.jpg` |
| `tanggal_tambah` | `YYYY-MM-DD`, untuk urutan di katalog |
| `deskripsi` | paragraf dipisah baris kosong (`\n\n`) |

3. Commit ke `main`. GitHub Actions membangun website dan memperbarui cabang `deploy`.

Link buku: `https://literaindo.com/buku/<slug>/`.

## Mencoba di komputer

```
node site/build.mjs
cd dist && python3 -m http.server 8000
```

Buka http://localhost:8000.

## Pemasangan di Hostinger

Hostinger hPanel → Websites → Kelola → Lanjutan → **GIT**:

- Repositori: `git@github.com:zulqoidyhabibie-tech/literaindo-bookstore.git`
- Cabang: `deploy`
- Direktori: kosong (= `public_html`, harus kosong saat pertama dipasang)

Karena repo privat, salin **SSH key** dari halaman GIT hPanel ke GitHub →
Settings → Deploy keys (cukup akses baca). Untuk pembaruan otomatis, aktifkan
**Auto Deployment** di hPanel dan simpan URL webhook-nya sebagai secret
`HOSTINGER_WEBHOOK` di GitHub → Settings → Secrets and variables → Actions.
