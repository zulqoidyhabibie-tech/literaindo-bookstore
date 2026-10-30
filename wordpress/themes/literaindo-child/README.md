# Literaindo Child

Child theme [Storefront](https://wordpress.org/themes/storefront/) untuk Literaindo Press Bookstore.

## Pemasangan

1. Di WordPress: **Tampilan → Tema → Tambah Baru**, cari dan pasang **Storefront** (jangan diaktifkan).
2. Kompres folder `literaindo-child` menjadi `literaindo-child.zip`, lalu **Tampilan → Tema → Tambah Baru → Unggah Tema**.
3. Aktifkan **Literaindo Child**.
4. **Pengaturan → Umum → Bahasa Situs**: Bahasa Indonesia (agar teks WooCommerce berbahasa Indonesia).
5. Atur permalink sesuai `design/sitemap.md`.
6. **Tampilan → Menu**: buat menu utama (lokasi *Primary Menu*) dan menu bantuan/kebijakan (lokasi *Footer: Bantuan & Kebijakan*).
7. **Tampilan → Sesuaikan → Beranda Literaindo**: ubah teks banner dan bagian Tentang bila perlu.

## Isi

| File | Fungsi |
|---|---|
| `functions.php` | Memuat font dan CSS, menyamakan warna Customizer Storefront dengan palet, footer |
| `front-page.php` | Beranda: banner + pencarian, kategori unggulan, buku terbaru, terlaris, tentang |
| `inc/customizer.php` | Teks beranda yang bisa diubah admin |
| `inc/woocommerce.php` | Penulis di kartu dan detail buku, tab "Informasi Buku", breadcrumb |
| `inc/template-tags.php` | Fungsi tampilan dan kontrak data dengan plugin |
| `assets/css/main.css` | Seluruh gaya dan design tokens |

## Data buku

Tema tidak menyimpan data. Penulis dan metadata (ISBN, tahun, halaman, ukuran,
status terbit) diisi oleh plugin `literaindo-bookstore` melalui filter
`literaindo_book_author` dan `literaindo_book_details`. Tanpa plugin, tab
"Informasi Buku" tidak tampil.

## Pratinjau tanpa WordPress

`design/preview/index.html` dan `produk.html` adalah tiruan statis yang memakai
`main.css` yang sama, untuk meninjau tampilan. Tata letak Storefront di sana
hanya perkiraan.
