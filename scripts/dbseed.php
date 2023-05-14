<?php
// DbSeed
// 2023-05-10 | CR
// Populate the mysql test database to have data for do_bkp_bd.php

function read_dotenv($env_filename) {
    $env_params = [];
    if (!file_exists($env_filename)) {
        log_exit('ERROR parameters file not found' . PHP_EOL);
    }
    $handle = fopen($env_filename, "r");
    if ($handle === false) {
        log_exit('ERROR reading parameters' . PHP_EOL);
    }
    while ($varname_value = fscanf($handle, "%s\n")) {
        if ($varname_value[0] == '#' || $varname_value[0] == '') {
            // Comments and empty lines are skipped
            continue;
        }
        list ($varname, $value) = explode('=', implode("", $varname_value));
        $env_params[$varname] = $value;
    }
    fclose($handle);
    return $env_params;
}

function log_msg($msg) {
    echo $msg . PHP_EOL;
}

function log_exit($msg) {
    log_msg($msg);
    exit();
}

class DatabaseConnector {

    private $dbConnection = null;
    private $env_params;

    public function __construct($env_filename)
    {
        $this->env_params = read_dotenv($env_filename);

        $host = $this->env_params['MYSQL_SERVER'];
        $port = $this->env_params['MYSQL_PORT'];
        $db   = $this->env_params['MYSQL_DATABASE'];
        $user = $this->env_params['MYSQL_USER'];
        $pass = $this->env_params['MYSQL_PASSWORD'];

        $connection_string = "mysql:host=$host;port=$port;charset=utf8mb4;dbname=$db";

        print_r(['connection_string'=>$connection_string]);

        try {
            $this->dbConnection = new \PDO(
                $connection_string,
                $user,
                $pass
            );
        } catch (\PDOException $e) {
            log_exit('ERROR opening database: ' . $e->getMessage() . PHP_EOL);
        }
    }

    public function getConnection()
    {
        return $this->dbConnection;
    }
}

class dbSeed {

    private $env_filename;
    private $sql_filename;
    private $env_params;

    public function __construct($env_filename, $sql_filename)
    {
        $this->env_filename = $env_filename;
        $this->sql_filename = $sql_filename;

        $this->env_params = read_dotenv($env_filename);
    
        print_r([
            'env_filename' => $this->env_filename,
            'sql_filename' => $this->sql_filename,
        ]);
    }

    function run() {
        $dbConnection = (new DatabaseConnector($this->env_filename))->getConnection();
        if ($this->sql_filename === '') {
            $statement = $this->default_sql_statement();
        }
        // else {
        //     $statement = $this->get_sql_statement_from_file($this->sql_filename);
        // }

        // Truncate
        try {
            $sql = 
                "SELECT CONCAT('DROP TABLE ',table_schema,'.',TABLE_NAME, ';') " .
                "FROM INFORMATION_SCHEMA.TABLES " .
                "WHERE table_schema = '{$this->env_params['MYSQL_DATABASE']}';";
            log_msg(PHP_EOL . $sql . PHP_EOL);
            $query_result = $dbConnection->query($sql);
            $query_result_string = '';
            foreach ($query_result as $row) {
                log_msg($row[0]);
                $query_result_string .= $row[0] . PHP_EOL;
            }
            log_msg('');
            if (!empty($query_result_string)) {
                $dbConnection->exec($query_result_string);
                log_msg('DROP ALL TABLES: Success!');
            } else {
                log_msg('Nothing to DROP...');
            }
            log_msg('');
        } catch (\PDOException $e) {
            log_exit('PDOException running TRUNCATE: ' . $e->getMessage());
        }
 
        // Restore
        if ($this->sql_filename === '') {
            try {
                log_msg('Processing SQL script...');
                $dbConnection->exec($statement);
                log_msg('SQL script: Success!');
            } catch (\PDOException $e) {
                log_exit('PDOException running the SQL script: ' . $e->getMessage());
            }    
        } else {
            $this->cli_restore($this->sql_filename);
        }
    }

    function cli_restore($filename) {
        if (!file_exists($filename)) {
            log_exit('ERROR SQL file does not exist: ' . $filename);
        }
        $host = $this->env_params['MYSQL_SERVER'];
        $port = $this->env_params['MYSQL_PORT'];
        $db   = $this->env_params['MYSQL_DATABASE'];
        $user = $this->env_params['MYSQL_USER'];
        $pass = $this->env_params['MYSQL_PASSWORD'];

        $mysql_options = 
            "-h{$host}" .
            " --port {$port}" .
            " -u{$user} " .
            "-p\"{$pass}\""; 

        $cmd = [
            'mysql',
            $mysql_options,
            $db,
            '<' . escapeshellarg($filename)
        ];

        log_msg(
            'Command: ' . 
            str_replace(
                "-p\"{$pass}\"",
                "-p\"*******\"",
                implode(' ', $cmd)
            )
        );
        $result_code = 0;
        $output_array = [];

        log_msg('MySQL restore in progress...');
        $output = exec(implode(" ", $cmd), $output_array, $result_code);
        log_msg('Result:');
        log_msg($output);
        print_r($output_array);
        if ($output === false || $result_code !== 0) {
            log_msg('ERROR in MySQL restore.');
            return false;
        }
        log_msg('MySQL restore: Success!');
        return true;
    }

    function get_sql_statement_from_file($filename) {
        if (!file_exists($filename)) {
            log_exit('ERROR SQL file does not exist: ' . $filename);
        }
        try {
            $handle = fopen($filename, "r");
            $contents = fread($handle, filesize($filename));
            fclose($handle);
        } catch (\Error $e) {
            log_exit('ERROR opening SQL file: ' . $e->getMessage());
        } catch (\Exception $e) {
            log_exit('EXCEPTION opening SQL file: ' . $e->getMessage());
        }    
        return $contents;
    }

    function default_sql_statement() {
        $statement = <<<EOS
            CREATE TABLE IF NOT EXISTS person (
                id INT NOT NULL AUTO_INCREMENT,
                firstname VARCHAR(100) NOT NULL,
                lastname VARCHAR(100) NOT NULL,
                firstparent_id INT DEFAULT NULL,
                secondparent_id INT DEFAULT NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (firstparent_id)
                    REFERENCES person(id)
                    ON DELETE SET NULL,
                FOREIGN KEY (secondparent_id)
                    REFERENCES person(id)
                    ON DELETE SET NULL
            ) ENGINE=INNODB;
        
            INSERT INTO person
                (id, firstname, lastname, firstparent_id, secondparent_id)
            VALUES
                (1, 'Krasimir', 'Hristozov', null, null),
                (2, 'Maria', 'Hristozova', null, null),
                (3, 'Masha', 'Hristozova', 1, 2),
                (4, 'Jane', 'Smith', null, null),
                (5, 'John', 'Smith', null, null),
                (6, 'Richard', 'Smith', 4, 5),
                (7, 'Donna', 'Smith', 4, 5),
                (8, 'Josh', 'Harrelson', null, null),
                (9, 'Anna', 'Harrelson', 7, 8);
        EOS;
        return $statement;
    }
}

$config_filename = '.env';
$sql_filename = '';
if (count($argv) > 1) {
    $config_filename = $argv[1];
}
if (count($argv) > 2) {
    $sql_filename = $argv[2];
}
$dbseed = new dbSeed($config_filename, $sql_filename);
$dbseed->run();
