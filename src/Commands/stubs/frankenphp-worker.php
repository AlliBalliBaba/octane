<?php

// set a default for the app base path if it is missing
$_SERVER['APP_BASE_PATH'] = $_SERVER['APP_BASE_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? __DIR__ . '/..';
$_SERVER['APP_PUBLIC_PATH'] = $_SERVER['APP_PUBLIC_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? __DIR__;

require __DIR__.'/../vendor/laravel/octane/bin/frankenphp-worker.php';
