#!/bin/sh
# dbseed_run.sh
# 2023-05-10 | CR
# Execute dbseed.php to populate the mysql test database
# Parameters:
# $1 = .SQL file to be used instead of the default create table/insert statements

php /var/www/scripts/dbseed.php /var/www/src/.env-prod-docker-mysql $1
