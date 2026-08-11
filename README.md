# Media Player V6

A self-hosted, web-based PHP media player with video streaming, music playback, image gallery, file management, optional YouTube integration, and password protection.

---

## Features

- **Video Player** – Stream local videos (MP4, MKV, AVI, MOV, MPEG)
- **Music Player** – Play MP3 files with next-track support
- **Image Gallery** – Browse and view images (JPG, PNG, WebP, GIF)
- **Media Manager** – Upload, rename, and delete media files
- **Thumbnails & Cover Art** – Upload custom thumbnails for videos and covers for music
- **Background Customization** – Set a custom background image
- **Password Protection** – Optional site-wide login + separate admin password
- **View Counters** – Active visitor tracking and per-video view counts
- **YouTube Integration** – Optional fetching of videos from a YouTube channel (requires API key)
- **Progressive Web App (PWA)** – Installable with service worker support
- **Admin Dashboard** – Manage settings, passwords, and feature toggles
- **Responsive Design** – Works on desktop and mobile

---

## Project Structure

```
Media Player - V6 Update/
│
├── assets/                     ← CSS & JavaScript (separated)
│   ├── css/
│   │   ├── common.css          # Shared dark theme, navbar, buttons, forms
│   │   ├── login.css
│   │   ├── admin.css
│   │   ├── admin-login.css
│   │   ├── settings.css
│   │   ├── media-manager.css
│   │   ├── gallery.css
│   │   ├── videos.css
│   │   ├── music.css
│   │   ├── music-player.css
│   │   └── watch.css
│   └── js/
│       ├── login.js
│       ├── admin.js
│       ├── settings.js
│       ├── media-manager.js
│       ├── gallery.js
│       ├── videos.js
│       ├── music.js
│       ├── music-player.js
│       └── watch.js
│
├── admin/                      ← Admin area (own folder)
│   ├── index.php               # Admin dashboard (was admin.php)
│   ├── login.php               # Admin login (was admin_login.php)
│   └── settings.php            # Settings panel
│
├── music/                      ← Uploaded MP3 files          (writable)
├── videos/                     ← Uploaded videos + thumbs    (writable)
├── gallery/                    ← Gallery images              (writable)
├── backgrounds/                ← Background images           (writable)
│
├── index.php                   # Site login page
├── watch.php                   # Main video player
├── music.php                   # Music library
├── music_player.php            # Music player interface
├── videos.php                  # Video library
├── gallery.php                 # Image gallery
├── media_manager.php           # Upload / rename / delete
├── upload.php                  # AJAX upload handler
├── delete_thumbnail.php
├── next_track.php
├── youtube_fetch.php
├── service-worker.js
├── manifest.json
├── settings.json               # Configuration
├── active_views.json
├── video_views.json
├── README.md
└── README-Permissions.md
```

### What lives where

| Folder / Area     | Purpose                                      |
|-------------------|----------------------------------------------|
| `assets/css/`     | All stylesheets (shared + page-specific)     |
| `assets/js/`      | All JavaScript (page-specific logic)         |
| `admin/`          | Admin dashboard, admin login, settings       |
| `music/`          | User-uploaded music                          |
| `videos/`         | User-uploaded videos + thumbnails            |
| `gallery/`        | User-uploaded gallery images                 |
| `backgrounds/`    | Custom background images                     |
| Root `.php` files | Public-facing player pages                   |

---

## Requirements

### Server
- PHP 7.4 or higher (PHP 8.x recommended)
- Web server: Apache or Nginx
- Write access to the project directory

### PHP Extensions
| Extension   | Required | Purpose                          |
|-------------|----------|----------------------------------|
| `json`      | Yes      | Settings & data files            |
| `session`   | Yes      | Authentication                   |
| `fileinfo`  | Recommended | MIME type detection            |
| `curl`      | Optional | YouTube integration              |
| `mbstring`  | Recommended | String handling                |

### Recommended PHP Settings (`php.ini`)

```ini
file_uploads = On
upload_max_filesize = 2048M
post_max_size = 2048M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
max_file_uploads = 20
display_errors = Off
log_errors = On
```

