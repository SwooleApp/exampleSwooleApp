<?php

declare(strict_types=1);

require_once "./vendor/autoload.php";

$config = json_decode(file_get_contents('./config.json'));

$app = new \Sidalex\SwooleApp\Application($config);
$server = $app->createServer();
$server->start();
