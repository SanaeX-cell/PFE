<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organisation') {
    header('Location: ../connexion.php');
    exit();
}

$org_id = $_SESSION['user_id'];

// Action supprimer
if (isset($_GET['supprimer'])) {
    $contrib_id = (int)$_GET['supprimer'];
    mysqli_query($conn, "DELETE FROM participations WHERE contributeur_id=$contrib_id AND post_id IN (SELECT id FROM posts WHERE organisation_id=$org_id)");
    header('Location: benevoles.php');
    exit();
}

// Récupérer les bénévoles inscrits pour les posts de cette organisation
$sql = "
  SELECT DISTINCT c.id, c.nom, c.prenom, c.email, c.telephone, p.statut as participation_statut,
         po.titre as post_titre, po.type_demande
  FROM contributeurs c
  JOIN participations p ON p.contributeur_id = c.id
  JOIN posts po ON po.id = p.post_id
  WHERE po.organisation_id = $org_id
  ORDER BY c.nom, c.prenom
";
$result = mysqli_query($conn, $sql);
$benevoles = [];
while($row = mysqli_fetch_assoc($result)) {
    $benevoles[] = $row;
}

// Total unique
$total_benevoles = count(array_unique(array_column($benevoles, 'id')));

// Récupérer les infos organisation
$sql_org = "SELECT * FROM organisations WHERE id = $org_id";
$result_org = mysqli_query($conn, $sql_org);
$org = mysqli_fetch_assoc($result_org);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Bénévoles inscrits</title>
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
  .search-bar { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; flex: 1; font-size: 13px; color: var(--text-secondary); box-shadow: var(--shadow); max-width: 380px; }
  .search-bar input { border: none; outline: none; background: transparent; width: 100%; font-family: inherit; font-size: 13px; }
  .total-badge { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; font-size: 13px; font-weight: 600; color: var(--text-primary); box-shadow: var(--shadow); white-space: nowrap; }
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
  .content { padding: 20px 32px 40px; }

  /* TABLE */
  .card { background: var(--card-bg); border-radius: var(--radius); padding: 28px; box-shadow: var(--shadow); }
  .card-title { font-size: 17px; font-weight: 700; margin-bottom: 22px; }
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  thead { background: var(--accent-orange); }
  thead th { padding: 13px 16px; text-align: left; font-size: 11px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 0.6px; }
  thead th:first-child { border-radius: 10px 0 0 10px; }
  thead th:last-child { border-radius: 0 10px 10px 0; }
  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: #fafafa; }
  tbody td { padding: 14px 16px; vertical-align: middle; font-size: 13px; }
  .volunteer-info { display: flex; align-items: center; gap: 10px; }
  .volunteer-avatar { width: 38px; height: 38px; border-radius: 10px; background: #E1F7F6; color: var(--accent-teal); font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .volunteer-name { font-weight: 600; }
  .volunteer-post { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }

  /* ACTION BUTTONS */
  .actions-cell { display: flex; align-items: center; gap: 6px; }
  .btn-action { display: inline-flex; align-items: center; gap: 5px; border: none; border-radius: 8px; padding: 7px 12px; font-size: 11px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; white-space: nowrap; text-decoration: none; }
  .btn-chat { background: #EFF6FF; color: #2563eb; }
  .btn-chat:hover { background: #dbeafe; }
  .btn-delete { background: #FEF2F2; color: #dc2626; }
  .btn-delete:hover { background: #fee2e2; }
  .btn-block { background: #FFF7ED; color: #c2410c; }
  .btn-block:hover { background: #ffedd5; }

  /* EMPTY */
  .empty-state { text-align: center; padding: 60px 20px; color: var(--text-secondary); }
  .empty-state svg { opacity: 0.25; margin-bottom: 14px; }
  .empty-state p { font-size: 14px; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="logo">
    <img src="../images/logo.png" alt="Logo" class="logo-image">
    <div class="logo-text"><span>Connect</span><span>Aid</span></div>
  </div>
  <nav>
    <a href="accueil.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
      Accueil
    </a>
    <a href="ma_page.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
      Ma page
    </a>
    <a href="creer_post.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      Créer un post
    </a>
    <a href="benevoles.php" class="nav-item active">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      Bénévoles inscrits
    </a>
    <a href="messages.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
      Messages
    </a>
    <a href="notifications.php" class="nav-item">
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
    <div class="search-bar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Rechercher un bénévole...">
    </div>
    <div class="total-badge">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Total bénévoles : <?php echo count($benevoles); ?>
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
    <div class="card">
      <div class="card-title">Gestion des bénévoles inscrits</div>
      <?php if(empty($benevoles)): ?>
        <div class="empty-state">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <p>Aucun bénévole inscrit pour le moment.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table id="benevoleTable">
            <thead>
              <tr>
                <th>Bénévole</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($benevoles as $b): ?>
              <tr>
                <td>
                  <div class="volunteer-info">
                    <div class="volunteer-avatar"><?php echo strtoupper(substr($b['nom'], 0, 1)); ?></div>
                    <div>
                      <div class="volunteer-name"><?php echo htmlspecialchars($b['nom'] . ' ' . $b['prenom']); ?></div>
                      <div class="volunteer-post">📌 <?php echo htmlspecialchars($b['post_titre']); ?></div>
                    </div>
                  </div>
                </td>
                <td><?php echo $b['telephone'] ? htmlspecialchars($b['telephone']) : '—'; ?></td>
                <td><?php echo htmlspecialchars($b['email']); ?></td>
                <td>
                  <div class="actions-cell">
                    <a href="messages.php?dest=<?php echo $b['id']; ?>" class="btn-action btn-chat">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                      Chat
                    </a>
                    <a href="?supprimer=<?php echo $b['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Retirer ce bénévole ?')">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                      Supprimer
                    </a>
                    <button class="btn-action btn-block" onclick="alert('Fonctionnalité de blocage à implémenter.')">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                      Bloquer
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.getElementById('searchInput')?.addEventListener('input', function() {
  const term = this.value.toLowerCase();
  document.querySelectorAll('#benevoleTable tbody tr').forEach(function(row) {
    row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
  });
});

document.addEventListener('click', function(e) {
  const area = document.getElementById('profileArea');
  if (area && !area.contains(e.target)) area.classList.remove('active');
});
</script>
</body>
</html>