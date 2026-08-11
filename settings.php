<?php
// settings.php - Admin page for managing settings with sidebar navigation

session_start();

// Check admin authentication
$settingsFile = __DIR__ . '/../settings.json';
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
    'music_size_limit_mb' => 1000,
    'background_type' => 'none',
    'background_file' => '',
    'admin_password_hash' => '',
    'site_password_hash' => '',
    'require_login' => false,
    'discord_client_id' => '' // Added for Discord Rich Presence
];

try {
    if (file_exists($settingsFile) && is_readable($settingsFile)) {
        $settingsContent = file_get_contents($settingsFile);
        if ($settingsContent !== false) {
            $loadedSettings = json_decode($settingsContent, true);
            if (is_array($loadedSettings)) {
                $settings = array_merge($settings, $loadedSettings);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    if (!empty($settings['admin_password_hash'])) {
        error_log("Unauthorized access attempt to settings.php in " . __FILE__ . " at line " . __LINE__);
        header('Location: login.php');
        exit;
    }
    error_log("No admin password set, allowing access to settings.php in " . __FILE__ . " at line " . __LINE__);
}

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');
error_log("Starting settings.php execution in " . __FILE__ . " at line " . __LINE__);

// Load settings from JSON
try {
    if (!file_exists($settingsFile)) {
        error_log("Settings file not found, creating default: $settingsFile in " . __FILE__ . " at line " . __LINE__);
        if (is_writable(dirname($settingsFile))) {
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
            error_log("Created default settings file: $settingsFile in " . __FILE__ . " at line " . __LINE__);
        } else {
            error_log("Cannot create settings file, directory not writable: $settingsFile in " . __FILE__ . " at line " . __LINE__);
            $error = "Cannot create settings file. Check directory permissions.";
        }
    } elseif (!is_readable($settingsFile)) {
        error_log("Settings file not readable: $settingsFile in " . __FILE__ . " at line " . __LINE__);
        $error = "Cannot read settings file. Check server logs.";
    } else {
        $settingsContent = file_get_contents($settingsFile);
        if ($settingsContent === false) {
            error_log("Failed to read settings file: $settingsFile in " . __FILE__ . " at line " . __LINE__);
            $error = "Failed to read settings file. Check server logs.";
        } else {
            $settings = json_decode($settingsContent, true);
            if (!is_array($settings)) {
                error_log("Invalid JSON in settings file: $settingsFile in " . __FILE__ . " at line " . __LINE__);
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
                    'music_size_limit_mb' => 1000,
                    'background_type' => 'none',
                    'background_file' => '',
                    'admin_password_hash' => '',
                    'site_password_hash' => '',
                    'require_login' => false,
                    'discord_client_id' => ''
                ];
            } else {
                $settings['videos_enabled'] = $settings['videos_enabled'] ?? true;
                $settings['settings_enabled'] = $settings['settings_enabled'] ?? true;
                $settings['video_size_limit_mb'] = $settings['video_size_limit_mb'] ?? 1000;
                $settings['thumbnail_size_limit_mb'] = $settings['thumbnail_size_limit_mb'] ?? 1000;
                $settings['music_size_limit_mb'] = $settings['music_size_limit_mb'] ?? 1000;
                $settings['background_type'] = $settings['background_type'] ?? 'none';
                $settings['background_file'] = $settings['background_file'] ?? '';
                $settings['admin_password_hash'] = $settings['admin_password_hash'] ?? '';
                $settings['site_password_hash'] = $settings['site_password_hash'] ?? '';
                $settings['require_login'] = $settings['require_login'] ?? false;
                $settings['discord_client_id'] = $settings['discord_client_id'] ?? ''; // Ensure discord_client_id is set
                error_log("Settings loaded successfully: " . json_encode($settings) . " in " . __FILE__ . " at line " . __LINE__);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
    $error = "Failed to load settings: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'error' => ''];
    $updatedSettings = $settings;

    // Update toggles
    $updatedSettings['active_view_count_enabled'] = isset($_POST['active_view_count_enabled']);
    $updatedSettings['video_view_count_enabled'] = isset($_POST['video_view_count_enabled']);
    $updatedSettings['youtube_enabled'] = isset($_POST['youtube_enabled']);
    $updatedSettings['videos_enabled'] = isset($_POST['videos_enabled']);
    $updatedSettings['settings_enabled'] = isset($_POST['settings_enabled']);
    $updatedSettings['require_login'] = isset($_POST['require_login']);

    // Handle age restriction toggle
    if (isset($_POST['age_restriction'])) {
        $currentTime = time();
        $lastToggled = $settings['age_restriction_last_toggled'] ?? 0;
        if ($currentTime - $lastToggled >= 24 * 3600) {
            $updatedSettings['age_restriction'] = true;
            $updatedSettings['age_restriction_last_toggled'] = $currentTime;
        } else {
            $response['error'] = 'Age restriction can only be toggled once every 24 hours';
        }
    } else {
        $updatedSettings['age_restriction'] = false;
    }

    // Update YouTube settings
    $updatedSettings['youtube_channel_id'] = $_POST['youtube_channel_id'] ?? '';
    $apiKeysInput = $_POST['youtube_api_keys'] ?? '';
    $apiKeys = array_filter(array_map('trim', explode(',', $apiKeysInput)));
    $updatedSettings['youtube_api_keys'] = $apiKeys;

    // Update file size limits
    $updatedSettings['video_size_limit_mb'] = filter_var($_POST['video_size_limit_mb'] ?? 1000, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1000;
    $updatedSettings['thumbnail_size_limit_mb'] = filter_var($_POST['thumbnail_size_limit_mb'] ?? 1000, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1000;
    $updatedSettings['music_size_limit_mb'] = filter_var($_POST['music_size_limit_mb'] ?? 1000, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1000;

    // Update passwords
    if (!empty($_POST['admin_password'])) {
        $updatedSettings['admin_password_hash'] = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
    }
    if (!empty($_POST['site_password'])) {
        $updatedSettings['site_password_hash'] = password_hash($_POST['site_password'], PASSWORD_DEFAULT);
    }

    // Handle background upload
    if (isset($_FILES['background']) && !empty($_FILES['background']['name'])) {
        $background = $_FILES['background'];
        $ext = strtolower(pathinfo($background['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webm', 'gif', 'html'];
        if (!in_array($ext, $allowedExt)) {
            $response['error'] = 'Invalid background format. Allowed: JPG, PNG, WebM, GIF, HTML';
        } elseif ($background['size'] > 1000 * 1048576) {
            $response['error'] = 'Background size exceeds 1000MB limit';
        } else {
            $backgroundDir = __DIR__ . '/../backgrounds/';
            if (!is_dir($backgroundDir)) {
                mkdir($backgroundDir, 0755, true);
                error_log("Created backgrounds directory in " . __FILE__ . " at line " . __LINE__);
            }
            $backgroundName = 'background.' . $ext;
            $backgroundPath = $backgroundDir . $backgroundName;
            if (move_uploaded_file($background['tmp_name'], $backgroundPath)) {
                error_log("Uploaded background: $backgroundPath in " . __FILE__ . " at line " . __LINE__);
                $updatedSettings['background_type'] = $ext === 'html' ? 'html' : ($ext === 'webm' || $ext === 'gif' ? 'video' : 'image');
                $updatedSettings['background_file'] = 'backgrounds/' . $backgroundName;
            } else {
                error_log("Failed to move background file: $backgroundPath in " . __FILE__ . " at line " . __LINE__);
                $response['error'] = 'Failed to upload background';
            }
        }
    }

    // Update Discord Client ID
    $updatedSettings['discord_client_id'] = $_POST['discord_client_id'] ?? '';

    // Save settings
    if (!$response['error']) {
        try {
            if (is_writable($settingsFile)) {
                file_put_contents($settingsFile, json_encode($updatedSettings, JSON_PRETTY_PRINT));
                error_log("Settings saved successfully: " . json_encode($updatedSettings) . " in " . __FILE__ . " at line " . __LINE__);
                $response['success'] = true;
                $response['message'] = 'Settings saved successfully';
                $settings = $updatedSettings;
            } else {
                error_log("Settings file not writable: $settingsFile in " . __FILE__ . " at line " . __LINE__);
                $response['error'] = 'Cannot save settings. Check file permissions.';
            }
        } catch (Exception $e) {
            error_log("Error saving settings: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
            $response['error'] = 'Failed to save settings: ' . $e->getMessage();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/settings.css">
</head>
<body>
    <div class="hamburger"><i class="fas fa-bars"></i></div>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Media Admin</h2>
            <a href="../watch.php" class="back-btn">Media Player</a>
        </div>
        <ul>
            <?php if ($settings['videos_enabled']): ?>
                <li><a href="../videos.php" class="nav-link"><i class="fas fa-video"></i> Videos</a></li>
            <?php endif; ?>
            <li><a href="../music.php" class="nav-link"><i class="fas fa-music"></i> Music</a></li>
            <?php if ($settings['settings_enabled']): ?>
                <li><a href="settings.php" class="nav-link active"><i class="fas fa-cog"></i> Settings</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="section">
            <h2>Settings</h2>
            <form method="POST" enctype="multipart/form-data" id="settings-form">
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="active_view_count_enabled" id="active_view_count_enabled" <?php echo $settings['active_view_count_enabled'] ? 'checked' : ''; ?>>
                    <label for="active_view_count_enabled">Enable Active View Count</label>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="video_view_count_enabled" id="video_view_count_enabled" <?php echo $settings['video_view_count_enabled'] ? 'checked' : ''; ?>>
                    <label for="video_view_count_enabled">Enable Per-Video View Count</label>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="youtube_enabled" id="youtube_enabled" <?php echo $settings['youtube_enabled'] ? 'checked' : ''; ?>>
                    <label for="youtube_enabled">Enable YouTube Mode</label>
                </div>
                <div class="form-group">
                    <label for="youtube_channel_id">YouTube Channel ID</label>
                    <input type="text" name="youtube_channel_id" id="youtube_channel_id" value="<?php echo htmlspecialchars($settings['youtube_channel_id']); ?>">
                </div>
                <div class="form-group">
                    <label for="youtube_api_keys">YouTube API Keys (comma-separated)</label>
                    <textarea name="youtube_api_keys" id="youtube_api_keys"><?php echo htmlspecialchars(implode(',', $settings['youtube_api_keys'])); ?></textarea>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="age_restriction" id="age_restriction" <?php echo $settings['age_restriction'] ? 'checked' : ''; ?>>
                    <label for="age_restriction">Enable Age Restriction (24-hour toggle cooldown)</label>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="videos_enabled" id="videos_enabled" <?php echo $settings['videos_enabled'] ? 'checked' : ''; ?>>
                    <label for="videos_enabled">Enable Videos Section</label>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="settings_enabled" id="settings_enabled" <?php echo $settings['settings_enabled'] ? 'checked' : ''; ?>>
                    <label for="settings_enabled">Enable Settings Section</label>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="require_login" id="require_login" <?php echo $settings['require_login'] ? 'checked' : ''; ?>>
                    <label for="require_login">Require Login for Main Site</label>
                </div>
                <div class="form-group">
                    <label for="admin_password">Admin Password (leave blank to keep current)</label>
                    <input type="password" name="admin_password" id="admin_password" placeholder="Set or change admin password">
                </div>
                <div class="form-group">
                    <label for="site_password">Main Site Password (leave blank to keep current)</label>
                    <input type="password" name="site_password" id="site_password" placeholder="Set or change site password">
                </div>
                <div class="form-group">
                    <label for="video_size_limit_mb">Video Size Limit (MB)</label>
                    <input type="number" name="video_size_limit_mb" id="video_size_limit_mb" value="<?php echo htmlspecialchars($settings['video_size_limit_mb']); ?>" min="1">
                </div>
                <div class="form-group">
                    <label for="thumbnail_size_limit_mb">Thumbnail Size Limit (MB)</label>
                    <input type="number" name="thumbnail_size_limit_mb" id="thumbnail_size_limit_mb" value="<?php echo htmlspecialchars($settings['thumbnail_size_limit_mb']); ?>" min="1">
                </div>
                <div class="form-group">
                    <label for="music_size_limit_mb">Music Size Limit (MB)</label>
                    <input type="number" name="music_size_limit_mb" id="music_size_limit_mb" value="<?php echo htmlspecialchars($settings['music_size_limit_mb']); ?>" min="1">
                </div>
                <div class="form-group">
                    <label for="background">Background (JPG, PNG, WebM, GIF, HTML, 1000MB max)</label>
                    <input type="file" name="background" id="background" accept=".jpg,.jpeg,.png,.webm,.gif,.html">
                </div>
                <div class="form-group">
                    <label for="discord_client_id">Discord Client ID (for Rich Presence)</label>
                    <input type="text" name="discord_client_id" id="discord_client_id" value="<?php echo htmlspecialchars($settings['discord_client_id']); ?>" placeholder="Enter Discord Client ID">
                </div>
                <button type="submit" class="submit-btn" id="settings-submit">Save Settings</button>
            </form>
        </div>
    </div>
        <script src="../assets/js/settings.js"></script>
</body>
</html>
<?php
error_log("settings.php execution completed in " . __FILE__ . " at line " . __LINE__);
?>