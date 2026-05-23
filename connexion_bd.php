<?php
// connexion_bd.php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'connect_aid';

$conn = mysqli_connect($host, $user, $password, $database);

mysqli_set_charset($conn, 'utf8mb4');

if(!$conn) {
    die("Erreur de connexion : " . mysqli_connect_error());
}
?>