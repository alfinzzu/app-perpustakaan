# Pertemuan 6: Evaluasi Tengah Semester (UTS) - Review Proyek dan Laporan Praktikum

> **Informasi Modul:**
> - **Prasyarat:** Telah menyelesaikan tahapan praktikum Pertemuan 1 hingga 5 pada proyek `app-perpustakaan` (fondasi MVC, routing resource, validasi form request, master layout Blade, serta migrasi skema dan CRUD Eloquent).
> - **Fokus Pembelajaran:** Sesi evaluasi tengah semester (UTS) berbasis portofolio proyek. Mengukur penguasaan materi teknis dan konseptual melalui audit repositori Git/GitHub, validasi fungsionalitas CRUD data nyata MySQL, serta penyusunan laporan tertulis komprehensif.

---

## 1. Format dan Ketentuan Ujian

| Komponen Evaluasi | Ketentuan Pelaksanaan |
| :--- | :--- |
| **Bentuk Evaluasi** | Laporan tertulis (format PDF) dan audit repositori GitHub. |
| **Sifat Pengerjaan** | Individu (*Mandiri*). |
| **Cakupan Materi** | Materi komprehensif Pertemuan 1 sampai dengan Pertemuan 5. |
| **Objek Penilaian** | Repositori kode sumber (implementasi nyata) dan laporan tertulis (dokumentasi alur dan pemahaman konseptual). |

> [!NOTE]
> Evaluasi Tengah Semester (UTS) ini **tidak menambahkan modul fitur baru** ke dalam aplikasi `app-perpustakaan`. Fokus evaluasi adalah menguji stabilitas basis kode pada titik akhir Pertemuan 5, ditambah nilai apresiasi jika tugas mandiri CRUD `members` telah diselesaikan.

---

## 2. Cakupan Materi Evaluasi (Pertemuan 1 - 5)

Mahasiswa diharapkan telah menguasai lima pilar kompetensi berikut:

1. **Pertemuan 1:** Urgensi framework dalam rekayasa web, arsitektur *Model-View-Controller* (MVC), dan pemahaman hierarki direktori Laravel 12.
2. **Pertemuan 2:** Sistem perutean terpusat (*Routing*), konvensi *Resource Route*, siklus hidup permintaan (*Request Lifecycle*), serta peran penyaring *Middleware*.
3. **Pertemuan 3:** Tanggung jawab *Controller*, ekstraksi parameter HTTP melalui objek `Request`, serta implementasi validasi berlapis (*Inline Validation* dan *Form Request*).
4. **Pertemuan 4:** *Blade Templating Engine*, pewarisan tata letak bersama (*Layout Inheritance*: `@extends`, `@section`, `@yield`), komponen parsial (`@include`), serta mitigasi keamanan XSS (`{{ }}` vs `{!! !!}`).
5. **Pertemuan 5:** *Database Migration* sebagai sistem kendali versi skema, pemetaan entitas dengan *Eloquent ORM*, operasi CRUD berbasis basis data nyata MySQL, serta proteksi *Mass Assignment* (`$fillable`).

---

## 3. Standar Kelayakan Repositori Git & GitHub

Sebelum menyusun laporan, pastikan repositori GitHub Anda telah memenuhi standar minimum berikut. Ketidaksesuaian pada poin-poin di bawah akan memengaruhi penilaian secara signifikan:

- [ ] Cabang pengembangan `dev` telah terbentuk dan aktif sebagai cabang kerja utama.
- [ ] Memiliki minimal 5 rekam jejak (*commit history*) yang terstruktur pada cabang `dev`, dengan format pesan commit yang konsisten (misal `[P-1] ...` hingga `[P-5] ...`).
- [ ] Operasi CRUD pada modul katalog buku (`books`) berfungsi penuh (*Create*, *Read index/show*, *Update*, *Delete*) dengan data yang tersimpan nyata di MySQL.
- [ ] Operasi CRUD pada modul kategori (`categories`) berfungsi penuh (*Create*, *Read*, *Update*, *Delete*) dengan data nyata dari MySQL.
- [ ] Halaman indeks (`index.blade.php`) modul `books` dan `categories` telah terintegrasi dengan *Master Layout* (`@extends('layouts.app')`).
- [ ] Seluruh halaman bebas dari galat kritis PHP (tidak memicu halaman *Whoops*, *Error 500*, atau *unhandled exceptions*).

