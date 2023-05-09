<?php
// src/do_bkp_db.php
// 2023-01-01 | CR

// require_once '../vendor/autoload.php';
// use Symfony\Component\Dotenv\Dotenv;

class do_bkp_db {

    private $env_params = [];
    private $logfile_handler;
    private $argv;
    private $current_params;

    function __construct($cli_argv) {
        $this->argv = $cli_argv;
        // print_r($this->argv);
    }

    function read_dotenv($env_filename, $config_name='main') {
        $this->env_params[$config_name] = [];
        $handle = fopen($env_filename, "r");
        while ($varname_value = fscanf($handle, "%s\n")) {
            if ($varname_value[0] == '#' || $varname_value[0] == '') {
                // Comments are skipped
                continue;
            }
            list ($varname, $value) = explode('=', implode("", $varname_value));
            if (substr($varname, 0, 1) == '@') {
                // It's not an option (environment variable) value, it's a sub-options file,
                // so the backup can be run for more than one MySQL schema
                $this->env_params[$varname] = [];
                // IMPORTANT: the varname will be the options group, the value will be the 'filename',
                // and it must have the .env* file fullpath.
                // Example: @mysql_db_website1=/home/website1/do_bkp_db/.env-prod-website1
                $this->env_params[$varname]['filename'] = $value;
            } else {
                // Normal option
                $this->env_params[$config_name][$varname] = $value;
            }
        }
        fclose($handle);
    }

    function get_command_line_args() {
        $params = [];
        $params['config_filename'] = '.env';
        if (count($this->argv) > 1) {
            $params['config_filename'] = $this->argv[1];
        }
        return $params;
    }

    function get_formatted_date($to_file_format = false) {
        $date_format = $to_file_format ? "Ymd_His" : "Y-m-d H:i:s";
        return date($date_format);
    }

    function get_filespec($database_name, $name_suffix, $file_extension, $directory) {
        $filename = "bkp-db-{$database_name}" .
            ($name_suffix ? "-{$name_suffix}" : "") .
            "-" . $this->get_formatted_date(true) .
            ".{$file_extension}";
        return $directory . DIRECTORY_SEPARATOR . $filename;
    }

    function echo_debug($msg) {
        if (!isset($this->current_params['debug']) || $this->current_params['debug'] == '1') {
            echo $msg . PHP_EOL;
        }
    }

    function log_msg($msg, $return_value = true, $interline = true) {
        if ($interline) {
            $this->echo_debug('');
            if ($this->logfile_handler) {
                fwrite($this->logfile_handler, PHP_EOL);
            }
        }
        $this->echo_debug($msg);
        if ($this->logfile_handler) {
            fwrite($this->logfile_handler, $msg . PHP_EOL);
        }
        return $return_value;
    }

    function execute_and_report($cmd) {
        $success = false;
        $this->echo_debug(implode(" ", $cmd));
        try {
            $output = shell_exec(implode(" ", $cmd));
            $this->log_msg($output);
            $success = true;
        } catch (Exception $e) {
            $success = $this->log_msg("ERROR: on command execution... " . $e->getMessage(), false);
        }
        return $success;
    }

    function get_env($varname, $config_name) {
        if(isset($this->env_params[$config_name][$varname])) {
            return $this->env_params[$config_name][$varname];
        }
        return null;
    }

    function read_params($config_name) {
        $response = [
            "mysql_user" => $this->get_env('MYSQL_USER', $config_name) ?: '',
            "mysql_password" => $this->get_env('MYSQL_PASSWORD', $config_name) ?: '',
            "mysql_port" => $this->get_env('MYSQL_PORT', $config_name) ?: '',
            "mysql_server" => $this->get_env('MYSQL_SERVER', $config_name),
            "mysql_database" => $this->get_env('MYSQL_DATABASE', $config_name),
            "backup_path" => $this->get_env('BACKUP_PATH', $config_name),
            "name_suffix" => $this->get_env('NAME_SUFFIX', $config_name) ?: '',
            "log_file_path" => $this->get_env('LOG_FILE_PATH', $config_name),
            "debug" => $this->get_env('DEBUG', $config_name) ?: '0',
        ];
        // print_r($response);
        return $response;
    }

    function verify_create_folder($output_path) {
        $error = false;
        if (!file_exists($output_path)) {
            try {
                $this->log_msg("Creating directory: " . $output_path);
                mkdir($output_path, 0777, true);
            } catch (Exception $e) {
                $error = $this->log_msg(
                    "ERROR: Output directory could not be created:" .
                    PHP_EOL . $e->getMessage()
                );
            }
        }
        if (!is_dir($output_path)) {
            $error = $this->log_msg(
                "ERROR: Output directory exists but is not a directory"
            );
        }
        return $error;
    }

