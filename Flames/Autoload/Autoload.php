<?php
declare(strict_types=1);


namespace Flames\Autoload;

use Flames\Autoload\Trait\LoadEvent;
use Flames\Autoload\Trait\Requires;
use Flames\Framework\PersistentData;
use Flames\Ready\ResetData;

require_once __DIR__ . '/Trait/LoadEvent.php';
require_once __DIR__ . '/Trait/Requires.php';

class Autoload
{
    use LoadEvent;
    use Requires;

    public static function run(): void
    {
        \spl_autoload_register(function ($name) {
            self::load($name);
        });
    }

    public static function load(string $name): void
    {
        $legacyOrmClasses = [
            'Flames\\Model' => ROOT_PATH . 'vendor/flamesphp/orm/Flames/Orm/Model.php',
            'Flames\\Repository' => ROOT_PATH . 'vendor/flamesphp/orm/Flames/Orm/Repository.php',
            'Flames\\Database' => ROOT_PATH . 'vendor/flamesphp/orm/Flames/Orm/Database.php',
        ];

        if (isset($legacyOrmClasses[$name])) {
            self::requires($legacyOrmClasses[$name]);
            return;
        }

        /* Flames\{Package}\* → vendor/flamesphp/{package}/Flames/{Package}/... (e.g. flamesphp/microservice) */
        if (str_starts_with($name, 'Flames\\')) {
            $parts   = explode('\\', $name);
            $package = strtolower($parts[1]);
            $path    = ROOT_PATH . 'vendor/flamesphp/' . $package . '/' . str_replace('\\', '/', $name) . '.php';
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }

        elseif (str_starts_with($name, 'App\\')) {
            if ($name === \App\App::class) {
                self::requires(ROOT_PATH . 'vendor/flamesphp/framework/App.php');
            } else {
                $path = (APP_PATH . substr(str_replace('\\', '/', $name), 4) . '.php');
                self::requires($path);
            }

            if (method_exists($name, '__constructStatic') === true) {
                $parts = explode('\\', $name);
                if (($parts[1] ?? null) !== 'Client') {
                    ($name . '::__constructStatic')();
                }
            }

            return;
        }

        elseif (str_starts_with($name, 'Microservice\\')) {
            $path = (ROOT_PATH . str_replace('\\', '/', $name) . '.php');
            self::requires($path);

            if (method_exists($name, '__constructStatic') === true) {
                $parts = explode('\\', $name);
                if (($parts[2] ?? null) !== 'Client') {
                    ($name . '::__constructStatic')();
                }
            }

            return;
        }

        else {
            self::loadEvent($name);
        }
    }
}
