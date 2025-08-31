<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$args = $_SERVER['argv'];

$application = new Application();

// ... register commands

$application->add(new \A8nx\CLI\Commands\Run());
$application->run();
