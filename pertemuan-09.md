# Pertemuan 9: RESTful API Dasar dan Format Respons JSON di Laravel 12

> **Informasi Modul:**
> - **Prasyarat:** Telah menyelesaikan Pertemuan 8 (autentikasi pengguna berbasis sesi dan middleware hak akses peran `admin`/`petugas` pada proyek `app-perpustakaan`).
> - **Fokus Pembelajaran:** Membangun antarmuka pemrograman aplikasi berbasis REST (*Representational State Transfer*) dengan dedicated API Controller di `app/Http/Controllers/Api/` untuk sumber daya `books`, `members`, `loans`, dan `stats`; memformat respons data terstruktur menggunakan API Resource (`JsonResource`); menangani paginasi data dan filter parameter kueri (*query parameters*); serta memvalidasi keandalan endpoint menggunakan Postman.

---

## 1. Capaian Pembelajaran

Setelah menyelesaikan modul pertemuan ini, mahasiswa diharapkan mampu:
1. Memahami arsitektur REST API, sifat *stateless*, serta perbedaan mendasarnya dengan aplikasi web berbasis perenderan peladen (*server-rendered application*).
2. Memahami peran format JSON (*JavaScript Object Notation*) dan semantik kode status HTTP (*HTTP Status Codes*) sebagai standar *de facto* pertukaran data antarsistem.
3. Mengonfigurasi dan mendaftarkan berkas perutean `routes/api.php` dengan prefiks otomatis `/api` pada `bootstrap/app.php` di Laravel 12.
4. Mengembangkan pengendali khusus API (*Dedicated API Controller*) yang mengembalikan respons data murni tanpa memanggil fungsi *view* template HTML.
5. Menerapkan lapisan transformasi data terstandar menggunakan *API Resource* (`JsonResource`) untuk menyaring data sensitif dan menyusun format payload JSON yang konsisten.
6. Mengimplementasikan paginasi (*pagination*) dan filter pencarian (*query parameters*) pada endpoint koleksi API.
7. Melakukan pengujian integrasi endpoint API secara menyeluruh menggunakan Postman (mencakup metode GET, POST, PUT, DELETE, verifikasi status code sukses 200/201, serta penanganan galat validasi 422 dan 404).

---

## 2. Konsep Dasar REST API dan Arsitektur Klien-Peladen

### 2.1 Mengapa REST API Dibutuhkan? (Server-Rendered vs Headless / API-Driven)

Hingga Pertemuan 8, aplikasi perpustakaan beroperasi sebagai aplikasi web monolitik berkonsep *server-rendered*: peramban web meminta halaman, Laravel merender tampilan Blade menjadi dokumen HTML utuh, dan peramban menampilkan visualisasi tersebut.

Namun, arsitektur perangkat lunak modern menuntut integrasi dengan beragam platform klien yang tidak membutuhkan dokumen HTML:
- **Aplikasi Seluler (Mobile Apps):** Aplikasi Android/iOS berbasis Flutter, React Native, atau Kotlin/Swift yang memiliki mesin perender antarmuka *native* sendiri.
- **Antarmuka Frontend Modern (SPA):** Single Page Application berbasis Vue, React, atau Angular.
- **Sistem Eksternal / Pihak Ketiga:** Dashboard analitik kampus, sistem perpustakaan nasional, atau layanan otomatisasi yang hanya membutuhkan data mentah (*raw data*).
- **Komponen Internal Aplikasi:** Komponen antarmuka (seperti grafik analitik pada Pertemuan 10) yang mengambil data via JavaScript (*Fetch/Axios*) tanpa memuat ulang seluruh halaman (*page reload*).

HTML dirancang agar peramban memahami tata letak visual, sedangkan sistem-sistem di atas hanya membutuhkan data terstruktur berbobot ringan yang mudah diparse secara programatik. Format **JSON (*JavaScript Object Notation*)** menjadi standar universal untuk tujuan ini.

