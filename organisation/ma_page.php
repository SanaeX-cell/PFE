<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organisation') {
    header('Location: ../connexion.php');
    exit();
}

$org_id = $_SESSION['user_id'];

// Gestion modification
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $nom = mysqli_real_escape_string($conn, $_POST['nom_organisation']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $adresse = mysqli_real_escape_string($conn, $_POST['adresse']);
    $telephone = mysqli_real_escape_string($conn, $_POST['telephone']);
    $site_web = mysqli_real_escape_string($conn, $_POST['site_web']);

    $sql_update = "UPDATE organisations SET nom_organisation='$nom', description='$description', adresse='$adresse', telephone='$telephone', site_web='$site_web' WHERE id=$org_id";
    if (mysqli_query($conn, $sql_update)) {
        $success_msg = 'Informations mises à jour avec succès.';
    } else {
        $error_msg = 'Erreur lors de la mise à jour.';
    }
}

// Gestion suppression post
if (isset($_GET['delete_post'])) {
    $post_id = (int)$_GET['delete_post'];
    mysqli_query($conn, "DELETE FROM posts WHERE id=$post_id AND organisation_id=$org_id");
    header('Location: ma_page.php');
    exit();
}

// Gestion terminer post
if (isset($_GET['terminer_post'])) {
    $post_id = (int)$_GET['terminer_post'];
    mysqli_query($conn, "UPDATE posts SET statut='termine' WHERE id=$post_id AND organisation_id=$org_id");
    header('Location: ma_page.php');
    exit();
}

// Récupérer les infos
$sql = "SELECT * FROM organisations WHERE id = $org_id";
$result = mysqli_query($conn, $sql);
$org = mysqli_fetch_assoc($result);

// Récupérer les posts
$sql_posts = "SELECT p.*, (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as nb_likes FROM posts p WHERE p.organisation_id = $org_id ORDER BY p.date_creation DESC";
$result_posts = mysqli_query($conn, $sql_posts);
$posts = [];
while($row = mysqli_fetch_assoc($result_posts)) {
    $posts[] = $row;
}

