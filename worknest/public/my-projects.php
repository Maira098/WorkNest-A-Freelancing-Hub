<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user info
$stmt = $pdo->prepare('SELECT u.email, u.role, p.display_name 
                       FROM users u 
                       LEFT JOIN profiles p ON u.id = p.user_id 
                       WHERE u.id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

$display_name = $user_data['display_name'] ?: explode('@', $user_data['email'])[0];
$name_parts = explode(' ', $display_name, 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : '';
$initials = strtoupper(substr($first_name, 0, 1) . ($last_name ? substr($last_name, 0, 1) : substr($first_name, 1, 1)));
$user_role_display = ucfirst($user_data['role']);

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get projects based on user role
if ($user_role === 'client') {
    // For clients: show jobs they posted
    $query = "SELECT j.*, 
                     (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) as proposal_count
              FROM jobs j
              WHERE j.client_id = ?";
    
    if ($filter !== 'all') {
        $query .= " AND j.status = ?";
    }
    $query .= " ORDER BY j.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    if ($filter !== 'all') {
        $stmt->execute([$user_id, $filter]);
    } else {
        $stmt->execute([$user_id]);
    }
} else {
    // For freelancers: show projects they've applied to or won
    $query = "SELECT j.*, p.status as proposal_status, p.bid_amount, p.created_at as applied_at,
                     (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) as proposal_count,
                     u.email as client_email, pr.display_name as client_name
              FROM proposals p
              INNER JOIN jobs j ON p.job_id = j.id
              LEFT JOIN users u ON j.client_id = u.id
              LEFT JOIN profiles pr ON u.id = pr.user_id
              WHERE p.freelancer_id = ?";
    
    if ($filter !== 'all') {
        $query .= " AND p.status = ?";
    }
    $query .= " ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    if ($filter !== 'all') {
        $stmt->execute([$user_id, $filter]);
    } else {
        $stmt->execute([$user_id]);
    }
}

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stats_query = $user_role === 'client' 
    ? "SELECT 
        COUNT(CASE WHEN status = 'open' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
       FROM jobs WHERE client_id = ?"
    : "SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
       FROM proposals WHERE freelancer_id = ?";

$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Projects - WorkNest</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #031121;
      --text: #ffffff;
      --card: #041d3a;
      --accent: #13d3f0;
      --sidebar: #020e1b;
      --border: rgba(255,255,255,0.1);
    }
    [data-theme="light"] {
      --bg: #f5f7fa;
      --text: #031121;
      --card: #ffffff;
      --accent: #1055c9;
      --sidebar: #ffffff;
      --border: rgba(0,0,0,0.1);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Inter', sans-serif;
      transition: all 0.3s ease;
    }
    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      width: 280px;
      height: 100vh;
      background-color: var(--sidebar);
      border-right: 1px solid var(--border);
      padding: 30px 20px;
      overflow-y: auto;
      transition: all 0.3s;
      z-index: 1000;
    }
    .sidebar-brand {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 40px;
      text-decoration: none;
      display: block;
    }
    .sidebar-menu { list-style: none; }
    .sidebar-menu li { margin-bottom: 10px; }
    .sidebar-menu a {
      display: flex;
      align-items: center;
      padding: 12px 15px;
      color: var(--text);
      text-decoration: none;
      border-radius: 10px;
      transition: all 0.3s;
      opacity: 0.7;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
      background-color: rgba(19,211,240,0.1);
      opacity: 1;
      color: var(--accent);
    }
    .sidebar-menu a i { margin-right: 12px; width: 20px; font-size: 1.1rem; }
    .sidebar-footer {
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }
    .user-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px;
      border-radius: 10px;
      transition: 0.3s;
      cursor: pointer;
      text-decoration: none;
      color: var(--text);
    }
    .user-profile:hover { background-color: rgba(19,211,240,0.1); }
    .user-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.2rem;
    }
    .user-info h4 { font-size: 0.95rem; margin-bottom: 2px; }
    .user-info p { font-size: 0.8rem; opacity: 0.7; margin: 0; }
    .logout-btn {
      display: block;
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      background: rgba(255,71,87,0.2);
      border: 1px solid #ff4757;
      color: #ff4757;
      border-radius: 8px;
      text-align: center;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s;
    }
    .logout-btn:hover {
      background: rgba(255,71,87,0.3);
      color: #ff4757;
    }
    .main-content {
      margin-left: 280px;
      padding: 30px;
      min-height: 100vh;
    }
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
      flex-wrap: wrap;
      gap: 20px;
    }
    .page-title h1 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    .page-title p {
      opacity: 0.7;
    }
    .theme-toggle-btn {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      background: var(--card);
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .theme-toggle-btn:hover {
      transform: scale(1.05);
      border-color: var(--accent);
    }
    .theme-toggle-btn i { font-size: 1.2rem; color: var(--accent); }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }
    .stat-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 25px;
      transition: all 0.3s;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .stat-card { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .stat-card:hover {
      transform: translateY(-5px);
      border-color: var(--accent);
    }
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(19,211,240,0.2), rgba(8,145,178,0.2));
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 15px;
    }
    .stat-icon i {
      font-size: 1.5rem;
      color: var(--accent);
    }
    .stat-label {
      font-size: 0.9rem;
      opacity: 0.7;
      margin-bottom: 8px;
    }
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--accent);
    }
    
    .filter-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }
    .filter-tab {
      padding: 10px 20px;
      background: var(--card);
      border: 2px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s;
      font-size: 0.95rem;
    }
    .filter-tab:hover {
      border-color: var(--accent);
      color: var(--accent);
    }
    .filter-tab.active {
      background: linear-gradient(135deg, var(--accent), #0891b2);
      border-color: var(--accent);
      color: white;
    }
    
    .projects-grid {
      display: grid;
      gap: 25px;
    }
    .project-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 25px;
      transition: all 0.3s;
      animation: fadeInUp 0.8s ease;
      cursor: pointer;
    }
    [data-theme="light"] .project-card { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .project-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(19,211,240,0.2);
      border-color: var(--accent);
    }
    .project-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 15px;
      gap: 20px;
    }
    .project-title {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .project-status {
      padding: 6px 15px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      white-space: nowrap;
    }
    .status-open, .status-pending {
      background: rgba(19,211,240,0.2);
      color: var(--accent);
      border: 1px solid rgba(19,211,240,0.3);
    }
    .status-in_progress, .status-accepted {
      background: rgba(255,165,2,0.2);
      color: #ffa502;
      border: 1px solid rgba(255,165,2,0.3);
    }
    .status-completed {
      background: rgba(46,213,115,0.2);
      color: #2ed573;
      border: 1px solid rgba(46,213,115,0.3);
    }
    .status-closed, .status-rejected {
      background: rgba(255,71,87,0.2);
      color: #ff4757;
      border: 1px solid rgba(255,71,87,0.3);
    }
    .project-description {
      opacity: 0.8;
      margin-bottom: 20px;
      line-height: 1.6;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .project-meta {
      display: flex;
      gap: 25px;
      flex-wrap: wrap;
      padding-top: 20px;
      border-top: 1px solid var(--border);
      font-size: 0.9rem;
    }
    .project-meta-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .project-meta-item i {
      color: var(--accent);
    }
    .project-budget {
      color: var(--accent);
      font-weight: 700;
      font-size: 1.1rem;
    }
    .empty-state {
      text-align: center;
      padding: 80px 20px;
    }
    .empty-state i {
      font-size: 5rem;
      color: var(--accent);
      opacity: 0.3;
      margin-bottom: 20px;
    }
    .empty-state h3 {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }
    .empty-state p {
      opacity: 0.7;
      margin-bottom: 30px;
    }
    .btn-primary {
      background: linear-gradient(135deg, #0ea5c9, #0891b2);
      color: white;
      border: none;
      padding: 12px 28px;
      border-radius: 10px;
      font-weight: 600;
      text-decoration: none;
      display: inline-block;
      transition: all 0.3s;
      font-size: 0.95rem;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(14, 165, 201, 0.3);
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(14, 165, 201, 0.4);
      color: white;
      background: linear-gradient(135deg, #0891b2, #0e7490);
    }
    .btn-primary i {
      margin-right: 8px;
      font-weight: 900;
    }
    
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
      .stats-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse-projects.php"><i class="fas fa-briefcase"></i> Browse Projects</a></li>
      <li><a href="my-projects.php" class="active"><i class="fas fa-folder"></i> My Projects</a></li>
      <li><a href="messages-freelancer.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="earnings.php"><i class="fas fa-wallet"></i> Earnings</a></li>
      <li><a href="reviews-freelancer.php"><i class="fas fa-star"></i> Reviews</a></li>
      <li><a href="settings-freelancer.php"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>
    <div class="sidebar-footer">
      <a href="profile-freelancer.php" class="user-profile">
        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="user-info">
          <h4><?= htmlspecialchars($display_name) ?></h4>
          <p><?= htmlspecialchars($user_role_display) ?></p>
        </div>
      </a>
      <a href="actions/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </aside>

  <main class="main-content">
    <div class="top-bar">
      <div class="page-title">
        <h1>My Projects 📁</h1>
        <p><?= $user_role === 'client' ? 'Manage your posted jobs' : 'Track your proposals and active projects' ?></p>
      </div>
      <div class="theme-toggle-btn" id="themeToggle">
        <i class="fas fa-sun"></i>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-label"><?= $user_role === 'client' ? 'Open Jobs' : 'Pending' ?></div>
        <div class="stat-value"><?= $stats['active'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-label">In Progress</div>
        <div class="stat-value"><?= $stats['in_progress'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-label">Completed</div>
        <div class="stat-value"><?= $stats['completed'] ?? 0 ?></div>
      </div>
    </div>

    <div class="filter-tabs">
      <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
      <a href="?filter=<?= $user_role === 'client' ? 'open' : 'pending' ?>" class="filter-tab <?= $filter === ($user_role === 'client' ? 'open' : 'pending') ? 'active' : '' ?>">
        <?= $user_role === 'client' ? 'Open' : 'Pending' ?>
      </a>
      <a href="?filter=<?= $user_role === 'client' ? 'in_progress' : 'accepted' ?>" class="filter-tab <?= $filter === ($user_role === 'client' ? 'in_progress' : 'accepted') ? 'active' : '' ?>">
        In Progress
      </a>
      <a href="?filter=completed" class="filter-tab <?= $filter === 'completed' ? 'active' : '' ?>">Completed</a>
    </div>

    <?php if (empty($projects)): ?>
      <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h3>No Projects Found</h3>
        <p><?= $user_role === 'client' ? "You haven't posted any jobs yet" : "You haven't applied to any projects yet" ?></p>
        <a href="<?= $user_role === 'client' ? 'post-job.php' : 'browse-projects.php' ?>" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
          <i class="fas fa-plus"></i> <?= $user_role === 'client' ? 'Post a Job' : 'Browse Projects' ?>
        </a>
      </div>
    <?php else: ?>
      <div class="projects-grid">
        <?php foreach ($projects as $project): 
          $status_display = $user_role === 'client' ? $project['status'] : $project['proposal_status'];
          $budget_display = '$' . number_format($project['budget_min']) . ' - $' . number_format($project['budget_max']);
          
          // Time posted
          $time_diff = time() - strtotime($project['created_at']);
          if ($time_diff < 86400) {
            $posted_text = floor($time_diff / 3600) . 'h ago';
          } else {
            $days = floor($time_diff / 86400);
            $posted_text = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
          }
        ?>
          <div class="project-card" onclick="window.location.href='job-details.php?id=<?= $project['id'] ?>'">
            <div class="project-header">
              <div>
                <h3 class="project-title"><?= htmlspecialchars($project['title']) ?></h3>
                <?php if ($user_role === 'freelancer' && !empty($project['client_name'])): ?>
                  <p style="opacity: 0.7; font-size: 0.9rem; margin-bottom: 10px;">
                    <i class="fas fa-user"></i> Client: <?= htmlspecialchars($project['client_name'] ?: explode('@', $project['client_email'])[0]) ?>
                  </p>
                <?php endif; ?>
              </div>
              <div class="project-status status-<?= htmlspecialchars($status_display) ?>">
                <?= ucfirst(str_replace('_', ' ', $status_display)) ?>
              </div>
            </div>
            
            <p class="project-description"><?= htmlspecialchars($project['description']) ?></p>
            
            <div class="project-meta">
              <div class="project-meta-item">
                <i class="fas fa-dollar-sign"></i>
                <span class="project-budget"><?= $budget_display ?></span>
              </div>
              <div class="project-meta-item">
                <i class="fas fa-file-alt"></i>
                <span><?= $project['proposal_count'] ?> proposals</span>
              </div>
              <div class="project-meta-item">
                <i class="fas fa-clock"></i>
                <span>Posted <?= $posted_text ?></span>
              </div>
              <?php if ($user_role === 'freelancer' && !empty($project['bid_amount'])): ?>
                <div class="project-meta-item">
                  <i class="fas fa-hand-holding-usd"></i>
                  <span>Your bid: $<?= number_format($project['bid_amount']) ?></span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <script>
    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    
    const savedTheme = localStorage.getItem('theme') || 'dark';
    html.setAttribute('data-theme', savedTheme);
    themeToggle.querySelector('i').className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

    themeToggle.addEventListener('click', () => {
      const current = html.getAttribute('data-theme');
      const newTheme = current === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
      themeToggle.querySelector('i').className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    });
  </script>
</body>
</html>