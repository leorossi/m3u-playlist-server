# M3U Playlist Server

A self-hosted server for managing and serving M3U/M3U8 IPTV playlists. Upload your playlist files, filter which channels to include, and share a stable URL with your IPTV device or player. Every time the device fetches the URL it gets a fresh, up-to-date playlist with only the channels you enabled.

## Features

- Upload M3U/M3U8 files — channels are parsed and stored in a database
- Enable or disable individual channels per playlist
- Auto-generated slug-based public URLs (e.g. `/lists/my-channels`)
- Request logging: IP, user agent, and all headers logged per playlist fetch
- Multi-user support with admin and user roles
- Admin can manage all users and all playlists
- Profile page to change your own password

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Symfony 8 (PHP 8.4) |
| Frontend | React 19 + TypeScript + Vite + Tailwind CSS |
| Database | SQLite (via Doctrine ORM) |
| Web server | nginx 1.27 + PHP-FPM |
| Runtime | Docker + Docker Compose |

---

## Running locally

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/)
- [Node.js](https://nodejs.org/) 18+ (for building the frontend)

### 1. Clone and configure

```bash
git clone <repo-url>
cd m3u-playlist-server
```

Copy the sample env file and fill in your values:

```bash
cp .env.sample .env.local
```

Edit `.env.local` and set:

```dotenv
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=a-strong-password
```

> `.env.local` is git-ignored and takes precedence over `.env`. Never commit real credentials to `.env`.

### 2. Build the frontend

```bash
cd assets
npm install
npm run build
cd ..
```

This compiles the React app into `public/build/`, which nginx serves as the SPA.

### 3. Start the services

```bash
docker compose up -d --build
```

This starts:
- **php** — PHP 8.4-FPM running the Symfony app
- **nginx** — listens on `http://localhost:8080`, serves the SPA and proxies PHP routes

### 4. Set up the database

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

This creates the SQLite schema at `var/data.db` and seeds the default admin account.

### 5. Open the app

Visit [http://localhost:8080](http://localhost:8080) and sign in with the `ADMIN_EMAIL` and `ADMIN_PASSWORD` you set in `.env.local`.

### Stopping

```bash
docker compose down
```

The `var/data.db` file persists on disk between restarts.

---

## Frontend development (hot reload)

To run the Vite dev server with hot module replacement:

```bash
cd assets
npm run dev
```

Open [http://localhost:5173](http://localhost:5173). The dev server proxies `/api` and `/lists` requests to the Symfony backend at `http://localhost:8080`, so both services need to be running.

---

## Running tests

Tests are PHP functional tests using Symfony's `WebTestCase`. They use a separate SQLite database (`var/data_test.db`) and recreate the schema from scratch before each test, so they are fully isolated from your dev data.

### Run the full suite

```bash
docker compose exec php php vendor/bin/phpunit --testdox
```

### Run a single test file

```bash
docker compose exec php php vendor/bin/phpunit --testdox tests/Controller/PlaylistControllerTest.php
```

### Test structure

```
tests/
  ApiTestCase.php                    # Base class: schema setup, seed users, helpers
  Fixtures/
    sample.m3u8                      # Sample playlist used in upload tests
  Controller/
    AuthControllerTest.php           # Login, logout, /api/me
    PlaylistControllerTest.php       # Playlist and channel CRUD, logs
    ProfileControllerTest.php        # Password change
    PublicControllerTest.php         # Public M3U serving endpoint
    UserControllerTest.php           # Admin user management
```

Every API endpoint has a happy-path test and at least one error case.

---

## Project structure

```
.
├── assets/                  # React + TypeScript frontend (Vite)
│   └── src/
│       ├── api/             # Fetch client and TypeScript types
│       ├── components/      # Layout, ProtectedRoute
│       ├── contexts/        # AuthContext
│       └── pages/           # Dashboard, PlaylistDetail, Logs, Users, Profile, Login
├── config/                  # Symfony configuration
├── docker/
│   ├── nginx/default.conf   # nginx routing (SPA + PHP)
│   └── php/Dockerfile       # PHP 8.4-FPM image
├── migrations/              # Doctrine database migrations
├── public/
│   └── build/               # Compiled frontend (git-ignored)
├── src/
│   ├── Controller/          # API controllers
│   ├── Entity/              # Doctrine entities (User, Playlist, Channel, RequestLog)
│   ├── Repository/          # Doctrine repositories
│   ├── Security/            # Voters, auth handlers, entry point
│   └── Service/             # M3U parser, slug generator, request logger
├── tests/                   # PHPUnit functional tests
├── var/
│   ├── data.db              # SQLite database (dev)
│   └── uploads/             # Uploaded M3U files
└── compose.yaml
```

## API endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/api/login` | — | Sign in (JSON body: `email`, `password`) |
| `POST` | `/api/logout` | user | Sign out |
| `GET` | `/api/me` | user | Current user info |
| `PUT` | `/api/profile/password` | user | Change own password |
| `GET` | `/api/lists` | user | List playlists |
| `POST` | `/api/lists` | user | Create playlist (multipart: `name`, `file`) |
| `GET` | `/api/lists/{id}` | owner/admin | Get playlist |
| `PUT` | `/api/lists/{id}` | owner/admin | Update name/slug |
| `DELETE` | `/api/lists/{id}` | owner/admin | Delete playlist |
| `GET` | `/api/lists/{id}/channels` | owner/admin | List channels |
| `PATCH` | `/api/lists/{id}/channels` | owner/admin | Toggle channels (`[{id, enabled}]`) |
| `GET` | `/api/lists/{id}/logs` | owner/admin | Paginated request logs |
| `GET` | `/api/users` | admin | List all users |
| `POST` | `/api/users` | admin | Create user |
| `GET` | `/api/users/{id}` | admin | Get user |
| `PUT` | `/api/users/{id}` | admin | Update user |
| `DELETE` | `/api/users/{id}` | admin | Delete user |
| `GET` | `/lists/{slug}` | — | **Public** — download playlist as M3U8 |
