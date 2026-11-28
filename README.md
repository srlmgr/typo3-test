# TYPO3 CMS Base Distribution

This repository builds a production TYPO3 image and provides a MariaDB-backed
Docker Compose setup for it.

## Prerequisites

- Docker Engine
- Docker Compose
- PHP 8.2 or newer, Composer, and the TYPO3 PHP extensions for maintaining
  dependencies on the host
- OpenSSL, for generating the TYPO3 encryption key

## Initial setup

Create the local environment file and replace its example secrets:

```bash
cp .env.example .env
```

Generate a fresh TYPO3 encryption key and set it as `TYPO3_ENCRYPTION_KEY` in
`.env`:

```bash
openssl rand -hex 48
```

At minimum, set `DB_ROOT_PASSWORD`, `DB_PASSWORD`,
`TYPO3_ENCRYPTION_KEY`, and `ADMIN_PASSWORD` in `.env`. The defaults use
`http://localhost:8380/` as the site URL and port `8380` for TYPO3.

## Build the base image locally

Build the Docker image as `t3test` using the repository's
`docker-compose.yml`:

```bash
docker compose build typo3
docker image inspect t3test
```

The image name is also used by both TYPO3 services in `docker-compose.yml`.
Rebuild `t3test` after changing the Dockerfile, Composer dependencies, or
application files.

## Initialize and run TYPO3

Start MariaDB, initialize an empty database once, and start TYPO3:

```bash
docker compose up -d mariadb
docker compose --profile tools run --rm typo3-init
docker compose up -d typo3
```

The initialization service refuses to continue when the database already
contains tables. It is therefore only needed for the first setup of a clean
database volume.

## Verify the local installation

Check the container and volume state:

```bash
docker compose ps
docker compose logs --tail=50 typo3
docker volume ls
```

Then verify the TYPO3 HTTP response:

```bash
curl --fail --show-error --head http://localhost:8380/
```

Open <http://localhost:8380/> in a browser. The backend is available at
<http://localhost:8380/typo3/>; sign in with the `ADMIN_USER` and
`ADMIN_PASSWORD` values from `.env`.

## Reset the local installation

To remove the database and TYPO3 application volumes and start over:

```bash
docker compose down -v
docker compose up -d mariadb
docker compose --profile tools run --rm typo3-init
docker compose up -d typo3
```

## Update TYPO3 dependencies

This project installs dependencies in the image from `composer.json` and
`composer.lock`. After updating Composer dependencies, rebuild the image:

```bash
docker compose build typo3
docker compose up -d --force-recreate typo3
```

## Upgrade to a new PHP version

Before changing PHP versions, check the TYPO3 and extension compatibility
requirements for the target PHP release. Review the package constraints in
`composer.json` and update the Composer platform setting only when the target
PHP version will be the minimum supported runtime.

1. Back up the database and the named TYPO3 volumes.
2. Change the default PHP image in `Dockerfile`

3. If PHP 8.5 becomes the minimum supported version, update
   `config.platform.php` in `composer.json` accordingly. Otherwise, leave the
   platform setting at the minimum supported PHP version.

4. Rebuild the `t3test` image and recreate the running services:

   ```bash
   docker compose build --no-cache typo3
   docker compose up -d --force-recreate mariadb typo3
   ```

5. Inspect the PHP runtime and application logs:

   ```bash
   docker compose exec typo3 php -v
   docker compose logs --tail=100 typo3
   curl --fail --show-error http://localhost:8380/
   ```

   Also verify the TYPO3 backend and run the Upgrade Analysis from the backend
   if TYPO3 reports that PHP configuration or extension changes are required.

## Upgrade TYPO3 files within the same main release

This procedure applies to patch or compatible minor updates within TYPO3 main
release 14, while keeping `typo3/cms-*` constraints compatible with the
existing release. The `^14.3` constraints in `composer.json` and the
`composer.lock` determine the exact TYPO3 files installed in the image.

1. Back up the MariaDB database and the `typo3-system`, `typo3-fileadmin`,
   `typo3-uploads`, and `typo3-temp` Docker volumes.

2. Update the TYPO3 packages inside the running container and refresh
   `composer.lock`:

   ```bash
   docker compose up -d mariadb typo3
   docker compose exec -e COMPOSER_ALLOW_SUPERUSER=1 typo3 \
     composer update "typo3/cms-*" --with-all-dependencies \
       --no-interaction --no-progress
   docker compose cp typo3:/var/www/html/composer.lock ./composer.lock
   ```

   Running Composer in the container resolves dependencies with the PHP
   version and extensions that TYPO3 will use in the final image. Copying the
   lockfile back to the repository makes the update reproducible. Composer
   updates only the dependencies allowed by the constraints in
   `composer.json`; it does not change a constraint such as `^14.3` into a
   different TYPO3 major release.

   If the site package or a third-party extension is also being upgraded,
   update its package explicitly and check its TYPO3 constraints:

   ```bash
   composer update myvendor/site-package vendor/package-name
   composer validate
   git diff -- composer.json composer.lock
   ```

3. Rebuild the `t3test` image and recreate TYPO3:

   ```bash
   docker compose build typo3
   docker compose up -d --force-recreate typo3
   ```

4. Run the TYPO3 upgrade checks and database updates for the existing
   installation. Use the Upgrade Analysis and Upgrade modules in the TYPO3
   backend, or run the image's TYPO3 CLI upgrade commands, for example:

   ```bash
   docker compose exec typo3 vendor/bin/typo3 upgrade:list
   docker compose exec typo3 vendor/bin/typo3 upgrade:run
   docker compose exec typo3 vendor/bin/typo3 extension:setup
   docker compose exec typo3 vendor/bin/typo3 asset:publish
   docker compose exec typo3 vendor/bin/typo3 cache:flush
   ```

5. Verify the frontend and backend, review the upgrade module, and confirm that
   the upgraded TYPO3 and PHP versions are present in the logs and environment.

Do not initialize the database again during an upgrade. The `typo3-init`
service refuses to run when the database is not empty, and the upgrade must
preserve the existing database and volume configuration.

## Next steps

- [Getting Started with TYPO3](https://docs.typo3.org/permalink/t3start:start)
- [Create a Site Package](https://docs.typo3.org/permalink/t3sitepackage:start)

## License

GPL-2.0 or later
