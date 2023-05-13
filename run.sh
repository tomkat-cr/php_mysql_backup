#!/bin/sh
# run.sh
# 2023-04-02 | CR
#
APP_DIR='src'
SCRIPT_DIR='scripts'
COMPOSER_VERSION="2.2.7"
ENV_FILESPEC=""
if [ -f "./.env" ]; then
    ENV_FILESPEC="./.env"
fi
if [ "$ENV_FILESPEC" != "" ]; then
    set -o allexport; source ${ENV_FILESPEC}; set +o allexport
fi
if [ "$2" != "" ]; then
    BACKUP_CONFIG_FILENAME="$2"
else
    BACKUP_CONFIG_FILENAME=".env-prod"
fi
# if [[ "$1" != "deactivate" && "$1" != "init" && "$1" != "clean" && "$1" != "test" ]]; then
if [ "$1" = "install" ]; then
    php composer.phar install -o --no-dev
fi
if [ "$1" = "init" ]; then
    curl -sS https://getcomposer.org/installer | php -- --filename=composer.phar --version=${COMPOSER_VERSION}
    php composer.phar require symfony/dotenv
fi
if [ "$1" = "clean" ]; then
    echo "Cleaning..."
    rm -rf vendor
fi
if [ "$1" = "up" ]; then
    echo "Docker-compose up..."
    cd ${SCRIPT_DIR}
    docker-compose -f docker-compose.yml up -d
fi
if [ "$1" = "down" ]; then
    echo "Docker-compose down..."
    cd ${SCRIPT_DIR}
    docker-compose -f docker-compose.yml down
fi
if [ "$1" = "remove" ]; then
    echo "Docker rm containers..."
    docker rm do_bkp_db_app
    docker rm do_bkp_db_mysql
fi
if [ "$1" = "exec" ]; then
    echo "Docker Exec..."
    echo ""
    echo "To prepare the testing environment, remember to run:"
    echo "" ;
    echo "sh /var/www/scripts/docker-start-point.sh"
    echo "" ;
    echo "Then try to perform a backup:"
    echo "" ;
    echo "cd /var/www" ;
    echo "sh run.sh run ./src/.env-prod"
    echo "" ;
    docker exec -ti do_bkp_db_app sh ;
fi
if [[ "$1" = "run" || "$1" = "" ]]; then
    echo "Run..."
    php ${APP_DIR}/do_bkp_db.php ${BACKUP_CONFIG_FILENAME}
    echo "Done..."
fi
