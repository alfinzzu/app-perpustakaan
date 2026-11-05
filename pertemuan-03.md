# Pertemuan 3: Controller, Request, dan Form Validation di Laravel 12

> **Informasi Modul:**
> - **Prasyarat:** Seluruh rute *resource* (`books`, `categories`, `members`, `loans`) telah terdaftar pada `routes/web.php` dan terhubung ke *skeleton controller* dari Pertemuan 2.
> - **Fokus Pembelajaran:** Mengimplementasikan logika pengendali (*Controller*), mengelola data permintaan HTTP melalui objek `Request`, menerapkan validasi formulir berlapis (*Inline Validation* dan *Form Request*), serta menyajikan data dan pesan galat (*validation errors*) pada antarmuka Blade.

---

## 1. Capaian Pembelajaran

Setelah menyelesaikan modul pertemuan ini, mahasiswa diharapkan mampu:
1. Menjelaskan peran strategis *Controller* dalam arsitektur MVC sebagai orkestrator alur kerja antara lapisan *Model* dan *View*.
2. Memahami anatomi dan tujuan dari 7 metode standar pada *Resource Controller*.
3. Memahami sifat nirkeadaan (*stateless*) pada siklus hidup *Controller* dalam lingkungan web.
4. Menggunakan kelas `Illuminate\Http\Request` untuk mengekstraksi dan menyaring berbagai tipe data masukan HTTP.
5. Mengimplementasikan validasi data formulir menggunakan pendekatan *Inline Validation* (`$request->validate()`) maupun kelas *Form Request* terpisah.
6. Mengelola dan menyajikan umpan balik pesan galat validasi serta mempertahankan masukan lama (*old input*) pada antarmuka Blade menggunakan direktif `@error`, `@selected`, dan fungsi pembantu `old()`.

---

## 2. Konsep Dasar Controller, Request, dan Validasi

### 2.1 Mengapa Controller Dibutuhkan? (Pemisahan Tanggung Jawab)

Pada Pertemuan 2, kita telah mempelajari bahwa *Route* bertugas memetakan URL ke penangan yang tepat—rute menentukan **ke mana** permintaan diarahkan, tetapi tidak dirancang untuk menangani **apa yang harus dikerjakan**. 

Jika seluruh logika aplikasi (mengambil data masukan, memvalidasi aturan bisnis, memproses penyimpanan, hingga menyiapkan respons HTML/JSON) ditulis langsung di dalam fungsi penutup (*Closure*) pada berkas rute, berkas `routes/web.php` akan cepat membengkak dan sulit dirawat.

*Controller* hadir untuk memisahkan tanggung jawab tersebut:
- **Route:** Bertanggung jawab murni atas gerbang masuk (*routing & endpoint dispatching*).
- **Controller:** Bertanggung jawab atas koordinasi alur logika setelah permintaan masuk (*business logic orchestration*).

Pemisahan ini memberikan dua manfaat utama:
1. **Pengujian Terisolasi (*Testability*):** Metode pengendali dapat diuji secara independen tanpa harus selalu memicu siklus HTTP penuh.
2. **Dukungan CLI & Reusabilitas (*Reusability*):** Logika pengendali dapat dipanggil kembali melalui konsol (misal via `php artisan tinker`).

### 2.2 Prinsip Desain: *"Skinny Controller, Fat Model"*

Dalam perancangan perangkat lunak berbasis MVC, berlaku konvensi praktik terbaik: **Skinny Controller, Fat Model**. Artinya, pengendali idealnya berukuran ramping dan hanya bertindak sebagai orkestrator:
1. Menerima masukan dari klien melalui objek `Request`.
2. Mendorong validasi ke lapisan *Form Request*.
3. Mendorong logika manipulasi data dan query ke lapisan *Model* / *Service*.
4. Mengembalikan respons yang sesuai (merender `view()` atau memicu `redirect()`).

Jika sebuah metode Controller mulai dipenuhi oleh puluhan baris query SQL manual, kalkulasi rumit, atau percabangan bersarang, hal tersebut merupakan indikasi bahwa tanggung jawab tersebut perlu dipindahkan ke lapisan Model atau Service terpisah.

### 2.3 Sifat Nirkeadaan (*Stateless*) dan Siklus Hidup Controller

Satu konsep fundamental dalam pemrograman web berbasis PHP yang sering kali disalahpahami adalah **sifat nirkeadaan (*stateless*)**:

