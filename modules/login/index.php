<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? 'Admin');
	$_SESSION['user_id'] = 1;
	$_SESSION['username'] = $username !== '' ? $username : 'Admin';
	header('Location: /index.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Đăng nhập</title>
	<style>
		body { font-family: system-ui, -apple-system, Segoe UI, Helvetica, Arial, sans-serif; margin: 0; display:flex; align-items:center; justify-content:center; min-height:100vh; background:#0f172a; color:#e2e8f0; }
		.card { background:#111827; border:1px solid #1f2937; border-radius:10px; padding:20px; width:320px; }
		label { display:block; font-size:14px; margin-bottom:6px; color:#cbd5e1; }
		input[type="text"] { width:100%; padding:10px 12px; border-radius:8px; border: 1px solid #334155; background:#0b1220; color:#e2e8f0; }
		button { width:100%; margin-top:12px; padding:10px 14px; border:0; border-radius:8px; background:#2563eb; color:white; font-weight:600; cursor:pointer; }
		.hint { font-size:12px; color:#94a3b8; margin-top:8px; }
	</style>
</head>
<body>
	<div class="card">
		<h2 style="margin:0 0 12px;font-size:18px">Đăng nhập</h2>
		<form method="post">
			<label for="username">Tên hiển thị</label>
			<input type="text" id="username" name="username" placeholder="Admin" />
			<button type="submit">Vào hệ thống</button>
			<div class="hint">Form demo: không kiểm tra mật khẩu.</div>
		</form>
	</div>
</body>
</html>