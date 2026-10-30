# Literaindo Press Bookstore

Toko online resmi untuk buku cetak dan e-book terbitan **Literaindo Press**.
Dibangun di atas WordPress + WooCommerce sesuai *Blueprint UI/UX dan Spesifikasi Teknis* v1.0.

## Struktur repositori

```
literaindo-bookstore/
├── wordpress/
│   ├── themes/literaindo-child/      # child theme (induk: Storefront)
│   └── plugins/literaindo-bookstore/ # fungsi khusus toko (metadata buku, QRIS BTN, e-book)
├── design/                           # design tokens, sitemap, spesifikasi UI
├── testing/                          # uji fungsional dan checklist peluncuran
└── documentation/                    # panduan instalasi, admin, backup
```

## Prinsip

- Gunakan fitur bawaan WooCommerce sebanyak mungkin.
- Jangan mengubah core WordPress/WooCommerce atau tema induk.
- Kredensial dan file konfigurasi (`wp-config.php`, `.env`) tidak boleh masuk repositori.
- Uji di staging sebelum produksi.
