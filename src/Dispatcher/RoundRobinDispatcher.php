<?php

declare(strict_types=1);

namespace App\Dispatcher;

use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Dispatcher\DispatcherInterface;

class RoundRobinDispatcher implements DispatcherInterface
{
    protected int $currentWorker = 0;

    public function getDispatchFunction(Application $app): callable
    {
        return function ($server, $fd, $type, $data): int {
            $workerNum = $server->setting['worker_num'];
            $workerId = $this->currentWorker % $workerNum;
            $this->currentWorker++;

            return $workerId;
        };
    }
}