> Setiap kali ada permintaan HTTP masuk, Laravel akan membuat instansiasi objek *Controller* baru dari awal, mengeksekusi metode yang relevan, mengembalikan respons ke peramban, lalu instansiasi objek tersebut langsung dihancurkan (*destroyed*) dari memori peladen.

Konsekuensi langsung dari arsitektur *stateless*:
- Properti variabel (misalnya array `$books` pada *Controller*) **bukanlah media penyimpanan permanen**.
- Operasi penambahan atau penghapusan elemen array pada *Controller* hanya bertahan selama siklus eksekusi permintaan tersebut berjalan. Begitu halaman dimuat ulang (*refresh*), Controller baru akan kembali diinisialisasi dengan data awal.
- Penyimpanan data yang bertahan lintas-permintaan (*persistent*) merupakan tanggung jawab sistem basis data (MySQL melalui Model Eloquent pada Pertemuan 5) atau *Session* untuk data sementara per pengguna (seperti *flash message* `session('success')`).

### 2.4 Urgensi Validasi Data: *"Never Trust User Input"*

Prinsip keamanan nomor satu dalam rekayasa perangkat lunak adalah: **jangan pernah mempercayai data masukan dari luar**. 

Pengguna dapat mengirimkan formulir kosong, memasukkan teks pada kolom numerik, atau mengirimkan payload manipulatif secara langsung melalui alat uji HTTP seperti Postman atau cURL.

Lapisan validasi berfungsi untuk:
- Mencegah data inkonsisten atau korup tersimpan ke basis data.
- Menghindari galat fatal (*unhandled exceptions*) di tengah proses komputasi.
- Memberikan panduan umpan balik (*user feedback*) yang jelas kepada pengguna mengenai perbaikan data yang diperlukan.

---

## 3. Komponen Controller dan Fitur Validasi Laravel 12

### 3.1 Resource Controller: Anatomi 7 Metode RESTful

Perintah Artisan generator menghasilkan 7 metode standar pada *Resource Controller*:

```bash
php artisan make:controller BookController --resource
```

| Metode Controller | Metode HTTP | URI | Nama Rute (`name`) | Deskripsi Tanggung Jawab |
| :--- | :--- | :--- | :--- | :--- |
| `index()` | `GET` | `/books` | `books.index` | Menampilkan katalog / daftar seluruh rekaman data. |
| `create()` | `GET` | `/books/create` | `books.create` | Menyajikan formulir HTML untuk input data baru. |
| `store()` | `POST` | `/books` | `books.store` | Memvalidasi dan menyimpan data baru ke penyimpanan. |
| `show()` | `GET` | `/books/{id}` | `books.show` | Menampilkan rincian data spesifik satu entitas. |
| `edit()` | `GET` | `/books/{id}/edit` | `books.edit` | Menyajikan formulir HTML penyuntingan data lama. |
| `update()` | `PUT/PATCH` | `/books/{id}` | `books.update` | Memvalidasi dan memperbarui data entitas di penyimpanan. |
| `destroy()` | `DELETE` | `/books/{id}` | `books.destroy` | Menghapus rekaman entitas dari sistem. |

> [!NOTE]
> **Mengapa 7 Metode, Bukan 4?**
> Operasi data dasar hanya terdiri dari 4 (CRUD). Perbedaan jumlah menjadi 7 timbul karena aplikasi web membutuhkan antarmuka visual sebelum aksi manipulasi dijalankan:
> - `create()` adalah antarmuka visual sebelum aksi `store()`.
> - `edit()` adalah antarmuka visual sebelum aksi `update()`.
> - Pada RESTful API murni (Pertemuan 9), metode `create()` dan `edit()` ditiadakan karena klien API tidak memerlukan penyajian formulir HTML.

### 3.2 Penangkapan Data Input Menggunakan Objek Request

Laravel menyediakan *Dependency Injection* otomatis untuk kelas `Illuminate\Http\Request` pada penandatanganan metode Controller:

```php
use Illuminate\Http\Request;

public function store(Request $request)
{
    // Mengambil nilai satu field input
    $judul = $request->input('judul');

    // Mengambil seluruh masukan dalam bentuk array asosiatif
    $semuaData = $request->all();

    // Mengambil subset field tertentu saja (Whitelisting)
    $dataAman = $request->only(['judul', 'penulis', 'category_id']);

    // Mengabaikan field tertentu (misal token keamanan)
    $dataTanpaToken = $request->except(['_token']);

    // Memeriksa keberadaan field
    if ($request->has('isbn')) {
        // Logika pemrosesan
    }
}
```

