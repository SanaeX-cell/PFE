<?php
// connexion_bd.php
$host = 'localhost';
$user = 'root';
$password = ''; // Mot de passe XAMPP par défaut (vide)
$database = 'connect_aid';

$conn = mysqli_connect($host, $user, $password, $database);

if(!$conn) {
    die("Erreur de connexion : " . mysqli_connect_error());
}
?>