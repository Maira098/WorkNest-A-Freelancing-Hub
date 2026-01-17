<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Only clients can post jobs
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

// Get available skills for selection
try {
    $skills_stmt = $pdo->query('SELECT DISTINCT name, category FROM skills ORDER BY category, name');
    $all_skills = $skills_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by category
    $skills_by_category = [];
    foreach ($all_skills as $skill) {
        $category = $skill['category'] ?: 'Other';
        if (!isset($skills_by_category[$category])) {
            $skills_by_category[$category] = [];
        }
        $skills_by_category[$category][] = $skill['name'];
    }
} catch (PDOException $e) {
    $skills_by_category = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Post a Job - WorkNest</title>
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
      margin-bottom: 10px;
    }
    .page-header p {
      opacity: 0.7;
      font-size: 1.1rem;
    }

    /* Form Card */
    .form-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 40px;
      max-width: 900px;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .form-card { box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
    
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Form Elements */
    .form-group {
      margin-bottom: 25px;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      font-size: 0.95rem;
    }
    .form-group label .required {
      color: #ff4757;
      margin-left: 3px;
    }
    .form-control, .form-select, .form-textarea {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid var(--border);
      border-radius: 10px;
      background: rgba(255,255,255,0.05);
      color: var(--text);
      font-size: 0.95rem;
      transition: all 0.3s;
      font-family: 'Inter', sans-serif;
    }
    
    /* Force text color in dark theme */
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select,
    [data-theme="dark"] .form-textarea {
      color: #ffffff;
    }
    
    /* Light theme styling */
    [data-theme="light"] .form-control,
    [data-theme="light"] .form-select,
    [data-theme="light"] .form-textarea {
      background: #ffffff;
      color: #031121;
      border-color: #dee2e6;
    }
    
    /* Option elements styling */
    .form-select option {
      background: var(--card);
      color: var(--text);
      padding: 10px;
    }
    [data-theme="light"] .form-select option {
      background: #ffffff;
      color: #031121;
    }
    
    .form-control:focus, .form-select:focus, .form-textarea:focus {
      outline: none;
      border-color: var(--accent);
      background: rgba(19,211,240,0.08);
    }
    [data-theme="light"] .form-control:focus,
    [data-theme="light"] .form-select:focus,
    [data-theme="light"] .form-textarea:focus {
      background: #f0f8ff;
    }
    .form-textarea {
      min-height: 150px;
      resize: vertical;
    }
    
    /* Number input specific styling */
    input[type="number"].form-control {
      -webkit-appearance: none;
      -moz-appearance: textfield;
    }
    input[type="number"].form-control::-webkit-inner-spin-button,
    input[type="number"].form-control::-webkit-outer-spin-button {
      opacity: 1;
    }
    
    /* Date input styling */
    input[type="date"].form-control {
      color: var(--text);
      position: relative;
    }
    input[type="date"].form-control::-webkit-calendar-picker-indicator {
      filter: invert(0.7);
      cursor: pointer;
    }
    [data-theme="light"] input[type="date"].form-control::-webkit-calendar-picker-indicator {
      filter: invert(0.4);
    }
    
    /* Placeholder styling */
    .form-control::placeholder,
    .form-textarea::placeholder {
      color: rgba(255,255,255,0.4);
      opacity: 1;
    }
    [data-theme="light"] .form-control::placeholder,
    [data-theme="light"] .form-textarea::placeholder {
      color: rgba(0,0,0,0.4);
    }
    
    .form-hint {
      font-size: 0.85rem;
      opacity: 0.7;
      margin-top: 5px;
    }

    /* Skills Selection */
    .skills-container {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 10px;
      padding: 15px;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 10px;
      min-height: 60px;
    }
    [data-theme="light"] .skills-container { background: rgba(0,0,0,0.02); }
    .skill-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 15px;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 500;
    }
    .skill-tag .remove {
      cursor: pointer;
      width: 18px;
      height: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255,255,255,0.3);
      border-radius: 50%;
      transition: 0.3s;
    }
    .skill-tag .remove:hover {
      background: rgba(255,255,255,0.5);
    }
    .add-skill-btn {
      padding: 8px 15px;
      background: rgba(19,211,240,0.2);
      border: 1px solid var(--accent);
      color: var(--accent);
      border-radius: 8px;
      cursor: pointer;
      transition: 0.3s;
      font-size: 0.9rem;
    }
    .add-skill-btn:hover {
      background: rgba(19,211,240,0.3);
    }

    /* Skill Picker Modal */
    .skill-modal {
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
    .skill-modal.active {
      display: flex;
    }
    .skill-modal-content {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
      width: 90%;
    }
    .skill-modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .skill-modal-header h3 {
      font-size: 1.3rem;
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
    .skill-category {
      margin-bottom: 20px;
    }
    .skill-category h4 {
      font-size: 1rem;
      color: var(--accent);
      margin-bottom: 10px;
    }
    .skill-options {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .skill-option {
      padding: 8px 15px;
      background: rgba(255,255,255,0.05);
      border: 1px solid var(--border);
      border-radius: 8px;
      cursor: pointer;
      transition: 0.3s;
      font-size: 0.9rem;
    }
    .skill-option:hover {
      background: rgba(19,211,240,0.1);
      border-color: var(--accent);
    }
    .skill-option.selected {
      background: var(--accent);
      border-color: var(--accent);
      color: white;
    }

    /* Buttons */
    .btn-submit {
      padding: 15px 40px;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(19,211,240,0.3);
    }
    .btn-cancel {
      padding: 15px 40px;
      background: transparent;
      color: var(--text);
      border: 2px solid var(--border);
      border-radius: 10px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-block;
    }
    .btn-cancel:hover {
      border-color: var(--accent);
      color: var(--accent);
    }
    .form-actions {
      display: flex;
      gap: 15px;
      margin-top: 30px;
      padding-top: 30px;
      border-top: 1px solid var(--border);
    }

    /* Success Message */
    .success-message {
      display: none;
      padding: 15px 20px;
      background: rgba(46,213,115,0.2);
      border: 1px solid #2ed573;
      color: #2ed573;
      border-radius: 10px;
      margin-bottom: 20px;
      align-items: center;
      gap: 10px;
    }
    .success-message.show {
      display: flex;
    }

    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
      .form-card { padding: 25px; }
      .form-actions { flex-direction: column; }
      .btn-submit, .btn-cancel { width: 100%; text-align: center; justify-content: center; }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse-freelancer.php"><i class="fas fa-users"></i> Browse Freelancers</a></li>
      <li><a href="post-job.php" class="active"><i class="fas fa-plus-circle"></i> Post a Job</a></li>
      <li><a href="proposals.php"><i class="fas fa-inbox"></i> Proposals</a></li>
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
        <h1>Post a New Job 💼</h1>
        <p>Find the perfect freelancer for your project</p>
      </div>
      <button id="themeToggle" style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text); transition: all 0.3s;">
        <i class="fas fa-sun"></i>
      </button>
    </div>

    <div class="success-message" id="successMessage">
      <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
      <span>Job posted successfully! Redirecting...</span>
    </div>

    <div class="form-card">
      <form id="postJobForm">
        <!-- Job Title -->
        <div class="form-group">
          <label for="jobTitle">Job Title<span class="required">*</span></label>
          <input type="text" id="jobTitle" name="title" class="form-control" placeholder="e.g., Website Development, Logo Design" required>
          <div class="form-hint">Be specific and clear about what you need</div>
        </div>

        <!-- Job Description -->
        <div class="form-group">
          <label for="jobDescription">Job Description<span class="required">*</span></label>
          <textarea id="jobDescription" name="description" class="form-textarea" placeholder="Describe your project in detail..." required></textarea>
          <div class="form-hint">Include project scope, deliverables, and any specific requirements</div>
        </div>

        <!-- Category -->
        <div class="form-group">
          <label for="jobCategory">Category<span class="required">*</span></label>
          <select id="jobCategory" name="category" class="form-select" required>
            <option value="">Select a category</option>
            <option value="Web Development">Web Development</option>
            <option value="Mobile Development">Mobile Development</option>
            <option value="Graphic Design">Graphic Design</option>
            <option value="Content Writing">Content Writing</option>
            <option value="Marketing & SEO">Marketing & SEO</option>
            <option value="Video Editing">Video Editing</option>
            <option value="Data Entry">Data Entry</option>
            <option value="Virtual Assistant">Virtual Assistant</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <!-- Budget -->
        <div class="form-group">
          <label for="jobBudget">Budget (USD)<span class="required">*</span></label>
          <input type="number" id="jobBudget" name="budget" class="form-control" placeholder="e.g., 500" min="0" step="0.01" required>
          <div class="form-hint">Set a realistic budget for your project</div>
        </div>

        <!-- Deadline -->
        <div class="form-group">
          <label for="jobDeadline">Deadline</label>
          <input type="date" id="jobDeadline" name="deadline" class="form-control" min="<?= date('Y-m-d') ?>">
          <div class="form-hint">When do you need this project completed?</div>
        </div>

        <!-- Required Skills -->
        <div class="form-group">
          <label>Required Skills</label>
          <div class="skills-container" id="selectedSkills">
            <button type="button" class="add-skill-btn" id="addSkillBtn">
              <i class="fas fa-plus"></i> Add Skills
            </button>
          </div>
          <input type="hidden" id="skillsInput" name="skills_required">
          <div class="form-hint">Select skills needed for this job</div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <button type="submit" class="btn-submit">
            <i class="fas fa-paper-plane"></i>
            Post Job
          </button>
          <a href="dashboard.php" class="btn-cancel">Cancel</a>
        </div>
      </form>
    </div>
  </main>

  <!-- Skill Picker Modal -->
  <div class="skill-modal" id="skillModal">
    <div class="skill-modal-content">
      <div class="skill-modal-header">
        <h3>Select Skills</h3>
        <button type="button" class="close-modal" id="closeModal">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <?php if (!empty($skills_by_category)): ?>
        <?php foreach ($skills_by_category as $category => $skills): ?>
          <div class="skill-category">
            <h4><?= htmlspecialchars($category) ?></h4>
            <div class="skill-options">
              <?php foreach ($skills as $skill): ?>
                <div class="skill-option" data-skill="<?= htmlspecialchars($skill) ?>">
                  <?= htmlspecialchars($skill) ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="skill-category">
          <h4>Programming</h4>
          <div class="skill-options">
            <div class="skill-option" data-skill="JavaScript">JavaScript</div>
            <div class="skill-option" data-skill="Python">Python</div>
            <div class="skill-option" data-skill="PHP">PHP</div>
            <div class="skill-option" data-skill="Java">Java</div>
          </div>
        </div>
        <div class="skill-category">
          <h4>Design</h4>
          <div class="skill-options">
            <div class="skill-option" data-skill="Photoshop">Photoshop</div>
            <div class="skill-option" data-skill="Illustrator">Illustrator</div>
            <div class="skill-option" data-skill="Figma">Figma</div>
            <div class="skill-option" data-skill="UI/UX Design">UI/UX Design</div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Selected skills array
    let selectedSkills = [];

    // Skill modal handling
    const addSkillBtn = document.getElementById('addSkillBtn');
    const skillModal = document.getElementById('skillModal');
    const closeModal = document.getElementById('closeModal');
    const skillOptions = document.querySelectorAll('.skill-option');
    const selectedSkillsContainer = document.getElementById('selectedSkills');
    const skillsInput = document.getElementById('skillsInput');

    // Open modal
    addSkillBtn.addEventListener('click', () => {
      skillModal.classList.add('active');
    });

    // Close modal
    closeModal.addEventListener('click', () => {
      skillModal.classList.remove('active');
    });

    // Close on background click
    skillModal.addEventListener('click', (e) => {
      if (e.target === skillModal) {
        skillModal.classList.remove('active');
      }
    });

    // Skill selection
    skillOptions.forEach(option => {
      option.addEventListener('click', () => {
        const skill = option.dataset.skill;
        
        if (option.classList.contains('selected')) {
          // Remove skill
          option.classList.remove('selected');
          selectedSkills = selectedSkills.filter(s => s !== skill);
        } else {
          // Add skill
          option.classList.add('selected');
          selectedSkills.push(skill);
        }
        
        updateSkillsDisplay();
      });
    });

    // Update skills display
    function updateSkillsDisplay() {
      selectedSkillsContainer.innerHTML = '';
      
      // Add skill tags
      selectedSkills.forEach(skill => {
        const tag = document.createElement('div');
        tag.className = 'skill-tag';
        tag.innerHTML = `
          ${skill}
          <span class="remove" data-skill="${skill}">
            <i class="fas fa-times"></i>
          </span>
        `;
        selectedSkillsContainer.appendChild(tag);
      });

      // Add "Add Skills" button
      selectedSkillsContainer.appendChild(addSkillBtn);

      // Update hidden input
      skillsInput.value = selectedSkills.join(',');

      // Add remove listeners
      document.querySelectorAll('.skill-tag .remove').forEach(btn => {
        btn.addEventListener('click', () => {
          const skill = btn.dataset.skill;
          selectedSkills = selectedSkills.filter(s => s !== skill);
          
          // Unselect in modal
          skillOptions.forEach(option => {
            if (option.dataset.skill === skill) {
              option.classList.remove('selected');
            }
          });
          
          updateSkillsDisplay();
        });
      });
    }

    // Form submission
    const form = document.getElementById('postJobForm');
    const successMessage = document.getElementById('successMessage');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(form);
      const data = {
        title: formData.get('title'),
        description: formData.get('description'),
        category: formData.get('category'),
        budget: parseFloat(formData.get('budget')),
        deadline: formData.get('deadline') || null,
        skills_required: skillsInput.value
      };

      try {
        const response = await fetch('api/post_job.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
          successMessage.classList.add('show');
          form.reset();
          selectedSkills = [];
          updateSkillsDisplay();

          // Redirect after 2 seconds
          setTimeout(() => {
            window.location.href = 'proposals.php';
          }, 2000);
        } else {
          alert('Error: ' + result.message);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while posting the job');
      }
    });

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