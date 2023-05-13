<?php
// web_run/index.php
// 2023-05-09 | CR
// To run the do_bkp_db from a Browser

class web_run {

    private $env_params = [];
    private $logfile_handler;
    private $global_params;
    private $current_params;

    function __construct($params) {
        $this->global_params = $params;
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
                // Example: @mysql_db_website1=/home/website1/web_run/.env-prod-website1
                $this->env_params[$varname]['filename'] = $value;
            } else {
                // Normal option
                $this->env_params[$config_name][$varname] = $value;
            }
        }
        fclose($handle);
    }

    function get_formatted_date($to_file_format = false) {
        $date_format = $to_file_format ? "Ymd_His" : "Y-m-d H:i:s";
        return date($date_format);
    }

    function get_filespec($name, $name_suffix, $file_extension, $directory) {
        $filename = "web_run-{$name}" .
            ($name_suffix ? "-{$name_suffix}" : "") .
            "-" . $this->get_formatted_date(true) .
            ".{$file_extension}";
        return $directory . DIRECTORY_SEPARATOR . $filename;
    }

    function echo_debug($msg) {
        if (!isset($this->current_params['debug']) || $this->current_params['debug'] == '1') {
            echo $msg . '<BR/>';
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
        $result_code = 0;
        $output_array = [];
        $this->log_msg(
            '[' .
            $this->global_params['execution_method'] .
            '] ' .
            implode(" ", $cmd)
        );
        try {
            switch ($this->global_params['execution_method']) {
                case 'include':
                    // For a configuration like:
                    // COMMAND="php%20./src/do_bkp_db.php%20./src/.env-prod-docker-mysql"
                    // $cmd is:
                    // Array("php", "./src/do_bkp_db.php" "./src/.env-prod-docker-mysql")
                    $argv[0] = $cmd[1]; // command name
                    $argv[1] = $cmd[2]; // .env configuration file
                    $argv[2] = '1'; // "web" parameter, to send a <BR/> at the end of each backup group run
                    if (!file_exists($cmd[1])) {
                        $result_code = 601;
                        $output = 'ERROR: file not found: ' . $cmd[1];
                    } elseif (!file_exists($cmd[2])) {
                        $result_code = 602;
                        $output = 'ERROR: file not found: ' . $cmd[2];
                    } else {
                        // Execute the PHP program incluuding its main file.
                        include $cmd[1];
                    }
                    break;
                case 'system':
                    $output = system(implode(" ", $cmd), $result_code);
                    $output_array = [$output];
                    if ($output === false) {
                        $result_code = 501;
                    }
                    break;
                case 'exec':
                    $output = exec(implode(" ", $cmd), $output_array, $result_code);
                    if ($output === false) {
                        $result_code = 502;
                    }
                    break;
                case 'shell_exec':
                default:
                    $output = shell_exec($cmd);
                    $output_array = [$output];
                    if ($output === false || is_null($output)) {
                        $result_code = 503;
                    }
            }
            $this->log_msg('Execution completed...');
            $this->log_msg(implode(PHP_EOL, $output_array));
            $success = ($result_code == 0);
            if ($success) {
                $this->log_msg('Execution successfully finished at ' . $this->get_formatted_date());
            } else {
                $this->log_msg(
                    'Execution ERROR at ' . $this->get_formatted_date() .
                    ' : Result code: ' . $result_code . 
                    ', Output: ' . $output
                );
                return;
            }
        } catch (\Exception $e) {
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
            "command" => $this->get_env('COMMAND', $config_name) ?: '',
            "name" => $this->get_env('NAME', $config_name) ?: '',
            "name_suffix" => $this->get_env('NAME_SUFFIX', $config_name) ?: $config_name,
            "log_file_path" => $this->get_env('LOG_FILE_PATH', $config_name) ?: './',
            "debug" => $this->get_env('DEBUG', $config_name) ?: '0',
        ];
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

    function explode_command($cmd_string) {
        return explode(' ', urldecode(trim($cmd_string, '"')));
    }

    function process_command() {
        $this->log_msg(
            "Command processing started | " . $this->get_formatted_date()
        );
        $cmd = $this->explode_command($this->current_params['command']);
        $this->log_msg("Executing: {$this->current_params['name']}");
        if (!$this->execute_and_report($cmd)) {
            return;
        }
        $this->log_msg('Command processing completed');
    }

    function load_config($params, $config_name='main') {
        $config_filespec = $params['config_filename'] ?? '';
        if ($config_filespec && !file_exists($config_filespec)) {
            $this->echo_debug("ERROR: specified config file '{$config_filespec}' doesn't exist");
            return;
        }
        $this->read_dotenv($config_filespec, $config_name);
    }

    function main() {
        echo 'Process ' . $this->global_params['process_name'] .
             ' started at ' . $this->get_formatted_date() .
             '<BR/><BR/>';
        // Read the main config file
        $this->load_config($this->global_params);

        foreach ($this->env_params as $config_name => $config_options) {
            // For each option group, a backup will be made. There'll be at least the 'main' group.
            if ($config_name != 'main') {
                $this->load_config(['config_filename' => $config_options['filename']], $config_name);
            } else {
                if (empty($config_options)) {
                    continue;
                }
            }
            // Get environment variable values for this option group
            $this->current_params = $this->read_params($config_name);
            $this->echo_debug(PHP_EOL . '>>> Command Group Name: ' . $config_name);

            if (!$this->current_params["command"]) {
                $this->echo_debug("ERROR: command must be specified");
                continue;
            }
            // Build the log file fullpath
            $log_filespec = $this->get_filespec(
                $this->current_params["name"],
                $this->current_params["name_suffix"],
                'log',
                $this->current_params["log_file_path"]
            );
            // Try to create directories if not exist
            $error = $this->verify_create_folder($this->current_params["log_file_path"]);
            if ($error !== false) {
                continue;
            }
            // It will open a log file for each option group
            $this->logfile_handler = fopen($log_filespec, 'w');
            // Perform backup
            $this->process_command();
            // Close log file
            $this->log_msg("The Log file is in: {$log_filespec}");
            $this->log_msg("Process Completed at " . $this->get_formatted_date());
            fclose($this->logfile_handler);
        }
        echo '<BR/>Process ' . $this->global_params['process_name'] .
             ' completed at ' . $this->get_formatted_date() .
             '<BR/>';
    }
}

$execution_method = 'include';
// $execution_method = 'exec';
// $execution_method = 'shell_exec';
// $execution_method = 'system';
if (isset($_GET['em'])) {
    $execution_method = $_GET['em'];
}
$params = [
    'process_name' => 'MBI-WR',
    'config_filename' => __DIR__.'/.env-prod-web-run',
    'execution_method' => $execution_method,
];
$web_run = new web_run($params);
$web_run->main();
?>
