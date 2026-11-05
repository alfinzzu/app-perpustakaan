# Pertemuan 4: Blade Templating dan Pembuatan Layout Admin di Laravel 12

> **Informasi Modul:**
> - **Prasyarat:** `BookController` dan `CategoryController` telah mengimplementasikan logika pengendali berbasis data *array dummy* serta validasi formulir dari Pertemuan 3.
> - **Fokus Pembelajaran:** Mengeliminasi redundansi kode antarmuka dengan mengonstruksi sistem tata letak terpusat (*Master Layout*), memisahkan komponen antarmuka yang dapat digunakan kembali (*reusable partials*), memahami mekanisme kompilasi dan sanitasi keamanan Blade (*XSS protection*), serta memodernisasi tampilan seluruh modul resource (`books`, `categories`, `members`).

---

## 1. Capaian Pembelajaran

Setelah menyelesaikan modul pertemuan ini, mahasiswa diharapkan mampu:
1. Menjelaskan konsep dasar *template engine* dan keunggulannya dibandingkan pencampuran PHP natif dengan HTML.
2. Memahami mekanisme kerja kompilasi Blade di balik layar (*view caching*) pada direktori `storage/framework/views/`.
3. Membedakan keluaran tersanitasi aman (`{{ $data }}`) dengan keluaran mentah (`{!! $html !!}`) untuk mencegah celah keamanan *Cross-Site Scripting* (XSS).
4. Menguasai direktif struktural Blade: pengondisian (`@if`, `@unless`), perulangan (`@foreach`, `@forelse`), serta variabel iterasi `$loop`.
5. Mengimplementasikan arsitektur *Layout Inheritance* menggunakan kombinasi direktif `@extends`, `@section`, dan `@yield`.
6. Memecah komponen tampilan yang berulang ke dalam berkas parsial menggunakan direktif `@include`.
7. Mengembangkan bilah navigasi dinamis dengan penanda status aktif otomatis berbasis rute menggunakan `request()->routeIs()`.

---

## 2. Konsep Dasar Template Engine dan Blade

### 2.1 Mengapa Template Engine Dibutuhkan? (Prinsip DRY)

Perhatikan kembali berkas tampilan yang telah dibangun pada Pertemuan 3:
- `resources/views/books/index.blade.php`
- `resources/views/books/create.blade.php`
- `resources/views/categories/index.blade.php`

Ketiga berkas tersebut memuat deklarasi `<!DOCTYPE html>`, elemen `<head>`, pustaka fon, dan blok kode `<style>` CSS yang nyaris identik dan ditulis berulang-ulang.

Jika di kemudian hari kita ingin menambahkan bilah navigasi (*navbar*), memperbarui tautan CSS, atau mengubah warna tema dasar, kita terpaksa menyunting puluhan berkas secara manual satu per satu. Kelalaian memperbarui satu berkas saja akan memicu inkonsistensi antarmuka.

