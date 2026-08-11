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
        header('Location: admin/login.php');
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
        <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/videos.css">
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
            <a href="admin/index.php" class="nav-link active">Admin</a>

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
                <li><a href="admin/settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
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

        <script src="assets/js/videos.js"></script>
</body>
</html>
<?php
error_log("videos.php execution completed in " . __FILE__ . " at line " . __LINE__);
?>