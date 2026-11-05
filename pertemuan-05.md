# Pertemuan 5: Database Migration, Eloquent Model, dan Operasi CRUD di Laravel 12

> **Informasi Modul:**
> - **Prasyarat:** `BookController` dan `CategoryController` telah mengimplementasikan validasi formulir dan tata letak bersama (*Master Layout*) Blade dari Pertemuan 4.
> - **Fokus Pembelajaran:** Menggantikan penyimpanan data berbasis *in-memory array dummy* dengan basis data relasional MySQL melalui skrip *Migration*, memetakan entitas tabel menggunakan *Eloquent ORM*, memahami proteksi *Mass Assignment* (`$fillable`), serta menyempurnakan alur operasi CRUD (*Create, Read, Update, Delete*) lengkap dengan paginasi (*Pagination*).

---

## 1. Capaian Pembelajaran

Setelah menyelesaikan modul pertemuan ini, mahasiswa diharapkan mampu:
1. Menjelaskan konsep *Database Migration* sebagai sistem kendali versi (*version control*) untuk skema basis data.
2. Merancang dan mengeksekusi skrip migrasi untuk mendefinisikan tabel-tabel utama (`categories`, `books`, `members`, `loans`, `loan_items`) beserta batasan kuncinya (*foreign keys*).
3. Memahami konsep dasar *Object-Relational Mapping* (ORM) pada arsitektur *Active Record* yang diusung oleh Laravel Eloquent.
4. Menerapkan proteksi keamanan *Mass Assignment* menggunakan atribut `$fillable` pada Model Eloquent.
5. Mengimplementasikan operasi CRUD data nyata secara komprehensif pada modul `books` dan `categories` menggantikan array *dummy*.
6. Menerapkan fitur paginasi (*Pagination*) bawaan Laravel untuk menampilkan sekumpulan data dalam beberapa halaman visual yang terstruktur.

---

## 2. Konsep Dasar Migration dan Object-Relational Mapping (ORM)

### 2.1 Mengapa Migration Dibutuhkan? (Version Control Basis Data)

Hingga Pertemuan 4, data buku dan kategori disimpan dalam variabel array PHP (`private array $books`) di dalam *Controller*. Pola ini memiliki kelemahan mendasar:
Array tersebut dibuat ulang dari nol pada setiap permintaan HTTP baru. Penambahan buku baru melalui formulir akan langsung hilang saat halaman disegarkan (*refresh*), karena siklus proses PHP telah berakhir dan memorinya dibersihkan.

Untuk mencapai persistensi data, aplikasi memerlukan basis data relasional (seperti MySQL/MariaDB). Namun, integrasi basis data menimbulkan tantangan kolaborasi baru:
- Bagaimana cara menyamakan struktur tabel antar-pengembang tanpa harus mengirim berkas `.sql` manual lewat aplikasi perpesanan?
- Bagaimana memastikan peladen *production* memiliki skema tabel yang identik dengan lingkungan *local development*?

**Database Migration** menyelesaikan masalah ini dengan memperlakukan struktur skema sebagai **kode sumber yang tercatat di repositori Git**. Setiap penambahan tabel, modifikasi kolom, atau pembuatan indeks didefinisikan ke dalam berkas PHP yang memiliki metode eksekusi (`up()`) dan metode pembatalan (`down()`). Pengembang lain cukup menjalankan perintah `php artisan migrate` untuk menyelaraskan skema lokal secara instan.

### 2.2 Menjembatani SQL dan Objek: Konsep ORM & Pola Active Record

Sistem basis data relasional menyimpan data dalam format baris dan kolom dua dimensi, sementara bahasa pemrograman modern berbasis objek (OOP) memanipulasi data dalam bentuk objek dan instansiasi kelas.

Secara konvensional, menjembatani kedua dunia ini memerlukan penulisan kueri SQL mentah berulang kali:
```sql
SELECT * FROM books WHERE id = 1;
INSERT INTO books (judul, penulis) VALUES ('Laskar Pelangi', 'Andrea Hirata');
```

**ORM (Object-Relational Mapping)** hadir sebagai jembatan abstraksi:
- Setiap **tabel** dipetakan ke dalam satu kelas **Model**.
- Setiap **baris rekaman** dipetakan ke dalam satu **objek instansiasi** dari Model tersebut.

