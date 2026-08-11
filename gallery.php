<?php
// gallery.php - Image gallery with centered preview + upload modal (updated navbar)

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_log("Starting gallery.php at " . date('Y-m-d H:i:s'));

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

// Gallery directory
$galleryDir = __DIR__ . '/gallery/';
$galleryWebPath = 'gallery/';

if (!file_exists($galleryDir)) {
    mkdir($galleryDir, 0755, true);
}

// Load images
$images = [];
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
if (is_dir($galleryDir)) {
    $files = scandir($galleryDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $images[] = [
                'file' => $file,
                'path' => $galleryWebPath . $file
            ];
        }
    }
    // Sort newest first
    usort($images, function($a, $b) {
        return filemtime($galleryDir . $b['file']) <=> filemtime($galleryDir . $a['file']);
    });
}

// Selected image
$selected = isset($_GET['img']) ? basename($_GET['img']) : ($images[0]['file'] ?? '');
$selectedPath = $selected ? $galleryWebPath . $selected : '';

// Sign out
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    setcookie('auth_token', '', time() - 3600, '/');
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gallery - Robert</title>
        <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/gallery.css">
</head>
<body>

    <div class="navbar">
        <div class="navbar-left">
            <button class="hamburger" id="hamburgerBtn">☰</button>
            <div class="navbar-links" id="navLinks">
                <a href="watch.php" class="nav-link">Videos</a>
                <a href="music_player.php" class="nav-link">Music</a>
                <a href="gallery.php" class="nav-link active">Gallery</a>
            </div>
        </div>

        <div class="navbar-right">
            <a href="?action=logout">Sign Out</a>
            <a href="media_manager.php">Media Manager</a>
            <a href="admin/index.php">Admin</a>

            <div class="dropdown">
                <button class="dropbtn">Upload ▼</button>
                <div class="dropdown-content">
                    <a href="#" id="openGalleryUpload">Upload Files</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile menu dropdown -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="watch.php" class="nav-link">Videos</a>
        <a href="music_player.php" class="nav-link">Music</a>
        <a href="gallery.php" class="nav-link active">Gallery</a>
    </div>

    <div class="main-content">
        <div class="preview-container">
            <?php if ($selectedPath): ?>
                <img id="previewImg" src="<?= htmlspecialchars($selectedPath) ?>" alt="Selected preview">
            <?php else: ?>
                <div class="no-preview">Click an image below to preview</div>
            <?php endif; ?>
        </div>

        <?php if (empty($images)): ?>
            <p class="error-message">No images found in the gallery directory.</p>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($images as $img): ?>
                    <a href="?img=<?= urlencode($img['file']) ?>" class="gallery-thumb <?= $img['file'] === $selected ? 'active' : '' ?>">
                        <img src="<?= htmlspecialchars($img['path']) ?>" alt="<?= htmlspecialchars($img['file']) ?>" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('uploadModal').style.display='none'">&times;</span>
            <h2>Upload Images</h2>

            <div class="upload-section">
                <h3>Upload Image (jpg, jpeg, png, webp, gif)</h3>
                <form id="imageForm" enctype="multipart/form-data">
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif" required>
                    <button type="submit">Upload Image</button>
                </form>
                <div class="progress-container-modal"><div id="imageProgress" class="progress-bar-modal"></div></div>
                <div id="image-message" class="status-message"></div>
            </div>

            <div class="upload-section">
                <h3>Upload Thumbnail (optional, for existing image)</h3>
                <form id="thumbForm" enctype="multipart/form-data">
                    <select name="image" required>
                        <option value="">-- Select image --</option>
                        <?php foreach ($images as $img): ?>
                            <option value="<?= htmlspecialchars($img['file']) ?>">
                                <?= htmlspecialchars($img['file']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.webp" required>
                    <button type="submit">Upload Thumbnail</button>
                </form>
                <div class="progress-container-modal"><div id="thumbProgress" class="progress-bar-modal"></div></div>
                <div id="thumb-message" class="status-message"></div>
            </div>
        </div>
    </div>

        <script src="assets/js/gallery.js"></script>
</body>
</html>
<?php
error_log("gallery.php completed - found " . count($images) . " images");
?>