### 3.3 Komparasi Validasi: Inline vs Form Request

Laravel mendukung dua pendekatan utama dalam menegakkan aturan validasi:

#### 1. Validasi Sebaris (*Inline Validation*)
Ditulis langsung di dalam tubuh metode Controller menggunakan metode pembantu `$request->validate()`:

```php
public function update(Request $request, string $id)
{
    $validated = $request->validate([
        'judul' => 'required|string|max:200',
        'stok'  => 'required|integer|min:0',
    ]);

    // Eksekusi pembaruan data menggunakan $validated...
}
```
*Karakteristik:* Sangat praktis untuk kebutuhan validasi berskala kecil atau logika spesifik satu metode, namun dapat membuat Controller cepat memanjang jika aturan validasi kompleks.

#### 2. Kelas Form Request (*Dedicated Form Request*)
Memindahkan definisi aturan validasi ke kelas terisolasi:

```bash
php artisan make:request StoreBookRequest
```

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Diberi hak akses
    }

    public function rules(): array
    {
        return [
            'judul' => 'required|string|max:200',
            'stok'  => 'required|integer|min:0',
        ];
    }
}
```
*Karakteristik:* Menjaga Controller tetap bersih dan memisahkan tanggung jawab otorisasi serta validasi secara rapi. Laravel secara otomatis memicu validasi **sebelum** tubuh metode Controller dieksekusi. Jika validasi gagal, pengguna seketika dialihkan kembali ke formulir asal dengan membawa galat sesi.

### 3.4 Mekanisme Penanganan dan Penyajian Pesan Galat di Blade

Ketika validasi gagal, Laravel secara otomatis:
1. Membatalkan kelanjutan eksekusi Controller.
2. Mengalihkan peramban kembali ke URL sebelumnya (*redirect back*).
3. Menyimpan pesan galat ke dalam sesi flash `$errors` (bertipe `Illuminate\Support\MessageBag`).
4. Menyimpan data masukan yang gagal ke dalam sesi flash masukan lama (`old()`).

Pada berkas template Blade:
- Gunakan direktif `@error('nama_field')` untuk memeriksa dan menampilkan pesan galat spesifik.
- Gunakan fungsi pembantu `old('nama_field', $default)` untuk mempertahankan isian pengguna agar tidak hilang saat halaman memuat ulang.
- Gunakan direktif `@selected(old(...) == $id)` untuk mempertahankan pilihan dropdown.

```blade
<label for="judul">Judul Buku</label>
<input type="text" name="judul" id="judul" value="{{ old('judul') }}">
@error('judul')
    <span class="text-danger">{{ $message }}</span>
@enderror
```

### 3.5 Tipe Respons Pengendali: `view()`, `redirect()`, dan `back()`

Tiga format keluaran yang paling lazim digunakan pada pengendali web:

```php
// 1. Merender tampilan Blade dengan mengirimkan variabel konteks
return view('books.index', compact('books'));

// 2. Mengalihkan ke rute bernama disertai flash message sesi
return redirect()->route('books.index')->with('success', 'Buku berhasil disimpan!');

// 3. Mengembalikan pengguna ke halaman sebelumnya dengan membawa isian lama
return back()->withInput();
```

---

## 4. Langkah-Langkah Praktikum

### Prasyarat Praktikum
Pastikan terminal Anda aktif pada direktori kerja proyek `app-perpustakaan` dan berada pada cabang `dev`:

```bash
cd /home/ububu/app-perpustakaan
git checkout dev
```

---

### Langkah 1: Pembuatan dan Konfigurasi Form Request

Buat dua kelas *Form Request* khusus untuk modul Buku dan Kategori melalui Artisan:

```bash
php artisan make:request StoreBookRequest
php artisan make:request StoreCategoryRequest
```

#### 1. Berkas: `app/Http/Requests/StoreBookRequest.php`
Buka berkas `app/Http/Requests/StoreBookRequest.php` dan sesuaikan kodenya:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * Menentukan apakah pengguna memiliki hak otorisasi untuk melakukan request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mendefinisikan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'judul'        => 'required|string|max:200',
            'penulis'      => 'required|string|max:100',
            'penerbit'     => 'required|string|max:100',
            'tahun_terbit' => 'required|integer|min:1900|max:'.date('Y'),
            'isbn'         => 'nullable|string|max:20',
            'stok'         => 'required|integer|min:0',
            'category_id'  => 'required|integer',
        ];
    }

    /**
     * Mendefinisikan pesan galat kustom dalam Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'judul.required'        => 'Judul buku wajib diisi.',
            'judul.max'             => 'Judul buku maksimal 200 karakter.',
            'penulis.required'      => 'Nama penulis wajib diisi.',
            'penerbit.required'     => 'Nama penerbit wajib diisi.',
            'tahun_terbit.required' => 'Tahun terbit wajib diisi.',
            'tahun_terbit.integer'  => 'Tahun terbit harus berupa angka.',
            'tahun_terbit.min'      => 'Tahun terbit tidak valid.',
            'tahun_terbit.max'      => 'Tahun terbit tidak boleh melebihi tahun sekarang.',
            'isbn.max'              => 'ISBN maksimal 20 karakter.',
            'stok.required'         => 'Stok wajib diisi.',
            'stok.integer'          => 'Stok harus berupa angka.',
            'stok.min'              => 'Stok tidak boleh bernilai negatif.',
            'category_id.required'  => 'Kategori buku wajib dipilih.',
        ];
    }
}
```