Kondisi tersebut melanggar prinsip fundamental rekayasa perangkat lunak: **DRY (Don't Repeat Yourself)**. Template engine hadir untuk mengatasi masalah ini dengan menyediakan mekanisme sentralisasi kerangka tampilan (*layout inheritance*).

### 2.2 Pemisahan Tampilan dan Logika Bisnis

Pada era awal web, PHP dirancang sebagai bahasa skrip yang disisipkan langsung ke dalam dokumen HTML menggunakan tag `<?php ... ?>`. Meskipun praktis pada mulanya, kebebasan ini sering disalahgunakan:
- Pengembang menyisipkan query SQL langsung di dalam tag HTML.
- Logika kalkulasi bisnis bercampur dengan tag presentasi visual.
- Kode menjadi sangat sulit dibaca, sulit diuji (*untestable*), dan rentan konflik kolaborasi tim (*merge conflicts*).

Template engine seperti **Blade** menegakkan batasan arsitektural:
Blade sengaja dirancang agar ekspresi tampilan ringkas dan tidak memfasilitasi penulisan query database yang kompleks di dalam antarmuka. Tanggung jawab pengolahan data tetap terkunci di lapisan *Controller* dan *Model*, sementara berkas Blade murni bertanggung jawab memformat data untuk pengguna.

### 2.3 Mekanisme Kompilasi Blade di Balik Layar

Blade bukanlah pustaka yang mem-parsing ulang sintaks pada setiap siklus permintaan HTTP. Seluruh berkas berekstensi `.blade.php` akan **dikompilasi menjadi berkas PHP natif murni** dan disimpan sebagai berkas *cache* pada direktori:

```text
storage/framework/views/
```

Kompilasi ulang hanya dilakukan jika tanggal modifikasi berkas sumber `.blade.php` lebih baru daripada berkas *cache*-nya. Oleh karena itu, pada tahap produksi (*production*), waktu eksekusi Blade setara dengan performa PHP biasa tanpa ada penurunan kecepatan (*zero-cost abstraction*).

Contoh transformasi sintaks Blade menjadi kode PHP hasil kompilasi:

```blade
{{-- Penulisan pada berkas Blade: --}}
@if ($book['stok'] > 0)
    <span>{{ $book['judul'] }}</span>
@endif
```

```php
// Kode hasil kompilasi Laravel di storage/framework/views/:
<?php if ($book['stok'] > 0): ?>
    <span><?php echo e($book['judul']); ?></span>
<?php endif; ?>
```

Fungsi pembantu `e()` di atas merupakan pembungkus fungsi bawaan PHP `htmlspecialchars()` dengan flag `ENT_QUOTES | ENT_SUBSTITUTE` dan enkripsi `UTF-8`.

### 2.4 Keamanan Tampilan: Echo Aman vs Raw Output (Pencegahan XSS)

Blade menyediakan dua format pencetakan variabel ke antarmuka:

| Sintaks Blade | Fungsi Eksekusi di PHP | Karakteristik Keamanan |
| :--- | :--- | :--- |
| `{{ $data }}` | `echo e($data);` | **Tersanitasi Otomatis (Auto-Escaping):** Mengonversi karakter khusus HTML (`<`, `>`, `&`, `"`, `'`) menjadi entitas HTML. Aman dari serangan *Cross-Site Scripting* (XSS). |
| `{!! $data !!}` | `echo $data;` | **Keluaran Mentah (Raw Output):** Mencetak data apa adanya tanpa sanitasi. Hanya boleh digunakan untuk konten HTML tepercaya (misal hasil kurasi editor WYSIWYG yang sudah dibersihkan). |

> [!WARNING]
> **Bahaya Serangan XSS (Cross-Site Scripting):**
> Jika seorang pengguna jahat mendaftarkan judul buku berupa payload:
> `<script>alert('Sesi Dibajak!');</script>`
> - Jika dicetak via `{{ $judul }}`: Peramban merendernya sebagai teks biasa `&lt;script&gt;...&lt;/script&gt;` tanpa mengeksekusi skrip.
> - Jika dicetak via `{!! $judul !!}`: Peramban akan **menjalankan skrip berbahaya** tersebut, yang berpotensi mencuri cookie otentikasi sesi atau memanipulasi tampilan sistem.
> 
> **Aturan Baku:** Selalu gunakan `{{ }}` untuk mencetak data yang bersumber dari masukan pengguna atau basis data.

---

## 3. Fitur dan Direktif Inti Blade di Laravel 12

### 3.1 Direktif Pengondisian

Blade menyediakan pintasan sintaks yang bersih untuk menggantikan percabangan logika PHP:

```blade
{{-- Percabangan standar --}}
@if ($book['stok'] > 5)
    <span class="badge badge-success">Stok Melimpah</span>
@elseif ($book['stok'] > 0)
    <span class="badge badge-warning">Stok Terbatas</span>
@else
    <span class="badge badge-danger">Habis</span>
@endif

{{-- Kebalikan dari @if (eksekusi jika bernilai false) --}}
@unless ($books->isEmpty())
    <p>Katalog buku tersedia.</p>
@endunless

{{-- Pengecekan autentikasi pengguna --}}
@auth
    <p>Selamat datang, {{ auth()->user()->name }}!</p>
@endauth

@guest
    <a href="/login">Masuk ke Akun</a>
@endguest
```

### 3.2 Direktif Perulangan dan Variabel `$loop`

Direktif `@forelse` merupakan kombinasi elegan antara perulangan `@foreach` dan pemeriksaan koleksi kosong:

```blade
@forelse ($books as $book)
    <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $book['judul'] }}</td>
    </tr>
@empty
    <tr>
        <td colspan="2">Belum ada data buku yang terdaftar.</td>
    </tr>
@endforelse
```

Di dalam blok `@foreach` atau `@forelse`, Blade menyediakan variabel `$loop` yang memuat metadata penting:

| Properti Variabel | Tipe Data | Deskripsi Nilai |
| :--- | :--- | :--- |
| `$loop->index` | `int` | Indeks iterasi saat ini, dimulai dari angka **0**. |
| `$loop->iteration` | `int` | Nomor urut iterasi saat ini, dimulai dari angka **1** (ideal untuk kolom nomor urut tabel). |
| `$loop->remaining` | `int` | Sisa iterasi item yang belum dieksekusi. |
| `$loop->count` | `int` | Total jumlah item dalam array atau koleksi yang sedang diulang. |
| `$loop->first` | `bool` | Bernilai `true` jika sedang memproses iterasi pertama. |
| `$loop->last` | `bool` | Bernilai `true` jika sedang memproses iterasi terakhir. |
| `$loop->even` / `$loop->odd` | `bool` | Bernilai `true` jika posisi iterasi berada pada nomor genap / ganjil. |
| `$loop->depth` | `int` | Tingkat kedalaman perulangan saat ini (dimulai dari 1). |
| `$loop->parent` | `object` | Mengakses variabel `$loop` dari perulangan induk jika terjadi perulangan bersarang (*nested loops*). |

### 3.3 Anatomi Pewarisan Tata Letak: `@extends`, `@section`, dan `@yield`

Konstruksi tata letak bersama (*Master Layout*) dibangun atas tiga direktif utama:

```text
+-----------------------------------------------------------------+
| resources/views/layouts/app.blade.php                           |
| (MASTER TEMPLATE)                                               |
|                                                                 |
|   <!DOCTYPE html>                                               |
|   <html>                                                        |
|   <head>                                                        |
|       <title>@yield('title', 'Perpustakaan Digital')</title>   |
|   </head>                                                       |
|   <body>                                                        |
|       @include('partials.navbar')                               |
|                                                                 |
|       <main>                                                    |
|           @yield('content')  <------------------------------+   |
|       </main>                                               |   |
|                                                             |   |
|       @include('partials.footer')                           |   |
|   </body>                                                   |   |
|   </html>                                                   |   |
+-------------------------------------------------------------|---+
                                                              |
                                                              | Diisi oleh:
+-------------------------------------------------------------|---+
| resources/views/books/index.blade.php                       |   |
| (CHILD VIEW)                                                |   |
|                                                             |   |
|   @extends('layouts.app')                                   |   |
|   @section('title', 'Katalog Buku')                         |   |
|                                                             |   |
|   @section('content') --------------------------------------+   |
|       <h1>Koleksi Buku</h1>                                     |
|       <!-- Tabel Data Buku -->                                  |
|   @endsection                                                   |
+-----------------------------------------------------------------+
```

- **`@yield('placeholder', 'Default')`:** Menandai wadah slot kosong pada *layout* induk yang akan diisi oleh *child view*. Argumen kedua berfungsi sebagai nilai default jika *child view* tidak menyediakannya.
- **`@extends('layouts.app')`:** Wajib ditulis pada baris paling awal berkas *child view* untuk mendefinisikan layout induk yang diwarisi.
- **`@section('placeholder') ... @endsection`:** Mendefinisikan bongkahan konten spesifik untuk dimasukkan ke dalam slot `@yield` yang bernama sama pada layout induk.

### 3.4 Urutan dan Alur Eksekusi Rendering Blade (Reverse Parsing)

Secara teknis, Laravel tidak membaca *layout* induk terlebih dahulu lalu mencari *child view*. Alur kerja yang benar adalah:
1. Controller memanggil `return view('books.index')`.
2. Laravel membuka berkas *child view* (`books.index.blade.php`).
3. Direktif `@extends('layouts.app')` menandakan bahwa berkas ini memiliki tata letak induk. Seluruh blok `@section(...)` dikantongi sementara ke dalam memori.
4. Setelah pembacaan *child view* selesai, Laravel memuat berkas *master layout* (`layouts.app`).
5. Saat parser menemukan `@yield('nama_section')`, Laravel menyuntikkan isi blok yang telah dikantongi sebelumnya.
6. Hasil penggabungan utuh dikirimkan sebagai respons HTTP ke peramban.

### 3.5 Komponen Parsial (`@include`) dan Penanda Navigasi Aktif

Direktif `@include('nama.berkas')` digunakan untuk menyisipkan potongan kode antarmuka kecil yang modular, seperti bilah menu (`partials/navbar.blade.php`) atau kotak notifikasi (`partials/alert.blade.php`).

Untuk menandai menu bilah navigasi mana yang sedang aktif tanpa harus mengirimkan variabel dari setiap Controller, kita dapat memanfaatkan fungsi pembantu bawaan:

```blade
request()->routeIs('books.*')
```

Metode ini akan mengembalikan nilai `true` jika rute yang sedang diakses peramban cocok dengan pola wildcard yang ditentukan (misalnya `books.index`, `books.create`, `books.edit`, atau `books.show`).

---

## 4. Langkah-Langkah Praktikum

### Prasyarat Praktikum
Pastikan terminal aktif di direktori proyek `app-perpustakaan` dan berada pada cabang `dev`:

```bash
cd /home/ububu/app-perpustakaan
git checkout dev
```

---

### Langkah 1: Pembuatan Master Layout

Bentuk berkas tata letak utama pada direktori `resources/views/layouts/app.blade.php`:

```blade
{{-- File: resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sistem Perpustakaan Digital Kampus')</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background-color: #f9fafb; color: #1f2937; }
        nav { background-color: #1e3a8a; padding: 14px 40px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        nav .brand { color: #ffffff; font-weight: bold; font-size: 18px; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        nav ul { list-style: none; display: flex; gap: 24px; margin: 0; padding: 0; }
        nav ul li a { color: #cbd5e1; text-decoration: none; padding: 6px 4px; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        nav ul li a:hover { color: #ffffff; }
        nav ul li a.active { color: #ffffff; font-weight: 700; border-bottom: 2px solid #60a5fa; }
        main { max-width: 1000px; margin: 32px auto; padding: 0 24px; min-height: 75vh; }
        .card { background: #ffffff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 10px 14px; text-align: left; font-size: 14px; }
        th { background-color: #f3f4f6; color: #374151; font-weight: 600; }
        tr:hover { background-color: #f9fafb; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .btn { display: inline-block; padding: 8px 16px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; border: none; cursor: pointer; }
        .btn:hover { background-color: #1d4ed8; }
        .btn-danger { background: none; border: none; color: #dc2626; cursor: pointer; padding: 0; font-size: inherit; text-decoration: underline; }
        form.inline { display: inline; }
        footer { text-align: center; padding: 24px 20px; color: #6b7280; font-size: 13px; border-top: 1px solid #e5e7eb; margin-top: 48px; background-color: #ffffff; }
    </style>
</head>
<body>
    @include('partials.navbar')

    <main>
        @include('partials.alert')

        @yield('content')
    </main>

    <footer>
        &copy; {{ date('Y') }} Sistem Perpustakaan Digital Kampus &bull; Modul Laravel 12
    </footer>
</body>
</html>
```

---

### Langkah 2: Pembuatan Komponen Parsial Navbar

Buat direktori `resources/views/partials/` jika belum tersedia, lalu buat berkas `navbar.blade.php`:

```blade
{{-- File: resources/views/partials/navbar.blade.php --}}
<nav>
    <a href="{{ route('books.index') }}" class="brand">
        📚 Perpustakaan Digital
    </a>
    <ul>
        <li>
            <a href="{{ route('books.index') }}" class="{{ request()->routeIs('books.*') ? 'active' : '' }}">
                Katalog Buku
            </a>
        </li>
        <li>
            <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">
                Kategori
            </a>
        </li>
        <li>
            <a href="{{ route('members.index') }}" class="{{ request()->routeIs('members.*') ? 'active' : '' }}">
                Keanggotaan
            </a>
        </li>
        <li>
            <a href="{{ route('loans.index') }}" class="{{ request()->routeIs('loans.*') ? 'active' : '' }}">
                Sirkulasi Peminjaman
            </a>
        </li>
    </ul>
</nav>
```

---

### Langkah 3: Pembuatan Komponen Parsial Alert

Buat berkas `alert.blade.php` di dalam direktori `resources/views/partials/`:

```blade
{{-- File: resources/views/partials/alert.blade.php --}}
@if (session('success'))
    <div class="alert-success">
        <strong>Berhasil!</strong> {{ session('success') }}
    </div>
@endif
```

---

### Langkah 4: Refaktorisasi Tampilan Indeks Buku

Buka berkas `resources/views/books/index.blade.php`. Hapus seluruh tag boilerplate HTML (`<!DOCTYPE>`, `<html>`, `<head>`, `<body>`, dan tag `<style>`), lalu ubah kodenya menjadi pewarisan layout:

```blade
{{-- File: resources/views/books/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Katalog Buku - Perpustakaan Digital')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="margin: 0;">Katalog Koleksi Buku</h2>
            <a href="{{ route('books.create') }}" class="btn">+ Tambah Buku Baru</a>
        </div>

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
                    <th style="width: 170px;">Aksi</th>
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
                        <td>{{ $book['kategori'] }}</td>
                        <td>
                            <a href="{{ route('books.show', $book['id']) }}">Rincian</a>
                            |
                            <a href="{{ route('books.edit', $book['id']) }}">Ubah</a>
                            |
                            <form class="inline" action="{{ route('books.destroy', $book['id']) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data buku ini?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; color: #6b7280; padding: 20px;">Belum ada data koleksi buku yang tersedia.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
            <em>* Catatan: Data bersumber dari in-memory array dummy pada Controller dan akan terhubung ke basis data MySQL pada Pertemuan 5.</em>
        </p>
    </div>
@endsection
```

---

### Langkah 5: Refaktorisasi Tampilan Indeks Kategori

Buka berkas `resources/views/categories/index.blade.php` dan terapkan pewarisan layout yang sama:

```blade
{{-- File: resources/views/categories/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Daftar Kategori - Perpustakaan Digital')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="margin: 0;">Klasifikasi Kategori Koleksi</h2>
            <a href="{{ route('categories.create') }}" class="btn">+ Tambah Kategori Baru</a>
        </div>

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
                            <form class="inline" action="{{ route('categories.destroy', $category['id']) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger" onclick="return confirm('Hapus kategori ini?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #6b7280; padding: 20px;">Belum ada klasifikasi kategori yang tersimpan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
```

---

### Langkah 6: Implementasi `MemberController@index` dan `members/index.blade.php`

#### 1. Berkas: `app/Http/Controllers/MemberController.php`
Buka berkas `app/Http/Controllers/MemberController.php`, tambahkan array data dummy `$members`, lalu perbarui metode `index()`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MemberController extends Controller
{
    private array $members = [
        [
            'id'            => 1,
            'nama'          => 'Siti Aminah',
            'nim'           => '2310501001',
            'email'         => 'siti.aminah@pens.ac.id',
            'nomor_telepon' => '081234567890',
            'status'        => 'aktif'
        ],
        [
            'id'            => 2,
            'nama'          => 'Budi Santoso',
            'nim'           => '2310501002',
            'email'         => 'budi.santoso@pens.ac.id',
            'nomor_telepon' => '081298765432',
            'status'        => 'aktif'
        ],
        [
            'id'            => 3,
            'nama'          => 'Dewi Lestari',
            'nim'           => '2310501003',
            'email'         => 'dewi.lestari@pens.ac.id',
            'nomor_telepon' => '081211122233',
            'status'        => 'nonaktif'
        ],
    ];

    public function index()
    {
        $members = $this->members;

        return view('members.index', compact('members'));
    }

    // Metode create, store, show, edit, update, destroy dipertahankan sesuai kerangka
}
```

#### 2. Berkas: `resources/views/members/index.blade.php`
Buat direktori `resources/views/members/` jika belum tersedia, lalu buat berkas `index.blade.php` dengan memanfaatkan master layout:

```blade
{{-- File: resources/views/members/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Data Keanggotaan - Perpustakaan Digital')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="margin: 0;">Data Anggota Perpustakaan</h2>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Nama Anggota</th>
                    <th>NIM</th>
                    <th>Alamat Email</th>
                    <th>No. Telepon</th>
                    <th style="width: 100px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>{{ $member['id'] }}</td>
                        <td><strong>{{ $member['nama'] }}</strong></td>
                        <td>{{ $member['nim'] }}</td>
                        <td>{{ $member['email'] }}</td>
                        <td>{{ $member['nomor_telepon'] }}</td>
                        <td>
                            @if ($member['status'] === 'aktif')
                                <span style="background-color: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">Aktif</span>
                            @else
                                <span style="background-color: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">Nonaktif</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #6b7280; padding: 20px;">Belum ada data anggota yang terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
            <em>* Catatan: Operasi CRUD keanggotaan secara komprehensif akan dihubungkan ke tabel MySQL pada modul berikutnya.</em>
        </p>
    </div>
@endsection
```

---

### Langkah 7: Verifikasi dan Uji Coba pada Web Browser

Nyalakan server pengembangan lokal:

```bash
php artisan serve
```

Lakukan pengujian antarmuka peramban secara komprehensif:
1. Akses `http://127.0.0.1:8000/books` &rarr; Periksa apakah bilah navigasi biru di bagian atas dan *footer* di bagian bawah tampil secara konsisten, serta menu **Katalog Buku** memiliki penanda aktif (`border-bottom`).
2. Klik menu **Kategori** (`/categories`) dan **Keanggotaan** (`/members`) &rarr; Pastikan transisi antarhalaman berjalan mulus tanpa adanya kerlipan perubahan struktur dasar *layout*.
3. Buka halaman `http://127.0.0.1:8000/books/create` lalu masukkan data buku baru &rarr; Pastikan saat dialihkan (*redirect*) kembali ke `/books`, kotak pesan sukses hijau dirender dengan benar melalui komponen parsial `alert.blade.php`.
4. Klik kanan pada peramban dan pilih **View Page Source** &rarr; Pastikan hanya terdapat tepat **satu** pasang tag `<!DOCTYPE html>`, `<html>`, dan `<head>`.

---

## 5. Checkpoint Git Praktikum

Setelah menyelesaikan seluruh langkah di atas dan memverifikasi integrasi tata letak bersama, simpan progres kerja Anda pada repositori lokal dan cabang `dev`:

```bash
git status
git add .
git commit -m "[P-4] implementasi master layout blade dan refaktor view index"
git push origin dev
```

---

## 6. Tugas Mandiri

Terapkan teknik *Layout Inheritance* pada formulir penambahan data yang telah dibangun di Pertemuan 3:

1. **Refaktorisasi `resources/views/books/create.blade.php`:**
   - Ganti struktur HTML mandiri dengan `@extends('layouts.app')`.
   - Masukkan isi formulir ke dalam blok `@section('content')`.
   - Pastikan notifikasi pesan galat validasi (`@error`) dan fungsi pembantu `old()` tetap bekerja sempurna.
2. **Refaktorisasi `resources/views/categories/create.blade.php`:**
   - Bungkus formulir input kategori menggunakan master layout `@extends('layouts.app')`.
3. **Penyediaan Tombol Navigasi Kembali:**
   - Pastikan terdapat tautan navigasi `&larr; Kembali` pada bagian atas formulir yang mengarah ke rute indeks masing-masing.

**Keluaran Tugas:**
- Tautan komit repositori GitHub pada cabang pengembangan `dev`.
- Tangkapan layar (*screenshot*) halaman `/books/create` dan `/categories/create` yang menampilkan bilah navigasi seragam hasil integrasi master layout.

---

## Navigasi Modul
- [← Pertemuan 03: Controller, Request, dan Form Validation di Laravel 12](./pertemuan-03.md)
- [Daftar Isi Modul](./README.md)
- [Pertemuan 05: Database Migration, Seeder, dan Eloquent ORM](./pertemuan-05.md) &rarr;
