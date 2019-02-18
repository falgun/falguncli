<?php
namespace Falgun\FalgunCLI\Commands;

use Exception;
use Inflect\Inflect;
use Falgun\Console\AbstractCommand;
use Falgun\Console\Input\Definition\ArgumentDefinition;

class Controller extends AbstractCommand
{

    const SIGNATURE = 'controller';

    protected $moduleName;
    protected $controllerName;
    protected $controllerString;

    public function configure()
    {
        $this->addArgument(new ArgumentDefinition('controller', ArgumentDefinition::VALUE_REQUIRED));
    }

    public function execute(array $input)
    {
        $this->moduleName = $this->prepareControllerName($input['controller']);
        $this->controllerName = $this->moduleName . 'Controller';

        $this->create();
    }

    protected function prepareControllerName($string)
    {
        return ucfirst(Inflect::singularize(str_replace(' ', null, ucwords(str_replace('_', ' ', $string)))));
    }

    public function create()
    {
        echo 'Creating new Controller : ' . $this->controllerName . PHP_EOL . PHP_EOL;
        $this->prepareControllerString();
        $this->writeController();
    }

    protected function prepareControllerString()
    {
        $this->controllerString = <<<PHP
<?php
namespace App\\Controllers;

use App\\Models\\' . $this->moduleName . 'Model;
use Falgun\\Controller\\AbstractController;

class ' . $this->controllerName . ' extends AbstractController
{

    /**
     *  @var ' . $this->moduleName . 'Model
     */
    protected $model;

    public function __construct(' . $this->moduleName . 'Model $model)
    {
        $this->model = $model;
    }
}
PHP;
    }

    protected function writeController()
    {
        $ControllerPath = APP_DIR . DS . 'Controllers' . DS . $this->controllerName . '.php';

        if (is_dir(dirname($ControllerPath)) === false) {
            mkdir(dirname($ControllerPath), 0755, true);
        }

        return file_put_contents($ControllerPath, $this->controllerString, LOCK_EX);
    }
}
