<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Nếu hệ thống có cơ chế đăng nhập, có thể bật kiểm tra này
// if (!isset($_SESSION['user_id'])) { header('Location: /modules/login/index.php'); exit(); }

$projectRoot = realpath(__DIR__ . '/../../');
$hlsBaseDir = $projectRoot . '/public/hls';
$logsBaseDir = $projectRoot . '/logs';

if (!is_dir($hlsBaseDir)) {
    @mkdir($hlsBaseDir, 0775, true);
}
if (!is_dir($logsBaseDir)) {
    @mkdir($logsBaseDir, 0775, true);
}

function sanitizeStreamKey(string $key): string {
    $key = trim($key);
    $key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    if ($key === '' || $key === null) {
        $key = 'stream_' . date('Ymd_His');
    }
    return $key;
}

function getStreamPaths(string $streamKey): array {
    global $hlsBaseDir, $logsBaseDir;
    $streamDir = $hlsBaseDir . '/' . $streamKey;
    $playlist = $streamDir . '/index.m3u8';
    $segmentPattern = $streamDir . '/%03d.ts';
    $logFile = $logsBaseDir . '/stream_' . $streamKey . '.log';
    $pidFile = $logsBaseDir . '/stream_' . $streamKey . '.pid';
    return [
        'dir' => $streamDir,
        'playlist' => $playlist,
        'segment' => $segmentPattern,
        'log' => $logFile,
        'pid' => $pidFile,
    ];
}

function isProcessRunning(int $pid): bool {
    if ($pid <= 0) return false;
    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }
    return file_exists('/proc/' . $pid);
}

function startStream(string $streamKey, string $rtspUrl, array $options = []): array {
    $streamKey = sanitizeStreamKey($streamKey);
    $paths = getStreamPaths($streamKey);

    if (!is_dir($paths['dir'])) {
        @mkdir($paths['dir'], 0775, true);
    }

    // Dọn các segment cũ nếu có
    foreach (glob($paths['dir'] . '/*') ?: [] as $oldFile) {
        @unlink($oldFile);
    }

    $rtspTransport = $options['rtsp_transport'] ?? 'tcp';
    $copyVideo = $options['copy_video'] ?? true; // copy codec H.264 nếu có
    $includeAudio = $options['audio'] ?? false; // mặc định tắt audio cho ổn định
    $hlsTime = (int)($options['hls_time'] ?? 2);
    $hlsListSize = (int)($options['hls_list_size'] ?? 6);

    $videoCodec = $copyVideo ? '-c:v copy' : '-c:v libx264 -preset veryfast -tune zerolatency -pix_fmt yuv420p -g ' . (int)($hlsListSize * $hlsTime * 2);
    $audioCodec = $includeAudio ? '-c:a aac -b:a 128k -ar 44100' : '-an';

    $ffmpeg = 'ffmpeg';
    $safeRtsp = escapeshellarg($rtspUrl);
    $safePlaylist = escapeshellarg($paths['playlist']);
    $safeSegment = escapeshellarg($paths['segment']);
    $safeLog = escapeshellarg($paths['log']);

    $cmd = "{$ffmpeg} -nostdin -rtsp_transport {$rtspTransport} -stimeout 15000000 -i {$safeRtsp} ";
    $cmd .= "-fflags nobuffer -flags low_delay -frag_duration 2000000 ";
    $cmd .= "$videoCodec $audioCodec -f hls -hls_time {$hlsTime} -hls_list_size {$hlsListSize} ";
    $cmd .= "-hls_flags delete_segments+append_list+program_date_time -hls_segment_type mpegts ";
    $cmd .= "-hls_segment_filename {$safeSegment} {$safePlaylist}";

    // Chạy nền và lấy PID của chính tiến trình FFmpeg
    $background = 'bash -c ' . escapeshellarg("nohup {$cmd} > {$paths['log']} 2>&1 & echo \\$!") . ' 2>/dev/null';
    $pid = (int)trim(shell_exec($background));

    if ($pid > 0) {
        file_put_contents($paths['pid'], (string)$pid);
        return ['ok' => true, 'pid' => $pid, 'streamKey' => $streamKey, 'message' => 'Started'];
    }

    return ['ok' => false, 'message' => 'Không thể khởi động FFmpeg. Xem log: ' . $paths['log']];
}

