<?php
// watch.php - Video player with updated navbar: left-aligned nav, mobile hamburger menu

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_log("Starting watch.php execution at " . date('Y-m-d H:i:s') . " in " . __FILE__ . " at line " . __LINE__);

// Start session
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    error_log("Unauthenticated access attempt, redirecting to index.php at " . __FILE__ . " line " . __LINE__);
    header('Location: index.php');
    exit;
}

// Define video directory
$videosDir = __DIR__ . '/videos/';
if (!file_exists($videosDir)) {
    if (mkdir($videosDir, 0755, true)) {
        error_log("Created directory: $videosDir at " . __FILE__ . " line " . __LINE__);
    } else {
        error_log("Failed to create directory: $videosDir at " . __FILE__ . " line " . __LINE__);
        die("Server error: Unable to create video directory.");
    }
}

// Scan for videos with pagination (supports .mp4 and .mkv)
$videos = [];
$videosPerPage = 20;
$videoPage = isset($_GET['video_page']) ? max(1, (int)$_GET['video_page']) : 1;
$videoOffset = ($videoPage - 1) * $videosPerPage;

if (is_dir($videosDir) && is_readable($videosDir)) {
    $videoFiles = glob($videosDir . '*.{mp4,mkv}', GLOB_BRACE);
    foreach ($videoFiles as $file) {
        $filename = basename($file);
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $thumbnail = null;
        $thumbExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($thumbExtensions as $ext) {
            $thumbPath = $videosDir . $baseName . '.' . $ext;
            if (file_exists($thumbPath)) {
                $thumbnail = 'videos/' . $baseName . '.' . $ext . '?v=' . filemtime($thumbPath);
                break;
            }
        }
        $videos[] = [
            'file' => $filename,
            'path' => 'videos/' . $filename,
            'thumbnail' => $thumbnail ?: 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='
        ];
    }
    sort($videos);
}
$totalVideos = count($videos);
$totalVideoPages = max(1, ceil($totalVideos / $videosPerPage));
$pagedVideos = array_slice($videos, $videoOffset, $videosPerPage);
error_log("Loaded $totalVideos videos, displaying page $videoPage (offset $videoOffset, $videosPerPage per page)");

// Get selected video
$video = isset($_GET['video']) ? basename($_GET['video']) : '';
$currentVideoInfo = null;
if ($video && in_array($video, array_column($videos, 'file'))) {
    $videoIndex = array_search($video, array_column($videos, 'file'));
    $currentVideoInfo = $videos[$videoIndex];
} else {
    $currentVideoInfo = !empty($videos) ? $videos[0] : null;
    $video = $currentVideoInfo['file'] ?? '';
    error_log("Selected default video: $video");
}

