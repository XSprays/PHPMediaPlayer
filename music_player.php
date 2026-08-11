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
        <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/music-player.css">
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
            <a href="admin/index.php">Admin</a>

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

        <script src="assets/js/music-player.js"></script>
</body>
</html>
<?php
error_log("music_player.php execution completed in " . __FILE__ . " at line " . __LINE__);
?>