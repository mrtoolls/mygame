<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

$root = dirname(__DIR__);
$compiler = new BladeCompiler(new Filesystem(), $root . '/storage/framework/views');
$views = [
    'resources/views/admin/table_games/index.blade.php',
    'resources/views/admin/table_games/pending.blade.php',
    'resources/views/templates/nocturne/sections/live_table_games.blade.php',
    'resources/views/templates/nocturne/games.blade.php',
    'resources/views/templates/nocturne/home.blade.php',
    'resources/views/templates/nocturne/user/table_games/index.blade.php',
    'resources/views/templates/nocturne/user/table_games/pending.blade.php',
    'resources/views/templates/nocturne/user/table_games/show.blade.php',
];

foreach ($views as $view) {
    $compiled = $compiler->compileString(file_get_contents($root . '/' . $view));
    $output = $root . '/tests/compiled_' . preg_replace('/[^a-z0-9]+/i', '_', $view) . '.php';
    file_put_contents($output, $compiled);
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($output), $lines, $status);
    @unlink($output);
    if ($status !== 0) throw new RuntimeException($view . " did not compile: " . implode("\n", $lines));
}

fwrite(STDOUT, "All table-game Blade views compiled and passed PHP syntax checks.\n");