> [!IMPORTANT]
> Penyesuaian metode `messages()` diperlukan agar seluruh notifikasi validasi tersaji dalam Bahasa Indonesia yang baku dan informatif bagi pengguna.

#### 2. Berkas: `app/Http/Requests/StoreCategoryRequest.php`
Buka berkas `app/Http/Requests/StoreCategoryRequest.php` dan sesuaikan kodenya:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama_kategori' => 'required|string|max:100',
            'deskripsi'     => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kategori.required' => 'Nama kategori wajib diisi.',
            'nama_kategori.max'      => 'Nama kategori maksimal 100 karakter.',
        ];
    }
}
```

---

### Langkah 2: Implementasi Logika Penuh pada BookController

Perbarui berkas `app/Http/Controllers/BookController.php` dengan data *array dummy* serta implementasi lengkap seluruh 7 metode *resource*:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use Illuminate\Http\Request;

class BookController extends Controller
{
    private array $categories = [
        ['id' => 1, 'nama_kategori' => 'Fiksi'],
        ['id' => 2, 'nama_kategori' => 'Teknologi'],
        ['id' => 3, 'nama_kategori' => 'Sejarah'],
    ];

    private array $books = [
        [
            'id'           => 1,
            'judul'        => 'Laskar Pelangi',
            'penulis'      => 'Andrea Hirata',
            'penerbit'     => 'Bentang Pustaka',
            'tahun_terbit' => 2005,
            'isbn'         => '9789793062792',
            'stok'         => 5,
            'category_id'  => 1,
            'kategori'     => 'Fiksi'
        ],
        [
            'id'           => 2,
            'judul'        => 'Bumi Manusia',
            'penulis'      => 'Pramoedya Ananta Toer',
            'penerbit'     => 'Hasta Mitra',
            'tahun_terbit' => 1980,
            'isbn'         => '9789794330746',
            'stok'         => 3,
            'category_id'  => 1,
            'kategori'     => 'Fiksi'
        ],
        [
            'id'           => 3,
            'judul'        => 'Clean Code',
            'penulis'      => 'Robert C. Martin',
            'penerbit'     => 'Prentice Hall',
            'tahun_terbit' => 2008,
            'isbn'         => '9780132350884',
            'stok'         => 7,
            'category_id'  => 2,
            'kategori'     => 'Teknologi'
        ],
    ];

    public function index()
    {
        $books = $this->books;

        return view('books.index', compact('books'));
    }

    public function create()
    {
        $categories = $this->categories;

        return view('books.create', compact('categories'));
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();

        return redirect()->route('books.index')
            ->with('success', "Buku \"{$validated['judul']}\" berhasil ditambahkan (data dummy simulai).");
    }

    public function show(string $id)
    {
        $book = collect($this->books)->firstWhere('id', (int) $id);

        abort_if(! $book, 404);

        return view('books.show', compact('book'));
    }

    public function edit(string $id)
    {
        $book = collect($this->books)->firstWhere('id', (int) $id);

        abort_if(! $book, 404);

        $categories = $this->categories;

        return view('books.edit', compact('book', 'categories'));
    }

    public function update(Request $request, string $id)
    {
        // Demonstrasi komparasi menggunakan Validasi Sebaris (Inline Validation)
        $validated = $request->validate([
            'judul'        => 'required|string|max:200',
            'penulis'      => 'required|string|max:100',
            'penerbit'     => 'required|string|max:100',
            'tahun_terbit' => 'required|integer|min:1900|max:'.date('Y'),
            'isbn'         => 'nullable|string|max:20',
            'stok'         => 'required|integer|min:0',
            'category_id'  => 'required|integer',
        ]);

        return redirect()->route('books.index')
            ->with('success', "Buku \"{$validated['judul']}\" berhasil diperbarui (data dummy simulasi).");
    }

    public function destroy(string $id)
    {
        return redirect()->route('books.index')
            ->with('success', "Buku dengan ID {$id} berhasil dihapus (data dummy simulasi).");
    }
}
```

