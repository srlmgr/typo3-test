# TYPO3 14 — Production Deployment

This describes how to start the production setup on a fresh system, assuming only the Docker image and the `docker-compose-prod.yml` file are available.

## Prerequisites

- Docker + Docker Compose installed
- The production image `my-typo3-site:latest` available locally or in a registry

If the image is not yet present, build it from source first (see `README.md`), then push it to your registry and adjust the `image:` reference in `docker-compose-prod.yml`.

## Steps

### 1. Create the environment file

```bash
cp .env.example .env.prod
```

Edit `.env.prod` and fill in all values:

```bash
# Database
DB_ROOT_PASSWORD=<strong-root-password>
DB_NAME=typo3
DB_USER=typo3
DB_PASSWORD=<strong-db-password>

# TYPO3 admin account — created automatically on first boot
ADMIN_USER=admin
ADMIN_PASSWORD=<strong-password-with-special-char>
ADMIN_EMAIL=admin@example.com

# Encryption key — generate once, keep stable across deployments
# openssl rand -hex 48
TYPO3_ENCRYPTION_KEY=<generated-key>

# Port the site will be reachable on
TYPO3_PORT=8080
```

> **Important:** The encryption key must stay the same across deployments. Changing it invalidates all sessions and encrypted data.

### 2. Start the stack

```bash
docker compose -f docker-compose-prod.yml --env-file .env.prod up -d
```

On first start TYPO3 detects the empty database and runs setup automatically:

- Creates all database tables
- Creates the admin account from `ADMIN_USER` / `ADMIN_PASSWORD`

This takes about 30–60 seconds. Watch progress with:

```bash
docker compose -f docker-compose-prod.yml --env-file .env.prod logs -f typo3
```

### 3. Access the backend

Once the container is healthy, open:

```
http://<host>:<TYPO3_PORT>/typo3
```

Log in with the `ADMIN_USER` / `ADMIN_PASSWORD` you set in `.env.prod`.

The frontend returns 404 until you create a root page via the backend — that is expected.

## Subsequent starts

```bash
docker compose -f docker-compose-prod.yml --env-file .env.prod up -d
```

TYPO3 skips auto-setup on subsequent starts because the database is already populated.

## Updating the site

1. Build a new image with your changes: `docker build -t my-typo3-site .`
2. Push it to your registry if deploying remotely
3. Pull and restart:

```bash
docker compose -f docker-compose-prod.yml --env-file .env.prod pull
docker compose -f docker-compose-prod.yml --env-file .env.prod up -d
```

## Runtime volumes

The following volumes persist data across container replacements:

| Volume            | Content               |
| ----------------- | --------------------- |
| `typo3_var`       | Cache, sessions, logs |
| `typo3_fileadmin` | Editor-uploaded files |
| `typo3_uploads`   | Form uploads          |
| `db_data`         | Database              |

Back these up before any deployment that changes the database schema.
