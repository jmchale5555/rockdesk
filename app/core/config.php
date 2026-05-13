<?php

$dbName = getenv('DB_NAME') ?: 'phpmon';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
$appName = getenv('APP_NAME') ?: 'My Sandbox';
$appDesc = getenv('APP_DESC') ?: 'Default edit me';
$debugMode = filter_var(getenv('DEBUG_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN);

define('DBNAME', $dbName);
define('DBHOST', $dbHost);
define('DBPORT', $dbPort);
define('DBUSER', $dbUser);
define('DBPASS', $dbPass);

define('ROOT', rtrim($appUrl, '/'));

define('APP_NAME', $appName);
define('APP_DESC', $appDesc);

define('DEBUG_MODE', $debugMode);
