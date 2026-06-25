<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organisation') {
    header('Location: ../connexion.php');
    exit();
}

$org_id = $_SESSION['user_id'];

$success_msg = '';
$error_msg = '';

// Traitement du formulaire de modification des infos de l'organisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_org') {
    $nom = mysqli_real_escape_string($conn, $_POST['nom_organisation']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $adresse = mysqli_real_escape_string($conn, $_POST['adresse']);
    $telephone = mysqli_real_escape_string($conn, $_POST['telephone']);
    $site_web = mysqli_real_escape_string($conn, $_POST['site_web']);

    // Gestion de l'upload du logo
    $logo_update = "";
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $upload_dir = '../uploads/';
        $file_name = time() . '_' . basename($_FILES['logo']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
            $logo_update = ", logo='uploads/$file_name'";
        } else {
            $error_msg = 'Erreur lors du téléchargement du logo.';
        }
    }

    $sql_update = "UPDATE organisations SET 
        nom_organisation='$nom', 
        description='$description', 
        adresse='$adresse', 
        telephone='$telephone', 
        site_web='$site_web'
        $logo_update
        WHERE id=$org_id";
        
    if (mysqli_query($conn, $sql_update)) {
        $success_msg = 'Informations mises à jour avec succès.';
        // Rafraîchir les données
        $result = mysqli_query($conn, "SELECT * FROM organisations WHERE id = $org_id");
        $org = mysqli_fetch_assoc($result);
    } else {
        $error_msg = 'Erreur lors de la mise à jour : ' . mysqli_error($conn);
    }
}

// Suppression d'un post
if (isset($_GET['delete_post'])) {
    $post_id = (int)$_GET['delete_post'];
    mysqli_query($conn, "DELETE FROM posts WHERE id=$post_id AND organisation_id=$org_id");
    header('Location: ma_page.php');
    exit();
}

// Marquer un post comme terminé
if (isset($_GET['terminer_post'])) {
    $post_id = (int)$_GET['terminer_post'];
    mysqli_query($conn, "UPDATE posts SET statut='termine' WHERE id=$post_id AND organisation_id=$org_id");
    header('Location: ma_page.php');
    exit();
}

// Récupération des données de l'organisation
$sql = "SELECT * FROM organisations WHERE id = $org_id";
$result = mysqli_query($conn, $sql);
$org = mysqli_fetch_assoc($result);

// Récupération des posts avec recherche (depuis le header)
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = "";
if (!empty($search)) {
    $search_condition = " AND p.titre LIKE '%$search%'";
}

$sql_posts = "SELECT p.*, 
    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as nb_likes 
    FROM posts p 
    WHERE p.organisation_id = $org_id $search_condition 
    ORDER BY p.date_creation DESC";
$result_posts = mysqli_query($conn, $sql_posts);
$posts = [];
while($row = mysqli_fetch_assoc($result_posts)) {
    $posts[] = $row;
}

// Notification non lues
$sql_notifs = "SELECT COUNT(*) as nb FROM notifications_organisation WHERE organisation_id = $org_id AND statut = 'non_lue'";
$result_n = mysqli_query($conn, $sql_notifs);
$total_notifs = mysqli_fetch_assoc($result_n)['nb'];

$current_page = basename($_SERVER['PHP_SELF']);

// Génération des initiales pour l'avatar
$words = explode(' ', trim($org['nom_organisation']));
$initials = '';
foreach (array_slice($words, 0, 2) as $word) {
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}

