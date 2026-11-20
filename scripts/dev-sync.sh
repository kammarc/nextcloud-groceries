#!/bin/sh
# Copies the app's runtime files into a local Nextcloud docker container (default: groceries-dev)
# and enables the app. Usage: scripts/dev-sync.sh [container]
#
# Do NOT bind-mount the project into /var/www/html of the official nextcloud image instead:
# its entrypoint runs `rsync --delete` over /var/www/html and wipes the mounted folder.
set -e
CONTAINER="${1:-groceries-dev}"
cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1
tar -cf - appinfo lib templates img js css serviceworker l10n \
	| docker exec -i "$CONTAINER" sh -c 'rm -rf /var/www/html/custom_apps/groceries && mkdir -p /var/www/html/custom_apps/groceries && tar -xf - -C /var/www/html/custom_apps/groceries && chown -R www-data:www-data /var/www/html/custom_apps/groceries'
# runs the app's database migrations after a version bump
docker exec -u www-data "$CONTAINER" php occ upgrade --no-interaction >/dev/null 2>&1 || true
docker exec -u www-data "$CONTAINER" php occ app:enable groceries
# reset OPcache (the image revalidates PHP files only every 60 s)
docker exec "$CONTAINER" apache2ctl -k graceful
