# Deployment guide

The app runs in Docker (php-fpm + nginx) on the remote server. The server's existing **haproxy** handles incoming traffic and forwards to the server's **nginx**, which reverse-proxies into the Docker container.

```
Internet → haproxy :443/:80 → nginx :8080 (host) → Docker nginx :80 → php-fpm :9000
```

---

## Prerequisites

- Docker + Docker Compose installed on the server
- Node.js 18+ available (only needed to build the frontend; can be done locally and copied)
- The server's nginx and haproxy are already running

---

## First deployment

### 1. Clone the repository

```bash
git clone <repo-url> /var/www/m3u-playlist-server
cd /var/www/m3u-playlist-server
```

### 2. Configure environment

```bash
cp .env.sample .env.local
```

Edit `.env.local`:

```dotenv
APP_ENV=prod
APP_SECRET=<random 32-byte hex — php -r "echo bin2hex(random_bytes(32));"> 
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=a-strong-password
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

### 3. Build the frontend

```bash
cd assets
npm ci
npm run build
cd ..
```

### 4. Build and start the containers

```bash
docker compose up -d --build
```

### 5. Set up the database and seed the admin user

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

The app is now listening on **port 8080** inside the host network.

---

## Bare-metal deployment (no Docker)

Use this when PHP-FPM runs directly on the host and haproxy does TLS termination before nginx.

```
Internet → haproxy :80/:443 (TLS) → nginx :8080 → php-fpm (unix socket)
```

### PHP-FPM pool

Edit (or create) a pool file, e.g. `/etc/php/8.x/fpm/pool.d/m3u.conf`:

```ini
[m3u]
user  = www-data
group = www-data

listen = /run/php/m3u.sock
listen.owner = www-data
listen.group = www-data
listen.mode  = 0660

pm = dynamic
pm.max_children      = 10
pm.start_servers     = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

