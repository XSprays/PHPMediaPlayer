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
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            margin:0;
            font-family:'Segoe UI', system-ui, sans-serif;
            background:#0d0d1a;
            color:#e0e0ff;
            min-height:100vh;
            display:flex;
            flex-direction:column;
        }
        a { color:#88aaff; text-decoration:none; }
        a:hover { text-decoration:underline; }

        .navbar {
            background:#141426;
            padding:1rem 1.5rem;
            border-bottom:1px solid #2a2a44;
            display:flex;
            justify-content:space-between;
            align-items:center;
            position:fixed;
            top:0;
            width:100%;
            z-index:1000;
            box-shadow:0 4px 16px rgba(0,0,0,0.5);
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
            background:#141426;
            border-bottom:1px solid #2a2a44;
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
            background:#141426;
            min-width:160px;
            box-shadow:0 8px 16px rgba(0,0,0,0.4);
            border-radius:4px;
            z-index:1001;
            border:1px solid #2a2a44;
        }
        .dropdown-content a {
            color:#eee;
            padding:12px 16px;
            display:block;
        }
        .dropdown-content a:hover { background:#333366; }
        .dropdown:hover .dropdown-content { display:block; }

        .main-content {
            flex:1;
            margin-top:80px;
            padding:2.5rem 1.5rem;
            max-width:1600px;
            margin-left:auto;
            margin-right:auto;
        }

        .section { margin-bottom:5rem; }
        .section h2 {
            color:#99bbff;
            font-size:1.9rem;
            margin-bottom:1.8rem;
            padding-bottom:0.8rem;
            border-bottom:2px solid #334466;
        }

        .search-bar {
            width:100%;
            max-width:700px;
            padding:1rem 1.4rem;
            margin:0 auto 2rem;
            border:1px solid #334466;
            border-radius:12px;
            background:#1a1a2e;
            color:#e0e0ff;
            font-size:1.05rem;
            outline:none;
            transition:all 0.3s;
        }
        .search-bar:focus {
            border-color:#88aaff;
            box-shadow:0 0 0 4px rgba(136,170,255,0.15);
        }

        .media-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
            gap:1.8rem;
        }
        .media-card {
            background:#17172e;
            border-radius:16px;
            overflow:hidden;
            box-shadow:0 8px 24px rgba(0,0,0,0.4);
            transition:transform 0.3s, box-shadow 0.3s;
            display:flex;
            flex-direction:column;
        }
        .media-card:hover {
            transform:translateY(-8px);
            box-shadow:0 16px 40px rgba(0,0,0,0.6);
        }
        .media-preview {
            width:100%;
            height:180px;
            background:#0d0d1a;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .media-preview img, .media-preview video {
            width:100%;
            height:100%;
            object-fit:cover;
        }
        .media-info {
            padding:1.2rem;
            flex-grow:1;
            display:flex;
            flex-direction:column;
            gap:1rem;
        }
        .media-name {
            font-size:1.05rem;
            font-weight:500;
            color:#d0d0ff;
            line-height:1.3;
        }
        .media-actions {
            display:flex;
            flex-wrap:wrap;
            gap:0.8rem;
            margin-top:auto;
        }
        .media-actions button,
        .media-actions .upload-btn {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:0.5rem;
            padding:0.7rem 1.2rem;
            border:none;
            border-radius:10px;
            font-size:0.95rem;
            cursor:pointer;
            transition:all 0.2s;
            min-width:140px;
            font-weight:500;
        }
        .btn-primary {
            background:#6677ff;
            color:white;
        }
        .btn-primary:hover { background:#5566ee; transform:translateY(-2px); }
        .btn-danger {
            background:#ff5555;
            color:white;
        }
        .btn-danger:hover { background:#dd4444; transform:translateY(-2px); }
        .btn-secondary {
            background:#444466;
            color:#d0d0ff;
        }
        .btn-secondary:hover { background:#555577; transform:translateY(-2px); }

        .upload-form {
            display:inline-block;
        }
        .upload-form label {
            margin:0;
        }

        .progress-container {
            height:4px;
            background:#2a2a44;
            margin-top:0.5rem;
            border-radius:2px;
            overflow:hidden;
            display:none;
            width:100%;
        }
        .progress-bar {
            height:100%;
            background:#6677ff;
            width:0%;
            transition:width 0.2s;
        }
        .status-message {
            font-size:0.85rem;
            margin-top:0.4rem;
            padding:0.4rem 0.8rem;
            border-radius:6px;
            display:none;
        }
        .status-success { background:#2a6633; color:#d4f4d4; }
        .status-error { background:#662a2a; color:#f4d4d4; }

        .no-media {
            text-align:center;
            color:#777799;
            padding:4rem 1rem;
            font-size:1.2rem;
        }

        @media (max-width: 768px) {
            .navbar-links { display:none; }
            .hamburger { display:block; }
            .navbar-right { gap:0.8rem; }
            .media-actions {
                flex-direction:column;
                align-items:stretch;
            }
            .media-actions button,
            .media-actions .upload-btn {
                width:100%;
                justify-content:center;
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
            <a href="media_manager.php" class="nav-link active">Media Manager</a>
            <a href="admin.php">Admin</a>

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

        // Delete item
        function deleteItem(type, file) {
            if (!confirm(`Delete ${file} permanently?`)) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&type=${type}&file=${encodeURIComponent(file)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(`${file} deleted successfully`);
                    location.reload();
                } else {
                    alert(data.error || 'Delete failed - check permissions');
                }
            })
            .catch(() => alert('Network error'));
        }

        // Rename
        function promptRename(type, file) {
            const newName = prompt(`Rename ${file} to:`, file);
            if (!newName || newName === file) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=rename&type=${type}&file=${encodeURIComponent(file)}&new_name=${encodeURIComponent(newName)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(`Renamed to ${newName}`);
                    location.reload();
                } else {
                    alert(data.error || 'Rename failed');
                }
            });
        }

        // Thumbnail upload
        document.querySelectorAll('input[type="file"][name="thumbnail"]').forEach(input => {
            input.addEventListener('change', function() {
                if (!this.files.length) return;

                const form = this.closest('form');
                const fileName = form.querySelector('input[name="file"]').value;
                if (!confirm(`Upload thumbnail for ${fileName}?`)) {
                    this.value = '';
                    return;
                }

                const formData = new FormData(form);
                const progressBar = form.querySelector('.progress-bar');
                const statusDiv = form.querySelector('.status-message');
                const progressContainer = form.querySelector('.progress-container');

                progressContainer.style.display = 'block';
                progressBar.style.width = '0%';
                statusDiv.style.display = 'none';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);

                xhr.upload.onprogress = ev => {
                    if (ev.lengthComputable) {
                        const percent = (ev.loaded / ev.total) * 100;
                        progressBar.style.width = percent + '%';
                    }
                };

                xhr.onload = () => {
                    progressContainer.style.display = 'none';
                    let data;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch {
                        statusDiv.className = 'status-message status-error';
                        statusDiv.textContent = 'Server error';
                        statusDiv.style.display = 'block';
                        return;
                    }

                    if (data.success) {
                        statusDiv.className = 'status-message status-success';
                        statusDiv.textContent = 'Thumbnail uploaded!';
                        statusDiv.style.display = 'block';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        statusDiv.className = 'status-message status-error';
                        statusDiv.textContent = data.error || 'Upload failed';
                        statusDiv.style.display = 'block';
                    }
                };

                xhr.onerror = () => {
                    progressContainer.style.display = 'none';
                    statusDiv.className = 'status-message status-error';
                    statusDiv.textContent = 'Network error';
                    statusDiv.style.display = 'block';
                };

                xhr.send(formData);
            });
        });

        // Music search
        function filterMusic() {
            const query = document.getElementById('musicSearch').value.toLowerCase();
            document.querySelectorAll('#musicItems .media-card').forEach(card => {
                const name = card.querySelector('.media-name').textContent.toLowerCase();
                card.style.display = name.includes(query) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
<?php
error_log("media_manager.php completed");
?>