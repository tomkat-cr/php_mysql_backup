# !/bin/sh
# docker-start-point.sh
# 2023-05-08 | CR

INSTALL_FLAG_FILE="/do_bkp_db_dependencies_ok.txt"
ERROR_MSG=""
cd /var/www

# Installing required dependencies
if [ ! -f ${INSTALL_FLAG_FILE} ]; then
    if [ "${ERROR_MSG}" = "" ]; then
        if ! apk add --update mysql-client make zip libzip libzip-dev
        then
            ERROR_MSG="ERROR Installing required dependencies"
        fi
    fi
    if [ "${ERROR_MSG}" = "" ]; then
        if ! docker-php-ext-install mysqli zip pdo_mysql
        then
            ERROR_MSG="ERROR enabling PHP extensions"
        fi
    fi
    if [ "${ERROR_MSG}" = "" ]; then
        if ! echo `date` >${INSTALL_FLAG_FILE}
        then
            ERROR_MSG="ERROR cannot write flag file: ${INSTALL_FLAG_FILE}"
        fi
    fi
fi

# Creating testing configurations
if [ "${ERROR_MSG}" = "" ]; then
    if ! cat > src/.env-prod-docker-mysql <<END
# .env-prod-docker-mysql
BACKUP_TYPE=db
MYSQL_SERVER=do_bkp_db_mysql
MYSQL_PORT=3306
MYSQL_DATABASE=test
MYSQL_USER=root
MYSQL_PASSWORD=toor
NAME_SUFFIX=prod
BACKUP_PATH=./do_bkp_db_backup
LOG_FILE_PATH=./do_bkp_db_log
END
    then
        ERROR_MSG="ERROR creating: src/.env-prod-docker-mysql"
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    if ! cat > src/.env-prod-docker-app <<END
# .env-prod-docker-app
BACKUP_TYPE=app
APP_NAME=test-app
APP_ROOT_PATH=.
NAME_SUFFIX=prod
BACKUP_PATH=./do_bkp_db_backup
LOG_FILE_PATH=./do_bkp_db_log
END
    then
        ERROR_MSG="ERROR creating: src/.env-prod-docker-app"
    fi
fi


if [ "${ERROR_MSG}" = "" ]; then
    if ! cat > src/.env-prod-docker-recycle <<END
# .env-prod-docker-recycle
BACKUP_TYPE=recycle
NAME_SUFFIX=prod
BACKUP_PATH=./do_bkp_db_backup
LOG_FILE_PATH=./do_bkp_db_log
# Recycle files older than XX days
MTIME_BKP=5
MTIME_LOG=7
# Exclude files contains .ocrcorp in the filename
EXCLUDE_FILENAMES_WITH=.ocrcorp
# 0=report and files removal, 1=only report, no removals
ONLY_REPORT=0
END
    then
        ERROR_MSG="ERROR creating: src/.env-prod-docker-recycle"
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then

    if ! cat > src/.env-prod <<END
# .env-prod
@test-01-mysql=./src/.env-prod-docker-mysql
@test-02-app=./src/.env-prod-docker-app
@test-03-recycle=./src/.env-prod-docker-recycle
DEBUG=1
END
    then
        ERROR_MSG="ERROR creating: src/.env-prod"
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    if ! cat > web_run/.env-prod-web-run-test <<END
# .env-prod-web-run-test
COMMAND="php%20./src/do_bkp_db.php%20./src/.env-prod-docker-mysql"
NAME=do_bkp_db-docker
NAME_SUFFIX=
LOG_FILE_PATH=./do_bkp_db_log
DEBUG=1
END
    then
        ERROR_MSG="ERROR creating: web_run/.env-prod-web-run-test"
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    # Populating the mysql test schema
    if ! sh scripts/dbseed_run.sh
    then
        ERROR_MSG="ERROR running: scripts/dbseed_run.sh"
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    echo "" ;
    echo "Testing environment is ready to be used." ;
    echo "" ;
    echo "To perform a backup with a pre-defined backup group:"
    echo "" ;
    echo "cd /var/www" ;
    echo "sh run.sh run ./src/.env-prod"
    echo "" ;
else
    echo "" ;
    echo "Testing environment is NOT ready to be used due to this error:" ;
    echo "${ERROR_MSG}" ;
    echo "" ;
fi
