<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../connexion.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['org_id'])) {
    $org_id = intval($_POST['org_id']);
    $action = $_POST['action'];
    if ($action === 'accepter') {
        mysqli_query($conn, "UPDATE organisations SET statut='valide' WHERE id=$org_id");
    } elseif ($action === 'refuser') {
        mysqli_query($conn, "UPDATE organisations SET statut='refuse' WHERE id=$org_id");
    }
    header('Location: demandes.php');
    exit();
}

$sql = "SELECT o.id, o.nom_organisation, o.email_connexion, o.telephone, 
               o.date_inscription, o.justificatif, o.logo
        FROM organisations o 
        WHERE o.statut = 'en_attente' 
        ORDER BY o.date_inscription DESC";
$result = mysqli_query($conn, $sql);
$demandes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $demandes[] = $row;
}
$nb_demandes = count($demandes);

$res_acc = mysqli_query($conn, "SELECT COUNT(*) as total FROM organisations WHERE statut='valide'");
$nb_acceptees = mysqli_fetch_assoc($res_acc)['total'];

$res_ref = mysqli_query($conn, "SELECT COUNT(*) as total FROM organisations WHERE statut='refuse'");
$nb_refusees = mysqli_fetch_assoc($res_ref)['total'];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Demandes d'inscription</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #f5f6fa;
    --sidebar-bg: #fff;
    --card-bg: #fff;
    --text-primary: #1a1d2e;
    --text-secondary: #8b8fa8;
    --text-light: #b0b3c6;
    --accent-teal: #1CB8B2;
    --accent-orange: #F47B20;
    --green: #22c55e;
    --red: #ef4444;
    --border: #f0f1f7;
    --shadow: 0 2px 20px rgba(0,0,0,0.06);
    --radius: 18px;
    --sidebar-width: 220px;
  }

  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-primary); display: flex; min-height: 100vh; font-size: 14px; }

  /* ===== SIDEBAR ===== */
  .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; border-right: 1px solid var(--border); z-index: 10; }
  .logo { display: flex; align-items: center; gap: 10px; padding: 0 24px 32px 18px; }
  .logo-image { width: 40px; height: 40px; object-fit: contain; border-radius: 10px; flex-shrink: 0; }
  .logo-text { font-size: 18px; font-weight: 700; letter-spacing: -0.5px; white-space: nowrap; }
  .logo-text span:first-child { color: var(--accent-teal); }
  .logo-text span:last-child  { color: var(--accent-orange); }
  nav { flex: 1; margin-top: 8px; }
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; font-weight: 500; font-size: 14px; position: relative; margin: 2px 0; }
  .nav-item:hover { color: var(--text-primary); background: var(--bg); }
  .nav-item.active { color: var(--accent-teal); background: #E1F7F6; }
  .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; background: var(--accent-teal); border-radius: 0 4px 4px 0; }
  .nav-icon { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .sidebar-bottom { padding: 0; border-top: 1px solid var(--border); padding-top: 12px; margin-top: auto; }
  .sidebar-bottom .nav-item { background: var(--accent-teal); color: white; border-radius: 40px; margin: 8px 16px; justify-content: center; }
  .sidebar-bottom .nav-item:hover { background: #138F8A; color: white; }
  .sidebar-bottom .nav-item::before { display: none; }

  /* ===== MAIN ===== */
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

  /* ===== TOPBAR ===== */
  .topbar { background: transparent; padding: 16px 32px; display: flex; align-items: center; gap: 12px; }

  .search-bar { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; flex: 1; font-size: 13px; color: var(--text-secondary); box-shadow: var(--shadow); }
  .search-bar input { border: none; outline: none; background: transparent; width: 100%; font-family: inherit; font-size: 13px; color: var(--text-primary); }
  .search-bar input::placeholder { color: var(--text-secondary); }

  .right-group { display: flex; align-items: center; gap: 12px; margin-left: auto; }

  .date-picker { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; font-size: 13px; font-weight: 500; cursor: pointer; color: var(--text-primary); box-shadow: var(--shadow); white-space: nowrap; }

  .profile-dropdown { position: relative; }
  .user-info { display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 6px 14px 6px 6px; box-shadow: var(--shadow); }
  .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent-teal); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; overflow: hidden; }
  .user-name { font-weight: 600; font-size: 13px; white-space: nowrap; }
  .dropdown-icon { font-size: 12px; transition: transform 0.2s; display: inline-flex; align-items: center; color: #8b8fa8; }
  .dropdown-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 200px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
  .profile-dropdown.active .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
  .profile-dropdown.active .dropdown-icon { transform: rotate(180deg); }
  .dropdown-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }

  /* ===== CONTENT ===== */
  .content { padding: 20px 32px 28px 32px; display: flex; flex-direction: column; gap: 20px; }

  .page-title-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 24px; font-size: 20px; font-weight: 700; color: var(--text-primary); box-shadow: var(--shadow); font-family: 'Plus Jakarta Sans', sans-serif; }

  /* ===== STATS ===== */
  .stats-row { display: flex; flex-wrap: wrap; gap: 10px; }
  .stat-card { display: inline-flex; align-items: center; gap: 8px; background: #fff; padding: 10px 18px; border-radius: 50px; box-shadow: var(--shadow); border: 1px solid var(--border); }
  .stat-card__icon { display: flex; align-items: center; flex-shrink: 0; }
  .stat-card__label { font-size: 13px; font-weight: 500; color: var(--text-secondary); white-space: nowrap; }
  .stat-card__value { font-size: 13px; font-weight: 700; color: var(--text-primary); }

  /* ===== TABLE CARD ===== */
  .card { background: #fff; border-radius: 18px; overflow: hidden; border: 1px solid var(--border); box-shadow: var(--shadow); width: 100%; }

  .dem-table { width: 100%; border-collapse: collapse; }
  .dem-table thead tr { background: var(--accent-orange); }
  .dem-table th { text-align: left; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.9); text-transform: uppercase; letter-spacing: 0.6px; padding: 12px 10px; }
  .dem-table td { padding: 13px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 13px; color: var(--text-primary); }
  .dem-table tbody tr:last-child td { border-bottom: none; }
  .dem-table tbody tr:hover { background: #fafbff; }

  /* Organisation cell */
  .org-cell { display: flex; align-items: center; gap: 10px; }
  .org-logo { width: 34px; height: 34px; border-radius: 10px; background: #E1F7F6; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: var(--accent-teal); flex-shrink: 0; overflow: hidden; }
  .org-logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
  .org-nom { font-size: 13px; font-weight: 600; color: var(--text-primary); }

  .email-cell { color: var(--text-secondary); font-size: 13px; }
  .tel-cell   { color: var(--text-secondary); font-size: 13px; }
  .date-cell  { color: var(--text-secondary); white-space: nowrap; font-size: 13px; }

  /* Justificatif */
  .pdf-link { display: inline-flex; align-items: center; gap: 6px; color: var(--text-primary); font-size: 12px; font-weight: 500; text-decoration: none; background: #f5f6fa; padding: 6px 10px; border-radius: 8px; transition: all 0.2s; border: 1px solid var(--border); }
  .pdf-link:hover { background: #eef0f8; }

  /* Actions */
  .actions-cell { display: flex; align-items: center; gap: 8px; }

  .btn-accepter { display: inline-flex; align-items: center; background: transparent; color: var(--accent-teal); border: 1.5px solid var(--accent-teal); border-radius: 50px; padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; white-space: nowrap; }
  .btn-accepter:hover { background: rgba(28,184,178,0.08); transform: translateY(-1px); }

  .btn-refuser { display: inline-flex; align-items: center; background: #fff5f5; color: var(--red); border: 1.5px solid #fecaca; border-radius: 50px; padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; white-space: nowrap; }
  .btn-refuser:hover { background: #fee2e2; border-color: var(--red); transform: translateY(-1px); }

  /* ===== PAGINATION ===== */
  .pagination-wrap { display: flex; justify-content: flex-end; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); }
  .pagination-controls { display: flex; align-items: center; gap: 6px; }
  .page-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: #fff; color: var(--text-secondary); font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.18s; font-family: inherit; }
  .page-btn:hover:not(:disabled):not(.active) { border-color: var(--accent-orange); color: var(--accent-orange); background: #fff4ee; }
  .page-btn.active { background: var(--accent-orange); color: #fff; border-color: var(--accent-orange); }
  .page-btn:disabled { opacity: 0.35; cursor: not-allowed; }

  /* ===== TOAST ===== */
  .toast { position: fixed; bottom: 28px; right: 28px; background: #1a1d2e; color: #fff; padding: 12px 20px; border-radius: 12px; font-size: 13px; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.18); display: flex; align-items: center; gap: 10px; opacity: 0; transform: translateY(12px); transition: all 0.3s; z-index: 999; pointer-events: none; }
  .toast.show { opacity: 1; transform: translateY(0); }
  .toast.success { background: #166534; }
  .toast.error   { background: #991b1b; }

  /* ===== MODAL ===== */
  .modal-overlay { position: fixed; inset: 0; background: rgba(26,29,46,0.45); z-index: 200; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.2s; }
  .modal-overlay.open { opacity: 1; visibility: visible; }
  .modal { background: #fff; border-radius: var(--radius); padding: 28px; max-width: 380px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.15); text-align: center; transform: scale(0.95); transition: transform 0.2s; }
  .modal-overlay.open .modal { transform: scale(1); }
  .modal-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
  .modal-icon.accept { background: rgba(28,184,178,0.1); }
  .modal-icon.refuse { background: rgba(239,68,68,0.1); }
  .modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
  .modal p { font-size: 13px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5; }
  .modal-actions { display: flex; gap: 10px; justify-content: center; }
  .btn-cancel { flex: 1; padding: 10px; border: 1px solid var(--border); background: #fff; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; color: var(--text-secondary); transition: background 0.18s; }
  .btn-cancel:hover { background: var(--bg); }
  .btn-confirm-accept { flex: 1; padding: 10px; background: var(--accent-teal); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.18s; }
  .btn-confirm-accept:hover { background: #138F8A; }
  .btn-confirm-refuse { flex: 1; padding: 10px; background: var(--red); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.18s; }
  .btn-confirm-refuse:hover { background: #dc2626; }

  .empty-state { text-align: center; padding: 60px 20px; color: var(--text-secondary); font-size: 13px; }
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
    <div class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" data-page="dashboard">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
      Tableau de bord
    </div>
    <div class="nav-item <?php echo ($current_page == 'demandes.php') ? 'active' : ''; ?>" data-page="demandes">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.708l.547.548 1.17-1.951a.5.5 0 1 1 .858.514"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6.5a.5.5 0 0 1-1 0V1H3v14h3v-2.5a.5.5 0 0 1 .5-.5H8v4H3a1 1 0 0 1-1-1z"/><path d="M4.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/></svg></span>
      Demandes
    </div>
    <div class="nav-item <?php echo ($current_page == 'organisations.php') ? 'active' : ''; ?>" data-page="organisations-nav">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/></svg></span>
      Organisations
    </div>
    <div class="nav-item <?php echo ($current_page == 'contributeurs.php') ? 'active' : ''; ?>" data-page="contributeurs">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      Contributeurs
    </div>
  </nav>
  <div class="sidebar-bottom">
    <div class="nav-item" data-page="deconnexion">
      <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      Déconnexion
    </div>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="search-bar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Rechercher...">
    </div>
    <div class="right-group">
      <div class="date-picker">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?php
          $mois_fr = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
          echo date('d') . ' ' . $mois_fr[(int)date('n') - 1] . ' ' . date('Y');
        ?>
      </div>
      <div class="profile-dropdown" id="profileDropdown">
        <div class="user-info" onclick="toggleDropdown()">
          <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_nom'], 0, 1)); ?></div>
          <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nom']); ?></span>
          <span class="dropdown-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg></span>
        </div>
        <div class="dropdown-menu">
          <a href="mon_profil.php?from=demandes"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Mon profil</a>
          <hr>
          <a href="../logout.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Déconnexion</a>
        </div>
      </div>
    </div>
  </header>

  <!-- CONTENT -->
  <div class="content">

    <div class="page-title-card">Gestion des demandes d'inscription</div>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat-card">
        <span class="stat-card__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e6a817" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
        <span class="stat-card__label">En attente :</span>
        <span class="stat-card__value" id="nbDemandesLabel"><?php echo $nb_demandes; ?></span>
      </div>
      <div class="stat-card">
        <span class="stat-card__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1CB8B2" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span>
        <span class="stat-card__label">Total acceptées :</span>
        <span class="stat-card__value"><?php echo $nb_acceptees; ?></span>
      </div>
      <div class="stat-card">
        <span class="stat-card__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span>
        <span class="stat-card__label">Total refusées :</span>
        <span class="stat-card__value"><?php echo $nb_refusees; ?></span>
      </div>
    </div>

    <!-- TABLE -->
    <div class="card">
      <table class="dem-table" id="demTable">
        <thead>
          <tr>
            <th>Organisation</th>
            <th>Email</th>
            <th>Téléphone</th>
            <th>Date d'inscription</th>
            <th>Justificatif</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="demTableBody"></tbody>
      </table>
      <div class="pagination-wrap">
        <div class="pagination-controls" id="paginationControls"></div>
      </div>
    </div>

  </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-icon" id="modalIcon">
      <svg id="modalIconSvg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke-width="2"></svg>
    </div>
    <h3 id="modalTitle">Confirmer la décision</h3>
    <p id="modalText">Êtes-vous sûr de vouloir effectuer cette action ?</p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">Annuler</button>
      <button id="btnConfirm" onclick="confirmAction()">Confirmer</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const allDemandes = <?php echo json_encode($demandes); ?>;
const ITEMS_PER_PAGE = 10;
let currentPage = 1;
let filtered = [...allDemandes];
let pendingAction = null;

const moisFr = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

function formatDate(str) {
  if (!str) return '—';
  var d = new Date(str);
  return d.getDate() + ' ' + moisFr[d.getMonth()] + ' ' + d.getFullYear();
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function getFirstLetter(str) {
  if (!str || str.trim() === '') return '?';
  return str.trim().charAt(0).toUpperCase();
}

function getLogoHtml(org) {
  if (org.logo && org.logo !== '') {
    return '<img src="../' + escapeHtml(org.logo) + '" alt="Logo">';
  }
  return '<span>' + getFirstLetter(org.nom_organisation) + '</span>';
}

function renderTable(data, page) {
  var tbody = document.getElementById('demTableBody');
  tbody.innerHTML = '';

  if (data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state">Aucune demande en attente</div></td></tr>';
    renderPagination(0, 1);
    return;
  }

  var start = (page - 1) * ITEMS_PER_PAGE;
  var slice = data.slice(start, start + ITEMS_PER_PAGE);

  slice.forEach(function(d) {
    var fileName = d.justificatif ? d.justificatif.split('/').pop() : '';
    var justificatifHtml = d.justificatif
      ? '<a class="pdf-link" href="/PFE/' + escapeHtml(d.justificatif) + '" target="_blank">'
        + '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/></svg>'
        + escapeHtml(fileName) + '</a>'
      : '<span style="color:var(--text-light);font-size:12px;">—</span>';

    var nomEscaped = escapeHtml(d.nom_organisation).replace(/'/g, "\\'");

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><div class="org-cell">'
        + '<div class="org-logo">' + getLogoHtml(d) + '</div>'
        + '<span class="org-nom">' + escapeHtml(d.nom_organisation) + '</span>'
      + '</div></td>'
      + '<td class="email-cell">' + escapeHtml(d.email_connexion || '—') + '</td>'
      + '<td class="tel-cell">'   + escapeHtml(d.telephone || '—')       + '</td>'
      + '<td class="date-cell">'  + formatDate(d.date_inscription)        + '</td>'
      + '<td>' + justificatifHtml + '</td>'
      + '<td class="actions-cell">'
        + '<button class="btn-accepter" onclick="openModal(' + d.id + ', \'accepter\', \'' + nomEscaped + '\')">Accepter</button>'
        + '<button class="btn-refuser"  onclick="openModal(' + d.id + ', \'refuser\',  \'' + nomEscaped + '\')">Refuser</button>'
      + '</td>';
    tbody.appendChild(tr);
  });

  renderPagination(data.length, page);
}

function renderPagination(total, page) {
  var totalPages = Math.ceil(total / ITEMS_PER_PAGE);
  var wrap = document.getElementById('paginationControls');
  wrap.innerHTML = '';
  if (totalPages <= 1) return;

  var prev = document.createElement('button');
  prev.className = 'page-btn';
  prev.disabled = page === 1;
  prev.innerHTML = '‹';
  prev.onclick = function() { goToPage(page - 1); };
  wrap.appendChild(prev);

  for (var i = 1; i <= totalPages; i++) {
    (function(p) {
      var btn = document.createElement('button');
      btn.className = 'page-btn' + (p === page ? ' active' : '');
      btn.textContent = p;
      btn.onclick = function() { goToPage(p); };
      wrap.appendChild(btn);
    })(i);
  }

  var next = document.createElement('button');
  next.className = 'page-btn';
  next.disabled = page === totalPages;
  next.innerHTML = '›';
  next.onclick = function() { goToPage(page + 1); };
  wrap.appendChild(next);
}

function goToPage(p) { currentPage = p; renderTable(filtered, currentPage); }

document.getElementById('searchInput').addEventListener('input', function() {
  var term = this.value.toLowerCase();
  filtered = allDemandes.filter(function(d) {
    return (d.nom_organisation || '').toLowerCase().includes(term)
        || (d.email_connexion  || '').toLowerCase().includes(term)
        || (d.telephone        || '').toLowerCase().includes(term);
  });
  currentPage = 1;
  renderTable(filtered, currentPage);
  document.getElementById('nbDemandesLabel').textContent = filtered.length;
});

function openModal(id, action, nom) {
  pendingAction = { id: id, action: action, nom: nom };
  var icon    = document.getElementById('modalIcon');
  var iconSvg = document.getElementById('modalIconSvg');
  var title   = document.getElementById('modalTitle');
  var text    = document.getElementById('modalText');
  var btn     = document.getElementById('btnConfirm');

  if (action === 'accepter') {
    icon.className = 'modal-icon accept';
    iconSvg.setAttribute('stroke', '#1CB8B2');
    iconSvg.innerHTML = '<polyline points="20 6 9 17 4 12"/>';
    title.textContent = 'Accepter la demande';
    text.textContent  = 'Accepter l\'organisation "' + nom + '" ? Elle pourra accéder à la plateforme.';
    btn.className     = 'btn-confirm-accept';
    btn.textContent   = 'Confirmer';
  } else {
    icon.className = 'modal-icon refuse';
    iconSvg.setAttribute('stroke', '#ef4444');
    iconSvg.innerHTML = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
    title.textContent = 'Refuser la demande';
    text.textContent  = 'Refuser la demande de "' + nom + '" ? Cette action est irréversible.';
    btn.className     = 'btn-confirm-refuse';
    btn.textContent   = 'Confirmer';
  }
  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  pendingAction = null;
}

function confirmAction() {
  if (!pendingAction) return;
  var action = pendingAction.action;
  var orgId  = pendingAction.id;
  var nom    = pendingAction.nom;
  closeModal();
  showToast(
    action === 'accepter' ? '✓ "' + nom + '" a été acceptée' : '✗ "' + nom + '" a été refusée',
    action === 'accepter' ? 'success' : 'error'
  );
  var form = document.createElement('form');
  form.method = 'POST';
  form.action = 'demandes.php';
  form.style.display = 'none';
  var iAction = document.createElement('input');
  iAction.type = 'hidden'; iAction.name = 'action'; iAction.value = action;
  var iId = document.createElement('input');
  iId.type = 'hidden'; iId.name = 'org_id'; iId.value = orgId;
  form.appendChild(iAction);
  form.appendChild(iId);
  document.body.appendChild(form);
  setTimeout(function() { form.submit(); }, 700);
}

function showToast(msg, type) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast ' + (type || '') + ' show';
  setTimeout(function() { t.classList.remove('show'); }, 3000);
}

function toggleDropdown() {
  document.getElementById('profileDropdown').classList.toggle('active');
}
document.addEventListener('click', function(e) {
  var d = document.getElementById('profileDropdown');
  if (d && !d.contains(e.target)) d.classList.remove('active');
});
document.getElementById('modalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

document.querySelectorAll('.nav-item[data-page]').forEach(function(item) {
  item.addEventListener('click', function() {
    var p = item.getAttribute('data-page');
    if      (p === 'dashboard')         window.location.href = 'dashboard.php';
    else if (p === 'demandes')          window.location.href = 'demandes.php';
    else if (p === 'organisations-nav') window.location.href = 'organisations.php';
    else if (p === 'contributeurs')     window.location.href = 'contributeurs.php';
    else if (p === 'deconnexion')       window.location.href = '../logout.php';
  });
});

renderTable(filtered, currentPage);
</script>
</body>
</html>