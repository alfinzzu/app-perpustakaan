# Pertemuan 2: Routing dan Request Lifecycle di Laravel 12

> **Informasi Modul:**
> - **Prasyarat:** Proyek Laravel 12 `app-perpustakaan` telah terinstal, terhubung ke basis data MySQL, dan tersinkronisasi pada repositori GitHub (cabang `main` dan `dev`).
> - **Fokus Pembelajaran:** Memahami urgensi perutean terpusat, menguasai berbagai pola route di Laravel 12 (*closure*, parameter wajib/opsional, *route grouping*, *route naming*), mengimplementasikan *Resource Controller* dan *Resource Routing* untuk domain sistem perpustakaan, serta memahami alur siklus hidup permintaan HTTP (*Request Lifecycle*) dari browser hingga menghasilkan respons.

---

## 1. Capaian Pembelajaran

Setelah menyelesaikan modul pertemuan ini, mahasiswa diharapkan mampu:
1. Menjelaskan konsep dasar *routing* dan alasan penggunaannya dibandingkan pendekatan berkas statis pada PHP natif.
2. Membedakan peran dan peruntukan berkas konfigurasi `routes/web.php` dan `routes/api.php`.
3. Mengimplementasikan berbagai variasi definisi rute: *closure*, parameter wajib (*required parameter*), parameter opsional (*optional parameter*), penamaan rute (*named routes*), serta pengelompokan (*route grouping*).
4. Menjelaskan 7 rute konvensi RESTful CRUD yang dihasilkan oleh `Route::resource()` beserta mekanisme parsial menggunakan `except()` dan `only()`.
5. Menguasai konsep dan menelusuri siklus hidup permintaan HTTP (*Request Lifecycle*) pada Laravel 12 mulai dari berkas `public/index.php`, *bootstrap*, *middleware*, *router*, hingga *controller*.
6. Memahami peran dan posisi strategis *Middleware* dalam menyaring permintaan sebelum dieksekusi oleh *Controller*.
7. Mengembangkan empat *Resource Controller* (`BookController`, `CategoryController`, `MemberController`, `LoanController`) dengan respons *dummy* dan memvalidasi hasilnya menggunakan `php artisan route:list`.

---

## 2. Konsep Dasar Routing

### 2.1 Mengapa Routing Dibutuhkan?

Pada era pemrograman web menggunakan PHP natif tanpa arsitektur terstruktur, pemetaan URL aplikasi umumnya terikat langsung pada struktur fisik berkas di dalam direktori peladen (*web server*). Sebagai contoh:
- `https://perpustakaan.test/buku.php` mengeksekusi berkas `buku.php`.
- `https://perpustakaan.test/buku-detail.php?id=12` mengeksekusi berkas `buku-detail.php`.
- `https://perpustakaan.test/tambah-buku.php` mengeksekusi berkas `tambah-buku.php`.

Sebagian pengembang mencoba mengatasi fragmentasi berkas tersebut dengan membuat gerbang tunggal `index.php` yang memuat logika pengondisian bersarang:

```php
// Pendekatan PHP natif dengan percabangan (kurang terstruktur)
$page = $_GET['page'] ?? 'beranda';

if ($page === 'beranda') {
    include 'halaman/beranda.php';
} elseif ($page === 'katalog') {
    include 'halaman/katalog.php';
} elseif ($page === 'buku-detail') {
    include 'halaman/buku-detail.php';
} else {
    http_response_code(404);
    include 'halaman/404.php';
}
```

Pendekatan ini memicu berbagai keterbatasan kritis:
- **Ketiadaan Validasi Metode HTTP:** Metode `GET`, `POST`, `PUT`, dan `DELETE` diperlakukan sama tanpa filter keamanan protokol.
- **Rentan terhadap *Spaghetti Code*:** Logika navigasi bercampur aduk dengan pemrosesan basis data dan tampilan antarmuka.
- **Ketiadaan Titik Kendali Terpusat (*Single Source of Truth*):** Sulit memetakan dan mengaudit seluruh titik akhir (*endpoints*) yang dimiliki sistem.

