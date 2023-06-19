#!/bin/sh
# remote_replication_lftp.sh
# 2023-06-03 | CR
#

ERROR_MSG=""

cd "`dirname "$0"`" ;
SCRIPTS_DIR="`pwd`" ;
if [ ! -f "${SCRIPTS_DIR}/.env" ]; then
    ERROR_MSG="ERROR: could not find ${SCRIPTS_DIR}/.env"
fi
if [ "${ERROR_MSG}" = "" ]; then
    echo "Processing ${SCRIPTS_DIR}/.env file..."
    set -o allexport;
    if ! . "${SCRIPTS_DIR}/.env"
    then
        ERROR_MSG="ERROR: could not process .env file."
    fi
    set +o allexport ;
fi
if [ "${ERROR_MSG}" = "" ]; then
    if [ "${FTP_HOST}" = "" ]; then
        ERROR_MSG="ERROR: FTP_HOST variable is not set. Could not process .env file."
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    if ! lftp -v >/dev/null
    then
        echo "Installing lftp..."
        if ! apt -y install lftp
        then
            ERROR_MSG="ERROR: could not install lftp."
        fi
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    if [ ! -f ~/.lftp/rc ]; then
        mkdir -p ~/.lftp
        if ! echo "set ssl:verify-certificate no" >> ~/.lftp/rc
        then
            ERROR_MSG="ERROR: creating the '~/.lftp/rc' file."
        fi
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    if [ ! -d ${LOCAL_BACKUP_DIR} ]; then
        if ! mkdir -p "${LOCAL_BACKUP_DIR}"
        then
            ERROR_MSG="ERROR: creating the directory: ${LOCAL_BACKUP_DIR}"
        fi
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    if ! lftp ftp://"${FTP_USER}":"${FTP_PASS}"@${FTP_HOST} -e "set ftp:ssl-allow no; mirror --parallel=2 --only-newer ${FTP_SOURCE_DIR} ${LOCAL_BACKUP_DIR} ; quit"
    then
        ERROR_MSG="ERROR: executing ftp://\"${FTP_USER}\":\"****\"@${FTP_HOST} -e \"set ftp:ssl-allow no; mirror --parallel=2 --only-newer ${FTP_SOURCE_DIR} ${LOCAL_BACKUP_DIR} ; quit\""
    fi
fi

echo ""
if [ "${ERROR_MSG}" = "" ]; then
    echo "Done!"
else
    echo "${ERROR_MSG}"
fi
echo ""
