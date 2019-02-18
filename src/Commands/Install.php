<?php
namespace Falgun\FalgunCLI\Commands;

use Exception;
use Inflect\Inflect;
use Falgun\Console\AbstractCommand;
use Falgun\Console\Input\Definition\ArgumentDefinition;

class Install extends AbstractCommand
{

    const SIGNATURE = 'install';
    
    public function configure()
    {
        $ignoredDIR = ROOT_DIR . DS . 'ignored';

        if (is_dir($ignoredDIR) === false) {
            throw new \Exception('You should have an "ignored" folder containing base files !');
        }

        $shouldBeCopied = [
            'config.php' => 'config',
            'db.config.php' => 'config',
            'mail.config.php' => 'config',
            '.htaccess' => 'public',
            'var/settings/site.php' => 'var/settings/'
        ];

        foreach ($shouldBeCopied as $src => $dst) {
            $srcPath = $ignoredDIR . DS . $src;
            $dstPath = ROOT_DIR . DS . $dst . DS . basename($src);

            if (file_exists($srcPath) === false) {
                continue;
            }

            if (is_dir(dirname($dstPath)) === false) {
                mkdir(dirname($dstPath), 0755, true);
            }

            copy($srcPath, $dstPath);
        }
    }

    public function execute(array $input)
    {
        $config = [];
        $dbConfig = [];

        echo PHP_EOL;
        echo 'Your Site name?' . PHP_EOL;
        $config['SITE'] = trim(fgets(STDIN));
        echo PHP_EOL;

        $suggestedLink = 'http://localhost/' . basename(ROOT_DIR) . '/public/';
        echo 'Your Site URL? (Suggestion: ' . $suggestedLink . ')' . PHP_EOL;
        $config['BASE_URL'] = trim(fgets(STDIN));
        echo PHP_EOL;

        echo 'Your DB username?' . PHP_EOL;
        $dbConfig['user'] = trim(fgets(STDIN));
        echo PHP_EOL;

        echo 'Your DB password?' . PHP_EOL;
        $dbConfig['password'] = trim(fgets(STDIN));
        echo PHP_EOL;

        echo 'Your DB name?' . PHP_EOL;
        $dbConfig['db'] = trim(fgets(STDIN));
        echo PHP_EOL;

        $configPHP = file_get_contents(ROOT_DIR . DS . 'ignored' . DS . 'config.php');
        foreach ($config as $key => $value) {
            $configPHP = preg_replace('#define\(\'' . $key . '\', \'(.*?)\'\);#', 'define(\'' . $key . '\', \'' . $value . '\');', $configPHP);
        }
        file_put_contents(CONFIG_DIR . DS . 'config.php', $configPHP);

        $dbConfigPHP = file_get_contents(ROOT_DIR . DS . 'ignored' . DS . 'db.config.php');
        foreach ($dbConfig as $key => $value) {
            $dbConfigPHP = preg_replace('#\'' . $key . '\' => \'(.*?)\'#', '\'' . $key . '\' => \'' . $value . '\'', $dbConfigPHP);
        }

        file_put_contents(CONFIG_DIR . DS . 'db.config.php', $dbConfigPHP);


//        $db = new DB();
//
//        if (file_exists(ROOT_DIR . DS . 'db' . DS . 'database.sql')) {
//            $db->import();
//        } else {
//            $db->connect();
//
//            echo 'Database Connection is OK' . PHP_EOL;
//        }
    }
}