**Routing** hadir sebagai lapisan abstraksi terpusat. Alih-alih membiarkan URL merepresentasikan hierarki fisik berkas di server, *Router* bertindak sebagai sentral navigasi: pengembang mendaftarkan pasangan pola URL dan metode HTTP beserta pengendali (*handler*) yang bertugas mengeksekusinya.

### 2.2 Komparasi Pendekatan Routing Lintas Framework

Konsep *routing* terpusat merupakan standar baku pada arsitektur perangkat lunak web modern:

| Framework | Bahasa | Mekanisme Definisi Route |
| :--- | :--- | :--- |
| **Laravel** | PHP | `Route::get('/books', [BookController::class, 'index']);` pada `routes/web.php` |
| **Express.js** | JavaScript / Node.js | `app.get('/books', bookController.getAll);` atau `router.get(...)` |
| **Django** | Python | `path('books/', views.book_list, name='book_list')` pada `urls.py` |
| **Spring Boot** | Java | Anotasi `@GetMapping("/books")` pada tingkat metode *Controller* |

Semua ekosistem modern menyelesaikan tantangan yang sama: memetakan permintaan HTTP (*Request*) menuju blok logika yang tepat secara modular, terstruktur, dan mudah dipelihara.

### 2.3 Pintu Masuk Route: `routes/web.php` vs `routes/api.php`

Secara bawaan, Laravel memisahkan gerbang pendaftaran rute sesuai target konsumsi klien:

| Parameter | `routes/web.php` | `routes/api.php` |
| :--- | :--- | :--- |
| **Format Respons** | Dokumen HTML (dirender melalui Blade templating). | Data terstruktur (RESTful JSON). |
| **Manajemen Sesi** | *Stateful* (mendukung sesi berbasis *cookie* / *session driver*). | *Stateless* (autentikasi berbasis *token*, misal Laravel Sanctum). |
| **Proteksi CSRF** | Dilengkapi proteksi otomatis terhadap serangan *Cross-Site Request Forgery*. | Tanpa verifikasi token CSRF secara bawaan. |
| **Prefix URL** | Tanpa awalan bawaan (misal `/books`). | Otomatis diberi awalan `/api` (misal `/api/books`). |

> [!NOTE]
> Pada praktikum semester ini, kita fokus mengembangkan aplikasi web monolitik berbasis Blade pada berkas **`routes/web.php`**. Topik perancangan RESTful API menggunakan `routes/api.php` akan dibahas secara terpisah pada modul tingkat lanjut.

### 2.4 Metode HTTP dan *Method Spoofing*

Protokol HTTP mendefinisikan kata kerja semantik (*verb*) untuk membedakan operasi pada sumber daya (*resource*):

| HTTP Method | Makna Operasi | Contoh Kasus pada Sistem Perpustakaan |
| :--- | :--- | :--- |
| **GET** | Membaca / mengambil sumber daya tanpa mengubah status server. | Menampilkan katalog buku, menampilkan formulir pendaftaran. |
| **POST** | Mengirimkan data baru ke server untuk disimpan. | Menyimpan data buku baru, mendaftarkan anggota baru. |
| **PUT** / **PATCH** | Memperbarui data sumber daya yang sudah tersimpan sebelumnya. | Mengubah informasi buku atau memperbarui profil anggota. |
| **DELETE** | Menghapus sumber daya yang ditentukan dari sistem. | Menghapus berkas buku atau membatalkan reservasi. |

Elemen `<form>` standar pada peramban web (*web browser*) hanya mendukung metode `GET` dan `POST`. Untuk mengirimkan permintaan berjenis `PUT`, `PATCH`, atau `DELETE`, Laravel menyediakan mekanisme **Method Spoofing**.

Pada template Blade, Anda cukup menyisipkan direktif `@method()`:
```html
<form action="/books/1" method="POST">
    @csrf
    @method('PUT')
    <!-- Input form pembaruan buku -->
    <button type="submit">Simpan Perubahan</button>
</form>
```
Direktif tersebut akan menyisipkan sebuah elemen input tersembunyi:
```html
<input type="hidden" name="_method" value="PUT">
```
Saat permintaan diterima oleh Kernel Laravel, nilai `_method` akan dibaca dan dievaluasi seolah-olah permintaan asli dikirimkan menggunakan protokol HTTP `PUT`.