Laravel menerapkan pola **Active Record**: kelas Model tidak hanya merepresentasikan struktur data, tetapi juga memuat logika untuk menyimpan, memperbarui, dan menghapus dirinya sendiri dari basis data:
```php
// Menemukan data dan memperbarui nilainya secara berorientasi objek:
$book = Book::findOrFail(1);
$book->stok = 10;
$book->save();
```

### 2.3 Keamanan Akses Data: Perlindungan Mass Assignment (`$fillable` vs `$guarded`)

Operasi *Mass Assignment* memungkinkan pengembang mengisi banyak kolom sekaligus dari array asosiatif:
```php
Book::create($request->validated());
```

Tanpa mekanisme proteksi, pengguna jahat dapat menyisipkan field berbahaya yang tidak semestinya diubah (misalnya `role=admin` atau `id=999`) melalui formulir HTML manipulatif atau alat uji API. Celah keamanan ini disebut sebagai **Mass Assignment Vulnerability**.

Untuk mencegahnya, Eloquent mewajibkan deklarasi salah satu properti pengaman:
- **`$fillable` (Metode Whitelist):** Mendefinisikan daftar kolom yang **diizinkan** untuk diisi secara massal. Kolom di luar daftar ini akan diabaikan secara otomatis oleh Laravel.
- **`$guarded` (Metode Blacklist):** Mendefinisikan daftar kolom yang **dilarang** untuk diisi secara massal, sedangkan kolom lainnya diizinkan.

> [!TIP]
> **Praktik Terbaik:** Gunakan selalu pendekatan `$fillable` karena menerapkan prinsip *secure by default*. Jika pengembang menambahkan kolom baru ke tabel namun lupa mendaftarkannya, kolom tersebut akan ditolak secara default hingga pengembang secara sadar mengizinkannya.

### 2.4 Urgensi Integritas Dependensi Skema (Parent vs Child Migrations)

Tabel yang memiliki batasan kunci asing (*foreign key*) hanya dapat dibentuk **setelah** tabel induk yang dirujuknya telah tercipta pada basis data.

Sebagai contoh:
- Tabel `books` merujuk ke tabel `categories` via `category_id`. Maka migrasi `categories` harus dieksekusi sebelum `books`.
- Tabel `loans` merujuk ke tabel `members` (`member_id`) dan `users` (`user_id`). Maka migrasi `members` dan `users` wajib dieksekusi sebelum `loans`.

Laravel menentukan urutan eksekusi skrip migrasi berdasarkan **nama berkas yang diawali cap waktu (*timestamp*) pembuatan**.

---

## 3. Fitur Inti Migration dan Eloquent di Laravel 12

### 3.1 Perintah Esensial Artisan Migration

Empat perintah Artisan yang menjadi pilar manajemen siklus hidup basis data:

```bash
# Membuat berkas migrasi baru
php artisan make:migration create_books_table

# Menjalankan seluruh migrasi yang belum terpasang
php artisan migrate

# Membatalkan (rollback) satu batch migrasi terakhir
php artisan migrate:rollback

# Memeriksa status berkas migrasi (sudah / belum dieksekusi)
php artisan migrate:status
```

> [!CAUTION]
> **Peringatan Perintah `migrate:fresh`:**
> Perintah `php artisan migrate:fresh` akan menghapus seluruh tabel yang ada (*DROP ALL TABLES*) lalu mengeksekusi ulang migrasi dari nol. Perintah ini sangat bermanfaat pada masa pengembangan (*local development*), tetapi **dilarang keras** dijalankan pada lingkungan peladen produksi (*production*) karena bersifat merusak seluruh data operasional secara permanen.

### 3.2 Tipe Data Kolom dan Atribut Blueprint

Kelas `Illuminate\Database\Schema\Blueprint` menyediakan ragam metode pembentuk tipe kolom basis data:

