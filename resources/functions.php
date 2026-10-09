<?php
declare(strict_types=1);


use Flames\Collection\Arr;
use Flames\Env\Env;
use Flames\Async\Async;
use Flames\Collection\Functions;
use Flames\Required;

function Arr(mixed $value = null): Arr {
    if ($value instanceof Arr) {
        return $value;
    }

    return new \Flames\Collection\Arr($value);
}

function async(\Closure $delegate, bool $parallel = true): \Flames\Async\Container {
    return \Flames\Async\Async::async($delegate, $parallel);
}

function await(\Flames\Async\Container ...$tasks): mixed {
    return \Flames\Async\Async::await(...$tasks);
}

define('async', async(...));
define('await', await(...));

function asleep(float $seconds): void {
    // When the C extension is loaded, sleep() and usleep() are patched to be
    // non-blocking inside managed coroutines, so usleep() does the right thing.
    // In PHP-pure mode this is a regular blocking usleep().
    usleep((int)($seconds * 1_000_000));
}

function once(Closure $delegate): mixed {
    return Functions::once($delegate);
}

function response(mixed $data = null, int $statusCode = 200, ?string $contentType = null): \Flames\Framework\Controller\Response {
    return new \Flames\Framework\Controller\Response($data, $statusCode, $contentType);
}

if (Env::get('DUMP_ENABLED')) {
    \Flames\Dumpper\Dump::$editor = 'phpstorm';
    function dump() : void
    {
        ob_start();
        \Flames\Dumpper\Dump::$aliases[] = __FUNCTION__;

        $params = func_get_args();
        call_user_func_array(\Flames\Dumpper\Dump::dump(...), $params);

        $buffer = ob_get_contents();
        ob_end_clean();

        if ($buffer === '' && $params !== []) {
            echo '<pre>';
            echo htmlspecialchars(
                json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
                    ?: 'dump produced no output',
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            );
            echo '</pre>';

            return;
        }

        if ($buffer === '') {
            return;
        }

        $buffer = str_replace('>Flames\Collection\Arr</a>', '>Arr</a>', $buffer);
        $buffer = str_replace('>Flames\Collection\ArrImmutable</a>', '>ArrImmutable</a>', $buffer);
        echo $buffer;
//    @flush();
//    @ob_flush();
    }
}
else {
    function dump() : void
    {
        var_dump(func_get_args());
    }
}
