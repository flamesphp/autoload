<?php
declare(strict_types=1);


namespace Flames\Autoload;

use Flames\Ready\Functions;

/**
 * @internal
 */
class C
{
    protected const ASYNC_SERVICE_VERSION = 'a3f8c2e1b7d94056f2a1c3e8b5d07f4a9c2e6b81';

//    public static function valid(string $package): bool
//    {
//        if ($package === 'async') {
////            self::validAsync();
//        }
//    }

    public static function validAsync()
    {
        return Functions::once(function() {
            if (function_exists('__flames_c_async_service_version__') === false) {
                return false;
            }
            return (__flames_c_async_service_version__() === self::ASYNC_SERVICE_VERSION);
        });
    }
}