// Handle sign out
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    try {
        session_destroy();
        setcookie('auth_token', '', time() - 3600, '/');
        error_log("User signed out successfully");
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        error_log("Error during sign out: " . $e->getMessage());
        http_response_code(500);
        echo "Sign out failed. Check server logs.";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Video Player - <?php echo htmlspecialchars($currentVideoInfo['file'] ?? 'No Video'); ?></title>
        <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/watch.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
            <button class="hamburger" id="hamburgerBtn">☰</button>
            <div class="navbar-links" id="navLinks">
                <a href="watch.php" class="nav-link active">Videos</a>
                <a href="music_player.php" class="nav-link">Music</a>
                <a href="gallery.php" class="nav-link">Gallery</a>
            </div>
        </div>

        <div class="navbar-right">
            <a href="?action=logout">Sign Out</a>
            <a href="media_manager.php">Media Manager</a>
            <a href="admin/index.php">Admin</a>

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
        <a href="watch.php" class="nav-link active">Videos</a>
        <a href="music_player.php" class="nav-link">Music</a>
        <a href="gallery.php" class="nav-link">Gallery</a>
    </div>

    <div class="container">
        <div class="main-content">
            <div class="video-player">
                <div class="video-wrapper">
                    <?php if ($video && $currentVideoInfo): ?>
                        <div class="main-video">
                            <video autoplay>
                                <source src="videos/<?php echo htmlspecialchars(rawurlencode($video)); ?>" type="video/mp4">
                                <img src="<?php echo htmlspecialchars($currentVideoInfo['thumbnail']); ?>" alt="Video Thumbnail" onerror="this.src='data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='">
                            </video>
                        </div>
                        <div class="controls">
                            <button class="play-btn">▶</button>
                            <button class="pause-btn" style="display: none;">❚❚</button>
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress"></div>
                                </div>
                                <span class="time-display">0:00 / 0:00</span>
                            </div>
                            <input type="range" min="0" max="1" step="0.01" value="1" class="volume-slider">
                            <button class="fullscreen-btn">☐</button>
                            <button class="theatre-btn" title="Theatre Mode">◻</button>
                        </div>
                        <div class="video-info">
                            <p class="video-name"><?php echo htmlspecialchars($currentVideoInfo['file']); ?></p>
                        </div>
                    <?php else: ?>
                        <p class="error-message">
                            <?php echo $video ? 'Video not found: ' . htmlspecialchars($video) : 'No videos found in videos directory.'; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="library-container">
                <h3>Videos (Page <?php echo $videoPage; ?> of <?php echo $totalVideoPages; ?>)</h3>
                <input type="text" class="search-bar" placeholder="Search videos...">
                <ul class="video-list">
                    <?php foreach ($pagedVideos as $vid): ?>
                        <li>
                            <a href="?video=<?php echo rawurlencode($vid['file']); ?>&video_page=<?php echo $videoPage; ?>" 
                               class="<?php echo $vid['file'] === $video ? 'active' : ''; ?>" 
                               title="<?php echo htmlspecialchars($vid['file']); ?>">
                                <img src="<?php echo htmlspecialchars($vid['thumbnail']); ?>" alt="Video Thumbnail" onerror="this.src='data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='">
                                <span class="video-name"><?php echo htmlspecialchars($vid['file']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($pagedVideos)): ?>
                        <li><p style="text-align: center; color: #888; padding: 1rem;">No videos found</p></li>
                    <?php endif; ?>
                </ul>
                <div class="pagination">
                    <?php if ($videoPage > 1): ?>
                        <a href="?video_page=<?php echo $videoPage - 1; ?><?php echo $video ? '&video=' . rawurlencode($video) : ''; ?>" class="pagination-btn">Previous</a>
                    <?php else: ?>
                        <a href="#" class="pagination-btn disabled">Previous</a>
                    <?php endif; ?>
                    <?php if ($videoPage < $totalVideoPages): ?>
                        <a href="?video_page=<?php echo $videoPage + 1; ?><?php echo $video ? '&video=' . rawurlencode($video) : ''; ?>" class="pagination-btn">Next</a>
                    <?php else: ?>
                        <a href="#" class="pagination-btn disabled">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('uploadModal').style.display='none'">&times;</span>
            <h2>Upload Video / Thumbnail</h2>

            <div class="upload-section">
                <h3>Upload Video (MP4 or MKV)</h3>
                <form id="videoForm" enctype="multipart/form-data">
                    <input type="file" name="video" accept=".mp4,.mkv" required>
                    <button type="submit">Upload Video</button>
                </form>
                <div class="progress-container-modal"><div id="videoProgress" class="progress-bar-modal"></div></div>
                <div id="video-message" class="status-message"></div>
            </div>

            <div class="upload-section">
                <h3>Upload Thumbnail (for existing video)</h3>
                <form id="thumbForm" enctype="multipart/form-data">
                    <select name="video" required>
                        <option value="">-- Select video --</option>
                        <?php foreach ($videos as $vid): ?>
                            <option value="<?php echo htmlspecialchars($vid['file']); ?>">
                                <?php echo htmlspecialchars($vid['file']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="video_thumbnail" accept=".jpg,.jpeg,.png,.webp" required>
                    <button type="submit">Upload Thumbnail</button>
                </form>
                <div class="progress-container-modal"><div id="thumbProgress" class="progress-bar-modal"></div></div>
                <div id="thumb-message" class="status-message"></div>
            </div>
        </div>
    </div>

        <script src="assets/js/watch.js"></script>
</body>
</html>
<?php
error_log("watch.php execution completed. Found $totalVideos videos");
?>