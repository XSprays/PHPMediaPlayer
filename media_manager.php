<?php
// media_manager.php - Final polished version with perfect button styling & alignment

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_log("Starting media_manager.php at " . date('Y-m-d H:i:s'));

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

// Directories
$videosDir  = rtrim(__DIR__ . '/videos/', '/') . '/';
$musicDir   = rtrim(__DIR__ . '/music/', '/') . '/';
$galleryDir = rtrim(__DIR__ . '/gallery/', '/') . '/';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $type = $_POST['type'] ?? '';
        $file = $_POST['file'] ?? '';
        $dir = $type === 'video' ? $videosDir : ($type === 'music' ? $musicDir : $galleryDir);
        $path = $dir . basename($file);
        error_log("Delete attempt: type=$type, file=$file, path=$path");
        if (file_exists($path)) {
            if (unlink($path)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Unlink failed - check permissions']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'File not found at: ' . $path]);
        }
        exit;
    }

    if ($action === 'rename') {
        $type = $_POST['type'] ?? '';
        $file = $_POST['file'] ?? '';
        $new_name = trim($_POST['new_name'] ?? '');
        if (!$new_name) {
            echo json_encode(['success' => false, 'error' => 'New name required']);
            exit;
        }
        $dir = $type === 'video' ? $videosDir : ($type === 'music' ? $musicDir : $galleryDir);
        $oldPath = $dir . basename($file);
        $newPath = $dir . basename($new_name);
        if (file_exists($oldPath) && rename($oldPath, $newPath)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Rename failed - check file exists and permissions']);
        }
        exit;
    }

    if ($action === 'upload_thumbnail') {
        $file = $_POST['file'] ?? '';
        if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== 0) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            exit;
        }
        $baseName = pathinfo($file, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            echo json_encode(['success' => false, 'error' => 'Only jpg, jpeg, png, webp allowed']);
            exit;
        }
        $thumbPath = $videosDir . $baseName . '.' . $ext;
        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbPath)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save thumbnail - check directory permissions']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Load videos (thumbnail priority for preview)
$videos = [];
if (is_dir($videosDir)) {
    $videoFiles = glob($videosDir . '*.{mp4,mkv}', GLOB_BRACE);
    foreach ($videoFiles as $file) {
        $filename = basename($file);
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $thumbnail = null;
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $thumbPath = $videosDir . $baseName . '.' . $ext;
            if (file_exists($thumbPath)) {
                $thumbnail = 'videos/' . $baseName . '.' . $ext . '?v=' . filemtime($thumbPath);
                break;
            }
        }
        $videos[] = [
            'file' => $filename,
            'path' => 'videos/' . $filename,
            'thumbnail' => $thumbnail
        ];
    }
    sort($videos);
}

// Load music
$music = [];
if (is_dir($musicDir)) {
    $musicFiles = glob($musicDir . '*.mp3');
    foreach ($musicFiles as $file) {
        $music[] = ['file' => basename($file), 'path' => 'music/' . basename($file)];
    }
    sort($music);
}

// Load pictures (Gallery)
$images = [];
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
if (is_dir($galleryDir)) {
    $files = scandir($galleryDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $images[] = ['file' => $file, 'path' => 'gallery/' . $file];
        }
    }
    sort($images);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Media Manager - Robert</title>
        <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/media-manager.css">
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
            <a href="media_manager.php" class="nav-link active">Media Manager</a>
            <a href="admin/index.php">Admin</a>

            <div class="dropdown">
                <button class="dropbtn">Upload ▼</button>
                <div class="dropdown-content">
                    <a href="#" id="openMediaUpload">Upload Files</a>
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

    <div class="main-content">
        <!-- Videos -->
        <div class="section">
            <h2>Videos</h2>
            <div class="media-grid">
                <?php foreach ($videos as $vid): $id = md5($vid['file']); ?>
                    <div class="media-card">
                        <div class="media-preview">
                            <?php if ($vid['thumbnail']): ?>
                                <img src="<?= htmlspecialchars($vid['thumbnail']) ?>" alt="Thumbnail" loading="lazy">
                            <?php else: ?>
                                <video controls preload="metadata">
                                    <source src="<?= htmlspecialchars($vid['path']) ?>" type="video/mp4">
                                </video>
                            <?php endif; ?>
                        </div>
                        <div class="media-info">
                            <div class="media-name"><?= htmlspecialchars($vid['file']) ?></div>
                            <div class="media-actions">
                                <button class="btn-secondary" onclick="promptRename('video', '<?= addslashes($vid['file']) ?>')">✏️ Rename</button>

                                <div class="upload-form">
                                    <form id="upload-<?= $id ?>" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="upload_thumbnail">
                                        <input type="hidden" name="type" value="video">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars($vid['file']) ?>">
                                        <label for="thumb-input-<?= $id ?>" class="upload-btn btn-primary">
                                            🖼️ Add Thumbnail
                                        </label>
                                        <input type="file" id="thumb-input-<?= $id ?>" name="thumbnail" accept=".jpg,.jpeg,.png,.webp" style="display:none;">
                                    </form>
                                    <div class="progress-container"><div class="progress-bar" id="prog-<?= $id ?>"></div></div>
                                    <div class="status-message" id="status-<?= $id ?>"></div>
                                </div>

                                <button class="btn-danger" onclick="deleteItem('video', '<?= addslashes($vid['file']) ?>')">🗑️ Delete</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($videos)): ?>
                    <div class="no-media">No videos found.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Music -->
        <div class="section">
            <h2>Music</h2>
            <div class="search-container">
                <input type="text" class="search-bar" id="musicSearch" placeholder="Search songs..." oninput="filterMusic()">
            </div>
            <div class="media-grid" id="musicItems">
                <?php foreach ($music as $song): $id = md5($song['file']); ?>
                    <div class="media-card">
                        <div class="media-preview">
                            <audio controls preload="metadata">
                                <source src="<?= htmlspecialchars($song['path']) ?>" type="audio/mpeg">
                            </audio>
                        </div>
                        <div class="media-info">
                            <div class="media-name"><?= htmlspecialchars($song['file']) ?></div>
                            <div class="media-actions">
                                <button class="btn-danger" onclick="deleteItem('music', '<?= addslashes($song['file']) ?>')">🗑️ Delete</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($music)): ?>
                    <div class="no-media">No music found.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pictures / Gallery -->
        <div class="section">
            <h2>Pictures</h2>
            <div class="media-grid">
                <?php foreach ($images as $img): $id = md5($img['file']); ?>
                    <div class="media-card">
                        <div class="media-preview">
                            <img src="<?= htmlspecialchars($img['path']) ?>" alt="<?= htmlspecialchars($img['file']) ?>" loading="lazy">
                        </div>
                        <div class="media-info">
                            <div class="media-name"><?= htmlspecialchars($img['file']) ?></div>
                            <div class="media-actions">
                                <button class="btn-danger" onclick="deleteItem('picture', '<?= addslashes($img['file']) ?>')">🗑️ Delete</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($images)): ?>
                    <div class="no-media">No pictures found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

        <script src="assets/js/media-manager.js"></script>
</body>
</html>
<?php
error_log("media_manager.php completed");
?>