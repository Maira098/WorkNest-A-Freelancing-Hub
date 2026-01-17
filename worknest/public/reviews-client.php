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

// Get freelancers who worked on client's projects (accepted proposals)
$freelancers_stmt = $pdo->prepare('SELECT DISTINCT u.id, u.email, p.display_name, p.bio,
                                          AVG(r.rating) as avg_rating,
                                          COUNT(DISTINCT r.id) as review_count,
                                          j.id as job_id, j.title as job_title,
                                          pr.id as proposal_id,
                                          EXISTS(SELECT 1 FROM reviews WHERE reviewer_id = ? AND reviewee_id = u.id AND job_id = j.id) as already_reviewed
                                   FROM proposals pr
                                   INNER JOIN jobs j ON pr.job_id = j.id
                                   INNER JOIN users u ON pr.freelancer_id = u.id
                                   LEFT JOIN profiles p ON u.id = p.user_id
                                   LEFT JOIN reviews r ON u.id = r.reviewee_id
                                   WHERE j.client_id = ? AND pr.status = "accepted"
                                   GROUP BY u.id, j.id
                                   ORDER BY pr.created_at DESC');
$freelancers_stmt->execute([$user_id, $user_id]);
$freelancers = $freelancers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get reviews client has given
$given_reviews_stmt = $pdo->prepare('SELECT r.*, j.title as job_title,
                                            u.email as freelancer_email, p.display_name as freelancer_name
                                     FROM reviews r
                                     INNER JOIN users u ON r.reviewee_id = u.id
                                     LEFT JOIN profiles p ON u.id = p.user_id
                                     LEFT JOIN jobs j ON r.job_id = j.id
                                     WHERE r.reviewer_id = ?
                                     ORDER BY r.created_at DESC');
$given_reviews_stmt->execute([$user_id]);
$given_reviews = $given_reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
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

    /* Section */
    .section {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .section { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .section-header {
      margin-bottom: 25px;
    }
    .section-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
    }

    /* Freelancer Card */
    .freelancer-card {
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
      transition: all 0.3s;
    }
    [data-theme="light"] .freelancer-card { background: rgba(0,0,0,0.02); }
    .freelancer-card:hover {
      transform: translateX(5px);
      border-color: var(--accent);
    }
    .freelancer-info {
      display: flex;
      align-items: center;
      gap: 15px;
      flex: 1;
    }
    .freelancer-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.5rem;
      flex-shrink: 0;
    }
    .freelancer-details {
      flex: 1;
    }
    .freelancer-name {
      font-weight: 700;
      font-size: 1.1rem;
      margin-bottom: 5px;
    }
    .freelancer-job {
      font-size: 0.9rem;
      opacity: 0.7;
      margin-bottom: 5px;
    }
    .freelancer-rating {
      color: #ffa500;
      font-size: 0.9rem;
    }
    .btn-review {
      padding: 10px 25px;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      white-space: nowrap;
    }
    .btn-review:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(19,211,240,0.4);
    }
    .btn-reviewed {
      padding: 10px 25px;
      background: rgba(46,213,115,0.2);
      color: #2ed573;
      border: 1px solid #2ed573;
      border-radius: 8px;
      font-weight: 600;
      white-space: nowrap;
    }

    /* Review Modal */
    .review-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.8);
      z-index: 2000;
      align-items: center;
      justify-content: center;
    }
    .review-modal.active {
      display: flex;
    }
    .review-modal-content {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 40px;
      max-width: 600px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
    }
    .review-modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }
    .review-modal-header h3 {
      font-size: 1.5rem;
    }
    .close-modal {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: rgba(255,71,87,0.2);
      border: none;
      color: #ff4757;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: 0.3s;
    }
    .close-modal:hover {
      background: rgba(255,71,87,0.3);
    }

    /* Star Rating */
    .star-rating {
      display: flex;
      gap: 10px;
      margin: 20px 0;
      justify-content: center;
    }
    .star {
      font-size: 2.5rem;
      color: rgba(255,165,0,0.3);
      cursor: pointer;
      transition: all 0.3s;
    }
    [data-theme="light"] .star {
      color: rgba(255,165,0,0.4);
    }
    .star:hover, .star.active {
      color: #ffa500;
      transform: scale(1.2);
    }
    .freelancer-rating {
      color: #ffa500;
      font-size: 0.9rem;
    }
    .review-stars {
      color: #ffa500;
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

    /* Form Elements */
    .form-group {
      margin-bottom: 25px;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
    }
    .form-textarea {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid var(--border);
      border-radius: 10px;
      background: rgba(255,255,255,0.05);
      color: var(--text);
      font-size: 0.95rem;
      min-height: 120px;
      resize: vertical;
      font-family: 'Inter', sans-serif;
    }
    .form-textarea:focus {
      outline: none;
      border-color: var(--accent);
    }

    /* Review Item */
    .review-item {
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 15px;
    }
    [data-theme="light"] .review-item { background: rgba(0,0,0,0.02); }
    .review-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    .review-freelancer {
      font-weight: 700;
      font-size: 1.1rem;
    }
    .review-stars {
      color: #ffa500;
    }
    .review-job {
      font-size: 0.9rem;
      opacity: 0.7;
      margin-bottom: 10px;
    }
    .review-comment {
      line-height: 1.6;
      margin-bottom: 10px;
    }
    .review-date {
      font-size: 0.85rem;
      opacity: 0.6;
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
      .freelancer-card { flex-direction: column; align-items: flex-start; }
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
      <li><a href="reviews-client.php" class="active"><i class="fas fa-star"></i> Reviews</a></li>
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
        <h1>Reviews ⭐</h1>
        <p>Rate freelancers you've worked with</p>
      </div>
      <button id="themeToggle" style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text); transition: all 0.3s;">
        <i class="fas fa-sun"></i>
      </button>
    </div>

    <!-- Freelancers to Review -->
    <div class="section">
      <div class="section-header">
        <h2><i class="fas fa-star"></i> Freelancers to Review</h2>
      </div>

      <?php if (!empty($freelancers)): ?>
        <?php foreach ($freelancers as $freelancer): 
          if ($freelancer['already_reviewed']) continue;
          $f_name = $freelancer['display_name'] ?: explode('@', $freelancer['email'])[0];
          $f_initials = strtoupper(substr($f_name, 0, 2));
          $avg_rating = $freelancer['avg_rating'] ? round($freelancer['avg_rating'], 1) : 0;
        ?>
          <div class="freelancer-card">
            <div class="freelancer-info">
              <div class="freelancer-avatar"><?= htmlspecialchars($f_initials) ?></div>
              <div class="freelancer-details">
                <div class="freelancer-name"><?= htmlspecialchars($f_name) ?></div>
                <div class="freelancer-job">Project: <?= htmlspecialchars($freelancer['job_title']) ?></div>
                <?php if ($avg_rating > 0): ?>
                  <div class="freelancer-rating">
                    <i class="fas fa-star"></i> <?= $avg_rating ?> (<?= $freelancer['review_count'] ?> reviews)
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <button class="btn-review" onclick="openReviewModal(<?= $freelancer['id'] ?>, '<?= htmlspecialchars($f_name, ENT_QUOTES) ?>', <?= $freelancer['job_id'] ?>, '<?= htmlspecialchars($freelancer['job_title'], ENT_QUOTES) ?>')">
              <i class="fas fa-star"></i> Write Review
            </button>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-star"></i>
          <h3>No Freelancers to Review</h3>
          <p>Reviews will appear here when you work with freelancers</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Reviews Given -->
    <?php if (!empty($given_reviews)): ?>
    <div class="section">
      <div class="section-header">
        <h2><i class="fas fa-check-circle"></i> Reviews You've Given</h2>
      </div>

      <?php foreach ($given_reviews as $review): 
        $freelancer_name = $review['freelancer_name'] ?: explode('@', $review['freelancer_email'])[0];
      ?>
        <div class="review-item">
          <div class="review-header">
            <div class="review-freelancer"><?= htmlspecialchars($freelancer_name) ?></div>
            <div class="review-stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fas fa-star <?= $i <= $review['rating'] ? 'star-filled' : 'star-empty' ?>"></i>
              <?php endfor; ?>
            </div>
          </div>
          <div class="review-job">Project: <?= htmlspecialchars($review['job_title']) ?></div>
          <div class="review-comment"><?= htmlspecialchars($review['comment']) ?></div>
          <div class="review-date"><?= date('M d, Y', strtotime($review['created_at'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>

  <!-- Review Modal -->
  <div class="review-modal" id="reviewModal">
    <div class="review-modal-content">
      <div class="review-modal-header">
        <h3>Write a Review</h3>
        <button class="close-modal" onclick="closeReviewModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <form id="reviewForm">
        <input type="hidden" id="revieweeId" name="reviewee_id">
        <input type="hidden" id="jobId" name="job_id">
        <input type="hidden" id="ratingValue" name="rating" value="0">

        <div style="text-align: center; margin-bottom: 30px;">
          <h4 id="freelancerNameModal" style="margin-bottom: 10px;"></h4>
          <p id="jobTitleModal" style="opacity: 0.7;"></p>
        </div>

        <div class="form-group">
          <label>Rating</label>
          <div class="star-rating">
            <i class="fas fa-star star" data-rating="1"></i>
            <i class="fas fa-star star" data-rating="2"></i>
            <i class="fas fa-star star" data-rating="3"></i>
            <i class="fas fa-star star" data-rating="4"></i>
            <i class="fas fa-star star" data-rating="5"></i>
          </div>
        </div>

        <div class="form-group">
          <label>Your Review</label>
          <textarea class="form-textarea" name="comment" placeholder="Share your experience working with this freelancer..." required></textarea>
        </div>

        <button type="submit" class="btn-review" style="width: 100%;">
          <i class="fas fa-paper-plane"></i> Submit Review
        </button>
      </form>
    </div>
  </div>

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

    // Star rating
    const stars = document.querySelectorAll('.star');
    const ratingValue = document.getElementById('ratingValue');
    let selectedRating = 0;

    stars.forEach(star => {
      star.addEventListener('click', () => {
        selectedRating = parseInt(star.dataset.rating);
        ratingValue.value = selectedRating;
        updateStars();
      });

      star.addEventListener('mouseenter', () => {
        const rating = parseInt(star.dataset.rating);
        stars.forEach((s, index) => {
          if (index < rating) {
            s.classList.add('active');
          } else {
            s.classList.remove('active');
          }
        });
      });
    });

    document.querySelector('.star-rating').addEventListener('mouseleave', updateStars);

    function updateStars() {
      stars.forEach((star, index) => {
        if (index < selectedRating) {
          star.classList.add('active');
        } else {
          star.classList.remove('active');
        }
      });
    }

    // Modal
    const modal = document.getElementById('reviewModal');

    function openReviewModal(freelancerId, freelancerName, jobId, jobTitle) {
      document.getElementById('revieweeId').value = freelancerId;
      document.getElementById('jobId').value = jobId;
      document.getElementById('freelancerNameModal').textContent = freelancerName;
      document.getElementById('jobTitleModal').textContent = 'Project: ' + jobTitle;
      modal.classList.add('active');
      selectedRating = 0;
      ratingValue.value = 0;
      updateStars();
    }

    function closeReviewModal() {
      modal.classList.remove('active');
      document.getElementById('reviewForm').reset();
    }

    // Close on background click
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeReviewModal();
      }
    });

    // Form submission
    document.getElementById('reviewForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      if (selectedRating === 0) {
        alert('Please select a rating');
        return;
      }

      const formData = new FormData(e.target);
      const data = {
        reviewee_id: formData.get('reviewee_id'),
        job_id: formData.get('job_id'),
        rating: formData.get('rating'),
        comment: formData.get('comment')
      };

      try {
        const response = await fetch('api/give_review.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
          alert('Review submitted successfully!');
          closeReviewModal();
          location.reload();
        } else {
          alert('Error: ' + result.message);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while submitting the review');
      }
    });
  </script>
</body>
</html>