| Definisi Blueprint | Tipe SQL yang Dihasilkan | Keterangan & Penggunaan |
| :--- | :--- | :--- |
| `$table->id();` | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | Kunci utama standar pada Laravel. |
| `$table->string('judul', 200);` | `VARCHAR(200)` | Teks pendek (judul, nama, nomor telepon). |
| `$table->text('deskripsi')->nullable();` | `TEXT (NULL)` | Teks panjang, diizinkan bernilai kosong (`NULL`). |
| `$table->integer('stok')->default(1);` | `INT DEFAULT 1` | Nilai numerik integer dengan angka default. |
| `$table->year('tahun_terbit');` | `YEAR` | Tahun empat digit. |
| `$table->enum('status', ['aktif', 'nonaktif']);` | `ENUM(...)` | Batasan opsi nilai tetap. |
| `$table->foreignId('category_id')->constrained();` | `BIGINT UNSIGNED` + `FOREIGN KEY` | Kunci asing relasional terkonvensi otomatis. |
| `$table->timestamps();` | `TIMESTAMP nullable` | Menghasilkan kolom `created_at` dan `updated_at`. |

### 3.3 Konvensi Penamaan Model Eloquent

Secara bawaan (*by convention*), Eloquent memetakan nama Model ke nama tabel basis data dengan aturan:
- **Nama Model:** Tunggal (*singular*), berformat `PascalCase`.
- **Nama Tabel Basis Data:** Jamak (*plural* bahasa Inggris), berformat `snake_case`.

| Kelas Model | Tabel Basis Data yang Dipetakan Otomatis |
| :--- | :--- |
| `Book` | `books` |
| `Category` | `categories` |
| `Member` | `members` |
| `Loan` | `loans` |
| `LoanItem` | `loan_items` |

### 3.4 Metode Kueri Dasar Eloquent

```php
// Mengambil seluruh rekaman data (mengembalikan Collection)
$books = Book::all();

// Mencari satu rekaman berdasarkan ID (mengembalikan null jika tidak ditemukan)
$book = Book::find(1);

// Mencari satu rekaman berdasarkan ID (otomatis melempar respons HTTP 404 jika nihil)
$book = Book::findOrFail(1);

// Kueri kondisional berantai
$availableBooks = Book::where('stok', '>', 0)->get();

// Menyimpan rekaman baru ke basis data (INSERT)
$newBook = Book::create($request->validated());

// Memperbarui rekaman data yang aktif (UPDATE)
$book->update($request->validated());

// Menghapus rekaman data dari basis data (DELETE)
$book->delete();
```

> [!NOTE]
> Penggunaan `findOrFail()` sangat dianjurkan untuk metode `show()`, `edit()`, `update()`, dan `destroy()`. Jika parameter ID URL yang dikirim pengguna tidak eksis di database, Laravel secara otomatis memunculkan halaman galat standar **404 Not Found**.

### 3.5 Pengelolaan Paginasi Data (Pagination)

Menampilkan ribuan baris data dalam satu halaman akan membebani memori peladen dan memperlambat waktu muat peramban. Laravel menyediakan solusi paginasi instan:

```php
// Pada Controller: membatasi 10 baris per halaman
$books = Book::paginate(10);
```

Pada template Blade, Anda cukup menambahkan tautan navigasi di bawah tabel:
```blade
{{-- Menghasilkan tombol Previous, nomor halaman, dan Next --}}
{{ $books->links() }}
```

---

## 4. Langkah-Langkah Praktikum

### Prasyarat Praktikum
Pastikan Anda berada di direktori proyek `app-perpustakaan` dan berada pada cabang `dev`:

```bash
cd /home/ububu/app-perpustakaan
git checkout dev
```

