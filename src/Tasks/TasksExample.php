<?php

namespace App\Tasks;

use Sidalex\SwooleApp\Classes\Tasks\Executors\AbstractTaskExecutor;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;

class TasksExample extends AbstractTaskExecutor
{

    public function execute(): TaskResulted
    {
        //incoming data
        print "task data incoming: ".var_export($this->dataStorage, true);
        sleep(3); // imitate long blocking task
        return new TaskResulted(['success' => 'ok']);
    }
}