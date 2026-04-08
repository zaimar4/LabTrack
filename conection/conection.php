<?php
$host = 'localhost';
$username = 'root'; 
$password = '';     
$db = 'sistem_peminjaman_barang'; 


$conn = mysqli_connect($host, $username, $password, $db);

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}
?>