---

## 3. Fitur dan Mekanisme Routing di Laravel 12

### 3.1 Route Closure dan Route Parameter

Bentuk paling mendasar dari rute Laravel adalah *Closure*, yaitu fungsi anonim yang langsung dieksekusi saat URL diakses:

```php
// Rute Closure sederhana
Route::get('/tentang', function () {
    return 'Halaman Profil Sistem Perpustakaan Digital Kampus';
});
```

Rute sering kali membutuhkan masukan dinamis dari segmen URL, yang disebut **Route Parameter**:
- **Parameter Wajib (*Required*):** Dibungkus menggunakan tanda kurung kurawal `{param}`.
- **Parameter Opsional (*Optional*):** Ditandai dengan tanda tanya `{param?}` dan wajib memiliki nilai default pada penandatanganan fungsi closure/controller.

```php
// Parameter wajib
Route::get('/books/{id}', function (string $id) {
    return "Menampilkan data buku dengan ID: {$id}";
});

// Parameter opsional dengan nilai default
Route::get('/books/{id}/{slug?}', function (string $id, ?string $slug = null) {
    return "Buku ID: {$id} | Slug: " . ($slug ?? 'tanpa-slug');
});
```

### 3.2 Penamaan Route (*Route Naming*) dan Helper `route()`

Setiap definisi rute sangat disarankan memiliki identitas unik melalui metode `->name()`. Penamaan ini memisahkan URL fisik dengan pemanggilan rute di dalam kode:

```php
// Mendefinisikan nama rute
Route::get('/katalog-buku', [BookController::class, 'index'])->name('books.index');
```

Dengan penamaan rute, jika di kemudian hari URL diubah dari `/katalog-buku` menjadi `/books/catalog`, Anda tidak perlu merombak seluruh tautan pada berkas Blade maupun pengalihan (*redirect*) pada Controller:

```php
// Penggunaan pada Controller:
return redirect()->route('books.index');

// Penggunaan pada template Blade:
// <a href="{{ route('books.index') }}">Lihat Katalog Buku</a>
```

### 3.3 Pengelompokan Route (*Route Grouping*) dengan Prefix

Ketika beberapa rute memiliki karakteristik yang sama—seperti awalan URL (*prefix*), *namespace*, atau *middleware*—kita dapat mengelompokkannya ke dalam satu blok `Route::prefix()->group()`:

```php
Route::prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        return 'Panel Kendali Administrator';
    })->name('admin.dashboard');

    Route::get('/laporan', function () {
        return 'Laporan Sirkulasi Buku';
    })->name('admin.reports');
});
// Menghasilkan URL: /admin/dashboard dan /admin/laporan
```

### 3.4 Resource Route: 7 Rute Standar RESTful CRUD

Dalam aplikasi pengelolaan data seperti Sistem Perpustakaan, pola operasi CRUD (*Create, Read, Update, Delete*) selalu berulang. Laravel menyediakan pintasan deklaratif `Route::resource()` yang secara otomatis mendaftarkan **7 rute RESTful standar** hanya dalam satu baris kode:

```php
Route::resource('books', BookController::class);
```

Tujuh rute konvensi yang otomatis dihasilkan oleh baris di atas adalah:

| HTTP Method | URI | Metode Controller | Nama Rute (`name`) | Deskripsi Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/books` | `index` | `books.index` | Menampilkan seluruh katalog buku. |
| **GET** | `/books/create` | `create` | `books.create` | Menampilkan formulir input buku baru. |
| **POST** | `/books` | `store` | `books.store` | Memvalidasi dan menyimpan buku baru. |
| **GET** | `/books/{book}` | `show` | `books.show` | Menampilkan rincian lengkap satu buku. |
| **GET** | `/books/{book}/edit` | `edit` | `books.edit` | Menampilkan formulir penyuntingan buku. |
| **PUT/PATCH** | `/books/{book}` | `update` | `books.update` | Memperbarui data buku pada basis data. |
| **DELETE** | `/books/{book}` | `destroy` | `books.destroy` | Menghapus data buku dari sistem. |

### 3.5 Resource Route Parsial: `only()` dan `except()`

Tidak semua entitas data membutuhkan ketujuh metode CRUD secara utuh. Laravel menyediakan metode berantai (*method chaining*) untuk membatasi pendaftaran rute:
- **`except([...])`:** Mendaftarkan seluruh rute *kecuali* metode yang disebutkan.
- **`only([...])`:** Mendaftarkan *hanya* metode yang disebutkan.

```php
// Kasus 1: Kategori buku tidak memerlukan halaman detail mandiri (show)
Route::resource('categories', CategoryController::class)->except(['show']);

