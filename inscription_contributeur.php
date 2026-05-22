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

    // Validation nom (lettres, espaces, tirets)
    if (!empty($nom) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $nom)) {
        $error = "⚠️ Le nom ne doit contenir que des lettres, espaces ou tirets.";
    }
    // Validation prénom
    elseif (!empty($prenom) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $prenom)) {
        $error = "⚠️ Le prénom ne doit contenir que des lettres, espaces ou tirets.";
    }
    // Validation téléphone (uniquement chiffres)
    elseif (!empty($telephone) && !preg_match('/^[0-9]+$/', $telephone)) {
        $error = "⚠️ Le numéro de téléphone ne doit contenir que des chiffres.";
    }
    // Validation email
    elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ L'adresse email doit contenir @ et un domaine valide (ex: nom@exemple.com).";
    }
    // Champs obligatoires
    elseif (empty($nom) || empty($prenom) || empty($email) || empty($mot_de_passe)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
    elseif ($mot_de_passe !== $confirme_mdp) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si l'email existe déjà
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
    <style>
        /* ===== VALIDATION EN TEMPS RÉEL ===== */
        .field-error {
            display: none;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            padding: 7px 12px;
            background: #fff1f1;
            border: 1px solid #fecaca;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #dc2626;
            animation: slideDown 0.2s ease;
        }
        .field-error.visible { display: flex; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        input.input-invalid { border-color: #ef4444 !important; background: #fff8f8; }
        input.input-valid   { border-color: #22c55e !important; }

        /* ===== TOGGLE MOT DE PASSE ===== */
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 44px; width: 100%; }
        .toggle-pw {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; padding: 4px;
            color: #8b8fa8;
            display: flex; align-items: center;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: #1CB8B2; }

        /* Masquer l'œil natif du navigateur */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear,
        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-credentials-auto-fill-button { display: none !important; }
        
        /* Styles de base */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Nunito', sans-serif;
            background: #EFF4F6;
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 650px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #1CB8B2 0%, #0E8A85 100%);
            padding: 30px;
            text-align: center;
        }
        
        .card-header h1 {
            color: white;
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        
        .card-header p {
            color: rgba(255,255,255,0.85);
        }
        
        .card-body {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2D3748;
            font-size: 0.85rem;
        }
        
        label .required {
            color: #e53e3e;
        }
        
        input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #CBD5E0;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: 'Nunito', sans-serif;
            transition: all 0.2s;
        }
        
        input:focus {
            outline: none;
            border-color: #1CB8B2;
            box-shadow: 0 0 0 3px rgba(28,184,178,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .alert-error {
            background: #FEE2E2;
            color: #C53030;
            border: 1px solid #FECACA;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #1CB8B2 0%, #0E8A85 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #718096;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 550px) {
            .card-body { padding: 25px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
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

            <form method="POST" id="inscriptionForm">

                <!-- NOM / PRÉNOM -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom <span class="required">*</span></label>
                        <input type="text" name="nom" id="nom" required autocomplete="off">
                        <div class="field-error" id="err-nom">
                            ⚠️ Le nom ne doit contenir que des lettres, espaces ou tirets.
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Prénom <span class="required">*</span></label>
                        <input type="text" name="prenom" id="prenom" required autocomplete="off">
                        <div class="field-error" id="err-prenom">
                            ⚠️ Le prénom ne doit contenir que des lettres, espaces ou tirets.
                        </div>
                    </div>
                </div>

                <!-- EMAIL -->
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="text" name="email" id="email" required autocomplete="off">
                    <div class="field-error" id="err-email">
                        ⚠️ L'adresse email doit contenir @ et un domaine valide (ex: nom@exemple.com).
                    </div>
                </div>

                <!-- TÉLÉPHONE -->
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" name="telephone" id="telephone" autocomplete="off">
                    <div class="field-error" id="err-telephone">
                        ⚠️ Le téléphone ne doit contenir que des chiffres. Lettres et symboles non autorisés.
                    </div>
                </div>

                <!-- MOTS DE PASSE -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Mot de passe <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="mot_de_passe" id="mot_de_passe" required>
                            <button type="button" class="toggle-pw"
                                    onclick="togglePw('mot_de_passe', this)" tabindex="-1">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <div class="field-error" id="err-mdp">
                            ⚠️ Le mot de passe est obligatoire.
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirmer le mot de passe <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="confirme_mdp" id="confirme_mdp" required>
                            <button type="button" class="toggle-pw"
                                    onclick="togglePw('confirme_mdp', this)" tabindex="-1">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <div class="field-error" id="err-confirm-mdp">
                            ⚠️ Les mots de passe ne correspondent pas.
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">S'inscrire</button>
                <a href="connexion.php" class="back-link">← Retour à la connexion</a>
            </form>
        </div>
    </div>
</div>

<script>
// ========== TOGGLE MOT DE PASSE ==========
function togglePw(fieldId, btn) {
    const input    = document.getElementById(fieldId);
    const isHidden = input.type === 'password';
    input.type     = isHidden ? 'text' : 'password';
    btn.innerHTML  = isHidden
        ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
               <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
               <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
               <line x1="1" y1="1" x2="23" y2="23"/></svg>`
        : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
               <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
               <circle cx="12" cy="12" r="3"/></svg>`;
    btn.style.color = isHidden ? '#1CB8B2' : '#8b8fa8';
}

// ========== HELPERS ==========
function showError(id)   { document.getElementById(id).classList.add('visible'); }
function hideError(id)   { document.getElementById(id).classList.remove('visible'); }
function markInvalid(el) { el.classList.add('input-invalid'); el.classList.remove('input-valid'); }
function markValid(el)   { el.classList.remove('input-invalid'); el.classList.add('input-valid'); }
function clearMark(el)   { el.classList.remove('input-invalid', 'input-valid'); }

// Regex
const LETTERS_RE = /^[a-zA-ZÀ-ÿ\s\-]+$/;
const EMAIL_RE   = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// ========== NOM ==========
const nomInput = document.getElementById('nom');
if (nomInput) {
    nomInput.addEventListener('input', function () {
        const cleaned = this.value.replace(/[^a-zA-ZÀ-ÿ\s\-]/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
            showError('err-nom');
            markInvalid(this);
        } else if (cleaned.length > 0) {
            hideError('err-nom');
            markValid(this);
        } else {
            hideError('err-nom');
            clearMark(this);
        }
    });
}

// ========== PRÉNOM ==========
const prenomInput = document.getElementById('prenom');
if (prenomInput) {
    prenomInput.addEventListener('input', function () {
        const cleaned = this.value.replace(/[^a-zA-ZÀ-ÿ\s\-]/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
            showError('err-prenom');
            markInvalid(this);
        } else if (cleaned.length > 0) {
            hideError('err-prenom');
            markValid(this);
        } else {
            hideError('err-prenom');
            clearMark(this);
        }
    });
}

// ========== EMAIL ==========
const emailInput = document.getElementById('email');
if (emailInput) {
    emailInput.addEventListener('input', function () {
        const val = this.value.trim();
        if (!val.length)               { hideError('err-email'); clearMark(this); }
        else if (!EMAIL_RE.test(val))  { showError('err-email'); markInvalid(this); }
        else                           { hideError('err-email'); markValid(this); }
    });
}

// ========== TÉLÉPHONE ==========
const telInput = document.getElementById('telephone');
if (telInput) {
    telInput.addEventListener('input', function () {
        const cleaned = this.value.replace(/[^0-9]/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
            showError('err-telephone');
            markInvalid(this);
        } else if (cleaned.length > 0) {
            hideError('err-telephone');
            markValid(this);
        } else {
            hideError('err-telephone');
            clearMark(this);
        }
    });
}

// ========== MOTS DE PASSE ==========
const mdpInput     = document.getElementById('mot_de_passe');
const confirmInput = document.getElementById('confirme_mdp');

function validateMdp() {
    if (!confirmInput.value.length) {
        hideError('err-confirm-mdp');
        clearMark(confirmInput);
        return;
    }
    if (mdpInput.value !== confirmInput.value) {
        showError('err-confirm-mdp');
        markInvalid(confirmInput);
    } else {
        hideError('err-confirm-mdp');
        markValid(confirmInput);
    }
}
if (mdpInput)     mdpInput.addEventListener('input', validateMdp);
if (confirmInput) confirmInput.addEventListener('input', validateMdp);

// ========== BLOCAGE ENVOI FORMULAIRE ==========
document.getElementById('inscriptionForm').addEventListener('submit', function (e) {
    let hasError = false;

    // Nom
    const nom = document.getElementById('nom');
    if (nom && (!LETTERS_RE.test(nom.value.trim()) || !nom.value.trim().length)) {
        showError('err-nom'); markInvalid(nom); hasError = true;
    }

    // Prénom
    const prenom = document.getElementById('prenom');
    if (prenom && (!LETTERS_RE.test(prenom.value.trim()) || !prenom.value.trim().length)) {
        showError('err-prenom'); markInvalid(prenom); hasError = true;
    }

    // Email
    const em = document.getElementById('email');
    if (em && !EMAIL_RE.test(em.value.trim())) {
        showError('err-email'); markInvalid(em); hasError = true;
    }

    // Téléphone (optionnel, mais validé si rempli)
    const tel = document.getElementById('telephone');
    if (tel && tel.value.length > 0 && !/^[0-9]+$/.test(tel.value)) {
        showError('err-telephone'); markInvalid(tel); hasError = true;
    }

    // Mots de passe
    if (mdpInput && confirmInput && mdpInput.value !== confirmInput.value) {
        showError('err-confirm-mdp'); markInvalid(confirmInput); hasError = true;
    }

    if (hasError) e.preventDefault();
});
</script>
</body>
</html>