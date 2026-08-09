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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #222233;
            color: #eee;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        a { color: #77aaff; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .navbar {
            background: #1a1a2e;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #333366;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .nav-link {
            color: #eee;
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.2s;
            text-decoration: none !important;
        }
        .nav-link:hover { background: #333366; }
        .nav-link.active { background: #6677ff; color: white; }

        /* Hamburger menu for mobile */
        .hamburger {
            display: none;
            font-size: 1.8rem;
            background: none;
            border: none;
            color: #eee;
            cursor: pointer;
        }

        /* Mobile menu */
        .mobile-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: #1a1a2e;
            border-bottom: 1px solid #333366;
            flex-direction: column;
            padding: 1rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.4);
        }
        .mobile-menu.open { display: flex; }
        .mobile-menu a {
            padding: 0.8rem 1rem;
            color: #eee;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .mobile-menu a:hover { background: #333366; }

        /* Dropdown for Upload */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropbtn {
            background: none;
            border: none;
            color: #eee;
            font-size: 1rem;
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: #1a1a2e;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.4);
            border-radius: 4px;
            z-index: 1001;
            border: 1px solid #333366;
        }
        .dropdown-content a {
            color: #eee;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .dropdown-content a:hover { background: #333366; }
        .dropdown:hover .dropdown-content { display: block; }

        .container {
            flex: 1;
            display: flex;
            overflow: hidden;
            height: 100%;
            margin-top: 60px;
        }
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100%;
            overflow-y: auto;
        }
        .video-player {
            width: 100%;
            background: #111122;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            border-bottom: 1px solid #333366;
            position: relative;
        }
        .video-wrapper {
            width: 100%;
            max-width: 800px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        .main-video {
            width: 100%;
            max-width: 56.25vh; /* portrait aspect ratio */
            margin: 0 auto;
            position: relative;
        }
        .main-video img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }
        .main-video video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 100%;
            max-width: 800px;
            padding: 0.5rem 0;
        }
        .play-btn, .pause-btn, .fullscreen-btn, .theatre-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: #6677ff;
            padding: 0.25rem;
        }
        .play-btn:hover, .pause-btn:hover, .fullscreen-btn:hover, .theatre-btn:hover { color: #5555ff; }
        .progress-container {
            flex-grow: 1;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .progress-bar {
            flex-grow: 1;
            height: 8px;
            background: #333366;
            border-radius: 4px;
            cursor: pointer;
        }
        .progress {
            height: 100%;
            background: #6677ff;
            border-radius: 4px;
            width: 0;
        }
        .time-display {
            font-size: 0.9rem;
            color: #aaa;
            min-width: 110px;
            text-align: left;
            white-space: nowrap;
        }
        .volume-slider {
            width: 100px;
            height: 8px;
            background: #333366;
            border-radius: 4px;
            cursor: pointer;
            appearance: none;
            outline: none;
        }
        .volume-slider::-webkit-slider-thumb {
            appearance: none;
            width: 12px;
            height: 12px;
            background: #6677ff;
            border-radius: 50%;
            cursor: pointer;
        }
        .volume-slider::-moz-range-thumb {
            width: 12px;
            height: 12px;
            background: #6677ff;
            border-radius: 50%;
            cursor: pointer;
        }
        .video-info {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
        }
        .video-info p {
            font-size: 0.9rem;
            color: #ccc;
        }
        .video-info .video-name {
            font-size: 1.1rem;
            color: #77aaff;
            font-weight: 600;
        }
        .search-bar {
            padding: 0.5rem;
            width: 100%;
            max-width: 600px;
            margin: 1rem auto;
            border: 1px solid #333366;
            border-radius: 4px;
            background: #1a1a2e;
            color: #eee;
            font-size: 0.9rem;
        }
        .library-container {
            flex: 1;
            background: #1a1a2e;
            padding: 1rem;
            overflow-y: auto;
        }
        .video-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.2rem;
            list-style: none;
            padding: 0;
        }
        .video-list li {
            background: #222244;
            border-radius: 6px;
            overflow: hidden;
            transition: transform 0.2s;
            margin-bottom: 0.2rem;
        }
        .video-list li:hover { transform: scale(1.05); }
        .video-list li a {
            display: flex;
            flex-direction: column;
            padding: 0.3rem;
            color: #eee;
            text-decoration: none;
            font-size: 0.8rem;
        }
        .video-list li a.active { background: #6677ff; color: white; }
        .video-list img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-bottom: 1px solid #333366;
        }
        .video-name {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .error-message {
            color: #ff5555;
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
        }
        .pagination {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        .pagination-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #444488;
            color: white;
            border-radius: 4px;
            text-decoration: none;
        }
        .pagination-btn:hover { background: #5555aa; }
        .pagination-btn.disabled { background: #333366; cursor: not-allowed; }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: #1a1a2e;
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid #333366;
            width: 90%;
            max-width: 520px;
            color: #eee;
            position: relative;
        }
        .close {
            position: absolute;
            right: 1.5rem;
            top: 1rem;
            color: #aaa;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: #fff; }
        .modal h2 { color: #77aaff; margin-bottom: 1.5rem; }
        .upload-section {
            margin: 1.5rem 0;
            padding: 1.2rem;
            background: #111122;
            border-radius: 6px;
            border: 1px solid #333366;
        }
        .upload-section h3 { margin-bottom: 1rem; color: #77aaff; }
        .upload-section form { display: flex; flex-direction: column; gap: 1rem; }
        .upload-section input[type="file"], .upload-section select {
            padding: 0.6rem;
            background: #222244;
            border: 1px solid #333366;
            color: #eee;
            border-radius: 4px;
        }
        .upload-section button {
            background: #6677ff;
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 6px;
            cursor: pointer;
        }
        .upload-section button:hover { background: #5555ff; }
        .progress-container-modal {
            height: 6px;
            background: #333366;
            border-radius: 3px;
            margin: 0.6rem 0;
            overflow: hidden;
        }
        .progress-bar-modal {
            height: 100%;
            background: #6677ff;
            width: 0%;
            transition: width 0.2s;
        }
        .status-message {
            min-height: 1.3rem;
            padding: 0.6rem;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        .status-message.success { background: #2a6633; color: #d4f4d4; }
        .status-message.error { background: #662a2a; color: #f4d4d4; }

        /* Theatre mode */
        .theatre-mode .video-wrapper {
            max-width: 95vw !important;
            margin: 0 auto;
        }
        .theatre-mode .main-video {
            max-width: none;
            width: 100%;
        }

        /* Hide custom controls in fullscreen */
        .video-wrapper:fullscreen .controls,
        .video-wrapper:fullscreen .video-info {
            display: none !important;
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .navbar-links {
                display: none;
            }
            .hamburger {
                display: block;
            }
            .navbar-right {
                gap: 0.8rem;
            }
        }
    </style>
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
            <a href="admin.php">Admin</a>

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

    <script>
        // Hamburger menu toggle
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

        // Cookie helpers
        function setCookie(name, value, days = 365) {
            let expires = "";
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i].trim();
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length);
            }
            return null;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const videoEl = document.querySelector('.main-video video');
            const playBtn = document.querySelector('.play-btn');
            const pauseBtn = document.querySelector('.pause-btn');
            const thumbnail = document.querySelector('.main-video img');
            const searchInput = document.querySelector('.search-bar');
            const videoList = document.querySelector('.video-list');
            const volumeSlider = document.querySelector('.volume-slider');
            const fullscreenBtn = document.querySelector('.fullscreen-btn');
            const theatreBtn = document.querySelector('.theatre-btn');
            const progressBar = document.querySelector('.progress-bar');
            const progress = document.querySelector('.progress');
            const timeDisplay = document.querySelector('.time-display');
            const videoWrapper = document.querySelector('.video-wrapper');

            if (videoEl) {
                // Volume from cookie
                const savedVolume = getCookie('mediaVolume') || 0.8;
                videoEl.volume = parseFloat(savedVolume);
                volumeSlider.value = videoEl.volume;

                volumeSlider.addEventListener('input', () => {
                    videoEl.volume = volumeSlider.value;
                    setCookie('mediaVolume', videoEl.volume.toFixed(3));
                });

                // Play / pause
                videoEl.addEventListener('play', () => {
                    if (thumbnail) thumbnail.style.display = 'none';
                    playBtn.style.display = 'none';
                    pauseBtn.style.display = 'inline-block';
                });

                playBtn.addEventListener('click', () => videoEl.play());
                pauseBtn.addEventListener('click', () => {
                    videoEl.pause();
                    pauseBtn.style.display = 'none';
                    playBtn.style.display = 'inline-block';
                });

                // Progress & time
                function formatTime(seconds) {
                    if (isNaN(seconds) || seconds < 0) return "0:00";
                    const mins = Math.floor(seconds / 60);
                    const secs = Math.floor(seconds % 60);
                    return mins + ":" + (secs < 10 ? "0" : "") + secs;
                }

                videoEl.addEventListener('timeupdate', () => {
                    if (!videoEl.duration) return;
                    const percent = (videoEl.currentTime / videoEl.duration) * 100;
                    progress.style.width = percent + '%';
                    timeDisplay.textContent = formatTime(videoEl.currentTime) + " / " + formatTime(videoEl.duration);
                });

                videoEl.addEventListener('loadedmetadata', () => {
                    timeDisplay.textContent = "0:00 / " + formatTime(videoEl.duration);
                });

                progressBar.addEventListener('click', (e) => {
                    const rect = progressBar.getBoundingClientRect();
                    const pos = (e.clientX - rect.left) / rect.width;
                    videoEl.currentTime = pos * videoEl.duration;
                });

                // Theatre mode (desktop only)
                if (theatreBtn) {
                    theatreBtn.addEventListener('click', () => {
                        if (!videoWrapper.classList.contains('theatre-mode')) {
                            videoWrapper.classList.add('theatre-mode');
                            theatreBtn.textContent = '■';
                        } else {
                            videoWrapper.classList.remove('theatre-mode');
                            theatreBtn.textContent = '◻';
                        }
                    });
                }

                // Fullscreen - use native browser fullscreen on the video element
                fullscreenBtn.addEventListener('click', () => {
                    if (!document.fullscreenElement) {
                        videoEl.requestFullscreen().catch(err => console.error('Fullscreen failed:', err));
                    } else {
                        document.exitFullscreen();
                    }
                });
            }

            // Search
            if (searchInput && videoList) {
                searchInput.addEventListener('input', () => {
                    const query = searchInput.value.toLowerCase();
                    videoList.querySelectorAll('li a').forEach(a => {
                        const name = a.querySelector('.video-name')?.textContent.toLowerCase() || '';
                        a.parentElement.style.display = name.includes(query) ? '' : 'none';
                    });
                });
            }

            // Upload handlers
            function handleUpload(formId, progressId, messageId, successText) {
                const form = document.getElementById(formId);
                if (!form) return;

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(form);

                    const msgDiv = document.getElementById(messageId);
                    const progressBar = document.getElementById(progressId);

                    msgDiv.innerHTML = 'Uploading...';
                    msgDiv.className = '';
                    progressBar.style.width = '0%';

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'upload.php', true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    xhr.upload.onprogress = function(e) {
                        if (e.lengthComputable) {
                            const percent = (e.loaded / e.total) * 100;
                            progressBar.style.width = percent + '%';
                        }
                    };

                    xhr.onload = function() {
                        progressBar.style.width = '100%';
                        let data;
                        try {
                            data = JSON.parse(xhr.responseText);
                        } catch (err) {
                            msgDiv.className = 'status-message error';
                            msgDiv.innerHTML = 'Invalid server response';
                            console.error('Raw response:', xhr.responseText);
                            return;
                        }

                        if (data.success) {
                            msgDiv.className = 'status-message success';
                            msgDiv.innerHTML = successText;
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            msgDiv.className = 'status-message error';
                            msgDiv.innerHTML = data.error || 'Upload failed';
                        }
                    };

                    xhr.onerror = function() {
                        progressBar.style.width = '0%';
                        msgDiv.className = 'status-message error';
                        msgDiv.innerHTML = 'Network error';
                    };

                    xhr.send(formData);
                });
            }

            handleUpload('videoForm', 'videoProgress', 'video-message', 'Video uploaded successfully!');
            handleUpload('thumbForm', 'thumbProgress', 'thumb-message', 'Thumbnail uploaded successfully!');

            // Reliable modal open
            const openLink = document.getElementById('openVideoUpload');
            if (openLink) {
                openLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const modal = document.getElementById('uploadModal');
                    if (modal) modal.style.display = 'flex';
                });
            }
        });
    </script>
</body>
</html>
<?php
error_log("watch.php execution completed. Found $totalVideos videos");
?>