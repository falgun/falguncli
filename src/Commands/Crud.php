<?php
namespace Falgun\FalgunCLI\Commands;

use Exception;
use Inflect\Inflect;
use Falgun\Console\AbstractCommand;
use Falgun\Console\Input\Definition\ArgumentDefinition;

class Crud extends AbstractCommand
{

    const SIGNATURE = 'crud';

    protected $arguments;
    protected $tableColumns;

    public function configure()
    {
        $this->addArgument(new ArgumentDefinition('table', ArgumentDefinition::VALUE_REQUIRED));
    }

    public function execute(array $input)
    {
        $this->arguments = $input;

        $this->createModel();
        $this->createController();
        $this->createViews();
    }

    public function createModel()
    {
        $modelObj = new Model();
        $modelObj->execute($this->arguments);

        $this->tableColumns = $modelObj->returnColumns();

        if (empty($this->tableColumns)) {
            throw new \Exception('No column found in this column !');
        }
    }

    public function createController()
    {
        $modules = $this->getControllerName();
        $module = Inflect::singularize($modules);

        echo 'Creating new Controller : ' . ucfirst($this->getControllerFileName()) . 'Controller' . PHP_EOL . PHP_EOL;

        usleep(50000);

        $validatorFields = '';
        $modelFields = '';
        foreach ($this->tableColumns as $column) {
            $this->columnNames[] = $column->Field;

            if ($column->Field !== 'id') {
                $validatorFields .= str_repeat(' ', 12) . '$validator->select(\'' . $column->Field . '\')->required();' . PHP_EOL;

                $modelFields .= str_repeat(' ', 16) . '$this->model->' . $column->Field . ' = $postData->' . $column->Field . ';' . PHP_EOL;

                $this->formFields[] = '		    <div class="form-group">
				<label for="' . $column->Field . '" class="col-sm-2 control-label">' . ucfirst($column->Field) . '</label>
				<div class="col-sm-10 col-xs-12 validationHolder">
				    <input type="text" name="' . $column->Field . '" class="form-control input-sm" id="' . $column->Field . '" placeholder="' . ucfirst($column->Field) . '" value="<?php echo $this->objval("' . $module . '", "' . $column->Field . '"); ?>">
				</div>
			    </div>';
            }
        }


        $replaceArray = array(
            '##ControllerName##' => ucfirst($this->getControllerFileName()),
            '##ModelName##' => ucfirst($module),
            '##Module##' => $module,
            '##Modules##' => $modules,
            '##ValidatorFieldPlaceholder##' => trim($validatorFields),
            '##ModelFieldPlaceholder##' => trim($modelFields)
        );

        $template = $this->getTemplateStub('crudTemplate');

        $template = str_replace(array_keys($replaceArray), array_values($replaceArray), $template);

        file_put_contents(APP_DIR . DS . 'Controllers' . DS . ucfirst($this->getControllerFileName()) . 'Controller.php', $template);
    }

    public function getControllerName()
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $this->arguments['table']))));
    }

    public function getControllerFileName()
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', ($this->arguments['-name'] ?? Inflect::singularize($this->arguments['table'])))))) . 'Manage';
    }

    public function createViews()
    {

        $modules = $this->getControllerName();
        $module = Inflect::singularize($modules);
        $columns = $this->columnNames;

        echo 'Creating new View for : ' . ucfirst($this->getControllerFileName()) . PHP_EOL . PHP_EOL;
        usleep(50000);

        // Show List page layout
        $listPage = str_replace('##MODULES##', $modules, $this->getTemplateStub('listContent'));
        $listPage = str_replace('##MODULE##', $module, $listPage);
        $listPage = str_replace('##UC_MODULES##', ucwords($modules), $listPage);
        $listPage = str_replace('##UC_MODULE##', ucwords($module), $listPage);
        $listPage = str_replace('##COLUMNS##', implode('</th>' . PHP_EOL . '<th class="text-center">', array_map(function($v) {
                    return ucfirst($v);
                }, $columns)), $listPage);

        $contentRow = '';
        foreach ($columns as $col) {
            if ($col == 'status') {
                $contentRow .= '<td class="text-center"><?php echo $this->statusIcon($' . $module . '->' . $col . '); ?></td>
		      ';
            } else {
                $contentRow .= '<td class="text-center"><?php echo $' . $module . '->' . $col . '; ?></td>
		      ';
            }
        }
        $listPage = str_replace('##MODULE_ROW##', $contentRow, $listPage);


        //Form Page Layout
        $formFields = implode('
            ', $this->formFields);
        $editForm = str_replace('##MODULE##', $module, $this->getTemplateStub('editContent'));
        $editForm = str_replace('##MODULES##', $modules, $editForm);
        $editForm = str_replace('##UC_MODULE##', ucwords($module), $editForm);
        $editForm = str_replace('##FORM_FIELDS##', $formFields, $editForm);


        // Delete page layout
        $deleteForm = str_replace('##MODULE##', $module, $this->getTemplateStub('deleteContent'));

        $viewFolder = APP_DIR . DS . 'Views' . DS . ucfirst($this->getControllerFileName());

        if (is_dir($viewFolder) === false) {
            mkdir($viewFolder, 0755, true);
        }

        file_put_contents($viewFolder . '/list.php', $listPage);
        file_put_contents($viewFolder . '/form.php', $editForm);
        file_put_contents($viewFolder . '/delete.php', $deleteForm);
    }

    public function getTemplateStub(string $name)
    {
        return file_get_contents(dirname(__DIR__) . '/Templates/' . $name . '.html');
    }
}
