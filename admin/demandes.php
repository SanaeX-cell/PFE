<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../connexion.php');
    exit();
}

// ========== TRAITEMENT ACCEPTER / REFUSER ==========
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

// ========== DEMANDES EN ATTENTE ==========
$sql = "SELECT o.id, o.nom_organisation, o.email_connexion, o.telephone, 
               o.date_inscription, o.justificatif
        FROM organisations o 
        WHERE o.statut = 'en_attente' 
        ORDER BY o.date_inscription DESC";
$result = mysqli_query($conn, $sql);
$demandes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $demandes[] = $row;
}
$nb_demandes = count($demandes);

// ========== STATISTIQUES TOTAUX ==========
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
  .logo { display: flex; align-items: center; justify-content: flex-end; width: 93%; padding: 0 24px 32px 18px; }
  .logo-image { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
  .logo-text { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; white-space: nowrap; }
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
  .topbar {
    background: transparent;
    padding: 16px 32px 16px calc(32px + 15px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 5;
    flex-wrap: wrap;
  }

  .today-date {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    box-shadow: var(--shadow);
    white-space: nowrap;
    flex-shrink: 0;
  }
  .today-date svg { color: var(--accent-teal); flex-shrink: 0; }

  .search-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 16px;
    flex: 1;
    max-width: none;
    font-size: 13px;
    color: var(--text-secondary);
    box-shadow: var(--shadow);
  }
  .search-bar input { border: none; outline: none; background: transparent; width: 100%; font-family: inherit; font-size: 13px; }

  .topbar-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
  .profile-dropdown { position: relative; }
  .user-info { display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 6px 14px 6px 6px; box-shadow: var(--shadow); }
  .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent-teal); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; }
  .user-name { font-weight: 600; font-size: 13px; }
  .dropdown-icon { transition: transform 0.2s; display: inline-flex; align-items: center; color: #8b8fa8; }
  .dropdown-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 200px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
  .profile-dropdown.active .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
  .profile-dropdown.active .dropdown-icon { transform: rotate(180deg); }
  .dropdown-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }

  /* ===== CONTENT ===== */
  .content { padding: 8px 32px 32px calc(32px + 15px); display: flex; flex-direction: column; gap: 24px; }

  /* ===== WELCOME CARD ===== */
  .welcome-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 24px 28px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
  }
  .welcome-card h1 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 6px;
  }
  .welcome-card p {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
  }

  /* ===== STATS CARDS ROW ===== */
  .stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
  }

  .stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: #fff;
    padding: 20px 24px;
    border-radius: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    min-height: 90px;
  }
  .stat-card__icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .stat-card--attente .stat-card__icon { background: linear-gradient(135deg, #1CB8B2, #138F8A); color: #fff; }
  .stat-card--green .stat-card__icon   { background: rgba(34,197,94,0.12); color: #22c55e; }
  .stat-card--red .stat-card__icon     { background: rgba(239,68,68,0.12); color: #ef4444; }
  .stat-card__body { display: flex; flex-direction: column; }
  .stat-card__label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin-bottom: 4px; }
  .stat-card__value { font-size: 28px; font-weight: 800; color: var(--text-primary); line-height: 1; }
  .stat-card--attente .stat-card__value { color: var(--accent-teal); }

  /* ===== CARD TABLEAU ===== */
  .card { background: var(--card-bg); border-radius: var(--radius); padding: 0; box-shadow: var(--shadow); overflow: hidden; }

  /* ===== TABLEAU ===== */
  .dem-table { width: 100%; border-collapse: collapse; }
  .dem-table thead tr { background: #fafbff; }
  .dem-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; padding: 16px 20px; border-bottom: 1px solid var(--border); }
  .dem-table td { padding: 18px 20px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 13px; color: var(--text-primary); }
  .dem-table tbody tr:last-child td { border-bottom: none; }
  .dem-table tbody tr { transition: background 0.15s; }
  .dem-table tbody tr:hover { background: #fafbff; }

  .org-cell { display: flex; align-items: center; gap: 12px; }
  .org-logo { width: 42px; height: 42px; border-radius: 12px; background: var(--bg); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; border: 1px solid var(--border); }
  .org-nom { font-weight: 600; font-size: 14px; color: var(--text-primary); }
  .email-cell { color: var(--text-secondary); font-size: 13px; }
  .tel-cell { color: var(--text-secondary); font-size: 13px; }
  .date-cell { color: var(--text-secondary); white-space: nowrap; }
  .pdf-link { display: inline-flex; align-items: center; gap: 6px; color: var(--accent-teal); font-size: 12px; font-weight: 600; text-decoration: none; background: rgba(28,184,178,0.08); padding: 5px 10px; border-radius: 8px; transition: background 0.15s; white-space: nowrap; }
  .pdf-link:hover { background: rgba(28,184,178,0.15); }
  .actions-cell { display: flex; align-items: center; gap: 8px; }
  .btn-accepter { display: inline-flex; align-items: center; gap: 6px; background: var(--accent-teal); color: #fff; border: none; border-radius: 10px; padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.18s, transform 0.1s; white-space: nowrap; }
  .btn-accepter:hover { background: #138F8A; transform: translateY(-1px); }
  .btn-refuser { display: inline-flex; align-items: center; gap: 6px; background: #fff; color: var(--red); border: 1.5px solid var(--red); border-radius: 10px; padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.18s, transform 0.1s; white-space: nowrap; }
  .btn-refuser:hover { background: #fff1f1; transform: translateY(-1px); }

  /* ===== PAGINATION ===== */
  .pagination-wrap { display: flex; justify-content: flex-end; align-items: center; gap: 8px; padding: 18px 20px; border-top: 1px solid var(--border); }
  .page-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; border: 1px solid var(--border); background: #fff; color: var(--text-secondary); font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.18s; }
  .page-btn:hover:not(:disabled) { border-color: var(--accent-teal); color: var(--accent-teal); background: #E1F7F6; }
  .page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
  .page-btn.active { background: #F47B20; color: #fff; border-color: #F47B20; }
  .page-num { width: 34px; height: 34px; padding: 0; justify-content: center; }
  .page-dots { padding: 0 4px; color: var(--text-light); font-size: 13px; display: flex; align-items: center; }

  /* ===== TOAST ===== */
  .toast { position: fixed; bottom: 28px; right: 28px; background: #1a1d2e; color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.18); display: flex; align-items: center; gap: 10px; opacity: 0; transform: translateY(12px); transition: all 0.3s; z-index: 999; pointer-events: none; }
  .toast.show { opacity: 1; transform: translateY(0); }
  .toast.success { background: #166534; }
  .toast.error   { background: #991b1b; }

  /* ===== MODAL ===== */
  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 200; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.2s; }
  .modal-overlay.show { opacity: 1; visibility: visible; }
  .modal { background: #fff; border-radius: 20px; padding: 32px; width: 380px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); transform: scale(0.95); transition: transform 0.2s; }
  .modal-overlay.show .modal { transform: scale(1); }
  .modal-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 18px; }
  .modal-icon.danger { background: #fee2e2; }
  .modal-icon.success { background: #dcfce7; }
  .modal h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
  .modal p  { font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 24px; }
  .modal-actions { display: flex; gap: 10px; }
  .modal-actions button { flex: 1; padding: 11px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; border: none; transition: all 0.18s; }
  .btn-cancel { background: var(--bg); color: var(--text-secondary); border: 1px solid var(--border) !important; }
  .btn-cancel:hover { background: var(--border); }
  .btn-confirm-accept { background: var(--accent-teal); color: #fff; }
  .btn-confirm-accept:hover { background: #138F8A; }
  .btn-confirm-refuse { background: var(--red); color: #fff; }
  .btn-confirm-refuse:hover { background: #dc2626; }
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
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.708l.547.548 1.17-1.951a.5.5 0 1 1 .858.514"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6.5a.5.5 0 0 1-1 0V1H3v14h3v-2.5a.5.5 0 0 1 .5-.5H8v4H3a1 1 0 0 1-1-1z"/><path d="M4.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/></svg></span>
      Demandes
    </div>
    <div class="nav-item <?php echo ($current_page == 'organisations.php') ? 'active' : ''; ?>" data-page="organisations-nav">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/></svg></span>
      Organisations
    </div>
    <div class="nav-item <?php echo ($current_page == 'contributeurs.php') ? 'active' : ''; ?>" data-page="contributeurs">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      Contributeurs
    </div>
  </nav>
  <div class="sidebar-bottom">
    <div class="nav-item" data-page="deconnexion">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      Déconnexion
    </div>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

  <!-- TOPBAR (barre de recherche à gauche, date + profil à droite) -->
  <header class="topbar">

    <!-- Barre de recherche (gauche, s'étire) -->
    <div class="search-bar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Rechercher une organisation...">
    </div>

    <!-- Date + Profil (droite, groupés) -->
    <div class="topbar-right">

      <div class="today-date">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <span id="todayLabel"></span>
      </div>

      <div class="profile-dropdown" id="profileDropdown">
        <div class="user-info" onclick="toggleDropdown()">
          <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_nom'], 0, 1)); ?></div>
          <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nom']); ?></span>
          <span class="dropdown-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg>
          </span>
        </div>
        <div class="dropdown-menu">
          <a href="mon_profil.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Mon profil</a>
          <hr>
          <a href="../logout.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Déconnexion</a>
        </div>
      </div>

    </div>
  </header>

  <!-- CONTENT -->
  <div class="content">

    <!-- WELCOME CARD (titre + sous-titre) -->
    <div class="welcome-card">
      <h1>Demandes d'inscription</h1>
      <p>Validez ou refusez les nouvelles organisations souhaitant rejoindre le réseau.</p>
    </div>

    <!-- STATS : 3 cartes -->
    <div class="stats-row">
      <div class="stat-card stat-card--attente">
        <div class="stat-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="white" viewBox="0 0 16 16">
            <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.708l.547.548 1.17-1.951a.5.5 0 1 1 .858.514"/>
            <path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6.5a.5.5 0 0 1-1 0V1H3v14h3v-2.5a.5.5 0 0 1 .5-.5H8v4H3a1 1 0 0 1-1-1z"/>
            <path d="M4.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/>
          </svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">En attente</span>
          <span class="stat-card__value" id="nbDemandesLabel"><?php echo $nb_demandes; ?></span>
        </div>
      </div>

      <div class="stat-card stat-card--green">
        <div class="stat-card__icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">Total acceptées</span>
          <span class="stat-card__value"><?php echo $nb_acceptees; ?></span>
        </div>
      </div>

      <div class="stat-card stat-card--red">
        <div class="stat-card__icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
          </svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">Total refusées</span>
          <span class="stat-card__value"><?php echo $nb_refusees; ?></span>
        </div>
      </div>
    </div>

    <!-- TABLEAU -->
    <div class="card">
      <table class="dem-table" id="demTable">
        <thead>
          <tr>
            <th>Organisation</th>
            <th>Email</th>
            <th>Téléphone</th>
            <th>Date</th>
            <th>Justificatif</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="demTableBody"></tbody>
      </table>
      <div class="pagination-wrap" id="paginationWrap"></div>
    </div>
  </div>
</div>

<!-- ===== MODAL CONFIRMATION ===== -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-icon" id="modalIcon">
      <svg id="modalIconSvg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke-width="2"></svg>
    </div>
    <h3 id="modalTitle"></h3>
    <p id="modalText"></p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">Annuler</button>
      <button id="btnConfirm" onclick="confirmAction()"></button>
    </div>
  </div>
</div>

<!-- ===== TOAST ===== -->
<div class="toast" id="toast"></div>

<script>
// ========== DATE DU JOUR ==========
(function() {
  const now = new Date();
  const label = now.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
  document.getElementById('todayLabel').textContent = label;
})();

// ========== DONNÉES ==========
const allDemandes = <?php echo json_encode($demandes); ?>;
const ITEMS_PER_PAGE = 5;
let currentPage = 1;
let filtered = [...allDemandes];
let pendingAction = null;

function formatDate(str) {
  if (!str) return '—';
  return new Date(str).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;');
}

function renderTable(data, page) {
  const tbody = document.getElementById('demTableBody');
  const pagWrap = document.getElementById('paginationWrap');
  tbody.innerHTML = '';

  if (data.length === 0) {
    tbody.innerHTML = `<td><td colspan="6" style="text-align:center;padding:52px 20px;color:var(--text-secondary);font-size:13px;">
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#1CB8B2" stroke-width="1.5" style="display:block;margin:0 auto 12px;opacity:0.35"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
      Aucune demande en attente
    <\/div></td>`;
    pagWrap.innerHTML = '';
    return;
  }

  const start = (page - 1) * ITEMS_PER_PAGE;
  const slice = data.slice(start, start + ITEMS_PER_PAGE);

  slice.forEach(d => {
    const tr = document.createElement('tr');
    const justificatifPath = d.justificatif ? `/PFE/${d.justificatif}` : '';
    const justificatifHtml = d.justificatif
      ? `<a class="pdf-link" href="${justificatifPath}" target="_blank">
           <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
           ${escapeHtml(d.justificatif.split('/').pop())}
         </a>`
      : '<span style="color:var(--text-light);font-size:12px;">—</span>';

    tr.innerHTML = `
      <td>
        <div class="org-cell">
          <div class="org-logo">🏢</div>
          <div class="org-nom">${escapeHtml(d.nom_organisation)}</div>
        </div>
      </td>
      <td class="email-cell">${escapeHtml(d.email_connexion || '—')}</td>
      <td class="tel-cell">${escapeHtml(d.telephone || '—')}</td>
      <td class="date-cell">${formatDate(d.date_inscription)}</td>
      <td>${justificatifHtml}</td>
      <td>
        <div class="actions-cell">
          <button class="btn-accepter" onclick="openModal(${d.id}, 'accepter', '${escapeHtml(d.nom_organisation).replace(/'/g,"\\'")}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Accepter
          </button>
          <button class="btn-refuser" onclick="openModal(${d.id}, 'refuser', '${escapeHtml(d.nom_organisation).replace(/'/g,"\\'")}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Refuser
          </button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  });
  renderPagination(data.length, page);
}

function renderPagination(total, page) {
  const totalPages = Math.ceil(total / ITEMS_PER_PAGE);
  const wrap = document.getElementById('paginationWrap');
  wrap.innerHTML = '';
  if (totalPages <= 1) return;
  const prev = document.createElement('button');
  prev.className = 'page-btn'; prev.disabled = page === 1;
  prev.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Précédent`;
  prev.onclick = () => goToPage(page - 1);
  wrap.appendChild(prev);
  const pages = getPageNumbers(page, totalPages);
  pages.forEach(p => {
    if (p === '...') {
      const s = document.createElement('span'); s.className = 'page-dots'; s.textContent = '…';
      wrap.appendChild(s);
    } else {
      const b = document.createElement('button');
      b.className = 'page-btn page-num' + (p === page ? ' active' : '');
      b.textContent = p;
      b.onclick = () => goToPage(p);
      wrap.appendChild(b);
    }
  });
  const next = document.createElement('button');
  next.className = 'page-btn'; next.disabled = page === totalPages;
  next.innerHTML = `Suivant <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>`;
  next.onclick = () => goToPage(page + 1);
  wrap.appendChild(next);
}

function getPageNumbers(current, total) {
  if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
  if (current <= 3) return [1,2,3,4,5,'...',total];
  if (current >= total - 2) return [1,'...',total-4,total-3,total-2,total-1,total];
  return [1,'...',current-1,current,current+1,'...',total];
}

function goToPage(p) { currentPage = p; renderTable(filtered, currentPage); }

document.getElementById('searchInput').addEventListener('input', function() {
  const term = this.value.toLowerCase();
  filtered = allDemandes.filter(d =>
    (d.nom_organisation || '').toLowerCase().includes(term) ||
    (d.email_connexion || '').toLowerCase().includes(term) ||
    (d.telephone || '').toLowerCase().includes(term)
  );
  currentPage = 1;
  renderTable(filtered, currentPage);
});

function openModal(id, action, nom) {
  pendingAction = { id, action, nom };
  const icon = document.getElementById('modalIcon');
  const iconSvg = document.getElementById('modalIconSvg');
  const title = document.getElementById('modalTitle');
  const text  = document.getElementById('modalText');
  const btn   = document.getElementById('btnConfirm');
  if (action === 'accepter') {
    icon.className = 'modal-icon success';
    iconSvg.setAttribute('stroke', '#166534');
    iconSvg.innerHTML = '<polyline points="20 6 9 17 4 12"/>';
    title.textContent = 'Accepter la demande';
    text.textContent  = `Vous êtes sur le point d'accepter l'organisation "${nom}". Elle pourra accéder à la plateforme.`;
    btn.textContent   = "Confirmer l'acceptation";
    btn.className     = 'btn-confirm-accept';
  } else {
    icon.className = 'modal-icon danger';
    iconSvg.setAttribute('stroke', '#991b1b');
    iconSvg.innerHTML = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
    title.textContent = 'Refuser la demande';
    text.textContent  = `Vous êtes sur le point de refuser la demande de "${nom}". Cette action est irréversible.`;
    btn.textContent   = 'Confirmer le refus';
    btn.className     = 'btn-confirm-refuse';
  }
  document.getElementById('modalOverlay').classList.add('show');
}

function closeModal() { document.getElementById('modalOverlay').classList.remove('show'); pendingAction = null; }

function confirmAction() {
  if (!pendingAction) return;
  closeModal();
  const form = document.createElement('form');
  form.method = 'POST'; form.action = 'demandes.php';
  form.innerHTML = `<input type="hidden" name="action" value="${pendingAction.action}"><input type="hidden" name="org_id" value="${pendingAction.id}">`;
  document.body.appendChild(form);
  showToast(
    pendingAction.action === 'accepter'
      ? `✓ "${pendingAction.nom}" a été acceptée`
      : `✗ "${pendingAction.nom}" a été refusée`,
    pendingAction.action === 'accepter' ? 'success' : 'error'
  );
  setTimeout(() => form.submit(), 900);
}

function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = 'toast ' + type + ' show';
  setTimeout(() => t.classList.remove('show'), 3000);
}

function toggleDropdown() { document.getElementById('profileDropdown').classList.toggle('active'); }
document.addEventListener('click', e => {
  const d = document.getElementById('profileDropdown');
  if (d && !d.contains(e.target)) d.classList.remove('active');
});
document.getElementById('modalOverlay').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
document.querySelectorAll('.nav-item[data-page]').forEach(item => {
  item.addEventListener('click', () => {
    const page = item.getAttribute('data-page');
    if      (page === 'dashboard')         window.location.href = 'dashboard.php';
    else if (page === 'demandes')          window.location.href = 'demandes.php';
    else if (page === 'organisations-nav') window.location.href = 'organisations.php';
    else if (page === 'contributeurs')     window.location.href = 'contributeurs.php';
    else if (page === 'deconnexion')       window.location.href = '../logout.php';
  });
});

renderTable(filtered, currentPage);
</script>
</body>
</html>