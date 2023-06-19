#!/bin/sh
#
# local_bkp_report.sh
# Report the backup directory content [ls], disk space used by the backups [du], and free disk space [df]. 
# 2023-06-18 | CR
#
ERROR_MSG=""

REPORT_DIR="/tmp"

cd "`dirname "$0"`"
SCRIPTS_DIR="`pwd`"

DATE_TIME_PART="`date +%Y-%m-%d`_`date +%H-%M`";
REPORT_FILE="${REPORT_DIR}/local_bkp_report_${DATE_TIME_PART}.txt"

if [ ! -f "${SCRIPTS_DIR}/.env" ]; then
    ERROR_MSG="ERROR: could not find ${SCRIPTS_DIR}/.env"
fi
if [ "${ERROR_MSG}" = "" ]; then
    # echo ""
    # echo "Processing ${SCRIPTS_DIR}/.env file..."
    set -o allexport;
    if ! . "${SCRIPTS_DIR}/.env"
    then
        ERROR_MSG="ERROR: could not process .env file."
    fi
    set +o allexport ;
fi

if [ "${ERROR_MSG}" = "" ]; then
    if ! echo "Backup report for: ${HOSTNAME}" >${REPORT_FILE}
    then
        ERROR_MSG="ERROR: could not create report file: ${REPORT_FILE}"
    else
        echo "date: ${DATE_TIME_PART}" >>${REPORT_FILE}
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    echo "" >>${REPORT_FILE}
    echo "*** ls -lahR ${LOCAL_BACKUP_DIR}/*" >>${REPORT_FILE}
    echo "" >>${REPORT_FILE}
    if ! ls -lahR ${LOCAL_BACKUP_DIR}/* >>${REPORT_FILE}
    then
        ERROR_MSG="ERROR: could not run 'ls' over ${LOCAL_BACKUP_DIR}"
    fi
fi
if [ "${ERROR_MSG}" = "" ]; then
    echo "" >>${REPORT_FILE}
    echo "*** du -h ${LOCAL_BACKUP_DIR}" >>${REPORT_FILE}
    echo "" >>${REPORT_FILE}
    if ! du -h ${LOCAL_BACKUP_DIR} >>${REPORT_FILE}
    then
        ERROR_MSG="ERROR: could not run 'du' over ${LOCAL_BACKUP_DIR}"
    fi
fi
if [ "${ERROR_MSG}" = "" ]; then
    echo "" >>${REPORT_FILE}
    echo "*** df -h" >>${REPORT_FILE}
    echo "" >>${REPORT_FILE}
    if ! df -h >>${REPORT_FILE}
    then
        ERROR_MSG="ERROR: could not run 'df'."
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    if [ "${EMAIL_APP}" != "" ]; then
        if [ "${EMAIL_TO}" != "" ]; then
            if ! ${EMAIL_APP} -t ${EMAIL_TO} -a ${REPORT_FILE} -s "Local Backup Report for: ${HOSTNAME} | ${DATE_TIME_PART}"
            then
                ERROR_MSG="ERROR: could not run: ${EMAIL_APP} -t ${EMAIL_TO} -a ${REPORT_FILE} -s \"Local Backup Report for: ${HOSTNAME} | ${DATE_TIME_PART}\""
            fi
        fi
    fi
fi

if [ "${ERROR_MSG}" = "" ]; then
    echo ""
    echo "Report file is in: ${REPORT_FILE}"
    echo ""
fi

echo ""
if [ "${ERROR_MSG}" = "" ]; then
    echo "Done!"
else
    echo "${ERROR_MSG}"
fi
echo ""
