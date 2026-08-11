<?php
// index.php - Login page (uses site_password_hash only)

session_start();

// Files
$settingsFile = __DIR__ . '/settings.json';
$viewsFile    = __DIR__ . '/active_views.json';

// Load settings safely
$defaults = array(
  'age_restriction'=>false,'last_age_on_time'=>0,'remember_me_enabled'=>true,
  'login_lockout_enabled'=>true,'active_view_count_enabled'=>true,'video_view_count_enabled'=>true,
  'require_login'=>false
);
$settings = $defaults;
if (file_exists($settingsFile) && is_readable($settingsFile)) {
  $raw = @file_get_contents($settingsFile);
  $j = json_decode($raw, true);
  if (is_array($j)) $settings = array_merge($defaults, $j);
}

// Early redirect if already authenticated
if (!empty($_SESSION['authenticated'])) {
  header('Location: watch.php'); exit;
}
if (!empty($settings['remember_me_enabled']) && isset($_COOKIE['auth_token']) && $_COOKIE['auth_token']==='some_secure_token') {
  $_SESSION['authenticated'] = true;
  header('Location: watch.php'); exit;
}

// Active views (bot/people/others)
$activeViews = array('robots'=>0,'people'=>0,'others'=>0,'visitors'=>array());
if (file_exists($viewsFile)) {
  $raw = @file_get_contents($viewsFile);
  $j = json_decode($raw, true);
  if (is_array($j)) $activeViews = array_merge($activeViews, $j);
}
function isBot($ua){
  $p=array('/bot/i','/crawl/i','/spider/i','/googlebot/i','/bingbot/i','/yandex/i','/baiduspider/i','/duckduckbot/i','/slurp/i','/teoma/i','/ia_archiver/i');
  foreach($p as $r){ if(preg_match($r,$ua)) return true; } return false;
}
$sessionId = session_id();
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
$now = time(); $timeout=300;
$vis = array(); $counts = array('robots'=>0,'people'=>0,'others'=>0);

foreach (isset($activeViews['visitors'])?$activeViews['visitors']:array() as $id=>$v) {
  if (isset($v['timestamp']) && $now-$v['timestamp']<$timeout) {
    $vis[$id]=$v;
    $key = ($v['type']==='robot')?'robots':(($v['type']==='person')?'people':'others');
    $counts[$key]++;
  }
}
$isAuthed = !empty($_SESSION['authenticated']) || (isset($_COOKIE['auth_token']) && $_COOKIE['auth_token']==='some_secure_token');
$type = isBot($userAgent)?'robot':($isAuthed?'person':'other');
$vis[$sessionId] = array('timestamp'=>$now,'type'=>$type);
$counts[ $type==='robot'?'robots':($type==='person'?'people':'others') ]++;

@file_put_contents($viewsFile, json_encode(array('robots'=>$counts['robots'],'people'=>$counts['people'],'others'=>$counts['others'],'visitors'=>$vis), JSON_PRETTY_PRINT));

// Lockout config
$maxAttempts = 3; $lockoutTime = 300;
if (!empty($settings['login_lockout_enabled'])) {
  if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts']=0;
  if (isset($_SESSION['lockout_time']) && time()<$_SESSION['lockout_time']) $error='Too many failed attempts. Try again later.';
}

// SITE LOGIN (uses site_password_hash only; falls back to old hardcoded if hash empty)
$siteHash = isset($settings['site_password_hash']) ? $settings['site_password_hash'] : '';
$hardcodedFallback = 'Xeroid96612345$'; // optional until you set site_password_hash

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $inputPassword = isset($_POST['password'])?(string)$_POST['password']:'';
  $remember = isset($_POST['remember']) && !empty($settings['remember_me_enabled']);

  $ok = false;
  if ($siteHash!=='') {
    if (function_exists('password_verify')) $ok = password_verify($inputPassword, $siteHash);
  } else {
    $ok = hash_equals($hardcodedFallback, $inputPassword);
  }

  if (!isset($error) && $ok) {
    $_SESSION['authenticated']=true;
    $_SESSION['login_attempts']=0; unset($_SESSION['lockout_time']);
    if ($remember) setcookie('auth_token','some_secure_token',time()+2592000,'/');
    header('Location: watch.php'); exit;
  } else {
    if (!empty($settings['login_lockout_enabled'])) {
      $_SESSION['login_attempts']++;
      if ($_SESSION['login_attempts']>=$maxAttempts) { $_SESSION['lockout_time']=time()+$lockoutTime; $error='Too many failed attempts. Locked for 5 minutes.'; }
      else $error='Incorrect password.';
    } else $error='Incorrect password.';
  }
}

// Simple page
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<link rel="manifest" href="manifest.json"> <!-- Add manifest link -->
<link rel="icon" type="image/png" href="icon.png"> <!-- PWA icon -->
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head><body>
<!-- [Rest of the HTML/PHP remains unchanged] -->
<div class="container">
  <h1>Login</h1>
  <?php if(isset($error)): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
  <form method="POST">
    <input type="password" name="password" placeholder="Password" required>
    <?php if(!empty($settings['remember_me_enabled'])): ?>
      <label><input type="checkbox" name="remember"> Remember me</label>
    <?php endif; ?>
    <button type="submit">Login</button>
  </form>
  <?php if(!empty($settings['active_view_count_enabled'])): ?>
  <div class="view-count">
    <p>Robots: <span><?php echo (int)$counts['robots']; ?></span></p>
    <p>People: <span><?php echo (int)$counts['people']; ?></span></p>
    <p>Others: <span><?php echo (int)$counts['others']; ?></span></p>
  </div>
  <?php endif; ?>
</div>
    <script src="assets/js/login.js"></script>
</body></html>
```