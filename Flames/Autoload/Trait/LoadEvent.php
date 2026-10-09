<?php
declare(strict_types=1);


namespace Flames\Autoload\Trait;

use Flames\Event;

$path = (APP_PATH . 'Server/Event/Load.php');
if (file_exists($path) === true) {
    /**
     * @internal
     */
    trait LoadEvent
    {
        protected static function loadEvent(string $name)
        {
            Event::dispatch('Load', 'onLoad', $name);
        }
    }

    return;
} else {
    /**
     * @internal
     */
    trait LoadEvent
    {
        protected static function loadEvent(string $name) {}
    }
}

