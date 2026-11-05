# Pertemuan 7: Eloquent Relationships dan Relasi Antar Tabel di Laravel 12

> **Informasi Modul:**
> - **Prasyarat:** Telah menyelesaikan evaluasi UTS (Pertemuan 6), memiliki skema basis data lengkap di MySQL (`categories`, `books`, `members`, `loans`, `loan_items`), serta operasional CRUD dasar pada entitas mandiri.
> - **Fokus Pembelajaran:** Mengonfigurasi relasi objek *Eloquent Relationships* (`hasMany` dan `belongsTo`), mendiagnosis serta memitigasi isu performa *N+1 Query Problem* melalui pemuatan dini (*Eager Loading*), serta membangun alur transaksi peminjaman buku (*Loans CRUD*) yang mengintegrasikan multi-tabel secara konsisten.

---

## 1. Capaian Pembelajaran

Setelah menyelesaikan modul pertemuan ini, mahasiswa diharapkan mampu:
1. Menjelaskan perbedaan peran antara batasan kunci asing (*foreign key constraints*) di tingkat basis data dengan relasi objek di tingkat Model Eloquent.
2. Mengimplementasikan metode relasi satu-ke-banyak (*One-to-Many*) timbal-balik menggunakan `hasMany()` dan `belongsTo()` pada kelas Model.
3. Menganalisis penyebab terjadinya inefisiensi kueri *N+1 Query Problem* akibat pemuatan malas (*Lazy Loading*) melalui pengujian *Artisan Tinker*.
4. Mengoptimasi kueri basis data menggunakan teknik pemuatan dini (*Eager Loading*) mandiri maupun bersarang (*nested eager loading*) via metode `with()`.
5. Membangun modul transaksi peminjaman buku (*Loans*) yang menyinkronkan data multi-tabel (`loans` dan `loan_items`) dalam satu kesatuan proses CRUD.

---

## 2. Konsep Dasar Relasi Objek Eloquent

### 2.1 Mengapa Relasi Didefinisikan pada Lapisan Model?

Sejak Pertemuan 5, kolom kunci asing `category_id` telah terdefinisi di dalam tabel basis data `books`. Basis data MySQL telah mengetahui bahwa setiap baris buku merujuk pada satu baris kategori, serta mampu menolak data yang tidak valid.

Mengapa kita tetap perlu menuliskan metode relasi `category()` pada kelas Model `Book` di PHP?

Jawabannya adalah karena **Foreign Key** di basis data dan **Relationship** di Eloquent memiliki peran yang berbeda:
- **Foreign Key di Basis Data:** Menjamin **integritas referensial data**. Basis data memastikan tidak ada buku yang merujuk ke ID kategori yang fiktif.
- **Relationship di Model Eloquent:** Menyediakan **antarmuka navigasi objek**. Relasi ini memungkinkan pengembang mengakses data relasional secara ekspresif tanpa menulis klausa `JOIN` SQL manual:

```php
// Mengakses entitas kategori melalui relasi objek:
$namaKategori = $book->category->nama_kategori;
```

Selain itu, foreign key di basis data hanya bersifat **satu arah** (dari tabel anak ke tabel induk). Di sisi aplikasi, kebutuhan bisnis menuntut navigasi **dua arah**:
1. Dari buku, kita ingin mengetahui kategorinya: `$book->category` (`belongsTo`).
2. Dari kategori, kita ingin mendapatkan seluruh koleksi bukunya: `$category->books` (`hasMany`).

Mekanisme timbal balik inilah yang dihidupkan oleh lapisan Model Eloquent.

### 2.2 Relasi Satu-ke-Banyak: `hasMany` vs `belongsTo`

Pasangan relasi yang paling fundamental dalam aplikasi web adalah **One-to-Many**:

```text
+---------------------+           +---------------------+
|      Category       | 1       * |        Book         |
|---------------------|-----------|---------------------|
| id                  |           | id                  |
| nama_kategori       |           | category_id (FK)    |
|                     |           | judul               |
| public function     |           | public function     |
| books(): HasMany    |           | category(): BelongsTo
+---------------------+           +---------------------+
```

- **`belongsTo(Induk::class)` (Sisi Anak / Pemilik Foreign Key):** Dideklarasikan pada Model yang memegang kolom foreign key (misalnya `Book` yang menyimpan kolom `category_id`). Penamaan metode menggunakan kata benda tunggal (*singular*), misal `category()`.
- **`hasMany(Anak::class)` (Sisi Induk):** Dideklarasikan pada Model yang dirujuk (misalnya `Category`). Penamaan metode menggunakan kata benda jamak (*plural*), misal `books()`.

### 2.3 Perbandingan Pendekatan Relasi Lintas Framework Backend

| Framework | Bahasa | Sintaks Pendefinisian Relasi |
| :--- | :--- | :--- |
| **Laravel** | PHP | `return $this->hasMany(Book::class);` |
| **Django** | Python | `books = models.ForeignKey(Category, related_name='books')` |
| **Spring Boot** | Java | `@OneToMany(mappedBy = "category") private List<Book> books;` |
| **Sequelize** | Node.js | `Category.hasMany(Book); Book.belongsTo(Category);` |

