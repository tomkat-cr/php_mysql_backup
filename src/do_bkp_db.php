<?php
// src/do_bkp_db.php
// 2023-01-01 | CR

// require_once '../vendor/autoload.php';
// use Symfony\Component\Dotenv\Dotenv;

class do_bkp_db {

    private $env_params = [];
    private $logfile_handler;
    private $argv;
    private $global_params;
    private $current_params;

    function __construct($cli_argv, $global_params) {
        $this->argv = $cli_argv;
        $this->global_params = $global_params;
        // print_r($this->argv);
        // print_r($this->global_params);
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

    function get_formatted_date($to_file_format=false) {
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

    function log_msg($msg) {
        $this->echo_debug($msg);
        if ($this->logfile_handler) {
            fwrite($this->logfile_handler, $msg . PHP_EOL);
        }
    }

    function log_error($msg) {
        $this->log_msg($msg);
        echo 'Error... check log file' . PHP_EOL;
    }

    function execute_and_report($cmd, $execution_method=null) {
        if (is_null($execution_method)) {
            $execution_method = $this->current_params['execution_method'];
        }
        $success = false;
        $result_code = 0;
        $output_array = [];
        $this->log_msg(
            '[' .
            $execution_method .
            '] ' .
            implode(" ", $cmd)
        );
        try {
            switch ($execution_method) {
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
                    // $output = shell_exec($cmd);
                    $output = shell_exec(implode(" ", $cmd));
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
                $this->log_error(
                    'Execution ERROR at ' . $this->get_formatted_date() .
                    ' : Result code: ' . $result_code . 
                    ', Output: ' . $output
                );
                return;
            }
        } catch (\Error $e) {
            $this->log_error("ERROR: on command execution... " . $e->getMessage());
        } catch (\Exception $e) {
            $this->log_error("EXCEPTION: on command execution... " . $e->getMessage());
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
            "mysql_port" => $this->get_env('MYSQL_PORT', $config_name) ?: '3306',
            "mysql_server" => $this->get_env('MYSQL_SERVER', $config_name),
            "mysql_database" => $this->get_env('MYSQL_DATABASE', $config_name),
            "backup_path" => $this->get_env('BACKUP_PATH', $config_name),
            "name_suffix" => $this->get_env('NAME_SUFFIX', $config_name) ?: '',
            "log_file_path" => $this->get_env('LOG_FILE_PATH', $config_name),
            "debug" => $this->get_env('DEBUG', $config_name) ?: '0',
            "execution_method" => $this->get_env('EXECUTION_METHOD', $config_name) ?:
                $this->global_params['default_execution_method'],
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
                $error = true;
                $this->log_error(
                    "ERROR: Output directory could not be created:" .
                    PHP_EOL . $e->getMessage()
                );
            }
        }
        if (!is_dir($output_path)) {
            $error = true;
            $this->log_error(
                "ERROR: Output directory exists but is not a directory"
            );
        }
        return $error;
    }

    function perform_backup_shell_exec($dump_filespec) {
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
        $success = $this->execute_and_report($cmd, 'system');
        return $success;
    }

    function perform_backup_to_sql_file($dump_filespec) {

        // Open the output .sql file
        try {
            $sql_file = fopen($dump_filespec, 'w');
        } catch (\Exception $e) {
            $this->log_error("ERROR: opening output .SQL file: " . $e->getMessage());
            return false;
        }

        // Connect to the database
        try {
            $mysqli = new mysqli(
                $this->current_params['mysql_server'],
                $this->current_params['mysql_user'],
                $this->current_params['mysql_password'],
                $this->current_params['mysql_database'],
                $this->current_params['mysql_port']
            );
        } catch (\Error $e) {
            $this->log_error("ERROR: opening MySQL database connection: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->log_error("EXCEPTION: opening MySQL database connection: " . $e->getMessage());
            return false;
        }

        if ($mysqli->connect_error) {
            $this->log_error("Connection failed: " . $mysqli->connect_error);
            return false;
        }

        // Get all tables in the database
        $tables = [];
        $result = $mysqli->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }

        // Generate SQL schema and data
        foreach ($tables as $table) {
            $sql = "";

            // Get table creation SQL
            $result = $mysqli->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_assoc();
            $sql .= $row['Create Table'] . ';' . PHP_EOL . PHP_EOL;

            // Get table indexes
            $result = $mysqli->query("SHOW INDEXES FROM `$table`");
            while ($row = $result->fetch_assoc()) {
                if ($row['Key_name'] != 'PRIMARY') {
                    $sql .= "ALTER TABLE `$table` ADD INDEX `{$row['Key_name']}` (`{$row['Column_name']}`);" . PHP_EOL;
                }
            }
            fwrite($sql_file, $sql);

            // Get table data
            $result = $mysqli->query("SELECT * FROM `$table`");
            $num_columns = $result->field_count;

            while ($row = $result->fetch_row()) {
                $sql = "INSERT INTO `$table` VALUES (";
                for ($i = 0; $i < $num_columns; $i++) {
                    $sql .= $mysqli->real_escape_string($row[$i]);
                    if ($i < $num_columns - 1) {
                        $sql .= ", ";
                    }
                }
                $sql .= ');' . PHP_EOL;
                fwrite($sql_file, $sql);
            }

            fwrite($sql_file, PHP_EOL);
        }

        // Save SQL schema and data to file
        fclose($sql_file);

        // Close database connection
        $mysqli->close();

        $this->log_msg("Backup completed successfully. Saved as {$dump_filespec}");
        return true;
    }

    function zip_file($zip_filespec, $dump_filespec) {
        switch($this->current_params['execution_method']) {
            case 'sql':
                try {
                    $zip = new ZipArchive();
                } catch (\Error $e) {
                    $this->log_error("ERROR: opening ZipArchive: " . $e->getMessage());
                    return false;
                } catch (\Exception $e) {
                    $this->log_error("EXCEPTION: opening ZipArchive: " . $e->getMessage());
                    return false;
                }
                if ($zip->open($zip_filespec, ZipArchive::CREATE)!==TRUE) {
                    $this->log_error("ERROR: cannot open <$zip_filespec>");
                    return false;
                }
                if (!$zip->addFile($dump_filespec)) {
                    $zip->close();
                    $this->log_error("ERROR: cannot zip .SQL file");
                    return false;
                }
                // echo "numfiles: " . $zip->numFiles . "\n";
                // echo "status:" . $zip->status . "\n";
                $zip->close();
                break;
            default:
                $cmd = [
                    'zip',
                    '-j',   // -j   junk (don't record) directory names
                    escapeshellarg($zip_filespec),
                    escapeshellarg($dump_filespec)
                ];
                if (!$this->execute_and_report($cmd)) {
                    return false;
                }
        }
        return true;
    }

    function validate_backup_parameters() {
        $error = false;
        if ($this->current_params["mysql_user"] && !$this->current_params["mysql_password"]) {
            $error = true;
            $this->log_error("ERROR: Password must be specified when user is not empty");
        }
        if (!$this->current_params["mysql_server"]) {
            $error = true;
            $this->log_error("ERROR: Server name must be specified");
        }
        if (!$this->current_params["backup_path"]) {
            $error = true;
            $this->log_error("ERROR: Backup directory must be specified");
        }
        return $error;
    }

    function human_readable_filesize($bytes, $dec = 2): string {
        $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        if ($factor == 0) $dec = 0;
        return sprintf("%.{$dec}f %s", $bytes / (1024 ** $factor), $size[$factor]);
    }

    function report_backup_location($zip_filespec) {
        $this->log_msg('The backup is in:');

        // List file(s) using shell...
        if ($this->current_params['execution_method'] !== 'sql') {
            $cmd = [
                'ls -la',
                escapeshellarg($zip_filespec)
            ];
            if (!$this->execute_and_report($cmd)) {
                return false;
            }
            return true;
        }

        // List file(s) without shell... ordered by file date/time
        $docs = [];
        foreach (glob($zip_filespec) as $path) {
            $docs[$path] = filectime($path);
        }
        asort($docs); // sort by value, preserving keys
        foreach ($docs as $path => $timestamp) {
            $this->log_msg(
                $path . ' ' .
                '| Date: ' . date("Y-m-d H:i:s ", $timestamp) .
                '| Size: ' . $this->human_readable_filesize(filesize($path))
            );
        }
        return true;
    }

    function perform_backup() {
        $this->log_msg(
            "Database Backup Started | DB: {$this->current_params['mysql_database']} " .
            "| Name Suffix: {$this->current_params['name_suffix']} | " . $this->get_formatted_date()
        );

        $error = $this->validate_backup_parameters();
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

        $this->log_msg("Creating Backup ({$this->current_params['execution_method']}): {$dump_filespec}");

        switch($this->current_params['execution_method']) {
            case 'sql':
                $success = $this->perform_backup_to_sql_file($dump_filespec);
                break;
            default:
                $success = $this->perform_backup_shell_exec($dump_filespec);
        }
        if (!$success) {
            return;
        }

        $this->log_msg('Zipping File:');
        if (!$this->zip_file($zip_filespec, $dump_filespec)) {
            return;
        }
        $this->log_msg(
            'Zip of mysqldump successfully finished at ' . $this->get_formatted_date()
        );

        $this->log_msg("Deleting Dump File: {$dump_filespec}");
        if (!unlink($dump_filespec)) {
            $this->log_error("ERROR: cannot remove file: $dump_filespec");
            return false;
        }

        $this->log_msg('Backup Completed');

        $this->report_backup_location($zip_filespec);
        return true;
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

$global_params = [
    'process_name' => 'MBI MySQL Backup Utility',
    'default_execution_method' => 'sql',
    // 'default_execution_method' => 'exec',
    // 'default_execution_method' => 'shell_exec',
    // 'default_execution_method' => 'system',
];

$do_bkp_db = new do_bkp_db($argv, $global_params);
$do_bkp_db->main();
?>