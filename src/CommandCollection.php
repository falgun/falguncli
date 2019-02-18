<?php
namespace Falgun\FalgunCLI;

use Falgun\FalgunCLI\Commands\Controller;
use Falgun\FalgunCLI\Commands\Model;
use Falgun\FalgunCLI\Commands\Crud;
use Falgun\FalgunCLI\Commands\DB;
use Falgun\FalgunCLI\Commands\Install;
use Falgun\Console\CommandCollection as BaseCollection;

class CommandCollection extends BaseCollection
{

    public function __construct()
    {
        parent::__construct([
            Controller::SIGNATURE => Controller::class,
            Model::SIGNATURE => Model::class,
            Crud::SIGNATURE => Crud::class,
            DB::SIGNATURE => DB::class,
            Install::SIGNATURE => Install::class
        ]);
    }
}