$current_page = basename($_SERVER['PHP_SELF']);
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
    --bg: #f5f6fa; --sidebar-bg: #fff; --card-bg: #fff;
    --text-primary: #1a1d2e; --text-secondary: #8b8fa8; --text-light: #b0b3c6;
    --accent-teal: #1CB8B2; --accent-orange: #F47B20;
    --border: #f0f1f7; --shadow: 0 2px 20px rgba(0,0,0,0.06);
    --radius: 18px; --sidebar-width: 220px;
  }
  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-primary); display: flex; min-height: 100vh; font-size: 14px; }
  .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; border-right: 1px solid var(--border); z-index: 10; }
  .logo { display: flex; align-items: center; justify-content: flex-end; width: 93%; padding: 0 24px 32px 18px; }
  .logo-image { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
  .logo-text { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; white-space: nowrap; }
  .logo-text span:first-child { color: var(--accent-teal); }
  .logo-text span:last-child { color: var(--accent-orange); }
  nav { flex: 1; margin-top: 8px; }
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; font-weight: 500; font-size: 14px; position: relative; margin: 2px 0; text-decoration: none; }
  .nav-item:hover { color: var(--text-primary); background: var(--bg); }
  .nav-item.active { color: var(--accent-teal); background: #E1F7F6; }
  .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; background: var(--accent-teal); border-radius: 0 4px 4px 0; }
  .nav-icon { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .sidebar-bottom { padding: 0; border-top: 1px solid var(--border); padding-top: 12px; margin-top: auto; }
  .sidebar-bottom .nav-item { background: var(--accent-teal); color: white; border-radius: 40px; margin: 8px 16px; justify-content: center; }
  .sidebar-bottom .nav-item:hover { background: #138F8A; color: white; }
  .sidebar-bottom .nav-item::before { display: none; }
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
  .topbar { background: transparent; padding: 16px 32px; display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 5; flex-wrap: wrap; }
  .greeting-bar { flex: 1; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 18px; box-shadow: var(--shadow); min-width: 200px; }
  .greeting-bar h2 { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
  .greeting-bar p { font-size: 11px; color: var(--text-secondary); }
  .date-badge { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; font-size: 13px; font-weight: 500; color: var(--text-primary); box-shadow: var(--shadow); white-space: nowrap; }
  .profile-area { position: relative; }
  .user-info { display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 6px 14px 6px 6px; box-shadow: var(--shadow); }
  .avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--accent-teal); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; overflow: hidden; flex-shrink: 0; }
  .user-name { font-weight: 600; font-size: 13px; white-space: nowrap; color: var(--text-primary); }
  .dropdown-icon { color: #8b8fa8; transition: transform 0.2s; }
  .dropdown-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 180px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
  .profile-area.active .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
  .profile-area.active .dropdown-icon { transform: rotate(180deg); }
  .dropdown-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }
  .content { padding: 20px 32px 40px; display: flex; flex-direction: column; gap: 24px; }

  /* ALERT */
  .alert { padding: 12px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 4px; }
  .alert-success { background: #dcfce7; color: #166534; }
  .alert-error { background: #fee2e2; color: #991b1b; }

  /* CARD */
  .card { background: var(--card-bg); border-radius: var(--radius); padding: 28px; box-shadow: var(--shadow); }
  .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 10px; }
  .card-title { font-size: 16px; font-weight: 700; }

  /* INFO SECTION */
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .info-item label { display: block; font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
  .info-item .value { font-size: 14px; font-weight: 500; color: var(--text-primary); }
  .info-item.full-width { grid-column: 1 / -1; }

  /* FORM STYLES */
  .form-group { display: flex; flex-direction: column; gap: 6px; }
  .form-group label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
  .form-group input, .form-group textarea { border: 1.5px solid var(--border); border-radius: 10px; padding: 10px 14px; font-size: 14px; font-family: inherit; color: var(--text-primary); transition: border-color 0.2s; background: var(--bg); resize: vertical; }
  .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--accent-teal); background: #fff; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 8px; }
  .btn { display: inline-flex; align-items: center; gap: 7px; border: none; border-radius: 10px; padding: 10px 20px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; }
  .btn-primary { background: var(--accent-teal); color: #fff; }
  .btn-primary:hover { background: #138F8A; }
  .btn-outline { background: transparent; color: var(--text-secondary); border: 1.5px solid var(--border); }
  .btn-outline:hover { border-color: var(--accent-teal); color: var(--accent-teal); }
  .btn-orange { background: var(--accent-orange); color: #fff; }
  .btn-orange:hover { background: #E06A10; }

  /* POSTS SECTION */
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
  .btn-icon { display: inline-flex; align-items: center; gap: 5px; border: none; border-radius: 8px; padding: 7px 13px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; }
  .btn-edit { background: #EFF6FF; color: #2563eb; }
  .btn-edit:hover { background: #dbeafe; }
  .btn-delete { background: #FEF2F2; color: #dc2626; }
  .btn-delete:hover { background: #fee2e2; }
  .btn-done { background: #F0FDF4; color: #16a34a; }
  .btn-done:hover { background: #dcfce7; }
  .status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .status-actif { background: #dcfce7; color: #16a34a; }
  .status-termine { background: #f3f4f6; color: #6b7280; }

  /* EDIT MODAL */
  .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 200; align-items: center; justify-content: center; }
  .modal-overlay.show { display: flex; }
  .modal-box { background: #fff; border-radius: 18px; padding: 30px; width: 90%; max-width: 560px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.18); }
  .modal-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; }
  .empty-posts { text-align: center; padding: 40px 20px; color: var(--text-secondary); }
  .empty-posts svg { opacity: 0.3; margin-bottom: 12px; }
  .empty-posts p { font-size: 14px; margin-bottom: 16px; }
</style>
</head>
<body>

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
      Bénévoles inscrits
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

<div class="main">
  <header class="topbar">
    <div class="greeting-bar">
      <h2>Ma page — <?php echo htmlspecialchars($org['nom_organisation']); ?></h2>
      <p>Gérez votre profil et vos publications</p>
    </div>
    <div class="date-badge">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <?php setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr'); echo strftime('%d %B %Y'); ?>
    </div>
    <div class="profile-area" id="profileArea">
      <div class="user-info" onclick="document.getElementById('profileArea').classList.toggle('active')">
        <div class="avatar">
          <?php if($org['logo']): ?>
            <img src="../<?php echo htmlspecialchars($org['logo']); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
          <?php else: ?>
            <?php echo strtoupper(substr($org['nom_organisation'], 0, 1)); ?>
          <?php endif; ?>
        </div>
        <span class="user-name"><?php echo htmlspecialchars($org['email_connexion']); ?></span>
        <span class="dropdown-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg></span>
      </div>
      <div class="dropdown-menu">
        <a href="ma_page.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Ma page</a>
        <hr>
        <a href="../logout.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Déconnexion</a>
      </div>
    </div>
  </header>

  <div class="content">
    <?php if($success_msg): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-error"><?php echo $error_msg; ?></div><?php endif; ?>

    <!-- SECTION INFOS -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Informations de l'organisation</div>
        <button class="btn btn-outline" onclick="toggleEdit()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Modifier
        </button>
      </div>

      <!-- VUE AFFICHAGE -->
      <div id="viewMode">
        <div class="info-grid">
          <div class="info-item">
            <label>Nom de l'organisation</label>
            <div class="value"><?php echo htmlspecialchars($org['nom_organisation']); ?></div>
          </div>
          <div class="info-item">
            <label>Email</label>
            <div class="value"><?php echo htmlspecialchars($org['email_connexion']); ?></div>
          </div>
          <div class="info-item">
            <label>Téléphone</label>
            <div class="value"><?php echo $org['telephone'] ?: '—'; ?></div>
          </div>
          <div class="info-item">
            <label>Site web</label>
            <div class="value"><?php echo $org['site_web'] ?: '—'; ?></div>
          </div>
          <div class="info-item full-width">
            <label>Adresse</label>
            <div class="value"><?php echo $org['adresse'] ?: '—'; ?></div>
          </div>
          <div class="info-item full-width">
            <label>Description</label>
            <div class="value" style="line-height:1.7;"><?php echo $org['description'] ?: '—'; ?></div>
          </div>
        </div>
      </div>

      <!-- VUE MODIFICATION -->
      <form method="POST" id="editMode" style="display:none;">
        <input type="hidden" name="action" value="modifier">
        <div style="display:flex;flex-direction:column;gap:16px;">
          <div class="form-row">
            <div class="form-group">
              <label>Nom de l'organisation</label>
              <input type="text" name="nom_organisation" value="<?php echo htmlspecialchars($org['nom_organisation']); ?>" required>
            </div>
            <div class="form-group">
              <label>Téléphone</label>
              <input type="text" name="telephone" value="<?php echo htmlspecialchars($org['telephone']); ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Site web</label>
              <input type="text" name="site_web" value="<?php echo htmlspecialchars($org['site_web']); ?>">
            </div>
            <div class="form-group">
              <label>Email (non modifiable)</label>
              <input type="text" value="<?php echo htmlspecialchars($org['email_connexion']); ?>" disabled style="opacity:0.6;cursor:not-allowed;">
            </div>
          </div>
          <div class="form-group">
            <label>Adresse</label>
            <input type="text" name="adresse" value="<?php echo htmlspecialchars($org['adresse']); ?>">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4"><?php echo htmlspecialchars($org['description']); ?></textarea>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="toggleEdit()">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
          </div>
        </div>
      </form>
    </div>

    <!-- SECTION CRÉER POST CTA -->
    <div class="card" style="background: linear-gradient(135deg,#fff 0%,#f0fffe 100%); border: 1.5px solid #c8f0ee; display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
      <div>
        <div style="font-size:16px;font-weight:700;margin-bottom:6px;">📢 Créez un nouveau post</div>
        <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;">Publiez un appel à bénévoles ou à dons pour mobiliser votre communauté.</p>
      </div>
      <a href="creer_post.php" class="btn btn-orange" style="text-decoration:none;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Créer un post
      </a>
    </div>

    <!-- SECTION POSTS -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Mes publications (<?php echo count($posts); ?>)</div>
      </div>
      <?php if(empty($posts)): ?>
        <div class="empty-posts">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <p>Aucune publication pour le moment.</p>
          <a href="creer_post.php" class="btn btn-orange" style="text-decoration:none;">Créer mon premier post</a>
        </div>
      <?php else: ?>
        <div class="posts-list">
          <?php foreach($posts as $post): ?>
          <div class="post-card">
            <div class="post-header">
              <div>
                <div class="post-title"><?php echo htmlspecialchars($post['titre']); ?></div>
              </div>
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
                <a href="?terminer_post=<?php echo $post['id']; ?>" class="btn-icon btn-done" onclick="return confirm('Marquer ce post comme terminé ?')" style="text-decoration:none;">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  Terminé
                </a>
                <?php endif; ?>
                <a href="?delete_post=<?php echo $post['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Supprimer ce post définitivement ?')" style="text-decoration:none;">
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

<!-- MODAL MODIFIER POST -->
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
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function toggleEdit() {
  const view = document.getElementById('viewMode');
  const edit = document.getElementById('editMode');
  if (edit.style.display === 'none') {
    edit.style.display = 'block';
    view.style.display = 'none';
  } else {
    edit.style.display = 'none';
    view.style.display = 'block';
  }
}

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

document.addEventListener('click', function(e) {
  const area = document.getElementById('profileArea');
  if (area && !area.contains(e.target)) area.classList.remove('active');
});
</script>
</body>
</html>