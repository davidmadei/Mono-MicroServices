#!/usr/bin/env bash
#
# setup-mac.sh -- macOS / Homebrew equivalent of setup.sh (which targets Ubuntu).
#
# OPTIONAL. The lab itself runs on AWS. This is only for reading and tracing
# the app on your own machine.
#
# Run it:   chmod +x setup-mac.sh && ./setup-mac.sh
#
# Two things differ from the Ubuntu script beyond the package manager:
#   1. macOS has no systemctl. Homebrew uses `brew services` instead.
#   2. Homebrew's Apache listens on port 8080, not 80, and its PHP module
#      is NOT loaded automatically. Both are fixed below.

set -e

BREW_PREFIX="$(brew --prefix)"          # /opt/homebrew on Apple Silicon, /usr/local on Intel
HTTPD_CONF="$BREW_PREFIX/etc/httpd/httpd.conf"
DOCROOT="$BREW_PREFIX/var/www"

echo "==> Homebrew prefix: $BREW_PREFIX"

# ---------------------------------------------------------------------------
# 1. Install
# ---------------------------------------------------------------------------
# Ubuntu needed seven packages. Homebrew's php bundles zip, mbstring, mysqli,
# xml and json already, so php-zip / php-mbstring / php-mysql / php-xml have no
# separate formula -- `brew install php` covers all of them.
# unzip ships with macOS, so it is not listed either.

echo "==> Installing httpd, php, mysql, wget"
brew install httpd php mysql wget

# ---------------------------------------------------------------------------
# 2. Wire PHP into Apache
# ---------------------------------------------------------------------------
# Ubuntu's libapache2-mod-php does this for you. Homebrew does not.

if ! grep -q "libphp.so" "$HTTPD_CONF"; then
  echo "==> Adding the PHP module to httpd.conf"
  cat >> "$HTTPD_CONF" <<'CONF'

# --- added for CSC 547 Lab 02 ---
LoadModule php_module PHP_MODULE_PATH
<FilesMatch \.php$>
    SetHandler application/x-httpd-php
</FilesMatch>
<IfModule dir_module>
    DirectoryIndex index.php index.html
</IfModule>
CONF
  # substitute the real path
  /usr/bin/sed -i '' "s|PHP_MODULE_PATH|$BREW_PREFIX/opt/php/lib/httpd/modules/libphp.so|" "$HTTPD_CONF"
else
  echo "==> PHP module already present in httpd.conf, skipping"
fi

# ---------------------------------------------------------------------------
# 3. Start the services
# ---------------------------------------------------------------------------
# Ubuntu: sudo systemctl start apache2
# macOS:  brew services start httpd

echo "==> Starting httpd and mysql"
brew services start httpd
brew services start mysql

# ---------------------------------------------------------------------------
# 4. Report
# ---------------------------------------------------------------------------
cat <<EOF

===========================================================================
Done. Where things are:

  Apache config     $HTTPD_CONF
  Document root     $DOCROOT
  Apache URL        http://localhost:8080      <-- port 8080, NOT 80
  MySQL             mysql -u root              <-- no password by default

Verify Apache is up (the macOS equivalent of 'systemctl status apache2'):

  brew services list
  curl -I http://localhost:8080

Next steps for the lab:

  1. Load the schema. You do NOT need phpMyAdmin -- DatabaseSetup.sql creates
     every table and seeds the data:

       mysql -u root < DatabaseSetup.sql

  2. Copy the app into the document root:

       cp Monolithic/products.php Monolithic/products.js "$DOCROOT"/

  3. Point the app at your local database. In $DOCROOT/products.php change:

       \$servername = "<your-rds-endpoint>";   ->  "localhost"
       \$username   = "<your-db-username>";    ->  "root"
       \$password   = "<your-db-password>";    ->  ""

  4. Open http://localhost:8080/products.php

To use the microservices version instead, copy products.php, displayProducts.php,
buyProducts.php and products.js from the Microservices folder, and set the two
service URLs in products.php to http://localhost:8080/displayProducts.php and
http://localhost:8080/buyProducts.php
===========================================================================
EOF