// Kasus 2: Rute publik untuk melihat katalog (hanya index dan show)
Route::resource('katalog', CatalogController::class)->only(['index', 'show']);
```

> [!IMPORTANT]
> **Perbedaan Status Galat 405 vs 404 pada Rute Parsial:**
> - Jika menggunakan `->except(['show'])`, pola URI `/categories/{category}` **masih terdaftar** di dalam sistem untuk metode `PUT`/`PATCH` (`categories.update`) dan `DELETE` (`categories.destroy`). Oleh karena itu, jika klien mengirimkan `GET /categories/1`, sistem akan mengembalikan status **`405 Method Not Allowed`** (karena URI dikenali, tetapi kata kerja HTTP `GET` tidak diizinkan).
> - Sebaliknya, jika seluruh metode untuk segmen URI tersebut dikecualikan (misal hanya memakai `->only(['index', 'create', 'store'])`), mengakses `GET /categories/1` akan langsung menghasilkan galat **`404 Not Found`** karena polanya memang tidak terdaftar sama sekali di router.

### 3.6 Pendaftaran Route Manual vs Resource Route

Kapan kita sebaiknya memilih `Route::resource()` dan kapan mendefinisikan rute secara manual?

| Karakteristik | `Route::resource()` | Rute Manual (`Route::get`, `Route::post`, dll.) |
| :--- | :--- | :--- |
| **Kesesuaian** | Sangat ideal untuk modul data master CRUD standar (`books`, `members`, `loans`). | Digunakan untuk alur khusus non-CRUD (misal `/loans/{id}/kembalikan`, autentikasi, unduh laporan PDF). |
| **Konsistensi** | Menjamin kepatuhan terhadap standar penamaan rute dan metode RESTful. | Fleksibilitas tinggi dalam kustomisasi nama rute dan segmen URI. |
| **Volume Kode** | Sangat ringkas (1 baris mewakili 7 rute). | Memerlukan pendefinisian eksplisit baris per baris. |

### 3.7 Inspeksi Route dengan `php artisan route:list`

Untuk memvalidasi dan mengaudit seluruh rute yang telah didaftarkan pada aplikasi, gunakan perintah Artisan:

```bash
php artisan route:list
```

Anda juga dapat menyaring daftar berdasarkan nama controller tertentu:
```bash
php artisan route:list --name=books
```

---

## 4. Siklus Hidup Permintaan HTTP (Request Lifecycle)

### 4.1 Alur Siklus Hidup Permintaan

Memahami alur perjalanan *Request* dari peramban hingga menjadi *Response* adalah kunci memahami arsitektur Laravel secara menyeluruh.

```text
+-------------------------------------------------------------------------+
|                          PERAMBAN WEB (CLIENT)                          |
+-------------------------------------------------------------------------+
                                     |
                              1. HTTP Request
                                     v
+-------------------------------------------------------------------------+
| public/index.php (Titik Masuk Tunggal / Single Entry Point)             |
| - Memuat Composer Autoloader (vendor/autoload.php)                      |
| - Menginisialisasi Bootstrap Aplikasi (bootstrap/app.php)               |
+-------------------------------------------------------------------------+
                                     |
                              2. Bootstrapping
                                     v
+-------------------------------------------------------------------------+
| HTTP Kernel / Application Context                                       |
| - Memuat Service Providers (Database, Routing, View, dll.)              |
| - Memuat Konfigurasi Lingkungan (.env) & Manajemen Error                |
+-------------------------------------------------------------------------+
                                     |
                              3. Pipeline Eksekusi
                                     v
