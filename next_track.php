<?php
// next_track.php - Returns the next track's details as JSON

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_log("Starting next_track.php execution in " . __FILE__ . " at line " . __LINE__);

// Start session
try {
    session_start();
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        error_log("Unauthenticated access attempt in " . __FILE__ . " at line " . __LINE__);
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
} catch (Exception $e) {
    error_log("Session error: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
    http_response_code(500);
    echo json_encode(['error' => 'Session initialization failed']);
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
                $album_base = $info['artist'] . ' - ' . $info['album'];
                $album_art = null;
                foreach (['jpg', 'png', 'webp'] as $ext) {
                    $art_file = $musicDir . $album_base . '.' . $ext;
                    if (file_exists($art_file)) {
                        $album_art = 'music/' . $album_base . '.' . $ext;
                        break;
                    }
                }
                $info['album_art'] = $album_art ?: 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
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

// Get parameters
$current = isset($_GET['current']) ? $_GET['current'] : '';
$filterType = isset($_GET['filterType']) ? $_GET['filterType'] : null;
$filterValue = isset($_GET['filterValue']) ? $_GET['filterValue'] : null;

// Apply filters
$filteredTracks = $musicFiles;
if ($filterType && $filterValue) {
    if ($filterType === 'artist' && in_array($filterValue, $artists)) {
        $filteredTracks = array_filter($musicFiles, function($m) use ($filterValue) {
            return $m['artist'] === $filterValue;
        });
    } elseif ($filterType === 'album' && in_array($filterValue, $albums)) {
        $filteredTracks = array_filter($musicFiles, function($m) use ($filterValue) {
            return $m['album'] === $filterValue;
        });
    }
}
$filteredTracks = array_values($filteredTracks); // Reindex array after filter

// Find current index
$currentIndex = -1;
foreach ($filteredTracks as $index => $trk) {
    if ($trk['file'] === $current) {
        $currentIndex = $index;
        break;
    }
}

// Handle case where current track isn't found
if ($currentIndex === -1 && !empty($filteredTracks)) {
    $currentIndex = 0; // Default to first track if current not found
    error_log("Current track not found, defaulting to first track in " . __FILE__ . " at line " . __LINE__);
}

// Get next index (wrap around)
$nextIndex = ($currentIndex + 1) % count($filteredTracks);
if ($nextIndex < 0 || $nextIndex >= count($filteredTracks)) {
    $nextIndex = 0; // Ensure valid index
}
$nextInfo = $filteredTracks[$nextIndex] ?? $filteredTracks[0]; // Fallback to first track if issue

// Output JSON
header('Content-Type: application/json');
echo json_encode([
    'title' => $nextInfo['title'],
    'artist' => $nextInfo['artist'],
    'album' => $nextInfo['album'],
    'file' => $nextInfo['file'],
    'album_art' => $nextInfo['album_art']
]);
?>