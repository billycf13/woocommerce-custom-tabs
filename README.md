# WooCommerce Custom Product Tabs Importer

Plugin WordPress yang memungkinkan Anda menambahkan tab kustom pada halaman produk WooCommerce dengan dukungan import/export.

![Plugin Banner](assets/images/banner.png)

## 🌟 Fitur Utama

- ✨ Tambahkan tab kustom tak terbatas pada setiap produk
- 📝 Editor visual (TinyMCE) dan mode kode HTML
- 🖼️ Dukungan media uploader untuk menambahkan gambar
- 📥 Import tab kustom dari file CSV
- 📤 Export tab kustom ke file CSV
- 🔄 Pengaturan prioritas untuk mengatur urutan tab
- 🎨 Tampilan admin yang user-friendly
- 🔌 Kompatibel dengan WooCommerce HPOS

## 📋 Persyaratan

- WordPress 5.8 atau lebih tinggi
- PHP 7.2 atau lebih tinggi
- WooCommerce 5.0 atau lebih tinggi
- WooCommerce diuji hingga versi 8.0

## 💻 Instalasi

1. Download plugin dari [GitHub releases](https://github.com/billycf13/woocommerce-custom-tabs/releases)
2. Upload file zip melalui menu Plugins > Add New > Upload Plugin
3. Aktifkan plugin "WooCommerce Custom Product Tabs Importer"

## 🚀 Penggunaan

### Menambahkan Tab Kustom

1. Buka halaman edit produk
2. Scroll ke bawah ke panel "Tab Kustom"
3. Klik tombol "Tambah Tab"
4. Isi:
   - Judul Tab
   - Konten Tab (mendukung editor visual dan HTML)
   - Prioritas (untuk mengatur urutan)
5. Simpan produk

### Import/Export Tab

#### Import
1. Buka WooCommerce > Products > Import
2. Upload file CSV
3. Map kolom CSV dengan format:
   - `custom_tab_1_title`: Judul tab pertama
   - `custom_tab_1_content`: Konten tab pertama
   - `custom_tab_1_priority`: Prioritas tab pertama
   - Dan seterusnya untuk tab berikutnya

#### Export
1. Buka WooCommerce > Products > Export
2. Pilih kolom tab kustom yang ingin diekspor
3. Download file CSV

## 📝 Format CSV

```csv
custom_tab_1_title,custom_tab_1_content,custom_tab_1_priority
"Spesifikasi","<p>Spesifikasi produk...</p>",10
```

## 🔧 Pengembangan

### Struktur Plugin
```
custom-product-tabs-importer/
├── assets/
│   ├── css/
│   │   └── admin-style.css
│   └── js/
│       └── admin-tabs.js
├── plugin-update-checker/
├── custom-product-tabs-importer.php
└── README.md
```

### Filter dan Action Hooks

```php
// Modifikasi tab yang ditampilkan
add_filter('woocommerce_product_tabs', 'custom_modify_product_tabs');

// Tambahkan konten kustom
add_action('woocommerce_product_tab_content', 'custom_tab_content');
```

## 🤝 Kontribusi

Kontribusi sangat diterima! Silakan buat pull request atau laporkan issue.

## 📄 Lisensi

Plugin ini dilisensikan di bawah [GPL v2 atau yang lebih baru](http://www.gnu.org/licenses/gpl-2.0.html)

## 👨‍💻 Author

- [Billycf](https://github.com/billycf13)

## 📞 Dukungan

Jika Anda menemukan masalah atau memiliki pertanyaan:
1. Buat issue di [GitHub repository](https://github.com/billycf13/woocommerce-custom-tabs/issues)
2. Kirim email ke [alamat email Anda]

## 📝 Changelog

### 1.0.1
- Tambah fitur editor visual dan mode kode
- Perbaikan tampilan tombol editor
- Optimasi performa loading editor

### 1.0.0
- Rilis pertama
- Fitur dasar tab kustom
- Dukungan import/export CSV