function stopStream(string $streamKey): array {
    $streamKey = sanitizeStreamKey($streamKey);
    $paths = getStreamPaths($streamKey);
    if (!file_exists($paths['pid'])) {
        return ['ok' => true, 'message' => 'Không có tiến trình đang chạy'];
    }
    $pid = (int)trim(@file_get_contents($paths['pid']));
    if ($pid > 0 && isProcessRunning($pid)) {
        @shell_exec('kill ' . (int)$pid . ' 2>/dev/null');
        // Chờ dừng trong chốc lát, nếu chưa thì KILL
        usleep(300000);
        if (isProcessRunning($pid)) {
            @shell_exec('kill -9 ' . (int)$pid . ' 2>/dev/null');
        }
    }
    @unlink($paths['pid']);
    return ['ok' => true, 'message' => 'Đã dừng stream'];
}

function listStreams(): array {
    global $logsBaseDir, $hlsBaseDir;
    $streams = [];
    foreach (glob($logsBaseDir . '/stream_*.pid') ?: [] as $pidFile) {
        $streamKey = basename($pidFile);
        $streamKey = preg_replace('/^stream_|\.pid$/', '', $streamKey);
        $paths = getStreamPaths($streamKey);
        $pid = (int)trim(@file_get_contents($pidFile));
        $running = $pid > 0 && isProcessRunning($pid);
        $hasPlaylist = file_exists($paths['playlist']);
        $streams[$streamKey] = [
            'pid' => $pid,
            'running' => $running,
            'playlist_url' => '/public/hls/' . rawurlencode($streamKey) . '/index.m3u8',
            'log' => $paths['log'],
            'has_playlist' => $hasPlaylist,
        ];
    }

    // Bổ sung các thư mục HLS không có pid (đã dừng hoặc được tạo sẵn)
    foreach (glob($hlsBaseDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $streamKey = basename($dir);
        if (!isset($streams[$streamKey])) {
            $paths = getStreamPaths($streamKey);
            $hasPlaylist = file_exists($paths['playlist']);
            $streams[$streamKey] = [
                'pid' => 0,
                'running' => false,
                'playlist_url' => '/public/hls/' . rawurlencode($streamKey) . '/index.m3u8',
                'log' => $paths['log'],
                'has_playlist' => $hasPlaylist,
            ];
        }
    }

    ksort($streams);
    return $streams;
}

$resultMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $streamKey = sanitizeStreamKey($_POST['stream_key'] ?? '');
    try {
        if ($action === 'start') {
            $rtspUrl = trim($_POST['rtsp_url'] ?? '');
            if ($rtspUrl === '') {
                $resultMessage = 'Vui lòng nhập RTSP URL';
            } else {
                $copyVideo = isset($_POST['copy_video']);
                $includeAudio = isset($_POST['include_audio']);
                $res = startStream($streamKey, $rtspUrl, [
                    'copy_video' => $copyVideo,
                    'audio' => $includeAudio,
                ]);
                $resultMessage = $res['message'] ?? ($res['ok'] ? 'Đã khởi động' : 'Lỗi không xác định');
            }
        } elseif ($action === 'stop') {
            $res = stopStream($streamKey);
            $resultMessage = $res['message'] ?? 'Đã dừng stream';
        }
    } catch (Throwable $e) {
        $resultMessage = 'Lỗi: ' . $e->getMessage();
    }
}

