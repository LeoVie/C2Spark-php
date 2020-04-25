<?php

use LeoVie\C2Spark\Transpiler;

require_once __DIR__ . '/vendor/autoload.php';

if (!array_key_exists(1, $argv)) {
    print("Please enter a c file name!\n");
    die;
}

shell_exec('docker build --tag c2spark:latest .');
$json = shell_exec('docker run c2spark c-to-json.py "' . $argv[1] . '"');

$transpiler = new Transpiler();
$result = $transpiler->transpile(Safe\json_decode($json, true), '');

print($result['value']);