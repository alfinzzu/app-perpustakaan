# Pertemuan 8: Autentikasi Pengguna dan Middleware Hak Akses di Laravel 12

> **Informasi Modul:**
> - **Prasyarat:** Telah menyelesaikan Pertemuan 7 (relasi antarmodel dan modul transaksi peminjaman buku multi-tabel pada proyek `app-perpustakaan`).
> - **Fokus Pembelajaran:** Membangun sistem autentikasi berbasis sesi (*session-based authentication*) secara mandiri dari awal tanpa *starter kit*, memproteksi rute CRUD menggunakan middleware bawaan `auth`, menciptakan middleware kustom `CheckAdminRole` untuk pembatasan hak akses berbasis peran (*Role-Based Access Control*), serta mengotomatisasi pengisian identitas petugas pencatat transaksi peminjaman melalui `auth()->id()`.

---

## 1. Capaian Pembelajaran

Setelah menyelesaikan modul pertemuan ini, mahasiswa diharapkan mampu:
1. Membedakan konsep dasar antara **autentikasi** (*siapa Anda*) dan **otorisasi** (*apa hak akses Anda*) dalam arsitektur perangkat lunak web modern.
2. Memahami mekanisme kerja autentikasi berbasis sesi (*Session-based Authentication*) dan perlindungan terhadap *Session Fixation Attack* melalui `session()->regenerate()`.
3. Mengembangkan alur masuk (*login*) dan keluar (*logout*) menggunakan kelas `Illuminate\Support\Facades\Auth` tanpa ketergantungan paket *starter kit*.
4. Mengamankan seluruh rute operasional CRUD menggunakan middleware bawaan `auth`.
5. Membuat middleware kustom `CheckAdminRole` dan mendaftarkan aliasnya pada berkas konfigurasi `bootstrap/app.php` di Laravel 12.
6. Menyajikan status akun pengguna aktif secara dinamis pada bilah navigasi Blade menggunakan direktif `@auth` dan `@guest`.
7. Mengintegrasikan ID pengguna yang sedang masuk (`auth()->id()`) ke dalam proses pencatatan sirkulasi peminjaman buku.

---

## 2. Konsep Dasar Autentikasi, Otorisasi, dan Middleware

### 2.1 Perbedaan Mendasar: Autentikasi vs Otorisasi

Setiap aplikasi multi-pengguna wajib memisahkan dua lapisan verifikasi keamanan utama:

| Dimensi | Autentikasi (*Authentication / AuthN*) | Otorisasi (*Authorization / AuthZ*) |
| :--- | :--- | :--- |
| **Pertanyaan Inti** | *"Siapakah Anda?"* (Verifikasi Identitas). | *"Apa saja wewenang yang boleh Anda lakukan?"* (Hak Akses). |
| **Mekanisme Uji** | Memeriksa kredensial rahasia (kombinasi email dan kata sandi ter-hash). | Memeriksa peran (*role*), kepemilikan data, atau izin (*permissions*). |
| **Frekuensi Evaluasi** | Umumnya dievaluasi sekali saat proses *login*, lalu identitas dipertahankan via sesi/token. | Dievaluasi berulang kali pada setiap pemanggilan aksi atau modul spesifik. |
| **Contoh Kasus** | Memvalidasi bahwa akun dengan email `petugas1@pens.ac.id` memiliki kata sandi yang valid. | Membatasi bahwa hanya akun berstatus `admin` yang diizinkan mengelola data Kategori Buku. |

### 2.2 Mekanisme Autentikasi: Berbasis Sesi (Session) vs Berbasis Token (JWT)

1. **Autentikasi Berbasis Sesi (*Stateful / Session-Based*):**
   - Peladen menyimpan rekaman sesi pengguna di memori/berkas/basis data lokal dan mengirimkan sebuah *cookie* pengenal sesi (*Session ID*) ke peramban.
   - Pada setiap permintaan lanjutan, peramban menyertakan *cookie* tersebut secara otomatis.
   - Pendekatan ini merupakan standar ideal untuk aplikasi web monolitik konvensional berbasis Blade karena didukung penuh oleh protokol penjelajah web.
2. **Autentikasi Berbasis Token (*Stateless / Token-Based / JWT*):**
   - Peladen tidak menyimpan data sesi, melainkan menerbitkan token digital terenkripsi yang memuat klaim identitas pengguna.
   - Klien (seperti aplikasi seluler Android/iOS atau antarmuka SPA React/Vue) wajib menyematkan token ini secara manual pada header `Authorization: Bearer <token>`.

### 2.3 Middleware sebagai Lapisan Filter Permintaan (Request Pipeline)