---

### Langkah 3: Pembuatan Berkas Tampilan Blade untuk Modul Buku

Buat direktori `resources/views/books/` jika belum tersedia, lalu lengkapi berkas tampilan berikut:

#### 3.1 Berkas: `resources/views/books/index.blade.php`

```blade
{{-- File: resources/views/books/index.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Buku - Perpustakaan Digital</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background-color: #f9fafb; color: #111827; }
        .container { max-width: 1000px; margin: 0 auto; background: #ffffff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #1f2937; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 10px 14px; text-align: left; }
        th { background-color: #f3f4f6; font-weight: 600; }
        tr:hover { background-color: #f9fafb; }
        .success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 8px 16px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 14px; }
        .btn:hover { background-color: #1d4ed8; }
        .btn-danger { background: none; border: none; color: #dc2626; cursor: pointer; padding: 0; font-size: inherit; text-decoration: underline; }
        form.inline { display: inline; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; background-color: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Katalog Koleksi Buku</h1>

        @if (session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <p><a href="{{ route('books.create') }}" class="btn">+ Tambah Buku Baru</a></p>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Judul Buku</th>
                    <th>Penulis</th>
                    <th>Penerbit</th>
                    <th>Tahun</th>
                    <th>Stok</th>
                    <th>Kategori</th>
                    <th style="width: 180px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td>{{ $book['id'] }}</td>
                        <td><strong>{{ $book['judul'] }}</strong></td>
                        <td>{{ $book['penulis'] }}</td>
                        <td>{{ $book['penerbit'] }}</td>
                        <td>{{ $book['tahun_terbit'] }}</td>
                        <td>{{ $book['stok'] }} eks</td>
                        <td><span class="badge">{{ $book['kategori'] }}</span></td>
                        <td>
                            <a href="{{ route('books.show', $book['id']) }}">Rincian</a>
                            |
                            <a href="{{ route('books.edit', $book['id']) }}">Ubah</a>
                            |
                            <form class="inline" action="{{ route('books.destroy', $book['id']) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus buku ini?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; color: #6b7280;">Katalog buku belum memiliki rekaman data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p style="margin-top: 24px; font-size: 13px; color: #6b7280;">
            <em>* Catatan: Data di atas bersumber dari array dummy in-memory pada Controller dan belum terintegrasi dengan tabel MySQL.</em>
        </p>
    </div>
</body>
</html>
```

#### 3.2 Berkas: `resources/views/books/create.blade.php`

