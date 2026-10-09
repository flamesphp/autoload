<?php

declare(strict_types=1);

namespace Flames\Autoload\Trait;

use Flames\Env\Env;
use Flames\Subset\Stream\Wrapper as SubsetWrapper;

trait Requires
{
    private static bool $subsetRegistered = false;

    protected static function requires(string $path): void
    {
        if (self::shouldUseSubset($path)) {
            self::registerSubset();
            require 'flames://' . $path;
            return;
        }

        require $path;
    }

    private static function shouldUseSubset(string $path): bool
    {
        if (
            ! defined('APP_PATH')
            || ! class_exists(\Flames\Ready\ResetData::class, false)
            || ! isset(\Flames\Ready\ResetData::$data['.env'])
            || ! class_exists(Env::class, false)
        ) {
            return false;
        }

        if (Env::get('SUBSET') !== true) {
            return false;
        }

        $realPath = realpath($path);
        $appPath = realpath(APP_PATH);

        if ($realPath !== false && $appPath !== false) {
            return str_starts_with($realPath, $appPath);
        }

        return str_starts_with($path, APP_PATH);
    }

    private static function registerSubset(): void
    {
        if (self::$subsetRegistered) {
            return;
        }

        SubsetWrapper::register();
        self::$subsetRegistered = true;
    }
}
