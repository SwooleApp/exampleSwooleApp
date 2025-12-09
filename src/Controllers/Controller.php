<?php

namespace App\Controllers;

use App\Middleware\LoggingMiddleware;
use Sidalex\SwooleApp\Classes\Controllers\AbstractController;
use Sidalex\SwooleApp\Classes\Controllers\Route;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;

#[Route(uri: "/", method: 'GET')]
#[Middleware(LoggingMiddleware::class,[])]
class Controller extends AbstractController
{

    public function execute(): \Swoole\Http\Response
    {
        $this->response->end('Hello World');
        return $this->response;
    }
}