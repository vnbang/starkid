<?php
// Bắt đầu output buffering để tránh lỗi headers already sent
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session nếu chưa có
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect nếu chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: /modules/login/index.php');
    exit();
}

// Kết nối CSDL
include_once __DIR__ . '/includes/db.php';

// Lấy thông tin người dùng
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Admin';
$avatar = $_SESSION['user_avatar'] ?? '';
$avatar_dir = 'public/uploads/avatars/';
$avatar_path = !empty($avatar) ? $avatar_dir . $avatar : $avatar_dir . 'default.png';
$avatar_url = htmlspecialchars($avatar_path . '?v=' . time());
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starkid Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="/public/css/responsive.css">
    <link rel="stylesheet" href="/public/css/dynamic_styles.php?v=<?= time() ?>">
    <link rel="stylesheet" href="/public/css/styles.css">
    <style>
        .avatar-banner {
            cursor: pointer;
            border-radius: 50%;
            width: 40px;
            height: 40px;
        }
        .hidden-file-input {
            display: none;
        }
        body, html {
            height: 100%;
            margin: 0;
        }
        .layout-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .body-wrapper {
            flex: 1;
            display: flex;
        }
        main {
            flex: 1;
            padding: 32px 24px;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Header -->
        <header>
            <?php if (file_exists(__DIR__ . '/modules/header/index.php')) include __DIR__ . '/modules/header/index.php'; ?>
        </header>
        <div class="body-wrapper">
            <!-- Sidebar -->
            <aside>
                <?php if (file_exists(__DIR__ . '/modules/sidebar/index.php')) include __DIR__ . '/modules/sidebar/index.php'; ?>
            </aside>
            <!-- Main Content -->
            <main>
                <?php if (file_exists(__DIR__ . '/modules/maincontent/index.php')) include __DIR__ . '/modules/maincontent/index.php'; ?>
            </main>
        </div>
        <!-- Footer -->
        <footer>
            <?php if (file_exists(__DIR__ . '/modules/footer/index.php')) include __DIR__ . '/modules/footer/index.php'; ?>
        </footer>
    </div>
    <script src="/public/js/script.js"></script>
</body>
</html>
<?php
// Kết thúc output buffering sau khi xuất toàn bộ nội dung
ob_end_flush();
?>