| Karakteristik | Aplikasi Web Konvensional (*Server-Rendered*) | Arsitektur RESTful API (*Data-Driven*) |
| :--- | :--- | :--- |
| **Bentuk Respons** | Dokumen HTML lengkap beserta CSS/JS siap tampil. | Data terstruktur murni dalam format JSON. |
| **Konsumen Utama** | Peramban web (*web browser*). | Beragam platform klien (Mobile, SPA, IoT, Service Eksternal). |
| **Kopling Tampilan** | Sangat erat (*tightly coupled*) dengan template Blade di server. | Terpisah (*decoupled* / *headless*); klien mengatur tampilan sendiri. |
| **Beban Bandwidth** | Lebih besar karena membawa markup presentasi visual. | Minimal dan efisien karena hanya membawa payload data mentah. |
| **Manajemen Status** | Berbasis sesi peladen (*stateful session* via cookie). | Bersifat tanpa status (*stateless*); tiap permintaan mandiri. |

```text
+------------------------------------------------------------------------+
|                          KLIEN MULTI-PLATFORM                          |
|  [Browser Desktop]     [Mobile App (Android/iOS)]     [Postman / cURL] |
+------------------------------------------------------------------------+
                                     │
                 Permintaan HTTP     │  GET /api/books
                 (JSON / Query Params)│  Accept: application/json
                                     ▼
+------------------------------------------------------------------------+
|                          LARAVEL 12 BACKEND                            |
|                                                                        |
|  routes/api.php  ──>  Api\BookController  ──>  Eloquent Model (Book)   |
|                               │                        │               |
|                               ▼                        ▼               |
|                       BookResource (JSON)      Basis Data MySQL        |
|                                                                        |
+------------------------------------------------------------------------+
                                     │
                 Respons HTTP        │  Status: 200 OK
                 (Payload JSON)      │  Content-Type: application/json
                                     ▼
                    {"data": [{"id": 1, "judul": "..."}]}
```

### 2.2 Prinsip-Prinsip Inti Arsitektur REST

REST (*Representational State Transfer*) dirumuskan oleh Roy Fielding sebagai serangkaian konvensi arsitektur terdistribusi:

1. **Ketanpastatusan (*Statelessness*):**  
   Setiap permintaan dari klien harus membawa seluruh data dan konteks yang dibutuhkan peladen untuk memprosesnya. Peladen tidak boleh mengandalkan "ingatan" atau status sesi dari permintaan sebelumnya. Jika sesi login web mengandalkan session cookie di server, REST API mengandalkan kredensial atau parameter yang dikirim eksplisit di setiap request.
2. **URI Berbasis Sumber Daya (*Resource-Based URI*):**  
   URL harus merepresentasikan objek/kata benda (*noun*), bukan tindakan/kata kerja (*verb*):
   - *Benar (RESTful):* `GET /api/books`, `DELETE /api/books/1`
   - *Salah (RPC style):* `GET /api/getBooks`, `POST /api/deleteBook?id=1`
3. **Penggunaan Kata Kerja HTTP Standar (*Standard HTTP Verbs*):**  
   Tindakan terhadap sumber daya ditentukan melalui metode HTTP (*HTTP Method*):
   - `GET`: Membaca atau mengambil representasi sumber daya. Operasi ini bersifat aman (*safe*) dan idempoten (*idempotent*).
   - `POST`: Membuat sumber daya baru di peladen. Tidak idempoten (memanggilnya dua kali menghasilkan dua rekaman baru).
   - `PUT` / `PATCH`: Mengganti atau memperbarui representasi sumber daya yang ada. Bersifat idempoten.
   - `DELETE`: Menghapus sumber daya dari peladen. Bersifat idempoten.
4. **Antarmuka Seragam dan Keterprediksian (*Uniform & Predictable Interface*):**  
   Begitu klien memahami bahwa `GET /api/books` mengembalikan koleksi buku, klien dapat secara intuitif menebak bahwa `GET /api/books/{id}` mengambil rincian satu buku dan `DELETE /api/books/{id}` menghapusnya.

Prinsip-prinsip ini bersifat agnostik framework (*framework-agnostic*). Baik menggunakan Laravel (PHP), Express.js (Node.js), Django REST Framework (Python), maupun Spring Boot (Java), pola desain yang diterapkan tetap identik karena tujuan yang dicapai sama: interoperabilitas antarsistem.

### 2.3 Peran dan Semantik Kode Status HTTP (*HTTP Status Codes*)

Kode status HTTP merupakan bagian integral dari bahasa komunikasi REST API. Klien API yang tangguh (termasuk pustaka HTTP klien pada aplikasi mobile atau Postman) selalu mengevaluasi kode status terlebih dahulu sebelum mengurai badan respons (*response body*).

