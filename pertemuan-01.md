# Pertemuan 1: Framework, Konsep MVC, dan Setup Proyek Laravel 12

> **Informasi Modul:**
> - **Prasyarat:** Belum ada (Pertemuan Perdana).
> - **Fokus Pembelajaran:** Memahami urgensi framework dan pola arsitektur MVC, melakukan instalasi Laravel 12, konfigurasi environment, serta penyiapan repositori Git dan GitHub untuk proyek `app-perpustakaan`.

---

## 1. Capaian Pembelajaran

Setelah menyelesaikan pertemuan ini, mahasiswa diharapkan mampu:
1. Menjelaskan alasan penggunaan framework dalam pengembangan web modern serta masalah yang dipecahkannya (*spaghetti code* vs *structured code*).
2. Memahami pola arsitektur **Model-View-Controller (MVC)** beserta peran dan batasan tanggung jawab tiap komponen.
3. Melakukan instalasi Laravel 12 menggunakan Composer dan menjalankan *development server*.
4. Memahami struktur direktori Laravel 12 beserta fungsi masing-masing berkas intinya.
5. Melakukan inisialisasi repositori Git lokal, menghubungkannya ke GitHub, serta mengelola alur cabang (*branching workflow*: `main` dan `dev`).

---

## 2. Konsep Dasar

### 2.1 Mengapa Framework Dibutuhkan?

Bayangkan Anda diminta membangun sebuah gedung bertingkat. Anda tentu tidak akan membuat semen sendiri dari batu kapur, merancang generator listrik dari nol, atau mencetak pipa paralon secara manual. Anda akan menggunakan material berstandar industri, cetakan (*blueprint*) yang teruji, serta metode konstruksi yang aman.

Dalam dunia rekayasa perangkat lunak web, **framework** bekerja dengan prinsip yang serupa. Framework adalah kerangka kerja terstruktur yang menyediakan solusi siap pakai untuk kebutuhan umum aplikasi web, seperti:
- Perutean URL (*Routing*),
- Abstraksi dan koneksi basis data (*Object-Relational Mapping* / ORM),
- Validasi data input,
- Manajemen sesi dan autentikasi pengguna,
- Keamanan bawaan (*CSRF protection*, *XSS prevention*, *SQL injection protection*),
- Pengelolaan respons (HTML *rendering* maupun RESTful JSON).

Tanpa framework, setiap pengembang harus membangun solusi di atas secara mandiri dari nol, yang sering kali menghasilkan kualitas kode tidak seragam dan celah keamanan yang rentan.

### 2.2 Evolusi: Spaghetti Code Menuju Framework Terstruktur

Pada era awal pemrograman PHP natif, sering dijumpai pola penulisan berkas tunggal yang memuat logika SQL, pemrosesan formulir, dan struktur HTML dalam satu tempat. Pola ini populer dengan istilah **spaghetti code** karena alur logikanya kusut dan sulit diurai.

Kelemahan pola *spaghetti code*:
- **Tinggi Ketergantungan (*High Coupling*):** Perubahan kecil pada tampilan HTML dapat merusak logika query SQL.
- **Rendah Keterbacaan (*Poor Readability*):** Pengembang baru membutuhkan waktu berjam-jam untuk memetakan alur satu berkas.
- **Hambatan Kolaborasi:** Pengembang antarmuka (*frontend*) dan pengembang logika (*backend*) tidak dapat bekerja bersamaan pada berkas yang sama tanpa memicu konflik kode (*code conflict*).

Framework hadir untuk menegakkan arsitektur yang konsisten dan terorganisasi, sehingga basis kode mudah dibaca, dirawat, dan diperluas di masa mendatang.

### 2.3 Perbedaan Framework dan Library

Penting untuk membedakan antara *library* dan *framework*:

| Pembeda | Library | Framework |
| :--- | :--- | :--- |
| **Kendali Alur Program** | Dikendalikan sepenuhnya oleh kode Anda. | Framework mengendalikan alur utama aplikasi. |
| **Mekanisme Eksekusi** | Kode Anda memanggil fungsi/metode di dalam library saat dibutuhkan. | Framework memanggil kode yang Anda tulis pada *hook* atau siklus tertentu (**Inversion of Control**). |
| **Contoh Penggunaan** | `Carbon` (manipulasi waktu), `Guzzle` (klien HTTP). | `Laravel` (PHP), `Django` (Python), `Spring Boot` (Java). |

### 2.4 Lanskap Framework Web Populer