    function perform_backup() {
        $this->log_msg(
            "Database Backup Started | DB: {$this->current_params['mysql_database']} " .
            "| Name Suffix: {$this->current_params['name_suffix']} | " . $this->get_formatted_date()
        );

        $error = false;
        if ($this->current_params["mysql_user"] && !$this->current_params["mysql_password"]) {
            $error = $this->log_msg("ERROR: Password must be specified when user is not empty");
        }
        if (!$this->current_params["mysql_server"]) {
            $error = $this->log_msg("ERROR: Server name must be specified");
        }
        if (!$this->current_params["backup_path"]) {
            $error = $this->log_msg("ERROR: Backup directory must be specified");
        }
        if ($error) {
            return;
        }

        $output_path = $this->current_params["backup_path"] . DIRECTORY_SEPARATOR . $this->current_params["mysql_database"];
        $error = $this->verify_create_folder($output_path);
        if ($error !== false) {
            return;
        }

        $dump_filespec = $this->get_filespec(
            $this->current_params["mysql_database"], $this->current_params["name_suffix"], 'sql', $output_path
        );
        $zip_filespec = $this->get_filespec(
            $this->current_params["mysql_database"], $this->current_params["name_suffix"], 'zip', $output_path
        );

        $this->log_msg("Creating Backup: {$dump_filespec}");

        $mysql_options = '';
        if ($this->current_params["mysql_user"]) {
            $mysql_options = "-h{$this->current_params['mysql_server']}" .
                " --port {$this->current_params['mysql_port']}" .
                " -u{$this->current_params['mysql_user']} " .
                "-p\"{$this->current_params['mysql_password']}\""; 
        }

        $cmd = [
            'mysqldump',
            $mysql_options,
            $this->current_params['mysql_database'],
            '>' . escapeshellarg($dump_filespec)
        ];
        $command_ouput = str_replace(
            $this->current_params['mysql_password'], '****', implode(" ", $cmd)
        );
        $this->echo_debug($command_ouput);
        $result_code = null;
        $cmd_output = system(implode(" ", $cmd), $result_code);

        if ($result_code == 0) {
            $this->log_msg('Mysqldump successfully finished at ' . $this->get_formatted_date());
        } else {
            $this->log_msg(
                'Mysqldump ERROR at ' . $this->get_formatted_date() .
                ' : Result code: ' . $result_code . 
                ', Output: ' . $cmd_output
            );
            return;
        }

        $this->log_msg('Zipping File:');    
        $cmd = [
            'zip',
            '-j',   // -j   junk (don't record) directory names
            escapeshellarg($zip_filespec),
            escapeshellarg($dump_filespec)
        ];
        if (!$this->execute_and_report($cmd)) {
            return;
        }

        $this->log_msg(
            'Zip of mysqldump successfully finished at ' . $this->get_formatted_date()
        );
        $this->log_msg("Deleting Dump File: {$dump_filespec}");

        $cmd = [
            'rm',
            escapeshellarg($dump_filespec)
        ];
        if (!$this->execute_and_report($cmd)) {
            return false;
        }

        $this->log_msg('Backup Completed');
        $this->log_msg('The backup is in:');
        $cmd = [
            'ls -la',
            escapeshellarg($zip_filespec)
        ];
        return $this->execute_and_report($cmd);
    }

    function load_config($params, $config_name='main') {
        $config_filespec = $params['config_filename'] ?? '';
        if ($config_filespec && !file_exists($config_filespec)) {
            $this->echo_debug("ERROR: specified config file {$config_filespec} doesn't exist");
            return;
        }
        // $dotenv = new Dotenv();
        // $dotenv->load($config_filespec);
        $this->read_dotenv($config_filespec, $config_name);
    }

    function main() {
        $params = $this->get_command_line_args();
        // Read the main config file
        $this->load_config($params);

        foreach ($this->env_params as $config_name => $config_options) {
            // For each option group, a backup will be made. There'll be at least the 'main' group.
            $this->echo_debug(PHP_EOL . '>>> Backup Group Name: ' . $config_name);
            if ($config_name != 'main') {
                $this->load_config(['config_filename' => $config_options['filename']], $config_name);
            } else {
                if (empty($config_options)) {
                    continue;
                }
            }
            // Get environment variable values for this option group
            $this->current_params = $this->read_params($config_name);
            if (!$this->current_params["mysql_database"]) {
                $this->echo_debug("ERROR: Database name must be specified");
                continue;
            }
            // Build the log file fullpath
            $log_filespec = $this->get_filespec(
                $this->current_params["mysql_database"], $this->current_params["name_suffix"], 'log', $this->current_params["log_file_path"]
            );
            // Try to create directories if not exist
            $error = $this->verify_create_folder($this->current_params["log_file_path"]);
            if ($error !== false) {
                continue;
            }
            // It will open a log file for each option group
            $this->logfile_handler = fopen($log_filespec, 'w');
            // Perform backup
            $this->perform_backup($this->current_params);
            // Close log file
            $this->log_msg("The Log file is in: {$log_filespec}");
            $this->log_msg("Process Completed at " . $this->get_formatted_date());
            fclose($this->logfile_handler);
        }
    }
}

$do_bkp_db = new do_bkp_db($argv);
$do_bkp_db->main();
?>