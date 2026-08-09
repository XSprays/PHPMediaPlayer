<?php
// admin.php - Admin homepage with sidebar navigation and description of music player features

session_start();

// Check admin authentication
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
    'admin_password_hash' => '' // Default empty, should be set via settings.php
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

// Check admin authentication
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    if (!empty($settings['admin_password_hash'])) {
        error_log("Unauthorized access attempt to admin.php in " . __FILE__ . " at line " . __LINE__);
        header('Location: admin_login.php');
        exit;
    }
    // If no admin password is set, allow access but log
    error_log("No admin password set, allowing access to admin.php in " . __FILE__ . " at line " . __LINE__);
}

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_log("Starting admin.php execution in " . __FILE__ . " at line " . __LINE__);

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
                    'admin_password_hash' => ''
                ];
            } else {
                $settings['videos_enabled'] = $settings['videos_enabled'] ?? true;
                $settings['settings_enabled'] = $settings['settings_enabled'] ?? true;
                $settings['admin_password_hash'] = $settings['admin_password_hash'] ?? '';
                error_log("Settings loaded successfully: " . json_encode($settings) . " in " . __FILE__ . " at line " . __LINE__);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage() . " in " . __FILE__ . " at line " . __LINE__);
    $error = "Failed to load settings: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #222233;
            color: #eee;
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }
        .sidebar {
            width: 250px;
            background: #1a1a2e;
            border-right: 1px solid #333366;
            padding: 1rem;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .sidebar-header h2 {
            font-size: 1.25rem;
            color: #77aaff;
        }
        .hamburger {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #77aaff;
        }
        .sidebar ul {
            list-style: none;
        }
        .sidebar li {
            margin-bottom: 0.5rem;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            color: #eee;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .sidebar a:hover {
            background: #333366;
        }
        .sidebar a.active {
            background: #6677ff;
            color: white;
        }
        .sidebar a.disabled {
            color: #666;
            cursor: not-allowed;
            pointer-events: none;
        }
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            height: 100vh;
        }
        .section {
            background: #1a1a2e;
            padding: 1.5rem;
            border-radius: 6px;
            border: 1px solid #333366;
            margin-bottom: 1.5rem;
        }
        .section h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: #77aaff;
        }
        .section p {
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }
        .section ul {
            list-style: disc;
            padding-left: 1.5rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .section ul li {
            margin-bottom: 0.5rem;
        }
        .footer {
            text-align: center;
            font-size: 0.8rem;
            color: #77aaff;
            margin-top: 2rem;
            padding: 1rem 0;
        }
        .error {
            padding: 0.5rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            background: #ff5555;
        }
        .back-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #444488;
            color: white;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-decoration: none;
        }
        .back-btn:hover {
            background: #5555aa;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 200px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .hamburger {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1100;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .section h2 {
                font-size: 1rem;
            }
            .section p, .section ul li {
                font-size: 0.8rem;
            }
            .back-btn {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            .footer {
                font-size: 0.7rem;
            }
        }
    </style>
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
            <li><a href="music.php" class="nav-link"><i class="fas fa-music"></i> Music</a></li>
            <?php if ($settings['settings_enabled']): ?>
                <li><a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="section">
            <h2>Welcome to the Media Admin Dashboard</h2>
            <p>
                This media player is designed to deliver a seamless experience for managing and playing your video and music content. Whether you're uploading local files or integrating with YouTube, this platform offers powerful features to customize your media experience.
            </p>
            <h3>Features</h3>
            <ul>
                <li><strong>Local Video and Music Playback</strong>: Upload and play videos (MP4, AVI, MOV, MPEG, &lt;5MB for thumbnails) and music (MP3, &lt;10MB) directly from your server.</li>
                <li><strong>YouTube Integration</strong>: Stream videos from a specified YouTube channel using API keys for a broader content library.</li>
                <li><strong>View Count Tracking</strong>: Monitor total views across all media or per-video view counts for detailed analytics.</li>
                <li><strong>Age Restriction</strong>: Restrict access to content with a toggleable age gate (24-hour cooldown for enabling).</li>
                <li><strong>Admin Management</strong>: Manage videos, music, and settings through an intuitive interface with secure authentication.</li>
                <li><strong>Responsive Design</strong>: Access the admin dashboard and player on desktop or mobile devices with a consistent experience.</li>
            </ul>
            <h3>Customizable Settings</h3>
            <p>
                You can tailor the media player’s functionality by toggling the following settings in the <a href="settings.php">Settings</a> section:
            </p>
            <ul>
                <li><strong>Active View Count</strong>: Enable or disable tracking of total views across all media.</li>
                <li><strong>Per-Video View Count</strong>: Toggle tracking of view counts for individual videos.</li>
                <li><strong>YouTube Mode</strong>: Enable or disable YouTube video streaming, requiring a valid channel ID and API keys.</li>
                <li><strong>Age Restriction</strong>: Turn on or off age-restricted access to content (can only be enabled once every 24 hours).</li>
                <li><strong>Videos Section</strong>: Enable or disable the Videos section in the admin dashboard and player.</li>
                <li><strong>Settings Section</strong>: Show or hide the Settings section in the admin dashboard.</li>
                <li><strong>Main Site Login</strong>: Enable or disable password protection for the main site (index.php).</li>
            </ul>
        </div>
        <div class="footer">
            Made by XSprays but was coded with Grok.
        </div>
    </div>
    <script>
        // Hamburger menu toggle
        const sidebar = document.querySelector('.sidebar');
        const hamburger = document.querySelector('.hamburger');
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    </script>
</body>
</html>
<?php
error_log("admin.php execution completed in " . __FILE__ . " at line " . __LINE__);
?>