+-------------------------------------------------------------------------+
| Lapisan Middleware                                                      |
| - Global Middleware & Route Middleware Group                            |
| - Pemeriksaan State Sesi, Enkripsi Cookie, dan Validasi Token CSRF      |
+-------------------------------------------------------------------------+
                                     |
                              4. Pencocokan Rute
                                     v
+-------------------------------------------------------------------------+
| Laravel Router (routes/web.php)                                         |
| - Evaluasi Pola URI & Validasi HTTP Method (GET, POST, PUT, DELETE)     |
| - Resolusi Parameter URL                                                |
+-------------------------------------------------------------------------+
                                     |
                              5. Eksekusi Aksi Pengendali
                                     v
+-------------------------------------------------------------------------+
| Controller / Handler Method                                             |
| (app/Http/Controllers/BookController.php, dsb.)                         |
| - Menjalankan Logika Bisnis Aplikasi                                    |
| - Mengembalikan Data Model / Merender Berkas Tampilan Blade             |
+-------------------------------------------------------------------------+
                                     |
                              6. Membentuk Respons
                                     v
+-------------------------------------------------------------------------+
| Objek HTTP Response (HTML / JSON / Redirect)                            |
+-------------------------------------------------------------------------+
                                     |
                              7. Mengirimkan Respons ke Klien
                                     v
+-------------------------------------------------------------------------+
|                          PERAMBAN WEB (CLIENT)                          |
|                       (Merender Tampilan Antarmuka)                     |
+-------------------------------------------------------------------------+
```

### 4.2 Middleware: Peran, Posisi, dan Kasus Penggunaan

**Middleware** bertindak sebagai mekanisme penyaring (*filter*) berlapis yang membungkus aplikasi. Setiap permintaan HTTP yang masuk harus melewati rangkaian pipa (*pipeline*) middleware sebelum tiba di Controller, dan setiap respons yang dihasilkan Controller kembali melewati middleware sebelum dikirimkan ke peramban.

Penerapan umum Middleware pada aplikasi web:
1. **Verifikasi Autentikasi (`auth`):** Memastikan pengguna telah masuk (*login*) sebelum diizinkan mengakses modul sirkulasi peminjaman buku.
2. **Pengalihan Tamu (`guest`):** Mengarahkan pengguna yang sudah login agar tidak membuka kembali halaman form login.
3. **Proteksi Keamanan (`csrf`):** Memvalidasi bahwa setiap pengiriman formulir dengan metode POST/PUT/DELETE berasal dari sesi sah pengguna, bukan manipulasi pihak ketiga (*Cross-Site Request Forgery*).

---

## 5. Langkah-Langkah Praktikum

### Prasyarat Praktikum
Pastikan Anda berada di direktori root proyek `app-perpustakaan` dan bekerja pada cabang pengembangan `dev`:

```bash
cd /home/ububu/app-perpustakaan
git checkout dev
```

> [!NOTE]
> Jika folder proyek `app-perpustakaan` berada langsung di `/home/ububu`, pastikan terminal Anda aktif pada direktori yang memuat berkas `artisan` dan folder `app/`.

---

### Langkah 1: Pembuatan Resource Controller

Jalankan perintah Artisan dengan flag `--resource` untuk mengenerate kerangka pengendali CRUD secara otomatis:

```bash
php artisan make:controller BookController --resource
php artisan make:controller CategoryController --resource
php artisan make:controller MemberController --resource
php artisan make:controller LoanController --resource
```

Perintah di atas akan membentuk 4 berkas Controller baru di dalam direktori `app/Http/Controllers/`.

---

### Langkah 2: Implementasi Respons Dummy pada Seluruh Controller

Karena lapisan Model dan Basis Data baru akan dipelajari pada pertemuan mendatang, kita akan mengisi setiap metode Controller dengan respons teks *dummy*. Tujuannya adalah memverifikasi keterhubungan rute dan pengendali secara akurat.

#### 1. Berkas: `app/Http/Controllers/BookController.php`

Buka berkas `app/Http/Controllers/BookController.php` dan pastikan kodenya seperti berikut:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index()
    {
        return 'BookController@index';
    }

    public function create()
    {
        return 'BookController@create';
    }

    public function store(Request $request)
    {
        return 'BookController@store';
    }

    public function show(string $id)
    {
        return "BookController@show, id: {$id}";
    }

    public function edit(string $id)
    {
        return "BookController@edit, id: {$id}";
    }

    public function update(Request $request, string $id)
    {
        return "BookController@update, id: {$id}";
    }

    public function destroy(string $id)
    {
        return "BookController@destroy, id: {$id}";
    }
}
```