| Bahasa Pemrograman | Framework | Karakteristik Utama |
| :--- | :--- | :--- |
| **PHP** | **Laravel** | *Batteries included* — kaya fitur bawaan (ORM Eloquent, migrasi, queue, autentikasi). |
| **JavaScript / TypeScript** | **Express.js / NestJS** | Minimalis, fleksibel, modular dengan dukungan ekosistem npm yang luas. |
| **Python** | **Django** | Mengusung filosofi konvensi yang kuat dan keamanan tingkat tinggi secara bawaan. |
| **Java** | **Spring Boot** | Arsitektur enterprise berbasis *Dependency Injection* yang skalabel dan teruji. |

---

## 3. Arsitektur Model-View-Controller (MVC)

Pola arsitektur MVC membagi tanggung jawab aplikasi menjadi tiga pilar utama:

```text
               +-------------------------------------------------------+
               |                       PENGGUNA                        |
               +-------------------------------------------------------+
                               |                       ^
                        1. HTTP Request           6. HTTP Response
                               v                       |
+----------------------------------------------------------------------+
| LARAVEL APLIKASI                                                     |
|                                                                      |
|    +-------------------+    2. Panggil Data     +---------------+    |
|    |    Controller     | ---------------------> |     Model     |    |
|    | (app/Http/        | <--------------------- | (app/Models/) |    |
|    |  Controllers/)    |    3. Hasil Data       +---------------+    |
|    +-------------------+                               |             |
|              |                                  Interaksi Query      |
|              | 4. Kirim Data                           v             |
|              v                                  +---------------+    |
|    +-------------------+                        |   Database    |    |
|    |       View        |                        |  (MySQL/dsb)  |    |
|    | (resources/views/)|                        +---------------+    |
|    +-------------------+                                             |
|              |                                                       |
|              +----------- 5. Render HTML ----------------------------+
+----------------------------------------------------------------------+
```

### Tanggung Jawab Komponen:
- **Model (`app/Models/`):** Mengelola representasi entitas data, logika bisnis terkait integritas data, serta relasi antar-tabel (misal relasi tabel `books`, `members`, `loans`).
- **View (`resources/views/`):** Bertanggung jawab murni pada presentasi antarmuka pengguna (menggunakan template engine Blade). Tidak boleh ada pemrosesan logika bisnis kompleks di dalam View.
- **Controller (`app/Http/Controllers/`):** Bertindak sebagai koordinator. Menerima *request*, memvalidasi masukan, meminta data dari Model, dan meneruskan data tersebut ke View untuk dirender.

### Keuntungan Pola MVC:
1. **Pemisahan Kepentingan (*Separation of Concerns*):** Kode tersusun berdasarkan domain fungsinya.
2. **Kemudahan Pemeliharaan (*Maintainability*):** Perubahan UI tidak mengganggu logika basis data.
3. **Efisiensi Kolaborasi Tim:** Pembagian kerja antara tim *frontend* dan *backend* dapat berjalan paralel.

---

## 4. Studi Kasus Semester: Sistem Perpustakaan Digital

Sepanjang materi praktikum, kita akan membangun satu aplikasi komprehensif: **Sistem Perpustakaan Digital Kampus** (`app-perpustakaan`).

Sistem ini dirancang untuk mencatat katalog buku, data keanggotaan, serta transaksi peminjaman dan pengembalian. Setiap pertemuan akan menambahkan fitur bertahap hingga menjadi aplikasi utuh pada evaluasi akhir semester (UAS).

> [!NOTE]
> Untuk memahami gambaran menyeluruh struktur tabel, relasi, dan aktor sistem, silakan jadikan panduan **[Studi Kasus & Desain Database](./studi-kasus-database.md)** sebagai rujukan berkala.

---

## 5. Ringkasan Struktur Direktori Laravel 12

| Direktori / Berkas | Fungsi Utama |
| :--- | :--- |
| `app/Http/Controllers/` | Tempat berkas Controller pengendali alur logika request-response. |
| `app/Models/` | Tempat berkas entitas Model Eloquent ORM. |
| `resources/views/` | Berkas tampilan antarmuka Blade (`.blade.php`). |
| `routes/web.php` | Rute aplikasi berbasis penjelajah web (mengembalikan HTML/sesi). |
| `routes/console.php` | Definisi perintah berbasis baris perintah (*console commands*). |
| `database/migrations/` | Skrip pendefinisian skema struktur tabel basis data. |
| `database/seeders/` | Skrip pengisian data awal / *dummy* ke basis data. |
| `public/` | Titik masuk tunggal (*document root*) aplikasi (`index.php`) dan aset statis. |
| `config/` | Berkas konfigurasi terpusat (koneksi, sesi, *cache*, dll.). |
| `.env` | Konfigurasi variabel lingkungan (*environment variables*) lokal yang bersifat sensitif. |
| `artisan` | Utilitas CLI Laravel untuk generator kode dan otomatisasi task. |

---

## 6. Langkah-Langkah Praktikum

### Prasyarat Sistem
Pastikan lingkungan pengembangan lokal telah terpasang:
- PHP >= 8.2
- Composer
- Layanan Database (MySQL / MariaDB)