Pastikan pula konfigurasi kredensial koneksi basis data pada berkas `.env` telah sesuai dengan konfigurasi peladen MySQL lokal Anda:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app_perpustakaan
DB_USERNAME=root
DB_PASSWORD=
```

---

### Langkah 1: Penambahan Kolom `role` pada Migration `users`

Skema tabel `users` bawaan Laravel belum memiliki kolom klasifikasi peran (*role*). Buka berkas `database/migrations/0001_01_01_000000_create_users_table.php` dan tambahkan kolom `role`:

```php
// File: database/migrations/0001_01_01_000000_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->enum('role', ['admin', 'petugas'])->default('petugas'); // Kolom tambahan
    $table->rememberToken();
    $table->timestamps();
});
```

Buka Model `app/Models/User.php` dan pastikan `'role'` telah didaftarkan pada properti `$fillable`:

```php
// File: app/Models/User.php
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
];
```

---

### Langkah 2: Pembuatan Migration untuk Seluruh Tabel Entitas

Jalankan perintah pembuatan berkas migrasi secara berurutan sesuai relasi ketergantungannya:

```bash
php artisan make:migration create_categories_table
php artisan make:migration create_books_table
php artisan make:migration create_members_table
php artisan make:migration create_loans_table
php artisan make:migration create_loan_items_table
```

Lengkapi rancangan skema pada masing-masing berkas migrasi yang baru terbentuk:

#### 1. Berkas: `database/migrations/xxxx_create_categories_table.php`
```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('nama_kategori', 100);
    $table->text('deskripsi')->nullable();
    $table->timestamps();
});
```

#### 2. Berkas: `database/migrations/xxxx_create_books_table.php`
```php
Schema::create('books', function (Blueprint $table) {
    $table->id();
    $table->string('judul', 200);
    $table->string('penulis', 100);
    $table->string('penerbit', 100);
    $table->year('tahun_terbit');
    $table->string('isbn', 20)->unique()->nullable();
    $table->integer('stok')->default(1);
    $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
    $table->string('sampul')->nullable();
    $table->timestamps();
});
```

#### 3. Berkas: `database/migrations/xxxx_create_members_table.php`
```php
Schema::create('members', function (Blueprint $table) {
    $table->id();
    $table->string('nama', 100);
    $table->string('nim', 20)->unique();
    $table->string('email', 100)->unique();
    $table->string('nomor_telepon', 15);
    $table->text('alamat');
    $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
    $table->timestamps();
});
```

#### 4. Berkas: `database/migrations/xxxx_create_loans_table.php`
```php
Schema::create('loans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->date('tanggal_pinjam');
    $table->date('tanggal_kembali');
    $table->date('tanggal_dikembalikan')->nullable();
    $table->enum('status', ['dipinjam', 'dikembalikan', 'terlambat'])->default('dipinjam');
    $table->timestamps();
});
```

#### 5. Berkas: `database/migrations/xxxx_create_loan_items_table.php`
```php
Schema::create('loan_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
    $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
    $table->timestamps();
});
```

> [!IMPORTANT]
> **Audit Cap Waktu Berkas Migrasi:**
> Periksa urutan berkas di dalam direktori `database/migrations/`. Pastikan cap waktu berkas `create_members_table.php` berada **sebelum** berkas `create_loans_table.php`. Jika kedua berkas terbuat pada detik yang sama, ganti nama berkas (*rename*) sehingga urutan alfabetis `members` dieksekusi lebih dahulu dari `loans`.

Eksekusi seluruh migrasi ke basis data:
```bash
php artisan migrate
```

---

### Langkah 3: Pembuatan dan Konfigurasi Model Eloquent

Generate lima kelas Model Eloquent melalui Artisan:

```bash
php artisan make:model Category
php artisan make:model Book
php artisan make:model Member
php artisan make:model Loan
php artisan make:model LoanItem
```

Daftarkan atribut proteksi `$fillable` pada setiap berkas Model:

```php
// File: app/Models/Category.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'nama_kategori',
        'deskripsi',
    ];
}
```

```php
// File: app/Models/Book.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = [
        'judul',
        'penulis',
        'penerbit',
        'tahun_terbit',
        'isbn',
        'stok',
        'category_id',
        'sampul',
    ];
}
```

```php
// File: app/Models/Member.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'nama',
        'nim',
        'email',
        'nomor_telepon',
        'alamat',
        'status',
    ];
}
```

```php
// File: app/Models/Loan.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = [
        'member_id',
        'user_id',
        'tanggal_pinjam',
        'tanggal_kembali',
        'tanggal_dikembalikan',
        'status',
    ];
}
```

```php
// File: app/Models/LoanItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanItem extends Model
{
    protected $fillable = [
        'loan_id',
        'book_id',
    ];
}
```

---

### Langkah 4: Implementasi CRUD Penuh pada CategoryController dan View Edit

#### 1. Berkas: `app/Http/Controllers/CategoryController.php`
Hapus seluruh array *dummy* `$categories` dan ganti dengan kueri Eloquent Model:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::paginate(10);

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();

        Category::create($validated);

        return redirect()->route('categories.index')
            ->with('success', "Kategori \"{$validated['nama_kategori']}\" berhasil disimpan ke basis data.");
    }

    public function edit(string $id)
    {
        $category = Category::findOrFail($id);

        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'nama_kategori' => 'required|string|max:100',
            'deskripsi'     => 'nullable|string',
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', "Kategori \"{$validated['nama_kategori']}\" berhasil diperbarui.");
    }

    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil dihapus dari basis data.');
    }
}
```

