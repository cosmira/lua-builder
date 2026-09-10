<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Cosmira\Lua\Lua;
use Cosmira\Lua\Script;

$rules = [
    [
        'id'      => 'reports/日本語',
        'enabled' => true,
        'from'    => '2026-01-01',
        'until'   => '2026-12-31',
        'filters' => [
            ['attribute' => 'department', 'values' => ['Разработка', '開発']],
            ['attribute' => 'extension', 'values' => ['pdf', 'csv']],
        ],
        'actions' => [
            ['name' => 'tag', 'arguments' => ['Отчёты 📄']],
            ['name' => 'notify', 'arguments' => ['ops@example.test', '確認してください']],
        ],
    ],
    [
        'id'      => 'audit',
        'enabled' => true,
        'from'    => null,
        'until'   => null,
        'filters' => [],
        'actions' => [['name' => 'tag', 'arguments' => ['checked']]],
    ],
    [
        'id'      => 'paused',
        'enabled' => false,
        'from'    => null,
        'until'   => null,
        'filters' => [],
        'actions' => [['name' => 'tag', 'arguments' => ['paused']]],
    ],
];

$handlers = Lua::var('handlers');
$script = Lua::script()
    ->local('handlers', [])
    ->function('get_config', [], fn (Script $lua) => $lua->return([
        'enabled'    => true,
        'recipients' => ['ops@example.test'],
    ]));

foreach ($rules as $rule) {
    if (! $rule['enabled']) {
        continue;
    }

    $script->set($handlers->index($rule['id']), Lua::function(['engine'], function (Script $lua) use ($rule): void {
        $matches = Lua::value(true);
        foreach ($rule['filters'] as $filter) {
            $matches = $matches->and(Lua::call('engine.matches', $filter));
        }

        $lua->if($matches->not(), fn (Script $lua) => $lua->return(false));

        foreach ($rule['actions'] as $action) {
            $lua->call('engine.'.$action['name'], ...$action['arguments']);
        }

        $lua->return(true);
    }));
}

$script->function('process_rules', ['context', 'engine'], function (Script $lua) use ($rules, $handlers): void {
    $date = Lua::var('context.date');
    foreach ($rules as $rule) {
        if (! $rule['enabled']) {
            continue;
        }

        $active = Lua::value(true);
        if ($rule['from'] !== null) {
            $active = $active->and($date->greaterThanOrEqual($rule['from']));
        }
        if ($rule['until'] !== null) {
            $active = $active->and($date->lessThanOrEqual($rule['until']));
        }

        $lua->if($active, fn (Script $lua) => $lua->call($handlers->index($rule['id']), Lua::var('engine')));
    }
});

echo $script;
