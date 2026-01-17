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

// Get review statistics
$avg_rating_stmt = $pdo->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
                                   FROM reviews
                                   WHERE reviewee_id = ?');
$avg_rating_stmt->execute([$user_id]);
$stats = $avg_rating_stmt->fetch(PDO::FETCH_ASSOC);
$avg_rating = $stats['avg_rating'] ? round($stats['avg_rating'], 1) : 0;
$total_reviews = $stats['total_reviews'];

// Get rating breakdown
$rating_breakdown_stmt = $pdo->prepare('SELECT rating, COUNT(*) as count
                                        FROM reviews
                                        WHERE reviewee_id = ?
                                        GROUP BY rating
                                        ORDER BY rating DESC');
$rating_breakdown_stmt->execute([$user_id]);
$rating_breakdown = $rating_breakdown_stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to array for easy access
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($rating_breakdown as $row) {
    $rating_counts[$row['rating']] = $row['count'];
}

// Get all reviews
$reviews_stmt = $pdo->prepare('SELECT r.*, j.title as job_title,
                                       u.email as client_email, p.display_name as client_name
                                FROM reviews r
                                INNER JOIN users u ON r.reviewer_id = u.id
                                LEFT JOIN profiles p ON u.id = p.user_id
                                LEFT JOIN jobs j ON r.job_id = j.id
                                WHERE r.reviewee_id = ?
                                ORDER BY r.created_at DESC');
$reviews_stmt->execute([$user_id]);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reviews - WorkNest</title>
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
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }
    .page-header h1 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    .page-header p {
      opacity: 0.7;
    }

    /* Stats Section */
    .stats-section {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 30px;
      margin-bottom: 40px;
    }
    .rating-overview {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      text-align: center;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .rating-overview { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .rating-number {
      font-size: 4rem;
      font-weight: 700;
      color: var(--accent);
      line-height: 1;
      margin-bottom: 10px;
    }
    .rating-stars {
      color: #ffa500;
      font-size: 1.5rem;
      margin-bottom: 10px;
    }
    .star-filled {
      color: #ffa500 !important;
    }
    .star-empty {
      color: rgba(255,165,0,0.25);
    }
    [data-theme="light"] .star-empty {
      color: rgba(255,165,0,0.35);
    }
    .rating-count {
      opacity: 0.7;
    }
    .rating-breakdown {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      animation: fadeInUp 0.7s ease;
    }
    [data-theme="light"] .rating-breakdown { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .rating-breakdown h3 {
      margin-bottom: 20px;
    }
    .rating-bar {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 15px;
    }
    .rating-label {
      display: flex;
      align-items: center;
      gap: 5px;
      min-width: 80px;
      color: #ffa500;
    }
    .rating-progress {
      flex: 1;
      height: 8px;
      background: rgba(255,165,0,0.15);
      border-radius: 10px;
      overflow: hidden;
    }
    [data-theme="light"] .rating-progress {
      background: rgba(0,0,0,0.1);
    }
    .rating-progress-fill {
      height: 100%;
      background: linear-gradient(135deg, #ffa500, #ff8c00);
      border-radius: 10px;
      transition: width 0.3s;
    }
    }
    .rating-bar-count {
      min-width: 40px;
      text-align: right;
      opacity: 0.7;
    }

    /* Section */
    .section {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
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

    /* Review Item */
    .review-item {
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 25px;
      margin-bottom: 20px;
      transition: all 0.3s;
    }
    [data-theme="light"] .review-item { background: rgba(0,0,0,0.02); }
    .review-item:hover {
      transform: translateX(5px);
      border-color: var(--accent);
    }
    .review-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .review-client {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .review-client-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #7b61ff, #ba61ff);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.2rem;
    }
    .review-client-name {
      font-weight: 700;
      font-size: 1.1rem;
    }
    .review-stars {
      color: #ffa500;
      font-size: 1.2rem;
    }
    .review-job {
      font-size: 0.9rem;
      opacity: 0.7;
      margin-bottom: 15px;
      padding-left: 65px;
    }
    .review-comment {
      line-height: 1.7;
      margin-bottom: 15px;
      font-size: 1.05rem;
    }
    .review-date {
      font-size: 0.85rem;
      opacity: 0.6;
      padding-left: 65px;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
      opacity: 0.6;
    }
    .empty-state i {
      font-size: 5rem;
      margin-bottom: 25px;
      opacity: 0.3;
      color: var(--accent);
    }
    .empty-state h3 {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }
    .review-stars {
      color: #ffa500;
      font-size: 1.2rem;
    }

    @media (max-width: 968px) {
      .stats-section { grid-template-columns: 1fr; }
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
      <li><a href="earnings.php"><i class="fas fa-wallet"></i> Earnings</a></li>
      <li><a href="reviews-freelancer.php" class="active"><i class="fas fa-star"></i> Reviews</a></li>
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
      <div>
        <h1>My Reviews ⭐</h1>
        <p>See what clients are saying about your work</p>
      </div>
      <button id="themeToggle" style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text); transition: all 0.3s;">
        <i class="fas fa-sun"></i>
      </button>
    </div>

    <!-- Rating Stats -->
    <div class="stats-section">
      <div class="rating-overview">
        <div class="rating-number"><?= $avg_rating ?></div>
        <div class="rating-stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="fas fa-star <?= $i <= round($avg_rating) ? 'star-filled' : 'star-empty' ?>"></i>
          <?php endfor; ?>
        </div>
        <div class="rating-count"><?= $total_reviews ?> reviews</div>
      </div>

      <div class="rating-breakdown">
        <h3>Rating Breakdown</h3>
        <?php for ($rating = 5; $rating >= 1; $rating--): ?>
          <?php 
            $count = $rating_counts[$rating];
            $percentage = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
          ?>
          <div class="rating-bar">
            <div class="rating-label">
              <?= $rating ?> <i class="fas fa-star"></i>
            </div>
            <div class="rating-progress">
              <div class="rating-progress-fill" style="width: <?= $percentage ?>%"></div>
            </div>
            <div class="rating-bar-count"><?= $count ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- All Reviews -->
    <div class="section">
      <div class="section-header">
        <h2><i class="fas fa-comments"></i> All Reviews (<?= count($reviews) ?>)</h2>
      </div>

      <?php if (!empty($reviews)): ?>
        <?php foreach ($reviews as $review): 
          $client_name = $review['client_name'] ?: explode('@', $review['client_email'])[0];
          $client_initials = strtoupper(substr($client_name, 0, 2));
        ?>
          <div class="review-item">
            <div class="review-header">
              <div class="review-client">
                <div class="review-client-avatar"><?= htmlspecialchars($client_initials) ?></div>
                <div class="review-client-name"><?= htmlspecialchars($client_name) ?></div>
              </div>
              <div class="review-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="fas fa-star <?= $i <= $review['rating'] ? 'star-filled' : 'star-empty' ?>"></i>
                <?php endfor; ?>
              </div>
            </div>
            <div class="review-job">Project: <?= htmlspecialchars($review['job_title']) ?></div>
            <div class="review-comment">"<?= htmlspecialchars($review['comment']) ?>"</div>
            <div class="review-date">
              <i class="far fa-clock"></i> <?= date('M d, Y', strtotime($review['created_at'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-star"></i>
          <h3>No Reviews Yet</h3>
          <p>Complete projects to start receiving reviews from clients</p>
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