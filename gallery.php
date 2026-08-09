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

        .main-content {
            flex:1;
            margin-top:70px;
            padding:1rem;
            display:flex;
            flex-direction:column;
            align-items:center;
        }

        .preview-container {
            width:100%;
            max-width:500px;
            background:#111122;
            border-radius:16px;
            overflow:hidden;
            margin-bottom:2rem;
            box-shadow:0 8px 32px rgba(0,0,0,0.5);
            display:flex;
            justify-content:center;
            align-items:center;
        }
        .preview-container img {
            max-width:100%;
            max-height:70vh;
            display:block;
            object-fit:contain;
            transition:transform 0.15s ease-out;
        }
        .no-preview {
            height:350px;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#666;
            font-size:1.2rem;
        }

        .gallery-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(160px, 1fr));
            gap:1.2rem;
            width:100%;
            max-width:1400px;
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .navbar-links { display:none; }
            .hamburger { display:block; }
            .navbar-right { gap:0.8rem; }
            .preview-container { max-width:90vw; margin-bottom:1rem; }
            .preview-container img { max-height:55vh; }
            .gallery-grid { grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:0.9rem; }
        }

        .gallery-thumb {
            background:#222244;
            border-radius:12px;
            overflow:hidden;
            cursor:pointer;
            transition:transform 0.2s, box-shadow 0.2s;
            aspect-ratio:1/1;
        }
        .gallery-thumb:hover {
            transform:scale(1.06);
            box-shadow:0 8px 24px rgba(0,0,0,0.4);
        }
        .gallery-thumb img {
            width:100%;
            height:100%;
            object-fit:cover;
        }

        .error-message {
            text-align:center;
            padding:3rem;
            color:#ff5555;
            font-size:1.2rem;
        }

        /* Modal - same as watch/music */
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
    </style>
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
            <a href="admin.php">Admin</a>

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

        // Zoom only (centered, no panning/dragging)
        const previewImg = document.getElementById('previewImg');
        if (previewImg) {
            let scale = 1;

            // Mouse wheel zoom (desktop)
            previewImg.addEventListener('wheel', (e) => {
                e.preventDefault();
                const delta = e.deltaY < 0 ? 0.1 : -0.1;
                scale = Math.max(0.5, Math.min(5, scale + delta));
                previewImg.style.transform = `scale(${scale})`;
                previewImg.style.transformOrigin = 'center center';
            });

            // Pinch zoom (mobile/touch)
            let startDistance = 0;
            previewImg.addEventListener('touchstart', (e) => {
                if (e.touches.length === 2) {
                    e.preventDefault();
                    const touch1 = e.touches[0];
                    const touch2 = e.touches[1];
                    startDistance = Math.hypot(
                        touch2.clientX - touch1.clientX,
                        touch2.clientY - touch1.clientY
                    );
                }
            }, { passive: false });

            previewImg.addEventListener('touchmove', (e) => {
                if (e.touches.length === 2) {
                    e.preventDefault();
                    const touch1 = e.touches[0];
                    const touch2 = e.touches[1];
                    const currentDistance = Math.hypot(
                        touch2.clientX - touch1.clientX,
                        touch2.clientY - touch1.clientY
                    );

                    const delta = currentDistance / startDistance;
                    scale = Math.max(0.5, Math.min(5, scale * delta));
                    previewImg.style.transform = `scale(${scale})`;
                    previewImg.style.transformOrigin = 'center center';

                    startDistance = currentDistance;
                }
            }, { passive: false });
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

        handleUpload('imageForm', 'imageProgress', 'image-message', 'Image uploaded successfully!');
        handleUpload('thumbForm', 'thumbProgress', 'thumb-message', 'Thumbnail uploaded successfully!');

        // Open modal
        const openLink = document.getElementById('openGalleryUpload');
        if (openLink) {
            openLink.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const modal = document.getElementById('uploadModal');
                if (modal) modal.style.display = 'flex';
            });
        }
    </script>

</body>
</html>
<?php
error_log("gallery.php completed - found " . count($images) . " images");
?>