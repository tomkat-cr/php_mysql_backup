#!/bin/sh
# dbseed_run.sh
# 2023-05-10 | CR
# Execute dbseed.php to populate the mysql test database

php /var/www/scripts/dbseed.php /var/www/src/.env-prod-docker-mysql
