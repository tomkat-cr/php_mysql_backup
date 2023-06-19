#!/bin/sh
# backup_local_zip.sh
# 2021-05-01 | CR

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

if [ "${LOCAL_REM_BKP_DIR}" = "" ]; then
    ERROR_MSG="ERROR: this variable must have a value: LOCAL_REM_BKP_DIR. For example: /home/pi/cwp_remote"
fi

if [ "${ERROR}" = "" ]; then
	bkp_app_dir_source="${LOCAL_REM_BKP_DIR}/daily";#
	bkp_db_dir_source="${LOCAL_REM_BKP_DIR}/mysql/daily";#
	bkp_app_dir_dest="${LOCAL_BACKUP_DIR}/app";#
	bkp_db_dir_dest="${LOCAL_BACKUP_DIR}/db";#
	log_file_dir="${LOG_FILE_DIR}/bkp_replica";#
	#
	date_time_part="`date +%Y-%m-%d`_`date +%H-%M`";#
	log_file_name="$log_file_dir/bkp-loc-rem-zip-$1-$date_time_part.log";#
	#
	secs_to_human() {
		echo "$(( ${1} / 3600 ))h $(( (${1} / 60) % 60 ))m $(( ${1} % 60 ))s"
	}
	do_zip() {
		source_dir="${1}";
		filename=$(basename -- "${source_dir}");
		extension="${filename##*.}";
		filename="${filename%.*}";
		#
		echo "Source Dir: ${source_dir} | Filename: ${filename} | Ext: ${extension} | Zip: ${filename}_${date_time_part}";
		#
		if [ "${extension}" != "sql" ]
		then
			# Application zip
			bkp_file_name="${bkp_app_dir_dest}/${filename}_APP_${date_time_part}";#
			echo "APP zip -r -q ${bkp_file_name}.zip ${source_dir}";#
			echo "APP zip -r -q ${bkp_file_name}.zip ${source_dir}" >> "${log_file_name}";#
			if zip -r -q ${bkp_file_name}.zip ${source_dir} >> "${log_file_name}";#
			then
				echo "App zip completed OF ${filename}";#
				echo "";#
				echo "The backup is in:";#
				echo "${bkp_file_name}.zip";#
				ls -la "${bkp_file_name}.zip";#
			else
				echo "App zip failed at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' ;#
				echo "App zip failed at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' >> "${log_file_name}"
			fi
		else
			# Database zip
			bkp_file_name="${bkp_db_dir_dest}/${filename}_DB_${date_time_part}";#
			echo "DB zip ${bkp_file_name}.zip ${source_dir}";#
			echo "DB zip ${bkp_file_name}.zip ${source_dir}" >> "${log_file_name}";#
			if zip ${bkp_file_name}.zip ${source_dir} >> "${log_file_name}";#
			then
				echo "DB zip completed OF ${filename}";#
				echo "";#
				echo "The backup is in:";#
				echo "${bkp_file_name}.zip";#
				ls -la "${bkp_file_name}.zip";#
			else
				echo "DB zip failed at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' ;#
				echo "DB zip failed at $(date +'%d-%m-%Y %H:%M:%S')"$'\r' >> "${log_file_name}"
			fi
		fi
	}
	#
	time_start=$(date +%s) ;#
	#
	echo Begin: `date +%Y-%m-%d` `date +%H:%M:%S`;#
	echo Begin: `date +%Y-%m-%d` `date +%H:%M:%S` > "$log_file_name";#
	#
	echo "";#
	echo "Remote CWP backups App zip: ${bkp_app_dir_source}";#
	echo "";#
	for FILE in ${bkp_app_dir_source}/*; do do_zip "$FILE"; done ;#
	#
	echo "";#
	echo "Remote CWP backups Db zip: ${bkp_db_dir_source}";#
	echo "";#
	for FILE in ${bkp_db_dir_source}/*; do do_zip "$FILE"; done ;#
	#
	echo "";#
	echo "Elapsed:" ;#
	secs_to_human "$(($(date +%s) - ${time_start}))";
	echo "";#
	echo "The LOG FILE is in: $log_file_name";#
	echo "The LOG FILE is in: $log_file_name" >> "$log_file_name"
fi

echo ""
if [ "${ERROR}" = "" ]; then
    echo "Done!"
else
    echo "${ERROR}"
fi
echo ""
