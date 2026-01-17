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

// Get earnings statistics
$total_earned_stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as total 
                                     FROM transactions 
                                     WHERE recipient_id = ? AND type = "payment" AND status = "completed"');
$total_earned_stmt->execute([$user_id]);
$total_earned = $total_earned_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$this_month_stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as total 
                                   FROM transactions 
                                   WHERE recipient_id = ? AND type = "payment" AND status = "completed"
                                   AND MONTH(created_at) = MONTH(CURRENT_DATE())
                                   AND YEAR(created_at) = YEAR(CURRENT_DATE())');
$this_month_stmt->execute([$user_id]);
$this_month = $this_month_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$completed_projects_stmt = $pdo->prepare('SELECT COUNT(*) as total
                                          FROM proposals
                                          WHERE freelancer_id = ? AND status = "accepted"');
$completed_projects_stmt->execute([$user_id]);
$completed_projects = $completed_projects_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent earnings/transactions
$transactions_stmt = $pdo->prepare('SELECT t.*, j.title as job_title,
                                           u.email as client_email, prof.display_name as client_name
                                    FROM transactions t
                                    LEFT JOIN jobs j ON t.job_id = j.id
                                    LEFT JOIN users u ON t.user_id = u.id
                                    LEFT JOIN profiles prof ON u.id = prof.user_id
                                    WHERE t.recipient_id = ? AND t.type = "payment"
                                    ORDER BY t.created_at DESC
                                    LIMIT 20');
$transactions_stmt->execute([$user_id]);
$transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Earnings - WorkNest</title>
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

    /* Sidebar */
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

    /* Main Content */
    .main-content {
      margin-left: 280px;
      padding: 30px;
      min-height: 100vh;
    }
    .page-header {
      margin-bottom: 40px;
    }
    .page-header h1 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    .page-header p {
      opacity: 0.7;
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }
    .stat-card {
      background: linear-gradient(135deg, var(--card), rgba(19,211,240,0.05));
      padding: 25px;
      border-radius: 15px;
      border: 1px solid var(--border);
      transition: all 0.3s;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .stat-card { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(19,211,240,0.2);
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 15px;
    }
    .stat-icon.green {
      background: rgba(46,213,115,0.2);
      color: #2ed573;
    }
    .stat-icon.blue {
      background: rgba(19,211,240,0.2);
      color: var(--accent);
    }
    .stat-icon.purple {
      background: rgba(123,97,255,0.2);
      color: #7b61ff;
    }
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    .stat-label {
      opacity: 0.7;
      font-size: 0.9rem;
    }

    /* Transactions Section */
    .section {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      animation: fadeInUp 0.8s ease;
    }
    [data-theme="light"] .section { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section-header {
      margin-bottom: 25px;
    }
    .section-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
    }

    /* Transaction Item */
    .transaction-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 12px;
      margin-bottom: 15px;
      transition: all 0.3s;
    }
    [data-theme="light"] .transaction-item { background: rgba(0,0,0,0.02); }
    .transaction-item:hover {
      transform: translateX(5px);
      border-color: var(--accent);
    }
    .transaction-icon {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: rgba(46,213,115,0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #2ed573;
      font-size: 1.3rem;
    }
    .transaction-info {
      flex: 1;
      margin-left: 20px;
    }
    .transaction-title {
      font-weight: 600;
      margin-bottom: 5px;
    }
    .transaction-meta {
      font-size: 0.85rem;
      opacity: 0.7;
    }
    .transaction-amount {
      font-size: 1.3rem;
      font-weight: 700;
      color: #2ed573;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      opacity: 0.6;
    }
    .empty-state i {
      font-size: 4rem;
      margin-bottom: 20px;
      opacity: 0.3;
    }

    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse.php"><i class="fas fa-briefcase"></i> Browse Projects</a></li>
      <li><a href="my-projects.php"><i class="fas fa-folder"></i> My Projects</a></li>
      <li><a href="messages-freelancer.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="earnings.php" class="active"><i class="fas fa-wallet"></i> Earnings</a></li>
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

  <!-- Main Content -->
  <main class="main-content">
    <div class="page-header">
      <h1>Earnings Overview 💰</h1>
      <p>Track your income from completed projects</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-value">$<?= number_format($total_earned, 2) ?></div>
        <div class="stat-label">Total Earned</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-value">$<?= number_format($this_month, 2) ?></div>
        <div class="stat-label">This Month</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon purple">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value"><?= $completed_projects ?></div>
        <div class="stat-label">Completed Projects</div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="section">
      <div class="section-header">
        <h2><i class="fas fa-receipt"></i> Recent Earnings</h2>
      </div>

      <?php if (!empty($transactions)): ?>
        <?php foreach ($transactions as $transaction): 
          $client_name = $transaction['client_name'] ?: ($transaction['client_email'] ? explode('@', $transaction['client_email'])[0] : 'Unknown');
        ?>
          <div class="transaction-item">
            <div class="transaction-icon">
              <i class="fas fa-arrow-down"></i>
            </div>
            <div class="transaction-info">
              <div class="transaction-title"><?= htmlspecialchars($transaction['job_title'] ?: 'Payment Received') ?></div>
              <div class="transaction-meta">
                From: <?= htmlspecialchars($client_name) ?> • 
                <?= date('M d, Y - g:i A', strtotime($transaction['created_at'])) ?>
              </div>
            </div>
            <div class="transaction-amount">+$<?= number_format($transaction['amount'], 2) ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-wallet"></i>
          <h3>No Earnings Yet</h3>
          <p>Complete projects to start earning</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>