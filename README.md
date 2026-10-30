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

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [documentation/setup-guide.md](documentation/setup-guide.md) | Instalasi di hosting langkah demi langkah |
| [documentation/admin-guide.md](documentation/admin-guide.md) | Menambah buku, halaman ISBN, memproses pesanan QRIS, e-book, refund |
| [documentation/backup-recovery.md](documentation/backup-recovery.md) | Backup, uji pemulihan, pembaruan |
| [testing/functional-tests.md](testing/functional-tests.md) | Skenario uji fungsional |
| [testing/launch-checklist.md](testing/launch-checklist.md) | Checklist sebelum peluncuran |

File zip tema dan plugin untuk diunggah ke WordPress:

```bash
scripts/build-zips.sh        # hasil di dist/
```

## Prinsip

- Gunakan fitur bawaan WooCommerce sebanyak mungkin.
- Jangan mengubah core WordPress/WooCommerce atau tema induk.
- Kredensial dan file konfigurasi (`wp-config.php`, `.env`) tidak boleh masuk repositori.
- Uji di staging sebelum produksi.
