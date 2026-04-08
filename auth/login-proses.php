<?php  
include '../conection/conection.php'; 

if(isset($_POST['login'])) { 
    $requestUsername = $_POST['username']; 
    $requestPassword = $_POST['password']; 

    $sql = "SELECT * FROM users WHERE username='$requestUsername'";

    $result = mysqli_query($conn, $sql); 
   
    if(mysqli_num_rows($result) > 0) { 
        $row = mysqli_fetch_assoc($result);

        if (password_verify($requestPassword, $row['password'])) { 
            
            session_start(); 
            $_SESSION['username'] = $row['username']; 
            $_SESSION['role'] = $row['role'];

            if($row['role'] === 'petugas'){
                header('Location: dashboard.php');
            } else {
                header('Location: index.php'); 
            }
            exit;

        } else {  
            echo "<script> 
                alert('Password anda salah!'); 
                window.location = 'login.php'; 
            </script>"; 
        } 
    } else {  
        echo "<script> 
            alert('Username tidak ditemukan!'); 
            window.location = 'login.php'; 
        </script>"; 
    } 
} 
?>