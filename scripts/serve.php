<?php

// Prepare the environment before Artisan starts its HTTP child process.
$root = dirname(__DIR__);
$temporaryDirectory = $root.'/storage/app/tmp';

if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0777, true) && ! is_dir($temporaryDirectory)) {
    throw new RuntimeException('Could not create the development temporary directory.');
}

$temporaryDirectory = realpath($temporaryDirectory);

if ($temporaryDirectory === false || ! is_writable($temporaryDirectory)) {
    throw new RuntimeException('The development temporary directory must be writable.');
}

foreach (['TEMP', 'TMP'] as $variable) {
    putenv($variable.'='.$temporaryDirectory);
    $_ENV[$variable] = $_SERVER[$variable] = $temporaryDirectory;
}

chdir($root);
$argv = array_merge([$root.'/artisan', 'serve', '--no-reload'], array_slice($argv, 1));
$_SERVER['argv'] = $argv;
$_SERVER['argc'] = count($argv);

require $root.'/artisan';