Gunakan perintah baris perintah berikut untuk memeriksa riwayat komit sebelum pengumpulan:

```bash
git log --oneline dev
```

---

## 4. Sistematika dan Struktur Laporan Tertulis

Laporan praktikum disusun secara rapi dan dikonversi ke dalam format **PDF** dengan urutan bab sebagai berikut:

### 4.1 Halaman Sampul (Cover)
Memuat identitas lengkap: Nama Lengkap, Nomor Induk Mahasiswa (NIM), Kelas/Jurusan, Judul Laporan (*"Evaluasi Tengah Semester Pemrograman Framework: Pengembangan Sistem Perpustakaan Digital Kampus"*), serta Logo Instansi Pendidikan.

### 4.2 Tautan Repositori GitHub
Cantumkan URL repositori GitHub proyek Anda secara jelas. Pastikan repositori berstatus publik (*Public*) atau pastikan dosen penilai telah diundang sebagai kolaborator jika repositori disetel privat (*Private*).

### 4.3 Dokumentasi Antarmuka dan Fitur (Tangkapan Layar)
Sertakan tangkapan layar (*screenshot*) beresolusi jelas yang merekam alur fungsionalitas sistem (sebelum aksi &rarr; formulir eksekusi &rarr; umpan balik notifikasi setelah aksi). Rincian tangkapan layar minimum:
- **Modul Buku (Books):** Halaman indeks dengan navigasi paginasi, formulir tambah buku, formulir sunting buku, serta bukti penghapusan data.
- **Modul Kategori (Categories):** Halaman indeks dengan paginasi, formulir tambah kategori, formulir sunting kategori, dan bukti penghapusan.
- **Uji Validasi Form:** Bukti visual saat formulir disubmit dalam kondisi kosong yang menampilkan pesan galat berbahasa Indonesia.

