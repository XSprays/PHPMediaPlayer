<?php
// delete_thumbnail.php - Handle deletion of video thumbnails

session_start();

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['video'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$videosDir = __DIR__ . '/videos/';
$videoName = basename($_POST['video']);

// Validate video exists and has allowed extension
if (!file_exists($videosDir . $videoName) || !preg_match('/\.(mp4|avi|mov|mpeg)$/i', $videoName)) {
    echo json_encode(['success' => false, 'error' => 'Invalid or non-existent video']);
    exit;
}

// Find and delete thumbnail
$baseName = pathinfo($videoName, PATHINFO_FILENAME);
$thumbnailDeleted = false;
foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
    $thumbPath = $videosDir . $baseName . '.' . $ext;
    if (file_exists($thumbPath)) {
        if (unlink($thumbPath)) {
            $thumbnailDeleted = true;
        } else {
            error_log("Failed to delete thumbnail: $thumbPath");
            echo json_encode(['success' => false, 'error' => 'Failed to delete thumbnail']);
            exit;
        }
    }
}

if ($thumbnailDeleted) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'No thumbnail found for this video']);
}
?>