#### 2. Berkas: `app/Http/Controllers/CategoryController.php`

Pada modul kategori, data kategori cukup ditampilkan pada tabel ringkasan dan tidak memiliki halaman rincian tunggal. Oleh karena itu, **metode `show()` sengaja dihilangkan**:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return 'CategoryController@index';
    }

    public function create()
    {
        return 'CategoryController@create';
    }

    public function store(Request $request)
    {
        return 'CategoryController@store';
    }

    // Catatan: Metode show() sengaja ditiadakan karena kategori tidak memiliki halaman rincian terpisah

    public function edit(string $id)
    {
        return "CategoryController@edit, id: {$id}";
    }

    public function update(Request $request, string $id)
    {
        return "CategoryController@update, id: {$id}";
    }

    public function destroy(string $id)
    {
        return "CategoryController@destroy, id: {$id}";
    }
}
```

#### 3. Berkas: `app/Http/Controllers/MemberController.php`

Buka berkas `app/Http/Controllers/MemberController.php` dan sesuaikan kodenya:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index()
    {
        return 'MemberController@index';
    }

    public function create()
    {
        return 'MemberController@create';
    }

    public function store(Request $request)
    {
        return 'MemberController@store';
    }

    public function show(string $id)
    {
        return "MemberController@show, id: {$id}";
    }

    public function edit(string $id)
    {
        return "MemberController@edit, id: {$id}";
    }

    public function update(Request $request, string $id)
    {
        return "MemberController@update, id: {$id}";
    }

    public function destroy(string $id)
    {
        return "MemberController@destroy, id: {$id}";
    }
}
```

#### 4. Berkas: `app/Http/Controllers/LoanController.php`

Khusus transaksi peminjaman, kita membutuhkan satu aksi kustom di luar 7 metode resource standar, yaitu aksi pengembalian buku (`kembalikan`):

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index()
    {
        return 'LoanController@index';
    }

    public function create()
    {
        return 'LoanController@create';
    }

    public function store(Request $request)
    {
        return 'LoanController@store';
    }

    public function show(string $id)
    {
        return "LoanController@show, id: {$id}";
    }

    public function edit(string $id)
    {
        return "LoanController@edit, id: {$id}";
    }

    public function update(Request $request, string $id)
    {
        return "LoanController@update, id: {$id}";
    }

    public function destroy(string $id)
    {
        return "LoanController@destroy, id: {$id}";
    }

    /**
     * Aksi kustom non-resource untuk pemrosesan pengembalian buku pinjaman.
     */
    public function kembalikan(string $id)
    {
        return "LoanController@kembalikan, id: {$id}";
    }
}
```

---

### Langkah 3: Pendaftaran Route pada `routes/web.php`

Buka berkas `routes/web.php` dan perbarui seluruh isinya menjadi seperti berikut:

```php
<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

// Halaman utama
Route::get('/', function () {
    return view('welcome');
});

// Pendaftaran Resource Route CRUD
Route::resource('books', BookController::class);
Route::resource('categories', CategoryController::class)->except(['show']);
Route::resource('members', MemberController::class);
Route::resource('loans', LoanController::class);

// Rute kustom pemrosesan sirkulasi pengembalian buku
Route::put('/loans/{id}/kembalikan', [LoanController::class, 'kembalikan'])
    ->name('loans.kembalikan');