```blade
{{-- File: resources/views/books/create.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Buku Baru</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background-color: #f9fafb; color: #111827; }
        .form-card { max-width: 520px; margin: 0 auto; background: #ffffff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 14px; font-weight: 600; font-size: 14px; }
        input, select { width: 100%; padding: 8px 10px; margin-top: 4px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #2563eb; ring: 2px solid #93c5fd; }
        .error { color: #dc2626; font-size: 13px; margin-top: 4px; }
        .btn { margin-top: 24px; padding: 10px 18px; background-color: #2563eb; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px; width: 100%; }
        .btn:hover { background-color: #1d4ed8; }
        .back-link { display: inline-block; margin-bottom: 16px; color: #4b5563; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="form-card">
        <a href="{{ route('books.index') }}" class="back-link">&larr; Kembali ke Katalog Buku</a>
        <h2 style="margin-top: 0;">Tambah Koleksi Buku</h2>

        <form action="{{ route('books.store') }}" method="POST">
            @csrf

            <label for="judul">Judul Buku</label>
            <input type="text" name="judul" id="judul" value="{{ old('judul') }}" placeholder="Masukkan judul buku">
            @error('judul')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="penulis">Nama Penulis</label>
            <input type="text" name="penulis" id="penulis" value="{{ old('penulis') }}" placeholder="Nama pengarang">
            @error('penulis')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="penerbit">Penerbit</label>
            <input type="text" name="penerbit" id="penerbit" value="{{ old('penerbit') }}" placeholder="Nama penerbit">
            @error('penerbit')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="tahun_terbit">Tahun Terbit</label>
            <input type="number" name="tahun_terbit" id="tahun_terbit" value="{{ old('tahun_terbit') }}" placeholder="Contoh: 2024">
            @error('tahun_terbit')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="isbn">Nomor ISBN (Opsional)</label>
            <input type="text" name="isbn" id="isbn" value="{{ old('isbn') }}" placeholder="Contoh: 978-602-...">
            @error('isbn')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="stok">Jumlah Stok Tersedia</label>
            <input type="number" name="stok" id="stok" value="{{ old('stok', 1) }}">
            @error('stok')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="category_id">Kategori Buku</label>
            <select name="category_id" id="category_id">
                <option value="">-- Pilih Klasifikasi Kategori --</option>
                @foreach ($categories as $category)
                    <option value="{{ $category['id'] }}" @selected(old('category_id') == $category['id'])>
                        {{ $category['nama_kategori'] }}
                    </option>
                @endforeach
            </select>
            @error('category_id')
                <div class="error">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn">Simpan Data Buku</button>
        </form>
    </div>
</body>
</html>
```

#### 3.3 Berkas: `resources/views/books/edit.blade.php`

```blade
{{-- File: resources/views/books/edit.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunting Data Buku</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background-color: #f9fafb; color: #111827; }
        .form-card { max-width: 520px; margin: 0 auto; background: #ffffff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 14px; font-weight: 600; font-size: 14px; }
        input, select { width: 100%; padding: 8px 10px; margin-top: 4px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .error { color: #dc2626; font-size: 13px; margin-top: 4px; }
        .btn { margin-top: 24px; padding: 10px 18px; background-color: #2563eb; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px; width: 100%; }
        .btn:hover { background-color: #1d4ed8; }
        .back-link { display: inline-block; margin-bottom: 16px; color: #4b5563; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
    <div class="form-card">
        <a href="{{ route('books.index') }}" class="back-link">&larr; Kembali ke Katalog Buku</a>
        <h2 style="margin-top: 0;">Sunting Data Buku</h2>

        <form action="{{ route('books.update', $book['id']) }}" method="POST">
            @csrf
            @method('PUT')

            <label for="judul">Judul Buku</label>
            <input type="text" name="judul" id="judul" value="{{ old('judul', $book['judul']) }}">
            @error('judul')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="penulis">Nama Penulis</label>
            <input type="text" name="penulis" id="penulis" value="{{ old('penulis', $book['penulis']) }}">
            @error('penulis')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="penerbit">Penerbit</label>
            <input type="text" name="penerbit" id="penerbit" value="{{ old('penerbit', $book['penerbit']) }}">
            @error('penerbit')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="tahun_terbit">Tahun Terbit</label>
            <input type="number" name="tahun_terbit" id="tahun_terbit" value="{{ old('tahun_terbit', $book['tahun_terbit']) }}">
            @error('tahun_terbit')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="isbn">Nomor ISBN</label>
            <input type="text" name="isbn" id="isbn" value="{{ old('isbn', $book['isbn']) }}">
            @error('isbn')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="stok">Jumlah Stok</label>
            <input type="number" name="stok" id="stok" value="{{ old('stok', $book['stok']) }}">
            @error('stok')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="category_id">Kategori Buku</label>
            <select name="category_id" id="category_id">
                <option value="">-- Pilih Klasifikasi Kategori --</option>
                @foreach ($categories as $category)
                    <option value="{{ $category['id'] }}" @selected(old('category_id', $book['category_id']) == $category['id'])>
                        {{ $category['nama_kategori'] }}
                    </option>
                @endforeach
            </select>
            @error('category_id')
                <div class="error">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn">Perbarui Data Buku</button>
        </form>
    </div>
</body>
</html>
```

#### 3.4 Berkas: `resources/views/books/show.blade.php`