#### 2. Berkas: `resources/views/categories/edit.blade.php`
Buat berkas tampilan sunting kategori dengan memanfaatkan master layout:

```blade
{{-- File: resources/views/categories/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Sunting Kategori - Perpustakaan Digital')

@section('content')
    <div class="card" style="max-width: 550px; margin: 0 auto;">
        <a href="{{ route('categories.index') }}" style="color: #4b5563; text-decoration: none; font-size: 14px;">&larr; Kembali ke Daftar Kategori</a>
        <h2 style="margin-top: 12px; margin-bottom: 20px;">Sunting Klasifikasi Kategori</h2>

        <form action="{{ route('categories.update', $category->id) }}" method="POST">
            @csrf
            @method('PUT')

            <label for="nama_kategori" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Nama Kategori</label>
            <input type="text" name="nama_kategori" id="nama_kategori" value="{{ old('nama_kategori', $category->nama_kategori) }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('nama_kategori')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="deskripsi" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Deskripsi Kategori</label>
            <textarea name="deskripsi" id="deskripsi" rows="4" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">{{ old('deskripsi', $category->deskripsi) }}</textarea>
            @error('deskripsi')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn" style="margin-top:24px; width:100%;">Perbarui Data Kategori</button>
        </form>
    </div>
@endsection
```

Perbarui bagian bawah tabel pada `resources/views/categories/index.blade.php` dengan menyisipkan tombol paginasi:
```blade
{{-- Letakkan di bawah penutup tag </table> --}}
<div style="margin-top: 20px;">
    {{ $categories->links() }}
</div>
```

---

### Langkah 5: Implementasi CRUD Penuh pada BookController

Perbarui `app/Http/Controllers/BookController.php` dengan mengintegrasikan Model `Book` dan `Category`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::paginate(10);

        return view('books.index', compact('books'));
    }

    public function create()
    {
        $categories = Category::all();

        return view('books.create', compact('categories'));
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();

        Book::create($validated);

        return redirect()->route('books.index')
            ->with('success', "Buku \"{$validated['judul']}\" berhasil disimpan ke basis data.");
    }

    public function show(string $id)
    {
        $book = Book::findOrFail($id);

        return view('books.show', compact('book'));
    }

    public function edit(string $id)
    {
        $book = Book::findOrFail($id);
        $categories = Category::all();

        return view('books.edit', compact('book', 'categories'));
    }

    public function update(Request $request, string $id)
    {
        $book = Book::findOrFail($id);

        $validated = $request->validate([
            'judul'        => 'required|string|max:200',
            'penulis'      => 'required|string|max:100',
            'penerbit'     => 'required|string|max:100',
            'tahun_terbit' => 'required|integer|min:1900|max:'.date('Y'),
            'isbn'         => 'nullable|string|max:20|unique:books,isbn,'.$book->id,
            'stok'         => 'required|integer|min:0',
            'category_id'  => 'required|integer|exists:categories,id',
        ]);

        $book->update($validated);

        return redirect()->route('books.index')
            ->with('success', "Buku \"{$validated['judul']}\" berhasil diperbarui.");
    }

    public function destroy(string $id)
    {
        $book = Book::findOrFail($id);
        $book->delete();

        return redirect()->route('books.index')
            ->with('success', 'Buku berhasil dihapus dari basis data.');
    }
}
```

---

### Langkah 6: Pembaruan Validasi `exists` dan Penyesuaian View Buku

#### 1. Validasi Aturan Foreign Key pada `StoreBookRequest.php`
Buka berkas `app/Http/Requests/StoreBookRequest.php` dan tambahkan aturan integritas `exists:categories,id`:

```php
// File: app/Http/Requests/StoreBookRequest.php
public function rules(): array
{
    return [
        'judul'        => 'required|string|max:200',
        'penulis'      => 'required|string|max:100',
        'penerbit'     => 'required|string|max:100',
        'tahun_terbit' => 'required|integer|min:1900|max:'.date('Y'),
        'isbn'         => 'nullable|string|max:20|unique:books,isbn',
        'stok'         => 'required|integer|min:0',
        'category_id'  => 'required|integer|exists:categories,id', // Integritas Relasi
    ];
}

