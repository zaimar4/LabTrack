<?php  
include '../conection/conection.php'; 
 
if(isset($_POST['register'])) { 
    $username = $_POST['username']; 
    $email = $_POST['email']; 
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT); 
 
    if(empty($email) || empty($username) || empty($_POST["password"])) { 
        echo " 
            <script> 
                alert('Pastikan Anda Mengisi Semua Data'); 
                window.location = 'register.php'; 
            </script> 
        "; 
    } else {
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
            echo "Error: " . mysqli_error($conn);
        }
    }
} 
?>