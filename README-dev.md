# TYPO3 14 — Developer Setup

This guide gets you from a fresh clone to a running local TYPO3 instance. No PHP or Composer on the host is required — everything runs inside Docker.

## Prerequisites

- Docker + Docker Compose installed

## First-time setup

### 1. Create your environment file

```bash
cp .env.example .env
```

Edit `.env` and adjust at minimum:

```bash
# Your host user IDs — must match your system to avoid permission problems
HOST_UID=1000   # replace with: id -u
HOST_GID=1000   # replace with: id -g

# Generate a fresh encryption key
TYPO3_ENCRYPTION_KEY=$(openssl rand -hex 48)
```

The other defaults (DB credentials, admin password, ports) are fine for local development.

### 2. Build the Docker image

```bash
docker compose build
```

### 3. Install TYPO3 via Composer

This step only runs once. It installs the TYPO3 base distribution into the project directory.

```bash
docker compose run --rm setup
```

This uses `composer create-project` inside the container and copies the result into your working directory (including `composer.json`, `composer.lock`, `vendor/`, and `public/`).

### 4. Start the stack

```bash
docker compose up -d typo3 db phpmyadmin
```

Wait for the database healthcheck to pass (about 20–30 seconds), then run the TYPO3 installer:

```bash
docker compose run --rm composer vendor/bin/typo3 setup \
  --driver=mysqli \
  --host=db \
  --port=3306 \
  --dbname="${DB_NAME:-typo3}" \
  --username="${DB_USER:-typo3}" \
  --password="${DB_PASSWORD:-typo3pass}" \
  --admin-username="${ADMIN_USER:-admin}" \
  --admin-user-password="${ADMIN_PASSWORD:-Admin1234!}" \
  --admin-email="${ADMIN_EMAIL:-admin@example.com}" \
  --project-name="TYPO3 Dev" \
  --server-type=apache \
  --create-site="http://localhost:${TYPO3_PORT:-8380}/" \
  --no-interaction
```

Or read the values from your `.env` file:

```bash
source .env
docker compose run --rm composer vendor/bin/typo3 setup \
  --driver=mysqli --host=db --port=3306 \
  --dbname="$DB_NAME" --username="$DB_USER" --password="$DB_PASSWORD" \
  --admin-username="$ADMIN_USER" --admin-user-password="$ADMIN_PASSWORD" \
  --admin-email="$ADMIN_EMAIL" \
  --project-name="TYPO3 Dev" --server-type=apache \
  --create-site="http://localhost:${TYPO3_PORT:-8380}/" \
  --no-interaction
```

### 5. Open the site

| URL                            | What                                        |
| ------------------------------ | ------------------------------------------- |
| `http://localhost:8380/`       | Frontend (404 until you create a root page) |
| `http://localhost:8380/typo3/` | Backend login                               |
| `http://localhost:8081/`       | phpMyAdmin                                  |

Log in to the backend with the `ADMIN_USER` / `ADMIN_PASSWORD` from your `.env`.

## Daily workflow

Start the stack:

```bash
docker compose up -d
```

Stop it:

```bash
docker compose down
```

Database and its data persist in the `db_data` Docker volume across restarts.

## Managing Composer dependencies

Use the `composer` service to run any Composer command — no local PHP needed:

```bash
# Add an extension
docker compose run --rm composer require vendor/my-extension

# Update dependencies
docker compose run --rm composer update

# Any other Composer command
docker compose run --rm composer <command>
```

## File editing

The `docker-compose.override.yml` mounts the project root into the container and runs Apache under your host user (`HOST_UID`/`HOST_GID`). This means:

- You edit files locally with your normal editor.
- PHP picks up changes immediately (no rebuild needed).
- Files created inside the container (caches, etc.) are owned by you, not root.

## Resetting to a clean state

To wipe the database and start over (keeps source files):

```bash
docker compose down -v
docker compose up -d typo3 db phpmyadmin
# then re-run the vendor/bin/typo3 setup command from step 4
```

To also remove installed TYPO3 files and start from scratch:

```bash
docker compose down -v
rm -rf vendor public/typo3 public/index.php composer.json composer.lock var/
docker compose run --rm setup
docker compose up -d typo3 db phpmyadmin
# then re-run the vendor/bin/typo3 setup command from step 4
```