; Adjust to your app path
chdir = /srv/m3u-playlist-server
```

Restart FPM after any pool change:

```bash
systemctl restart php8.x-fpm
```

### nginx site

Create `/etc/nginx/sites-available/m3u-playlist`:

```nginx
server {
    listen 8080;
    server_name _;

    root /srv/m3u-playlist-server/public;
    server_tokens off;

    # Vite build assets — long-lived cache (hashed filenames)
    location ^~ /build/ {
        try_files $uri =404;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # PHP-handled routes: API + public list endpoint
    location ~ ^/(api|lists)(/|$) {
        try_files $uri /index.php$is_args$args;
    }

    # SPA catch-all: serve built index.html for all other paths
    location / {
        try_files $uri /build/index.html;
    }

    # PHP-FPM handler (internal only)
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/m3u.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

Enable and reload:

```bash
ln -s /etc/nginx/sites-available/m3u-playlist /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### haproxy

Add to `/etc/haproxy/haproxy.cfg`:

```
frontend http_front
    bind *:80
    # Redirect all HTTP to HTTPS
    http-request redirect scheme https code 301

frontend https_front
    bind *:443 ssl crt /etc/haproxy/certs/m3u-server.pem
    # Forward real client IP and protocol to nginx / Symfony
    option forwardfor
    http-request set-header X-Forwarded-Proto https
    # Route by hostname
    acl host_m3u hdr(host) -i m3u-server.leorossi.online
    use_backend m3u_backend if host_m3u

backend m3u_backend
    server app 127.0.0.1:8080 check
```

> The TLS certificate at `/etc/haproxy/certs/m3u-server.pem` must be a single file containing the full chain + private key (i.e. `cat fullchain.pem privkey.pem > m3u-server.pem`).

Reload haproxy:

```bash
haproxy -c -f /etc/haproxy/haproxy.cfg && systemctl reload haproxy
```

### File permissions

PHP-FPM runs as `www-data` and needs write access to `var/cache/` and `var/log/`. If you deploy as the `debian` user:

```bash
# Give www-data group write access to var/
sudo chown -R debian:www-data /srv/m3u-playlist-server/var/
sudo chmod -R 775 /srv/m3u-playlist-server/var/

# Allow debian user to run bin/console as www-data for cache clears etc.
sudo usermod -aG www-data debian
```

Add this to your deploy script so permissions are reset after every `git pull`:

```bash
sudo chown -R debian:www-data /srv/m3u-playlist-server/var/
sudo chmod -R 775 /srv/m3u-playlist-server/var/
```

### First-time setup (bare metal)

```bash
cd /srv/m3u-playlist-server
cp .env.sample .env.local
# edit .env.local: APP_ENV=prod, APP_SECRET, DATABASE_URL, ADMIN_EMAIL, ADMIN_PASSWORD

composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

### Updating (bare metal)

```bash
cd /srv/m3u-playlist-server
git pull

# Rebuild frontend if assets changed
cd assets && npm ci && npm run build && cd ..

composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction

sudo chown -R debian:www-data var/ && sudo chmod -R 775 var/
```

---

## Continuous deployment (GitHub Actions)

Pushes to `main` automatically deploy via `.github/workflows/deploy.yml`.

### 1. Generate a dedicated SSH key pair for CI

Run this **on your local machine** (no passphrase):

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/m3u_deploy
```

This produces `~/.ssh/m3u_deploy` (private) and `~/.ssh/m3u_deploy.pub` (public).

### 2. Authorise the key on the server

```bash
# On the server — append the public key
echo "<contents of ~/.ssh/m3u_deploy.pub>" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### 3. Allow passwordless sudo for the deploy commands

The workflow runs `sudo chown`, `sudo chmod`, and `sudo systemctl reload php8.4-fpm`.  
Add a sudoers drop-in on the server (replace `debian` with your deploy user):

```bash
sudo visudo -f /etc/sudoers.d/m3u-deploy
```

Paste:

```
debian ALL=(ALL) NOPASSWD: /bin/chown -R debian\:www-data /srv/m3u-playlist-server/var/, \
                            /bin/chmod -R 775 /srv/m3u-playlist-server/var/, \
                            /bin/systemctl reload php8.4-fpm
```

> Adjust the PHP-FPM service name (`php8.4-fpm`) if your server runs a different version.

### 4. Add GitHub repository secrets

Go to **Settings → Secrets and variables → Actions** and add:

| Secret | Value |
|--------|-------|
| `SSH_HOST` | Server IP or hostname |
| `SSH_USER` | Deploy user (e.g. `debian`) |
| `SSH_KEY` | Contents of `~/.ssh/m3u_deploy` (the private key) |
| `SSH_PORT` | SSH port (e.g. `22`) |

### 5. Verify

Push any commit to `main` and watch the **Actions** tab on GitHub. A successful run means the server is fully up to date.

---

## nginx reverse proxy

Add a site config on the host nginx (e.g. `/etc/nginx/sites-available/m3u-playlist`):

```nginx
server {
    listen 8080;
    server_name _;

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
    }
}
```

> If you want nginx to listen on a different host port, change the Docker port mapping in `compose.yaml` (`"HOSTPORT:80"`) and update the `proxy_pass` accordingly.

Enable and reload:

```bash
ln -s /etc/nginx/sites-available/m3u-playlist /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## haproxy

Add a backend in `/etc/haproxy/haproxy.cfg` that routes by hostname to the nginx listener:

```
frontend https_front
    bind *:443 ssl crt /etc/haproxy/certs/example.com.pem
    acl host_m3u hdr(host) -i m3u.example.com
    use_backend m3u_backend if host_m3u

backend m3u_backend
    option forwardfor
    http-request set-header X-Forwarded-Proto https
    server app 127.0.0.1:8080 check
```

Reload haproxy:

```bash
haproxy -c -f /etc/haproxy/haproxy.cfg && systemctl reload haproxy
```

---

## Updating the app

```bash
cd /var/www/m3u-playlist-server
git pull

# Rebuild frontend if assets changed
cd assets && npm ci && npm run build && cd ..

# Rebuild PHP image if Dockerfile or composer.json changed
docker compose build php

# Apply any new database migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Restart
docker compose up -d
```

---

## Useful commands

```bash
# View logs
docker compose logs -f php
docker compose logs -f nginx

# Open a shell in the PHP container
docker compose exec php bash

# Clear Symfony cache
docker compose exec php php bin/console cache:clear

# Stop the app
docker compose down
```