| Kategori | Kode Status | Frasa Alasan (*Reason Phrase*) | Arti & Kapan Digunakan dalam REST API |
| :--- | :--- | :--- | :--- |
| **2xx: Sukses** | `200` | `OK` | Permintaan berhasil diproses untuk operasi `GET`, `PUT`, atau respons data umum. |
| | `201` | `Created` | Sumber daya baru berhasil diciptakan di peladen (standar mutlak untuk operasi `POST`). |
| | `204` | `No Content` | Aksi berhasil dieksekusi tetapi peladen sengaja tidak mengirimkan isi badan (*body*) respons. |
| **4xx: Galat Klien** | `400` | `Bad Request` | Permintaan tidak dapat dipahami karena kesalahan sintaksis atau struktur data rusak. |
| | `401` | `Unauthorized` | Klien belum diautentikasi (kredensial identitas hilang atau tidak valid). |
| | `403` | `Forbidden` | Klien terautentikasi tetapi tidak memiliki hak akses terhadap sumber daya yang diminta. |
| | `404` | `Not Found` | Sumber daya (URI endpoint atau ID rekaman data) tidak ditemukan di peladen. |
| | `422` | `Unprocessable Entity` | Format payload valid, tetapi gagal melewati aturan validasi bisnis (field kosong, tipe data salah). |
| **5xx: Galat Server**| `500` | `Internal Server Error` | Terjadi kegagalan internal atau pengecualian (*exception*) tak tertangani pada sistem peladen. |

---

## 3. Fitur Inti REST API dan Transformasi Data di Laravel 12

### 3.1 Berkas Rute `routes/api.php` dan Pendaftaran pada `bootstrap/app.php`

Pada Laravel 12 yang mengadopsi arsitektur *slim application skeleton*, berkas `routes/api.php` tidak lagi dibuat secara otomatis di awal pembuatan proyek. Pengembang mendaftarkannya secara manual melalui rantai konfigurasi perutean pada berkas `bootstrap/app.php`:

```php
// Contoh ilustrasi konfigurasi pada bootstrap/app.php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
```

Rute yang terdaftar pada `routes/api.php` memiliki dua karakteristik bawaan:
1. **Otomatis Berprefiks `/api`:** Rute yang didefinisikan sebagai `Route::get('/books', ...)` akan dapat diakses secara publik melalui URI `/api/books`.
2. **Grup Middleware `api`:** Terbebas dari middleware berbasis sesi seperti enkripsi *cookie*, manajemen sesi peramban, dan verifikasi token CSRF web.

### 3.2 Karakteristik Dedicated API Controller

Berbeda dengan pengendali web konvensional yang memanggil fungsi perenderan template `return view('...')`, pengendali API mengembalikan data murni (*raw data*):

```php
// Contoh ilustrasi konsep dasar API Controller
class BookController extends Controller
{
    public function index()
    {
        return Book::all(); // Laravel otomatis mengonversi Collection menjadi string JSON
    }
}
```

Ketika nilai kembalian (*return value*) sebuah Controller berupa Model, Collection, atau array, Laravel secara otomatis mendeteksinya, mengonversinya menjadi string format JSON melalui fungsi serialisasi internal, serta menyematkan header respons HTTP `Content-Type: application/json`.

### 3.3 Transformasi Data dengan API Resource (`JsonResource`)

Meskipun `return Book::all()` bekerja secara fungsional, pendekatan tersebut memiliki kelemahan arsitektural yang serius:
1. **Kebocoran Data Sensitif (*Data Leakage*):** Seluruh kolom basis data terekspos apa adanya. Jika diterapkan pada model `User`, atribut sensitif seperti hash kata sandi (*password*) atau token rahasia dapat bocor ke publik.
2. **Keterikatan Skema Basis Data (*Database Schema Coupling*):** Nama field pada respons JSON menjadi identik dengan nama kolom fisik pada tabel database. Jika nama kolom diubah saat migrasi, aplikasi klien eksternal akan langsung mengalami kerusakan (*breaking changes*).
3. **Format Relasi yang Kurang Rapi:** Relasi data antartabel sulit disajikan dalam struktur bersarang (*nested structure*) yang terstandarisasi.

