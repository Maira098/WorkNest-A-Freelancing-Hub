<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Get all freelancers with their stats
$freelancers_stmt = $pdo->prepare('SELECT u.id, u.email, u.created_at,
                                           p.display_name, p.bio, p.hourly_rate, p.location,
                                           COUNT(DISTINCT pr.id) as total_proposals,
                                           AVG(r.rating) as avg_rating,
                                           COUNT(DISTINCT r.id) as review_count
                                    FROM users u
                                    LEFT JOIN profiles p ON u.id = p.user_id
                                    LEFT JOIN proposals pr ON u.id = pr.freelancer_id
                                    LEFT JOIN reviews r ON u.id = r.reviewee_id
                                    WHERE u.role = "freelancer"
                                    GROUP BY u.id
                                    ORDER BY avg_rating DESC, review_count DESC');
$freelancers_stmt->execute();
$freelancers = $freelancers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique skills for filter
$skills_stmt = $pdo->query('SELECT DISTINCT name FROM skills ORDER BY name');
$all_skills = $skills_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Freelancers - WorkNest</title>
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
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }
    .page-header h1 { font-size: 2rem; font-weight: 700; margin-bottom: 5px; }
    .page-header p { opacity: 0.7; }
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

    /* Filter Bar */
    .filter-bar {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 30px;
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      align-items: center;
    }
    [data-theme="light"] .filter-bar { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .search-box {
      flex: 1;
      min-width: 250px;
      position: relative;
    }
    .search-box input {
      width: 100%;
      padding: 12px 15px 12px 45px;
      border: 2px solid var(--border);
      border-radius: 10px;
      background: rgba(255,255,255,0.05);
      color: var(--text);
      font-size: 0.95rem;
      transition: all 0.3s;
    }
    [data-theme="light"] .search-box input { background: #f8f9fa; }
    .search-box input:focus {
      outline: none;
      border-color: var(--accent);
    }
    .search-box i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--accent);
    }
    .filter-select {
      padding: 12px 15px;
      border: 2px solid var(--border);
      border-radius: 10px;
      background: rgba(255,255,255,0.05);
      color: var(--text);
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.3s;
    }
    [data-theme="light"] .filter-select { background: #f8f9fa; }
    .filter-select:focus {
      outline: none;
      border-color: var(--accent);
    }

    /* Freelancer Grid */
    .freelancer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 25px;
    }
    .freelancer-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 25px;
      transition: all 0.3s;
      cursor: pointer;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .freelancer-card { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .freelancer-card:hover {
      transform: translateY(-5px);
      border-color: var(--accent);
      box-shadow: 0 10px 30px rgba(19,211,240,0.2);
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .freelancer-header {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 15px;
    }
    .freelancer-avatar {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.8rem;
      flex-shrink: 0;
    }
    .freelancer-name {
      font-weight: 700;
      font-size: 1.2rem;
      margin-bottom: 5px;
    }
    .freelancer-rating {
      color: #ffa500;
      font-size: 0.95rem;
    }
    .freelancer-bio {
      font-size: 0.95rem;
      opacity: 0.8;
      margin-bottom: 15px;
      line-height: 1.6;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
      min-height: 4.5em;
    }
    .freelancer-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 15px;
      border-top: 1px solid var(--border);
      font-size: 0.9rem;
    }
    .freelancer-location {
      opacity: 0.7;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .freelancer-rate {
      color: var(--accent);
      font-weight: 700;
      font-size: 1.1rem;
    }
    .no-results {
      text-align: center;
      padding: 60px 20px;
      opacity: 0.6;
    }
    .no-results i {
      font-size: 4rem;
      margin-bottom: 20px;
      opacity: 0.3;
    }

    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
      .freelancer-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse-freelancer.php" class="active"><i class="fas fa-users"></i> Browse Freelancers</a></li>
      <li><a href="post-job.php"><i class="fas fa-plus-circle"></i> Post a Job</a></li>
      <li><a href="proposals.php" class="active"><i class="fas fa-inbox"></i> Proposals</a></li>
      <li><a href="messages-client.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="earnings.php"><i class="fas fa-wallet"></i> Spending</a></li>
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
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="page-header">
        <h1>Browse Freelancers 👥</h1>
        <p>Find the perfect talent for your projects</p>
      </div>
      <div class="theme-toggle-btn" id="themeToggle">
        <i class="fas fa-sun"></i>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="searchFreelancers" placeholder="Search by name or skill...">
      </div>
      <select class="filter-select" id="filterRating">
        <option value="">All Ratings</option>
        <option value="5">5 Stars</option>
        <option value="4">4+ Stars</option>
        <option value="3">3+ Stars</option>
      </select>
      <select class="filter-select" id="sortBy">
        <option value="rating">Top Rated</option>
        <option value="recent">Recently Joined</option>
        <option value="rate_low">Rate: Low to High</option>
        <option value="rate_high">Rate: High to Low</option>
      </select>
    </div>

    <!-- Freelancer Grid -->
    <div class="freelancer-grid" id="freelancerGrid">
      <?php if (!empty($freelancers)): ?>
        <?php foreach ($freelancers as $freelancer): 
          $f_name = $freelancer['display_name'] ?: explode('@', $freelancer['email'])[0];
          $f_initials = strtoupper(substr($f_name, 0, 2));
          $avg_rating = $freelancer['avg_rating'] ? round($freelancer['avg_rating'], 1) : 0;
          $review_count = $freelancer['review_count'];
          $member_since = date('M Y', strtotime($freelancer['created_at']));
        ?>
          <div class="freelancer-card" 
               data-name="<?= strtolower(htmlspecialchars($f_name)) ?>"
               data-rating="<?= $avg_rating ?>"
               data-rate="<?= $freelancer['hourly_rate'] ?: 0 ?>"
               data-joined="<?= strtotime($freelancer['created_at']) ?>"
               onclick="window.location.href='profile-freelancer.php?id=<?= $freelancer['id'] ?>'">
            <div class="freelancer-header">
              <div class="freelancer-avatar"><?= htmlspecialchars($f_initials) ?></div>
              <div style="flex: 1;">
                <div class="freelancer-name"><?= htmlspecialchars($f_name) ?></div>
                <div class="freelancer-rating">
                  <?php if ($avg_rating > 0): ?>
                    <i class="fas fa-star"></i> <?= $avg_rating ?> (<?= $review_count ?> reviews)
                  <?php else: ?>
                    <span style="opacity: 0.5;">No reviews yet</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <div class="freelancer-bio">
              <?= $freelancer['bio'] ? htmlspecialchars($freelancer['bio']) : 'Professional freelancer ready to help with your projects.' ?>
            </div>
            
            <div class="freelancer-meta">
              <div class="freelancer-location">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($freelancer['location'] ?: 'Remote') ?>
              </div>
              <div class="freelancer-rate">
                <?= $freelancer['hourly_rate'] ? '$' . number_format($freelancer['hourly_rate']) . '/hr' : 'Contact for rate' ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-results">
          <i class="fas fa-users-slash"></i>
          <h3>No Freelancers Found</h3>
          <p>Check back later for new talent</p>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script>
    // Theme Toggle
    const toggle = document.getElementById("themeToggle");
    const icon = toggle.querySelector("i");
    const body = document.body;

    const savedTheme = localStorage.getItem("theme") || "dark";
    body.setAttribute("data-theme", savedTheme);
    icon.className = savedTheme === "light" ? "fas fa-moon" : "fas fa-sun";

    toggle.addEventListener("click", () => {
      const current = body.getAttribute("data-theme");
      const next = current === "dark" ? "light" : "dark";
      body.setAttribute("data-theme", next);
      icon.className = next === "light" ? "fas fa-moon" : "fas fa-sun";
      localStorage.setItem("theme", next);
    });

    // Search & Filter
    const searchInput = document.getElementById('searchFreelancers');
    const ratingFilter = document.getElementById('filterRating');
    const sortSelect = document.getElementById('sortBy');
    const grid = document.getElementById('freelancerGrid');
    const cards = Array.from(document.querySelectorAll('.freelancer-card'));

    function filterAndSort() {
      const searchTerm = searchInput.value.toLowerCase();
      const minRating = ratingFilter.value ? parseFloat(ratingFilter.value) : 0;
      const sortBy = sortSelect.value;

      // Filter cards
      let visibleCards = cards.filter(card => {
        const name = card.dataset.name;
        const rating = parseFloat(card.dataset.rating);
        
        const matchesSearch = name.includes(searchTerm);
        const matchesRating = rating >= minRating;
        
        return matchesSearch && matchesRating;
      });

      // Sort cards
      visibleCards.sort((a, b) => {
        switch(sortBy) {
          case 'rating':
            return parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating);
          case 'recent':
            return parseInt(b.dataset.joined) - parseInt(a.dataset.joined);
          case 'rate_low':
            return parseFloat(a.dataset.rate) - parseFloat(b.dataset.rate);
          case 'rate_high':
            return parseFloat(b.dataset.rate) - parseFloat(a.dataset.rate);
          default:
            return 0;
        }
      });

      // Hide all cards
      cards.forEach(card => card.style.display = 'none');

      // Show and reorder visible cards
      visibleCards.forEach(card => {
        card.style.display = 'block';
        grid.appendChild(card);
      });

      // Show no results message
      if (visibleCards.length === 0 && cards.length > 0) {
        grid.innerHTML = `
          <div class="no-results">
            <i class="fas fa-search"></i>
            <h3>No Freelancers Match Your Criteria</h3>
            <p>Try adjusting your filters</p>
          </div>
        `;
      }
    }

    searchInput.addEventListener('input', filterAndSort);
    ratingFilter.addEventListener('change', filterAndSort);
    sortSelect.addEventListener('change', filterAndSort);
  </script>
</body>
</html>