```blade
{{-- File: resources/views/books/show.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rincian Koleksi Buku</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background-color: #f9fafb; color: #111827; }
        .detail-card { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 10px 14px; text-align: left; }
        th { width: 160px; background-color: #f3f4f6; color: #374151; font-weight: 600; }
        .back-link { display: inline-block; margin-bottom: 16px; color: #2563eb; text-decoration: none; font-size: 14px; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="detail-card">
        <a href="{{ route('books.index') }}" class="back-link">&larr; Kembali ke Katalog Buku</a>
        <h2 style="margin-top: 0;">Rincian Data Koleksi Buku</h2>

        <table>
            <tr>
                <th>ID Registrasi</th>
                <td>{{ $book['id'] }}</td>
            </tr>
            <tr>
                <th>Judul Buku</th>
                <td><strong>{{ $book['judul'] }}</strong></td>
            </tr>
            <tr>
                <th>Penulis</th>
                <td>{{ $book['penulis'] }}</td>
            </tr>
            <tr>
                <th>Penerbit</th>
                <td>{{ $book['penerbit'] }}</td>
            </tr>
            <tr>
                <th>Tahun Terbit</th>
                <td>{{ $book['tahun_terbit'] }}</td>
            </tr>
            <tr>
                <th>Nomor ISBN</th>
                <td>{{ $book['isbn'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>Kapasitas Stok</th>
                <td>{{ $book['stok'] }} eksemplar</td>
            </tr>
            <tr>
                <th>Klasifikasi Kategori</th>
                <td>{{ $book['kategori'] }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
```

---

### Langkah 4: Implementasi Logika pada CategoryController

Pada modul kategori, metode `index()`, `create()`, dan `store()` diimplementasikan penuh dengan data simulasi array. Metode lainnya dibiarkan sebagai respons kerangka:

