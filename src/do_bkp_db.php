<?php
// src/do_bkp_db.php
// 2023-01-01 | CR

// require_once '../vendor/autoload.php';
// use Symfony\Component\Dotenv\Dotenv;

$env_params = [];

function read_dotenv($env_filename, $config_name='main') {
    global $env_params;
    $env_params[$config_name] = [];
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
            $env_params[$varname] = [];
            // IMPORTANT: the varname will be the options group, the value will be the 'filename',
            // and it must have the .env* file fullpath.
            // Example: @mysql_db_website1=/home/website1/do_bkp_db/.env-prod-website1
            $env_params[$varname]['filename'] = $value;
        } else {
            // Normal option
            $env_params[$config_name][$varname] = $value;
        }
    }
    fclose($handle);
}

function get_command_line_args()
{
    global $argv;
    $params = [];
    $params['config_filename'] = '.env';
    if (count($argv) > 1) {
        $params['config_filename'] = $argv[1];
    }
    return $params;
}

function get_formatted_date($to_file_format = false)
{
    $date_format = $to_file_format ? "Ymd_His" : "Y-m-d H:i:s";
    return date($date_format);
}

function get_filespec($database_name, $name_suffix, $file_extension, $directory)
{
    $filename = "bkp-db-{$database_name}" .
        ($name_suffix ? "-{$name_suffix}" : "") .
        "-" . get_formatted_date(true) .
        ".{$file_extension}";
    return $directory . DIRECTORY_SEPARATOR . $filename;
}

function log_msg($f, $msg, $return_value = true, $interline = true)
{
    if ($interline) {
        echo PHP_EOL;
        if ($f) {
            fwrite($f, PHP_EOL);
        }
    }
    echo $msg . PHP_EOL;
    if ($f) {
        fwrite($f, $msg . PHP_EOL);
    }
    return $return_value;
}

function execute_and_report($f, $cmd)
{
    $success = false;
    echo implode(" ", $cmd) . PHP_EOL;
    try {
        $output = shell_exec(implode(" ", $cmd));
        log_msg($f, $output);
        $success = true;
    } catch (Exception $e) {
        $success = log_msg($f, "ERROR: on command execution... " . $e->getMessage(), false);
    }
    return $success;
}

function get_env($varname, $config_name) {
    global $env_params;
    if(isset($env_params[$config_name][$varname])) {
        return $env_params[$config_name][$varname];
    }
    return null;
}

function read_params($config_name)
{
    return [
        "mysql_user" => get_env('MYSQL_USER', $config_name) ?: '',
        "mysql_password" => get_env('MYSQL_PASSWORD', $config_name) ?: '',
        "mysql_port" => get_env('MYSQL_PORT', $config_name) ?: '',
        "mysql_server" => get_env('MYSQL_SERVER', $config_name),
        "mysql_database" => get_env('MYSQL_DATABASE', $config_name),
        "backup_path" => get_env('BACKUP_PATH', $config_name),
        "name_suffix" => get_env('NAME_SUFFIX', $config_name) ?: '',
        "log_file_path" => get_env('LOG_FILE_PATH', $config_name)
    ];
}

function verify_create_folder($f, $output_path, $par) {
    $error = false;
    if (!file_exists($output_path)) {
        try {
            log_msg($f, "Creating directory: " . $output_path);
            mkdir($output_path, 0777, true);
        } catch (Exception $e) {
            $error = log_msg(
                $f,
                "ERROR: Output directory could not be created:" .
                PHP_EOL . $e->getMessage()
            );
        }
    }
    if (!is_dir($output_path)) {
        $error = log_msg(
            $f,
            "ERROR: Output directory exists but is not a directory"
        );
    }
    return $error;
}

