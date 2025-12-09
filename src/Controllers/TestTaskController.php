<?php

namespace App\Controllers;

use App\Middleware\LoggingMiddleware;
use App\Tasks\TasksExample;
use Sidalex\SwooleApp\Classes\Controllers\AbstractController;
use Sidalex\SwooleApp\Classes\Controllers\Route;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Sidalex\SwooleApp\Classes\Tasks\Data\BasicTaskData;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;

#[Route(uri: "/test-task", method: 'POST')]
#[Middleware(LoggingMiddleware::class,[])]
class TestTaskController extends AbstractController
{

    public function execute(): \Swoole\Http\Response
    {
        $body = $this->request->getContent();
        if(!self::isJson($body)) {
            $this->response->status(400);
            $this->response->end("Bad request json is not valid");
            return $this->response;
        }
        $task = new BasicTaskData(TasksExample::class, ['data' => $body]);
        /**
         * @var TaskResulted $tesult
         */
        $tesult = $this->server->taskwait($task,10);
        if($tesult->getResult()){
            $this->response->end("Task executed sucsess");
        }
        else{
            $this->response->status(500);
            $this->response->end("Task executed failed");
        }
        return $this->response;
    }
    static function isJson(string $string): bool {
        // Проверяем, что строка не пустая
        if (empty(trim($string))) {
            return false;
        }

        // Декодируем JSON
        json_decode($string);

        // Проверяем, была ли ошибка при декодировании
        return json_last_error() === JSON_ERROR_NONE;
    }
}