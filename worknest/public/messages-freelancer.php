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

// Get selected conversation from URL
$selected_user_id = isset($_GET['user']) ? (int)$_GET['user'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages - WorkNest</title>
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
      --message-sent: #13d3f0;
      --message-received: #041d3a;
    }
    [data-theme="light"] {
      --bg: #f5f7fa;
      --text: #031121;
      --card: #ffffff;
      --accent: #1055c9;
      --sidebar: #ffffff;
      --border: rgba(0,0,0,0.1);
      --message-sent: #1055c9;
      --message-received: #e9ecef;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Inter', sans-serif;
      transition: all 0.3s ease;
      overflow: hidden;
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
      flex-shrink: 0;
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
      height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 30px 30px 20px 30px;
      border-bottom: 1px solid var(--border);
      background: var(--bg);
      flex-shrink: 0;
    }
    .page-header h1 { font-size: 2rem; font-weight: 700; margin-bottom: 5px; }
    .page-header p { opacity: 0.7; font-size: 0.95rem; }
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
    .messages-container {
      display: grid;
      grid-template-columns: 380px 1fr;
      flex: 1;
      overflow: hidden;
    }
    .conversations-panel {
      background: var(--card);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .conversations-header {
      padding: 20px;
      border-bottom: 1px solid var(--border);
    }
    .search-box {
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
    .btn-new-conversation {
      margin-top: 15px;
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.95rem;
    }
    .btn-new-conversation:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(19,211,240,0.4);
    }
    .conversations-list {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
    }
    .conversation-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 5px;
      position: relative;
      text-decoration: none;
      color: var(--text);
    }
    .conversation-item:hover {
      background: rgba(19,211,240,0.1);
      color: var(--text);
    }
    .conversation-item.active {
      background: rgba(19,211,240,0.15);
      border-left: 3px solid var(--accent);
    }
    .conversation-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.2rem;
      flex-shrink: 0;
      position: relative;
    }
    .conversation-details {
      flex: 1;
      min-width: 0;
    }
    .conversation-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 5px;
    }
    .conversation-name {
      font-weight: 600;
      font-size: 1rem;
    }
    .conversation-time {
      font-size: 0.75rem;
      opacity: 0.6;
    }
    .conversation-preview {
      font-size: 0.9rem;
      opacity: 0.7;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .unread-badge {
      position: absolute;
      top: 10px;
      right: 15px;
      background: var(--accent);
      color: white;
      font-size: 0.75rem;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 12px;
      min-width: 20px;
      text-align: center;
    }
    .chat-panel {
      display: flex;
      flex-direction: column;
      background: var(--bg);
      overflow: hidden;
    }
    .chat-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 30px;
      background: var(--card);
      border-bottom: 1px solid var(--border);
    }
    .chat-header-info {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .chat-user-name {
      font-weight: 600;
      font-size: 1.1rem;
      margin-bottom: 3px;
    }
    .chat-user-status {
      font-size: 0.85rem;
      opacity: 0.7;
    }
    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 30px;
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    .message {
      display: flex;
      gap: 12px;
      max-width: 70%;
      animation: messageSlide 0.3s ease;
    }
    @keyframes messageSlide {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .message.sent {
      align-self: flex-end;
      flex-direction: row-reverse;
    }
    .message-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.95rem;
      flex-shrink: 0;
    }
    .message-content {
      flex: 1;
    }
    .message-bubble {
      padding: 12px 18px;
      border-radius: 15px;
      font-size: 0.95rem;
      line-height: 1.6;
      word-wrap: break-word;
    }
    .message.received .message-bubble {
      background: var(--message-received);
      border-bottom-left-radius: 5px;
    }
    [data-theme="light"] .message.received .message-bubble {
      color: var(--text);
    }
    .message.sent .message-bubble {
      background: var(--message-sent);
      color: white;
      border-bottom-right-radius: 5px;
    }
    .message-time {
      font-size: 0.75rem;
      opacity: 0.6;
      margin-top: 5px;
      padding: 0 5px;
    }
    .message.sent .message-time {
      text-align: right;
    }
    .chat-input-container {
      padding: 20px 30px;
      background: var(--card);
      border-top: 1px solid var(--border);
    }
    .chat-input-wrapper {
      display: flex;
      gap: 12px;
      align-items: flex-end;
    }
    .message-input {
      flex: 1;
      padding: 12px 18px;
      border: 2px solid var(--border);
      border-radius: 10px;
      background: rgba(255,255,255,0.05);
      color: var(--text);
      font-size: 0.95rem;
      resize: none;
      max-height: 120px;
      font-family: 'Inter', sans-serif;
      transition: all 0.3s;
    }
    [data-theme="light"] .message-input { background: #f8f9fa; }
    .message-input:focus {
      outline: none;
      border-color: var(--accent);
    }
    .attach-btn {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      background: var(--card);
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s;
      color: var(--accent);
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .attach-btn:hover {
      background: rgba(19,211,240,0.1);
      border-color: var(--accent);
    }
    .send-btn {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s;
      color: white;
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .send-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(19,211,240,0.4);
    }
    .send-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .file-preview {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 10px 15px;
      background: rgba(19,211,240,0.1);
      border: 1px solid var(--accent);
      border-radius: 8px;
      margin-top: 10px;
    }
    .file-preview i {
      color: var(--accent);
      font-size: 1.2rem;
    }
    .file-preview-name {
      font-size: 0.9rem;
      max-width: 200px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .file-preview-remove {
      background: none;
      border: none;
      color: #ff4757;
      cursor: pointer;
      font-size: 1rem;
      padding: 0 5px;
    }
    .empty-state {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px;
      text-align: center;
    }
    .empty-state i {
      font-size: 4rem;
      color: var(--accent);
      margin-bottom: 20px;
      opacity: 0.5;
    }
    .empty-state h3 {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }
    .empty-state p {
      opacity: 0.7;
      font-size: 1rem;
    }
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.7);
      z-index: 2000;
      align-items: center;
      justify-content: center;
    }
    .modal.show { display: flex; }
    .modal-content {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      max-width: 500px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      animation: slideUp 0.3s ease;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    .modal-header h2 { font-size: 1.5rem; margin: 0; }
    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--text);
      cursor: pointer;
      opacity: 0.7;
      transition: 0.3s;
    }
    .modal-close:hover { opacity: 1; color: var(--accent); }
    .user-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .user-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s;
    }
    [data-theme="light"] .user-item { background: rgba(0,0,0,0.02); }
    .user-item:hover {
      background: rgba(19,211,240,0.1);
      border-color: var(--accent);
    }
    .user-item-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.1rem;
    }
    .user-item-info h4 { font-size: 1rem; margin-bottom: 3px; }
    .user-item-info p { font-size: 0.85rem; opacity: 0.7; margin: 0; }
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
      .messages-container { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse-projects.php"><i class="fas fa-briefcase"></i> Browse Projects</a></li>
      <li><a href="my-projects.php"><i class="fas fa-folder"></i> My Projects</a></li>
      <li><a href="messages-freelancer.php" class="active"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="earnings.php"><i class="fas fa-wallet"></i> Earnings</a></li>
      <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
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
      <div class="page-header">
        <h1>Messages 💬</h1>
        <p>Stay connected with your clients and team</p>
      </div>
      <div class="theme-toggle-btn" id="themeToggle">
        <i class="fas fa-sun"></i>
      </div>
    </div>

    <div class="messages-container">
      <div class="conversations-panel">
        <div class="conversations-header">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchConversations" placeholder="Search conversations...">
          </div>
          <button class="btn-new-conversation" onclick="openNewMessageModal()">
            <i class="fas fa-plus"></i> New Message
          </button>
        </div>
        <div class="conversations-list" id="conversationsList">
          <!-- Conversations will be loaded here via AJAX -->
        </div>
      </div>

      <div class="chat-panel" id="chatPanel">
        <div class="empty-state">
          <i class="fas fa-comments"></i>
          <h3>Select a conversation</h3>
          <p>Choose a conversation from the left to start messaging</p>
          <p style="margin-top: 15px; font-size: 0.9rem;">Or click <strong>"New Message"</strong> to start a new conversation</p>
        </div>
      </div>
    </div>
  </main>

  <!-- New Message Modal -->
  <div class="modal" id="newMessageModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>New Message</h2>
        <button class="modal-close" onclick="closeNewMessageModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <p style="opacity: 0.7; margin-bottom: 20px;">Select a user to start messaging:</p>
      <div class="user-list" id="userList">
        <!-- Users will be loaded here -->
      </div>
    </div>
  </div>

  <script>
    const currentUserId = <?= $user_id ?>;
    const currentUserInitials = '<?= $initials ?>';
    let activeConversationUserId = <?= $selected_user_id ? $selected_user_id : 'null' ?>;
    let lastMessageId = 0;
    let pollingInterval = null;
    let selectedFile = null;

    // Load conversations
    function loadConversations() {
      fetch('api/get_conversations.php')
        .then(response => response.json())
        .then(data => {
          renderConversations(data.conversations || []);
        })
        .catch(error => console.error('Error loading conversations:', error));
    }

    // Render conversations
    function renderConversations(conversations) {
      const list = document.getElementById('conversationsList');
      
      if (conversations.length === 0) {
        list.innerHTML = `
          <div class="empty-state" style="padding: 40px 20px;">
            <i class="fas fa-inbox"></i>
            <p style="font-size: 0.9rem; margin-top: 10px;">No conversations yet</p>
          </div>
        `;
        return;
      }
      
      list.innerHTML = conversations.map(conv => `
        <a href="messages-freelancer.php?user=${conv.other_user_id}" 
           class="conversation-item ${activeConversationUserId == conv.other_user_id ? 'active' : ''}">
          <div class="conversation-avatar">${conv.initials}</div>
          <div class="conversation-details">
            <div class="conversation-header">
              <div class="conversation-name">${conv.name}</div>
              <div class="conversation-time">${conv.time_ago}</div>
            </div>
            <div class="conversation-preview">${conv.last_message}</div>
          </div>
          ${conv.unread_count > 0 ? `<div class="unread-badge">${conv.unread_count}</div>` : ''}
        </a>
      `).join('');
    }

    // Load messages for active conversation
    function loadMessages() {
      if (!activeConversationUserId) return;
      
      // Don't reload if user is actively typing
      const input = document.getElementById('messageInput');
      if (input && input === document.activeElement && input.value.length > 0) {
        return; // Skip this poll cycle if user is typing
      }
      
      fetch(`api/get_messages.php?other_user_id=${activeConversationUserId}&after_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            if (data.messages.length > 0) {
              lastMessageId = data.messages[data.messages.length - 1].id;
              renderMessages(data.messages, data.other_user);
            } else if (lastMessageId === 0) {
              // First load with no messages
              renderEmptyChat(data.other_user);
            }
          }
        })
        .catch(error => console.error('Error loading messages:', error));
    }

    // Render messages
    function renderMessages(messages, otherUser) {
      const chatPanel = document.getElementById('chatPanel');
      let container = document.getElementById('chatMessages');
      
      // Create chat UI only if it doesn't exist
      if (!container) {
        chatPanel.innerHTML = `
          <div class="chat-header">
            <div class="chat-header-info">
              <div class="user-avatar">${otherUser.initials}</div>
              <div>
                <div class="chat-user-name">${otherUser.name}</div>
                <div class="chat-user-status">Online</div>
              </div>
            </div>
          </div>
          <div class="chat-messages" id="chatMessages"></div>
          <div class="chat-input-container">
            <div id="filePreviewContainer"></div>
            <div class="chat-input-wrapper">
              <button class="attach-btn" id="attachBtn" title="Attach file">
                <i class="fas fa-paperclip"></i>
              </button>
              <input type="file" id="fileInput" style="display: none;" accept="image/*,.pdf,.doc,.docx,.txt">
              <textarea class="message-input" id="messageInput" placeholder="Type your message..." rows="1"></textarea>
              <button class="send-btn" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
              </button>
            </div>
          </div>
        `;
        
        container = document.getElementById('chatMessages');
        setupMessageInput();
      }
      
      // Remove empty state if it exists
      const emptyState = container.querySelector('.empty-state');
      if (emptyState) {
        emptyState.remove();
      }
      
      // Append only NEW messages (not already displayed)
      messages.forEach(msg => {
        // Check if message already exists
        if (document.querySelector(`[data-message-id="${msg.id}"]`)) {
          return; // Skip if already displayed
        }
        
        const messageClass = msg.sender_id == currentUserId ? 'sent' : 'received';
        const avatar = msg.sender_id == currentUserId ? currentUserInitials : otherUser.initials;
        
        const messageEl = document.createElement('div');
        messageEl.className = `message ${messageClass}`;
        messageEl.setAttribute('data-message-id', msg.id);
        messageEl.innerHTML = `
          <div class="message-avatar">${avatar}</div>
          <div class="message-content">
            <div class="message-bubble">${escapeHtml(msg.content)}</div>
            <div class="message-time">${msg.time_ago}</div>
          </div>
        `;
        container.appendChild(messageEl);
      });
      
      // Only scroll if new messages were added
      if (messages.length > 0) {
        scrollToBottom();
      }
    }

    // Render empty chat
    function renderEmptyChat(otherUser) {
      const chatPanel = document.getElementById('chatPanel');
      chatPanel.innerHTML = `
        <div class="chat-header">
          <div class="chat-header-info">
            <div class="user-avatar">${otherUser.initials}</div>
            <div>
              <div class="chat-user-name">${otherUser.name}</div>
              <div class="chat-user-status">Online</div>
            </div>
          </div>
        </div>
        <div class="chat-messages" id="chatMessages">
          <div class="empty-state">
            <i class="fas fa-comment-dots"></i>
            <h3>Start a conversation</h3>
            <p>Send a message to ${otherUser.name}</p>
          </div>
        </div>
        <div class="chat-input-container">
          <div id="filePreviewContainer"></div>
          <div class="chat-input-wrapper">
            <button class="attach-btn" id="attachBtn" title="Attach file">
              <i class="fas fa-paperclip"></i>
            </button>
            <input type="file" id="fileInput" style="display: none;" accept="image/*,.pdf,.doc,.docx,.txt">
            <textarea class="message-input" id="messageInput" placeholder="Type your message..." rows="1"></textarea>
            <button class="send-btn" id="sendBtn">
              <i class="fas fa-paper-plane"></i>
            </button>
          </div>
        </div>
      `;
      
      setupMessageInput();
    }

    // Setup message input - STABLE VERSION
    function setupMessageInput() {
      const input = document.getElementById('messageInput');
      const sendBtn = document.getElementById('sendBtn');
      const attachBtn = document.getElementById('attachBtn');
      const fileInput = document.getElementById('fileInput');
      
      if (!input || !sendBtn) return;
      
      // Remove existing listeners by replacing with clone - prevents duplicates
      const newInput = input.cloneNode(true);
      input.parentNode.replaceChild(newInput, input);
      
      const newSendBtn = sendBtn.cloneNode(true);
      sendBtn.parentNode.replaceChild(newSendBtn, sendBtn);
      
      // Get fresh references
      const messageInput = document.getElementById('messageInput');
      const sendButton = document.getElementById('sendBtn');
      
      // Auto-resize textarea
      messageInput.addEventListener('input', function(e) {
        e.stopPropagation();
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      });
      
      // Prevent any blur events from clearing
      messageInput.addEventListener('blur', function(e) {
        e.stopPropagation();
        // Don't do anything on blur - keep the text
      });
      
      // Send on Enter (without Shift)
      messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          e.stopPropagation();
          sendMessageNow();
        }
      });
      
      // Send button click - use mousedown instead of click
      sendButton.addEventListener('mousedown', function(e) {
        e.preventDefault();
        e.stopPropagation();
        sendMessageNow();
      });
      
      // Also handle regular click as backup
      sendButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
      });
      
      // File attachment
      if (attachBtn && fileInput) {
        attachBtn.addEventListener('click', function(e) {
          e.preventDefault();
          fileInput.click();
        });
        
        fileInput.addEventListener('change', function(e) {
          if (this.files.length > 0) {
            handleFileAttachment(this.files[0]);
          }
        });
      }
    }

    // Send message - separate function
    function sendMessageNow() {
      const input = document.getElementById('messageInput');
      const sendBtn = document.getElementById('sendBtn');
      
      if (!input || !sendBtn) return;
      if (sendBtn.disabled) return; // Prevent double-send
      
      const text = input.value.trim();
      
      if (!text && !selectedFile) return;
      if (!activeConversationUserId) return;
      
      // Store the text before any operations
      const messageText = text;
      
      sendBtn.disabled = true;
      
      // Create form data for file upload support
      const formData = new FormData();
      formData.append('receiver_id', activeConversationUserId);
      formData.append('content', messageText);
      if (selectedFile) {
        formData.append('file', selectedFile);
      }
      
      fetch('api/send_message.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Only clear after successful send
          input.value = '';
          input.style.height = 'auto';
          removeFileAttachment();
          lastMessageId = data.message_id;
          
          // Reload messages
          const chatMessages = document.getElementById('chatMessages');
          if (chatMessages) {
            // Remove empty state if exists
            const emptyState = chatMessages.querySelector('.empty-state');
            if (emptyState) {
              emptyState.remove();
            }
          }
          
          loadMessages();
          loadConversations();
        } else {
          alert('Error sending message: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error sending message:', error);
        alert('Error sending message');
      })
      .finally(() => {
        sendBtn.disabled = false;
        input.focus();
      });
    }

    // Handle file attachment
    function handleFileAttachment(file) {
      // Check file size (max 5MB)
      if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        return;
      }
      
      selectedFile = file;
      showFilePreview(file);
    }

    // Show file preview
    function showFilePreview(file) {
      const container = document.getElementById('filePreviewContainer');
      if (!container) return;
      
      const iconMap = {
        'image': 'fa-image',
        'pdf': 'fa-file-pdf',
        'word': 'fa-file-word',
        'text': 'fa-file-alt'
      };
      
      let iconClass = 'fa-file';
      if (file.type.startsWith('image/')) iconClass = iconMap.image;
      else if (file.type.includes('pdf')) iconClass = iconMap.pdf;
      else if (file.type.includes('word') || file.type.includes('document')) iconClass = iconMap.word;
      else if (file.type.includes('text')) iconClass = iconMap.text;
      
      container.innerHTML = `
        <div class="file-preview">
          <i class="fas ${iconClass}"></i>
          <span class="file-preview-name">${file.name}</span>
          <button class="file-preview-remove" onclick="removeFileAttachment()">
            <i class="fas fa-times"></i>
          </button>
        </div>
      `;
    }

    // Remove file attachment
    function removeFileAttachment() {
      selectedFile = null;
      const container = document.getElementById('filePreviewContainer');
      if (container) container.innerHTML = '';
      const fileInput = document.getElementById('fileInput');
      if (fileInput) fileInput.value = '';
    }

    // Send message - FIXED VERSION
    function sendMessage() {
      const input = document.getElementById('messageInput');
      const sendBtn = document.getElementById('sendBtn');
      
      if (!input || !sendBtn) return;
      
      const text = input.value.trim();
      
      if (!text && !selectedFile) return;
      if (!activeConversationUserId) return;
      
      sendBtn.disabled = true;
      
      // Create form data for file upload support
      const formData = new FormData();
      formData.append('receiver_id', activeConversationUserId);
      formData.append('content', text);
      if (selectedFile) {
        formData.append('file', selectedFile);
      }
      
      fetch('api/send_message.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          input.value = '';
          input.style.height = 'auto';
          removeFileAttachment();
          lastMessageId = data.message_id;
          loadMessages();
          loadConversations();
        } else {
          alert('Error sending message: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error sending message:', error);
        alert('Error sending message');
      })
      .finally(() => {
        sendBtn.disabled = false;
      });
    }

    // Scroll to bottom
    function scrollToBottom() {
      const container = document.getElementById('chatMessages');
      if (container) {
        container.scrollTop = container.scrollHeight;
      }
    }

    // Escape HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Start polling for new messages
    function startPolling() {
      if (pollingInterval) clearInterval(pollingInterval);
      
      pollingInterval = setInterval(() => {
        if (activeConversationUserId) {
          loadMessages();
        }
        loadConversations();
      }, 3000); // Poll every 3 seconds
    }

    // Open new message modal
    function openNewMessageModal() {
      document.getElementById('newMessageModal').classList.add('show');
      document.body.style.overflow = 'hidden';
      loadUsers();
    }

    // Close new message modal
    function closeNewMessageModal() {
      document.getElementById('newMessageModal').classList.remove('show');
      document.body.style.overflow = 'auto';
    }

    // Load users for new message
    function loadUsers() {
      fetch('api/get_users.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            renderUsers(data.users);
          }
        })
        .catch(error => console.error('Error loading users:', error));
    }

    // Render users
    function renderUsers(users) {
      const list = document.getElementById('userList');
      
      if (users.length === 0) {
        list.innerHTML = '<p style="text-align: center; opacity: 0.7;">No users available</p>';
        return;
      }
      
      list.innerHTML = users.map(user => `
        <div class="user-item" onclick="startConversation(${user.id})">
          <div class="user-item-avatar">${user.initials}</div>
          <div class="user-item-info">
            <h4>${user.name}</h4>
            <p>${user.role}</p>
          </div>
        </div>
      `).join('');
    }

    // Start conversation with selected user
    function startConversation(userId) {
      closeNewMessageModal();
      window.location.href = `messages-freelancer.php?user=${userId}`;
    }

    // Close modal on outside click
    document.getElementById('newMessageModal').addEventListener('click', function(e) {
      if (e.target.id === 'newMessageModal') {
        closeNewMessageModal();
      }
    });

    // Search conversations
    document.getElementById('searchConversations').addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const items = document.querySelectorAll('.conversation-item');
      
      items.forEach(item => {
        const name = item.querySelector('.conversation-name').textContent.toLowerCase();
        item.style.display = name.includes(searchTerm) ? 'flex' : 'none';
      });
    });

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

    // Initialize
    loadConversations();
    if (activeConversationUserId) {
      loadMessages();
      startPolling();
    }
  </script>
</body>
</html>