function perform_backup($f, $config_name)
{
    $par = read_params($config_name);

    log_msg(
        $f,
        "Database Backup Started | DB: {$par['mysql_database']} " .
        "| Name Suffix: {$par['name_suffix']} | " . get_formatted_date()
    );

    $error = false;
    if ($par["mysql_user"] && !$par["mysql_password"]) {
        $error = log_msg($f, "ERROR: Password must be specified when user is not empty");
    }
    if (!$par["mysql_server"]) {
        $error = log_msg($f, "ERROR: Server name must be specified");
    }
    if (!$par["backup_path"]) {
        $error = log_msg($f, "ERROR: Backup directory must be specified");
    }
    if ($error) {
        return;
    }

    $output_path = $par["backup_path"] . DIRECTORY_SEPARATOR . $par["mysql_database"];
    $error = verify_create_folder($f, $output_path, $par);
    if ($error !== false) {
        return;
    }

    $dump_filespec = get_filespec(
        $par["mysql_database"], $par["name_suffix"], 'sql', $output_path
    );
    $zip_filespec = get_filespec(
        $par["mysql_database"], $par["name_suffix"], 'zip', $output_path
    );

    log_msg($f, "Creating Backup: {$dump_filespec}");

    $mysql_options = '';
    if ($par["mysql_user"]) {
        $mysql_options = "-h{$par['mysql_server']}" .
            " --port {$par['mysql_port']}" .
            " -u{$par['mysql_user']} -p\"{$par['mysql_password']}\""; // +
        // ' --protocol tcp ';
    }

    $cmd = [
        'mysqldump',
        $mysql_options,
        $par['mysql_database'],
        '>' . escapeshellarg($dump_filespec)
    ];
    $command_ouput = str_replace(
        $par['mysql_password'], '****', implode(" ", $cmd)
    );
    echo $command_ouput . PHP_EOL;
    $result_code = null;
    $cmd_output = system(implode(" ", $cmd), $result_code);

    if ($result_code == 0) {
        log_msg($f, 'Mysqldump successfully finished at ' . get_formatted_date());
    } else {
        log_msg(
            $f,
            'Mysqldump ERROR at ' . get_formatted_date() .
            ' : Result code: ' . $result_code . 
            ', Output: ' . $cmd_output
        );
        return;
    }

    log_msg($f, 'Zipping File:');    
    $cmd = [
        'zip',
        escapeshellarg($zip_filespec),
        escapeshellarg($dump_filespec)
    ];
    if (!execute_and_report($f, $cmd)) {
        return;
    }

    log_msg(
        $f,
        'Zip of mysqldump successfully finished at ' . get_formatted_date()
    );
    log_msg($f, "Deleting Dump File: {$dump_filespec}");

    $cmd = [
        'rm',
        escapeshellarg($dump_filespec)
    ];
    if (!execute_and_report($f, $cmd)) {
        return false;
    }

    log_msg($f, 'Backup Completed');
    log_msg($f, 'The backup is in:');
    $cmd = [
        'ls -la',
        escapeshellarg($zip_filespec)
    ];
    return execute_and_report($f, $cmd);
}

function load_config($params, $config_name='main')
{
    $config_filespec = $params['config_filename'] ?? '';
    if ($config_filespec && !file_exists($config_filespec)) {
        echo "ERROR: specified config file {$config_filespec} doesn't exist" . PHP_EOL;
        return;
    }
    // $dotenv = new Dotenv();
    // $dotenv->load($config_filespec);
    read_dotenv($config_filespec, $config_name);
}

function main()
{
    global $env_params;
    $params = get_command_line_args();
    // Read the main config file
    load_config($params);

    foreach ($env_params as $config_name => $config_options) {
        // For each option group, a backup will be made. There'll be at least the 'main' group.
        if ($config_name != 'main') {
            load_config(['config_filename' => $config_options['filename']], $config_name);
        }
        // Get environment variable values for this option group
        $par = read_params($config_name);
        if (!$par["mysql_database"]) {
            echo "ERROR: Database name must be specified" . PHP_EOL;
            return;
        }
        // Build the log file fullpath
        $log_filespec = get_filespec(
            $par["mysql_database"], $par["name_suffix"], 'log', $par["log_file_path"]
        );
        // Try to create directories if not exist
        $error = verify_create_folder(false, $par["log_file_path"], $par);
        if ($error !== false) {
            return;
        }
        // It will open a log file for each option group
        $f = fopen($log_filespec, 'w');
        // Perform backup
        perform_backup($f, $config_name);
        // Close log file
        log_msg($f, "The Log file is in: {$log_filespec}");
        log_msg($f, "Process Completed at " . get_formatted_date());
        fclose($f);
    }
}

main();
?>