Middleware adalah komponen perangkat lunak yang bertindak sebagai rantai penyaring pipa (*pipeline*) di antara permintaan HTTP yang masuk dan penangan akhir (*Controller*):

```text
Permintaan Masuk (Client Request)
       │
       ▼
+-----------------------------------+
| Middleware 1: VerifyCsrfToken     |  --> [Gagal: Status 419 Token Expired]
+-----------------------------------+
       │ (Lolos)
       ▼
+-----------------------------------+
| Middleware 2: Authenticate (auth) |  --> [Belum Login: Redirect ke /login]
+-----------------------------------+
       │ (Lolos)
       ▼
+-----------------------------------+
| Middleware 3: CheckAdminRole      |  --> [Bukan Admin: Status 403 Forbidden]
+-----------------------------------+
       │ (Lolos)
       ▼
Controller Handler & Logika Bisnis
```

Setiap lapisan middleware memiliki wewenang untuk:
- Meneruskan permintaan ke tahap berikutnya menggunakan pemanggilan closure `$next($request)`.
- Menghentikan proses seketika (*short-circuiting*) dengan melempar respons galat (`abort(403)`) atau mengalihkan pengguna (`redirect()->route('login')`).

### 2.4 Keamanan Sesi: Pencegahan Session Fixation & Proteksi CSRF

- **Pencegahan *Session Fixation Attack*:**  
  Serangan pembajakan sesi di mana peretas menetapkan ID sesi tertentu sebelum pengguna masuk. Laravel menangkal ancaman ini dengan mewajibkan pemanggilan `$request->session()->regenerate()` tepat setelah kredensial berhasil divalidasi, sehingga ID sesi diacak ulang menjadi identitas baru yang aman.
- **Proteksi *Cross-Site Request Forgery* (CSRF):**  
  Direktif `@csrf` menyisipkan token rahasia acak pada setiap formulir `POST`, `PUT`, atau `DELETE`. Token ini memvalidasi bahwa permintaan berasal dari antarmuka sah aplikasi kita, bukan manipulasi eksternal dari situs berbahaya.

---

## 3. Fitur Inti Autentikasi dan Middleware di Laravel 12

### 3.1 Pemanfaatan `Auth` Facade

Fasad `Illuminate\Support\Facades\Auth` menyediakan gerbang operasional autentikasi terpadu:

```php
use Illuminate\Support\Facades\Auth;

// 1. Memvalidasi kredensial dan menginisialisasi sesi (mengembalikan boolean)
if (Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
    // Kredensial benar dan hash bcrypt cocok
}

// 2. Mengambil objek instansiasi pengguna yang sedang aktif (atau null jika tamu)
$user = Auth::user();

// 3. Mengambil primary key ID pengguna yang sedang masuk
$userId = Auth::id();

// 4. Memeriksa status login pengguna (mengembalikan boolean true/false)
if (Auth::check()) {
    // Pengguna telah terautentikasi
}

// 5. Menghancurkan status otentikasi pengguna
Auth::logout();
```

Metode `Auth::attempt()` secara cerdas membandingkan kata sandi teks polos kiriman pengguna dengan nilai hash *bcrypt* pada tabel basis data secara aman terhadap ancaman *timing attacks*.

### 3.2 Middleware Bawaan: `auth`, `guest`, dan `throttle`

- **`auth`:** Menolak akses bagi pengunjung yang belum terotentikasi dan secara otomatis mengalihkannya ke rute bernama `login`.
- **`guest`:** Kebalikan dari `auth`, mencegah pengguna yang sudah masuk untuk membuka kembali halaman autentikasi (seperti formulir login).
- **`throttle`:** Membatasi frekuensi pengiriman permintaan dalam durasi waktu tertentu guna mencegah serangan *brute-force* pada pintu masuk sistem.

### 3.3 Anatomi dan Registrasi Middleware Alias pada `bootstrap/app.php`

Pada Laravel 12, arsitektur konfigurasi telah disederhanakan tanpa keberadaan `Kernel.php`. Pendaftaran alias middleware dilakukan terpusat pada berkas `bootstrap/app.php`:

```php
// Berkas: bootstrap/app.php
use App\Http\Middleware\CheckAdminRole;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    // Konfigurasi routing...
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => CheckAdminRole::class,
        ]);
    })
    // Konfigurasi exception...
    ->create();
```

### 3.4 Direktif Kondisional Pengguna di Blade

Blade menyediakan direktif semantik untuk mengatur visibilitas tampilan berdasarkan status login:

