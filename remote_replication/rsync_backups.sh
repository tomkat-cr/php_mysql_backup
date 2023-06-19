#!/bin/sh
#
# rsync_backups.sh
#
# Performs backup replication with rsync + ssh from a remote server to local.
#
# Password-less rsync connection, FTP transfer the private key from the remote server
# to the local directory: ~/Downloads
#
# Then:
# mv ~/Downloads/id_rsa_key.pem /etc/ssl/remote_server/id_rsa_key.pem
#
# sudo mkdir -p ${LOCAL_BACKUP_DIR}
# sudo mkdir -p ${LOG_FILE_DIR}/bkp_replica
# sudo chmod -R 775 ${LOCAL_BACKUP_DIR}/..
# sudo chown -R pi:pi ${LOCAL_BACKUP_DIR}/..
#
# sh ${DO_SH_SCRIPTS_DIR}/rsync_backups.sh
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

if [ "${ERROR}" = "" ]; then
    #######################################################
    # GENERAL PARAMETERS
    #######################################################
    if [ "${PEM_KEY}" = "" ]; then
        sync_dir_source="\"${FTP_USER}\":\"${FTP_PASS}\"@${FTP_HOST}:${FTP_SOURCE_DIR}";#
    else
        sync_dir_source="\"${FTP_USER}\"@${FTP_HOST}:${FTP_SOURCE_DIR}";#
    fi
    sync_dir_dest="${LOCAL_BACKUP_DIR}";#
    # Log file dir
    log_file_dir="${LOG_FILE_DIR}";#
    #######################################################
    date_time_part="`date +%Y-%m-%d`_`date +%H-%M`";#
    log_file_name="$log_file_dir/replica_bkp-$date_time_part.log";#
    #######################################################
    echo "Backup Replication started at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' ;#
    echo "Backup Replication started at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' >> "$log_file_name"
    echo "Source: $sync_dir_source | Destination: $sync_dir_dest";#
    echo "Source: $sync_dir_source | Destination: $sync_dir_dest" > "$log_file_name" ;#
    if [ "${PEM_KEY}" = "" ]; then
        if ! rsync -arv $sync_dir_source/* $sync_dir_dest >> "$log_file_name" ;#
        then
            ERROR_MSG="ERROR: executing rsync [1]."
        fi
    else
        if ! rsync -arv -e "ssh -i /etc/ssl/mbi-tk/id_rsa_mediabros_turkey.pem" $sync_dir_source/* $sync_dir_dest >> "$log_file_name" ;#
        then
            ERROR_MSG="ERROR: executing rsync [2]."
        fi
    fi
fi

echo ""
if [ "${ERROR}" = "" ]; then
    echo "Backup Replication successfully finished at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' ;#
    echo "Backup Replication successfully finished at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' >> "$log_file_name"
else
    echo "${ERROR}"
    echo ""
    echo "Backup Replication failed at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' ;#
    echo "Backup Replication failed at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' >> "$log_file_name"
fi
if [ "${log_file_name}" != "" ]; then
    echo "The LOG FILE is in: $log_file_name";#
    echo "The LOG FILE is in: $log_file_name" >> "$log_file_name"
fi
echo ""
