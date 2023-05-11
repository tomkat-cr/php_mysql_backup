#!/bin/sh
# do_bkp_db_run.sh
# 2023-05-10 | CR
# Execute do_bkp_db.php to backup the mysql test database

php /var/www/src/do_bkp_db.php /var/www/src/.env-prod-docker-mysql
