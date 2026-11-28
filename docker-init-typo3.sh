#!/bin/sh
set -eu

cd /var/www/html

echo "[typo3-init] Checking configuration..."

# Validate required environment variables
: "${TYPO3_DB_HOST:?TYPO3_DB_HOST is required}"
: "${TYPO3_DB_NAME:?TYPO3_DB_NAME is required}"
: "${TYPO3_DB_USERNAME:?TYPO3_DB_USERNAME is required}"
: "${TYPO3_DB_PASSWORD:?TYPO3_DB_PASSWORD is required}"
: "${TYPO3_SETUP_ADMIN_PASSWORD:?TYPO3_SETUP_ADMIN_PASSWORD is required}"
: "${TYPO3_SITE_URL:?TYPO3_SITE_URL is required (e.g. https://www.example.com/)}"

# Check database connectivity and initialization state.
# Any existing table means we refuse to initialize.
DB_STATUS=$(php -r '
try {
    $pdo = new PDO(
        sprintf(
            "mysql:host=%s;port=%d;dbname=%s",
            getenv("TYPO3_DB_HOST"),
            (int)(getenv("TYPO3_DB_PORT") ?: 3306),
            getenv("TYPO3_DB_NAME")
        ),
        getenv("TYPO3_DB_USERNAME"),
        getenv("TYPO3_DB_PASSWORD"),
        [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo count($tables) === 0 ? "empty" : "initialized";

} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
')

if [ "$DB_STATUS" != "empty" ]; then
    echo "[typo3-init] ERROR: Database is not empty."
    echo "[typo3-init] Refusing to initialize an existing database."
    exit 1
fi

echo "[typo3-init] Database is empty. Starting TYPO3 setup..."

# --create-site is needed to create the root page (uid 1), but it also
# overwrites config/sites/main with a generated config. Preserve the site
# configuration shipped in the image and restore it afterwards.
SITES_BACKUP=$(mktemp -d)
cp -a config/sites/. "$SITES_BACKUP/"

vendor/bin/typo3 setup \
    --driver="${TYPO3_DB_DRIVER:-mysqli}" \
    --host="${TYPO3_DB_HOST}" \
    --port="${TYPO3_DB_PORT:-3306}" \
    --dbname="${TYPO3_DB_NAME}" \
    --username="${TYPO3_DB_USERNAME}" \
    --admin-username="${TYPO3_SETUP_ADMIN_USERNAME:-admin}" \
    --admin-email="${TYPO3_SETUP_ADMIN_EMAIL:-admin@example.com}" \
    --project-name="${TYPO3_PROJECT_NAME:-TYPO3}" \
    --server-type="${TYPO3_SERVER_TYPE:-apache}" \
    --create-site="${TYPO3_SITE_URL}" \
    --force \
    --no-interaction

echo "[typo3-init] Restoring shipped site configuration..."
rm -rf config/sites
mkdir -p config/sites
cp -a "$SITES_BACKUP/." config/sites/
rm -rf "$SITES_BACKUP"

echo "[typo3-init] Setting permissions..."

chown -R www-data:www-data \
    var \
    public/fileadmin \
    public/uploads \
    public/typo3temp \
    config/system

echo "[typo3-init] TYPO3 initialization completed successfully."