public function messages(): array
{
    return [
        // Pesan validasi sebelumnya...
        'category_id.exists' => 'Kategori yang dipilih tidak valid atau belum terdaftar.',
    ];
}
```

#### 2. Penyesuaian Berkas Tampilan Indeks Buku (`books/index.blade.php`)
Ubah kolom tabel pada `resources/views/books/index.blade.php` untuk menampilkan ID Kategori sementara (relasi nama kategori akan diimplementasikan pada Pertemuan 7):

```blade
<th>ID Kategori</th>
...
<td>{{ $book->category_id }}</td>
```

Di bawah tabel, sematkan tautan paginasi:
```blade
<div style="margin-top: 20px;">
    {{ $books->links() }}
</div>
```

---

### Langkah 7: Pengujian dan Verifikasi Alur CRUD pada Web Browser

Jalankan server aplikasi:
```bash
php artisan serve
```

Lakukan pengujian menyeluruh:
1. Akses `/categories/create` lalu tambahkan kategori `"Teknologi"`. Pastikan data muncul pada tabel `/categories` dan **tetap tersimpan** meski peramban di-*refresh*.
2. Akses `/categories/{id}/edit` lalu ubah data deskripsi. Pastikan perubahan tersimpan pada basis data MySQL.
3. Akses `/books/create` lalu periksa *dropdown* kategori. Pastikan opsi kategori bersumber langsung dari tabel `categories`.
4. Tambahkan buku baru dan verifikasi alur *redirect* serta pesan sukses.
5. Uji penghapusan data melalui tombol **Hapus** dan pastikan rekaman data terhapus dari tabel.

---

## 5. Checkpoint Git Praktikum

Setelah menyelesaikan seluruh langkah di atas dan memastikan operasional CRUD terhubung penuh ke MySQL, simpan progres kerja Anda ke repositori Git lokal dan cabang `dev`:

```bash
git status
git add .
git commit -m "[P-5] migrasi semua tabel dan implementasi crud eloquent books categories"
git push origin dev
```

---

## 6. Tugas Mandiri

Terapkan alur migrasi dan CRUD penuh menggunakan Eloquent Model pada modul keanggotaan (**Member**):

1. **Penyempurnaan Tampilan Form Anggota:**
   - Bangun berkas `resources/views/members/create.blade.php`, `resources/views/members/edit.blade.php`, dan `resources/views/members/show.blade.php` memanfaatkan master layout `@extends('layouts.app')`.
2. **Implementasi Validasi:**
   - Bentuk kelas `StoreMemberRequest` dengan aturan: `nama` (required), `nim` (required, unique), `email` (required, email, unique), `nomor_telepon` (required), `alamat` (required), dan `status` (required, in:aktif,nonaktif).
3. **Penyempurnaan `MemberController`:**
   - Gantikan array *dummy* pada `MemberController` menggunakan Model `Member`.
   - Gunakan `Member::paginate(10)` pada metode `index()`.
   - Implementasikan metode `store()`, `show()`, `edit()`, `update()`, dan `destroy()`.
4. **Fitur Pencarian Anggota (*Search Query*):**
   - Tambahkan formulir input pencarian berbasis teks di atas tabel anggota yang mengirim parameter `GET /members?search=...`.
   - Modifikasi `MemberController@index` menggunakan kueri:
     ```php
     $members = Member::when(request('search'), function ($query, $search) {
         $query->where('nama', 'like', "%{$search}%")
               ->orWhere('nim', 'like', "%{$search}%");
     })->paginate(10)->withQueryString();
     ```

**Keluaran Tugas:**
- Tautan komit repositori GitHub pada cabang pengembangan `dev`.
- Tangkapan layar (*screenshot*) tabel data anggota nyata dari MySQL serta pengujian pencarian anggota berdasarkan kata kunci.

---

## Navigasi Modul
- [← Pertemuan 04: Blade Templating dan Pembuatan Layout Admin](./pertemuan-04.md)
- [Daftar Isi Modul](./README.md)
- [Pertemuan 06: Form Handling Lanjutan dan Pengunggahan Berkas](./pertemuan-06.md) &rarr;
