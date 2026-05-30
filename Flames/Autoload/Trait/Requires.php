<?php

namespace Flames\Autoload\Trait;

//if (true) {
//    /**
//     * @internal
//     */
//    trait Requires
//    {
//        protected function requires(string $path)
//        {
//            require($path);
//        }
//    }
//} else {
    /**
     * @internal
     */
    trait Requires
    {
        protected function requires(string $path)
        {
            require($path);
        }
    }
//}

