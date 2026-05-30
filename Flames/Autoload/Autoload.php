<?php

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
        require (FLAMES_PATH . 'autoload/resources/functions.php');

        // Verify if event load exists and register]
        $path = (APP_PATH . 'Server/Event/Load.php');
        if (file_exists($path) === true) {
            self::$event = true;
        }

        \spl_autoload_register(function ($name) {
            self::load($name);
        });
    }

    public static function load(string $name): void
    {
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
            $path = (APP_PATH . substr(str_replace('\\', '/', $name), 4) . '.php');
            self::requires($path);

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
    }
}
