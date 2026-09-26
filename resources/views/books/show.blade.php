{{-- File: resources/views/books/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Detail Buku')

@section('content')
    <h1>Detail Buku</h1>
    <p><a href="{{ route('books.index') }}">&larr; Kembali ke daftar buku</a></p>

    <style>
        table.detail-table { border-collapse: collapse; width: 100%; margin-top: 16px; max-width: 600px; }
        table.detail-table th, table.detail-table td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
        table.detail-table th { width: 160px; background: #f3f4f6; }
    </style>

    <table class="detail-table">
        <tr>
            <th>Judul</th>
            <td>{{ $book['judul'] }}</td>
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
            <th>ISBN</th>
            <td>{{ $book['isbn'] ?? '-' }}</td>
        </tr>
        <tr>
            <th>Stok</th>
            <td>{{ $book['stok'] }}</td>
        </tr>
        <tr>
            <th>ID Kategori</th>
            <td>{{ $book['category_id'] }}</td>
        </tr>
    </table>
@endsection