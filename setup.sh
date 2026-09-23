#!/usr/bin/env bash
#
# setup.sh -- Ubuntu / Debian. Installs Apache, PHP and MySQL for the webstore.
#
# OPTIONAL. The lab itself runs on AWS. This is only for reading and tracing
# the app on your own machine.
#
# macOS users:   run setup-mac.sh instead (apt does not exist on macOS).
# Windows users: not needed. The lab runs on AWS; this is optional. See readMeLocal.md.
#
# Run it:  chmod +x setup.sh && ./setup.sh
#
# Tested on Ubuntu 20.04 and 22.04.

set -e

echo "==> Updating package lists"
sudo apt-get update

echo "==> Installing Apache, PHP and MySQL"
# -y so the script runs unattended. libapache2-mod-php is what wires PHP into
# Apache; without it .php files are served as downloads instead of executed.
sudo apt-get install -y \
  apache2 \
  libapache2-mod-php \
  php php-zip php-mbstring php-mysql php-xml \
  mysql-server \
  unzip \
  wget

# php-json is not listed: JSON is compiled into PHP 8 and the separate package
# no longer exists on current Ubuntu.

echo "==> Verifying Apache"
sudo systemctl enable --now apache2
sudo systemctl status apache2 --no-pager || true

cat <<'EOF'

===========================================================================
Installed. Next steps:

  1. Load the schema. You do NOT need phpMyAdmin -- DatabaseSetup.sql creates
     every table and seeds the data:

       sudo mysql < DatabaseSetup.sql

     (Use `sudo mysql`, not `mysql -u root` -- a fresh mysql-server
     authenticates root through the unix socket, so -u root is refused.)

  2. Copy the app into the web root:

       sudo cp Monolithic/products.php Monolithic/products.js /var/www/html/

  3. Edit /var/www/html/products.php and change the connection placeholders
     to:

       $servername = "localhost";
       $username   = "root";
       $password   = "";

  4. Open http://localhost/products.php

Full instructions, including the microservices version: readMeLocal.md
===========================================================================
EOF