$logo_path = !empty($org['logo']) ? '../' . $org['logo'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Ma Page</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #f5f6fa;
    --sidebar-bg: #fff;
    --text-primary: #1a1d2e;
    --text-secondary: #8b8fa8;
    --text-light: #b0b3c6;
    --accent-teal: #1CB8B2;
    --accent-orange: #F47B20;
    --accent-orange-dark: #D95C10;
    --accent-orange-light: #FF9A4D;
    --accent-orange-bg: #FFF5ED;
    --accent-yellow: #F7AD19;
    --border: #f0f1f7;
    --shadow: 0 2px 20px rgba(0,0,0,0.06);
    --radius: 18px;
    --sidebar-width: 220px;
    --header-height: 80px;
  }
  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-primary); display: flex; min-height: 100vh; font-size: 14px; }

  /* ===== SIDEBAR ===== */
  .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; border-right: 1px solid var(--border); z-index: 20; }
  .logo { display: flex; align-items: center; justify-content: flex-end; width: 93%; padding: 0 24px 32px 18px; }
  .logo-image { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
  .logo-text { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; white-space: nowrap; }
  .logo-text span:first-child { color: var(--accent-teal); }
  .logo-text span:last-child { color: var(--accent-orange); }
  nav { flex: 1; margin-top: 8px; }
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; font-weight: 500; font-size: 14px; position: relative; margin: 2px 0; text-decoration: none; }
  .nav-item:hover { color: var(--text-primary); background: var(--bg); }
  .nav-item.active { color: var(--accent-orange); background: #FFF0E6; }
  .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; background: var(--accent-orange); border-radius: 0 4px 4px 0; }
  .nav-icon { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .sidebar-bottom { padding: 0; border-top: 1px solid var(--border); padding-top: 12px; margin-top: auto; }
  .sidebar-bottom .nav-item { background: var(--accent-orange); color: white; border-radius: 40px; margin: 8px 16px; justify-content: center; }
  .sidebar-bottom .nav-item:hover { background: var(--accent-orange-dark); color: white; }
  .sidebar-bottom .nav-item::before { display: none; }

  /* ===== STICKY HEADER ===== */
  .sticky-header {
    position: fixed; top: 0; left: var(--sidebar-width); right: 0;
    height: var(--header-height);
    background: rgba(245,246,250,0.92);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    z-index: 15; display: flex; align-items: center;
    justify-content: space-between; padding: 0 32px; gap: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.05); transition: box-shadow 0.3s;
  }
  .sticky-header.scrolled { box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
  .greeting-box { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 10px 24px; box-shadow: var(--shadow); flex: 0 1 auto; min-width: 180px; display: flex; align-items: center; gap: 12px; }
  .greeting-box h1 { font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0; }
  .greeting-box .separator { color: var(--text-light); font-weight: 300; }
  .greeting-box .sub { font-size: 13px; color: var(--text-secondary); font-weight: 500; }
  .header-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  
  /* ===== BARRE DE RECHERCHE SIMPLE ===== */
  .header-search {
    display: flex;
    align-items: center;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 50px;
    padding: 0 16px;
    height: 44px;
    min-width: 260px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  .header-search:focus-within {
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 3px rgba(244,123,32,0.08);
  }
  .header-search .search-icon {
    color: var(--text-light);
    font-size: 16px;
    margin-right: 10px;
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }
  .header-search input {
    border: none;
    outline: none;
    padding: 10px 0;
    font-size: 14px;
    font-family: inherit;
    color: var(--text-primary);
    background: transparent;
    width: 100%;
    min-width: 140px;
  }
  .header-search input::placeholder {
    color: #a0a0a0;
    font-weight: 400;
  }
  .header-search .btn-clear-header {
    background: transparent;
    color: #ccc;
    border: none;
    padding: 4px 8px;
    font-size: 16px;
    cursor: pointer;
    font-family: inherit;
    transition: color 0.2s;
    text-decoration: none;
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }
  .header-search .btn-clear-header:hover { color: var(--text-secondary); }
  .header-search .search-submit {
    background: transparent;
    border: none;
    color: var(--text-light);
    padding: 4px 0 4px 8px;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: color 0.2s;
  }
  .header-search .search-submit:hover { color: var(--accent-orange); }

  .notif-icon { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow); position: relative; text-decoration: none; color: var(--text-secondary); transition: all 0.2s; flex-shrink: 0; }
  .notif-icon:hover { border-color: var(--accent-orange-light); color: var(--accent-orange); }
  .notif-count-header { position: absolute; top: -4px; right: -4px; background: #EF4444; color: #fff; border-radius: 50%; width: 20px; height: 20px; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; }

  /* ===== ORG DROPDOWN - ÉLARGI ===== */
  .org-name-box { 
    background: #fff; 
    border: 1px solid var(--border); 
    border-radius: var(--radius); 
    padding: 6px 14px 6px 6px; 
    box-shadow: var(--shadow); 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    cursor: pointer; 
    transition: all 0.2s; 
    position: relative; 
    min-width: 220px; 
    max-width: 400px;  /* Élargi de 250px à 400px */
    flex-shrink: 0; 
  }
  .org-name-box:hover { border-color: var(--accent-orange-light); }
  .org-avatar { 
    width: 34px; 
    height: 34px; 
    border-radius: 10px; 
    background: linear-gradient(135deg, #F47B20, #F7AD19); 
    color: #fff; 
    font-size: 13px; 
    font-weight: 700; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    flex-shrink: 0; 
    letter-spacing: 0.5px; 
    overflow: hidden; 
  }
  .org-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
  .org-name-box .name { 
    font-weight: 600; 
    font-size: 14px; 
    color: var(--text-primary); 
    white-space: nowrap; 
    overflow: hidden; 
    text-overflow: ellipsis; 
    flex: 1; 
  }
  .org-name-box .chevron { color: var(--text-secondary); transition: transform 0.2s; font-size: 12px; display: flex; align-items: center; flex-shrink: 0; }
  .org-name-box.active .chevron { transform: rotate(180deg); }
  .dropdown-menu-header { position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 190px; opacity: 0; visibility: hidden; transform: translateY(-8px); transition: all 0.2s; z-index: 100; }
  .org-name-box.active .dropdown-menu-header { opacity: 1; visibility: visible; transform: translateY(0); }
  .dropdown-org-info { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--border); }
  .dropdown-org-info .d-avatar { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #F47B20, #F7AD19); color: #fff; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
  .dropdown-org-info .d-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
  .dropdown-org-info .d-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
  .dropdown-org-info .d-role { font-size: 11px; color: var(--text-secondary); }
  .dropdown-menu-header a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu-header a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu-header hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }

  /* ===== MAIN ===== */
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; padding: 24px 32px 40px; padding-top: calc(var(--header-height) + 24px); }

  /* ===== CONTENU ===== */
  .content { display: flex; flex-direction: column; gap: 24px; }

  .alert { padding: 12px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; }
  .alert-success { background: #dcfce7; color: #166534; }
  .alert-error { background: #fee2e2; color: #991b1b; }

  .card { background: #fff; border-radius: var(--radius); padding: 28px; box-shadow: var(--shadow); border: 1px solid var(--border); }
  .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 10px; }
  .card-title { font-size: 16px; font-weight: 700; }

  /* ===== BOX PROFIL BLANC ===== */
  .profile-white-box {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
    box-shadow: var(--shadow);
  }
  .profile-left {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    min-width: 200px;
  }
  .profile-logo {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    background: linear-gradient(135deg, #F47B20, #F7AD19);
    color: #fff;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid #f0f0f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  }
  .profile-logo img { width: 100%; height: 100%; object-fit: cover; }
  .profile-name {
    flex: 1;
    min-width: 150px;
  }
  .profile-name h2 {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    line-height: 1.3;
    word-break: break-word;
  }
  .btn-modifier {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 10px 28px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    flex-shrink: 0;
  }
  .btn-modifier:hover { background: var(--accent-orange-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(244,123,32,0.3); }

  /* ===== INFOS ORGANISATION ===== */
  .info-grid-custom {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }
  .info-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
  }
  .info-row:last-child { border-bottom: none; }
  .info-icon {
    width: 20px;
    color: var(--text-secondary);
    flex-shrink: 0;
    margin-top: 2px;
  }
  .info-label {
    font-weight: 500;
    color: var(--text-secondary);
    min-width: 80px;
    font-size: 13px;
  }
  .info-value {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 14px;
    word-break: break-word;
  }

  /* ===== ABOUT ===== */
  .about-container {
    position: relative;
  }
  .about-text {
    line-height: 1.8;
    color: var(--text-secondary);
    font-size: 14px;
    padding: 4px 0;
    overflow: hidden;
    transition: max-height 0.4s ease;
  }
  .about-text.collapsed {
    max-height: 80px;
  }
  .about-text.expanded {
    max-height: 2000px;
  }
  .about-text .fade-overlay {
    display: none;
  }
  .about-text.collapsed .fade-overlay {
    display: block;
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: linear-gradient(to bottom, transparent, #fff);
    pointer-events: none;
  }
  .btn-voir-tout {
    background: transparent;
    color: var(--accent-orange);
    border: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
    padding: 6px 0;
    transition: color 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }
  .btn-voir-tout:hover { color: var(--accent-orange-dark); }

  /* ===== POSTS ===== */
  .posts-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .btn-orange-new {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 9px 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
  }
  .btn-orange-new:hover { background: var(--accent-orange-dark); box-shadow: 0 4px 12px rgba(244,123,32,0.25); }

  .posts-list { display: flex; flex-direction: column; gap: 16px; }
  .post-card { border: 1.5px solid var(--border); border-radius: 14px; padding: 20px 22px; transition: border-color 0.2s; }
  .post-card:hover { border-color: #c8cce0; }
  .post-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; gap: 14px; }
  .post-title { font-size: 15px; font-weight: 700; }
  .post-type-badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .type-volontariat { background: #E1F7F6; color: var(--accent-teal); }
  .type-donation { background: #FFF0E6; color: var(--accent-orange); }
  .post-meta { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; margin-bottom: 12px; }
  .meta-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-secondary); }
  .meta-item strong { color: var(--text-primary); }
  .post-description { font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 12px; border-left: 3px solid var(--border); padding-left: 12px; }
  .post-besoins { font-size: 12px; color: var(--text-secondary); margin-bottom: 14px; }
  .post-besoins strong { color: var(--text-primary); }
  .post-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; border-top: 1px solid var(--border); padding-top: 12px; }
  .likes-count { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #ef4444; }
  .post-actions { display: flex; align-items: center; gap: 8px; }
  .btn-icon { display: inline-flex; align-items: center; gap: 5px; border: none; border-radius: 8px; padding: 7px 13px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; text-decoration: none; }
  .btn-edit { background: #EFF6FF; color: #2563eb; }
  .btn-edit:hover { background: #dbeafe; }
  .btn-delete { background: #FEF2F2; color: #dc2626; }
  .btn-delete:hover { background: #fee2e2; }
  .btn-done { background: #F0FDF4; color: #16a34a; }
  .btn-done:hover { background: #dcfce7; }
  .status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .status-actif { background: #dcfce7; color: #16a34a; }
  .status-termine { background: #f3f4f6; color: #6b7280; }

  /* ===== MODAL MODIFICATION ===== */
  .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 200; align-items: center; justify-content: center; }
  .modal-overlay.show { display: flex; }
  .modal-box { background: #fff; border-radius: 18px; padding: 30px; width: 90%; max-width: 560px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.18); }
  .modal-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; }

  .empty-posts { text-align: center; padding: 40px 20px; color: var(--text-secondary); }
  .empty-posts svg { opacity: 0.3; margin-bottom: 12px; }
  .empty-posts p { font-size: 14px; margin-bottom: 16px; }

  /* FORMULAIRE MODIFICATION */
  .form-group { display: flex; flex-direction: column; gap: 6px; }
  .form-group label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
  .form-group input, .form-group textarea { border: 1.5px solid var(--border); border-radius: 10px; padding: 10px 14px; font-size: 14px; font-family: inherit; color: var(--text-primary); transition: border-color 0.2s; background: var(--bg); resize: vertical; }
  .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--accent-orange); background: #fff; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
  .btn { display: inline-flex; align-items: center; gap: 7px; border: none; border-radius: 10px; padding: 10px 20px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; text-decoration: none; }
  .btn-primary { background: var(--accent-teal); color: #fff; }
  .btn-primary:hover { background: #138F8A; }
  .btn-orange-save { background: var(--accent-orange); color: #fff; }
  .btn-orange-save:hover { background: var(--accent-orange-dark); box-shadow: 0 4px 12px rgba(244,123,32,0.25); }
  .btn-outline { background: transparent; color: var(--text-secondary); border: 1.5px solid var(--border); }
  .btn-outline:hover { border-color: var(--accent-orange); color: var(--accent-orange); }

  .file-upload-wrapper { position: relative; }
  .file-upload-wrapper input[type="file"] { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
  .file-upload-label { display: inline-block; background: var(--bg); border: 1.5px dashed var(--border); border-radius: 10px; padding: 12px 18px; width: 100%; text-align: center; font-size: 13px; color: var(--text-secondary); transition: all 0.2s; }
  .file-upload-label:hover { border-color: var(--accent-orange); background: var(--accent-orange-bg); }

  @media (max-width: 768px) {
    .sticky-header { left: 0; padding: 0 16px; flex-wrap: wrap; height: auto; min-height: var(--header-height); gap: 8px; }
    .main { margin-left: 0; padding: 16px; padding-top: calc(var(--header-height) + 80px); }
    .info-grid-custom { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .org-name-box { min-width: auto; max-width: 100%; }
    .profile-left { flex-wrap: wrap; }
    .profile-name h2 { font-size: 20px; }
    .profile-white-box { padding: 20px; }
    .header-search { min-width: 160px; flex: 1; }
    .header-search input { min-width: 80px; font-size: 13px; }
    .greeting-box { min-width: auto; padding: 8px 16px; }
    .greeting-box h1 { font-size: 15px; }
    .greeting-box .sub { font-size: 11px; }
  }

  @media (max-width: 480px) {
    .profile-white-box { flex-direction: column; align-items: stretch; }
    .profile-left { flex-direction: column; align-items: center; text-align: center; }
    .profile-name h2 { font-size: 18px; }
    .btn-modifier { align-self: center; }
  }
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
  <div class="logo">
    <img src="../images/logo.png" alt="Logo" class="logo-image">
    <div class="logo-text"><span>Connect</span><span>Aid</span></div>
  </div>
  <nav>
    <a href="accueil.php" class="nav-item <?php echo ($current_page=='accueil.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
      Accueil
    </a>
    <a href="ma_page.php" class="nav-item <?php echo ($current_page=='ma_page.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
      Ma page
    </a>
    <a href="creer_post.php" class="nav-item <?php echo ($current_page=='creer_post.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      Créer un post
    </a>
    <a href="benevoles.php" class="nav-item <?php echo ($current_page=='benevoles.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      Bénévoles
    </a>
    <a href="messages.php" class="nav-item <?php echo ($current_page=='messages.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
      Messages
    </a>
    <a href="notifications.php" class="nav-item <?php echo ($current_page=='notifications.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
      Notifications
    </a>
  </nav>
  <div class="sidebar-bottom">
    <a href="../logout.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      Déconnexion
    </a>
  </div>
</aside>

<!-- ===== STICKY HEADER ===== -->
<header class="sticky-header" id="stickyHeader">
  <div class="greeting-box">
    <h1>Ma page</h1>
    <span class="separator">·</span>
    <span class="sub">Espace organisation</span>
  </div>
  <div class="header-right">
    <!-- ===== BARRE DE RECHERCHE SIMPLE ===== -->
    <form method="GET" class="header-search" id="headerSearchForm">
      <span class="search-icon">🔍</span>
      <input type="text" name="search" placeholder="Rechercher un post..." value="<?php echo htmlspecialchars($search); ?>">
      <?php if(!empty($search)): ?>
        <a href="ma_page.php" class="btn-clear-header">✕</a>
      <?php endif; ?>
    </form>
    
    <a href="notifications.php" class="notif-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if($total_notifs > 0): ?>
        <span class="notif-count-header"><?php echo $total_notifs; ?></span>
      <?php endif; ?>
    </a>
    <div class="org-name-box" id="orgDropdown">
      <div class="org-avatar">
        <?php if (!empty($org['logo'])): ?>
          <img src="../<?php echo htmlspecialchars($org['logo']); ?>" alt="Logo">
        <?php else: ?>
          <?php echo htmlspecialchars($initials); ?>
        <?php endif; ?>
      </div>
      <span class="name"><?php echo htmlspecialchars($org['nom_organisation']); ?></span>
      <span class="chevron">▾</span>
      <div class="dropdown-menu-header">
        <div class="dropdown-org-info">
          <div class="d-avatar">
            <?php if (!empty($org['logo'])): ?>
              <img src="../<?php echo htmlspecialchars($org['logo']); ?>" alt="Logo">
            <?php else: ?>
              <?php echo htmlspecialchars($initials); ?>
            <?php endif; ?>
          </div>
          <div>
            <div class="d-name"><?php echo htmlspecialchars($org['nom_organisation']); ?></div>
            <div class="d-role">Organisation</div>
          </div>
        </div>
        <a href="ma_page.php">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Ma page
        </a>
        <hr>
        <a href="../logout.php">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Déconnexion
        </a>
      </div>
    </div>
  </div>
</header>

<!-- ===== MAIN ===== -->
<div class="main">
  <div class="content">

    <?php if($success_msg): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-error"><?php echo $error_msg; ?></div><?php endif; ?>

    <!-- ===== BOX PROFIL BLANC ===== -->
    <div class="profile-white-box">
      <div class="profile-left">
        <div class="profile-logo">
          <?php if (!empty($org['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($org['logo']); ?>" alt="Logo">
          <?php else: ?>
            <?php echo htmlspecialchars($initials); ?>
          <?php endif; ?>
        </div>
        <div class="profile-name">
          <h2><?php echo htmlspecialchars($org['nom_organisation']); ?></h2>
        </div>
      </div>
      <button class="btn-modifier" onclick="openEditModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Modifier
      </button>
    </div>

    <!-- ===== INFOS ORGANISATION ===== -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">📋 Informations de l'organisation</div>
      </div>
      <div class="info-grid-custom">
        <div class="info-row">
          <span class="info-icon">📞</span>
          <span class="info-label">Téléphone</span>
          <span class="info-value"><?php echo $org['telephone'] ?: '—'; ?></span>
        </div>
        <div class="info-row">
          <span class="info-icon">✉️</span>
          <span class="info-label">Email</span>
          <span class="info-value"><?php echo htmlspecialchars($org['email_connexion']); ?></span>
        </div>
        <div class="info-row">
          <span class="info-icon">📍</span>
          <span class="info-label">Adresse</span>
          <span class="info-value"><?php echo $org['adresse'] ?: '—'; ?></span>
        </div>
        <div class="info-row">
          <span class="info-icon">🌐</span>
          <span class="info-label">Site web</span>
          <span class="info-value"><?php echo $org['site_web'] ?: '—'; ?></span>
        </div>
      </div>
    </div>

    <!-- ===== À PROPOS ===== -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">📖 À propos</div>
      </div>
      <div class="about-container">
        <div class="about-text collapsed" id="aboutText">
          <?php echo $org['description'] ? nl2br(htmlspecialchars($org['description'])) : 'Aucune description pour le moment.'; ?>
          <div class="fade-overlay"></div>
        </div>
        <?php 
          $desc_length = strlen(trim($org['description'] ?? ''));
          if($desc_length > 150): 
        ?>
        <button class="btn-voir-tout" id="toggleAboutBtn" onclick="toggleAbout()">
          Voir tout <span id="aboutArrow">▾</span>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- ===== MES PUBLICATIONS ===== -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">📢 Mes publications (<?php echo count($posts); ?>)</div>
        <div class="posts-header-actions">
          <a href="creer_post.php" class="btn-orange-new">+ Nouveau post</a>
        </div>
      </div>
      <?php if(empty($posts)): ?>
        <div class="empty-posts">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <p><?php echo !empty($search) ? 'Aucun post ne correspond à votre recherche.' : 'Aucune publication pour le moment.'; ?></p>
          <?php if(empty($search)): ?>
            <a href="creer_post.php" class="btn-orange-new" style="display:inline-flex;">Créer mon premier post</a>
          <?php else: ?>
            <a href="ma_page.php" class="btn btn-outline">Voir tous les posts</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="posts-list">
          <?php foreach($posts as $post): ?>
          <div class="post-card">
            <div class="post-header">
              <div class="post-title"><?php echo htmlspecialchars($post['titre']); ?></div>
              <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <span class="post-type-badge <?php echo $post['type_demande']==='volontariat'?'type-volontariat':'type-donation'; ?>">
                  <?php echo ucfirst($post['type_demande']); ?>
                </span>
                <span class="status-badge <?php echo $post['statut']==='actif'?'status-actif':'status-termine'; ?>">
                  <?php echo $post['statut']==='actif'?'Actif':'Terminé'; ?>
                </span>
              </div>
            </div>
            <div class="post-meta">
              <div class="meta-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Date : <strong><?php echo date('d/m/Y', strtotime($post['date_evenement'])); ?></strong></span>
              </div>
              <div class="meta-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span>Lieu : <strong><?php echo htmlspecialchars($post['localisation']); ?></strong></span>
              </div>
              <?php if($post['type_demande']==='volontariat' && $post['nb_benevoles_requis']): ?>
              <div class="meta-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <span>Bénévoles requis : <strong><?php echo $post['nb_benevoles_requis']; ?></strong></span>
              </div>
              <?php endif; ?>
              <?php if($post['type_demande']==='donation' && $post['montant_objectif']): ?>
              <div class="meta-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Objectif : <strong><?php echo number_format($post['montant_objectif'], 2); ?> DH</strong></span>
              </div>
              <?php endif; ?>
            </div>
            <div class="post-description"><?php echo nl2br(htmlspecialchars($post['description'])); ?></div>
            <?php if($post['besoins']): ?>
            <div class="post-besoins"><strong>Besoins :</strong> <?php echo htmlspecialchars($post['besoins']); ?></div>
            <?php endif; ?>
            <div class="post-footer">
              <div class="likes-count">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#ef4444" stroke="#ef4444" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <?php echo $post['nb_likes']; ?> j'aime
              </div>
              <div class="post-actions">
                <?php if($post['statut']==='actif'): ?>
                <button class="btn-icon btn-edit" onclick="openEditPost(<?php echo htmlspecialchars(json_encode($post)); ?>)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Modifier
                </button>
                <a href="?terminer_post=<?php echo $post['id']; ?>" class="btn-icon btn-done" onclick="return confirm('Marquer ce post comme terminé ?')">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  Terminé
                </a>
                <?php endif; ?>
                <a href="?delete_post=<?php echo $post['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Supprimer ce post définitivement ?')">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                  Supprimer
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ===== MODAL MODIFIER ORGANISATION ===== -->
<div class="modal-overlay" id="editOrgModal">
  <div class="modal-box">
    <div class="modal-title">✏️ Modifier l'organisation</div>
    <form method="POST" enctype="multipart/form-data" id="editOrgForm">
      <input type="hidden" name="action" value="modifier_org">
      <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="form-group">
          <label>Logo</label>
          <div class="file-upload-wrapper">
            <div class="file-upload-label">📁 Cliquez pour changer le logo</div>
            <input type="file" name="logo" accept="image/*">
          </div>
        </div>
        <div class="form-group">
          <label>Nom de l'organisation</label>
          <input type="text" name="nom_organisation" value="<?php echo htmlspecialchars($org['nom_organisation']); ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Téléphone</label>
            <input type="text" name="telephone" value="<?php echo htmlspecialchars($org['telephone']); ?>">
          </div>
          <div class="form-group">
            <label>Site web</label>
            <input type="text" name="site_web" value="<?php echo htmlspecialchars($org['site_web']); ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Adresse</label>
          <input type="text" name="adresse" value="<?php echo htmlspecialchars($org['adresse']); ?>">
        </div>
        <div class="form-group">
          <label>À propos (description)</label>
          <textarea name="description" rows="5"><?php echo htmlspecialchars($org['description']); ?></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-outline" onclick="closeEditModal()">Annuler</button>
          <button type="submit" class="btn btn-orange-save">💾 Enregistrer</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ===== MODAL MODIFIER POST ===== -->
<div class="modal-overlay" id="editPostModal">
  <div class="modal-box">
    <div class="modal-title">Modifier le post</div>
    <form method="POST" action="modifier_post.php" id="editPostForm">
      <input type="hidden" name="post_id" id="edit_post_id">
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="form-group">
          <label>Titre</label>
          <input type="text" name="titre" id="edit_titre" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" id="edit_description" rows="4" required></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Date de l'événement</label>
            <input type="date" name="date_evenement" id="edit_date">
          </div>
          <div class="form-group">
            <label>Localisation</label>
            <input type="text" name="localisation" id="edit_localisation">
          </div>
        </div>
        <div class="form-group" id="edit_benevoles_group" style="display:none;">
          <label>Nombre de bénévoles requis</label>
          <input type="number" name="nb_benevoles_requis" id="edit_nb_benevoles" min="1">
        </div>
        <div class="form-group" id="edit_montant_group" style="display:none;">
          <label>Montant objectif (DH)</label>
          <input type="number" name="montant_objectif" id="edit_montant" min="0" step="0.01">
        </div>
        <div class="form-group" id="edit_besoins_group" style="display:none;">
          <label>Besoins</label>
          <input type="text" name="besoins" id="edit_besoins">
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-outline" onclick="closeEditPost()">Annuler</button>
          <button type="submit" class="btn btn-orange-save">💾 Enregistrer</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  // Dropdown profil
  document.getElementById('orgDropdown').addEventListener('click', function(e) {
    e.stopPropagation();
    this.classList.toggle('active');
  });
  document.addEventListener('click', function() {
    document.getElementById('orgDropdown').classList.remove('active');
  });

  // Header scroll shadow
  window.addEventListener('scroll', function() {
    document.getElementById('stickyHeader').classList.toggle('scrolled', window.scrollY > 10);
  });

  // ===== MODAL MODIFIER ORGANISATION =====
  function openEditModal() {
    document.getElementById('editOrgModal').classList.add('show');
  }

  function closeEditModal() {
    document.getElementById('editOrgModal').classList.remove('show');
  }

  document.getElementById('editOrgModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
  });

  // ===== TOGGLE ABOUT =====
  function toggleAbout() {
    const text = document.getElementById('aboutText');
    const arrow = document.getElementById('aboutArrow');
    const btn = document.getElementById('toggleAboutBtn');
    
    if (text.classList.contains('collapsed')) {
      text.classList.remove('collapsed');
      text.classList.add('expanded');
      btn.innerHTML = 'Voir moins <span id="aboutArrow">▴</span>';
    } else {
      text.classList.remove('expanded');
      text.classList.add('collapsed');
      btn.innerHTML = 'Voir tout <span id="aboutArrow">▾</span>';
    }
  }

  // ===== MODAL MODIFIER POST =====
  function openEditPost(post) {
    document.getElementById('edit_post_id').value = post.id;
    document.getElementById('edit_titre').value = post.titre;
    document.getElementById('edit_description').value = post.description;
    document.getElementById('edit_date').value = post.date_evenement;
    document.getElementById('edit_localisation').value = post.localisation;
    document.getElementById('edit_benevoles_group').style.display = post.type_demande === 'volontariat' ? 'flex' : 'none';
    document.getElementById('edit_nb_benevoles').value = post.nb_benevoles_requis || '';
    document.getElementById('edit_montant_group').style.display = (post.type_demande === 'donation' && post.sous_type === 'argent') ? 'flex' : 'none';
    document.getElementById('edit_montant').value = post.montant_objectif || '';
    document.getElementById('edit_besoins_group').style.display = (post.type_demande === 'donation' && post.sous_type !== 'argent') ? 'flex' : 'none';
    document.getElementById('edit_besoins').value = post.besoins || '';
    document.getElementById('editPostModal').classList.add('show');
  }

  function closeEditPost() {
    document.getElementById('editPostModal').classList.remove('show');
  }

  document.getElementById('editPostModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditPost();
  });

  // Vérifier si la description est trop longue pour initialiser le bouton
  document.addEventListener('DOMContentLoaded', function() {
    const text = document.getElementById('aboutText');
    const btn = document.getElementById('toggleAboutBtn');
    if (text && btn) {
      const length = text.textContent.trim().length;
      if (length <= 150) {
        btn.style.display = 'none';
        text.classList.remove('collapsed');
        text.classList.add('expanded');
      }
    }
  });
</script>
</body>
</html>