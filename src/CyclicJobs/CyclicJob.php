<?php

namespace App\CyclicJobs;

use Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob;

class CyclicJob extends AbstractCyclicJob
{
    protected float $timeSleep = 10;

    public function runJob(): void
    {
        $date = date('Y-m-d H:i:s');
        file_put_contents('Job.log', "Cyclic Job date now {$date} \n",FILE_APPEND);
    }
}