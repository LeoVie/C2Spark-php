<?php

use LeoVie\C2Spark\Transpiler;
use LeoVie\GNATWrapper\GNATProve;

require_once __DIR__ . '/vendor/autoload.php';

if (!array_key_exists(1, $argv)) {
    print("Please enter a c file name!\n");
    die;
}
if (!array_key_exists(2, $argv)) {
    print("Please enter an output directory!\n");
    die;
}
$outputDirectory = $argv[2];
if (array_key_exists(3, $argv) && $argv[3] === '--build') {
    shell_exec('docker build --tag c2spark:latest .');
}

$json = shell_exec('docker run c2spark c-to-json.py "' . $argv[1] . '"');

if ($json === null) {
    die;
}

$transpiler = new Transpiler();
$transpiler->transpile(Safe\json_decode($json, true), '');

$gpr = "project Transpile is\n    for Source_Dirs use (\"src\");\n    for Object_Dir use \"obj\";\nend Transpile;\n";
$adb = $transpiler->getAdbContent();
$ads = $transpiler->getAdsContent();

if (!is_dir($outputDirectory)) {
    mkdir($outputDirectory);
}
if (!is_dir($outputDirectory . '/src')) {
    mkdir($outputDirectory . '/src');
}

Safe\file_put_contents($outputDirectory . '/transpile.gpr', $gpr);
Safe\file_put_contents($outputDirectory . '/src/transpiled.adb', $adb);
Safe\file_put_contents($outputDirectory . '/src/transpiled.ads', $ads);

$sep = DIRECTORY_SEPARATOR;

$gnatProve = GNATProve::create(realpath($outputDirectory) . $sep . 'transpile.gpr');
$gnatProve->level(4)
    ->dontStopAtFirstError()
    ->analyzeSingleFile('transpiled.adb');

print($gnatProve->getCommand() . "\n");

$proveResults = $gnatProve->execute();

print($proveResults);