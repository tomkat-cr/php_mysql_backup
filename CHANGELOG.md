# CHANGELOG

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/) and [Keep a Changelog](http://keepachangelog.com/).



## Unreleased
---

### New

### Changes

### Fixes

### Breaks


## 0.1.9 (2023-09-08)

### New
local_bkp_report.sh includes the REPORT_LOG_FILES in the report.


## 0.1.9 (2023-06-19)

### New
local_bkp_report.sh includes the cron run applications in the report.

### Fix
Hostname resolution enhanced in local_bkp_report.sh, specially when the report runs under sh.
local_bkp_report.sh must be run under bash to do a effective comma substitution on REPORT_CRON_APPS


## 0.1.8 (2023-06-18)

### New
"local_bkp_report.sh" script created to Report the backup directory content [ls], disk space used by the backups [du], and free disk space [df].

### Change
"crontab.txt" entries use the complete project path "/php_mysql_backup/remote_replication/", so the SCRIPTS_DIR variable can be shared between different projects.
do_bkp_cleaning-pi.sh renamed to local_bkp_cleaning.sh


## 0.1.7 (2023-06-17)

### Fix
Replication cleaner wasn't doing the recycling.


## 0.1.6 (2023-06-03)
---

### New
Remote replication script "remote_replication_lftp.sh" to copy backups via LFTP to a host different than the backup server.
Other replication/sincronization/recycling scripts were added to maintain the host receiving the backups.


## 0.1.5 (2023-05-13)
---

### New
File backup added.
Backup recycling added.
Zipper class that handles directories recursively
Dbseed runs mysql by shell_exec() when a file is specified, so it can restore large .sql files.
"backup_type" added as directory name prefix, so now backup directories display is clearer.

### Changes
"web_cron" app has been renamed to "web_run".
"web_run" new execution method 'include' to run the do_bkp_db as a PHP include, enabling to run in shared hosting environments where shell() is disabled.
Makefile use only run.sh, so run.sh performs all the docker operations.
Error trapping was added to docker-start-point.sh.
do_bkp_db: double quotes changed to single quotes in all parameters entry names.
do_bkp_db: the 2nd CLI parameter is now web=1/0 to handle calls from "web_run" and do some echo() of the backup groups processed.
Class do_bkp_db renamed to BackupUtility.

### Fixes
do_bkp_db: $log_filespec converted to a class property to be reported appropiately in the output log file.
Added double quotes to SQL INSERT values.


## 0.1.4 (2023-05-12)
---

### New
Empty value in main .env file validation.
Main log file.


## 0.1.3 (2023-05-10)
---

### New
Backup directly to a .SQL file without using shell_exec like phpMyAdmin.


## 0.1.2 (2023-05-09)
---

### Changes
do_bkp_db converted to class.

### New
Web_cron app.


## 0.1.1 (2023-05-08)
---

### New
Option groups by specifying variable name with @ prefix to have multiple .env files and backups in one procedure call.
Docker containers to test the app with a local mysql database.

### Fixes
Mkdir nested directory creation wasn't working.


## 0.1.0 (2023-04-02)
---

### New
Convertion from the python python_mysql_backup version (developed on 2023-01-01)
