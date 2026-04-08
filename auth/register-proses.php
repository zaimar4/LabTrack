<?php  
include '../conection/conection.php'; 
 
if(isset($_POST['register'])) { 
    $username = $_POST['username']; 
    $email = $_POST['email']; 
    // Mengamankan password
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT); 
 
    // Cek apakah data kosong sebelum menjalankan query
    if(empty($email) || empty($username) || empty($_POST["password"])) { 
        echo " 
            <script> 
                alert('Pastikan Anda Mengisi Semua Data'); 
                window.location = 'register.php'; 
            </script> 
        "; 
    } else {
        // PERBAIKAN DI SINI: Sebutkan kolom yang ingin diisi saja
        // id_user (auto) dan role (default 'user') tidak perlu ditulis
        $sql = "INSERT INTO users (email, password, username) 
                VALUES ('$email', '$password', '$username')"; 

        if(mysqli_query($conn, $sql)) {  
            echo "   
                <script> 
                    alert('Registrasi Berhasil. Silahkan login'); 
                    window.location = 'login.php'; 
                </script> 
            "; 
        } else { 
            // Menampilkan error asli dari MySQL jika gagal (untuk debug)
            echo "Error: " . mysqli_error($conn);
        }
    }
} 
?>