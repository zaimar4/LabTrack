LabTrack

Sistem Peminjaman Barang Laboratorium Berbasis Web

Latar Belakang

Di laboratorium sekolah, proses peminjaman barang sering masih dilakukan secara manual menggunakan buku catatan. Cara ini memiliki beberapa masalah:

Data peminjaman mudah hilang atau rusak
Stok barang tidak terkontrol dengan baik
Sulit mengetahui siapa yang belum mengembalikan barang
Proses pencatatan dan pengecekan memakan waktu

Untuk mengatasi masalah tersebut, dibuatlah LabTrack sebagai sistem peminjaman barang berbasis web yang lebih terstruktur dan terdigitalisasi.

Tujuan Aplikasi
Mengelola data barang laboratorium
Mencatat proses peminjaman dan pengembalian
Mengontrol stok barang secara otomatis
Mempermudah petugas dalam melakukan monitoring
Role Pengguna

Petugas:

Mengelola data barang
Mengelola data siswa
Menyetujui atau menolak peminjaman
Mengonfirmasi pengembalian
Melihat data laporan

Siswa:

Melihat daftar barang
Mengajukan peminjaman
Melihat status peminjaman
Teknologi yang Digunakan
PHP Native
MySQL
HTML
CSS
JavaScript (dasar)
XAMPP atau Laragon
Cara Menjalankan Project

Karena folder config atau file koneksi database tidak di-push ke repository, lakukan langkah berikut setelah melakukan clone.

1. Clone Repository
git clone https://github.com/zaimar4/LabTrack

Pindahkan folder project ke dalam folder htdocs jika menggunakan XAMPP.

2. Buat Database
Buka phpMyAdmin
Buat database baru, misalnya:
labtrack
Import file database (.sql) jika tersedia
atau gunakan sql dari databaseschema.text yang tersedia
4. Buat File Koneksi Database

Buat folder dan file berikut jika belum ada:

config/database.php

Isi dengan kode berikut:

<?php
$conn = mysqli_connect("localhost", "root", "", "labtrack");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>

Sesuaikan nama database jika berbeda.

4. Jalankan di Browser

Buka browser dan akses:

http://localhost/labtrack
Catatan

Project ini dibuat sebagai tugas mata pelajaran Basis Data untuk melatih perancangan database, relasi antar tabel, implementasi CRUD, serta manajemen role menggunakan PHP Native tanpa framework.
