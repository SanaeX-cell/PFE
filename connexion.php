<?php
session_start();
require_once 'connexion_bd.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        // 1. Vérifier dans organisations
       $stmt = $conn->prepare("SELECT * FROM organisations WHERE email_connexion = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['mot_de_passe'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = 'organisation';
                $_SESSION['user_nom'] = $user['nom_organisation'];
                if ($user['statut'] === 'en_attente') {
                    $error = "Votre compte organisation est en attente de validation par l'administrateur.";
                } else {
                    header('Location: organisation/accueil.php');
                    exit();
                }
            } else {
                $error = "Mot de passe incorrect.";
            }
        } else {
            // 2. Vérifier dans contributeurs
            $stmt = $conn->prepare("SELECT * FROM contributeurs WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['mot_de_passe'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'contributeur';
                    $_SESSION['user_nom'] = $user['prenom'] . ' ' . $user['nom'];
                    header('Location: contributeur/accueil.php');
                    exit();
                } else {
                    $error = "Mot de passe incorrect.";
                }
            } else {
                // 3. Vérifier dans admins
                $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['mot_de_passe'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = 'admin';
                        $_SESSION['user_nom'] = $user['email'];
                        header('Location: admin/dashboard.php');
                        exit();
                    } else {
                        $error = "Mot de passe incorrect.";
                    }
                } else {
                    $error = "Aucun compte trouvé avec cet email.";
                }
            }
        }
        $stmt->close();
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectAid – Connexion</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/connexion.css">
</head>
<body>

<div class="nav-wrapper">
    <nav>
        <a class="nav-logo" href="#">
            <img src="images/logo.png" alt="ConnectAid" style="height: 38px;">
            <span>Connect<em>Aid</em></span>
        </a>
        <div class="nav-actions">
            <a href="connexion.php" class="btn-outline">Se connecter</a>
            <a href="choix_inscription.php" class="btn-solid">S'inscrire</a>
        </div>
    </nav>
</div>

<main>
    <div class="card">

        <!-- PANEL GAUCHE -->
        <div class="panel-left">
            <div class="bubble bubble-1"></div>
            <div class="bubble bubble-2"></div>
            <div class="bubble bubble-3"></div>
            <div class="bubble bubble-4"></div>
            <div class="bubble bubble-5"></div>

            <div class="text-container">
                <h2>Bienvenue sur<br/>ConnectAid</h2>
                <p class="slogan">Ensemble, faisons la différence pour ceux qui en ont besoin !</p>
            </div>

            <div class="photos-wrapper">
                <div class="photos-top">
                    <div class="photo-top photo-item">
                        <img src="images/photo-gauche.jpg" alt="Photo gauche" onerror="this.src='https://placehold.co/70x70?text=⬅️'">
                    </div>
                    <div class="photo-top photo-item">
                        <img src="images/photo-droite.jpg" alt="Photo droite" onerror="this.src='https://placehold.co/70x70?text=➡️'">
                    </div>
                </div>
                <div class="photo-center photo-item">
                    <img src="images/photo-centre.jpg" alt="Photo centre" onerror="this.src='https://placehold.co/70x70?text=⬇️'">
                </div>
            </div>
        </div>

        <!-- PANEL DROIT -->
        <div class="panel-right">
            <h1>Se connecter</h1>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Adresse e-mail</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-login">Se connecter</button>

                <div class="register-link">
                    Vous n'avez pas encore de compte ? <a href="choix_inscription.php">Créer un compte</a>
                </div>
            </form>
        </div>
    </div>
</main>

<footer>
    <div class="footer-brand">
        ConnectAid
        <small>© 2026 ConnectAid Platform. Empowering communities through structured empathy.</small>
    </div>
    <div class="footer-links">
        <a href="#">À propos</a>
        <a href="#">Contact</a>
        <a href="#">Confidentialité</a>
        <a href="#">Conditions</a>
    </div>
</footer>

</body>
</html>