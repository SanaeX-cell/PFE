<?php
session_start();
require_once 'connexion_bd.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Étape 1
    $nom_organisation = trim($_POST['nom_organisation'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $email_connexion = trim($_POST['email_connexion'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $site_web = trim($_POST['site_web'] ?? '');
    $region = trim($_POST['region'] ?? '');
    
    // Étape 2
    $resp_nom = trim($_POST['resp_nom'] ?? '');
    $resp_fonction = trim($_POST['resp_fonction'] ?? '');
    $resp_email = trim($_POST['resp_email'] ?? '');
    $resp_tel = trim($_POST['resp_tel'] ?? '');
    
    // Étape 3
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirm_mdp = $_POST['confirm_mdp'] ?? '';
    
    // ========== VALIDATIONS ==========
    
    // Validation téléphone (uniquement chiffres)
    if (!empty($telephone) && !preg_match('/^[0-9]+$/', $telephone)) {
        $error = "⚠️ Le numéro de téléphone ne doit contenir que des chiffres. Les lettres et symboles ne sont pas autorisés.";
    }
    // Validation site web (URL valide)
    elseif (!empty($site_web) && !filter_var($site_web, FILTER_VALIDATE_URL) === false) {
        $error = "⚠️ Veuillez entrer une URL valide (ex: https://www.example.com).";
    }
    // Validation nom du responsable (uniquement lettres, espaces, tirets)
    elseif (!empty($resp_nom) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $resp_nom)) {
        $error = "⚠️ Le nom du responsable ne doit contenir que des lettres, espaces ou tirets.";
    }
    // Validation email responsable
    elseif (!empty($resp_email) && !filter_var($resp_email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ Veuillez entrer un email valide pour le responsable.";
    }
    // Champs obligatoires étape 1
    elseif (empty($nom_organisation) || empty($adresse) || empty($email_connexion) || empty($telephone) || empty($region)) {
        $error = "Veuillez remplir tous les champs obligatoires de l'étape 1.";
    }
    // Champs obligatoires étape 2
    elseif (empty($resp_nom) || empty($resp_email)) {
        $error = "Veuillez remplir tous les champs obligatoires de l'étape 2.";
    }
    // Mots de passe
    elseif (empty($mot_de_passe) || $mot_de_passe !== $confirm_mdp) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si l'email existe déjà
        $check = $conn->prepare("SELECT id FROM organisations WHERE email_connexion = ?");
        $check->bind_param("s", $email_connexion);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = "Cet email est déjà utilisé.";
        } else {
            // Upload logo
            $logo = '';
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $logo = $upload_dir . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], $logo);
            }
            
            // Upload justificatif
            $justificatif = '';
            if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                $ext = pathinfo($_FILES['justificatif']['name'], PATHINFO_EXTENSION);
                $justificatif = $upload_dir . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['justificatif']['tmp_name'], $justificatif);
            }
            
            $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $statut = 'en_attente';
            
            $stmt = $conn->prepare("INSERT INTO organisations (nom_organisation, description, adresse, email_connexion, telephone, site_web, region, logo, justificatif, mot_de_passe, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", $nom_organisation, $description, $adresse, $email_connexion, $telephone, $site_web, $region, $logo, $justificatif, $hashed_password, $statut);
            
            if ($stmt->execute()) {
                $org_id = $stmt->insert_id;
                $stmt2 = $conn->prepare("INSERT INTO contact_principal (organisation_id, nom, fonction, email, telephone) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("issss", $org_id, $resp_nom, $resp_fonction, $resp_email, $resp_tel);
                $stmt2->execute();
                
                $success = "Votre inscription a bien été enregistrée. Un administrateur validera votre compte sous 48h.";
                header("refresh:3;url=connexion.php");
            } else {
                $error = "Erreur lors de l'inscription. Veuillez réessayer.";
            }
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
    <title>ConnectAid – Inscription Organisation</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="css/inscription_organisation.css">
</head>
<body>

<div class="nav-wrapper">
    <nav>
        <a class="nav-logo" href="index.php">
            <img src="images/logo.png" alt="ConnectAid" style="height: 38px;">
            <span>Connect<em>Aid</em></span>
        </a>
        <div class="nav-right">
            Déjà inscrit ? <a href="connexion.php">Se connecter</a>
        </div>
    </nav>
</div>

<main>
    <div class="form-container">
        <h1>Inscription organisation</h1>
        <div class="subtitle">Rejoignez notre communauté solidaire</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="multiStepForm">
            <div class="steps">
                <div class="step" id="step1Indicator">
                    <div class="step-number">1</div>
                    <div class="step-label">Organisation</div>
                </div>
                <div class="step" id="step2Indicator">
                    <div class="step-number">2</div>
                    <div class="step-label">Responsable</div>
                </div>
                <div class="step" id="step3Indicator">
                    <div class="step-number">3</div>
                    <div class="step-label">Sécurité</div>
                </div>
            </div>

            <!-- ÉTAPE 1 -->
            <div class="step-content" id="step1">
                <div class="first-row">
                    <div class="field-name">
                        <label>Nom de l'organisation <span class="required-star">*</span></label>
                        <input type="text" name="nom_organisation" required>
                    </div>
                    <div class="logo-area">
                        <div class="logo-circle" onclick="document.getElementById('logoInput').click()">
                            <svg class="camera-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M20 5h-2.83l-1.58-2H8.41L6.83 5H4C2.9 5 2 5.9 2 7v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-8 13c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                            <img id="logoPreview" class="logo-preview-img">
                        </div>
                        <div class="logo-text">Ajouter logo</div>
                        <input type="file" id="logoInput" name="logo" accept="image/*" style="display: none;" onchange="previewLogo(event)">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Adresse <span class="required-star">*</span></label>
                    <div class="address-wrapper">
                        <input type="text" name="adresse" id="adresse" required>
                        <div class="map-icon" onclick="openMapModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label>Email <span class="required-star">*</span></label>
                        <input type="email" name="email_connexion" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone <span class="required-star">*</span></label>
                        <input type="tel" 
                               name="telephone" 
                               pattern="[0-9]+" 
                               title="⚠️ Uniquement des chiffres (0-9). Les lettres et symboles ne sont pas autorisés."
                               placeholder="0612345678"
                               required>
                    </div>
                </div>
                <div class="row-2">
                    <div class="form-group">
                        <label>Site web</label>
                        <input type="url" name="site_web" 
                               pattern="https?://.+"
                               title="⚠️ Entrez une URL valide (ex: https://www.example.com)"
                               placeholder="https://www.example.com">
                    </div>
                    <div class="form-group">
                        <label>Région <span class="required-star">*</span></label>
                        <select name="region" required>
                            <option value="">Sélectionnez une région</option>
                            <option value="Tanger-Tétouan-Al Hoceïma">Tanger-Tétouan-Al Hoceïma</option>
                            <option value="Oriental">Oriental</option>
                            <option value="Fès-Meknès">Fès-Meknès</option>
                            <option value="Rabat-Salé-Kénitra">Rabat-Salé-Kénitra</option>
                            <option value="Béni Mellal-Khénifra">Béni Mellal-Khénifra</option>
                            <option value="Casablanca-Settat">Casablanca-Settat</option>
                            <option value="Marrakech-Safi">Marrakech-Safi</option>
                            <option value="Drâa-Tafilalet">Drâa-Tafilalet</option>
                            <option value="Souss-Massa">Souss-Massa</option>
                            <option value="Guelmim-Oued Noun">Guelmim-Oued Noun</option>
                            <option value="Laâyoune-Sakia El Hamra">Laâyoune-Sakia El Hamra</option>
                            <option value="Dakhla-Oued Ed-Dahab">Dakhla-Oued Ed-Dahab</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Document justificatif (PDF, JPG, PNG)</label>
                    <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png">
                    <small style="color: var(--text-muted);">Ce document sera vérifié par notre équipe.</small>
                </div>
            </div>

            <!-- ÉTAPE 2 -->
            <div class="step-content" id="step2">
                <div class="row-2">
                    <div class="form-group">
                        <label>Nom complet <span class="required-star">*</span></label>
                        <input type="text" 
                               name="resp_nom" 
                               pattern="[A-Za-zÀ-ÿ\s\-]+" 
                               title="⚠️ Uniquement des lettres, espaces ou tirets"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Fonction</label>
                        <input type="text" name="resp_fonction" list="fonctions-suggestions" placeholder="Ex: Directeur, Président...">
                        <datalist id="fonctions-suggestions">
                            <option value="Directeur">Directeur</option>
                            <option value="Président">Président</option>
                            <option value="Coordinateur">Coordinateur</option>
                            <option value="Responsable RH">Responsable RH</option>
                            <option value="Responsable administratif">Responsable administratif</option>
                            <option value="Secrétaire général">Secrétaire général</option>
                            <option value="Trésorier">Trésorier</option>
                            <option value="Chargé de projet">Chargé de projet</option>
                        </datalist>
                        <small style="color: var(--text-muted); font-size: 0.7rem;">Suggestions : Directeur, Président, Coordinateur...</small>
                    </div>
                </div>
                <div class="row-2">
                    <div class="form-group">
                        <label>Email <span class="required-star">*</span></label>
                        <input type="email" name="resp_email" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="resp_tel" 
                               pattern="[0-9]+" 
                               title="⚠️ Uniquement des chiffres"
                               placeholder="0612345678">
                    </div>
                </div>
            </div>

            <!-- ÉTAPE 3 -->
            <div class="step-content" id="step3">
                <div class="row-2">
                    <div class="form-group">
                        <label>Mot de passe <span class="required-star">*</span></label>
                        <input type="password" name="mot_de_passe" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer le mot de passe <span class="required-star">*</span></label>
                        <input type="password" name="confirm_mdp" required>
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn-prev" id="prevBtn" style="display: none;">← Précédent</button>
                <button type="button" class="btn-next" id="nextBtn">Suivant →</button>
                <button type="submit" class="btn-submit" id="submitBtn" style="display: none;">S'inscrire</button>
            </div>
        </form>
    </div>
</main>

<!-- MODALE CARTE -->
<div id="mapModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Sélectionnez l'adresse sur la carte</h3>
            <button class="close-modal" onclick="closeMapModal()">&times;</button>
        </div>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Nom de rue, ville, région...">
            <button onclick="searchLocation()">Rechercher</button>
        </div>
        <div id="map"></div>
        <button class="validate-address" onclick="validateAddress()">Utiliser cette adresse</button>
    </div>
</div>

<script>
    let map;
    let marker;
    let selectedAddress = '';

    function openMapModal() {
        document.getElementById('mapModal').style.display = 'flex';
        setTimeout(() => {
            if (!map) {
                map = L.map('map').setView([31.7917, -7.0926], 6);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);
                
                map.on('click', async function(e) {
                    if (marker) map.removeLayer(marker);
                    marker = L.marker([e.latlng.lat, e.latlng.lng]).addTo(map);
                    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${e.latlng.lat}&lon=${e.latlng.lng}&format=json`);
                    const data = await response.json();
                    selectedAddress = data.display_name || `${e.latlng.lat}, ${e.latlng.lng}`;
                });
            } else {
                map.invalidateSize();
            }
        }, 100);
    }

    async function searchLocation() {
        const query = document.getElementById('searchInput').value;
        if (!query) return;
        const response = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1`);
        const data = await response.json();
        if (data.length > 0) {
            const { lat, lon, display_name } = data[0];
            map.setView([lat, lon], 16);
            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lon]).addTo(map);
            selectedAddress = display_name;
        } else {
            alert("Aucun résultat trouvé. Essayez un autre nom.");
        }
    }

    function closeMapModal() {
        document.getElementById('mapModal').style.display = 'none';
    }

    function validateAddress() {
        if (selectedAddress) {
            document.getElementById('adresse').value = selectedAddress;
        }
        closeMapModal();
    }

    function previewLogo(event) {
        const preview = document.getElementById('logoPreview');
        const icon = document.querySelector('.camera-icon');
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                if (icon) icon.style.display = 'none';
            }
            reader.readAsDataURL(file);
        }
    }

    let currentStep = 1;
    const totalSteps = 3;

    function showStep(step) {
        for (let i = 1; i <= totalSteps; i++) {
            document.getElementById(`step${i}`).classList.remove('active');
            document.getElementById(`step${i}Indicator`).classList.remove('active');
            document.getElementById(`step${i}Indicator`).classList.remove('completed');
        }
        document.getElementById(`step${step}`).classList.add('active');
        document.getElementById(`step${step}Indicator`).classList.add('active');
        for (let i = 1; i < step; i++) {
            document.getElementById(`step${i}Indicator`).classList.add('completed');
        }
        
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        
        if (step === 1) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'inline-block';
            submitBtn.style.display = 'none';
        } else if (step === totalSteps) {
            prevBtn.style.display = 'inline-block';
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-block';
        } else {
            prevBtn.style.display = 'inline-block';
            nextBtn.style.display = 'inline-block';
            submitBtn.style.display = 'none';
        }
    }

    document.getElementById('nextBtn').addEventListener('click', () => {
        if (currentStep < totalSteps) {
            currentStep++;
            
            showStep(currentStep);
        }
    });

    document.getElementById('prevBtn').addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    showStep(currentStep);
</script>

</body>
</html>