Seluruh ekosistem modern mengadopsi abstraksi serupa untuk membebaskan pengembang dari penulisan query string `JOIN` yang berbelit-belit.

### 2.4 Memahami Tabel Penghubung: Pivot Table vs Model Entitas Penuh

Dalam studi kasus perpustakaan ini, satu transaksi peminjaman (`loans`) dapat memuat banyak buku, dan satu buku dapat dipinjam berulang kali dalam berbagai transaksi.

Secara teknis, hubungan ini adalah **Many-to-Many**. Pada skenario sederhana, Laravel menyediakan relasi `belongsToMany()` dengan tabel perantara (*pivot table*). Namun, pada tabel `loan_items`, setiap baris peminjaman memiliki makna domain transaksi tersendiri.

Oleh karena itu, arsitektur yang kita gunakan adalah memodelkannya sebagai **Model Entitas Penuh** (`LoanItem`):
- `Loan` memiliki banyak `loanItems` (`hasMany`).
- `LoanItem` merujuk ke satu `Loan` (`belongsTo`) dan satu `Book` (`belongsTo`).
- Pola ini memberikan fleksibilitas tinggi jika di kemudian hari kita ingin menambahkan kolom kondisi buku saat dipinjam, denda per item, atau status pengembalian individual per buku.

---

## 3. Optimasi Kueri dan Masalah N+1 (N+1 Query Problem)

### 3.1 Anatomi Masalah N+1 Kueri (Lazy Loading)

Secara bawaan (*default*), Eloquent menerapkan mekanisme **Lazy Loading**: data relasi tidak diambil dari basis data hingga properti relasi tersebut benar-benar diakses di dalam kode.

Perhatikan skenario berisiko berikut:

```php
// Mengambil 20 data buku (1 kali kueri SQL)
$books = Book::all();

foreach ($books as $book) {
    // Pada setiap perulangan, memicu 1 kueri SQL baru ke tabel categories!
    echo $book->category->nama_kategori;
}
```

Dampak kueri yang dieksekusi peladen basis data:
- 1 kueri utama untuk mengambil 20 buku:  
  `SELECT * FROM books;`
- 20 kueri tambahan untuk mencari kategori dari masing-masing buku:  
  `SELECT * FROM categories WHERE id = 1;`  
  `SELECT * FROM categories WHERE id = 2;`  
  *(diulang sebanyak 20 kali...)*
- **Total:** $1 + 20 = 21$ kueri basis data.

Fenomena inilah yang disebut **N+1 Query Problem** (1 kueri utama + $N$ kueri relasi per baris data). Jika tabel memuat 1.000 rekaman, peladen akan dibebani 1.001 kueri terpisah yang mengakibatkan degradasi performa drastis dan latensi aplikasi tinggi.

### 3.2 Solusi Pemuatan Dini (Eager Loading) dengan `with()`

Untuk mengatasi inefisiensi *Lazy Loading*, Laravel menyediakan metode **Eager Loading** melalui metode `with()`:

```php
// Mengambil seluruh data buku sekaligus memuat relasi kategorinya
$books = Book::with('category')->get();

foreach ($books as $book) {
    echo $book->category->nama_kategori; // Tidak memicu kueri baru
}
```

Mekanisme kueri di balik layar:
1. `SELECT * FROM books;` (Mengambil seluruh buku).
2. `SELECT * FROM categories WHERE id IN (1, 2, 3, ...);` (Mengambil seluruh kategori yang cocok secara sekaligus).

Total kueri terpangkas menjadi **hanya 2 kueri**, terlepas dari apakah data berjumlah 20 baris maupun 10.000 baris.

### 3.3 Eager Loading Majemuk dan Bersarang (Nested Eager Loading)

Metode `with()` mendukung pemanggilan multipel relasi sekaligus menggunakan array, serta relasi bertingkat (*nested*) menggunakan notasi titik (`.`):

```php
// Memuat data transaksi, anggota peminjam, petugas pencatat, dan detail buku per item
$loans = Loan::with(['member', 'user', 'loanItems.book'])->paginate(10);
```

### 3.4 Pemuatan Dini Berkondisi (Constrained Eager Loading)

Kita juga dapat menyaring (*filter*) rekaman relasi yang dimuat menggunakan closure:

```php
$categories = Category::with(['books' => function ($query) {
    $query->where('stok', '>', 0);
}])->get();
```

---

## 4. Langkah-Langkah Praktikum

### Prasyarat Praktikum
Pastikan terminal aktif di direktori proyek `app-perpustakaan` dan berada pada cabang `dev`:

```bash
cd /home/ububu/app-perpustakaan
git checkout dev
```

---

### Langkah 0: Penyelesaian CRUD Mandiri Modul Member (Prasyarat Transaksi)

Formulir transaksi peminjaman buku membutuhkan data anggota nyata. Jika Anda belum melengkapi modul `members` dari tugas mandiri Pertemuan 5, ikuti sub-langkah berikut:

