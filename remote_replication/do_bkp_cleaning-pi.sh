#!/bin/sh
#
# do_bkp_cleaning-pi.sh
# Remote backup replication & logs recycling
# 2021-05-01 | CR
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
    # set -o allexport; . ".env"; set +o allexport ;

	#######################################################
	# GENERAL PARAMETERS
	#######################################################
	source_bkp_dir_base="${LOCAL_BACKUP_DIR}";
	par_mtime="${PAR_MTIME}";
	exclude_filenames_with="${EXCLUDE_FILENAMES_WITH}";
	log_file_dir="${LOG_FILE_DIR}";
	do_sh_scripts_dir="${DO_SH_SCRIPTS_DIR}";
	single_bkp_dir="${SINGLE_BKP_DIR}";
	#
	date_time_part="`date +%Y-%m-%d`_`date +%H-%M`";
	log_file_name="$log_file_dir/bkp-recycle-$date_time_part.log";
	#######################################################
	if [ "${single_bkp_dir}" = "" ]; then
		single_bkp_dir="1";
	fi
	sw_only_report="1";
	if [ "$1" = "-deletion" ]
	then
		sw_only_report="0";
	fi
	#
	echo "Backup Cleaning";
	echo "  Source: $source_bkp_dir_base";
	echo "  Only Report=$sw_only_report";
	#
	echo "Backup Cleaning">$log_file_name;
	echo "  Source: $source_bkp_dir_base">>$log_file_name;
	echo "  Only Report: $sw_only_report">>$log_file_name;
	echo "  Recycle files older than: $par_mtime days">>$log_file_name;
	echo "  Dirs to be included:">>$log_file_name;
	if [ "${single_bkp_dir}" = "1" ]; then
		echo "  $source_bkp_dir_base/db/">>$log_file_name;
	else
		echo "  $source_bkp_dir_base/db/">>$log_file_name;
		echo "  $source_bkp_dir_base/app/">>$log_file_name;
	fi
	echo "  $log_file_dir/">>$log_file_name;
	echo "">>$log_file_name;
	echo "Files to be deleted today $date_time_part:">>$log_file_name;
	echo "">>$log_file_name;
	if [ "${single_bkp_dir}" = "1" ]; then
		#
		# Unified backups recycle
		#
		source_bkp_dir="$source_bkp_dir_base";
		# Report files
		find $source_bkp_dir/* -mtime +$par_mtime ! -name *$exclude_filenames_with* -exec ls -la {} \; >>$log_file_name;
		if [ "$sw_only_report" = "0" ]
		then
			# Remove files
			find $source_bkp_dir/* -mtime +$par_mtime ! -name *$exclude_filenames_with* -exec sh ${do_sh_scripts_dir}/do_rm.sh {} ${log_file_name} \; ;
		fi
	else
		#
		# Database backups recycle
		#
		source_bkp_dir="$source_bkp_dir_base/db";
		# Report files
		find $source_bkp_dir/* -mtime +$par_mtime ! -name *$exclude_filenames_with* -exec ls -la {} \; >>$log_file_name;
		if [ "$sw_only_report" = "0" ]
		then
			# Remove files
			find $source_bkp_dir/* -mtime +$par_mtime ! -name *$exclude_filenames_with* -exec sh ${do_sh_scripts_dir}/do_rm.sh {} ${log_file_name} \; ;
		fi
		#
		# Application files backup recycle
		#
		source_bkp_dir="$source_bkp_dir_base/app";
		# Report files
		find $source_bkp_dir/* -mtime +$par_mtime ! -name *$exclude_filenames_with* -exec ls -la {} \; >>$log_file_name;
		if [ "$sw_only_report" = "0" ]
		then
			# Remove files
			find $source_bkp_dir/* -mtime +$par_mtime ! -name *$exclude_filenames_with* -exec sh ${do_sh_scripts_dir}/do_rm.sh {} ${log_file_name} \; ;
		fi
	fi
	#
	# Logs recycle
	#
	source_bkp_dir="$log_file_dir";
	# Report files
	find $source_bkp_dir/* -mtime +$par_mtime ! -name *$exclude_filenames_with* -exec ls -la {} \; >>$log_file_name;
	if [ "$sw_only_report" = "0" ]
	then
		# Remove files
		find $source_bkp_dir/* -mtime +$par_mtime ! -name *$exclude_filenames_with* -exec sh ${do_sh_scripts_dir}/do_rm.sh {} ${log_file_name} \; ;
	fi
	echo "Done! to see results:";
	echo "cat $log_file_name";
	ls -la $log_file_name ;
fi

echo ""
if [ "${ERROR}" = "" ]; then
    echo "Done!"
else
    echo "${ERROR}"
fi
echo ""
