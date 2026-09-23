# Running the Webstore Locally (optional)

> **This lab runs on AWS.** The webstore is deployed to Elastic Beanstalk, the database
> is Amazon RDS, and the whole point — autoscaling under load — only happens in the
> cloud. Nothing in this file is required to complete the lab.
>
> This file exists for one reason: if you want a copy of the application running on
> your own machine so you can read it, trace a request through it, and break it
> without touching AWS, here is how. A local copy cannot scale, has no load balancer,
> and no CloudWatch metrics, so it shows you the *application* and none of the
> *infrastructure*.
>
> To do the actual lab, go to `Monolithic/readMePaaS.md` and then
> `Microservices/readMePaas.md`.

---

## Pick your path

The paths differ only in **Step 1**. Steps 2 through 5 are the same for everyone,
except for three values that depend on your OS:

| | macOS (Homebrew) | Ubuntu / Debian (apt) |
|---|---|---|
| Web root | `/opt/homebrew/var/www` | `/var/www/html` |
| URL | `http://localhost:8080` | `http://localhost` |
| Service control | `brew services` | `sudo systemctl` |

> **Apple Silicon vs Intel:** Homebrew installs to `/opt/homebrew` on Apple Silicon
> and `/usr/local` on Intel Macs. Run `brew --prefix` to see yours, and substitute it
> wherever this document says `/opt/homebrew`.

> **Windows:** you do not need any of this. Beanstalk takes a `.zip` through the
> browser, RDS is a hostname, dbeaver is cross-platform, and Locust installs with
> `pip install locust`. Nothing in the lab requires a local web server.

---

## Install — macOS

Run the script. It is safe to re-run if you have already installed some of this by
hand — `brew install` is a no-op on formulas you already have, and the script checks
`httpd.conf` before touching it.

```bash
chmod +x setup-mac.sh
./setup-mac.sh
```

Or do it by hand:

```bash
brew install httpd php mysql wget
```

Four formulas, not seven. Homebrew's `php` already bundles zip, mbstring, mysqli,
xml, and json, so there are no separate `php-*` packages. `unzip` ships with macOS.
(The Apache formula is named `httpd`, not `apache2`.)

Then wire PHP into Apache — **Homebrew does not do this for you**, unlike Ubuntu's
`libapache2-mod-php`. Append to `/opt/homebrew/etc/httpd/httpd.conf`:

```apache
LoadModule php_module /opt/homebrew/opt/php/lib/httpd/modules/libphp.so
<FilesMatch \.php$>
    SetHandler application/x-httpd-php
</FilesMatch>
<IfModule dir_module>
    DirectoryIndex index.php index.html
</IfModule>
```

Start both services:

```bash
brew services start httpd
brew services start mysql
```

> **Port 8080, not 80.** Homebrew runs Apache as your user so it needs no `sudo`,
> and ports below 1024 require root. Every URL in this document is therefore
> `http://localhost:8080/...` on macOS. Homebrew tells you this during install.

Now go to [Step 2](#step-2--verify-the-web-server).

---

## Install — Ubuntu

```bash
chmod +x setup.sh
./setup.sh
```

Or by hand:

```bash
sudo apt-get update
sudo apt-get install -y apache2 libapache2-mod-php \
                        php php-zip php-mbstring php-mysql php-xml \
                        mysql-server unzip wget
```

Apache starts automatically. Now go to [Step 2](#step-2--verify-the-web-server).

---

## Step 2 — Verify the web server

**macOS:**
```bash
brew services list
curl -I http://localhost:8080
```

**Ubuntu:**
```bash
sudo systemctl status apache2
curl -I http://localhost
```

Either should return `HTTP/1.1 200`. If not, stop here and fix it — nothing below
will work until Apache serves a page.

---

## Step 3 — Load the database

The repository ships a schema file that creates all three tables and seeds the data.
**You do not need phpMyAdmin.** One command replaces the entire GUI table-building
process:

**macOS:**
```bash
mysql -u root < DatabaseSetup.sql
```

**Ubuntu** — a fresh `mysql-server` authenticates root through the socket, so you
need `sudo` rather than `-u root`:
```bash
sudo mysql < DatabaseSetup.sql
```

Confirm it worked:

```bash
mysql -u root -e "SELECT * FROM Products.Footwear;"     # macOS
sudo mysql -e "SELECT * FROM Products.Footwear;"        # Ubuntu
```

You should see three pairs of shoes.

> **Stock levels.** `DatabaseSetup.sql` seeds 100,000,000 of each item so a load test
> cannot drain it. `reset_demo.sql` puts back the original 25 / 40 / 15 if you want
> realistic numbers to click through; `reset_inventory.sql` puts the large numbers back.

---

## Step 4 — Deploy the app

### Monolithic

```bash
# macOS
cp Monolithic/products.php Monolithic/products.js /opt/homebrew/var/www/

# Ubuntu
sudo cp Monolithic/products.php Monolithic/products.js /var/www/html/
```

Then edit `products.php` in the web root and change the connection placeholders to
your local database:

```php
$servername = "localhost";
$username   = "root";
$password   = "";          // empty on a fresh local install
```

### Microservices

```bash
# macOS
cp Microservices/products.php Microservices/displayProducts.php \
   Microservices/buyProducts.php Microservices/products.js /opt/homebrew/var/www/

# Ubuntu
sudo cp Microservices/products.php Microservices/displayProducts.php \
        Microservices/buyProducts.php Microservices/products.js /var/www/html/
```

Three edits, not one:

1. In `displayProducts.php` — set `$databaseServer`, `$username`, `$password`.
2. In `buyProducts.php` — the same three values.
3. In `products.php` — set the two service URLs. **Include the port on macOS:**

```php
// macOS
$displayProductsMicroService = "http://localhost:8080/displayProducts.php";
$buyProductsMicroService     = "http://localhost:8080/buyProducts.php";

// Ubuntu
$displayProductsMicroService = "http://localhost/displayProducts.php";
$buyProductsMicroService     = "http://localhost/buyProducts.php";
```

> **Omit the port and the call fails.** `file_get_contents` tries port 80 and is
> refused. The page now says *RouteRequest could not reach DisplayProducts* and shows
> the URL it tried (the original code showed a blank page). The monolithic version
> cannot fail this way — it never makes a network call.

---

## Step 5 — Verify the app

Open `http://localhost:8080/products.php` (macOS) or `http://localhost/products.php`
(Ubuntu).

1. Three products should be listed with descriptions and prices.
2. Pick a quantity, enter any email, click **Buy**.
3. Confirm the order was recorded:

```bash
mysql -u root -e "SELECT * FROM Products.Orders;"
```

If a row appears, the whole stack works: browser → Apache → PHP → MySQL.

---

## Troubleshooting

**"RouteRequest could not reach …" (microservices only).** Missing `:8080` on the
two service URLs. See the warning in Step 4.

**`.php` files download instead of running.** PHP is not wired into Apache. On macOS,
check the `LoadModule` line is in `httpd.conf` and restart with
`brew services restart httpd`.

**`Access denied for user 'root'`.** On Ubuntu use `sudo mysql` rather than
`mysql -u root`. On macOS a fresh Homebrew MySQL has an empty root password — leave
`$password` as `""`.

**`Database connection failed` on the page.** The connection details still hold the
placeholders or an AWS RDS endpoint. Change the host to `localhost`.

**Purchases stop working after a while.** Inventory has drained (likely after
`reset_demo.sql`). Run `reset_inventory.sql`.

**Port 8080 already in use.** Something else has it. `lsof -i :8080` will name it.