```

> [!TIP]
> Perhatikan bahwa rute kustom `loans.kembalikan` diletakkan setelah pendefinisian `Route::resource('loans', ...)`. Penempatan ini aman dari tumpang-tindih (*conflict*) karena segmen URI `/loans/{id}/kembalikan` bersifat spesifik dan tidak bertabrakan dengan pola rute bawaan resource.

---

### Langkah 4: Verifikasi Daftar Route Menggunakan CLI

Untuk memastikan bahwa seluruh 28 rute kustom aplikasi telah didaftarkan dengan benar, jalankan perintah:

```bash
php artisan route:list
```

**Evaluasi Hasil Keluaran Terminal:**
- Modul `books`: terdaftar 7 rute (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`).
- Modul `categories`: terdaftar **6 rute** (rute `categories.show` tidak terdaftar karena telah dikecualikan via `except`).
- Modul `members`: terdaftar 7 rute.
- Modul `loans`: terdaftar 7 rute resource standar ditambah **1 rute kustom** (`PUT loans/{id}/kembalikan`).
- Total: Terdapat 28 endpoint spesifik aplikasi perpustakaan yang terdaftar secara resmi.

---

### Langkah 5: Uji Coba Route pada Web Browser

Nyalakan *development server* Laravel:

```bash
php artisan serve
```

Buka peramban web dan uji titik akhir berikut secara berurutan:

| URL yang Diuji | Metode HTTP | Ekspektasi Respons Tampilan |
| :--- | :--- | :--- |
| `http://127.0.0.1:8000/books` | GET | Menampilkan teks: `BookController@index` |
| `http://127.0.0.1:8000/categories` | GET | Menampilkan teks: `CategoryController@index` |
| `http://127.0.0.1:8000/members` | GET | Menampilkan teks: `MemberController@index` |
| `http://127.0.0.1:8000/loans` | GET | Menampilkan teks: `LoanController@index` |
| `http://127.0.0.1:8000/books/5` | GET | Menampilkan teks: `BookController@show, id: 5` |
| `http://127.0.0.1:8000/categories/5` | GET | Menampilkan halaman galat **405 Method Not Allowed** |

> [!NOTE]
> Tampilan halaman galat **405 Method Not Allowed** saat mengakses `http://127.0.0.1:8000/categories/5` adalah **perilaku yang benar dan diharapkan**. Hal ini membuktikan bahwa metode `GET` untuk rute rincian kategori memang telah berhasil dinonaktifkan dari router, sementara URI tersebut hanya dialokasikan untuk operasi `PUT` (*update*) dan `DELETE` (*destroy*).

---

## 6. Checkpoint Git Praktikum

Setelah menyelesaikan seluruh langkah di atas dan memverifikasi fungsionalitas rute, lakukan komit dan dorong pembaruan ke repositori GitHub pada cabang `dev`:

```bash
git status
git add .
git commit -m "[P-2] setup routing resource semua tabel"
git push origin dev
```

---

## 7. Tugas Mandiri

Untuk memperdalam pemahaman mengenai pengelompokan rute (*Route Grouping*), kerjakan latihan mandiri berikut:
1. Buka berkas `routes/web.php`.
2. Tambahkan satu blok *Route Grouping* baru menggunakan prefix `/admin` yang memuat minimal 2 rute *closure*:
   - URL `/admin/dashboard`: Mengembalikan respons teks `"Selamat Datang di Dashboard Admin"`.
   - URL `/admin/profil`: Mengembalikan respons teks `"Halaman Profil Administrator"`.
3. Beri nama masing-masing rute tersebut (`admin.dashboard` dan `admin.profil`).
4. Jalankan perintah `php artisan route:list --path=admin` dan dokumentasikan hasil keluaran terminalnya.

**Keluaran Tugas:**
- Tautan komit GitHub pada cabang `dev` yang memuat kode penambahan *route group*.
- Tangkapan layar (*screenshot*) atau salinan teks dari perintah `php artisan route:list --path=admin`.

---

## Navigasi Modul
- [← Pertemuan 01: Framework, Konsep MVC, dan Setup Proyek Laravel 12](./pertemuan-01.md)
- [Daftar Isi Modul](./README.md)
- [Pertemuan 03: Blade Templating dan Pembuatan Layout Admin](./pertemuan-03.md) &rarr;
