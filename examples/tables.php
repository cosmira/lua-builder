<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Cosmira\Lua\Lua;
use Cosmira\Lua\Script;

echo Lua::script()
    ->local('services', ['api', 'worker'])
    ->forEach(
        ['index', 'service'],
        Lua::call('ipairs', Lua::var('services')),
        fn (Script $lua) => $lua->call('print', Lua::var('index'), Lua::var('service')),
    );