```blade
@auth
    <span>Selamat bertugas, {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</span>
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit">Keluar</button>
    </form>
@endauth

@guest
    <a href="{{ route('login') }}">Masuk ke Sistem</a>
@endguest
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

### Langkah 1: Pembuatan dan Eksekusi `UserSeeder`

Buat seeder untuk menyiapkan akun pengguna awal (1 Administrator dan 2 Petugas Perpustakaan):

```bash
php artisan make:seeder UserSeeder
```

Buka berkas `database/seeders/UserSeeder.php` dan definisikan data awal menggunakan metode idempoten `updateOrCreate()`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Administrator Utama
        User::updateOrCreate(
            ['email' => 'admin@pens.ac.id'],
            [
                'name'     => 'Admin Perpustakaan',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        // 2. Akun Petugas Layanan 1
        User::updateOrCreate(
            ['email' => 'petugas1@pens.ac.id'],
            [
                'name'     => 'Petugas Satu',
                'password' => Hash::make('password'),
                'role'     => 'petugas',
            ]
        );

        // 3. Akun Petugas Layanan 2
        User::updateOrCreate(
            ['email' => 'petugas2@pens.ac.id'],
            [
                'name'     => 'Petugas Dua',
                'password' => Hash::make('password'),
                'role'     => 'petugas',
            ]
        );
    }
}
```

Daftarkan pemanggilan `UserSeeder` pada `database/seeders/DatabaseSeeder.php`:

```php
// File: database/seeders/DatabaseSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}
```

Jalankan perintah pengisian data seeder ke basis data:
```bash
php artisan db:seed --class=UserSeeder
```

---

### Langkah 2: Pembuatan Pengendali Autentikasi Mandiri (`AuthController`)

Buat pengendali polos tanpa *resource flags*:

```bash
php artisan make:controller AuthController
```

