<?php
// music.php - Admin page for managing music with sidebar navigation, AJAX uploads with progress, deletions, and album cover uploads

session_start();

// Authentication check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    error_log("Unauthorized access attempt to music.php at " . __FILE__ . " line " . __LINE__ . " on " . date('Y-m-d H:i:s'));
    header('Location: index.php');
    exit;
}

// Error reporting configuration
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_log("Starting music.php execution at " . __FILE__ . " line " . __LINE__ . " on " . date('Y-m-d H:i:s'));

// Load settings from JSON
$settingsFile = __DIR__ . '/settings.json';
$settings = [
    'active_view_count_enabled' => true,
    'video_view_count_enabled' => true,
    'youtube_enabled' => false,
    'youtube_channel_id' => '',
    'youtube_api_keys' => [],
    'age_restriction' => false,
    'age_restriction_last_toggled' => 0,
    'videos_enabled' => true,
    'settings_enabled' => true,
    'video_size_limit_mb' => 1000,
    'thumbnail_size_limit_mb' => 1000,
    'music_size_limit_mb' => 1000
];

if (!file_exists($settingsFile)) {
    if (is_writable(dirname($settingsFile))) {
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        error_log("Created default settings file: $settingsFile at " . __FILE__ . " line " . __LINE__);
    } else {
        error_log("Cannot create settings file, directory not writable: $settingsFile at " . __FILE__ . " line " . __LINE__);
        $error = "Cannot create settings file. Check directory permissions.";
    }
} elseif (is_readable($settingsFile)) {
    $settingsContent = file_get_contents($settingsFile);
    if ($settingsContent !== false) {
        $loadedSettings = json_decode($settingsContent, true);
        if (is_array($loadedSettings)) {
            $settings = array_merge($settings, $loadedSettings);
        } else {
            error_log("Invalid JSON in settings file: $settingsFile at " . __FILE__ . " line " . __LINE__);
        }
    } else {
        error_log("Failed to read settings file: $settingsFile at " . __FILE__ . " line " . __LINE__);
        $error = "Failed to read settings file. Check server logs.";
    }
} else {
    error_log("Settings file not readable: $settingsFile at " . __FILE__ . " line " . __LINE__);
    $error = "Cannot read settings file. Check server logs.";
}

$settings['videos_enabled'] = $settings['videos_enabled'] ?? true;
$settings['settings_enabled'] = $settings['settings_enabled'] ?? true;
$settings['video_size_limit_mb'] = $settings['video_size_limit_mb'] ?? 1000;
$settings['thumbnail_size_limit_mb'] = $settings['thumbnail_size_limit_mb'] ?? 1000;
$settings['music_size_limit_mb'] = $settings['music_size_limit_mb'] ?? 1000;
error_log("Settings loaded: " . json_encode($settings) . " at " . __FILE__ . " line " . __LINE__);

