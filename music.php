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
        <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/music.css">
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
                <li><a href="admin/settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
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
        <script src="assets/js/music.js"></script>
</body>
</html>
<?php
error_log("music.php execution completed at " . __FILE__ . " line " . __LINE__ . " on " . date('Y-m-d H:i:s'));
?>