---

## Installation

### 1. Extract the files

```bash
unzip "Media Player - V6 Update.zip"
cd "Media Player - V6 Update"
```

### 2. Create required directories (if missing)

```bash
mkdir -p music videos gallery backgrounds assets/css assets/js admin
```

### 3. Set permissions

Replace `www-data` with your web server user (`apache`, `nginx`, etc.):

```bash
sudo chown -R www-data:www-data .

sudo chmod 755 .
sudo chmod 775 music videos gallery backgrounds
sudo chmod 664 settings.json active_views.json video_views.json

sudo find . -type f -name "*.php" -exec chmod 644 {} \;
sudo find . -type f -name "*.css" -exec chmod 644 {} \;
sudo find . -type f -name "*.js"  -exec chmod 644 {} \;

sudo touch php_errors.log
sudo chmod 664 php_errors.log
sudo chown www-data:www-data php_errors.log
```

### 4. Web server notes

Point the document root at the project folder (the one containing `index.php`).

**Apache** – useful `.htaccess` snippet:

```apache
php_value upload_max_filesize 2048M
php_value post_max_size 2048M
php_value max_execution_time 300
php_value memory_limit 512M

Options -Indexes

<FilesMatch "\.(json|log)$">
    Require all denied
</FilesMatch>
```

**Nginx** – set `client_max_body_size 2048M;` and protect `.json` / `.log` files.

### 5. First run

1. Open the site → login page (`index.php`).
2. Go to **Admin → Settings** (`admin/settings.php`).
3. Set **Admin Password** and **Main Site Password** immediately.
4. Optionally enable **Require Login for Main Site**.
5. Test uploading a small MP3 and MP4 via Media Manager.

---

## Admin Area

| URL                    | Purpose                |
|------------------------|------------------------|
| `/admin/login.php`     | Admin login            |
| `/admin/index.php`     | Admin dashboard        |
| `/admin/settings.php`  | All configuration      |

Admin pages load CSS/JS from `../assets/`.

---

## Permissions Summary

The web server user must be able to **write** to:

| Path                  | Purpose                          |
|-----------------------|----------------------------------|
| `music/`              | MP3 uploads + covers             |
| `videos/`             | Video + thumbnail uploads        |
| `gallery/`            | Gallery image uploads            |
| `backgrounds/`        | Background image uploads         |
| `settings.json`       | Save settings                    |
| `active_views.json`   | Live visitor counters            |
| `video_views.json`    | Per-video view counts            |
| `php_errors.log`      | Error logging                    |

See `README-Permissions.md` for the full detailed permissions guide.

---

## Supported Media Formats

| Type       | Extensions                              |
|------------|-----------------------------------------|
| Music      | `.mp3`                                  |
| Video      | `.mp4`, `.mkv`, `.avi`, `.mov`, `.mpeg` |
| Images     | `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif`|
| Thumbnails | `.jpg`, `.jpeg`, `.png`, `.webp`        |

---

## Common Issues

| Problem                                | Fix                                              |
|----------------------------------------|--------------------------------------------------|
| `DIR_002 – Directory not writable`     | `chmod 775` on media folders + correct ownership |
| Cannot save settings                   | Make `settings.json` writable                    |
| Large uploads fail                     | Raise `upload_max_filesize` & `post_max_size`    |
| Styles / scripts missing               | Check `assets/css` and `assets/js` are present   |
| Admin pages unstyled                   | Confirm paths use `../assets/...`                |
| Blank page / 500                       | Check `php_errors.log`                           |

---

## Security Recommendations

1. Set both Admin and Site passwords right after install.
2. Block direct access to `*.json` and `*.log` files.
3. Use HTTPS in production.
4. Keep PHP and the web server updated.
5. Consider moving `music/`, `videos/`, etc. outside the web root for stronger protection.

---

## Debugging

All major scripts log to `php_errors.log` in the project root.

---

**Media Player V6** – Self-hosted PHP media library with clean separation of CSS, JavaScript, and admin code.
