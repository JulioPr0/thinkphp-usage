![](https://www.thinkphp.cn/uploads/images/20230630/300c856765af4d8ae758c503185f8739.png)

# ThinkPHP 8

[![build](https://github.com/top-think/framework/actions/workflows/build.yml/badge.svg?branch=8.0)](https://github.com/top-think/framework/actions)
[![Total Downloads](https://poser.pugx.org/topthink/framework/downloads)](https://packagist.org/packages/topthink/framework)
[![Latest Stable Version](https://poser.pugx.org/topthink/framework/v/stable)](https://packagist.org/packages/topthink/framework)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](http://www.php.net/)
[![License](https://poser.pugx.org/topthink/framework/license)](https://packagist.org/packages/topthink/framework)

## Fitur utama

- Dibangun ulang berbasis PHP 8.0+
- Upgrade dependensi PSR
- Mengandalkan think-orm 3.0+ versi
- think-dumper baru mendukung debug jarak jauh
- Upgrade mulus dari 6.0 / 6.1

> ThinkPHP 8 membutuhkan PHP 8.0+

Sekarang kamu bisa memakai [ThinkChat](https://chat.topthink.com/) sebagai asisten AI pribadi saat belajar ThinkPHP.

[![](https://www.topthink.com/uploads/assistant/20230630/4d1a3f0ad2958b49bb8189b7ef824cb0.png)](https://chat.topthink.com/)

Layanan ekosistem ThinkPHP disediakan oleh [TopThink Cloud](https://www.topthink.com) yang menawarkan layanan pengembang profesional.

## Dokumentasi

- [Panduan lengkap](https://doc.thinkphp.cn)
- Latihan & jawaban berbasis manual resmi: [Asisten ahli resmi](https://chat.topthink.com/chat/eorole)

## Sponsor

Program sponsor: https://www.thinkphp.cn/sponsor memberi eksposur tinggi di situs, manual, halaman sambutan, dan repo git sambil mendukung keberlanjutan ThinkPHP.

[![](https://www.thinkphp.cn/sponsor/special.svg)](https://www.thinkphp.cn/sponsor/special)

[![](https://www.thinkphp.cn/sponsor.svg)](https://www.thinkphp.cn/sponsor)

## Mulai cepat — SQLite

Jalankan dari folder proyek:

```bash
composer install
cp .env.example .env
php think app:install --seed
php think admin:create admin@example.com "Administrator"
php think run --host 127.0.0.1 --port 8000
```

Pada Windows, salin `.env.example` menjadi `.env` melalui File Explorer atau `Copy-Item .env.example .env` di PowerShell.

Perintah pembuatan admin meminta password tersembunyi dan konfirmasinya. Gunakan 12–72 byte. **Tidak ada akun atau password bawaan.**

- Blog: <http://localhost:8000/blog>
- Login: <http://localhost:8000/login>
- Dashboard setelah login: <http://localhost:8000/dashboard>

`--seed` menambahkan artikel contoh hanya ketika tabel artikel masih kosong. Hilangkan opsi tersebut untuk instalasi kosong. Menjalankan `app:install` kembali hanya menjalankan migrasi yang belum tercatat.

## Menggunakan MySQL

Buat database UTF-8 terlebih dahulu dan akun aplikasi tersendiri. Atur `.env`:

```ini
DB_DRIVER = mysql
DB_HOST = 127.0.0.1
DB_PORT = 3306
DB_NAME = articles_db
DB_USER = articles_app
DB_PASS = "password-database-anda"
```

Lalu jalankan `php think app:install`. Akun yang menjalankan migrasi memerlukan izin membuat/mengubah tabel serta indeks; akun runtime dapat dibatasi setelahnya.

## Upgrade database dari versi awal

1. Backup database dan salin `.env` lama ke luar folder proyek.
2. Ekstrak kode baru. Pertahankan kredensial dan isi `.env` menggunakan nama `DB_DRIVER`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT` seperti contoh di atas. Untuk database MySQL lama, isi `DB_DRIVER = mysql` secara eksplisit karena default baru adalah SQLite.
3. Jalankan `composer install`, kemudian `php think app:install` tanpa `--seed`.
4. Migrasi menambahkan tabel admin/kategori/pembatasan login dan kolom `slug`, `category_id`, `deleted_at`, `version`. Data judul, ringkasan, konten, ID, dan timestamp lama dipertahankan. Slug artikel lama mendapat akhiran ID agar unik.
5. Artikel published lama tanpa waktu terbit memakai tanggal pembuatannya. Status MySQL diperluas dari enum awal menjadi varchar untuk mendukung arsip.
6. Buat admin melalui CLI. Periksa jumlah artikel dan hasil render sebelum menggunakan database tersebut untuk produksi.

MySQL melakukan commit otomatis untuk perubahan skema. Gunakan jendela pemeliharaan dan backup; migrasi ini tidak menyediakan rollback otomatis. Jangan menjalankan beberapa proses migrasi secara bersamaan.

`database/legacy_schema.sql` hanya menyimpan skema awal untuk referensi. Instalasi baru dan upgrade memakai migrasi, bukan mengimpor file tersebut.

## Penggunaan

- **Draft**: privat; waktu terbit dikosongkan saat disimpan.
- **Published**: publik jika waktu terbit sudah tiba. Waktu kosong memakai waktu sekarang.
- **Terjadwal**: label untuk Published dengan waktu terbit di masa depan. Visibilitas dihitung saat halaman diminta.
- **Arsip**: privat; histori tanggal terbit dapat dipertahankan.
- **Sampah**: tidak terlihat publik dan tidak bisa diedit. Pulihkan dahulu; hasil pemulihan selalu Draft.
- **Aksi massal Terbitkan sekarang** mengubah tanggal terbit menjadi waktu saat aksi diproses.
- **Slug kosong saat edit** mempertahankan slug lama. Perubahan slug manual mengganti URL publik; redirect dari slug sebelumnya belum tersedia.
- **CSV** berisi ID, judul, slug, ringkasan, status, kategori, serta timestamp. Konten penuh tidak disertakan agar ukuran ekspor terkendali. Nilai yang berpotensi menjadi formula diberi awalan apostrof.
- Konten disimpan sebagai **teks biasa**, bukan HTML atau Markdown. Ini berlaku juga pada pratinjau.
- Tekan `/` pada daftar/blog untuk memfokuskan pencarian. Tema tampilan tersimpan di browser.

## Akun dan sesi

Semua akun yang dibuat melalui CLI memiliki akses admin yang sama. Belum ada editor dengan izin terbatas, registrasi publik, reset password melalui email, maupun manajemen pengguna di UI.

```bash
php think admin:create admin@example.com "Administrator" --reset
```

Reset CLI dan ganti password di halaman Akun mengakhiri seluruh sesi akun tersebut. Sesi memiliki batas tidak aktif 2 jam dan batas total 8 jam. Login dibatasi 5 percobaan per pasangan email/IP dan 30 percobaan per IP dalam jendela 15 menit. Pembatasan tersimpan di database sehingga membuat sesi browser baru tidak meresetnya. IP menggunakan alamat koneksi langsung; jika memakai reverse proxy, pertimbangkan kebijakan rate limit di proxy karena beberapa pengguna dapat berbagi IP proxy yang sama.

Untuk otomasi CLI, password dapat diberikan melalui environment `ADMIN_PASSWORD` dari secret manager. Hindari argumen password pada command line dan jangan menyimpannya di repository.

## Lisensi

Basis ThinkPHP tetap mengikuti lisensi [Apache-2.0](LICENSE.txt). Kredit framework asli dipertahankan pada metadata Composer.
