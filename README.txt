TONY FORD'S FREE IMAGE GALLERY
by Anthony W. Ford, Jr. (Tony Ford) - awfordjr@gmail.com
Free for non-commercial use. See LICENSE.txt.
==========================================================

A free, self-hosted photo & image gallery written in PHP and MySQL/MariaDB.
Share your pictures with the world - as the only poster (Single Owner) or
with everybody (Open Registration). You choose when you install it, and you can
change your mind any time in Admin > Settings.

WHAT YOU NEED
  - Any web host with PHP 8.0 or newer (PHP 8.2+ recommended)
  - PHP extensions: pdo_mysql, gd, fileinfo (exif and WebP support in GD are nice to have)
  - A MySQL or MariaDB database (create an empty one and a user for it - your
    host's control panel, such as cPanel, does this in a minute)
  - Apache (works out of the box) or nginx (see "nginx" below)

INSTALL IN 5 STEPS
  1. Upload everything in this folder to your web space (public_html or a sub-folder).
  2. Make sure the folder itself and the uploads/ folder are writable by PHP
     (permissions 755, or 775 if your host needs it).
  3. Open  http://your-site/install.php  in your browser.
  4. The installer checks your server, then asks for:
       - database host, name, user and password
       - your site title
       - who can post:  [ ] Single Owner (only you post; others view, like and comment)
                        [ ] Open Registration (anyone can sign up and share)
       - your admin username, email and password
  5. Click Install. It creates the tables and your admin account, writes
     config.php, and then LOCKS ITSELF (install.php is renamed to
     install.php.locked). Log in and start uploading!

  If the installer cannot write config.php it shows you the text to save as
  config.php yourself - nothing else is needed.

TRY IT WITH DEMO PICTURES (optional)
  From a command line in the gallery folder:   php tools/seed-demo.php
  This adds nested categories and 28 generated demo pictures (no copyrighted
  photos). Delete them in Admin > Images (tag: demo).

NGINX
  Add these to your server block (Apache users need nothing - .htaccess does it):
    location / { try_files $uri $uri/ /index.php?r=$uri&$args; }
    location ~ ^/(inc|pages|tools)/ { deny all; }
    location ~ ^/(config\.php|install\.php\.locked)$ { deny all; }
    location ^~ /uploads/ { location ~ \.php$ { deny all; } }
    location ~ \.php$ { fastcgi_pass unix:/run/php/php-fpm.sock; include fastcgi_params;
                        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
  (If you skip pretty URLs, turn "Pretty URLs" off in Admin > Settings.)

FEATURES
  Nested albums/categories with breadcrumbs - justified grid layout - lightbox with
  keyboard and swipe - slideshow - drag & drop multi-upload with progress - EXIF info -
  album covers - likes and favorites - tags - comments - search - SEO-friendly URLs -
  WebP thumbnails - lazy loading - admin dashboard with stats - moderation - user
  management - 10 themes (Toxic Rain, Matrix, Dragon's Lair, Midnight Starfield, Ember Glow,
  Frozen Snowfall, Neon Synthwave, Ocean Depths, Classic Light, Classic Dark) with
  animated effects that can be switched off, and visitors can pick their own theme.

SECURITY
  Prepared statements everywhere, password hashing, CSRF tokens, session
  protection and login throttling, strict Content-Security-Policy, uploads are
  checked (real MIME type, size, pixel limits), re-encoded, given random
  names, and stored where scripts cannot run.

HELP
  Tony Ford - awfordjr@gmail.com