#### 1. Pembuatan Form Request `StoreMemberRequest.php`
Jalankan perintah Artisan:
```bash
php artisan make:request StoreMemberRequest
```
Buka berkas `app/Http/Requests/StoreMemberRequest.php` dan sesuaikan kodenya:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
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
            'nama'          => 'required|string|max:100',
            'nim'           => 'required|string|max:20|unique:members,nim',
            'email'         => 'required|email|max:100|unique:members,email',
            'nomor_telepon' => 'required|string|max:15',
            'alamat'        => 'required|string',
            'status'        => 'required|in:aktif,nonaktif',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required'          => 'Nama anggota wajib diisi.',
            'nim.required'           => 'NIM wajib diisi.',
            'nim.unique'             => 'NIM sudah terdaftar pada sistem.',
            'email.required'         => 'Alamat email wajib diisi.',
            'email.email'            => 'Format penulisan email tidak valid.',
            'email.unique'           => 'Alamat email sudah digunakan.',
            'nomor_telepon.required' => 'Nomor telepon wajib diisi.',
            'alamat.required'        => 'Alamat domisili wajib diisi.',
            'status.required'        => 'Status keanggotaan wajib ditentukan.',
            'status.in'              => 'Pilihan status keanggotaan tidak valid.',
        ];
    }
}
```

#### 2. Implementasi CRUD pada `MemberController.php`
Buka berkas `app/Http/Controllers/MemberController.php` dan terapkan kueri Eloquent Model:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index()
    {
        $members = Member::paginate(10);

        return view('members.index', compact('members'));
    }

    public function create()
    {
        return view('members.create');
    }

    public function store(StoreMemberRequest $request)
    {
        $validated = $request->validated();

        Member::create($validated);

        return redirect()->route('members.index')
            ->with('success', "Anggota \"{$validated['nama']}\" berhasil didaftarkan.");
    }

    public function show(string $id)
    {
        // Akan disempurnakan dengan riwayat peminjaman pada Langkah 4
        $member = Member::findOrFail($id);

        return view('members.show', compact('member'));
    }

    public function edit(string $id)
    {
        $member = Member::findOrFail($id);

        return view('members.edit', compact('member'));
    }

    public function update(Request $request, string $id)
    {
        $member = Member::findOrFail($id);

        $validated = $request->validate([
            'nama'          => 'required|string|max:100',
            'nim'           => 'required|string|max:20|unique:members,nim,'.$member->id,
            'email'         => 'required|email|max:100|unique:members,email,'.$member->id,
            'nomor_telepon' => 'required|string|max:15',
            'alamat'        => 'required|string',
            'status'        => 'required|in:aktif,nonaktif',
        ]);

        $member->update($validated);

        return redirect()->route('members.index')
            ->with('success', "Data anggota \"{$validated['nama']}\" berhasil diperbarui.");
    }

    public function destroy(string $id)
    {
        $member = Member::findOrFail($id);
        $member->delete();

        return redirect()->route('members.index')
            ->with('success', 'Data anggota berhasil dihapus dari sistem.');
    }
}
```

#### 3. Tampilan Formulir Tambah Anggota (`resources/views/members/create.blade.php`)
```blade
{{-- File: resources/views/members/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Tambah Anggota Baru - Perpustakaan Digital')

@section('content')
    <div class="card" style="max-width: 550px; margin: 0 auto;">
        <a href="{{ route('members.index') }}" style="color: #4b5563; text-decoration: none; font-size: 14px;">&larr; Kembali ke Daftar Anggota</a>
        <h2 style="margin-top: 12px; margin-bottom: 20px;">Registrasi Anggota Baru</h2>

        <form action="{{ route('members.store') }}" method="POST">
            @csrf

            <label for="nama" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Nama Lengkap</label>
            <input type="text" name="nama" id="nama" value="{{ old('nama') }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('nama')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="nim" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Nomor Induk Mahasiswa (NIM)</label>
            <input type="text" name="nim" id="nim" value="{{ old('nim') }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('nim')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="email" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Alamat Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('email')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="nomor_telepon" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Nomor Telepon / WhatsApp</label>
            <input type="text" name="nomor_telepon" id="nomor_telepon" value="{{ old('nomor_telepon') }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('nomor_telepon')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="alamat" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Alamat Domisili</label>
            <textarea name="alamat" id="alamat" rows="3" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">{{ old('alamat') }}</textarea>
            @error('alamat')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="status" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Status Keanggotaan</label>
            <select name="status" id="status" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
                <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
            </select>
            @error('status')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn" style="margin-top:24px; width:100%;">Daftarkan Anggota</button>
        </form>
    </div>
@endsection
```