Buka berkas `app/Http/Controllers/AuthController.php` dan susun logika autentikasi:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Menyajikan formulir antarmuka login.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Memproses verifikasi kredensial login pengguna.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required'    => 'Alamat email wajib diisi.',
            'email.email'       => 'Format penulisan email tidak valid.',
            'password.required' => 'Kata sandi akun wajib diisi.',
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'email' => 'Kombinasi alamat email atau kata sandi tidak cocok.',
            ])->onlyInput('email');
        }

        // Regenerasi ID sesi untuk mencegah Session Fixation Attack
        $request->session()->regenerate();

        return redirect()->intended(route('books.index'))
            ->with('success', 'Otentikasi berhasil! Selamat bertugas, ' . Auth::user()->name . '.');
    }

    /**
     * Memproses penghentian sesi login pengguna (Logout).
     */
    public function logout(Request $request)
    {
        Auth::logout();

        // Invalidate dan regenerasi token keamanan sesi
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Anda telah berhasil keluar dari sistem perpustakaan.');
    }
}
```

---

### Langkah 3: Pembuatan Berkas Tampilan Antarmuka Login (`auth/login.blade.php`)

Buat direktori `resources/views/auth/` dan buat berkas `login.blade.php`:

```blade
{{-- File: resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem - Perpustakaan Digital Kampus</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #ffffff; padding: 36px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); width: 100%; max-width: 400px; border: 1px solid #e2e8f0; }
        .login-card h1 { font-size: 22px; margin-top: 0; margin-bottom: 8px; text-align: center; color: #1e293b; }
        .login-card p.subtitle { margin-top: 0; margin-bottom: 24px; text-align: center; color: #64748b; font-size: 14px; }
        label { display: block; margin-top: 14px; font-weight: 600; font-size: 14px; color: #334155; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px 12px; margin-top: 4px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        input:focus { outline: none; border-color: #2563eb; ring: 2px solid #bfdbfe; }
        .error { color: #dc2626; font-size: 13px; margin-top: 4px; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .checkbox-container { display: flex; align-items: center; gap: 8px; margin-top: 16px; font-size: 14px; color: #475569; }
        .btn-submit { margin-top: 24px; width: 100%; padding: 10px; background-color: #1e3a8a; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: 600; }
        .btn-submit:hover { background-color: #1e40af; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>📚 Portal Masuk</h1>
        <p class="subtitle">Sistem Perpustakaan Digital Kampus</p>

        @if (session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf

            <label for="email">Alamat Email Pengguna</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" placeholder="admin@pens.ac.id" autofocus>
            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror

            <label for="password">Kata Sandi</label>
            <input type="password" name="password" id="password" placeholder="••••••••">
            @error('password')
                <div class="error">{{ $message }}</div>
            @enderror

            <div class="checkbox-container">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember" style="margin-top: 0; font-weight: normal; cursor: pointer;">Ingat sesi login saya</label>
            </div>

            <button type="submit" class="btn-submit">Masuk ke Sistem</button>
        </form>
    </div>
</body>
</html>
```

---

### Langkah 4: Penataan Ulang Rute dan Proteksi Middleware `auth` pada `routes/web.php`

Buka berkas `routes/web.php` dan susun rute ke dalam struktur terisolasi:

```php
// File: routes/web.php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

// --- 1. RUTE PUBLIK (DAPAT DIAKSES SEBELUM LOGIN) ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- 2. RUTE TERPROTEKSI (WAJIB MEMILIKI SESI LOGIN) ---
Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('books.index');
    });

    // Modul Operasional CRUD Umum (Dapat diakses Admin dan Petugas)
    Route::resource('books', BookController::class);
    Route::resource('members', MemberController::class);
    Route::resource('loans', LoanController::class);
    Route::put('/loans/{id}/kembalikan', [LoanController::class, 'kembalikan'])
        ->name('loans.kembalikan');

    // Modul Khusus Administrator (Akan diproteksi middleware admin di Langkah 5)
    Route::middleware(['admin'])->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show']);
    });
});
```

---

### Langkah 5: Pembuatan dan Pendaftaran Middleware Kustom `CheckAdminRole`

Jalankan perintah pembuatan middleware:

```bash
php artisan make:middleware CheckAdminRole
```

Buka berkas `app/Http/Middleware/CheckAdminRole.php` dan definisikan pemeriksaan peran:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Memeriksa apakah pengguna memiliki atribut peran administrator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'Akses Ditolak: Halaman ini hanya boleh diakses oleh Administrator Sistem.');
        }

        return $next($request);
    }
}
```

Daftarkan alias middleware pada `bootstrap/app.php`:

```php
// File: bootstrap/app.php
use App\Http\Middleware\CheckAdminRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => CheckAdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

---

### Langkah 6: Pembaruan Bilah Navigasi Dinamis dan Identitas Akun Login

Perbarui berkas `resources/views/partials/navbar.blade.php`:

```blade
{{-- File: resources/views/partials/navbar.blade.php --}}
<nav>
    <a href="{{ route('books.index') }}" class="brand">
        📚 Perpustakaan Digital
    </a>

    @auth
        <ul>
            <li>
                <a href="{{ route('books.index') }}" class="{{ request()->routeIs('books.*') ? 'active' : '' }}">
                    Katalog Buku
                </a>
            </li>
            @if (auth()->user()->role === 'admin')
                <li>
                    <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        Kategori
                    </a>
                </li>
            @endif
            <li>
                <a href="{{ route('members.index') }}" class="{{ request()->routeIs('members.*') ? 'active' : '' }}">
                    Keanggotaan
                </a>
            </li>
            <li>
                <a href="{{ route('loans.index') }}" class="{{ request()->routeIs('loans.*') ? 'active' : '' }}">
                    Peminjaman
                </a>
            </li>
        </ul>

        <div class="navbar-user">
            <span>{{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</span>
            <form action="{{ route('logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    @endauth

    @guest
        <a href="{{ route('login') }}" class="btn" style="padding: 6px 14px; font-size: 13px;">Masuk</a>
    @endguest
</nav>
```

Tambahkan aturan CSS pada blok `<style>` di `resources/views/layouts/app.blade.php`:

```css
/* Penambahan aturan gaya visual pengguna pada navbar */
nav .navbar-user { display: flex; align-items: center; gap: 14px; color: #cbd5e1; font-size: 14px; }
nav .btn-logout { background: transparent; border: 1px solid #94a3b8; color: #f1f5f9; padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; }
nav .btn-logout:hover { background-color: #dc2626; border-color: #dc2626; color: #ffffff; }
```

---

### Langkah 7: Refaktorisasi `LoanController` (Otomatisasi ID Petugas via `auth()->id()`)

Karena identitas petugas yang sedang bertugas telah terverifikasi melalui autentikasi sesi, kita tidak perlu lagi memilih petugas secara manual lewat formulir.

Buka `app/Http/Controllers/LoanController.php`:
1. Sederhanakan metode `create()` tanpa mengambil koleksi `User`:
   ```php
   public function create()
   {
       $members = Member::where('status', 'aktif')->get();
       $books   = Book::where('stok', '>', 0)->get();

       return view('loans.create', compact('members', 'books'));
   }
   ```
2. Otomatisasi penugasan `user_id` pada metode `store()`:
   ```php
   public function store(Request $request)
   {
       $validated = $request->validate([
           'member_id'       => 'required|integer|exists:members,id',
           'tanggal_pinjam'  => 'required|date',
           'tanggal_kembali' => 'required|date|after_or_equal:tanggal_pinjam',
           'book_ids'        => 'required|array|min:1',
           'book_ids.*'      => 'integer|exists:books,id',
       ]);

       $loan = Loan::create([
           'member_id'       => $validated['member_id'],
           'user_id'         => auth()->id(), // Otomatis dari sesi pengguna login
           'tanggal_pinjam'  => $validated['tanggal_pinjam'],
           'tanggal_kembali' => $validated['tanggal_kembali'],
           'status'          => 'dipinjam',
       ]);

       foreach ($validated['book_ids'] as $bookId) {
           $loan->loanItems()->create([
               'book_id' => $bookId,
           ]);
       }

       return redirect()->route('loans.index')
           ->with('success', 'Transaksi peminjaman buku berhasil diterbitkan.');
   }
   ```

Buka `resources/views/loans/create.blade.php`: Hapus elemen `<select name="user_id">` dan gantikan dengan indikator statis:
```blade
<div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 6px; margin-top: 14px; font-size: 14px; color: #475569;">
    <strong>Petugas Pelayanan:</strong> {{ auth()->user()->name }} (Otomatis dari Akun Login)
</div>
```

---

### Langkah 8: Pengujian dan Verifikasi Alur Autentikasi pada Web Browser

Jalankan server aplikasi:
```bash
php artisan serve
```

Lakukan skenario pengujian komprehensif:
1. Akses `http://127.0.0.1:8000/books` dalam kondisi belum login &rarr; Pastikan sistem otomatis mengalihkan ke `/login`.
2. Masukkan kata sandi salah &rarr; Pastikan pesan galat informatif muncul tanpa membocorkan eksistensi data spesifik.
3. Masuk menggunakan akun admin (`admin@pens.ac.id` / `password`) &rarr; Pastikan berhasil dialihkan ke katalog buku, menu **Kategori** muncul di navbar, dan label akun menampilkan `(Admin)`.
4. Buka halaman `/categories` &rarr; Akses diberikan penuh.
5. Klik tombol **Logout** &rarr; Pastikan sesi berakhir dan dialihkan kembali ke `/login`.
6. Masuk menggunakan akun petugas (`petugas1@pens.ac.id` / `password`) &rarr; Pastikan menu **Kategori** tidak muncul di navbar.
7. Coba buka URL `http://127.0.0.1:8000/categories` secara paksa &rarr; Pastikan sistem memblokir akses dan memunculkan halaman **403 Forbidden**.
8. Buka `/loans/create` &rarr; Pastikan nama petugas otomatis terpasang sesuai akun login petugas saat ini.

---

## 5. Checkpoint Git Praktikum

Setelah menyelesaikan seluruh langkah di atas dan memastikan operasional autentikasi dan middleware bekerja sempurna, simpan progres kerja Anda pada repositori lokal dan publikasikan ke cabang `dev`:

```bash
git status
git add .
git commit -m "[P-8] implementasi autentikasi login logout dan middleware"
git push origin dev
```

---

## 6. Tugas Mandiri

Tingkatkan sistem autentikasi dengan membangun modul pengelolaan profil akun mandiri:

1. **Pembuatan Pengendali Profil:**
   - Buat `ProfileController` melalui Artisan.
   - Daftarkan rute `GET /profile` dan `PUT /profile/password` di dalam grup middleware `auth`.
2. **Antarmuka Profil & Pembaruan Kata Sandi:**
   - Bangun tampilan `resources/views/profile/index.blade.php` yang menampilkan ringkasan data profil akun (`nama`, `email`, `role`).
   - Sediakan formulir pembaruan kata sandi dengan field: `current_password`, `new_password`, dan `new_password_confirmation`.
3. **Validasi dan Enkripsi Keamanan:**
   - Validasi kata sandi lama menggunakan fasad `Hash::check($request->current_password, auth()->user()->password)`.
   - Validasi kata sandi baru minimal 8 karakter dengan aturan konfirmasi (`confirmed`).
   - Simpan kata sandi baru menggunakan `Hash::make($request->new_password)`.

**Keluaran Tugas:**
- Tautan komit repositori GitHub pada cabang pengembangan `dev`.
- Tangkapan layar (*screenshot*) halaman profil akun dan notifikasi berhasil saat kata sandi berhasil diperbarui.

---

## Navigasi Modul
- [← Pertemuan 07: Eloquent Relationship dan Relasi Antar Tabel](./pertemuan-07.md)
- [Daftar Isi Modul](./README.md)
- [Pertemuan 09: RESTful API dan Format Pertukaran Data JSON](./pertemuan-09.md) &rarr;
