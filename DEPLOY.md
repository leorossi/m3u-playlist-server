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