#### 4. Tampilan Formulir Sunting Anggota (`resources/views/members/edit.blade.php`)
```blade
{{-- File: resources/views/members/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Sunting Data Anggota - Perpustakaan Digital')

@section('content')
    <div class="card" style="max-width: 550px; margin: 0 auto;">
        <a href="{{ route('members.index') }}" style="color: #4b5563; text-decoration: none; font-size: 14px;">&larr; Kembali ke Daftar Anggota</a>
        <h2 style="margin-top: 12px; margin-bottom: 20px;">Sunting Data Anggota</h2>

        <form action="{{ route('members.update', $member->id) }}" method="POST">
            @csrf
            @method('PUT')

            <label for="nama" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Nama Lengkap</label>
            <input type="text" name="nama" id="nama" value="{{ old('nama', $member->nama) }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('nama')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="nim" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Nomor Induk Mahasiswa (NIM)</label>
            <input type="text" name="nim" id="nim" value="{{ old('nim', $member->nim) }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('nim')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="email" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Alamat Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $member->email) }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('email')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="nomor_telepon" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Nomor Telepon</label>
            <input type="text" name="nomor_telepon" id="nomor_telepon" value="{{ old('nomor_telepon', $member->nomor_telepon) }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('nomor_telepon')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="alamat" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Alamat Domisili</label>
            <textarea name="alamat" id="alamat" rows="3" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">{{ old('alamat', $member->alamat) }}</textarea>
            @error('alamat')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="status" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Status Keanggotaan</label>
            <select name="status" id="status" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
                <option value="aktif" @selected(old('status', $member->status) === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status', $member->status) === 'nonaktif')>Nonaktif</option>
            </select>
            @error('status')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn" style="margin-top:24px; width:100%;">Perbarui Data Anggota</button>
        </form>
    </div>
@endsection
```

#### 5. Pembaruan Tabel Indeks Anggota (`resources/views/members/index.blade.php`)
Pastikan tautan navigasi dan tombol aksi pada `resources/views/members/index.blade.php` telah lengkap:

```blade
{{-- File: resources/views/members/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Data Keanggotaan - Perpustakaan Digital')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="margin: 0;">Data Anggota Perpustakaan</h2>
            <a href="{{ route('members.create') }}" class="btn">+ Tambah Anggota Baru</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Nama Anggota</th>
                    <th>NIM</th>
                    <th>Email</th>
                    <th>No. Telepon</th>
                    <th style="width: 100px;">Status</th>
                    <th style="width: 170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>{{ $member->id }}</td>
                        <td><strong>{{ $member->nama }}</strong></td>
                        <td>{{ $member->nim }}</td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->nomor_telepon }}</td>
                        <td>
                            @if ($member->status === 'aktif')
                                <span style="background-color: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">Aktif</span>
                            @else
                                <span style="background-color: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('members.show', $member->id) }}">Rincian</a>
                            |
                            <a href="{{ route('members.edit', $member->id) }}">Ubah</a>
                            |
                            <form class="inline" action="{{ route('members.destroy', $member->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data anggota ini?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: #6b7280; padding: 20px;">Belum ada data anggota yang terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            {{ $members->links() }}
        </div>
    </div>
@endsection
```

---

### Langkah 1: Pendefinisian Relasi pada Seluruh Model Eloquent

Buka masing-masing berkas Model pada direktori `app/Models/` dan tambahkan metode relasi timbal balik:

#### 1. Berkas: `app/Models/Category.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['nama_kategori', 'deskripsi'];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
```

#### 2. Berkas: `app/Models/Book.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = [
        'judul', 'penulis', 'penerbit', 'tahun_terbit',
        'isbn', 'stok', 'category_id', 'sampul',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }
}
```

#### 3. Berkas: `app/Models/Member.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'nama', 'nim', 'email', 'nomor_telepon', 'alamat', 'status',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
```

#### 4. Berkas: `app/Models/User.php`
```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role'];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
```

#### 5. Berkas: `app/Models/Loan.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'member_id', 'user_id', 'tanggal_pinjam',
        'tanggal_kembali', 'tanggal_dikembalikan', 'status',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }
}
```

#### 6. Berkas: `app/Models/LoanItem.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanItem extends Model
{
    protected $fillable = ['loan_id', 'book_id'];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
```

---

### Langkah 2: Demonstrasi Masalah N+1 Kueri melalui Artisan Tinker

Buka shell interaktif Artisan:
```bash
php artisan tinker
```

Jalankan pengujian komparasi kueri berikut:

```php
use Illuminate\Support\Facades\DB;

// Pengujian 1: Tanpa Eager Loading (Lazy Loading - Masalah N+1)
DB::enableQueryLog();
$booksLazy = App\Models\Book::all();
foreach ($booksLazy as $book) { 
    $nama = $book->category->nama_kategori; 
}
echo 'Jumlah Kueri Tanpa Eager Loading: ' . count(DB::getQueryLog()) . ' kueri' . PHP_EOL;

// Pengujian 2: Dengan Eager Loading (with())
DB::flushQueryLog();
$booksEager = App\Models\Book::with('category')->get();
foreach ($booksEager as $book) { 
    $nama = $book->category->nama_kategori; 
}
echo 'Jumlah Kueri Dengan Eager Loading: ' . count(DB::getQueryLog()) . ' kueri' . PHP_EOL;
```

Ketik `exit` untuk keluar dari Tinker.

---

### Langkah 3: Refaktorisasi BookController dan Tampilan Buku dengan Eager Loading

Buka berkas `app/Http/Controllers/BookController.php` dan perbarui metode `index()` serta `show()` agar memuat relasi kategori secara dini (*Eager Loaded*):

```php
// File: app/Http/Controllers/BookController.php
public function index()
{
    $books = Book::with('category')->paginate(10);

    return view('books.index', compact('books'));
}

public function show(string $id)
{
    $book = Book::with('category')->findOrFail($id);

    return view('books.show', compact('book'));
}
```

Buka `resources/views/books/index.blade.php`:
- Ubah judul header tabel: dari `<th>ID Kategori</th>` menjadi `<th>Kategori</th>`.
- Cetak nama kategori nyata melalui relasi:
  ```blade
  <td><span style="background-color: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500;">{{ $book->category->nama_kategori ?? '-' }}</span></td>
  ```

Buka `resources/views/books/show.blade.php`:
- Ubah baris detail:
  ```blade
  <tr>
      <th>Klasifikasi Kategori</th>
      <td>{{ $book->category->nama_kategori ?? '-' }}</td>
  </tr>
  ```

---

### Langkah 4: Implementasi Riwayat Peminjaman pada Detail Anggota (Member Show)

Perbarui metode `show()` pada `app/Http/Controllers/MemberController.php`:

```php
// File: app/Http/Controllers/MemberController.php
public function show(string $id)
{
    // Memuat relasi bertingkat: transaksi peminjaman, petugas, dan koleksi buku per item
    $member = Member::with(['loans.loanItems.book', 'loans.user'])->findOrFail($id);

    return view('members.show', compact('member'));
}
```

Buat berkas tampilan rincian anggota `resources/views/members/show.blade.php`:

```blade
{{-- File: resources/views/members/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Rincian Profil Anggota - Perpustakaan Digital')

@section('content')
    <div class="card">
        <a href="{{ route('members.index') }}" style="color: #4b5563; text-decoration: none; font-size: 14px;">&larr; Kembali ke Daftar Anggota</a>
        <h2 style="margin-top: 12px; margin-bottom: 20px;">Rincian Data Anggota</h2>

        <table>
            <tr>
                <th style="width: 200px;">Nama Lengkap</th>
                <td><strong>{{ $member->nama }}</strong></td>
            </tr>
            <tr>
                <th>NIM</th>
                <td>{{ $member->nim }}</td>
            </tr>
            <tr>
                <th>Alamat Email</th>
                <td>{{ $member->email }}</td>
            </tr>
            <tr>
                <th>Nomor Telepon</th>
                <td>{{ $member->nomor_telepon }}</td>
            </tr>
            <tr>
                <th>Alamat Domisili</th>
                <td>{{ $member->alamat }}</td>
            </tr>
            <tr>
                <th>Status Keanggotaan</th>
                <td>
                    @if ($member->status === 'aktif')
                        <span style="background-color: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">Aktif</span>
                    @else
                        <span style="background-color: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">Nonaktif</span>
                    @endif
                </td>
            </tr>
        </table>

        <h3 style="margin-top: 32px; margin-bottom: 12px;">Riwayat Sirkulasi Peminjaman</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Tanggal Pinjam</th>
                    <th>Jatuh Tempo</th>
                    <th>Petugas Pencatat</th>
                    <th>Koleksi Buku yang Dipinjam</th>
                    <th style="width: 110px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($member->loans as $loan)
                    <tr>
                        <td>#{{ $loan->id }}</td>
                        <td>{{ $loan->tanggal_pinjam }}</td>
                        <td>{{ $loan->tanggal_kembali }}</td>
                        <td>{{ $loan->user->name ?? 'Sistem' }}</td>
                        <td>
                            <ul style="margin: 0; padding-left: 18px;">
                                @foreach ($loan->loanItems as $item)
                                    <li>{{ $item->book->judul ?? 'Buku Tidak Terdaftar' }}</li>
                                @endforeach
                            </ul>
                        </td>
                        <td>
                            @if ($loan->status === 'dikembalikan')
                                <span style="background-color: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Selesai</span>
                            @elseif ($loan->status === 'terlambat')
                                <span style="background-color: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Terlambat</span>
                            @else
                                <span style="background-color: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Dipinjam</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #6b7280; padding: 16px;">Anggota ini belum memiliki riwayat transaksi peminjaman buku.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
```

---

### Langkah 5: Konstruksi CRUD Penuh Modul Peminjaman (Loans)

Buka berkas `app/Http/Controllers/LoanController.php` dan implementasikan seluruh metode CRUD:

```php
// File: app/Http/Controllers/LoanController.php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index()
    {
        $loans = Loan::with(['member', 'user', 'loanItems.book'])->paginate(10);

        return view('loans.index', compact('loans'));
    }

    public function create()
    {
        $members = Member::where('status', 'aktif')->get();
        $books   = Book::where('stok', '>', 0)->get();
        $users   = User::all();

        return view('loans.create', compact('members', 'books', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id'       => 'required|integer|exists:members,id',
            'user_id'         => 'required|integer|exists:users,id',
            'tanggal_pinjam'  => 'required|date',
            'tanggal_kembali' => 'required|date|after_or_equal:tanggal_pinjam',
            'book_ids'        => 'required|array|min:1',
            'book_ids.*'      => 'integer|exists:books,id',
        ], [
            'member_id.required'       => 'Peminjam wajib dipilih dari daftar anggota.',
            'user_id.required'         => 'Petugas pencatat transaksi wajib ditentukan.',
            'tanggal_pinjam.required'  => 'Tanggal peminjaman wajib diisi.',
            'tanggal_kembali.required' => 'Tanggal rencana pengembalian wajib diisi.',
            'tanggal_kembali.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
            'book_ids.required'        => 'Pilih minimal satu buku untuk dipinjam.',
        ]);

        // 1. Simpan rekaman transaksi peminjaman utama
        $loan = Loan::create([
            'member_id'       => $validated['member_id'],
            'user_id'         => $validated['user_id'],
            'tanggal_pinjam'  => $validated['tanggal_pinjam'],
            'tanggal_kembali' => $validated['tanggal_kembali'],
            'status'          => 'dipinjam',
        ]);

        // 2. Simpan setiap buku pinjaman ke tabel loan_items secara relasional
        foreach ($validated['book_ids'] as $bookId) {
            $loan->loanItems()->create([
                'book_id' => $bookId,
            ]);
        }

        return redirect()->route('loans.index')
            ->with('success', 'Transaksi peminjaman buku berhasil diterbitkan.');
    }

    public function show(string $id)
    {
        $loan = Loan::with(['member', 'user', 'loanItems.book'])->findOrFail($id);

        return view('loans.show', compact('loan'));
    }

    public function edit(string $id)
    {
        $loan = Loan::with(['member', 'user', 'loanItems.book'])->findOrFail($id);

        return view('loans.edit', compact('loan'));
    }

    public function update(Request $request, string $id)
    {
        $loan = Loan::findOrFail($id);

        $validated = $request->validate([
            'tanggal_kembali' => 'required|date|after_or_equal:tanggal_pinjam',
            'status'          => 'required|in:dipinjam,dikembalikan,terlambat',
        ], [
            'tanggal_kembali.required'       => 'Tanggal kembali wajib diisi.',
            'tanggal_kembali.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
            'status.required'                => 'Status peminjaman wajib dipilih.',
        ]);

        $loan->update($validated);

        return redirect()->route('loans.index')
            ->with('success', 'Status transaksi peminjaman berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $loan = Loan::findOrFail($id);
        
        // Hapus rekaman anak di loan_items terlebih dahulu
        $loan->loanItems()->delete();
        $loan->delete();

        return redirect()->route('loans.index')
            ->with('success', 'Transaksi peminjaman berhasil dihapus.');
    }

    public function kembalikan(string $id)
    {
        $loan = Loan::findOrFail($id);
        $loan->update([
            'status'               => 'dikembalikan',
            'tanggal_dikembalikan' => now()->toDateString(),
        ]);

        return redirect()->route('loans.index')
            ->with('success', "Buku pada transaksi #{$loan->id} telah berhasil dikembalikan.");
    }
}
```

Bentuk berkas tampilan modul peminjaman berikut:

#### 1. Berkas: `resources/views/loans/index.blade.php`
```blade
{{-- File: resources/views/loans/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Sirkulasi Peminjaman - Perpustakaan Digital')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="margin: 0;">Sirkulasi Transaksi Peminjaman</h2>
            <a href="{{ route('loans.create') }}" class="btn">+ Transaksi Peminjaman Baru</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Nama Anggota</th>
                    <th>Petugas</th>
                    <th>Koleksi Buku</th>
                    <th>Tgl Pinjam</th>
                    <th>Tgl Kembali</th>
                    <th style="width: 110px;">Status</th>
                    <th style="width: 200px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td>#{{ $loan->id }}</td>
                        <td><strong>{{ $loan->member->nama ?? 'Anggota Terhapus' }}</strong></td>
                        <td>{{ $loan->user->name ?? 'Petugas' }}</td>
                        <td>
                            @foreach ($loan->loanItems as $item)
                                <div>&bull; {{ $item->book->judul ?? 'Buku Tidak Terdaftar' }}</div>
                            @endforeach
                        </td>
                        <td>{{ $loan->tanggal_pinjam }}</td>
                        <td>{{ $loan->tanggal_kembali }}</td>
                        <td>
                            @if ($loan->status === 'dikembalikan')
                                <span style="background-color: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Dikembalikan</span>
                            @elseif ($loan->status === 'terlambat')
                                <span style="background-color: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Terlambat</span>
                            @else
                                <span style="background-color: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Dipinjam</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('loans.show', $loan->id) }}">Rincian</a>
                            |
                            <a href="{{ route('loans.edit', $loan->id) }}">Ubah</a>
                            |
                            @if ($loan->status === 'dipinjam')
                                <form class="inline" action="{{ route('loans.kembalikan', $loan->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" style="background:none; border:none; color:#059669; cursor:pointer; text-decoration:underline;">Kembalikan</button>
                                </form>
                                |
                            @endif
                            <form class="inline" action="{{ route('loans.destroy', $loan->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger" onclick="return confirm('Hapus transaksi ini?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; color: #6b7280; padding: 20px;">Belum ada riwayat transaksi peminjaman.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            {{ $loans->links() }}
        </div>
    </div>
@endsection
```

#### 2. Berkas: `resources/views/loans/create.blade.php`
```blade
{{-- File: resources/views/loans/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Transaksi Peminjaman Baru - Perpustakaan Digital')

@section('content')
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <a href="{{ route('loans.index') }}" style="color: #4b5563; text-decoration: none; font-size: 14px;">&larr; Kembali ke Daftar Peminjaman</a>
        <h2 style="margin-top: 12px; margin-bottom: 20px;">Terbitkan Transaksi Peminjaman</h2>

        <form action="{{ route('loans.store') }}" method="POST">
            @csrf

            <label for="member_id" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Pilih Anggota Peminjam</label>
            <select name="member_id" id="member_id" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
                <option value="">-- Pilih Anggota Terdaftar --</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected(old('member_id') == $member->id)>
                        {{ $member->nama }} (NIM: {{ $member->nim }})
                    </option>
                @endforeach
            </select>
            @error('member_id')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="user_id" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Petugas Pelayanan</label>
            <select name="user_id" id="user_id" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
                <option value="">-- Pilih Petugas yang Bertugas --</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                        {{ $user->name }} ({{ ucfirst($user->role ?? 'Petugas') }})
                    </option>
                @endforeach
            </select>
            @error('user_id')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <div style="display: flex; gap: 16px; margin-top: 14px;">
                <div style="flex: 1;">
                    <label for="tanggal_pinjam" style="display:block; font-weight:600; font-size:14px;">Tanggal Pinjam</label>
                    <input type="date" name="tanggal_pinjam" id="tanggal_pinjam" value="{{ old('tanggal_pinjam', date('Y-m-d')) }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
                    @error('tanggal_pinjam')
                        <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
                    @enderror
                </div>
                <div style="flex: 1;">
                    <label for="tanggal_kembali" style="display:block; font-weight:600; font-size:14px;">Batas Waktu Pengembalian</label>
                    <input type="date" name="tanggal_kembali" id="tanggal_kembali" value="{{ old('tanggal_kembali', date('Y-m-d', strtotime('+7 days'))) }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
                    @error('tanggal_kembali')
                        <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <label style="display:block; font-weight:600; font-size:14px; margin-top:16px;">Pilih Koleksi Buku (Dapat Memilih Lebih dari Satu):</label>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; margin-top: 4px; background-color: #fafafa;">
                @forelse ($books as $book)
                    <div style="margin-bottom: 8px;">
                        <label style="font-weight: normal; font-size: 14px; cursor: pointer;">
                            <input type="checkbox" name="book_ids[]" value="{{ $book->id }}" @checked(is_array(old('book_ids')) && in_array($book->id, old('book_ids')))>
                            <strong>{{ $book->judul }}</strong> &bull; {{ $book->penulis }} (Tersedia: {{ $book->stok }} eks)
                        </label>
                    </div>
                @empty
                    <div style="color: #6b7280; font-size: 14px;">Tidak ada koleksi buku yang memiliki stok saat ini.</div>
                @endforelse
            </div>
            @error('book_ids')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn" style="margin-top:24px; width:100%;">Terbitkan Transaksi Pinjaman</button>
        </form>
    </div>
@endsection
```

#### 3. Berkas: `resources/views/loans/show.blade.php`
```blade
{{-- File: resources/views/loans/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Rincian Transaksi Peminjaman - Perpustakaan Digital')

@section('content')
    <div class="card" style="max-width: 650px; margin: 0 auto;">
        <a href="{{ route('loans.index') }}" style="color: #4b5563; text-decoration: none; font-size: 14px;">&larr; Kembali ke Daftar Peminjaman</a>
        <h2 style="margin-top: 12px; margin-bottom: 20px;">Rincian Transaksi Peminjaman #{{ $loan->id }}</h2>

        <table>
            <tr>
                <th style="width: 200px;">Peminjam (Anggota)</th>
                <td><strong>{{ $loan->member->nama ?? 'Data Tidak Ditemukan' }}</strong> (NIM: {{ $loan->member->nim ?? '-' }})</td>
            </tr>
            <tr>
                <th>Petugas Pencatat</th>
                <td>{{ $loan->user->name ?? 'Petugas' }}</td>
            </tr>
            <tr>
                <th>Tanggal Pinjam</th>
                <td>{{ $loan->tanggal_pinjam }}</td>
            </tr>
            <tr>
                <th>Jatuh Tempo Pengembalian</th>
                <td>{{ $loan->tanggal_kembali }}</td>
            </tr>
            <tr>
                <th>Tanggal Pengembalian Riil</th>
                <td>{{ $loan->tanggal_dikembalikan ?? '-' }}</td>
            </tr>
            <tr>
                <th>Status Transaksi</th>
                <td>
                    @if ($loan->status === 'dikembalikan')
                        <span style="background-color: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Dikembalikan</span>
                    @elseif ($loan->status === 'terlambat')
                        <span style="background-color: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Terlambat</span>
                    @else
                        <span style="background-color: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Dipinjam</span>
                    @endif
                </td>
            </tr>
        </table>

        <h3 style="margin-top: 28px; margin-bottom: 12px;">Daftar Buku yang Dipinjam</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Judul Buku</th>
                    <th>Penulis</th>
                    <th>Penerbit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($loan->loanItems as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->book->judul ?? 'Buku Tidak Terdaftar' }}</strong></td>
                        <td>{{ $item->book->penulis ?? '-' }}</td>
                        <td>{{ $item->book->penerbit ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
```

#### 4. Berkas: `resources/views/loans/edit.blade.php`
```blade
{{-- File: resources/views/loans/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Perbarui Transaksi Peminjaman - Perpustakaan Digital')

@section('content')
    <div class="card" style="max-width: 550px; margin: 0 auto;">
        <a href="{{ route('loans.index') }}" style="color: #4b5563; text-decoration: none; font-size: 14px;">&larr; Kembali ke Daftar Peminjaman</a>
        <h2 style="margin-top: 12px; margin-bottom: 20px;">Perbarui Status Peminjaman #{{ $loan->id }}</h2>

        <div style="background-color: #f3f4f6; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;">
            <div><strong>Peminjam:</strong> {{ $loan->member->nama ?? 'Anggota' }} (NIM: {{ $loan->member->nim ?? '-' }})</div>
            <div style="margin-top: 4px;"><strong>Buku yang Dipinjam:</strong>
                @foreach ($loan->loanItems as $item)
                    {{ $item->book->judul ?? '-' }}@if (!$loop->last), @endif
                @endforeach
            </div>
        </div>

        <form action="{{ route('loans.update', $loan->id) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="tanggal_pinjam" value="{{ $loan->tanggal_pinjam }}">

            <label for="tanggal_kembali" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Tanggal Jatuh Tempo Pengembalian</label>
            <input type="date" name="tanggal_kembali" id="tanggal_kembali" value="{{ old('tanggal_kembali', $loan->tanggal_kembali) }}" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
            @error('tanggal_kembali')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <label for="status" style="display:block; font-weight:600; font-size:14px; margin-top:14px;">Status Peminjaman</label>
            <select name="status" id="status" style="width:100%; padding:8px 10px; margin-top:4px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;">
                <option value="dipinjam" @selected(old('status', $loan->status) === 'dipinjam')>Dipinjam</option>
                <option value="dikembalikan" @selected(old('status', $loan->status) === 'dikembalikan')>Dikembalikan</option>
                <option value="terlambat" @selected(old('status', $loan->status) === 'terlambat')>Terlambat</option>
            </select>
            @error('status')
                <div style="color:#dc2626; font-size:13px; margin-top:4px;">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn" style="margin-top:24px; width:100%;">Simpan Perubahan Status</button>
        </form>
    </div>
@endsection
```

---

### Langkah 6: Pengujian dan Verifikasi Integrasi Relasi pada Web Browser

Nyalakan server:
```bash
php artisan serve
```

Lakukan verifikasi skenario:
1. Buka halaman `/books` &rarr; Pastikan kolom kategori menyajikan nama teks kategori (misal "Teknologi", "Fiksi") bukan angka ID.
2. Buka halaman `/loans/create` &rarr; Buat transaksi peminjaman baru dengan memilih anggota, petugas, serta mencentang minimal 2 judul buku.
3. Setelah dialihkan ke `/loans` &rarr; Pastikan baris transaksi baru menampilkan nama anggota, nama petugas, serta judul buku yang dipinjam.
4. Klik tombol **Kembalikan** &rarr; Pastikan status peminjaman berubah menjadi `dikembalikan` dan tanggal pengembalian otomatis terisi.
5. Akses rincian anggota `/members/{id}` &rarr; Pastikan tabel riwayat peminjaman menampilkan transaksi yang baru saja dieksekusi.

---

## 5. Checkpoint Git Praktikum

Setelah menyelesaikan seluruh langkah di atas dan menguji keterhubungan relasi multi-tabel, simpan progres kerja Anda pada repositori lokal dan publikasikan ke cabang `dev`:

```bash
git status
git add .
git commit -m "[P-7] implementasi eloquent relationships dan crud loans"
git push origin dev
```

---

## 6. Tugas Mandiri

Tingkatkan modul sirkulasi peminjaman dengan menyelesaikan dua penyempurnaan fitur berikut:

1. **Otomatisasi Penurunan dan Pemulihan Stok Buku:**
   - Pada `LoanController@store`, kurangi stok buku sebanyak 1 eks untuk setiap buku yang dipinjam (`$book->decrement('stok')`).
   - Pada `LoanController@kembalikan`, pulihkan stok buku sebanyak 1 eks (`$book->increment('stok')`).
2. **Badge Status Berwarna Dinamis:**
   - Tambahkan kelas CSS pada master layout `layouts/app.blade.php`: `.badge-success` (latar hijau), `.badge-warning` (latar kuning), dan `.badge-danger` (latar merah).
   - Terapkan kelas badge tersebut pada kolom status di `resources/views/loans/index.blade.php`.

**Keluaran Tugas:**
- Tautan komit repositori GitHub pada cabang pengembangan `dev`.
- Tangkapan layar (*screenshot*) saat stok buku otomatis berkurang setelah transaksi peminjaman diterbitkan, dan bertambah kembali setelah buku dikembalikan.

---

## Navigasi Modul
- [← Pertemuan 06: Evaluasi Tengah Semester (UTS)](./pertemuan-06.md)
- [Daftar Isi Modul](./README.md)
- [Pertemuan 08: Autentikasi Pengguna dan Middleware Hak Akses](./pertemuan-08.md) &rarr;
