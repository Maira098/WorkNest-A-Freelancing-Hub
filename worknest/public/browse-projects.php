<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user profile information
$stmt = $pdo->prepare('SELECT u.email, u.role, u.created_at, p.display_name 
                       FROM users u 
                       LEFT JOIN profiles p ON u.id = p.user_id 
                       WHERE u.id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Set user display information
$display_name = $user_data['display_name'] ?: explode('@', $user_data['email'])[0];

// Parse first and last name from display_name
$name_parts = explode(' ', $display_name, 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : '';

$user_role_display = ucfirst($user_data['role']);

// Get initials for avatar
if ($first_name && $last_name) {
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
} else {
    $initials = strtoupper(substr($display_name, 0, 2));
}

// Mock projects data (TODO: Replace with actual database queries)
$mock_projects = [
    [
        'id' => 1,
        'title' => 'E-Commerce Website Development',
        'description' => 'Looking for an experienced developer to build a modern e-commerce platform with payment integration, user authentication, and admin dashboard.',
        'category' => 'Web Development',
        'categorySlug' => 'web-dev',
        'budget' => 2500,
        'duration' => 'medium',
        'deadline' => '3 weeks',
        'proposals' => 12,
        'skills' => ['React', 'Node.js', 'MongoDB', 'Stripe'],
        'postedDate' => '2024-12-10',
        'client' => 'TechStore Inc.'
    ],
    [
        'id' => 2,
        'title' => 'Mobile App UI/UX Design',
        'description' => 'Need a creative designer to create modern, user-friendly UI/UX designs for our fitness mobile application. Must have experience with Figma.',
        'category' => 'Graphic Design',
        'categorySlug' => 'design',
        'budget' => 800,
        'duration' => 'short',
        'deadline' => '1 week',
        'proposals' => 8,
        'skills' => ['Figma', 'UI Design', 'Prototyping', 'Mobile Design'],
        'postedDate' => '2024-12-11',
        'client' => 'FitLife App'
    ]
];

$projects = $mock_projects;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Projects - WorkNest</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ===== THEME VARIABLES ===== */
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        /* ===== SIDEBAR ===== */
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

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

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

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(19,211,240,0.1);
            opacity: 1;
            color: var(--accent);
        }

        .sidebar-menu a i {
            margin-right: 12px;
            width: 20px;
            font-size: 1.1rem;
        }

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

        .user-profile:hover {
            background-color: rgba(19,211,240,0.1);
        }

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

        .user-info h4 {
            font-size: 0.95rem;
            margin-bottom: 2px;
        }

        .user-info p {
            font-size: 0.8rem;
            opacity: 0.7;
            margin: 0;
        }

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

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        /* Top Bar */
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

        .top-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-bar {
            position: relative;
        }

        .search-bar input {
            padding: 10px 15px 10px 45px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background-color: var(--card);
            color: var(--text);
            width: 280px;
            transition: all 0.3s;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 10px rgba(19,211,240,0.2);
        }

        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent);
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

        .theme-toggle-btn i {
            font-size: 1.2rem;
            color: var(--accent);
        }

        /* Filter Section */
        .filter-section {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filter-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .filter-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background-color: var(--bg);
            color: var(--text);
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .btn-filter {
            background: linear-gradient(135deg, var(--accent), #0891b2);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 24px;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(19,211,240,0.3);
        }

        /* Projects Grid */
        .projects-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .projects-count {
            font-size: 1.1rem;
            opacity: 0.8;
        }

        .projects-count strong {
            color: var(--accent);
        }

        .sort-select {
            padding: 8px 15px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background-color: var(--card);
            color: var(--text);
            cursor: pointer;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        /* Project Card */
        .project-card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s;
            animation: fadeInUp 0.8s ease;
            cursor: pointer;
        }

        [data-theme="light"] .project-card {
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(19,211,240,0.2);
            border-color: var(--accent);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .project-category {
            background: rgba(19,211,240,0.2);
            color: var(--accent);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .project-bookmark {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
        }

        .project-bookmark:hover {
            background: rgba(19,211,240,0.1);
            border-color: var(--accent);
            color: var(--accent);
        }

        .project-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text);
        }

        .project-description {
            opacity: 0.8;
            margin-bottom: 20px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .project-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .tag {
            background: rgba(255,255,255,0.05);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            opacity: 0.9;
        }

        [data-theme="light"] .tag {
            background: rgba(0,0,0,0.05);
        }

        .project-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .project-budget {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .budget-label {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .budget-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }

        .project-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .project-meta i {
            color: var(--accent);
            margin-right: 5px;
        }

        .btn-apply {
            background: linear-gradient(135deg, var(--accent), #0891b2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
            width: 100%;
        }

        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(19,211,240,0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .projects-grid {
                grid-template-columns: 1fr;
            }

            .search-bar input {
                width: 200px;
            }
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">WorkNest</a>

        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="browse-projects.php" class="active"><i class="fas fa-briefcase"></i> Browse Projects</a></li>
            <li><a href="my-projects.php"><i class="fas fa-folder"></i> My Projects</a></li>
            <li><a href="messages-freelancer.php"><i class="fas fa-comments"></i> Messages</a></li>
            <li><a href="earnings.php"><i class="fas fa-wallet"></i> Earnings</a></li>
            <li><a href="reviews-freelancer.php"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="settings-freelancer.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>

        <div class="sidebar-footer">
            <a href="profile-freelancer.php" class="user-profile">
                <div class="user-avatar">
                    <?= htmlspecialchars($initials) ?>
                </div>
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
                <h1>Browse Projects 🔍</h1>
                <p>Find your next opportunity from thousands of projects</p>
            </div>

            <div class="top-actions">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search projects...">
                </div>

                <div class="theme-toggle-btn" id="themeToggle">
                    <i class="fas fa-sun"></i>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Category</label>
                    <select class="filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="web-dev">Web Development</option>
                        <option value="design">Graphic Design</option>
                        <option value="writing">Content Writing</option>
                        <option value="marketing">Marketing & SEO</option>
                        <option value="video">Video Editing</option>
                        <option value="mobile">Mobile Development</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Budget Range</label>
                    <select class="filter-select" id="budgetFilter">
                        <option value="">Any Budget</option>
                        <option value="0-500">$0 - $500</option>
                        <option value="500-1000">$500 - $1,000</option>
                        <option value="1000-3000">$1,000 - $3,000</option>
                        <option value="3000+">$3,000+</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Duration</label>
                    <select class="filter-select" id="durationFilter">
                        <option value="">Any Duration</option>
                        <option value="short">Less than 1 week</option>
                        <option value="medium">1-4 weeks</option>
                        <option value="long">1-3 months</option>
                        <option value="ongoing">3+ months</option>
                    </select>
                </div>

                <button class="btn-filter" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </div>

        <div class="projects-header">
            <div class="projects-count">
                Showing <strong id="projectCount"><?= count($projects) ?></strong> projects
            </div>
            <select class="sort-select" id="sortSelect" onchange="sortProjects()">
                <option value="newest">Newest First</option>
                <option value="budget-high">Highest Budget</option>
                <option value="budget-low">Lowest Budget</option>
                <option value="deadline">Deadline Soon</option>
            </select>
        </div>

        <div class="projects-grid" id="projectsGrid">
            <?php if (count($projects) > 0): ?>
                <?php foreach ($projects as $project): ?>
                    <?php
                        $formatted_budget = number_format($project['budget']);
                        $tags_html = '';
                        $skills_to_show = array_slice($project['skills'], 0, 4);
                        foreach ($skills_to_show as $skill) {
                            $tags_html .= '<span class="tag">' . htmlspecialchars($skill) . '</span>';
                        }
                    ?>
                    <div class="project-card" data-project-id="<?= $project['id'] ?>" 
                         data-category="<?= $project['categorySlug'] ?>" 
                         data-budget="<?= $project['budget'] ?>"
                         data-duration="<?= $project['duration'] ?>"
                         data-date="<?= $project['postedDate'] ?>"
                         onclick="viewProject(<?= $project['id'] ?>)">
                        <div class="project-header">
                            <span class="project-category"><?= htmlspecialchars($project['category']) ?></span>
                            <button class="project-bookmark" onclick="event.stopPropagation(); toggleBookmark(<?= $project['id'] ?>)">
                                <i class="far fa-bookmark"></i>
                            </button>
                        </div>
                        
                        <h3 class="project-title"><?= htmlspecialchars($project['title']) ?></h3>
                        <p class="project-description"><?= htmlspecialchars($project['description']) ?></p>
                        
                        <div class="project-tags">
                            <?= $tags_html ?>
                        </div>
                        
                        <div class="project-footer">
                            <div class="project-budget">
                                <span class="budget-label">Budget</span>
                                <span class="budget-amount">$<?= $formatted_budget ?></span>
                            </div>
                            <div style="text-align: right;">
                                <div class="project-meta">
                                    <span><i class="fas fa-clock"></i> <?= htmlspecialchars($project['deadline']) ?></span>
                                </div>
                                <div class="project-meta" style="margin-top: 5px;">
                                    <span><i class="fas fa-file-alt"></i> <?= $project['proposals'] ?> proposals</span>
                                </div>
                            </div>
                        </div>
                        
                        <button class="btn-apply" onclick="event.stopPropagation(); applyToProject(<?= $project['id'] ?>)">
                            Apply Now
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px; grid-column: 1/-1;">
                    <i class="fas fa-search" style="font-size: 4rem; opacity: 0.3; margin-bottom: 20px;"></i>
                    <h3>No projects currently available</h3>
                    <p style="opacity: 0.7;">Check back soon for new opportunities</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        const allProjects = <?= json_encode($projects) ?>;
        let filteredProjects = [...allProjects];

        function renderProjects(projects) {
            const grid = document.getElementById('projectsGrid');
            const projectCount = document.getElementById('projectCount');
            
            projectCount.textContent = projects.length;
            
            if (projects.length === 0) {
                grid.innerHTML = `
                    <div style="text-align: center; padding: 60px; grid-column: 1/-1;">
                        <i class="fas fa-search" style="font-size: 4rem; opacity: 0.3; margin-bottom: 20px;"></i>
                        <h3>No projects found</h3>
                        <p style="opacity: 0.7;">Try adjusting your filters or search terms</p>
                    </div>
                `;
                return;
            }
            
            grid.innerHTML = projects.map(project => {
                const tagsHtml = project.skills.slice(0, 4).map(skill => 
                    `<span class="tag">${escapeHtml(skill)}</span>`
                ).join('');
                
                return `
                    <div class="project-card" onclick="viewProject(${project.id})">
                        <div class="project-header">
                            <span class="project-category">${escapeHtml(project.category)}</span>
                            <button class="project-bookmark" onclick="event.stopPropagation(); toggleBookmark(${project.id})">
                                <i class="far fa-bookmark"></i>
                            </button>
                        </div>
                        
                        <h3 class="project-title">${escapeHtml(project.title)}</h3>
                        <p class="project-description">${escapeHtml(project.description)}</p>
                        
                        <div class="project-tags">${tagsHtml}</div>
                        
                        <div class="project-footer">
                            <div class="project-budget">
                                <span class="budget-label">Budget</span>
                                <span class="budget-amount">$${project.budget.toLocaleString()}</span>
                            </div>
                            <div style="text-align: right;">
                                <div class="project-meta">
                                    <span><i class="fas fa-clock"></i> ${escapeHtml(project.deadline)}</span>
                                </div>
                                <div class="project-meta" style="margin-top: 5px;">
                                    <span><i class="fas fa-file-alt"></i> ${project.proposals} proposals</span>
                                </div>
                            </div>
                        </div>
                        
                        <button class="btn-apply" onclick="event.stopPropagation(); applyToProject(${project.id})">
                            Apply Now
                        </button>
                    </div>
                `;
            }).join('');
        }

        function escapeHtml(str) {
            if (typeof str !== 'string') return str;
            return str.replace(/&/g, "&amp;")
                       .replace(/</g, "&lt;")
                       .replace(/>/g, "&gt;")
                       .replace(/"/g, "&quot;")
                       .replace(/'/g, "&#039;");
        }

        function applyFilters() {
            const category = document.getElementById('categoryFilter').value;
            const budget = document.getElementById('budgetFilter').value;
            const duration = document.getElementById('durationFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            filteredProjects = allProjects.filter(project => {
                let match = true;
                
                if (category && project.categorySlug !== category) match = false;
                if (duration && project.duration !== duration) match = false;
                if (searchTerm && !project.title.toLowerCase().includes(searchTerm) && 
                    !project.description.toLowerCase().includes(searchTerm)) match = false;
                
                if (budget) {
                    const [min, maxStr] = budget.split('-');
                    const minVal = parseInt(min) || 0;
                    const maxVal = maxStr.includes('+') ? Infinity : parseInt(maxStr) || Infinity;
                    
                    if (project.budget < minVal || project.budget > maxVal) match = false;
                }
                
                return match;
            });
            
            sortProjects();
        }

        function sortProjects() {
            const sortValue = document.getElementById('sortSelect').value;
            let sorted = [...filteredProjects];
            
            switch(sortValue) {
                case 'newest':
                    sorted.sort((a, b) => new Date(b.postedDate) - new Date(a.postedDate));
                    break;
                case 'budget-high':
                    sorted.sort((a, b) => b.budget - a.budget);
                    break;
                case 'budget-low':
                    sorted.sort((a, b) => a.budget - b.budget);
                    break;
            }
            
            renderProjects(sorted);
        }

        document.getElementById('searchInput').addEventListener('input', applyFilters);

        function viewProject(projectId) {
            window.location.href = `job-details.php?id=${projectId}`;
        }

        function applyToProject(projectId) {
            window.location.href = `job-details.php?id=${projectId}`;
        }

        function toggleBookmark(projectId) {
            alert(`Bookmark feature coming soon for project ${projectId}`);
        }

        // Theme toggle
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

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            applyFilters();
        });
    </script>

</body>
</html>