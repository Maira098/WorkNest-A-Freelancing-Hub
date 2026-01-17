<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Only clients can view this page
if ($user_role !== 'client') {
    header("Location: dashboard.php");
    exit;
}

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

// Get client's jobs with proposals
$jobs_stmt = $pdo->prepare('SELECT j.*, 
                                   COUNT(DISTINCT p.id) as total_proposals,
                                   COUNT(DISTINCT CASE WHEN p.status = "pending" THEN p.id END) as pending_proposals,
                                   COUNT(DISTINCT CASE WHEN p.status = "accepted" THEN p.id END) as accepted_proposals
                            FROM jobs j
                            LEFT JOIN proposals p ON j.id = p.job_id
                            WHERE j.client_id = ?
                            GROUP BY j.id
                            ORDER BY j.created_at DESC');
$jobs_stmt->execute([$user_id]);
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Proposals - WorkNest</title>
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
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
      flex-wrap: wrap;
      gap: 20px;
    }
    .page-header h1 {
      font-size: 2rem;
      font-weight: 700;
    }
    .btn-post-job {
      padding: 12px 25px;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s;
    }
    .btn-post-job:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(19,211,240,0.3);
      color: white;
    }

    /* Job Cards */
    .jobs-grid {
      display: grid;
      gap: 25px;
    }
    .job-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 25px;
      transition: all 0.3s;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .job-card { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .job-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(19,211,240,0.2);
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .job-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 15px;
      gap: 20px;
    }
    .job-title {
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .job-status {
      padding: 6px 15px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      white-space: nowrap;
    }
    .job-status.open {
      background: rgba(46,213,115,0.2);
      color: #2ed573;
    }
    .job-status.in_progress {
      background: rgba(19,211,240,0.2);
      color: var(--accent);
    }
    .job-status.completed {
      background: rgba(123,97,255,0.2);
      color: #7b61ff;
    }
    .job-meta {
      display: flex;
      gap: 25px;
      flex-wrap: wrap;
      font-size: 0.9rem;
      opacity: 0.8;
      margin-bottom: 20px;
    }
    .job-meta-item {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .job-meta-item i {
      color: var(--accent);
    }

    /* Proposals Section */
    .proposals-section {
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }
    .proposals-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    .proposals-count {
      font-weight: 700;
      font-size: 1.1rem;
    }
    .proposals-count .number {
      color: var(--accent);
    }
    .btn-view-proposals {
      padding: 8px 20px;
      background: var(--accent);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .btn-view-proposals:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(19,211,240,0.4);
      color: white;
    }

    /* Proposal Preview Cards */
    .proposal-preview {
      display: grid;
      gap: 12px;
      margin-top: 15px;
    }
    .proposal-item {
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 15px;
      transition: all 0.3s;
    }
    [data-theme="light"] .proposal-item { background: rgba(0,0,0,0.02); }
    .proposal-item:hover {
      background: rgba(19,211,240,0.05);
      border-color: var(--accent);
    }
    .freelancer-info {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
    }
    .freelancer-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: linear-gradient(135deg, #7b61ff, #ba61ff);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .freelancer-details {
      flex: 1;
    }
    .freelancer-name {
      font-weight: 600;
      margin-bottom: 3px;
    }
    .proposal-amount {
      font-size: 0.85rem;
      opacity: 0.8;
    }
    .proposal-amount .amount {
      color: var(--accent);
      font-weight: 700;
    }
    .proposal-status {
      padding: 5px 12px;
      border-radius: 15px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    .proposal-status.pending {
      background: rgba(255,193,7,0.2);
      color: #ffc107;
    }
    .proposal-status.accepted {
      background: rgba(46,213,115,0.2);
      color: #2ed573;
    }
    .proposal-status.rejected {
      background: rgba(255,71,87,0.2);
      color: #ff4757;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
    }
    .empty-state i {
      font-size: 4rem;
      color: var(--accent);
      margin-bottom: 20px;
      opacity: 0.3;
    }
    .empty-state h3 {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }
    .empty-state p {
      opacity: 0.7;
      margin-bottom: 30px;
    }

    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
      .page-header { flex-direction: column; align-items: flex-start; }
      .job-header { flex-direction: column; }
      .proposal-item { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse.php"><i class="fas fa-users"></i> Browse Freelancers</a></li>
      <li><a href="post-job.php"><i class="fas fa-plus-circle"></i> Post a Job</a></li>
      <li><a href="proposals.php" class="active"><i class="fas fa-inbox"></i> Proposals</a></li>
      <li><a href="messages-client.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="spending.php"><i class="fas fa-wallet"></i> Spending</a></li>
      <li><a href="reviews-client.php"><i class="fas fa-star"></i> Reviews</a></li>
      <li><a href="settings-client.php"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>
    <div class="sidebar-footer">
      <a href="profile-client.php" class="user-profile">
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
      <div>
        <h1>Proposals 📬</h1>
        <p style="opacity: 0.7; margin-top: 5px;">Review proposals from freelancers on your jobs</p>
      </div>
      <div style="display: flex; align-items: center; gap: 15px;">
        <button id="themeToggle" style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text); transition: all 0.3s;">
          <i class="fas fa-sun"></i>
        </button>
        <a href="post-job.php" class="btn-post-job">
          <i class="fas fa-plus"></i>
          Post New Job
        </a>
      </div>
    </div>

    <!-- Jobs with Proposals -->
    <div class="jobs-grid">
      <?php if (!empty($jobs)): ?>
        <?php foreach ($jobs as $job): ?>
          <?php
          // Get proposals for this job
          $proposals_stmt = $pdo->prepare('SELECT p.*, u.email as freelancer_email, prof.display_name as freelancer_name,
                                                  AVG(r.rating) as avg_rating
                                           FROM proposals p
                                           INNER JOIN users u ON p.freelancer_id = u.id
                                           LEFT JOIN profiles prof ON u.id = prof.user_id
                                           LEFT JOIN reviews r ON u.id = r.reviewee_id
                                           WHERE p.job_id = ?
                                           GROUP BY p.id
                                           ORDER BY p.created_at DESC
                                           LIMIT 3');
          $proposals_stmt->execute([$job['id']]);
          $proposals = $proposals_stmt->fetchAll(PDO::FETCH_ASSOC);
          ?>
          
          <div class="job-card">
            <div class="job-header">
              <div style="flex: 1;">
                <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
                <div class="job-meta">
                  <div class="job-meta-item">
                    <i class="fas fa-tag"></i>
                    <span><?= htmlspecialchars($job['category'] ?: 'Uncategorized') ?></span>
                  </div>
                  <div class="job-meta-item">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Budget: $<?= number_format($job['budget'] ?? 0) ?></span>
                  </div>
                  <div class="job-meta-item">
                    <i class="fas fa-calendar"></i>
                    <span>Posted: <?= date('M d, Y', strtotime($job['created_at'])) ?></span>
                  </div>
                </div>
              </div>
              <span class="job-status <?= $job['status'] ?>">
                <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
              </span>
            </div>

            <div class="proposals-section">
              <div class="proposals-header">
                <div class="proposals-count">
                  <span class="number"><?= $job['total_proposals'] ?></span> Proposals
                  <?php if ($job['pending_proposals'] > 0): ?>
                    <span style="font-size: 0.9rem; opacity: 0.7;">
                      (<?= $job['pending_proposals'] ?> pending)
                    </span>
                  <?php endif; ?>
                </div>
                <?php if ($job['total_proposals'] > 0): ?>
                  <a href="job-details.php?id=<?= $job['id'] ?>" class="btn-view-proposals">
                    <i class="fas fa-eye"></i> View All
                  </a>
                <?php endif; ?>
              </div>

              <?php if (!empty($proposals)): ?>
                <div class="proposal-preview">
                  <?php foreach ($proposals as $proposal): 
                    $f_name = $proposal['freelancer_name'] ?: explode('@', $proposal['freelancer_email'])[0];
                    $f_initials = strtoupper(substr($f_name, 0, 2));
                    $avg_rating = $proposal['avg_rating'] ? round($proposal['avg_rating'], 1) : 0;
                  ?>
                    <div class="proposal-item">
                      <div class="freelancer-info">
                        <div class="freelancer-avatar"><?= htmlspecialchars($f_initials) ?></div>
                        <div class="freelancer-details">
                          <div class="freelancer-name">
                            <?= htmlspecialchars($f_name) ?>
                            <?php if ($avg_rating > 0): ?>
                              <span style="color: #ffa500; font-size: 0.85rem; margin-left: 5px;">
                                <i class="fas fa-star"></i> <?= $avg_rating ?>
                              </span>
                            <?php endif; ?>
                          </div>
                          <div class="proposal-amount">
                            Bid: <span class="amount">$<?= number_format($proposal['amount'] ?? 0) ?></span>
                            <?php if (isset($proposal['delivery_time']) && $proposal['delivery_time']): ?>
                              • <?= $proposal['delivery_time'] ?> days
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                      <span class="proposal-status <?= $proposal['status'] ?>">
                        <?= ucfirst($proposal['status']) ?>
                      </span>
                    </div>
                  <?php endforeach; ?>
                </div>
                
                <?php if ($job['total_proposals'] > 3): ?>
                  <div style="text-align: center; margin-top: 15px; opacity: 0.7; font-size: 0.9rem;">
                    + <?= $job['total_proposals'] - 3 ?> more proposals
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <div style="text-align: center; padding: 30px; opacity: 0.6;">
                  <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i>
                  <p>No proposals yet</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-briefcase"></i>
          <h3>No Jobs Posted Yet</h3>
          <p>Post your first job to start receiving proposals from talented freelancers</p>
          <a href="post-job.php" class="btn-post-job">
            <i class="fas fa-plus"></i>
            Post Your First Job
          </a>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script>
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = themeToggle.querySelector('i');
    const body = document.body;

    const savedTheme = localStorage.getItem('theme') || 'dark';
    body.setAttribute('data-theme', savedTheme);
    themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';

    themeToggle.addEventListener('click', () => {
      const current = body.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      body.setAttribute('data-theme', next);
      themeIcon.className = next === 'light' ? 'fas fa-moon' : 'fas fa-sun';
      localStorage.setItem('theme', next);
    });
  </script>
</body>
</html>