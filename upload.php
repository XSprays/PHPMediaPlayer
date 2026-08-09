<?php
// upload.php - Pure PHP upload handler (no HTML here!)
// Last working version restored + MKV support added

session_start();

// Authentication check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errorCode' => 'AUTH_001', 'error' => 'Unauthorized']);
    error_log("Unauthorized access attempt to upload.php at " . __FILE__ . " line " . __LINE__);
    exit;
}

// Force JSON & error logging
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = 'Fatal PHP error: ' . $error['message'] . ' in ' . $error['file'] . ' line ' . $error['line'];
        error_log($msg);
        echo json_encode(['success' => false, 'errorCode' => 'FATAL_001', 'error' => 'Server error during upload']);
        exit;
    }
});

// Log request
error_log("upload.php called - FILES: " . json_encode($_FILES));

// Non-POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'errorCode' => 'REQ_001', 'error' => 'Only POST allowed']);
    exit;
}

// Directories
$musicDir = __DIR__ . '/music/';
$videosDir = __DIR__ . '/videos/';

// Create/check dirs
foreach ([$musicDir, $videosDir] as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo json_encode(['success' => false, 'errorCode' => 'DIR_001', 'error' => 'Failed to create directory', 'path' => $dir]);
            exit;
        }
    }
    if (!is_writable($dir)) {
        echo json_encode(['success' => false, 'errorCode' => 'DIR_002', 'error' => 'Directory not writable', 'path' => $dir]);
        exit;
    }
}

// Music upload
if (isset($_FILES['music']) && $_FILES['music']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['music'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext !== 'mp3') {
        echo json_encode(['success' => false, 'errorCode' => 'FILE_001', 'error' => 'Only MP3 allowed']);
        exit;
    }

    $destPath = $musicDir . basename($file['name']);

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        chmod($destPath, 0644);
        echo json_encode(['success' => true, 'message' => 'Music uploaded', 'filename' => basename($destPath)]);
    } else {
        echo json_encode(['success' => false, 'errorCode' => 'MOVE_001', 'error' => 'Failed to save file']);
    }
    exit;
}

// Cover art upload
if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK && !empty($_POST['album'])) {
    $album = preg_replace('/[^a-zA-Z0-9 _-]/', '', trim($_POST['album']));
    $albumDir = $musicDir . $album . '/';

    if (!is_dir($albumDir) && !mkdir($albumDir, 0755, true)) {
        echo json_encode(['success' => false, 'errorCode' => 'DIR_003', 'error' => 'Cannot create album dir']);
        exit;
    }

    if (!is_writable($albumDir)) {
        echo json_encode(['success' => false, 'errorCode' => 'DIR_004', 'error' => 'Album dir not writable']);
        exit;
    }

    $file = $_FILES['cover'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
        echo json_encode(['success' => false, 'errorCode' => 'FILE_003', 'error' => 'Invalid cover format']);
        exit;
    }

    $destPath = $albumDir . 'cover.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        chmod($destPath, 0644);
        echo json_encode(['success' => true, 'message' => 'Cover uploaded']);
    } else {
        echo json_encode(['success' => false, 'errorCode' => 'MOVE_002', 'error' => 'Failed to save cover']);
    }
    exit;
}

// Video upload (MP4 + MKV support)
if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['video'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $allowedExt = ['mp4', 'mkv', 'avi', 'mov', 'mpeg'];
    $allowedMime = ['video/mp4', 'video/x-matroska', 'video/avi', 'video/quicktime', 'video/mpeg'];

    if (!in_array($ext, $allowedExt) || !in_array($file['type'], $allowedMime)) {
        echo json_encode(['success' => false, 'errorCode' => 'FILE_004', 'error' => 'Invalid video format. Allowed: mp4, mkv, avi, mov, mpeg']);
        exit;
    }

    $destPath = $videosDir . basename($file['name']);

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        chmod($destPath, 0644);
        echo json_encode(['success' => true, 'message' => 'Video uploaded', 'filename' => basename($destPath)]);
    } else {
        echo json_encode(['success' => false, 'errorCode' => 'MOVE_003', 'error' => 'Failed to save video']);
    }
    exit;
}

// Video thumbnail-only upload (for existing video)
if (isset($_FILES['video_thumbnail']) && $_FILES['video_thumbnail']['error'] === UPLOAD_ERR_OK && !empty($_POST['video'])) {
    $videoName = basename($_POST['video']);
    $videoPath = $videosDir . $videoName;

    if (!file_exists($videoPath) || !preg_match('/\.(mp4|mkv|avi|mov|mpeg)$/i', $videoName)) {
        echo json_encode(['success' => false, 'errorCode' => 'FILE_005', 'error' => 'Invalid or non-existent video']);
        exit;
    }

    $file = $_FILES['video_thumbnail'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
        echo json_encode(['success' => false, 'errorCode' => 'FILE_006', 'error' => 'Invalid thumbnail format']);
        exit;
    }

    $baseName = pathinfo($videoName, PATHINFO_FILENAME);
    $destPath = $videosDir . $baseName . '.' . $ext;

    // Delete old thumbnails
    foreach (['jpg','jpeg','png','webp'] as $e) {
        $old = $videosDir . $baseName . '.' . $e;
        if (file_exists($old)) @unlink($old);
    }

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        chmod($destPath, 0644);
        echo json_encode(['success' => true, 'message' => 'Thumbnail uploaded']);
    } else {
        echo json_encode(['success' => false, 'errorCode' => 'MOVE_004', 'error' => 'Failed to save thumbnail']);
    }
    exit;
}

// Fallback
echo json_encode(['success' => false, 'errorCode' => 'REQ_002', 'error' => 'No valid file or action']);
?>