$streams = listStreams();
?>
<div class="camera-module">
	<style>
		body.camera-standalone { font-family: Roboto, system-ui, -apple-system, Segoe UI, Helvetica, Arial, sans-serif; margin: 0; padding: 16px; background: #0f172a; color: #e2e8f0; }
		h1 { margin: 0 0 16px; font-size: 20px; }
		.card { background: #111827; border: 1px solid #1f2937; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
		label { display: block; font-size: 14px; margin-bottom: 6px; color: #cbd5e1; }
		input[type="text"], input[type="url"] { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #334155; background: #0b1220; color: #e2e8f0; }
		.row { display: grid; grid-template-columns: 1fr; gap: 12px; }
		.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
		.actions { display: flex; gap: 8px; flex-wrap: wrap; }
		button { cursor: pointer; border: 0; padding: 10px 14px; border-radius: 8px; font-weight: 500; }
		.btn-primary { background: #2563eb; color: white; }
		.btn-danger { background: #ef4444; color: white; }
		.hint { font-size: 12px; color: #94a3b8; }
		.msg { margin-top: 10px; font-size: 13px; color: #fcd34d; }
		.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px,1fr)); gap: 12px; }
		.player { background: #0b1220; border: 1px solid #1f2937; border-radius: 10px; overflow: hidden; }
		video { width: 100%; height: 220px; background: black; }
		.player-head { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; font-size: 13px; color: #cbd5e1; }
		.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
		.switch { display: inline-flex; align-items: center; gap: 8px; }
		.switch input { transform: scale(1.1); }
		@media (min-width: 900px) { .row { grid-template-columns: 2fr 3fr; } }
	</style>

	<div class="card">
		<h1>Xem Camera qua Web (RTSP -> HLS)</h1>
		<form method="post" class="row">
			<div>
				<label for="stream_key">Mã Stream (chỉ chữ/số/_/-)</label>
				<input type="text" id="stream_key" name="stream_key" placeholder="vd: lobby_cam01" required />
				<div class="hint">Mỗi camera nên có một mã riêng để lưu output HLS.</div>
			</div>
			<div>
				<label for="rtsp_url">RTSP URL</label>
				<input type="url" id="rtsp_url" name="rtsp_url" placeholder="rtsp://user:pass@ip:554/Streaming/Channels/101" />
				<div class="hint">Ví dụ Hikvision/DAHUA/Onvif. Nên dùng TCP để ổn định.</div>
			</div>
			<div class="row-2">
				<label class="switch"><input type="checkbox" name="copy_video" checked /> Giữ nguyên codec video (copy) nếu là H.264</label>
				<label class="switch"><input type="checkbox" name="include_audio" /> Bật audio (AAC)</label>
			</div>
			<div class="actions">
				<button class="btn-primary" type="submit" name="action" value="start">Start Stream</button>
				<button class="btn-danger" type="submit" name="action" value="stop">Stop Stream</button>
			</div>
			<?php if (!empty($resultMessage)): ?>
				<div class="msg"><?= htmlspecialchars($resultMessage) ?></div>
			<?php endif; ?>
		</form>
	</div>

	<div class="card">
		<h1>Streams</h1>
		<?php if (empty($streams)): ?>
			<div class="hint">Chưa có stream nào. Hãy nhập RTSP và nhấn Start.</div>
		<?php else: ?>
			<div class="grid">
				<?php foreach ($streams as $key => $info): ?>
					<div class="player">
						<div class="player-head">
							<div title="Stream Key" class="mono"><?= htmlspecialchars($key) ?></div>
							<div>
								<?= $info['running'] ? '<span style="color:#22c55e">RUNNING</span>' : '<span style="color:#f97316">IDLE</span>' ?>
							</div>
						</div>
						<video id="video_<?= htmlspecialchars($key) ?>" controls muted playsinline></video>
						<div class="player-head">
							<div class="mono" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%" title="<?= htmlspecialchars($info['playlist_url']) ?>">m3u8: <?= htmlspecialchars($info['playlist_url']) ?></div>
							<form method="post" style="margin:0">
								<input type="hidden" name="stream_key" value="<?= htmlspecialchars($key) ?>" />
								<button class="btn-danger" type="submit" name="action" value="stop">Stop</button>
							</form>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
	<script>
		function setupHls(videoEl, src) {
			if (!src) return;
			if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
				videoEl.src = src;
			} else if (window.Hls) {
				const hls = new Hls({
					maxBufferLength: 4,
					liveSyncDuration: 2,
					liveMaxLatencyDuration: 10,
					lowLatencyMode: true
				});
				hls.loadSource(src);
				hls.attachMedia(videoEl);
				videoEl.addEventListener('canplay', () => {
					videoEl.play().catch(() => {});
				});
			}
		}
		<?php foreach ($streams as $key => $info): if (!empty($info['has_playlist'])): ?>
		(function(){
			const video = document.getElementById('video_<?= addslashes($key) ?>');
			setupHls(video, '<?= addslashes($info['playlist_url']) ?>');
		})();
		<?php endif; endforeach; ?>
	</script>
</div>