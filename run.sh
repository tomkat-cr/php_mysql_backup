#!/bin/sh
# run.sh
# 2023-04-02 | CR
#
APP_DIR='src'
COMPOSER_VERSION="2.2.7"
ENV_FILESPEC=""
if [ -f "./.env" ]; then
    ENV_FILESPEC="./.env"
fi
if [ "$ENV_FILESPEC" != "" ]; then
    set -o allexport; source ${ENV_FILESPEC}; set +o allexport ;
fi
if [ "$2" != "" ]; then
    BACKUP_CONFIG_FILENAME="$2"
else
    BACKUP_CONFIG_FILENAME=".env-prod"
fi
# if [[ "$1" != "deactivate" && "$1" != "init" && "$1" != "clean" && "$1" != "test" ]]; then
    # php composer.phar install -o --no-dev
# fi
# if [ "$1" = "init" ]; then
    # curl -sS https://getcomposer.org/installer | php -- --filename=composer.phar --version=${COMPOSER_VERSION}
    # php composer.phar require symfony/dotenv
# fi
if [ "$1" = "clean" ]; then
    echo "Cleaning..."
    rm -rf vendor ;
fi
if [[ "$1" = "run" || "$1" = "" ]]; then
    echo "Run..."
    php ${APP_DIR}/do_bkp_db.php ${BACKUP_CONFIG_FILENAME}
    echo "Done..."
fi
