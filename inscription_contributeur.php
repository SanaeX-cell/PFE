<?php
session_start();
require_once 'connexion_bd.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirme_mdp = $_POST['confirme_mdp'] ?? '';

    // Validation nom (uniquement lettres, espaces, tirets)
    if (!empty($nom) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $nom)) {
        $error = "⚠️ Le nom ne doit contenir que des lettres, espaces ou tirets.";
    }
    // Validation prénom
    elseif (!empty($prenom) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $prenom)) {
        $error = "⚠️ Le prénom ne doit contenir que des lettres, espaces ou tirets.";
    }
    // Validation téléphone (uniquement chiffres)
    elseif (!empty($telephone) && !preg_match('/^[0-9]+$/', $telephone)) {
        $error = "⚠️ Le numéro de téléphone ne doit contenir que des chiffres. Les lettres et symboles ne sont pas autorisés.";
    }
    // Validation champs obligatoires
    elseif (empty($nom) || empty($prenom) || empty($email) || empty($mot_de_passe)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($mot_de_passe !== $confirme_mdp) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $check = $conn->prepare("SELECT id FROM contributeurs WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO contributeurs (nom, prenom, email, telephone, mot_de_passe) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nom, $prenom, $email, $telephone, $hashed_password);

            if ($stmt->execute()) {
                $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
                header("refresh:3;url=connexion.php");
            } else {
                $error = "Erreur lors de l'inscription : " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Contributeur - ConnectAid</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/inscription_contributeur.css">
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>Inscription Contributeur</h1>
            <p>Rejoignez la communauté et participez aux actions solidaires</p>
        </div>
        <div class="card-body">
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom <span class="required">*</span></label>
                        <input type="text" name="nom" 
                               pattern="[A-Za-zÀ-ÿ\s\-]+" 
                               title="⚠️ Uniquement des lettres, espaces ou tirets"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Prénom <span class="required">*</span></label>
                        <input type="text" name="prenom" 
                               pattern="[A-Za-zÀ-ÿ\s\-]+" 
                               title="⚠️ Uniquement des lettres, espaces ou tirets"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" 
                           pattern="[0-9]+" 
                           title="⚠️ Uniquement des chiffres. Les lettres et symboles ne sont pas autorisés.">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Mot de passe <span class="required">*</span></label>
                        <input type="password" name="mot_de_passe" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer le mot de passe <span class="required">*</span></label>
                        <input type="password" name="confirme_mdp" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">S'inscrire</button>
                
                <a href="connexion.php" class="back-link">← Retour à la connexion</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>