Untuk mengatasi masalah di atas, Laravel menyediakan **API Resource (`JsonResource`)** sebagai lapisan transformasi eksplisit antara model Eloquent dan respons JSON:

```bash
php artisan make:resource BookResource
```

```php
// Contoh ilustrasi kelas Resource
class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'category' => [
                'id' => $this->category?->id,
                'nama_kategori' => $this->category?->nama_kategori,
            ],
        ];
    }
}
```

Metode `toArray()` menjadi gerbang penentu: field mana saja yang diizinkan keluar ke publik, bagaimana tipe datanya diformat, dan bagaimana struktur relasi Eloquent (`$this->category`) ditransformasikan menjadi objek bertingkat yang rapi.

### 3.4 Paginasi Data dan Pembungkus Koleksi (`ResourceCollection`)

Untuk menyajikan banyak rekaman data sekaligus, API Resource menyediakan metode statis `collection()`:

```php
// Contoh ilustrasi penggunaan Resource Collection dengan Paginasi
public function index()
{
    $books = Book::with('category')->paginate(10);

    return BookResource::collection($books);
}
```

Ketika objek paginator Laravel (`paginate()`) dilewatkan ke dalam `BookResource::collection()`, Laravel secara otomatis menyusun struktur payload JSON standar dengan format pembungkus (*envelope*):

```json
{
  "data": [
    { "id": 1, "judul": "Pemrograman Web Modern", "category": { ... } }
  ],
  "links": {
    "first": "http://127.0.0.1:8000/api/books?page=1",
    "last": "http://127.0.0.1:8000/api/books?page=5",
    "prev": null,
    "next": "http://127.0.0.1:8000/api/books?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 10,
    "to": 10,
    "total": 50
  }
}
```

Format ini memberikan informasi navigasi lengkap kepada aplikasi klien tanpa mengharuskan klien menghitung jumlah halaman secara manual.

---

## 4. Langkah-Langkah Praktikum

> [!IMPORTANT]
> **Konteks Praktikum:**  
> Praktikum ini melanjutkan repositori `app-perpustakaan` dari Pertemuan 8. Pastikan Anda berada pada cabang kerja `dev`.
>
> **Status Proteksi Endpoint:**  
> Middleware `auth` yang dibangun pada Pertemuan 8 berbasis sesi peramban dan tidak otomatis berlaku untuk rute API. Otentikasi API berbasis token (seperti *Laravel Sanctum*) merupakan topik tingkat lanjut terpisah. Oleh karena itu, seluruh 11 endpoint pada modul ini **sengaja dibiarkan bersifat publik** agar fokus pembelajaran terpusat pada perancangan arsitektur REST, penanganan rute, dan transformasi serialisasi data JSON.

Pastikan cabang aktif Anda adalah `dev` sebelum memulai pekerjaan:

```bash
git checkout dev
```

---

### Langkah 1: Mendaftarkan Routing API (`bootstrap/app.php` dan `routes/api.php`)

Karena Laravel 12 tidak menyediakan `routes/api.php` secara bawaan, daftarkan rute API pada berkas `bootstrap/app.php`:

```php
// File: bootstrap/app.php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\CheckAdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

Buat berkas baru bernama `routes/api.php` pada direktori root rute aplikasi dan daftarkan 11 endpoint yang diperlukan:

```php
// File: routes/api.php
<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Proyek Perpustakaan
|--------------------------------------------------------------------------
|
| Seluruh rute di bawah ini otomatis mendapatkan prefiks URI '/api'
| dan dinaungi oleh middleware group 'api'.
|
*/

// 1. Endpoint Sumber Daya Buku (CRUD Lengkap)
Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{id}', [BookController::class, 'show']);
Route::post('/books', [BookController::class, 'store']);
Route::put('/books/{id}', [BookController::class, 'update']);
Route::delete('/books/{id}', [BookController::class, 'destroy']);

// 2. Endpoint Sumber Daya Anggota (Read-Only)
Route::get('/members', [MemberController::class, 'index']);
Route::get('/members/{id}', [MemberController::class, 'show']);

// 3. Endpoint Sumber Daya Peminjaman Buku
Route::get('/loans', [LoanController::class, 'index']);
Route::post('/loans', [LoanController::class, 'store']);
Route::put('/loans/{id}/kembalikan', [LoanController::class, 'kembalikan']);

