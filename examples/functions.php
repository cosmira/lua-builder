<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Cosmira\Lua\Lua;
use Cosmira\Lua\Script;

$score = Lua::var('score');

echo Lua::script()
    ->localFunction('grade', ['score'], function (Script $lua) use ($score): void {
        $lua->if(
            $score->greaterThanOrEqual(80),
            fn (Script $lua) => $lua->return('passed'),
        );
        $lua->return('try again');
    })
    ->call('print', Lua::call('grade', 90));
