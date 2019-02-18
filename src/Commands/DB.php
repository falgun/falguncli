<?php
namespace Falgun\FalgunCLI\Commands;

use Exception;
use Inflect\Inflect;
use Falgun\Console\AbstractCommand;
use Falgun\Console\Input\Definition\ArgumentDefinition;

class DB extends AbstractCommand
{

    const SIGNATURE = 'db';

    protected $input;
    protected $config;
    protected $mysqli, $db;

    public function configure()
    {
        $this->addArgument(new ArgumentDefinition('table', ArgumentDefinition::VALUE_REQUIRED));
    }

    public function execute(array $input)
    {
        $this->input = $input;
        $this->config = $input['-db'] ?? 'db.config.php';

        $this->export();
    }

    public function connect()
    {
        echo 'Connecting to Database.....' . PHP_EOL . PHP_EOL;
        usleep(500000);

        if (file_exists(CONFIG_DIR . DS . $this->config) !== false) {
            $dbConf = require (CONFIG_DIR . DS . $this->config);
        } else {
            throw new Exception('Error Finding Database configuration file !');
        }

        $this->db = $dbConf['db'];
        $mysqli = new \mysqli($dbConf['host'], $dbConf['user'], $dbConf['password']);

        if (!empty($mysqli->connect_errno)) {
            throw new Exception($mysqli->connect_error);
        }

        if (isset($dbConf['characterSet'])) {
            $mysqli->set_charset($dbConf['characterSet']);
        }

        return $mysqli;
    }

    protected function createDB(\mysqli $mysqli)
    {
        echo 'Checking Database.....' . PHP_EOL . PHP_EOL;
        usleep(500000);

        $mysqli->query('CREATE DATABASE IF NOT EXISTS ' . $this->db);
        $mysqli->select_db($this->db);
    }

    public function import()
    {

        $mysqli = $this->connect();
        $this->createDB($mysqli);

        $filename = ROOT_DIR . DS . 'db' . DS . str_replace(',', '_', ($this->input['-db'] ?? 'database')) . '.sql';
        // Temporary variable, used to store current query
        $templine = '';
        // Read in entire file
        $lines = file($filename);
        // Loop through each line
        foreach ($lines as $line) {
            // Skip it if it's a comment
            if (substr($line, 0, 2) == '--' || $line == '')
                continue;

            // Add this line to the current segment
            $templine .= $line;
            // If it has a semicolon at the end, it's the end of the query
            if (substr(trim($line), -1, 1) == ';') {
                // Perform the query
                mysqli_query($mysqli, $templine) or print('Error performing query \'<strong>' . $templine . '\': ' . mysqli_error($mysqli) . '<br /><br />');
                // Reset temp variable to empty
                $templine = '';
            }
        }
        echo 'Database imported successfully.' . PHP_EOL . PHP_EOL;
    }

    public function export()
    {
        $this->tables = $this->input['table'] ?? 'all';
        $mysqli = $this->connect();
        $mysqli->select_db($this->db);
        $mysqli->query("SET NAMES 'utf8'");
        $backup_name = 'users.sql';

        $queryTables = $mysqli->query('SHOW TABLES');
        while ($row = $queryTables->fetch_row()) {
            $target_tables[] = $row[0];
        }
        if ($this->tables != 'all') {
            $target_tables = array_intersect($target_tables, explode(',', $this->tables));
        }
        foreach ($target_tables as $table) {
            $result = $mysqli->query('SELECT * FROM ' . $table);
            $fields_amount = $result->field_count;
            $rows_num = $mysqli->affected_rows;
            $res = $mysqli->query('SHOW CREATE TABLE ' . $table);
            $TableMLine = $res->fetch_row(); //pr($TableMLine);die;
            $content = (!isset($content) ? "-- Falgun Framework\n-- FalgunCLI DB dump\n-- by Ataur Rahman" : $content) . "\n\nDROP TABLE IF EXISTS `" . $table . "`;\n" . $TableMLine[1] . ";\n\n";

            if (isset($this->input['--structure']) === false) {
                for ($i = 0, $st_counter = 0; $i < $fields_amount; $i++, $st_counter = 0) {
                    while ($row = $result->fetch_row()) { //when started (and every after 100 command cycle):
                        if ($st_counter % 100 == 0 || $st_counter == 0) {
                            $content .= "\nINSERT INTO " . $table . " VALUES";
                        }
                        $content .= "\n(";
                        for ($j = 0; $j < $fields_amount; $j++) {
                            $row[$j] = str_replace("\n", "\\n", addslashes($row[$j]));
                            if (isset($row[$j])) {
                                $content .= '"' . $row[$j] . '"';
                            } else {
                                $content .= '""';
                            }
                            if ($j < ($fields_amount - 1)) {
                                $content .= ',';
                            }
                        }
                        $content .= ")";
                        //every after 100 command cycle [or at last line] ....p.s. but should be inserted 1 cycle eariler
                        if ((($st_counter + 1) % 100 == 0 && $st_counter != 0) || $st_counter + 1 == $rows_num) {
                            $content .= ";";
                        } else {
                            $content .= ",";
                        }
                        $st_counter = $st_counter + 1;
                    }
                } $content .= "\n\n\n";
            }
        }
        /*
          //$backup_name = $backup_name ? $backup_name : $name."___(".date('H-i-s')."_".date('d-m-Y').")__rand".rand(1,11111111).".sql";
          $backup_name = $backup_name ? $backup_name : $name.".sql";
          header('Content-Type: application/octet-stream');
          header("Content-Transfer-Encoding: Binary");
          header("Content-disposition: attachment; filename=\"".$backup_name."\"");
          echo $content; exit;
         */
        $fileName = ($this->tables == 'all') ? 'database.sql' : str_replace(',', '_', $this->tables) . '.sql';
        file_put_contents(ROOT_DIR . '/db/' . $fileName, $content);

        echo 'Database export complete !' . PHP_EOL . PHP_EOL;
    }

    public function sync()
    {
        $this->model = ($this->input['self'] ?? 'users');
        $modelClass = "\\App\\Models\\" . $this->model . 'Model';

        $provider = new \Falgun\DInjector\DInjector();
        $model = $provider->resolve($modelClass, null, []);

        $modelColumns = $model->returnColumns();

        $model->setQuery('SHOW COLUMNS FROM ' . $model->getTable());

        $tableColumns = [];
        $tableColumnList = $model->getRows();
        if (!empty($tableColumnList)) {
            foreach ($tableColumnList as $tableColumn) {
                $tableColumns[] = $tableColumn->Field;
            }
        }

        if (!empty($modelColumns)) {
            foreach ($modelColumns as $column => $type) {
                if (in_array($column, $tableColumns) === false) {
                    $model->setQuery('ALTER TABLE ' . $model->getTable() . ' ADD ' . $column . ' VARCHAR(255) NOT NULL');
                    $model->runQuery();
                }
            }
        }
    }
}
