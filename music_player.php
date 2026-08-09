<?php
// music_player.php - Music player with updated navbar (left nav + mobile hamburger)

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_log("Starting music_player.php execution in " . __FILE__ . " at line " . __LINE__);

// Start session
try {
    session_start();
    error_log("Session started successfully in " . __FILE__ . " at line " . __LINE__);
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        error_log("Unauthenticated access attempt, redirecting to index.php in " . __FILE__ . " at line " . __LINE__);
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Session error: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
    http_response_code(500);
    echo "Session initialization failed. Check server logs.";
    exit;
}

// Load settings from JSON
$settingsFile = __DIR__ . '/settings.json';
try {
    if (!file_exists($settingsFile)) {
        error_log("Settings file not found: $settingsFile in " . __FILE__ . " at line " . __LINE__);
        $settings = ['age_restriction' => false];
    } elseif (!is_readable($settingsFile)) {
        error_log("Settings file not readable: $settingsFile in " . __FILE__ . " at line " . __LINE__);
        http_response_code(500);
        echo "Cannot read settings file. Check server logs.";
        exit;
    } else {
        $settingsContent = file_get_contents($settingsFile);
        if ($settingsContent === false) {
            error_log("Failed to read settings file: $settingsFile in " . __FILE__ . " at line " . __LINE__);
            http_response_code(500);
            echo "Failed to read settings file. Check server logs.";
            exit;
        }
        $settings = json_decode($settingsContent, true);
        if (!is_array($settings)) {
            error_log("Invalid JSON in settings file: $settingsFile in " . __FILE__ . " at line " . __LINE__);
            $settings = ['age_restriction' => false];
        }
        $settings['age_restriction'] = $settings['age_restriction'] ?? false;
        error_log("Settings loaded successfully in " . __FILE__ . " at line " . __LINE__);
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
    http_response_code(500);
    echo "Failed to load settings. Check server logs.";
    exit;
}

// Function to get album art
function getAlbumArt($albumDir, $musicDir) {
    if (!is_dir($musicDir . $albumDir)) return 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
    
    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    foreach ($extensions as $ext) {
        $coverPath = $musicDir . $albumDir . '/cover.' . $ext;
        if (file_exists($coverPath)) {
            return 'music/' . ltrim($albumDir, '/\\') . '/cover.' . $ext . '?v=' . filemtime($coverPath);
        }
    }
    
    $pattern = $musicDir . $albumDir . '/*.{jpg,jpeg,png,webp}';
    $coverFiles = glob($pattern, GLOB_BRACE);
    if ($coverFiles && is_array($coverFiles) && count($coverFiles) > 0) {
        $coverFile = $coverFiles[0];
        return 'music/' . ltrim($albumDir, '/\\') . '/' . basename($coverFile) . '?v=' . filemtime($coverFile);
    }
    
    return 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
}

// Function to get MP3 ID3 info
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
try {
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
                $albumDir = dirname($relPath) ?: '';
                $info['album_art'] = getAlbumArt($albumDir, $musicDir);
                $musicFiles[] = $info;
            }
        }
        usort($musicFiles, function($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
        error_log("Loaded " . count($musicFiles) . " music files at " . __FILE__ . " at line " . __LINE__);
    } else {
        error_log("Music directory not accessible: $musicDir at " . __FILE__ . " at line " . __LINE__);
    }
} catch (Exception $e) {
    error_log("Error scanning music directory: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
    $musicFiles = [];
}

// Get unique artists and albums
$artists = array_unique(array_column($musicFiles, 'artist'));
sort($artists);
$albums = array_unique(array_column($musicFiles, 'album'));
sort($albums);

// Apply filters
$track = isset($_GET['track']) ? $_GET['track'] : '';
$filterType = null;
$filterValue = null;
if (isset($_GET['artist']) && in_array($_GET['artist'], $artists)) {
    $filterType = 'artist';
    $filterValue = $_GET['artist'];
} elseif (isset($_GET['album']) && in_array($_GET['album'], $albums)) {
    $filterType = 'album';
    $filterValue = $_GET['album'];
}
$filteredTracks = $musicFiles;
if ($filterType) {
    $filteredTracks = array_filter($musicFiles, function($m) use ($filterType, $filterValue) {
        return $m[$filterType] === $filterValue;
    });
}

if ($filterType === 'artist') {
    $albums = array_unique(array_column($filteredTracks, 'album'));
    sort($albums);
}

// Select first track if none selected or invalid
$allTracks = array_column($musicFiles, 'file');
if (!$track || !in_array($track, $allTracks)) {
    $track = $allTracks[0] ?? '';
    error_log("Selected default track: $track in " . __FILE__ . " at line " . __LINE__);
}

// Handle sign out
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    try {
        session_destroy();
        setcookie('auth_token', '', time() - 3600, '/');
        error_log("User signed out successfully in " . __FILE__ . " at line " . __LINE__);
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        error_log("Error during sign out: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
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
    <title>Music Player - <?php echo htmlspecialchars($track ? ($musicFiles[array_search($track, array_column($musicFiles, 'file'))]['title'] ?? $track) : 'No Track'); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            margin:0;
            font-family:'Segoe UI',Tahoma,sans-serif;
            background:#222233;
            color:#eee;
            min-height:100vh;
            display:flex;
            flex-direction:column;
        }
        a { color:#77aaff; text-decoration:none; }
        a:hover { text-decoration:underline; }

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

        .container {
            flex:1;
            display:flex;
            overflow:hidden;
            height:100%;
            margin-top:60px;
        }
        .main-content {
            flex:1;
            display:flex;
            flex-direction:column;
            min-height:100%;
            overflow-y:auto;
        }
        .audio-player {
            width:100%;
            background:#111122;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:1rem;
            border-bottom:1px solid #333366;
        }
        .audio-wrapper {
            width:100%;
            max-width:800px;
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:0.5rem;
        }
        .main-audio {
            width:100%;
        }
        .controls {
            display:flex;
            align-items:center;
            gap:1rem;
            width:100%;
            max-width:800px;
        }
        .play-btn, .pause-btn {
            background:none;
            border:none;
            cursor:pointer;
            font-size:1.5rem;
            color:#6677ff;
            padding:0.25rem;
        }
        .play-btn:hover, .pause-btn:hover { color:#5555ff; }
        .progress-container {
            flex-grow:1;
            display:flex;
            align-items:center;
            gap:12px;
        }
        .progress-bar {
            flex-grow:1;
            height:8px;
            background:#333366;
            border-radius:4px;
            cursor:pointer;
        }
        .progress {
            height:100%;
            background:#6677ff;
            border-radius:4px;
            width:0;
        }
        .time-display {
            font-size:0.9rem;
            color:#aaa;
            min-width:110px;
            text-align:left;
            white-space:nowrap;
        }
        .volume-slider {
            width:100px;
            height:8px;
            background:#333366;
            border-radius:4px;
            cursor:pointer;
            appearance:none;
            outline:none;
        }
        .volume-slider::-webkit-slider-thumb {
            appearance:none;
            width:12px;
            height:12px;
            background:#6677ff;
            border-radius:50%;
            cursor:pointer;
        }
        .volume-slider::-moz-range-thumb {
            width:12px;
            height:12px;
            background:#6677ff;
            border-radius:50%;
            cursor:pointer;
        }
        .track-info {
            text-align:center;
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:0.5rem;
            padding:0.75rem;
        }
        .album-art {
            width:150px;
            height:150px;
            object-fit:cover;
            border-radius:8px;
            border:1px solid #ff69b4;
            margin-bottom:0.5rem;
        }
        .track-info p {
            font-size:0.9rem;
            color:#ccc;
        }
        .track-info .song-name {
            font-size:1.1rem;
            color:#77aaff;
            font-weight:600;
        }
        .track-info .artist, .track-info .album {
            font-size:1.1rem;
            color:#ccc;
        }
        .search-bar {
            padding:0.5rem;
            width:100%;
            max-width:600px;
            margin:1rem auto;
            border:1px solid #333366;
            border-radius:4px;
            background:#1a1a2e;
            color:#eee;
            font-size:0.9rem;
        }
        .library-container {
            flex:1;
            background:#1a1a2e;
            padding:1rem;
            overflow-y:auto;
            display:flex;
            gap:0.5rem;
        }
        .library-column {
            min-width:150px;
        }
        .library-column.tracks {
            flex:2;
        }
        .library-column h3 {
            font-size:0.9rem;
            color:#77aaff;
            margin-bottom:0.5rem;
        }
        .track-list, .artist-list, .album-list {
            list-style:none;
            padding:0;
        }
        .track-list {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(100px, 1fr));
            gap:0.2rem;
        }
        .track-list li {
            background:#222244;
            border-radius:6px;
            overflow:hidden;
            transition:transform 0.2s;
            margin-bottom:0.2rem;
        }
        .track-list li:hover {
            transform:scale(1.05);
        }
        .track-list li a {
            display:flex;
            flex-direction:column;
            padding:0.3rem;
            color:#eee;
            text-decoration:none;
            font-size:0.8rem;
        }
        .track-list li a.active {
            background:#6677ff;
            color:white;
        }
        .track-list img {
            width:100%;
            height:80px;
            object-fit:cover;
            border-bottom:1px solid #333366;
        }
        .track-name {
            font-weight:600;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .track-meta {
            font-size:0.7rem;
            color:#ccc;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .artist-list li a, .album-list li a {
            display:block;
            padding:0.4rem;
            color:#eee;
            border-radius:4px;
            transition:background 0.2s;
            font-size:1rem;
            margin-bottom:0.2rem;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .artist-list li a:hover, .album-list li a:hover {
            background:#333366;
        }
        .artist-list li a.active, .album-list li a.active {
            background:#6677ff;
            color:white;
        }
        .error-message {
            color:#ff5555;
            text-align:center;
            padding:1rem;
            font-size:0.9rem;
        }

        /* Modal styles */
        .modal {
            display:none;
            position:fixed;
            z-index:2000;
            left:0; top:0;
            width:100%; height:100%;
            background:rgba(0,0,0,0.7);
            align-items:center;
            justify-content:center;
        }
        .modal-content {
            background:#1a1a2e;
            padding:2rem;
            border-radius:8px;
            border:1px solid #333366;
            width:90%;
            max-width:520px;
            color:#eee;
            position:relative;
        }
        .close {
            position:absolute;
            right:1.5rem; top:1rem;
            color:#aaa;
            font-size:1.8rem;
            font-weight:bold;
            cursor:pointer;
        }
        .close:hover { color:#fff; }
        .modal h2 { color:#77aaff; margin-bottom:1.5rem; }
        .upload-section {
            margin:1.5rem 0;
            padding:1.2rem;
            background:#111122;
            border-radius:6px;
            border:1px solid #333366;
        }
        .upload-section h3 { margin-bottom:1rem; color:#77aaff; }
        .upload-section form { display:flex; flex-direction:column; gap:1rem; }
        .upload-section input[type="file"], .upload-section select {
            padding:0.6rem;
            background:#222244;
            border:1px solid #333366;
            color:#eee;
            border-radius:4px;
        }
        .upload-section button {
            background:#6677ff;
            color:white;
            border:none;
            padding:0.8rem;
            border-radius:6px;
            cursor:pointer;
        }
        .upload-section button:hover { background:#5555ff; }
        .progress-container-modal {
            height:6px;
            background:#333366;
            border-radius:3px;
            margin:0.6rem 0;
            overflow:hidden;
        }
        .progress-bar-modal {
            height:100%;
            background:#6677ff;
            width:0%;
            transition:width 0.2s;
        }
        .status-message {
            min-height:1.3rem;
            padding:0.6rem;
            border-radius:4px;
            font-size:0.95rem;
        }
        .status-message.success { background:#2a6633; color:#d4f4d4; }
        .status-message.error { background:#662a2a; color:#f4d4d4; }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .navbar-links { display:none; }
            .hamburger { display:block; }
            .navbar-right { gap:0.8rem; }
        }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="navbar-left">
            <button class="hamburger" id="hamburgerBtn">☰</button>
            <div class="navbar-links" id="navLinks">
                <a href="watch.php" class="nav-link">Videos</a>
                <a href="music_player.php" class="nav-link active">Music</a>
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
                    <a href="#" onclick="openModal()">Upload Files</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile menu dropdown -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="watch.php" class="nav-link">Videos</a>
        <a href="music_player.php" class="nav-link active">Music</a>
        <a href="gallery.php" class="nav-link">Gallery</a>
    </div>

    <?php if (!isset($_SESSION['age_popup_shown']) && !empty($settings['age_restriction'])): ?>
        <script>alert("This website is for only 18+.");</script>
        <?php $_SESSION['age_popup_shown'] = true; ?>
    <?php endif; ?>

    <div class="container">
        <div class="main-content">
            <div class="audio-player">
                <div class="audio-wrapper">
                    <?php if ($track && in_array($track, $allTracks)): ?>
                        <audio class="main-audio" autoplay>
                            <source src="<?php echo htmlspecialchars('music/' . rawurlencode($track)); ?>" type="audio/mpeg">
                            Your browser does not support the audio tag.
                        </audio>
                        <div class="track-info">
                            <?php $trackInfo = $musicFiles[array_search($track, array_column($musicFiles, 'file'))] ?? ['title' => 'Unknown', 'artist' => 'Unknown', 'album' => 'Unknown', 'album_art' => 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==']; ?>
                            <img src="<?php echo htmlspecialchars($trackInfo['album_art']); ?>" alt="Album Art" class="album-art">
                            <p class="song-name"><?php echo htmlspecialchars($trackInfo['title']); ?></p>
                            <p class="artist">Artist: <?php echo htmlspecialchars($trackInfo['artist']); ?></p>
                            <p class="album">Album: <?php echo htmlspecialchars($trackInfo['album']); ?></p>
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
                        </div>
                    <?php else: ?>
                        <p class="error-message"><?php echo $track ? 'Unsupported audio format or file not found.' : 'No music files found in the music directory.'; ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="library-container">
                <div class="library-column">
                    <h3>Artists</h3>
                    <ul class="artist-list">
                        <li><a href="music_player.php" class="<?php echo (!$filterType) ? 'active' : ''; ?>">All Music</a></li>
                        <?php foreach ($artists as $artist): ?>
                            <li><a href="?artist=<?php echo urlencode($artist); ?>" class="<?php echo ($filterType === 'artist' && $filterValue === $artist) ? 'active' : ''; ?>"><?php echo htmlspecialchars($artist); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="library-column">
                    <h3>Albums</h3>
                    <ul class="album-list">
                        <li><a href="music_player.php" class="<?php echo (!$filterType) ? 'active' : ''; ?>">All Music</a></li>
                        <?php foreach ($albums as $album): ?>
                            <li><a href="?album=<?php echo urlencode($album); ?>" class="<?php echo ($filterType === 'album' && $filterValue === $album) ? 'active' : ''; ?>"><?php echo htmlspecialchars($album); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="library-column tracks">
                    <h3>Tracks</h3>
                    <input type="text" class="search-bar" placeholder="Search tracks, artists, or albums...">
                    <ul class="track-list">
                        <?php foreach ($filteredTracks as $trk): ?>
                            <li>
                                <a href="?track=<?php echo rawurlencode($trk['file']); ?><?php echo $filterType ? '&' . $filterType . '=' . urlencode($filterValue) : ''; ?>" 
                                   class="<?php echo $trk['file'] === $track ? 'active' : ''; ?>" 
                                   title="<?php echo htmlspecialchars($trk['title'] . ' - ' . $trk['artist']); ?>">
                                    <img src="<?php echo htmlspecialchars($trk['album_art']); ?>" alt="Album Art">
                                    <span class="track-name"><?php echo htmlspecialchars($trk['title']); ?></span>
                                    <span class="track-meta"><?php echo htmlspecialchars($trk['artist'] . ' - ' . $trk['album']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Upload Files</h2>

            <div class="upload-section">
                <h3>Upload MP3</h3>
                <form id="mp3Form" enctype="multipart/form-data">
                    <input type="file" name="music" accept=".mp3" required>
                    <button type="submit">Upload MP3</button>
                </form>
                <div class="progress-container-modal"><div id="mp3Progress" class="progress-bar-modal"></div></div>
                <div id="mp3-message" class="status-message"></div>
            </div>

            <div class="upload-section">
                <h3>Upload Cover Art (optional)</h3>
                <form id="coverForm" enctype="multipart/form-data">
                    <select name="album" required>
                        <option value="">-- Select album --</option>
                        <?php foreach ($albums as $album): ?>
                            <option value="<?php echo htmlspecialchars($album); ?>"><?php echo htmlspecialchars($album); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp" required>
                    <button type="submit">Upload Cover</button>
                </form>
                <div class="progress-container-modal"><div id="coverProgress" class="progress-bar-modal"></div></div>
                <div id="cover-message" class="status-message"></div>
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

        // Cookie helpers
        function setCookie(name, value, days = 60) {
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
            const audio = document.querySelector('.main-audio');
            if (!audio) return;

            const playBtn      = document.querySelector('.play-btn');
            const pauseBtn     = document.querySelector('.pause-btn');
            const progressBar  = document.querySelector('.progress-bar');
            const progress     = document.querySelector('.progress');
            const timeDisplay  = document.querySelector('.time-display');
            const volumeSlider = document.querySelector('.volume-slider');
            const searchInput  = document.querySelector('.search-bar');
            const trackList    = document.querySelector('.track-list');

            // Volume persistence
            const savedVolume = getCookie('musicVolume');
            if (savedVolume !== null) {
                audio.volume = parseFloat(savedVolume);
                volumeSlider.value = audio.volume;
            } else {
                audio.volume = 0.8;
                volumeSlider.value = 0.8;
            }

            volumeSlider.addEventListener('input', () => {
                audio.volume = volumeSlider.value;
                setCookie('musicVolume', audio.volume.toFixed(3));
            });

            // Play / Pause
            playBtn.addEventListener('click', () => {
                audio.play().catch(() => {});
                playBtn.style.display = 'none';
                pauseBtn.style.display = 'inline-block';
            });
            pauseBtn.addEventListener('click', () => {
                audio.pause();
                pauseBtn.style.display = 'none';
                playBtn.style.display = 'inline-block';
            });

            // Progress + Time
            function formatTime(seconds) {
                if (isNaN(seconds) || seconds < 0) return "0:00";
                const mins = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return mins + ":" + (secs < 10 ? "0" : "") + secs;
            }

            audio.addEventListener('timeupdate', () => {
                if (!audio.duration || isNaN(audio.duration)) return;
                const percent = (audio.currentTime / audio.duration) * 100;
                progress.style.width = percent + '%';
                timeDisplay.textContent = formatTime(audio.currentTime) + " / " + formatTime(audio.duration);
            });

            audio.addEventListener('loadedmetadata', () => {
                timeDisplay.textContent = "0:00 / " + formatTime(audio.duration);
            });

            // Seek
            progressBar.addEventListener('click', (e) => {
                const rect = progressBar.getBoundingClientRect();
                const pos = (e.clientX - rect.left) / rect.width;
                audio.currentTime = pos * audio.duration;
            });

            // Search
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.toLowerCase();
                trackList.querySelectorAll('li').forEach(item => {
                    const name = item.querySelector('.track-name')?.textContent.toLowerCase() || '';
                    const meta = item.querySelector('.track-meta')?.textContent.toLowerCase() || '';
                    item.style.display = (name.includes(query) || meta.includes(query)) ? '' : 'none';
                });
            });

            // Initial state
            pauseBtn.style.display = 'none';
            if (audio.autoplay) {
                playBtn.style.display = 'none';
                pauseBtn.style.display = 'inline-block';
            }
        });

        // Modal controls
        function openModal() {
            document.getElementById('uploadModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('uploadModal').style.display = 'none';
            document.getElementById('mp3-message').innerHTML = '';
            document.getElementById('cover-message').innerHTML = '';
            document.getElementById('mp3Progress').style.width = '0%';
            document.getElementById('coverProgress').style.width = '0%';
        }

        window.onclick = function(event) {
            if (event.target.id === 'uploadModal') {
                closeModal();
            }
        };

        // Upload handler
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
                        console.log('Upload response:', data);
                    } catch (err) {
                        msgDiv.className = 'status-message error';
                        msgDiv.innerHTML = 'Invalid server response';
                        console.error('Raw response was:', xhr.responseText);
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

        document.addEventListener('DOMContentLoaded', () => {
            handleUpload('mp3Form',   'mp3Progress',   'mp3-message',   'MP3 uploaded successfully!');
            handleUpload('coverForm', 'coverProgress', 'cover-message', 'Cover art uploaded successfully!');
        });
    </script>
</body>
</html>
<?php
error_log("music_player.php execution completed in " . __FILE__ . " at line " . __LINE__);
?>