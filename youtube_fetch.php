<?php
// youtube_fetch.php - Fetch YouTube videos with simplified channel ID validation and detailed error logging

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!function_exists('curl_init')) {
    echo json_encode(['success' => false, 'error' => 'PHP curl extension is not enabled']);
    error_log("PHP curl extension not enabled in " . __FILE__);
    exit;
}

$settingsFile = __DIR__ . '/settings.json';
if (!file_exists($settingsFile) || !is_readable($settingsFile)) {
    echo json_encode(['success' => false, 'error' => 'Settings file not found or unreadable']);
    error_log("Settings file not found or unreadable: $settingsFile in " . __FILE__);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true);
if (!is_array($settings)) {
    echo json_encode(['success' => false, 'error' => 'Invalid settings file format']);
    error_log("Invalid settings.json format in " . __FILE__);
    exit;
}

if (!$settings['youtube_enabled']) {
    echo json_encode(['success' => false, 'error' => 'YouTube integration is disabled']);
    error_log("YouTube integration disabled in " . __FILE__);
    exit;
}

$channelId = $settings['youtube_channel_id'] ?? '';
$apiKeys = $settings['youtube_api_keys'] ?? [];
error_log("Processing channel ID: '$channelId' in " . __FILE__);

if (empty($channelId)) {
    echo json_encode(['success' => false, 'error' => 'YouTube Channel ID is required']);
    error_log("Channel ID empty in " . __FILE__);
    exit;
}
error_log("Channel ID accepted (bypassing regex): '$channelId' in " . __FILE__);

if (empty($apiKeys)) {
    echo json_encode(['success' => false, 'error' => 'No YouTube API keys provided']);
    error_log("No API keys provided in " . __FILE__);
    exit;
}

$validKeys = array_filter($apiKeys, fn($key) => preg_match('/^[A-Za-z0-9_-]{39}$/', trim($key)));
if (empty($validKeys)) {
    echo json_encode(['success' => false, 'error' => 'No valid API keys provided']);
    error_log("No valid API keys provided in " . __FILE__);
    exit;
}

$maxResults = 50;
$pageToken = $_GET['pageToken'] ?? '';
$videos = [];
$workingKey = null;

function makeCurlRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GrokYouTubeClient/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ['response' => $response, 'http_code' => $httpCode, 'error' => $error];
}

foreach ($validKeys as $index => $apiKey) {
    $testUrl = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=$channelId&maxResults=1&type=video&key=$apiKey";
    $result = makeCurlRequest($testUrl);
    error_log("API key $index test: HTTP {$result['http_code']}, Response: " . substr($result['response'], 0, 100) . "... in " . __FILE__);

    if ($result['response'] === false || !empty($result['error'])) {
        error_log("API key $index failed: {$result['error']} (HTTP: {$result['http_code']}) in " . __FILE__);
        continue;
    }

    $data = json_decode($result['response'], true);
    if (!is_array($data)) {
        error_log("Invalid API response for key $index: " . substr($result['response'], 0, 100) . "... in " . __FILE__);
        continue;
    }

    if (isset($data['error'])) {
        $errorMsg = $data['error']['message'] ?? 'Unknown error';
        $errorCode = $data['error']['code'] ?? 'unknown';
        error_log("API key $index error: $errorMsg (Code: $errorCode, HTTP: {$result['http_code']}) in " . __FILE__);
        continue;
    }

    $workingKey = $apiKey;
    break;
}

if (!$workingKey) {
    echo json_encode(['success' => false, 'error' => 'No working API keys found']);
    error_log("No working API keys found in " . __FILE__);
    exit;
}

while (count($videos) < $maxResults) {
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=$channelId&order=date&maxResults=" . min(50, $maxResults - count($videos)) . "&type=video&key=$workingKey";
    if (!empty($pageToken)) {
        $url .= "&pageToken=" . urlencode($pageToken);
    }

    $result = makeCurlRequest($url);
    if ($result['response'] === false || !empty($result['error'])) {
        echo json_encode(['success' => false, 'error' => "Failed to fetch videos: {$result['error']}"]);
        error_log("Video fetch failed: {$result['error']} (HTTP: {$result['http_code']}) in " . __FILE__);
        exit;
    }

    $data = json_decode($result['response'], true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Invalid response from YouTube API']);
        error_log("Invalid video fetch response: " . substr($result['response'], 0, 100) . "... (HTTP: {$result['http_code']}) in " . __FILE__);
        exit;
    }

    if (isset($data['error'])) {
        $errorMsg = $data['error']['message'] ?? 'Unknown error';
        $errorCode = $data['error']['code'] ?? 'unknown';
        echo json_encode(['success' => false, 'error' => "Failed to fetch videos: $errorMsg (Code: $errorCode)"]);
        error_log("Video fetch error: $errorMsg (Code: $errorCode, HTTP: {$result['http_code']}) in " . __FILE__);
        exit;
    }

    if (isset($data['items'])) {
        foreach ($data['items'] as $item) {
            if (!isset($item['id']['videoId'])) {
                error_log("Missing videoId in item: " . json_encode($item) . " in " . __FILE__);
                continue;
            }
            $videos[] = [
                'id' => $item['id']['videoId'],
                'title' => $item['snippet']['title'] ?? 'Untitled',
                'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? '',
                'description' => $item['snippet']['description'] ?? '',
                'publishedAt' => $item['snippet']['publishedAt'] ?? '',
                'viewCount' => 'N/A'
            ];
            if (count($videos) >= $maxResults) break;
        }
    }

    $pageToken = $data['nextPageToken'] ?? '';
    if (empty($pageToken)) break;
}

if (empty($videos)) {
    echo json_encode(['success' => false, 'error' => 'No videos found for the specified channel']);
    error_log("No videos found for channel: $channelId in " . __FILE__);
} else {
    echo json_encode(['success' => true, 'videos' => $videos, 'pageToken' => $pageToken]);
}
?>