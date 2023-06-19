#!/bin/sh
# Script: /home/pi/menus/do_rm.sh
# CR | 2021-05-04
# el find -exec de do_bkp_cleaning.sh no puede usar funciones internas, asi que se hizo este "do_rm.sh"
# para poder reportar las eliminaciones que se hacen
file_to_delete="${1}";#
log_file_name="${2}";#
echo "Deleting file ${file_to_delete}";#
echo "Deleting file ${file_to_delete}">>${log_file_name};#
rm ${file_to_delete};#

