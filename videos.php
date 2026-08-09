<?php
// videos.php - Admin page for managing videos with updated navbar and sidebar

session_start();

// Check admin authentication
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
    'music_size_limit_mb' => 1000,
    'admin_password_hash' => ''
];

try {
    if (file_exists($settingsFile) && is_readable($settingsFile)) {
        $settingsContent = file_get_contents($settingsFile);
        if ($settingsContent !== false) {
            $loadedSettings = json_decode($settingsContent, true);
            if (is_array($loadedSettings)) {
                $settings = array_merge($settings, $loadedSettings);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    if (!empty($settings['admin_password_hash'])) {
        error_log("Unauthorized access attempt to videos.php in " . __FILE__ . " at line " . __LINE__);
        header('Location: admin_login.php');
        exit;
    }
    error_log("No admin password set, allowing access to videos.php in " . __FILE__ . " at line " . __LINE__);
}

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_log("Starting videos.php execution in " . __FILE__ . " at line " . __LINE__);

// Load settings from JSON
try {
    if (!file_exists($settingsFile)) {
        error_log("Settings file not found, creating default: $settingsFile in " . __FILE__ . " at line " . __LINE__);
        if (is_writable(dirname($settingsFile))) {
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
            error_log("Created default settings file: $settingsFile in " . __FILE__ . " at line " . __LINE__);
        } else {
            error_log("Cannot create settings file, directory not writable: $settingsFile in " . __FILE__ . " at line " . __LINE__);
            $error = "Cannot create settings file. Check directory permissions.";
        }
    } elseif (!is_readable($settingsFile)) {
        error_log("Settings file not readable: $settingsFile in " . __FILE__ . " at line " . __LINE__);
        $error = "Cannot read settings file. Check server logs.";
    } else {
        $settingsContent = file_get_contents($settingsFile);
        if ($settingsContent === false) {
            error_log("Failed to read settings file: $settingsFile in " . __FILE__ . " at line " . __LINE__);
            $error = "Failed to read settings file. Check server logs.";
        } else {
            $settings = json_decode($settingsContent, true);
            if (!is_array($settings)) {
                error_log("Invalid JSON in settings file: $settingsFile in " . __FILE__ . " at line " . __LINE__);
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
                    'music_size_limit_mb' => 1000,
                    'admin_password_hash' => ''
                ];
            } else {
                $settings['videos_enabled'] = $settings['videos_enabled'] ?? true;
                $settings['settings_enabled'] = $settings['settings_enabled'] ?? true;
                $settings['video_size_limit_mb'] = $settings['video_size_limit_mb'] ?? 1000;
                $settings['thumbnail_size_limit_mb'] = $settings['thumbnail_size_limit_mb'] ?? 1000;
                $settings['music_size_limit_mb'] = $settings['music_size_limit_mb'] ?? 1000;
                $settings['admin_password_hash'] = $settings['admin_password_hash'] ?? '';
                error_log("Settings loaded successfully: " . json_encode($settings) . " in " . __FILE__ . " at line " . __LINE__);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
    $error = "Failed to load settings: " . $e->getMessage();
}

// Handle video deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_video') {
    $video = isset($_POST['video']) ? basename($_POST['video']) : '';
    $thumbnail = isset($_POST['thumbnail']) ? basename($_POST['thumbnail']) : '';
    $response = ['success' => false, 'error' => ''];

    if ($video && file_exists("videos/$video")) {
        if (unlink("videos/$video")) {
            error_log("Deleted video: videos/$video in " . __FILE__ . " at line " . __LINE__);
            if ($thumbnail && file_exists("videos/$thumbnail")) {
                unlink("videos/$thumbnail");
                error_log("Deleted thumbnail: videos/$thumbnail in " . __FILE__ . " at line " . __LINE__);
            }
            $response = ['success' => true, 'message' => 'Video deleted successfully'];
        } else {
            error_log("Failed to delete video: videos/$video in " . __FILE__ . " at line " . __LINE__);
            $response['error'] = 'Failed to delete video';
        }
    } else {
        error_log("Invalid video for deletion: $video in " . __FILE__ . " at line " . __LINE__);
        $response['error'] = 'Video not found';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Load videos and thumbnails
$videos = [];
$videoDir = __DIR__ . '/videos/';
if (is_dir($videoDir)) {
    $files = scandir($videoDir);
    foreach ($files as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['mp4', 'avi', 'mov', 'mpeg'])) {
            $thumbnail = pathinfo($file, PATHINFO_FILENAME) . '.jpg';
            $thumbnail = file_exists($videoDir . $thumbnail) ? $thumbnail : '';
            $videos[] = ['file' => $file, 'thumbnail' => $thumbnail];
        }
    }
    error_log("Loaded " . count($videos) . " videos in " . __FILE__ . " at line " . __LINE__);
} else {
    error_log("Videos directory not found: $videoDir in " . __FILE__ . " at line " . __LINE__);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Videos Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #222233;
            color: #eee;
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* Navbar */
        .navbar {
            background:#1a1a2e;
            padding:1rem 1.5rem;
            border-bottom:1px solid #333366;
            display:flex;
            justify-content:space-between;
            align-items:center;
            position:fixed;
            top:0;
            width:100%;
            z-index:1000;
        }
        .navbar-left {
            display:flex;
            align-items:center;
            gap:1.5rem;
        }
        .navbar-right {
            display:flex;
            align-items:center;
            gap:1rem;
        }
        .nav-link {
            color:#eee;
            font-size:1rem;
            padding:0.5rem 1rem;
            border-radius:6px;
            transition:background 0.2s;
            text-decoration:none !important;
        }
        .nav-link:hover { background:#333366; }
        .nav-link.active { background:#6677ff; color:white; }

        /* Hamburger for mobile */
        .hamburger {
            display:none;
            font-size:1.8rem;
            background:none;
            border:none;
            color:#eee;
            cursor:pointer;
        }

        /* Mobile menu */
        .mobile-menu {
            display:none;
            position:absolute;
            top:100%;
            left:0;
            width:100%;
            background:#1a1a2e;
            border-bottom:1px solid #333366;
            flex-direction:column;
            padding:1rem;
            box-shadow:0 8px 16px rgba(0,0,0,0.4);
        }
        .mobile-menu.open { display:flex; }
        .mobile-menu a {
            padding:0.8rem 1rem;
            color:#eee;
            text-decoration:none;
            border-radius:6px;
            transition:background 0.2s;
        }
        .mobile-menu a:hover { background:#333366; }

        .dropdown {
            position:relative;
            display:inline-block;
        }
        .dropbtn {
            background:none;
            border:none;
            color:#eee;
            font-size:1rem;
            padding:0.5rem 1rem;
            cursor:pointer;
        }
        .dropdown-content {
            display:none;
            position:absolute;
            right:0;
            background:#1a1a2e;
            min-width:160px;
            box-shadow:0 8px 16px rgba(0,0,0,0.4);
            border-radius:4px;
            z-index:1001;
            border:1px solid #333366;
        }
        .dropdown-content a {
            color:#eee;
            padding:12px 16px;
            display:block;
        }
        .dropdown-content a:hover { background:#333366; }
        .dropdown:hover .dropdown-content { display:block; }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #1a1a2e;
            border-right: 1px solid #333366;
            padding: 1rem;
            height: 100vh;
            position: fixed;
            top: 70px; /* below navbar */
            left: 0;
            transition: transform 0.3s ease;
            z-index: 900;
        }
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .sidebar-header h2 {
            font-size: 1.25rem;
            color: #77aaff;
        }
        .sidebar ul {
            list-style: none;
        }
        .sidebar li {
            margin-bottom: 0.5rem;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            color: #eee;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .sidebar a:hover {
            background: #333366;
        }
        .sidebar a.active {
            background: #6677ff;
            color: white;
        }
        .sidebar a.disabled {
            color: #666;
            cursor: not-allowed;
            pointer-events: none;
        }

        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            height: 100vh;
            margin-top: 70px; /* below navbar */
        }

        .section {
            background: #1a1a2e;
            padding: 1.5rem;
            border-radius: 6px;
            border: 1px solid #333366;
            margin-bottom: 1.5rem;
        }
        .section h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: #77aaff;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            color: #77aaff;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        .form-group input[type="file"] {
            width: 100%;
            padding: 0.5rem;
            background: #111122;
            color: #eee;
            border: 1px solid #333366;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .submit-btn {
            padding: 0.5rem 1rem;
            background: #6677ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .submit-btn:hover {
            background: #5555ff;
        }
        .submit-btn:disabled {
            background: #444488;
            cursor: not-allowed;
        }
        .video-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .video-item {
            background: #111122;
            padding: 0.75rem;
            border-radius: 4px;
            border: 1px solid #333366;
        }
        .video-item img {
            width: 100%;
            height: auto;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        .video-item p {
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            word-break: break-all;
        }
        .delete-btn {
            padding: 0.4rem 0.8rem;
            background: #ff5555;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .delete-btn:hover {
            background: #cc4444;
        }
        .error, .success {
            padding: 0.5rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .error {
            background: #ff5555;
        }
        .success {
            background: #55aa55;
        }
        .back-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #444488;
            color: white;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-decoration: none;
        }
        .back-btn:hover {
            background: #5555aa;
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .navbar-links { display:none; }
            .hamburger { display:block; }
            .sidebar {
                transform: translateX(-100%);
                width: 200px;
                top: 70px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .hamburger {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1100;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .section h2 {
                font-size: 1rem;
            }
            .form-group label {
                font-size: 0.8rem;
            }
            .form-group input[type="file"] {
                font-size: 0.75rem;
            }
            .submit-btn, .delete-btn, .back-btn {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            .video-list {
                grid-template-columns: 1fr;
            }
            .video-item p {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
            <button class="hamburger" id="hamburgerBtn">☰</button>
            <div class="navbar-links" id="navLinks">
                <a href="watch.php" class="nav-link">Videos</a>
                <a href="music_player.php" class="nav-link">Music</a>
                <a href="gallery.php" class="nav-link">Gallery</a>
            </div>
        </div>

        <div class="navbar-right">
            <a href="?action=logout">Sign Out</a>
            <a href="media_manager.php">Media Manager</a>
            <a href="admin.php" class="nav-link active">Admin</a>

            <div class="dropdown">
                <button class="dropbtn">Upload ▼</button>
                <div class="dropdown-content">
                    <a href="#" id="openVideoUpload">Upload Files</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile menu dropdown -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="watch.php" class="nav-link">Videos</a>
        <a href="music_player.php" class="nav-link">Music</a>
        <a href="gallery.php" class="nav-link">Gallery</a>
    </div>

    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Media Admin</h2>
            <a href="watch.php" class="back-btn">Media Player</a>
        </div>
        <ul>
            <?php if ($settings['videos_enabled']): ?>
                <li><a href="videos.php" class="nav-link active"><i class="fas fa-video"></i> Videos</a></li>
            <?php endif; ?>
            <li><a href="music.php" class="nav-link"><i class="fas fa-music"></i> Music</a></li>
            <?php if ($settings['settings_enabled']): ?>
                <li><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="section">
            <h2>Upload Video</h2>
            <form method="POST" enctype="multipart/form-data" id="video-upload-form">
                <div class="form-group">
                    <label for="video">Video File (MP4, AVI, MOV, MPEG, <?php echo $settings['video_size_limit_mb']; ?>MB max)</label>
                    <input type="file" name="video" id="video" accept=".mp4,.avi,.mov,.mpeg" required>
                </div>
                <div class="form-group">
                    <label for="thumbnail">Thumbnail Image (Optional, JPG/PNG/WebP, <?php echo $settings['thumbnail_size_limit_mb']; ?>MB max)</label>
                    <input type="file" name="thumbnail" id="thumbnail" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <button type="submit" class="submit-btn" id="video-submit">Upload Video</button>
            </form>
        </div>
        <div class="section">
            <h2>Manage Videos</h2>
            <div class="video-list">
                <?php foreach ($videos as $video): ?>
                    <div class="video-item">
                        <?php if ($video['thumbnail']): ?>
                            <img src="videos/<?php echo htmlspecialchars($video['thumbnail']); ?>" alt="Thumbnail">
                        <?php endif; ?>
                        <p><?php echo htmlspecialchars($video['file']); ?></p>
                        <button class="delete-btn" data-video="<?php echo htmlspecialchars($video['file']); ?>" data-thumbnail="<?php echo htmlspecialchars($video['thumbnail']); ?>">Delete</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Hamburger menu toggle (top navbar)
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        if (hamburgerBtn && mobileMenu) {
            hamburgerBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('open');
            });
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburgerBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.remove('open');
            }
        });

        // Sidebar hamburger toggle (your existing sidebar)
        const sidebar = document.querySelector('.sidebar');
        const sidebarHamburger = document.querySelector('.hamburger');
        if (sidebarHamburger && sidebar) {
            sidebarHamburger.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }

        // Handle video upload with AJAX
        const uploadForm = document.getElementById('video-upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = document.getElementById('video-submit');

                // Clear previous messages
                const existingMessages = document.querySelectorAll('.main-content .error, .main-content .success');
                existingMessages.forEach(msg => msg.remove());

                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';

                fetch('upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Upload response:', data);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Video';

                    const messageDiv = document.createElement('div');
                    messageDiv.className = data.success ? 'success' : 'error';
                    messageDiv.textContent = data.success ? data.message : data.error;
                    document.querySelector('.main-content').prepend(messageDiv);

                    if (data.success) {
                        // Add new video to the list
                        const videoList = document.querySelector('.video-list');
                        const videoItem = document.createElement('div');
                        videoItem.className = 'video-item';
                        if (data.thumbnail) {
                            const img = document.createElement('img');
                            img.src = `videos/${data.thumbnail}`;
                            img.alt = 'Thumbnail';
                            videoItem.appendChild(img);
                        }
                        const p = document.createElement('p');
                        p.textContent = data.video;
                        videoItem.appendChild(p);
                        const deleteBtn = document.createElement('button');
                        deleteBtn.className = 'delete-btn';
                        deleteBtn.dataset.video = data.video;
                        deleteBtn.dataset.thumbnail = data.thumbnail || '';
                        deleteBtn.textContent = 'Delete';
                        videoItem.appendChild(deleteBtn);
                        videoList.prepend(videoItem);

                        // Reset form
                        uploadForm.reset();
                    }
                })
                .catch(error => {
                    console.error('Upload failed:', error);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Video';
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error';
                    errorDiv.textContent = 'Upload failed: ' + error.message;
                    document.querySelector('.main-content').prepend(errorDiv);
                });
            });
        }

        // Handle video deletion with AJAX
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const video = this.dataset.video;
                const thumbnail = this.dataset.thumbnail;

                if (confirm(`Are you sure you want to delete ${video}?`)) {
                    const formData = new FormData();
                    formData.append('action', 'delete_video');
                    formData.append('video', video);
                    formData.append('thumbnail', thumbnail);

                    fetch('videos.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Delete response:', data);
                        if (data.success) {
                            this.parentElement.remove();
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'success';
                            messageDiv.textContent = data.message;
                            document.querySelector('.main-content').prepend(messageDiv);
                        } else {
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'error';
                            errorDiv.textContent = data.error;
                            document.querySelector('.main-content').prepend(errorDiv);
                        }
                    })
                    .catch(error => {
                        console.error('Delete failed:', error);
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error';
                        errorDiv.textContent = 'Delete failed: ' + error.message;
                        document.querySelector('.main-content').prepend(errorDiv);
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php
error_log("videos.php execution completed in " . __FILE__ . " at line " . __LINE__);
?>