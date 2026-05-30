<?php

use Flames\Collection\Arr;
use Flames\Framework\Shutdown;
use Flames\Async\Async;
use Flames\Collection\Functions;

function __shutdown__() {
    Shutdown::onShutdown();
}
register_shutdown_function('__shutdown__');

function Arr(mixed $value = null): Arr {
    if ($value instanceof Arr) {
        return $value;
    }

    return new \Flames\Collection\Arr($value);
}

function async(\Closure $delegate): mixed {
    return \Flames\Async\Async::async($delegate);
}

function await(\Flames\Async\Service\Task|\Flames\Async\Async\Service\Task ...$tasks): mixed {
    return \Flames\Async\Async::await(...$tasks);
}

function once(Closure $delegate): mixed {
    return Functions::once($delegate);
}

function dump() : void
{
    ob_start();
    Dump::$aliases[] = __FUNCTION__;

    $params = func_get_args();
    call_user_func_array(['Flames\Dumpper\Dump', 'dump'], $params);

    $buffer = ob_get_contents();
    ob_end_clean();

    if (\Flames\Connection\HttpClient::isHttpClient() === true) {
        $buffer = str_replace('class="_dumpper-parent"', 'class="_dumpper-parent _dumpper-show"', $buffer);
    }

    $buffer = str_replace('>Flames\Collection\Arr</a>', '>Arr</a>', $buffer);
    echo $buffer;
//    @flush();
//    @ob_flush();
}