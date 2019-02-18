<?php
namespace Falgun\FalgunCLI\Commands;

use Exception;
use Inflect\Inflect;
use Falgun\Console\AbstractCommand;
use Falgun\Console\Input\Definition\ArgumentDefinition;

class Model extends AbstractCommand
{
    const SIGNATURE = 'model';

    protected $input;
    protected $table;
    protected $modelName;
    protected $dbConf;
    protected $db;
    protected $stmt;
    protected $columns;
    protected $modelString;

    public function configure()
    {
        $this->addArgument(new ArgumentDefinition('table', ArgumentDefinition::VALUE_REQUIRED));
    }

    public function execute(array $input)
    {
        $this->input = $input;
        $this->table = $input['table'];

        $this->create();
    }

    public function create()
    {
        echo 'Loading DB Info .....' . PHP_EOL . PHP_EOL;
        $this->loadDbConf();
        $this->loadDB();

        if ($this->table === 'all') {
            foreach ($this->fetchTables() as $table) {
                $this->generate($table);
            }
        } elseif (is_string($this->table)) {
            $this->generate($this->table);
        } else {
            throw new Exception('Invalid Model Name !');
        }
    }

    protected function generate($table)
    {
        $this->table = $table;
        $this->modelName = $this->prepareModelName($this->table);

        echo ( ($this->modelExists()) ? 'Updating Model' : 'Creating new Model') . ' : ' . $this->modelName . PHP_EOL . PHP_EOL;

        $this->fetchColumns();
        $this->prepareModelString();
        $this->writeModel();

        return true;
    }

    protected function prepareModelName(string $name)
    {
        return ucfirst(Inflect::singularize(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))))) . 'Model';
    }

    protected function loadDbConf()
    {
        if (isset($this->input['-db'])) {
            $this->dbConf = $this->input['-db'];
        } else {
            $this->dbConf = 'db.config.php';
        }
    }

    protected function loadDB()
    {
        $dbConfPath = CONFIG_DIR . DS . $this->dbConf;
        $conf = require ($dbConfPath);

        $this->db = new \mysqli($conf['host'], $conf['user'], $conf['password'], $conf['db']);

        if (!empty($this->db->connect_errno)) {
            throw new Exception($this->db->connect_error);
        }

        if (isset($conf['characterSet'])) {
            $this->db->set_charset($conf['characterSet']);
        }
    }

    protected function fetchTables()
    {
        $this->stmt = $this->db->prepare('SHOW TABLES');

        if ($this->stmt !== false) {
            $this->stmt->execute();
            $result = $this->stmt->get_result();
            if (!empty($result)) {
                $tables = [];
                while ($row = $result->fetch_object()) {
                    $tables[] = $row->Tables_in_mail_responder;
                }

                return $tables;
            }
            throw new Exception('Table not found !');
        }
    }

    protected function fetchColumns()
    {
        $this->stmt = $this->db->prepare('DESCRIBE ' . $this->table);

        if ($this->stmt !== false) {
            $this->stmt->execute();
            $result = $this->stmt->get_result();

            if (!empty($result)) {
                while ($row = $result->fetch_object()) {
                    $this->columns[] = $row;
                }

                if (empty($this->columns)) {
                    throw new \Exception('No column found in this column !');
                }
                return true;
            }
        }
        throw new Exception('Table not found !');
    }

    public function returnColumns()
    {
        return $this->columns;
    }

    protected function prepareModelString()
    {

        $this->modelString = '<?php
namespace App\Models;

use Falgun\Database\Stacky\Model;

class ' . $this->modelName . ' extends Model 
{
     ';
        $this->modelString .= PHP_EOL . "\t" . '/**';
        foreach ($this->columns as $column) {
            $name = $column->Field;
            $type = $column->Type;
            if (strpos($type, 'int') !== false) {
                $type = 'int';
            } elseif (strpos($type, 'decimal') !== false) {
                $type = 'double';
            } else {
                $type = 'string';
            }

            // magic property

            $this->modelString .= PHP_EOL . "\t" . ' * @property ' . $type . ' $' . $name;

            // table columns
            $typeStrings[] = "'$name' => '$type'";
        }
        $this->modelString .= PHP_EOL . "\t" . ' */' . PHP_EOL;


        $this->modelString .= '
    protected $table = \'' . $this->table . '\';
    protected $config = \'' . $this->dbConf . '\';
    protected $columns = [
        ' . implode(',
        ', $typeStrings) . '
    ];

}';
    }

    protected function writeModel()
    {
        $modelPath = APP_DIR . DS . 'Models' . DS . $this->modelName . '.php';

        if (is_dir(dirname($modelPath)) === false) {
            mkdir(dirname($modelPath), 0755, true);
        }

        if (file_exists($modelPath) === false) {
            $this->overwriteModel($modelPath);
        } else {
            return $this->modifyModel($modelPath);
        }
    }

    protected function overwriteModel($modelPath)
    {
        return file_put_contents($modelPath, $this->modelString, LOCK_EX);
    }

    protected function modifyModel($modelPath)
    {
        $preModelString = file_get_contents($modelPath);
        $postBoot = strstr($preModelString, ');');
        $newBoot = strstr($this->modelString, ');', true);

        if ($postBoot === false) {
            return $this->overwriteModel($modelPath);
        }

        return file_put_contents($modelPath, $newBoot . $postBoot, LOCK_EX);
    }

    protected function modelExists()
    {
        return file_exists(APP_DIR . DS . 'Models' . DS . $this->modelName . '.php');
    }
}
