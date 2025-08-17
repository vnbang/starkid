<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

$action = $_GET['action'] ?? '';

// Thanh điều hướng đơn giản
?>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
	<a href="/index.php" style="color:#60a5fa">Trang chủ</a>
	<a href="/index.php?action=camera" style="color:#60a5fa">Xem Camera</a>
</div>
<?php
if ($action === 'camera') {
	include __DIR__ . '/../camera/index.php';
	return;
}
?>
<div style="color:#cbd5e1">Chọn "Xem Camera" để mở module.</div>