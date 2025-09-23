# Changelog

Semua perubahan penting pada plugin "WooCommerce Custom Product Tabs Importer" akan didokumentasikan dalam file ini.

Format changelog ini mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
dan proyek ini mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-03-21

### ✨ Ditambahkan
- Fitur editor visual (TinyMCE) untuk pengeditan konten tab yang lebih mudah
- Mode Code untuk pengeditan HTML langsung
- Tombol toggle untuk beralih antara mode Visual dan Code
- Dukungan media uploader untuk menambahkan gambar ke konten
- Sistem pembaruan otomatis plugin melalui GitHub releases

### 🔧 Diperbaiki
- Masalah tampilan tombol Visual/Code yang muncul ganda
- Inisialisasi editor saat menambahkan tab baru
- Optimasi performa loading editor dengan penundaan yang tepat
- Penyesuaian styling untuk toolbar editor dan media buttons

### 🎨 Diubah
- Peningkatan UI/UX pada panel tab kustom
- Penyederhanaan proses penambahan tab baru
- Penataan ulang struktur kode untuk maintainability yang lebih baik

### 🔐 Keamanan
- Validasi dan sanitasi input yang lebih ketat
- Peningkatan keamanan dalam penanganan konten HTML

## [1.0.0] - 2024-03-15

### ✨ Ditambahkan
- Fitur dasar untuk menambahkan tab kustom pada produk WooCommerce
- Dukungan import data tab dari file CSV
- Dukungan export data tab ke file CSV
- Pengaturan prioritas untuk mengatur urutan tab
- Panel admin yang user-friendly
- Kompatibilitas dengan WooCommerce HPOS

### 🔧 Teknis
- Struktur plugin modular
- Integrasi dengan WooCommerce hooks
- Optimasi database untuk penyimpanan data tab
- Sistem caching untuk performa yang lebih baik

[1.1.0]: https://github.com/billycf13/woocommerce-custom-tabs/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/billycf13/woocommerce-custom-tabs/releases/tag/v1.0.0