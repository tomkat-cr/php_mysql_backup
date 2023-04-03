<?php
// src/do_bkp_db.php
// 2023-01-01 | CR

// require_once '../vendor/autoload.php';
// use Symfony\Component\Dotenv\Dotenv;

$env_params = [];

function read_dotenv($env_filename) {
    global $env_params;
    $handle = fopen($env_filename, "r");
    while ($varname_value = fscanf($handle, "%s\n")) {
        if ($varname_value[0] == '#') {
            continue;
        }
        list ($varname, $value) = explode('=', implode("", $varname_value));
        $env_params[$varname] = $value;
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

function get_env($varname) {
    global $env_params;
    return (isset($env_params[$varname]) ? $env_params[$varname] : null);
}

function read_params()
{
    return [
        "mysql_user" => get_env('MYSQL_USER') ?: '',
        "mysql_password" => get_env('MYSQL_PASSWORD') ?: '',
        "mysql_port" => get_env('MYSQL_PORT') ?: '',
        "mysql_server" => get_env('MYSQL_SERVER'),
        "mysql_database" => get_env('MYSQL_DATABASE'),
        "backup_path" => get_env('BACKUP_PATH'),
        "name_suffix" => get_env('NAME_SUFFIX') ?: '',
        "log_file_path" => get_env('LOG_FILE_PATH')
    ];
}

function verify_dir($f, $output_path) {
    $error = false;
    if (!file_exists($output_path)) {
        try {
            mkdir($output_path);
        } catch (Exception $e) {
            $error = log_msg(
                $f,
                "ERROR: Output directory could not be create:" .
                PHP_EOL . $e->getMessage()
            );
        }
    } elseif (!is_dir($output_path)) {
        $error = log_msg(
            $f,
            "ERROR: Output directory exists but is not a directory"
        );
    }
    return $error;
}

function perform_backup($f)
{
    $par = read_params();

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
    $error = verify_dir($f, $output_path);
    if ($error) {
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
    echo implode(" ", $cmd) . PHP_EOL;
    $result_code = null;
    $cmd_output = system(implode(" ", $cmd), $result_code);
    // if (!execute_and_report($f, $cmd)) {
    //     return;
    // }

    if ($result_code == 0) {
        log_msg($f, 'Mysqldump successfully finished at ' . get_formatted_date());
    } else {
        log_msg(
            $f,
            'Mysqldump ERROR at ' . get_formatted_date() .
            ' : Result code: ' . $result_code . 
            ', Error: ' . $cmd_output
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

function load_config($params)
{
    $config_filespec = $params['config_filename'] ?? '';
    if ($config_filespec && !file_exists($config_filespec)) {
        echo "ERROR: specified config file {$config_filespec} doesn't exist" . PHP_EOL;
        return;
    }
    // $dotenv = new Dotenv();
    // $dotenv->load($config_filespec);
    read_dotenv($config_filespec);
}

function main()
{
    $params = get_command_line_args();
    load_config($params);
    $par = read_params();

    if (!$par["mysql_database"]) {
        echo "ERROR: Database name must be specified" . PHP_EOL;
        return;
    }

    $log_filespec = get_filespec(
        $par["mysql_database"], $par["name_suffix"], 'log', $par["log_file_path"]
    );

    $error = verify_dir(false, $par["log_file_path"]);
    if ($error) {
        return;
    }

    $f = fopen($log_filespec, 'w');
    perform_backup($f);
    log_msg($f, "The Log file is in: {$log_filespec}");
    log_msg($f, "Backup Completed at " . get_formatted_date());
    fclose($f);
}

main();
?>