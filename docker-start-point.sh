# !/bin/sh
# docker-start-point.sh
# 2023-05-08 | CR
apk add --update --no-cache mysql-client make zip
cd /var/www
cat > src/.env-prod-docker-mysql <<END
# .env-prod-docker-mysql
MYSQL_USER=root
MYSQL_PASSWORD=toor
MYSQL_PORT=3306
MYSQL_SERVER=do_bkp_db_mysql
MYSQL_DATABASE=test
NAME_SUFFIX=prod
BACKUP_PATH=./backup_db
LOG_FILE_PATH=./log
END
