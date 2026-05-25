<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../connexion.php');
    exit();
}

// ========== SUPPRESSION ==========
if (isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM organisations WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    header('Location: organisations.php');
    exit();
}

// ========== RÉCUPÉRATION DES ORGANISATIONS VALIDÉES ==========
$sql = "SELECT * FROM organisations WHERE statut = 'valide' ORDER BY date_inscription DESC";
$result = mysqli_query($conn, $sql);
$toutes_organisations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $toutes_organisations[] = $row;
}
$total_organisations = count($toutes_organisations);

$current_page = basename($_SERVER['PHP_SELF']);

function getFirstLetter($str) {
    if (empty($str)) return '?';
    $str = trim($str);
    $firstChar = mb_substr($str, 0, 1, 'UTF-8');
    return $firstChar;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Gestion des organisations</title>
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
    --accent-blue: #9FE7F5;
    --accent-gold: #F7AD19;
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
  .sidebar-bottom .nav-item svg { stroke: white; color: white; }

  /* ===== MAIN ===== */
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

  /* ===== TOPBAR ===== */
  .topbar { background: transparent; padding: 16px 32px 16px calc(32px + 15px); display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 5; }

  .search-bar { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; flex: 1; font-size: 13px; color: var(--text-secondary); box-shadow: var(--shadow); }
  .search-bar input { border: none; outline: none; background: transparent; width: 100%; font-family: inherit; font-size: 13px; }

  .right-group { display: flex; align-items: center; gap: 12px; margin-left: auto; }

  .total-box { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 18px; font-size: 13px; font-weight: 500; color: var(--text-secondary); box-shadow: var(--shadow); white-space: nowrap; }
  .total-box strong { color: var(--text-primary); font-weight: 700; }

  .date-picker { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; font-size: 13px; font-weight: 500; cursor: pointer; color: var(--text-primary); box-shadow: var(--shadow); white-space: nowrap; }

  .profile-dropdown { position: relative; }
  .user-info { display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 6px 14px 6px 6px; box-shadow: var(--shadow); }
  .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent-teal); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; overflow: hidden; }
  .user-name { font-weight: 600; font-size: 13px; white-space: nowrap; }
  .dropdown-icon { font-size: 12px; transition: transform 0.2s; display: inline-flex; align-items: center; color: #8b8fa8; }
  .dropdown-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 200px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
  .profile-dropdown.active .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
  .profile-dropdown.active .dropdown-icon { transform: rotate(180deg); }
  .dropdown-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }

  /* ===== CONTENT ===== */
  .content { padding: 20px 32px 28px calc(32px + 15px); display: flex; flex-direction: column; gap: 20px; }

  .page-title-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 24px; font-size: 20px; font-weight: 700; color: var(--text-primary); box-shadow: var(--shadow); font-family: 'Plus Jakarta Sans', sans-serif; }

  /* ===== CARD / TABLE ===== */
  .card { background: var(--card-bg); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); width: 100%; }

  .org-table { width: 100%; border-collapse: collapse; margin-top: 4px; border-radius: 12px; overflow: hidden; }
  .org-table thead tr { background: linear-gradient(90deg, #F47B20 0%, #E06A10 100%); }
  .org-table th { text-align: left; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.9); text-transform: uppercase; letter-spacing: 0.6px; padding: 12px 10px; }
  .org-table td { padding: 13px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 13px; color: var(--text-primary); }
  .org-table tr:last-child td { border-bottom: none; }

  .org-cell { display: flex; align-items: center; gap: 10px; }
  .org-logo { width: 34px; height: 34px; border-radius: 10px; background: #E1F7F6; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: var(--accent-teal); flex-shrink: 0; overflow: hidden; }
  .org-logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
  .org-logo span { font-size: 14px; font-weight: 700; color: var(--accent-teal); text-transform: uppercase; }

  .btn-icon { display: inline-flex; align-items: center; gap: 6px; background: rgba(0,0,0,0.04); color: var(--text-secondary); border: none; border-radius: 8px; padding: 7px 12px; font-size: 12px; font-weight: 500; cursor: pointer; font-family: inherit; transition: all 0.18s; text-decoration: none; }
  .btn-icon:hover { background: rgba(0,0,0,0.08); }

  .btn-delete { display: inline-flex; align-items: center; gap: 6px; background: rgba(239,68,68,0.08); color: var(--red); border: none; border-radius: 8px; padding: 7px 12px; font-size: 12px; font-weight: 500; cursor: pointer; font-family: inherit; transition: all 0.18s; }
  .btn-delete:hover { background: var(--red); color: #fff; }

  .btn-view { display: inline-flex; align-items: center; gap: 6px; background: rgba(28,184,178,0.08); color: var(--accent-teal); border: none; border-radius: 8px; padding: 7px 12px; font-size: 12px; font-weight: 500; cursor: pointer; font-family: inherit; transition: all 0.18s; text-decoration: none; }
  .btn-view:hover { background: var(--accent-teal); color: #fff; }

  .actions-cell { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

  /* ===== PAGINATION ===== */
  .pagination-wrap { display: flex; justify-content: flex-end; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border); }
  .pagination-controls { display: flex; align-items: center; gap: 6px; }
  .page-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: #fff; color: var(--text-secondary); font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.18s; font-family: inherit; }
  .page-btn:hover:not(:disabled):not(.active) { border-color: #F47B20; color: #F47B20; background: #fff4ee; }
  .page-btn.active { background: #F47B20; color: #fff; border-color: #F47B20; }
  .page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
  .page-dots { padding: 0 4px; color: var(--text-light); font-size: 13px; }

  /* ===== MODAL ===== */
  .modal-overlay { position: fixed; inset: 0; background: rgba(26,29,46,0.45); z-index: 200; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.2s; }
  .modal-overlay.open { opacity: 1; visibility: visible; }
  .modal { background: #fff; border-radius: var(--radius); padding: 32px; max-width: 380px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.15); text-align: center; transform: scale(0.95); transition: transform 0.2s; }
  .modal-overlay.open .modal { transform: scale(1); }
  .modal-icon { width: 54px; height: 54px; background: rgba(239,68,68,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; }
  .modal h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
  .modal p { font-size: 13px; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.6; }
  .modal-actions { display: flex; gap: 10px; justify-content: center; }
  .btn-cancel { flex: 1; padding: 11px; border: 1px solid var(--border); background: #fff; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; color: var(--text-secondary); transition: background 0.18s; }
  .btn-cancel:hover { background: var(--bg); }
  .btn-confirm { flex: 1; padding: 11px; background: var(--red); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.18s; }
  .btn-confirm:hover { background: #dc2626; }
</style>
</head>
<body>

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
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building-check" viewBox="0 0 16 16"><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.708l.547.548 1.17-1.951a.5.5 0 1 1 .858.514"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6.5a.5.5 0 0 1-1 0V1H3v14h3v-2.5a.5.5 0 0 1 .5-.5H8v4H3a1 1 0 0 1-1-1z"/><path d="M4.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/></svg></span>
      Demandes
    </div>
    <div class="nav-item <?php echo ($current_page == 'organisations.php') ? 'active' : ''; ?>" data-page="organisations-nav">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building" viewBox="0 0 16 16"><path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/></svg></span>
      Organisations
    </div>
    <div class="nav-item <?php echo ($current_page == 'contributeurs.php') ? 'active' : ''; ?>" data-page="contributeurs">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      Contributeurs
    </div>
  </nav>
  <div class="sidebar-bottom">
    <div class="nav-item" data-page="deconnexion">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      Déconnexion
    </div>
  </div>
</aside>

<div class="main">
  <header class="topbar">

    <div class="search-bar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Rechercher...">
    </div>

    <div class="right-group">

      <div class="total-box">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building" viewBox="0 0 16 16">
          <path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/>
          <path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/>
        </svg>
        Total organisations : <strong id="totalCount"><?php echo $total_organisations; ?></strong>
      </div>

      <div class="date-picker">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?php setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr'); echo strftime('%d %B %Y'); ?>
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
          <a href="mon_profil.php?from=organisations">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Mon profil
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

  <div class="content">
    <div class="page-title-card">Gestion des organisations</div>

    <div class="card">
      <table class="org-table">
        <thead>
          <tr>
            <th>Organisation</th>
            <th>Email</th>
            <th>Téléphone</th>
            <th>Date d'inscription</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="orgTableBody"></tbody>
      </table>
      <div class="pagination-wrap">
        <div class="pagination-controls" id="paginationControls"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal de confirmation suppression -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <div class="modal-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
    </div>
    <h3>Supprimer l'organisation</h3>
    <p>Êtes-vous sûr de vouloir supprimer cette organisation ? Cette action est irréversible.</p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">Annuler</button>
      <button class="btn-confirm" onclick="confirmDelete()">Supprimer</button>
    </div>
  </div>
</div>

<form id="deleteForm" method="POST" action="organisations.php" style="display:none;">
  <input type="hidden" name="delete_id" id="deleteIdInput">
</form>

<script>
const allOrganisations = <?php echo json_encode($toutes_organisations); ?>;
const ITEMS_PER_PAGE = 10;
let currentPage = 1;
let filteredOrganisations = [...allOrganisations];
let pendingDeleteId = null;

function formatDateFr(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}
function escapeHtml(str) {
  if (!str) return '—';
  return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);
}

function getFirstLetter(str) {
  if (!str || str === '') return '?';
  str = str.trim();
  var firstChar = str.substring(0, 1);
  return firstChar.toUpperCase();
}

function getLogoHtml(org) {
  if (org.logo && org.logo !== '') {
    var logoPath = org.logo;
    if (!logoPath.startsWith('uploads/') && !logoPath.startsWith('images/')) {
      logoPath = '../' + logoPath;
    } else {
      logoPath = '../' + logoPath;
    }
    return '<img src="' + logoPath + '" alt="Logo" style="width:34px;height:34px;border-radius:10px;object-fit:cover;">';
  } else {
    var letter = getFirstLetter(org.nom_organisation);
    return '<span>' + letter + '</span>';
  }
}

function renderOrganisations(data, page) {
  const tbody = document.getElementById('orgTableBody');
  if (!tbody) return;
  const start = (page - 1) * ITEMS_PER_PAGE;
  const slice = data.slice(start, start + ITEMS_PER_PAGE);
  tbody.innerHTML = '';
  if (slice.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-light);padding:30px 0;">Aucune organisation trouvée</td><\/tr>';
  } else {
    slice.forEach(org => {
      const logoHtml = getLogoHtml(org);
      const row = document.createElement('tr');
      row.innerHTML = `
        <td><div class="org-cell"><div class="org-logo">${logoHtml}</div>${escapeHtml(org.nom_organisation)}</div></td>
        <td>${escapeHtml(org.email_connexion)}</td>
        <td>${escapeHtml(org.telephone)}</td>
        <td>${formatDateFr(org.date_inscription)}</td>
        <td class="actions-cell">
          <a href="../ma_page.php?id=${org.id}" class="btn-view" target="_blank">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Voir
          </a>
          <button class="btn-delete" onclick="openModal(${org.id}, '${escapeHtml(org.nom_organisation).replace(/'/g, "\\'")}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            Supprimer
          </button>
        </td>
      `;
      tbody.appendChild(row);
    });
  }
  renderPagination(data.length, page);
}

function renderPagination(total, page) {
  const totalPages = Math.ceil(total / ITEMS_PER_PAGE);
  const ctrl = document.getElementById('paginationControls');
  ctrl.innerHTML = '';
  if (totalPages <= 1) return;

  const prev = document.createElement('button');
  prev.className = 'page-btn'; prev.textContent = '‹'; prev.disabled = page === 1;
  prev.onclick = () => goToPage(page - 1);
  ctrl.appendChild(prev);

  const pages = getPageNumbers(page, totalPages);
  pages.forEach(p => {
    if (p === '...') {
      const s = document.createElement('span');
      s.className = 'page-dots'; s.textContent = '…';
      ctrl.appendChild(s);
    } else {
      const b = document.createElement('button');
      b.className = 'page-btn' + (p === page ? ' active' : '');
      b.textContent = p;
      b.onclick = () => goToPage(p);
      ctrl.appendChild(b);
    }
  });

  const next = document.createElement('button');
  next.className = 'page-btn'; next.textContent = '›'; next.disabled = page === totalPages;
  next.onclick = () => goToPage(page + 1);
  ctrl.appendChild(next);
}

function getPageNumbers(current, total) {
  if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
  if (current <= 3) return [1,2,3,4,5,'...',total];
  if (current >= total - 2) return [1,'...',total-4,total-3,total-2,total-1,total];
  return [1,'...',current-1,current,current+1,'...',total];
}

function goToPage(page) {
  currentPage = page;
  renderOrganisations(filteredOrganisations, currentPage);
}

// Recherche
document.getElementById('searchInput').addEventListener('input', function(e) {
  const term = e.target.value.toLowerCase().trim();
  filteredOrganisations = allOrganisations.filter(org =>
    (org.nom_organisation || '').toLowerCase().includes(term) ||
    (org.email_connexion || '').toLowerCase().includes(term) ||
    (org.telephone || '').toLowerCase().includes(term)
  );
  document.getElementById('totalCount').textContent = filteredOrganisations.length;
  currentPage = 1;
  renderOrganisations(filteredOrganisations, currentPage);
});

// Modal suppression
function openModal(id, nom) {
  pendingDeleteId = id;
  const modal = document.getElementById('deleteModal');
  const p = modal.querySelector('p');
  p.textContent = `Êtes-vous sûr de vouloir supprimer l'organisation "${nom}" ? Cette action est irréversible.`;
  modal.classList.add('open');
}
function closeModal() {
  pendingDeleteId = null;
  document.getElementById('deleteModal').classList.remove('open');
}
function confirmDelete() {
  if (pendingDeleteId) {
    document.getElementById('deleteIdInput').value = pendingDeleteId;
    document.getElementById('deleteForm').submit();
  }
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Dropdown profil
function toggleDropdown() { document.getElementById('profileDropdown').classList.toggle('active'); }
document.addEventListener('click', e => {
  const d = document.getElementById('profileDropdown');
  if (d && !d.contains(e.target)) d.classList.remove('active');
});

// Navigation sidebar
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

// Init
renderOrganisations(filteredOrganisations, currentPage);
</script>
</body>
</html>