// Handle music deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_music') {
    $music = isset($_POST['music']) ? $_POST['music'] : '';
    $response = ['success' => false, 'error' => ''];

    if ($music && file_exists(__DIR__ . '/music/' . $music)) {
        if (unlink(__DIR__ . '/music/' . $music)) {
            error_log("Deleted music: music/$music at " . __FILE__ . " line " . __LINE__);
            $response = ['success' => true, 'message' => 'Music deleted successfully'];
        } else {
            error_log("Failed to delete music: music/$music at " . __FILE__ . " line " . __LINE__);
            $response['error'] = 'Failed to delete music';
        }
    } else {
        error_log("Invalid music for deletion: $music at " . __FILE__ . " line " . __LINE__);
        $response['error'] = 'Music not found';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Function to get MP3 ID3v1 info
function getMP3Info($filePath) {
    if (!file_exists($filePath)) return null;
    $fp = @fopen($filePath, 'r');
    if (!$fp) return null;
    fseek($fp, -128, SEEK_END);
    $tag = fread($fp, 3);
    if ($tag === 'TAG') {
        $title = trim(fread($fp, 30));
        $artist = trim(fread($fp, 30));
        $album = trim(fread($fp, 30));
        $year = trim(fread($fp, 4));
        $comment = trim(fread($fp, 28));
        fread($fp, 1); // zero
        $track = ord(fread($fp, 1));
        $genre = ord(fread($fp, 1));
        fclose($fp);
        return [
            'title' => $title ?: 'Unknown',
            'artist' => $artist ?: 'Unknown',
            'album' => $album ?: 'Unknown',
            'year' => $year,
            'track' => $track,
            'file' => '' // to be set later
        ];
    }
    fclose($fp);
    return null;
}

// Load music files recursively
$musicFiles = [];
$musicDir = __DIR__ . '/music/';
if (is_dir($musicDir) && is_readable($musicDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($musicDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'mp3') {
            $relPath = str_replace($musicDir, '', $file->getPathname());
            $relPath = ltrim($relPath, '/\\');
            $info = getMP3Info($file->getPathname());
            if ($info) {
                $info['file'] = $relPath;
            } else {
                // Fallback to folder structure
                $parts = explode('/', $relPath);
                $filename = array_pop($parts);
                $title = pathinfo($filename, PATHINFO_FILENAME);
                if (count($parts) >= 2) {
                    $artist = $parts[0];
                    $album = implode('/', array_slice($parts, 1));
                } elseif (count($parts) === 1) {
                    $artist = 'Unknown';
                    $album = $parts[0];
                } else {
                    $artist = 'Unknown';
                    $album = 'Unknown';
                }
                $info = [
                    'title' => $title,
                    'artist' => $artist,
                    'album' => $album,
                    'file' => $relPath
                ];
            }
            $musicFiles[] = $info;
        }
    }
    usort($musicFiles, function($a, $b) {
        return strcmp($a['title'], $b['title']);
    });
    error_log("Loaded " . count($musicFiles) . " music files at " . __FILE__ . " line " . __LINE__);
} else {
    error_log("Music directory not accessible: $musicDir at " . __FILE__ . " line " . __LINE__);
}

// Get unique artists and albums
$artists = array_unique(array_column($musicFiles, 'artist'));
sort($artists);
$albums = array_unique(array_column($musicFiles, 'album'));
sort($albums);

// Apply filters
$filterType = null;
$filterValue = null;
if (isset($_GET['artist']) && in_array($_GET['artist'], $artists)) {
    $filterType = 'artist';
    $filterValue = $_GET['artist'];
} elseif (isset($_GET['album']) && in_array($_GET['album'], $albums)) {
    $filterType = 'album';
    $filterValue = $_GET['album'];
}
if ($filterType) {
    $musicFiles = array_filter($musicFiles, function($m) use ($filterType, $filterValue) {
        return $m[$filterType] === $filterValue;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Music Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #222233; color: #eee; min-height: 100vh; display: flex; overflow: hidden; }
        .sidebar { width: 250px; background: #1a1a2e; border-right: 1px solid #333366; padding: 1rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
        .sidebar-header h2 { font-size: 1.25rem; color: #77aaff; }
        .hamburger { display: none; font-size: 1.5rem; cursor: pointer; color: #77aaff; }
        .sidebar ul { list-style: none; }
        .sidebar li { margin-bottom: 0.5rem; }
        .sidebar a { display: flex; align-items: center; padding: 0.75rem; color: #eee; text-decoration: none; border-radius: 4px; transition: background 0.2s; }
        .sidebar a:hover { background: #333366; }
        .sidebar a.active { background: #6677ff; color: white; }
        .sidebar a.disabled { color: #666; cursor: not-allowed; pointer-events: none; }
        .sidebar h3 { font-size: 1rem; color: #77aaff; margin: 1rem 0 0.5rem; }
        .library-nav { overflow-y: auto; }
        .main-content { margin-left: 250px; flex: 1; padding: 2rem; overflow-y: auto; height: 100vh; }
        .section { background: #1a1a2e; padding: 1.5rem; border-radius: 6px; border: 1px solid #333366; margin-bottom: 1.5rem; }
        .section h2 { font-size: 1.25rem; margin-bottom: 1rem; color: #77aaff; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; color: #77aaff; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .form-group input[type="file"], .form-group select { width: 100%; padding: 0.5rem; background: #111122; color: #eee; border: 1px solid #333366; border-radius: 4px; font-size: 0.85rem; }
        .submit-btn { padding: 0.5rem 1rem; background: #6677ff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; }
        .submit-btn:hover { background: #5555ff; }
        .submit-btn:disabled { background: #444488; cursor: not-allowed; }
        .music-list { display: grid; grid-template-columns: 1fr; gap: 0.5rem; }
        .music-item { background: #111122; padding: 0.75rem; border-radius: 4px; border: 1px solid #333366; display: flex; justify-content: space-between; align-items: center; }
        .music-item p { font-size: 0.85rem; word-break: break-all; flex: 1; margin-right: 0.5rem; }
        .play-btn, .delete-btn { padding: 0.4rem 0.8rem; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem; margin-left: 0.5rem; }
        .play-btn { background: #55aa55; }
        .play-btn:hover { background: #449944; }
        .delete-btn { background: #ff5555; }
        .delete-btn:hover { background: #cc4444; }
        .error, .success { padding: 0.5rem; margin-bottom: 1rem; border-radius: 4px; }
        .error { background: #ff5555; }
        .success { background: #55aa55; }
        .back-btn { display: inline-block; padding: 0.5rem 1rem; background: #444488; color: white; border-radius: 4px; margin-bottom: 1rem; text-decoration: none; }
        .back-btn:hover { background: #5555aa; }
        .upload-message { margin-top: 0.5rem; }
        .progress-container { display: none; margin-top: 0.5rem; }
        .progress-bar { width: 100%; height: 8px; border-radius: 4px; background: #333366; overflow: hidden; }
        .progress-fill { height: 100%; background: #6677ff; width: 0%; transition: width 0.3s ease; }
        .progress-text { font-size: 0.8rem; color: #ccc; text-align: center; margin-top: 0.25rem; }
        .player { position: fixed; bottom: 0; left: 250px; right: 0; background: #1a1a2e; padding: 1rem; display: flex; align-items: center; border-top: 1px solid #333366; z-index: 999; }
        .player audio { margin-right: 1rem; flex: 1; max-width: 300px; }
        .player span { font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 200px; }
            .sidebar.active { transform: translateX(0); }
            .hamburger { display: block; position: fixed; top: 1rem; left: 1rem; z-index: 1100; }
            .main-content { margin-left: 0; padding: 1rem; }
            .section h2 { font-size: 1rem; }
            .form-group label { font-size: 0.8rem; }
            .form-group input[type="file"], .form-group select { font-size: 0.75rem; }
            .submit-btn, .delete-btn, .back-btn { font-size: 0.8rem; padding: 0.4rem 0.8rem; }
            .music-item p { font-size: 0.75rem; }
            .upload-message { font-size: 0.8rem; }
            .progress-text { font-size: 0.75rem; }
            .player { left: 0; flex-direction: column; padding: 0.5rem; }
            .player audio { max-width: 100%; margin-right: 0; margin-bottom: 0.5rem; }
            .player span { font-size: 0.8rem; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="hamburger"><i class="fas fa-bars"></i></div>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Media Admin</h2>
            <a href="watch.php" class="back-btn">Media Player</a>
        </div>
        <ul>
            <?php if ($settings['videos_enabled']): ?>
                <li><a href="videos.php" class="nav-link"><i class="fas fa-video"></i> Videos</a></li>
            <?php endif; ?>
            <li><a href="music.php" class="nav-link active"><i class="fas fa-music"></i> Music</a></li>
            <?php if ($settings['settings_enabled']): ?>
                <li><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
            <?php endif; ?>
        </ul>
        <div class="library-nav mt-auto">
            <h3>Library</h3>
            <ul>
                <li><a href="music.php" class="<?php echo (!$filterType) ? 'active' : ''; ?>">All Music</a></li>
            </ul>
            <h3>Artists</h3>
            <ul>
                <?php foreach ($artists as $artist): ?>
                    <li><a href="?artist=<?php echo urlencode($artist); ?>" class="<?php echo ($filterType === 'artist' && $filterValue === $artist) ? 'active' : ''; ?>"><?php echo htmlspecialchars($artist); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <h3>Albums</h3>
            <ul>
                <?php foreach ($albums as $album): ?>
                    <li><a href="?album=<?php echo urlencode($album); ?>" class="<?php echo ($filterType === 'album' && $filterValue === $album) ? 'active' : ''; ?>"><?php echo htmlspecialchars($album); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="section">
            <h2>Upload Music</h2>
            <form method="POST" enctype="multipart/form-data" id="music-upload-form">
                <div class="form-group">
                    <label for="music">Music File (MP3, <?php echo $settings['music_size_limit_mb']; ?>MB max)</label>
                    <input type="file" name="music" id="music" accept=".mp3" required>
                </div>
                <button type="submit" class="submit-btn" id="music-submit">Upload Music</button>
            </form>
            <div id="upload-message" class="upload-message"></div>
            <div class="progress-container" id="progress-container">
                <div class="progress-text">Uploading: <span id="upload-percent">0%</span></div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
            </div>
            <h2>Upload Album Cover</h2>
            <form method="POST" enctype="multipart/form-data" id="cover-upload-form">
                <div class="form-group">
                    <label for="artist">Artist:</label>
                    <select name="artist" id="artist" required>
                        <option value="">Select Artist</option>
                        <?php foreach ($artists as $artist): ?>
                            <option value="<?php echo htmlspecialchars($artist); ?>"><?php echo htmlspecialchars($artist); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="album">Album:</label>
                    <select name="album" id="album" required>
                        <option value="">Select Album</option>
                        <?php foreach ($albums as $album): ?>
                            <option value="<?php echo htmlspecialchars($album); ?>"><?php echo htmlspecialchars($album); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cover">Cover Image (JPEG, PNG, WebP, <?php echo $settings['thumbnail_size_limit_mb']; ?>MB max)</label>
                    <input type="file" name="cover" id="cover" accept=".jpg,.jpeg,.png,.webp" required>
                </div>
                <button type="submit" class="submit-btn" id="cover-submit">Upload Cover</button>
            </form>
            <div id="cover-message" class="upload-message"></div>
            <div class="progress-container" id="cover-progress-container">
                <div class="progress-text">Uploading: <span id="cover-percent">0%</span></div>
                <div class="progress-bar">
                    <div class="progress-fill" id="cover-progress-fill"></div>
                </div>
            </div>
        </div>
        <div class="section">
            <h2>Music Library</h2>
            <div class="music-list">
                <?php foreach ($musicFiles as $music): ?>
                    <div class="music-item">
                        <p><?php echo htmlspecialchars($music['title'] . ' - ' . $music['artist'] . ' - ' . $music['album']); ?></p>
                        <button class="play-btn" data-file="<?php echo htmlspecialchars($music['file']); ?>"><i class="fas fa-play"></i> Play</button>
                        <button class="delete-btn" data-music="<?php echo htmlspecialchars($music['file']); ?>">Delete</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="player">
        <audio id="audio-player" controls></audio>
        <span id="current-track">No track playing</span>
    </div>
    <script>
        // Hamburger menu toggle
        document.querySelector('.hamburger').addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Audio player
        const audio = document.getElementById('audio-player');
        const currentTrack = document.getElementById('current-track');

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('play-btn') || e.target.parentElement.classList.contains('play-btn')) {
                const btn = e.target.closest('.play-btn');
                const file = btn.dataset.file;
                audio.src = `music/${encodeURIComponent(file)}`;
                audio.play();
                currentTrack.textContent = btn.parentElement.querySelector('p').textContent;
            }
        });

        // Handle music upload with AJAX and progress bar
        const uploadForm = document.getElementById('music-upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(uploadForm);
                const submitBtn = document.getElementById('music-submit');
                const messageDiv = document.getElementById('upload-message');
                const progressContainer = document.getElementById('progress-container');
                const progressFill = document.getElementById('progress-fill');
                const uploadPercent = document.getElementById('upload-percent');

                messageDiv.innerHTML = '';

                const fileInput = document.getElementById('music');
                const file = fileInput.files[0];
                if (!file) {
                    messageDiv.innerHTML = '<div class="error">No file selected. Please choose an MP3 file.</div>';
                    return;
                }

                if (file.type !== 'audio/mpeg' && !file.name.toLowerCase().endsWith('.mp3')) {
                    messageDiv.innerHTML = '<div class="error">Invalid file type. Only MP3 files are allowed.</div>';
                    return;
                }

                const maxSize = <?php echo $settings['music_size_limit_mb'] * 1024 * 1024; ?>;
                if (file.size > maxSize) {
                    messageDiv.innerHTML = '<div class="error">File size exceeds <?php echo $settings['music_size_limit_mb']; ?>MB limit.</div>';
                    return;
                }

                console.log('Uploading file details:', {
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    formDataEntries: Array.from(formData.entries())
                });

                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
                progressContainer.style.display = 'block';
                uploadPercent.textContent = '0%';
                progressFill.style.width = '0%';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload.php', true);

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const percent = Math.round((event.loaded / event.total) * 100);
                        uploadPercent.textContent = percent + '%';
                        progressFill.style.width = percent + '%';
                    }
                };

                xhr.onload = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Music';
                    progressContainer.style.display = 'none';

                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            console.log('Server response:', data);
                            const message = document.createElement('div');
                            message.className = data.success ? 'success' : 'error';
                            message.textContent = data.success ? data.message : (data.error || 'Unknown error');
                            messageDiv.appendChild(message);

                            if (data.success) {
                                // Reload page to update library
                                location.reload();
                            }
                        } catch (e) {
                            console.error('JSON parse error:', e, 'Raw response:', xhr.responseText);
                            const message = document.createElement('div');
                            message.className = 'error';
                            message.textContent = 'Invalid server response: ' + e.message + ' (Response starts with: ' + xhr.responseText.substring(0, 20) + '...)';
                            messageDiv.appendChild(message);
                        }
                    } else {
                        console.error('Upload failed with status:', xhr.status, 'Response:', xhr.responseText);
                        const message = document.createElement('div');
                        message.className = 'error';
                        message.textContent = 'Upload failed with status: ' + xhr.status + ' (Response: ' + xhr.responseText.substring(0, 20) + '...)';
                        messageDiv.appendChild(message);
                    }
                };

                xhr.onerror = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Music';
                    progressContainer.style.display = 'none';
                    console.error('Network error during upload');
                    const message = document.createElement('div');
                    message.className = 'error';
                    message.textContent = 'Network error during upload';
                    messageDiv.appendChild(message);
                };

                xhr.ontimeout = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Music';
                    progressContainer.style.display = 'none';
                    console.error('Upload timed out');
                    const message = document.createElement('div');
                    message.className = 'error';
                    message.textContent = 'Upload timed out';
                    messageDiv.appendChild(message);
                };

                xhr.timeout = 30000; // 30 seconds timeout
                xhr.send(formData);
            });
        }

        // Handle cover upload with AJAX and progress bar
        const coverForm = document.getElementById('cover-upload-form');
        if (coverForm) {
            coverForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(coverForm);
                formData.append('action', 'upload_cover');
                const submitBtn = document.getElementById('cover-submit');
                const messageDiv = document.getElementById('cover-message');
                const progressContainer = document.getElementById('cover-progress-container');
                const progressFill = document.getElementById('cover-progress-fill');
                const uploadPercent = document.getElementById('cover-percent');

                messageDiv.innerHTML = '';

                const fileInput = document.getElementById('cover');
                const file = fileInput.files[0];
                if (!file) {
                    messageDiv.innerHTML = '<div class="error">No file selected. Please choose a cover image.</div>';
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    messageDiv.innerHTML = '<div class="error">Invalid file type. Only JPEG, PNG, and WebP are allowed.</div>';
                    return;
                }

                const maxSize = <?php echo $settings['thumbnail_size_limit_mb'] * 1024 * 1024; ?>;
                if (file.size > maxSize) {
                    messageDiv.innerHTML = '<div class="error">File size exceeds <?php echo $settings['thumbnail_size_limit_mb']; ?>MB limit.</div>';
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
                progressContainer.style.display = 'block';
                uploadPercent.textContent = '0%';
                progressFill.style.width = '0%';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload_cover.php', true);

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const percent = Math.round((event.loaded / event.total) * 100);
                        uploadPercent.textContent = percent + '%';
                        progressFill.style.width = percent + '%';
                    }
                };

                xhr.onload = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Cover';
                    progressContainer.style.display = 'none';

                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            const message = document.createElement('div');
                            message.className = data.success ? 'success' : 'error';
                            message.textContent = data.success ? data.message : data.error;
                            messageDiv.appendChild(message);

                            if (data.success) {
                                // Optionally refresh the page or update the UI
                                location.reload();
                            }
                        } catch (e) {
                            console.error('JSON parse error:', e, 'Raw response:', xhr.responseText);
                            const message = document.createElement('div');
                            message.className = 'error';
                            message.textContent = 'Invalid server response: ' + e.message;
                            messageDiv.appendChild(message);
                        }
                    } else {
                        console.error('Upload failed with status:', xhr.status, 'Response:', xhr.responseText);
                        const message = document.createElement('div');
                        message.className = 'error';
                        message.textContent = 'Upload failed with status: ' + xhr.status;
                        messageDiv.appendChild(message);
                    }
                };

                xhr.onerror = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Cover';
                    progressContainer.style.display = 'none';
                    console.error('Network error during upload');
                    const message = document.createElement('div');
                    message.className = 'error';
                    message.textContent = 'Network error during upload';
                    messageDiv.appendChild(message);
                };

                xhr.ontimeout = () => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Cover';
                    progressContainer.style.display = 'none';
                    console.error('Upload timed out');
                    const message = document.createElement('div');
                    message.className = 'error';
                    message.textContent = 'Upload timed out';
                    messageDiv.appendChild(message);
                };

                xhr.timeout = 30000; // 30 seconds timeout
                xhr.send(formData);
            });
        }

        // Handle music deletion with AJAX
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const music = button.dataset.music;

                    if (confirm(`Are you sure you want to delete ${music}?`)) {
                        const formData = new FormData();
                        formData.append('action', 'delete_music');
                        formData.append('music', music);

                        fetch('music.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                button.parentElement.remove();
                                const messageDiv = document.getElementById('upload-message');
                                const message = document.createElement('div');
                                message.className = 'success';
                                message.textContent = data.message;
                                messageDiv.appendChild(message);
                            } else {
                                const messageDiv = document.getElementById('upload-message');
                                const message = document.createElement('div');
                                message.className = 'error';
                                message.textContent = data.error;
                                messageDiv.appendChild(message);
                            }
                        })
                        .catch(error => {
                            const messageDiv = document.getElementById('upload-message');
                            const message = document.createElement('div');
                            message.className = 'error';
                            message.textContent = 'Delete failed: ' + error.message;
                            messageDiv.appendChild(message);
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php
error_log("music.php execution completed at " . __FILE__ . " line " . __LINE__ . " on " . date('Y-m-d H:i:s'));
?>