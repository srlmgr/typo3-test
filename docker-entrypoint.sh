#!/bin/sh
set -e

cd /var/www/html

# Returns "yes" if pages table exists, "no" if DB is empty, "error" if unreachable.
INITIALIZED=$(php -r "
try {
    \$pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s',
            getenv('TYPO3_DB_HOST'),
            (int)(getenv('TYPO3_DB_PORT') ?: 3306),
            getenv('TYPO3_DB_NAME')
        ),
        getenv('TYPO3_DB_USERNAME'),
        getenv('TYPO3_DB_PASSWORD'),
        [PDO::ATTR_TIMEOUT => 5]
    );
    echo \$pdo->query(\"SHOW TABLES LIKE 'pages'\")->rowCount() > 0 ? 'yes' : 'no';
} catch (Exception \$e) {
    echo 'error: ' . \$e->getMessage();
}
" 2>/dev/null)

if [ "$INITIALIZED" = "no" ]; then
    echo "[entrypoint] Database is empty — running TYPO3 setup..."
    vendor/bin/typo3 setup \
        --driver="${TYPO3_DB_DRIVER:-mysqli}" \
        --host="${TYPO3_DB_HOST}" \
        --port="${TYPO3_DB_PORT:-3306}" \
        --dbname="${TYPO3_DB_NAME}" \
        --username="${TYPO3_DB_USERNAME}" \
        --password="${TYPO3_DB_PASSWORD}" \
        --admin-username="${TYPO3_SETUP_ADMIN_USERNAME:-admin}" \
        --admin-user-password="${TYPO3_SETUP_ADMIN_PASSWORD}" \
        --admin-email="${TYPO3_SETUP_ADMIN_EMAIL:-admin@example.com}" \
        --project-name="${TYPO3_PROJECT_NAME:-TYPO3}" \
        --server-type="${TYPO3_SERVER_TYPE:-apache}" \
        --create-site="${TYPO3_SITE_URL:-http://localhost/}" \
        --force \
        --no-interaction
    chown -R www-data:www-data /var/www/html/var
    echo "[entrypoint] TYPO3 setup complete."
else
    echo "[entrypoint] DB check: ${INITIALIZED}"
fi

exec docker-php-entrypoint "$@"
