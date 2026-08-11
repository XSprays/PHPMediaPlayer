# Media Player V6 – Permissions & Setup Guide

This document covers **every permission**, configuration, and step required to properly install and run the web-based PHP Media Player (V6).

---

## 1. Overview of What Needs Permissions

The application is a pure PHP + HTML5 media library with:

- Local video streaming (MP4, MKV, AVI, MOV, MPEG)
- Music player (MP3)
- Image gallery
- File upload / rename / delete
- Thumbnail & cover art management
- Background image upload
- Settings stored in JSON files
- Optional YouTube integration (requires cURL)
- Optional site-wide password protection
- Admin panel with separate password
- Active visitor / view counters
- Progressive Web App (PWA) support (service worker + manifest)

Because of this, the **web server user** (usually `www-data`, `apache`, `nginx`, or `nobody`) must be able to:

1. **Read** all PHP files and media
2. **Write** to several directories and JSON files
3. **Create** directories and files on the fly
4. **Delete / rename** media files
5. Upload large files (PHP + web server limits)

---

## 2. Required Directory Structure & Permissions

After extracting the ZIP, you should have this structure:

```
Media Player - V6 Update/
├── index.php                 (login page)
├── watch.php                 (main video player)
├── music.php / music_player.php
├── videos.php
├── gallery.php
├── media_manager.php         (upload / delete / rename)
├── upload.php                (AJAX upload handler)
├── settings.php / settings.json
├── admin.php / admin_login.php
├── youtube_fetch.php
├── delete_thumbnail.php
├── next_track.php
├── service-worker.js
├── manifest.json
├── active_views.json
├── video_views.json
├── music/                    ← must be writable
├── videos/                   ← must be writable
├── gallery/                  ← must be writable
└── backgrounds/              ← must be writable
```

### Recommended Linux Permissions

Run these commands from the project root (adjust the user/group to match your web server):

```bash
# Set ownership to the web server user (common examples)
sudo chown -R www-data:www-data .
# or
sudo chown -R apache:apache .
# or for some shared hosts / nginx
sudo chown -R nginx:nginx .

# Directories that must be writable
sudo chmod 755 .
sudo chmod 775 music/ videos/ gallery/ backgrounds/
sudo chmod 664 settings.json active_views.json video_views.json

# PHP files should be readable but not writable by the world
sudo find . -type f -name "*.php" -exec chmod 644 {} \;
sudo find . -type f -name "*.js"  -exec chmod 644 {} \;
sudo find . -type f -name "*.json" -exec chmod 664 {} \;

# Make sure the web server can create the error log
sudo touch php_errors.log
sudo chmod 664 php_errors.log
sudo chown www-data:www-data php_errors.log
```

### What each writable location is used for

| Path                    | Why it needs write access                                      |
|-------------------------|----------------------------------------------------------------|
| `music/`                | Uploading MP3 files + optional album cover folders             |
| `videos/`               | Uploading videos + thumbnail images (same base name)           |
| `gallery/`              | Uploading images for the gallery                               |
| `backgrounds/`          | Custom background image for the UI                             |
| `settings.json`         | Saving all configuration changes                               |
| `active_views.json`     | Real-time visitor / bot counter                                |
| `video_views.json`      | Per-video view counts                                          |
| `php_errors.log`        | Error logging (created automatically)                          |
| Root directory          | Creating the above JSON/log files if they don't exist yet      |

**Critical**: If any of the media directories are not writable, uploads will fail with errors such as `DIR_002 – Directory not writable`.

---

## 3. PHP Configuration Requirements

Edit your `php.ini` (or use `.user.ini` / `.htaccess` on some hosts).

### Minimum recommended settings

```ini
file_uploads = On
upload_max_filesize = 2048M          ; or higher if you upload large videos
post_max_size = 2048M                ; must be >= upload_max_filesize
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
max_file_uploads = 20

; Sessions (required for login)
session.save_path = "/tmp"           ; or a writable path
session.gc_maxlifetime = 86400

; Recommended for security & debugging
display_errors = Off
log_errors = On
error_log = /path/to/your/project/php_errors.log
```

### Required PHP Extensions

| Extension     | Purpose                                      | Required? |
|---------------|----------------------------------------------|-----------|
| `json`        | settings.json, active_views.json, etc.       | **Yes**   |
| `session`     | Login / authentication                       | **Yes**   |
| `fileinfo`    | Better MIME type detection (recommended)     | Strongly recommended |
| `curl`        | YouTube integration (`youtube_fetch.php`)    | Only if you enable YouTube |
| `mbstring`    | Safer string handling                        | Recommended |

Check with:

```bash
php -m | grep -E 'json|session|curl|fileinfo|mbstring'
```

---

## 4. Web Server Configuration

### Apache (with mod_php or PHP-FPM)

Make sure these modules are enabled:

```bash
sudo a2enmod rewrite headers expires
```

Example `.htaccess` (place in the project root) – useful for larger uploads and security:

```apache
# Increase limits (if allowed by the host)
php_value upload_max_filesize 2048M
php_value post_max_size 2048M
php_value max_execution_time 300
php_value memory_limit 512M

# Prevent directory listing
Options -Indexes

# Block direct access to sensitive files
<FilesMatch "\.(json|log)$">
    Require all denied
</FilesMatch>

# Allow the service worker
<Files "service-worker.js">
    Header set Service-Worker-Allowed "/"
</Files>
```

### Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/media-player;
    index index.php;

    client_max_body_size 2048M;          # Critical for large video uploads

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;   # adjust version
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    # Protect sensitive files
    location ~* \.(json|log)$ {
        deny all;
    }

    location = /service-worker.js {
        add_header Service-Worker-Allowed /;
        add_header Cache-Control "no-cache";
    }
}
```

---

## 5. Step-by-Step Setup

1. **Upload / Extract**
   ```bash
   unzip "Media Player - V6 Update.zip"
   cd "Media Player - V6 Update"
   ```

2. **Set ownership & permissions** (see Section 2)

3. **Create the media folders** (they are empty in the ZIP)
   ```bash
   mkdir -p music videos gallery backgrounds
   chmod 775 music videos gallery backgrounds
   ```

4. **Configure PHP** (upload limits, etc.)

5. **First visit**
   - Open `https://your-domain.com/` → you should see the login page (`index.php`).
   - Default behaviour when no passwords are set yet is relatively open.  
     **Immediately go to Settings and set both passwords.**

6. **Set passwords (important)**
   - Go to `settings.php` (or via Admin → Settings).
   - Set **Admin Password** and **Main Site Password**.
   - Enable **Require Login for Main Site** if you want the whole player protected.
   - Save.

7. **Test uploads**
   - Go to Media Manager.
   - Upload a small MP3 and a small MP4.
   - Confirm they appear and play.

8. **Optional: YouTube**
   - Enable YouTube Mode in Settings.
   - Enter a Channel ID and one or more YouTube Data API v3 keys.
   - Requires the PHP `curl` extension.

---

## 6. Authentication & Session Permissions

| Feature                    | File(s)                  | How it works                                      |
|----------------------------|--------------------------|---------------------------------------------------|
| Site-wide login            | `index.php`              | Uses `site_password_hash` in `settings.json`      |
| Admin login                | `admin_login.php`        | Uses `admin_password_hash`                        |
| Remember-me cookie         | `index.php`              | Sets a simple `auth_token` cookie (30 days)       |
| Session                    | All protected pages      | `$_SESSION['authenticated']` / `admin_authenticated` |

**Important security notes:**

- Passwords are stored as `password_hash()` (bcrypt).
- The remember-me token is currently a hardcoded value (`some_secure_token`). Consider changing this in production.
- `settings.json` contains the password hashes — protect it with web-server rules (see Apache/Nginx examples).

---

## 7. Browser / Client-Side Permissions

The player itself does **not** request special browser permissions beyond normal media playback. However:

| Feature                     | Browser Permission / Note                              |
|-----------------------------|--------------------------------------------------------|
| HTML5 `<video>` / `<audio>` | None required                                          |
| Fullscreen                  | User gesture required                                  |
| Picture-in-Picture          | Supported in modern browsers                           |
| Media Session API           | Used for lock-screen controls (Chrome/Edge/Android)    |
| PWA / Install               | `manifest.json` + `service-worker.js`                  |
| Background audio            | Works while the tab is open; true background play is limited on mobile |

No microphone, camera, geolocation, or notification permissions are requested by the current code.

---

## 8. Common Permission Errors & Fixes

| Error message / Symptom                          | Cause                                      | Fix |
|--------------------------------------------------|--------------------------------------------|-----|
| `DIR_002 – Directory not writable`               | `music/` or `videos/` not writable by PHP  | `chmod 775` + correct ownership |
| `Cannot save settings. Check file permissions.`  | `settings.json` or parent dir not writable | `chmod 664 settings.json` + ownership |
| Upload succeeds but file is 0 bytes / missing    | `move_uploaded_file` failed                | Check disk space + `open_basedir` |
| Large videos fail silently                       | `upload_max_filesize` / `post_max_size` too low | Raise both in php.ini + web server |
| YouTube fails with “curl extension is not enabled” | Missing PHP curl                           | `sudo apt install php-curl` (or equivalent) |
| “Unlink failed – check permissions”              | Cannot delete media files                  | Ownership / `chmod` on the media folders |
| Blank page / 500 error                           | Check `php_errors.log`                     | Almost always a permissions or missing extension issue |

---

## 9. Security Recommendations

1. **Always set both passwords** after first install.
2. Protect `*.json` and `php_errors.log` from direct web access (examples above).
3. Keep the media directories outside the web root if possible and serve them via a PHP proxy or carefully configured alias (advanced).
4. Use HTTPS in production (required for reliable PWA / service worker behaviour).
5. Regularly update PHP and the web server.
6. Consider adding rate limiting on `upload.php` and login endpoints.
7. The current remember-me token is weak — replace it with a properly generated random token stored server-side if you need stronger security.

---

## 10. Quick Permission Checklist

Before going live, verify:

- [ ] Web server user owns the project (or has write access)
- [ ] `music/`, `videos/`, `gallery/`, `backgrounds/` are `775` (or `755` + correct owner)
- [ ] `settings.json`, `active_views.json`, `video_views.json` are writable
- [ ] `php.ini` has high enough `upload_max_filesize` and `post_max_size`
- [ ] PHP extensions: `json`, `session` (and `curl` if using YouTube)
- [ ] Admin + Site passwords have been set
- [ ] Sensitive files are blocked from direct download
- [ ] A test MP3 and MP4 upload + playback works
- [ ] Error log is being written (`php_errors.log`)

---

## 11. Support / Debugging

All major scripts log to:

```
php_errors.log
```

(in the project root). Always check this file first when something fails.

---

**This README is tailored specifically to the V6 codebase you provided.**  
If you later add new features (e.g. user accounts, remote storage, FFmpeg transcoding, etc.), the permission requirements will need to be updated.
