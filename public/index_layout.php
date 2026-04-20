<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// Handle Notification Read (DB-based)
if (isset($_GET['mark_read']) && isset($_SESSION['user_id'])) {
  $nid = intval($_GET['mark_read']);
  $current_user_id = $_SESSION['user_id'];
  $is_admin_role = (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')) ? 1 : 0;

  if (isset($conn)) {
    // Mark as read if it belongs to the user OR if it's a global notification (NULL user_id) and the user is an admin
    $stmtMark = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR (user_id IS NULL AND ? = 1))");
    if ($stmtMark) {
      $stmtMark->bind_param("iii", $nid, $current_user_id, $is_admin_role);
      $stmtMark->execute();
      $stmtMark->close();
    }
  }
}

// Handle Inventory Notification Read (Cookie-based for persistence across sessions, per user)
if (isset($_GET['mark_inv_read']) && isset($_SESSION['user_id'])) {
  $cookie_name = 'read_inv_notifs_' . $_SESSION['user_id'];
  $read_notifs = isset($_COOKIE[$cookie_name]) ? json_decode($_COOKIE[$cookie_name], true) : [];
  if (!is_array($read_notifs)) {
    $read_notifs = [];
  }

  // Fix relative path issue if accessed with trailing slash
  if (strpos($_SERVER['REQUEST_URI'], '/logs') !== false) {
    header("Location: logs");
    exit();
  }
  // Prevent duplicates
  if (!in_array($_GET['mark_inv_read'], $read_notifs)) {
    $read_notifs[] = $_GET['mark_inv_read'];
    // Valid for 30 days
    setcookie($cookie_name, json_encode($read_notifs), time() + (86400 * 30), "/");
    // Update current request cookie so we don't need to reload
    $_COOKIE[$cookie_name] = json_encode($read_notifs);
  }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OCNHS Medical Clinic RMS</title>
  <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dropdown.css?v=<?= time() ?>">
  <link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/dark-mode.css?v=<?= time() ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#00ACB1">

  <script>
    // Apply dark mode BEFORE render to prevent flash
    (function () {
      const excludedKeywords = ['certificate_generator', 'census', 'daily_ailments', 'pe_monitoring'];
      const currentPath = window.location.pathname.toLowerCase();
      const isExcluded = excludedKeywords.some(keyword => currentPath.includes(keyword));

      if (!isExcluded && localStorage.getItem('darkMode') === 'true') {
        document.documentElement.style.background = '#18191a';
      }
    })();
  </script>
  <?php include 'assets/inc/console_suppress.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="page-<?= str_replace('.php', '', $currentPage) ?>">
  <script>
    (function () {
      const excludedKeywords = ['certificate_generator', 'census', 'daily_ailments', 'pe_monitoring'];
      const currentPath = window.location.pathname.toLowerCase();
      const isExcluded = excludedKeywords.some(keyword => currentPath.includes(keyword));

      if (!isExcluded && localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
      }
    })();
  </script>

  <?php
  // Global Token Check for SweetAlert UI
  if (!isset($_SESSION['session_token'])) {
    echo "
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
          icon: 'error',
          title: 'Access Denied',
          text: 'Authorized token is missing. Please login to access the system.',
          confirmButtonColor: '#00ACB1',
          allowOutsideClick: false
        }).then(() => {
          window.location.href = 'index.php';
        });
      });
    </script>";
  }

  // Persistent Backup Reminder Banner (For Admins)
  if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')) {
    require_once '../config/backup_reminder.php';
    if (shouldShowBackupReminder($conn)) {
      $lastBackup = getLastBackupDate($conn);
      $daysCount = $lastBackup ? floor((time() - strtotime($lastBackup)) / (86400)) : 'many';
      echo '
      <div id="persistentBackupReminder" class="persistent-banner">
        <div class="banner-content">
          <i class="fa-solid fa-triangle-exclamation pulse-icon"></i>
          <span><strong>Monthly Backup Required:</strong> It is time for your scheduled monthly backup. This reminder will stay until a backup is completed for this month.</span>
          <a href="backup.php" class="banner-btn"><i class="fa-solid fa-download"></i> Go to Backup</a>
        </div>
      </div>';
    }
  }
  ?>

  <header>
    <div class="logo">
      <a href="student.php"> <img src="assets/img/LOGO.png" alt="Logo"></a>
    </div>

    <div class="header-shape"></div>

    <?php
    // Initialize notifications
    $notifications = [];
    $hasNotifications = false;
    $notificationCount = 0;

    // Get read notifications from cookie (User-Specific)
    $read_inv_ids = [];
    if (isset($_SESSION['user_id'])) {
      $cookie_name = 'read_inv_notifs_' . $_SESSION['user_id'];
      $read_inv_ids = isset($_COOKIE[$cookie_name]) ? json_decode($_COOKIE[$cookie_name], true) : [];
    }
    if (!is_array($read_inv_ids))
      $read_inv_ids = [];

    // 1. Treatment Schedule Notifications (For All Users)
    // Check for schedules today
    $today = date('Y-m-d');
    $currentTime = time();


    $checkTables = [
      ['table' => 'students', 'type' => 'student'],
      ['table' => 'employees', 'type' => 'employee']
    ];

    foreach ($checkTables as $tData) {
      $table = $tData['table'];
      $notifType = $tData['type'];

      $schedSql = "SELECT id, name, treatment_logs_json FROM $table WHERE treatment_logs_json IS NOT NULL AND treatment_logs_json != '[]' AND is_archived = 0";
      $schedRes = $conn->query($schedSql);

      if ($schedRes) {
        while ($row = $schedRes->fetch_assoc()) {
          $logs = json_decode($row['treatment_logs_json'], true);
          if (is_array($logs)) {
            foreach ($logs as $log) {
              if (isset($log['next_visit']) && !empty($log['next_visit'])) {
                $visitTimestamp = strtotime($log['next_visit']);
                $visitDate = date('Y-m-d', $visitTimestamp);

                // Show all schedules for TODAY and UPCOMING (Next 7 Days)
                $futureDate = date('Y-m-d', strtotime('+7 days'));

                $isToday = ($visitDate === $today);
                $isUpcoming = ($visitDate > $today && $visitDate <= $futureDate);

                $isPast = $visitTimestamp < $currentTime;

                if (($isToday || $isUpcoming) && !$isPast) {
                  $timeStr = date('M d, h:i A', $visitTimestamp);

                  if ($isToday) {
                    $status = $isPast ? "(Done)" : "(Today)";
                    $title = "Treatment Schedule Today";
                    $color = $isPast ? '#95a5a6' : '#f39c12';
                    $icon = 'fa-calendar-day';
                    $timeDisplay = 'Today';
                  } else {
                    $status = "(Upcoming)";
                    $title = "Upcoming Treatment";
                    $color = '#3498db'; // Blue
                    $icon = 'fa-calendar-plus';
                    $timeDisplay = date('M d', $visitTimestamp);
                  }

                  $notifications[] = [
                    'type' => 'schedule',
                    'title' => $title,
                    'message' => "Patient: " . $row['name'] . " (" . ucfirst($notifType) . ") @ " . $timeStr . " " . $status,
                    'link' => 'view_treatment.php?view_id=' . $row['id'] . '&type=' . $notifType,
                    'time' => $timeDisplay,
                    'icon' => $icon,
                    'color' => $color
                  ];
                }
              }
            }
          }
        }
      }
    }

    // 1.5. Inventory Notifications (Real-time checks)
    // Low Stock
    $invLowSql = "SELECT id, name, quantity, unit FROM inventory_items WHERE quantity <= 10 AND quantity > 0";
    $invLowRes = $conn->query($invLowSql);
    if ($invLowRes) {
      while ($row = $invLowRes->fetch_assoc()) {
        $nid = 'low_' . $row['id'];
        if (in_array($nid, $read_inv_ids))
          continue;

        $notifications[] = [
          'type' => 'inventory_low',
          'title' => 'Low Stock Alert',
          'message' => $row['name'] . " is running low (" . $row['quantity'] . " " . $row['unit'] . " left)",
          'link' => 'inventory.php?status=low_stock&mark_inv_read=' . $nid, // Append read flag
          'time' => 'Action Needed',
          'icon' => 'fa-triangle-exclamation',
          'color' => '#ff9800' // Orange
        ];
      }
    }

    // Expired Items
    $invExpSql = "SELECT id, name, expiry_date FROM inventory_items WHERE expiry_date IS NOT NULL AND expiry_date <= CURDATE()";
    $invExpRes = $conn->query($invExpSql);
    if ($invExpRes) {
      while ($row = $invExpRes->fetch_assoc()) {
        $nid = 'exp_' . $row['id'];
        if (in_array($nid, $read_inv_ids))
          continue;

        $notifications[] = [
          'type' => 'inventory_expired',
          'title' => 'Item Expired',
          'message' => $row['name'] . " expired on " . date('M d, Y', strtotime($row['expiry_date'])),
          'link' => 'inventory.php?status=expired&mark_inv_read=' . $nid, // Append read flag
          'time' => 'Urgent',
          'icon' => 'fa-ban',
          'color' => '#f44336' // Red
        ];
      }
    }

    // 2. System Notifications (DB-based)
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $isAdmin = (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin'));

    // A. Backup Reminder (Admin Only)
    if ($isAdmin) {
      require_once '../config/backup_reminder.php';

      if (shouldShowBackupReminder($conn)) {
        $lastBackup = getLastBackupDate($conn);
        $backupMsg = $lastBackup ? "Last backup: " . date('M d, Y', strtotime($lastBackup)) : "No backup created yet";
        $notifications[] = [
          'type' => 'backup',
          'title' => 'Database Backup Required',
          'message' => $backupMsg,
          'link' => 'backup.php',
          'time' => 'Monthly Reminder',
          'icon' => 'fa-triangle-exclamation',
          'color' => '#ff6b6b'
        ];
      }
    }

    // B. Database Notifications (Registration, Security, etc.)
    // Ensure table exists (safe check)
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, message TEXT NOT NULL, link VARCHAR(255) DEFAULT NULL, is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id))");

    if ($currentUserId) {
      // Fetch personal notifications (user_id = ID) AND Global/Admin notifications (user_id IS NULL) if Admin
      // Non-admins only see their own (user_id = ID)
      $sql = "SELECT * FROM notifications WHERE is_read = 0 AND (user_id = ?";
      if ($isAdmin) {
        $sql .= " OR user_id IS NULL";
      }
      $sql .= ") ORDER BY created_at DESC LIMIT 10";

      $stmtNotif = $conn->prepare($sql);
      // Bind ID only. If isAdmin, the OR clause doesn't need param.
      $stmtNotif->bind_param("i", $currentUserId);
      $stmtNotif->execute();
      $notifRes = $stmtNotif->get_result();

      if ($notifRes) {
        while ($row = $notifRes->fetch_assoc()) {
          $icon = 'fa-bell';
          $color = '#00ACB1';
          $title = 'Notification';

          // Customize based on type
          if ($row['type'] === 'security') {
            $icon = 'fa-shield-halved';
            $color = '#e74c3c'; // Red
            $title = 'Security Alert';
          } elseif ($row['type'] === 'registration') {
            $icon = 'fa-user-plus';
            $color = '#00ACB1';
            $title = 'New Registration';
          }

          $link = $row['link'] ? ($row['link'] . (strpos($row['link'], '?') !== false ? '&' : '?') . 'mark_read=' . $row['id']) : '#';

          $notifications[] = [
            'type' => $row['type'],
            'title' => $title,
            'message' => $row['message'],
            'link' => $link,
            'time' => date('M d, H:i', strtotime($row['created_at'])),
            'icon' => $icon,
            'color' => $color
          ];
        }
      }
    }

    $notificationCount = count($notifications);
    $hasNotifications = $notificationCount > 0;

    $hasClearableNotifs = false;
    foreach ($notifications as $n) {
      if (!in_array($n['type'], ['schedule', 'backup'])) {
        $hasClearableNotifs = true;
        break;
      }
    }

    $resetNotification = false;

    // Check if backup was just created
    if (isset($_SESSION['backup_created'])) {
      $resetNotification = true;
      unset($_SESSION['backup_created']);
    }
    ?>

    <?php if ($resetNotification): ?>
      <script>
        // Reset notification badge when backup is created
        localStorage.removeItem('backupNotificationRead');
      </script>
    <?php endif; ?>

    <!-- Header Right Section: Notification + User Dropdown -->
    <div class="header-right">
      <!-- Dark Mode Toggle -->
      <button class="dark-mode-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
        <i class="fa-solid fa-moon" id="darkModeIcon"></i>
      </button>

      <!-- Notification Bell (Visible to All) -->
      <div class="notification-icon" onclick="toggleNotificationMenu(event)">
        <i class="fa-solid fa-bell"></i>
        <?php if ($hasNotifications): ?>
          <span class="notification-badge"><?= $notificationCount ?></span>
        <?php endif; ?>

        <div class="notification-dropdown" id="notificationMenu">
          <div class="notification-header">
            <div>
              <strong>Notifications</strong>
              <?php if ($hasNotifications): ?>
                <span class="badge-count"><?= $notificationCount ?></span>
              <?php endif; ?>
            </div>
            <?php if ($hasClearableNotifs): ?>
              <button onclick="markAllNotificationsRead(event)" class="mark-all-btn" title="Clear notifications">
                <i class="fa-solid fa-broom"></i> Clear Notif
              </button>
            <?php endif; ?>
          </div>
          <div class="notification-list">
            <div class="notification-list" id="notifListWrapper">
              <?php if ($hasNotifications): ?>
                <?php foreach ($notifications as $notif): ?>
                  <a href="<?= htmlspecialchars($notif['link']) ?>" class="notification-item"
                    data-type="<?= htmlspecialchars($notif['type']) ?>">
                    <div class="notif-icon" style="background: <?= $notif['color'] ?>; color: white;">
                      <i class="fa-solid <?= $notif['icon'] ?>"></i>
                    </div>
                    <div class="notif-content">
                      <strong><?= htmlspecialchars($notif['title']) ?></strong>
                      <p><?= htmlspecialchars($notif['message']) ?></p>
                      <span class="notif-time"><?= $notif['time'] ?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="notification-empty">
                  <i class="fa-solid fa-check-circle"></i>
                  <p>All caught up!</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- User Dropdown -->
      <div class="user-dropdown" onclick="toggleUserMenu()">
        <div class="user-info">
          <div style="position: relative; display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-circle-user" style="font-size: 1.5rem;"></i>
            <span id="statusDot" title="Online"
              style="position: absolute; bottom: -2px; right: -2px; width: 10px; height: 10px; border-radius: 50%; background: #2ecc71; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.1); transition: 0.3s;"></span>
          </div>
          <span><?= isset($_SESSION['username']) ? htmlspecialchars(strtoupper($_SESSION['username'])) : 'ADMIN' ?></span>
          <i class="fa-solid fa-caret-down"></i>
        </div>
        <div class="dropdown-menu" id="userMenu">
          <a href="profile.php"><i class="fa-solid fa-user-gear"></i> PROFILE</a>
          <a href="change_password.php"><i class="fa-solid fa-key"></i> PASSWORD</a>
          <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
            <a href="archive_list.php"><i class="fa-solid fa-box-archive"></i> ARCHIVE LIST</a>
          <?php endif; ?>
          <hr>
          <a href="#" id="installAppBtn" style="display: block; color: #00ACB1; font-weight: bold;">
            <i class="fa-solid fa-cloud-arrow-down"></i> INSTALL APP
          </a>
          <a href="signout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> SIGN OUT</a>
        </div>
      </div>
    </div>
  </header>

  <style>
    /* Persistent Banner Styling */
    .persistent-banner {
      background: linear-gradient(135deg, #ff6b6b, #ee5253);
      color: white;
      padding: 12px 20px;
      text-align: center;
      position: sticky;
      top: 0;
      z-index: 9999;
      box-shadow: 0 4px 15px rgba(238, 82, 83, 0.3);
      animation: slideDownIn 0.5s ease-out;
    }

    .banner-content {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 15px;
      max-width: 1200px;
      margin: 0 auto;
      font-size: 0.95rem;
    }

    .banner-btn {
      background: white;
      color: #ee5253;
      padding: 6px 15px;
      border-radius: 20px;
      text-decoration: none;
      font-weight: bold;
      font-size: 0.85rem;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .banner-btn:hover {
      background: #f1f1f1;
      transform: translateY(-2px);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .pulse-icon {
      animation: pulse 2s infinite;
      font-size: 1.2rem;
    }

    @keyframes slideDownIn {
      from {
        transform: translateY(-100%);
      }

      to {
        transform: translateY(0);
      }
    }

    @keyframes pulse {
      0% {
        transform: scale(1);
        opacity: 1;
      }

      50% {
        transform: scale(1.2);
        opacity: 0.8;
      }

      100% {
        transform: scale(1);
        opacity: 1;
      }
    }

    /* Adjust header position if banner is present */
    body:has(.persistent-banner) header {
      top: 45px;
      /* Adjust according to banner height */
    }

    body.dark-mode .persistent-banner {
      background: linear-gradient(135deg, #c0392b, #96281b);
    }

    /* Notification Bell Icon */
    .notification-icon {
      position: relative;
      margin-right: 20px;
      cursor: pointer;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.3s ease;
    }

    .notification-icon:hover {
      background: rgba(0, 172, 177, 0.1);
    }

    .notification-icon i.fa-bell {
      color: #00ACB1;
      font-size: 1.4rem;
      transition: all 0.3s ease;
    }

    .notification-icon:hover i.fa-bell {
      transform: rotate(15deg);
      color: #008e91;
    }

    .notification-badge {
      position: absolute;
      top: 2px;
      right: 2px;
      background: #ff4757;
      color: white;
      font-size: 0.6rem;
      font-weight: 800;
      padding: 0;
      border-radius: 50%;
      width: 18px;
      height: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid white;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.15);
      }
    }

    .notification-dropdown {
      position: absolute;
      top: calc(100% + 15px);
      right: 0;
      background: white;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
      width: 360px;
      max-height: 450px;
      overflow: hidden;
      opacity: 0;
      visibility: hidden;
      transform: translateY(-10px);
      transition: all 0.3s ease;
      z-index: 1000;
    }

    .notification-dropdown.show {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .notification-header {
      padding: 15px 18px;
      background: linear-gradient(135deg, #00ACB1 0%, #008e91 100%);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-radius: 12px 12px 0 0;
    }

    .mark-all-btn {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      font-size: 0.8rem;
      padding: 4px 8px;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 5px;
      transition: background 0.2s;
    }

    .mark-all-btn:hover {
      background: rgba(255, 255, 255, 0.4);
    }

    border-radius: 12px 12px 0 0;
    }

    .notification-header strong {
      font-size: 1.05rem;
    }

    .badge-count {
      background: rgba(255, 255, 255, 0.3);
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .notification-list {
      max-height: 380px;
      overflow-y: auto;
    }

    .notification-item {
      display: flex;
      gap: 12px;
      padding: 14px 18px;
      border-bottom: 1px solid #f0f0f0;
      text-decoration: none;
      color: #333;
      transition: background 0.2s ease;
      background: #fff8e1;
    }

    .notification-item:hover {
      background: #fff3cd;
    }

    .notif-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 1.1rem;
    }

    .notif-content {
      flex: 1;
    }

    .notif-content strong {
      display: block;
      margin-bottom: 4px;
      font-size: 0.92rem;
      color: #333;
    }

    .notif-content p {
      margin: 0 0 5px 0;
      font-size: 0.82rem;
      color: #666;
      line-height: 1.3;
    }

    .notif-time {
      font-size: 0.72rem;
      color: #999;
    }

    .notification-empty {
      padding: 50px 20px;
      text-align: center;
      color: #999;
    }

    .notification-empty i {
      font-size: 3.5rem;
      color: #00ACB1;
      margin-bottom: 12px;
    }

    .notification-empty p {
      margin: 0;
      font-size: 0.9rem;
    }

    @media (max-width: 768px) {
      .notification-dropdown {
        width: 300px;
      }
    }
  </style>

  <script>
    // Check if notification has been read
    function isNotificationRead() {
      return localStorage.getItem('backupNotificationRead') === 'true';
    }

    // Mark notification as read
    function markNotificationAsRead() {
      localStorage.setItem('backupNotificationRead', 'true');
      hideNotificationBadge();
    }

    // Hide notification badge
    function hideNotificationBadge() {
      const badge = document.querySelector('.notification-badge');
      const badgeCount = document.querySelector('.badge-count');
      if (badge) {
        badge.style.display = 'none';
      }
      if (badgeCount) {
        badgeCount.style.display = 'none';
      }
    }

    // Clear/Reset notification (for testing or when new backup is created)
    function clearNotification() {
      localStorage.removeItem('backupNotificationRead');
      location.reload();
    }

    function toggleNotificationMenu(event) {
      event.stopPropagation();
      const dropdown = document.getElementById('notificationMenu');
      const userMenu = document.getElementById('userMenu');

      // Close user menu if open
      if (userMenu && userMenu.classList.contains('show')) {
        userMenu.classList.remove('show');
      }

      dropdown.classList.toggle('show');

      // Auto-mark read disabled
    }

    function toggleUserMenu() {
      const menu = document.getElementById('userMenu');
      const notifMenu = document.getElementById('notificationMenu');

      // Close notification menu if open
      if (notifMenu && notifMenu.classList.contains('show')) {
        notifMenu.classList.remove('show');
      }

      menu.classList.toggle('show');
      event.stopPropagation();
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (event) {
      const userMenu = document.getElementById('userMenu');
      const notifMenu = document.getElementById('notificationMenu');

      if (userMenu && !event.target.closest('.user-dropdown')) {
        userMenu.classList.remove('show');
      }

      if (notifMenu && !event.target.closest('.notification-icon')) {
        notifMenu.classList.remove('show');
      }
    });

    // When notification item is clicked, mark as read
    document.addEventListener('DOMContentLoaded', function () {
      const notifItems = document.querySelectorAll('.notification-item');
      notifItems.forEach(item => {
        item.addEventListener('click', function () {
          markNotificationAsRead();
        });
      });

      // Auto-hide disabled
    });
  </script>
  <nav>
    <a href="dashboard" class="<?= ($currentPage == 'dashboard.php' || $currentPage == 'dashboard') ? 'active' : '' ?>">
      <span>Dashboard</span>
    </a>

    <a href="student" class="<?= ($currentPage == 'student.php' || $currentPage == 'student') ? 'active' : '' ?>">
      <span>Students Records</span>
    </a>

    <a href="employees" class="<?= ($currentPage == 'employees.php' || $currentPage == 'employees') ? 'active' : '' ?>">
      <span>Employee Records</span>
    </a>

    <a href="others" class="<?= ($currentPage == 'others.php' || $currentPage == 'others') ? 'active' : '' ?>">
      <span>Others Records</span>
    </a>

    <a href="inventory" class="<?= ($currentPage == 'inventory.php' || $currentPage == 'inventory') ? 'active' : '' ?>">
      <span>Inventory</span>
    </a>

    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
      <a href="logs" class="<?= ($currentPage == 'logs.php' || $currentPage == 'logs') ? 'active' : '' ?>">
        <span>Activity Logs</span>
      </a>

      <a href="users" class="<?= ($currentPage == 'users.php' || $currentPage == 'users') ? 'active' : '' ?>">
        <span>User Management</span>
      </a>

      <a href="merge_tool"
        class="<?= ($currentPage == 'merge_tool.php' || $currentPage == 'merge_tool') ? 'active' : '' ?>">
        <span>Merge Records</span>
      </a>

      <a href="backup" class="<?= ($currentPage == 'backup.php' || $currentPage == 'backup') ? 'active' : '' ?>">
        <span>Backup Recovery</span>
      </a>
    <?php endif; ?>

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'doctor'): ?>
      <a href="certificate_generator.php" class="<?= ($currentPage == 'certificate_generator.php') ? 'active' : '' ?>">
        <span>Certificate Generator</span>
      </a>
    <?php endif; ?>

  </nav>

  <!-- PWA Install Prompt Logic -->
  <script>
    let deferredPrompt;
    const installBtn = document.getElementById('installAppBtn');

    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredPrompt = e;
    });

    if (installBtn) {
      installBtn.addEventListener('click', async (e) => {
        e.preventDefault();

        // If the automatic prompt is ready (usually works on localhost or HTTPS)
        if (deferredPrompt) {
          deferredPrompt.prompt();
          const { outcome } = await deferredPrompt.userChoice;
          if (outcome === 'accepted') {
            installBtn.style.display = 'none';
          }
          deferredPrompt = null;
        } else {
          // Fallback for Local Network (HTTP IPs) where automatic prompt is blocked by Chrome
          // Fallback for Local Network (HTTP IPs) where automatic prompt is blocked by Chrome
          Swal.fire({
            title: '<i class="fa-solid fa-download" style="color: #00ACB1; margin-right: 10px;"></i> Install Application',
            width: 500,
            html: `
              <div style="text-align: left; font-size: 0.95rem; line-height: 1.6; color: #444;">
                <p style="margin-top: 0; color: #666;">For local network environments, please follow these simple steps to install the app on your device for offline support and faster access.</p>
                
                <h4 style="color: #333; margin-top: 20px; border-bottom: 2px solid #eee; padding-bottom: 5px;"><i class="fa-brands fa-chrome" style="color: #4285F4; margin-right: 5px;"></i> Google Chrome / Edge</h4>
                <ul style="padding-left: 20px; margin-bottom: 20px;">
                  <li>Click the Browser Menu (<i class="fa-solid fa-ellipsis-vertical"></i>) on the top-right.</li>
                  <li>Select <b>"Add to Home screen"</b> or <b>"Install app"</b>.</li>
                  <li>Click <b>Install</b> to finalize.</li>
                </ul>
                
                <h4 style="color: #333; margin-top: 15px; border-bottom: 2px solid #eee; padding-bottom: 5px;"><i class="fa-brands fa-safari" style="color: #007AFF; margin-right: 5px;"></i> Apple Safari (iOS / iPhone)</h4>
                <ul style="padding-left: 20px; margin-bottom: 20px;">
                  <li>Tap the Share icon (<i class="fa-solid fa-arrow-up-from-bracket"></i>) at the bottom.</li>
                  <li>Scroll down and select <b>"Add to Home Screen"</b>.</li>
                  <li>Tap <b>Add</b> to finalize.</li>
                </ul>

                <div style="background: #eef9f9; padding: 15px; border-radius: 8px; border: 1px solid #c2ecee; margin-top: 25px;">
                  <h4 style="color: #008f94; margin-top: 0; margin-bottom: 10px; font-weight: bold;"><i class="fa-solid fa-wifi" style="margin-right: 5px;"></i> Offline Mode Guidelines</h4>
                  <ul style="padding-left: 20px; font-size: 0.9rem; color: #555; margin-bottom: 0;">
                    <li style="margin-bottom: 8px;"><b>Login first:</b> You must be logged into your account while connected to the internet before offline mode can work.</li>
                    <li style="margin-bottom: 8px;"><b>Visit to Cache:</b> Briefly open the pages you need (e.g., Student Records) while online. The system will only work offline for pages you have already visited.</li>
                    <li><b>Auto-Sync:</b> All offline data is saved to your device. Keep the app open once the connection is restored, and it will sync automatically.</li>
                  </ul>
                </div>
              </div>
            `,
            showCloseButton: true,
            confirmButtonColor: '#00ACB1',
            confirmButtonText: 'Got It!',
            customClass: {
              title: 'install-swal-title'
            }
          });
        }
      });
    }

    // Hide button if app is already installed
    window.addEventListener('appinstalled', (evt) => {
      if (installBtn) installBtn.style.display = 'none';
    });

    // Check if running in standalone mode (already installed)
    if (window.matchMedia('(display-mode: standalone)').matches) {
      if (installBtn) installBtn.style.display = 'none';
    }
  </script>


  <!-- Session Check Script -->
  <script>
    setInterval(function () {
      // Add timestamp to prevent caching
      fetch('check_session.php?t=' + new Date().getTime(), {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => response.json())
        .then(data => {
          if (data.valid === false) {
            // Only show alert if it's a concurrent login (security related)
            // As per user request: "kapag hindi nagalaw naman yung account dapat walang lalabas na ganyan"
            if (data.reason === 'concurrent_login') {
              if (!Swal.isVisible()) {
                Swal.fire({
                  title: 'Account Logged In Elsewhere',
                  text: 'You have been logged out because your account was logged in on another device.',
                  icon: 'warning',
                  confirmButtonText: 'Login Again',
                  confirmButtonColor: '#00ACB1',
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                }).then((result) => {
                  if (result.isConfirmed) {
                    window.location.href = 'index.php?reason=concurrent_login';
                  }
                });
              }
            } else if (data.reason === 'inactivity') {
              // Silent fail for inactivity as requested
              // User will be redirected on their next interaction anyway by db.php
            }
          }
        })
        .catch(error => {
          // Silently fail if network error
        });
    }, 1000); // Check every 1 second
  </script>

  <!-- Dark Mode Script -->
  <script>
    function toggleDarkMode() {
      const excludedKeywords = ['certificate_generator', 'census', 'daily_ailments', 'pe_monitoring'];
      const currentPath = window.location.pathname.toLowerCase();
      if (excludedKeywords.some(kw => currentPath.includes(kw))) return;

      if (document.body.classList.contains('dark-mode')) {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('darkMode', 'false');
        document.documentElement.style.background = '';
      } else {
        document.body.classList.add('dark-mode');
        document.documentElement.style.background = '#18191a';
        localStorage.setItem('darkMode', 'true');
      }

      const isDarkPref = localStorage.getItem('darkMode') === 'true';
      const icon = document.getElementById('darkModeIcon');
      if (icon) {
        icon.classList.toggle('fa-moon', !isDarkPref);
        icon.classList.toggle('fa-sun', isDarkPref);
      }
    }

    // Update icon on load (body class already applied by inline script)
    document.addEventListener('DOMContentLoaded', function () {
      const isDarkPref = localStorage.getItem('darkMode') === 'true';
      const icon = document.getElementById('darkModeIcon');
      if (icon) {
        icon.classList.toggle('fa-moon', !isDarkPref);
        icon.classList.toggle('fa-sun', isDarkPref);
      }
    });

    // Logout Confirmation
    const logoutLinks = document.querySelectorAll('.logout-link'); // Changed to querySelectorAll in case of multiple
    logoutLinks.forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        Swal.fire({
          title: 'Sign Out?',
          text: "Are you sure you want to end your session?",
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#00ACB1',
          cancelButtonColor: '#666',
          confirmButtonText: 'Yes, Sign Out'
        }).then((result) => {
          if (result.isConfirmed) {
            // Create a form to submit via POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'signout.php'; // Or logout (via .htaccess)
            document.body.appendChild(form);
            form.submit();
          }
        });
      });
    });
  </script>

  <?php if (isset($_SESSION['login_welcome'])): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
          title: 'Welcome Back!',
          text: 'Hello, <?= htmlspecialchars(ucfirst($_SESSION['username'])) ?>! You have successfully logged in.',
          icon: 'success',
          confirmButtonText: 'Continue',
          confirmButtonColor: '#00ACB1',
          timer: 3000,
          timerProgressBar: true
        });
      });
    </script>
    <?php unset($_SESSION['login_welcome']); ?>
  <?php endif; ?>

  <script>
    function markAllNotificationsRead(e) {
      if (e) e.stopPropagation();
      fetch('api/mark_all_read', {
        method: 'POST'
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            let remainingCount = 0;

            // Remove clearable notifications only
            document.querySelectorAll('.notification-item').forEach(item => {
              const type = item.getAttribute('data-type');
              if (type !== 'schedule' && type !== 'backup') {
                item.remove();
              } else {
                remainingCount++;
              }
            });

            // Update badges
            const badges = document.querySelectorAll('.badge-count, .notification-badge');
            if (remainingCount > 0) {
              badges.forEach(b => b.innerText = remainingCount);
            } else {
              badges.forEach(b => b.remove());
              const notifWrapper = document.getElementById('notifListWrapper');
              if (notifWrapper) {
                notifWrapper.innerHTML = `
                  <div class="notification-empty">
                    <i class="fa-solid fa-check-circle"></i>
                    <p>All caught up!</p>
                  </div>
                `;
              }
            }

            // Remove the 'Clear Notif' button
            const clearBtn = document.querySelector('.mark-all-btn');
            if (clearBtn) clearBtn.remove();
          }
        })
        .catch(error => {
          console.error("Error clearing notifications", error);
        });
    }
  </script>

  <!-- PWA & Offline Handler -->
  <script src="assets/js/offline_handler.js"></script>
  <script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
          .then(reg => console.log('Service Worker registered.'))
          .catch(err => console.log('Service Worker registration failed:', err));
      });
    }

    // Intercept form submissions when offline
    document.addEventListener('submit', (e) => {
      if (!navigator.onLine && e.target.tagName === 'FORM') {
        const form = e.target;
        let action = form.getAttribute('action');
        let currentUrl = window.location.pathname + window.location.search;

        // If form has no explicit action (like medical_records.php), use current URL
        if (!action) {
          action = currentUrl;
        }

        // Intercept data-entry forms (add/edit) AND file upload forms (medical_records, consent)
        if (action && (action.includes('add_') || action.includes('edit_') || action.includes('medical_records') || action.includes('consent'))) {
          e.preventDefault();
          const formData = new FormData(form);
          let type = action.split('?')[0].split('/').pop().split('.')[0];
          if (!type) type = 'file_upload';

          window.ClinicSync.save(action, formData, type).then(() => {
            // Close any open modals
            const openModal = document.querySelector('.modal-overlay[style*="display: flex"]') ||
              document.querySelector('.modal-overlay[style*="display: block"]');
            if (openModal) openModal.style.display = 'none';

            // Success feedback
            Swal.fire({
              title: 'Data Saved (Offline)',
              text: 'You are currently offline. Your data has been saved locally and will be automatically uploaded once you reconnect to the server.',
              icon: 'warning',
              confirmButtonColor: '#00ACB1'
            });
          });
        }
      }
    });
  </script>