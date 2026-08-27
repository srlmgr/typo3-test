#!/bin/sh
set -e
composer create-project typo3/cms-base-distribution:^14 /tmp/t3 --no-interaction
cp -r /tmp/t3/. /var/www/html/
find /var/www/html -maxdepth 10 -exec chown "${HOST_UID}:${HOST_GID}" {} \;
