<?php

namespace App\Controllers;

use Sidalex\SwooleApp\Classes\Controllers\AbstractController;
use Sidalex\SwooleApp\Classes\Controllers\Route;

#[Route(uri: "/", method: 'GET')]
class Controller extends AbstractController
{

    public function execute(): \Swoole\Http\Response
    {
        $this->response->end('Hello World');
        return $this->response;
    }
}