---

### Langkah 1: Instalasi Laravel 12 via Composer

Buka terminal dan navigasikan ke direktori kerja praktikum Anda, kemudian jalankan perintah pembuatan proyek:

```bash
composer create-project laravel/laravel app-perpustakaan
cd app-perpustakaan
```

Perintah di atas akan mengunduh kerangka dasar proyek Laravel 12 beserta dependensi pendukungnya.

---

### Langkah 2: Menjalankan Development Server

Pastikan instalasi berhasil dan server pengembangan lokal dapat berjalan dengan normal:

```bash
php artisan serve
```

Akses URL berikut melalui web browser:
```text
http://127.0.0.1:8000
```
Tampilan halaman selamat datang (*Welcome page*) Laravel menandakan sistem dasar siap digunakan. Tekan `Ctrl + C` pada terminal untuk menghentikan server sementara.

---

### Langkah 3: Konfigurasi Basis Data pada `.env`

Buka berkas konfigurasi lingkungan `.env` pada direktori root proyek `app-perpustakaan`. Sesuaikan pengaturan basis data sesuai konfigurasi MySQL lokal Anda:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_perpustakaan
DB_USERNAME=root
DB_PASSWORD=
```

Buat basis data kosong bernama `db_perpustakaan` melalui terminal MySQL atau phpMyAdmin:
```sql
CREATE DATABASE db_perpustakaan;
```

> [!TIP]
> Berkas `.env` telah tercatat di `.gitignore` secara otomatis agar konfigurasi sensitif (kata sandi, kunci aplikasi) tidak terunggah ke repositori publik.

---

### Langkah 4: Inisialisasi Git dan Integrasi GitHub

1. Buat sebuah repositori baru bernama `app-perpustakaan` di akun GitHub Anda (pastikan repositori berstatus kosong tanpa inisialisasi README/License).
2. Hubungkan direktori proyek lokal ke repositori GitHub tersebut melalui urutan perintah berikut:

```bash
# 1. Inisialisasi Git lokal
git init

# 2. Pastikan nama cabang utama adalah 'main'
git branch -M main

# 3. Tambahkan remote repository (sesuaikan USERNAME Anda)
git remote add origin https://github.com/[USERNAME]/app-perpustakaan.git

# 4. Stage semua berkas proyek dan buat commit awal
git add .
git commit -m "feat: inisialisasi proyek laravel 12 app-perpustakaan"

# 5. Unggah cabang main ke repositori remote
git push -u origin main
```

---

### Langkah 5: Pembuatan dan Publikasi Cabang `dev`

Dalam alur kerja tim profesional, cabang `main` dikhususkan untuk kode stabil (*production-ready*). Pengembangan fitur sehari-hari dilakukan pada cabang `dev`.

```bash
# Membuat dan beralih ke cabang dev
git checkout -b dev

# Mendorong cabang dev ke remote repository
git push -u origin dev
```

---

### Langkah 6: Verifikasi dan Uji Coba

1. Periksa status cabang yang sedang aktif di terminal:
   ```bash
   git branch
   ```
   *Pastikan tanda bintang (`*`) berada di cabang `dev`.*

2. Jalankan kembali development server:
   ```bash
   php artisan serve
   ```
   Pastikan aplikasi berjalan tanpa pesan galat pada `http://127.0.0.1:8000`.

---

## 7. Checkpoint Git Praktikum

Sebelum menyelesaikan sesi, pastikan rekam jejak repositori telah diperbarui dengan rapi:

```bash
git status
git add .
git commit -m "docs: checkpoint setup awal modul pertemuan-01"
git push origin dev
```

---

## 8. Tugas Mandiri

Kerjakan tugas mandiri berikut untuk pendalaman materi:
1. Pastikan repositori `app-perpustakaan` di GitHub memiliki dua cabang aktif: `main` dan `dev`.
2. Modifikasi berkas `README.md` pada root proyek dengan informasi:
   - Nama proyek dan deskripsi ringkas aplikasi,
   - Prasyarat teknologi (*stack*),
   - Panduan instalasi dan menjalankan proyek secara lokal.
3. Tuliskan refleksi singkat (2-3 kalimat) mengenai pemahaman Anda tentang perbedaan peran **Model**, **View**, dan **Controller**.

**Keluaran Tugas:**
- Tautan repositori GitHub proyek `app-perpustakaan` (pada cabang `dev`).
- Tangkapan layar (*screenshot*) halaman Welcome Laravel 12 yang berhasil berjalan di browser (`http://127.0.0.1:8000`).

---

## Navigasi Modul
- [Daftar Isi Modul](./README.md)
- [Pertemuan 02: Routing, Controller, dan Blade Templating](./pertemuan-02.md) &rarr;
