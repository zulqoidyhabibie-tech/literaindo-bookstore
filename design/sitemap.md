# Sitemap dan permalink

Atur di **Pengaturan → Permalink** (WordPress) setelah WooCommerce aktif:

- Struktur umum: **Nama tulisan** (`/%postname%/`)
- Dasar kategori produk: `kategori`
- Dasar produk: **Kustom** → `/produk/`

| Halaman | URL | Cara membuat |
|---|---|---|
| Beranda | `/` | Otomatis dari `front-page.php` |
| Semua buku | `/buku/` | Halaman "Buku" dijadikan halaman Toko (WooCommerce → Pengaturan → Produk) |
| Kategori | `/kategori/nama-kategori/` | Produk → Kategori |
| Detail buku | `/produk/slug-buku/` | Setiap produk. **URL ini yang dipakai sebagai tautan publikasi untuk pengajuan ISBN.** |
| Keranjang | `/keranjang/` | Halaman WooCommerce, ubah slug |
| Checkout | `/checkout/` | Halaman WooCommerce |
| Akun | `/akun/` | Halaman WooCommerce "Akun Saya", ubah slug |
| Tentang kami | `/tentang-kami/` | Halaman biasa |
| Bantuan | `/bantuan/` | Halaman biasa |

`/format/cetak/` dan `/format/ebook/` dibuat di langkah 2 (plugin) sebagai
taksonomi format.

Slug produk sebaiknya tidak diubah setelah tautannya dipakai untuk ISBN.
