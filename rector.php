<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/tools', __DIR__.'/examples'])
    ->withPreparedSets(deadCode: true, privatization: true, instanceOf: true, earlyReturn: true)
    ->withPhpSets(php82: true);
