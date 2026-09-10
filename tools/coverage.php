<?php

declare(strict_types=1);

$report = simplexml_load_file(__DIR__.'/../build/clover.xml');

if ($report === false) {
    fwrite(STDERR, "Cannot read coverage report.\n");
    exit(1);
}

$metrics = $report->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements === 0 || $covered !== $statements) {
    fwrite(STDERR, "100% line coverage required; covered {$covered}/{$statements} statements.\n");
    exit(1);
}

fwrite(STDOUT, "100% line coverage verified: {$covered}/{$statements}.\n");
