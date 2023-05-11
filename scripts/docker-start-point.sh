# !/bin/sh
# docker-start-point.sh
# 2023-05-08 | CR

apk add --update mysql-client make zip libzip libzip-dev
docker-php-ext-install mysqli zip pdo_mysql

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

cat > src/.env-prod <<END
# .env-prod
@mysql-test-01=./src/.env-prod-docker-mysql
@mysql-test-02=./src/.env-prod-docker-mysql
END

cat > web_cron/.env-prod-web-cron <<END
# .env-prod-web-cron
COMMAND="php%20./src/do_bkp_db.php%20./src/.env-prod-docker-mysql"
NAME=do_bkp_db-docker
NAME_SUFFIX=
LOG_FILE_PATH=./log
DEBUG=1
END

sh scripts/dbseed_run.sh

