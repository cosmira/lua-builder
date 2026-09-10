<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Cosmira\Lua\Lua;

echo Lua::script()
    ->comment('Generated configuration. Edit the PHP source.')
    ->return([
        'name'    => 'Moonlight',
        'enabled' => true,
        'servers' => ['127.0.0.1', '192.168.1.1'],
        'timeout' => 30,
    ]);