// 4. Endpoint Statistik Ringkas Sistem
Route::get('/stats', [StatsController::class, 'index']);
```

Verifikasi pendaftaran rute melalui terminal untuk memastikan seluruh 11 endpoint telah terbaca dengan prefiks `/api`:

```bash
php artisan route:list --path=api
```

---

### Langkah 2: Pembuatan API Controller dan API Resource melalui Artisan

Jalankan serangkaian perintah Artisan untuk membuat 4 berkas API Controller di dalam subdirektori `Api/` serta 3 berkas API Resource:

```bash
php artisan make:controller Api/BookController
php artisan make:controller Api/MemberController
php artisan make:controller Api/LoanController
php artisan make:controller Api/StatsController

php artisan make:resource BookResource
php artisan make:resource MemberResource
php artisan make:resource LoanResource
```

> [!NOTE]
> Struktur pengendali API dipisahkan ke dalam subdirektori `app/Http/Controllers/Api/` untuk menjaga kerapian kode (*separation of concerns*) agar tidak bercampur dengan pengendali tampilan web konvensional.

---

### Langkah 3: Implementasi Kelas API Resource (`BookResource`, `MemberResource`, `LoanResource`)

Lapisan Resource bertugas menyaring atribut model Eloquent dan membentuk struktur JSON yang konsisten.

Buka berkas `app/Http/Resources/BookResource.php` dan sesuaikan kodenya:

```php
// File: app/Http/Resources/BookResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'penulis' => $this->penulis,
            'penerbit' => $this->penerbit,
            'tahun_terbit' => $this->tahun_terbit,
            'isbn' => $this->isbn,
            'stok' => $this->stok,
            'sampul' => $this->sampul,
            'category' => [
                'id' => $this->category?->id,
                'nama_kategori' => $this->category?->nama_kategori,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

Buka berkas `app/Http/Resources/MemberResource.php` dan sesuaikan kodenya:

```php
// File: app/Http/Resources/MemberResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'nim' => $this->nim,
            'email' => $this->email,
            'nomor_telepon' => $this->nomor_telepon,
            'alamat' => $this->alamat,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

Buka berkas `app/Http/Resources/LoanResource.php`. Pada resource ini, relasi `member`, `user` (petugas pencatat), serta item transaksi `loanItems.book` ditransformasikan menjadi representasi bersarang:

```php
// File: app/Http/Resources/LoanResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member' => [
                'id' => $this->member?->id,
                'nama' => $this->member?->nama,
            ],
            'petugas' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'tanggal_pinjam' => $this->tanggal_pinjam,
            'tanggal_kembali' => $this->tanggal_kembali,
            'tanggal_dikembalikan' => $this->tanggal_dikembalikan,
            'status' => $this->status,
            'books' => $this->loanItems->map(fn ($item) => [
                'id' => $item->book?->id,
                'judul' => $item->book?->judul,
            ]),
        ];
    }
}
```

---

### Langkah 4: Implementasi Controller Buku (`app/Http/Controllers/Api/BookController.php`)

Buka `app/Http/Controllers/Api/BookController.php` dan lengkapi kelima metode CRUD:
1. `index()`: Mendukung filter kueri pencarian sebagian (`?judul=`) dan kecocokan kategori (`?category_id=`), serta paginasi 10 item per halaman.
2. `store()`: Menggunakan kembali aturan `StoreBookRequest` dari Pertemuan 3 dan menetapkan status kode `201 Created` secara eksplisit melalui `->response()->setStatusCode(201)`.
3. `show()`: Menampilkan satu buku beserta relasi kategorinya melalui `findOrFail()`.
4. `update()`: Melakukan validasi inline data masukan dan memperbarui entitas buku.
5. `destroy()`: Menghapus data buku dan mengembalikan konfirmasi JSON.

```php
// File: app/Http/Controllers/Api/BookController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * Menampilkan daftar buku dengan dukungan filter pencarian dan paginasi.
     */
    public function index(Request $request)
    {
        $query = Book::with('category');

        if ($request->filled('judul')) {
            $query->where('judul', 'like', '%'.$request->query('judul').'%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        $books = $query->paginate(10);

        return BookResource::collection($books);
    }

    /**
     * Menyimpan data buku baru dengan validasi StoreBookRequest.
     */
    public function store(StoreBookRequest $request)
    {
        $book = Book::create($request->validated());

        return (new BookResource($book->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Menampilkan detail satu buku berdasarkan ID.
     */
    public function show(string $id)
    {
        $book = Book::with('category')->findOrFail($id);

        return new BookResource($book);
    }

    /**
     * Memperbarui informasi buku berdasarkan ID.
     */
    public function update(Request $request, string $id)
    {
        $book = Book::findOrFail($id);

        $validated = $request->validate([
            'judul' => 'required|string|max:200',
            'penulis' => 'required|string|max:100',
            'penerbit' => 'required|string|max:100',
            'tahun_terbit' => 'required|integer|min:1900|max:'.date('Y'),
            'isbn' => 'nullable|string|max:20',
            'stok' => 'required|integer|min:0',
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        $book->update($validated);

        return new BookResource($book->load('category'));
    }

    /**
     * Menghapus rekaman buku berdasarkan ID.
     */
    public function destroy(string $id)
    {
        $book = Book::findOrFail($id);
        $book->delete();

        return response()->json(['message' => 'Buku berhasil dihapus.']);
    }
}
```

---

### Langkah 5: Implementasi Controller Anggota (`app/Http/Controllers/Api/MemberController.php`)

Buka `app/Http/Controllers/Api/MemberController.php`. Pada pertemuan ini, data anggota disajikan secara *read-only* (hanya indeks terpaginasi dan detail anggota tunggal):

```php
// File: app/Http/Controllers/Api/MemberController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;

class MemberController extends Controller
{
    /**
     * Menampilkan daftar anggota perpustakaan secara terpaginasi.
     */
    public function index()
    {
        $members = Member::paginate(10);

        return MemberResource::collection($members);
    }

    /**
     * Menampilkan detail satu anggota perpustakaan berdasarkan ID.
     */
    public function show(string $id)
    {
        $member = Member::findOrFail($id);

        return new MemberResource($member);
    }
}
```

---

### Langkah 6: Implementasi Controller Transaksi Peminjaman (`app/Http/Controllers/Api/LoanController.php`)

Buka `app/Http/Controllers/Api/LoanController.php` dan implementasikan 3 aksi utama:
1. `index()`: Mengambil daftar peminjaman dengan eager loading `['member', 'user', 'loanItems.book']` untuk mencegah masalah kueri ganda (*N+1 Query Problem*).
2. `store()`: Mencatat transaksi peminjaman baru. Perhatikan bahwa karena endpoint ini bersifat tanpa sesi autentikasi web, ID petugas (`user_id`) wajib dikirim secara eksplisit pada payload request.
3. `kembalikan()`: Memproses pengembalian buku dengan memperbarui kolom `status` menjadi `'dikembalikan'` dan mencatat tanggal pengembalian hari ini (`now()->toDateString()`).

> [!TIP]
> **Alasan Teknis Pemanggilan `$loan->refresh()`:**  
> Pemanggilan `$loan = Loan::create(...)` hanya mengembalikan objek dalam memori yang belum memuat nilai bawaan (*default value*) kolom `status` (`'dipinjam'`) yang ditentukan di tingkat basis data. Tanpa pemanggilan `$loan->refresh()`, payload respons pertama setelah proses `POST` akan menghasilkan `status: null` meskipun basis data telah menyimpannya dengan benar. Metode `refresh()` memuat ulang baris rekaman segar dari basis data sebelum dilewatkan ke `LoanResource`.

```php
// File: app/Http/Controllers/Api/LoanController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    /**
     * Menampilkan riwayat peminjaman buku dengan eager loading relasi.
     */
    public function index()
    {
        $loans = Loan::with(['member', 'user', 'loanItems.book'])->paginate(10);

        return LoanResource::collection($loans);
    }

    /**
     * Mencatat transaksi peminjaman buku multi-item baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|integer|exists:members,id',
            'user_id' => 'required|integer|exists:users,id',
            'tanggal_pinjam' => 'required|date',
            'tanggal_kembali' => 'required|date|after_or_equal:tanggal_pinjam',
            'book_ids' => 'required|array|min:1',
            'book_ids.*' => 'integer|exists:books,id',
        ], [
            'member_id.required' => 'Anggota wajib dipilih.',
            'user_id.required' => 'Petugas (user_id) wajib diisi.',
            'user_id.exists' => 'Petugas yang dipilih tidak valid.',
            'tanggal_pinjam.required' => 'Tanggal pinjam wajib diisi.',
            'tanggal_kembali.required' => 'Tanggal kembali wajib diisi.',
            'tanggal_kembali.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
            'book_ids.required' => 'Pilih minimal satu buku.',
        ]);

        $loan = Loan::create([
            'member_id' => $validated['member_id'],
            'user_id' => $validated['user_id'],
            'tanggal_pinjam' => $validated['tanggal_pinjam'],
            'tanggal_kembali' => $validated['tanggal_kembali'],
        ]);

        foreach ($validated['book_ids'] as $bookId) {
            $loan->loanItems()->create(['book_id' => $bookId]);
        }

        // Muat ulang rekaman dari database agar nilai default kolom ikut terbawa ke respons
        $loan->refresh()->load(['member', 'user', 'loanItems.book']);

        return (new LoanResource($loan))->response()->setStatusCode(201);
    }

    /**
     * Memproses pengembalian buku dan mengubah status transaksi.
     */
    public function kembalikan(string $id)
    {
        $loan = Loan::with(['member', 'user', 'loanItems.book'])->findOrFail($id);

        $loan->update([
            'status' => 'dikembalikan',
            'tanggal_dikembalikan' => now()->toDateString(),
        ]);

        return new LoanResource($loan);
    }
}
```

---

### Langkah 7: Implementasi Controller Statistik Ringkas (`app/Http/Controllers/Api/StatsController.php`)

Buka `app/Http/Controllers/Api/StatsController.php`. Endpoint ini tidak merepresentasikan satu model tertentu, melainkan kumpulan metrik agregat. Oleh karena itu, kita cukup memanfaatkan `response()->json()` secara langsung tanpa perlu membungkusnya dengan API Resource:

```php
// File: app/Http/Controllers/Api/StatsController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;

class StatsController extends Controller
{
    /**
     * Menampilkan data statistik ringkas sistem perpustakaan.
     */
    public function index()
    {
        return response()->json([
            'total_buku' => Book::count(),
            'total_anggota' => Member::count(),
            'peminjaman_aktif' => Loan::where('status', 'dipinjam')->count(),
        ]);
    }
}
```

> [!NOTE]
> Endpoint `/api/stats` ini akan dikonsumsi oleh antarmuka Blade pada Pertemuan 10 untuk menampilkan kartu ringkasan analitik perpustakaan.

---

### Langkah 8: Pengujian dan Verifikasi Endpoint Menggunakan Postman

Nyalakan server pengembangan lokal Laravel:

```bash
php artisan serve
```

Buka aplikasi **Postman** dan siapkan lingkungan pengujian:
1. Buat koleksi baru dengan nama `app-perpustakaan`.
2. Tetapkan variabel lingkungan atau langsung gunakan basis URL: `http://127.0.0.1:8000/api`.
3. Tambahkan header pada setiap permintaan:
   - `Accept: application/json` (Wajib, agar seluruh galat validasi dikembalikan dalam bentuk payload JSON bukan pengalihan HTML).
   - `Content-Type: application/json` (Untuk permintaan dengan metode `POST` dan `PUT`).

Lakukan 13 skenario pengujian berikut secara berurutan:

| No | Metode | Endpoint URL | Parameter / Payload Body | Status Diharapkan | Catatan Validasi |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | `GET` | `/books` | - | `200 OK` | Memverifikasi struktur pembungkus `data`, `links`, dan `meta`. Relasi `category` bertingkat rapi. |
| 2 | `GET` | `/books?judul=laskar` | Query param: `judul` | `200 OK` | Hanya menampilkan buku yang memuat kata kunci pada judulnya. |
| 3 | `GET` | `/books?category_id=2` | Query param: `category_id` | `200 OK` | Hanya menampilkan buku dengan ID kategori terkait. |
| 4 | `POST` | `/books` | JSON Body buku lengkap | `201 Created` | Menambahkan rekaman buku baru. Memverifikasi buku langsung muncul pada daftar. |
| 5 | `POST` | `/books` | `{}` (Payload kosong) | `422 Unprocessable Entity` | Memverifikasi respon galat validasi terperinci per kolom pada atribut `errors`. |
| 6 | `GET` | `/books/1` | - | `200 OK` | Menampilkan representasi detail satu buku. |
| 7 | `GET` | `/books/9999` | - | `404 Not Found` | Memverifikasi penanganan ID fiktif oleh `findOrFail()`. |
| 8 | `PUT` | `/books/1` | JSON Body perubahan data | `200 OK` | Memverifikasi atribut buku berhasil diperbarui. |
| 9 | `DELETE` | `/books/1` | - | `200 OK` | Menghapus buku dan menerima payload konfirmasi pesan JSON. |
| 10 | `GET` | `/members` | - | `200 OK` | Menampilkan daftar anggota perpustakaan secara terpaginasi. |
| 11 | `GET` | `/loans` | - | `200 OK` | Memverifikasi relasi eager loaded `member`, `petugas`, dan array `books`. |
| 12 | `POST` | `/loans` | JSON Body transaksi peminjaman | `201 Created` | Memverifikasi pembuatan peminjaman multi-buku dan nilai `status` bernilai `"dipinjam"`. |
| 13 | `PUT` | `/loans/1/kembalikan`| - | `200 OK` | Status berubah menjadi `"dikembalikan"` dan `tanggal_dikembalikan` terisi tanggal hari ini. |
| 14 | `GET` | `/stats` | - | `200 OK` | Mengembalikan ringkasan objek JSON `total_buku`, `total_anggota`, dan `peminjaman_aktif`. |

Contoh payload permintaan untuk `POST /api/loans`:

```json
{
  "member_id": 1,
  "user_id": 1,
  "tanggal_pinjam": "2026-11-05",
  "tanggal_kembali": "2026-11-12",
  "book_ids": [2, 3]
}
```

---

## 5. Checkpoint Git Praktikum

Setelah menguji seluruh endpoint dan memastikan implementasi RESTful API berjalan sesuai spesifikasi, simpan pekerjaan Anda pada repositori lokal dan unggah ke cabang pengembangan `dev`:

```bash
git status
git add .
git commit -m "[P-9] implementasi rest api books members loans dan stats"
git push origin dev
```

---

## 6. Tugas Mandiri

Susunlah dokumentasi spesifikasi teknis antarmuka REST API (*API Documentation*) yang telah Anda kembangkan ke dalam berkas Markdown terpisah bernama `API.md` di direktori utama (*root*) repositori proyek Anda.

Dokumentasi wajib memuat tabel terstruktur yang mencakup **seluruh 11 endpoint API**:

| Metode HTTP | Endpoint URI | Header Wajib | Parameter / Payload Body | Kode Status Respons | Keterangan Singkat |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `GET` | `/api/books` | `Accept: application/json` | Opsional: `?judul=`, `?category_id=`, `?page=` | `200 OK` | Menampilkan katalog buku terpaginasi. |
| `POST` | `/api/books` | `Accept: application/json`<br>`Content-Type: application/json` | JSON Body: `judul`, `penulis`, `penerbit`, `tahun_terbit`, `stok`, `category_id` | `201 Created`<br>`422 Unprocessable Entity` | Menambahkan rekaman buku baru ke katalog. |
| ... | ... | ... | ... | ... | ... |

**Ketentuan Tugas:**
1. Setiap endpoint harus dilengkapi dengan contoh payload respons JSON nyata yang disalin dari hasil pengujian Postman (bukan karangan manual).
2. Sertakan contoh respons untuk skenario sukses (misalnya `200` atau `201`) serta skenario galat (misalnya respons validasi `422` atau `404 Not Found`).

**Keluaran Tugas:**
- Tautan komit pada cabang `dev` di GitHub yang memuat berkas dokumentasi `API.md`.
- Tangkapan layar (*screenshot*) koleksi Postman yang memperlihatkan eksekusi endpoint yang berhasil.

---

## Navigasi Modul
- [← Pertemuan 08: Autentikasi Pengguna dan Middleware Hak Akses di Laravel 12](./pertemuan-08.md)
- [Daftar Isi Modul](./README.md)
- [Pertemuan 10: Integrasi Frontend Blade dan Konsumsi API](./pertemuan-10.md) &rarr;