### 4.4 Pembahasan Pertanyaan Konsep Esensial
Jawablah kelima pertanyaan evaluasi konsep pada [Bagian 5](#5-pertanyaan-konsep-wajib-dijawab) dengan analisis mandiri yang komprehensif (setiap nomor diuraikan minimal dalam 3 hingga 5 kalimat).

### 4.5 Analisis Kendala Teknis dan Solusi
Uraikan minimal satu studi kasus kendala teknis nyata (*error message*, kegagalan migrasi relasi, atau kendala logika penataan layout) yang Anda hadapi sepanjang Pertemuan 1 hingga 5, serta jelaskan langkah sistematis yang Anda ambil untuk mengatasinya.

---

## 5. Pertanyaan Konsep (Wajib Dijawab)

Pertanyaan ini dirancang untuk mengevaluasi kedalaman pemahaman arsitektural (*alasan teknis* di balik penerapan konvensi, bukan sekadar hafalan sintaks):

1. **Konseptualisasi Pola MVC:**  
   Jika aplikasi Sistem Perpustakaan ini dibangun murni menggunakan PHP natif tanpa framework, permasalahan apa saja yang akan timbul seiring bertambahnya kompleksitas sistem? Kaitkan analisis Anda dengan prinsip *Separation of Concerns* pada arsitektur MVC!
2. **Penelusuran Siklus Hidup Permintaan (*Request Lifecycle*):**  
   Uraikan perjalanan teknis secara mendalam saat seorang pustakawan menekan tombol "Simpan Data Buku" pada formulir penambahan buku, mulai dari pengiriman HTTP Request oleh peramban, peran *bootstrap* `public/index.php`, penyaringan *Middleware* (CSRF), pencocokan *Router*, validasi *Form Request*, eksekusi *Eloquent Model*, hingga *Response* dialihkan kembali ke halaman indeks!
3. **Urgensi Validasi di Sisi Peladen (*Server-Side Validation*):**  
   Mengapa validasi masukan wajib ditegakkan di sisi peladen (melalui Controller / Form Request), padahal formulir HTML5 di peramban telah dilengkapi atribut seperti `required`, `type="number"`, atau `maxlength`? Jelaskan dari sudut pandang keamanan sistem!
4. **Keunggulan Database Migration:**  
   Masalah apa yang diselesaikan oleh sistem *Database Migration* dibandingkan pengelolaan tabel secara manual melalui GUI phpMyAdmin atau ekspor-impor berkas `.sql` mentah? Jelaskan urgensinya dalam konteks kolaborasi tim perangkat lunak!
5. **Mitigasi Mass Assignment Vulnerability:**  
   Jelaskan apa yang dimaksud dengan celah keamanan *Mass Assignment Vulnerability* pada aplikasi web! Bagaimana mekanisme atribut `$fillable` pada Model Eloquent mencegah eksploitasi data yang tidak sah?

---

## 6. Rubrik Penilaian dan Pembobotan UTS

### 6.1 Ringkasan Bobot Penilaian

| Komponen Penilaian | Bobot | Fokus Evaluasi |
| :--- | :---: | :--- |
| **Repositori GitHub & Tata Kelola Git** | 25% | Kerapian cabang `dev`, histori commit berkala, dan struktur repositori. |
| **Fungsionalitas CRUD Buku (Books)** | 25% | Persistensi data nyata MySQL, validasi form, paginasi, dan flash message. |
| **Fungsionalitas CRUD Kategori (Categories)** | 20% | Persistensi data, integritas validasi, operasi pembaruan, dan penghapusan. |
| **Penerapan Master Layout Blade** | 15% | Integrasi layout bersama, bilah navigasi aktif dinamis, dan partial alert. |
| **Kualitas Laporan Tertulis & Pemahaman Konsep** | 15% | Kelengkapan bukti pengujian, analisis kendala, dan jawaban 5 pertanyaan konsep. |

---

### 6.2 Rincian Aspek 1: Kualitas Repositori Git (25%)

| Indikator Penilaian | Skor Maksimal |
| :--- | :---: |
| Cabang `dev` terkonfigurasi dengan benar dan aktif sebagai cabang utama pengembangan. | 8 Poin |
| Riwayat commit terstruktur rapi (minimal 5 commit bertahap dengan format `[P-X] deskripsi`). | 12 Poin |
| Riwayat commit mencerminkan progres autentik pengerjaan modul (bukan commit tunggal masal). | 5 Poin |
| **Poin Bonus:** Telah menyelesaikan dan mengintegrasikan CRUD anggota (`members`). | +3 Poin *(Maksimal total tetap 25)* |

### 6.3 Rincian Aspek 2: Fungsionalitas CRUD Books (25%)

| Indikator Penilaian | Skor Maksimal |
| :--- | :---: |
| **Create:** Data buku baru berhasil tersimpan ke MySQL, redirect rute aman disertai *flash message*. | 6 Poin |
| **Read:** Data indeks tersaji dengan paginasi rapi dan halaman rincian (`show`) menampilkan data valid. | 6 Poin |
| **Update:** Formulir sunting terisi otomatis (*pre-filled*) dengan data lama dan pembaruan tersimpan akurat. | 6 Poin |
| **Delete:** Rekaman data buku berhasil dihapus dari tabel basis data. | 4 Poin |
| **Validasi:** Pesan galat berbahasa Indonesia tampil saat pengiriman data kosong atau tidak valid. | 3 Poin |

### 6.4 Rincian Aspek 3: Fungsionalitas CRUD Categories (20%)

| Indikator Penilaian | Skor Maksimal |
| :--- | :---: |
| **Create:** Kategori baru tersimpan ke MySQL, pengalihan berhasil disertai notifikasi sukses. | 5 Poin |
| **Read:** Halaman indeks kategori menampilkan seluruh koleksi lengkap dengan fitur paginasi. | 5 Poin |
| **Update:** Data nama dan deskripsi kategori berhasil diperbarui secara akurat. | 5 Poin |
| **Delete:** Rekaman kategori berhasil dihapus dari tabel basis data. | 3 Poin |
| **Validasi:** Pesan galat validasi field wajib terpicu secara tepat. | 2 Poin |

### 6.5 Rincian Aspek 4: Penerapan Master Layout Blade (15%)

| Indikator Penilaian | Skor Maksimal |
| :--- | :---: |
| Berkas `books/index.blade.php` mewarisi tata letak master (`@extends('layouts.app')`). | 4 Poin |
| Berkas `categories/index.blade.php` mewarisi tata letak master (`@extends('layouts.app')`). | 4 Poin |
| Bilah navigasi (*navbar*) tampil konsisten dengan penanda status menu aktif (`request()->routeIs()`). | 3 Poin |
| Kotak pesan notifikasi diekstrak sebagai komponen parsial (`partials/alert.blade.php`). | 2 Poin |
| **Nilai Tambah:** Formulir `create` dan `edit` telah direfaktorisasi memanfaatkan master layout. | +2 Poin *(Maksimal total tetap 15)* |

### 6.6 Rincian Aspek 5: Kualitas Laporan Tertulis (15%)

| Indikator Penilaian | Skor Maksimal |
| :--- | :---: |
| Tangkapan layar (*screenshot*) lengkap merekam seluruh operasi CRUD dan pesan galat validasi. | 5 Poin |
| Jawaban atas 5 pertanyaan konsep mendalam, kritis, dan menguraikan rasionalitas arsitektur. | 7 Poin |
| Uraian analisis kendala teknis dan solusi bersumber dari pengalaman praktikum nyata. | 3 Poin |

---

### 6.7 Skala Konversi Nilai Akhir

$$\text{Nilai Akhir UTS} = \sum (\text{Skor Aspek 1 s.d. 5})$$

| Rentang Skor Kumulatif | Huruf Mutu | Kualifikasi Predikat |
| :---: | :---: | :--- |
| **85 – 100** | **A** | Sangat Memuaskan (*Excellent*) |
| **70 – 84** | **B** | Memuaskan (*Good*) |
| **55 – 69** | **C** | Cukup (*Sufficient*) |
| **40 – 54** | **D** | Kurang (*Deficient*) |
| **< 40** | **E** | Tidak Memenuhi Standar (*Failed*) |

---

## 7. Panduan Khusus Dosen Penilai (Evaluator Notes)

> [!IMPORTANT]
> **Pedoman Penilaian Standar:**
> 1. **Aksesibilitas Repositori:** Jika tautan repositori mahasiswa tidak dapat diakses (rusak atau berstatus privat tanpa izin akses), Aspek 1 hingga 4 otomatis bernilai 0. Mahasiswa hanya mendapatkan penilaian berbasis dokumen laporan fisik pada Aspek 5.
> 2. **Tampilan ID Kategori pada Modul Buku:** Pada Pertemuan 5, tabel `books` masih menampilkan ID numerik (`category_id`) dan belum menampilkan nama relasi teks kategori. Hal ini **bukan merupakan kesalahan atau kekurangan**, karena materi relasi *Eloquent Relationships* (`belongsTo`/`hasMany`) baru menjadi fokus pembelajaran pada Pertemuan 7.
> 3. **Kelengkapan Modul Anggota (Members):** Modul anggota berstatus tugas mandiri pengayaan pada P-5. Keberadaannya memberikan poin bonus pada Aspek 1 dan ketidakhadirannya tidak boleh mengurangi poin pokok mahasiswa.
> 4. **Integrasi Tata Letak pada Formulir Input:** Mahasiswa diwajibkan menerapkan *Master Layout* minimal pada halaman indeks (`index.blade.php`). Penerapan master layout pada halaman formulir (`create`, `edit`, `show`) dihitung sebagai nilai tambah.

---

## Navigasi Modul
- [← Pertemuan 05: Database Migration, Eloquent Model, dan Operasi CRUD](./pertemuan-05.md)
- [Daftar Isi Modul](./README.md)
- [Pertemuan 07: Eloquent Relationship dan Relasi Antar Tabel](./pertemuan-07.md) &rarr;
