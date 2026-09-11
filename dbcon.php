<?php

$envFile = __DIR__ . "/env.php";

if (!file_exists($envFile)) {
    die("Configuration file env.php was not found.");
}

require_once $envFile;

$requiredVariables = [
    "host",
    "tableprefix",
    "databasename",
    "dbuser",
    "dbpassword",
    "adminUsername",
    "adminPassword"
];

foreach ($requiredVariables as $variable) {
    if (!isset($$variable)) {
        die("Missing configuration variable: " . $variable);
    }
}