```php
// File: app/Http/Controllers/CategoryController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    private array $categories = [
        ['id' => 1, 'nama_kategori' => 'Fiksi', 'deskripsi' => 'Buku cerita rekaan seperti novel, prosa, dan antologi cerpen.'],
        ['id' => 2, 'nama_kategori' => 'Teknologi', 'deskripsi' => 'Buku seputar rekayasa perangkat lunak, pemrograman, dan sains komputer.'],
        ['id' => 3, 'nama_kategori' => 'Sejarah', 'deskripsi' => 'Dokumentasi peristiwa bersejarah dan biografi tokoh nasional.'],
    ];

    public function index()
    {
        $categories = $this->categories;

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();

        return redirect()->route('categories.index')
            ->with('success', "Kategori \"{$validated['nama_kategori']}\" berhasil ditambahkan (data dummy simulasi).");
    }

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

---

### Langkah 5: Pembuatan Berkas Tampilan Blade untuk Modul Kategori

Buat direktori `resources/views/categories/` dan lengkapi berkas berikut:

#### 5.1 Berkas: `resources/views/categories/index.blade.php`

```blade
{{-- File: resources/views/categories/index.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Kategori Buku</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background-color: #f9fafb; color: #111827; }
        .container { max-width: 850px; margin: 0 auto; background: #ffffff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 10px 14px; text-align: left; }
        th { background-color: #f3f4f6; font-weight: 600; }
        .success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 8px 16px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Master Kategori Koleksi</h1>

        @if (session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <p><a href="{{ route('categories.create') }}" class="btn">+ Tambah Kategori</a></p>

        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th style="width: 220px;">Nama Kategori</th>
                    <th>Deskripsi</th>
                    <th style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category['id'] }}</td>
                        <td><strong>{{ $category['nama_kategori'] }}</strong></td>
                        <td>{{ $category['deskripsi'] ?? '-' }}</td>
                        <td>
                            <a href="{{ route('categories.edit', $category['id']) }}">Ubah</a>
                            |
                            <form style="display:inline" action="{{ route('categories.destroy', $category['id']) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none; border:none; color:#dc2626; cursor:pointer; text-decoration:underline;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #6b7280;">Belum ada klasifikasi kategori yang tersimpan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
```

#### 5.2 Berkas: `resources/views/categories/create.blade.php`

```blade
{{-- File: resources/views/categories/create.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kategori Koleksi</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background-color: #f9fafb; color: #111827; }
        .form-card { max-width: 500px; margin: 0 auto; background: #ffffff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 14px; font-weight: 600; font-size: 14px; }
        input, textarea { width: 100%; padding: 8px 10px; margin-top: 4px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .error { color: #dc2626; font-size: 13px; margin-top: 4px; }
        .btn { margin-top: 24px; padding: 10px 18px; background-color: #2563eb; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px; width: 100%; }
        .btn:hover { background-color: #1d4ed8; }
        .back-link { display: inline-block; margin-bottom: 16px; color: #4b5563; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
    <div class="form-card">
        <a href="{{ route('categories.index') }}" class="back-link">&larr; Kembali ke Daftar Kategori</a>
        <h2 style="margin-top: 0;">Tambah Kategori Koleksi</h2>

        <form action="{{ route('categories.store') }}" method="POST">
            @csrf

            <label for="nama_kategori">Nama Kategori</label>
            <input type="text" name="nama_kategori" id="nama_kategori" value="{{ old('nama_kategori') }}" placeholder="Contoh: Sains Populer">
            @error('nama_kategori')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="deskripsi">Deskripsi Kategori (Opsional)</label>
            <textarea name="deskripsi" id="deskripsi" rows="4" placeholder="Keterangan singkat mengenai cakupan kategori...">{{ old('deskripsi') }}</textarea>
            @error('deskripsi')
                <div class="error">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn">Simpan Kategori</button>
        </form>
    </div>
</body>
</html>
```

---

### Langkah 6: Verifikasi dan Uji Coba pada Web Browser

Nyalakan server pengembangan lokal:

```bash
php artisan serve
```

Lakukan pengujian skenario fungsional berikut:
1. Akses `http://127.0.0.1:8000/books` &rarr; Pastikan tabel koleksi buku dummy berhasil dirender.
2. Akses `http://127.0.0.1:8000/books/create` lalu klik **Simpan Data Buku** tanpa mengisi formulir &rarr; Pastikan seluruh notifikasi galat validasi berbahasa Indonesia tampil dengan warna merah di bawah masing-masing kolom.
3. Masukkan data valid pada formulir penambahan buku lalu submit &rarr; Sistem harus mengalihkan kembali ke `/books` disertai *flash message* sukses berwarna hijau.
4. Akses `http://127.0.0.1:8000/books/2/edit` &rarr; Pastikan data buku ID 2 otomatis terisi pada formulir dan dropdown kategori mengarah tepat ke opsi "Fiksi".
5. Akses `http://127.0.0.1:8000/categories/create` lalu uji validasi masukan kosong dan penyimpanan sukses.

---

## 5. Checkpoint Git Praktikum

Setelah menyelesaikan seluruh langkah di atas dan menguji keterhubungan logika dengan antarmuka Blade, simpan progres kerja Anda ke repositori lokal dan publikasikan ke cabang `dev`:

```bash
git status
git add .
git commit -m "[P-3] implementasi controller book category dan validasi form"
git push origin dev
```

---

## 6. Tugas Mandiri

Terapkan pola perancangan *Controller*, *Form Request*, dan *Blade View* yang sama pada modul keanggotaan (**Member**):

1. **Pembuatan Form Request:**
   Jalankan `php artisan make:request StoreMemberRequest`. Definisikan aturan pada `rules()` dan pesan bahasa Indonesia pada `messages()` untuk field: `nama`, `nim`, `email`, `nomor_telepon`, `alamat`, dan `status`.
2. **Implementasi `MemberController`:**
   - Siapkan properti array dummy `$members` di dalam `MemberController`.
   - Implementasikan metode `index()` untuk mengirimkan data ke tampilan `members.index`.
   - Implementasikan metode `create()` untuk menyajikan formulir `members.create`.
   - Implementasikan metode `store(StoreMemberRequest $request)` untuk memvalidasi masukan dan mengalihkan ke `members.index` disertai *flash message*.
3. **Pembuatan Tampilan Antarmuka Blade:**
   - Bentuk berkas `resources/views/members/index.blade.php` (tabel anggota dan tombol navigasi).
   - Bentuk berkas `resources/views/members/create.blade.php` (formulir input dengan penanganan galat `@error` dan `old()`).

**Keluaran Tugas:**
- Tautan komit repositori GitHub pada cabang pengembangan `dev`.
- Tangkapan layar (*screenshot*) saat formulir `/members/create` menampilkan pesan galat validasi input kosong, serta tangkapan layar saat penambahan anggota berhasil menampilkan notifikasi sukses pada `/members`.

---

## Navigasi Modul
- [← Pertemuan 02: Routing dan Request Lifecycle di Laravel 12](./pertemuan-02.md)
- [Daftar Isi Modul](./README.md)
- [Pertemuan 04: Blade Templating dan Pembuatan Layout Admin](./pertemuan-04.md) &rarr;
